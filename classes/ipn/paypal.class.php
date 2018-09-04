<?php
/**
*   This file contains the Paypal IPN class, it provides an interface to
*   deal with IPN transactions from Paypal.
*   Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner
*   @package    paypal
*   @version    0.5.7
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal\ipn;

use \Paypal\Cart;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Define failure reasons */
define('PAYPAL_FAILURE_UNKNOWN', 0);
define('PAYPAL_FAILURE_VERIFY', 1);
define('PAYPAL_FAILURE_COMPLETED', 2);
define('PAYPAL_FAILURE_UNIQUE', 3);
define('PAYPAL_FAILURE_EMAIL', 4);
define('PAYPAL_FAILURE_FUNDS', 5);

/**
*   Class to deal with IPN transactions from Paypal.
*
*   @since 0.5.0
*   @package paypal
*/
class paypal extends \Paypal\IPN
{
    /**
    *   Variable to hold unserialized custom data
    *   @var array */
    private $custom = array();


    /**
    *   Constructor.  Set up variables received from Paypal.
    *
    *   @param  array   $A      $_POST'd variables from Paypal
    */
    function __construct($A=array())
    {
        $this->gw_id = 'paypal';
        parent::__construct($A);

        $this->pp_data['txn_id'] = PP_getVar($A, 'txn_id');
        $this->pp_data['payer_email'] = PP_getVar($A, 'payer_email');
        $this->pp_data['payer_name'] = PP_getVar($A, 'first_name') .' '. PP_getVar($A, 'last_name');
        $this->pp_data['pmt_date'] = PP_getVar($A, 'payment_date');
        $this->pp_data['pmt_gross'] = PP_getVar($A, 'mc_gross', 'float');
        $this->pp_data['pmt_tax'] = PP_getVar($A, 'tax', 'float');
        $this->pp_data['gw_desc'] = $this->gw->Description();
        $this->pp_data['gw_name'] = $this->gw->Name();
        $this->pp_data['pmt_status'] = PP_getVar($A, 'payment_status');
        $this->pp_data['currency'] = PP_getVar($A, 'mc_currency');
        $this->pp_data['discount'] = PP_getVar($A, 'discount', 'float');
        if (isset($A['invoice']))
            $this->pp_data['invoice'] = $A['invoice'];
        if (isset($A['parent_txn_id']))
            $this->pp_data['parent_txn_id'] = $A['parent_txn_id'];

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['address_street']) || isset($A['address_city'])) {
            $this->pp_data['shipto'] = array(
                'name'      => PP_getVar($A, 'address_name'),
                'address1'  => PP_getVar($A, 'address_street'),
                'address2'  => '',
                'city'      => PP_getVar($A, 'address_city'),
                'state'     => PP_getVar($A, 'address_state'),
                'country'   => PP_getVar($A, 'address_country'),
                'zip'       => PP_getVar($A, 'address_zip'),
            );
        }

        // Set the custom data into an array.  If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->custom = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->custom) {
                $this->custom = array('uid' => $A['custom']);
            }
        }

        switch ($this->pp_data['pmt_status']) {
        case 'Pending':
            $this->pp_data['status'] = 'pending';
            break;
        case 'Completed':
            $this->pp_data['status'] = 'paid';
            break;
        case 'Refunded':
            $this->pp_data['status'] = 'refunded';
            break;
        }

        switch (PP_getVar($A, 'txn_type')) {
        case 'web_accept':
        case 'send_money':
            $this->pp_data['pmt_shipping'] = PP_getVar($A, 'shipping', 'float');
            $this->pp_data['pmt_handling'] = PP_getVar($A, 'handling', 'float');
            break;
        case 'cart':
            $this->pp_data['pmt_shipping'] = PP_getVar($A, 'mc_shipping', 'float');
            $this->pp_data['pmt_handling'] = PP_getVar($A, 'mc_handling', 'float');
            break;
        }
    }


    /**
    *   Verify the transaction with Paypal
    *   Validate transaction by posting data back to the paypal webserver.
    *   The response from paypal should include 'VERIFIED' on a line by itself.
    *
    *   @uses   paypal::getActionUrl()
    *   @return boolean         true if successfully validated, false otherwise
    */
    private function Verify()
    {
        if ($this->gw->getConfig('test_mode')) {
            return true;
        }

        // Default verification to false
        $verified = false;

        // read the post from PayPal system and add 'cmd'
        $req = 'cmd=_notify-validate';

        // re-encode the transaction variables to be verified
        foreach ($this->ipn_data as $key => $value) {
            $value = urlencode($value);
            $req .= "&$key=$value";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->gw->getPostBackUrl() .
                '/cgi-bin/webscr');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        $PP_response = curl_exec($ch);
        curl_close($ch);
        if (strcmp($PP_response, 'VERIFIED') == 0) $verified = true;
        return $verified;
    }


    /**
    *   Confirms that payment status is complete.
    *   (not 'denied', 'failed', 'pending', etc.)
    *
    *   @param  string  $payment_status     Payment status to verify
    *   @return boolean                     True if complete, False otherwise
    */
    private function isStatusCompleted($payment_status) {
        return ($payment_status == 'Completed');
    }


    /**
    *   Checks if payment status is reversed or refunded.
    *   For example, some sort of cancelation.
    *
    *   @param  string  $payment_status     Payment status to check
    *   @return boolean                     True if reversed or refunded
    */
    private function isStatusReversed($payment_status) {
        return ($payment_status == 'Reversed' || $payment_status == 'Refunded');
    }


    /**
    *   Process an incoming IPN transaction
    *   Do the following:
    *       1. Verify IPN
    *       2. Log IPN
    *       3. Check that transaction is complete
    *       4. Check that transaction is unique
    *       5. Check for valid receiver email address
    *       6. Process IPN
    *
    *   @uses   BaseIPN::AddItem()
    *   @uses   BaseIPN::handleFailure()
    *   @uses   BaseIPN::handlePurchase()
    *   @uses   BaseIPN::isUniqueTxnId()
    *   @uses   BaseIPN::isSufficientFunds()
    *   @uses   BaseIPN::Log()
    *   @uses   Verify()
    *   @uses   isStatusCompleted()
    *   @param  array   $in     POST variables of transaction
    *   @return boolean true if processing valid and completed, false otherwise
    */
    public function Process()
    {
        // If no data has been received, then there's nothing to do.
        if (empty($this->ipn_data))
            return false;

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(PAYPAL_FAILURE_VERIFY,
                            "($logId) Verification failed");
            return false;
        } else {
            $logId = $this->Log(true);
        }

        // Set the custom data field to the exploded value.  This has to
        // be done after Verify() or the Paypal verification will fail.
        $this->pp_data['custom'] = $this->custom;

        switch ($this->ipn_data['txn_type']) {
        case 'web_accept':  //usually buy now
        case 'send_money':  //usually donation/send money
            // Process Buy Now & Send Money

            $item_number = PP_getVar($this->ipn_data, 'item_number');
            $quantity = PP_getVar($this->ipn_data, 'quantity', 'float');
            $fees_paid = PP_getVar($this->ipn_data, 'tax', 'float') +
                        PP_getVar($this->pp_data, 'pmt_shipping', 'float') +
                        PP_getVar($this->pp_data, 'pmt_handling', 'float');

            if (empty($item_number)) {
                $this->handleFailure(NULL, 'Missing Item Number in Buy-now process');
                return false;
            }
            if (empty($quantity)) {
                $quantity = 1;
            }

                $payment_gross = PP_getVar($this->pp_data, 'pmt_gross', 'float') - $fees_paid;
                $unit_price = $payment_gross / $quantity;
                $this->AddItem(array(
                        'item_id'   => $item_number,
                        'quantity'  => $quantity,
                        'price'     => $unit_price,
                        'item_name' => PP_getVar($this->ipn_data, 'item_name', 'string', 'Undefined'),
                        'shipping'  => PP_getVar($this->pp_data, 'pmt_shipping', 'float'),
                        'handling'  => PP_getVar($this->pp_data, 'pmt_handling', 'float'),
                ) );

                $currency = PP_getVar($this->pp_data, 'currency', 'string', 'Unk');
                PAYPAL_debug("Net Settled: $payment_gross $currency");
                $this->handlePurchase();
            }
            break;

        case 'cart':
            // shopping cart
            // Create a cart and read the info from the cart table.
            $this->Order = Cart::getInstance(0, $this->pp_data['invoice']);
            if ($this->Order->isNew) {
                $this->handleFailure(NULL, "Order ID {$this->pp_data['invlice']} not found for cart purchases");
                return false;
            }
            $this->pp_data['pmt_tax'] = (float)$this->Order->getInfo('tax');
            $this->pp_data['pmt_shipping'] = (float)$this->Order->getInfo('shipping');
            $this->pp_data['pmt_handling'] = (float)$this->Order->getInfo('handling');
            /*$fees_paid = $this->pp_data['pmt_tax'] +
                        $this->pp_data['pmt_shipping'] +
                        $this->pp_data['pmt_handling'];*/
            $fees_paid = 0;
            if (empty($this->pp_data['custom']['cart_id'])) {
                $this->handleFailure(NULL, 'Missing Cart ID');
                return false;
            }
            $Cart = $this->Order->Cart();
            if (empty($Cart)) {
                COM_errorLog("Paypal\\paypal_ipn::Process() - Empty Cart for id {$this->pp_data['custom']['cart_id']}");
                return false;
            }

            foreach ($Cart as $item) {
                $item_id = $item->product_id;
                if ($item->options != '') {
                    $item_id .= '|' . $item->options;
                }
                $args = array(
                        'item_id'   => $item_id,
                        'quantity'  => $item->quantity,
                        'price'     => $item->price,
                        'item_name' => $item->getShortDscp(),
                        'shipping'  => $item->shipping,
                        'handling'  => $item->handling,
                        'tax'       => $item->tax,
                        'extras'    => $item->extras,
                    );
                $this->AddItem($args);
            }

            $payment_gross = $this->ipn_data['mc_gross'] - $fees_paid;
            PAYPAL_debug("Received $payment_gross gross payment");
            //$currency = $this->ipn_data['mc_currency'];
            $this->handlePurchase();
            break;

        // other, unknown, unsupported
        default:
            switch ($this->ipn_data['reason_code']) {
            case 'refund':
                $this->handleRefund();
                break;
            default:
                $this->handleFailure(PAYPAL_FAILURE_UNKNOWN,
                        "($logId) Unknown transaction type");
                return false;
                break;
            }
            break;
        }

        return true;
    }   // function Process

}   // class paypal_ipn

?>

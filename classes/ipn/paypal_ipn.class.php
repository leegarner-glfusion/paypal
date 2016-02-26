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

require_once 'BaseIPN.class.php';

/**
*   Class to deal with IPN transactions from Paypal.
*
*   @since 0.5.0
*   @package paypal
*/
class PaypalIPN extends BaseIPN
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

        $this->pp_data['txn_id'] = $A['txn_id'];
        $this->pp_data['payer_email'] = $A['payer_email'];
        $this->pp_data['payer_name'] = $A['first_name'] .' '. $A['last_name'];
        $this->pp_data['pmt_date'] = $A['payment_date'];
        $this->pp_data['pmt_gross'] = (float)$A['mc_gross'];
        $this->pp_data['pmt_tax'] = (float)$A['tax'];
        $this->pp_data['gw_desc'] = $this->gw->Description();
        $this->pp_data['gw_name'] = $this->gw->Name();
        $this->pp_data['pmt_status'] = $A['payment_status'];
        $this->pp_data['currency'] = $A['mc_currency'];
        if (isset($A['invoice']))
            $this->pp_data['invoice'] = $A['invoice'];
        if (isset($A['parent_txn_id']))
            $this->pp_data['parent_txn_id'] = $A['parent_txn_id'];

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['address_street']) || isset($A['address_city'])) {
            $this->pp_data['shipto'] = array(
                'name'      => $A['address_name'],
                'address1'  => $A['address_street'],
                'address2'  => '',
                'city'      => $A['address_city'],
                'state'     => $A['address_state'],
                'country'   => $A['address_country'],
                'zip'       => $A['address_zip'],
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


        switch ($A['txn_type']) {
        case 'web_accept':
        case 'send_money':
            $this->pp_data['pmt_shipping'] = (float)$A['shipping'];
            $this->pp_data['pmt_handling'] = (float)$A['handling'];
            break;
        case 'cart':
            $this->pp_data['pmt_shipping'] = (float)$A['mc_shipping'];
            $this->pp_data['pmt_handling'] = (float)$A['mc_handling'];
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
        global $_CONF,$_PP_CONF;

        if (TESTING) return true;

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
        curl_setopt($ch, CURLOPT_URL, $this->gw->getActionUrl() . 
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

        //if (!$this->isStatusCompleted($this->pp_data['pmt_status'])) {
            // Not logged since this probably isn't an error
            // $this->handleFailure(PAYPAL_FAILURE_COMPLETED, 
            //                  "($logId) Status not complete");
        //    return false;
        //}

        /*if (!$this->isUniqueTxnId($this->pp_data)) {
            $this->handleFailure(PAYPAL_FAILURE_UNIQUE, 
                            "($logId) Non-unique transaction id");
            return false;
        }*/

        switch ($this->ipn_data['txn_type']) {
        case 'web_accept':  //usually buy now
        case 'send_money':  //usually donation/send money
            // Process Buy Now & Send Money
            $fees_paid = $this->ipn_data['tax'] + 
                        $this->pp_data['pmt_shipping'] +
                        $this->pp_data['pmt_handling'];

            if (!empty($this->ipn_data['item_number'])) {

                if (!isset($this->ipn_data['quantity']) ||
                    (float)$this->ipn_data['quantity'] == 0) {
                    $this->ipn_data['quantity'] = 1;
                }

                $payment_gross = $this->pp_data['pmt_gross'] - $fees_paid;
                $unit_price = $payment_gross / $this->ipn_data['quantity'];
                $this->AddItem($this->ipn_data['item_number'],
                        $this->ipn_data['quantity'],
                        $unit_price,
                        $this->ipn_data['item_name'],
                        $this->pp_data['pmt_shipping'],
                        $this->pp_data['pmt_handling']);

                $currency = $this->pp_data['currency'];

                PAYPAL_debug("Net Settled: $payment_gross $currency");
                if ($this->isSufficientFunds()) {
                    $this->handlePurchase();
                } else {
                    $this->handleFailure(PAYPAL_FAILURE_FUNDS, 
                                "($logId) Insufficient funds for purchase");
                    return false;
                }
            }
            break;

        case 'cart':
            // shopping cart
            $fees_paid = $this->pp_data['pmt_tax'] + 
                        $this->pp_data['pmt_shipping'] +
                        $this->pp_data['pmt_handling'];
            USES_paypal_class_cart();
            if (empty($this->pp_data['custom']['cart_id'])) {
                $this->handleFailure(NULL, 'Missing Cart ID');
                return false;
            }
            // Create a cart and read the info from the cart table.
            // Actual items purchased and prices will come from the IPN.
            $ppCart = new ppCart($this->pp_data['custom']['cart_id']);
            $Cart = $ppCart->Cart();

            $items = array();
            for ($i = 1; $i <= $this->ipn_data['num_cart_items']; $i++) {

                // PayPal returns the total price as mc_gross_X, so divide
                // by the quantity to get back to a unit price.
                if (!isset($this->ipn_data["quantity$i"]) ||
                    (float)$this->ipn_data["quantity$i"] == 0) {
                    $this->ipn_data["quantity$i"] = 1;
                }
                $item_gross = $this->ipn_data["mc_gross_$i"];
                if (isset($this->ipn_data["mc_shipping$i"])) {
                    $item_shipping = (float)$this->ipn_data["mc_shipping$i"];
                    $item_gross -= $item_shipping;
                } else {
                    $item_shipping = 0;
                }
                if (isset($this->ipn_data["tax$i"])) {
                    $item_tax = (float)$this->ipn_data["tax$i"];
                    $item_gross -= $item_tax;
                } else {
                    $item_tax = 0;
                }
                if (isset($this->ipn_data["mc_handling$i"])) {
                    $item_handling = (float)$this->ipn_data["mc_handling$i"];
                    $item_gross -= $item_handling;
                } else {
                    $item_handling = 0;
                }
                $unit_price = $item_gross / (float)$this->ipn_data["quantity$i"];
                // Add the item to the array for the order creation.
                // IPN item numbers are indexes into the cart, so get the
                // actual product ID from the cart
                $this->AddItem(
                        //$this->ipn_data["item_number$i"],
                        $Cart[$this->ipn_data["item_number$i"]]['item_id'],
                        $this->ipn_data["quantity$i"],
                        $unit_price,
                        $this->ipn_data["item_name$i"],
                        $item_shipping,
                        $item_handling,
                        $item_tax
                );
            }
            $payment_gross = $this->ipn_data['mc_gross'] - $fees_paid;
            PAYPAL_debug("Received $payment_gross gross payment");
            //$currency = $this->ipn_data['mc_currency'];
            if ($this->isSufficientFunds()) {
                $this->handlePurchase();
            } else {
                $this->handleFailure(PAYPAL_FAILURE_FUNDS, 
                        "($logId) Insufficient/incorrect funds for purchase");
                return false;
            }
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


}   // class PaypalIPN

?>

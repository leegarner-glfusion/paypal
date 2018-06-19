<?php
/**
*   This file contains the NULL IPN class.
*   It is used with orders that have zero balances and thus don't go through
*   an actual payment processor.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
*   Class to deal with IPN transactions from Paypal.
*
*   @since 0.5.12
*   @package paypal
*/
class null_ipn extends IPN
{

    private $Cart = NULL;  // Cart object

    /**
    *   Constructor.
    *   Fake payment gateway variables.
    *
    *   @param  array   $A      $_POST'd variables from Paypal
    */
    function __construct($A=array())
    {
        $this->gw_id = 'null';
        parent::__construct($A);

        $cart_id = PP_getVar($A, 'cart_id');
        if (!empty($cart_id)) {
            $this->Cart = Cart::getInstance(0, $cart_id);
        }
        if (!$this->Cart) return NULL;

        $this->pp_data['txn_id'] = $cart_id;
        $billto = $this->Cart->getAddress('billto');
        $shipto = $this->Cart->getAddress('shipto');
        if (empty($shipto)) $shipto = $billto;

        $this->pp_data['payer_email'] = PP_getVar($A, 'payer_email');
        $this->pp_data['payer_name'] = PP_getVar($A, 'name') .' '. PP_getVar($A, 'last_name');
        $this->pp_data['pmt_date'] = PP_getVar($A, 'payment_date');
        $this->pp_data['pmt_gross'] = PP_getVar($A, 'mc_gross', 'float');
        $this->pp_data['pmt_tax'] = PP_getVar($A, 'tax', 'float');
        $this->pp_data['gw_desc'] = 'Null IPN';
        $this->pp_data['gw_name'] = 'Null IPN';
        $this->pp_data['pmt_status'] = PP_getVar($A, 'payment_status');
        $this->pp_data['currency'] = PP_getVar($A, 'mc_currency');
        $this->pp_data['discount'] = PP_getVar($A, 'discount', 'float');

        if (isset($A['invoice']))
            $this->pp_data['invoice'] = $A['invoice'];
        if (isset($A['parent_txn_id']))
            $this->pp_data['parent_txn_id'] = $A['parent_txn_id'];

        $this->pp_data['shipto'] = array(
            'name'      => PP_getVar($shipto, 'name'),
            'company'   => PP_getVar($shipto, 'company'),
            'address1'  => PP_getVar($shipto, 'address1'),
            'address2'  => PP_getVar($shipto, 'address2'),
            'city'      => PP_getVar($shipto, 'city'),
            'state'     => PP_getVar($shipto, 'state'),
            'country'   => PP_getVar($shipto, 'country'),
            'zip'       => PP_getVar($shipto, 'zip'),
        );

        // Set the custom data into an array.  If it can't be unserialized,
        // then treat it as a single value which contains only the user ID.
        if (isset($A['custom'])) {
            $this->pp_data['custom'] = @unserialize(str_replace('\'', '"', $A['custom']));
            if (!$this->pp_data['custom']) {
                $this->pp_data['custom'] = array('uid' => $A['custom']);
            }
        }
        $this->pp_data['custom']['transtype'] = 'null_ipn';

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
    }


    /**
    *   Verify the transaction.
    *   This just checks that a valid cart_id was received along with other
    *   variables.
    *
    *   @return boolean         true if successfully validated, false otherwise
    */
    private function Verify()
    {
        if ($this->Cart === NULL) return false;
        // Order total must be zero to use the null gateway
        $info = $this->Cart->getInfo();
        $total = PP_getVar($this->Cart->getInfo(), 'final_total', 'float');
        if ($total > .001) return false;
        //if ($this->Cart->getInfo()['final_total'] > .001) return false;
        return true;
    }


    /**
    *   Confirms that payment status is complete.
    *   (not 'denied', 'failed', 'pending', etc.)
    *
    *   @param  string  $payment_status     Payment status to verify
    *   @return boolean                     True if complete, False otherwise
    */
    private function isStatusCompleted($payment_status)
    {
        return ($payment_status == 'Completed');
    }


    /**
    *   Checks if payment status is reversed or refunded.
    *   For example, some sort of cancelation.
    *
    *   @param  string  $payment_status     Payment status to check
    *   @return boolean                     True if reversed or refunded
    */
    private function isStatusReversed($payment_status)
    {
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

        $Cart = $this->Cart->Cart();

        if (empty($Cart)) {
            COM_errorLog("Paypal\\null_ipn::Process() - Empty Cart for id {$this->Cart->CartID()}");
            return false;
        }
        $items = array();

        foreach ($Cart as $idx=>$item) {

            // Add the item to the array for the order creation.
            // IPN item numbers are indexes into the cart, so get the
            // actual product ID from the cart
            $args = array(
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'item_name' => $item['name'],
                'shipping' => 0,
                'handling' => 0,
                'tax' => 0,
                'extras' => $item['extras']
            );
            $this->AddItem($args);
  
            $payment_gross = $this->Cart->getInfo()['apply_gc'];
            PAYPAL_debug("Received $payment_gross gift card payment");
            $this->pp_data['pmt_gross'] = $payment_gross;

            if ($this->isSufficientFunds()) {
                $this->handlePurchase();
            } else {
                $this->handleFailure(IPN_FAILURE_FUNDS,
                        "($logId) Insufficient/incorrect funds for purchase");
                return false;
            }
        }
        return true;
    }   // function Process

}   // class paypal_ipn

?>

<?php
/**
*   This file contains the Dummy IPN class.
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
namespace Paypal\ipn;

use \Paypal\Cart;
use \Paypal\Currency;
use \Paypal\Coupon;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 *  Class to provide IPN for internal-only transactions,
 *  such as zero-balance orders.
 *
 *  @since 0.6.0
 *  @package paypal
 */
class internal extends \Paypal\IPN
{

    private $custom;    // holder for custom data

    /**
    *   Constructor.
    *   Fake payment gateway variables.
    *
    *   @param  array   $A      $_POST'd variables from Paypal
    */
    function __construct($A=array())
    {
        global $_USER;

        $this->gw_id = '_internal';
        parent::__construct($A);

        $cart_id = PP_getVar($A, 'cart_id');
        if (!empty($cart_id)) {
            $this->Order = Cart::getInstance(0, $cart_id);
        }
        if (!$this->Order) return NULL;

        $this->pp_data['txn_id'] = $cart_id;
        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto)) $shipto = $billto;

        $this->pp_data['payer_email'] = PP_getVar($A, 'payer_email', 'string', $_USER['email']);
        $this->pp_data['payer_name'] = trim(PP_getVar($A, 'name') .' '. PP_getVar($A, 'last_name'));
        if ($this->pp_data['payer_name'] == '') {
            $this->pp_data['payer_name'] = $_USER['fullname'];
        }
        $this->pp_data['pmt_date'] = PAYPAL_now()->toMySQL(true);
        $this->pp_data['pmt_gross'] = $this->Order->getInfo('total');
        $this->pp_data['pmt_tax'] = $this->Order->getInfo('tax');
        $this->pp_data['gw_desc'] = 'Internal IPN';
        $this->pp_data['gw_name'] = 'Internal IPN';
        $this->pp_data['pmt_status'] = PP_getVar($A, 'payment_status');
        $this->pp_data['currency'] = Currency::getInstance()->code;
        $this->pp_data['discount'] = 0;

        if (isset($A['invoice'])) {
            $this->pp_data['invoice'] = $A['invoice'];
        } else {
            $this->pp_data['invoice'] = $this->Order->order_id;
        }

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
        $this->pp_data['custom']['transtype'] = 'internal_ipn';
        $this->pp_data['pmt_status'] = 'paid';
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
        if ($this->Order === NULL) {
            COM_errorLog("No cart provided");
            return false;
        }

        $info = $this->Order->getInfo();
        $uid = $this->Order->uid;
        $gateway = PP_getVar($info, 'gateway');
        $total = $this->Order->getTotal();
        switch ($gateway) {
        case '_coupon':
            // Order total must be zero to use the coupon gateway in full
            $by_gc = PP_getVar($info, 'apply_gc', 'float');
            if (is_null($by_gc)) {
                $by_gc = Coupon::getUserBalance($uid);
            }
            if ($by_gc < $total) return false;
            break;
        }
        $this->pp_data['status'] = 'paid';
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

        $custom = PP_getVar($this->ipn_data, 'custom');
        $this->custom = @unserialize($custom);

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(IPN_FAILURE_VERIFY,
                            "($logId) Verification failed");
            return false;
        } else {
            $logId = $this->Log(true);
        }

        $Cart = $this->Order->Cart();
        if (empty($Cart)) {
            COM_errorLog("Paypal\\internal_ipn::Process() - Empty Cart for id {$this->Order->cartID()}");
            return false;
        }
        $items = array();
        $total_shipping = 0;
        $total_handling = 0;

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($Cart as $idx=>$item) {
            $args = array(
                'item_id'   => $item->item_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->name,
                'shipping'  => $item->shipping,
                'handling'  => $item->handling,
                'extras'    => $item->extras,
            );
            $this->AddItem($args);
            $total_shipping += $item->shipping;
            $total_handling += $item->handling;
        }
        $this->pp_data['pmt_gross'] = 0;    // This only handles fully-paid items
        $this->pp_data['pmt_shipping'] = $total_shipping;
        $this->pp_data['pmt_handling'] = $total_handling;
        return $this->handlePurchase();
    }   // function Process

}   // class paypal_ipn

?>

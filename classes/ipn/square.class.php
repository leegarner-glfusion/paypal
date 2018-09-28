<?php
/**
*   This file contains the Square IPN class.
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
class square extends \Paypal\IPN
{
    /**
    *   Constructor.
    *   Fake payment gateway variables.
    *
    *   @param  array   $A      $_POST'd variables from Paypal
    */
    function __construct($A=array())
    {
        global $_USER;

        $this->gw_id = 'square';
        parent::__construct($A);

        $order_id = PP_getVar($A, 'referenceId');
        $trans_id = PP_getVar($A, 'transactionId');
        $this->pp_data['pmt_gross'] = 0;
        $this->pp_data['pmt_fee'] = 0;

        if (!empty($order_id)) {
            $this->Order = Cart::getInstance(0, $order_id);
        }
        if (!$this->Order) return NULL;

        $this->pp_data['txn_id'] = $trans_id;
        $billto = $this->Order->getAddress('billto');
        $shipto = $this->Order->getAddress('shipto');
        if (empty($shipto)) $shipto = $billto;

        $this->pp_data['payer_email'] = $this->Order->buyer_email;
        $this->pp_data['payer_name'] = $_USER['fullname'];
        $this->pp_data['pmt_date'] = PAYPAL_now()->toMySQL(true);
        $this->pp_data['gw_desc'] = $this->gw->Description();
        $this->pp_data['gw_name'] = $this->gw->Name();;
        $this->pp_data['pmt_status'] = $status;
        $this->pp_data['currency'] = $C->code;
        $this->pp_data['discount'] = 0;
        $this->pp_data['invoice'] = $this->Order->order_id;

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
        $this->pp_data['custom'] = array(
            'transtype' => $this->gw->Name(),
            'uid'       => $this->Order->uid,
            'by_gc'     => $this->Order->getInfo()['apply_gc'],
        );

        foreach ($this->Order->Cart() as $idx=>$item) {
            $args = array(
                'item_id'   => $item->product_id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'item_name' => $item->description,
                'shipping'  => $item->shipping,
                'handling'  => $item->handling,
                'extras'    => $item->extras,
            );
            $this->AddItem($args);
            $total_shipping += $item->shipping;
            $total_handling += $item->handling;
        }
        $this->pp_data['pmt_shipping'] = $total_shipping;
        $this->pp_data['pmt_handling'] = $total_handling;
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
        $trans = $this->gw->getTransaction($this->pp_data['txn_id']);
        if ($trans) {
            // Get through the top-level array var
            $trans= PP_getVar($trans, 'transaction', 'array');
            if (empty($trans)) return false;
            $tenders = PP_getVar($trans, 'tenders', 'array');
            if (empty($tenders)) return false;
            $order_id = PP_getVar($trans, 'reference_id');
            if (empty($order_id)) return false;

            $status = 'paid';
            foreach ($tenders as $tender) {
                if ($tender['card_details']['status'] == 'CAPTURED') {
                    $C = \Paypal\Currency::getInstance($tender['amount_money']['currency']);
                    $this->pp_data['pmt_gross'] += $C->fromInt($tender['amount_money']['amount']);
                    $this->pp_data['pmt_fee'] += $C->fromInt($tender['processing_fee_money']['amount']);
                } else {
                    $status = 'pending';
                }
            }
        }
        $this->pp_data['status'] = $status;
        $this->pp_data['pmt_status'] = $status;
        return true;


        if ($this->Cart === NULL) {
            COM_errorLog("No cart provided");
            return false;
        }

        // Order total must be zero to use the internal gateway
        $info = $this->Cart->getInfo();
        var_dump($info);die;
        $by_gc = PP_getVar($info, 'apply_gc', 'float');
        $total = PP_getVar($info, 'final_total', 'float');
        if ($by_gc < $total) return false;
        if (!Coupon::verifyBalance($by_gc, $this->pp_data['custom']['uid'])) {
            return false;
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

        // Add the item to the array for the order creation.
        // IPN item numbers are indexes into the cart, so get the
        // actual product ID from the cart
        foreach ($this->Cart as $idx=>$item) {
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

        if (!$this->Verify()) {
            $logId = $this->Log(false);
            $this->handleFailure(IPN_FAILURE_VERIFY,
                            "($logId) Verification failed");
            return false;
        } else {
            $logId = $this->Log(true);
        }
        return $this->handlePurchase();
    }   // function Process

}   // class paypal_ipn

?>

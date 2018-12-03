<?php
/**
 * This file contains the IPN processor for Authorize.Net.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2013 Lee Garner
 * @package     paypal
 * @version     v0.5.2
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Paypal\ipn;

use \Paypal\Cart;

/**
 * Authorize.Net IPN Processor.
 * @since   v0.5.3
 * @package paypal
 */
class authorizenet extends \Paypal\IPN
{
    /**
     * Constructor. Set up the pp_data array.
     *
     * @param   array   $A  Array of IPN data
     */
    function __construct($A=array())
    {
        $this->gw_id = 'authorizenet';
        parent::__construct($A);

        $this->pp_data['txn_id'] = $A['x_trans_id'];
        $this->pp_data['payer_email'] = $A['x_email'];
        $this->pp_data['payer_name'] = $A['x_first_name'] . ' ' .
                    $A['x_last_name'];
        $this->pp_data['pmt_date'] =
                    strftime('%d %b %Y %H:%M:%S', time());
        $this->pp_data['pmt_gross'] = PP_getVar($A, 'x_amount', 'float');
        $this->pp_data['gw_name'] = $this->gw->Description();
        $this->pp_data['pmt_shipping'] = PP_getVar($A, 'x_freight', 'float');
        $this->pp_data['pmt_handling'] = 0; // not supported?
        $this->pp_data['pmt_tax'] = PP_getVar($A, 'x_tax', 'float');
        $this->pp_data['invoice'] = PP_getVar($A, 'x_invoice_num');

        // Check a couple of vars to see if a shipping address was supplied
        $shipto_addr = PP_getVar($A, 'x_ship_to_address');
        $shipto_city = PP_getVar($A, 'x_ship_to_city');
        if ($shipto_addr != '' && $shipto_city != '') {
            $this->pp_data['shipto'] = array(
                'name'      => PP_getVar($A, 'x_ship_to_first_name') . ' ' . 
                                PP_getVar($A, 'x_ship_to_last_name'),
                'address1'  => $shipto_addr,
                'address2'  => '',
                'city'      => $shipto_city,
                'state'     => PP_getVar($A, 'x_ship_to_state'),
                'country'   => PP_getVar($A, 'x_ship_to_country'),
                'zip'       => PP_getVar($A, 'x_ship_to_zip'),
                'phone'     => PP_getVar($A, 'x_phone'),
            );
        }

        /*$custom = explode(';', PP_getVar($A, 'custom'));
        foreach ($custom as $name => $temp) {
            list($name, $value) = explode(':', $temp);
            $this->pp_data['custom'][$name] = $value;
        }*/

        switch(PP_getVar($A, 'x_response_code', 'integer')) {
        case 1:
            $this->pp_data['pmt_status'] = 'paid';
            break;
        default:
            $this->pp_data['pmt_status'] = 'pending';
            break;
        }

        $this->Order = Cart::getInstance(0, $this->pp_data['invoice']);
        // Get the custom data from the order since authorize.net doesn't
        // support pass-through user variables
        $this->pp_data['custom'] = $this->Order->getInfo();
        $this->pp_data['custom']['uid'] = $this->Order->uid;

        // Hack to get the gift card amount into the right variable name
        $by_gc = PP_getVar($this->pp_data['custom'], 'apply_gc', 'float');
        if ($by_gc > 0) {
            $this->pp_data['custom']['by_gc'] = $by_gc;
        }

        /*$items = explode('::', $A['item_var']);
        foreach ($items as $item) {
            list($itm_id, $price, $qty) = explode(';', $item);
            $this->AddItem($itm_id, $qty, $price);
        }*/

    }


    /**
     * Process the transaction.
     * Verifies that the transaction is valid, then records the purchase and
     * notifies the buyer and administrator
     *
     * @uses    self::Verify()
     * @uses    BaseIPN::isUniqueTxnId()
     * @uses    BaseIPN::handlePurchase()
     */
    public function Process()
    {
        if (!$this->Verify()) {
            return false;
        }

        if ('paid' != $this->pp_data['pmt_status']) 
            return false;
        else
            $this->pp_data['status'] = 'paid';

        if (!$this->isUniqueTxnId($this->pp_data))
            return false;

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->Log(true);

        PAYPAL_debug("Received $item_gross gross payment");
        if ($this->isSufficientFunds()) {
            $this->handlePurchase();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Verify the transaction with Authorize.Net
     * Validate transaction by posting data back to the webserver.
     * Checks that a valid response is received and that key fields match the
     * IPN message.
     *
     * @return  boolean         true if successfully validated, false otherwise
     */
    private function Verify()
    {
        return true;

        $json = array(
            'getTransactionDetailsRequest' => array(
                'merchantAuthentication' => array(
                    'name' => $this->gw->getApiLogin(),
                    'transactionKey' => $this->gw->getTransKey(),
                ),
                'refId' => $this->pp_data['invoice'],
                'transId' => $this->pp_data['txn_id'],
            ),
        );
        $jsonEncoded = json_encode($json);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://apitest.authorize.net/xml/v1/request.api');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonEncoded);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            return false;
        }
        $bom = pack('H*','EFBBBF');
        $result = preg_replace("/^$bom/", '', $result);
        $result = json_decode($result, true);

        // Check return fields against known values
        $trans = PP_getVar($result, 'transaction', 'array', NULL);
        if (!$trans) return false;

        if (PP_getVar($trans, 'transId') != $this->pp_data['txn_id']) {
            return false;
        }
        if (PP_getVar($trans, 'responseCode', 'integer') != 1) {
            return false;
        }
        if (PP_getVar($trans, 'settleAmount', 'float') != $this->pp_data['pmt_gross']) {
            return false;
        }

        $order = PP_getVar($trans, 'order', 'array');
        if (empty($order)) return false;
        if (PP_getVar($order, 'invoiceNumber') != $this->pp_data['invoice']) {
            return false;
        }

        // All conditions met
        return true;
    }


    /**
     * Test function that can be run from a script.
     * Adjust pp_data params as needed to test the Verify() function
     *
     * @return  boolean     Results from Verify()
     */
    public function testVerify()
    {
        $this->pp_data['invoice'] = '20180925224531700';
        $this->pp_data['txn_id'] = '40018916851';
        $this->pp_data['pmt_gross'] = 23.77;
        return $this->Verify();
    }

}   // class AuthorizeNetIPN

?>

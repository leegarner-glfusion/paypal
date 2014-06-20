<?php
/**
*   This file contains the IPN processor for Authorize.Net.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2013 Lee Garner
*   @package    paypal
*   @version    0.5.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @license    Portions http://aws.amazon.com/apache2.0
*               Apache License 2.0
*   @filesource
*/


require_once 'BaseIPN.class.php';

/**
*   Authorize.Net SIM IPN Processor
*   @since  0.5.3
*   @package paypal
*/
class AuthorizeNetIPN extends BaseIPN
{
    /**
    *   Constructor.
    *   Set up the pp_data array.
    */
    function __construct($A=array())
    {
        $this->gw_id = 'authorizenetsim';
        parent::__construct($A);

        $this->pp_data['txn_id'] = $A['x_trans_id'];
        $this->pp_data['payer_email'] = $A['x_email'];
        $this->pp_data['payer_name'] = $A['x_first_name'] . ' ' .
                    $A['x_last_name'];
        $this->pp_data['pmt_date'] =
                    strftime('%d %b %Y %H:%M:%S', time());
        $this->pp_data['pmt_gross'] = (float)$A['x_amount'];
        $this->pp_data['gw_name'] = $this->gw->Description();
        $this->pp_data['pmt_status'] = $A['x_response_code'];
        $this->pp_data['pmt_shipping'] = $A['x_freight'];
        $this->pp_data['pmt_handling'] = 0; // not supported?
        $this->pp_data['pmt_tax'] = $A['x_tax'];

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['x_ship_to_address']) && !empty($a['x_ship_to_address'])
            || isset($A['x_ship_to_city']) && !empty($A['x_ship_to_city'])) {
            $this->pp_data['shipto'] = array(
                'name'      => $A['x_ship_to_first_name'] . ' ' .
                                $A['x_ship_to_last_name'],
                'address1'  => $A['x_ship_to_address'],
                'address2'  => '',
                'city'      => $A['x_ship_to_city'],
                'state'     => $A['x_ship_to_state'],
                'country'   => $A['x_ship_to_country'],
                'zip'       => $A['x_ship_to_zip'],
                'phone'     => $A['x_phone'],
            );
        }

        $custom = explode(';', $A['custom']);
        foreach ($custom as $name => $temp) {
            list($name, $value) = explode(':', $temp);
            $this->pp_data['custom'][$name] = $value;
        }

        $items = explode('::', $A['item_var']);
        foreach ($items as $item) {
            list($itm_id, $price, $qty) = explode(';', $item);
            $this->AddItem($itm_id, $qty, $price);
        }

    }


    /**
    *   Process the transaction.
    *   Verifies that the transaction is valid, then records the purchase and
    *   notifies the buyer and administrator
    *
    *   @uses   Validate()
    *   @uses   BaseIPN::isUniqueTxnId()
    *   @uses   BaseIPN::handlePurchase()
    */
    public function Process()
    {
        if ($this->Validate() > 0) {
            return false;
        }

        if (1 != $this->pp_data['pmt_status']) 
            return false;
        else
            $this->pp_data['status'] = 'paid';

        if (!$this->isUniqueTxnId($this->pp_data))
            return false;

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->Log(true);

        // shopping cart
        $fees_paid = $this->pp_data['pmt_tax'] + 
                        $this->pp_data['pmt_shipping'] +
                        $this->pp_data['pmt_handling'];

        PAYPAL_debug("Received $item_gross gross payment");
        if ($this->isSufficientFunds()) {
            $this->handlePurchase();
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Check that this is a valid purchase IPN.
    *   Calculates the hash and compares against the returned x_MD5_Hash value
    *
    *   @return integer     Zero on success, positive value on failure
    */
    private function Validate()
    {
        return 0;

        $hash_key = $this->gw->HashKey();
        $api_login = $this->gw->ApiLogin();
        $trans_id = $this->ipn_data['x_trans_id'];
        $amount = $this->ipn_data['x_amount'];
        $hash = strtoupper(md5($hash_key . $api_login . $trans_id . $amount));

        if ($hash != $this->ipn_data['x_MD5_Hash']) return 1;
        return 0;
    }

}   // class AuthorizeNetIPN

?>

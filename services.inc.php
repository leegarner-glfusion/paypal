<?php
/**
*   Web service functions for the PayPal plugin.
*   This is used to supply PayPal functions to other plugins.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.1
*   @since      version 0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
*   Create the payment buttons for an external item.
*   Creates the requested buy_now button type and, if requested,
*   an add_cart button.
*
*   All gateways that have the 'external' service enabled as well as the
*   requested button type will provide a button.
*
*   $args['btn_type'] can be empty or not set, to create only an Add to Cart
*   button.  $args['add_cart'] must still be set in this case.  If neither
*   button type is requested, an empty array is returned.
*
*   Provided $args should include at least:
*       'item_number', 'item_name', 'price', 'quantity', and 'item_type'
*   $args['btn_type'] should reflect the type of immediate-purchase button
*   desired.  $args['add_cart'] simply needs to be set to get an add-to-cart
*   button.
*
*   @uses   PaymentGw::ExternalButton()
*   @param  array   $args       Array of item information
*   @param  array   &$output    Pointer to output array
*   @param  array   &$svc_msg   Unused
*   @return integer             Status code
*/
function service_genButton_paypal($args, &$output, &$svc_msg)
{
    global $_CONF, $_PP_CONF;

    $btn_type = isset($args['btn_type']) ? $args['btn_type'] : '';
    $output = array();

    // Create the immediate purchase button, if requested.  As soon as a
    // gateway supplies the requested button type, break from the loop.
    if (!empty($btn_type)) {
        PAYPAL_loadGateways();      // load all gateways
        if (!empty($_PP_CONF['gateways'])) {    // Should be at least one
            // Get the first gateway that supports the button type
            foreach ($_PP_CONF['gateways'] as $gw_info) {
                if (PaymentGw::Supports($btn_type, $gw_info) &&
                        PaymentGw::Supports('external', $gw_info) &&
                        class_exists($gw_info['id'])) {
                    $gw = new $gw_info['id'];
                    $output[] = $gw->ExternalButton($args, $btn_type);
                }
            }
        }
    }

    // Now create an add-to-cart button, if requested.
    if (isset($args['add_cart']) && $_PP_CONF['ena_cart'] == 1) {
        $tpl_add_cart = 'btn_add_cart.thtml';
        if (!isset($args['item_type'])) $args['item_type'] = PP_PROD_VIRTUAL;
        $T = new Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file('cart', 'buttons/btn_add_cart.thtml');
        $T->set_var(array(
                'item_name'     => $args['item_name'],
                'item_number'   => $args['item_number'],
                'short_description' => $args['short_description'],
                'amount'        => $args['amount'],
                'pi_url'        => PAYPAL_URL,
                'item_type'     => $args['item_type'],
                'have_tax'      => isset($args['tax']) ? 'true' : '',
                'tax'           => isset($args['tax']) ? $args['tax'] : 0,
        ) );

        $output['add_cart'] = $T->parse('', 'cart');
    }

    $retval = PLG_RET_OK;
    return $retval;
}


/**
*   Return the configured currency.
*   This is an API function to allow other plugins to find out what
*   currency we accept.
*
*   @return string      Our configured currency code.
*/
function service_getCurrency_paypal($args, &$output, &$svc_msg)
{
    global $_PP_CONF;

    $output = $_PP_CONF['currency'];
    return PLG_RET_OK;
}


/**
*   API function to return the url to a Paypal item.
*   This returns the url to a Paypal-controlled item, such as the
*   IPN transaction data.  This is meant to provide a backlink for other 
*   plugins to use with their products.
*
*   @param  array   $args       Array of item information, at least 'type'
*   @param  array   &$output    Pointer to output array
*   @param  array   &$svc_msg   Unused
*   @return integer             Status code
*/
function service_getUrl_paypal($args, &$output, &$svc_msg)
{
    if (!is_array($args)) return PLG_RET_ERROR;

    $type = isset($args['type']) ? $args['type'] : '';
    $url = '';

    switch ($type) {
    case 'ipn':
        $id = isset($args['id']) ? $args['id'] : '';
        if ($id != '') {
            $url = PAYPAL_ADMIN_URL .
                    '/index.php?ipnlog=x&op=single&txn_id=' . $id;
        }
        break;
    }

    if (!empty($url)) {
        $output = $url;
        return PLG_RET_OK;
    } else {
        $output = '';
        return PLG_RET_ERROR;
    }

}


?>

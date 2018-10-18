<?php
/**
 * Testing gateway to pass orders through the internal IPN handler.
 *
 * *NOTE* All orders passed through this gateway are automatically
 * treated as paid in full. This gateway should *NOT* be enabled on
 * a live site!
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     paypal
 * @version     0.6.0
 * @since       0.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Paypal\Gateways;

use \Paypal\Cart;
use \Paypal\Coupon;
use \Paypal\Currency;

/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class test extends \Paypal\Gateway
{
    /**
    *   Constructor.
    *   Set gateway-specific items and call the parent constructor.
    */
    public function __construct()
    {
        global $LANG_PP;

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'test';
        $this->gw_desc = 'Internal Testing Gateway';
        $this->gw_url = PAYPAL_URL . '/ipn/internal.php';
        parent::__construct();
    }


    /*
     * Get the checkout selection for applying a gift card balance.
     * If the GC balance exceeds the order value, create a radio button
     * just like any other gateway to use the balance as payment in full.
     * If the GC balance is less than the order amount, use a checkbox
     * to give the buyer the option of applying it as partial payment.
     *
     * @param   boolean $selected   Indicate if this should be the selected option
     * @return  string      HTML for the radio button or checkbox
     */
    public function checkoutRadio($selected = false)
    {
        global $LANG_PP;

        // Get the order total from the cart, and the user's balance
        // to decide what kind of button to show.
        $cart = Cart::getInstance();
        $total = $cart->getTotal();

        $sel = $selected ? 'checked="checked" ' : '';
        $radio = '<input required type="radio" name="gateway" value="' .
                $this->gw_name . '" ' . $sel . '/>&nbsp;' . $this->gw_desc;
        return $radio;
    }


    /**
     *  Get the form variables for this checkout button.
     *  Used if the entire order is being paid by the gift card balance.
     *
     *  @param  object  $cart   Shopping cart
     *  @return string          HTML for input vars
     */
    public function gatewayVars($cart)
    {
        global $_USER;

        // Add custom info for the internal ipn processor
        $cust = $cart->custom_info;
        $cust['uid'] = $_USER['uid'];
        $cust['transtype'] = 'coupon';
        $cust['cart_id'] = $cart->CartID();

        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="by_gc" />',
            '<input type="hidden" name="cart_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="custom" value=\'' . @serialize($cust) . '\' />',
            '<input type="hidden" name="payment_status" value="Completed" />',
        );
        if (COM_isAnonUser()) {
            //$T->set_var('need_email', true);
        } else {
            $gateway_vars[] = '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />';
        }
        return implode("\n", $gatewayVars);
    }


    public function getConfigFields()
    {
        return array();
    }

}

?>

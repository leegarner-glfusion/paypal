<?php
/**
*   Class to manage payment by gift card.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
 *  Coupon gateway class, just to provide checkout buttons for coupons
 */
class _coupon_gw extends Gateway
{
    /**
    *   Constructor.
    *   Set gateway-specific items and call the parent constructor.
    */
    public function __construct()
    {
        global $LANG_PP;

        // These are used by the parent constructor, set them first.
        $this->gw_name = '_coupon_gw';
        $this->gw_desc = $LANG_PP['apply_gc'];
        $this->gw_url = PAYPAL_URL . '/ipn/internal_ipn.php';
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
    public function checkoutRadio($cart, $selected = false)
    {
        global $LANG_PP;

        // Get the order total from the cart, and the user's balance
        // to decide what kind of button to show.
        $gc_bal = Coupon::getUserBalance();
        $total = Cart::getInstance()->getInfo('order_total');
        $items = $cart->Cart();
        $gc_can_apply = 0;
        foreach ($items as $item) {
            $P = Product::getInstance($item['item_id']);
            if (!$P->isNew && $P->prod_type != PP_PROD_COUPON) {
                $gc_can_apply += $P->getPrice($item['options'], $item['quantity']);
            }
        }
        if ($gc_bal == 0) {
            // No gift card balance.
            $radio = '';
        } elseif ($gc_bal < $gc_can_apply) {
            // GC balance is not enough, apply the whole thing.
            $radio = '<input type="checkbox" name="by_gc" value="' . $by_gc .
                '" checked="checked" />&nbsp;';
            $radio .= sprintf($LANG_PP['use_gc_full'],
                    Currency::getInstance()->Format($gc_can_apply));
        } elseif ($gc_bal >= $gc_can_apply && $gc_can_apply == $total) {
            // GC balance is enough to pay for the order. Show a regular
            // radio button.
            $sel = $selected ? 'checked="checked" ' : '';
            $radio = '<input required type="radio" name="gateway" value="' .
                $this->gw_name . '" ' . $sel . '/>&nbsp;';
            $radio .= sprintf($LANG_PP['use_gc_part'],
                    Currency::getInstance()->Format($gc_can_apply),
                    Currency::getInstance()->Format($gc_bal));
            // Make sure any apply_gc amount is hidden, it will be created
            // from the gateway radio
            $radio .= '<input type="hidden" name="by_gc" value="0" />';
        } else {
            // Have a GC balance, but not enough to pay the entire order.
            $by_gc = min($gc_bal, $gc_can_apply);
            $radio = '<input type="checkbox" name="by_gc" value="' . $by_gc .
                '" checked="checked" />&nbsp;';
            $radio .= sprintf($LANG_PP['use_gc_part'],
                    Currency::getInstance()->Format($gc_can_apply),
                    Currency::getInstance()->Format($gc_bal));
            $radio .= '<br /><div class="ppNoGCMsg">' . $LANG_PP['some_gc_disallowed'] . '</div>';
        }
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
        $cust['transtype'] = 'internal';
        $cust['cart_id'] = $cart->CartID();

        $gatewayVars = array(
            '<input type="hidden" name="processorder" value="by_gc" />',
            '<input type="hidden" name="cart_id" value="' . $cart->CartID() . '" />',
            '<input type="hidden" name="custom" value=\'' . @serialize($cust) . '\' />',
        );
        $cart->setInfo('apply_gc', $cart->getInfo('final_total'));
        if (COM_isAnonUser()) {
            //$T->set_var('need_email', true);
        } else {
            $gateway_vars[] = '<input type="hidden" name="payer_email" value="' . $_USER['email'] . '" />';
        }
        return implode("\n", $gatewayVars);
    }

}

?>


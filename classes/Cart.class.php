<?php
/**
*   Shopping cart class for the Paypal plugin.
*   The cart is saved in the cart table with an ID based on the session_id.
*   For anonymous users, the cart ID is stored in a cookie so it can be merged
*   into the user cart at login.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*
*   Based partially on work done for the unreleased "ecommerce" plugin
*       by Josh Pendergrass <cendent AT syndicate-gaming DOT com>
*/
namespace Paypal;

/**
*   Shopping cart class
*   @package paypal
*/
class Cart extends Order
{
    private static $session_var = 'ppGCart';

    /** Holder for custom information
    *   @var array */
    public $custom_info = array();

    /**
    *   Constructor.
    *   Reads the cart contents from the "cart" table, if available.
    *   Does not read from the userinfo table- that's up to the uesr
    *   login and logout functions.
    */
    public function __construct($cart_id='', $interactive=true)
    {
        global $_TABLES, $_PP_CONF, $_USER;

        if (empty($cart_id)) {
            $cart_id = self::getCart();
        }
        parent::__construct($cart_id);
        if ($this->isNew) {
            $this->status = 'cart';
            $this->Save();    // Save to reserve the ID
        }
        if (COM_isAnonUser()) {
            self::setAnonCartID($this->order_id);
        }
        return;

        // Don't use session-based carts for paypal IPN, for those
        // we just want an empty cart that can be read.
        if ($interactive) {
            Workflow::Init();
            self::initSession();

            // Cart ID can be passed in, typically by IPN processors
            // If not, get the cart based on session or user ID
            if (empty($cart_id)) {
                $cart_id = self::getCart();
            }
            // If a cart ID still not found, create a new one
            if (empty($cart_id)) {
                $cart_id = self::_createID();
            }
            // Set the cart ID in the cookie and the local variable
            $this->order_id = $cart_id;
            $this->Save();
            /*$this->m_cart_id = $cart_id;
            if (COM_isAnonUser()) {
                self::setAnonCartID($this->m_cart_id);
            }*/
        } else {
            // For non-interactive sessions a cart ID must be provided
            $this->order_id = $cart_id;
            //$this->m_cart_id = $cart_id;
        }

//        $this->m_cart = array();
//        $this->m_info = array();
        $this->Load();
    }


    /**
    *   Get the cart for the current user
    *
    *   @return object  Cart object
    */
    public static function getInstance($uid = 0, $cart_id = '')
    {
        global $_TABLES, $_USER;
        static $carts = array();

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        /*$cart_id = DB_escapeString($cart_id);
        $sql = "SELECT cart_info, apply_gc, cart_contents
                   FROM {$_TABLES['paypal.cart']}
                   WHERE cart_id = '$cart_id'";
            //echo $sql;die;
        */
        if ($uid > 1) {
            if (!array_key_exists($uid, $carts)) {
                $carts[$uid] = new self($cart_id);
            }
            $cart = $carts[$uid];
        } else {
            // Get a cart for another user. Used to get the anonymous
            // cart by ID to merge when logging in. Can't cache this.
            $cart = new self($cart_id);
        }
        return $cart;
    }


    /**
    *   Get the cart contents as an array of items
    *
    *   @return array   Current cart contents
    */
    public function Cart()
    {
        return $this->items;
    }


    /**
    *   Set the cart contents.
    *
    *   @uses   self::Read()
    */
    public function XLoad()
    {
        $cart_id = $this->cartID(true);
        if ($cart_id == '') {
            return;
        }
        $data = self::Read($cart_id);
        $this->m_info = $data['info'];
        $this->m_cart = $data['cart'];
    }


    /**
    *   Static function to read cart info from the database.
    *
    *   @param  string  $cart_id    Cart ID
    *   @return array       Array of cart contents and other info
    */
    public static function Read($cart_id)
    {
        global $_TABLES, $_PP_CONF;

        $info = array();
        $cart = array();
        if (PP_isMinVersion('0.6.0')) {
            $sql = "SELECT cart_info, apply_gc, cart_contents
                   FROM {$_TABLES['paypal.cart']}
                   WHERE cart_id = '$cart_id'";
            //echo $sql;die;
            $res = DB_query($sql);
            $A = DB_fetchArray($res, false);
            if (is_array($A)) {
                if (isset($A['cart_info']) && !empty($A['cart_info'])) {
                    $info = @unserialize($A['cart_info']);
                }
                if (!$_PP_CONF['gc_enabled']) $info['apply_gc'] = 0;
                if (isset($A['cart_contents']) && !empty($A['cart_contents'])) {
                    $cart = @unserialize($A['cart_contents']);
                }
            }
            // Reset these back to empty arrays in case unserialize() was NULL
            if (!$info) {
                $info = array();
            }
            if (!$cart) {
                $cart = array();
            }
        }
        return array('info' => $info, 'cart' => $cart);
    }


    /**
    *   Merge the saved cart for Anonymous into the current user's cart.
    *   Saves the updated cart to the database
    *
    *   @return array       Current cart contents
    */
    public function Merge($cart_id)
    {
        global $_TABLES, $_USER;

        if ($_USER['uid'] < 2) return;

        $AnonCart = self::getInstance(1, $cart_id);
        if (!empty($AnonCart->m_cart)) {
            foreach ($AnonCart->m_cart as $A) {
                $item = PAYPAL_explode_opts($A['item_id']);
                $args = array(
                    'item_number' => $item[0],
                    'item_name' => $A['name'],
                    'descrip' => $A['descrip'],
                    'quantity' => $A['quantity'],
                    'price' => $A['price'],
                    'options' => isset($item[1]) ? $item[1] : '',
                    'extras' => $A['extras'],
                );
                $this->addItem($args);
            }
            $this->Save();
        }
        self::delAnonCart();    // Delete to avoid re-merging
        return $this->m_cart;
    }


    /**
    *   Save the current cart items to the database
    */
    public function XSave()
    {
        global $_TABLES, $_USER, $_PP_CONF;

        $uid = (int)$_USER['uid'];

        $cart = @serialize($this->m_cart);
        if (!$cart) return;     // Error with the array, just quit
        $cart = DB_escapeString($cart);

        $info = @serialize($this->m_info);
        if (!$info) $info = '';
        $info = DB_escapeString($info);
        // New way- use the cart table
        $sql = "INSERT INTO {$_TABLES['paypal.cart']}
                (cart_id, cart_uid, cart_info, cart_contents, last_update)
            VALUES (
                '" . $this->cartID(true) . "',
                $uid,
                '$info',
                '$cart',
                '" . PAYPAL_now()->toMySql() . "'
            )
            ON DUPLICATE KEY UPDATE
                cart_contents = '$cart',
                cart_info = '$info',
                last_update = '" . PAYPAL_now()->toMySql() . "'";
        //COM_errorLog($sql);
        DB_query($sql, 1);
        if (DB_error()) COM_errorLog("Error saving cart for user $uid", 1);
    }


    /**
    *   Save the current cart items to the userinfo table for logged-in users
    *   @deprecated
    */
    public function X_SaveUserCart()
    {
        global $_TABLES, $_USER;

        $uid = (int)$_USER['uid'];
        if ($uid < 2)
            return;         // don't save anon users cart

        // Create a cart record even if empty
        if (empty($this->m_cart) || !is_array($this->m_cart)) {
            $this->m_cart = array();
        }

        $cart = @serialize($this->m_cart);
        if (!$cart) return;     // Error with the array, just quit
        $cart = DB_escapeString($cart);

        // See if there's a userinfo record for this user and update or insert
        // as appropriate
        $U = UserInfo::getInstance($uid);
        $U->cart = $this->m_cart;
        $U->SaveUser();
        /*$sql = "INSERT INTO {$_TABLES['paypal.userinfo']} (uid, cart)
                    VALUES ($uid, '$cart')
                ON DUPLICATE KEY UPDATE
                    cart = '$cart'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) COM_errorLog("Error saving cart for user $uid", 1);*/
        // TODO: Delete cart table contents?
    }


    /**
     *  Add a single item to the cart.
     *  Formats the argument array to match the format used by the Order class
     *  and calls that class's addItem() function to actually add the item.
     *
     *  Some values are straight from the item table, but may be overridden
     *  to handle special cases or customization.
     *
     *  @param  array   $args   Array of arguments. item_number is required.
     *  @return integer             Current item quantity
     */
    public function addItem($args)
    {
        global $_PP_CONF;

        if (!isset($args['item_number'])) return false;
        $item_id = $args['item_number'];    // may contain options
        $P = Product::getInstance($item_id);
        $quantity   = PP_getVar($args, 'quantity', 'float', 1);
        $override   = isset($args['override']) ? $args['price'] : NULL;
        $extras     = PP_getVar($args, 'extras', 'array');
        $options    = PP_getVar($args, 'options', 'array');
        $item_name  = PP_getVar($args, 'item_name');
        $item_dscp  = PP_getVar($args, 'description');
        $uid        = PP_getVar($args, 'uid', 'int', 1);
        if (!is_array($this->items))
            $this->items = array();

        // Extract the attribute IDs from the options array to create
        // the item_id.
        // Options are formatted as "id|dscp|price"
        $opts = array();
        $opt_str = '';          // CSV option numbers
        if (is_array($options) && !empty($options)) {
            foreach($options as $optname=>$option) {
                $opt_tmp = explode('|', $option);
                $opts[] = $opt_tmp[0];
            }
            $opt_str = implode(',', $opts);
            // Add the option numbers to the item ID to create a new ID
            $item_id .= '|' . $opt_str;
        } else {
            $options = array();
        }

        // Look for identical items, including options (to catch
        // attributes).  If found, just update the quantity.
        $have_id = $this->Contains($item_id, $extras);
        if ($have_id !== false) {
            $this->items[$have_id]->quantity += $quantity;
            $this->Save();
            $new_quantity = $this->items[$have_id]->quantity;
        } else {
            $price = $P->getPrice($opts, $quantity, array('uid'=>$uid));
            $tmp = array(
                'item_id'   => $item_id,
                'quantity'  => $quantity,
                'name'      => $P->getName($item_name),
                'description'   => $P->getDscp($item_dscp),
                'price'     => sprintf("%.2f", $price),
                'options'   => $opt_str,
                'extras'    => $extras,
                'taxable'   => $P->isTaxable() ? 1 : 0,
            );
            //$this->items[] = $tmp;
            parent::addItem($tmp);
            $new_quantity = $quantity;
        }
        //$this->Save();
        return $new_quantity;
    }


    /**
    *   Update an existing cart item.
    *   This only works where items are unique since the caller has no access
    *   to the cart ID.
    *
    *   @param  string  $item_number    Item number to update
    *   @param  array   $updates        Array (field=>value) of new values
    */
    public function updateItem($item_number, $updates)
    {
        // Search through the cart for the item number
        foreach ($this->m_cart as $id=>$item) {
            if ($item['item_id'] == $item_number) {
                // If the item is found, loop through the updates and apply
                foreach ($updates as $fld=>$val) {
                    if (isset($item[$fld])) {
                        $this->m_cart[$id][$fld] = $val;
                    }
                }
                break;
            }
        }
        $this->Save();
    }


    /**
    *   Update the quantity for all cart items.
    *   Called from the View Cart form to update any quantities that have
    *   changed.
    *   Also applies a coupon code, if entered.
    *
    *   @see    Cart::UpdateQty()
    *   @param  array   $items  Array if items as itemID=>newQty
    *   @return array           Updated cart contents
    */
    public function Update($A)
    {
        global $_PP_CONF;

        $items = $A['quantity'];
        if (!is_array($items)) {
            // No items in the cart?
            return;
        }
        foreach ($items as $id=>$value) {
            $value = (float)$value;
            $this->UpdateQty($id, $value, false);
        }
        // Now look for a coupon code to redeem against the user's account.
        if ($_PP_CONF['gc_enabled']) {
            $gc = PP_getVar($A, 'gc_code');
            if (!empty($gc)) {
                if (Coupon::Redeem($gc) == 0) {
                    unset($this->m_info['apply_gc']);
                }
            }
        }
        $this->Save();  // UpdateQty didn't save the cart, so do it here
        return $this->m_cart;
    }


    /**
    *   Update the quantity for a cart item.
    *   Since this may be called by Cart::UpdateAllQty, the $save parameter
    *   may be false to keep from updating the DB after every save.  In that
    *   case, it's up to the caller to promptly save the cart.
    *
    *   @param  string  $id     Item ID to update
    *   @param  integer $newqty New quantity
    *   @param  boolean $save   Indicate whether to save the cart after update
    *   @return array           Updated cart contents
    */
    public function UpdateQty($id, $newqty, $save=true)
    {
        if (isset($this->m_cart[$id])) {
            if ($newqty <= 0) {
                $this->Remove($id);
            } else {
                $this->m_cart[$id]['quantity'] = (float)$newqty;
            }
            if ($save) $this->Save();
        }
        return $this->m_cart;
    }


    /**
     * Get the instructions text
     *
     * @return  string  Instructions text, if set
     */
    public function getInstructions()
    {
        if (isset($this->m_info['order_instr'])) {
            return $this->m_info['order_instr'];
        } else {
            return '';
        }
    }


    /**
    *   Remove an item from the cart.
    *   Saves the updated cart after removal.
    *
    *   @param  string  $id     Item ID to remove
    *   @return array           Current cart contents
    */
    public function Remove($id)
    {
        if (isset($this->m_cart[$id])) {
            unset($this->m_cart[$id]);
            $this->Save();
        }
        return $this->m_cart;
    }


    /**
    *   Empty and destroy the cart
    *
    *   @param  boolean $del_order  True to delete any related order
    *   @return array       Empty cart array
    */
    public function Clear($del_order = true)
    {
        global $_TABLES, $_USER;

        $sql = "DELETE FROM {$_TABLES['paypal.cart']} WHERE
                cart_id = '" . DB_escapeString($this->cartID()) . "'";
        if (!COM_isAnonUser()) {
            $sql .= " OR cart_uid = " . (int)$_USER['uid'];
        }
        DB_query($sql);
        if ($del_order && isset($_SESSION[self::$session_var]['order_id']) &&
            !empty($_SESSION[self::$session_var]['order_id'])) {
            Order::Delete($_SESSION[self::$session_var]['order_id']);
        }
        $this->m_cart = array();
        self::delAnonCart();
        return $this->m_cart;
    }


    /**
    *   Delete all carts in the table
    *   This may be called during plugin upgrades to ensure the data
    *   is not corrupt.
    *
    *   @since  0.6.0
    */
    public static function deleteAll()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['paypal.cart']}");
    }


    /**
    *   View the cart.
    *   This function shows the shopping cart, either with the quantity fields
    *   and option to update, or with the checkout buttons depending on the
    *   value of $checkout.
    *
    *   @uses   getCheckoutButtons()
    *   @param  boolean $checkout   True to indicate this is the final checkout
    *   @return string      HTML for the "view cart" form
    */
    public function XView($checkout = false)
    {
        global $_CONF, $_PP_CONF, $_USER, $LANG_PP, $_TABLES, $_SYSTEM;

        if (!$this->hasItems()) {
            return $LANG_PP['cart_empty'];
        }
        $currency = Currency::getInstance();
        $tpl = $checkout ? 'order' : 'viewcart';
        $T = PP_getTemplate($tpl, 'cart');
        if ($checkout) {
            foreach (Workflow::getAll() as $key => $value) {
                $T->set_var('have_' . $value, 'true');
                foreach ($this->_addr_fields as $fldname) {
                    if (isset($this->m_info[$value][$fldname])) {
                        $T->set_var($value . '_' . $fldname,
                            $this->m_info[$value][$fldname]);
                    } else {
                        $T->set_var($value . '_' . $fldname, '');
                    }
                }
            }
            $T->set_var('not_final', 'true');
        }

        $T->set_block('cart', 'ItemRow', 'iRow');
        $counter = 0;
        $subtotal = 0;
        $shipping = 0;
        $handling = 0;
        $tax_items = 0;
        $tax_amt = 0;
        foreach ($this->m_cart as $id=>$item) {
            $counter++;
            $attr_desc = '';
            list($item_id, $attr_keys) = PAYPAL_explode_opts($item['item_id']);
            $P = Product::getInstance($item_id);
            if ($checkout) {
                $item_price = $item['price'];
            } else {
                $item_price = $P->getPrice($attr_keys, $item['quantity']) / $item['quantity'];
            }
            $this->m_cart[$id]['price'] = $item_price;  // update from catalog
            $item_name = $item['descrip'];
            // Get shipping amount and weight
            if ($P->shipping_type == 2 && $P->shipping_amt > 0) {
                // fixed shipping amount per item. Update actual cart
                $this->m_cart[$id]['shipping'] = $P->shipping_amt * $item['quantity'];
                $shipping += $this->m_cart[$id]['shipping']; // for display
            } elseif ($P->shipping_type == 1 && $P->weight > 0) {
                // using gateway profile, save the item's weight in the cart
                $this->m_cart[$id]['weight'] = $P->weight * $item['quantity'];
            }
            $this->m_cart[$id]['type'] = $P->prod_type;
            $item_total = $item_price * $item['quantity'];
            $item_taxable = PP_getVar($item, 'taxable', 'int', 0);
            if ($item_taxable) {
                $tax_amt += ($item_price * $item['quantity']);
                $tax_items++;       // count the taxable items for display
            }
            $T->set_var(array(
                'cart_item_id'  => $id,
                'pi_url'        => PAYPAL_URL,
                'cart_id'       => $item['item_id'],
                'pp_id'         => $counter,
                'item_id'       => $item_id,
                'item_name'     => $item_name,
                'item_descrip'  => $item['descrip'],
                'item_options'  => $P->getOptionDisplay($item),
                'item_price'    => COM_numberFormat($item_price, 2),
                'item_quantity' => $item['quantity'],
                'item_total'    => COM_numberFormat($item_total, 2),
                'item_link'     => $P->getLink(),
                'iconset'       => $_PP_CONF['_iconset'],
                'is_uikit'      => $_PP_CONF['_is_uikit'],
                'taxable'       => $item_taxable,
                'tax_icon'      => $LANG_PP['tax'][0],
            ) );
            $T->parse('iRow', 'ItemRow', true);
            $subtotal += $item_total;
        }

        $custom_info = array(
                'uid'       => $_USER['uid'],
                'transtype' => 'cart_upload',
                'cart_id'   => $this->cartID(),
        );
        $cart_tax = PP_getTax($tax_amt);
        $total = $subtotal + $shipping + $handling + $cart_tax;

        // Save order total values in the info array
        $this->m_info['order_total'] = $total;
        $this->m_info['shipping'] = $shipping;
        $this->m_info['tax'] = $cart_tax;

        // A little hack to show only the total if there are no other
        // charges
        //if ($total == $subtotal) $subtotal = 0;

        // Format the TOC link, if any
        if (!empty($_PP_CONF['tc_link'])) {
            $tc_link = str_replace('{site_url}', $_CONF['site_url'], $_PP_CONF['tc_link']);
        } else {
            $tc_link = '';
        }

        $gc_bal = 0;
        $apply_gc = 0;
        if ($_PP_CONF['gc_enabled']) {
            $gc_bal = Coupon::getUserBalance();
            $apply_gc = PP_getVar($this->m_info, 'apply_gc', 'float', 0);
            if ($apply_gc !== 0) {
                $apply_gc = min($apply_gc, $gc_bal, Coupon::canPayByGC($this));
            } elseif (!$checkout) {
                // Only calculate the GC to apply when selecting tye
                // gateway. If in final checkout the amount has already
                // been submitted.
                $apply_gc = min(Coupon::canPayByGC($this), $gc_bal);
            }
        }
        $this->custom_info['by_gc'] = $apply_gc;
        $this->custom_info['final_total'] = $total - $apply_gc;
        $this->m_info['final_total'] = $total - $apply_gc;
        $this->m_info['gc_bal'] = $gc_bal;
        $this->m_info['apply_gc'] = $apply_gc;
        $T->set_var(array(
            //'paypal_url'        => $_PP_CONF['paypal_url'],
            //'receiver_email'    => $_PP_CONF['receiver_email'][0],
            'custom'    => serialize($custom_info),
            'shipping'  => $shipping > 0 ? $currency->FormatValue($shipping) : '',
            'subtotal'  => $subtotal != $total ? $currency->Format($subtotal) : '',
            'total'     => $currency->Format($total),
            'order_instr' => htmlspecialchars($this->getInstructions()),
            'tc_link'  => $tc_link,
            'apply_gc'  => $apply_gc ? $currency->FormatValue($apply_gc) : 0,
            'gc_bal_disp' => $gc_bal > 0 ? $currency->FormatValue($gc_bal) : 0,
            'use_gc'    => $_PP_CONF['gc_enabled']  && !COM_isAnonUser() ? true : false,
            'net_total' => $currency->Format(max($total - $apply_gc, 0)),
            'cart_tax'  => $cart_tax > 0 ? $currency->FormatValue($cart_tax) : '',
            'tax_on_items' => sprintf($LANG_PP['tax_on_x_items'], PP_getTaxRate() * 100, $tax_items),
            'payer_email' => $this->getInfo('payer_email'),
        ) );

        // If this is the final checkout, then show the payment buttons
        $this->Save();      // Update for tax
        if ($checkout) {
            $gw = Gateway::getInstance($this->m_info['gateway']);
            $T->set_var(array(
                'gateway_vars'  => $this->checkoutButton($gw),
                'checkout'      => 'true',
                'pmt_method'    => $gw->getLogo(),
            ) );
        } else {
            $T->set_var('gateway_radios', $this->getCheckoutRadios());
            $T->set_var('payer_email', COM_isAnonUser() ? '' : $_USER['email']);
        }
        $T->parse('output', 'cart');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
    *   Create a fake checkout button to be used when the order value is zero
    *   due to coupons. This takes the user directly to the internal IPN processor.
    *
    *   @param  object  $gw Selected Payment Gateway
    *   @return string      HTML for final checkout button
    */
    public function checkoutButton($gw)
    {
        global $_PP_CONF, $_USER;

        $T = PP_getTemplate('btn_checkout', 'checkout', 'buttons');
        $T->set_var(array(
            'is_uikit' => $_PP_CONF['_is_uikit'],
        ) );
        // Special handling if there is a zero total due to discounts
        // or gift cards
        if ($this->custom_info['final_total'] < .001) {
            $this->custom_info['uid'] = $_USER['uid'];
            $this->custom_info['transtype'] = 'internal';
            $this->custom_info['cart_id'] = $this->CartID();
            $gateway_vars = array(
                '<input type="hidden" name="processorder" value="by_gc" />',
                '<input type="hidden" name="cart_id" value="' . $this->CartID() . '" />',
                '<input type="hidden" name="custom" value=\'' . @serialize($this->custom_info) . '\' />',
            );
            $T->set_var(array(
                'action'        => PAYPAL_URL . '/ipn/internal_ipn.php',
                'gateway_vars'  => implode("\n", $gateway_vars),
                'cart_id'       => $this->m_cart_id,
                'uid'           => $_USER['uid'],
            ) );
            $T->parse('checkout_btn', 'checkout');
            return $T->finish($T->get_var('checkout_btn'));
        }
        // Else, if amount > 0, regular checkout button
        if ($gw->Supports('checkout')) {
            return $gw->checkoutButton($this);
        } else {
            return 'Gateway does not support checkout';
        }
    }


    /**
    *   Get the payment gateway checkout buttons.
    *
    *   @uses   PaymentGw::CheckoutButton()
    *   @return string      HTML for checkout buttons
    */
    public function getCheckoutButtons()
    {
        global $_PP_CONF;

        $gateway_vars = '';
        if ($_PP_CONF['anon_buy'] || !COM_isAnonUser()) {
            foreach (Gateway::getAll() as $gw) {
                if ($gw->Supports('checkout')) {
                    $gateway_vars .= '<div class="paypalCheckoutButton">' .
                        $gw->CheckoutButton($this) . '</div>';
                }
            }
        } else {
            $L = PP_getTemplate('btn_login_req', 'login');
            $L->parse('login_btn', 'login');
            $gateway_vars = $L->finish($L->get_var('login_btn'));
        }
        return $gateway_vars;
    }


    /**
    *   Get the payment gateway checkout buttons.
    *   If there is only one possible gateway, pre-select it. If more than one
    *   then leave all unselected, unless m_info['gateway'] has already been
    *   set for this order.
    *
    *   @uses   PaymentGw::CheckoutButton()
    *   @return string      HTML for checkout buttons
    */
    public function getCheckoutRadios()
    {
        global $_PP_CONF;

        $retval = '';
        $T = PP_getTemplate('gw_checkout_select', 'radios');
        $T->set_block('radios', 'Radios', 'row');
        if ($_PP_CONF['anon_buy'] || !COM_isAnonUser()) {
            $gateways = Gateway::getAll();
            if ($_PP_CONF['gc_enabled']) {
                $gateways['_coupon'] = Gateway::getInstance('_coupon');
            }
            $gc_bal = $_PP_CONF['gc_enabled'] ? Coupon::getUserBalance() : 0;
            if (empty($gateways)) return NULL;  // no available gateways
            if (isset($this->m_info['gateway']) && array_key_exists($this->m_info['gateway'], $gateways)) {
                // Select the previously selected gateway
                $gw_sel = $this->m_info['gateway'];
            } elseif ($gc_bal >= $this->total) {
                // Select the coupon gateway as full payment
                $gw_sel = '_coupon';
            } else {
                // Select the first if there's one, otherwise select none.
                $gw_sel = '';
            }
            foreach ($gateways as $gw) {
                COM_errorLog(print_r($gw,true));
                if (is_null($gw)) {
                    var_dump($gateways);die;
                    echo "bad gw";die;
                    continue;
                }
                //COM_errorLog("supports: " . print_r($gw->Supports('checkout'),true));
                if ($gw->Supports('checkout')) {
                    if ($gw_sel == '') $gw_sel = $gw->Name();
                    $T->set_var(array(
                        'gw_id' => $gw->Name(),
                        'radio' => $gw->checkoutRadio($gw_sel == $gw->Name()),
                    ) );
                    $T->parse('row', 'Radios', true);
                }
            }
        }
        $T->parse('output', 'radios');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
    *   Check if an item already exists in the cart.
    *   This can be used to determine whether to add the item or not.
    *   Check for "false" return value as the return may be zero for the
    *   first item in the cart.
    *
    *   @param  string  $item_id    Item ID to check, e.g. "1|2,3,4"
    *   @param  array   $extras     Option custom values, e.g. text fields
    *   @return mixed       Item cart ID if item exists in cart, False if not
    */
    public function XContains($item_id, $extras=array())
    {
        foreach ($this->m_cart as $id=>$info) {
            if ($info['item_id'] == $item_id) {
                if (array_key_exists('extras', $info)) {
                    if ($info['extras'] == $extras) {
                        return $id;
                    } else {
                        return false;
                    }
                }
                return $id;
            }
        }
        return false;
    }


    /**
    *   Check if there are any items in the cart
    *
    *   @return boolean     True if cart is NOT empty, False if it is
    */
    public function hasItems()
    {
        return empty($this->items) ? false : true;
    }


    /**
    *   Create the cart ID.
    *   Since it's transmitted in cleartext, it'd be a good idea to
    *   use something more "encrypted" than just the session ID.
    *   On the other hand, it can't be too random since it needs to be
    *   repeatable.
    *
    *   @return string  Cart ID
    */
    protected static function X_makeID()
    {
        global $_TABLES;
        do {
            $id = COM_makeSid();
        } while (DB_getItem($_TABLES['paypal.orders'], 'order_id', "order_id = '$id'") !== NULL);
        return $id;
    }


    /**
    *   Get the cart ID.
    *   Returns either the native cart ID, or a version escaped for SQL
    *
    *   @param  boolean $escape True to escape return value for DB
    *   @return string      Cart ID string
    */
    public function cartID($escape=false)
    {
        if ($escape)
            return DB_escapeString($this->order_id);
        else
            return $this->order_id;
    }


    /**
    *   Set the address values for a single address
    *
    *   @param  array   $A      Array of address elements
    *   @param  string  $type   Type of address, billing or shipping
    */
    public function setAddress($A, $type = 'billto')
    {
        global $_TABLES;

        if ($type != 'billto') $type = 'shipto';

        $this->m_info[$type] = array();
        $this->m_info[$type]['addr_id'] = isset($A['addr_id']) ?
            (int)$A['addr_id'] : '0';
        $var = $type . '_id';
        $this->$var = PP_getVar($A, 'addr_id', 'integer', 0);
        foreach($this->_addr_fields as $fld) {
            $var = $type . '_' . $fld;
            $this->$var = isset($A[$fld]) ? htmlspecialchars($A[$fld]) : '';
        }
        $this->Save();
    }


    /**
    *   Get the requested address array.
    *
    *   @param  string  $type   Type of address, billing or shipping
    *   @return array           Array of address elements
    */
    public function getAddress($type)
    {
        if ($type != 'billto') $type = 'shipto';
        return isset($this->m_info[$type]) ? $this->m_info[$type] : array();
    }


    /**
    *   Get a cart for a given user
    *   Gets the latest cart, and cleans up extra carts that may accumulate
    *   due to expired sessions
    */
    public static function getCart($uid = 0)
    {
        global $_USER, $_TABLES, $_PP_CONF, $_PLUGIN_INFO;

        // Guard against invalid SQL if the DB hasn't been updated
        if (!PP_isMinVersion('0.6.0')) return NULL;

        $cart_id = NULL;
        $uid = $uid > 0 ? (int)$uid : (int)$_USER['uid'];
        if (COM_isAnonUser()) {
            $cart_id = self::getAnonCartID();
            /*if ($cart_id === NULL) {
                $cart_id = self::_makeID();
            }*/
        } else {
            $cart_id = DB_getItem($_TABLES['paypal.orders'], 'order_id',
                "uid = $uid AND status = 'cart' ORDER BY last_mod DESC limit 1");
            if (!empty($cart_id)) {
                // For logged-in usrs, delete superfluous carts
                DB_query("DELETE FROM {$_TABLES['paypal.orders']}
                    WHERE uid = $uid
                    AND status = 'cart'
                    AND order_id <> '" . DB_escapeString($cart_id) . "'");
            }
        }
        return $cart_id;
    }


    /**
    *   Create the Paypal session var if it doesn't exist
    */
    public static function initSession()
    {
        if (!isset($_SESSION[self::$session_var])) {
            $_SESSION[self::$session_var] = array(
                'cart_id' => '',
                //'items' => array(),
            );
        }
    }


    /**
    *   Add a session variable.
    *
    *   @param  string  $key    Name of variable
    *   @param  mixed   $value  Value to set
    */
    public static function setSession($key, $value)
    {
        $_SESSION[self::$session_var][$key] = $value;
    }


    /**
    *   Retrieve a session variable
    *
    *   @param  string  $key    Name of variable
    *   @return mixed       Variable value, or NULL if it is not set
    */
    public static function getSession($key)
    {
        if (isset($_SESSION[self::$session_var][$key])) {
            return $_SESSION[self::$session_var][$key];
        } else {
            return NULL;
        }
    }


    /**
    *   Remove a session variable
    *
    *   @param  string  $key    Name of variable
    */
    public static function clearSession($key)
    {
        unset($_SESSION[self::$session_var][$key]);
    }


    /**
    *   Get the gift card amount applied to this cart
    *
    *   @return float   Gift card amount
    */
    public function getGC()
    {
        return $this->m_info['apply_gc'];
    }


    /**
    *   Apply a gift card amount to this cart
    *
    *   @param  float   $amt    Amount of credit to apply
    */
    public function setGC($amt)
    {
        global $_TABLES;

        $amt = (float)$amt;
        if ($amt == -1) {
            $gc_bal = Coupon::getUserBalance();
            $amt = min($gc_bal, Coupon::canPayByGC($this));
        }
        $this->setInfo('apply_gc', $amt);
    }


    /**
    *   Set the chosen payment gateway into the cart information.
    *   Used so the gateway will be pre-selected if the buyer returns to the
    *   cart update page.
    *
    *   @param  string  $gw_name    Gateway name
    */
    public function setGateway($gw_name)
    {
        $this->m_info['gateway'] = $gw_name;
    }


    /**
    *   Check if this cart has any physical items.
    *   Not currently used, may be used later to adapt workflows based on
    *   product types
    *
    *   @return boolean     True if at least one physical product is present
    */
    public function hasPhysical()
    {
        foreach ($this->m_cart as $id=>$item) {
            if ($item['type'] & PP_PROD_PHYSICAL == PP_PROD_PHYSICAL)
                return true;
        }
        return false;
    }


    /**
    *   Delete any cart(s) for a user.
    *
    *   @param  integer $uid    User ID
    */
    public static function deleteUser($uid)
    {
        global $_TABLES;
        DB_delete($_TABLES['paypal.cart'], 'cart_uid', $uid);
        PAYPAL_debug("All carts for user {$uid} deleted");
    }


    /**
    *   Delete a specific cart.
    *   Called after an order is created from the cart items.
    *
    *   @param  string  $cart_id    Cart ID
    */
    public function Xdelete()
    {
        global $_TABLES, $_PP_CONF;

        if (isset($_PP_CONF['develop']) && $_PP_CONF['develop']) {
            return;
        }
        DB_delete($_TABLES['paypal.cart'], 'cart_id', $this->m_cart_id);
        PAYPAL_debug("Cart {$this->m_cart_id} deleted");
    }


    /**
    *   Set the anonymous user's cart ID.
    *   Used so the anonymous cart can be located and merged when a user
    *   logs in.
    *
    *   @param  string  $cart_id    Cart ID
    */
    public static function setAnonCartID($cart_id)
    {
        setcookie(self::$session_var, $cart_id, 0, '/');
    }


    /**
    *   Delete the anonymous user's cart.
    *   This is done after merging the cart during login to prevent it from
    *   being left behind and possibly re-merged during a subsequent login
    */
    public static function delAnonCart()
    {
        global $_TABLES;

        $cart_id = self::getAnonCartID();
        if ($cart_id) {
            // Remove the cookie
            unset($_COOKIE[self::$session_var]);
            setcookie(self::$session_var, '', time()-3600, '/');
            // And delete the cart record
            Order::Delete($cart_id);
            /*DB_delete($_TABLES['paypal.orders'],
                array('order__id', 'uid', 'is_cart'),
                array($cart_id, 1, 1)
            );*/
        }
    }


    /**
    *   Get the anonymous user's cart ID from the cookie
    *
    *   @return mixed   Cart ID, or Null if not set
    */
    public static function getAnonCartID()
    {
        if (isset($_COOKIE[self::$session_var]) && !empty($_COOKIE[self::$session_var])) {
            return $_COOKIE[self::$session_var];
        } else {
            return NULL;
        }
    }


    /**
    *   Set the is_order flag in a cart to indicate that it is finalized
    *   and submitted for payment.
    *   Also removes the cart_id cookie for anonymous users.
    *
    *   @param  string  $cart_id    Cart ID to update
    *   @param  boolean $status     Status to set, currently only "true"
    */
    public static function setFinal($cart_id, $status=true)
    {
        global $_TABLES;

        $status = false;        // DEBUG
        $status = $status ? 1 : 0;
        $cart_id = DB_escapeString($cart_id);
        $sql = "UPDATE {$_TABLES['paypal.cart']}
                SET is_order = $status
                WHERE cart_id = '{$cart_id}'";
        DB_query($sql);
        if ($status == 1) {
            unset($_COOKIE[self::$session_var]);
            // Make sure the cookie gets deleted also
            setcookie(self::$session_var, '', time()-3600, '/');
        } else {
            // restoring the cart, put back the cookie
            self::setAnonCartID($cart_id);
        }
        return DB_error() ? 1 : 0;
    }

}   // class Cart

?>

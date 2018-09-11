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
     * Merge the saved cart for Anonymous into the current user's cart.
     * Saves the updated cart to the database
     *
     * @param   string  $anon_cart      Anonymous cart ID
     * @return  array       Current cart contents
     */
    public function Merge($cart_id)
    {
        global $_TABLES, $_USER;

        if ($_USER['uid'] < 2) return;

        $AnonCart = self::getInstance(1, $cart_id);
        if (!empty($AnonCart->items)) {
            $sql = "UPDATE {$_TABLES['paypal.purchases']}
                    SET order_id = '" . DB_escapeString($this->order_id) . "'
                    WHERE order_id = '" . DB_escapeString($AnonCart->order_id) . "'";
            DB_query($sql);
        }
        self::delAnonCart();    // Delete to avoid re-merging
        return $this->Cart();
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
        if ($P->cartCanAccumulate()) {
            $have_id = $this->Contains($item_id, $extras);
        } else {
            $have_id = false;
        }
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
            //COM_errorLog(print_r($tmp,true));
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
    *   @param  string  $item_number    Product ID of item to update
    *   @param  array   $updates        Array (field=>value) of new values
    */
    public function updateItem($item_number, $updates)
    {
        // Search through the cart for the item number
        foreach ($this->items as $id=>$item) {
            if ($item->product_id == $item_number) {
                // If the item is found, loop through the updates and apply
                foreach ($updates as $fld=>$val) {
                    $this->items[$id]->$fld = $val;
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
            $this->items[$id]->setQuantity($value);
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
        if (isset($A['gateway'])) {
            $this->setGateway($A['gateway']);
        }
        if (isset($A['by_gc'])) {
            $this->setGC($A['by_gc']);
        }
        $this->Save();  // Save cart vars, if changed, and update the timestamp
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
     * Save the cart. Logging is disabled for cart updates.
     *
     * @param   boolean $log    True to log the update, False for silent update
     * @return  string      Order ID
     */
    public function Save($log = true)
    {
        return parent::Save(false);
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
        global $_TABLES;

        if (isset($this->items[$id])) {
            DB_delete($_TABLES['paypal.purchases'], 'id', (int)$id);
            unset($this->items[$id]);
            $this->Save();  // just to update timestamp
        }
        return $this->items;
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

        if ($this->status != 'cart') return $this->Cart();

        DB_delete($_TABLES['paypal.purchases'], 'order_id', $this->cartID());
        if ($del_order) {
            DB_delete($_TABLES['paypal.orders'], 'order_id', $this->cartID());
            self::delAnonCart();
        }
        return array();
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
        $by_gc = (float)$this->getInfo('apply_gc');
        $net_total = $this->total - $by_gc;
        // Special handling if there is a zero total due to discounts
        // or gift cards
        if ($net_total < .001) {
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
        } elseif ($gw->Supports('checkout')) {
            // Else, if amount > 0, regular checkout button
            $this->custom_info['by_gc'] = $by_gc;  // pass GC amount used via gateway
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
                //COM_errorLog(print_r($gw,true));
                if (is_null($gw)) {
                //    var_dump($gateways);die;
                //    echo "bad gw";die;
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
    *   Check if there are any items in the cart
    *
    *   @return boolean     True if cart is NOT empty, False if it is
    */
    public function hasItems()
    {
        return empty($this->items) ? false : true;
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
        $A = array();
        foreach ($this->_addr_fields as $fld) {
            $var = $type . '_' . $fld;
            $A[$fld] = $this->$var;
        }
        return $A;
        //return isset($this->m_info[$type]) ? $this->m_info[$type] : array();
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
        if (!PP_isMinVersion()) return NULL;

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
    *   Delete any cart(s) for a user.
    *
    *   @param  integer $uid    User ID
    */
    public static function deleteUser($uid)
    {
        global $_TABLES;
        DB_delete($_TABLES['paypal.orders'],
            array('status', 'uid'),
            array('cart',$uid ));
        PAYPAL_debug("All carts for user {$uid} deleted");
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
    *   Set the order status to indicate that this is no longer a cart and has
    *   been submitted for payment.
    *   Pass $status = false to revert back to a cart, e.g. if the purchase is
    *   cancelled.
    *   Also removes the cart_id cookie for anonymous users.
    *
    *   @param  string  $cart_id    Cart ID to update
    *   @param  boolean $status     Status to set, currently only "true"
    */
    public static function setFinal($cart_id, $status=true)
    {
        global $_TABLES;

        $status = $status ? 'pending' : 'cart';
        $cart_id = DB_escapeString($cart_id);
        $sql = "UPDATE {$_TABLES['paypal.orders']} SET
                status = '{$status}',
                order_date = UNIX_TIMESTAMP()
                WHERE order_id = '{$cart_id}'";
        DB_query($sql);
        if ($status == 'pending') {
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

<?php
/**
*   Shopping cart class for the Paypal plugin.
*   The database is used to save the cart in two forms:
*   1 - the "cart" table holds the transient cart which is identifed by the
*   session ID. This allows for anonymous users to shop.
*   2 - During the login and logout process, the "userinfo" table is read or
*   updated so logged-in users can have a cart that transcends the PHP session.
*   The transient cart should be deleted upon logout, and merged into the user's
*   cart during login.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2016 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.7
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
class Cart
{
    /** Shopping cart contents.
        @var array */
    private $m_cart;

    /** Shopping cart ID.
        @var string */
    private $m_cart_id;

    //private $m_billto;
    //private $m_shipto;
    private $m_info;

    private $_addr_fields = array(
            'name', 'company', 'address1', 'address2', 
            'city', 'state', 'country', 'zip',
    );


    /**
    *   Constructor.
    *   Reads the cart contents from the "cart" table, if available.
    *   Does not read from the userinfo table- that's up to the uesr
    *   login and logout functions.
    */
    public function __construct($cart_id='', $interactive=true) 
    {
        global $_TABLES, $_PP_CONF, $_USER;

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
                $cart_id = self::makeCartID();
            }
            // Set the cart ID in the session and the local variable
            $_SESSION[PP_CART_VAR]['cart_id'] = $cart_id;
            $this->m_cart_id = $cart_id;
        } else {
            // For non-interactive sessions a cart ID must be provided
            $this->m_cart_id = $cart_id;
        }

        $this->m_cart = array();
        $this->m_info = array();
        $this->Load();
    }


    /**
    *   Get the cart contents as an array of items
    *
    *   @return array   Current cart contents
    */
    public function Cart() 
    {
        return $this->m_cart;
    }


    /**
    *   Set the cart contents.
    *   If a cart array is provided, then it is used.  Otherwise, the current
    *   user's cart is read from the database.  Either way, any current contents
    *   of $m_cart are overwritten.
    */
    public function Load()
    {
        $cart_id = $this->cartID(true);
        if ($cart_id == '') {
            return;
        }

        $data = $this->Read($cart_id);
        $this->m_info = $data['info'];
        $this->m_cart = $data['cart'];
    }


    public function Read($cart_id)
    {
        global $_TABLES;

        $sql = "SELECT cart_info, cart_contents
               FROM {$_TABLES['paypal.cart']}
               WHERE cart_id = '$cart_id'";
        //echo $sql;die;
        $info = array();
        $cart = array();
        $res = DB_query($sql);
        $A = DB_fetchArray($res, false);
        if (is_array($A)) {
            if (isset($A['cart_info']) && !empty($A['cart_info'])) {
                $info = @unserialize($A['cart_info']);
            }
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
        return array('info' => $info, 'cart' => $cart);
    }


    /**
    *   Merge the save cart from the database into the current cart.
    *   Normally used when a user logs in to add their saved cart items
    *   to any that might have been added while browsing anonymously.
    *   Saves the updated cart to the database
    *
    *   @return array       Current cart contents
    */
    public function Merge()
    {
        global $_TABLES, $_USER;

        if ($_USER['uid'] < 2) return;

        $txt = DB_getItem($_TABLES['paypal.userinfo'], 'cart',
                    'uid=' . (int)$_USER['uid']);

        if (!empty($txt)) {         // No saved cart
            $saved = @unserialize($txt);
            if (!$saved) return;    // Unable to unserialize
        }

        if (is_array($saved)) {
            // Add saved items to current cart
            foreach ($saved as $id=>$A) {
                list($item_id, $options) = PAYPAL_explode_opts($A['item_id']);
                $this->addItem($item_id, $A['name'], $A['descrip'], $A['quantity'], 
                            $A['price'], $options, $A['extras']);
            }
            $this->Save();
        }
        return $this->m_cart;
    }


    /**
    *   Save the current cart items to the database
    */
    public function Save()
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
                last_update = '{PAYPAL_now()->toMySql()}'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) COM_errorLog("Error saving cart for user $uid", 1);
    }


    /**
    *   Save the current cart items to the userinfo table for logged-in users
    */
    public function SaveUserCart()
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
        $sql = "INSERT INTO {$_TABLES['paypal.userinfo']} (uid, cart)
                    VALUES ($uid, '$cart')
                ON DUPLICATE KEY UPDATE 
                    cart = '$cart'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) COM_errorLog("Error saving cart for user $uid", 1);
        // TODO: Delete cart table contents?
    }


    /**
    *   Add a single item to the cart.
    *   Some values are straight from the item table, but may be overridden
    *   to handle special cases or customization.
    *
    *   @param  string  $id         Item ID
    *   @param  string  $descrip    Item Description
    *   @param  float   $quantity   Quantity
    *   @param  float   $price      Unit Price
    *   @param  array   $options    Product options (size, color, etc).
    *   @param  array   $extras     Extra cart items (shipping, etc.)
    *   @return integer             Current item quantity
    */
    public function addItem($item_id, $name, $descrip='', $quantity=1,
            $price=0, $options=array(), $extras=array())
    {
        $quantity = (float)$quantity;
        $item_id = trim($item_id);
        $name = trim($name);
        $descrip = trim($descrip);
        $price = (float)$price;

        if (!is_array($this->m_cart))
            $this->m_cart = array();

        // Extract the attribute IDs from the options array to create
        // the item_id
        if (is_array($options) && !empty($options)) {
            $opts = array();
            foreach($options as $optname=>$option) {
                list($opt_id, $opt_desc, $opt_price) = explode('|', $option);
                $opts[] = $opt_id;
            }
            $item_id .= '|' . implode(',', $opts);
        } else {
            $options = array();
        }

        // Look for identical items, including options (to catch 
        // attributes).  If found, just update the quantity.
        $have_id = $this->Contains($item_id, $extras);
        if ($have_id !== false) {
            $this->m_cart[$have_id]['quantity'] += $quantity;
            $new_quantity = $this->m_cart[$have_id]['quantity'];
        } else {
            $this->m_cart[] = array(
                'item_id'   => $item_id,
                'quantity'  => $quantity,
                'name'      => $name,
                'descrip'   => $descrip, 
                'price'     => sprintf("%.2f", $price),
                'options'   => $options,
                'extras'    => $extras,
            );
            $new_quantity = $quantity;
        }
        $this->Save();
        return $new_quantity;
    }


    /**
    *   Update the quantity for all cart items.
    *   Called from the View Cart form to update any quantities that have
    *   changed.
    *
    *   @see    Cart::UpdateQty()
    *   @param  array   $items  Array if items as itemID=>newQty
    *   @return array           Updated cart contents
    */
    public function UpdateAllQty($items)
    {
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $id=>$value) {
            $value = (float)$value;
            $this->UpdateQty($id, $value, false);
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


    public function setInstructions($text)
    {
        $this->m_info['order_instr'] = $text;
        $this->Save();
    }


    public function getInstructions()
    {
        return $this->m_info['order_instr'];
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
        global $_TABLES;

        $sql = "DELETE FROM {$_TABLES['paypal.cart']} WHERE
                cart_id = '" . DB_escapeString($this->cartID()) . "'";
        if (!COM_isAnonUser()) {
            $sql .= " OR cart_uid = " . (int)$_USER['uid'];
        }
        DB_query($sql);
        if ($del_order && isset($_SESSION[PP_CART_VAR]['order_id']) &&
            !empty($_SESSION[PP_CART_VAR]['order_id'])) {
            Order::Delete($_SESSION[PP_CART_VAR]['order_id']);
        }
        $this->m_cart = array();
        unset($_SESSION[PP_CART_VAR]);
        return $this->m_cart;
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
    public function View($checkout = false)
    {
        global $_CONF, $_PP_CONF, $_USER, $LANG_PP, $_TABLES, $_SYSTEM;

        $currency = new Currency();

        if (!isset($this->m_cart) ||
                empty($this->m_cart)) {
            return $LANG_PP['cart_empty'];
        }
        $T = new \Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file('cart', $checkout ? 'order.thtml' : 'viewcart.thtml');

        if ($checkout) {
            foreach ($_PP_CONF['workflows'] as $key => $value) {
                $T->set_var('have_' . $value, 'true');
                foreach ($this->_addr_fields as $fldname) {
                    $T->set_var($value . '_' . $fldname, 
                            $this->m_info[$value][$fldname]);
                }
            }
            $T->set_var('not_final', 'true');
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        // Get the workflows so we show the relevant info.
        if (!isset($_PP_CONF['workflows']) ||
            !is_array($_PP_CONF['workflows'])) {
            Workflow::Load();
        }

        $T->set_block('cart', 'ItemRow', 'iRow');
        $counter = 0;
        $subtotal = 0;
        $shipping = 0;
        foreach ($this->m_cart as $id=>$item) {
            $counter++;
            $attr_desc = '';
            list($item_id, $attr_keys) = PAYPAL_explode_opts($item['item_id']);

            if (is_numeric($item_id)) {
                // a catalog item, get the "right" price
                $P = new Product($item_id);
                $item_price = $P->getPrice($attr_keys, $item['quantity']);
                if (!empty($attr_keys)) {
                    foreach ($attr_keys as $attr_key) {
                        if (!isset($P->options[$attr_key])) continue;   // invalid?
                        //$attr_price = (float)$P->options[$attr_key]['attr_price'];
                        $attr_name = $P->options[$attr_key]['attr_name'];
                        $attr_value = $P->options[$attr_key]['attr_value'];
                        $attr_desc .= "<br />&nbsp;&nbsp;-- $attr_name: $attr_value";
                        /*if ($attr_price != 0) {
                            $item_price += $attr_price;
                        }*/
                    }
                }
                $text_names = explode('|', $P->custom);
                if (!empty($text_names) &&
                    is_array($item['extras']['custom'])) {
                    foreach ($item['extras']['custom'] as $tid=>$val) {
                        $attr_desc .= '<br />&nbsp;&nbsp;-- ' .
                            htmlspecialchars($text_names[$tid]) . ': ' .
                            htmlspecialchars($val);
                    }
                }
                $item['descrip'] .= $attr_desc;

                // Get shipping amount and weight
                if ($P->shipping_type == 2 && $P->shipping_amt > 0) {
                    // fixed shipping amount per item. Update actual cart
                    $this->m_cart[$id]['shipping'] = $P->shipping_amt * $item['quantity'];
                    $shipping += $this->m_cart[$id]['shipping']; // for display
                } elseif ($P->shipping_type == 1 && $P->weight > 0) {
                    // using gateway profile, save the item's weight in the cart
                    $this->m_cart[$id]['weight'] = $P->weight * $item['quantity'];
                }
                $this->m_cart[$id]['taxable'] = $P->taxable ? 'Y' : 'N';
                $this->m_cart[$id]['type'] = $P->prod_type;
            } else {
                // A plugin item, it's not something we can look up
                $item_price = (float)$item['price'];
                if (isset($item['extras']['shipping'])) {
                    $shipping += (float)$item['extras']['shipping'];
                    $this->m_cart[$id]['shipping'] = $item['extras']['shipping'];
                }
            }
            $item_total = $item_price * $item['quantity'];
            $T->set_var(array(
                'cart_item_id'  => $id,
                'pi_url'        => PAYPAL_URL,
                'cart_id'       => $item['item_id'],
                'pp_id'         => $counter,
                'item_id'       => $item_id,
                'item_descrip'  => $item['descrip'],
                'item_price'    => COM_numberFormat($item_price, 2),
                'item_quantity' => $item['quantity'],
                'item_total'    => COM_numberFormat($item_total, 2),
                'item_link'     => is_numeric($item_id) ? 'true' : '',
                'iconset'       => $_PP_CONF['_iconset'],
                'is_uikit'      => $_PP_CONF['_is_uikit'],
            ) );
            $T->parse('iRow', 'ItemRow', true);

            $subtotal += $item_total;
        }

        $custom_info = array(
                'uid'       =>$_USER['uid'], 
                'transtype' => 'cart_upload',
                'cart_id'   => $this->cartID(),
        );

        $total = $subtotal + $shipping;

        // A little hack to show only the total if there are no other
        // charges
        //if ($total == $subtotal) $subtotal = 0;

        // Format the TOC link, if any
        if (!empty($_PP_CONF['tc_link'])) {
            $tc_link = str_replace('{site_url}', $_CONF['site_url'], $_PP_CONF['tc_link']);
        } else {
            $tc_link = '';
        }
        $T->set_var(array(
            'paypal_url'        => $_PP_CONF['paypal_url'],
            'receiver_email'    => $_PP_CONF['receiver_email'][0],
            'custom'    => serialize($custom_info),
            'shipping'  => $shipping > 0 ? $currency->Format($shipping) : '',
            'subtotal'  => $subtotal > 0 ? $currency->Format($subtotal) : '',
            'total'     => $currency->Format($total),
            'order_instr' => htmlspecialchars($this->getInstructions()),
            'tc_link'  => $tc_link,
        ) );

        // If this is the final checkout, then show the payment buttons
        if ($checkout) {
            $T->set_var(array(
                'gateway_vars'  => $this->getCheckoutButtons(),
                'checkout'      => 'true',
            ) );
        }

        $T->parse('output', 'cart');
        $form = $T->finish($T->get_var('output'));

        return $form;
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
            $L = new \Template(PAYPAL_PI_PATH . '/templates/buttons');
            $L->set_file('login', 'btn_login_req.thtml');
            $L->parse('login_btn', 'login');
            $gateway_vars = $L->finish($L->get_var('login_btn'));
        }

        return $gateway_vars;
    }


    /**
    *   Check if an item already exists in the cart.
    *   This can be used to determine whether to add the item or not.
    *   Check for "false" return value as the return may be zero for the
    *   first item in the cart.
    *
    *   @param  string  $item_id    Item ID to check
    *   @param  array   $extras     Option custom values, e.g. text fields
    *   @return mixed       Item cart ID if item exists in cart, False if not
    */
    public function Contains($item_id, $extras=array())
    {
        foreach ($this->m_cart as $id=>$info) {
            if ($info['item_id'] == $item_id) {
                if (!empty($extras)) {
                    if ($info['extras']['custom'] == $extras['custom']) {
                        return $id;
                    }
                } else {
                    return $id;
                }
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
        return empty($this->m_cart) ? false : true;
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
    public function makeCartID()
    {
        global $_CONF;

        return md5(session_id() . $_CONF['site_name']);
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
            return DB_escapeString($this->m_cart_id);
        else
            return $this->m_cart_id;
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

        foreach($this->_addr_fields as $fld) {
            $this->m_info[$type][$fld] = isset($A[$fld]) ?
                        htmlspecialchars($A[$fld]) : '';
        }
            /*'name'      => isset($A['name']) ? $A['name'] : '',
            'company'   => isset($A['company']) ? $A['company'] : '',
            'address1'  => isset($A['address1']) ? $A['address1'] : '',
            'address2'  => isset($A['address2']) ? $A['address2'] : '',
            'city'      => isset($A['city']) ? $A['city'] : '',
            'state'     => isset($A['state']) ? $A['state'] : '',
            'zip'       => isset($A['zip']) ? $A['zip'] : '',
            'country'   => isset($A['country']) ? $A['country'] : '',
        );*/

        // Serialize the address and update the current cart with it
        $info = @serialize($this->m_info);
        if ($info) {
            $info = DB_escapeString($info);
            DB_query("UPDATE {$_TABLES['paypal.cart']}
                    SET cart_info = '$info'
                    WHERE cart_id = '" . $this->cartID(true) . "'");
        }
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
        return $this->m_info[$type];
    }


    /**
    *   Get a cart for a given user
    *   Gets the latest cart, and cleans up extra carts that may accumulate
    *   due to expired sessions
    */
    public function getCart($uid = 0)
    {
        global $_USER, $_TABLES;

        $cart_id = NULL;
        $uid = $uid > 0 ? (int)$uid : (int)$_USER['uid'];

        if (COM_isAnonUser()) {
            if (!empty($_SESSION[PP_CART_VAR]['cart_id'])) {
                $cart_id = $_SESSION[PP_CART_VAR]['cart_id'];
            }
        } else {
            $cart_id = DB_getItem($_TABLES['paypal.cart'], 'cart_id',
                "cart_uid = $uid ORDER BY last_update DESC limit 1");
            if (!empty($cart_id)) {
                DB_query("DELETE FROM {$_TABLES['paypal.cart']}
                    WHERE cart_id <> '" . DB_escapeString($cart_id) . "'");
            }
        }
        return $cart_id;
    }


    /**
    *   Create the Paypal session var if it doesn't exist
    */
    public static function initSession()
    {
        if (!isset($_SESSION['glPPcart'])) {
            $_SESSION['glPPcart'] = array(
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
        $_SESSION['glPPcart'][$key] = $value;
    }


    /**
    *   Retrieve a session variable
    *
    *   @param  string  $key    Name of variable
    *   @return mixed       Variable value, or NULL if it is not set
    */
    public static function getSession($key)
    {
        if (isset($_SESSION['glPPcart'][$key])) {
            return $_SESSION['glPPcart'][$key];
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
        unset($_SESSION['glPPcart'][$key]);
    }

}   // class Cart

?>

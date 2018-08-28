<?php
/**
*   Payment gateway class.
*   Provides the base class for actual payment gateway classes to use.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for Paypal payment gateway
*   Provides common variables and methods required by payment gateway classes
*   @package paypal
*   @since  0.5.0
*/
class Gateway
{
    /** Property fields.  Accessed via __set() and __get().
    *   This is for configurable properties of the gateway- URL, testing
    *   mode, etc.  Charactistics of the gateway itself (order, enabled, name)
    *   are held in protected class variables below.
    *
    *   @var array
    */
    protected $properties;

    /** Items on this order.
    *   @var array
    */
    protected $items;

    /** Error string or value, to be accessible by the calling routines.
    *   @var mixed
    */
    public  $Error;

    /** The short name of the gateway, e.g. "paypal" or "amazon"
    *   @var string
    */
    protected $gw_name;

    /** The long name or description of the gateway, e.g. "Amazon SimplePay"
    *   @var string
    */
    protected $gw_desc;

    /** Services (button types) provided by the gateway.
    *   This is an array of button_name=>0/1 to indicate which services are
    *   available.
    *   @var array
    */
    protected $services = NULL;

    /** The gateway's configuration items.  This is an associative array of
    *   name=>value elements.
    *   @var array
    */
    protected $config = array();

    /** The URL to the gateway's IPN processor.
    *   @var string
    */
    protected $ipn_url;

    /** Indicator of whether the gateway is enabled at all.
    *   @var boolean
    */
    protected $enabled;

    /** Order in which the gateway is selected.  Gateways are selected from
    *   lowest to highest order.
    *   @var integer
    */
    protected $orderby;

    /** This is an array of custom data to be passed to the gateway.
    *   How it is passed is up to the gateway, which uses the PrepareCustom()
    *   function to get the array data into the desired format. AddCustom()
    *   can be used to add items to the array.
    *   @var array
    */
    protected $custom;

    /** The URL to the payment gateway.  This must be set by the derived class.
    *   getActionUrl() can be overriden by the derived class to apply additional
    *   logic to the url before it is used to create payment buttons.
    *   @var string
    */
    protected $gw_url;

    /**
    *   The postback URL for verification of IPN messages. If not set
    *   the value of gw_url will be used.
    */
    protected $postback_url = NULL;

    protected $button_url = '';

    private static $gateways = array();

    /**
    *   Constructor.
    *   Initializes variables.
    *   Derived classes should set the gw_name, gw_desc and config values
    *   before calling this constructor, to make sure these properties are
    *   correct.  This function merges the config items read from the database
    *   into the existing config array.
    *
    *   Optionally, the child can also create the services array if desired,
    *   to limit the services provided.
    *
    *   @uses   AddCustom()
    *   @param  array   $A  Optional array of fields, used with getInstance()
    */
    function __construct($A = array())
    {
        global $_PP_CONF, $_TABLES, $_USER;

        $this->properties = array();
        $this->items = array();
        $this->custom = array();
        $this->ipn_url = PAYPAL_URL . '/ipn/' . $this->gw_name . '.php';
        $this->currency_code = empty($_PP_CONF['currency']) ? 'USD' :
                $_PP_CONF['currency'];

        // The child gateway can override the services array.
        if (!isset($this->services)) {
            $this->services = array(
                'buy_now'   => 0,
                'donation'  => 0,
                'pay_now'   => 0,
                'subscribe' => 0,
                'checkout'  => 0,
                'external'  => 0,
            );
        }

        if (empty($A)) {
            $sql = "SELECT *
                FROM {$_TABLES['paypal.gateways']}
                WHERE id = '" . DB_escapeString($this->gw_name) . "'";
            $res = DB_query($sql);
            if ($res) $A = DB_fetchArray($res, false);
        }
        if (!empty($A)) {
            $this->orderby = (int)$A['orderby'];
            $this->enabled = (int)$A['enabled'];
            $services = @unserialize($A['services']);
            if ($services) {
                $this->services = array_merge($this->services, $services);
            }
            $props = @unserialize($A['config']);
            if ($props) {
                $this->config = array_merge($this->config, $props);
            }
        }

        // The user ID is usually required, and doesn't hurt to add it here.
        $this->AddCustom('uid', $_USER['uid']);

        if ($this->postback_url === NULL) {
            $this->postback_url = $this->gw_url;
        }
    }


    /**
    *   Magic getter function.
    *   Returns the requeste value if set, otherwise returns NULL.
    *   Note that derived classes must define their own __set() function.
    *
    *   @param  string  $key    Name of property to return
    *   @return mixed   property value if defined, otherwise returns NULL
    */
    public function __get($key)
    {
        switch ($key) {
        case 'buy_now':
        case 'pay_now':
        case 'donation':
        case 'subscribe':
            if (isset($this->services[$key])) {
                return $this->services[$key];
            } else {
                return NULL;
            }
            break;
        default:
            if (isset($this->properties[$key])) {
                return $this->properties[$key];
            } else {
                return NULL;
            }
            break;
        }
    }


    /**
    *   Return the gateway short name.
    *
    *   @return string      Short name of gateway
    */
    public function Name()
    {
        return $this->gw_name;
    }


    /**
    *   Return the gateway short name, capitlaized for display.
    *
    *   @return string      Short name of gateway
    */
    public function DisplayName()
    {
        return ucfirst($this->gw_name);
    }


    /**
    *   Return the gateway description
    *
    *   @return string      Full name of the gateway
    */
    public function Description()  {   return $this->gw_desc;  }


    /**
    *   Get a single buy_now-type button from the database.
    *
    *   @param  string  $item_id    Item ID
    *   @param  string  $btn_key    Button Key, btn_type + price
    *   @return string      Button code, or empty if not available
    */
    protected function _ReadButton($P, $btn_key)
    {
        global $_TABLES;

        $pi_name = DB_escapeString($P->pi_name);
        $item_id = DB_escapeString($P->item_id);
        $btn_key = DB_escapeString($btn_key);
        $btn  = DB_getItem($_TABLES['paypal.buttons'], 'button',
                "pi_name = '{$pi_name}' AND item_id = '{$item_id}' AND
                gw_name = '{$this->gw_name}' AND btn_key = '{$btn_key}'");
        return $btn;
    }


    /**
    *   Save a single button to the button cache table.
    *
    *   @param  string  $item_id    ID of item for this button
    *   @param  string  $btn_type   Button type
    *   @param  string  $btn_value  HTML code for this button
    */
    protected function _SaveButton($P, $btn_key, $btn_value)
    {
        global $_TABLES;

        $pi_name = DB_escapeString($P->pi_name);
        $item_id = DB_escapeString($P->item_id);
        $btn_key = DB_escapeString($btn_key);
        $btn_value = DB_escapeString($btn_value);

        $sql = "INSERT INTO {$_TABLES['paypal.buttons']}
                (pi_name, item_id, gw_name, btn_key, button)
            VALUES
                ('{$pi_name}', '{$item_id}', '{$this->gw_name}', '{$btn_key}', '{$btn_value}')
            ON DUPLICATE KEY UPDATE
                button = '{$btn_value}'";
        //echo $sql;die;
        DB_query($sql);
    }


    /**
    *   Save the gateway config variables.
    *
    *   @uses   ReOrder()
    *   @param  array   $A      Array of config items, e.g. $_POST
    *   @return boolean         True if saved successfully, False if not
    */
    protected function SaveConfig($A)
    {
        global $_TABLES;

        $this->enabled = isset($A['enabled']) ? 1 : 0;
        $this->orderby = (int)$A['orderby'];
        $services = PP_getVar($A, 'service', 'array');
        $config = @serialize($this->config);
        if (!$config) return false;

        $config = DB_escapeString($config);
        $services = DB_escapeString(@serialize($services));
        $id = DB_escapeString($this->gw_name);

        $sql = "UPDATE {$_TABLES['paypal.gateways']} SET
                config = '$config',
                services = '$services',
                orderby = '{$this->orderby}',
                enabled = '{$this->enabled}'
                WHERE id='$id'";
        //echo $sql;die;
        DB_query($sql);
        self::ClearButtonCache();   // delete all buttons for this gateway
        if (DB_error()) {
            return false;
        } else {
            $this->_postConfigSave();   // Run function for further setup
            Cache::clear('gateways');
            self::Reorder();
            return true;
        }
    }


    /**
    *   Toggles a boolean value from zero or one to the opposite value
    *
    *   @param  integer $oldvalue   Original value, 1 or 0
    *   @param  string  $varname    Field name to set
    *   @param  integer $id         ID number of element to modify
    *   @return integer             New value, or old value upon failure
    */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        $id = DB_escapeString($id);
        $varname = DB_escapeString($varname);
        $oldvalue = $oldvalue == 0 ? 0 : 1;

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['paypal.gateways']}
                SET $varname=$newvalue
                WHERE id='$id'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            return $oldvalue;
        } else {
            Cache::clear('gateways');
            return $newvalue;
        }
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @uses   _toggle()
    *   @param  integer $oldvalue   Original value
    *   @param  integer $id         ID number of element to modify
    *   @return integer             New value, or old value upon failure
    */
    public static function toggleEnabled($oldvalue, $id)
    {
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
    *   Sets the "buy_now" field to the specified value.
    *
    *   @uses   _toggle()
    *   @param  integer $oldvalue    Original value
    *   @param  integer $id          ID number of element to modify
    *   @return integer              New value, or old value upon failure
    */
    public static function toggleBuyNow($oldvalue, $id)
    {
        return self::_toggle($oldvalue, 'buy_now', $id);
    }


    /**
    *   Sets the "donation" field to the specified value.
    *
    *   @uses   _toggle()
    *   @param  integer $oldvalue    Original value
    *   @param  integer $id          ID number of element to modify
    *   @return integer              New value, or old value upon failure
    */
    public static function toggleDonation($oldvalue, $id)
    {
        return self::_toggle($oldvalue, 'donation', $id);
    }


    /**
    *   Reorder all gateways
    */
    public static function ReOrder()
    {
        global $_TABLES;

        $sql = "SELECT id, orderby
                FROM {$_TABLES['paypal.gateways']}
                ORDER BY orderby ASC;";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $sql = "UPDATE {$_TABLES['paypal.gateways']}
                    SET orderby = '$order'
                    WHERE id = '" . DB_escapeString($A['id']) . "'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
        Cache::clear('gateways');
    }


    /**
    *   Move a gateway definition up or down the admin list.
    *
    *   @param  string  $id     Gateway IDa
    *   @param  string  $where  Direction to move (up or down)
    */
    public static function moveRow($id, $where)
    {
        global $_TABLES;

        switch ($where) {
        case 'up':
            $oper = '-';
            break;
        case 'down':
            $oper = '+';
            break;
        default:
            $oper = '';
            break;
        }

        if (!empty($oper)) {
            $id = DB_escapeString($id);
            $sql = "UPDATE {$_TABLES['paypal.gateways']}
                    SET orderby = orderby $oper 11
                    WHERE id = '$id'";
            //echo $sql;die;
            DB_query($sql);
            self::ReOrder();
        }
    }


    /**
    *   Clear the cached buttons for this payment gateway
    */
    function ClearButtonCache()
    {
        global $_TABLES;

        DB_delete($_TABLES['paypal.buttons'], 'gw_name', $this->gw_name);
    }


    /**
    *   Install a new gateway into the gateways table
    *   The gateway has to be instantiated, then called as
    *       $newGateway->Install()
    *   The config values set by the gateways constructor will be saved.
    *
    *   @return boolean     True on success, False on failure
    */
    public function Install()
    {
        global $_TABLES;

        // Only install the gateway if it isn't already installed
        $installed = self::getAll(false);
        if (!array_key_exists($this->gw_name, $installed)) {
            if (is_array($this->config)) {
                $config = @serialize($this->config);
            } else {
                $config = '';
            }
            if (is_array($this->services)) {
                $services = @serialize($this->services);
            } else {
                $services = '';
            }
            $sql = "INSERT INTO {$_TABLES['paypal.gateways']}
                (id, orderby, enabled, description, config, services) VALUES (
                '" . DB_escapeString($this->gw_name) . "',
                '999',
                '0',
                '" . DB_escapeString($this->gw_desc) . "',
                '" . DB_escapeString($config) . "',
                '" . DB_escapeString($services) . "')";
            DB_query($sql);
            Cache::clear('gateways');
            return DB_error() ? false : true;
        }
        return false;
    }


    /**
    *   Remove the current gateway.
    *   This removes all of the configuration for the gateway, but not files.
    */
    public function Remove()
    {
        global $_TABLES;

        $this->ClearButtonCache();
        DB_delete($_TABLES['paypal.gateways'], 'id', $this->gw_name);
        Cache::clear('gateways');
    }


    /**
    *   Get the checkboxes for the button types in the configuration form.
    *
    *   @return string      HTML for checkboxes
    */
    protected function getServiceCheckboxes()
    {
        global $LANG_PP;

        $T = PP_getTemplate('gw_servicechk', 'tpl');
        $T->set_block('tpl', 'ServiceCheckbox', 'cBox');
        foreach ($this->services as $name => $value) {
            $T->set_var(array(
                'text'      => $LANG_PP['buttons'][$name],
                'name'      => $name,
                'checked'   => $value == 1 ? 'checked="checked"' : '',
            ) );
            $T->parse('cBox', 'ServiceCheckbox', true);
        }
        $T->parse('output', 'tpl');
        return $T->finish($T->get_var('output'));
    }


    /**
    *   Check if the current gateway supports a specific button type
    *
    *   @uses   self::Supports()
    *   @param  string  $btn_type   Button type to check
    *   @param  array   $gw_info    Array from $_PP_CONF, empty to check current
    *   @return boolean             True if the button is supported
    */
    protected function _Supports($btn_type)
    {
        $arr_parms = array(
            'enabled' => $this->enabled,
            'services' => $this->services,
        );
        return self::Supports($btn_type, $arr_parms);
    }


    /**
    *   Check if a gateway from the $_PP_CONF array supports a button type.
    *   The $gw_info parameter should be the array of info for a single gateway
    *   if only that gateway is to be checked.
    *
    *   @param  string  $btn_type   Button type to check
    *   @param  array   $gw_info    Array from $_PP_CONF, empty to check current
    *   @return boolean             True if the button is supported
    */
    public static function XSupports($btn_type, $gw_info = '')
    {
        $retval = false;
        if (is_array($gw_info)) {
            // if an array is passed in, check it for the button type
            if ($gw_info['enabled'] == 1 &&
                    isset($gw_info['services'][$btn_type]))
                $retval = true;
        }
        return $retval;
    }

    public function Supports($btn_type)
    {
        return isset($this->services[$btn_type]) ? true : false;
    }


    /**
    *   Load a gateway's language file.
    *   The language variable should be $LANG_PP_<gwname> and should be
    *   declared "global" in the language file.
    */
    protected function LoadLanguage()
    {
        global $_CONF;

        $langfile = $this->gw_name . '_' . $_CONF['language'] . '.php';
        if (!is_file(PAYPAL_PI_PATH . '/language/' . $langfile)) {
            $langfile = $this->gw_name . '_english.php';
        }
        global $LANG_PP_gateway;
        include_once PAYPAL_PI_PATH . '/language/' . $langfile;
        return $LANG_PP_gateway;
    }


    /**
    *   Return the order status to be set when an IPN message is received.
    *   The default is to mark the order "closed" for downloadable items,
    *   since no further processing is needed, and "paid" for other items.
    *
    *   @param  integer $prod_types     A single value made by OR'ing the types
    *   @return string          Status of the order
    */
    public function getPaidStatus($types)
    {
        if ($types == PP_PROD_DOWNLOAD) {
            // Only downloadable items
            $retval = 'closed';
        } else {
            // Physical and/or Other Virtual items
            $retval = 'paid';
        }
        return $retval;
    }


    /**
    *   Processes the purchase, for purchases made without an IPN message.
    *
    *   @param  array   $vals   Submitted values, e.g. $_POST
    */
    public function handlePurchase($vals = array())
    {
        global $_TABLES, $_CONF, $_PP_CONF;

        USES_paypal_functions();

        if (!empty($vals['cart_id'])) {
            $cart = new Cart($vals['cart_id']);
            if (!$cart->hasItems()) return; // shouldn't be empty
            $items = $cart->Cart();
        } else {
            $cart = new Cart();
        }

        // Create an order record to get the order ID
        $Order = $this->CreateOrder($vals, $cart);
        $db_order_id = DB_escapeString($Order->order_id);

        $prod_types = 0;

        // For each item purchased, record purchase in purchase table
        foreach ($items as $id=>$item) {
            //COM_errorLog("Processing item: $id");
            list($item_number, $item_opts) = PAYPAL_explode_opts($id, true);

            // If the item number is numeric, assume it's an
            // inventory item.  Otherwise, it should be a plugin-supplied
            // item with the item number like pi_name:item_number:options
            if (PAYPAL_is_plugin_item($item_number)) {
                PAYPAL_debug("handlePurchase for Plugin item " .
                        $item_number);

                // Initialize item info array to be used later
                $A = array();

                // Split the item number into component parts.  It could
                // be just a single string, depending on the plugin's needs.
                $pi_info = explode(':', $item['item_number']);
                PAYPAL_debug('Paymentgw::handlePurchase() pi_info: ' . print_r($pi_info,true));

                $status = LGLIB_invokeService($pi_info[0], 'productinfo',
                        array($item_number, $item_opts),
                        $product_info, $svc_msg);
                if ($status != PLG_RET_OK) {
                    $product_info = array();
                }

                if (!empty($product_info)) {
                    $items[$id]['name'] = $product_info['name'];
                }
                PAYPAL_debug("Paymentgw::handlePurchase() Got name " . $items[$id]['name']);
                $vars = array(
                        'item' => $item,
                        'ipn_data' => array(),
                );
                $status = LGLIB_invokeService($pi_info[0], 'handlePurchase',
                            $vars, $A, $svc_msg);
                if ($status != PLG_RET_OK) {
                    $A = array();
                }

                // Mark what type of product this is
                $prod_types |= PP_PROD_VIRTUAL;

            } else {
                PAYPAL_debug("Paypal item " . $item_number);
                $P = Product::getInstance($item_number);
                $A = array(
                    'name' => $P->getName(),
                    'short_description' => $P->getDscp(),
                    'expiration' => $P->expiration,
                    'prod_type' => $P->prod_type,
                    'file' => $P->file,
                    'price' => $item['price'],
                );

                if (!empty($item_opts)) {
                    $opts = explode(',', $itemopts);
                    $opt_str = $P->getOptionDesc($opts);
                    if (!empty($opt_str)) {
                        $A['short_description'] .= " ($opt_str)";
                    }
                    $item_number .= '|' . $item_opts;
                }

                // Mark what type of product this is
                $prod_types |= $P->prod_type;
            }

            // An invalid item number, or nothing returned for a plugin
            if (empty($A)) {
                continue;
            }

            // If it's a downloadable item, then get the full path to the file.
            // TODO: pp_data isn't available here, should be from $vals?
            if (!empty($A['file'])) {
                $this->items[$id]['file'] = $_PP_CONF['download_path'] . $A['file'];
                $token_base = $this->pp_data['txn_id'] . time() . rand(0,99);
                $token = md5($token_base);
                $this->items[$id]['token'] = $token;
            } else {
                $token = '';
            }
            $items[$id]['prod_type'] = $A['prod_type'];

            // If a custom name was supplied by the gateway's IPN processor,
            // then use that.  Otherwise, plug in the name from inventory or
            // the plugin, for the notification email.
            if (empty($item['name'])) {
                $items[$id]['name'] = $A['short_description'];
            }

            // Add the purchase to the paypal purchase table
            $uid = isset($vals['uid']) ? (int)$vals['uid'] : $_USER['uid'];

            $sql = "INSERT INTO {$_TABLES['paypal.purchases']} SET
                        order_id = '{$db_order_id}',
                        product_id = '{$item_number}',
                        description = '{$items[$id]['name']}',
                        quantity = '{$item['quantity']}',
                        user_id = '{$uid}',
                        txn_type = '{$this->gw_id}',
                        txn_id = '',
                        purchase_date = '" . PAYPAL_now()->toMySQL() . "',
                        status = 'complete',
                        token = '$token',
                        price = " . (float)$item['price'] . ",
                        options = '" . DB_escapeString($item_opts) . "'";

            // add an expiration date if appropriate
            if (is_numeric($A['expiration']) && $A['expiration'] > 0) {
                $sql .= ", expiration = DATE_ADD('" . PAYPAL_now()->toMySQL() .
                        "', INTERVAL {$A['expiration']} DAY)";
            }
            //echo $sql;die;
            PAYPAL_debug($sql);
            DB_query($sql);

        }   // foreach item
    }


    /**
    *   Create an order record.
    *   This is virtually identical to the function in BaseIPN.class.php
    *   and is used here to create an order record when the purchase is
    *   being handled by the payment gateway, without an IPN.
    *
    *   @param  array   $A      Array of order info, at least a user ID
    *   @param  array   $cart   The shopping cart, to get addresses, etc.
    *   @return string          Order ID just created
    */
    protected function CreateOrder($A, $cart)
    {
        global $_TABLES, $_USER;

        $ord = new Order();
        $uid = isset($A['uid']) ? (int)$A['uid'] : $_USER['uid'];
        $ord->uid = $uid;
        $ord->status = 'pending';   // so there's something in the status field

        if ($uid > 1) {
            $U = self::UserInfo($uid);
        }

        $BillTo = $cart->getAddress('billto');
        if (empty($BillTo) && $uid > 1) {
            $BillTo = $U->getDefaultAddress('billto');
        }

        if (is_array($BillTo)) {
            $ord->setBilling($BillTo);
        }

        $ShipTo = $cart->getAddress('shipto');
        if (empty($ShipTo) && $uid > 1) {
            $ShipTo = $U->getDefaultAddress('shipto');
        }
        if (is_array($ShipTo)) {
            $ord->setShipping($ShipTo);
        }

        $ord->pmt_method = $this->gw_name;
        $ord->pmt_txn_id = '';
        /*$ord->tax = $this->pp_data['pmt_tax'];
        $ord->shipping = $this->pp_data['pmt_shipping'];
        $ord->handling = $this->pp_data['pmt_handling'];*/
        $ord->buyer_email = DB_getItem($_TABLES['users'], 'email', "uid=$uid");
        $ord->log_user = COM_getDisplayName($uid) . " ($uid)";

        //$order_id = $ord->Save();
        //return $order_id;
        return $ord;
    }


    //
    //  The next group of functions will PROBABLY need to be re-declared
    //  for each child class.  For the most part, they don't do anything useful
    //  and you won't get payment buttons or proper IPN processing without them.
    //

    /**
    *   Create a "buy now" button for a catalog item.
    *   Each gateway must implement its own function for payment buttons.
    *
    *   @param  object  $P      Instance of a Product object for the product
    *   @return string          Complete HTML for the "Buy Now"-type button
    */
    public function ProductButton($P)
    {
        return '';
    }


    /**
    *   Create a "buy now" button for an external (plugin) product.
    *   Each gateway must implement its own function for external buttons.
    *
    *   @param  array   $vars       Variables used to create the button
    *   @param  string  $btn_type   Type of button requested
    *   @return string              Empty string, this is a stub function
    */
    public function ExternalButton($vars = array(), $btn_type = 'buy_now')
    {
        return '';
    }


    /**
    *   Get the checkout button
    *
    *   @param  object  $cart   Shoppping cart
    *   @return string      HTML for checkout button
    */
    public function checkoutButton($cart)
    {
        global $_PP_CONF, $_USER;

        if (!$this->_Supports('checkout')) return '';

        $gateway_vars = $this->gatewayVars($cart);
        $T = PP_getTemplate('btn_checkout', 'btn', 'templates/buttons');
        $T->set_var(array(
            'action'    => $this->getActionUrl(),
            'method'    => $this->getMethod(),
            'gateway_vars' => $gateway_vars,
            'is_uikit'  => $_PP_CONF['_is_uikit'],
            'button_url' => $this->getCheckoutButton(),
            'cart_id'   => $cart->cartID(),
            'uid'       => $_USER['uid'],
        ) );
        return $T->parse('', 'btn');
    }


    public function getLogo()
    {
        return $this->gw_desc;
    }


    /**
    *   Abstract function to add an item to the custom string.
    *   This default just addes the value to an array; the child class
    *   can override this if necessary
    *
    *   @param  string  $key        Item name
    *   @param  string  $value      Item value
    */
    public function AddCustom($key, $value)
    {
        $this->custom[$key] = $value;
    }


    /**
    *   Check that the seller email address in an IPN message is valid.
    *   Default is true, override this function in the gateway to implement.
    *
    *   @param  string  $email  Email address to check
    *   @return boolean     True if valid, False if not.
    */
    public function isBusinessEmail($email)
    {
        return true;
    }


    /**
    *   Get the form action URL.
    *   This function may be overridden by the child class.
    *   The default is to simply return the configured URL
    *
    *   This is public so that if it is not declared by the child class,
    *   it can be called during IPN processing.
    *
    *   @return string      URL to payment processor
    */
    public function getActionUrl()
    {
        return $this->gw_url;
    }


    /**
    *   Get the postback URL for transaction verification.
    *
    *   @return strin       URL for postbacks
    */
    public function getPostBackUrl()
    {
        return $this->postback_url;
    }


    /**
    *   Get the values to show in the "Thank You" message when a customer
    *   returns to our site. The returned array should be
    *   formatted as shown. There are no parameters, any data will be
    *   via $_GET.
    *
    *   This stub function returns only an empty array, which will cause
    *   a simple "Thanks for your order" message to appear, without any
    *   payment details.
    *
    *   @return array   Array of name=>value pairs, empty for default msg
    */
    public function thanksVars()
    {
        $R = array(
            //'gateway_url'   => Gateway URL for use to check purchase
            //'gateway_name'  => self::Description(),
        );
        return $R;
    }


    /**
    *   Function to return the "custom" string.
    *   Depends on the gateway to define this.  This default simply
    *   returns an HTML-safe version of the serialized $custom array.
    *
    *   @return string  Custom string to pass to gateway
    */
    protected function PrepareCustom()
    {
        return str_replace('"', '\'', serialize($this->custom));
    }


    /**
    *   Get the user information.
    *   Just a wrapper for the UserInfo class to save re-reading the
    *   database each time a UserInfo object is needed. Assumes only one
    *   user's information is needed per page load.
    *
    *   @return object  UserInfo object
    */
    protected static function UserInfo()
    {
        static $UserInfo = NULL;

        if ($UserInfo === NULL) {
            $UserInfo = new UserInfo();
        }
        return $UserInfo;
    }


    //
    //  The following functions MUST be declared in each derived class.
    //  There is no way that these can deliver reasonable default behavior.
    //  Technically, __set() is optional; you can populate the $properties array
    //  manually or use actual local variables, but to reference your
    //  gateway's undefined local variables as $this->varname you'll need a
    //  __set() function.  Otherwise, they'll be created on demand, but
    //  retrieved via our __get() function which only looks at the local
    //  $properties variable.
    //

    /**
    *   Magic setter function.
    *   Must be declared in the child object
    *
    *   @param  string  $key    Name of property to set
    *   @param  mixed   $value  Value to set for property
    */
    public function __set($key, $value)
    {
    }


    /**
    *   Get the variables to display with the IPN log
    *   This gets the variables from the gateway's IPN data into standard
    *   array values to be displayed in the IPN log view.
    *
    *   @param  array   $data       Array of original IPN data
    *   @return array               Name=>Value array of data for display
    */
    public function ipnlogVars($data)
    {
        return array();
    }


    /**
    *   Present the configuration form for a gateway.
    *   This could almost be run within the parent class, but only
    *   the gateway knows the types of its configuration variables (checkbox,
    *   radio, text, etc).  Therefore, this function MUST be declared in each
    *   child class.
    *
    *   getServiceCheckboxes() is available to create the list of checkboxes
    *   for button types handled by this gateway.  Refer to the instance in
    *   the paypal gateway for guidance.
    *
    *   @return string      HTML for the configuration form.
    */
    public function Configure()
    {
        global $_CONF, $LANG_PP, $_PP_CONF;

        $T = PP_getTemplate('gateway_edit', 'tpl');
        $svc_boxes = $this->getServiceCheckboxes();

        $doc_url = PAYPAL_getDocUrl('gwhelp_' . $this->gw_name,
                $_CONF['language']);
        $T->set_var(array(
            'gw_description' => self::Description(),
            'gw_id'         => $this->gw_name,
            'orderby'       => $this->orderby,
            'enabled_chk'   => $this->enabled == 1 ? ' checked="checked"' : '',
            'pi_admin_url'  => PAYPAL_ADMIN_URL,
            'doc_url'       => $doc_url,
            'svc_checkboxes' => $svc_boxes,
        ) );

        // Load the language for this gateway and get all the config fields
        $LANG = $this->LoadLanguage();
        $fields = $this->getConfigFields($LANG);
        $T->set_block('tpl', 'ItemRow', 'IRow');
        foreach ($fields as $name=>$field) {
            $T->set_var(array(
                'param_name'    => isset($LANG[$name]) ? $LANG[$name] : $name,
                'field_name'    => $name,
                'param_field'   => $field['param_field'],
                'other_label'   => isset($field['other_label']) ? $field['other_label'] : '',
            ) );
            $T->parse('IRow', 'ItemRow', true);
        }
        $T->parse('output', 'tpl');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
    *   Get an instance of a gateway.
    *   Supports reading multiple gateways, but only one is normally needed.
    *
    *   @param  string  $gw_name    Gateway name, or '_enabled' for all enabled
    *   @param  array   $A          Optional array of fields and values
    *   @return object              Gateway object
    */
    public static function getInstance($gw_name, $A=array())
    {
        global $_TABLES, $_PP_CONF;
        static $gateways = array();

        if (!$gw_name) return NULL;
        if (!array_key_exists($gw_name, $gateways)) {
            $gw = __NAMESPACE__ . '\\Gateway\\' . $gw_name;
            if (class_exists($gw)) {
                $gateways[$gw_name] = new $gw($A);
            } else {
                $gateways[$gw_name] = NULL;
            }
        }
        return $gateways[$gw_name];
    }


    /**
    *   Get all gateways into a static array.
    *
    *   @param  boolean $enabled    True to get only enabled gateways
    *   @return array       Array of gateways, enabled or all
    */
    public static function getAll($enabled = true)
    {
        global $_TABLES, $_PP_CONF;

        static $gateways = array();
        $key = $enabled ? 1 : 0;

        if (!isset($gateways[$key])) {
            $gateways[$key] = array();
            $cache_key = 'gateways_' . $key;
            $gateways[$key] = Cache::get($cache_key);
            if ($gateways[$key] === NULL) {
                // Load the gateways
                $sql = "SELECT id, enabled, services
                    FROM {$_TABLES['paypal.gateways']}";
                // If not loading all gateways, get just then enabled ones
                if ($enabled) $sql .= ' WHERE enabled=1';
                $sql .= ' ORDER BY orderby';
                $res = DB_query($sql);
                while ($A = DB_fetchArray($res, false)) {
                    // For each available gateway, load its class file and add it
                    // to the static array. Check that a valid object is
                    // returned from getInstance()
                    $gw = self::getInstance($A['id'], $A);
                    if (is_object($gw)) {
                        $gateways[$key][$A['id']] = $gw;
                    }
                }
                Cache::set($cache_key, $gateways[$key], 'gateways');
            }
        }
        return $gateways[$key];
    }


    /**
    *   Get a config item.
    *   Provides a safe way to reference a configuration item without first
    *   checking whether it's set.
    *
    *   @param  string  $item   Config item name
    *   @return mixed       Value of item, or NULL if not set
    */
    public function getConfig($item)
    {
        return isset($this->config[$item]) ? $this->config[$item] : NULL;
    }


    /**
    *   Get the radio button for this gateway to show on the checkout form
    *
    *   @param  boolean $selected   True if the button should be selected
    *   @return string      HTML for radio button
    */
    public function checkoutRadio($selected = false)
    {
        $sel = $selected ? 'checked="checked" ' : '';
        $radio = '<input required type="radio" name="gateway" value="' .
                $this->gw_name . '" ' . $sel . '/>&nbsp;' . $this->getLogo();
        return $radio;
    }


    /**
     * Stub function to get the gateway variables.
     * The child class should provide this.
     *
     * @param   object  $cart   Cart object
     * @return string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        return '';
    }


    /**
     * Stub function to get the HTML for a checkout button.
     * Each child class must supply this
     *
     * @return  string  HTML for checkout button
     */
    public function getCheckoutButton()
    {
        return NULL;
    }


    /**
    *   Get an array of uninstalled gateways.
    *   Used to provide a list of links to install the gateway.
    *
    *   @return array   Array of gateways(filename, fullpath)
    */
    public static function getUninstalled()
    {
        global $LANG32;

        $installed = self::getAll(false);
        $available = array();
        $files = glob(__DIR__ . '/Gateway/*.class.php');
        if (is_array($files)) {
            foreach ($files as $fullpath) {
                $parts = explode('/', $fullpath);
                list($class,$x1,$x2) = explode('.', $parts[count($parts)-1]);
                if ($class[0] == '_') continue;     // special internal gateway
                if (array_key_exists($class, $installed)) continue; // already installed
                $gw = self::getInstance($class);
                if (is_object($gw)) {
                    $available[$class] = $gw;
                }
            }
        }
        return $available;
    }


    /**
     * Get the form method to use with the final checkout button.
     * Return POST by default
     *
     * @return  string  Form method
     */
    public function getMethod()
    {
        return 'post';
    }


    /**
     * Run additional functions after saving the configuration
     */
    protected function _postConfigSave()
    {
        return;
    }

}   // class Gateway

?>

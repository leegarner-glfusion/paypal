<?php
/**
*   Class to manage Authorize.Net SIM payments.
*   The overhead in signing Authorize.Net buttons is low, so they are not cached
*   in the "paypal.buttons" table.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import base gateway class */
USES_paypal_gateway_base();

/**
*   Class for Authorize.Net payment gateway
*   @since  0.5.3
*   @package paypal
*/
class authorizenetsim extends PaymentGw
{
    /** Authorize.net transaction key
    *   @var string */
    private $trans_key;

    /** Authorize.net api login
    *   @var string */
    private $api_login;

    /** MD5 Hash key configured on Authorize.Net
    *   @var string */
    private $hash_key;

    /** Shopping cart object. We need to access this both from CheckoutButon()
    *   and _getButton()
    *   @var object */
    private $cart;


    /**
    *   Constructor
    *   Sets gateway-specific variables and calls the parent constructor
    */
    function __construct()
    {
        global $_PP_CONF;

        // These are used by the parent constructor, set them first
        $this->gw_name = 'authorizenetsim';
        $this->gw_desc = 'Autnorize.Net SIM';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'prod_api_login'    => '',
            'prod_trans_key'    => '',
            'test_api_login'    => '',
            'test_trans_key'    => '',
            'prod_url'          => 'https://secure.authorize.net',
            'sandbox_url'       => 'https://test.authorize.net',
            'prod_md5_hash'     => '',
            'test_md5_hash'     => '',
            'test_mode'         => 1,
        );

        // The parent constructor reads our config items from the database to
        // override defaults
        parent::__construct();

        // The parent constructor sets all possible services.  Remove services
        // not supported by this gateway
        unset($this->services['subscribe']);

        $this->LoadLanguage();

        // parent constructor loads the config array, here we select which
        // keys to use based on test_mode
        if ($this->config['test_mode'] == '1') {
            $this->api_login = $this->config['test_api_login'];
            $this->trans_key = $this->config['test_trans_key'];
            $this->gw_url = $this->config['sandbox_url'];
            $this->hash_key = $this->config['test_hash_key'];
        } else {
            $this->api_login = $this->config['prod_api_login'];
            $this->trans_key = $this->config['prod_trans_key'];
            $this->gw_url = $this->config['prod_url'];
            $this->hash_key = $this->config['prod_hash_key'];
        }

        $this->get_shipping = 0;

    }


    /**
    *   Magic "setter" function.
    *
    *   @param  string  $key    Name of property to set
    *   @param  mixed   $value  Value to set
    */
    function __set($key, $value)
    {
        switch ($key) {
        case 'item_name':
        case 'currency_code':
            $this->properties[$key] = trim($value);
            break;

        case 'item_number':
            $this->properties[$key] = COM_sanitizeId($value, false);
            break;

        case 'amount':
            $this->properties[$key] = (float)$value;
            break;

        case 'get_shipping':
            $this->properties[$key] = (int)$value;
        }
    }                


    /**
    *   Get the main website URL for this payment gateway.
    *   Used to tell the buyer where to log in to check their account.
    *
    *   @return string      Gateway's website URL
    */
    private function getMainUrl()
    {   return 'https://www.authorize.net';    }


    /**
    *   Get the form variables for the cart checkout button.
    *
    *   @uses   _addItem()
    *   @uses   _getButton()
    *   @uses   getActionUrl()
    *   @return string      HTML code for the button
    */
    public function CheckoutButton($cart)
    {
        global $_PP_CONF, $_USER, $LANG_PP_authorizenetsim;

        if (!$this->Supports('checkout')) {
            return '';
        }

        $this->cart = $cart;
        $cartItems = $this->cart->Cart();
        $cartID = $this->cart->cartID();
        $this->AddCustom('cart_id', $cartID);

        USES_paypal_class_Product();
        foreach ($cartItems as $item_id=>$item) {
            list($id, $optstr) = explode('|', $item_id);
            if (is_numeric($id)) {
                $P = new Product($id);
                if ($optstr) {
                    $opts = explode(',', $optstr);
                    $optdesc = $P->getOptionDesc($opts);
                    if (!empty($optdesc)) {
                        $item['descrip'] .= ', ' . $optdesc;
                    }
                }
            }
            $this->_addItem($item_id, $item);
            /*$this->_addItem($item_id, $item['name'] , $item['descrip'],
                        $item['price'],
                        $item['quantity'], $item['shipping'], $item['taxable']);*/
        }

        $gateway_vars = $this->_getButton('cart');
        $T = new Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                $this->gw_name);
        $T->set_file(array('btn' => 'btn_checkout.thtml'));
        $T->set_var('action_url', $this->getActionUrl());
        $T->set_var('gw_name', $this->gw_name);
        $T->set_var('gateway_vars', $gateway_vars);
        $T->set_var('btn_text', $LANG_PP_authorizenetsim['buy_now']);
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
    *   Get the custom string properly formatted for the gateway.
    *
    *   @return string      Formatted custom string
    */
    protected function PrepareCustom()
    {
        if (is_array($this->custom)) {
            $tmp = array();
            foreach ($this->custom as $key => $value) {
                $tmp[] = $key . ':' . $value;
            }
            return implode(';', $tmp);
         }else
            return '';
    }


    /**
    *   Get a "buy now" button for a catalog item
    *
    *   @uses   _addItem()
    *   @uses   _getButton()
    *   @uses   getActionUrl()
    *   @param  object  $P      Product Object
    *   @return string          HTML for button
    */
    public function ProductButton($P)
    {
        global $_PP_CONF, $LANG_PP_authorizenetsim;

        // If this is a physical item, instruct the gateway to collect
        // the shipping address.
        $type = $P->prod_type;
        if ($type & PP_PROD_PHYSICAL == PP_PROD_PHYSICAL) {
            $this->get_shipping = 1;
        }
            /*$this->_addItem($item_id, $item['name'] , $item['descrip'],
                        $item['price'],
                        $item['quantity'], $item['shipping'], $item['taxable']);*/
        $vars = array(
            'name'          => htmlspecialchars($P->name),
            'descrip'       => htmlspecialchars($P->short_description),
            'price'         => $P->price,
            'options'       => array(),
            'weight'        => $P->weight,
            'taxable'       => $P->taxable ? 'Y' : 'N',
            'type'          => $P->prod_type,
            'quantity'      => 1,
        );
        $this->_addItem($P->id, $vars);
        /*$this->_addItem($P->id, $P->name,
                $P->short_description, $P->price,
                $P->tax, $P->shipping_amt);*/
        $gateway_vars = $this->_getButton($P->btn_type);

        $T = new Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                $this->gw_name); 
        $T->set_file(array('btn' => 
                'btn_' . self::gwButtonType($P->btn_type) . '.thtml'));
        $T->set_var('action_url', $this->getActionUrl());
        $T->set_var('gateway_vars', $gateway_vars);
        $T->set_var('btn_text', $LANG_PP_authorizenetsim['buy_now']);
        $retval = $T->parse('', 'btn');

        return $retval;
    }


    /**
    *   Get a button for an external (plugin) item.
    *   Only supports a "buy now" button since we don't necessarily know
    *   what type of button the item uses.  Assumes a quantity of one.
    *
    *   @uses   _addItem()
    *   @uses   _getButton()
    *   @uses   getActionUrl()
    *   @uses   PaymentGw::AddCustom()
    *   @param  array   $attribs    Attribute array (item_number, price)
    *   @param  string  $btn_type   Button type
    *   @return string              HTML for button
    */
    public function ExternalButton($attribs, $btn_type)
    {
        // Add options, if present.  Only 2 are supported, and the amount must
        // already be included in the $amount above.
        $item_number = $attribs['item_number'];
        if (isset($attribs['options']) && is_array($attribs['options'])) {
            $item_number .= '|' . implode(',', $attribs['options']);
        } else {
            $attribs['options'] = array();
        }
        $vars = array(
            'name'      => $attribs['item_number'],
            'descrip'   => $attribs['item_name'],
            'price'     => $attribs['amount'],
            'options'   => $attribs['options'],
            'weight'    => isset($attribs['weight']) ? $attribs['weight'] : 0,
            'taxable'   => (isset($attribs['tax']) && $attribs['tax'] > 0) ? 'Y' : 'N',
            'type'      => isset($attribs['type']) ? $attribs['type'] : PP_PROD_VIRTUAL,
            'quantity'  => isset($attibs['quantity']) ? $attribs['quantity'] : 1,
        );
        $this->_addItem($item_number, $vars);
        $gateway_vars = $this->_getButton($btn_type);

        $T = new Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                $this->gw_name); 
        $T->set_file(array('btn' => 'btn_buy_now.thtml'));
        $T->set_var('action_url', $this->getActionUrl());
        $T->set_var('gw_name', $this->gw_name);
        $T->set_var('gateway_vars', $gateway_vars);

        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
    *   Get a purchase button.
    *   This takes separate parameters so it can be called directly or via
    *   ExternalButton or ProductButton
    *
    *   @uses   PaymentGw::AddCustom()
    *   @uses   PrepareCustom()
    *   @param  mixed   $item_id        Item ID number
    *   @param  float   $item_price     Item unit price
    *   @param  string  $btn_type       Button Type (optional)
    *   @param  string  $custom         Custom information
    *   @return string                  HTML for button code
    */
    private function _getButton($btn_type)
    {
        global $_PP_CONF, $_USER;

        // Make sure we have at least one item
        if (empty($this->items)) return '';
        $items = array();
        $total_amount = 0;
        $total_shipping = 0;
        $x_line_item = '';

        foreach ($this->items as $item) {
            $total_amount += (float)$item['price'] * (float)$item['quantity'];
            if (isset($item['shipping']))
                $total_shipping += (float)$item['shipping'];
                $total_amount += (float)$item['shipping'];
            // Have to assemble x_line_item separately, since it reuses the
            // same variable name on the form; won't work in an assoc. array.
            $x_line_item .= '<input type="hidden" name="x_line_item" value="'.
                $item['item_name'] . '<|>' .
                $item['item_name'] . '<|>' .
                $item['description'] . '<|>' .
                //htmlspecialchars($item['description']) . '<|>' .
                $item['quantity'] . '<|>' .
                $item['price'] . '<|>' .
                $item['taxable'] .
                '" />' . LB;

            // Now put the item into a custom variable that can be returned
            // with the IPN. x_line_item is not returned.
            $items[] = $item['item_id'] . ';' . $item['price'] . ';' .
                    $item['quantity'];
        }
        $item_var = implode('::', $items);
        $custom = $this->PrepareCustom();

        $fp_timestamp = time();
        $fp_sequence = "123" . $fp_timestamp; // invoice or other unique number.
        $total_amount = sprintf('%.02f', $total_amount);
        $fingerprint = self::getFingerprint($this->api_login,
                $this->trans_key, $total_amount, $fp_sequence, $fp_timestamp,
                $this->currency_code);
        $vars = array(
            'x_login'       => $this->api_login,
            'x_amount'      => sprintf('%.02f', $total_amount),
            'x_cust_id'     => $_USER['uid'],
            'x_fp_timestamp' => $fp_timestamp,
            'x_fp_sequence' => $fp_sequence,
            'x_fp_hash'     => $fingerprint,
            'x_version'     => '3.1',
            'x_show_form'   => 'payment_form',
            'x_test_request' => 'false',
            'x_relay_response' => 'true',
            'x_relay_url'   => $this->ipn_url,
            'x_delim_data'  => 'false',
            'x_cancel_url'  => PAYPAL_URL,
            'x_currency_code' => $this->currency_code,
            'custom'        => $custom,
            'item_var'      => $item_var,
        );

        if ($total_shipping > 0) {
            $vars['x_freight'] = sprintf('%.02f', $total_shipping);
        }
        if (!COM_isAnonUser()) {
            $vars['x_email'] = $_USER['email'];
        }

        if ($btn_type == 'cart') {
            $billto = $this->cart->getAddress('billto');
            $shipto = $this->cart->getAddress('shipto');
        } else {
            // Buy-now product button, set default billing/shipping addresses
            $U = self::UserInfo();
            $billto = $U->getDefaultAddress('billto');
            $shipto = $U->getDefaultAddress('shipto');
        }
        if (!empty($billto)) {
            list($fname, $lname)  = explode(' ', $billto['name']);
            $vars['x_first_name'] = $fname;
            if ($lname) $vars['x_last_name'] = $lname;
            $vars['x_company'] = $billto['company'];
            $vars['x_address'] = $billto['address1'];
            if (!empty($billto['address2']))
                $vars['x_address'] .= ', ' . $billto['address2'];
            $vars['x_city'] = $billto['city'];
            $vars['x_state'] = $billto['state'];
            $vars['x_zip'] = $billto['zip'];
            $vars['x_country'] = $billto['country'];
        }
        if (!empty($shipto)) {
            list($fname, $lname)  = explode(' ', $shipto['name']);
            $vars['x_ship_to_first_name'] = $fname;
            if ($lname) $vars['x_ship_to_last_name'] = $lname;
            $vars['x_ship_to_company'] = $shipto['company'];
            $vars['x_ship_to_address'] = $shipto['address1'];
            if (!empty($shipto['address2']))
                $vars['x_ship_to_address'] .= ', ' . $shipto['address2'];
            $vars['x_ship_to_city'] = $shipto['city'];
            $vars['x_ship_to_state'] = $shipto['state'];
            $vars['x_ship_to_zip'] = $shipto['zip'];
            $vars['x_ship_to_country'] = $shipto['country'];
        }

        $gateway_vars = '';
        foreach ($vars as $name=>$value) {
            $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value=\'' . $value . '\' />' . "\n";
        }
        $gateway_vars .= $x_line_item;

        return $gateway_vars;
    }


    /**
    *   Generates a fingerprint needed for a hosted order form or DPM.
    *
    *   @param  string  $api_login_id    Login ID.
    *   @param  string  $transaction_key API key.
    *   @param  string  $amount          Amount of transaction.
    *   @param  string  $fp_sequence     An invoice number or random number.
    *   @param  string  $fp_timestamp    Timestamp.
    *   @param  string  $currency        Currency code, USD, GBP, etc.
    *
    *   @return string      The fingerprint.
    */
    public static function getFingerprint($api_login_id, $transaction_key,
            $amount, $fp_sequence, $fp_timestamp, $currency)
    {
        $field_str = $api_login_id . '^' . $fp_sequence . '^' . $fp_timestamp . '^'
            . $amount . '^' . $currency;
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $field_str, $transaction_key);
        } else {
            return bin2hex(mhash(MHASH_MD5, $field_str, $transaction_key));
        }
    }


    /**
    *   Get the action url for the payment button.
    *   Overridden from the parent since we need to append to the url.
    *
    *   @return string      Payment URL
    */ 
    public function getActionUrl()
    {
        return $this->gw_url . '/gateway/transact.dll';
    }


    /**
    *   Get the variables from the return URL to display a "thank-you"
    *   message to the buyer.
    *
    *   @uses   getMainUrl()
    *   @uses   PaymentGw::Description()
    *   @param  array   $A      Optionally override the $_GET parameters
    *   @return array           Array of standard name=>value pairs
    */
    public function thanksVars($A='')
    {
        if (empty($A)) {
            $A = $_GET;     // Amazon's returnUrl uses $_GET
        }
        list($currency, $amount) = preg_split('/\s+/', $A['transactionAmount']);
        $amount = COM_numberFormat($amount, 2);
        $R = array(
            'payment_date'  => strftime('%d %b %Y @ %H:%M:%S', $A['transactionDate']),
            'currency'      => $currency,
            'payment_amount' => $amount,
            'gateway_url'   => self::getMainUrl(),
            'gateway_name'  => self::Description(),
        );
        return $R;
    }


    /**
    *   Make sure that the button type is one of our valid types
    *
    *   @param  string  $btn_type   Button type, typically from product record
    *   @return string              Valid button type
    */
    private function gwButtonType($btn_type)
    {
        switch ($btn_type) {
        case 'donation':
        case 'buy_now':
            $retval = $btn_type;
            break;
        default:
            $retval = 'buy_now';
            break;
        }
        return $retval;
    }


    /**
    *   Present the configuration form for this gateway.
    *
    *   @uses   PaymentGw::getServiceCheckboxes
    *   @return string      HTML for the configuration form.
    */
    public function Configure()
    {
        global $_CONF, $LANG_PP_authorizenetsim;

        $T = new Template(PAYPAL_PI_PATH . '/templates/');
        $T->set_file('tpl', 'gateway_edit.thtml');

        $doc_url = PAYPAL_getDocUrl('gwhelp_' . $this->gw_name . '.html',
                $_CONF['language']);

        $svc_boxes = $this->getServiceCheckboxes();

        $T->set_var(array(
            'gw_description' => self::Description(),
            'gw_id'         => $this->gw_name,
            'enabled_chk'   => $this->enabled == 1 ? ' checked="checked"' : '',
            'orderby'       => $this->orderby,
            'pi_admin_url'  => PAYPAL_ADMIN_URL,
            'doc_url'       => $doc_url,
            'svc_checkboxes' => $svc_boxes,
        ) );

        //$this->LoadLanguage();

        $T->set_block('tpl', 'ItemRow', 'IRow');
        foreach($this->config as $name=>$value) {
            switch ($name) {
            case 'test_mode':
                $field = '<input type="checkbox" name="' . $name . 
                    '" value="1" ';
                if ($value == 1) $field .= 'checked="checked" ';
                $field .= '/>';
                break;
            default:
                $field = '<input type="text" name="' . $name . '" value="' .
                    $value . '" size="60" />';
                break;
            }

            $T->set_var(array(
                'param_name'    => $LANG_PP_authorizenetsim[$name],
                'param_field'   => $field,
                'field_name'    => $name,
                'doc_url'       => $doc_url,
            ) );
            $T->parse('IRow', 'ItemRow', true);
        }
        $T->parse('output', 'tpl');
        $form = $T->finish($T->get_var('output'));

        return $form;
    }


    /**
    *   Prepare to save the configuraiton.
    *   This copies the new config values into our local variables, then
    *   calls the parent function to save to the database.
    *
    *   @uses   PaymentGw::SaveConfig()
    *   @param  array   $A      Array of name=>value pairs (e.g. $_POST)
    *   @return boolean         Results of parent SaveConfig function
    */
    public function SaveConfig($A)
    {
        if (!is_array($A)) return false;
        foreach ($this->config as $name=>$value) {
            switch ($name) {
            case 'test_mode':
                $this->config[$name] = isset($A[$name]) ? 1 : 0;
                break;
            default:
                $this->config[$name] = $A[$name];
                break;
            }
        }
        return parent::SaveConfig($A);
    }


    /**
    *   Add a single item to our item array.
    *   The description needs its tags stripped to appear right on the
    *   gateway's payment form.
    *
    *   @param  mixed   $item_id    ID of item, including options
    *   @param  string  $item_name  Short item name
    *   @param  string  $descrip    Item description
    *   @param  float   $price      Item price
    *   @param  integer $qty        Quantity
    *   @param  float   $shippin    Per-item shipping
    */
    //private function _addItem($item_id, $item_name, $descrip,
    //            $price, $qty=0, $shipping=0)
    private function _addItem($item_id, $vars)
    {
        if ($vars['quantity'] == 0) $vars['quantity'] = 1;
        $qty = (float)$qty;
        $price = (float)$vars['price'];
        $this->items[] = array(
            'item_id' => $item_id,
            'item_name' => $vars['name'],
            'description' => strip_tags($vars['descrip']),
            'price' => $price,
            'quantity' => $vars['quantity'],
            'shipping' => $vars['shipping'],
            'weight' => $vars['weight'],
            'taxable' => $vars['taxable'],
        );
    }


    /**
    *   Get the variables to display with the IPN log
    *   This gets the variables from the gateway's IPN data into standard
    *   array values to be displayed in the IPN log view
    *
    *   @param  array   $data       Array of original IPN data
    *   @return array               Name=>Value array of data for display
    */
    public function ipnlogVars($data)
    {
        if (!is_array($data)) {
            return array();
        }

        list($currency, $amount) = explode(' ', $data['transactionAmount']);
        $retval = array(
            'pmt_gross'     => $data['x_amount'],
            'verified'      => 'verified',
            'pmt_status'    => 'complete',
            'buyer_email'   => $data['x_email'],
        );
        return $retval;
    }


    /**
    *   Get the MD5 hash key.
    *   Needed by the IPN processor
    *
    *   @return string  MD5 Hash Key
    */
    public function HashKey()
    {
        return $this->hash_key;
    }


    /**
    *   Get the API Login
    *   Needed by the IPN processor
    *
    *   @return string  API Login ID
    */
    public function ApiLogin()
    {
        return $this->api_login;
    }

}   // class amazon

?>

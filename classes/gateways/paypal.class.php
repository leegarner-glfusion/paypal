<?php
/**
*   Gateway implementation for PayPal.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.7
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for Paypal payment gateway
*   @since 0.5.0
*   @package paypal
*/
class paypal extends Gateway
{

    /** Business e-mail to be used for creating buttons
    *   @var string */
    private $receiver_email;

    /** PayPal-assigned certificate ID to be used for encrypted buttons
    *   @var string */
    private $cert_id;


    /**
    *   Constructor.
    *   Set gateway-specific items and call the parent constructor.
    */
    function __construct()
    {
        global $_PP_CONF, $_USER;

        $supported_currency = array(
            'USD', 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'NZD', 'CHF', 'HKD',
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'HUF', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB',
        );

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'paypal';
        $this->gw_desc = 'PayPal Web Payments Standard';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'bus_prod_email'    => '',
            'micro_prod_email'  => '',
            'bus_test_email'    => '',
            'micro_test_email'  => '',
            'test_mode'         => 1,
            'micro_threshold'   => '',
            'encrypt'           => 0,
            'pp_cert'           => '',
            'pp_cert_id'        => '',
            'micro_cert_id'     => '',
            'sandbox_main_cert' => '',
            'sandbox_micro_cert' => '',
            'prv_key'           => '',
            'pub_key'           => '',
            'prod_url'          => 'https://www.paypal.com',
            'sandbox_url'       => 'https://www.sandbox.paypal.com',
            'ipn_url'           => '',
        );

        // Call the parent constructor to initialize the common variables.
        parent::__construct();

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->config['test_mode'] == 1) {
            $this->gw_url = $this->config['sandbox_url'];
            $this->postback_url = 'https://ipnpb.sandbox.paypal.com';
        } else {
            $this->gw_url = $this->config['prod_url'];
            $this->postback_url = 'https://ipnpb.paypal.com';
        }
 
        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }

        // Set defaults, just to make sure something is set
        $this->cert_id = $this->config['pp_cert_id'];
        $this->receiver_email = $this->config['bus_prod_email'];

        // Override the default IPN URL if an override is provided
        if (!empty($this->config['ipn_url'])) {
            $this->ipn_url = $this->config['ipn_url'];
        }
    }


    /**
    *   Get the main gateway url.
    *   This is used to tell the buyer where they can log in to check their
    *   purchase.  For PayPal this is the same as the production action URL.
    *
    *   @return string      Gateway's home page
    */
    function getMainUrl()
    {   return 'https://www.paypal.com';    }


    /**
    *   Magic "setter" function
    *
    *   @see    Gateway::__get()
    *   @param  string  $key    Name of property to set
    *   @param  mixed   $value  New value for property
    */
    function __set($key, $value)
    {
        switch ($key) {
        case 'business':
        case 'item_name':
        case 'currency_code':
        case 'cert_id':
        case 'bus_prod_email':
        case 'micro_prod_email':
        case 'bus_test_email':
        case 'micro_test_email':
            $this->properties[$key] = trim($value);
            break;

        case 'item_number':
            $this->properties[$key] = COM_sanitizeId($value, false);
            break;

        case 'amount':
        case 'weight':
        case 'tax':
        case 'shipping_amount':
            $this->properties[$key] = (float)$value;
            break;

        case 'shipping_type':
            $this->properties[$key] = (int)$value;
            break;

        case 'service':
            foreach ($value as $svc=>$enabled) {
                $this->services[$svc] = $enabled == 1 ? 1 : 0;
            }
            break;
        /*case 'buy_now':
        case 'pay_now':
        case 'donation':
        case 'subscribe':
            $this->services[$key] = $value == 1 ? 1 : 0;
            break;*/
        }
    }


    /**
    *   Get the form variables for the purchase button.
    *
    *   @uses   Gateway::_Supports()
    *   @uses   _encButton()
    *   @uses   getActionUrl()
    *   @return string      HTML for purchase button
    */
    public function CheckoutButton($cart)
    {
        global $_PP_CONF, $_USER, $_TABLES, $LANG_PP;

        if (!$this->_Supports('checkout')) {
            return '';
        }

        $cartItems = $cart->Cart();
        $cartID = $cart->CartID();

        $custom_arr = array(
            'uid' => $_USER['uid'],
            'transtype' => 'cart_upload',
            'cart_id' => $cartID,
        );

        $fields = array(
            'cmd'       => '_cart',
            'upload'    => '1',
            'cancel_return' => PAYPAL_URL.'/index.php?view=cart',
            'return'    => PAYPAL_URL.'/index.php?thanks=paypal',
            'rm'        => '1',     // simple GET return url
            'paymentaction' => 'sale',
            'notify_url' => $this->ipn_url,
            'currency_code'  => $this->currency_code,
            'custom'    => str_replace('"', '\'', serialize($custom_arr)),
        );

        $address = $cart->getAddress('shipto');
        if (!empty($address)) {
            list($fname, $lname) = explode(' ', $address['name']);
            $fields['first_name'] = htmlspecialchars($fname);
            $fields['last_name'] = htmlspecialchars($lname);
            $fields['address1'] = htmlspecialchars($address['address1']);
            $fields['address2'] = htmlspecialchars($address['address2']);
            $fields['city'] = htmlspecialchars($address['city']);
            $fields['state'] = htmlspecialchars($address['state']);
            $fields['country'] = htmlspecialchars($address['country']);
            $fields['zip'] = htmlspecialchars($address['zip']);
        }

        $i = 1;
        $total_amount = 0;
        $shipping = 0;
        $weight = 0;
        $handling = 0;
        $fields['tax_cart'] = 0;

        foreach ($cartItems as $cart_item_id=>$item) {
            $opt_str = '';
            $item_parts = explode('|', $item['item_id']);
            $db_item_id = $item_parts[0];
            $options = isset($item_parts[1]) ? $item_parts[1] : '';
            //if (is_numeric($db_item_id)) {
                //$P = new Product($db_item_id);
                $P = Product::getInstance($db_item_id);
                $db_item_id = DB_escapeString($db_item_id);
                $oc = 0;
                if (is_array($item['options'])) {
                    $opts = explode(',', $options);
                    foreach ($opts as $optval) {
                        $opt_info = $P->getOption($optval);
                        if ($opt_info) {
                            $opt_str .= ', ' . $opt_info['value'];
                            $fields['on'.$oc.'_'.$i] = $opt_info['name'];
                            $fields['os'.$oc.'_'.$i] = $opt_info['value'];
                            $oc++;
                        }
                    }
                    //$item['descrip'] .= $opt_str;
                } else {
                    $opts = array();
                }
                $fields['amount_' . $i] = $P->getPrice($opts, $item['quantity']);
                $fields['tax_cart'] += $P->getTax($fields['amount_' . $i], $item['quantity']);
                /*if ($P->taxable == 0) {
                    $fields['tax_' . $i] = '0.00';
                }*/
            /*} else {
                // Plugin item
                $fields['amount_' . $i] = $item['price'];
            }*/
            //$fields['item_number_' . $i] = htmlspecialchars($item['item_id']);
            $fields['item_number_' . $i] = (int)$cart_item_id;
            $fields['item_name_' . $i] = htmlspecialchars($item['descrip']);
            $total_amount += $item['price'];
            if (isset($item['extras']['custom']) && is_array($item['extras']['custom'])) {
                foreach ($item['extras']['custom'] as $id=>$val) {
                    $fields['on'.$oc.'_'.$i] = $P->getCustom($id);
                    $fields['os'.$oc.'_'.$i] = $val;
                    $oc++;
                }
            }
            $fields['quantity_' . $i] = $item['quantity'];

            if (isset($item['shipping'])) {
                $fields['shipping_' . $i] = $item['shipping'];
                $shipping += $item['shipping'];
            }
            if (isset($item['weight']) && $item['weight'] > 0) {
                $weight += $item['weight'];
            }
            /*if (isset($item['tax'])) {
                $fields['tax_' . $i] = $item['tax'];
            } elseif (isset($item['options']['tax'])) {
                $fields['tax_' . $i] = $item['options']['tax'];
            }*/
            $i++;
        }

        $gc = $cart->getGC();
        if ($gc > 0) {
            $fields['discount_amount_cart'] = (float)($gc);
        }

        if ($shipping > 0) $total_amount += $shipping;
        if ($weight > 0) {
            $fields['weight_cart'] = $weight;
            $fields['weight_unit'] = $_PP_CONF['weight_unit'] == 'kgs' ?
                            'kgs' : 'lbs';
        }

        // Set the business e-mail address based on the total puchase amount
        // There must be an address configured; if not then this gateway can't
        // be used for this purchase
        $this->setReceiver($total_amount);
        $fields['business'] = $this->receiver_email;
        if (empty($fields['business']))
            return '';

        $gatewayVars = array();
        $enc_btn = '';
        if ($this->config['encrypt']) {
            $enc_btn = self::_encButton($fields);
            if (!empty($enc_btn)) {
                $gatewayVars[] = 
                '<input type="hidden" name="cmd" value="_s-xclick" />';
                $gatewayVars[] = '<input type="hidden" name="encrypted" '.
                'value="' . $enc_btn . '" />';
            }
        }
        if (empty($enc_btn)) {
            // If we didn't get an encrypted button, set the plaintext vars
            foreach($fields as $name=>$value) {
                $gatewayVars[] = '<input type="hidden" name="' .
                    $name . '" value="' . $value . '" />';
            }
        }

        $gateway_vars = implode("\n", $gatewayVars);
        $T = new \Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                    $this->gw_name);
        $T->set_file(array('btn' => 'btn_checkout.thtml'));
        $T->set_var('paypal_url', $this->getActionUrl());
        $T->set_var('gateway_vars', $gateway_vars);
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
    *   Create encrypted buttons.
    *
    *   Requires that the plugin is configured to do so, and that the key files
    *   are set up correctly.  If an error is encountered, an empty string
    *   is returned so the caller can proceed with an un-encrypted button.
    *
    *   @since  version 0.4.0
    *   @param  array   $fields     Array of data to encrypt into buttons
    *   @return string              Encrypted_value, or empty string on error
    */
    private function _encButton($fields)
    {
        global $_CONF, $_PP_CONF;

        // Make sure button encryption is enabled and needed values are set
        if ($this->config['encrypt'] != 1 ||
            empty($this->config['prv_key']) ||
            empty($this->config['pub_key']) ||
            empty($this->config['pp_cert']) ||
            $this->cert_id == '') {
            return '';
        }

        // Now check that the files exist and can be read
        foreach (array('prv_key', 'pub_key', 'pp_cert') as $idx=>$name) {
            if (!is_file($this->config[$name]) || 
                !is_readable($this->config[$name])) {
                return '';
            }
        }

        // Create a temporary file to begin storing our data.  If this fails,
        // then return.
        $dataFile = tempnam($_PP_CONF['tmpdir'], 'data');
        if (!is_writable($dataFile))
            return '';

        $plainText = '';
        $signedText = array();
        $encText = '';

        $pub_key = @openssl_x509_read(file_get_contents($this->config['pub_key']));
        if (!$pub_key) {
            COM_errorLog("Failed reading public key from {$this->config['pub_key']}", 1);
            return '';
        }
        $prv_key = @openssl_get_privatekey(file_get_contents($this->config['prv_key']));
        if (!$prv_key) {
            COM_errorLog("Failed reading private key from {$this->config['prv_key']}", 1);
            return '';
        }
        $pp_cert = @openssl_x509_read(file_get_contents($this->config['pp_cert']));
        if (!$pp_cert) {
            COM_errorLog("Failed reading PayPal certificate from {$this->config['pp_cert']}", 1);
            return '';
        }

        //  Make sure this key and certificate belong together
        if (!openssl_x509_check_private_key($pub_key, $prv_key)) {
            COM_errorLog("Mismatched private & public keys", 1);
            return '';
        }

        //  Start off the form data with the PayPal certificate ID
        $plainText .= "cert_id=" . $this->cert_id;

        //  Create the form data by separating each value set by a new line
        //  Make sure that required fields are available.  We assume that the
        //  item_number, item_name and amount are in.
        if (!isset($fields['business']))
            $fields['business'] = $this->receiver_email;
        if (!isset($fields['currency_code']))
            $fields['currency_code'] = $this->currency_code;
        foreach($fields as $key => $value) {
            $plainText .= "\n{$key}={$value}";
        }

        //  First create a file for storing the plain text values
        $fh = fopen($dataFile . '_plain.txt', 'wb');
        if ($fh) fwrite($fh, $plainText);
        else return '';
        @fclose($fh);

        // Now sign the plaintext values into the signed file
        //$fh = fopen($dataFile . "_signed.txt", "w+");
        if (!openssl_pkcs7_sign($dataFile . '_plain.txt',
                    $dataFile . '_signed.txt',
                    $pub_key,
                    $prv_key,
                    array(),
                    PKCS7_BINARY)) {
            return '';
        }

        //  Parse the signed file between the header and content
        $signedText = explode("\n\n",
                file_get_contents($dataFile . '_signed.txt'));

        //  Save only the content but base64 decode it first
        $fh = fopen($dataFile . '_signed.txt', 'wb');
        if ($fh) fwrite($fh, base64_decode($signedText[1]));
        else return '';
        @fclose($fh);

        // Now encrypt the signed file we just wrote
        if (!openssl_pkcs7_encrypt($dataFile . '_signed.txt',
                    $dataFile . '_enc.txt',
                    $pp_cert,
                    array(),
                    PKCS7_BINARY)) {
            return '';
        }

        // Parse the encrypted file between header and content
        $encryptedData = explode("\n\n",
                file_get_contents($dataFile . "_enc.txt"));
        $encText = $encryptedData[1];

        // Delete all of our temporary files
        @unlink($dataFile);
        @unlink($dataFile . "_plain.txt");
        @unlink($dataFile . "_signed.txt");
        @unlink($dataFile . "_enc.txt");

        //  Return the now-encrypted form content
        return "-----BEGIN PKCS7-----\n" . $encText . "\n-----END PKCS7-----";

    }


    /**
    *   Get a buy-now button for a catalog product.
    *   Checks the button table to see if a button exists, and if not
    *   a new button will be created.
    *
    *   @uses   gwButtonType()
    *   @uses   PrepareCustom()
    *   @uses   Gateway::_ReadButton()
    *   @uses   Gateway::_SaveButton()
    *   @param  object  $P      Product Item object
    *   @return string          HTML code for the button.
    */
    public function ProductButton($P)
    {
        global $_PP_CONF, $LANG_PP;

        // Make sure we want to create a buy_now-type button
        $btn_type = $P->btn_type;
        if (empty($btn_type)) return '';

        $btn_info = self::gwButtonType($btn_type);
        $this->AddCustom('transtype', $btn_type);
        $gateway_vars = '';

        // See if the button is in our cache table
        if ($this->config['encrypt']) {
            $gateway_vars = $this->_ReadButton($P->id);
        }
        if (empty($gateway_vars)) {
            $vars = array();
            $vars['cmd'] = $btn_info['cmd'];
            $this->setReceiver($P->price);
            $vars['business'] = $this->receiver_email;
            $vars['item_number'] = htmlspecialchars($P->id);
            $vars['item_name'] = htmlspecialchars($P->short_description);
            $vars['currency_code'] = $this->currency_code;
            $vars['custom'] = $this->PrepareCustom();
            $vars['return'] = PAYPAL_URL . '/index.php?thanks=paypal';
            $vars['cancel_return'] = PAYPAL_URL;
            $vars['amount'] = $P->_act_price;
            $vars['undefined_quantity'] = '1';
            $vars['notify_url'] = $this->ipn_url;

            if ($P->weight > 0) {
                $vars['weight'] = $P->weight;
            } else {
                $vars['no_shipping'] = '1';
            }

            switch ($P->shipping_type) {
            case 0:
                $vars['no_shipping'] = '1';
                break;
            case 2:
                $vars['shipping'] = $P->shipping_amt;
                $vars['no_shipping'] = '1';
                break;
            case 1:
                $vars['no_shipping'] = '2';
                break;
            }

            $vars['tax'] = sprintf("%0.2f", $P->getTax($P->price));

            // Buy-now product button, set default billing/shipping addresses
            $U = self::UserInfo();
            $shipto = $U->getDefaultAddress('shipto');
            if (!empty($shipto)) {
                if (strpos($shipto['name'], ' ')) {
                    list($fname, $lname) = explode(' ', $shipto['name']);
                    $vars['first_name'] = $fname;
                    if ($lname) $vars['last_name'] = $lname;
                } else {
                    $vars['first_name'] = $shipto['name'];
                }
                $vars['address1'] = $shipto['address1'];
                if (!empty($shipto['address2']))
                    $vars['address2'] = $shipto['address2'];
                $vars['city'] = $shipto['city'];
                $vars['state'] = $shipto['state'];
                $vars['zip'] = $shipto['zip'];
                $vars['country'] = $shipto['country'];
            }

            $gateway_vars = '';
            $enc_btn = '';
            if ($this->config['encrypt']) {
                $enc_btn = $this->_encButton($vars);
                if (!empty($enc_btn)) {
                    $gateway_vars .=
                    '<input type="hidden" name="cmd" value="_s-xclick" />'.LB .
                    '<input type="hidden" name="encrypted" value=\'' .
                        $enc_btn . '\' />' . "\n";
                }
            }
            if (empty($enc_btn)) {
                // Create unencrypted buttons if not configured to encrypt,
                // or if encryption fails.
                foreach ($vars as $name=>$value) {
                    $gateway_vars .= '<input type="hidden" name="' . $name . 
                        '" value="' . $value . '" />' . "\n";
                }
            } else {
                $this->_SaveButton($P->id, $gateway_vars);
            }
        }

        // Set the text for the button, falling back to our Buy Now
        // phrase if not available
        $btn_text = isset($LANG_PP['buttons'][$btn_type]) ? 
                $LANG_PP['buttons'][$btn_type] : $LANG_PP['buy_now'];

        $T = new \Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                    $this->gw_name);
        $T->set_file('btn', 'btn_' . $btn_info['tpl'] . '.thtml');
        //$T = new \Template(PAYPAL_PI_PATH . '/templates/');
        //$T->set_file('btn', 'buttons/' . $this->gw_name . 
        //        '/btn_' . $btn_info['tpl'] . '.thtml');

        $T->set_var('paypal_url', $this->getActionUrl());
        $T->set_var('btn_text', $btn_text);
        $T->set_var('gateway_vars', $gateway_vars);
        $T->set_var('iconset', $_PP_CONF['_iconset']);
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
    *   Get a button for an external item, not one of our catalog items.
    *
    *   @uses   getActionUrl()
    *   @uses   AddCustom()
    *   @uses   setReceiver()
    *   @param  array   $attribs    Array of standard item attributes
    *   @param  string  $type       Type of button (buy_now, etc.)
    *   @return string              HTML for button
    */
    public function ExternalButton($attribs = array(), $type = 'buy_now')
    {
        global $_PP_CONF, $LANG_PP;

        $T = new \Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                    $this->gw_name);
        $T->set_file('btn', 'btn_' . $type . '.thtml');
        $btn_text = isset($LANG_PP['buttons'][$type]) ? 
                $LANG_PP['buttons'][$type] : $LANG_PP['buy_now'];
        $amount = isset($attribs['amount']) ? (float)$attribs['amount'] : 0;
        $this->setReceiver($amount);
        $this->AddCustom('transtype', $type);
        if (isset($attribs['custom']) && is_array($attribs['custom'])) {
            foreach ($attribs['custom'] as $key => $value) {
                $this->AddCustom($key, $value);
            }
        }
        $cmd = '_xclick';       // default Paypal command type
        if (isset($attribs['cmd'])) {
            $valid_cmds = array('_xclick', '_cart', '_oe-gift-certificate',
                '_xclick-subscriptions', '_xclick-auto-billing',
                '_xclick-payment-plan', '_donations');
            if (in_array($attribs['cmd'], $valid_cmds)) {
                $cmd = $attribs['cmd'];
            }
        }
        $vars = array(
            'cmd'           => $cmd,
            'business'      => $this->receiver_email,
            'item_number'   => $attribs['item_number'],
            'item_name'     => $attribs['item_name'],
            'currency_code' => $this->currency_code,
            'custom'        => $this->PrepareCustom(),
            'return'        => isset($attribs['return']) ? $attribs['return'] :
                            PAYPAL_URL . '/index.php?thanks=paypal',
            'rm'            => 1,
            'notify_url'    => $this->ipn_url,
            'amount'        => $amount,
        );

        // Add options, if present.  Only 2 are supported, and the amount must
        // already be included in the $amount above.
        // Option variables are shown on the checkout page, but the custom value
        // is what's really used to process the purchase since that's available
        // to all gateways.
        if (isset($attribs['options']) && is_array($attribs['options'])) {
            $i = 0;
            foreach ($attribs['options'] as $name => $value) {
                $this->addcustom($name, $value);
                $vars['on' . $i] = $name;
                $vars['os' . $i] = $value;
                $i++;
            }
        }

        if (!isset($attribs['quantity']) || $attribs['quantity'] == 0) {
            $vars['undefined_quantity'] = '1';
        } else {
            $vars['quantity'] = $attribs['quantity'];
        }

        if (isset($attribs['weight']) && $attribs['weight'] > 0) {
            $vars['weight'] = $attribs['weight'];
        } else {
            $vars['no_shipping'] = '1';
        }

        if (!isset($attribs['shipping_type']))
            $attribs['shipping_type'] = 0;
        switch ($attribs['shipping_type']) {
        case 0:
            $vars['no_shipping'] = '1';
            break;
        case 2:
            $vars['shipping'] = $attribs['shipping_amt'];
        case 1:
            $vars['no_shipping'] = '2';
            break;
        }

        // Set the tax flag.  If item is taxable ($attribs['taxable'] == 1), then set
        // the tax amount to the specific $attribs['tax'] amount if given.  If no tax
        // amount is given for a taxable item, do not set the value- let PayPal calculate
        // the tax.  Setting $vars['tax'] to zero means no tax is charged.
        if (isset($attribs['taxable']) && $attribs['taxable'] > 0) {
            if (isset($attribs['tax']) && $attribs['tax'] > 0) {
                $vars['tax'] = (float)$attribs['tax'];
            }
        } else {
            $vars['tax'] = '0';
        }

        if ($this->config['encrypt']) {
            $enc_btn = $this->_encButton($vars);
            //if (empty($enc_btn)) $this->config['encrypt'] = 0;
            if (!empty($enc_btn)) {
                $vars = array(
                    'encrypted' => $enc_btn,
                    'cmd'       => '_s-xclick',
                );
            }
        }
         /*if ($this->config['encrypt']) {
            $vars = array(
                'encrypted' => $enc_btn,
                'cmd'       => '_s-xclick',
            );
        }*/
        $gateway_vars = '';
        foreach ($vars as $name=>$value) {
            $gateway_vars .= '<input type="hidden" name="' . $name . 
                        '" value="' . $value . '" />' . "\n";
        }
        $T->set_var('paypal_url', $this->getActionUrl());
        $T->set_var('btn_text', $btn_text);
        $T->set_var('gateway_vars', $gateway_vars);
        $retval = $T->parse('', 'btn');
        return $retval;
    }


    /**
    *   Get the command value and template name for the requested button type.
    *
    *   @param  string  $btn_type   Type of button being created
    *   @return array       Array ('cmd'=>command, 'tpl'=>template name
    */
    private function gwButtonType($btn_type='')
    {
        switch ($btn_type) {
        case 'donation':
            $cmd = '_donations';
            $tpl = 'donation';
            break;
        case 'buy_now':
        default:
            $cmd = '_xclick';
            $tpl = 'buy_now';
            break;
        }
        return array('cmd' => $cmd, 'tpl' => $tpl);
    }


    /**
    *   Get the values to show in the "Thank You" message when a customer
    *   returns to our site.
    *
    *   @uses   getMainUrl()
    *   @uses   Gateway::Description()
    *   @return array       Array of name=>value pairs
    */
    public function DEPR_thanksVars()
    {
        $R = array(
            'payment_date'  => $_POST['payment_date'],
            'currency'      => $_POST['mc_currency'],
            'payment_amount' => $_POST['mc_gross'],
            'gateway_url'   => self::getMainUrl(),
            'gateway_name'  => self::Description(),
            'payment_status' => $_POST['payment_status'],
            '_status'       => $_POST['payment_status'] == 'Completed' ?
                        'completed' : 'pending',
        );
        return $R;
    }


    /**
    *   Verify that a given email address is one of our business addresses.
    *   Called during IPN validation.
    *
    *   @param  string  $email  Email address to check (receiver_email)
    *   @return boolean         True if valid, False if not.
    */
    function isBusinessEmail($email)
    {
        switch ($email) {
        case $this->config['bus_prod_email']:
        case $this->config['micro_prod_email']:
        case $this->config['bus_test_email']:
        case $this->config['micro_test_email']:
            $retval = true;
            break;
        default:
            $retval = false;
            break;
        }
        return $retval;
    }


    /**
    *   Present the configuration form for this gateway.
    *
    *   @uses   Gateway::getServiceCheckboxes
    *   @return string      HTML for the configuration form.
    */
    public function Configure()
    {
        global $_CONF, $LANG_PP_paypal, $_PP_CONF;

        $T = new \Template(PAYPAL_PI_PATH . '/templates/');
        if ($_PP_CONF['_is_uikit']) {
            $T->set_file('tpl', 'gateway_edit.uikit.thtml');
        } else {
            $T->set_file('tpl', 'gateway_edit.thtml');
        }

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

        $this->LoadLanguage();

        $T->set_block('tpl', 'ItemRow', 'IRow');
        foreach($this->config as $name=>$value) {
            $other_label = '';
            switch ($name) {
            case 'test_mode':
            case 'encrypt':
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
                'param_name'    => $LANG_PP_paypal[$name],
                'field_name'    => $name,
                'param_field'   => $field,
                'other_label'   => $other_label,
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
    *   @param  array   $A      Array of name=>value pairs (e.g. $_POST)
    */
    function SaveConfig($A)
    {
        if (!is_array($A)) return false;

        foreach ($this->config as $name=>$value) {
            switch ($name) {
            case 'encrypt':
                // Check if the "encrypt" value has changed.  If so, clear the
                // button cache
                $encrypt = isset($A['encrypt']) ? 1 : 0;
                if ($encrypt != $this->config['encrypt'])
                    $this->ClearButtonCache();
                $this->config['encrypt'] = $encrypt;
                break;
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
    *   Get the custom string properly formatted for the gateway.
    *
    *   @return string      Formatted custom string
    */
    protected function PrepareCustom()
    {
        return str_replace('"', '\'', serialize($this->custom));
    }


    /**
    *   Sets the gateway values based on the order amount and testing status.
    *
    *   Sets the receiver_email and cert_id properties.
    *
    *   @param  float   $amount     Total puchase amount.
    */
    private function setReceiver($amount)
    {
        // Available receiver_email addresses
        $aEmail = array(
            array('bus_prod_email', 'micro_prod_email'),
            array('bus_test_email', 'micro_test_email'),
        );

        // Available cert_id properties
        $aCert = array(
            array('pp_cert_id', 'micro_cert_id'),
            array('sandbox_main_cert', 'sandbox_micro_cert'),
        );

        // Set the array keys based on test mode and amount
        $kTest = $this->config['test_mode'] == 1 ? 1 : 0;
        $kAmount = $amount < $this->config['micro_threshold'] ? 1 : 0;
        $this->receiver_email = !empty($this->config[$aEmail[$kTest][$kAmount]]) ?
                $this->config[$aEmail[$kTest][$kAmount]] :
                $this->config[$aEmail[$kTest][0]];

        $this->cert_id = !empty($this->config[$aCert[$kTest][$kAmount]]) ?
                $this->config[$aCert[$kTest][$kAmount]] :
                $this->config[$aCert[$kTest][0]];
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

        $retval = array(
            'pmt_gross'     => $data['mc_gross'] . ' ' . $data['mc_currency'],
            'verified'      => $data['payer_status'],
            'pmt_status'    => $data['payment_status'],
            'buyer_email'   => $data['payer_email'],
        );
        return $retval;
    }

}   // class paypal
 
?>

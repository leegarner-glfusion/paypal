<?php
/**
*   Class to manage Amazon SimplePay buttons.
*   The overhead in signing Amazon buttons is low, so they are not cached
*   in the "paypal.buttons" table.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal\Gateways;

/**
*   Class for Amazon payment gateway
*   @since  0.5.0
*   @package paypal
*/
class amazon extends \Paypal\Gateway
{
    const SIGNATURE_ALGORITHM = 'sha256';   // sha256 or sha1
    const SIGNATURE_METHOD = 'HmacSHA256';  // HmacSHA256 or HmacSHA1

    /** AWS Secret Key
    *   @var string */
    private $secret_key;

    /** AWS Access Key
    *   @var string */
    private $access_key;

    /** AWS payment URL
    *   @var string */
    private $aws_url;

    private $seller_id;
    private $cancel_url;
    private $return_url;
    private $client_id;
    private $client_secret;
    private $queryParams;

    private $js_done = false;

    /**
    *   Constructor
    *   Sets gateway-specific variables and calls the parent constructor
    */
    public function __construct()
    {
        global $_PP_CONF;

        // These are used by the parent constructor, set them first
        $this->gw_name = 'amazon';
        $this->gw_desc = 'Amazon SimplePay';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'prod_seller_id'    => '',
            'prod_access_key'   => '',
            'prod_secret_key'   => '',
            'prod_client_id'    => '',
            'test_seller_id'    => '',
            'test_access_key'   => '',
            'test_secret_key'   => '',
            'test_client_id'    => '',
            'prod_url'          => 'https://authorize.payments.amazon.com',
            'sandbox_url'   => 'https://authorize.payments-sandbox.amazon.com',
            'test_mode'         => 1,
        );

        $this->services = array(
            'checkout' => 1,
        );
        // The parent constructor reads our config items from the database to
        // override defaults
        parent::__construct();

        // parent constructor loads the config array, here we select which
        // keys to use based on test_mode
        if ($this->config['test_mode'] == '1') {
            $this->access_key = $this->config['test_access_key'];
            $this->secret_key = $this->config['test_secret_key'];
            $this->aws_url = $this->config['sandbox_url'];
            $this->seller_id = $this->config['test_seller_id'];
            $this->client_id = $this->config['test_client_id'];
        } else {
            $this->access_key = $this->config['prod_access_key'];
            $this->secret_key = $this->config['prod_secret_key'];
            $this->aws_url = $this->config['prod_url'];
            $this->seller_id = $this->config['prod_seller_id'];
            $this->client_id = $this->config['prod_client_id'];
        }
        $this->currency_code = \Paypal\Currency::getInstance()->code;
        $this->get_shipping = 0;
        $this->return_url = PAYPAL_URL . '/index.php?thanks=amazon';
        $this->cancel_url = PAYPAL_URL . '/index.php?view=cart&cart=';
    }


    /**
    *   Get the main website URL for this payment gateway.
    *   Used to tell the buyer where to log in to check their account.
    *
    *   @return string      Gateway's website URL
    */
    public function getMainUrl()
    {   return 'https://www.amazon.com';    }


    /**
    *   Magic "setter" function.
    *
    *   @param  string  $key    Name of property to set
    *   @param  mixed   $value  Value to set
    */
    public function __set($key, $value)
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
    *   Get the form variables for the cart checkout button.
    *
    *   @uses   _addItem()
    *   @uses   getActionUrl()
    *   @return string      HTML code for the button
    */
    public function checkoutButton($Cart)
    {
        global $_PP_CONF, $_USER;

        if (!$this->Supports('checkout')) {
            return '';
        }
        /*$this->AddCustom('uid', $_USER['uid']);
        $this->AddCustom('cart_id', $Cart->CartID());

        foreach ($Cart->Cart() as $item_id=>$item) {
            $this->_addItem($item_id, $item->price, $item->quantity);
        }
        $gateway_vars = $this->gatewayVars($Cart);

        $T = new \Template(PAYPAL_PI_PATH . '/templates/buttons/' .
                    $this->gw_name);
        $T->set_file(array('btn' => 'btn_checkout.thtml'));
        $T->set_var('action', $this->getActionUrl());
        foreach ($gateway_vars as $name=>$value) {
            $T->set_var($name, $value);
        }
        $retval = $T->parse('', 'btn');
        return $retval;
         */
        return parent::checkoutButton($Cart);
    }


    /**
    *   Get the custom string properly formatted for the gateway.
    *
    *   @return string      Formatted custom string
    */
    public function PrepareCustom()
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
    *   @uses   getActionUrl()
    *   @param  object  $P      Product Object
    *   @return string          HTML for button
    */
    public function ProductButton($P)
    {
        global $_PP_CONF;

        // If this is a physical item, instruct the gateway to collect
        // the shipping address.
        $type = $P->prod_type;
        if ($type & PP_PROD_PHYSICAL == PP_PROD_PHYSICAL) {
            $this->get_shipping = 1;
        }

        //$this->_addItem($P->id, $P->price);
        //$gateway_vars = $this->_getButton($P->btn_type);
        //
        $this->_addJS();

        $T = new \Template(PAYPAL_PI_PATH . '/templates/buttons/' . 
            $this->gw_name);
        $T->set_file(array('btn' => 
                'btn_' . self::gwButtonType($P->btn_type) . '.thtml'));
        $T->set_var(array(
            'amazon_url'    => $this->getActionUrl(),
            'seller_id'     => $this->seller_id,
            'amount'        => $P->getPrice(),
            'currency_code' => \Paypal\Currency::getInstance()->code,
            'returnUrl'     => PAYPAL_URL . '/index.php?thanks=amazon',
            'cancelUrl'     => PAYPAL_URL,

        ) );
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
        if (isset($attribs['options']) && is_array($attribs['options'])) {
            foreach ($attribs['options'] as $name => $value) {
                $this->AddCustom($name, $value);
            }
        }

        $this->_addItem($attribs['item_number'], $attribs['amount']);
        $gateway_vars = $this->_getButton($btn_type);

        $T = new \Template(PAYPAL_PI_PATH . '/templates/buttons/' . 
                    $this->gw_name);
        $T->set_file(array('btn' => 'btn_buy_now.thtml'));
        $T->set_var('amazon_url', $this->getActionUrl());
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
        foreach ($this->items as $item) {
            $total_amount += (float)$item['price'] * (float)$item['quantity'];
            $items[] = implode(';', $item);
        }
        $description = implode('::', $items);
        
        $this->AddCustom('transtype', $btn_type);
        $custom = $this->PrepareCustom();

        $this->queryParams = array(
            'accessKey'         => $this->access_key,
            'amount'            => $total_amount,
            'cancelReturnUrl'   => $this->cancel_url,
            'currencyCode'      => $this->currency_code,
            'lwaClientId'       => $this->client_id,
            'paymentAction'     => 'AuthorizeAndCapture',
            'returnURL'         => $this->return_url,
            'sellerId'          => $this->seller_id,
            'sellerNote'        => $this->seller_note,
            'sellerOrderId'     => $Cart->CartID(),
        );

        // Get shipping info for physical items
        if ($this->get_shipping > 0) {
            $this->queryParams['shippingAddressRequired'] = true;
        }

        $endpoint = parse_url($this->aws_url);
        $stringToSign = "POST\n";
        $stringToSign .= $endpoint['host'] . "\n";
        $stringToSign .= "/\n";
        /*$requestURI = '/pba/paypipeline';
        $uriencoded = implode('/', 
                    array_map(array('amazon', '_urlencode'),
                    explode('/', $requestURI)));
        $stringToSign .= "$uriencoded\n";*/

        $queryparams = array();
        $gateway_vars = '';
        uksort($this->queryParams, 'strcmp');
        $stringToSign = $this->_getParametersAsString();
/*        foreach ($vars as $name=>$value) {
            $queryparams[] = $name . '=' . self::_urlencode($value);
            $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value=\'' . $value . '\'>' . "\n";
        }
        $stringToSign .= implode('&', $queryparams);*/
        $signature = self::Sign($stringToSign);
        /*$gateway_vars .= '<input type="hidden" name="signature" value="' .
            $signature . '">' . "\n";*/

        return $gateway_vars;
    }


    /**
    *   Create the AWS signature from a string of item parameters
    *
    *   @param  string  $toSign     String to be signed
    *   @return string              Signature
    */
    public function Sign($toSign)
    {
        if (!isset($this->secret_key) || empty($this->secret_key)) {
            return '';
        }

        $sig = base64_encode(
            hash_hmac(self::SIGNATURE_ALGORITHM, $toSign, 
                    $this->secret_key, true)
                );
        return $sig;
    }


    /**
    *   Get the action url for the payment button.
    *
    *   @return string      Payment URL
    */ 
    public function getActionUrl()
    {
        return $this->aws_url . '?' . $this->_getParametersAsString();
    }


    /**
    *   Perform the special URL-encoding needed for the Amazon signature.
    *
    *   @param  string  $string     String to encode
    *   @return string              Encoded string
    */
    private function _urlencode($string)
    {
        return str_replace('%7E', '~', rawurlencode($string));
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
    public function gwButtonType($btn_type)
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
    *   Get all the configuration fields specifiec to this gateway
    *
    *   @return array   Array of fields (name=>field_info)
    */
    protected function getConfigFields()
    {
        $fields = array();
        foreach($this->config as $name=>$value) {
            $other_label = '';
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
            $fields[$name] = array(
                'param_field'   => $field,
                'other_label'   => $other_label,
                'doc_url'       => '',
            );
        }
        return $fields;
    }


    /**
    *   Present the configuration form for this gateway.
    *
    *   @uses   PaymmentGw::getServiceCheckboxes
    *   @return string      HTML for the configuration form.
    */
    public function XConfigure()
    {
        global $_CONF;

        $T = new \Template(PAYPAL_PI_PATH . '/templates/');
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

        $langfile = "amazon_{$_CONF['language']}.php";
        if (!is_file(PAYPAL_PI_PATH . '/language/' . $langfile)) {
            $langfile = 'amazon_english.php';
        }
        include_once PAYPAL_PI_PATH . '/language/' . $langfile;

        $T->set_block('tpl', 'ItemRow', 'IRow');
        foreach($this->config as $name=>$value) {
            switch ($name) {
            case 'test_mode':
                $field = '<input type="checkbox" name="' . $name . 
                    '" value="1" ';
                if ($value == 1) $field .= 'checked="checked" ';
                $field .= XHTML . '>';
                break;
            default:
                $field = '<input type="text" name="' . $name . '" value="' .
                    $value . '" size="60" ' . XHTML . '>';
                break;
            }

            $T->set_var(array(
                'param_name'    => $_PP_LANG_amazon[$name],
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
    *   Add a single item to our item array
    *
    *   @param  mixed   $item_id    ID of item, including options
    *   @param  float   $price      Item price
    *   @param  integer $qty        Quantity
    */
    private function _addItem($item_id, $price, $qty=0)
    {
        if ($qty == 0) $qty = 1;
        $qty = (float)$qty;
        $price = (float)$price;
        $this->items[] = array('item_id' => $item_id,
            'price' => $price,
            'quantity' => $qty);
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
            'pmt_gross'     => $amount . ' ' . $currency,
            'verified'      => 'verified',
            'pmt_status'    => 'complete',
            'buyer_email'   => $data['buyerEmail'],
        );
        return $retval;
    }


    public function gatewayVars($Cart)
    {
        $this->_addJS();

        $total_amount = 0;
        foreach ($Cart->Cart() as $Item) {
            $total_amount += (float)$Item->price * (float)$Item->quantity;
        }

        $vars = array(
            'accessKey'         => $this->access_key,
            'amount'            => $total_amount,
            'cancelReturnURL'   => $this->cancel_url . $Cart->CartID(),
            'currencyCode'      => $this->currency_code,
            'lwaClientId'       => $this->client_id,
            'paymentAction'     => 'AuthorizeAndCapture',
            'returnURL'         => $this->return_url,
            'sellerId'          => $this->seller_id,
            'sellerNote'        => $this->seller_note,
            'sellerOrderId'     => $Cart->cartID(),
        );

        // Get shipping info for physical items
        if ($this->get_shipping > 0) {
            $vars['shippingAddressRequired'] = true;
        }

        $endpoint = parse_url($this->aws_url);
        $stringToSign = "POST\n";
        $stringToSign .= $endpoint['host'] . "\n";
        $stringToSign .= "/\n";
        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
        $uriencoded = implode('/', 
            array_map(array($this, '_urlencode'),
            explode('/', $uri)));
        $stringToSign .= "$uriencoded\n";

        $queryparams = array();
        $gateway_vars = '';
        uksort($vars, 'strcmp');
        $this->queryParams = $vars;
        $stringToSign = $this->_getParametersAsString();

        /*foreach ($vars as $name=>$value) {
            $queryparams[] = $name . '=' . self::_urlencode($value);
        }
        $stringToSign .= implode('&', $queryparams);*/
        $this->queryParams['signature'] = $this->Sign($stringToSign);
        return '';
        return $vars;
    }


    private function _addJS()
    {
        if (!$this->js_done) {
            $outputHandle = \outputHandler::getInstance();
            if ($this->config['test_mode']) {
                $outputHandle->addLinkScript('https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js');
            } else {
                $outputHandle->addLinkScript('https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js');
            }
            $this->js_done = true;
        }
    }

    private function _getParametersAsString($params = NULL)
    {
        if ($params === NULL) {
            $params = $this->queryParams;
        }
        $queryParameters = array();
        foreach ($params as $key => $value) {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }


    /**
     * Square doesn't support a "cancel URL", so don't finalize the cart.
     *
     * @return  boolean True to set final, False to leave as "cart"
     */
    protected function _setFinal()
    {
        return false;
    }


    /**
     * Get the form method to use with the final checkout button.
     * Return POST by default
     *
     * @return  string  Form method
     */
    public function getMethod()
    {
        return 'get';
    }


}   // class amazon

?>

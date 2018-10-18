<?php
/**
*   Class to manage Authorize.Net Hosted Accept payments.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal\Gateways;

/**
*   Class for Authorize.Net payment gateway
*   @since  0.5.3
*   @package paypal
*/
class authorizenet extends \Paypal\Gateway
{
    /**
     * Authorize.net transaction key
     * @var string
     */
    private $trans_key;

    /**
     * Authorize.net api login
     * @var string
     */
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
        $this->gw_name = 'authorizenet';
        $this->gw_provider = 'Authorize.Net';
        $this->gw_desc = 'Authorize.Net Accept Hosted';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'prod_api_login'    => '',
            'prod_trans_key'    => '',
            'test_api_login'    => '',
            'test_trans_key'    => '',
//            'prod_md5_hash'     => '',
//            'test_md5_hash'     => '',
            'test_mode'         => 1,
//            'test_hash_key'     => '',
//            'prod_hash_key'     => '',
        );

        // Set the supported services as this gateway only supports cart checkout
        $this->services = array('checkout' => 1); 

        // The parent constructor reads our config items from the database to
        // override defaults
        parent::__construct();

        $this->LoadLanguage();

        // parent constructor loads the config array, here we select which
        // keys to use based on test_mode
        if ($this->config['test_mode'] == '1') {
            $this->api_login = $this->config['test_api_login'];
            $this->trans_key = $this->config['test_trans_key'];
            //$this->hash_key = $this->config['test_hash_key'];
            $this->token_url = 'https://apitest.authorize.net/xml/v1/request.api';
            $this->gw_url = 'https://test.authorize.net/payment/payment';
        } else {
            $this->api_login = $this->config['prod_api_login'];
            $this->trans_key = $this->config['prod_trans_key'];
            //$this->hash_key = $this->config['prod_hash_key'];
            $this->token_url = 'https://api.authorize.net/xml/v1/request.api';
            $this->gw_url = 'https://accept.authorize.net/payment/payment';
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
        case 'token_url':
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
    private function _getMainUrl()
    {
        return 'https://www.authorize.net';
    }


    /**
     *  Get the gateway variables to put in the checkout button.
     *
     *  @param  object      $cart   Shopping Cart Object
     *  @return string      Gateay variable input fields
     */
    public function gatewayVars($cart)
    {
        global $_PP_CONF, $_USER, $LANG_PP;

        // Make sure we have at least one item
        if (empty($cart->Cart())) return '';
        $total_amount = 0;
        $Cur = \Paypal\Currency::getInstance();

        $return_opts = array(
            'url'       => PAYPAL_URL . '/index.php?' . urlencode('thanks=authorizenet'),
            'cancelUrl' => PAYPAL_URL . '/index.php?' . urlencode('view=cart&cid=' . $cart->order_id),
        );

        $json = array(
            'getHostedPaymentPageRequest' => array(
                'merchantAuthentication' => array(
                    'name' => $this->api_login,
                    'transactionKey' => $this->trans_key,
                ),
                'refId' => $cart->order_id,
                'transactionRequest' => array(
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => '0.00',
                    'order' => array(
                        'invoiceNumber' => $cart->order_id,
                    ),
                    'lineItems' => array(),
                    'tax' => array(
                        'amount' => $Cur->FormatValue($cart->tax),
                        'name' => 'Sales Tax',
                    ),
                    'shipping' => array(
                        'amount' => $Cur->FormatValue($cart->shipping),
                        'name' => 'Shipping',
                    ),
                    'customer' => array(
                        'id' => $cart->uid,
                        'email' => $cart->buyer_email,
                    ),
                ),
                'hostedPaymentSettings' => array(
                    'setting' => array(
                        0 => array(
                            'settingName' => 'hostedPaymentReturnOptions',
                            'settingValue' => json_encode($return_opts, JSON_UNESCAPED_SLASHES),
                        ),
                        1 => array(
                            'settingName' => 'hostedPaymentButtonOptions',
                            'settingValue' => '{"text": "Pay"}',
                        ),
                        2 => array(
                            'settingName' => 'hostedPaymentPaymentOptions',
                            'settingValue' => '{"cardCodeRequired": false, "showCreditCard": true, "showBankAccount": true}',
                        ),
                        3 => array(
                            'settingName' => 'hostedPaymentSecurityOptions',
                            'settingValue' => '{"captcha": false}',
                        ),
                        4 => array(
                            'settingName' => 'hostedPaymentIFrameCommunicatorUrl',
                            'settingValue' => '{"url": "' . $this->ipn_url . '"}',
                        ),
                    ),
                ),
            ),
        );

        $by_gc = $cart->getGC();
        if ($by_gc > 0) {
            $total_amount = $cart->getTotal() - $by_gc;
            $json['getHostedPaymentPageRequest']['transactionRequest']['lineItems']['lineItem'][] = array(
                    'itemId' => $LANG_PP['cart'],
                    'name' => $LANG_PP['all_items'],
                    'description' => $LANG_PP['all_items'],
                    'quantity' => 1,
                    'unitPrice' => $Cur->FormatValue($total_amount),
                    'taxable' => false,
            );
        } else {
            foreach ($cart->Cart() as $Item) {
                $P = $Item->getProduct();
                $json['getHostedPaymentPageRequest']['transactionRequest']['lineItems']['lineItem'][] = array(
                    'itemId'    => substr($P->item_id, 0, 31),
                    'name'      => substr($P->short_description, 0, 31),
                    'description' => substr($P->description, 0, 255),
                    'quantity' => $Item->quantity,
                    'unitPrice' => $Cur->FormatValue($Item->price),
                    'taxable' => $Item->taxable ? true : false,
                );
                $total_amount += (float)$Item->price * (float)$Item->quantity;
            }
            $total_amount += $cart->shipping;
            $total_amount += $cart->handling;
            $total_amount += $cart->tax;
        }

        $json['getHostedPaymentPageRequest']['transactionRequest']['amount'] = $Cur->FormatValue($total_amount);
        $jsonEncoded = json_encode($json, JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->token_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonEncoded);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
        )); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {             // Check for a 200 code before anything else
            COM_setMsg("Error checking out");
            return false;
        }
        $bom = pack('H*','EFBBBF');
        $result = preg_replace("/^$bom/", '', $result);
        $result = json_decode($result);
        if ($result->messages->resultCode != 'Ok') {  // Check for errors due to invalid data, etc.
            foreach ($result->messages->message as $msg) {
                COM_errorlog($this->gw_provider . ' error: ' . $msg->code . ' - ' . $msg->text);
            }
            COM_setMsg("Error checking out");
            return false;
        }

        $vars = array(
            'token' => $result->token,
        );
        $gateway_vars = '';
        foreach ($vars as $name=>$value) {
            $gateway_vars .= '<input type="hidden" name="' . $name .
                        '" value=\'' . $value . '\' />' . "\n";
        }
        return $gateway_vars;
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
        $R = array(
            'gateway_name'  => $this->gw_provider,
        );
        return $R;
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
            $fields[$name] = array(
                'param_field'   => $field,
                'other_label'   => $other_label,
                'doc_url'       => '',
            );
        }
        return $fields;
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
    public function SaveConfig($A=NULL)
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
    public function getTransKey()
    {
        return $this->trans_key;
    }


    /**
    *   Get the API Login
    *   Needed by the IPN processor
    *
    *   @return string  API Login ID
    */
    public function getApiLogin()
    {
        return $this->api_login;
    }


    /**
    *   Get a logo image to show on the order as the payment method.
    *
    *   @since  0.6.0
    *   @return string      HTML for logo image
    */
    public function getLogo()
    {
        global $_CONF;
        return '<img src="https://www.authorize.net/content/dam/authorize/images/authorizenet_200x50.png" border="0" alt="Authorize.Net Logo" style="width:160px;height:40px" />';
        //return '<img src="' . $_CONF['site_url'] . '/paypal/images/creditcard.svg" border="0" alt="Authorize.Net" class="tooltip" title="Authorize.Net" style="height:40px;"/>';
    }

}   // class authorizenet

?>

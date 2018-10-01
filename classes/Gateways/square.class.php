<?php
/**
*   Gateway implementation for PayPal.
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

require_once __DIR__ . '/square/connect-php-sdk/autoload.php';


/**
*   Class for Square payment gateway
*   @since 0.6.0
*   @package paypal
*/
class square extends \Paypal\Gateway
{

    /** Square location value
    *   @var string */
    private $loc_id;
    private $appid;
    private $token;

    /**
    *   Constructor.
    *   Set gateway-specific items and call the parent constructor.
    */
    public function __construct()
    {
        global $_PP_CONF, $_USER;

        $supported_currency = array(
            'USD', 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'NZD', 'CHF', 'HKD',
            'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'CZK', 'ILS', 'MXN',
            'PHP', 'TWD', 'THB', 'MYR', 'RUB',
        );

        // These are used by the parent constructor, set them first.
        $this->gw_name = 'square';
        $this->gw_desc = 'SquareConnect';

        // Set default values for the config items, just to be sure that
        // something is set here.
        $this->config = array(
            'sb_loc_id'    => '',
            'sb_appid'     => '',
            'sb_token'     => '',
            'prod_loc_id'  => '',
            'prod_appid'   => '',
            'prod_token'   => '',
            'ipn_url'           => PAYPAL_URL . '/ipn/square.php',
            'test_mode'         => 1,
        );

        // Set the only service supported
        $this->services = array('checkout' => 1);

        // Call the parent constructor to initialize the common variables.
        parent::__construct();

        // Set the gateway URL depending on whether we're in test mode or not
        if ($this->config['test_mode'] == 1) {
            $this->loc_id = $this->config['sb_loc_id'];
            $this->appid = $this->config['sb_appid'];
            $this->token = $this->config['sb_token'];
        } else {
            $this->loc_id = $this->config['prod_loc_id'];
            $this->appid = $this->config['prod_appid'];
            $this->token = $this->config['prod_token'];
        }
        $this->gw_url = NULL;

        // If the configured currency is not one of the supported ones,
        // this gateway cannot be used, so disable it.
        if (!in_array($this->currency_code, $supported_currency)) {
            $this->enabled = 0;
        }

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
    public function getMainUrl()
    {   return '';    }


    /**
    *   Magic "setter" function
    *
    *   @see    Gateway::__get()
    *   @param  string  $key    Name of property to set
    *   @param  mixed   $value  New value for property
    */
    public function __set($key, $value)
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

        default:
            $this->properties[$key] = $value;
            break;
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
    public function gatewayVars($cart)
    {
        global $_PP_CONF, $_USER, $_TABLES, $LANG_PP;

        if (!$this->_Supports('checkout')) {
            return '';
        }

        $cartID = $cart->CartID();
        $shipping = 0;
        $Cur = \Paypal\Currency::getInstance();

        $accessToken = $this->token;
        $locationId = $this->loc_id;

        // Create and configure a new API client object
        $defaultApiConfig = new \SquareConnect\Configuration();
        $defaultApiConfig->setAccessToken($accessToken);
        $defaultApiClient = new \SquareConnect\ApiClient($defaultApiConfig);
        $checkoutClient = new \SquareConnect\Api\CheckoutApi($defaultApiClient);

        $lineItems = array();
        $by_gc = $cart->getInfo('apply_gc');
        if ($by_gc > 0) {
            $total_amount = $cart->getTotal() - $by_gc;
            $PriceMoney = new \SquareConnect\Model\Money;
            $PriceMoney->setCurrency($this->currency_code);
            $PriceMoney->setAmount($Cur->toInt($total_amount));
            $itm = new \SquareConnect\Model\CreateOrderRequestLineItem;
            $itm->setName($LANG_PP['all_items']);
            $itm->setQuantity('1');
            $itm->setBasePriceMoney($PriceMoney);
            //Puts our line item object in an array called lineItems.
            array_push($lineItems, $itm);
        } else {
            foreach ($cart->Cart() as $Item) {
                $P = $Item->getProduct();

                $PriceMoney = new \SquareConnect\Model\Money;
                $PriceMoney->setCurrency($this->currency_code);
                $Item->Price = $P->getPrice($Item->options);
                $PriceMoney->setAmount($Cur->toInt($Item->price));
                $itm = new \SquareConnect\Model\CreateOrderRequestLineItem;
                $opts = $P->getOptionDesc($Item->options);
                $dscp = $Item->description;
                if (!empty($opts)) {
                    $dscp .= ' : ' . $opts;
                }
                $itm->setName($dscp);
                $itm->setQuantity((string)$Item->quantity);
                $itm->setBasePriceMoney($PriceMoney);

                // Add tax, if applicable
                if ($Item->taxable) {
                    $TaxMoney = new \SquareConnect\Model\Money;
                    $TaxMoney->setCurrency($this->currency_code);
                    $taxObj = new \SquareConnect\Model\OrderLineItemTax(
                        array(
                            'percentage' => (string)$Cur->toInt($_PP_CONF['tax_rate']),
                            'name' => 'Sales Tax',
                        )
                    );
                    $tax = $Item->price * $Item->quantity * $_PP_CONF['tax_rate'];
                    $tax = $Cur->toInt($tax);
                    $TaxMoney->setAmount($tax);
                    $taxObj->setAppliedMoney($TaxMoney);
                    $itm->setTaxes(array($taxObj));
                }
                $shipping += $Item->shipping;
            }

            //Puts our line item object in an array called lineItems.
            array_push($lineItems, $itm);
        }

        if ($shipping > 0) {
            $ShipMoney = new \SquareConnect\Model\Money;
            $ShipMoney->setCurrency($this->currency_code);
            $ShipMoney->setAmount($Cur->toInt($shipping));
            $itm = new \SquareConnect\Model\CreateOrderRequestLineItem;
            $itm->setName($LANG_PP['shipping']);
            $itm->setQuantity('1');
            $itm->setBasePriceMoney($ShipMoney);
            array_push($lineItems, $itm);
        }

        // Create an Order object using line items from above
        $order = new \SquareConnect\Model\CreateOrderRequest();
        $order->setIdempotencyKey(uniqid()); //uniqid() generates a random string.
        $order->setReferenceId($cart->cartID());

        //sets the lineItems array in the order object
        $order->setLineItems($lineItems);
        //COM_errorLog(print_r($order,true));

        $checkout = new \SquareConnect\Model\CreateCheckoutRequest();
        $checkout->setPrePopulateBuyerEmail($cart->getInfo('payer_email'));
        $checkout->setIdempotencyKey(uniqid()); //uniqid() generates a random string.
        $checkout->setOrder($order); //this is the order we created in the previous step
        $checkout->setRedirectUrl($this->ipn_url . '?thanks=square');

        $url = '';
        $gatewayVars = array();
        try {
            $result = $checkoutClient->createCheckout(
              $locationId,
              $checkout
            );
            //Save the checkout ID for verifying transactions
            $checkoutId = $result->getCheckout()->getId();
            //Get the checkout URL that opens the checkout page.
            $url = $result->getCheckout()->getCheckoutPageUrl();
        } catch (Exception $e) {
            COM_setMsg('Exception when calling CheckoutApi->createCheckout: ', $e->getMessage(), PHP_EOL);
        }

        //$url = 'https://connect.squareup.com/v2/checkout?c=CBASECUd9G1SQkYybg1uchpFRRogAQ&amp;l=CBASEFAhbyuNAPycQ8Pxr1hdbWIgAQ';
        $url_parts = parse_url($url);
        parse_str($url_parts['query'], $q_parts);
        foreach ($q_parts as $key=>$val) {
            $gatewayVars[] = '<input type="hidden" name="' . $key . '" value="' . $val . '"/>';
        }
        $gateway_vars = implode("\n", $gatewayVars);
        return $gateway_vars;
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
    public function thanksVars()
    {
        $R = array(
            'gateway_url'   => self::getMainUrl(),
            'gateway_name'  => self::Description(),
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
    public function isBusinessEmail($email)
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
    *   @param  array   $A      Array of name=>value pairs (e.g. $_POST)
    */
    public function SaveConfig($A)
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
     * Get the variables to display with the IPN log.
     * This gateway does not have any particular log values of interest.
     *
     * @param  array   $data       Array of original IPN data
     * @return array               Name=>Value array of data for display
     */
    public function ipnlogVars($data)
    {
        return array();
    }


    /**
    *   Get a logo image to show on the order as the payment method.
    *
    *   @since  0.6.0
    *   @return string      HTML for logo image
    */
    public function getLogo()
    {
        global $_CONF, $_PP_CONF;
        return COM_createImage($_CONF['site_url'] . '/' .
            $_PP_CONF['pi_name'] . '/images/gateways/square-logo-100-27.png');
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


    /**
     *   Get the form action URL.
     *   This gets the checkout URL from Square that is created by submitting
     *   an order.
     *
     *   @return string      URL to payment processor
     */
    public function getActionUrl()
    {
        return 'https://connect.squareup.com/v2/checkout';
    }


    /**
     * Additional actions to take after saving the configuration.
     *  - Subscribe to webhooks
     */
    protected function _postSaveConfig()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, '[PAYMENT_UPDATED]');
        curl_setopt($ch, CURLOPT_PUT, true);
        foreach (array('sb', 'prod') as $env) {
            if (empty($this->config[$env . '_token'])) continue;
            $url = "https://connect.squareup.com/v2/{$this->config[$env . '_loc_id']}/webhooks";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Authorization: Bearer {$this->config[$env . '_token']}",
                'Content-Type: application/json',
            ) );
        /*curl_setopt($ch, CURLOPT_ENCODING,       'gzip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR,    1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT,        10);*/
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            var_dump($code);die;
            var_dump($result);die;
        }
    }


    /**
     * Verify a transaction.
     * Gets parameters from $_GET values in the redirect_url
     *
     * @return  boolean True if transaction is good, False if not
     */
    public function getTransaction($trans_id)
    {
        //$trans_id = PP_getVar($_GET, 'transactionId');
        if (empty($trans_id)) {
            return false;
        }
        // Create and configure a new API client object
        $defaultApiConfig = new \SquareConnect\Configuration();
        $defaultApiConfig->setAccessToken($this->token);
        $defaultApiClient = new \SquareConnect\ApiClient($defaultApiConfig);
 
        $api = new \SquareConnect\Api\TransactionsApi();
        $api->setApiClient($defaultApiClient);
        $resp = $api->retrieveTransaction($this->loc_id, $trans_id);
        $resp = json_decode($resp,true);
        return $resp;
    }


    public function runPostSave()
    {
        $this->_postSaveConfig();
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

}   // class square

?>

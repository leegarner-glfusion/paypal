<?php
/**
*   This file contains the IPN processor for Amazon SimplePay.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner
*   @copyright  Portions (c) 2008-2010 Amazon Technologies, Inc.
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @license    Portions http://aws.amazon.com/apache2.0
*               Apache License 2.0
*   @filesource
*/


/** Define failure reasons */
define('AMAZON_FAILURE_UNKNOWN', 1);
define('AMAZON_FAILURE_VERIFY', 2);
define('AMAZON_FAILURE_COMPLETED', 4);
define('AMAZON_FAILURE_UNIQUE', 8);
define('AMAZON_FAILURE_EMAIL', 16);
define('AMAZON_FAILURE_FUNDS', 32);

require_once 'BaseIPN.class.php';

/**
*   Amazon IPN Processor
*   @since  0.5.0
*   @package paypal
*/
class AmazonIPN extends BaseIPN
{
    var $prod_type, $prod_id;   // Product type (cart, buy_now) and ID

    /**
    *   Constructor.
    *   Set up the pp_data array.
    */
    function __construct($A=array())
    {
        $this->gw_id = 'amazon';
        parent::__construct($A);

        list($ccode, $amount) = 
            preg_split('/\ +/', $A['transactionAmount']);

        $this->pp_data['txn_id'] = $A['transactionId'];
        $this->pp_data['payer_email'] = $A['buyerEmail'];
        $this->pp_data['payer_name'] = $A['buyerName'];
        $this->pp_data['pmt_date'] =
                    strftime('%d %b %Y %H:%M:%S', $A['transactionDate']);
        $this->pp_data['pmt_gross'] = (float)$amount;
        $this->pp_data['gw_name'] = $this->gw->Description();
        $this->pp_data['pmt_status'] = $A['status'];

        // Check a couple of vars to see if a shipping address was supplied
        if (isset($A['addressLine1']) || isset($A['city'])) {
            $this->pp_data['shipto'] = array(
                'name'      => $A['addressName'],
                'address1'  => $A['addressLine1'],
                'address2'  => $A['addressLine2'],
                'city'      => $A['city'],
                'state'     => $A['state'],
                'country'   => $A['country'],
                'zip'       => $A['zip'],
                'phone'     => $A['phoneNumber'],
            );
        }

        $custom = explode(';', $A['referenceId']);
        foreach ($custom as $name => $temp) {
            list($name, $value) = explode(':', $temp);
            $this->pp_data['custom'][$name] = $value;
        }

        if ($this->pp_data['custom']['transtype'] == 'cart') {
            USES_paypal_class_cart();
            $cart = new ppCart($this->pp_data['custom']['cart_id']);
            foreach ($cart->Cart() as $itm_id=>$data) {
                $this->AddItem($itm_id, $data['quantity'], $data['price']);
            }
        } else {
            $items = explode('::', $A['paymentReason']);
            foreach ($items as $item) {
                list($itm_id, $price, $qty) = explode(';', $item);
                $this->AddItem($itm_id, $qty, $price);
            }
        }
    }


    /**
    *   Process the transaction.
    *   Verifies that the transaction is valid, then records the purchase and
    *   notifies the buyer and administrator
    *
    *   @uses   Validate()
    *   @uses   BaseIPN::isUniqueTxnId()
    *   @uses   BaseIPN::handlePurchase()
    */
    public function Process()
    {
        if (0 != $this->Validate())
            return false;

        if ('PS' != $this->pp_data['pmt_status']) 
            return false;
        else
            $this->pp_data['status'] = 'paid';

        if (!$this->isUniqueTxnId($this->pp_data))
            return false;

        // Log the IPN.  Verified is 'true' if we got this far.
        $LogID = $this->Log(true);

        $this->handlePurchase();
    }


    /**
    *   Check that this is a valid purchase IPN.
    *   Checks with Amazon to verify the signature, and also checks the amount
    *   received against the items purchased.
    *
    *   @uses   SignatureUtilsForOutbound
    *   @return integer     Zero on success, positive value on failure
    */
    private function Validate()
    {
        // First, use Amazon's utility to verify the IPN signature.
        $utils = new SignatureUtilsForOutbound();
        $urlEndPoint = PAYPAL_URL . '/ipn/amazon_ipn.php';

        if (TESTING) {
            $valid = true;
        } else {
            $valid = $utils->validateRequest($this->ipn_data, $urlEndPoint, 'POST');
        }

        if (!$valid) {
            $this->Error('Invalid IPN received');
            return AMAZON_FAILURE_VERIFY;
        }

        // Make sure we can figure out what was purchased
        if (empty($this->ipn_data['referenceId'])) {
            $this->Error('Missing Order or Item ID');
            return AMAZON_FAILURE_UNKNOWN;
        }

        if (!$this->isSufficientFunds()) {
            return AMAZON_FAILURE_FUNDS;
        }

        return 0;
    }

}   // class AmazonIPN



/******************************************************************************
 *	Copyright 2008-2010 Amazon Technologies, Inc.
 *	Licensed under the Apache License, Version 2.0 (the 'License');
 *
 *	You may not use this file except in compliance with the License.
 *	You may obtain a copy of the License at: http://aws.amazon.com/apache2.0
 *	This file is distributed on an 'AS IS' BASIS, WITHOUT WARRANTIES OR
 *	CONDITIONS OF ANY KIND, either express or implied. See the License for the
 *	specific language governing permissions and limitations under the License.
 ******************************************************************************/

class SignatureException extends Exception
{
    function __construct($msg)
    {
        COM_errorLog('Amazon IPN Exception: ' . $msg);
    }

}   // class SignatureException


class SignatureUtilsForOutbound {
	 
    const SIGNATURE_KEYNAME = "signature";
    const SIGNATURE_METHOD_KEYNAME = "signatureMethod";
    const SIGNATURE_VERSION_KEYNAME = "signatureVersion";
    const CERTIFICATE_URL_KEYNAME = "certificateUrl";

    const FPS_PROD_ENDPOINT = 'https://fps.amazonaws.com/';
    const FPS_SANDBOX_ENDPOINT = 'https://fps.sandbox.amazonaws.com/';
    const USER_AGENT_IDENTIFIER = 'ASPDonation-PHP-2.0-2010-09-13';

	//cache of the public key so that it need not be fetched every time!
    static $public_key_cache = array();

    public function __construct() {
    	if (!function_exists('curl_init') ||
	    !function_exists('curl_setopt') ||
	    !function_exists('curl_exec')){
		throw new SignatureException('The cURL extension has not been installed in this PHP environment (http://php.net/curl)');
	}

		// Bail if OpenSSL extension is missing
	/*if (!function_exists('openssl_x509_parse')){
		throw new SignatureException('The OpenSSL extension has not been installed in this PHP environment (http://php.net/openssl)');
	}*/

		// Bail if SimpleXML extension is missing (built-in to PHP 5.0+; can be disabled at compile time)
	if (!class_exists('SimpleXMLElement')){
		throw new SignatureException('The SimpleXML extension has not been compiled into this PHP environment (http://php.net/simplexml)');
	}

	return $this;

    }
	
    /**
     * Validates the request by checking the integrity of its parameters.
     * @param parameters - all the http parameters sent in IPNs or return urls. 
     * @param urlEndPoint should be the url which recieved this request. 
     * @param httpMethod should be either POST (IPNs) or GET (returnUrl redirections)
     * Verifies the signature. 
     * Only default algorithm OPENSSL_ALGO_SHA1 is supported.
     */
    public function validateRequest(array $parameters, $urlEndPoint, $httpMethod)  {
	//1. Input validation
	    $signature = $parameters[self::SIGNATURE_KEYNAME];
	    if (!isset($signature)) {
	    	throw new SignatureException("'signature' is missing from the parameters.");
	    }
            $signatureVersion = $parameters[self::SIGNATURE_VERSION_KEYNAME];
	    if (!isset($signatureVersion)) {
	    	throw new SignatureException("'signatureVersion' is missing from the parameters.");
	    }
	    $signatureMethod = $parameters[self::SIGNATURE_METHOD_KEYNAME];
	    if (!isset($signatureMethod)) {
	    	throw new SignatureException("'signatureMethod' is missing from the parameters.");
	    }
	    $signatureAlgorithm = self::getSignatureAlgorithm($signatureMethod);
	    if (!isset($signatureAlgorithm)) {
	    	throw new SignatureException("'signatureMethod' present in parameters is invalid. Valid values are: RSA-SHA1");
	    }
	    $certificateUrl = $parameters[self::CERTIFICATE_URL_KEYNAME];
	    if (!isset($certificateUrl)) {
	    	throw new Exception("'certificateUrl' is missing from the parameters.");
	    }
	    elseif((stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_PROD_ENDPOINT) !== 0) 
	        && (stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_SANDBOX_ENDPOINT) !== 0)){
			throw new SignatureException('The `certificateUrl` value must begin with ' . self::FPS_PROD_ENDPOINT . ' or ' . self::FPS_SANDBOX_ENDPOINT . '.');
		}
	     $verified = $this->verifySignature($parameters, $urlEndPoint);
	    if (!$verified){
		throw new SignatureException('Certificate could not be verified by the FPS service');
	    }

	     return $verified;
    }
    
    private static function getSignatureAlgorithm($signatureMethod) {
        if ("RSA-SHA1" == $signatureMethod) {
            return OPENSSL_ALGO_SHA1;
        }
        return null;
    }
   private function httpsRequest($url){
		// Compose the cURL request
   	   $curlHandle = curl_init();
   	   curl_setopt($curlHandle, CURLOPT_URL, $url);
   	   curl_setopt($curlHandle, CURLOPT_FILETIME, false);
   	   curl_setopt($curlHandle, CURLOPT_FRESH_CONNECT, true);
   	   curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
   	   curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
   	   curl_setopt($curlHandle, CURLOPT_CAINFO, 'ca-bundle.crt');
   	   curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, false);
   	   curl_setopt($curlHandle, CURLOPT_MAXREDIRS, 0);
   	   curl_setopt($curlHandle, CURLOPT_HEADER, true);
   	   curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
   	   curl_setopt($curlHandle, CURLOPT_NOSIGNAL, true);
   	   curl_setopt($curlHandle, CURLOPT_USERAGENT, self::USER_AGENT_IDENTIFIER);
   		// Handle the encoding if we can.
   	   if (extension_loaded('zlib')){
   	   	curl_setopt($curlHandle, CURLOPT_ENCODING, '');
   	   }
   	
   	    // Execute the request
   	   $response = curl_exec($curlHandle);
   		
	    // Grab only the body
   	   $headerSize = curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);
   	   $responseBody = substr($response, $headerSize);
   	
   		// Close the cURL connection
   	   curl_close($curlHandle);
   	
   		// Return the public key
   	   return $responseBody;
	}

	/**
	 * Method: verify_signature
	 */
	private function verifySignature($parameters, $urlEndPoint){
		// Switch hostnames
		if (stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_SANDBOX_ENDPOINT) === 0){
			$fpsServiceEndPoint = self::FPS_SANDBOX_ENDPOINT;
		}
		elseif (stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_PROD_ENDPOINT) === 0){
			$fpsServiceEndPoint = self::FPS_PROD_ENDPOINT;
		}

		$url = $fpsServiceEndPoint . '?Action=VerifySignature&UrlEndPoint=' . rawurlencode($urlEndPoint);

		$queryString = rawurlencode(http_build_query($parameters, '', '&'));
		//$queryString = str_replace(array('%2F', '%2B'), array('%252F', '%252B'), $queryString);

		$url .= '&HttpParameters=' . $queryString . '&Version=2008-09-17';

		$response = $this->httpsRequest($url);
		$xml = new SimpleXMLElement($response);
		$result = (string) $xml->VerifySignatureResult->VerificationStatus;

		return ($result === 'Success');
	}

}   // class SignatureUtilsForOutbound

?>

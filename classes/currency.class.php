<?php
//  $Id: currency.class.php 7511 2014-06-10 21:28:44Z lgarner $
/**
*   Class to handle currency display
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2014 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*              GNU Public License v2 or later
*   @filesource
*/


class ppCurrency
{
    var $defaultCurrency;
    private $prefixes = array();
    private $postfixes = array();
    public $currencies;

    /**
    *   Constructor. Simply sets an initial default currency.
    *
    *   @param  string  $defCur Currency code to set as default
    */
    public function __construct($defCur='USD')
    {
        $this->setDefault($defCur);
    }


    /*
    *   Load a single currency type from the database
    *   Uses a static array so repeated calls don't cause repeated DB queries.
    *
    *   @param  string  $code   Currency code, e.g. "USD", "GBP"
    *   @return array       Complete record from DB
    */
    public function Load($code)
    {
        global $_TABLES;

        static $currencies = array();

        if (!isset($currencies[$code])) {
            $currencies[$code] = FALSE;
            $res = DB_query("SELECT * FROM {$_TABLES['paypal.currency']}
                    WHERE code = '" . DB_escapeString($code) . "'");
            if ($res) {
                $currencies[$code] = DB_fetchArray($res, false);
            }
        }
        return $currencies[$code];
    }


    /**
    *   Sets and loads a new default currency
    *
    *   @param  string  $code   New currency code
    */
    public function setDefault($code)
    {
        $this->defaultCurrency = $code;
        $this->Load($code);
    }


    /**
    *   Return the number of decimal places associated with a currency
    *
    *   @param  string  $code   Currency code to check, blank for default
    *   @return integer     Number of decimal places used for the currency
    */
    public function Decimals($code='')
    {
        if (empty($code)) $code = $this->defaultCurrency;
        $currency = $this->Load($code);
        return $currency['decimals'];
    }


    /**
    *   Return the prefix, if any, for a currency
    *
    *   @param  string  $code   Currency code to check, blank for default
    *   @return string      Prefix, e.g. dollar sign
    */
    public function Pre($code='')
    {
        if (empty($code)) $code = $this->defaultCurrency;

        if (!isset($this->prefixes[$code])) {
            $currency = $this->Load($code);
            $this->prefixes[$code] = '';
            if ($currency['symbol_placement'] == 'before') {
                $this->prefixes[$code] .= $currency['symbol'] . $currency['symbol_spacer'];
            }

            if ($currency['code_placement'] == 'before') {
                $this->prefixes[$code] .= $currency['code'] . $currency['code_spacer'];
            }
        }
        return $this->prefixes[$code];
    }


    /**
    *   Return the postfix, if any, for a currency
    *
    *   @param  string  $code   Currency code to check, blank for default
    *   @return string      Postfix, e.g. Euro sign
    */
    public function Post($code='')
    {
        if (empty($code)) $code = $this->defaultCurrency;

        if (!isset($this->postfixes[$code])) {
            $currency = $this->Load($code);
            $this->postfixes[$code] = '';
            if ($currency['symbol_placement'] == 'after') {
                $this->postfixes[$code] .= $currency['symbol'] . $currency['symbol_spacer'];
            }

            if ($currency['code_placement'] == 'after') {
                $this->postfixes[$code] .= $currency['code'] . $currency['code_spacer'];
            }
        }
        return $this->postfixes[$code];
    }

 
    /**
    *   Get the formatted string for an amount.
    *   e.g. "$ 125.00"
    *
    *   @param  float   $amount Dollar amount
    *   @param  string  $code   Currency code used, blank for default
    *   @return string      Formatted string for display
    */
    public function Format($amount, $code='')
    {
        $val = $this->_Format($amount, $code);
        return $val[0] . $val[1] . $val[2];
    }


    /**
    *   Get just the numeric part of the formatted price
    *   e.g. "125.00" for "125"
    *
    *   @param  float   $amount Dollar amount
    *   @param  string  $code   Currency code used, blank for default
    *   @return float       Formatted numeric value
    */
    public function FormatValue($amount, $code='')
    {
        $val = $this->_Format($amount, $code);
        return $val[1];
    }


    /**
    * Formats a price for a particular currency.
    *
    * @param    float   $amount A numeric price amount value.
    * @param    string  $code   The three character code of the currency.
    *
    * @return   array   Array of prefix, number, postfix
    */
    private function _Format($amount, $code='')
    {
        static $formatted = array();

        // First load the currency array.
        if (empty($code)) {
            $code = $this->defaultCurrency;
        }

        $key = "{$code}{$amount}";
        if (isset($formatted[$key])) return $formatted[$key];
        
        $currency = $this->Load($code);

        // Format the price as a number.
        $price = number_format($this->currencyRound(abs($amount), $currency), 
                    $currency['decimals'], 
                    $currency['decimal_sep'],
                    $currency['thousands_sep']);

        $negative = $amount < 0 ? '-' : '';
        $formatted[$key] = array($this->Pre($code), $negative.$price, $this->Post($code));
        return $formatted[$key];
    }


    /**
    * Rounds a price amount for the specified currency.
    *
    * Rounding of the minor unit with a currency specific step size. For example,
    * Swiss Francs are rounded using a step size of 0.05. This means a price of
    * 10.93 is converted to 10.95.
    *
    * @param $amount
    *   The numeric amount value of the price to be rounded.
    * @param $currency
    *   The currency array containing the rounding information pertinent to this
    *     price. Specifically, this function looks for the 'rounding_step' property
    *     for the step size to round to, supporting '0.05' and '0.02'. If the value
    *     is 0, this function performs normal rounding to the nearest supported
    *     decimal value.
    *
    * @return
    *   The rounded numeric amount value for the price.
    */
    function currencyRound($amount, $currency)
    {
        if ($currency['rounding_step'] < .01) {
            return round($amount, $currency['decimals']);
        }

        $modifier = 1 / $currency['rounding_step'];
        return round($amount * $modifier) / $modifier;
    }

    /**
    * Converts a price amount from a currency to the target currency based on the
    *   current currency conversion rates.
    *
    * To convert an amount from one currency to another, we simply take the amount
    * value and multiply it by the current currency's conversion rate divided by
    * the target currency's conversion rate.
    *
    * @param $amount
    *   The numeric amount value of the price to be rounded.
    * @param $currency_code
    *   The currency code for the current currency of the price.
    * @param $target_currency_code
    *   The currency code for the target currency of the price.
    *
    * @return
    *   The numeric amount value converted to its equivalent in the target currency.
    */
    public function Convert($amount, $toCurrency, $fromCurrency='')
    {
       return $amount * $this->ConversionRate($toCurrency, $fromCurrency);
    }


    /**
    *   Get the conversion rate between currencies.
    *   If $from is not specified, uses the current default. $to must be given.
    *
    *   @param  string  $toCurrency     Destination currency code
    *   @param  string  $fromCurrency   Starting currency code
    *   @return float       Conversion rate to get $from to $to
    */
    public function ConversionRate($toCurrency, $fromCurrency='')
    {
        static $rates = array();

        if (empty($fromCurrency)) $fromCurrency = $this->defaultCurrency;

        if (!isset($rates[$fromCurrency][$toCurrency])) {
            $amount = urlencode($amount);
            $from_Currency = urlencode($fromCurrency);
            $to_Currency = urlencode($toCurrency);

            $url = "http://download.finance.yahoo.com/d/quotes.csv?s={$from_Currency}{$to_Currency}=X&f=l1";
            $timeout = 0;
            $ch = curl_init();
            curl_setopt ($ch, CURLOPT_URL, $url);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($ch, CURLOPT_USERAGENT,
                 "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);
            if (!isset($rates[$fromCurrency])) $rates[$fromCurrency] = array();
            $rates[$fromCurrency][$toCurrency] = trim($data);
        }
        return (float)$data;
    }

 
    /**
    *   Get all currency info.
    *   Used by the plugin configuration to create a dropdown list
    *   of currencies.
    *
    *   @return array   Array of all DB records
    */
    public static function GetAll()
    {
        global $_TABLES;

        static $currencies = NULL;
        if ($currencies === NULL) {
            $res = DB_query("SELECT * FROM {$_TABLES['paypal.currency']}");
            while ($A = DB_fetchArray($res, false)) {
                $currencies[$A['code']] = $A;
            }
        }
        return $currencies;
    }

}

?>

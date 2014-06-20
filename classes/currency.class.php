<?php
/**
 *  Class to handle currency display
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2014 Lee Garner <lee@leegarner.com>
 *  @package    paypal
 *  @version    0.5.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

// Define our own rounding constants since we can't depend on PHP 5.3.
define('COMMERCE_ROUND_NONE', 0);
define('COMMERCE_ROUND_HALF_UP', 1);
define('COMMERCE_ROUND_HALF_DOWN', 2);
define('COMMERCE_ROUND_HALF_EVEN', 3);
define('COMMERCE_ROUND_HALF_ODD', 4);


class ppCurrency
{
    var $defaultCurrency;
    private $prefixes = array();
    private $postfixes = array();
    public $currencies;

    public function __construct($defCur='USD')
    {
        $this->setDefault($defCur);
    }

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


    public function Decimals($code='')
    {
        if (empty($code)) $code = $this->defaultCurrency;
        $currency = $this->Load($code);
        return $currency['decimals'];
    }


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
    */
    public function Format($amount, $code='')
    {
        $val = $this->_Format($amount, $code);
        return $val[0] . $val[1] . $val[2];
    }


    /**
    *   Get just the numeric part of the formatted price
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

        /*
        switch ($currency['symbol_placement']) {
        case 'before':
            $symbol_before = $currency['symbol'] . $currency['symbol_spacer'];
            $symbol_after = '';
            break;
        case 'after':
            $symbol_after = $currency['symbol_spacer'] . $currency['symbol'];
            $symbol_before = '';
            break;
        case 'hidden':
        default:
            $symbol_before = '';
            $symbol_after = '';
            break;
        }

        switch ($currency['code_placement']) {
        case 'before':
            $code_before = $currency['code'] . $currency['code_spacer'];
            $coce_after = '';
            break;
        case 'after':
            $code_after = $currency['code_spacer'] . $currency['code'];
            $coce_before = '';
            break;
        case 'hidden':
            $code_after = '';
            $code_before = '';
            break;
        }
        */

        $negative = $amount < 0 ? '-' : '';
        /*$replacements = array(
            '@code_before' => $code_before,
            '@symbol_before' => $symbol_before,
            '@price' => $price,
            '@symbol_after' => $symbol_after,
            '@code_after' => $code_after,
            '@negative' => $negative,
        );*/

        /*$stringval = $code_before . $symbol_before,
        foreach ($replacements as $name => $value) {
            $retval .= $value;
        }*/
        //return $retval;
//        $formatted[$key] = array($code_before.$symbol_before, $negative.$price, $symbol_after.$code_after);
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
        if (empty($fromCurrency)) $fromCurrency = $this->defaultCurrency;

        if (!isset($this->currencies[$toCurrency]['conversion_rate'])) {
            $amount = urlencode($amount);
            $from_Currency = urlencode($fromCurrency);
            $to_Currency = urlencode($toCurrency);

            //$url = "http://www.google.com/finance/converter?a=$amount&from=$from_Currency&to=$to_Currency";
            $url = "http://download.finance.yahoo.com/d/quotes.csv?s={$from_Currency}{$to_Currency}=X&f=l1";
            $timeout = 0;
            $ch = curl_init();
            curl_setopt ($ch, CURLOPT_URL, $url);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($ch, CURLOPT_USERAGENT,
                 "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            //$rawdata = curl_exec($ch);
            $data = curl_exec($ch);
            curl_close($ch);
            //$data = explode('bld>', $rawdata);
            //$data = explode($to_Currency, $data[1]);
            //        return round($data[0], 2);

            $this->currencies[$toCurrency]['conversion_rate'] = trim($data);
        }
        return $amount * ($this->currencies[$toCurrency]['conversion_rate']);
    }


    /**
    * Rounds a number using the specified rounding mode.
    *
    * @param $round_mode
    *   The round mode specifying which direction to round the number.
    * @param $number
    *   The number to round.
    *
    * @return
    *   The number rounded based on the specified mode.
    */
    function xcurrencyRound($round_mode, $number)
    {
        // Remember if this is a negative or positive number and make it positive.
        $negative = $number < 0;
        $number = abs($number);

        // Store the decimal value of the number.
        $decimal = $number - floor($number);

        // No need to round if there is no decimal value.
        if ($decimal == 0) {
            return $negative ? -$number : $number;
        }

        // Round it now according to the specified round mode.
        switch ($round_mode) {
        // PHP's round() function defaults to rounding the half up.
        case COMMERCE_ROUND_HALF_UP:
            $number = round($number);
              break;

        // PHP < 5.3.0 does not support rounding the half down, so we compare the
        // decimal value and use floor() / ceil() directly.
        case COMMERCE_ROUND_HALF_DOWN:
            if ($decimal <= .5) {
                $number = floor($number);
            } else {
                $number = ceil($number);
            }
            break;

        // PHP < 5.3.0 does not support rounding to the nearest even number, so we
        // determine it ourselves if the decimal is .5.
        case COMMERCE_ROUND_HALF_EVEN:
            if ($decimal == .5) {
                if (floor($number) % 2 == 0) {
                    $number = floor($number);
                } else {
                    $number = ceil($number);
                }
            } else {
                $number = round($number);
            }
            break;

        // PHP < 5.3.0 does not support rounding to the nearest odd number, so we
        // determine it ourselves if the decimal is .5.
        case COMMERCE_ROUND_HALF_ODD:
            if ($decimal == .5) {
                if (floor($number) % 2 == 0) {
                    $number = ceil($number);
                } else {
                    $number = floor($number);
                }
            } else {
                $number = round($number);
            }
            break;

        case COMMERCE_ROUND_NONE:
        default:
            break;
        }

        // Return the number preserving the initial negative / positive value.
        return $negative ? -$number : $number;
    }


    /**
    *   Get all currency info.
    *   Used by the plugin configuration to create a dropdown list
    *   of currencies.
    *
    *   @return array   Array of all DB records
    */
    public function GetAll()
    {
        global $_TABLES;

        $currencies = array();
        $res = DB_query("SELECT * FROM {$_TABLES['paypal.currency']}");
        if (!$res) return $A;

        while ($A = DB_fetchArray($res, false)) {
            $currencies[$A['code']] = $A;
        }
        return $currencies;
    }

}

?>

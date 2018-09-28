<?php
/**
*   Class to handle currency display
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2014-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.5.4
*   @license    http://opensource.org/licenses/gpl-2.0.php
*              GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
 * @class   Currency
 * @package paypal
 */
class Currency
{
    private $properties = array();

    /**
    *   Constructor. Simply sets an initial default currency.
    *
    *   @param  mixed   $code   Currency code, or DB record as an array
    */
    public function __construct($code = NULL)
    {
        global $_PP_CONF, $_TABLES;

        if (is_array($code)) {      // a DB record already read
            $this->setVars($code);
        } else {                    // just a currency code supplied
            if ($code === NULL) $code = $_PP_CONF['currency'];
            $res = DB_query("SELECT * FROM {$_TABLES['paypal.currency']}
                        WHERE code = '" . DB_escapeString($code) . "'");
            if ($res) {
                $A = DB_fetchArray($res, false);
                $this->setVars($A);
            }
        }
    }


    /**
    *   Get an instance of a currency.
    *   Caches in a static variable for quick repeated retrivals,
    *   and also caches using glFusion caching if available.
    *
    *   @param  string  $code   Currency Code
    *   @return array           Array of information
    */
    public static function getInstance($code = NULL)
    {
        global $_PP_CONF;
        static $currencies = array();

        if ($code === NULL) $code = $_PP_CONF['currency'];

        if (!isset($currencies[$code])) {
            $key = 'currency_' . $code;
            $currencies[$code] = Cache::get($key);
            if (!$currencies[$code]) {
                $currencies[$code] = new self($code);
                Cache::set($key, $currencies[$code]);
            }
        }
        return $currencies[$code];
    }


    /**
    *   Set all the record values into properties
    *
    *   @since  0.6.0
    *   @param  array   $A      Array of key->value pairs
    */
    public function setVars($A)
    {
        $fields = array(
            'code', 'symbol', 'name', 'numeric_code',
            'symbol_placement', 'symbol_spacer',
            'code_placement', 'decimals',
            'rounding_step', 'thousands_sep', 'decimal_sep',
            'major_unit', 'minor_unit',
            'conversion_rate', 'conversion_ts',
        );
        foreach ($fields as $field) {
            $this->$field = $A[$field];
        }
     }


    /**
    *   Set a property value
    *
    *   @since  0.6.0
    *   @param  string  $key    Property Name
    *   @param  mixed   $value  Property Value
    */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'code':
        case 'symbol':
        case 'name':
        case 'symbol_placement':
        case 'symbol_spacer':
        case 'code_placement':
        case 'thousands_sep':
        case 'decimals_sep':
        case 'major_unit':
        case 'minor_unit':
        case 'conversion_ts':
            $this->properties[$key] = trim($value);
            break;

        case 'numeric_code':
        case 'decimals':
            $this->properties[$key] = (int)$value;
            break;

        case 'rounding_step':
        case 'conversion_rate':
            $this->properties[$key] = (float)$value;
            break;
        }
    }


    /**
    *   Get a property value
    *
    *   @since  0.6.0
    *   @param  string  $key    Property Name
    *   @return mixed           Property Value, NULL if not set
    */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
    }


    /**
    *   Return the number of decimal places associated with a currency
    *
    *   @return integer     Number of decimal places used for the currency
    */
    public function Decimals()
    {
        return $this->decimals;
    }


    /**
    *   Return the prefix, if any, for a currency
    *
    *   @return string      Prefix, e.g. dollar sign
    */
    public function Pre()
    {
        $prefix = '';
        if ($this->symbol_placement == 'before') {
            $prefix .= $this->symbol . $this->symbol_spacer;
        }

        if ($this->code_placement == 'before') {
            $prefix .= $this->code . $this->code_spacer;
        }
        return $prefix;
    }


    /**
    *   Return the postfix, if any, for a currency
    *
    *   @return string      Postfix, e.g. Euro sign
    */
    public function Post()
    {
        $postfix = '';
        if ($this->symbol_placement == 'after') {
            $postfix .= $this->symbol . $this->symbol_spacer;
        }

        if ($this->code_placement == 'after') {
            $postfix .= $this->code . $this->code_spacer;
        }
        return $postfix;
    }


    /**
    *   Get the formatted string for an amount.
    *   e.g. "$ 125.00"
    *
    *   @param  float   $amount Dollar amount
    *   @return string      Formatted string for display
    */
    public function Format($amount)
    {
        $val = $this->_Format($amount);
        return $val[0] . $val[1] . $val[2];
    }


    /**
    *   Get just the numeric part of the formatted price
    *   e.g. "125.00" for "125"
    *
    *   @param  float   $amount Dollar amount
    *   @return float       Formatted numeric value
    */
    public function FormatValue($amount)
    {
        $val = $this->_Format($amount);
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
        $price = number_format($this->currencyRound(abs($amount)),
            $this->decimals,
            $this->decimal_sep,
            $this->thousands_sep
        );
        $negative = $amount < 0 ? '-' : '';
        $formatted = array($this->Pre(), $negative.$price, $this->Post());
        return $formatted;
    }


    /**
    *   Rounds a price amount for the specified currency.
    *
    *   Rounding of the minor unit with a currency specific step size. For example,
    *   Swiss Francs are rounded using a step size of 0.05. This means a price of
    *   10.93 is converted to 10.95.
    *
    *   @param  float   $amount The numeric amount value of the price to be rounded.
    *   @return string          The rounded numeric amount value for the price.
    */
    private function currencyRound($amount)
    {
        if ($this->rounding_step < .01) {
            return round($amount, $this->decimals);
        }
        $modifier = 1 / $this->rounding_step;
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
    public static function Convert($amount, $toCurrency, $fromCurrency='')
    {
       return $amount * self::getConversionRate($toCurrency, $fromCurrency);
    }


    /**
    *   Get the conversion rate between currencies.
    *   If $from is not specified, uses the current default. $to must be given.
    *
    *   @param  string  $to     Target currency code
    *   @param  string  $from   Starting currency code
    *   @return float       Conversion rate to get $from to $to
    */
    public static function getConversionRate($to, $from='')
    {
        global $_PP_CONF;

        if (empty($from)) $from = $_PP_CONF['currency'];

        // currencyconverterapi keys currencies as "from_to"
        $key = $from . '_' . $to;
        $cache_key = 'currency_convert_' . $key;
        $data = Cache::get($cache_key);
        if ($data === NULL) {
            $from = urlencode($from);
            $to = urlencode($to);

            $url = "https://free.currencyconverterapi.com/api/v5/convert?q={$key}&compact=y";
            //$url = "http://finance.yahoo.com/d/quotes.csv?s={$from}{$to}=X&f=l1";
            $timeout = 0;
            $ch = curl_init();
            curl_setopt ($ch, CURLOPT_URL, $url);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($ch, CURLOPT_USERAGENT,
                 "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($data,true);
            if ($data !== NULL) {
                Cache::set($cache_key, $data, 'currencies', 3600);
            }
        }

        if ($data !== NULL && isset($data[$key]['val'])) {
            $rate = (float)$data[$key]['val'];
        } else {
            $rate = false;
        }
        return $rate;
    }


    /**
    *   Get all currency info.
    *   Used by the plugin configuration to create a dropdown list
    *   of currencies.
    *
    *   @return array   Array of all DB records
    */
    public static function getAll()
    {
        global $_TABLES;

        static $currencies = NULL;
        if ($currencies === NULL) {
            $currencies = array();
            $res = DB_query("SELECT * FROM {$_TABLES['paypal.currency']}");
            while ($A = DB_fetchArray($res, false)) {
                $currencies[$A['code']] = new self($A);
            }
        }
        return $currencies;
    }


    /**
     * Convert all prices and fees to a new currency.
     *
     * @param   string  $from   Old currency code
     * @param   string  $to     New currency code
     */
    public static function convertAll($from, $to='')
    {
        global $_PP_CONF, $LANG_PP;

        if ($to == '') $to = $_PP_CONF['currency'];
        if (empty($from) || empty($to) || $from  == $to) {
            COM_setMsg($LANG_PP['no_cur_change']);
            return;
        }
        $rate = self::getConversionRate($to, $from);
        if ($rate == false) {
            COM_setMsg($LANG_PP['no_cur_change']);
            return;
        }

        // A rate was obtained, now convert all the currency numbers
        Order::convertAllCurrency($from, $to, $rate);
        Sales::convertAllCurrency($from, $to, $rate);
        Product::convertAllCurrency($from, $to, $rate);
        Coupon::convertAllCurrency($from, $to, $rate);
        Cache::clear();
        COM_setMsg(sprintf($LANG_PP['cur_changed'], $from, $to));
        return;
    }


    /**
     * Convert an amount to an integer based on the number of decimals.
     * Example: $1.95 US becomes 195
     *
     * @param   float   $amount     Money amount to convert
     * @return  int                 Integer version of the amount
     */
    public function toInt($amount)
    {
        return (int)($amount * (10 ** $this->decimals));
    }


    /**
     * Convert an amount to an integer based on the number of decimals.
     * Example: 195 becomes 1.95
     *
     * @param   int     $amount     Integer version of the amount
     * @return  float                Money amount to convert
     */
    public function fromInt($intval)
    {
        return (float)($intval / (10 ** $this->decimals));
    }

}

?>

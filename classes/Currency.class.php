<?php
/**
 * Class to handle currency display.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2014-2018 Lee Garner <lee@leegarner.com>
 * @package     paypal
 * @version     v0.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Paypal;

/**
 * Class to handle currencies.
 * @package paypal
 */
class Currency
{
    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /**
     * Constructor. Simply sets an initial default currency.
     *
     * @param   mixed   $code   Currency code, or DB record as an array
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
     * Get an instance of a currency.
     * Caches in a static variable for quick repeated retrivals,
     * and also caches using glFusion caching if available.
     *
     * @param   string  $code   Currency Code
     * @return  array           Array of information
     */
    public static function getInstance($code = NULL)
    {
        global $_PP_CONF;
        static $currencies = array();

        if (empty($code)) $code = $_PP_CONF['currency'];

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
     * Set all the record values into properties.
     *
     * @since   v0.6.0
     * @param   array   $A      Array of key->value pairs
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
     * Set a property value.
     *
     * @since   v0.6.0
     * @param   string  $key    Property Name
     * @param   mixed   $value  Property Value
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
     * Get a property value
     *
     * @since   v0.6.0
     * @param   string  $key    Property Name
     * @return  mixed           Property Value, NULL if not set
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
     * Return the number of decimal places associated with a currency.
     *
     * @return  integer     Number of decimal places used for the currency
     */
    public function Decimals()
    {
        return $this->decimals;
    }


    /**
     * Return the prefix, if any, for a currency.
     *
     * @return  string      Prefix, e.g. dollar sign
     */
    public function Pre()
    {
        static $prefix = NULL;  // cache for repeated use
        if ($prefix === NULL) {
            $prefix = '';
            if ($this->symbol_placement == 'before') {
                $prefix .= $this->symbol . $this->symbol_spacer;
            }

            if ($this->code_placement == 'before') {
                $prefix .= $this->code . $this->code_spacer;
            }
        }
        return $prefix;
    }


    /**
     * Return the postfix, if any, for a currency.
     *
     * @return  string      Postfix, e.g. Euro sign
     */
    public function Post()
    {
        static $postfix = NULL;     // cache for repeated use
        if ($postfix === NULL) {
            $postfix = '';
            if ($this->symbol_placement == 'after') {
                $postfix .= $this->symbol . $this->symbol_spacer;
            }

            if ($this->code_placement == 'after') {
                $postfix .= $this->code . $this->code_spacer;
            }
        }
        return $postfix;
    }


    /**
     * Get the formatted string for an amount, e.g. "$ 125.00".
     *
     * @param   float   $amount Dollar amount
     * @param   boolean $symbol True to format as "$1.00", False for "1.00 USD"
     * @return  string      Formatted string for display
     */
    public function Format($amount, $symbol = true)
    {
        $val = $this->_Format($amount);
        if ($symbol) {
            return $val[0] . $val[1] . $val[2];
        } else {
            return $val[1] . ' ' . $this->code;
        }
    }


    /**
     * Get just the numeric part of the formatted price, e.g. "125.00" for "125".
     *
     * @param   float   $amount Dollar amount
     * @return  float       Formatted numeric value
     */
    public function FormatValue($amount)
    {
        $val = $this->_Format($amount);
        return $val[1];
    }


    /**
     * Formats a price for a particular currency.
     *
     * @param   float   $amount A numeric price amount value.
     * @param   string  $code   The three character code of the currency.
     * @return  array   Array of prefix, number, postfix
     */
    private function _Format($amount)
    {
        static $amounts = array();

        $key = (string)$amount;
        if (!array_key_exists($key, $amounts)) {
            // Format the price as a number.
            $price = number_format($this->currencyRound(abs($amount)),
                    $this->decimals,
                    $this->decimal_sep,
                    $this->thousands_sep);
            $negative = $amount < 0 ? '-' : '';
            $formatted = array($this->Pre(), $negative.$price, $this->Post());
            $amounts[$key] = $formatted;
        }
        return $amounts[$key];
    }


    /**
     * Rounds a price amount for the specified currency.
     *
     * Rounding of the minor unit with a currency specific step size. For example,
     * Swiss Francs are rounded using a step size of 0.05. This means a price of
     * 10.93 is converted to 10.95.
     *
     * @param   float   $amount The numeric amount value of the price to be rounded.
     * @return  string          The rounded numeric amount value for the price.
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
     * Converts a price amount from the current currency to the target currency.
     *
     * To convert an amount from one currency to another, we simply take the amount
     * value and multiply it by the current currency's conversion rate divided by
     * the target currency's conversion rate.
     *
     * @param   float   $amount         The numeric value to be converted
     * @param   string  $toCurrency     Target currency code
     * @param   string  $fromCurrency   Source currency override
     * @return  float       The converted amount
     */
    public function Convert($amount, $toCurrency, $fromCurrency='')
    {
       return $amount * $this->ConversionRate($toCurrency, $fromCurrency);
    }


    /**
     * Get the conversion rate between currencies.
     * If $from is not specified, uses the current default. $to must be given.
     *
     * @param   string  $toCurrency     Destination currency code
     * @param   string  $fromCurrency   Starting currency code
     * @return  float       Conversion rate to get $from to $to
     */
    public static function ConversionRate($toCurrency, $fromCurrency='')
    {
        global $_PP_CONF;
        static $rates = array();

        if (empty($fromCurrency)) $fromCurrency = $_PP_CONF['currency'];

        // check if this conversion has already been done this session
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
     * Get all currency info.
     * Used by the plugin configuration to create a dropdown list of currencies.
     *
     * @return  array   Array of all DB records
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
     * Convert an amount to an integer based on the number of decimals.
     * Example: $1.95 US becomes 195
     *
     * @param   float   $amount     Money amount to convert
     * @return  integer             Integer version of the amount
     */
    public function toInt($amount)
    {
        return (int)($amount * (10 ** $this->decimals));
    }


    /**
     * Convert an amount to an integer based on the number of decimals.
     * Example: 195 becomes 1.95
     *
     * @param   integer $intval     Integer version of the amount
     * @return  float               Money amount to convert
     */
    public function fromInt($intval)
    {
        return (float)($intval / (10 ** $this->decimals));
    }

}

?>

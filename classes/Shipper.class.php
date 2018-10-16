<?php
/**
 *   Class to handle shipping costs based on quantity, total weight and class
 *   First iteration only allows for a number of "units" per product
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     paypal
 * @version     0.6.0
 * @since       0.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Paypal;

/**
*   Class for product and category sales
*   @package paypal
*/
class Shipper
{
    static $base_tag = 'shipping';

    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    var $properties;

    /** Indicate whether the current object is a new entry or not.
    *   @var boolean */
    var $isNew;


    /**
    *   Constructor.
    *   Sets variables from the provided array
    *
    *   @param  array   DB record
    */
    public function __construct($A=array())
    {
        $this->properties = array();
        $this->isNew = true;

        if (is_array($A) && !empty($A)) {
            // DB record passed in, e.g. from _getSales()
            $this->setVars($A);
            $this->isNew = false;
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admn form
            if ($this->Read($A)) $this->isNew = false;
        } else {
            // New entry, set defaults
            $this->id = 0;
            $this->enabled = 1;
            $this->name = '';
            $this->rates = array(
                (object)array(
                    'dscp'  => 'Rate 1',
                    'units' => 10,
                    'rate'  => 5,
                ),
            );
            $this->best_rate = 0;
        }
    }


    /**
    *   Read a single record based on the record ID
    *
    *   @param  integer $id     DB record ID
    *   @return boolean     True on success, False on failure
    */
    public function Read($id)
    {
        global $_TABLES;

        $id = (int)$id;
        $cache_key = self::$base_tag . ' _ ' . $id;
        $A = Cache::get($cache_key);
        if ($A === NULL) {
            $sql = "SELECT *
                    FROM {$_TABLES['paypal.shipping']}
                    WHERE id = $id";
            //echo $sql;die;
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
                Cache::set($cache_key, $A, self::$base_tag);
            }
        }
        if (!empty($A)) {
            $this->setVars($A);
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Set the variables from a DB record into object properties
    *
    *   @param  array   $A      Array of properties
    *   @param  boolean $fromDB True if reading from DB, False if from a form
    */
    public function setVars($A, $fromDB=true)
    {
        $this->id = PP_getVar($A, 'id', 'integer');
        $this->name = PP_getVar($A, 'name');
        $this->min_units = PP_getVar($A, 'min_units', 'integer');
        $this->max_units = PP_getVar($A, 'max_units', 'integer');
        $this->enabled = PP_getVar($A, 'enabled', 'integer');
        if (!$fromDB) {
            $rates = array();
            foreach ($A['rateDscp'] as $id=>$txt) {
                if (!empty($txt) && !empty($A['rateUnits'][$id]) && !empty($A['rateRate'][$id])) {
                    $rates[] = array(
                        'dscp' => $txt,
                        'units' => $A['rateUnits'][$id],
                        'rate' => $A['rateRate'][$id],
                    );
                }
            }
            $this->rates = $rates;
        } else {
            //$rates = json_decode($A['rates'], true);
            $rates = json_decode($A['rates']);
            if ($rates === NULL) $rates = array();
            $this->rates = $rates;
        }
    }


    /**
    *   Get all shipping options.
    *
    *   @return array   Array of all DB records
    */
    public static function getAll()
    {
        global $_TABLES;

        $cache_key = 'shippers_all';
        $shippers = Cache::get($cache_key);
        if ($shippers === NULL) {
            $shippers = array();
            $sql = "SELECT * FROM {$_TABLES['paypal.shipping']}
                WHERE enabled = 1";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $shippers[$A['id']] = $A;
            }
            Cache::set($cache_key, $shippers, self::$base_tag);
        }
        $retval = array();
        foreach ($shippers as $shipper) {
            $retval[$shipper['id']] = new self($shipper);
        }
        return $retval;
    }


    public static function XgetAll()     // dummy test func
    {
        $shippers = array(
            array(
                'id' => 1,
                'name' => 'Flat Rate',
                'min_units' => 1,
                'max_units' => 100,
                'rates' => json_encode(array(
                    /*20 => 11.95,
                    5 => 5.95,
                    50 => 29.95,
                )),*/
                    array(
                        'units' => 5, 'rate' => 7.2,
                    ),
                    array(
                        'units' => 20, 'rate' => 13.65,
                    ),
                    array(
                        'units' => 50, 'rate' => 18.90,
                    ),
                )),
            ),
            array(
                'id' => 2,
                'name' => 'UPS Ground',
                'min_units' => 1,
                'max_units' => 30,
                'rates' => json_encode(array(
                    /*10 => 6.50,
                    20 => 10.50,
                )),*/

                    array(
                        'units' => 10,
                        'rate' => 6.50,
                    ),
                    array(
                        'units' => 20,
                        'rate' => 10.50,
                    ),
                )),
            ),
        );
        $retval = array();
        foreach ($shippers as $shipper) {
           // var_dump($shipper);die;
            $retval[] = new self($shipper);
        //    var_dump($retval);die;
        }
        return $retval;
    }


    /**
     * Get all the shippers and rates for shippers that can handle X units.
     *
     * @param   float   $units      Number of units being shipped
     * @return  array               Array of shipper objects
     */
    public static function getShippers($units=0)
    {
        $rates = array();
        if ($units == 0) return $rates;     // no shipping, return empty

        $shippers = self::getAll();
        $shipper->best_rate = 0;
        foreach ($shippers as $s_id=>$shipper) {
            if ($units < $shipper->min_units || ($shipper->max_units > 0 && $units > $shipper->max_units)) {
                // Skip shippers that don't handle this number of units
                continue;
            } else {
                $shipper->best_rate = 1000000;
                foreach ($shipper->rates as $r_id=>$rate) {
                    $rate = $rate->rate * ceil($units / $rate->units);
                    if ($shipper->best_rate > $rate) {
                        $shipper->best_rate = $rate;
                    }
                }
                $rates[$s_id] = $shipper;
            }
        }
        return $rates;
    }


    /**
    *   Get the single best shipper for a number of units
    *
    *   @param  integer $units      Number of units being shipped
    *   @return object      Shipper object for the shipper with the lowest rate
    */
    public static function getBestRate($units)
    {
        $shippers = self::getShippers($units);
        $best = NULL;
        foreach ($shippers as $shipper) {
            if ($best === NULL || ($shipper->best_rate !== NULL && $shipper->best_rate < $best->best_rate)) {
                $best = $shipper;
            }
        }
        if ($best === NULL) {
            // Create an empty object to provide zero shipping cost
            $best = new self();
        }
        return $best;
    }


    public static function calcBestFit($shipper, $units)
    {
        //var_dump($shipper->rates);die;
        return 15;
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($var, $value='')
    {
        global $_CONF;

        switch ($var) {
        case 'id':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'name':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'rates':
            // Floating-point values
            $this->properties[$var] = $value;
            break;

        case 'min_units':
            if ($value == 0) $value = .0001;
        case 'max_units':
        case 'best_rate':
            $this->properties[$var] = (float)$value;
            break;

        case 'enabled':
            $this->properties[$var] = $value == 0 ? 0 : 1;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
    *   Get the value of a property.
    *
    *   @param  string  $var    Name of property to retrieve.
    *   @return mixed           Value of property, NULL if undefined.
    */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
    *   Save the current or provided values to the database.
    *
    *   @param  array   $A      Optional array of values from $_POST
    *   @return boolean         True if no errors, False otherwise
    */
    public function Save($A =NULL)
    {
        global $_TABLES, $_PP_CONF;

        if (is_array($A)) {
            $this->setVars($A, false);
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['paypal.shipping']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['paypal.shipping']}";
            $sql3 = " WHERE id={$this->id}";
        }
        $sql2 = " SET name = '" . DB_escapeString($this->name) . "',
                min_units = '{$this->min_units}',
                max_units = '{$this->max_units}',
                rates = '" . DB_escapeString(json_encode($this->rates)) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            Cache::clear(self::$base_tag);
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Delete a single shipper record from the database
    *
    *   @param  integer $id     Record ID
    *   @return boolean     True on success, False on invalid ID
    */
    public static function Delete($id)
    {
        global $_TABLES;

        if ($id <= 0)
            return false;

        DB_delete($_TABLES['paypal.shipping'], 'id', $id);
        Cache::clear(self::$base_tag);
        return true;
    }


    /**
    *   Creates the edit form.
    *
    *   @param  integer $id Attributeal ID, current record used if zero
    *   @return string      HTML for edit form
    */
    public function Edit()
    {
        global $_CONF, $_PP_CONF, $LANG_PP;

        $T = PP_getTemplate('shipping_form', 'form');
        $retval = '';
        $T->set_var(array(
            'id'            => $this->id,
            'name'          => $this->name,
            'action_url'    => PAYPAL_ADMIN_URL,
            'doc_url'       => PAYPAL_getDocURL('shipping_form',
                                            $_CONF['language']),
            'min_units'     => $this->min_units,
            'max_units'     => $this->max_units,
            'ena_sel'       => $this->enabled ? 'checked="checked"' : '',
        ) );
        $T->set_block('form', 'rateTable', 'rt');
        foreach ($this->rates as $R) {
            $T->set_var(array(
                'rate_dscp'     => $R->dscp,
                'rate_units'    => $R->units,
                'rate_price'    => Currency::getInstance()->formatValue($R->rate),
            ) );
            $T->parse('rt', 'rateTable', true);
        }
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @uses   Attribute::_toggle()
    *   @param  integer $oldvalue   Original field value
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    public static function toggleEnabled($oldvalue, $id)
    {
        global $_TABLES;

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $newvalue = $oldvalue == 1 ? 0 : 1;
        $id = (int)$id;

        $sql = "UPDATE {$_TABLES['paypal.shipping']}
                SET enabled = $newvalue
                WHERE id = $id";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            COM_errorLog("Shipper::_toggle() SQL error: $sql", 1);
            return $oldvalue;
        } else {
            Cache::clear(self::$base_tag);
            return $newvalue;
        }
    }


    /**
     * Shortcut function to see if there are any enabled shippers.
     * If the units param is omitted, all enabled shippers are checked,
     * otherwise only those that can handle the units are checked.
     *
     * @param   float   $units      Units being shipped, if any
     * @return  boolean     True if there is at least one shipper.
     */
    public static function haveShippers($units = -1)
    {
        if ($units < 0) {
            return count(self::getAll()) > 0 ? true : false;
        } else {
            return count(self::gertShippers($units)) > 0 ? true : false;
        }
    }

}   // class Shipper

?>

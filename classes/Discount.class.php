<?php
/**
*   Class to manage product sale prices based on item or category
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for product and category discounts
*   @package paypal
*/
class Discount
{
    const DEF_DATE = '1900-01-01';
    static $base_tag = 'discounts';

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
            // DB record passed in, e.g. from _getDiscounts()
            $this->setVars($A);
            $this->isNew = false;
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admn form
            if ($this->Read($A)) $this->isNew = false;
        } else {
            // New entry, set defaults
            $this->id = 0;
            $this->item_type = 'product';
            $this->item_id = 0;
            $this->start = '';
            $this->end = '';
            $this->discount_type = 'none';
            $this->amount = 0;
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

        $sql = "SELECT * FROM {$_TABLES['paypal.discounts']}
                WHERE id = $id";
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            $this->setVars($A);
            return true;
        }
        return false;
    }


    /**
    *   Set the variables from a DB record into object properties
    *
    *   @param  array   $A      Array of properties
    */
    public function setVars($A)
    {
        $this->id = $A['id'];
        $this->item_type = $A['item_type'];
        $this->item_id = $A['item_id'];
        $this->start = $A['start'];
        $this->end = $A['end'];
        $this->discount_type = $A['discount_type'];
        $this->amount = $A['amount'];
    }


    /**
    *   Get all discount records for the specified type and item
    *
    *   @param  string  $type       Item type (product or category)
    *   @param  integer $item_id    Product or Category ID
    *   @return array           Array of Discount objects
    */
    private function _getDiscounts($type, $item_id)
    {
        global $_TABLES;
        static $discounts = array();

        if ($type != 'product') $type = 'category';
        $item_id = (int)$item_id;

        if (!array_key_exists($type, $discounts)) $discounts[$type] = array();
        if (!array_key_exists($item_id, $discounts[$type])) {
            $cache_key = self::_makeCacheKey($type . '_' . $item_id);
            $discounts[$type][$item_id] = Cache::get($cache_key);
            if (!$discounts[$type][$item_id]) {
                // If not found in cache
                $discounts[$type][$item_id] = array();
                $sql = "SELECT * FROM {$_TABLES['paypal.discounts']}
                        WHERE item_type = '$type' AND item_id = {$item_id}
                        ORDER BY start ASC";
                //echo $sql;die;
                $res = DB_query($sql);
                while ($A = DB_fetchArray($res, false)) {
                    $discounts[$type][$item_id][] = new self($A);
                }
                Cache::set($cache_key, $discounts[$type][$item_id], self::$base_tag);
            }
        }
        return $discounts[$type][$item_id];
    }


    /**
    *   Read all the sale prices for a category
    *
    *   @uses   self::_getDiscounts()
    *   @param  integer $cat_id     Category ID
    *   @return array       Array of Discount objects
    */
    public static function getCategory($cat_id)
    {
        return self::_getDiscounts('category', $cat_id);
    }


    /**
    *   Read all the sale prices for a product
    *
    *   @uses   self::_getDiscounts()
    *   @param  integer $item_id    Product ID
    *   @return array       Array of Discount objects
    */
    public static function getProduct($item_id)
    {
        return self::_getDiscounts('product', $item_id);
    }


    /**
    *   Get the current active discount object for a product.
    *   First check product discounts, then categories.
    *
    *   @param  object  $P  Product object
    *   @return object      Discount object, empty object if not found
    */
    public static function getEffective($P)
    {
        $now = PAYPAL_now()->toMySQL();
        $discounts = self::getProduct($P->id);
        foreach ($discounts as $obj) {
            if ($obj->start < $now || $obj->end > $now) {
                // Found an active product discount, return it.
                return $obj;
            }
        }

        // If no product discount was found, look for a category.
        // Traverse the category tree from the current category up to
        // the root and return the first discount object found, if any.
        $cats = Category::getPath($P->cat_id, false);
        $cats = array_reverse($cats);
        foreach ($cats as $cat) {
            $discounts = Discount::getCategory($cat->cat_id);
            foreach ($discounts as $obj) {
                if ($obj->start < $now && $obj->end > $now) {
                    return $obj;
                }
            }
        }
        // Return an empty object so Discount::getEffective->calcPrice()
        // will work.
        return new self;
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'id':
        case 'item_id':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'start':
        case 'end':
            if (empty($value)) $value = self::DEF_DATE;
        case 'item_type':
        case 'discount_type':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'amount':
            // Floating-point values
            $this->properties[$var] = (float)$value;
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
    *   Save the current values to the database.
    *
    *   @param  array   $A      Array of values from $_POST
    *   @return boolean         True if no errors, False otherwise
    */
    public function Save($A = array())
    {
        global $_TABLES, $_PP_CONF;

        if (is_array($A)) {
            $this->SetVars($A);
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['paypal.discounts']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['paypal.discounts']}";
            $sql3 = " WHERE id={$this->id}";
        }

        $sql2 = " SET item_type = '" . DB_escapeString($this->item_type) . "',
                item_id = '{$this->item_id}',
                start = '{$this->start}',
                end = '{$this->end}',
                discount_type = '" . DB_escapeString($this->discount_type) . "',
                amount = '{$this->amount}'";
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
    *   Delete a single discount record from the database
    *
    *   @param  integer $id     Record ID
    *   @return boolean     True on success, False on invalid ID
    */
    public static function Delete($id)
    {
        global $_TABLES;

        if ($id <= 0)
            return false;

        DB_delete($_TABLES['paypal.discounts'], 'id', $id);
        Cache::clear(self::$base_tag);
        return true;
    }


    /**
    *   Clean out old discount records.
    *   Called from runScheculedTask function
    */
    public static function Clean()
    {
        global $_TABLES;

        $now = PAYPAL_now()->toMySQL();
        $sql = "DELETE FROM {$_TABLES['paypal.discounts']}
                WHERE end < '$now'";
        DB_query($sql);
    }


    /**
    *   Creates the edit form.
    *
    *   @param  integer $id Attributeal ID, current record used if zero
    *   @return string      HTML for edit form
    */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $_SYSTEM;

        // If there are no products defined, return a formatted error message
        // instead of the form.
        if (DB_count($_TABLES['paypal.products']) == 0) {
            return PAYPAL_errMsg($LANG_PP['todo_noproducts']);
        }

        $T = PP_getTemplate('discount_form', 'form');
        $retval = '';
        $T->set_var(array(
            'disc_id'       => $this->id,
            'action_url'    => PAYPAL_ADMIN_URL,
            'pi_url'        => PAYPAL_URL,
            'doc_url'       => PAYPAL_getDocURL('discount_form',
                                            $_CONF['language']),
            'amount'        => $this->amount,
            'product_select' => COM_optionList($_TABLES['paypal.products'],
                    'id,name', $this->item_id),
            'category_select' => Category::optionList($this->item_id),
            'it_sel_' . $this->item_type => 'checked="checked"',
            'dt_sel_' . $this->discount_type => 'selected="selected"',
            'item_type'     => $this->item_type,
            'start'         => $this->start == self::DEF_DATE ? '' : $this->start,
            'end'           => $this->end == self::DEF_DATE ? '' : $this->end,
        ) );
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
    *   Helper function to create the cache key.
    *
    *   @param  string  $id     Item ID, e.g. "category_1"
    *   @return string  Cache key
    */
    protected static function _makeCacheKey($id)
    {
        return self::$base_tag . '_' . $id;
    }


    /**
    *   Calculate the discounted price.
    *   Always returns at least zero.
    *
    *   @param  float   $price      Item base price
    *   @return float               Discounted price
    */
    public function calcPrice($price)
    {
        switch ($this->discount_type) {
        case 'amount':
            $price -= $this->amount;
            break;
        case 'percent':
            $price = $price * (100 - $this->amount) / 100;
            break;
        case 'none':
        default:
            // An empty Discount object may be returned if there is no
            // discount. In that case, there's no discount to apply.
            break;
        }
        return max($price, 0);
    }

}   // class Discount

?>

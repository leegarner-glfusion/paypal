<?php
/**
*   Class to manage order items
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.12
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

namespace Paypal;

/**
 * Class for order items.
 *  @package paypal
 */
class OrderItem
{
    private $properties = array();
    private $product = NULL;
    private static $fields = array('id', 'order_id', 'product_id',
            'description', 'quantity', 'user_id', 'txn_id', 'txn_type',
            'purchase_date', 'status', 'expiration', 'price', 'token',
            'options', 'options_text', 'extras', 'taxable', 'paid',
            'shipping', 'handling',
    );

    /**
    *   Constructor.
    *   Initializes the array of orderstatus.
    *
    *   @uses   Load()
    */
    function __construct($item = 0)
    {
        if (is_numeric($item) && $item > 0) {
            $status = $this->Read($item);
            if (!$status) {
                $this->id = 0;
            }
        } elseif (is_array($item)) {
            if (!isset($item['product_id']) && isset($item['item_id'])) {
                // extract the item_id with options into the product ID
                list($this->product_id) = explode('|', $item['item_id']);
            }
            $status = $this->setVars($item);
        }
        if ($status) {
            $this->product = Product::getInstance($this->product_id);
        }
    }


    /**
    *   Load the item information
    *
    *   @param  integer $rec_id     DB record ID of item
    *   @return boolean     True on success, False on failure
    */
    public function Read($rec_id)
    {
        global $_PP_CONF, $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT *,
                UNIX_TIMESTAMP(CONVERT_TZ(`expiration`, '+00:00', @@session.time_zone)) AS ux_exp
                FROM {$_TABLES['paypal.purchases']}
                WHERE id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            return $this->setVars(DB_fetchArray($res, false));
        } else {
            return false;
        }
    }


    /**
    *   Set the object variables from an array
    *
    *   @param  array   $A      Array of values
    *   @return boolean     True on success, False if $A is not an array
    */
    public function setVars($A)
    {
        if (!is_array($A)) return false;
        foreach (self::$fields as $field) {
            if (isset($A[$field])) {
                $this->$field = $A[$field];
            }
        }
        $this->ux_exp = PP_getVar($A, 'ux_exp');
        return true;
    }


    /**
     * Setter function
     *
     * @param   string  $key    Name of property to set
     * @param   mixed   $value  Value to set for property
     */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'id':
        case 'user_id':
        case 'quantity':
            $this->properties[$key] = (int)$value;
            break;
        case 'extras':
        case 'options_text':
            if (is_string($value)) {    // convert to array
                $value = @json_decode($value, true);
                if (!$value) $value = array();
            }
            $this->properties[$key] = $value;
            break;
        case 'price':
        case 'paid':
        case 'shipping':
        case 'handling':
            $this->properties[$key] = (float)$value;
            break;
        case 'taxable':
            $this->properties[$key] = $value == 0 ? 0 : 1;
            break;
        default:
            $this->properties[$key] = trim($value);
            break;
        }
    }


    /**
     * Getter function
     *
     * @param   string  $key    Property to retrieve
     * @return  mixed           Value of property, NULL if undefined
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
    *   Public function to get the Product object for this item
    *
    *   @return object      Product object
    */
    public function getProduct()
    {
        return $this->product;
    }


    /**
    *   Get the short description. Return the long descr if not defined
    *
    *   @return string  Description string
    */
    public function getShortDscp()
    {
        if ($this->short_description == '') {
            return $this->description;
        } else {
            return $this->short_description;
        }
    }


    /**
    *   Add an option text item to the order item.
    *   This allows products to add additional information when purchased,
    *   beyond the standard options selected.
    *
    *   @param  string  $text   Text to add
    *   @param  boolean $save   True to immediately save the item
    */
    public function addOptionText($text, $save=true)
    {
        $opts = $this->options_text;
        $opts[] = $text;
        $this->options_text = $opts;
        if ($save) $this->Save();
    }


    /**
    *   Add a special text element to an order item.
    *   This allows products to add additional information when purchased,
    *   beyond the items entered at purchase.
    *
    *   @param  string  $name   Name of element
    *   @param  string  $value  Value of element
    *   @param  boolean $save   True to immediately save the item
    */
    public function addSpecial($name, $value, $save=true)
    {
        // extras is set by __set so it has to be extracted to get at
        // the sub-elements
        $x = $this->extras;
        $x['special'][$name] = $value;
        $this->extras = $x;
        if ($save) $this->Save();
    }


    /**
    *   Save an order item to the database.
    *
    *   @param  array   $A  Optional array of data to save
    *   @return boolean     True on success, False on DB error
    */
    public function Save($A= NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }
        $purchase_ts = PAYPAL_now()->toUnix();
        $shipping = $this->product->getShipping($this->quantity);
        $handling = $this->product->getHandling($this->quantity);

        if ($this->id > 0) {
            $sql1 = "UPDATE {$_TABLES['paypal.purchases']} ";
            $sql3 = " WHERE id = '{$this->id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['paypal.purchases']} ";
            $sql3 = '';
        }
        $sql2 = "SET order_id = '" . DB_escapeString($this->order_id) . "',
                product_id = '" . DB_escapeString($this->product_id) . "',
                description = '" . DB_escapeString($this->description) . "',
                quantity = '{$this->quantity}',
                user_id = '{$this->user_id}',
                txn_id = '" . DB_escapeString($this->txn_id) . "',
                txn_type = '" . DB_escapeString($this->txn_type) . "',
                purchase_date = '$purchase_ts',
                status = '" . DB_escapeString($this->status) . "',
                price = '$this->price',
                taxable = '{$this->taxable}',
                token = '" . DB_escapeString($this->token) . "',
                options = '" . DB_escapeString($this->options) . "',
                options_text = '" . DB_escapeString(@json_encode($this->options_text)) . "',
                extras = '" . DB_escapeString(json_encode($this->extras)) . "',
                shipping = {$shipping},
                handling = {$handling}";
            // add an expiration date if appropriate
        if ($this->product->expiration > 0) {
            $sql2 .= ", expiration = " . (string)($purchase_ts + ($this->product->expiration * 86400));
        }
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        //COM_errorLog($sql);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->id == 0) {
                $this->id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
    }

}

?>

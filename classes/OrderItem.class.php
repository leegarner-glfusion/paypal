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
*   Class for order processing workflow items
*   Order statuses are defined in the database and can be re-ordered and
*   individually enabled or disabled.
*   @package paypal
*/
class OrderItem
{
    private $properties = array();
    private $product = NULL;
    private static $fields = array('id', 'order_id', 'product_id',
            'description', 'quantity', 'user_id', 'txn_id', 'txn_type',
            'purchase_date', 'status', 'expiration', 'price', 'token',
            'options', 'options_text', 'extras',
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
        $sql = "SELECT * FROM {$_TABLES['paypal.purchases']}
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
        return true;
    }


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
        default:
            $this->properties[$key] = trim($value);
            break;
        }
    }


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
        $purchase_date = DB_escapeString(PAYPAL_now()->toMySQL());

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
                purchase_date = '$purchase_date',
                status = '" . DB_escapeString($this->status) . "',
                price = '" . DB_escapeString($this->price) . "',
                token = '" . DB_escapeString($this->token) . "',
                options = '" . DB_escapeString($this->options) . "',
                options_text = '" . DB_escapeString(@json_encode($this->options_text)) . "',
                extras = '" . DB_escapeString(json_encode($this->extras)) . "'";
            // add an expiration date if appropriate
        if ($this->product->expiration > 0) {
            $sql2 .= ", expiration = DATE_ADD('$purchase_date', INTERVAL {$this->product->expiration} DAY)";
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

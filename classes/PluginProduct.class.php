<?php
/**
*   Class to interface with plugins for product information
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.7
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for product
*   @package paypal
*/
class PluginProduct extends Product
{
    /**
    *   Constructor.
    *   Creates an object for a plugin product and gets data from the
    *   plugin's service function
    *
    *  @param integer $id Optional Item ID - plugin:item_id|opt1,opt2...
    */
    public function __construct($item_id)
    {
        global $_PP_CONF;

        $this->properties = array();
        $this->currency = new Currency($_PP_CONF['currency']);
        $this->item_id = $item_id;
        $pi_info = explode(':', $item_id);
        $this->pi_name = $pi_info[0];
        if (isset($pi_info[1])) {
            $this->product_id = $pi_info[1];
        } else {
            $this->item_id = NULL;
            return;
        }

        $this->prod_type = PP_PROD_PLUGIN;

        // Try to call the plugin's function to get product info.
        $status = LGLIB_invokeService($this->pi_name, 'productinfo',
                    $pi_info, $A, $svc_msg);
        if ($status == PLG_RET_OK) {
            $this->price = isset($A['price']) ? $A['price'] : 0;
            $this->item_name = $A['name'];
            $this->short_description = $A['short_description'];
            $this->description = isset($A['description']) ? $A['description'] : $this->short-description;
            $this->taxable = isset($A['taxable']) ? $A['taxable'] : 0;
         } else {
            // probably an invalid product ID
            $price = 0;
            $this->item_name = '';
            $this->short_description = '';
            $this->item_id = NULL;
            $this->isNew = true;
        }
    }


    /**
    *   Dummy function since plugin items don't support saving
    *   @return boolean         True, always
    */
    public function Save($A = '')
    {
        return true;
    }


    /**
    *   Dummy function since plugin items can't be deleted here.
    *   @return boolean     True, always
    */
    public function Delete()
    {
        return true;
    }


    /**
    *   Handle the purchase of this item.
    *   1. Update qty on hand if track_onhand is set (min. value 0)
    *
    *   @param  integer $qty        Quantity ordered
    *   @param  object  $Item       Item record, to get options, etc.
    *   @param  object  $Order      Optional order (not used yet)
    *   @param  array   $ipn_data   IPN data
    *   @return integer     Zero or error value
    */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data=array())
    {
        // Initialize item info array to be used later
        //$A = array();

        PAYPAL_debug('Paypal\\PluginProduct::handlePurchase() pi_info: ' . $this->pi_name);
//        $item_info = array(
//            'item_number' => $this->pi_name
//return;
        //$pi_info = array();
        // Set the variables. A couple of layouts have been supported by
        // plugins so the item info is set two ways.
        $vars = array(
            'item'  => array(
                'item_id' => $Item->product_id,
                'quantity' => $Item->quantity,
                'name' => $Item->item_name,
                'price' => $Item->price,
            ),
            'item_id'   => $Item->product_id,
            'quantity'  => $Item->quantity,
            'name' => $Item->item_name,
            'price' => $Item->price,
            'ipn_data'  => $ipn_data,
//          'order' => $this->Order,
        );
        if ($ipn_data['status'] == 'paid') {
            $status = LGLIB_invokeService($this->pi_name, 'handlePurchase', $vars, $output, $svc_msg);
            /*if ($status != PLG_RET_OK) {
                $A = array();
            }*/
        }
    }


    public function handleRefund($pp_data = array())
    {
        if (empty($pp_data)) return false;
        $vars = array(
            'item_id'   => explode(':', $this->item_id),
            'ipn_data'  => $pp_data,
        );
        $status = LGLIB_invokeService($this->pi_name, 'handleRefund',
                $vars, $output, $svc_msg);
        return $status == PLG_RET_OK ? true : false;
    }


    public function cancelPurchase($qty, $order_id='')
    {
    }


    /**
    *   Get the unit price of this product, considering the specified options.
    *   Plugins don't currently support option prices or discounts
    *
    *   @param  array   $options    Array of integer option values
    *   @param  integer $quantity   Quantity, used to calculate discounts
    *   @return float       Product price, including options
    */
    public function getPrice($options = array(), $quantity = 1)
    {
        return $this->price;
    }


    /**
    *   Determine if a given item number belongs to a plugin.
    *   Overrides the parent function, always returns true for a plugin item.
    *
    *   @param  mixed   $item_number    Item Number to check
    *   @return boolean     Always true for this class
    */
    public static function isPluginItem($item_number)
    {
        return true;
    }
 
}   // class PluginProduct

?>

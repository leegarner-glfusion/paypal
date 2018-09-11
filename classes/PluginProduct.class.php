<?php
/**
*   Class to interface with plugins for product information
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
*   Class for a plugin-supplied product
*   @package paypal
*/
class PluginProduct extends Product
{
    public $url;        // URL to product detail page, if any
    public $pi_info;    // plugin info with item_id and other vars
    private $_have_detail_svc = false;  // Plugin has a detail page service function
    private $_buynow_qty = 0;   // Quantity to use for buy-now buttons

    /**
    *   Constructor.
    *   Creates an object for a plugin product and gets data from the
    *   plugin's service function
    *
    *   @param  string  $id     Item ID - plugin:item_id|opt1,opt2...
    *   @param  array   $mods   Array of modifiers from parent::getInstance()
    */
    public function __construct($item, $mods=array())
    {
        global $_USER;

        $this->pi_info = array();
        $item = explode('|', $item);
        $item_id = $item[0];
        $this->properties = array();
        $this->currency = Currency::getInstance();
        $this->item_id = $item_id;  // Full item id
        $this->id = $item_id;       // TODO: convert Product class to use item_id
        $item_parts = explode(':', $item_id);
        $this->pi_name = $item_parts[0];
        array_shift($item_parts);         // Remove plugin name
        $this->pi_info['item_id'] = $item_parts;
        $this->product_id = $item_parts[0];

        // Get the user ID to pass to the plugin in case it's needed.
        if (!isset($mods['uid'])) {
            $mods['uid'] = $_USER['uid'];
        }
        $this->pi_info['mods'] = $mods;
        $this->prod_type = PP_PROD_PLUGIN;

        // Try to call the plugin's function to get product info.
        $status = LGLIB_invokeService($this->pi_name, 'productinfo',
            $this->pi_info, $A, $svc_msg);
        if ($status == PLG_RET_OK) {
            $this->price = PP_getVar($A, 'price', 'float', 0);
            $this->item_name = PP_getVar($A, 'name');
            $this->short_description = PP_getVar($A, 'short_description');
            $this->description = PP_getVar($A, 'description', 'string', $this->short_description);
            $this->taxable = PP_getVar($A, 'taxable', 'integer', 0);
            $this->url = PP_getVar($A, 'url');
            $this->override_price = PP_getVar($A, 'override_price', 'integer', 0);
            $this->btn_type = PP_getVar($A, 'btn_type', 'string', 'buy_now');
            $this->btn_text = PP_getVar($A, 'btn_text');
            $this->_have_detail_svc = PP_getVar($A, 'have_detail_svc', 'boolean', false);
            $this->_fixed_q = PP_getVar($A, 'fixed_q', 'integer', 0);
            $this->_buynow_qty = PP_getVar($A, 'buynow_qty', 'integer', $this->_fixed_q);
         } else {
            // probably an invalid product ID
            $this->price = 0;
            $this->item_name = '';
            $this->short_description = '';
            $this->item_id = NULL;
            $this->isNew = true;
            $this->url = '';
            $this->taxable = 0;
            $this->_have_detail_svc = false;
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
    *   @param  object  $Item       Item object, to get options, etc.
    *   @param  object  $Order      Optional order object (not used yet)
    *   @param  array   $ipn_data   IPN data
    *   @return integer     Zero or error value
    */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data=array())
    {
        PAYPAL_debug('Paypal\\PluginProduct::handlePurchase() pi_info: ' . $this->pi_name);
        $status = PLG_RET_OK;       // Assume OK in case the plugin does nothing
        $args = array(
            'item'  => array(
                'item_id' => $Item->product_id,
                'quantity' => $Item->quantity,
                'name' => $Item->item_name,
                'price' => $Item->price,
                'paid' => $Item->price,
            ),
            'ipn_data'  => $ipn_data,
            'order' => $Order,      // Pass the order object, may be used in the future
        );
        if ($ipn_data['status'] == 'paid') {
            $status = LGLIB_invokeService($this->pi_name, 'handlePurchase', $args, $output, $svc_msg);
        }
        return $status == PLG_RET_OK ? true : false;
    }


    /**
    *   Handle a refund for this product
    *
    *   @param  array   $pp_data    Paypal IPN data
    *   @return integer         Status from plugin's handleRefund function
    */
    public function handleRefund($Order, $pp_data = array())
    {
        if (empty($pp_data)) return false;
        $args = array(
            'item_id'   => explode(':', $this->item_id),
            'ipn_data'  => $pp_data,
        );
        $status = LGLIB_invokeService($this->pi_name, 'handleRefund',
                $args, $output, $svc_msg);
        return $status == PLG_RET_OK ? true : false;
    }


    public function cancelPurchase($qty, $order_id='')
    {
    }


    /**
    *   Get the unit price of this product, considering the specified options.
    *   Plugins don't currently support option prices or discounts so the
    *   price is just the set price.
    *
    *   @param  array   $options    Array of integer option values (unused)
    *   @param  integer $quantity   Quantity, used to calculate discounts (unused)
    *   @param  array   $override   Array of override options (price, uid)
    *   @return float       Product price, including options
    */
    public function getPrice($options = array(), $quantity = 1, $override = array())
    {
        if ($this->override_price && isset($override['price'])) {
            return (float)$override['price'];
        } else {
            if (isset($override['uid'])) {
                $this->pi_info['mods']['uid'] = $override['uid'];
            }
            $status = LGLIB_invokeService($this->pi_name, 'productinfo',
                    $this->pi_info, $A, $svc_msg);
            if ($status == PLG_RET_OK && isset($A['price'])) {
                return $A['price'];
            } else {
                return $this->price;
            }
        }
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


    /**
    *   Get the URL to the item detail page
    *
    *   @return string      Item detail URL
    */
    public function getLink()
    {
        if ($this->_have_detail_svc) {
            return PAYPAL_URL . '/index.php?pidetail=' . $this->item_id;
        } else {
            return $this->url;
        }
    }


    /**
    *   Get additional text to add to the buyer's recipt for a product
    *
    *   @param  object  $item   Order Item object (not used)
    *   @return string          Additional message to include in email
    */
    public function EmailExtra($item)
    {
        $text = '';
        // status from the service function isn't used.
        LGLIB_invokeService($this->pi_name, 'emailReceiptInfo',
                    $this->pi_info, $text, $svc_msg);
        return $text;
    }


    /**
     * Get the quantity for a buy-now button.
     * This is used to either set a fixed quantity, or if zero then
     * the buyer can enter a quantity.
     * Plugin products like subscriptions may set a specific quantity.
     *
     * @return  integer     Fixed quantity
     */
    public function buynowQty()
    {
        return $this->_buynow_qty;
    }

}   // class PluginProduct

?>

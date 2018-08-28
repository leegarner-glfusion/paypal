<?php
/**
*   Base class for handling IPN messages.
*   Each IPN handler receives its data in a unique way, which it is responsible
*   for putting into this class's pp_data array which holds common, standard
*   data elements.
*
*   The derived class may implement a "Process" function, or other master
*   control.  The protected functions here are available for derived classes,
*   or they may implement their own methods for handlePurchase(),
*   createOrder(), etc.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net>
*   @copyright  Copyright (c) 2009-2018 Lee Garner
*   @copyright  Copyright (c) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

// Just for E_ALL. If "testing" isn't defined, define it.
if (!isset($_PP_CONF['sys_test_ipn'])) $_PP_CONF['sys_test_ipn'] = false;

// Define failure reasons- maybe delete if not needed for all gateways
define('IPN_FAILURE_UNKNOWN', 0);
define('IPN_FAILURE_VERIFY', 1);
define('IPN_FAILURE_COMPLETED', 2);
define('IPN_FAILURE_UNIQUE', 3);
define('IPN_FAILURE_EMAIL', 4);
define('IPN_FAILURE_FUNDS', 5);


/**
*   Interface to deal with IPN transactions from a payment gateway.
*
*   @package paypal
*/
class IPN
{
    /** Standard IPN data items required for all IPN types.
    *   @var array */
    var $pp_data = array();

    /** Holder for the complete IPN data array.  Used only for recording
    *   the raw data; no processing is done on this data by the base class.
    *   @var array */
    var $ipn_data = array();

    /** Array of items purchased.  Extracted from $ipn_data by the derived
    *   IPN processor class.
    *   @var array */
    var $items = array();

    /** ID of payment gateway, e.g. 'paypal' or 'amazon'
    *   @var string */
    var $gw_id;

    /** Instance of the appropriate gateway object
    *   @var object */
    var $gw;

    /** This is just a holder for the current date in SQL format,
    *   so we don't have to rely on the database's NOW() function.
    *   @var string */
    var $sql_date;

    /** Order object
    *   @var object */
    protected $Order;

    /** Cart object
    *   @var object */
    protected $Cart;

    /**
    *   Constructor.
    *   Set up variables received in the IPN message. Stores the complete
    *   IPN message in ipn_data, and initializes a pp_data for standard
    *   values to be filled in by the gateway's IPN processor.
    *
    *   @param  array   $A      $_POST'd variables from the gateway
    */
    function __construct($A=array())
    {
        global $_PP_CONF;

        if (is_array($A)) {
            $this->ipn_data = $A;
        }

        $this->sql_date = PAYPAL_now()->toMySQL();

        $this->pp_data = array(
            'txn_id'        => '',
            'payer_email'   => '',
            'payer_name'    => '',
            'pmt_date'      => '',
            'sql_date'      => $this->sql_date,
            'pmt_gross'     => 0,
            'pmt_shipping'  => 0,
            'pmt_handling'  => 0,
            'pmt_tax'       => 0,
            'gw_name'       => '',
            'pmt_status'    => 0,
            'currency'      => '',
            'shipto'        => array(),
            'custom'        => array(),
            'status'        => '',
        );

        // Create a gateway object to get some of the config values
        $this->gw = Gateway::getInstance($this->gw_id);
    }


    /**
    *   Add an item from the IPN message to our $items array.
    *
    *   @param  array   $args   Array of arguments
    */
    protected function AddItem($args)
    {
        // Minimum required arguments: item, quantity, unit price
        if (!isset($args['item_id']) || !isset($args['quantity']) || !isset($args['price'])) {
            return;
        }

        // Separate the item ID and options to get pricing
        $tmp = explode('|', $args['item_id']);
        $P = Product::getInstance($tmp[0], $this->pp_data['custom']);
        if ($P->isNew) {
            COM_errorLog("Product {$args['item_id']} not found in catalog");
            return;      // no product found to add
        }
        if (isset($tmp[1])) {
            $opts = explode(',', $tmp[1]);
        } else {
            $opts = array();
        }
        // If the product allows the price to be overridden, just take the
        // IPN-supplied price. This is the case for donations.
        $overrides = $this->pp_data['custom'];
        $overrides['price'] = $args['price'];
        $price = $P->getPrice($opts, $args['quantity'], $overrides);

        $this->items[] = array(
            'item_id'   => $args['item_id'],
            'item_number' => $tmp[0],
            'name'      => isset($args['item_name']) ? $args['item_name'] : '',
            'quantity'  => $args['quantity'],
            'price'     => $price,      // price including options
            'shipping'  => isset($args['shipping']) ? $args['shipping'] : 0,
            'handling'  => isset($args['handling']) ? $args['handling'] : 0,
            //'tax'       => $tax,
            'taxable'   => $P->taxable ? 1 : 0,
            'options'   => isset($tmp[1]) ? $tmp[1] : '',
            'extras'    => isset($args['extras']) ? $args['extras'] : '',
            'overrides' => $overrides,
        );
    }


    /**
    *   Log an instant payment notification.
    *
    *   Logs the incoming IPN (serialized) along with the time it arrived,
    *   the originating IP address and whether or not it has been verified
    *   (caller specified).  Also inserts the txn_id separately for
    *   look-up purposes.
    *
    *   @param  boolean $verified   true if verified, false otherwise
    *   @return integer             Database ID of log record
    */
    protected function Log($verified = false)
    {
        global $_SERVER, $_TABLES;

        // Change $verified into format for database
        if ($verified) {
            $verified = 1;
        } else {
            $verified = 0;
        }

        // Log to database
        $sql = "INSERT INTO {$_TABLES['paypal.ipnlog']} SET
                ip_addr = '{$_SERVER['REMOTE_ADDR']}',
                time = '{$this->sql_date}',
                verified = $verified,
                txn_id = '" . DB_escapeString($this->pp_data['txn_id']) . "',
                gateway = '{$this->gw_id}',
                ipn_data = '" . DB_escapeString(serialize($this->ipn_data)) . '\'';
        // Ignore DB error in order to not block IPN
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Paypal\IPN::Log() SQL error: $sql", 1);
        }
        return DB_insertId();
    }


    /**
    *   Checks that the transaction id is unique to prevent double counting.
    *
    *   @param  string  $txn_id     Transaction id to verify
    *   @return boolean             True if unique, False otherwise
    */
    protected function isUniqueTxnId($data)
    {
        global $_TABLES, $_PP_CONF;
        if ($_PP_CONF['sys_test_ipn']) return true;

        // Count purchases with txn_id, if > 0
        $count = DB_count($_TABLES['paypal.purchases'], 'txn_id',
                    $data['txn_id']);
        if ($count > 0) {
            return false;
        } else {
            return true;
        }
    }


    /**
    *   Check that provided funds are sufficient to cover the cost of
    *   the purchase.
    *
    *   @return boolean                 True for sufficient funds, False if not
    */
    protected function isSufficientFunds()
    {
        // Get the amount paid along with any gift card balance used and
        // check against the total order amount.
        $pmt = PP_getVar($this->pp_data, 'pmt_gross', 'float');
        $by_gc = PP_getVar($this->pp_data['custom'], 'by_gc', 'float');
        if ($by_gc > 0) {
            $uid = PP_getVar($this->pp_data['custom'], 'uid', 'int');
            if (!Coupon::verifyBalance($by_gc, $uid)) {
                $gc_bal = Coupon::getUserBalance($uid);
                COM_errorLog("Insufficient Gift Card Balance, need $by_gc, have $gc_bal");
                $by_gc = 0;     // ignore the gift card amount
            }
        }
        $total_credit = $pmt + $by_gc;
        // Compare total order amount to gross payment.  The ".0001" is to help
        // kill any floating-point errors. Include any discount.
        $total_order = $this->Order->getTotal();
        $msg = "$pmt received plus $by_gc coupon, require $total_order";
        if ($total_order <= $total_credit + .0001) {
            PAYPAL_debug("OK: $msg");
            return true;
        } else {
            PAYPAL_debug("Insufficient Funds: $msg");
            return false;
        }
    }   // isSufficientFunds()


    /**
    *   Handles the item purchases.
    *   The purchase should already have been validated; this function simply
    *   records the purchases.  Purchased files will be emailed to the
    *   customer by Order::Notify().
    *
    *   @uses   createOrder()
    */
    protected function handlePurchase()
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP;

        $prod_types = 0;

        // For each item purchased, create an order item
        foreach ($this->items as $id=>$item) {
            $P = Product::getInstance($item['item_number']);
            if ($P->isNew) {
                $this->Error("Item {$item['item_number']} not found - txn " .
                        $this->pp_data['txn_id']);
                continue;
            }

            $this->items[$id]['prod_type'] = $P->prod_type;

            PAYPAL_debug("Paypal item " . $item['item_number']);

            // Mark what type of product this is
            $prod_types |= $P->prod_type;

            // If it's a downloadable item, then get the full path to the file.
            if ($P->file != '') {
                $this->items[$id]['file'] = $_PP_CONF['download_path'] . $P->file;
                $token_base = $this->pp_data['txn_id'] . time() . rand(0,99);
                $token = md5($token_base);
                $this->items[$id]['token'] = $token;
            } else {
                $token = '';
            }
            if (is_numeric($P->expiration) && $P->expiration > 0) {
                $this->items[$id]['expiration'] = $P->expiration;
            }

            // If a custom name was supplied by the gateway's IPN processor,
            // then use that.  Otherwise, plug in the name from inventory or
            // the plugin, for the notification email.
            if (empty($item['name'])) {
                $this->items[$id]['name'] = $P->short_description;
            }

            // Add the purchase to the paypal purchase table
            if (is_numeric($this->pp_data['custom']['uid'])) {
                $uid = $this->pp_data['custom']['uid'];
            } else {
                $uid = 1;       // Anonymous as a fallback
            }
        }   // foreach item

        $status = $this->createOrder();
        if ($status == 0) {
            // Now all of the items are in the order object, check for sufficient
            // funds. If OK, then save the order and call each handlePurchase()
            // for each item.
            if (!$this->isSufficientFunds()) {
                $logId = $this->pp_data['gw_name'] . ' - ' . $this->pp_data['txn_id'];
                $this->handleFailure(IPN_FAILURE_FUNDS,
                        "($logId) Insufficient/incorrect funds for purchase");
                return false;
            }

            // Save the order record now that funds have been checked.
            $this->Order->Save();
            foreach ($this->Order->items as $item) {
                $item->getProduct()->handlePurchase($item, $this->Order, $this->pp_data);
            }
            $this->Order->Log(sprintf($LANG_PP['amt_paid_gw'], $this->pp_data['pmt_gross'], $this->gw->DisplayName()));
            $by_gc = PP_getVar($this->pp_data['custom'], 'by_gc', 'float');
            $this->Order->by_gc = $by_gc;
            if ($by_gc > 0) {
                $this->Order->Log(sprintf($LANG_PP['amt_paid_gw'], $by_gc, 'Gift Card'));
                Coupon::Apply($by_gc, $this->Order->uid, $this->Order);
            }
            $this->Order->Notify();
            // If this was a user's cart, then clear that also
            if ($this->Cart) {
                $this->Cart->delete();
            } else {
                PAYPAL_debug('no cart to delete');
            }
        } else {
            COM_errorLog('Error creating order: ' . print_r($status,true));
        }

        return true;
    }  // function handlePurchase


    /**
    *   Create and populate an Order record for this purchase.
    *   Gets the billto and shipto addresses from the cart, if any.
    *   Items are saved in the purchases table by handlePurchase().
    *   The order is not saved to the database here. Only after checking
    *   for sufficient funds is the records saved.
    *
    *   This function is called only by our own handlePurchase() function,
    *   but is made "protected" so a derived class can use it if necessary.
    *
    *   @return integer     Zero for success, non-zero on error
    */
    protected function createOrder()
    {
        global $_TABLES, $_PP_CONF;

        // See if an order already exists for this transaction.
        // If so, load it and update the status. If not, continue on
        // and create a new order
        $order_id = DB_getItem($_TABLES['paypal.orders'], 'order_id',
            "pmt_txn_id='" . DB_escapeString($this->pp_data['txn_id']) . "'");
        if (!empty($order_id)) {
            $this->Order = Order::getInstance($order_id);
            if ($this->Order->order_id != '') {
                $this->Order->log_user = $this->gw->Description();
                $this->Order->updateStatus($this->pp_data['status']);
            }
            return 2;
        }

        // Need to create a new, empty order object
        $this->Order = new Order();

        if (isset($this->pp_data['custom']['cart_id'])) {
            $this->Cart = new Cart($this->pp_data['custom']['cart_id'], false);
            if (!$this->Cart->hasItems()) {
                if (!$_PP_CONF['sys_test_ipn']) {
                    return 1; // shouldn't normally be empty except during testing
                }
            }
        } else {
            $this->Cart = NULL;
        }

        $uid = (int)$this->pp_data['custom']['uid'];
        $this->Order->uid = $uid;
        $this->Order->buyer_email = $this->pp_data['payer_email'];
        $this->Order->status = !empty($this->pp_data['status']) ?
                $this->pp_data['status'] : 'pending';
        if ($uid > 1) {
            $U = new UserInfo($uid);
        }

        // Get the billing and shipping addresses from the cart record,
        // if any.  There may not be a cart in the database if it was
        // removed by a previous IPN, e.g. this is the 'completed' message
        // and we already processed a 'pending' message
        $BillTo = '';
        if ($this->Cart) {
            $BillTo = $this->Cart->getAddress('billto');
            $this->Order->instructions = $this->Cart->getInstructions();
        }
        if (empty($BillTo) && $uid > 1) {
            $BillTo = $U->getDefaultAddress('billto');
        }
        if (is_array($BillTo)) {
            $this->Order->setBilling($BillTo);
        }

        $ShipTo = $this->pp_data['shipto'];
        if (empty($ShipTo)) {
            if ($this->Cart) $ShipTo = $this->Cart->getAddress('shipto');
            if (empty($ShipTo) && $uid > 1) {
                $ShipTo = $U->getDefaultAddress('shipto');
            }
        }
        if (is_array($ShipTo)) {
            $this->Order->setShipping($ShipTo);
        }
        if (isset($this->pp_data['shipto']['phone'])) {
            $this->Order->phone = $this->pp_data['shipto']['phone'];
        }
        $this->Order->pmt_method = $this->gw_id;
        $this->Order->pmt_txn_id = $this->pp_data['txn_id'];
        $this->Order->shipping = $this->pp_data['pmt_shipping'];
        $this->Order->handling = $this->pp_data['pmt_handling'];
        $this->Order->buyer_email = $this->pp_data['payer_email'];
        $this->Order->log_user = $this->gw->Description();

        $this->Order->items = array();
        foreach ($this->items as $id=>$item) {
            $options = DB_escapeString($item['options']);
            $option_desc = array();
            //$tmp = explode('|', $item['item_number']);
            //list($item_number,$options) =
            //if (is_numeric($item_number)) {
            $P = Product::getInstance($item['item_id'], $this->pp_data['custom']);
            $item['short_description'] = $P->short_description;
            if (!empty($options)) {
                // options is expected as CSV
                $sql = "SELECT attr_name, attr_value
                        FROM {$_TABLES['paypal.prod_attr']}
                        WHERE attr_id IN ($options)";
                $optres = DB_query($sql);
                $opt_str = '';
                while ($O = DB_fetchArray($optres, false)) {
                    $opt_str .= ', ' . $O['attr_value'];
                    $option_desc[] = $O['attr_name'] . ': ' . $O['attr_value'];
                }
            }

            // Get the product record and custom strings
            if (isset($item['extras']['custom']) &&
                    is_array($item['extras']['custom']) &&
                    !empty($item['extras']['custom'])) {
                foreach ($item['extras']['custom'] as $cust_id=>$cust_val) {
                    $option_desc[] = $P->getCustom($cust_id) . ': ' . $cust_val;
                }
            }
            $args = array(
                'order_id' => $this->Order->order_id,
                'product_id' => $item['item_number'],
                'description' => $item['short_description'],
                'quantity' => $item['quantity'],
                'user_id' => $this->pp_data['custom']['uid'],
                'txn_type' => $this->pp_data['custom']['transtype'],
                'txn_id' => $this->pp_data['txn_id'],
                'purchase_date' => $this->sql_date,
                'status' => 'paid',
                'token' => md5(time()),
                'price' => $item['price'],
                'taxable' => $P->taxable,
                'options' => $options,
                'options_text' => $option_desc,
                'extras' => $item['extras'],
                'shipping' => PP_getVar($item, 'shipping', 'float'),
                'handling' => PP_getVar($item, 'handling', 'float'),
                'paid' => PP_getVar($item['overrides'], 'price', 'float', $item['price']),
            );
            $this->Order->addItem($args);
        }   // foreach item
        return 0;
    }


    /**
    *   Process a refund.
    *   If a purchase is completely refunded, then call the plugins to
    *   handle the refund.  Otherwise, do nothing; partial refunds need to
    *   be handled manually.
    *
    *   @todo: handle partial refunds
    */
    protected function handleRefund()
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP;

        // Try to get original order information.  Use the "parent transaction"
        // or invoice number, if available from the IPN message
        if (isset($this->pp_data['invoice'])) {
            $order_id = $this->pp_data['invoice'];
        } else {
            $order_id = DB_getItem($_TABLES['paypal.orders'], 'order_id',
                "pmt_txn_id = '" . DB_escapeString($this->pp_data['parent_txn_id'])
                . "'");
        }

        $Order = Order::getInstance($order_id);
        if ($Order->order_id == '') {
            return false;
        }

        // Figure out if the entire order was refunded
        $refund_amt = abs((float)$this->pp_data['pmt_gross']);

        $item_total = 0;
        foreach ($Order->items as $key => $data) {
            $item_total += $data['quantity'] * $data['price'];
        }
        $item_total += $Order->miscCharges();

        if ($item_total == $refund_amt) {
            // Completely refunded, let the items handle any refund
            // actions.  None for catalog items (since there's no inventory,
            // but plugin items may need to do something.
            foreach ($Order->items as $key=>$data) {
                $P = Product::getInstance($data['product_id'], $this->pp_data['custom']);
                // Don't care about the status, really.  May not even be
                // a plugin function to handle refunds
                $P->handleRefund($Order, $this->pp_data);
            }
            // Update the order status to Refunded
            $Order->updateStatus($LANG_PP['orderstatus']['refunded']);
        }
    }  // function handleRefund


    /**
    *   Handle a subscription payment. (Not implemented yet)
    *
    *   @todo Implement handleSubscription
    */
    /*private function handleSubscription()
    {
        $this->handleFailure(IPN_FAILURE_UNKNOWN, "Subscription not handled");
    }*/


    /**
    *   Handle a Donation payment (Not implemented)
    *
    *   @todo Implement handleDonation
    */
    /*private function handleDonation()
    {
        $this->handleFailure(IPN_FAILURE_UNKNOWN, "Donation not handled");
    }*/


    /**
    *   Handle what to do in the event of a purchase/IPN failure.
    *
    *   This method does some basic failure handling.  For anything more
    *   advanced it is recommend you override this method.
    *
    *   @param  integer $type   Type of failure that occurred
    *   @param  string  $msg    Failure message
    */
    protected function handleFailure($type = IPN_FAILURE_UNKNOWN, $msg = '')
    {
        // Log the failure to glFusion's error log
        $this->Error($this->gw_id . '-IPN: ' . $msg, 1);
    }


    /**
    *   Debugging function.  Dumps variables to error log
    *
    *   @param  mixed   $var    Data to log
    */
    protected function debug($var)
    {
        $msg = print_r($var, true);
        COM_errorLog('IPN Debug: ' . $msg, 1);
    }


    /**
    *   Log an error message.
    *   This just formats the message to indicate the gateway ID.
    *
    *   @param  string  $str    Error message to log
    */
    protected function Error($str)
    {
        COM_errorLog($this->gw_id. ' IPN Exception: ' . $str, 1);
    }


    /**
    *   Instantiate and return an IPN class
    *
    *   @param  string  $name   Gateway name, e.g. paypal
    *   @param  array   $vars   Gateway variables to be passed to the IPN
    *   @return object          IPN handler object
    */
    public static function getInstance($name, $vars=array())
    {
        static $ipns = array();
        if (!array_key_exists($name, $ipns)) {
            $cls = __NAMESPACE__ . '\\ipn\\' . $name;
            if (class_exists($cls)) {
                $ipns[$name] = new $cls($vars);
            } else {
                $ipns[$name] = NULL;
            }
        }
        return $ipns[$name];
    }

}   // class IPN

?>

<?php
/**
*   Order class for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.10
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Order class
*   @package    paypal
*/
class Order
{
    public $items = array();
    private $properties = array();
    private $isNew = true;
    var $no_shipping = 1;
    private $_addr_fields = array('billto_name',
            'billto_company', 'billto_address1', 'billto_address2',
            'billto_city', 'billto_state', 'billto_country', 'billto_zip',
            'shipto_name',
            'shipto_company', 'shipto_address1', 'shipto_address2',
            'shipto_city', 'shipto_state', 'shipto_country',
            'shipto_zip',
    );


    /**
    *   Constructor
    *   Set internal variables and read the existing order if an id is provided
    *
    *   @param  string  $id     Optional order ID to read
    */
    public function __construct($id='')
    {
        global $_USER, $_PP_CONF;

        $this->isNew = true;
        $this->uid = $_USER['uid'];
        $this->order_date = PAYPAL_now()->toMySql();
        $this->instructions = '';
        if (!empty($id)) {
            $this->order_id = $id;
            if (!$this->Load($id)) {
                $this->isNew = true;
                $this->items = array();
            } else {
                $this->isNew = false;
            }
        }
        if ($this->isNew) {
            $this->order_id = COM_makeSid();
        }
    }


    /**
    *   Get an object instance for an order.
    *
    *   @param  string  $id     Order ID
    *   @return object          Order object
    */
    public static function getInstance($id)
    {
        global $_TABLES;
        static $orders = array();
        if (!array_key_exists($id, $orders)) {
            $orders[$id] = new self($id);
        }
        return $orders[$id];
    }


    /**
    *   Magic setter function
    *   Set a property value
    *
    *   @param  string  $name   Name of property to set
    *   @param  mixed   $value  Value to set
    */
    function __set($name, $value)
    {
        switch ($name) {
        case 'uid':
            $this->properties[$name] = (int)$value;
            break;

        case 'tax':
        case 'shipping':
        case 'handling':
        case 'by_gc':
            $this->properties[$name] = (float)$value;
            break;

        default:
            $this->properties[$name] = $value;
            break;
        }
    }


    /**
    *   Magic getter function
    *   Return the value of a property, or NULL if the property is not set
    *
    *   @param  string  $name   Name of property to retrieve
    *   @return mixed           Value of property
    */
    function __get($name)
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else {
            return NULL;
        }
    }


    /**
    *   Load the order information from the database
    *
    *   @param  string  $id     Order ID
    *   @return boolean     True on success, False if order not found
    */
    public function Load($id = '')
    {
        global $_TABLES;

        if ($id != '') {
            $this->order_id = $id;

            $sql = "SELECT * FROM {$_TABLES['paypal.orders']}
                    WHERE order_id='{$this->order_id}'";
            $res = DB_query($sql);
            if (!$res) return false;    // requested order not found
            $A = DB_fetchArray($res, false);
            if (empty($A)) return false;
            if ($this->SetVars($A)) $this->isNew = false;
        }

        // Now load the items
        $this->items = array();
        $sql = "SELECT * FROM {$_TABLES['paypal.purchases']}
                WHERE order_id = '{$this->order_id}'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $this->items[$A['id']] = new OrderItem($A);
            $X = DB_fetchArray(DB_query("SELECT *
                    FROM {$_TABLES['paypal.products']}
                    WHERE id='".DB_escapeString($A['product_id'])."'"), false);
            //$this->items[$A['id']]['data'] = $X;
        }
        return true;
    }


    /**
    *   Set the order items from the provided array.
    *
    *   @param  array   $A      Array of item_id=>item_data
    */
    public function XXsetItems($A)
    {
        global $_TABLES;

        if (!is_array($A)) return;
        $this->items = array();         // re-initialize the array

        // For each item, break out the item data and set the array value.
        // Look up the product information from the database and set that
        // into the array's "data" element.
        foreach ($A as $item_number => $data) {
            $this->AddItem($item_number, $data);
        }
    }


    /**
    *   Add a single item to this order
    *   Extracts item information from the provided $data variable, and
    *   reads the item information from the database as well.  The entire
    *   item record is added to the $items array as 'data'
    *
    *   @param  mixed   $item_number    Item number, may be string or integer
    *   @param  array   $data           Array of item information
    */
    public function XXAddItem($item_number, $data)
    {
        global $_TABLES;

        if (!is_array($data)) return;

        list($item_id, $item_opts) = explode('|', $item_number);
        if (!is_array($data['flags'])) {
            $data['flags'] = array('flag' => $data['flags']);
        }
        $X = DB_fetchArray(DB_query("SELECT *
                    FROM {$_TABLES['paypal.products']}
                    WHERE id='".DB_escapeString($item_id)."'"), false);
        $this->items[$item_number] = array(
                'item_number'   => $item_number,
                'descrip'       => $data['descrip'],
                'quantity'      => $data['quantity'],
                'price'         => $data['price'],
                'options'       => $item_opts,
                'data'          => $X,
        );
        if (isset($data['options']) && !empty($data['options'])) {
            $this->items[$item_number]['options'] = $data['options'];
            $opt_arr = explode(',', $data['options']);
            $optname_arr = array();
            foreach($opt_arr as $opt_id) {
                $optname_arr[] = DB_getItem($_TABLES['paypal.prod_attr'],
                            'attr_value', "attr_id='".(int)$opt_id."'");
            }
            $this->items[$item_number]['descrip'] .=
                    ' (' . implode(', ', $optname_arr) . ')';
        }
    }


    /**
    *   Set the billing address.
    *
    *   @param  array   $A      Array of info, such as from $_POST
    */
    public function setBilling($A)
    {
        if (isset($A['useaddress'])) {
            // If set, the user has selected an existing address. Read
            // that value and use it's values.
            Cart::setSession('billing', $A['useaddress']);
            $A = UserInfo::getAddress($A['useaddress']);
            $prefix = '';
        } else {
            // form vars have this prefix
            $prefix = 'billto_';
        }

        if (!empty($A)) {
            $this->billto_name     = PP_getVar($A, 'name');
            $this->billto_company  = PP_getVar($A, 'company');
            $this->billto_address1 = PP_getVar($A, 'address1');
            $this->billto_address2 = PP_getVar($A, 'address2');
            $this->billto_city     = PP_getVar($A, 'city');
            $this->billto_state    = PP_getVar($A, 'state');
            $this->billto_country  = PP_getVar($A, 'country');
            $this->billto_zip      = PP_getVar($A, 'zip');
        }
    }


    /**
    *   Set shipping address
    *
    *   @param  array   $A      Array of info, such as from $_POST
    */
    public function setShipping($A)
    {
        if (isset($A['useaddress'])) {
            // If set, read and use an existing address
            Cart::setSession('shipping', $A['useaddress']);
            $A = UserInfo::getAddress($A['useaddress']);
            $prefix = '';
        } else {
            // form vars have this prefix
            $prefix = 'shipto_';
        }

        if (!empty($A)) {
            $this->shipto_name     = PP_getVar($A, 'name');
            $this->shipto_company  = PP_getVar($A, 'company');
            $this->shipto_address1 = PP_getVar($A, 'address1');
            $this->shipto_address2 = PP_getVar($A, 'address2');
            $this->shipto_city     = PP_getVar($A, 'city');
            $this->shipto_state    = PP_getVar($A, 'state');
            $this->shipto_country  = PP_getVar($A, 'country');
            $this->shipto_zip      = PP_getVar($A, 'zip');
        }
    }


    /**
    *   Set all class variables, from a form or a database item
    *
    *   @param  array   $A      Array of items
    */
    function SetVars($A)
    {
        if (!is_array($A)) return false;

        $this->uid      = PP_getVar($A, 'uid', 'int');
        $this->status   = PP_getVar($A, 'status');
        $this->pmt_method = PP_getVar($A, 'pmt_method');
        $this->pmt_txn_id = PP_getVar($A, 'pmt_txn_id');
        $this->order_date = PP_getVar($A, 'order_date');
        $this->order_id = PP_getVar($A, 'order_id');
        $this->shipping = PP_getVar($A, 'shipping', 'float');
        $this->handling = PP_getVar($A, 'handling', 'float');
        $this->tax = PP_getVar($A, 'tax', 'float');
        $this->instructions = PP_getVar($A, 'instructions');
        $this->by_gc = PP_getVar($A, 'by_gc', 'float');

        foreach ($this->_addr_fields as $fld) {
            $this->$fld = $A[$fld];
        }
        if (isset($A['uid'])) $this->uid = $A['uid'];

        if (isset($A['order_id']) && !empty($A['order_id'])) {
            $this->order_id = $A['order_id'];
            $this->isNew = false;
            Cart::setSession('order_id', $A['order_id']);
        } else {
            $this->order_id = '';
            $this->isNew = true;
            Cart::clearSession('order_id');
        }
    }


    /**
    *   API function to delete all cart items, if an order has been created.
    *
    *   @param  stirng  $order_id       Order ID, taken from $_SESSION if empty
    */
    public function Delete($order_id = '')
    {
        global $_TABLES;

        if ($order_id == '') {
            $order_id = Cart::getSession('order_id');
        }
        if (!$order_id) return;

        $order_id = DB_escapeString($order_id);
        $status = (int)DB_getItem($_TABLES['paypal.orders'],
                'status', "order_id='$order_id'");
        if ($status == PP_STATUS_OPEN) {
            DB_delete($_TABLES['paypal.purchases'], 'order_id', $order_id);
            DB_delete($_TABLES['paypal.orders'], 'order_id', $order_id);
        }
    }


    public function CreateFromCart($cart)
    {
        foreach (array('billto', 'shipto') as $t) {
            $A = $cart->getAddress($t);
            $this->{$t . '_name'}   = $A['name'];
            $this->{$t . '_company'}  = $A['company'];
            $this->{$t . '_address1'} = $A['address1'];
            $this->{$t . '_address2'} = $A['address2'];
            $this->{$t . '_city'}     = $A['city'];
            $this->{$t . '_state'}    = $A['state'];
            $this->{$t . '_country'}  = $A['country'];
            $this->{$t.'_zip'}      = $A['zip'];
        }
        $this->status = '';
        $this->pmt_method = '';
        $this->pmt_txn_id = '';
    }


    /**
    *   Save the current order to the database
    */
    public function Save()
    {
        global $_TABLES, $_PP_CONF;

        if ($this->isNew) {
            // Shouldn't have an empty order ID, but double-check
            if ($this->order_id == '') $this->order_id = COM_makeSid();
            if ($this->billto_name == '') {
                $this->billto_name = COM_getDisplayName($this->uid);
            }
            Cart::setSession('order_id', $this->order_id);
            $sql1 = "INSERT INTO {$_TABLES['paypal.orders']} SET
                    order_id='{$this->order_id}',
                    order_date = '{$this->order_date}',
                    uid = '" . (int)$this->uid . "', ";
            $sql2 = '';
            $log_msg = 'Order Created';
        } else {
            $sql1 = "UPDATE {$_TABLES['paypal.orders']} SET ";
            $sql2 = " WHERE order_id = '{$this->order_id}'";
            $log_msg = 'Order Updated';
        }

        $fields = array(
                "status = '{$this->status}'",
                "pmt_txn_id = '" . DB_escapeString($this->pmt_txn_id) . "'",
                "pmt_method = '" . DB_escapeString($this->pmt_method) . "'",
                "by_gc = '{$this->by_gc}'",
                "phone = '" . DB_escapeString($this->phone) . "'",
                "tax = '{$this->tax}'",
                "shipping = '{$this->shipping}'",
                "handling = '{$this->handling}'",
                "instructions = '" . DB_escapeString($this->instructions) . "'",
                "buyer_email = '" . DB_escapeString($this->buyer_email) . "'",
        );
        foreach ($this->_addr_fields as $fld) {
            $fields[] = $fld . "='" . DB_escapeString($this->$fld) . "'";
        }
        $sql = $sql1 . implode(', ', $fields) . $sql2;
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            $this->Log($log_msg);
        }
        $this->isNew = false;

        return $this->order_id;
    }


    /**
    *   View the current order summary
    *
    *   @param  boolean $final      Indicates that this order is final.
    *   @param  string  $tpl        "print" for a printable template
    *   @return string      HTML for order view
    */
    public function View($final = false, $tpl = '')
    {
        global $_PP_CONF, $_USER, $LANG_PP, $LANG_ADMIN, $_TABLES, $_CONF,
            $_SYSTEM;

        // canView should be handled by the caller
        if (!$this->canView()) return '';

        $T = new \Template(PAYPAL_PI_PATH . '/templates');
        $tplname = 'order';
        if (!empty($tpl)) $tplname .= '.' . $tpl;
        $T->set_file('order', "$tplname.thtml");

        $isAdmin = plugin_ismoderator_paypal() ? true : false;

        foreach ($this->_addr_fields as $fldname) {
            $T->set_var($fldname, $this->$fldname);
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        // Get the workflows so we sho the relevant info.
        if (!isset($_PP_CONF['workflows']) ||
            !is_array($_PP_CONF['workflows'])) {
            Workflow::Load();
        }
        foreach ($_PP_CONF['workflows'] as $key => $value) {
            $T->set_var('have_' . $value, 'true');
        }

        $this->no_shipping = 1;   // no shipping unless physical item ordered
        $subtotal = 0;
        foreach ($this->items as $key => $item) {
            $item_options = '';
            $opt = $item->options_text;
            if ($opt && is_array($opt)) {
                foreach ($opt as $opt_str) {
                    $item_options .= "&nbsp;&nbsp;--&nbsp;$opt_str<br />\n";
                }
            }
            $item_total = $item->price * $item->quantity;
            $subtotal += $item_total;
            $T->set_var(array(
                'item_id'       => htmlspecialchars($item->product_id),
                'item_descrip'  => htmlspecialchars($item->description),
                'item_price'    => COM_numberFormat($item->price, 2),
                'item_quantity' => (int)$item->quantity,
                'item_total'    => COM_numberFormat($item_total, 2),
                'item_options'  => $item_options,
                'is_admin' => $isAdmin ? 'true' : '',
                'is_file' => $item->getProduct()->file != '' ? 'true' : '',
            ) );
            $T->parse('iRow', 'ItemRow', true);
            if ($item->getProduct()->prod_type == PP_PROD_PHYSICAL) {
                $this->no_shipping = 0;
            }
        }

        $dt = new \Date($this->order_date, $_CONF['timezone']);
        $total = $subtotal + $this->shipping + $this->handling + $this->tax;
        $T->set_var(array(
            'pi_url'    => PAYPAL_URL,
            'is_admin' => $isAdmin ? 'true' : '',
            'pi_admin_url' => PAYPAL_ADMIN_URL,
            'total'     => COM_numberFormat($total, 2),
            'not_final' => $final ? '' : 'true',
            'order_date' => $dt->format($_PP_CONF['datetime_fmt'], true),
            'order_date_tip' => $dt->format($_PP_CONF['datetime_fmt'], false),
            'order_number' => $this->order_id,
            'shipping' => COM_numberFormat($this->shipping, 2),
            'handling' => COM_numberFormat($this->handling, 2),
            'tax' => COM_numberFormat($this->tax, 2),
            'subtotal' => COM_numberFormat($subtotal, 2),
            'have_billto' => 'true',
            'have_shipto' => 'true',
            'order_instr' => htmlspecialchars($this->instructions),
            'shop_name' => $_PP_CONF['shop_name'],
            'shop_addr' => $_PP_CONF['shop_addr'],
            'shop_phone' => $_PP_CONF['shop_phone'],
            'apply_gc'     => COM_numberFormat($this->by_gc, 2),
            'net_total' => $total - $this->by_gc,
            'iconset'   => $_PP_CONF['_iconset'],
        ) );

        //if ($isAdmin) {
            $T->set_var(array(
                'purch_name' => COM_getDisplayName($this->uid),
                'purch_uid' => $this->uid,
                'stat_update' => OrderStatus::Selection($this->order_id, 1, $this->status),
                'status' => $this->status,
            ) );

            $log = $this->getLog();
            $T->set_block('order', 'LogMessages', 'Log');
            foreach ($log as $L) {
                $dt->setTimestamp(strtotime($L['ts']));
                $T->set_var(array(
                    'log_username'  => $L['username'],
                    'log_msg'       => $L['message'],
                    'log_ts'        => $dt->format($_PP_CONF['datetime_fmt'], true),
                    'log_ts_tip'    => $dt->format($_PP_CONF['datetime_fmt'], false),
                ) );
                $T->parse('Log', 'LogMessages', true);
            }
        //}

        $status = $this->status;
        if ($this->pmt_method != '') {
            $gw = Gateway::getInstance($this->pmt_method);
            if ($gw !== NULL) {
                $pmt_method = $gw->Description();
            } else {
                $pmt_method = $this->pmt_method;
            }

            $T->set_var(array(
                'pmt_method' => $pmt_method,
                'pmt_txn_id' => $this->pmt_txn_id,
            ) );
        }

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));

        return $form;
    }


    /**
    *   Update the order's status flag to a new value
    *   If the new status isn't really new, the order is unchanged and "true"
    *   is returned.  If this is called by some automated process, $log can
    *   be set to "false" to avoid logging the change, such as during order
    *   creation.
    *
    *   @uses   Log()
    *   @param  string  $newstatus      New order status
    *   @param  boolean $log            True to log the change, False to not
    *   @return boolean                 True on success or no change
    */
    public function updateStatus($newstatus, $log = true)
    {
        global $_TABLES, $_PP_CONF;

        // Need to get the order statuses to see if we should notify
        // the buyer
        $OrdStat = new OrderStatus();

        $order_id = $this->order_id;
        $oldstatus = $this->status;
        $this->status = $newstatus;
        $db_order_id = DB_escapeString($order_id);
        $log_user = $this->log_user;

        // If the status isn't really changed, don't bother updating anything
        // and just treat it as successful
//        if ($oldstatus == $newstatus) return true;

        $sql = "UPDATE {$_TABLES['paypal.orders']}
                SET status = '". DB_escapeString($newstatus) . "'
                WHERE order_id = '$db_order_id'";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) return false;
        if ($log) {
            $this->Log("Status changed from $oldstatus to $newstatus",
                    $log_user);
        }

        if (isset($_PP_CONF['orderstatus'][$newstatus]['notify_buyer']) &&
                $_PP_CONF['orderstatus'][$newstatus]['notify_buyer'] == 1) {
            $this->Notify($newstatus);
        }
        return true;
    }


    /**
    *   Log a message related to this order.
    *   Typically used to log status changes.  If this is called for an
    *   order object, the local "log_user" variable can be preset to the
    *   log user name.  Otherwise, the current user's display name will be
    *   associated with the log entry.
    *
    *   @param  string  $msg        Log message
    *   @param  string  $log_user   Optional log username
    *   @return boolean             True on success, False on DB error
    */
    public function Log($msg, $log_user = '')
    {
        global $_TABLES, $_USER;

        // If the order ID is omitted, get information from the current
        // object.
        if (empty($log_user)) {
            $log_user = COM_getDisplayName($_USER['uid']) .
                ' (' . $_USER['uid'] . ')';
        }
        $order_id = DB_escapeString($this->order_id);
        $sql = "INSERT INTO {$_TABLES['paypal.order_log']} SET
            username = '" . DB_escapeString($log_user) . "',
            order_id = '$order_id',
            message = '" . DB_escapeString($msg) . "',
            ts = UTC_TIMESTAMP()";
        DB_query($sql);
        $cache_key = 'orderlog_' . $order_id;
        Cache::delete($cache_key);
        return true;
    }


    /**
    *   Send an email to the buyer
    *
    *   @param  string  $status     Order status (pending, paid, etc.)
    *   @param  string  $msg        Optional message to include with email
    */
    public function Notify($status='', $gw_msg='')
    {
        global $_CONF, $_PP_CONF, $_TABLES;

        // Check if we're supposed to send a notification
        if ( ($this->uid > 1 &&
                    $_PP_CONF['purch_email_user'] == 0) ||
            ($this->uid == 1  &&
                    $_PP_CONF['purch_email_anon'] == 0) ) {
            return;
        }
        PAYPAL_debug("Sending email to " . $this->uid);

        // setup templates
        $T = new \Template(PAYPAL_PI_PATH . '/templates');
        $T ->set_file(array(
            'subject' => 'purchase_email_subject.txt',
            'msg_admin' => 'purchase_email_admin.txt',
            'msg_user' => 'purchase_email_user.txt',
            'msg_body' => 'purchase_email_body.txt',
        ) );

        // Add all the items to the message
        $total = (float)0;      // Track total purchase value
        $files = array();       // Array of filenames, for attachments
        $num_format = "%5.2f";
        $item_total = 0;
        $have_physical = 0;     // Assume no physical items.
        $dl_links = '';         // Start with empty download links

        foreach ($this->items as $id=>$item) {
            //$P = Product::getInstance($item->product_id);
            //if ($P->prod_type & PP_PROD_PHYSICAL == PP_PROD_PHYSICAL)
            if ($item->getProduct()->prod_type & PP_PROD_PHYSICAL == PP_PROD_PHYSICAL)
                $have_physical = 1;

            // Add the file to the filename array, if any. Download
            // links are only included if the order status is 'paid'
            $file = $item->getProduct()->file;
            if (!empty($file) && $this->status == 'paid') {
                $files[] = $file;
                $dl_url = PAYPAL_URL . '/download.php?';
                // There should always be a token, but fall back to the
                // product ID if there isn't
                if (!empty($item->token)) {
                    $dl_url .= 'token=' . urlencode($item->token);
                } else {
                    $dl_url .= 'id=' . $item->item_number;
                }
                $dl_links .= "<a href=\"$dl_url\">$dl_url</a><br />";
            }

            $ext = (float)$item->quantity * (float)$item->price;
            $item_total += $ext;
            $item_descr = $item->getShortDscp();

            $options_text = '';
            $opts = $item->options_text;
            if (is_array($opts)) {
                foreach ($opts as $opt_text) {
                    $options_text .= "&nbsp;&nbsp;--&nbsp;$opt_text<br />";
                }
            }
            $T->set_block('msg_body', 'ItemList', 'List');
            $T->set_var(array(
                'qty'   => $item->quantity,
                'price' => sprintf($num_format, $item->price),
                'ext'   => sprintf($num_format, $ext),
                'name'  => $item_descr,
                'options_text' => $options_text,
            ) );
            $T->parse('List', 'ItemList', true);
        }

        // Determine if files will be attached to this message based on
        // global config and whether there are actually any files to
        // attach. Affects the 'files' flag in the email template and
        // which email function is used.
        if ( (( is_numeric($this->uid) &&
                    $this->uid != 1 &&
                    $_PP_CONF['purch_email_user_attach'] ) ||
            ( (!is_numeric($this->uid) ||
                    $this->uid == 1) &&
                    $_PP_CONF['purch_email_anon_attach'] )) &&
                count($files) > 0  ) {
            $do_send_attachments = true;
        } else {
            $do_send_attachments = false;
        }

        $total_amount = $item_total + $this->tax + $this->shipping +
                        $this->handling;
        $user_name = COM_getDisplayName($this->uid);
        if ($this->billto_name == '') {
            $this->billto_name = $user_name;
        }

        $T->set_var(array(
            'payment_gross'     => sprintf($num_format, $total_amount),
            'payment_items'     => sprintf($num_format, $item_total),
            'tax'               => sprintf($num_format, $this->tax),
            'tax_num'           => $this->tax,
            'shipping'          => sprintf($num_format, $this->shipping),
            'shipping_num'      => $this->shipping,
            'handling'          => sprintf($num_format, $this->handling),
            'handling_num'      => $this->handling,
            'payment_date'      => PAYPAL_now()->toMySQL(true),
            'payer_email'       => $this->buyer_email,
            'payer_name'        => $this->billto_name,
            'site_name'         => $_CONF['site_name'],
            'txn_id'            => $this->pmt_txn_id,
            'pi_url'            => PAYPAL_URL,
            'pi_admin_url'      => PAYPAL_ADMIN_URL,
            'dl_links'          => $dl_links,
            'files'             => $do_send_attachments ? 'true' : '',
            'buyer_uid'         => $this->uid,
            'user_name'         => $user_name,
            'gateway_name'      => $this->pmt_method,
            'pending'       => $this->status == 'pending' ? 'true' : '',
            'gw_msg'        => $gw_msg,
            'status'            => $this->status,
            'order_instr'   => $this->instructions,
            'order_id'      => $this->order_id,
        ) );
        if ($this->by_gc > 0) {
            $T->set_var(array(
                'by_gc'     => sprintf($num_format, $this->by_gc),
                'net_total' => sprintf($num_format, $total_amount - $this->by_gc),
            ) );
        }
        $gc_bal = Coupon::getUserBalance($this->uid);
        if ($gc_bal > 0) {
            $T->set_var(array(
                'gc_bal_fmt' => sprintf($num_format, $gc_bal),
                'gc_bal_num' => $gc_bal,
            ) );
        }

        // parse templates for subject/text
        $subject = trim($T->parse('output', 'subject'));
        $T->set_var('purchase_details',
                        $T->parse('detail', 'msg_body'));
        $user_text  = $T->parse('user_out', 'msg_user');
        $admin_text = $T->parse('admin_out', 'msg_admin');

        if ($this->buyer_email != '') {
            // if specified to mail attachment, do so, otherwise skip
            // attachment
            if ($do_send_attachments) {
                // Make sure plugin functions are available
                USES_paypal_functions();
                PAYPAL_mailAttachment($this->buyer_email,
                                    $subject,
                                    $user_text,
                                    $_CONF['site_email'],
                                    true,
                                    0, '', '', $files);
            } else {
                // Otherwise send a standard notification
                COM_emailNotification(array(
                        'to' => array($this->buyer_email),
                        'from' => $_CONF['site_mail'],
                        'htmlmessage' => $user_text,
                        'subject' => $subject,
                ) );
            }
        }

        // Send a notification to the administrator, new purchases only
        if ($status == '') {
            if ($_PP_CONF['purch_email_admin'] == 2 ||
                    ($have_physical && $_PP_CONF['purch_email_admin'] == 1)) {
                PAYPAL_debug('Sending email to Admin');
                $email_addr = empty($_PP_CONF['admin_email_addr']) ?
                            $_CONF['site_mail'] : $_PP_CONF['admin_email_addr'];
                COM_emailNotification(array(
                        'to' => array($email_addr),
                        'from' => $_CONF['noreply_mail'],
                        'htmlmessage' => $admin_text,
                            'subject' => $subject,
                ) );
            }
        }
    }   // Notify()


    /**
    *   Get the miscellaneous charges on this order.
    *   Just a shortcut to adding up the non-item charges.
    *
    *   @return float   Total "other" charges, e.g. tax, shipping, etc.
    */
    public function miscCharges()
    {
        return $this->shipping + $this->handling + $this->tax;
    }


    /**
    *   Determine if the current user can view this order.
    *
    *   @return boolean     True if allowed to view, False if denied.
    */
    public function canView()
    {
        global $_USER;

        if ($_USER['uid'] == $this->uid ||
            plugin_ismoderator_paypal()) {
            return true;
        } else {
            return false;
        }
    }


    public function getLog()
    {
        global $_TABLES, $_CONF;

        $order_id = DB_escapeString($this->order_id);
        $cache_key = 'orderlog_' . $order_id;
        $log = Cache::get($cache_key);
        if ($log === NULL) {
            $log = array();
            $sql = "SELECT * FROM {$_TABLES['paypal.order_log']} WHERE order_id = '$order_id'";
            $res = DB_query($sql);
            while ($L = DB_fetchArray($res, false)) {
                $log[] = $L;
            }
            Cache::set($cache_key, $log, 'order_log');
        }
        return $log;
    }

}

?>

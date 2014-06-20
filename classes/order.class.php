<?php
/**
*   Order class for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Order class
*   @package    paypal
*/
class ppOrder
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
        $this->buyer_email = $_USER['email'];
        $this->order_date = $_PP_CONF['now'];
        $this->order_id = COM_makeSid();
        if (!empty($id)) {
            $this->order_id = $id;
            if (!$this->Load($id)) {
                $this->isNew = true;
                $this->items = array();
            } else {
                $this->isNew = false;
            }
        }
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
            $this->items[] = $A;
        }
        return true;
    }


    /**
    *   Set the order items from the provided array.
    *
    *   @param  array   $A      Array of item_id=>item_data
    */
    public function setItems($A)
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
    public function AddItem($item_number, $data)
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
            // If set, the user has selected an existing address.  Read
            // that value and use it's values.
            $_SESSION[PP_CART_VAR]['billing'] = $A['useaddress'];
            USES_paypal_class_userinfo();
            $A = ppUserInfo::getAddress($A['useaddress']);
            $prefix = '';
        } else {
            // form vars have this prefix
            $prefix = 'billto_';
        }

        if (!empty($A)) {
            $this->billto_name     = $A['name'];
            $this->billto_company  = $A['company'];
            $this->billto_address1 = $A['address1'];
            $this->billto_address2 = $A['address2'];
            $this->billto_city     = $A['city'];
            $this->billto_state    = $A['state'];
            $this->billto_country  = $A['country'];
            $this->billto_zip      = $A['zip'];
        }
    }


    /**
    *   Set shipping address
    *
    *   @param  array   $A      Array of info, such as from $_POST
    */
    public function setShipping($A)
    {
        USES_paypal_class_userinfo();

        if (isset($A['useaddress'])) {
            // If set, read and use an existing address
            $_SESSION[PP_CART_VAR]['shipping'] = $A['useaddress'];
            $A = ppUserInfo::getAddress($A['useaddress']);
            $prefix = '';
        } else {
            // form vars have this prefix
            $prefix = 'shipto_';
        }

        if (!empty($A)) {
            $this->shipto_name     = $A['name'];
            $this->shipto_company  = $A['company'];
            $this->shipto_address1 = $A['address1'];
            $this->shipto_address2 = $A['address2'];
            $this->shipto_city     = $A['city'];
            $this->shipto_state    = $A['state'];
            $this->shipto_country  = $A['country'];
            $this->shipto_zip      = $A['zip'];
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

        $this->uid      = (int)$A['uid'];
        $this->status   = $A['status'];
        $this->pmt_method = $A['pmt_method'];
        $this->pmt_txn_id = $A['pmt_txn_id'];
        $this->order_date = $A['order_date'];
        $this->order_id = $A['order_id'];
        $this->shipping = $A['shipping'];
        $this->handling = $A['handling'];
        $this->tax = $A['tax'];

        foreach ($this->_addr_fields as $fld) {
            $this->$fld = $A[$fld];
        }
        if (isset($A['uid'])) $this->uid = $A['uid'];

        //$this->setShipping($A);

        $this->order_date = $A['order_date'];
        if (isset($A['order_id']) && !empty($A['order_id'])) {
            $this->order_id = $A['order_id'];
            $this->isNew = false;
            $_SESSION[PP_CART_VAR]['order_id'] = $A['order_id'];
        } else {
            $this->order_id = '';
            $this->isNew = true;
            $_SESSION[PP_CART_VAR]['order_id'] = '';
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
            if (isset($_SESSION[PP_CART_VAR]['order_id']) &&
                !empty($_SESSION[PP_CART_VAR]['order_id'])) {
                $order_id = $_SESSION[PP_CART_VAR]['order_id'];
            } else {
                return;
            }
        }

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
        global $_USER;

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
            $_SESSION[PP_CART_VAR]['order_id'] = $this->order_id;
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
                "phone = '" . DB_escapeString($this->phone) . "'",
                "tax = '{$this->tax}'",
                "shipping = '{$this->shipping}'",
                "handling = '{$this->handling}'",
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
    *   @return string      HTML for order view
    */
    public function View($final = false)
    {
        global $_PP_CONF, $_USER, $LANG_PP, $LANG_ADMIN, $_TABLES;

        $T = new Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file(array(
                'order'=> 'order.thtml',
        ) );
            
        $isAdmin = SEC_hasRights('paypal.admin') ? true : false;

        foreach ($this->_addr_fields as $fldname) {
            $T->set_var($fldname, $this->$fldname);
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        // Get the workflows so we sho the relevant info.
        if (!isset($_PP_CONF['workflows']) ||
            !is_array($_PP_CONF['workflows'])) {
            USES_paypal_class_workflow();
            ppWorkflow::Load();
        }
        foreach ($_PP_CONF['workflows'] as $key => $value) {
            $T->set_var('have_' . $value, 'true');
        }
        $this->no_shipping = 1;   // no shipping unless physical item ordered
        $subtotal = 0;
        foreach ($this->items as $key => $item) {
            $item_total = $item['price'] * $item['quantity'];
            $subtotal += $item_total;
            $T->set_var(array(
                //'pi_url'        => PAYPAL_URL,
                //'cart_id'       => $id,
                //'pp_id'         => $id + 1,
                //'item_id'       => $item['item_number'],
                'item_id'       => $item['product_id'],
                'item_descrip'  => $item['description'],
                'item_price'    => COM_numberFormat($item['price'], 2),
                'item_quantity' => (int)$item['quantity'],
                'item_total'    => COM_numberFormat($item_total, 2),
            ) );
            $T->parse('iRow', 'ItemRow', true);
            if ($item['data']['prod_type'] == PP_PROD_PHYSICAL) {
                $this->no_shipping = 0;
            }
        }
        
        $total = $subtotal + $this->shipping + $this->handling + $this->tax;
        $T->set_var(array(
            'pi_url'    => PAYPAL_URL,
            'is_admin' => $isAdmin ? 'true' : '',
            'pi_admin_url' => PAYPAL_ADMIN_URL,
            'total'     => sprintf('%6.2f', $total),
            'not_final' => $final ? '' : 'true',
            'order_date' => $this->order_date,
            'order_number' => $this->order_id,
            'shipping' => COM_numberFormat($this->shipping, 2),
            'handling' => COM_numberFormat($this->handling, 2),
            'tax' => COM_numberFormat($this->tax, 2),
            'subtotal' => COM_numberFormat($subtotal, 2),
            'have_billto' => 'true',
            'have_shipto' => 'true',
        ) );

        if ($isAdmin) {
            USES_paypal_class_orderstatus();
            $T->set_var(array(
                'purch_name' => COM_getDisplayName($this->uid),
                'purch_uid' => $this->uid,
                'stat_update' => ppOrderStatus::Selection($this->order_id, 1, $this->status),
            ) );

            $sql = "SELECT * FROM {$_TABLES['paypal.order_log']} WHERE order_id = '" .
                    DB_escapeString($this->order_id) . "'";
            $res = DB_query($sql, 1);
            $T->set_block('order', 'LogMessages', 'Log');
            while ($L = DB_fetchArray($res, false)) {
                $T->set_var(array(
                    'log_username'  => $L['username'],
                    'log_msg'       => $L['message'],
                    'log_ts'        => $L['ts'],
                ) );
                $T->parse('Log', 'LogMessages', true);
            }
            
        }

        $status = $this->status;
        if ($this->pmt_method != '') {
        //if ($status & PP_STATUS_PAID) {
            if (USES_paypal_gateway($this->pmt_method)) {
                $gw = new $this->pmt_method;
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
    *   @param  string  $order_id       Optional order ID or use current object
    *   @param  boolean $log            True to log the change, False to not
    *   @return boolean                 True on success or no change
    */
    function UpdateStatus($newstatus, $order_id = '', $log = true)
    {
        global $_TABLES, $_PP_CONF;

        // Need to get the order statuses to see if we should notify
        // the buyer
        USES_paypal_class_orderstatus();
        $OrdStat = new ppOrderStatus();

        if ($order_id == '' && is_object($this)) {
            $order_id = $this->order_id;
            $oldstatus = $this->status;
            $this->status = $newstatus;
            $db_order_id = DB_escapeString($order_id);
            $log_user = $this->log_user;
        } elseif ($order_id != '') {
            $db_order_id = DB_escapeString($order_id);
            $oldstatus = DB_getItem($_TABLES['paypal.orders'], 'status',
                "order_id = '$db_order_id'");
            $log_user = '';
        }

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
            self::Log("Status changed from $oldstatus to $newstatus",
                    $order_id, $log_user);
        }

        if ($_PP_CONF['orderstatus'][$newstatus]['notify_buyer'] == 1) {
            $this->Notify();
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
    *   @param  string  $order_id   Optional order ID, or use current object
    *   @param  string  $log_user   Optional log username
    *   @return boolean             True on success, False on DB error
    */
    function Log($msg, $order_id = '', $log_user = '')
    {
        global $_TABLES, $_USER;

        // If the order ID is omitted, get information from the current
        // object.
        if ($order_id == '' && is_object($this)) {
            $order_id = $this->order_id;
            $log_user = $this->log_user;
        }
        if (empty($order_id)) return false;
        if (empty($log_user)) {
            $log_user = COM_getDisplayName($_USER['uid']) . 
                ' (' . $_USER['uid'] . ')';
        }

        $sql = "INSERT INTO {$_TABLES['paypal.order_log']} SET
            username = '" . DB_escapeString($log_user) . "',
            order_id = '" . DB_escapeString($order_id) . "',
            message = '" . DB_escapeString($msg) . "'";
        DB_query($sql);
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
        global $_CONF, $_PP_CONF;

        // Check if we're supposed to send a notification
        if ( ($this->uid != 1 &&
                    $_PP_CONF['purch_email_user']) ||
            ($this->uid == 1  &&
                    $_PP_CONF['purch_email_anon']) ) {
            PAYPAL_debug("Sending email to " . $this->uid);

            // setup templates
            $message = new Template(PAYPAL_PI_PATH . '/templates');
            //if ($status == '') {
                $message->set_file(array(
                        'subject' => 'purchase_email_subject.txt',
                        'msg_admin' => 'purchase_email_admin.txt',
                        'msg_user' => 'purchase_email_user.txt',
                        'msg_body' => 'purchase_email_body.txt',
                ) );
/*            } else {
                $message->set_file(array(
                        'subject' => 'update_email_subject.txt',
                        'msg_user' => 'update_email_user.txt',
                        'msg_body' => 'update_email_body.txt',
                ) );
            }*/

            // Add all the items to the message
            $total = (float)0;      // Track total purchase value
            $files = array();       // Array of filenames, for attachments
            $num_format = "%5.2f";
            $item_total = 0;
            $have_physical = 0;     // Assume no physical items.
            $dl_links = '';         // Start with empty download links

            USES_paypal_class_product();
            foreach ($this->items as $id=>$item) {
                if (!PAYPAL_is_plugin_item($item['product_id'])) {
                    $P = new Product($item['product_id']);
                    if ($P->prod_type & PP_PROD_PHYSICAL == PP_PROD_PHYSICAL)
                        $have_physical = 1;

                    // Add the file to the filename array, if any. Download
                    // links are only included if the order status is 'paid'
                    $file = $P->file;
                    if (!empty($file) && $this->status == 'paid') {
                        $files[] = $file;
                        $dl_url = PAYPAL_URL . '/download.php?';
                        // There should always be a token, but fall back to the
                        // product ID if there isn't
                        if (!empty($item['token'])) {
                            $dl_url .= 'token=' . urlencode($item['token']);
                        } else {
                            $dl_url .= 'id=' . $item['item_number'];
                        }
                        $dl_links .= "<p /><a href=\"$dl_url\">$dl_url</a>";
                    }
                }

                $ext = (float)$item['quantity'] * (float)$item['price'];
                $item_total += $ext;
                $item_descr = isset($item['description']) ? $item['description'] : $item['descrip'];

                //$message->set_block('message', 'ItemList', 'List');
                $message->set_block('msg_body', 'ItemList', 'List');
                $message->set_var(array(
                    'qty'   => $item['quantity'],
                    'price' => sprintf($num_format, $item['price']),
                    'ext'   => sprintf($num_format, $ext),
                    'name'  => $item_descr,
                ) );
                //PAYPAL_debug("Qty: {$item['quantity']} : Amount: {$item['price']} : Name: {$item['name']}", 'debug_ipn');
                $message->parse('List', 'ItemList', true);

            }
            if (!empty($files))
                $message->set_var('files', 'true');

            $total_amount = $item_total + $this->tax + $this->shipping +
                        $this->handling;
            $message->set_var(array(
                //'payment_gross'     => sprintf('%6.2f',
                //                    $this->pp_data['pmt_gross']),
                'payment_gross'     => sprintf($num_format, $total_amount),
                'payment_items'     => sprintf($num_format, $item_total),
                'tax'               => sprintf($num_format, $this->tax),
                'shipping'          => sprintf($num_format, $this->shipping),
                'handling'          => sprintf($num_format, $this->handling),
                'payment_date'      => $this->order_date,
                'payer_email'       => $this->buyer_email,
                'payer_name'        => $this->billto_naem,
                'site_name'         => $_CONF['site_name'],
                'txn_id'            => $this->pmt_txn_id,
                'pi_url'            => PAYPAL_URL,
                'pi_admin_url'      => PAYPAL_ADMIN_URL,
                'dl_links'          => $dl_links,
                'buyer_uid'         => $this->uid,
                'gateway_name'      => $this->pmt_method,
                'pending'       => $this->status == 'pending' ? 'true' : '',
                'gw_msg'        => $gw_msg,
            ) );

            // parse templates for subject/text
            $subject = trim($message->parse('output', 'subject'));
            $message->set_var('purchase_details',
                        $message->parse('detail', 'msg_body'));
            $user_text  = $message->parse('user_out', 'msg_user');
            $admin_text = $message->parse('admin_out', 'msg_admin');

            if ($this->buyer_email != '') {
            // if specified to mail attachment, do so, otherwise skip attachment
            if ( (( is_numeric($this->uid) &&
                        $this->uid != 1 &&
                        $_PP_CONF['purch_email_user_attach'] ) ||
                ( (!is_numeric($this->uid) ||
                        $this->uid == 1) &&
                        $_PP_CONF['purch_email_anon_attach'] )) &&
                    count($files) > 0  ) {

                // Make sure plugin functions are available
                USES_paypal_functions();
                PAYPAL_mailAttachment($this->buyer_email,
                                    $subject,
                                    $user_text,
                                    $_CONF['site_email'],
                                    true,
                                    0, '', '', $files);
            } else {
                COM_mail($this->buyer_email,
                            $subject, $user_text,
                            $_CONF['site_email'],
                            true);
            }
            }

            // Send a notification to the administrator, new purchases only
            if ($status == '') {
                if ($_PP_CONF['purch_email_admin'] == 2 ||
                    ($have_physical && $_PP_CONF['purch_email_admin'] == 1)) {
                    PAYPAL_debug('Sending email to Admin');
                    $email_addr = empty($_PP_CONF['admin_email_addr']) ?
                            $_CONF['site_mail'] : $_PP_CONF['admin_email_addr'];
                    COM_mail($email_addr, $subject, $admin_text,
                            '', true);
                }
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

}

?>

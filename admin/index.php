<?php
/**
*   Admin index page for the paypal plugin.  
*   By default, lists products available for editing.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net>
*   @copyright  Copyright (c) 2009-2011 Lee Garner
*   @copyright  Copyright (c) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/** Import Required glFusion libraries */
require_once('../../../lib-common.php');

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('paypal', $_PLUGINS)) {
    COM_404();
}

require_once('../../auth.inc.php');

// Check for required permissions
PAYPAL_access_check('paypal.admin');

USES_paypal_functions();
USES_lib_admin();
USES_paypal_class_product();

$is_uikit = $_SYSTEM['framework'] == 'uikit' ? true : false;

$content = '';

// Get the message to the admin, if any
$msg = array();
if (isset($_REQUEST['msg'])) $msg[] = $_REQUEST['msg'];

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = '';
$expected = array(
    // Actions to perform
    'deleteproduct', 'deletecatimage', 'deletecat', 'delete_img',
    'saveproduct', 'savecat', 'saveopt', 'deleteopt', 'resetbuttons',
    'gwmove', 'gwsave', 'wfmove', 'gwinstall', 'gwdelete', 'attrcopy',
    'dup_product',
    // Views to display
    'history', 'orderhist', 'ipnlog', 'editproduct', 'editcat', 'catlist',
    'attributes', 'editattr', 'other', 'productlist', 'gwadmin', 'gwedit', 
    'wfadmin', 'order', 'itemhist',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';

switch ($action) {
case 'dup_product':
    $P = new Product($_REQUEST['id']);
    $P->Duplicate();
    echo COM_refresh(PAYPAL_ADMIN_URL.'/index.php');
    break;

case 'deleteproduct':
    $P = new Product($_REQUEST['id']);
    if (!$P->isUsed()) {
        $P->Delete();
    } else {
        $content .= "Product has purchase records, can't delete.";
    }
    break;

case 'deletecatimage':
    USES_paypal_class_category();
    $id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    if ($id > 0) {
        $C = new Category($id);
        $C->DeleteImage();
        $view = 'editcat';
        $_REQUEST['id'] = $id;
    } else {
        $view = 'catlist';
    }
    break;

case 'deletecat':
    USES_paypal_class_category();
    $C = new Category($_REQUEST['cat_id']);
    if (!$C->isUsed()) {
        $C->Delete();
    } else {
        $content .= "Category has related products, can't delete.";
    }
    $view = 'catlist';
    break;

case 'delete_img':
    $img_id = (int)$_REQUEST['img_id'];
    Product::DeleteImage($img_id);
    $view = 'editproduct';
    break;

case 'saveproduct':    
    $P = new Product($_POST['id']);
    if (!$P->Save($_POST)) {
        $content .= PAYPAL_errMsg($P->PrintErrors());
        $view = 'editproduct';
    }
    break;

case 'savecat':
    USES_paypal_class_category();
    $C = new Category($_POST['cat_id']);
    if (!$C->Save($_POST)) {
        $content .= PAYPAL_popupMsg($LANG_PP['invalid_form']);
        $view = 'editcat';
    } else {
        $view = 'catlist';
    }
    break;

case 'saveopt':
    USES_paypal_class_attribute();
    $Attr = new Attribute($_POST['attr_id']);
    if (!$Attr->Save($_POST)) {
        $content .= PAYPAL_popupMsg($LANG_PP['invalid_form']);
    }
    if (isset($_POST['attr_id']) && !empty($_POST['attr_id'])) {
        // Updating an existing option, return to the list
        $view = 'attributes';
    } else {
        $view = 'editattr';
    }
    break;

case 'deleteopt':
    USES_paypal_class_attribute();
    // attr_id could be via $_GET or $_POST
    $Attr = new Attribute($_REQUEST['attr_id']);
    $Attr->Delete();
    $view = 'attributes';
    break;

case 'resetbuttons':
    DB_query("TRUNCATE {$_TABLES['paypal.buttons']}");
    $view = 'other';
    break;

case 'gwinstall':
    $gwname = $_GET['gwname'];
    $gwpath = PAYPAL_PI_PATH . '/classes/gateways/' . $gwname . '.class.php';
    if (is_file($gwpath)) {
        PAYPAL_loadGateways();
        require_once $gwpath;
        $gw = new $gwname();
        if ($gw->Install()) {
            $msg[] = "Gateway \"$gwname\" installed successfully";
        } else {
            $msg[] = "Failed to install the \"$gwname\" gateway";
        }
    }
    $view = 'gwadmin';
    break;
        
case 'gwdelete':
    PAYPAL_loadGateways(true);
    $gw_id = $_GET['id'];
    $gw = new $gw_id();
    $status = $gw->Remove();
    $view = 'gwadmin';
    break;

case 'gwsave':
    // Save a payment gateway configuration
    PAYPAL_loadGateways(true);
    $gw_id = $_POST['gw_id'];
    $gw = new $gw_id();
    $status = $gw->SaveConfig($_POST);
    $view = 'gwadmin';
    break;

case 'gwmove':
    PAYPAL_loadGateways();  // just need the PaymentGw class
    PaymentGw::moveRow($_GET['id'], $actionval);
    $view = 'gwadmin';
    break;

case 'wfmove':
    switch ($_GET['type']) {
    case 'workflow':
        USES_paypal_class_workflow();
        ppWorkflow::moveRow($_GET['id'], $actionval);
        break;
    case 'orderstatus':
        USES_paypal_class_orderstatus();
        ppOrderStatus::moveRow($_GET['id'], $actionval);
        break;
    }
    $view = 'wfadmin';
    break;

case 'attrcopy':
    // Copy attributes from a product to another product or category
    $src_prod = (int)$_POST['src_prod'];
    $dest_prod = (int)$_POST['dest_prod'];
    $dest_cat = (int)$_POST['dest_cat'];

    // Nothing to do if no source product selected
    if ($src_prod < 1) break;

    if ($dest_prod > 0 && $dest_prod != $src_prod) {
        $sql = "INSERT IGNORE INTO {$_TABLES['paypal.prod_attr']}
            SELECT NULL, $dest_prod, attr_name, attr_value, orderby, attr_price, enabled
            FROM {$_TABLES['paypal.prod_attr']}
            WHERE item_id = $src_prod";
        DB_query($sql);
    }

    // Copy product attributes to all products in a category.
    // Ignore the source product, which may or may not be in the category.
    if ($dest_cat > 0) {
        // Get all products in the category
        $res = DB_query("SELECT id FROM {$_TABLES['paypal.products']}
                WHERE cat_id = $dest_cat
                AND id <> $src_prod");
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $dest_prod = (int)$A['id'];
                $sql = "INSERT IGNORE INTO {$_TABLES['paypal.prod_attr']}
                SELECT NULL, $dest_prod, attr_name, attr_value, orderby, attr_price, enabled
                FROM {$_TABLES['paypal.prod_attr']}
                WHERE item_id = $src_prod";
                DB_query($sql);
            }
        }
    }
    echo COM_refresh(COM_buildUrl(PAYPAL_ADMIN_URL . '/index.php?attributes=x'));
    break;

default:
    $view = $action;
    break;
}

//PAYPAL_debug('Admin view: ' . $action);
switch ($view) {
case 'history':
    $content .= PAYPAL_history(true);
    break;

case 'orderhist':
    // Show all purchases
    if (isset($_POST['upd_orders']) && is_array($_POST['upd_orders'])) {
        USES_paypal_class_order();
        $i = 0;
        foreach ($_POST['upd_orders'] as $order_id) {
            if (!isset($_POST['newstatus'][$order_id]) ||
                !isset($_POST['oldstatus'][$order_id]) ||
                $_POST['newstatus'][$order_id] == $_POST['oldstatus'][$order_id]) {
                continue;
            }
            $ord = new ppOrder($order_id);
            $ord->UpdateStatus($_POST['newstatus'][$order_id]);
            $i++;
        }
        $msg[] = sprintf($LANG_PP['updated_x_orders'], $i);
    }
    $uid = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : 0;
    $content .= PAYPAL_orders(true, $uid);
    break;

case 'itemhist':
    $content .= PAYPAL_itemhist($actionval);
    break;
    
case 'order':
    USES_paypal_class_order();
    $order = new ppOrder($actionval);
    $content .= $order->View(true);
    break;

case 'ipnlog':
    $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : 'all';
    $log_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $txn_id = isset($_REQUEST['txn_id']) ? 
                    COM_applyFilter($_REQUEST['txn_id']) : '';
    switch ($op) {
    case 'single':
        $content .= PAYPAL_ipnlogSingle($log_id, $txn_id);
        break;
    default:
        $content .= PAYPAL_adminlist_IPNLog();
        break;
    }
    break;

case 'editproduct':
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $P = new Product($id);
    if ($id == 0 && isset($_POST['short_description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $P->SetVars($_POST);
    }
    $content .= $P->showForm();
    break;

case 'editcat':
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    USES_paypal_class_category();
    $C = new Category($id);
    if ($id == 0 && isset($_POST['description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $C->SetVars($_POST);
    }
    $content .= $C->showForm();
    break;

case 'catlist':
    $content .= PAYPAL_adminlist_Category();
    break;

case 'attributes':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        USES_paypal_class_attribute();
        foreach ($_POST['delitem'] as $attr_id) {
            Attribute::Delete($attr_id);
        }
    }
    $content .= PAYPAL_adminlist_Attributes();
    break;

case 'editattr':
    USES_paypal_class_attribute();
    $attr_id = isset($_REQUEST['attr_id']) ? $_REQUEST['attr_id'] : '';
    $Attr = new Attribute($attr_id);
    $content .= $Attr->Edit();
    break;

case 'other':
    $content .= '<a href="' . PAYPAL_ADMIN_URL . 
            '/index.php?resetbuttons=x' . '">Reset All Buttons</a>' . "\n";
    break;

case 'gwadmin':
    $content .= PAYPAL_adminList_Gateway();
    break;

case 'gwedit':
    // Load all installed gateways, not just enabled ones
    PAYPAL_loadGateways(true);
    $gwid = isset($_GET['gw_id']) ? $_GET['gw_id'] : '';
    if (DB_count($_TABLES['paypal.gateways'], 'id', $gwid) > 0) {
        $gw = new $gwid();
        $content .= $gw->Configure();
    }
    break;

case 'wfadmin':
    $content .= PAYPAL_adminlist_Workflow();
    $content .= PAYPAL_adminlist_OrderStatus();
    break;

default:
    $view = 'productlist';
    $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    $content .= PAYPAL_adminlist_Product($cat_id);
    break;
}

$display = COM_siteHeader();
$display .= PAYPAL_adminMenu($view);
if (!empty($msg)) {
    $messages = implode('<br />', $msg);
    /*$display .= COM_startBlock('Message');
    $display .= $messages;
    $display .= COM_endBlock();*/
    $display .= COM_showMessageText($messages);
}

$display .= $content;
$display .= COM_siteFooter();
echo $display;
exit;

/**
*  Product Admin List View.
*
*   @param  integer $cat_id     Optional category ID to limit listing
*   @return string      HTML for the product list.
*/
function PAYPAL_adminlist_Product($cat_id=0)
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN;

    $sql = "SELECT 
                p.id, p.name, p.short_description, p.description, p.price, 
                p.prod_type, p.enabled, p.featured, 
                c.cat_id, c.cat_name
            FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON p.cat_id = c.cat_id";

    $header_arr = array(
        array('text' => 'ID', 
                'field' => 'id', 'sort' => true),
        array('text' => $LANG_ADMIN['edit'], 
                'field' => 'edit', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_ADMIN['copy'],
                'field' => 'copy', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['enabled'], 
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['featured'],
                'field' => 'featured', 'sort' => true,
                'align' => 'center'),
        array('text' => $LANG_PP['product'], 
                'field' => 'name', 'sort' => true),
        array('text' => $LANG_PP['description'],
                'field' => 'short_description', 'sort' => true),
        array('text' => $LANG_PP['category'],
                'field' => 'cat_name', 'sort' => true),
        array('text' => $LANG_PP['price'],
                'field' => 'price', 'sort' => true, 'align' => 'right'),
        array('text' => $LANG_PP['prod_type'],
                'field' => 'prod_type', 'sort' => true),
        array('text' => $LANG_ADMIN['delete'],
                'field' => 'delete', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'id',
            'direction' => 'asc');

    $display .= COM_startBlock('', '', 
                    COM_getBlockTemplate('_admin_block', 'header'));

    if ($cat_id > 0) {
        $def_filter = "WHERE c.cat_id='$cat_id'";
    } else {
        $def_filter = 'WHERE 1=1';
    }
    $query_arr = array('table' => 'paypal.products',
        'sql' => $sql,
        'query_fields' => array('p.name', 'p.short_description', 
                            'p.description', 'c.cat_name'),
        'default_filter' => $def_filter,
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_Product',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the history screen.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_Product($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $is_uikit;

    $retval = '';

    switch($fieldname) {
    case 'copy':
        if ($is_uikit) {
            $retval .= COM_createLink('',
                PAYPAL_ADMIN_URL . "/index.php?dup_product=x&amp;id={$A['id']}",
                array('class' => 'uk-icon-clone')
            );
        } else {
            $retval .= COM_createLink(
                $icon_arr['copy'],
                PAYPAL_ADMIN_URL . "/index.php?dup_product=x&amp;id={$A['id']}"
            );
        }
        break;

    case 'edit':
        if ($is_uikit) {
            $retval .= COM_createLink('',
                PAYPAL_ADMIN_URL . "/index.php?editproduct=x&amp;id={$A['id']}",
                array('class' => 'uk-icon-edit')
            );
        } else {
            $retval .= COM_createLink(
                $icon_arr['edit'],
                PAYPAL_ADMIN_URL . "/index.php?editproduct=x&amp;id={$A['id']}"
            );
        }
        break;

    case 'delete':
        if (!Product::isUsed($A['id'])) {
            if ($is_uikit) {
                $retval .= COM_createLink('',
                    PAYPAL_ADMIN_URL. '/index.php?deleteproduct=x&amp;id=' . $A['id'],
                    array('class' => 'uk-icon-trash-o pp-icon-danger',
                    'onclick'=>'return confirm(\'Do you really want to delete this item?\');',
                    'title' => 'Delete this item',
                    )
                );
            } else {
                $retval .= COM_createLink(
                    $icon_arr['delete'],
                    PAYPAL_ADMIN_URL. '/index.php?deleteproduct=x&amp;id=' . $A['id'],
                    array('class'=>'gl_mootip',
                    'onclick'=>'return confirm(\'Do you really want to delete this item?\');',
                    'title' => 'Delete this item',
                    )
                );
            }
        } else {
            $retval = '';
        }
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\" 
                id=\"togenabled{$A['id']}\"
                onclick='PP_toggle(this,\"{$A['id']}\",\"enabled\",".
                "\"product\",\"".PAYPAL_ADMIN_URL."\");' />" . LB;
        break;

    case 'featured':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\" 
                id=\"togfeatured{$A['id']}\"
                onclick='PP_toggle(this,\"{$A['id']}\",\"featured\",".
                "\"product\",\"".PAYPAL_ADMIN_URL."\");' />" . LB;
        break;

    case 'name':
        $retval = COM_createLink($fieldvalue, 
            PAYPAL_ADMIN_URL . '/index.php?itemhist=' . $A['id']);
        //        PAYPAL_URL . '/index.php?detail=x&id=' . $A['id']);
        break; 

    case 'prod_type':
        $retval = $LANG_PP['prod_types'][$A['prod_type']];
        break;

    case 'cat_name':
        $retval = COM_createLink($fieldvalue, 
                PAYPAL_ADMIN_URL . '/index.php?cat_id=' . $A['cat_id']);
        break;
    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
*   Get the to-do list to display at the top of the admin screen.
*   There's probably a less sql-expensive way to do this.
*
*   @return array   Array of strings (the to-do list)
*/
function PAYPAL_adminTodo()
{
    global $_TABLES, $LANG_PP;

    $todo = array();
    if (DB_count($_TABLES['paypal.products']) == 0) 
        $todo[] = $LANG_PP['todo_noproducts'];

    if (DB_count($_TABLES['paypal.gateways'], 'enabled', 1) == 0)
        $todo[] = $LANG_PP['todo_nogateways'];

    return $todo;
}

/**
*   Create the administrator menu
*
*   @param  string  $view   View being shown, so set the help text
*   @return string      Administrator menu
*/
function PAYPAL_adminMenu($view='')
{
    global $_CONF, $LANG_ADMIN, $LANG_PP, $_PP_CONF;

    $menu_arr[] = array('url'  => $_CONF['site_admin_url'],
                  'text' => $LANG_ADMIN['admin_home']);
    if (isset($LANG_PP['admin_hdr_' . $view]) && 
        !empty($LANG_PP['admin_hdr_' . $view])) {
        $hdr_txt = $LANG_PP['admin_hdr_' . $view];
    } else {
        $hdr_txt = $LANG_PP['admin_hdr'];
    }

    if ($view == 'catlist') {
        $menu_arr[] = array(
                    'url'  => PAYPAL_ADMIN_URL . '/index.php?editcat=x',
                    'text' => '<span class="ppNewAdminItem">' .
                            $LANG_PP['new_category'], '</span>');
    } else {
        $menu_arr[] = array(
                    'url'  => PAYPAL_ADMIN_URL . '/index.php?catlist=x',
                    'text' => $LANG_PP['category_list']);
    }

    if ($view == 'productlist') {
        $menu_arr[] = array(
                    'url'  => PAYPAL_ADMIN_URL . '/index.php?editproduct=x',
                    'text' => '<span class="ppNewAdminItem">' .
                            $LANG_PP['new_product']. '</span>');
    } else {
        $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php',
                    'text' => $LANG_PP['product_list']);
    }

    if ($view == 'attributes') {
        $menu_arr[] = array(
                    'url'  => PAYPAL_ADMIN_URL . '/index.php?editattr=x',
                    'text' => '<span class="ppNewAdminItem">' .
                            $LANG_PP['new_attr'] . '</span>');
    } else {
        $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . 
                            '/index.php?attributes=x',
                    'text' => $LANG_PP['attr_list']);
    }


    $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?orderhist=x',
                    'text' => $LANG_PP['purchase_history']);
    //$menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?history=x',
    //                'text' => $LANG_PP['purchase_history']);
    $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?ipnlog=x',
                    'text' => $LANG_PP['ipnlog']);
    $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?gwadmin=x',
                    'text' => $LANG_PP['gateways']);
    $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?wfadmin=x',
                    'text' => $LANG_PP['workflows']);
    $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?other=x',
                    'text' => $LANG_PP['other_func']);
    $menu_arr[] = array('url'  => PAYPAL_URL . '/index.php',
                    'text' => $LANG_PP['storefront']);

    $T = new Template(PAYPAL_PI_PATH . '/templates');
    $T->set_file('title', 'paypal_title.thtml');
    $T->set_var('title', 
        $LANG_PP['admin_title'] . ' (Ver. ' . $_PP_CONF['pi_version'] . ')');
    $todo_arr = PAYPAL_adminTodo();
    foreach ($todo_arr as $item_todo) {
        $todo_list .= "<li>$item_todo</li>" . LB;
    }
    if (!empty($todo_list)) {
        $T->set_var('todo', '<ul>' . $todo_list . '</ul>');
    }
    $retval = $T->parse('', 'title');
    $retval .= ADMIN_createMenu($menu_arr, $hdr_txt, 
            plugin_geticon_paypal());

    return $retval;

}


/**
 * Displays the list of ipn history from the log stored in the database
 *
 * @return string HTML string containing the contents of the ipnlog
 */
function PAYPAL_adminlist_IPNLog()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN;

    $display = '';
    $sql = "SELECT * FROM {$_TABLES['paypal.ipnlog']} ";

    $header_arr = array(
        array('text' => 'ID', 
                'field' => 'id', 'sort' => true),
        array('text' => $LANG_PP['ip_addr'], 
                'field' => 'ip_addr', 'sort' => false),
        array('text' => $LANG_PP['datetime'], 
                'field' => 'time', 'sort' => true),
        array('text' => $LANG_PP['verified'],
                'field' => 'verified', 'sort' => true),
        array('text' => $LANG_PP['txn_id'],
                'field' => 'txn_id', 'sort' => true),
        array('text' => $LANG_PP['pmt_status'],
                'field' => 'pmt_status', 'sort' => true),
        array('text' => $LANG_PP['gateway'],
                'field' => 'gateway', 'sort' => true),
    );

    $defsort_arr = array('field' => 'time',
            'direction' => 'desc');

    $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.ipnlog',
        'sql' => $sql,
        'query_fields' => array('ip_addr', 'txn_id'),
        'default_filter' => 'WHERE 1=1',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php?ipnlog=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_IPNLog',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the IPN Log screen.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_IPNLog($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_TABLES;
    
    $retval = '';

    $ipn_data = unserialize($A['ipn_data']);

    switch($fieldname) {
    case 'id':
        $retval = COM_createLink($fieldvalue, 
                PAYPAL_ADMIN_URL . 
                '/index.php?ipnlog=x&amp;op=single&amp;id=' . $A['id']);
        break; 

    case 'verified':
        $retval = $fieldvalue > 0 ? 'True' : 'False';
        break;

    case 'purchaser':
        $name = DB_getItem($_TABLES['users'], 'username', 
                            "uid=" . (int)$ipn_data['custom']);
        $retval = COM_createLink($name, 
                $_CONF['site_url'] . 
                '/users.php?mode=profile&amp;uid=' . $ipn_data['custom']);
        break;

    case 'pmt_status':
        $retval = htmlspecialchars($ipn_data['payment_status'], ENT_QUOTES, COM_getEncodingt());
        break;

    case 'txn_id':
        $retval = COM_createLink($fieldvalue,
                PAYPAL_ADMIN_URL .
                '/index.php?ipnlog=x&amp;op=single&amp;txn_id=' . $fieldvalue);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
*   Category Admin List View.
*
*   @return string      HTML for the category listing
*/
function PAYPAL_adminlist_Category()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN;

    // Actually used by PAYPAL_getAdminField_Category()
    USES_paypal_class_category();
 
    $sql = "SELECT 
                cat.cat_id, cat.cat_name, cat.description, cat.enabled,
                cat.grp_access, parent.cat_name as pcat
            FROM {$_TABLES['paypal.categories']} cat
            LEFT JOIN {$_TABLES['paypal.categories']} parent
            ON cat.parent_id = parent.cat_id";

    $header_arr = array(
        array('text' => 'ID', 
                'field' => 'cat_id', 'sort' => true),
        array('text' => $LANG_ADMIN['edit'], 
                'field' => 'edit', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['enabled'], 
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['category'], 
                'field' => 'cat_name', 'sort' => true),
        array('text' => $LANG_PP['description'],
                'field' => 'description', 'sort' => false),
        array('text' => $LANG_PP['parent_cat'],
                'field' => 'pcat', 'sort' => true),
        array('text' => $LANG_PP['visible_to'],
                'field' => 'grp_access', 'sort' => false),
        array('text' => $LANG_ADMIN['delete'],
                'field' => 'delete', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'cat_id',
            'direction' => 'asc');

    $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.categories',
        'sql' => $sql,
        'query_fields' => array('cat.name', 'cat.description'),
        'default_filter' => 'WHERE 1=1',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_Category',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the category admin list.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_Category($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_TABLES, $is_uikit;

    $retval = '';
    static $grp_names = array();

    switch($fieldname) {
    case 'edit':
        if ($is_uikit) {
            $retval .= COM_createLink('',
                PAYPAL_ADMIN_URL . "/index.php?editcat=x&amp;id={$A['cat_id']}",
                array('class' => 'uk-icon-edit')
            );
        } else {
            $retval .= COM_createLink(
                $icon_arr['edit'],
                PAYPAL_ADMIN_URL . "/index.php?editcat=x&amp;id={$A['cat_id']}"
            );
        }
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
            $switch = ' checked="checked"';
            $enabled = 1;
        } else {
            $switch = '';
            $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\" 
                id=\"togenabled{$A['cat_id']}\"
                onclick='PP_toggle(this,\"{$A['cat_id']}\",\"enabled\",".
                "\"category\",\"".PAYPAL_ADMIN_URL."\");' />" . LB;
        break;

    case 'grp_access':
        $fieldvalue = (int)$fieldvalue;
        if (!isset($grp_names[$fieldvalue])) {
            $grp_names[$fieldvalue] = DB_getItem($_TABLES['groups'], 'grp_name',
                        "grp_id = $fieldvalue");
        }
        $retval = $grp_names[$fieldvalue];
        break;

    case 'delete':
        if (!Category::isUsed($A['cat_id'])) {
            if ($is_uikit) {
                $retval .= COM_createLink('',
                    PAYPAL_ADMIN_URL. '/index.php?deletecat=x&amp;cat_id=' . $A['cat_id'],
                    array('class'=>'uk-icon-trash-o pp-icon-danger',
                    'onclick'=>'return confirm(\'Do you really want to delete this item?\');',
                    'title' => 'Delete this item',
                    'data-uk-tooltip' => ''
                    )
                );
            } else {
                $retval .= COM_createLink(
                    $icon_arr['delete'],
                    PAYPAL_ADMIN_URL. '/index.php?deletecat=x&amp;cat_id=' . $A['cat_id'],
                    array('class'=>'gl_mootip',
                    'onclick'=>'return confirm(\'Do you really want to delete this item?\');',
                    'title' => 'Delete this item',
                    )
                );
            }
        }
        break;

    case 'description':
        $retval = strip_tags($fieldvalue);
        if (utf8_strlen($retval) > 80) {
            $retval = substr($retval, 0, 80 ) . '...';
        }
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
*   Displays the list of product attributes.
*
*   @return string  HTML string containing the contents of the ipnlog
*/
function PAYPAL_adminlist_Attributes()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN, $_SYSTEM;

    $sql = "SELECT a.*, p.name AS prod_name
            FROM {$_TABLES['paypal.prod_attr']} a
            LEFT JOIN {$_TABLES['paypal.products']} p
            ON a.item_id = p.id 
            WHERE 1=1 ";

    if (isset($_POST['product_id']) && $_POST['product_id'] != '0') {
        $sel_prod_id = (int)$_POST['product_id'];
        $sql .= "AND p.id = '$sel_prod_id' ";
    } else {
        $sel_prod_id = '';
    }

    $header_arr = array(
        array('text' => 'ID', 
                'field' => 'attr_id', 'sort' => true),
        array('text' => $LANG_PP['edit'],
                'field' => 'edit', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['enabled'],
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['product'],
                'field' => 'prod_name', 'sort' => true),
        array('text' => $LANG_PP['attr_name'], 
                'field' => 'attr_name', 'sort' => true),
        array('text' => $LANG_PP['attr_value'],
                'field' => 'attr_value', 'sort' => true),
        array('text' => $LANG_PP['order'],
                'field' => 'orderby', 'sort' => true),
        array('text' => $LANG_PP['attr_price'],
                'field' => 'attr_price', 'sort' => true),
        array('text' => $LANG_ADMIN['delete'],
                'field' => 'delete', 'sort' => 'false',
                'align' => 'center'),
    );

    $defsort_arr = array(
            'field' => 'prod_name,attr_name,orderby',
            'direction' => 'ASC');

    $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));

    $product_selection = COM_optionList($_TABLES['paypal.products'], 'id, name', $sel_prod_id);
    $filter = "{$LANG_PP['product']}: <select name=\"product_id\"
        onchange=\"this.form.submit();\">
        <option value=\"0\">-- Any --</option>\n" .
        $product_selection .
        "</select>&nbsp;\n";

    $query_arr = array('table' => 'paypal.prod_attr',
        'sql' => $sql,
        'query_fields' => array('p.name', 'attr_name', 'attr_value'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php?attributes=x',
    );

    $options = array('chkdelete' => true, 'chkfield' => 'attr_id');

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_Attribute',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, '');

    // Create the "copy attributes" form at the bottom
    $T = new Template(PAYPAL_PI_PATH . '/templates');
    $T->set_file('copy_attr_form', 'copy_attributes_form.thtml');
    $T->set_var(array(
        'src_product'       => $product_selection,
        'product_select'    => COM_optionList($_TABLES['paypal.products'], 'id, name'),
        'cat_select'        => COM_optionList($_TABLES['paypal.categories'], 'cat_id,cat_name'),
        'uikit'     => $_SYSTEM['framework'] == 'uikit' ? 'true' : '',
    ) );
    $display .= $T->parse('output', 'copy_attr_form');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the options admin list.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_Attribute($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $is_uikit;
   
    $retval = '';

    switch($fieldname) {
    case 'edit':
        if ($is_uikit) {
            $retval .= COM_createLink('',
                "/index.php?editattr=x&amp;attr_id={$A['attr_id']}",
                array('class' => 'uk-icon-edit')
            );
        } else {
            $retval .=  COM_createLink(
                $icon_arr['edit'],
                PAYPAL_ADMIN_URL . 
                    "/index.php?editattr=x&amp;attr_id={$A['attr_id']}"
            );
        }
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\" 
                id=\"togenabled{$A['attr_id']}\"
                onclick='PP_toggle(this,\"{$A['attr_id']}\",\"enabled\",".
                "\"attribute\",\"".PAYPAL_ADMIN_URL."\");' />" . LB;
        break;

    case 'delete':
        if ($is_uikit) {
            $retval .= COM_createLink('',
                PAYPAL_ADMIN_URL. '/index.php?deleteopt=x&amp;attr_id=' . $A['attr_id'],
                array('class'=>'uk-icon-trash-o pp-icon-danger',
                    'onclick'=>'return confirm(\'Do you really want to delete this item?\');',
                    'title' => 'Delete this item',
                    'data-uk-tooltip' => '',
                )
            );
        } else {
            $retval .= COM_createLink(
                $icon_arr['delete'],
                PAYPAL_ADMIN_URL. '/index.php?deleteopt=x&amp;attr_id=' . $A['attr_id'],
                array('class'=>'gl_mootip',
                'onclick'=>'return confirm(\'Do you really want to delete this item?\');',
                'title' => 'Delete this item',
                )
            );
        }
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
*   Payment Gateway Admin View.
*
*   @return string      HTML for the gateway listing
*/
function PAYPAL_adminList_Gateway()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN,
            $LANG32;

    $sql = "SELECT * FROM {$_TABLES['paypal.gateways']}";
    $res = DB_query($sql);
    $installed = array();
    while ($A = DB_fetchArray($res, false)) {
        $installed[$A['id']] = 1;
    }

    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'], 
                'field' => 'edit', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['orderby'], 
                'field' => 'orderby', 'sort' => false,
                'align' => 'center'),
        array('text' => 'ID', 
                'field' => 'id', 'sort' => true),
        array('text' => $LANG_PP['description'],
                'field' => 'description', 'sort' => true),
        array('text' => $LANG_PP['enabled'], 
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_ADMIN['delete'],
                'field' => 'delete', 'sort' => 'false',
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'orderby',
            'direction' => 'asc');

    $display = COM_startBlock('', '', 
                    COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.gateways',
        'sql' => $sql,
        'query_fields' => array('id', 'description'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php?gwadmin=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_Gateway',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $results = glob(PAYPAL_PI_PATH . '/classes/gateways/*.class.php');
    $ins_gw = '';
    if (is_array($results)) {
        foreach ($results as $fullpath) {
            $parts = explode('/', $fullpath);
            list($class,$x1,$x2) = explode('.', $parts[count($parts)-1]);
            if (!array_key_exists($class, $installed)) {
                $ins_gw .= $class . '&nbsp;&nbsp;<a href="' . 
                    PAYPAL_ADMIN_URL . '/index.php?gwinstall=x&gwname=' . 
                    urlencode($class) . '">' . $LANG32[22] . '</a><br />' . LB;
            }
        }
    }
    if (!empty($ins_gw)) {
        $display .= $LANG_PP['gw_notinstalled'] . '<br />' . LB . $ins_gw;
    }
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the options admin list.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_Gateway($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $is_uikit;
   
    $retval = '';

    switch($fieldname) {
    case 'edit':
        if ($is_uikit) {
            $retval .= COM_createLink('',
                PAYPAL_ADMIN_URL . 
                    "/index.php?gwedit=x&amp;gw_id={$A['id']}",
                array('class' => 'uk-icon-edit')
            );
        } else {
            $retval .= COM_createLink($icon_arr['edit'],
                PAYPAL_ADMIN_URL . 
                    "/index.php?gwedit=x&amp;gw_id={$A['id']}"
            );
        }
        break;

    case 'enabled':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\" 
                id=\"togenabled{$A['id']}\"
                onclick='PP_toggle(this,\"{$A['id']}\",\"{$fieldname}\",".
                "\"gateway\",\"".PAYPAL_ADMIN_URL."\");' />" . LB;
        break;

    case 'orderby':
        $retval = COM_createLink(
                '<img src="' . PAYPAL_URL . 
                '/images/up.png" height="16" width="16" border="0" />',
                PAYPAL_ADMIN_URL . '/index.php?gwmove=up&id=' . $A['id']
            ) .
            COM_createLink(
                '<img src="' . PAYPAL_URL . 
                    '/images/down.png" height="16" width="16" border="0" />',
                PAYPAL_ADMIN_URL . '/index.php?gwmove=down&id=' . $A['id']
            );
        break;

    case 'delete':
        if ($is_uikit) {
            $retval = COM_createLink('',
                PAYPAL_ADMIN_URL. '/index.php?gwdelete=x&amp;id=' . $A['id'],
                array('onclick'=>'return confirm(\'' . $LANG_PP['q_del_item'] . '\');',
                    'class' => 'uk-icon-trash-o pp-icon-danger'
                )
            );
        } else {
            $retval = COM_createLink(
                $icon_arr['delete'],
                PAYPAL_ADMIN_URL. '/index.php?gwdelete=x&amp;id=' . $A['id'],
                array('onclick'=>'return confirm(\'' . $LANG_PP['q_del_item'] . '\');',
                )
            );
        }
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


/**
*  Workflow Admin List View.
*
*   @return string      HTML for the product list.
*/
function PAYPAL_adminlist_Workflow()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN;

    $sql = "SELECT id, wf_name, orderby, enabled,
                'workflow' AS rec_type
            FROM {$_TABLES['paypal.workflows']}";

    $header_arr = array(
        array('text' => $LANG_PP['order'],
                'field' => 'orderby', 'sort' => true),
        array('text' => $LANG_PP['name'], 
                'field' => 'wf_name', 'sort' => true),
        array('text' => $LANG_PP['enabled'], 
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'orderby',
            'direction' => 'ASC');

    $display = COM_startBlock('', '', 
                    COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.workflows',
        'sql' => $sql,
        'query_fields' => array('wf_name'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_Workflow',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Order Status Admin List View.
*
*   @return string      HTML for the product list.
*/
function PAYPAL_adminlist_OrderStatus()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN;

    $sql = "SELECT *, 'orderstatus' AS rec_type
            FROM {$_TABLES['paypal.orderstatus']}";

    $header_arr = array(
        array('text' => $LANG_PP['order'],
                'field' => 'orderby', 'sort' => true),
        array('text' => $LANG_PP['name'], 
                'field' => 'name', 'sort' => true),
        array('text' => $LANG_PP['enabled'], 
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['notify'], 
                'field' => 'notify_buyer', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'orderby',
            'direction' => 'ASC');

    $display = COM_startBlock('', '', 
                    COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.orderstatus',
        'sql' => $sql,
        'query_fields' => array('name'),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_createMenu(array(), $LANG_PP['admin_hdr_wfstatus']);

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_Workflow',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the options admin list.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_Workflow($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP;
   
    $retval = '';

    switch($fieldname) {
    case 'enabled':
    case 'notify_buyer':
        if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
        } else {
                $switch = '';
                $enabled = 0;
        }
        $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"{$fieldname}_check\" 
                id=\"tog{$fieldname}{$A['id']}\"
                onclick='PP_toggle(this,\"{$A['id']}\",\"{$fieldname}\",".
                "\"{$A['rec_type']}\",\"".PAYPAL_ADMIN_URL."\");' />" . LB;
        break;

    case 'orderby':
        $url = PAYPAL_ADMIN_URL . 
            "/index.php?id={$A['id']}&amp;type={$A['rec_type']}&amp;wfmove=";
        $retval = COM_createLink(
                '<img src="' . PAYPAL_URL . 
                '/images/up.png" height="16" width="16" border="0" />',
                $url . 'up'
            ) .
            COM_createLink(
                '<img src="' . PAYPAL_URL . 
                    '/images/down.png" height="16" width="16" border="0" />',
                $url . 'down'
            );
        break;

    case 'wf_name':
        $retval = $LANG_PP[$fieldvalue];
        break;

    case 'name':
        $retval = $LANG_PP['orderstatus'][$fieldvalue];
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


function PAYPAL_itemhist($item_id = 0)
{
    global $_TABLES, $LANG_PP;

    $Item = new Product($item_id);
    if ($Item->isNew) {
        COM_404();
    }

    $sql = "SELECT *, sum(quantity) as qty FROM {$_TABLES['paypal.purchases']}";
    if ($item_id > 0) {
        $sql .= ' WHERE product_id = ' . $Item->id;
    }
    $sql .= " GROUP BY order_id";

    $header_arr = array(
        array('text' => $LANG_PP['purch_date'],
                'field' => 'purchase_date', 'sort' => true),
        array('text' => $LANG_PP['quantity'],
                'field' => 'qty', 'sort' => false),
        array('text' => $LANG_PP['order'],
                'field' => 'order_id', 'sort' => true),
        array('text' => $LANG_PP['name'], 
                'field' => 'user_id', 'sort' => true),
    );

    $defsort_arr = array('field' => 'purchase_date',
            'direction' => 'ASC');

    $display = COM_startBlock('', '', 
                    COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.orderstatus',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php?itemhist=' . $Item->id,
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_createMenu(array(), $LANG_PP['item_history'] . ': ' . $Item->short_description);

    $display .= ADMIN_list('paypal', 'PAYPAL_getAdminField_itemhist',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Get an individual field for the item purchase history
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function PAYPAL_getAdminField_itemhist($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP;
   
    $retval = '';
    static $username = array();

    switch($fieldname) {
    case 'user_id':
        if (!isset($username[$fieldvalue])) {
            $username[$fieldvalue] = COM_getDisplayName($fieldvalue);
        }
        $retval = COM_createLink($username[$fieldvalue],
            PAYPAL_ADMIN_URL . '/index.php?orderhist=x&uid=' . $fieldvalue);
        break;

    case 'order_id':
        $retval = COM_createLink($fieldvalue,
            PAYPAL_ADMIN_URL . '/index.php?order=' . $fieldvalue);
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


?>

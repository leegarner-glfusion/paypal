<?php
/**
*   Admin index page for the paypal plugin.
*   By default, lists products available for editing.
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

/** Import Required glFusion libraries */
require_once('../../../lib-common.php');

// If plugin is installed but not enabled, display an error and exit gracefully
if (!isset($_PP_CONF) || !in_array($_PP_CONF['pi_name'], $_PLUGINS)) {
    COM_404();
}

require_once('../../auth.inc.php');

// Check for required permissions
PAYPAL_access_check('paypal.admin');

USES_paypal_functions();
USES_lib_admin();

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
    'dup_product', 'runreport', 'configreport', 'sendcards', 'purgecache',
    'deldiscount', 'savediscount',
    // Views to display
    'history', 'orderhist', 'ipnlog', 'editproduct', 'editcat', 'catlist',
    'attributes', 'editattr', 'other', 'productlist', 'gwadmin', 'gwedit',
    'wfadmin', 'order', 'itemhist', 'reports', 'coupons', 'sendcards_form',
    'sales', 'editdiscount',
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
$view = 'productlist';

switch ($action) {
case 'dup_product':
    $P = new \Paypal\Product($_REQUEST['id']);
    $P->Duplicate();
    echo COM_refresh(PAYPAL_ADMIN_URL.'/index.php');
    break;

case 'deleteproduct':
    $P = \Paypal\Product::getInstance($_REQUEST['id']);
    if (!\Paypal\Product::isUsed($_REQUEST['id'])) {
        $P->Delete();
    } else {
        COM_setMsg(sprintf($LANG_PP['no_del_item'], $P->name), 'error');
    }
    echo COM_refresh(PAYPAL_ADMIN_URL);
    break;

case 'deletecatimage':
    $id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    if ($id > 0) {
        $C = new \Paypal\Category($id);
        $C->deleteImage();
        $view = 'editcat';
        $_REQUEST['id'] = $id;
    } else {
        $view = 'catlist';
    }
    break;

case 'deletecat':
    $C = \Paypal\Category::getInstance($_REQUEST['cat_id']);
    if ($C->parent_id == 0) {
        COM_setMsg($LANG_PP['dscp_root_cat'], 'error');
    } elseif (\Paypal\Category::isUsed($_REQUEST['cat_id'])) {
        COM_setMsg(sprintf($LANG_PP['no_del_cat'], $C->cat_name), 'error');
    } else {
        $C->Delete();
    }
    echo COM_refresh(PAYPAL_ADMIN_URL . '/index.php?catlist');
    break;

case 'delete_img':
    $img_id = (int)$_REQUEST['img_id'];
    \Paypal\Product::deleteImage($img_id);
    $view = 'editproduct';
    break;

case 'saveproduct':
    $P = new \Paypal\Product($_POST['id']);
    if (!$P->Save($_POST)) {
        $content .= \Paypal\PAYPAL_errMsg($P->PrintErrors());
        $view = 'editproduct';
    }
    break;

case 'savecat':
    $C = new \Paypal\Category($_POST['cat_id']);
    if (!$C->Save($_POST)) {
        $content .= PAYPAL_popupMsg($C->PrintErrors());
        $view = 'editcat';
    } else {
        $view = 'catlist';
    }
    break;

case 'saveopt':
    $Attr = new \Paypal\Attribute($_POST['attr_id']);
    if (!$Attr->Save($_POST)) {
        $content .= PAYPAL_popupMsg($LANG_PP['invalid_form']);
    }
    if (isset($_POST['attr_id']) && !empty($_POST['attr_id'])) {
        // Updating an existing option, return to the list
        COM_refresh(PAYPAL_ADMIN_URL . '/index.php?attributes=x');
    } else {
        COM_refresh(PAYPAL_ADMIN_URL . '/index.php?editattr=x&item_id=' . $_POST['item_id']);
    }
    break;

case 'deleteopt':
    // attr_id could be via $_GET or $_POST
    $Attr = new \Paypal\Attribute($_REQUEST['attr_id']);
    $Attr->Delete();
    $view = 'attributes';
    break;

case 'resetbuttons':
    DB_query("TRUNCATE {$_TABLES['paypal.buttons']}");
    $view = 'other';
    break;

case 'gwinstall':
    $gwname = $_GET['gwname'];
    $gw = \Paypal\Gateway::getInstance($gwname);
    if ($gw !== NULL) {
        if ($gw->Install()) {
            $msg[] = "Gateway \"$gwname\" installed successfully";
        } else {
            $msg[] = "Failed to install the \"$gwname\" gateway";
        }
    }
    $view = 'gwadmin';
    break;

case 'gwdelete':
    $gw = \Paypal\Gateway::getInstance($_GET['id']);
    if ($gw !== NULL) {
        $status = $gw->Remove();
    }
    $view = 'gwadmin';
    break;

case 'gwsave':
    // Save a payment gateway configuration
    $gw = \Paypal\Gateway::getInstance($_POST['gw_id']);
    if ($gw !== NULL) {
        $status = $gw->SaveConfig($_POST);
    }
    $view = 'gwadmin';
    break;

case 'gwmove':
    \Paypal\Gateway::moveRow($_GET['id'], $actionval);
    $view = 'gwadmin';
    break;

case 'wfmove':
    switch ($_GET['type']) {
    case 'workflow':
        \Paypal\Workflow::moveRow($_GET['id'], $actionval);
        break;
    case 'orderstatus':
        \Paypal\OrderStatus::moveRow($_GET['id'], $actionval);
        break;
    }
    $view = 'wfadmin';
    break;

case 'attrcopy':
    // Copy attributes from a product to another product or category
    $src_prod = (int)$_POST['src_prod'];
    $dest_prod = (int)$_POST['dest_prod'];
    $dest_cat = (int)$_POST['dest_cat'];
    $del_existing = isset($_POST['del_existing_attr']) ? true : false;
    $done_prods = array();

    // Nothing to do if no source product selected
    if ($src_prod < 1) break;

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
                $done_prods[] = $dest_prod;     // track for later
                if ($del_existing) {
                    DB_delete($_TABLES['paypal.prod_attr'], 'item_id', $dest_prod);
                }
                $sql = "INSERT IGNORE INTO {$_TABLES['paypal.prod_attr']}
                SELECT NULL, $dest_prod, attr_name, attr_value, orderby, attr_price, enabled
                FROM {$_TABLES['paypal.prod_attr']}
                WHERE item_id = $src_prod";
                DB_query($sql);
            }
        }
    }

    // If a target product was selected, it's not the same as the source, and hasn't
    // already been done as part of the category, then update the target product also.
    if ($dest_prod > 0 && $dest_prod != $src_prod && !in_array($dest_prod, $done_prods)) {
        if ($del_existing) {
            DB_delete($_TABLES['paypal.prod_attr'], 'item_id', $dest_prod);
        }
        $sql = "INSERT IGNORE INTO {$_TABLES['paypal.prod_attr']}
            SELECT NULL, $dest_prod, attr_name, attr_value, orderby, attr_price, enabled
            FROM {$_TABLES['paypal.prod_attr']}
            WHERE item_id = $src_prod";
        DB_query($sql);
    }
    echo COM_refresh(PAYPAL_ADMIN_URL . '/index.php?attributes=x');
    break;

case 'runreport':
    $reportname = isset($_POST['reportname']) ? $_POST['reportname'] : '';
    if (USES_paypal_class_Report($reportname)) {
        $R = new $reportname();
        $content .= $R->Render();
        exit;
    }
    break;

case 'sendcards':
    $amt = PP_getVar($_POST, 'amount', 'float');
    $uids = PP_getVar($_POST, 'groupmembers', 'string');
    $gid = PP_getVar($_POST, 'group_id', 'int');
    $exp = PP_getVar($_POST, 'expires', 'string');
    if (!empty($uids)) {
        $uids = explode('|', $uids);
    } else {
        $uids = array();
    }
    if ($gid > 0) {
        $sql = "SELECT ug_uid FROM {$_TABLES['group_assignments']}
                WHERE ug_main_grp_id = $gid AND ug_uid > 1";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $uids[] = $A['ug_uid'];
        }
    }
    $errs = array();
    if ($amt < .01) $errs[] = $LANG_PP['err_gc_amt'];
    if (empty($uids)) $errs[] = $LANG_PP['err_gc_nousers'];
    if (empty($errs)) {
        foreach ($uids as $uid) {
            $code = \Paypal\Coupon::Purchase($amt, $uid, $exp);
            $email = DB_getItem($_TABLES['users'], 'email', "uid = $uid");
            if (!empty($email)) {
                \Paypal\Coupon::Notify($code, $email, $amt);
            }
        }
        COM_setMsg(count($uids) . ' coupons sent');
    } else {
        $msg = '<ul><li>' . implode('</li><li>', $errs) . '</li></ul>';
        COM_setMsg($msg, 'error', true);
    }
    COM_refresh(PAYPAL_ADMIN_URL . '/index.php?sendcards_form=x');
    break;

case 'purgecache':
    \Paypal\Cache::clear();
    COM_refresh(PAYPAL_ADMIN_URL);
    break;

case 'savediscount':
    $D = new \Paypal\Sales($_POST['id']);
    if (!$D->Save($_POST)) {
        COM_setMsg($LANG_PP['msg_nochange']);
        COM_refresh(PAYPAL_ADMIN_URL . '/index.php?editdiscount&id=' . $D->id);
    } else {
        COM_setMsg($LANG_PP['msg_updated']);
        COM_refresh(PAYPAL_ADMIN_URL . '/index.php?sales');
    }
    exit;
    break;

case 'deldiscount':
    $id = PP_getVar($_GET, 'id', 'integer', 0);
    if ($id > 0) {
        \Paypal\Sales::Delete($id);
    }
    COM_refresh(PAYPAL_ADMIN_URL . '/index.php?sales');
    break;

default:
    $view = $action;
    break;
}

//PAYPAL_debug('Admin view: ' . $action);
switch ($view) {
case 'history':
    $content .= \Paypal\history(true);
    break;

case 'orderhist':
    // Show all purchases
    if (isset($_POST['upd_orders']) && is_array($_POST['upd_orders'])) {
        $i = 0;
        foreach ($_POST['upd_orders'] as $order_id) {
            if (!isset($_POST['newstatus'][$order_id]) ||
                !isset($_POST['oldstatus'][$order_id]) ||
                $_POST['newstatus'][$order_id] == $_POST['oldstatus'][$order_id]) {
                continue;
            }
            $ord = new \Paypal\Order($order_id);
            $ord->updateStatus($_POST['newstatus'][$order_id]);
            $i++;
        }
        $msg[] = sprintf($LANG_PP['updated_x_orders'], $i);
    }
    $uid = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : 0;
    $content .= \Paypal\listOrders(true, $uid);
    break;

case 'coupons':
    $content .= PAYPAL_couponlist();
    break;

case 'itemhist':
    $content .= PAYPAL_itemhist($actionval);
    break;

case 'order':
    $order = new \Paypal\Order($actionval);
    $order->setAdmin(true);
    $content .= $order->View(true);
    break;

case 'ipnlog':
    $op = isset($_REQUEST['op']) ? $_REQUEST['op'] : 'all';
    $log_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $txn_id = isset($_REQUEST['txn_id']) ?
                    COM_applyFilter($_REQUEST['txn_id']) : '';
    switch ($op) {
    case 'single':
        $content .= \Paypal\ipnlogSingle($log_id, $txn_id);
        break;
    default:
        $content .= PAYPAL_adminlist_IPNLog();
        break;
    }
    break;

case 'editproduct':
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $P = new \Paypal\Product($id);
    if ($id == 0 && isset($_POST['short_description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $P->SetVars($_POST);
    }
    $content .= $P->showForm();
    break;

case 'editcat':
    $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
    $C = new \Paypal\Category($id);
    if ($id == 0 && isset($_POST['description'])) {
        // Pick a field.  If it exists, then this is probably a rejected save
        $C->SetVars($_POST);
    }
    $content .= $C->showForm();
    break;

case 'catlist':
    $content .= PAYPAL_adminlist_Category();
    break;

case 'sales':
    $content .= PAYPAL_adminlist_Sales();
    break;

case 'attributes':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        foreach ($_POST['delitem'] as $attr_id) {
            \Paypal\Attribute::Delete($attr_id);
        }
    }
    $content .= PAYPAL_adminlist_Attributes();
    break;

case 'editattr':
    $attr_id = PP_getVar($_GET, 'attr_id');
    $Attr = new \Paypal\Attribute($attr_id);
    $Attr->item_id = PP_getVar($_GET, 'item_id');
    $content .= $Attr->Edit();
    break;

case 'editdiscount':
    $id = PP_getVar($_GET, 'id', 'integer', 0);
    $D = new \Paypal\Sales($id);
    $content .= $D->Edit();
    break;

case 'other':
    $content .= '<div><a href="' . PAYPAL_ADMIN_URL .
            '/index.php?resetbuttons=x' . '">' . $LANG_PP['resetbuttons'] . "</a></div>\n";
    $content .= '<div><a href="' . PAYPAL_ADMIN_URL .
            '/index.php?purgecache=x' . '">' . $LANG_PP['purge_cache'] . "</a></div>\n";
    break;

case 'sendcards_form':
    $T = PP_getTemplate('send_cards', 'cards');
    $sql = "SELECT uid,fullname FROM {$_TABLES['users']}
                WHERE status > 0 AND uid > 1";
    $res = DB_query($sql, 1);
    $included = '';
    $excluded = '';
    if ($_PP_CONF['gc_exp_days'] > 0) {
        $period = 'P' . (int)$_PP_CONF['gc_exp_days'] . 'D';
        $dt = new \Date('now', $_CONF['timezone']);
        $dt->add(new DateInterval($period));
        $expires = $dt->format('Y-m-d');
    } else {
        $expires = '9999-12-31';
    }
    $tmp = array();
    while ($A = DB_fetchArray($res, false)) {
        $excluded .= "<option value=\"{$A['uid']}\">{$A['fullname']}</option>\n";
    }
    $T->set_var(array(
        'excluded' => $excluded,
        'grp_select' => COM_optionList($_TABLES['groups'],
                            'grp_id,grp_name', '', 1),
        'expires' => $expires,
    ) );
    $T->parse('output', 'cards');
    $content = $T->finish($T->get_var('output'));
    break;


case 'gwadmin':
    $content .= PAYPAL_adminList_Gateway();
    break;

case 'gwedit':
    $gw = \Paypal\Gateway::getInstance($_GET['gw_id']);
    if ($gw !== NULL) {
        $content .= $gw->Configure();
    }
    break;

case 'wfadmin':
    $content .= PAYPAL_adminlist_Workflow();
    $content .= PAYPAL_adminlist_OrderStatus();
    break;

case 'reports':
    USES_paypal_reports();
    $content .= PAYPAL_reportsList();
    break;

case 'configreport':
    if (USES_paypal_class_Report($actionval)) {
        $R = new $actionval();
        $content .= $R->showForm();
    }
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
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN, $LANG_PP_HELP;

    $display = '';
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
        array('text' => $LANG_ADMIN['delete'] .
                    '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                    $LANG_PP_HELP['hlp_prod_delete'] . '"></i>',
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
    $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    $filter = $LANG_PP['category'] . ': <select name="cat_id"
            onchange="javascript: document.location.href=\'' .
                PAYPAL_ADMIN_URL .
                '/index.php?view=prodcts&amp;cat_id=\'+' .
                'this.options[this.selectedIndex].value">' .
        '<option value="0">' . $LANG_PP['all'] . '</option>' . LB .
        COM_optionList($_TABLES['paypal.categories'], 'cat_id, cat_name',
                $cat_id, 1) .
        "</select>" . LB;

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_productlist',
            __NAMESPACE__ . '\getAdminField_Product',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', '', '');

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
function getAdminField_Product($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'copy':
        $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                '-clone pp-icon-info tooltip" title="' . $LANG_ADMIN['copy'] . '"></i>',
                PAYPAL_ADMIN_URL . "/index.php?dup_product=x&amp;id={$A['id']}"
        );
        break;

    case 'edit':
        $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
            '-edit pp-icon-info tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
            PAYPAL_ADMIN_URL . "/index.php?editproduct=x&amp;id={$A['id']}"
        );
        break;

    case 'delete':
        if (!\Paypal\Product::isUsed($A['id'])) {
            $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-trash-o pp-icon-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
                    PAYPAL_ADMIN_URL. '/index.php?deleteproduct=x&amp;id=' . $A['id'],
                array(
                    'onclick'=>'return confirm(\'' . $LANG_PP['q_del_item'] . '\');',
                    'title' => $LANG_PP['q_del_item'],
                )
            );
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
                "\"product\");' />" . LB;
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
                "\"product\");' />" . LB;
        break;

    case 'name':
        $retval = COM_createLink($fieldvalue,
            PAYPAL_ADMIN_URL . '/index.php?itemhist=' . $A['id']);
        //        PAYPAL_URL . '/index.php?detail=x&id=' . $A['id']);
        break;

    case 'prod_type':
        if (isset($LANG_PP['prod_types'][$A['prod_type']])) {
            $retval = $LANG_PP['prod_types'][$A['prod_type']];
        } else {
            $retval = '';
        }
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

    if ($view == 'productlist') {
        $menu_arr[] = array(
                    'url'  => PAYPAL_ADMIN_URL . '/index.php?editproduct=x',
                    'text' => '<span class="ppNewAdminItem">' .
                            $LANG_PP['new_product']. '</span>');
    } else {
        $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php',
                    'text' => $LANG_PP['product_list']);
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

    if ($view == 'sales') {
        $menu_arr[] = array(
                    'url'  => PAYPAL_ADMIN_URL . '/index.php?editdiscount=x',
                    'text' => '<span class="ppNewAdminItem">' .
                            $LANG_PP['new_sale'] . '</span>');
    } else {
        $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?sales=x',
                    'text' => $LANG_PP['sale_prices']);
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
    if ($_PP_CONF['gc_enabled']) {
        // Show the Coupons menu option only if enabled
        $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?coupons=x',
                    'text' => $LANG_PP['coupons']);
    }
//    $menu_arr[] = array('url'  => PAYPAL_ADMIN_URL . '/index.php?reports=x',
//                    'text' => $LANG_PP['reports']);

    $T = PP_getTemplate('paypal_title', 'title');
    $T->set_var(array(
        'title' => $LANG_PP['admin_title'] . ' (Ver. ' . $_PP_CONF['pi_version'] . ')',
        'is_admin' => true,
    ) );
    $todo_arr = PAYPAL_adminTodo();
    if (!empty($todo_arr)) {
        $todo_list = '';
        foreach ($todo_arr as $item_todo) {
            $todo_list .= "<li>$item_todo</li>" . LB;
        }
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

    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_ipnlog',
            __NAMESPACE__ . '\getAdminField_IPNLog',
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
function getAdminField_IPNLog($fieldname, $fieldvalue, $A, $icon_arr)
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
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN, $LANG_PP_HELP;

    $display = '';
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
        array('text' => $LANG_ADMIN['delete'] .
                    '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                    $LANG_PP_HELP['hlp_cat_delete'] . '"></i>',
                'field' => 'delete', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'cat_id',
            'direction' => 'asc');

    $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.categories',
        'sql' => $sql,
        'query_fields' => array('cat.cat_name', 'cat.description'),
        'default_filter' => 'WHERE 1=1',
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php?catlist=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_catlist',
            __NAMESPACE__ . '\getAdminField_Category',
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
function getAdminField_Category($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_TABLES, $LANG_ADMIN;

    $retval = '';
    static $grp_names = array();
    static $now = NULL;
    if ($now === NULL) $now = PAYPAL_now()->format('Y-m-d');

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
            '-edit pp-icon-info tooltip" title="' . $LANG_PP['edit'] . '"></i>',
            PAYPAL_ADMIN_URL . "/index.php?editcat=x&amp;id={$A['cat_id']}"
        );
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
                "\"category\");' />" . LB;
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
        if (!\Paypal\Category::isUsed($A['cat_id'])) {
            $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-trash-o pp-icon-danger tooltip"></i>',
                PAYPAL_ADMIN_URL. '/index.php?deletecat=x&amp;cat_id=' . $A['cat_id'],
                array(
                    'onclick'=>"return confirm('{$LANG_PP['q_del_item']}');",
                    'title' => $LANG_ADMIN['delete'],
                    'data-uk-tooltip' => '',
                )
            );
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
                'field' => 'attr_price',
                'align' => 'right',
                'sort' => true,
        ),
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

    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_attrlist',
            __NAMESPACE__ . '\getAdminField_Attribute',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, '');

    // Create the "copy attributes" form at the bottom
    $T = PP_getTemplate('copy_attributes_form', 'copy_attr_form');
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
function getAdminField_Attribute($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-edit pp-icon-info tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
                PAYPAL_ADMIN_URL . "/index.php?editattr=x&amp;attr_id={$A['attr_id']}"
        );
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
                "\"attribute\");' />" . LB;
        break;

    case 'delete':
        $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                '-trash-o pp-icon-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
            PAYPAL_ADMIN_URL. '/index.php?deleteopt=x&amp;attr_id=' . $A['attr_id'],
            array(
                'onclick'=>'return confirm(\'' . $LANG_PP['q_del_item'] . '\');',
                'title' => 'Delete this item',
            )
        );
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
    $to_install = \Paypal\Gateway::getUninstalled();

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

    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_gwlist',
            __NAMESPACE__ . '\getAdminField_Gateway',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    if (!empty($to_install)) {
        $display .= $LANG_PP['gw_notinstalled'] . ':<br />';
        foreach ($to_install as $name=>$info) {
            $display .= $name . '&nbsp;&nbsp;<a href="' .
                    PAYPAL_ADMIN_URL. '/index.php?gwinstall=x&gwname=' .
                    urlencode($name) . '">' . $LANG32[22] . '</a><br />' . LB;
        }
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
function getAdminField_Gateway($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $LANG_ADMIN;

    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval .= COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                '-edit pp-icon-info tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
            PAYPAL_ADMIN_URL .
                "/index.php?gwedit=x&amp;gw_id={$A['id']}"
        );
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
                "\"gateway\");' />" . LB;
        break;

    case 'orderby':
        $retval = COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-arrow-up pp-icon-info"></i>',
                PAYPAL_ADMIN_URL . '/index.php?gwmove=up&id=' . $A['id']
            ) .
            COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-arrow-down pp-icon-info"></i>',
                PAYPAL_ADMIN_URL . '/index.php?gwmove=down&id=' . $A['id']
            );
        break;

    case 'delete':
        $retval = COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                '-trash-o pp-icon-danger tooltip" title="' . $LANG_ADMIN['delete'] . '"></i>',
            PAYPAL_ADMIN_URL. '/index.php?gwdelete=x&amp;id=' . $A['id'],
            array(
                'onclick'=>'return confirm(\'' . $LANG_PP['q_del_item'] . '\');',
            )
        );
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
                'field' => 'orderby', 'sort' => false),
        array('text' => $LANG_PP['name'],
                'field' => 'wf_name', 'sort' => false),
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

    $display .= "<h2>{$LANG_PP['workflows']}</h2>\n";
    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_workflowlist',
            __NAMESPACE__ . '\getAdminField_Workflow',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
*   Sale Pricing Admin List View.
*
*   @return string      HTML for the product list.
*/
function PAYPAL_adminlist_Sales()
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_ADMIN;

    $sql = "SELECT *
            FROM {$_TABLES['paypal.sales']}";

    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'],
                'field' => 'edit', 'align' => 'center',
        ),
        array('text' => $LANG_PP['item_type'],
                'field' => 'item_type', 'sort' => false),
        array('text' => $LANG_PP['name'],
                'field' => 'item_id', 'sort' => false),
        array('text' => $LANG_PP['amount'] . '/' . $LANG_PP['percent'],
                'field' => 'amount', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_PP['start'],
                'field' => 'start', 'sort' => true,
        ),
        array('text' => $LANG_PP['end'],
                'field' => 'end', 'sort' => true,
        ),
        array('text' => $LANG_ADMIN['delete'],
                'field' => 'delete', 'align' => 'center',
        ),
    );

    $defsort_arr = array('field' => 'start',
            'direction' => 'ASC');

    $display = COM_startBlock('', '',
                    COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.sales',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= "<h2>{$LANG_PP['sale_prices']}</h2>\n";
    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_discountlist',
            __NAMESPACE__ . '\getAdminField_Sales',
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
                'field' => 'orderby', 'sort' => false),
        array('text' => $LANG_PP['name'],
                'field' => 'name', 'sort' => false),
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

    //$display .= ADMIN_createMenu(array(), $LANG_PP['admin_hdr_wfstatus']);

    $display .= "<h2>{$LANG_PP['statuses']}</h2>\n";
    $display .= $LANG_PP['admin_hdr_wfstatus'] . "\n";
    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_statuslist',
            __NAMESPACE__ . '\getAdminField_Workflow',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
*   Get an individual field for the Sales admin list.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function getAdminField_Sales($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $LANG_ADMIN;
    static $Cur = NULL;
    static $Dt = NULL;
    if ($Cur === NULL) $Cur = \Paypal\Currency::getInstance();
    if ($Dt === NULL) $Dt = new Date('now', $_CONF['timezone']);
    $retval = '';

    switch($fieldname) {
    case 'edit':
        $retval = COM_createLink('<i class="' . PP_getIcon('edit') . '"></i>',
                PAYPAL_ADMIN_URL . '/index.php?editdiscount&id=' . $A['id']
        );
        break;

    case 'delete':
        $retval = COM_createLink('<i class="' . PP_getIcon('trash-o', 'danger') . '"></i>',
                PAYPAL_ADMIN_URL . '/index.php?deldiscount&id=' . $A['id'],
                array(
                    'onclick'=>'return confirm(\'' . $LANG_PP['q_del_item'] . '\');',
                )
        );
        break;

    case 'start':
    case 'end':
        $Dt->setTimestamp((int)$fieldvalue);
        $retval = '<span class="tooltip" title="' . $Dt->toMySQL(false) . ' UTC">'
            . $Dt->toMySQL(true) . '</span>';
        break;

    case 'item_id':
        switch ($A['item_type']) {
        case 'product':
            $P = \Paypal\Product::getInstance($fieldvalue);
            if ($P) {
                $retval = $P->short_description;
            } else {
                $retval = 'Unknown';
            }
            break;
        case 'category':
            if ($fieldvalue == 0) {     // root category
                $retval = $LANG_PP['home'];
            } else {
                $C = \Paypal\Category::getInstance($fieldvalue);
                $retval = $C->cat_name;
            }
            break;
        default;
            $retval = '';
            break;
        }
        break;

    case 'amount':
        switch ($A['discount_type']) {
        case 'amount':
            $retval = $Cur->format($fieldvalue);
            break;
        case 'percent':
            $retval = $fieldvalue . ' %';
            break;
        }
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }
    return $retval;
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
function getAdminField_Workflow($fieldname, $fieldvalue, $A, $icon_arr)
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
                "\"{$A['rec_type']}\");' />" . LB;
        break;

    case 'orderby':
        $url = PAYPAL_ADMIN_URL .
            "/index.php?id={$A['id']}&amp;type={$A['rec_type']}&amp;wfmove=";
        $retval = COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-arrow-up pp-icon-info"></i>',
                $url . 'up'
            ) .
            COM_createLink('<i class="' . $_PP_CONF['_iconset'] .
                    '-arrow-down pp-icon-info"></i>',
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


/**
*   Display the purchase history for a single item.
*
*   @param  mixed   $item_id    Numeric or string item ID
*   @return string      Display HTML
*/
function PAYPAL_couponlist()
{
    global $_TABLES, $LANG_PP, $_PP_CONF;

    $filt_sql = '';
    if (isset($_GET['filter']) && isset($_GET['value'])) {
        switch ($_GET['filter']) {
        case 'buyer':
        case 'redeemer':
            $filt_sql = "WHERE `{$_GET['filter']}` = '" . DB_escapeString($_GET['value']) . "'";
            break;
        }
    }
    $sql = "SELECT * FROM {$_TABLES['paypal.coupons']} $filt_sql";

    $header_arr = array(
        array('text' => $LANG_PP['code'],
                'field' => 'code', 'sort' => true),
        array('text' => $LANG_PP['purch_date'],
                'field' => 'purchased', 'sort' => true),
        array('text' => $LANG_PP['amount'],
                'field' => 'amount', 'sort' => false),
        array('text' => $LANG_PP['balance'],
                'field' => 'balance', 'sort' => false),
        array('text' => $LANG_PP['buyer'],
                'field' => 'buyer', 'sort' => true),
        array('text' => $LANG_PP['redeemer'],
                'field' => 'redeemer', 'sort' => true),
    );

    $defsort_arr = array('field' => 'purchased',
            'direction' => 'DESC');

    $query_arr = array('table' => 'paypal.coupons',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array();
    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display = COM_startBlock('', '',
                    COM_getBlockTemplate('_admin_block', 'header'));
    $display .= '<h2>' . $LANG_PP['couponlist'] . '</h2>';
    $display .= '<div><a href="' . PAYPAL_ADMIN_URL .
            '/index.php?sendcards_form=x' . '">' . $LANG_PP['send_giftcards'] . "</a></div>\n";
    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_couponlist',
            __NAMESPACE__ . '\getAdminField_coupons',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', '');
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
*   Display the purchase history for a single item.
*
*   @param  mixed   $item_id    Numeric or string item ID
*   @return string      Display HTML
*/
function PAYPAL_itemhist($item_id = '')
{
    global $_TABLES, $LANG_PP, $_PP_CONF;

    if (is_numeric($item_id)) {
        $Item = new \Paypal\Product($item_id);
        $item_desc = $Item->short_description;
    } else {
        $item_desc = $item_id;
    }

    $sql = "SELECT *, sum(quantity) as qty FROM {$_TABLES['paypal.purchases']}";
    if ($item_id != '') {
        $sql .= " WHERE product_id = '" . DB_escapeString($item_id) . "'";
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
            'direction' => 'DESC');

    $query_arr = array('table' => 'paypal.orderstatus',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => '',
    );

    $text_arr = array();
/*    $text_arr = array(
        'has_extras' => true,
        'form_url' => PAYPAL_ADMIN_URL . '/index.php?itemhist=' . $item_id,
    );
*/
    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display = COM_startBlock('', '',
                    COM_getBlockTemplate('_admin_block', 'header'));
    $display .= $LANG_PP['item_history'] . ': ' . $item_desc;
    $display .= ADMIN_list($_PP_CONF['pi_name'] . '_itemhist',
            __NAMESPACE__ . '\getAdminField_itemhist',
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
function getAdminField_itemhist($fieldname, $fieldvalue, $A, $icon_arr)
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


/**
*   Get an individual field for the coupon listing
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function getAdminField_coupons($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP;

    $retval = '';
    static $username = array();

    switch($fieldname) {
    case 'buyer':
    case 'redeemer':
        if (!isset($username[$fieldvalue])) {
            $username[$fieldvalue] = COM_getDisplayName($fieldvalue);
        }
        $retval = COM_createLink($username[$fieldvalue],
            PAYPAL_ADMIN_URL . "/index.php?coupons=x&filter=$fieldname&value=$fieldvalue",
            array(
                'title' => 'Click to filter by ' . $fieldname,
                'class' => 'tooltip',
            )
        );
        break;

    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }

    return $retval;
}


?>

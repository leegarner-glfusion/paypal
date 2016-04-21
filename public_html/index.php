<?php
/**
*   Public index page for users of the paypal plugin
*
*   By default displays available products along with links to purchase history
*   and detailed product views
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net
*   @copyright  Copyright (c) 2009-2011 Lee Garner
*   @copyright  Copyright (c) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.5.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('paypal', $_PLUGINS)) {
    COM_404();
}

// Ensure sufficient privs and dependencies to read this page
PAYPAL_access_check();

// Import plugin-specific functions
USES_paypal_functions();

// Create a global shopping cart for our use.  This allows the cart to be
// manipulated in an action and then displayed in a view, without necessarily
// having to revisit the database or create a new cart.
USES_paypal_class_cart();
$ppGCart = new ppCart();

// First try to get the SEO-friendly arguments.  A single "action" and "id"
// will probably be the most common anyway.  If that fails, go through all
// the possibilies for actions that might come from submit buttons, etc.
COM_setArgNames(array('action', 'id'));
$action = COM_getArgument('action');
$actionval = '';

if (!empty($action)) {
    $id = COM_sanitizeID(COM_getArgument('id'));
} else {
    $expected = array(
        // Actions
        'updatecart', 'checkout', 'searchcat', 
        'savebillto', 'saveshipto', 
        'updatecart', 'emptycart',
        'addcartitem', 'addcartitem_x', 'checkoutcart',
        'processorder', 'thanks', 'action', 
        // Views
        'order', 'view', 'detail');
    $action = 'view';
    //echo $action;die;
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
    if (isset($_POST['id'])) {
        $id = $_POST['id'];
    } elseif (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $id = '';
    }
}

$content = '';

switch ($action) {
case 'updatecart':
    $ppGCart->UpdateAllQty($_POST['quantity']);
    $view = 'cart';
    break;

case 'checkout':
    if (isset($_POST['quantity'])) {
        // Update the cart quantities if coming from the cart view.
        $ppGCart->UpdateAllQty($_POST['quantity']);
    }
    if (isset($_POST['order_instr'])) {
        $ppGCart->setInstructions($_POST['order_instr']);
    }
    if ($_PP_CONF['anon_buy'] == 1 || !COM_isAnonUser()) {
        USES_paypal_class_workflow();
        USES_paypal_class_userinfo();
        // Start with the first view.
        //$view = ppWorkflow::getNextView();
        $view = 'checkoutcart';

        // See what workflow elements we already have.
        foreach ($_PP_CONF['workflows'] as $wf_name) {
            switch ($wf_name) {
            case 'billto':
            case 'shipto':
                if (!(ppUserInfo::isValidAddress($ppGCart->getAddress($wf_name)) == '')) {
                    //$view = ppWorkflow::getNextView($wf_name);
                    $view = $wf_name;
                    break 2;    // exit switch and foreach
                }
                break;
            }
        }
    } else {
        $content .= SEC_loginRequiredForm();
        $view = 'none';
    }
    break;

case 'savebillto':
case 'saveshipto':
    $addr_type = substr($action, 4);   // get 'billto' or 'shipto'
    USES_paypal_class_userinfo();
    USES_paypal_class_workflow();
    $status = ppUserInfo::isValidAddress($_POST);
    if ($status != '') {
        $content .= PAYPAL_errMsg($status, $LANG_PP['invalid_form']);
        $view = $addr_type;
        break;
    }

    $U = new ppUserInfo();
    if ($U->uid > 1) {
        $addr_id = $U->SaveAddress($_POST, $addr_type);
        if ($addr_id[0] < 0) {
            if (!empty($addr_id[1])) 
                $content .= PAYPAL_errorMessage($addr_id[1], 'alert', 
                        $LANG_PP['missing_fields']);
            $view = $addr_type;
            break;
        } else {
            $_POST['useaddress'] = $addr_id[0];
        }
    }
    $view = ppWorkflow::getNextView($addr_type);
    $ppGCart->setAddress($_POST, $addr_type);
    break;

case 'addcartitem':
case 'addcartitem_x':   // using the image submit button, such as Paypal's
    USES_paypal_class_cart();
    $view = 'productlist';
    $qty = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 1;
    $ppGCart->addItem($_POST['item_number'], $_POST['item_name'],
                $_POST['item_descr'], $qty, 
                $_POST['amount'], $_POST['options'], $_POST['extras']);
    if (isset($_POST['_ret_url'])) {
        COM_refresh($_POST['_ret_url']);
        exit;
    } else {
        COM_refresh(PAYPAL_URL.'/detail.php?id='.$_POST['item_number']);
        exit;
    }
    break;

case 'delcartitem':
    $ppGCart->Remove($_GET['id']);
    /*if (isset($_SESSION[PP_CART_VAR]['order_id']) &&
        !empty($_SESSION[PP_CART_VAR]['order_id'])) {
        //USES_paypal_class_order();
        //ppOrder::delCartItem($_GET['id'], $_SESSION[PP_CART_VAR]['order_id']);
        $ppGCart->Remove($_GET['id']);
    }*/
    $view = 'cart';
    break;

case 'updatecart':
    $view = 'cart';
    $ppGCart->UpdateAllQty($_POST['quantity']);
    break;

case 'emptycart':
    $view = 'productlist';
    $ppGCart->Clear();
    LGLIB_storeMessage($LANG_PP['cart_empty']);
    break;

case 'thanks':
    $ppGCart->Clear(false);
    if (!empty($actionval) && USES_paypal_gateway($actionval)) {
        $gw = new $actionval();
        $tVars = $gw->thanksVars();
        if (!empty($tVars)) {
            $T = new Template($_CONF['path'] . 'plugins/paypal/templates');
            $T ->set_file(array('msg'   => 'thanks_for_order.thtml'));
            $T->set_var(array(
                'site_name'     => $_CONF['site_name'],
                'payment_date'  => $tVars['payment_date'],
                'currency'      => $tVars['currency'],
                'mc_gross'      => $tVars['payment_amount'],
                'gateway_url'   => $tVars['gateway_url'],
                'gateway_name'  => $tVars['gateway_name'],
                'payment_status' => $tVars['payment_status'],
                'completed' => $tVars['_status'] == 'completed' ? 'true' : '',
            ) );
            $message = $T->parse('output', 'msg');
        } else {
            // Allow for no thanksVars function
            $message = $LANG_PP['thanks_title'];
        }
    } else {
        // Allow for missing or unknown payment gateway name
        $message = $LANG_PP['thanks_title'];
    }
    $content .= COM_showMessageText($message, $LANG_PP['thanks_title'], true);
    $view = 'productlist';
    break;

case 'action':      // catch all the "?action=" urls
    switch ($actionval) {
    case 'thanks':
        $ppGCart->Clear();
        $T = new Template($_CONF['path'] . 'plugins/paypal/templates');
        $T ->set_file(array('msg'   => 'thanks_for_order.thtml'));
        $T->set_var(array(
            'site_name'     => $_CONF['site_name'],
            'payment_date'  => $_POST['payment_date'],
            'currency'      => $_POST['mc_currency'],
            'mc_gross'      => $_POST['mc_gross'],
            'paypal_url'    => $_PP_CONF['paypal_url'],
        ) );
    
        $content .= COM_showMessageText($T->parse('output', 'msg'), 
                    $LANG_PP['thanks_title'], true);
        $view = 'productlist';
        break;
    }
    break;

case 'view':            // "?view=" url passed in
    $view = $actionval;
    break;

case 'processorder':
    // Process the order, similar to what an IPN would normally do.
    // This is for internal, manual processes like C.O.D. or Prepayment orders
    $gw_name = isset($_POST['gateway']) ? $_POST['gateway'] : '';
    $status = USES_paypal_gateway($gw_name);
    if ($status) {
        $gw = new $gw_name;
        $output = $gw->handlePurchase($_POST);
        if (!empty($output)) {
            $content .= $output;
            $view = 'none';
            break;
        }
        $view = 'thanks';
        $ppGCart->Clear(false);
        if (USES_paypal_gateway($actionval)) {
            $gw = new $actionval();
            $tVars = $gw->thanksVars();
            if (!empty($tVars)) {
                $T = new Template($_CONF['path'] . 'plugins/paypal/templates');
                $T ->set_file(array('msg'   => 'thanks_for_order.thtml'));
                $T->set_var(array(
                    'site_name'     => $_CONF['site_name'],
                    'payment_date'  => $tVars['payment_date'],
                    'currency'      => $tVars['currency'],
                    'mc_gross'      => $tVars['payment_amount'],
                    'gateway_url'   => $tVars['gateway_url'],
                    'gateway_name'  => $tVars['gateway_name'],
                ) );
                $message = $T->parse('output', 'msg');
            } else {
                // Allow for no thanksVars function
                $message = $LANG_PP['thanks_title'];
            }
        } else {
            // Allow for missing or unknown payment gateway name
            $message = $LANG_PP['thanks_title'];
        }
        $content .= COM_showMessageText($message, $LANG_PP['thanks_title'], true);
    }
    $view = 'productlist';
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'history':
    //$content .= PAYPAL_history();
    $content .= PAYPAL_orders();
    $menu_opt = $LANG_PP['purchase_history'];
    $page_title = $LANG_PP['purchase_history'];
    break;

case 'billto':
case 'shipto':
    USES_paypal_class_userinfo();
    $U = new ppUserInfo();
    $A = isset($_POST['address1']) ? $_POST : $ppGCart->getAddress($view);
    $content .= $U->AddressForm($view, $A);
    break;

case 'order':
    USES_paypal_class_order();
    $order = new ppOrder($actionval);
    if ($order->canView()) {
        $content .= $order->View(true);
    } else {
        $content .= $LANG_PP['access_denied_msg'];
    }
    break;

case 'vieworder':
    $_SESSION[PP_CART_VAR]['prevpage'] = $view;
    $content .= $ppGCart->View(true);
    $page_title = $LANG_PP['view_order'];
    break;

case 'detail':
    // deprecated, should be displayed via detail.php
    USES_paypal_class_product();
    $P = new Product($id);
    $content .= $P->Detail();
    $menu_opt = $LANG_PP['product_list'];
    $page_title = $LANG_PP['product_detail'];
    break;

case 'cart':
case 'viewcart':
    $menu_opt = $LANG_PP['viewcart'];
    if ($ppGCart->hasItems()) {
        $content .= $ppGCart->View();
    } else {
        $content .= '<span class="info">' . $LANG_PP['cart_empty'] . '</span>';
    }
    break;

case 'checkoutcart':
    // Need to create an order or save the cart, so IPN class
    // can access the data. For now, use the cart.
    /*USES_paypal_class_order();
    if (empty($_SESSION[PP_CART_VAR]['invoice'])) {
        $Ord = new ppOrder();
        $Ord->CreateFromCart($ppGCart);
    } else {
        $Ord = new ppOrder($_SESSION[PP_CART_VAR]['invoice']);
    }*/
    $ppGCart->Save();   // make sure it's saved to the DB
    $content .= $ppGCart->View(true);
    break;

case 'productlist':
default:
    if (isset($_REQUEST['category'])) {
        $content .= PAYPAL_ProductList($_REQUEST['category']);
    } else {
        $content .= PAYPAL_ProductList();
    }
    $menu_opt = $LANG_PP['product_list'];
    $page_title = $LANG_PP['main_title'];
    break;

case 'none':
    // Add nothing, useful if the view is handled by the action above
    break;
}

$display = PAYPAL_siteHeader();
$T = new Template(PAYPAL_PI_PATH . '/templates');
$T->set_file('title', 'paypal_title.thtml');
$T->set_var('title', $page_title);
$display .= $T->parse('', 'title');
if (!empty($msg)) {
    //msg block
    $display .= COM_startBlock('','','blockheader-message.thtml');
    $display .= $msg;
    $display .= COM_endBlock('blockfooter-message.thtml');
}
$display .= LGLIB_showAllMessages();
//$display .= PAYPAL_userMenu($menu_opt);
$display .= $content;
$display .= PAYPAL_siteFooter();
echo $display;

?>

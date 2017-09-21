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
PAYPAL_setCart();

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
        'order', 'view', 'detail', 'printorder', 'orderhist',
    );
    $action = 'view';
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
        // Start with the first view.
        //$view = Workflow::getNextView();
        $view = 'checkoutcart';

        // See what workflow elements we already have.
        foreach ($_PP_CONF['workflows'] as $wf_name) {
            switch ($wf_name) {
            case 'billto':
            case 'shipto':
                if (!(Paypal\UserInfo::isValidAddress($ppGCart->getAddress($wf_name)) == '')) {
                    //$view = Workflow::getNextView($wf_name);
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
    $status = Paypal\UserInfo::isValidAddress($_POST);
    if ($status != '') {
        $content .= PAYPAL_errMsg($status, $LANG_PP['invalid_form']);
        $view = $addr_type;
        break;
    }

    $U = new Paypal\UserInfo();
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
    $view = Paypal\Workflow::getNextView($addr_type);
    $ppGCart->setAddress($_POST, $addr_type);
    break;

case 'addcartitem':
case 'addcartitem_x':   // using the image submit button, such as Paypal's
    $view = 'productlist';
    if (isset($_POST['_unique']) && $_POST['_unique'] &&
            $ppGCart->Contains($_POST['item_number']) !== false) {
        break;
    }
    $qty = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 1;
    $ppGCart->addItem($_POST['item_number'], $_POST['item_name'],
                $_POST['item_descr'], $qty, 
                $_POST['amount'], $_POST['options'], $_POST['extras']);
    if (isset($_POST['_ret_url'])) {
        COM_refresh($_POST['_ret_url']);
        exit;
    } elseif (PAYPAL_is_plugin_item($$_POST['item_number'])) {
        COM_refresh(PAYPAL_URL);
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
        //Paypal\Order::delCartItem($_GET['id'], $_SESSION[PP_CART_VAR]['order_id']);
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
    // Allow for no thanksVars function
    $message = $LANG_PP['thanks_title'];
    if (!empty($actionval)) {
        $gw = Paypal\Gateway($actionval);
        if ($gw !== NULL) {
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
            }
        }
    }
    $content .= COM_showMessageText($message, $LANG_PP['thanks_title'], true, 'success');
    $view = 'productlist';
    break;

case 'action':      // catch all the "?action=" urls
    switch ($actionval) {
    case 'thanks':
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
    $gw = Paypal\Gateway::getInstance($gw_name);
    if ($gw !== NULL) {
        $output = $gw->handlePurchase($_POST);
        if (!empty($output)) {
            $content .= $output;
            $view = 'none';
            break;
        }
        $view = 'thanks';
        $ppGCart->Clear(false);
        /*if (USES_paypal_gateway($actionval)) {
            $gw = new $actionval;
            $tVars = $gw->thanksVars();
            if (!empty($tVars)) {
                $T = new \Template($_CONF['path'] . 'plugins/paypal/templates');
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
        }*/
        //$content .= COM_showMessageText($message, $LANG_PP['thanks_title'], true);
    }
    $view = 'productlist';
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'orderhist':
case 'history':
    if (COM_isAnonUser()) COM_404();
    $content .= PAYPAL_orders();
    $menu_opt = $LANG_PP['purchase_history'];
    $page_title = $LANG_PP['purchase_history'];
    break;

case 'billto':
case 'shipto':
    if (COM_isAnonUser()) COM_404();
    $U = new Paypal\UserInfo();
    $A = isset($_POST['address1']) ? $_POST : $ppGCart->getAddress($view);
    $content .= $U->AddressForm($view, $A);
    break;

case 'order':
    if (COM_isAnonUser()) COM_404();
    $order = new Paypal\Order($actionval);
    if ($order->canView()) {
        $content .= $order->View(true);
    } else {
        $content .= $LANG_PP['access_denied_msg'];
    }
    break;

case 'printorder':
    $order = new Paypal\Order($actionval);
    if ($order->canView()) {
        echo $order->View(true, 'print');
        exit;
    } else {
        COM_404();
    }
    break;

case 'vieworder':
    if (COM_isAnonUser()) COM_404();
    //$_SESSION[PP_CART_VAR]['prevpage'] = $view;
    Paypal\Cart::setSession('prevpage', $view);
    $content .= $ppGCart->View(true);
    $page_title = $LANG_PP['view_order'];
    break;

case 'detail':
    // deprecated, should be displayed via detail.php
    $P = new Paypal\Product($id);
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
        LGLIB_storeMessage($LANG_PP['cart_empty']);
        COM_refresh(PAYPAL_URL . '/index.php');
        exit;
    }
    break;

case 'checkoutcart':
    // Need to create an order or save the cart, so IPN class
    // can access the data. For now, use the cart.
    /*
    if (empty($_SESSION[PP_CART_VAR]['invoice'])) {
        $Ord = new Paypal\Order();
        $Ord->CreateFromCart($ppGCart);
    } else {
        $Ord = new Order($_SESSION[PP_CART_VAR]['invoice']);
    }*/
    $ppGCart->Save();   // make sure it's saved to the DB
    $content .= $ppGCart->View(true);
    break;

case 'productlist':
default:
    if (isset($_REQUEST['category'])) {
        $content .= Paypal\ProductList($_REQUEST['category']);
    } else {
        $content .= Paypal\ProductList();
    }
    $menu_opt = $LANG_PP['product_list'];
    $page_title = $LANG_PP['main_title'];
    break;

case 'none':
    // Add nothing, useful if the view is handled by the action above
    break;
}

$display = Paypal\siteHeader();
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
$display .= $content;
$display .= Paypal\siteFooter();
echo $display;

?>

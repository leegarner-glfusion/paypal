<?php
/**
*   Product detail display for the PayPal plugin.
*   This page's only job is to display the product detail.  This is to help
*   with SEO and uses rewritten urls.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2011 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

namespace Paypal;

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('paypal', $_PLUGINS)) {
    COM_404();
    exit;
}

/* Ensure sufficient privs to read this page */
paypal_access_check();

// Import plugin-specific functions
USES_paypal_functions();

// Create a global shopping cart for our use.  This allows the cart to be
// manipulated in an action and then displayed in a view, without necessarily
// having to revisit the database or create a new cart.
USES_paypal_class_Cart();
$ppGCart = new Cart();

COM_setArgNames(array('id'));
if (isset($_GET['id'])) {
    $id = COM_sanitizeID($_GET['id']);
} else {
    $id = COM_applyFilter(COM_getArgument('id'));
}

$display = PAYPAL_siteHeader();
$T = new \Template(PAYPAL_PI_PATH . '/templates');
$T->set_file('title', 'paypal_title.thtml');
$T->set_var('title', $LANG_PP['main_title']);
$display .= $T->parse('', 'title');
if (!empty($msg)) {
    //msg block
    $display .= COM_startBlock('','','blockheader-message.thtml');
    $display .= $msg;
    $display .= COM_endBlock('blockfooter-message.thtml');
}

//$display .= PAYPAL_userMenu($LANG_PP['product_list']);

$content = '';
$breadcrumbs = '';
if (!empty($id)) {
    USES_paypal_class_Product();
    $P = new Product($id);
    if ($P->id == $id) {
        $breadcrumbs = PAYPAL_Breadcrumbs($P->cat_id);
        $content .= $P->Detail();
    }
}
if (empty($content)) {
    $content .= PAYPAL_errorMessage($LANG_PP['invalid_product_id']);
}
if (empty($breadcrumbs)) {
    $breadcrumbs = COM_createLink($LANG_PP['back_to_catalog'], PAYPAL_URL);
}

$display .= $breadcrumbs;
$display .= $content;

$display .= PAYPAL_siteFooter();
echo $display;

?>

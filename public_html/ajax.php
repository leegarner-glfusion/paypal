<?php
/**
*   Common user-facing AJAX functions
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Include required glFusion common functions
*/
require_once '../lib-common.php';

$uid = (int)$_USER['uid'];
$action = PP_getVar($_GET, 'action');
switch ($action) {
case 'delAddress':          // Remove a shipping address
    if ($uid < 2) break;    // Not available to anonymous
    $id = (int)$_GET['id']; // Sanitize address ID
    \Paypal\UserInfo::deleteAddress($id);
    //DB_delete($_TABLES['paypal.address'], array('id','uid'), array($id,$uid));
    break;

case 'getAddress':
    if ($uid < 2) break;
    $id = (int)$_GET['id'];
    $res = DB_query("SELECT * FROM {$_TABLES['paypal.address']} WHERE id=$id",1);
    $A = DB_fetchArray($res, false);
    //if (!empty($A)) {
    break;

case 'addcartitem':
    if (!isset($_POST['item_number'])) {
        echo json_encode(array('content' => '', 'statusMessage' => ''));;
        exit;
    }
    $P = \Paypal\Product::getInstance($_POST['item_number']);
    if ($P->isNew) {
        // Invalid product ID passed
        echo json_encode(array('content' => '', 'statusMessage' => ''));;
        exit;
    }
    $Cart = \Paypal\Cart::getInstance();
    if (isset($_POST['_unique']) && $_POST['_unique'] &&
        $Cart->Contains($_POST['item_number']) !== false) {
        // Do nothing if only one item instance may be added
        break;
    }
    $args = array(
        'item_number'   => $_POST['item_number'],     // isset ensured above
        'item_name'     => PP_getVar($_POST, 'item_name'),
        'description'   => PP_getVar($_POST, 'item_descr'),
        'quantity'      => PP_getVar($_POST, 'quantity', 'int'),
        //'price'         => PP_getVar($_POST, 'base_price', 'float'),
        'price'         => $P->getPrice(),
        'options'       => PP_getVar($_POST, 'options', 'array'),
        'extras'        => PP_getVar($_POST, 'extras', 'array'),
        //'tax'           => PP_getVar($_POST, 'tax', 'float'),
    );
    $Cart->addItem($args);
    $A = array(
        'content' => phpblock_paypal_cart_contents(),
        'statusMessage' => $LANG_PP['msg_item_added'],
        'ret_url' => isset($_POST['_ret_url']) && !empty($_POST['_ret_url']) ?
                $_POST['_ret_url'] : '',
        'unique' => isset($_POST['_unique']) ? true : false,
    );
    echo json_encode($A);
    exit;
    break;

case 'finalizecart':
    $cart_id = PP_getVar($_POST, 'cart_id');
    $status = \Paypal\Cart::setFinal($cart_id);
    $A = array(
        'status' => $status,
    );
    echo json_encode($A);
    exit;
    break;

case 'redeem_gc':
    if (COM_isAnonUser()) {
        $msg = $LANG_PP['gc_need_acct'];
    } else {
        $code = PP_getVar($_POST, 'gc_code');
        $uid = $_USER['uid'];
        list($status, $status_msg) = \Paypal\Coupon::Redeem($code, $uid);
        $gw = \Paypal\Gateway::getInstance('_coupon');
        $gw_radio = $gw->checkoutRadio($status == 0 ? true : false);
        $A = array (
            'statusMessage' => $status_msg,
            'html' => $gw_radio,
            'status' => $status,
        );
        echo json_encode($A);
        exit;
    }
default:
    // Missing action, nothing to do
    break;
}

?>

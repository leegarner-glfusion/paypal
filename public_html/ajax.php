<?php
/**
*   Common user-facing AJAX functions
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.4.6
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Include required glFusion common functions
*/
require_once '../lib-common.php';

$uid = (int)$_USER['uid'];

switch ($_GET['action']) {
case 'delAddress':          // Remove a shipping address
    if ($uid < 2) break;    // Not available to anonymous
    $id = (int)$_GET['id']; // Sanitize address ID
    Paypal\UserInfo::deleteAddress($id);
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
    $ppGCart = Paypal\Cart::getInstance();
    if (isset($_POST['_unique']) && $_POST['_unique'] &&
            $ppGCart->Contains($_POST['item_number']) !== false) {
        break;
    }
    $args = array(
        'item_number'   => $_POST['item_number'],     // isset ensured above
        'item_name'     => PP_getVar($_POST, 'item_name'),
        'description'   => PP_getVar($_POST, 'item_descr'),
        'quantity'      => PP_getVar($_POST, 'quantity', 'int'),
        'price'         => PP_getVar($_POST, 'base_price', 'float'),
        'options'       => PP_getVar($_POST, 'options', 'array'),
        'extras'        => PP_getVar($_POST, 'extras', 'array'),
        //'tax'           => PP_getVar($_POST, 'tax', 'float'),
    );
    $ppGCart->addItem($args);
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
    $status = Paypal\Cart::setFinal($cart_id);
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
        $status = Paypal\Coupon::Redeem($code, $uid);
        $gw = Paypal\Gateway::getInstance('_coupon_gw');
        $gw_radio = $gw->checkoutRadio($status == 0 ? true : false);
        $status_msg = sprintf($LANG_PP['coupon_apply_msg' . $status], $_CONF['site_mail']);
        $A = array (
            'statusMessage' => $status_msg,
            'html' => $gw_radio,
            'status' => $status,
        );
        echo json_encode($A);
        exit;
    }
}

?>

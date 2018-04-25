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
    //PAYPAL_setCart();
    $ppGCart = Paypal\Cart::getInstance();
    if (isset($_POST['_unique']) && $_POST['_unique'] &&
            $ppGCart->Contains($_POST['item_number']) !== false) {
        break;
    }
    $args = array(
        'item_number' => isset($_POST['item_number']) ? $_POST['item_number'] : '',
        'item_name' => isset($_POST['item_name']) ? $_POST['item_name'] : '',
        'description' => isset($_POST['item_descr']) ? $_POST['item_descr'] : '',
        'quantity' => isset($_POST['quantity']) ? (float)$_POST['quantity'] : 1,
        'price' => isset($_POST['base_price']) ? $_POST['base_price'] : 0,
        'options' => isset($_POST['options']) ? $_POST['options'] : array(),
        'extras' => isset($_POST['extras']) ? $_POST['extras'] : array(),
        //'tax' => isset($_POST['tax']) ? $_POST['tax'] : NULL,
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
}

?>

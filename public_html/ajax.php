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
    DB_delete($_TABLES['paypal.address'], array('id','uid'), array($id,$uid));
    break;
case 'getAddress':
    if ($uid < 2) break;
    $id = (int)$_GET['id'];
    $res = DB_query("SELECT * FROM {$_TABLES['paypal.address']} WHERE id=$id",1);
    $A = DB_fetchArray($res, false);
    //if (!empty($A)) {
    break;
case 'addcartitem':
    USES_paypal_class_cart();
    $ppGCart = new ppCart();
    $view = 'list';
    $qty = isset($_GET['quantity']) ? (float)$_GET['quantity'] : 1;
    $opts = isset($_GET['options']) ? $_GET['options'] : '';
    $price = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
    public function addItem($item_id, $name, $descrip='', $quantity=1,
            $price=0, $options='')
    $new_qty = $ppGCart->addItem($_GET['item_number'], $_GET['item_name'],
                $_GET['item_descr'], $qty, $price, $opts);
    echo json_encode(array('qty' => $new_qty));
    break;
}

?>

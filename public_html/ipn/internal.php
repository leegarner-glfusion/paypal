<?php
/**
*   Dummy IPN processor for orders with zero balances.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion functions */
require_once '../../lib-common.php';

if ($_PP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog("Recieved IPN:", 1);
    COM_errorLog(var_export($_POST, true), 1);
}

// Process IPN request
$ipn = \Paypal\IPN::getInstance('internal', $_POST);
if ($ipn) {
    $ipn->Process();
}

if (!isset($_GET['debug'])) {
    COM_refresh(PAYPAL_URL . '/index.php?thanks');
} else {
    echo 'Debug Finished';
}

?>

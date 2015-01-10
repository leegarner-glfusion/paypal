<?php
/**
*   IPN processor for Amazon SimplePay notifications.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
    
/** Import core glFusion functions */
require_once '../../lib-common.php';

USES_paypal_functions();
USES_paypal_class_ipn('amazon');

if ($_PP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog(var_export($_POST, true));
}

$ipn = new AmazonIPN($_POST);
$ipn->Process();

?>

<?php
/**
*   IPN processor for Paypal notifications.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2014 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
   
/** Import core glFusion functions */
require_once '../../lib-common.php';

USES_paypal_functions();

/** Import IPN class */
USES_paypal_class_ipn('paypal');

if ($_PP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog("Recieved IPN:");
    COM_errorLog(var_export($_POST, true));
}

// Process IPN request
$ipn = new PaypalIPN($_POST);
$ipn->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";

?>

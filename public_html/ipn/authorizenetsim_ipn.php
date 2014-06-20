<?php
/**
*   IPN processor for Amazon SimplePay notifications.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
    
/** Import core glFusion functions */
require_once '../../lib-common.php';

USES_paypal_functions();
USES_paypal_class_ipn('authorizenetsim');

if ($_PP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog(var_export($_POST, true));
}

if (GVERSION < '1.3.0') {
    $_POST = LGLIB_stripslashes($_POST);
}

$ipn = new AuthorizeNetIPN($_POST);
if ($ipn->Process()) {
    $redirect_url = 'http://dogbert.leegarner.com/dev/paypal/index.php?thanks';
} else {
    LGLIB_storeMessage($LANG_PP['pmt_error']);
    $redirect_url = 'http://dogbert.leegarner.com/dev/paypal/index.php';
}
echo "<html><head><script language=\"javascript\">
        <!--
        window.location=\"{$redirect_url}\";
        //-->
        </script>
        </head><body><noscript><meta http-equiv=\"refresh\" content=\"1;url={$redirect_url}\"></noscript></body></html>";
?>

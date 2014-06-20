<?php
/**
*   Installation functions for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Mark Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Mark Evans <mark@glfusion.org>
*   @package    paypal
*   @version    0.4.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion functions */
require_once '../../../lib-common.php';
/** Import this plugin's installation routines */
require_once $_CONF['path'].'/plugins/paypal/autoinstall.php';

USES_lib_install();

if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the PayPal install/uninstall page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: {$_SERVER['REMOTE_ADDR']}",1);
    $display = COM_siteHeader ('menu', $LANG_ACCESS['accessdenied'])
             . COM_startBlock ($LANG_ACCESS['accessdenied'])
             . $LANG_ACCESS['plugin_access_denied_msg']
             . COM_endBlock ()
             . COM_siteFooter ();
    echo $display;
    exit;
}

/**
* Main Function
*/

if (SEC_checkToken()) {
    $action = COM_applyFilter($_GET['action']);
    if ($action == 'install') {
        if (plugin_install_paypal()) {
    		// Redirects to the plugin editor
    		echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=44');
    		exit;
        } else {
    		echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=72');
    		exit;
        }
    } else if ($action == 'uninstall') {
    	if (plugin_uninstall_paypal('installed')) {
    		// Success - Redirects to the plugin editor
    		echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=45');
    		exit;
    	} else {
    		echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=73');
    		exit;
    	}
    }
}

echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php');

?>

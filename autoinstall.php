<?php
/**
*   Automatic installation functions for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Mark Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2009-2011 Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Mark Evans <mark@glfusion.org>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;

/** Include plugin configuration */
require_once $_CONF['path'].'plugins/paypal/paypal.php';
/** Include database queries */
require_once $_CONF['path'].'plugins/paypal/sql/'.$_DB_dbms.'_install.php';
/** Include default values */
require_once $_CONF['path'].'plugins/paypal/install_defaults.php';

$language = $_CONF['language'];
if (!is_file($_CONF['path'].'plugins/paypal/language/' . $language . '.php')) {
    $language = 'english';
}
require_once $_CONF['path'].'plugins/paypal/language/' . $language . '.php';
global $LANG_PP;

/**
*   Plugin installation options
*/
$INSTALL_plugin['paypal'] = array(
    'installer' => array(
            'type' => 'installer', 
            'version' => '1', 
            'mode' => 'install'),

    'plugin' => array(
            'type' => 'plugin', 
            'name' => $_PP_CONF['pi_name'],
            'ver' => $_PP_CONF['pi_version'], 
            'gl_ver' => $_PP_CONF['gl_version'],
            'url' => $_PP_CONF['pi_url'], 
            'display' => $_PP_CONF['pi_display_name']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.ipnlog'], 
            'sql' => $_SQL['paypal.ipnlog']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.products'], 
            'sql' => $_SQL['paypal.products']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.purchases'], 
            'sql' => $_SQL['paypal.purchases']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.categories'], 
            'sql' => $_SQL['paypal.categories']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.prod_attr'], 
            'sql' => $_SQL['paypal.prod_attr']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.images'], 
            'sql' => $_SQL['paypal.images']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.orders'], 
            'sql' => $_SQL['paypal.orders']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.gateways'], 
            'sql' => $_SQL['paypal.gateways']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.address'], 
            'sql' => $_SQL['paypal.address']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.userinfo'], 
            'sql' => $_SQL['paypal.userinfo']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.cart'], 
            'sql' => $_SQL['paypal.cart']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.workflows'], 
            'sql' => $_SQL['paypal.workflows']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.buttons'], 
            'sql' => $_SQL['paypal.buttons']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.orderstatus'], 
            'sql' => $_SQL['paypal.orderstatus']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.order_log'], 
            'sql' => $_SQL['paypal.order_log']),

    array(  'type' => 'table', 
            'table' => $_TABLES['paypal.currency'], 
            'sql' => $_SQL['paypal.currency']),

    array(  'type' => 'group', 
            'group' => 'paypal Admin', 
            'desc' => 'Users in this group can administer the PayPal plugin',
            'variable' => 'admin_group_id', 
            'admin' => true,
            'addroot' => true),

    array(  'type' => 'feature', 
            'feature' => 'paypal.admin', 
            'desc' => 'Ability to administer the PayPal plugin',
            'variable' => 'admin_feature_id'),

    array(  'type' => 'feature', 
            'feature' => 'paypal.user', 
            'desc' => 'Ability to use the PayPal plugin',
            'variable' => 'user_feature_id'),

    array(  'type' => 'feature', 
            'feature' => 'paypal.view', 
            'desc' => 'Ability to view PayPal entries',
            'variable' => 'view_feature_id'),

    array(  'type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'admin_feature_id',
            'log' => 'Adding feature to the admin group'),

    array(  'type' => 'mapping', 
            'findgroup' => 'All Users', 
            'feature' => 'view_feature_id',
            'log' => 'Adding feature to the All Users group'),

    array(  'type' => 'mapping', 
            'findgroup' => 'Logged-in Users', 
            'feature' => 'user_feature_id',
            'log' => 'Adding feature to the Logged-in Users group'),

    array(  'type' => 'block', 
            'name' => 'paypal_random', 
            'title' => $LANG_PP['random_product'],
            'phpblockfn' => 'phpblock_paypal_random', 
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0),

    array(  'type' => 'block', 
            'name' => 'paypal_categories', 
            'title' => $LANG_PP['product_categories'],
            'phpblockfn' => 'phpblock_paypal_categories', 
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0),

    array(  'type' => 'block', 
            'name' => 'paypal_featured', 
            'title' => $LANG_PP['featured_product'],
            'phpblockfn' => 'phpblock_paypal_featured', 
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0),

    array(  'type' => 'block', 
            'name' => 'paypal_popular', 
            'title' => $LANG_PP['popular_product'],
            'phpblockfn' => 'phpblock_paypal_popular', 
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0),

    array(  'type' => 'block', 
            'name' => 'paypal_cart', 
            'title' => $LANG_PP['cart_blocktitle'],
            'phpblockfn' => 'phpblock_paypal_cart', 
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'blockorder' => 5,
            'onleft' => 1,
            'is_enabled' => 1),

);


/**
*   Puts the datastructures for this plugin into the glFusion database
*   Note: Corresponding uninstall routine is in functions.inc
*
*   @return boolean     True if successful False otherwise
*/
function plugin_install_paypal()
{
    global $INSTALL_plugin, $_PP_CONF;

    $pi_name            = $_PP_CONF['pi_name'];
    $pi_display_name    = $_PP_CONF['pi_display_name'];

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
*   Loads the configuration records for the Online Config Manager
*
*   @return boolean true = proceed with install, false = an error occured
*/
function plugin_load_configuration_paypal()
{
    global $_CONF, $_PP_CONF, $_TABLES;

    // Get the group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_PP_CONF['pi_name']} Admin'");

    return plugin_initconfig_paypal($group_id);
}


/**
*   Plugin-specific post-installation function
*   Creates the file download path and working area
*/
function plugin_postinstall_paypal()
{
    global $_CONF, $_PP_CONF, $_PP_DEFAULTS, $_PP_SAMPLEDATA;

    // Create the working directory.  Under private/data by default
    // 0.5.0 - download path moved under tmpdir, so both are created
    //      here.
    $paths = array(
        $_PP_DEFAULTS['tmpdir'],
        $_PP_DEFAULTS['tmpdir'] . 'keys',
        $_PP_DEFAULTS['download_path'],
    );
    foreach ($paths as $path) {
        COM_errorLog("Creating $path");
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_writable($path)) {
            COM_errorLog("Cannot write to $path");
        }
    }

    // Create an empty log file
    if (!file_exists($_PP_CONF['logfile'])) {
        $fp = fopen($_PP_CONF['logfile'], "w+");
        if (!$fp) {
            COM_errorLog("Failed to create logfile {$_PP_CONF['logfile']}");
        } else {
            fwrite($fp, "*** Logfile Created ***\n");
        }
    }

    if (!is_writable($_PP_CONF['logfile'])) {
        COM_errorLog("Can't write to {$_PP_CONF['logfile']}");
    }

    $pi_path = $_CONF['path'] . '/plugins/' . $_PP_CONF['pi_name'];
    if (is_file($pi_path . '/config.php')) {
        if (!rename($pi_path . '/config.php', $pi_path . '/config.old.php')) {
            COM_errorLog("Failed to rename old config.php file.  Manual intervention needed");
        }
    }

    if (is_array($_PP_SAMPLEDATA)) {
        foreach ($_PP_SAMPLEDATA as $sql) {
            DB_query($sql, 1);
            if (DB_error()) {
                COM_errorLog("Sample Data SQL Error: $sql");
            }
        }
    }
}


?>

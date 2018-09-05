<?php
/**
*   Automatic installation functions for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Mark Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Mark Evans <mark@glfusion.org>
*   @package    paypal
*   @version    0.5.9
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

global $_DB_dbms;

/** Include plugin configuration */
require_once __DIR__  . '/paypal.php';
/** Include database queries */
require_once __DIR__ . '/sql/'.$_DB_dbms.'_install.php';
/** Include default values */
require_once __DIR__ . '/install_defaults.php';

$language = $_CONF['language'];
if (!is_file(__DIR__  . '/language/' . $language . '.php')) {
    $language = 'english';
}
require_once __DIR__ . '/language/' . $language . '.php';
global $LANG_PP;

/**
*   Plugin installation options
*/
$INSTALL_plugin['paypal'] = array(
    'installer' => array(
            'type' => 'installer',
            'version' => '1',
            'mode' => 'install',
        ),

    'plugin' => array(
            'type' => 'plugin',
            'name' => $_PP_CONF['pi_name'],
            'ver' => $_PP_CONF['pi_version'],
            'gl_ver' => $_PP_CONF['gl_version'],
            'url' => $_PP_CONF['pi_url'],
            'display' => $_PP_CONF['pi_display_name'],
        ),

    array(  'type' => 'group',
            'group' => 'paypal Admin',
            'desc' => 'Users in this group can administer the PayPal plugin',
            'variable' => 'admin_group_id',
            'admin' => true,
            'addroot' => true,
        ),

    array(  'type' => 'feature',
            'feature' => 'paypal.admin',
            'desc' => 'Ability to administer the PayPal plugin',
            'variable' => 'admin_feature_id',
        ),

    array(  'type' => 'feature',
            'feature' => 'paypal.user',
            'desc' => 'Ability to use the PayPal plugin',
            'variable' => 'user_feature_id',
        ),

    array(  'type' => 'feature',
            'feature' => 'paypal.view',
            'desc' => 'Ability to view PayPal entries',
            'variable' => 'view_feature_id',
        ),

    array(  'type' => 'mapping',
            'group' => 'admin_group_id',
            'feature' => 'admin_feature_id',
            'log' => 'Adding feature to the admin group',
        ),

    array(  'type' => 'mapping',
            'findgroup' => 'All Users',
            'feature' => 'view_feature_id',
            'log' => 'Adding feature to the All Users group',
        ),

    array(  'type' => 'mapping',
            'findgroup' => 'Logged-in Users',
            'feature' => 'user_feature_id',
            'log' => 'Adding feature to the Logged-in Users group',
        ),

    array(  'type' => 'block',
            'name' => 'paypal_search',
            'title' => 'Catalog Search',
            'phpblockfn' => 'phpblock_paypal_search',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0,
        ),

    array(  'type' => 'block',
            'name' => 'paypal_random',
            'title' => 'Random Product',
            'phpblockfn' => 'phpblock_paypal_random',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0,
        ),

    array(  'type' => 'block',
            'name' => 'paypal_categories',
            'title' => 'Product Categories',
            'phpblockfn' => 'phpblock_paypal_categories',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0,
        ),

    array(  'type' => 'block',
            'name' => 'paypal_featured',
            'title' => 'Featured Products',
            'phpblockfn' => 'phpblock_paypal_featured',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0,
        ),

    array(  'type' => 'block',
            'name' => 'paypal_popular',
            'title' => 'Popular',
            'phpblockfn' => 'phpblock_paypal_popular',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0,
        ),

    array(  'type' => 'block',
            'name' => 'paypal_recent',
            'title' => 'Newest Items',
            'phpblockfn' => 'phpblock_paypal_recent',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'is_enabled' => 0,
        ),

    array(  'type' => 'block',
            'name' => 'paypal_cart',
            'title' => 'Shopping Cart',
            'phpblockfn' => 'phpblock_paypal_cart',
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id',
            'blockorder' => 5,
            'onleft' => 1,
            'is_enabled' => 1,
        ),
);

$tables = array(
    'products', 'categories', 'purchases', 'ipnlog', 'cart', 'orders',
    'prod_attr', 'images', 'gateways', 'address', 'userinfo', 'workflows',
    'buttons', 'orderstatus', 'order_log', 'currency', 'coupons', 'coupon_log',
);
foreach ($tables as $table) {
    $INSTALL_plugin['paypal'][] = array(
        'type' => 'table',
        'table' => $_TABLES['paypal.' . $table],
        'sql' => $_SQL['paypal.'. $table],
    );
}

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
    global $_CONF, $_PP_CONF, $_PP_DEFAULTS, $_PP_SAMPLEDATA, $_TABLES;

    // Create the working directory.  Under private/data by default
    // 0.5.0 - download path moved under tmpdir, so both are created
    //      here.
    $paths = array(
        $_PP_CONF['tmpdir'],
        $_PP_CONF['tmpdir'] . 'keys',
        $_PP_CONF['tmpdir'] . 'cache',
        $_PP_CONF['download_path'],
    );
    foreach ($paths as $path) {
        COM_errorLog("Creating $path", 1);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_writable($path)) {
            COM_errorLog("Cannot write to $path", 1);
        }
    }

    // Create an empty log file
    if (!file_exists($_PP_CONF['logfile'])) {
        $fp = fopen($_PP_CONF['logfile'], "w+");
        if (!$fp) {
            COM_errorLog("Failed to create logfile {$_PP_CONF['logfile']}", 1);
        } else {
            fwrite($fp, "*** Logfile Created ***\n");
        }
    }

    if (!is_writable($_PP_CONF['logfile'])) {
        COM_errorLog("Can't write to {$_PP_CONF['logfile']}", 1);
    }

    if (is_array($_PP_SAMPLEDATA)) {
        foreach ($_PP_SAMPLEDATA as $sql) {
            DB_query($sql, 1);
            if (DB_error()) {
                COM_errorLog("Sample Data SQL Error: $sql", 1);
            }
        }
    }

    // Set the paypal Admin ID
    $gid = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_PP_CONF['pi_name']} Admin'");
    if ($gid < 1)
        $gid = 1;        // default to Root if paypal group not found
    DB_query("INSERT INTO {$_TABLES['vars']}
                SET name='paypal_gid', value=$gid");
}

?>

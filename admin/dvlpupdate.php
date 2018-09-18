<?php
/**
*   Apply updates to Paypal during development.
*   Calls upgrade function with "ignore_errors" set so repeated SQL statements
*   won't cause functions to abort.
*
*   Only updates from the previous released version.
*
*   @author     Mark R. Evans mark AT glfusion DOT org
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../../../lib-common.php';
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to access the Paypal Development Code Upgrade Routine without proper permissions.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: " . $_SERVER['REMOTE_ADDR'],1);
    $display  = COM_siteHeader();
    $display .= COM_startBlock($LANG27[12]);
    $display .= $LANG27[12];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}
require_once PAYPAL_PI_PATH . '/upgrade.inc.php';   // needed for set_version()
if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
\Paypal\Cache::clear();

$ver = '0.5.11';
PAYPAL_do_set_version($ver);
plugin_upgrade_paypal();

// need to clear the template cache so do it here
if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
header('Location: '.$_CONF['site_admin_url'].'/plugins.php?msg=600');
exit;


function _paypal_update_config()
{
    global $_CONF, $_SPX_CONF, $_TABLES;

    $c = config::get_instance();

    require_once __DIR__ . '/install_defaults.php';

    // remove stray items
    $result = DB_query("SELECT * FROM {$_TABLES['conf_values']} WHERE group_name='paypal'");
    while ($row = DB_fetchArray($result, false)) {
        $item = $row['name'];
        if (($key = _searchForIdKey($item,$paypalConfigData)) === NULL) {
            DB_query("DELETE FROM {$_TABLES['conf_values']} WHERE name='".DB_escapeString($item)."' AND group_name='paypal'");
        } else {
            $paypalConfigData[$key]['indb'] = 1;
        }
    }
    // add any missing items
    foreach ($paypalConfigData AS $cfgItem ) {
        if (!isset($cfgItem['indb']) ) {
            _addConfigItem( $cfgItem );
        }
    }

    $c = config::get_instance();
    $c->initConfig();
    $tcnf = $c->get_config('paypal');
    // sync up sequence, etc.
    foreach ($paypalConfigData AS $cfgItem) {
        $c->sync(
            $cfgItem['name'],
            $cfgItem['default_value'],
            $cfgItem['type'],
            $cfgItem['subgroup'],
            $cfgItem['fieldset'],
            $cfgItem['selection_array'],
            $cfgItem['sort'],
            $cfgItem['set'],
            $cfgItem['group']
        );
    }
}


?>

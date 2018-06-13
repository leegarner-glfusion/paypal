<?php
/**
*   Upgrade routines for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.12
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the ADVT_DEFAULTS config values
global $_CONF, $_CONF_ADVT, $_ADVT_DEFAULT, $_DB_dbms;

/** Include the default configuration values */
require_once PAYPAL_PI_PATH . '/install_defaults.php';

/** Include the table creation strings */
require_once PAYPAL_PI_PATH . "/sql/{$_DB_dbms}_install.php";

/**
*   Perform the upgrade starting at the current version.
*
*   @since  version 0.4.0
*   @return integer                 Error code, 0 for success
*/
function PAYPAL_do_upgrade()
{
    global $_TABLES, $_CONF, $_PP_CONF, $_PP_DEFAULTS, $PP_UPGRADE, $_PLUGIN_INFO;

    $pi_name = $_PP_CONF['pi_name'];
    if (isset($_PLUGIN_INFO[$pi_name])) {
        if (is_array($_PLUGIN_INFO[$pi_name])) {
            // glFusion >= 1.6.6
            $current_ver = $_PLUGIN_INFO[$pi_name]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$pi_name];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_paypal();

    // Get the config instance, several upgrades might need it
    $c = config::get_instance();

    if (!COM_checkVersion($current_ver, '0.2')) {
        // upgrade to 0.2.2
        $current_ver = '0.2.2';
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.0')) {
        // upgrade to 0.4.0
        $current_ver = '0.4.0';
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!plugin_initconfig_paypal()) return false;

        // Migrate existing categories to the new category table
        $r = DB_query("SELECT DISTINCT category
                FROM {$_TABLES['paypal.products']}
                WHERE category <> '' and category IS NOT NULL");
        if (DB_error()) {
            COM_errorLog("Could not retrieve old categories", 1);
            return false;
        }
        if (DB_numRows($r) > 0) {
            while ($A = DB_fetchArray($r, false)) {
                DB_query("INSERT INTO {$_TABLES['paypal.categories']}
                        (cat_name)
                    VALUES ('{$A['category']}')");
                if (DB_error()) {
                    COM_errorLog("Could not add new category {$A['category']}", 1);
                    return false;
                }
                $cats[$A['category']] = DB_insertID();
            }
            // Now populate the cross-reference table
            $r = DB_query("SELECT id, category
                    FROM {$_TABLES['paypal.products']}");
            if (DB_error()) {
                COM_errorLog("Error retrieving category data from products", 1);
                return false;
            }
            if (DB_numRows($r) > 0) {
                while ($A = DB_fetchArray($r, false)) {
                    DB_query("UPDATE {$_TABLES['paypal.products']}
                        SET cat_id = '{$cats[$A['category']]}'
                        WHERE id = '{$A['id']}'");
                    if (DB_error()) {
                        COM_errorLog("Error updating prodXcat table", 1);
                        return false;
                    }
                }
            }
            DB_query("ALTER TABLE {$_TABLES['paypal.products']}
                    DROP category");
        }

        // Add buttons to the product records or they won't be shown.
        // Old paypal version always has buy_now and add_cart buttons.
        $buttons = serialize(array('buy_now' => '', 'add_cart' => ''));
        DB_query("UPDATE {$_TABLES['paypal.products']}
                SET buttons='$buttons',
                dt_add = UNIX_TIMESTAMP()");

        // Finally, rename any existing config.php file since we now use
        // the online configuration.
        $pi_path = $_CONF['path'] . '/plugins/' . $pi_name;
        if (is_file($pi_path . '/config.php')) {
            COM_errorLog("Renaming old config.php file to $pi_path/config.old.php", 1);
            if (!rename($pi_path . '/config.php', $pi_path . '/config.old.php')) {
                COM_errorLog("Failed to rename old config.php file.  Manual intervention needed", 1);
            }
        }
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.1')) {
        // upgrade to 0.4.1
        $current_ver = '0.4.1';
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;

        if ($c->group_exists($pi_name)) {
             $c->add('blk_random_limit', $_PP_DEFAULTS['blk_random_limit'],
                    'text', 0, 30, 2, 40, true, $pi_name);
            $c->add('blk_featured_limit', $_PP_DEFAULTS['blk_featured_limit'],
                    'text', 0, 30, 2, 50, true, $pi_name);
            $c->add('blk_popular_limit', $_PP_DEFAULTS['blk_popular_limit'],
                    'text', 0, 30, 2, 60, true, $pi_name);

            $c->add('fs_debug', NULL, 'fieldset', 0, 50, NULL, 0, true, $pi_name);
            $c->add('debug', $_PP_DEFAULTS['debug'],
                'select', 0, 50, 2, 10, true, $pi_name);
        }
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.2')) {
        // upgrade to 0.4.2
        $current_ver = '0.4.2';
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.3')) {
        // upgrade to 0.4.3
        // this adds a field that was possibly missing in the initial
        // installation, but could have been added in the 0.4.1 update. So,
        // an error is to be expected and ignored
        $current_ver = '0.4.3';
        if (!PAYPAL_do_upgrade_sql($current_ver, true)) return false;

        if ($c->group_exists($pi_name)) {
            $c->add('def_expiration', $_PP_DEFAULTS['def_expiration'],
                'text', 0, 30, 0, 40, true, $pi_name);
        }
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.4')) {
        $current_ver = '0.4.4';
        // Remove individual block selections and combine into one
        $displayblocks = 0;
        if ($_PP_CONF['leftblocks'] == 1) $displayblocks += 1;
        if ($_PP_CONF['rightblocks'] == 1) $displayblocks += 2;

        $c->del('leftblocks', $pi_name);
        $c->del('rightblocks', $pi_name);
        $c->add('displayblocks', $displayblocks,
                'select', 0, 0, 13, 210, true, $pi_name);
        $c->add('debug_ipn', $_PP_DEFAULTS['debug_ipn'],
                'select', 0, 50, 2, 20, true, $pi_name);
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.5')) {
        $current_ver = '0.4.5';
        // Add notification email override
        $c->add('admin_email_addr', $_PP_DEFAULTS['admin_email_addr'],
                'text', 0, 0, 0, 40, true, $pi_name);
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.5')) {
        $current_ver = '0.4.5';
        // Move the buy_now buttons into a separate table
        $sql = "SELECT id, buttons FROM {$_TABLES['paypal.products']}";
        $res = DB_query($sql, 1);
        while ($A = DB_fetchArray($res, false)) {
            $id = $A['id'];
            $btns = @unserialize($A['buttons']);
            if ($btns && isset($btns['buy_now'])) {
                $button = DB_escapeString($btns['buy_now']);
            } else {
                $button = '';
            }
            DB_query("INSERT INTO {$_TABLES['paypal.buttons']} VALUES
                ('$id', $pi_name, '$button')", 1);
        }
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.0')) {
        $current_ver = '0.5.0';

        // Perform the main database upgrades
        // The first few lines get the schema updated for elements that
        // may have been missed (0.4.4 wasn't updated properly).
        // Errors need to be ignored for these.
        DB_query("ALTER TABLE {$_TABLES['paypal.products']}
                ADD options text after show_popular", 1);
        DB_query("ALTER TABLE {$_TABLES['paypal.purchases']}
                ADD token varchar(40) after price", 1);
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;

        // Move the global PayPal-specific configurations into the config table
        $receiver_email = DB_escapeString($_PP_CONF['receiver_email'][0]);
        $gwconfig = array(
            'bus_prod_email' => $receiver_email,
            'bus_test_email' => $receiver_email,
            'micro_prod_email' => $receiver_email,
            'micro_test_email' => $receiver_email,
            'micro_threshold' => 10,
            'prod_url'      => 'https://www.paypal.com',
            'sandbox_url'   => 'https://www.sandbox.paypal.com',
            'test_mode'     => (int)$_PP_CONF['testing'],
            'prv_key'       => DB_escapeString($_PP_CONF['prv_key']),
            'pub_key'       => DB_escapeString($_PP_CONF['pub_key']),
            'pp_cert'       => DB_escapeString($_PP_CONF['pp_cert']),
            'pp_cert_id'    => DB_escapeString($_PP_CONF['pp_cert_id']),
            'micro_cert_id' => DB_escapeString($_PP_CONF['pp_cert_id']),
            'encrypt'       => (int)$_PP_CONF['encrypt_buttons'],
        );
        $db_config = DB_escapeString(@serialize($gwconfig));
        $services = array(
            'buy_now' => 1,
            'pay_now' => 1,
            'checkout' => 1,
            'donation' => 1,
            'subscribe' => 1,
            'external' => 1,
        );
        $db_services = DB_escapeString(@serialize($services));
        $sql = "INSERT INTO {$_TABLES['paypal.gateways']}
                (id, orderby, enabled, description, config, services)
                VALUES
                ('paypal', 10, 1, 'Paypal Website Payments Standard',
                    '$db_config', '$db_services'),
                ('amazon', 20, 0, 'Amazon SimplePay', '', '$db_services')";
        //echo $sql;die;
        // ... and remove Paypal-specific configs from the main config system
        $c->del('receiver_email', $pi_name);
        $c->del('testing', $pi_name);
        $c->del('paypal_url', $pi_name);
        $c->del('prod_url', $pi_name);
        $c->del('use_css_menus', $pi_name);     // Just not used any more
        $c->del('encrypt_buttons', $pi_name);
        $c->del('prv_key', $pi_name);
        $c->del('pub_key', $pi_name);
        $c->del('pp_cert', $pi_name);
        $c->del('pp_cert_id', $pi_name);

        // Add new plugin config items
        $c->add('fs_addresses', NULL, 'fieldset', 0, 60, NULL, 0, true, $pi_name);
        $c->add('get_street', $_PP_DEFAULTS['get_street'],
                'select', 0, 60, 14, 10, true, $pi_name);
        $c->add('get_city', $_PP_DEFAULTS['get_city'],
                'select', 0, 60, 14, 20, true, $pi_name);
        $c->add('get_state', $_PP_DEFAULTS['get_state'],
                'select', 0, 60, 14, 30, true, $pi_name);
        $c->add('get_country', $_PP_DEFAULTS['get_country'],
                'select', 0, 60, 14, 40, true, $pi_name;
        $c->add('get_postal', $_PP_DEFAULTS['get_postal'],
                'select', 0, 60, 14, 50, true, $pi_name);
        $c->add('weight_unit', $_PP_DEFAULTS['weight_unit'],
                'select', 0, 0, 15, 230, true, $pi_name);
        $c->add('ena_cart', $PP_DEFAULTS['ena_cart'],
                'select', 0, 0, 2, 220, true, $pi_name);

        DB_query("UPDATE {$_TABLES['conf_values']}
                SET sort_order=80
                WHERE name='tmpdir'
                AND group_name='$pi_name'");
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Error Executing SQL: $sql", 1);
        }

        // Convert saved buttons in the product records to simple text strings
        // indicating the type of button to use.  Don't save the button in the
        // new cache table; that will be done when the button is needed.
        DB_query("UPDATE {$_TABLES['paypal.products']} SET buttons='buy_now'");

        // Create order records and associate with the existing purchase table.
        // We create our own sid to try and use the original purchase date.
        // Since this function runs so fast, there could still be duplicate
        // sid's so we check for an existing sid before trying to use it.
        // If that happens, the order_id will just be a current sid.
        $sql = "SELECT * FROM {$_TABLES['paypal.purchases']}";
        $res = DB_query($sql);
        if ($res && DB_numRows($res) > 0) {
            USES_paypal_class_Order();
            while ($A = DB_fetchArray($res, false)) {
                $dt_tm = explode(' ', $A['purchase_date']);
                list($y, $m, $d) = explode('-', $dt_tm[0]);
                list($h, $i, $s) = explode(':', $dt_tm[1]);
                $sid = $y.$m.$d.$h.$i.$s;
                $order_id = $sid . mt_rand(0, 999);
                while (DB_count($_TABLES['paypal.orders'], 'order_id', $order_id) > 0) {
                    $order_id = COM_makeSid();
                }

                // Discovered that the "price" field isn't filled in for the
                // purchase table.  Read the IPN data and use mc_gross.
                $IPN = DB_getItem($_TABLES['paypal.ipnlog'], 'ipn_data',
                        "txn_id = '" . DB_escapeString($A['txn_id']) . "'");
                $price = 0;
                if (!empty($IPN)) {
                    $data = @unserialize($IPN);
                    if ($data && isset($data['mc_gross'])) {
                        $price = (float)$data['mc_gross'];
                        if (isset($data['tax'])) {
                            $tax = (float)$data['tax'];
                            $price -= $tax;
                        } else {
                            $tax = 0;
                        }
                        if (isset($data['shipping'])) {
                            $shipping = (float)$data['shipping'];
                            $price -= $shipping;
                        } else {
                            $shipping = 0;
                        }
                        if (isset($data['handling'])) {
                            $handling = (float)$data['handling'];
                            $price -= $handling;
                        } else {
                            $handling = 0;
                        }
                    }
                }

                $ord = new \Paypal\Order($order_id);
                $ord->uid = $A['user_id'];
                $ord->order_date = DB_escapeString($A['purchase_date']);
                $ord->status = PP_STATUS_PAID;
                $ord->pmt_method = 'paypal';
                $ord->pmt_txn_id = $A['txn_id'];
                $ord->tax = $tax;
                $ord->shipping = $shipping;
                $ord->handling = $handling;
                $order_id = $ord->Save();

                // Also, split out the item number from any attributes.
                // Starting with 0.5.0 we store the actual item number
                // and options separately.
                // * PAYPAL_explode_opts() not available in this version *
                list($item_num, $options) = explode('|', $A['product_id']);
                if (!$options) $options = '';
                DB_query("UPDATE {$_TABLES['paypal.purchases']} SET
                        order_id = '" . DB_escapeString($order_id) . "',
                        price = '$price',
                        product_id = '" . DB_escapeString($item_num) . "',
                        options = '" . DB_escapeString($options) . "'
                    WHERE txn_id = '{$A['txn_id']}'");
            }
        }
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.2')) {
        $current_ver = '0.5.2';
        $error = PAYPAL_do_upgrade_sql($current_ver);
        if ($error)
            return $error;
        $c->add('centerblock', $_PP_DEFAULTS['centerblock'],
                'select', 0, 0, 2, 215, true, $pi_name);
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.4')) {
        $current_ver = '0.5.4';
        // Addes the currency table and formatting functions
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.6')) {
        $current_ver = '0.5.6';
        // SQL updates in 0.5.4 weren't included in new installation, so check
        // if they're done and add them to the upgrade process if not.
        $res = DB_query("SHOW TABLES LIKE '{$_TABLES['paypal.currency']}'",1);
        if (!$res || DB_numRows($res) < 1) {
            // Add the table
            $PP_UPGRADE['0.5.6'][] = $PP_UPGRADE['0.5.4'][0];
            // Populate with data
            $PP_UPGRADE['0.5.6'][] = $PP_UPGRADE['0.5.4'][1];
        }
        $res = DB_query("SHOW COLUMNS FROM {$_TABLES['paypal.products']}
                        LIKE 'sale_price'", 1);
        if (!$res || DB_numRows($res) < 1) {
            // Add the field to the products table
            $PP_UPGRADE['0.5.6'][] = $PP_UPGRADE['0.5.4'][2];
        }
        if (!PAYPAL_do_upgrade_sql('0.5.6')) return false;

        // Add new product defaults for onhand tracking
        $c->add('def_track_onhand', $_PP_DEFAULTS['def_track_onhand'],
                'select', 0, 30, 2, 50, true, $pi_name);
        $c->add('def_oversell', $_PP_DEFAULTS['def_oversell'],
                'select', 0, 30, 16, 60, true, $pi_name);
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.7')) {
        $current_ver = '0.5.7';
        $gid = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$pi_name} Admin'");
        if ($gid < 1)
            $gid = 1;        // default to Root if paypal group not found
        DB_query("INSERT INTO {$_TABLES['vars']}
                SET name='paypal_gid', value=$gid");
        $c->add('product_tpl_ver', $_PP_DEFAULTS['product_tpl_ver'],
                'select', 0, 30, 2, 70, true, $pi_name);
        $c->add('list_tpl_ver', $_PP_DEFAULTS['list_tpl_ver'],
                'select', 0, 30, 0, 80, true, $pi_name);
        $c->add('cache_max_age', $_PP_DEFAULTS['cache_max_age'],
                'text', 0, 40, 2, 40, true, $pi_name);

        // Create cache directory
        if (!is_dir($_PP_DEFAULTS['tmpdir'] . 'cache')) {
            @mkdir($_PP_DEFAULTS['tmpdir'] . 'cache', '0755', true);
        }

        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.8')) {
        $current_ver = '0.5.8';
        // Add terms and conditions link
        $c->add('tc_link', $_PP_DEFAULTS['tc_link'],
                'text', 0, 40, 2, 50, true, $pi_name);
        // Upgrade sql changes from owner/group/member/anon perms to group id
        // First update the group_id based on the perms.
        $sql = "SELECT cat_id,group_id,perm_group,perm_members,perm_anon
                FROM {$_TABLES['paypal.categories']}";
        $res = DB_query($sql,1);
        while ($A = DB_fetchArray($res, false)) {
            if ($A['perm_anon'] >= 2) $grp_id = 2;      // all users
            elseif ($A['perm_members'] >= 2) $grp_id = 13;  // logged-in users
            else $grp_id = $A['group_id'];
            if ($A['group_id'] != $grp_id) {
                $grp_id = (int)$grp_id;
                DB_query("UPDATE {$_TABLES['paypal.categories']}
                        SET group_id = $grp_id
                        WHERE cat_id = {$A['cat_id']}");
            }
        }
        // Remove Amazon Simplepay gateway file to prevent re-enabling
        @unlink(PAYPAL_PI_PATH . '/classes/gateways/amazon.class.php');
        if (!PAYPAL_do_upgrade_sql($current_ver, true)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.9')) {
        $current_ver = '0.5.9';
        // Add shop phone and email conf values, fix subgroup ID for shop info
        $c->add('shop_phone', '',
                'text', 10, 100, 0, 30, true, $pi_name);
        $c->add('shop_email', $_PP_DEFAULTS['shop_email'],
                'text', 10, 100, 0, 40, true, $pi_name);
        // Create default path for downloads (even if not used)
        @mkdir($_CONF['path'] . 'data/' . $pi_name . '/files', true);
        // Remove stray .htaccess file that interferes with plugin removal
        @unlink(PAYPAL_PI_PATH . '/files/.htaccess');
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.10')) {
        $current_ver = '0.5.10';
        // Changed working dir to a static config item, so make sure the
        // paths are set up in case the web admin changed them
        // Set the tmpdir to a static path, config value will be removed
        // later.
        $tmpdir = $_CONF['path'] . 'data/paypal/';
        $paths = array(
            $tmpdir,
            $tmpdir . 'keys',
            $tmpdir . 'cache',
            $tmpdir . 'files',
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
        // Delete config option and working-directory fieldset
        $c->del('tmpdir', $pi_name);
        $c->del('fs_encbtn', $pi_name);
        // Add option to show plugins on product page. Default to "1" during
        // upgrade for backward compatibility
        $c->add('show_plugins', 1, 'select', 0, 0, 2, 165, true, $pi_name);
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.11')) {
        $current_ver = '0.5.11';
        // Make sure a "uid" key doesn't exist in this table.
        // This will fail if it already doesn't exist, so ignore any error
        DB_query("ALTER TABLE {$_TABLES['paypal.address']}
                DROP KEY `uid`", 1);
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.12')) {
        $current_ver = '0.5.12';
        $c->del('download_path', $pi_name);

        // Previously, categories were not required. With the MPTT method,
        // there must be at least one.
        $cats = DB_count($_TABLES['paypal.categories']);
        if ($cats == 0) {
            $sql = "INSERT INTO {$_TABLES['paypal.categories']}
                    (cat_id, cat_name, description)
                VALUES
                    (1, 'Home', 'Root Category', 1, 2)";
            DB_query($sql);
            $sql = "UPDATE {$_TABLES['paypal.products']}
                    SET cat_id = 1 WHERE cat_id = 0";
            DB_query($sql);
        }
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        // Rebuild the tree after the lft/rgt category fields are added.
        Paypal\Category::rebuildTree();
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!PAYPAL_do_set_version($installed_ver)) return false;
    }

    CTL_clearCache($pi_name);
    COM_errorLog("Successfully updated the {$_PP_CONF['pi_display_name']} Plugin", 1);
    return true;
}


/**
*   Actually perform any sql updates.
*   Gets the sql statements from the $UPGRADE array defined (maybe)
*   in the SQL installation file.
*
*   @since  version 0.4.0
*   @param  string  $version    Version being upgraded TO
*   @param  boolean $ignore_error   True to ignore SQL errors.
*   @param  array   $sql        Array of SQL statement(s) to execute
*/
function PAYPAL_do_upgrade_sql($version, $ignore_error=false)
{
    global $_TABLES, $_PP_CONF, $PP_UPGRADE;

    // If no sql statements passed in, return success
    if (!is_array($PP_UPGRADE[$version]))
        return true;

    // Execute SQL now to perform the upgrade
    COM_errorLog("--- Updating Paypal to version $version", 1);
    foreach($PP_UPGRADE[$version] as $sql) {
        COM_errorLOG("Paypal Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Paypal Plugin update", 1);
            if (!$ignore_error){
                return false;
            }
        }
    }
    COM_errorLog("--- Paypal plugin SQL update to version $version done", 1);
    return true;
}


/**
*   Update the plugin version number in the database.
*   Called at each version upgrade to keep up to date with
*   successful upgrades.
*
*   @param  string  $ver    New version to set
*   @return boolean         True on success, False on failure
*/
function PAYPAL_do_set_version($ver)
{
    global $_TABLES, $_PP_CONF;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '{$_PP_CONF['pi_version']}',
            pi_gl_version = '{$_PP_CONF['gl_version']}',
            pi_homepage = '{$_PP_CONF['pi_url']}'
        WHERE pi_name = '{$_PP_CONF['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_PP_CONF['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}

?>

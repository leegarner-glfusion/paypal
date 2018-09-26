<?php
/**
*   Upgrade routines for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

global $_CONF, $_PP_CONF;

/** Include the table creation strings */
require_once __DIR__ . "/sql/mysql_install.php";

/**
*   Perform the upgrade starting at the current version.
*
*   @since  version 0.4.0
*   @return integer                 Error code, 0 for success
*/
function PAYPAL_do_upgrade($dvlp = false)
{
    global $_TABLES, $_CONF, $_PP_CONF, $paypalConfigData, $PP_UPGRADE, $_PLUGIN_INFO, $_DB_name;

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
        if (is_file(__DIR__ . '/config.php')) {
            COM_errorLog('Renaming old config.php file to ' . __DIR . '/config.old.php', 1);
            if (!rename(__DIR__ . '/config.php', $pi_path . '/config.old.php')) {
                COM_errorLog("Failed to rename old config.php file.  Manual intervention needed", 1);
            }
        }
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.1')) {
        // upgrade to 0.4.1
        $current_ver = '0.4.1';
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
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
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.4')) {
        $current_ver = '0.4.4';
        // Remove individual block selections and combine into one
        $displayblocks = 0;
        if ($_PP_CONF['leftblocks'] == 1) $displayblocks += 1;
        if ($_PP_CONF['rightblocks'] == 1) $displayblocks += 2;

        // This is here since there are specific config values to be set
        // leftblocks and rightblocks will be deleted on PAYPAL_update_config().
        $c = config::get_instance();
        $c->add('displayblocks', $displayblocks,
                'select', 0, 0, 13, 210, true, $pi_name);
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4.5')) {
        $current_ver = '0.4.5';
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
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
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
        if (!_PP_tableHasColumn('paypal.products', 'sale_price')) {
            // Add the field to the products table
            $PP_UPGRADE['0.5.6'][] = $PP_UPGRADE['0.5.4'][2];
        }
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
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
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.8')) {
        $current_ver = '0.5.8';
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
        @unlink(__DIR__ . '/classes/gateways/amazon.class.php');
        if (!PAYPAL_do_upgrade_sql($current_ver)) return false;
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.5.9')) {
        $current_ver = '0.5.9';
        // Create default path for downloads (even if not used)
        @mkdir($_CONF['path'] . 'data/' . $pi_name . '/files', true);
        // Remove stray .htaccess file that interferes with plugin removal
        @unlink(__DIR__ . '/files/.htaccess');
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

    if (!COM_checkVersion($current_ver, '0.6.0')) {
        $current_ver = '0.6.0';

        // Previously, categories were not required. With the MPTT method,
        // there must be at least one. Collect all the categories, increment
        // the ID and parent_id, add the home category, and update the products
        // to match.
        if (!_PPtableHasColumn('paypal.categories', 'rgt')) { // Category table hasn't been updated yet
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.categories']} ADD `lft` smallint(5) unsigned NOT NULL DEFAULT '0'";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.categories']} ADD `rgt` smallint(5) unsigned NOT NULL DEFAULT '0'";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.categories']} ADD KEY `cat_lft` (`lft`)";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.categories']} ADD KEY `cat_rgt` (`rgt`)";
            $res = DB_query("SELECT * FROM {$_TABLES['paypal.categories']}");
            $cats = array();
            if ($res) {
                while ($A = DB_fetchArray($res, false)) {
                    $cats[] = $A;
                }
                $sql_cats = array();
                foreach ($cats as $id=>$cat) {
                    $cats[$id]['cat_id']++;
                    $cats[$id]['parent_id']++;
                    $sql_cats[] = "('" . implode("','", $cats[$id]) . "')";
                }
                $sql_cats = implode(', ', $sql_cats);
                $PP_UPGRADE[$current_ver][] = "TRUNCATE {$_TABLES['paypal.categories']}";
                $PP_UPGRADE[$current_ver][] = "INSERT INTO {$_TABLES['paypal.categories']}
                        (cat_id, cat_name, description, grp_access, lft, rgt)
                    VALUES
                        (1, 'Home', 'Root Category', 2, 1, 2)";
                if (!empty($sql_cats)) {
                    $PP_UPGRADE[$current_ver][] = "INSERT INTO {$_TABLES['paypal.categories']}
                            (cat_id, parent_id, cat_name, description, enabled, grp_access, image)
                        VALUES $sql_cats";
                }
                $PP_UPGRADE[$current_ver][]= "UPDATE {$_TABLES['paypal.products']} SET cat_id = cat_id + 1";
            }
            $add_cat_mptt = true;
        } else {
            $add_cat_mptt = false;
        }

        // Update the order_date to an int if not already done
        if (_PPcolumnType('paypal.orders', 'order_date') == 'datetime') {
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.orders']} CHANGE order_date order_date_old datetime";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.orders']} ADD order_date int(11) unsigned NOT NULL DEFAULT 0 AFTER uid";
            $PP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['paypal.orders']} SET
                last_mod = NOW(),
                order_date = UNIX_TIMESTAMP(CONVERT_TZ(`order_date_old`, '+00:00', @@session.time_zone))";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.orders']} DROP order_date_old";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.orders']} ADD KEY (`order_date`)";
        }
        if (_PPcolumnType('paypal.purchases', 'expiration') == 'datetime') {
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.purchases']} DROP key purchases_expiration";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.purchases']} CHANGE expiration exp_old datetime";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.purchases']} ADD expiration int(11) unsigned not null default 0 after status";
            $PP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['paypal.purchases']} SET
                expiration = UNIX_TIMESTAMP(CONVERT_TZ(`exp_old`, '+00:00', @@session.time_zone))";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.purchases']} DROP exp_old";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.purchases']} ADD KEY `purchases_expiration` (`expiration`)";
        }
        // Sales and discounts have been moved to another table. Collect any active sales
        // and move them over.
        if (_PPtableHasColumn('paypal.products', 'sale_price')) {        // Sales haven't been moved to new table yet
            $sql = "SELECT id, sale_price, sale_beg, sale_end, price FROM {$_TABLES['paypal.products']}";
            $res = DB_query($sql);
            if ($res) {
                $sql = array();
                while ($A = DB_fetchArray($res, false)) {
                    $s_price = (float)$A['sale_price'];
                    if ($s_price != 0) {
                        $price = (float)$A['price'];
                        $discount = (float)($price - $s_price);
                        // Fix dates to fit in an Unix timestamp
                        foreach (array('sale_beg', 'sale_end') as $key) {
                            if ($A[$key] < '1970-01-01') {
                                $A[$key] = '1970-01-01';
                            } elseif ($A[$key] > '2037-12-31') {
                                $A[$key] = '2017-12-31';
                            }
                        }
                        $st = new Date($A['sale_beg'], $_CONF['timezone']);
                        $end = new Date($A['sale_end'] . ' 23:59:59', $_CONF['timezone']);
                        $sql[] = "('product', '{$A['id']}', '{$st->toUnix()}', '{$end->toUnix()}', 'amount', '$discount')";
                    }
                }
                if (!empty($sql)) {
                    $sql = implode(',', $sql);
                    $PP_UPGRADE['0.6.0'][] = "INSERT INTO {$_TABLES['paypal.sales']}
                            (item_type, item_id, start, end, discount_type, amount)
                            VALUES $sql";
                }
            }
        }

        // Update workflows table with can_delete flag, and change enabled values if this is the first pass.
        if (!_PPtableHasColumn('paypal.workflows', 'can_disable')) {
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.workflows']} ADD `can_disable` tinyint(1) unsigned NOT NULL DEFAULT '1'";
            $PP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['paypal.workflows']} SET can_disable = 0, enabled = 3 WHERE wf_name = 'viewcart'";
            $PP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['paypal.workflows']} SET enabled = 3 WHERE enabled = 1";
        }

        if (!_PPtableHasColumn('paypal.orderstatus', 'notify_admin')) {
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.orderstatus']} ADD `notify_admin` TINYINT(1) NOT NULL DEFAULT '0'";
            $PP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['paypal.orderstatus']} SET notify_admin = 1 WHERE name = 'paid'";
        }

        // Change the log table to use Unix timestamps.
        if (_PPcolumnType('paypal.order_log', 'ts') == 'datetime') {
            // 1. Change to datetime so timestamp doesn't get updated by these changes
            // 2. Add an integer field to get the timestamp value
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.order_log']} CHANGE ts ts_old datetime";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.order_log']} ADD ts int(11) unsigned after id";
            // 3. Set the int field to the Unix timestamp
            $PP_UPGRADE[$current_ver][] = "UPDATE {$_TABLES['paypal.order_log']} SET ts = UNIX_TIMESTAMP(CONVERT_TZ(`ts_old`, '+00:00', @@session.time_zone))";
            // 4. Drop the old timestamp field
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.order_log']} DROP ts_old";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.order_log']} DROP KEY `order_id`";
            $PP_UPGRADE[$current_ver][] = "ALTER TABLE {$_TABLES['paypal.order_log']} ADD KEY `order_id` (`order_id`, `ts`)";
        }

        if (!PAYPAL_do_upgrade_sql($current_ver, $dvlp)) return false;
        // Rebuild the tree after the lft/rgt category fields are added.
        if ($add_cat_mptt) {
            \Paypal\Category::rebuildTree();
        }
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }

    PAYPAL_update_config();
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!PAYPAL_do_set_version($current_ver)) return false;
    }
    \Paypal\Cache::clear();
    PAYPAL_remove_old_files();
    CTL_clearCache();   // clear cache to ensure CSS updates come through
    COM_errorLog("Successfully updated the {$_PP_CONF['pi_display_name']} Plugin", 1);
    // Set a message in the session to replace the "has not been upgraded" message
    COM_setMsg("Paypal Plugin has been updated to $current_ver", 'info', 1);
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
function PAYPAL_do_upgrade_sql($version, $ignore_error = false)
{
    global $_TABLES, $_PP_CONF, $PP_UPGRADE;

    // If no sql statements passed in, return success
    if (!is_array($PP_UPGRADE[$version]))
        return true;

    // Execute SQL now to perform the upgrade
    COM_errorLog("--- Updating Paypal to version $version", 1);
    foreach($PP_UPGRADE[$version] as $sql) {
        COM_errorLog("Paypal Plugin $version update: Executing SQL => $sql");
        try {
            DB_query($sql, '1');
            if (DB_error()) {
                // check for error here for glFusion < 2.0.0
                COM_errorLog('SQL Error during update', 1);
                if (!$ignore_error) return false;
            }
        } catch (Exception $e) {
            COM_errorLog('SQL Error ' . $e->getMessage(), 1);
            if (!$ignore_error) return false;
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
    global $_TABLES, $_PP_CONF, $_PLUGIN_INFO;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '$ver',
            pi_gl_version = '{$_PP_CONF['gl_version']}',
            pi_homepage = '{$_PP_CONF['pi_url']}'
        WHERE pi_name = '{$_PP_CONF['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_PP_CONF['pi_display_name']} Plugin version",1);
        return false;
    } else {
        COM_errorLog("{$_PP_CONF['pi_display_name']} version set to $ver");
        // Set in-memory config vars to avoid tripping PP_isMinVersion();
        $_PP_CONF['pi_version'] = $ver;
        $_PLUGIN_INFO[$_PP_CONF['pi_name']]['pi_version'] = $ver;
        return true;
    }
}


/**
 * Update the plugin configuration
 */
function PAYPAL_update_config()
{
    USES_lib_install();

    require_once __DIR__ . '/install_defaults.php';
    _update_config('paypal', $paypalConfigData);
}


/**
 *   Remove deprecated files
 *   Errors in unlink() and rmdir() are ignored.
 */
function PAYPAL_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/paypal
        __DIR__ => array(
            // 0.6.0
            'language/authorizenetsim_english.php',
            'templates/viewcart.uikit.thtml',
            'templates/detail/v2/product_detail.thtml',
            'templates/detail/v1/product_detail.thtml',
            'templates/list/v1/product_list_item.thtml',
            'templates/list/v2/product_list_item.thtml',
            'templates/order.uikit.thtml',
            'classes/paymentgw.class.php',
            'classes/ppFile.class.php',
            'classes/ipn/internal_ipn.class.php',
            'classes/ipn/paypal_ipn.class.php',
            'classes/ipn/authorizenet_sim.class.php',
            'classes/ipn/BaseIPN.class.php',
        ),
        // public_html/paypal
        $_CONF['path_html'] . 'paypal' => array(
            'ipn/paypal_ipn.php',
            'ipn/authorizenetsim_ipn.php',
        ),
        // admin/plugins/paypal
        $_CONF['path_html'] . 'admin/plugins/paypal' => array(
        ),
    );

    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        // Files that were renamed, changing case only.
        // Only delete thes on non-windows systems.
        $files = array(
            'classes/attribute.class.php',
            'classes/cart.class.php',
            'classes/category.class.php',
            'classes/currency.class.php',
            'classes/order.class.php',
            'classes/orderstatus.class.php',
            'classes/product.class.php',
            'classes/workflow.class.php',
        );
        $paths[__DIR__] = array_merge($paths[__DIR__], $files);

        // The gateways class dir has been renamed to Gatways for better namespacing.
        // Remove the old class dir and files, only if not on Windows
        $dir = __DIR__ . '/classes/gateways';
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.','..'));
            foreach ($files as $file) {
                $path = "$dir/$file";
                if (is_file($path)) @unlink($path);
            }
            @rmdir($dir);
        }
    }
    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            @unlink("$path/$file");
        }
    }
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in paypal.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _PPtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $col_name = DB_escapeString($col_name);
    $res = DB_query("SHOW COLUMNS FROM {$_TABLES[$table]} LIKE '$col_name'");
    return DB_numRows($res) == 0 ? false : true;
}


/**
 * Get the datatype for a specific column.
 *
 * @param   string  $table      Table Key, defined in paypal.php
 * @param   string  $col_name   Column name to check
 * @return  string      Column datatype
 */
function _PPcolumnType($table, $col_name)
{
    global $_TABLES, $_DB_name;

    $col_name = DB_escapeString($col_name);
    $col_type = DB_getItem('INFORMATION_SCHEMA.COLUMNS', 'DATA_TYPE',
            "table_schema = '{$_DB_name}'
            AND table_name = '{$_TABLES[$table]}'
            AND COLUMN_NAME = '$col_name'");
    return $col_type;
}

?>

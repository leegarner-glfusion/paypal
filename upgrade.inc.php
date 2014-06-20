<?php
/**
*   Upgrade routines for the Paypal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.4.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*   GNU Public License v2 or later
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
*   @param  string  $current_ver    Current version to be upgraded
*   @return integer                 Error code, 0 for success
*/
function PAYPAL_do_upgrade($current_ver)
{
    global $_TABLES, $_CONF, $_PP_CONF, $_PP_DEFAULTS;

    $error = 0;

    // Get the config instance, several upgrades might need it
    $c = config::get_instance();

    if ($current_ver < '0.2') {
        // upgrade to 0.2.2
        $error = PAYPAL_do_upgrade_sql('0.2');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.4.0') {
        // upgrade to 0.4.0
        $error = PAYPAL_do_upgrade_sql('0.4.0');
        if (!plugin_initconfig_paypal())
            $error = 1;

        // Migrate existing categories to the new category table
        $r = DB_query("SELECT DISTINCT category
                FROM {$_TABLES['paypal.products']}
                WHERE category <> '' and category IS NOT NULL");
        if (DB_error()) {
            COM_errorLog("Could not retrieve old categories");
            return 1;
        }
        if (DB_numRows($r) > 0) {
            while ($A = DB_fetchArray($r, false)) {
                DB_query("INSERT INTO {$_TABLES['paypal.categories']}
                        (cat_name)
                    VALUES ('{$A['category']}')");
                if (DB_error()) {
                    COM_errorLog("Could not add new category {$A['category']}");
                    return 1;
                }
                $cats[$A['category']] = DB_insertID();
            }
            // Now populate the cross-reference table
            $r = DB_query("SELECT id, category
                    FROM {$_TABLES['paypal.products']}");
            if (DB_error()) {
                COM_errorLog("Error retrieving category data from products");
                return 1;
            }
            if (DB_numRows($r) > 0) {
                while ($A = DB_fetchArray($r, false)) {
                    DB_query("UPDATE {$_TABLES['paypal.products']}
                        SET cat_id = '{$cats[$A['category']]}'
                        WHERE id = '{$A['id']}'");
                    if (DB_error()) {
                        COM_errorLog("Error updating prodXcat table");
                        return 1;
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
        $pi_path = $_CONF['path'] . '/plugins/' . $_PP_CONF['pi_name'];
        if (is_file($pi_path . '/config.php')) {
            COM_errorLog("Renaming old config.php file to $pi_path/config.old.php");
            if (!rename($pi_path . '/config.php', $pi_path . '/config.old.php')) {
                COM_errorLog("Failed to rename old config.php file.  Manual intervention needed");
            }
        }

    }

    if ($current_ver < '0.4.1') {
        // upgrade to 0.4.1
        $error = PAYPAL_do_upgrade_sql('0.4.1');

        // Add new configuration items
        if ($error)
            return $error;

        if ($c->group_exists($_PP_CONF['pi_name'])) {
             $c->add('blk_random_limit', $_PP_DEFAULTS['blk_random_limit'],
                    'text', 0, 30, 2, 40, true, $_PP_CONF['pi_name']);
            $c->add('blk_featured_limit', $_PP_DEFAULTS['blk_featured_limit'],
                    'text', 0, 30, 2, 50, true, $_PP_CONF['pi_name']);
            $c->add('blk_popular_limit', $_PP_DEFAULTS['blk_popular_limit'],
                    'text', 0, 30, 2, 60, true, $_PP_CONF['pi_name']);

            $c->add('fs_debug', NULL, 'fieldset', 0, 50, NULL, 0, true, 
                $_PP_CONF['pi_name']);
            $c->add('debug', $_PP_DEFAULTS['debug'],
                'select', 0, 50, 2, 10, true, $_PP_CONF['pi_name']);
        }
    }

    if ($current_ver < '0.4.2') {
        // upgrade to 0.4.2
        $error = PAYPAL_do_upgrade_sql('0.4.2');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.4.3') {
        // upgrade to 0.4.3
        // this adds a field that was possibly missing in the initial
        // installation, but could have been added in the 0.4.1 update. So,
        // an error is to be expected and ignored
       DB_query("ALTER TABLE {$_TABLES['paypal.purchases']}
                ADD description varchar(255) AFTER product_id", 1);

        if ($c->group_exists($_PP_CONF['pi_name'])) {
            $c->add('def_expiration', $_PP_DEFAULTS['def_expiration'],
                'text', 0, 30, 0, 40, true, $_PP_CONF['pi_name']);
        }
    }

    if ($current_ver < '0.4.4') {
        // Remove individual block selections and combine into one
        $displayblocks = 0;
        if ($_PP_CONF['leftblocks'] == 1) $displayblocks += 1;
        if ($_PP_CONF['rightblocks'] == 1) $displayblocks += 2;

        $c->del('leftblocks','paypal');
        $c->del('rightblocks','paypal');
        $c->add('displayblocks', $displayblocks,
                'select', 0, 0, 13, 210, true, $_PP_CONF['pi_name']);
        $c->add('debug_ipn', $_PP_DEFAULTS['debug_ipn'],
                'select', 0, 50, 2, 20, true, $_PP_CONF['pi_name']);
        $error = PAYPAL_do_upgrade_sql('0.4.4');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.4.5') {
        // Add notification email override
        $c->add('admin_email_addr', $_PP_DEFAULTS['admin_email_addr'],
                'text', 0, 0, 0, 40, true, $_PP_CONF['pi_name']);
        $error = PAYPAL_do_upgrade_sql('0.4.5');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.4.6') {
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
                ('$id', 'paypal', '$button')", 1);
        }
        $error = PAYPAL_do_upgrade_sql('0.4.6');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.5.0') {

        // Perform the main database upgrades
        // The first few lines get the schema updated for elements that
        // may have been missed (0.4.4 wasn't updated properly).
        // Errors need to be ignored for these.
        DB_query("ALTER TABLE {$_TABLES['paypal.products']}
                ADD options text after show_popular", 1);
        DB_query("ALTER TABLE {$_TABLES['paypal.purchases']}
                ADD token varchar(40) after price", 1);
        $error = PAYPAL_do_upgrade_sql('0.5.0');
        if ($error) {
            return $error;
        }

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
        $c->del('receiver_email', 'paypal');
        $c->del('testing', 'paypal');
        $c->del('paypal_url', 'paypal');
        $c->del('prod_url', 'paypal');
        $c->del('use_css_menus', 'paypal');     // Just not used any more
        $c->del('encrypt_buttons', 'paypal');
        $c->del('prv_key', 'paypal');
        $c->del('pub_key', 'paypal');
        $c->del('pp_cert', 'paypal');
        $c->del('pp_cert_id', 'paypal');

        // Add new plugin config items
        $c->add('fs_addresses', NULL, 'fieldset', 0, 60, NULL, 0, true, 
                $_PP_CONF['pi_name']);
        $c->add('get_street', $_PP_DEFAULTS['get_street'],
                'select', 0, 60, 14, 10, true, $_PP_CONF['pi_name']);
        $c->add('get_city', $_PP_DEFAULTS['get_city'],
                'select', 0, 60, 14, 20, true, $_PP_CONF['pi_name']);
        $c->add('get_state', $_PP_DEFAULTS['get_state'],
                'select', 0, 60, 14, 30, true, $_PP_CONF['pi_name']);
        $c->add('get_country', $_PP_DEFAULTS['get_country'],
                'select', 0, 60, 14, 40, true, $_PP_CONF['pi_name']);
        $c->add('get_postal', $_PP_DEFAULTS['get_postal'],
                'select', 0, 60, 14, 50, true, $_PP_CONF['pi_name']);
        $c->add('weight_unit', $_PP_DEFAULTS['weight_unit'],
                'select', 0, 0, 15, 230, true, $_PP_CONF['pi_name']);
        $c->add('ena_cart', $PP_DEFAULTS['ena_cart'],
                'select', 0, 0, 2, 220, true, $_PP_CONF['pi_name']);

        DB_query("UPDATE {$_TABLES['conf_values']}
                SET sort_order=80
                WHERE name='tmpdir'
                AND group_name='paypal'");
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Error Executing SQL: $sql");
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
            USES_paypal_class_order();
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

                $ord = new ppOrder($order_id);
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

    }

    if ($current_ver < '0.5.2') {
        $error = PAYPAL_do_upgrade_sql('0.5.2');
        if ($error)
            return $error;
        $c->add('centerblock', $_PP_DEFAULTS['centerblock'],
                'select', 0, 0, 2, 215, true, $_PP_CONF['pi_name']);
    }

    if ($current_ver < '0.5.4') {
        // Addes the currency table and formatting functions
        $error = PAYPAL_do_upgrade_sql('0.5.4');
        if ($error) {
            return $error;
        }
    }

    return $error;

}


/**
*   Actually perform any sql updates.
*   Gets the sql statements from the $UPGRADE array defined (maybe)
*   in the SQL installation file.
*
*   @since  version 0.4.0
*   @param  string  $version    Version being upgraded TO
*   @param  array   $sql        Array of SQL statement(s) to execute
*/
function PAYPAL_do_upgrade_sql($version='')
{
    global $_TABLES, $_PP_CONF, $PP_UPGRADE;

    $error = 0;

    // If no sql statements passed in, return success
    if (!is_array($PP_UPGRADE[$version]))
        return $error;

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Paypal to version $version");
    foreach($PP_UPGRADE[$version] as $sql) {
        COM_errorLOG("Paypal Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Paypal Plugin update",1);
            $error = 1;
            break;
        }
    }

    return $error;

}


?>

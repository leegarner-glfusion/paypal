<?php
/**
*   Configuration Defaults Paypal plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*
*   Based on the gl-paypal Plugin for Geeklog CMS.
*   @copyright Copyright (C) 2005-2006 Vincent Furia <vinny01@users.sourceforge.net>
*/

// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Paypal plugin configuration defaults
*   @global array */
global $_PP_DEFAULTS;
$_PP_DEFAULTS = array();

// Currency to use for transactions.  Any valid PayPal-supported currency can be used.
$_PP_DEFAULTS['currency'] = 'USD';

/**
* Allow anonymous visitors to purchase?
* Anonymous users won't have access to purchase history or to direct downloads
*/
$_PP_DEFAULTS['anonymous_buy'] = true;

/** Admin email override.
*   If this is not empty, notification email will be sent to this
*   address instead of $_CONF['site_mail']
*/
$_PP_DEFAULTS['admin_email_addr'] = '';

/**
 * true to enable display on menu, false diables
 * NOTE: requires 'plugins' to be included in $_CONF['menu_elements'] array
 */
$_PP_DEFAULTS['menuitem'] = 1;

/**
 * Number of products to display per page.  0 indicates that all products should
 * be displayed on a single page.
 */
$_PP_DEFAULTS['maxPerPage'] = 10;

/**
 * Number of columns of categories to display.  Set to 0 to disable categories.
 * If you don't have any categories set, this setting is meaningless.
 */
$_PP_DEFAULTS['categoryColumns'] = 3;

/**
*   Include plugin products on the main catalog page? 1 = yes, 0 = no
*/
$_PP_DEFAULTS['show_plugins'] = 0;

/**
 * Ordering of products in lists
 * Values must be a column of the produts table: 'name', 'price', 'id'
 * Values can be modified by either 'ASC' or 'DESC': 'name DESC'
 */
$_PP_DEFAULTS['order'] = 'name';


// Image-related values
$_PP_DEFAULTS['max_images'] = 3;
$_PP_DEFAULTS['image_dir'] = $_CONF['path_html'] . 'paypal/images/products';
$_PP_DEFAULTS['max_thumb_size'] = 100;
$_PP_DEFAULTS['img_max_width'] = 800;
$_PP_DEFAULTS['img_max_height'] = 600;
$_PP_DEFAULTS['max_image_size'] = 4194304;

// Max size for file uploads, in MB
$_PP_DEFAULTS['max_file_size'] = 8;

// Comments supported?
$_PP_DEFAULTS['ena_comments'] = 1;

// Enable ratings?
$_PP_DEFAULTS['ena_ratings'] = 1;
$_PP_DEFAULTS['anon_can_rate'] = 0;

// Control which blocks to display- both by default
$_PP_DEFAULTS['displayblocks'] = 3;

// Debugging?
$_PP_DEFAULTS['debug_ipn'] = 0;
$_PP_DEFAULTS['debug'] = 0;

// Various defaults for new products:
$_PP_DEFAULTS['def_taxable'] = 1;
$_PP_DEFAULTS['def_enabled'] = 1;
$_PP_DEFAULTS['def_featured'] = 0;
$_PP_DEFAULTS['def_oversell'] = 0;
$_PP_DEFAULTS['def_track_onhand'] = 0;  // Track qty onhand?

// Limits on number of products shown in blocks:
$_PP_DEFAULTS['blk_random_limit'] = 1;
$_PP_DEFAULTS['blk_featured_limit'] = 1;
$_PP_DEFAULTS['blk_popular_limit'] = 1;

// Default expiration days for downloads (days)
$_PP_DEFAULTS['def_expiration'] = 3;

// Required address fields for shipping & billing workflows
// 0 = Don't Collect, 1 = optional, 2 = required
$_PP_DEFAULTS['get_street'] = 2;
$_PP_DEFAULTS['get_city'] = 2;
$_PP_DEFAULTS['get_state'] = 2;
$_PP_DEFAULTS['get_country'] = 2;
$_PP_DEFAULTS['get_postal'] = 1;


$_PP_DEFAULTS['ena_cart'] = 1;  // Enable the shopping cart by default
$_PP_DEFAULTS['weight_unit'] = 'lbs';   // Unit of weight measure (lb/kg)

$_PP_DEFAULTS['shop_name'] = $_CONF['site_name'];
$_PP_DEFAULTS['shop_addr'] = '';
$_PP_DEFAULTS['shop_email'] = isset($_CONF['site_mail']) ? $_CONF['site_mail'] : '';
$_PP_DEFAULTS['tax_rate'] = 0;      // Default tax rate

$_PP_DEFAULTS['centerblock'] = 0;   // Enable centerblock?

$_PP_DEFAULTS['product_tpl_ver'] = 'v1';   // default product detail template
$_PP_DEFAULTS['list_tpl_ver'] = 'v1';   // default product list item template
$_PP_DEFAULTS['cache_max_age'] = 900;   // default cache file age, 15 minutes
$_PP_DEFAULTS['tc_link'] = '';     // Link to terms and conditions

// Default number of days for unfinished orders to remain
$_PP_DEFAULTS['days_purge_cart'] = 14;
$_PP_DEFAULTS['days_purge_pending'] = 180;

$_PP_DEFAULTS['gc_enabled'] = 0;        // enable gift cards? 1=yes, 0=no
$_PP_DEFAULTS['gc_exp_days'] = 365;     // default expiration for gift cards
$_PP_DEFAULTS['gc_mask'] = 'XXXX-XXXX-XXXX-XXXX';
$_PP_DEFAULTS['gc_letters'] = 1;
$_PP_DEFAULTS['gc_numbers'] = 1;
$_PP_DEFAULTS['gc_symbols'] = 0;
$_PP_DEFAULTS['gc_length'] = 10;
$_PP_DEFAULTS['gc_prefix'] = '';
$_PP_DEFAULTS['gc_suffix'] = '';

$_PP_DEFAULTS['purge_sale_prices'] = 1; // purge expired sale prices?


/**
 *  Initialize Paypal plugin configuration
 *
 *  Creates the database entries for the configuation if they don't already
 *  exist. Initial values will be taken from $_PP_CONF if available (e.g. from
 *  an old config.php), uses $_PP_DEFAULTS otherwise.
 *
 *  @param  integer $group_id   Group ID to use as the plugin's admin group
 *  @return boolean             true: success; false: an error occurred
 */
function plugin_initconfig_paypal($group_id = 0)
{
    global $_CONF, $_PP_CONF, $_PP_DEFAULTS;

    if (is_array($_PP_CONF) && (count($_PP_CONF) > 1)) {
        $_PP_DEFAULTS = array_merge($_PP_DEFAULTS, $_PP_CONF);
    }

    // Use configured default if a valid group ID wasn't presented
    if ($group_id == 0)
        $group_id = $_PP_DEFAULTS['defgrp'];

    $c = config::get_instance();
    if (!$c->group_exists($_PP_CONF['pi_name'])) {

        // Main configuration, shop email addresses, encrypted button settings
        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('fs_main', NULL, 'fieldset', 0, 0, NULL, 0, true,
                $_PP_CONF['pi_name']);

        $c->add('admin_email_addr', $_PP_DEFAULTS['admin_email_addr'],
                'text', 0, 0, 0, 40, true, $_PP_CONF['pi_name']);
        $c->add('currency', $_PP_DEFAULTS['currency'],
                'select', 0, 0, 4, 50, true, $_PP_CONF['pi_name']);
        $c->add('anon_buy', $_PP_DEFAULTS['anonymous_buy'],
                'select', 0, 0, 2, 60, true, $_PP_CONF['pi_name']);
        $c->add('menuitem', $_PP_DEFAULTS['menuitem'],
                'select', 0, 0, 2, 120, true, $_PP_CONF['pi_name']);
        $c->add('order', $_PP_DEFAULTS['order'],
                'select', 0, 0, 5, 130, true, $_PP_CONF['pi_name']);
        $c->add('prod_per_page', $_PP_DEFAULTS['maxPerPage'],
                'text', 0, 0, 0, 150, true, $_PP_CONF['pi_name']);
        $c->add('cat_columns', $_PP_DEFAULTS['categoryColumns'],
                'text', 0, 0, 0, 160, true, $_PP_CONF['pi_name']);
        $c->add('show_plugins', $_PP_DEFAULTS['show_plugins'],
                'text', 0, 0, 2, 165, true, $_PP_CONF['pi_name']);
        $c->add('ena_comments', $_PP_DEFAULTS['ena_comments'],
                'select', 0, 0, 2, 180, true, $_PP_CONF['pi_name']);
        $c->add('ena_ratings', $_PP_DEFAULTS['ena_ratings'],
                'select', 0, 0, 2, 190, true, $_PP_CONF['pi_name']);
        $c->add('anon_can_rate', $_PP_DEFAULTS['anon_can_rate'],
                'select', 0, 0, 2, 200, true, $_PP_CONF['pi_name']);
        $c->add('displayblocks', $_PP_DEFAULTS['displayblocks'],
                'select', 0, 0, 13, 210, true, $_PP_CONF['pi_name']);
        $c->add('centerblock', $_PP_DEFAULTS['centerblock'],
                'select', 0, 0, 2, 215, true, $_PP_CONF['pi_name']);
        $c->add('ena_cart', $_PP_DEFAULTS['ena_cart'],
                'select', 0, 0, 2, 220, true, $_PP_CONF['pi_name']);
        $c->add('weight_unit', $_PP_DEFAULTS['weight_unit'],
                'select', 0, 0, 15, 230, true, $_PP_CONF['pi_name']);
        $c->add('tc_link', $_PP_DEFAULTS['tc_link'],
                'text', 0, 0, 2, 240, true, $_PP_CONF['pi_name']);
        $c->add('days_purge_cart', $_PP_DEFAULTS['days_purge_cart'],
                'text', 0, 0, 2, 250, true, $_PP_CONF['pi_name']);
        $c->add('days_purge_pending', $_PP_DEFAULTS['days_purge_pending'],
                'text', 0, 0, 2, 260, true, $_PP_CONF['pi_name']);

        // Path and image handling
        $c->add('fs_paths', NULL, 'fieldset', 0, 10, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('max_images', $_PP_DEFAULTS['max_images'],
                'text', 0, 10, 0, 10, true, $_PP_CONF['pi_name']);
        $c->add('max_image_size', $_PP_DEFAULTS['max_image_size'],
                'text', 0, 10, 0, 20, true, $_PP_CONF['pi_name']);
        $c->add('max_thumb_size', $_PP_DEFAULTS['max_thumb_size'],
                'text', 0, 10, 0, 30, true, $_PP_CONF['pi_name']);
        $c->add('img_max_width', $_PP_DEFAULTS['img_max_width'],
                'text', 0, 10, 0, 40, true, $_PP_CONF['pi_name']);
        $c->add('img_max_height', $_PP_DEFAULTS['img_max_height'],
                'text', 0, 10, 0, 50, true, $_PP_CONF['pi_name']);
        $c->add('max_file_size', $_PP_DEFAULTS['max_file_size'],
                'text', 0, 10, 0, 70, true, $_PP_CONF['pi_name']);

        $c->add('fs_prod_defaults', NULL, 'fieldset', 0, 30, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('def_enabled', $_PP_DEFAULTS['def_enabled'],
                'select', 0, 30, 2, 10, true, $_PP_CONF['pi_name']);
        $c->add('def_taxable', $_PP_DEFAULTS['def_taxable'],
                'select', 0, 30, 2, 20, true, $_PP_CONF['pi_name']);
        $c->add('def_featured', $_PP_DEFAULTS['def_featured'],
                'select', 0, 30, 2, 30, true, $_PP_CONF['pi_name']);
        $c->add('def_expiration', $_PP_DEFAULTS['def_expiration'],
                'text', 0, 30, 0, 40, true, $_PP_CONF['pi_name']);
        $c->add('def_track_onhand', $_PP_DEFAULTS['def_track_onhand'],
                'select', 0, 30, 2, 50, true, $_PP_CONF['pi_name']);
        $c->add('def_oversell', $_PP_DEFAULTS['def_oversell'],
                'select', 0, 30, 16, 60, true, $_PP_CONF['pi_name']);
        $c->add('product_tpl_ver', $_PP_DEFAULTS['product_tpl_ver'],
                'select', 0, 30, 0, 70, true, $_PP_CONF['pi_name']);
        $c->add('list_tpl_ver', $_PP_DEFAULTS['list_tpl_ver'],
                'select', 0, 30, 0, 80, true, $_PP_CONF['pi_name']);

        $c->add('fs_blocks', NULL, 'fieldset', 0, 40, NULL, 0, true,
                $_PP_CONF['pi_name']);
         $c->add('blk_random_limit', $_PP_DEFAULTS['blk_random_limit'],
                'text', 0, 40, 2, 10, true, $_PP_CONF['pi_name']);
        $c->add('blk_featured_limit', $_PP_DEFAULTS['blk_featured_limit'],
                'text', 0, 40, 2, 20, true, $_PP_CONF['pi_name']);
        $c->add('blk_popular_limit', $_PP_DEFAULTS['blk_popular_limit'],
                'text', 0, 40, 2, 30, true, $_PP_CONF['pi_name']);
        $c->add('cache_max_age', $_PP_DEFAULTS['cache_max_age'],
                'text', 0, 40, 2, 40, true, $_PP_CONF['pi_name']);
        $c->add('fs_debug', NULL, 'fieldset', 0, 50, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('debug', $_PP_DEFAULTS['debug'],
                'select', 0, 50, 2, 10, true, $_PP_CONF['pi_name']);
        $c->add('debug_ipn', $_PP_DEFAULTS['debug_ipn'],
                'select', 0, 50, 2, 20, true, $_PP_CONF['pi_name']);

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

        $c->add('sg_shop', NULL, 'subgroup', 10, 0, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('fs_shop', NULL, 'fieldset', 10, 100, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('shop_name', $_PP_DEFAULTS['shop_name'],
                'text', 10, 100, 0, 10, true, $_PP_CONF['pi_name']);
        $c->add('shop_addr', $_PP_DEFAULTS['shop_addr'],
                'text', 10, 100, 0, 20, true, $_PP_CONF['pi_name']);
        $c->add('shop_phone', '',
                'text', 10, 100, 0, 30, true, $_PP_CONF['pi_name']);
        $c->add('shop_email', $_PP_DEFAULTS['shop_email'],
                'text', 10, 100, 0, 40, true, $_PP_CONF['pi_name']);
        $c->add('tax_rate', $_PP_DEFAULTS['tax_rate'],
                'text', 10, 100, 0, 50, true, $_PP_CONF['pi_name']);
        $c->add('purge_sale_prices', $_PP_DEFAULTS['purge_sale_prices'],
                'select', 10, 100, 2, 50, true, $_PP_CONF['pi_name']);

        $c->add('sg_gc', NULL, 'subgroup', 20, 0, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('fs_gc', NULL, 'fieldset', 20, 0, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('gc_enabled', $_PP_DEFAULTS['gc_enabled'],
                'select', 20, 0, 2, 10, true, $_PP_CONF['pi_name']);
        $c->add('gc_exp_days', $_PP_DEFAULTS['gc_exp_days'],
                'text', 20, 0, 0, 20, true, $_PP_CONF['pi_name']);
        // coupon code format defaults
        $c->add('fs_gc_format', NULL, 'fieldset', 20, 10, NULL, 0, true,
                $_PP_CONF['pi_name']);
        $c->add('gc_letters', $_PP_DEFAULTS['gc_letters'],
                'select', 20, 10, 17, 10, true, $_PP_CONF['pi_name']);
        $c->add('gc_numbers', $_PP_DEFAULTS['gc_numbers'],
                'select', 20, 10, 2, 20, true, $_PP_CONF['pi_name']);
        $c->add('gc_symbols', $_PP_DEFAULTS['gc_symbols'],
                'select', 20, 10, 2, 30, true, $_PP_CONF['pi_name']);
        $c->add('gc_prefix', $_PP_DEFAULTS['gc_prefix'],
                'text', 20, 10, 0, 40, true, $_PP_CONF['pi_name']);
        $c->add('gc_suffix', $_PP_DEFAULTS['gc_suffix'],
                'text', 20, 10, 0, 50, true, $_PP_CONF['pi_name']);
        $c->add('gc_length', $_PP_DEFAULTS['gc_length'],
                'text', 20, 10, 0, 60, true, $_PP_CONF['pi_name']);
        $c->add('gc_mask', $_PP_DEFAULTS['gc_mask'],
                'text', 20, 10, 0, 70, true, $_PP_CONF['pi_name']);
     }
     return true;
}

?>

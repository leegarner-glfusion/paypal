<?php
/**
*   Configuration Defaults Paypal plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.4.5
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

/**
 *  Specify whether "on purchase" emails will be sent.
 *  Be sure to edit the files templates/purchase_email_subject.txt and
 *  templates/purchase_email_message.txt to customize the email message.
 *  You should disable the *_attach options below if you are distributing 
 *  large files or it is likely that a purchaser will be buying many medium 
 *  files otherwise the email will become too large and will likely never be 
 *  received.
 *
 * purch_email_user true if logged in users should get a purchase email
 * purch_email_user_attach true if logged in users should get purchases
 *                            emailed as attachments
 * purchase_email_anon true if anonymous users should get a purchase email
 * purchase_email_anon_attach true if anonymous users should get purchases
 *                            emailed as attachments
 */
$_PP_DEFAULTS['purchase_email_user']        = 1;
$_PP_DEFAULTS['purchase_email_user_attach'] = 0;
$_PP_DEFAULTS['purchase_email_anon']        = 1;
$_PP_DEFAULTS['purchase_email_anon_attach'] = 0;

/** Email administrator upon purchase?
*   0 = Never
*   1 = Only when physical items are purchased
*   2 = Always
*/
$_PP_DEFAULTS['purch_email_admin']      = 2;

/** Admin email override.
*   If this is not empty, notification email will be sent to this
*   address instead of $_CONF['site_email']
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

// Determine whether to use the internal CSS tabbed menu or the
// default glFusion version
//$_PP_DEFAULTS['use_css_menu'] = 0;

// Max size for file uploads, in MB
$_PP_DEFAULTS['max_file_size'] = 8;

// Comments supported?
$_PP_DEFAULTS['ena_comments'] = 1;

// Enable ratings?
$_PP_DEFAULTS['ena_ratings'] = 1;
$_PP_DEFAULTS['anon_can_rate'] = 0;

// Temporary work path when creating buttons
$_PP_DEFAULTS['tmpdir'] = $_CONF['path'] . 'data/paypal/';

// Path to downloadable files
$_PP_DEFAULTS['download_path'] = $_PP_DEFAULTS['tmpdir'] . 'files/';

// Control which blocks to display- both by default
$_PP_DEFAULTS['displayblocks'] = 3;

// Debugging?
$_PP_DEFAULTS['debug_ipn'] = 0;
$_PP_DEFAULTS['debug'] = 0;

// Various defaults for new products:
$_PP_DEFAULTS['def_taxable'] = 1;
$_PP_DEFAULTS['def_enabled'] = 1;
$_PP_DEFAULTS['def_featured'] = 0;

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
$_PP_DEFAULTS['centerblock'] = 0;   // Enable centerblock?

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
        $c->add('purch_email_user', $_PP_DEFAULTS['purchase_email_user'],
                'select', 0, 0, 2, 70, true, $_PP_CONF['pi_name']);
        $c->add('purch_email_user_attach', 
                $_PP_DEFAULTS['purchase_email_user_attach'],
                'select', 0, 0, 2, 80, true, $_PP_CONF['pi_name']);
        $c->add('purch_email_anon', $_PP_DEFAULTS['purchase_email_anon'],
                'select', 0, 0, 2, 90, true, $_PP_CONF['pi_name']);
        $c->add('purch_email_anon_attach', 
                $_PP_DEFAULTS['purchase_email_anon_attach'],
                'select', 0, 0, 2, 100, true, $_PP_CONF['pi_name']);
        $c->add('purch_email_admin', $_PP_DEFAULTS['purch_email_admin'],
                'select', 0, 0, 6, 110, true, $_PP_CONF['pi_name']);
        $c->add('menuitem', $_PP_DEFAULTS['menuitem'],
                'select', 0, 0, 2, 120, true, $_PP_CONF['pi_name']);
        $c->add('order', $_PP_DEFAULTS['order'],
                'select', 0, 0, 5, 130, true, $_PP_CONF['pi_name']);
        $c->add('prod_per_page', $_PP_DEFAULTS['maxPerPage'],
                'text', 0, 0, 0, 150, true, $_PP_CONF['pi_name']);
        $c->add('cat_columns', $_PP_DEFAULTS['categoryColumns'],
                'text', 0, 0, 0, 160, true, $_PP_CONF['pi_name']);
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
        $c->add('download_path', $_PP_DEFAULTS['download_path'],
                'text', 0, 10, 0, 60, true, $_PP_CONF['pi_name']);
        $c->add('max_file_size', $_PP_DEFAULTS['max_file_size'],
                'text', 0, 10, 0, 70, true, $_PP_CONF['pi_name']);

        // Working directory, formerly Encrypted Button Support
        $c->add('fs_encbtn', NULL, 'fieldset', 0, 20, NULL, 0, true, 
                $_PP_CONF['pi_name']);
        $c->add('tmpdir', $_PP_DEFAULTS['tmpdir'],
                'text', 0, 20, 0, 80, true, $_PP_CONF['pi_name']);

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

        $c->add('fs_blocks', NULL, 'fieldset', 0, 40, NULL, 0, true, 
                $_PP_CONF['pi_name']);
         $c->add('blk_random_limit', $_PP_DEFAULTS['blk_random_limit'],
                'text', 0, 40, 2, 10, true, $_PP_CONF['pi_name']);
        $c->add('blk_featured_limit', $_PP_DEFAULTS['blk_featured_limit'],
                'text', 0, 40, 2, 20, true, $_PP_CONF['pi_name']);
        $c->add('blk_popular_limit', $_PP_DEFAULTS['blk_popular_limit'],
                'text', 0, 40, 2, 30, true, $_PP_CONF['pi_name']);

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

        $c->add('sg_shop', NULL, 'subgroup', 0, 10, NULL, 0, true, 
                $_PP_CONF['pi_name']);
        $c->add('fs_shop', NULL, 'fieldset', 10, 100, NULL, 0, true, 
                $_PP_CONF['pi_name']);
        $c->add('shop_name', $_PP_DEFAULTS['shop_name'],
                'text', 10, 100, 0, 10, true, $_PP_CONF['pi_name']);
        $c->add('shop_addr', $_PP_DEFAULTS['shop_addr'],
                'text', 10, 100, 0, 20, true, $_PP_CONF['pi_name']);

     }

     return true;

}

?>

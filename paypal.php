<?php
/**
*   Global configuration items for the PayPal plugin.
*   These are either static items, such as the plugin name and table
*   definitions, or are items that don't lend themselves well to the 
*   glFusion configuration system, such as allowed file types.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Mark Evans <mark@glfusion.org
*   @copyright  Copyright (c) 2009-2010 Lee Garner <lee@leegarner.com>
*   @copyright  Mark Evans <mark@glfusion.org
*   @package    paypal
*   @version    0.5.6
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_table_prefix, $_TABLES;
global $_PP_CONF;

$_PP_CONF['pi_name']            = 'paypal';
$_PP_CONF['pi_display_name']    = 'PayPal';
$_PP_CONF['pi_version']         = '0.5.6';
$_PP_CONF['gl_version']         = '1.4.0';
$_PP_CONF['pi_url']             = 'http://www.glfusion.org';

$_PP_table_prefix = $_DB_table_prefix . 'pp_';

$_TABLES['paypal.ipnlog']       = $_PP_table_prefix . 'ipnlog';
$_TABLES['paypal.products']     = $_PP_table_prefix . 'products';
$_TABLES['paypal.downloads']    = $_PP_table_prefix . 'downloads';
$_TABLES['paypal.purchases']    = $_PP_table_prefix . 'purchases';
$_TABLES['paypal.images']       = $_PP_table_prefix . 'images';
$_TABLES['paypal.categories']   = $_PP_table_prefix . 'categories';
$_TABLES['paypal.prodXcat']     = $_PP_table_prefix . 'prodXcat';
$_TABLES['paypal.prod_attr']    = $_PP_table_prefix . 'product_attributes';
$_TABLES['paypal.address']      = $_PP_table_prefix . 'address';
$_TABLES['paypal.orders']       = $_PP_table_prefix . 'orders';
$_TABLES['paypal.userinfo']     = $_PP_table_prefix . 'userinfo';
$_TABLES['paypal.cart']         = $_PP_table_prefix . 'cart';
$_TABLES['paypal.buttons']      = $_PP_table_prefix . 'buttons';
$_TABLES['paypal.gateways']     = $_PP_table_prefix . 'gateways';
$_TABLES['paypal.workflows']    = $_PP_table_prefix . 'workflows';
$_TABLES['paypal.orderstatus']  = $_PP_table_prefix . 'order_status';
$_TABLES['paypal.order_log']    = $_PP_table_prefix . 'order_log';
$_TABLES['paypal.currency']     = $_PP_table_prefix . 'currency';
$_TABLES['paypal.specials']     = $_PP_table_prefix . 'specials';

// Other relatively static values;
$_PP_CONF['image_dir']  = $_CONF['path_html'] . $_PP_CONF['pi_name'] . 
                            '/images/products';
$_PP_CONF['logfile']    = $_CONF['path'] . 
                            "logs/{$_PP_CONF['pi_name']}_downloads.log";
$_PP_CONF['catimgpath'] = $_CONF['path_html'] . $_PP_CONF['pi_name'] . 
                            '/images/categories';


/**
*   Allowed extensions for downloads.
*   Make sure that every downloadable file extension is included in this list.
*   For security you may want to remove unused file extensions.  Also try 
*   to avoid php and phps.
*   NOTE: extensions must be defined in 
*       $_CONF['path']/system/classes/downloader.class.php
*   to be listed here.
*/
$_PP_CONF['allowedextensions'] = array (
    'tgz'  => 'application/x-gzip-compressed',
    'gz'   => 'application/x-gzip-compressed',
    'zip'  => 'application/x-zip-compresseed',
    'tar'  => 'application/x-tar',
    'php'  => 'text/plain',
    'phps' => 'text/plain',
    'txt'  => 'text/plain',
    'html' => 'text/html',
    'htm'  => 'text/html',
    'bmp'  => 'image/bmp',
    'ico'  => 'image/bmp',
    'gif'  => 'image/gif',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/x-png',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'pdf'  => 'application/pdf',
    'swf'  => 'application/x-shockwave-flash',
    'doc'  => 'application/msword',
    'xls'  => 'application/vnd.ms-excel',
    'exe'  => 'application/octet-stream'
);

/**
*   Indicate which buttons will be checked by default for new products.
*/
$_PP_CONF['buttons'] = array(
    'buy_now'   => 1,
    'donation'  => 0,
);

$_PP_CONF['tpl_ver_detail'] = '/v1';

?>

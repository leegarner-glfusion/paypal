<?php
/**
*   glFusion API functions for the Paypal plugin.
*   Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net
*   @copyright  Copyright (c) 2009-2018 Lee Garner
*   @copyright  Copyright (c) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Include the system configuration class */
require_once $_CONF['path_system'] . 'classes/config.class.php';
$pp_config = config::get_instance();
$_PP_CONF= $pp_config->get_config('paypal');

/** Include plugin config file  */
require_once __DIR__ . '/paypal.php';

$_PP_CONF['datetime_fmt'] = 'Y-m-d H:i:s T';

/** Define base path to plugin */
define('PAYPAL_PI_PATH', "{$_CONF['path']}plugins/{$_PP_CONF['pi_name']}");
/** Define base URL to plugin */
define('PAYPAL_URL', "{$_CONF['site_url']}/{$_PP_CONF['pi_name']}");
/** Define URL to plugin admin interface */
define('PAYPAL_ADMIN_URL',
        "{$_CONF['site_admin_url']}/plugins/{$_PP_CONF['pi_name']}");

// Define product types
define('PP_PROD_PHYSICAL',  1);
define('PP_PROD_DOWNLOAD',  2);
define('PP_PROD_VIRTUAL',   4);
define('PP_PROD_COUPON',    8);
define('PP_PROD_PLUGIN',    16);

// Define comment status
define('PP_COMMENTS_ENABLED',   0);
define('PP_COMMENTS_CLOSED' ,   1);
define('PP_COMMENTS_DISABLED',  -1);

// Order status codes
define('PP_STATUS_OPEN', 0);
define('PP_STATUS_PAID', 1);

// Session variables for TimThumb image url, if used
SESS_setVar($_PP_CONF['pi_name'], array(
    'origpath' => $_PP_CONF['image_dir'],
));

// Load the site's language file, falling back to English.
$langpath = __DIR__ . '/language';
$langfile = "{$_CONF['language']}.php";
if (!is_file("$langpath/$langfile")) {
    $langfile = 'english.php';
}
/** Include the proper language file */
require_once "$langpath/$langfile";

if (is_file("$langpath/custom/$langfile")) {
    /** Include the custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

if (file_exists(__DIR__ . '/config.php')) {
    /** Include local configuration overrides */
    include_once __DIR__ . '/config.php';
}

//  Set user ID = 1 for anonymous users.
if (empty($_USER['uid']) || $_USER['uid'] < 2) {
    $_USER['uid'] = 1;
    $_USER['username'] = 'anonymous';
    $_USER['fullname'] = 'Anonymous';
}

spl_autoload_register(function ($class)
{
    if (strpos($class, 'Paypal\\') === 0) {
        $class = str_replace('Paypal\\', '', $class);
        $path = __DIR__ . '/classes/' . $class . '.class.php';
        if (file_exists($path)) {
            include $path;
        }
    }
});

/** Import plugin-specific functions */
function USES_paypal_functions()
{   require_once __DIR__ . '/paypal_functions.inc.php'; }

function USES_paypal_reports()
{   require_once __DIR__ . '/reports.inc.php';  }

// Mark whether this is a uikit theme. Save to global conf var
// so it's available where needed.
$_PP_CONF['_is_uikit'] = $_SYSTEM['framework'] == 'uikit' ? true : false;
$_PP_CONF['_iconset'] = $_PP_CONF['_is_uikit'] ? 'uk-icon uk-icon' : 'fa fa';

global $ppGCart;
$ppGCart = NULL;

/**
*   Set the global cart object.
*
*   @return object      Global cart, for use in a local variable
*/
function PAYPAL_setCart()
{
    global $ppGCart;
    $ppGCart = Paypal\Cart::getInstance();
    return $ppGCart;
}


function USES_paypal_class_report($name='')
{
    $classfile = __DIR__ . '/classes/reports/' . $name . '.class.php';
    if (is_file($classfile)) {
        require_once $classfile;
        return true;
    } else {
        return false;
    }
}


/**
*   Show the product catalog in the centerblock.
*
*   @param  integer $where  Where to show the block (only after featured story)
*   @param  integer $page   Which page (Home page only)
*   @param  string  $topic  Which topics to show with (All)
*   @return string      HTML code for submission form
*/
function plugin_centerblock_paypal($where = 1, $page = 1, $topic = '')
{
    global $_PP_CONF;

    $display = '';

    // Only show if no topic is requested
    if ($_PP_CONF['centerblock'] == 1 && $topic == '') {
        USES_paypal_functions();
        //PAYPAL_setCart();
        $display = \Paypal\siteHeader();
        $T = new Template(__DIR__ . '/templates');
        $T->set_file('title', 'paypal_title.thtml');
        $T->set_var('title', $LANG_PP['main_title']);
        $display .= $T->parse('', 'title');
        $display .= \Paypal\ProductList();
        $display .= \Paypal\siteFooter();
    }
    return $display;
}


/**
*   Upgrade the plugin to the currently-installed version.
*
*   @return mixed   True if successful, error message number otherwise
*/
function plugin_upgrade_paypal()
{
    require_once __DIR__ . '/upgrade.inc.php';
    return PAYPAL_do_upgrade();
}


/**
 *  Returns the main menu items for the plugin.
 *
 *  @return array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_paypal()
{
    global $_CONF, $_PP_CONF, $LANG_PP;

    $menuitems = array();
    if (SEC_hasRights('paypal.user,paypal.admin,paypal.view', 'OR') &&
            $_PP_CONF['menuitem']) {
        $a = preg_replace(
            '/\{(.*)\}/i',
            '<i class="' . PP_getIcon('${1}', '') . '"></i>',
            $LANG_PP['mnu_paypal']);
        $menuitems[$a] = PAYPAL_URL . '/index.php';
    }
    return $menuitems;
}


/**
 *  Check to see if this plugin supports comments.
 *
 *  @return boolean     True if comments supported, false otherwise
 */
function plugin_commentsupport_paypal()
{
    global $_PP_CONF;
    return $_PP_CONF['ena_comments'] == 0 ? false : true;
}


/**
*   Save a comment
*
*   @param  string  $type       Plugin to save comment
*   @param  string  $title      Comment title
*   @param  string  $comment    Comment text
*   @param  string  $id         Item id to which $cid belongs
*   @param  integer $pid        Comment parent
*   @param  string  $postmode   'html' or 'text'
*   @return mixed   false for failure, HTML string (redirect?) for success
*/
function plugin_savecomment_paypal($title,$comment,$id,$pid,$postmode)
{
    global $LANG03, $_PP_CONF;

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_paypal())
        return;

    $id = (int)$id;
    $title = strip_tags($title);    // No HTML in the comment title
    $pid = COM_applyFilter($pid, true);
    $postmode = COM_applyFilter ($postmode);

    // Actually save the comment
    $ret = CMT_saveComment($title, $comment, $id, $pid, $_PP_CONF['pi_name'], $postmode);

    if ($ret > 0) {     // failure
        return COM_siteHeader()
            . CMT_commentform ($title, $comment, $id, $pid,
                    $_PP_CONF['pi_name'], $LANG03[14], $postmode)
            . COM_siteFooter();
    } else {        // success - update the comment counter for this item
        return COM_refresh(COM_buildUrl(PAYPAL_URL . "/detail.php?id=$id"));
    }
}


/**
*   How the plugin will display specific comments.
*
*   @param   string  $id     Unique idenifier for item comment belongs to
*   @param   int     $cid    Comment id to display
*   @param   string  $title  Page/comment title
*   @param   string  $order  'ASC' or 'DSC' or blank
*   @param   string  $format 'threaded', 'nested', or 'flat'
*   @param   int     $page   Page number of comments to display
*   @param   boolean $view   True to view comment (by cid), false to display (by $pid)
*   @return  mixed   results of calling the plugin_displaycomment_ function
*/
function plugin_displaycomment_paypal($id, $cid, $title, $order,$format, $page, $view)
{
    global $_USER, $LANG_ACCESS, $_PP_CONF;

    $retval = '';

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_paypal())
        return $retval;

    $P = new Paypal\Product($id);
    if ($P->isNew)
        return $retval;

    $allowed = 1;

    if ($allowed == 1) {
        $delete_option = plugin_ismoderator_paypal();
        $retval .= CMT_userComments($id, $title, $_PP_CONF['pi_name'], $order, $format,
                                     $cid, $page, $view, $delete_option,
                                     $P->comments_enabled, $_USER['uid']);
    } else {
        $retval .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                        COM_getBlockTemplate ('_msg_block', 'header'))
                . $LANG_ACCESS['storydenialmsg']
                . COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
    }

    return $retval;
}


/**
*   Delete a single comment.
*
*   @param  int     $cid    Comment to be deleted
*   @param  string  $id     Item id to which $cid belongs
*   @return mixed   false for failure, HTML string (redirect?) for success
*/
function plugin_deletecomment_paypal($cid, $id)
{
    global $_CONF, $_TABLES, $_PP_CONF;

    if (plugin_ismoderator_paypal()) {
        if (CMT_deleteComment($cid, $id, $_PP_CONF['pi_name']) == 0) {
            $count = DB_count($_TABLES['comments'],
                        array('sid', 'type'),
                        array($id, $_PP_CONF['pi_name']));
            $count--;
            if ($count < 0) $count = 0;
            DB_query("UPDATE {$_TABLES['paypal.products']}
                    SET comments=$count
                    WHERE id='$id'");
            // Redirect back to the product detail page
            return COM_refresh(PAYPAL_URL . '/detail.php?id=' . $id);
        }
    }

    // Default return if anything fails
    return false;
}


/**
*   Handle any post-processing for an edited comment.
*   Just redirects back to the product detail page.  The comment is actually
*   saved in comment.php.
*
*   @param  integer $cid    Comment ID
*   @param  mixed   $id     Item ID
*/
function plugin_editcomment_paypal($cid, $id)
{
    echo COM_refresh(PAYPAL_URL . '/detail.php?id=' . $id);
    exit;
}


/**
*   Returns the URL of the plugin's icon.
*
*   @return string      URL of the icon
*/
function plugin_geticon_paypal()
{
    return PAYPAL_URL . '/images/paypal.png';
}


/**
*   Check if this is a plugin administrator.
*   There is no "moderator" for the Paypal plugin.
*   Saves a static value to conserve calls to SEC_hasRights()
*
*   @return boolean     True for admins, False for regular users
*/
function plugin_ismoderator_paypal()
{
    static $isadmin = NULL;
    if ($isadmin === NULL) {
        $isadmin = SEC_hasRights('paypal.admin');
    }
    return $isadmin;
}


/**
 *  Add an option for the plugin in the command and control area.
 *
 *  @return array   Array containing (pi_name, admin_url, icon_url)
 */
function plugin_cclabel_paypal()
{
    global $_PP_CONF;

    if (plugin_ismoderator_paypal()) {
        return array($_PP_CONF['pi_display_name'],
                    PAYPAL_ADMIN_URL . '/index.php',
                    plugin_geticon_paypal());
    }
}


/**
 *  Adds the paypal plugin to the Admin menu.
 *
 *  @return array   Array containing (pi_name, pi_admin_url, # of items or '')
 */
function plugin_getadminoption_paypal()
{
    global $_TABLES, $_PP_CONF;

    if (plugin_ismoderator_paypal()) {
        return array($_PP_CONF['pi_display_name'],
                    PAYPAL_ADMIN_URL . '/index.php',
                    DB_count($_TABLES['paypal.products']));
    }
}


/**
*   Returns the user option for this plugin
*   Adds the plugin to the user menu
*
*   @return   array   Array containing (plugin name, plugin user url, 0)
*/
function plugin_getuseroption_paypal()
{
    global $LANG_PP, $_TABLES, $_USER;

    $retval = array();
    if (!COM_isAnonUser() &&
        DB_count($_TABLES['paypal.orders'], 'uid', $_USER['uid']) > 0) {
        $retval[] = array($LANG_PP['my_orders'],
                PAYPAL_URL . '/index.php?orderhist=x');
    }
    //PAYPAL_setCart();
    if (Paypal\Cart::getInstance()->hasItems()) {
        $retval[] = array($LANG_PP['viewcart'],
                PAYPAL_URL . '/index.php?view=cart');
    }
    return $retval;
}


/**
 *  Returns the current version of the paypal plugin.
 *
 *  @return string  Plugin version
 */
function plugin_chkVersion_paypal()
{
    global $_PP_CONF;

    return $_PP_CONF['pi_version'];
}


/**
*   Performs operations when a user (or admin) deletes an account
*   - Anonymize user data in orders
*   - Delete user address info
*
*   @param  int $uid    User ID that is being deleted
*/
function plugin_user_delete_paypal($uid)
{
    global $_TABLES;

    $uid = (int)$uid;
    $sql = "UPDATE {$_TABLES['paypal.orders']}
            SET uid = 1
            WHERE uid = '$uid'";

    Paypal\UserInfo::deleteUser($uid);
    Paypal\Cart::deleteUser($uid);
}


/**
*   Support merging local accounts into remote accounts
*
*   @param  integer $origUID    Original (remote) user ID
*   @param  integer $destUID    Merged (local) user ID
*/
function plugin_user_move_paypal($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    DB_query("UPDATE {$_TABLES['paypal.purchases']}
            SET user_id = $destUID WHERE user_id = $origUID", 1);
    DB_query("UPDATE {$_TABLES['paypal.address']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['paypal.orders']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['paypal.userinfo']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['paypal.cart']}
            SET cart_uid = $destUID WHERE cart_uid = $origUID", 1);
}


/**
*   Implements the [paypal:] autotag.
*
*   Currently only allows for a single product ID, which will generate
*   a link to that product's detail page.
*
*   @since  version 0.4.0
*   @param  string  $op         Operation to perform (tagname or parse)
*   @param  string  $content    Content in which to replace autotag
*   @param  array   $autotag    Autotag details
*   @return string              Updated $content
*/
function plugin_autotags_paypal ($op, $content = '', $autotag = '')
{
    global $_CONF, $_TABLES, $_PP_CONF;

    $pi_name = $_PP_CONF['pi_name'];
    if ($op == 'tagname' ) {
        return array($pi_name, $pi_name . '_cat');
    } else if ($op == 'parse') {
        switch ($autotag['tag']) {
        case $pi_name:
            $id = COM_applyFilter($autotag['parm1'], true);
            $url = COM_buildUrl(PAYPAL_URL . '/detail.php?id=' . $id);

            // Allow the author to use different text for the link.  Default
            // is the stored product name.
            if (empty($autotag['parm2'])) {
                $linktext = DB_getItem($_TABLES['paypal.products'],
                        'name', "id = '$id'");
            } else {
                $linktext = $autotag['parm2'];
            }
            $link = '<a href="' . $url . '">' . $linktext . '</a>';
            $content = str_replace ($autotag['tagstr'], $link, $content);
            break;

        case $pi_name . '_cat':
            $id = COM_applyFilter($autotag['parm1'], true);
            $url = COM_buildUrl(PAYPAL_URL.'/index.php?category='.$id);
            if (empty($autotag['parm2'])) {
                $linktext = DB_getItem($_TABLES['paypal.categories'],
                        'cat_name', "cat_id = '$id'");
            } else {
                $linktext = $autotag['parm2'];
            }
            $link = '<a href="' . $url . '">' . $linktext . '</a>';
            $content = str_replace ($autotag['tagstr'], $link, $content);
            break;
        }
        return $content;
    }
}


/**
 *  Checks to see if the user has sufficient rights to view the page.
 *
 *  Check if the current user has rights (at least one of) specified by
 *  $rights.  If not log the error, display a generic access denied message
 *  and exit.
 *
 *  @param  string  $rights     Comma-separated list of allowable rights
 */
function PAYPAL_access_check($rights = 'paypal.user,paypal.admin,paypal.view')
{
    global $_PLUGINS, $_PP_CONF;

    // If this plugin or a required plugin isn't available, abort gracefully
    if (!in_array($_PP_CONF['pi_name'], $_PLUGINS) ||
        !in_array('lglib', $_PLUGINS) ||
        !SEC_hasRights($rights, 'OR')) {
        COM_404();
        exit;
    }
}


/**
*   Generate a single buy-now button for the first enabled payment gateway.
*   This function is deprecated, but is kept to avoid completely breaking
*   if some plugin that calls this function is still in place.
*
*   Plugins should call LGLIB_invokeService('paypal, 'genButton'...) instead.
*
*   Provided $vars should include:
*       'item_number', 'item_name', 'amount', and optionally 'item_type'
*
*   @deprecated version 0.5.0
*   @uses   Gateway::ExternalButton()
*   @param  string  $btn_type   Type of button (buy_now, add_to_cart, etc.)
*   @param  array   $vars       Associative array of input variables
*   @param  string  $item_type  Optional type indicator (physical, virtual)
*   @return string          HTML of requested button
*/
function PAYPAL_genButton($btn_type, $vars, $item_type=0)
{
    global $_CONF, $_PP_CONF, $_USER, $LANG_configselects;

    $retval = '';

    // must be an expected type
    if (!in_array($btn_type,
        array('buy_now', 'add_cart', 'pay_now', 'subscribe', 'donation'))) {
        return $retval;
    }

    if ($btn_type == 'add_cart') {
        if ($_PP_CONF['ena_cart'] == 1) {
            $tpl_add_cart = 'btn_add_cart.thtml';
            if ($item_type == 0) $item_type = PP_PROD_VIRTUAL;
            $T = new Template(__DIR__ . '/templates');
            $T->set_file('cart', 'buttons/btn_add_cart.thtml');
            $T->set_var(array(
                'item_name'     => $vars['item_name'],
                'item_number'   => $vars['item_number'],
                'amount'        => $vars['amount'],
                'pi_url'        => PAYPAL_URL,
                'item_type'     => $item_type,
            ) );
            $retval = $T->parse('', 'cart');
        }
    } else {
        foreach (Paypal\Gateway::getAll() as $gw) {
            $retval = $gw->ExternalButton($vars, $btn_type);
            break;
        }
    }
    return $retval;
}


/**
*   Return the types to list in the search types selection.
*
*   @return array   Associative array (typename => title)
*/
function plugin_searchtypes_paypal()
{
    global $_PP_CONF, $LANG_PP;

    return array($_PP_CONF['pi_name'] => $LANG_PP['srchtitle']);
}


/**
*   Create a plugin search object to search this plugin
*
*   @param  string  $query      Search query string
*   @param  string  $datestart  Starting date for search
*   @param  string  $dateend    Ending date for search
*   @param  string  $topic      Topic selected for search
*   @param  string  $type       Type selected during search
*   @param  string  $author     Limit results to this author (not used)
*   @return object              Search object to be processed by Search class
*/
function plugin_dopluginsearch_paypal(
    $query, $datestart, $dateend, $topic, $keyType, $author
)
{
    global $_CONF, $_TABLES, $_PP_CONF, $LANG_PP;

    // Products aren't "auhored", so return null if searching by author
    if (!empty($author) ||
            !SEC_hasRights('paypal.user,paypal.admin,paypal.view', 'OR')) {
        return NULL;
    }

    if (empty($keyType)) {
        $type = 'all';
    }

    // Encode query for inclusion in result's url
    $htmlquery = urlencode($query);

    $query = trim(DB_escapeString(htmlspecialchars($query), ENT_QUOTES, COM_getEncodingt()));

    $sql = "SELECT
                p.id, p.name as title, p.description as description,
                dt_add as date,  views as hits,
                CONCAT('/{$_PP_CONF['pi_name']}/detail.php?id=',p.id,'&query=$htmlquery') as url
            FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON p.cat_id=c.cat_id
            WHERE p.enabled = 1
            AND (c.enabled=1 OR c.enabled IS NULL)";

    $search = new SearchCriteria($_PP_CONF['pi_name'], $LANG_PP['blocktitle']);
    $columns = array('p.name', 'p.short_description', 'p.description',
                    'p.keywords', 'c.cat_name', 'c.description');
    list($sql, $ftsql) =
            $search->buildSearchSQL($keyType, $query, $columns, $sql);
    $search->setSQL($sql);
    return $search;
}


/**
*   Get additional Javascript files to import in the header
*
*   @return array   Array of filenames to import
*/
function plugin_getheaderjs_paypal()
{
    $files = array(
        //__DIR__ . '/js/toggleEnabled.js',
        __DIR__ . '/js/addcart.js',
    );
    return $files;
}


/**
*   Get additional CSS into the site header.
*   Checks first for layout-specific version, gets the plugin default if none.
*
*   @return array   List of paths to CSS files
*/
function plugin_getheadercss_paypal()
{
    global $_CONF, $_PP_CONF;

    $styles = array();
    $pi_css = $_CONF['path_html'] . 'paypal/css/';
    $layout_css = "{$_CONF['path_layout']}plugins/{$_PP_CONF['pi_name']}/";

    $files = array('style.css');

    foreach ($files as $file) {
        $styles[] = $pi_css . $file;
        if (@file_exists($pi_css . 'custom/' . $file)) {
            $styles[] = $pi_css . 'custom/' . $file;
        }
        if (@file_exists($layout_css . $file)) {
            $styles[] = $layout_css . $file;
        }
    }

    return $styles;
}


/**
*   Get the URL to the localized configuration help file.
*
*   @uses   PAYPAL_getDocURL()
*   @param  string  $option     Configuration option to display
*   @param  string  $doclang    Documentation language requested
*   @return array   Array of (url, window_type)
*/
function plugin_getconfigelementhelp_paypal($option, $doclang='english')
{
    $doc_url = PAYPAL_getDocURL('config', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }

    return array($doc_url, 0);
}


/**
*   Get the link to a documentation file.
*
*   Returns the URL to the file, or to the directory if no file requested.
*
*   @see    plugin_getconfigelementhelp_paypal()
*   @see    \Paypal\Product::showForm()
*   @param  string  $file       HTML file
*   @param  string  $doclang    Documentation language
*   @return string              URL to document directory or file
*/
function PAYPAL_getDocURL($file='', $doclang='english')
{
    $doc_path = __DIR__ . "/docs/$doclang/";
    $doc_url = '';

    if (version_compare(GVERSION, '1.6.0', '<')) {
        $file .= '.legacy';
    }
    $file .= '.html';

    if ($file != '') {
        if (!file_exists($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = PAYPAL_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = PAYPAL_URL . "/docs/$doclang/";
    }
    return $doc_url;
}


/**
*   Return the items that should be removed with the plugin.
*
*   @return array   Array of items to be removed.
*/
function plugin_autouninstall_paypal ()
{
    $out = array (
        // Give the name of the tables, without $_TABLES[]
        'tables' => array(
                'paypal.address',
                'paypal.buttons',
                'paypal.cart',
                'paypal.categories',
                'paypal.currency',
                'paypal.gateways',
                'paypal.images',
                'paypal.ipnlog',
                'paypal.order_items',
                'paypal.order_log',
                'paypal.orderstatus',
                'paypal.orders',
                'paypal.prod_attr',
                'paypal.products',
                'paypal.purchases',
                'paypal.userinfo',
                'paypal.workflows',
        ),
        // Give the full name of the group, as in the db
        'groups' => array('paypal Admin'),
        // Give the full name of the feature, as in the db
        'features' => array(
                'paypal.admin',
                'paypal.user',
                'paypal.view'
        ),
        // Give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array(
                'phpblock_paypal_random',
                'phpblock_paypal_featured',
                'phpblock_paypal_categories',
                'phpblock_paypal_popular',
                'phpblock_paypal_cart',
                'phpblock_paypal_recent',
                'phpblock_paypal_search',
        ),
        // List all vars by name
        'vars'=> array('paypal_gid'),
    );
    Paypal\Cache::clear();  // clear everything from cache
    return $out;
}


/**
*   Disable this plugin's blocks when the plugin is changed.
*
*   Acts on all blocks with a phpblock function similar to the specified
*   functions.  Blocks are not enabled automatically when enabling the plugin.
*
*   @param  boolean $enabled    Plugin's new "enabled" state
*/
function plugin_enablestatechange_paypal($enabled)
{
    global $_TABLES, $_PLUGINS, $_PP_CONF;

    $enabled = $enabled == true ? 1 : 0;
    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', $_PP_CONF['pi_name']);
        $enabled = 0;
    }

    if ($enabled == 0) {
        $blockfns = array('phpblock_paypal_');

        foreach ($blockfns as $blockfn) {
            DB_query("UPDATE {$_TABLES['blocks']}
                    SET is_enabled=$enabled
                    WHERE phpblockfn like '{$blockfn}%'"
                    );
        }
    }
}


/**
*   Receives notification of another plugin's deletion
*
*   @param  mixed   $id     Item ID
*   @param  string  $type   Plugin Name
*/
function plugin_itemdeleted_paypal($id, $type)
{
    global $_TABLES, $_PP_CONF;

    // If the lgLib plugin is disabled or removed, disable thie plugin
    if ($type == 'lglib' && $id == '*') {
        plugin_enablestatechange_paypal(false);
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', $_PP_CONF['pi_name']);
        unset($_POST['enabledplugins'][$_PP_CONF['pi_name']]);
        unset($_POST['pluginarray'][$_PP_CONF['pi_name']]);
    }
}


/**
*   Get comment url and unique id fieldname.
*
*   @return array   Array containing main plugin URL and the unqiue id name
*/
function plugin_getcommenturlid_paypal()
{
    return array(COM_buildUrl(PAYPAL_URL . '/detail.php'), 'product');
}


/**
*   Creates the shopping cart block, if the cart is not empty.
*
*   @uses   phpblock_paypal_cart_contents()
*   @return string      HTML for the shopping cart block.
*/
function phpblock_paypal_cart()
{
    $T = new Template(__DIR__ . '/templates');
    $T->set_file('shopping_cart', 'blk_cart.thtml');
    $contents = phpblock_paypal_cart_contents();
    $vis = empty($contents) ? 'none' : 'block';
    $T->set_var(array(
        'cart_contents' => $contents,
        'visibility'    => $vis,
    ) );
    $T->parse('output', 'shopping_cart');
    return $T->finish ($T->get_var('output'));
}


/**
*   Supplies the actual content for the shopping cart block.
*   Used to allow AJAX updates to the cart display
*
*   Each item in the cart is shown along with its quantity, and links
*   to view the cart or check out.
*
*   @see    phpblock_paypal_cart()
*   @return string      HTML for the shopping cart block.
*/
function phpblock_paypal_cart_contents()
{
    global $_PP_CONF, $LANG_PP;

    if ($_PP_CONF['ena_cart'] == 0) return '';

    //$cart = PAYPAL_setCart();
    $cart = Paypal\Cart::getInstance();

    // If the cart is empty, the block isn't shown
    if (!$cart->hasItems()) return '';

    $T = new Template(__DIR__ . '/templates');
    $T->set_file('shopping_cart_contents', 'blk_cart_contents.thtml');
    $T->set_block('shopping_cart_contents', 'CartRow', 'cRow');
    $total = 0;
    foreach ($cart->Cart() as $id=>$item) {
        $P = Paypal\Product::getInstance($item['item_id']);

        // See if this is a plugin item, will have a ':' in the item number
        if (strstr($item['item_id'], ':')) {
            $item_info = explode(':', $item['item_id']);
        }
        if (strpos($item['item_id'], '|')) {
            list($item_id, $options) = explode('|', $item['item_id']);
        } else {
            $item_id = $item['item_id'];
            $options = '';
        }
        $item_price = $item['price'];
        // Store the text for each attribute and custom field in an array,
        // then implode around linebreaks to display them
        $opt_str = '';
        $opt_arr = array();
        if ($options) {
            $opt_ids = explode(',', $options);
            foreach ($opt_ids as $opt_id) {
                $opt_arr[] = $P->options[$opt_id]['attr_name'] . ': '.
                        $P->options[$opt_id]['attr_value'];
            }
        }
        $item_total = $item_price * $item['quantity'];
        $text_names = explode('|', $P->custom);
        if (!empty($text_names) && isset($item['extras']['custom']) && is_array($item['extras']['custom'])) {
            foreach ($item['extras']['custom'] as $id=>$val) {
                if (isset($text_names[$id]) && !empty($val)) {
                    $opt_arr[] = $text_names[$id] . ': ' . $val;
                }
            }
        }
        $opt_str = implode('<br />', $opt_arr);
        $T->set_var(array(
            'item_id'       => $item_id,
            'options'       => $opt_str,
            'item_name'     => $P->short_description,
            'item_dscp'     => $P->descrip,
            'price'         => COM_numberFormat($item['price'], 2),
            'quantity'      => $item['quantity'],
            'item_total'    => COM_numberFormat($item_total, 2),
            'pi_url'        => PAYPAL_URL,
            'item_link'     => $P->getLink(),
        ) );
        $T->parse('cRow', 'CartRow', true);
        $total += $item_total;
    }
    $T->set_var(array(
        'grand_total' => COM_numberFormat($total, 2),
    ) );
    $T->parse('output', 'shopping_cart_contents');
    return $T->finish ($T->get_var('output'));
}


/**
*   Catalog search block
*
*   @return string  HTML for search form
*/
function phpblock_paypal_search()
{
    global $_TABLES;

    $T = new Template(__DIR__ . '/templates/');
    $T->set_file('search', 'blk_search.thtml');

    $category_options = COM_optionList($_TABLES['paypal.categories'],
       'cat_id, cat_name', '', 1, SEC_buildAccessSql(''));

    $T->set_var(array(
        'category_options'  => $category_options,
        'pi_url'            => PAYPAL_URL,
    ) );
    $T->parse('output', 'search');
    return $T->finish($T->get_var('output'));
}


/**
*   Create a random product block.
*   Shows a single product selected from all enabled products.
*
*   @uses   PAYPAL_phpblock_product_sql()
*   @param  string  $cat    Optional single category
*   @return string          HTML for the block
*/
function phpblock_paypal_random($cat='')
{
    global $_PP_CONF;

    $limit = (int)$_PP_CONF['blk_random_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = ' show_random=1 ';
    if ($cat != '') {
        $sql .= "AND category='" . DB_escapeString($cat). "' ";
    }

    return PAYPAL_phpblock_product_sql($sql, $limit);
}


/**
*   Create a featured product block.
*   Shows a single product selected from all enabled featured products.
*
*   @uses   PAYPAL_phpblock_product_sql()
*   @param  string  $cat    Optional single category
*   @return string          HTML for the block
*/
function phpblock_paypal_featured($cat='')
{
    global $_PP_CONF;

    $limit = (int)$_PP_CONF['blk_featured_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = ' featured=1 ';
    if ($cat != '') {
        $sql .= "AND category='" . DB_escapeString($cat) . "' ";
    }

    return PAYPAL_phpblock_product_sql($sql, $limit);
}


/**
*   Create a category-list block.
*   Returns HTML for a list of category names and the number of
*   products under each one.
*
*   @return     HTML for the block
*/
function phpblock_paypal_categories()
{
    global $_TABLES;

    $sql = "SELECT c.cat_id, c.cat_name, count(p.id) AS cnt
            FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON p.cat_id = c.cat_id
            WHERE c.cat_id > 0
            AND c.enabled = 1
            AND c.parent_id = 0 " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " GROUP BY c.cat_id
            ORDER BY c.cat_name";
                //HAVING cnt > 0
    //echo $sql;die;
    $result = DB_query($sql);
    if (!$result || DB_numRows($result) == 0)
        return '';

    $T = new Template(__DIR__ . '/templates');
    $T->set_file(array('blk_category' => 'blk_categories.thtml'));

    while ($A = DB_fetchArray($result, false)) {
        $cnt = (int)$A['cnt'];
        $cnt += PAYPAL_child_cat_product_count($A['cat_id']);
        if ($cnt == 0) continue;
        $T->set_block('blk_category', 'row', 'ItemRow');
        $T->set_var(array(
            'category'  => $A['cat_name'],
            'count'     => $cnt,
            'url'       => PAYPAL_URL . '/index.php?category=' . $A['cat_id'],
        ) );
        $T->parse('ItemRow', 'row', true);
    }
    $retval .= $T->parse('output', 'blk_category');

    return $retval;
}


/**
*   Create a block showing one or more "popular" products.
*   Popularity is determined by counting purchase records.
*
*   @return string              HTML for "Popular Items" block
*/
function phpblock_paypal_popular()
{
    global $_TABLES, $_PP_CONF;

    $limit = (int)$_PP_CONF['blk_popular_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = "SELECT DISTINCT
                pr.id,
                (SELECT count(*) FROM {$_TABLES['paypal.purchases']} pu
                    WHERE product_id = pr.id) AS cnt
            FROM {$_TABLES['paypal.products']} pr
            LEFT JOIN {$_TABLES['paypal.categories']} c
            ON pr.cat_id=c.cat_id
            WHERE pr.enabled=1
            AND (c.enabled=1 OR c.enabled IS NULL)
            AND pr.show_popular = 1 " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " HAVING cnt > 0
            ORDER BY cnt DESC
            LIMIT $limit";
    //echo $sql;die;

    $retval = PAYPAL_phpblock_product($sql);
    return $retval;
}


/**
*   Create a "New Products" block.
*   Shows most recently-added products
*
*   @uses   PAYPAL_phpblock_product_sql()
*   @param  string  $cat    Optional single category
*   @return string          HTML for the block
*/
function phpblock_paypal_recent($cat='')
{
    global $_TABLES, $_PP_CONF;

    $limit = (int)$_PP_CONF['blk_popular_limit'];
    if ($limit < 1)
        $limit = 1;

    $sql = "SELECT DISTINCT
                pr.id,
                (SELECT count(*) FROM {$_TABLES['paypal.purchases']} pu
                    WHERE product_id = pr.id) AS cnt
            FROM {$_TABLES['paypal.products']} pr
            LEFT JOIN {$_TABLES['paypal.categories']} c
            ON pr.cat_id=c.cat_id
            WHERE pr.enabled=1
            AND (c.enabled=1 OR c.enabled IS NULL)
            AND pr.show_popular = 1 " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " HAVING cnt > 0
            ORDER BY dt_add DESC
            LIMIT $limit";
    //echo $sql;die;

    $retval = PAYPAL_phpblock_product($sql);
    return $retval;
}


/**
*   Return the configured currency.
*   This is an API function to allow other plugins to find out what
*   currency we accept.
*
*   This function is deprecated, but is kept to avoid completely breaking
*   if some plugin that calls this function is still in place.
*
*   Plugins should call LGLIB_invokeService('paypal, 'getCurrency'...) instead.
*
*   @deprecated 0.5.0
*   @return string      Our configured currency code.
*/
function X_PAYPAL_getCurrency()
{
    global $_PP_CONF;
    return $_PP_CONF['currency'];
}


/**
*   Display a formatted error message.
*   This returns the provided message formatted using glFusion's
*   typography options.
*
*   @param  string $msg     Error message to display
*   @param  string $type    Type of message, used for style and header
*   @param  string $header  Optional header text.
*   @return string          HTML for formatted error message
*/
function PAYPAL_errorMessage($msg = '', $type = '', $header = '')
{
    global $LANG_PP;

    // Require a valid message
    if ($msg == '')
        return '';

    // If no header text specified, we'll put in a default.
    // Also validate the message type in this switch.
    switch (strtolower($type)) {
    case 'info':
        $class = 'info';
        if ($header == '') $header = $LANG_PP['info'];
        break;

    case 'note':
    case 'warn':
        $class = 'note';
        if ($header == '') $header = $LANG_PP['warning'];
        break;

    case 'alert':
    default:
        $class = 'alert';
        if ($header == '') $header = $LANG_PP['alert'];
        break;
    }

    return "<span class=\"$class\">"
            . COM_startBlock($header)
            . $msg
            . COM_endBlock()
            . '</span>';
}


/**
*   Create the SQL for a product block and return the block contents
*
*   @uses   PAYPAL_phpblock_product()
*   @param  string  $where  Search condition, starting with AND
*   @param  integer $limit  Number of items to show.
*   @return string      HTML for the random-product block
*/
function PAYPAL_phpblock_product_sql($where, $limit=1)
{
    global $_TABLES, $_PP_CONF, $LANG_PP;

    $retval = '';

    if ($where != '') {
        $where = ' AND ' . DB_escapeString($where) . ' ';
    }

    if ($limit != 0) {
        $limit = ' LIMIT ' . (int)$limit . ' ';
    }

    // The "c.enabled IS NULL" is to allow products which have
    // no category record, as long as the product is enabled.
    $sql = "SELECT  id
            FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
            ON p.cat_id=c.cat_id
            WHERE
                p.enabled=1 AND (c.enabled=1 OR c.enabled IS NULL) " .
            SEC_buildAccessSql('AND', 'c.grp_access') . "
            $where
            ORDER BY RAND()
            $limit";

    $retval = PAYPAL_phpblock_product($sql);
    return $retval;
}


/**
*   Create a product block.
*   Takes the supplied SQL and executes the query, formatting the block
*   and returning the HTML.
*   Only the product ID needs to be selected in the query.
*
*   @param  string  $sql    Complete SQL query to be executed.
*   @return string          HTML for the product block.
*/
function PAYPAL_phpblock_product($sql)
{
    global $_PP_CONF, $_TABLES, $_CONF;

    // By default, return nothing
    $retval = '';

    // Check for a recent cached copy of the block to save DB calls
    if ((int)$_PP_CONF['cache_max_age'] > 0) {
        $key = md5($sql);
        $cachefile = "{$_CONF['path_data']}/layout_cache/{$cachedir}/__paypal__{$key}";
        if (is_file($cachefile)) {
            // Get the HTML file, ignore errors. If empty the query will run anyway
            $retval = @file_get_contents($cachefile);
            if (!empty($retval)) return $retval;
        }
    }

    // Loop through all valid responses.  There may be more than one
    // item to show
    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("PAYPAL_phpblock_product() SQL error: $sql", 1);
    }
    if ($res && DB_numRows($res) > 0) {

        $T = new Template(__DIR__ . '/templates');
        $T->set_file(array(
            'blk_random' => 'blk_random.thtml',
            'btn_details' => 'buttons/btn_details.thtml',
        ));

        $P = new Paypal\Product();
        for ($i = 0; $A = DB_fetchArray($res, false); $i++) {
            $P->Read($A['id']);
            $T->set_var(array(
                'name'      => $P->name,
                'item_url'  => COM_buildUrl(PAYPAL_URL . '/detail.php?id=' .
                                $A['id']),
                'descrip'   => $P->short_description,
                'price'     => $P->price > 0 ?
                                sprintf("%5.2f", $P->price) : '',
                'currency'  => $_PP_CONF['currency'],
                'rec_num'   => $i,      // to separate items by <hr/>
                'id'        => $A['id'],
                'pi_url'    => PAYPAL_URL,
            ) );

            if (!$P->hasAttributes()) {
                $buttons = $P->PurchaseLinks();
                $btn_txt = '';
                foreach ($buttons as $name=>$html) {
                    $btn_txt .= $html . '<br />';
                }
                $T->set_var('button', $btn_txt);
            } else {
                if ($_PP_CONF['ena_cart']) {
                    // If the product has attributes, then the cart must be
                    // enabled to allow purchasing
                    $button = $T->parse('', 'btn_details') . '&nbsp;';
                    $T->set_var('button', $button);
                    //$T->parse('Btn', 'BtnBlock', true);
                }
            }

            $img = DB_getItem($_TABLES['paypal.images'], 'filename',
                    "product_id='{$A['id']}'");
            if ($img != '') {
                $T->set_var('img_url', PAYPAL_ImageUrl($img));
            } else {
                $T->set_var('img_url', '');
            }

            $retval .= $T->parse('output', 'blk_random');
        }

    }

    // Write the cache file if used
    if ((int)$_PP_CONF['cache_max_age'] > 0) {
        $f = fopen($cachefile, 'w+');
        if ($f) {
            fwrite($f, $retval);
        }
    }
    return $retval;
}


/**
*   Updates the product table with the new rating and vote count.
*   Requires glFusion 1.1.7 or higher.
*
*   @since  version 0.4.0
*   @param  mixed   $id     Product ID
*   @param  float   $rating Rating Value
*   @param  integer $votes  Number of votes cast
*/
function plugin_itemrated_paypal($id, $rating, $votes)
{
    global $_TABLES;

    $rating = number_format($rating, 2, '.', '');
    $sql = "UPDATE {$_TABLES['paypal.products']}
            SET rating = $rating, votes = $votes
            WHERE id=".$id;
    DB_query($sql);
    return true;
}


/**
*   Determine if the current user is allowed to rate an item.
*   Curretly, all logged-in users can rate (even admin).
*   Requires glFusion 1.1.7 or higher.
*
*   @since  version 0.4.0
*   @param  mixed   $item_id    ID of product to be rated
*   @param  integer $uid        User ID of the one doing the rating
*   @return boolean             True if user is allowed, false if not
*/
function plugin_canuserrate_paypal($item_id, $uid)
{
    global $_PP_CONF;

    if ($_PP_CONF['anon_can_rate'] == 0 && COM_isAnonUser()) {
        return false;
    } else {
        return true;
    }
}


/**
*   Save the current user's shopping cart
*
*   @uses   Cart::SaveUserCart()
*   @since  version 0.5.0
*   @param  integer $uid    User ID (not really used
*/
function X_plugin_user_logout_paypal($uid)
{
    global $_PP_CONF;

    // Does not apply to anonymouse users or if cart is disabled
    if ($uid < 2 || $_PP_CONF['ena_cart'] == 0) return;

    //PAYPAL_setCart();
    $cart = Paypal\Cart::getInstance();
    $cart->SaveUserCart($uid);
    $cart->Clear($uid);
}


/**
*   Handle actions needed when the current user logs in.
*   Merges the saved cart with any values added while browsing anonymously
*
*   @param  integer $uid    User ID
*/
function plugin_user_login_paypal($uid)
{
    global $_PP_CONF;

    if ($uid < 2 || $_PP_CONF['ena_cart'] == 0) return;

    $anon_cart = Paypal\Cart::getAnonCartID();  // get the anonymous cart ID
    if (empty($anon_cart)) return;             // save a step if there's no anon cart
    $user_cart = Paypal\Cart::getInstance();    // get the logged-in user's cart
    $user_cart->Merge($anon_cart);
}


/**
*   Run scheduled tasks.
*   1. Delete old carts that aren't assigned to any user.
*   2. Call PLG_itemDeleted to notify plugins that an item is not available
*/
function plugin_runScheduledTask_paypal()
{
    global $_PP_CONF, $_TABLES, $_CONF;

    if ($_PP_CONF['ena_cart'] == 1) {
        // Delete old shopping carts, if the cart is enabled
        $sql = "DELETE FROM {$_TABLES['paypal.cart']}
            WHERE cart_uid = 1
            AND last_update < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        //COM_errorLog($sql);
        DB_query($sql);
    }

    // Clean up the HTML cache if used
    if ((int)$_PP_CONF['cache_max_age'] > 0) {
        $cachedir = "{$_CONF['path_data']}/layout_cache";
        $files = glob("$cachedir/__paypal__*");
        if ($files) {
            $timeAgo = time() - (int)$_PP_CONF['cache_max_age'];
            foreach ($files as $file) {
                if (@filemtime($file) < $timeAgo) {
                    @unlink($file);
                }
            }
        }
    }

    // Treat disabled and expired products as "deleted" to get them out of
    // search results, etc.
    $where = " WHERE enabled = 1 AND avail_end < '" .
            PAYPAL_now()->format('Y-m-d', true) . "'";
    $sql = "SELECT id, prod_type FROM {$_TABLES['paypal.products']} $where";
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        PLG_itemDeleted($A['id'], $A['prod_type']);
        DB_query("UPDATE {$_TABLES['paypal.products']}
                SET enabled = 0 $where");
    }
}


/**
*   Return false since this plugin doesn't support webservices
*
*   @return boolean     True, if webservices are supported
*/
function plugin_wsEnabled_paypal()
{
    return false;
}


/**
*   Determine if a given item number belongs to a plugin.
*   For now, this simply checks whether the item number is numeric.  If
*   it is, it's assumed to be a catalog item.  If it's non-numeric, it's
*   assumed to be a plugin-supplied item where the item number is
*   formated as "pi_name:item_number:other_opts"
*
*   @param  mixed   $item_number    Item Number to check
*   @return boolean     True if it's a plugin item, false if it's ours
*/
function PAYPAL_is_plugin_item($item_number)
{
    if (!is_numeric($item_number)) {
        return true;
    } else {
        return false;
    }
}


/**
*   Helper function to get image urls from LGLIB_ImageUrl()
*
*   @param  string  $imgname    Image name, no path
*   @param  integer $width      Optional width, assume thumbnail
*   @param  integer $height     Optional height, assume thumbnail
*   @return string      URL to resized image
*/
function PAYPAL_ImageUrl($imgname, $width=0, $height=0)
{
    global $_PP_CONF;

    $width = $width == 0 ? $_PP_CONF['max_thumb_size'] : (int)$width;
    $height = $height == 0 ? $_PP_CONF['max_thumb_size'] : (int)$height;
    return LGLIB_ImageUrl($_PP_CONF['image_dir'] . '/' . $imgname, $width, $height);
}


/**
*   Send debug info to the error log.
*
*   If the plugin configuration is set to log messages indicated by $type,
*   then the message is logged.
*
*   @param  string  $msg    Text message to display, may be blank.
*   @param  string  $type   Type of message
*/
function PAYPAL_debug($msg, $type = 'debug')
{
    global $_PP_CONF;

    switch ($type) {
    case 'error':
        // Always log an error message
        COM_errorLog('PP Error: ' . $msg, 1);
        break;
    case 'debug':
    default:
        // Log a message only if the message type is enabled
        if (isset($_PP_CONF[$type]) && $_PP_CONF[$type] == 1) {
            COM_errorLog('PP DEBUG: ' . $msg, 1);
        }
    }
}


/**
*   Select the template to use for product detail pages, by default
*
*   @return array   $option => path
*/
function plugin_configmanager_select_product_tpl_ver_paypal()
{
    $A = array();
    $dirs = glob(__DIR__ . '/templates/detail/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $tmp = explode('/', $dir);
        $opt = end($tmp);
        $A[$opt] = $opt;
    }
    return $A;
}


/**
*   Select the template to use for product list pages
*
*   @return array   $option => path
*/
function plugin_configmanager_select_list_tpl_ver_paypal()
{
    $A = array();
    $dirs = glob(__DIR__ . '/templates/list/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $tmp = explode('/', $dir);
        $opt = end($tmp);
        $A[$opt] = $opt;
    }
    return $A;
}


/**
*   Create a currency dropdown list for selecting the default currency.
*
*   @return array   Array of description => value
*/
function plugin_configmanager_select_currency_paypal()
{
    $A = array();
    $tmp = Paypal\Currency::GetAll();
    foreach ($tmp as $code => $data) {
        $A["{$code} - {$data['name']}"] = $code;
    }
    return $A;
}


/*   Displays account information in the user's profile.
*
*   @param  integer $uid    User ID whose profile is being displayed
*   @param  object  &$T     Pointer to template object
*   @return string          HTML to include in profile page
*/
function Xplugin_profilevariablesdisplay_paypal($uid, &$T)
{
    global $_TABLES, $_USER, $_PP_CONF;

    $retval = '';

    // Make sure that a valid UID was passed in.
    if (empty($uid) || (!plugin_ismoderator_paypal() && $uid != $_USER['uid'])) {
        return '';
    }

    $LT = new \Template(__DIR__ . '/templates/');
    $LT->set_file(array(
        'block' => 'profileblock.thtml',
    ));
    $LT->set_var('is_uikit', $_PP_CONF['_is_uikit']);
    $U = Paypal\UserInfo::getInstance($uid);
    $Cur = new Paypal\Currency();
    $gc_bal = Paypal\Coupon::getUserBalance($uid);
    if ($gc_bal > 0) {
        $LT->set_var('gc_bal', $Cur->Format($gc_bal));
    }
    if (!empty($U->cart)) {
        $cart = true;
        $LT->set_var('cart', true);
    } else {
        $cart = false;
    }
    $ord_count = DB_count($_TABLES['paypal.orders'], "uid = $uid");
    $LT->set_var('orders', $ord_count);

    // Only display the template if there's something to show
    if ($ord_count > 0 || $cart || $gc_bal > 0) {
        $LT->parse('output', 'block');
        $existingFields = $T->get_var('plugin_fields');
        $T->set_var('plugin_fields',
                $existingFields . $T->finish($LT->get_var('output')));
    }
}


/**
*   Display variables related to the shop
*
*   @param  integer $uid        User ID
*   @param  string  $panel      Panel being populated
*   @param  string  $fieldset   Name of fieldset being populated
*   @return string              HTML for edit form. Never NULL.
*/
function plugin_profileedit_paypal($uid, $panel, $fieldset)
{
    global $_PP_CONF, $_USER, $_TABLES;

    $retval = '';
    // Make sure that a valid UID was passed in.
    if (empty($uid) || (!plugin_ismoderator_paypal() && $uid != $_USER['uid'])) {
        return $retval;
    }

    if ( ($panel == 'useredit') || ($panel == '' && $fieldset == '') ) {
    //if (($panel == '' || $panel == 'useredit') && $fieldset == '') {
        $LT = new \Template(__DIR__ . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_var('is_uikit', $_PP_CONF['_is_uikit']);
        $U = Paypal\UserInfo::getInstance($uid);
        $gc_bal = Paypal\Coupon::getUserBalance($uid);
        $Cur = new Paypal\Currency();
        if ($gc_bal > 0) {
            $LT->set_var('gc_bal', $Cur->Format($gc_bal));
        }
        if (!empty($U->cart)) {
            $cart = true;
            $LT->set_var('cart', true);
        } else {
            $cart = false;
        }
        $ord_count = DB_count($_TABLES['paypal.orders'], "uid = $uid");
        $LT->set_var('orders', $ord_count);

        // Only display the template if there's something to show
        if ($ord_count > 0 || $cart || $gc_bal > 0) {
            $LT->parse('output', 'block');
            $retval .= $LT->finish($LT->get_var('output'));
        }
    }
    return $retval;
}



/**
*   Get all parent categories and a product count.
*   Recurses through all child categories.
*
*   @param  integer $parent_cat Parent category ID to check
*   @return integer             Product count for all child categories
*/
function PAYPAL_child_cat_product_count($parent_cat)
{
    global $_TABLES;

    $parent_total = 0;

    $sql = "SELECT c.cat_id, c.cat_name, count(p.id) AS cnt
            FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON p.cat_id = c.cat_id
            WHERE c.cat_id > 0
            AND c.enabled = 1
            AND c.parent_id = $parent_cat " .
            SEC_buildAccessSql('AND', 'c.grp_access') .
            " GROUP BY c.cat_id
            ORDER BY c.cat_name";
    //echo $sql;die;
    $result = DB_query($sql);
    while ($A = DB_fetchArray($result, false)) {
        // Add the total products in the child category
        $parent_total += (int)$A['cnt'];
        // And call again to get the child's children
        $parent_total += PAYPAL_child_cat_product_count($A['cat_id']);
    }
    return $parent_total;
}


/**
*   Explode a full item ID into item_id and options
*   If $opt_string is set then the option string is returned
*   unchanged, otherwise option IDs are returned as an array
*
*   @param  string  $item   Original item ID, e.g. "4|20,17"
*   @param  boolean $opt_string True to return options as a string
*   @return array       Array of (item_id, options)
*/
function PAYPAL_explode_opts($item, $opt_string = false)
{
    if (strpos($item, '|')) {
        list($item_id, $options) = explode('|', $item);
    } else {
        $item_id = $item;
        $options = NULL;
    }
    if ($options === NULL) $options = '';
    if (!$opt_string) {
        if (empty($options)) {
            $options = array();
        } else {
            $options = explode(',', $options);
        }
    }
    return array($item_id, $options);
}


/**
*   Get a date object corresponding to "now".
*   Used to avoid creating a lot of Date objects or cluttering
*   the global variables
*
*   @return object      Date object
*/
function PAYPAL_now()
{
    global $_CONF;
    static $dt = NULL;
    if ($dt === NULL) {
        $dt = new Date('now', $_CONF['timezone']);
    }
    return $dt;
}


function PAYPAL_timezone()
{
    global $_CONF;
    static $tz = NULL;
    if ($tz === NULL) {
        $tz = new DateTimeZone($_CONF['timezone']);
    }
    return $tz;
}


/**
*   Get an icon from the CSS icon set, either UIkit or FontAwesome
*
*   @param  string  $name   Base name of icon, e.g. "edit" for "uk-icon-edit"
*   @param  string  $style  Style name from local CSS
*   @return string      Class names for icon
*/
function PP_getIcon($name, $style='info')
{
    global $_PP_CONF;

    if ($style != '')
        $style = ' pp-icon-' . $style;
    return $_PP_CONF['_iconset'] . '-' . $name . $style;
}

require_once __DIR__ . '/services.inc.php';

/**
*   Provides the getItemInfo function for products
*
*   @param  string  $id         Product ID
*   @param  string  $fields     Comma-separated list of fields to return
*   @param  integer $uid        User ID
*   @param  array   $options    Not used
*   @return array           Array of product information
*/
function plugin_getiteminfo_paypal($id, $what, $uid=0, $options=array())
{
    global $_TABLES, $_CONF, $LANG_PP;

    $properties = explode(',', $what);
    $fields = array('p.id');        // always get item id
    $retval = array();
    $buildingSearchIndex = false;
    foreach ($properties as $p) {
        switch ($p) {
        case 'title':
            $fields[] = 'p.name';
            break;
        case 'excerpt':
        case 'introtext':
            $fields[] = 'p.short_description';
            break;
        case 'bodytext':
            $fields[] = 'p.description';
            break;
        case 'description':
            $fields[] = 'p.short_description';
            $fields[] = 'p.description';
            break;
        case 'searchidx':
            $buildingSearchIndex = true;
            $fields[] = 'p.name';
            $fields[] = 'p.keywords';
        case 'content':
            $fields[] = 'p.short_description';
            $fields[] = 'p.description';
            break;
        case 'date':
            $fields[] = 'UNIX_TIMESTAMP(p.dt_add) as unixtime';
            break;
        case 'price':
            $fields[] = 'p.price';
            break;
        case 'search_index':
            $buildingSearchIndex = true;
            break;
        case 'perms':
            $fields[] = 'c.grp_access';
            break;
        }
    }
    if (count($fields) == 0) {
        return $retval;
    }
    $fields = array_unique($fields);
    $fields = implode(',', $fields);

    $retval = array();
    $sql = "SELECT $fields
             FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON c.cat_id = p.cat_id ";
    if ($id == '*') {
        if ( $buildingSearchIndex ) {
            $today = date('Y-m-d');
            $where = " WHERE enabled = 1 AND avail_beg <= '$today' AND avail_end >= '$today' ";
        } else {
            $where = ' WHERE 1=1 ';
        }
    } else {
        $where = " WHERE (p.id = '" . DB_escapeString($id) . "')";
    }
    if ($uid > 0) {
        $where .= SEC_buildAccessSql($clause = 'AND', $field = 'c.grp_access');
    }
    if (isset($options['cat_id'])) {
        $where .= ' AND p.cat_id = ' . (int)$options['cat_id'];
    }

    $sql .= $where;
    $res = DB_query($sql);

    $props = array();
    while ($A = DB_fetchArray($res, false)) {
        foreach ($properties as $p) {
            switch ($p) {
            case 'introtext':
                $props[$p] = $A['short_description'];
                break;
            case 'bodytext':
                $props[$p] = $A['description'];
                break;
            case 'description':
                $props[$p] = $A['short_description'] . ' ' . $A['description'];
                break;
            case 'title':
                $props[$p] = $A['name'];
                break;
            case 'searchidx':
                $props[$p] = $A['name'] . ' ' .
                        $A['short_description'] . ' ' .
                        $A['description'] . ' ' .
                        $A['keywords'];
                break;
            case 'content':
                $props[$p] = $A['short_description'] . ' ' .
                            $A['description'];
                break;
            case 'date':
                $props[$p] = $A['unixtime'];
                break;
            case 'perms':
                $props['perms'] = array(
                    'owner_id' => 2,    // admin
                    'group_id' => $A['grp_access'],
                    'perm_owner' => 3,
                    'perm_group' => 2,
                    'perm_members' => 1,
                    'perm_anon' => 1,
                );
                break;
            default:
                $props[$p] = isset($A[$p]) ? $A[$p] : NULL;
                break;
            }
        }
        $props['url'] = COM_buildUrl($_CONF['site_url'] .
                    '/paypal/detail.php?id=' . urlencode($A['id']));
        $mapped = array();
        foreach ($props as $key => $value) {
            if ($id == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($id == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }
    if ($retval === '' || (is_array($retval) && count($retval) == 0)) {
        return NULL;
    } else {
        return $retval;
    }
}


/**
*   Export user data when requested.
*
*   @param  integer $uid        User ID
*   @param  string  $email      User Email Address (not used)
*   @param  string  $username   User Name (not used)
*   @param  string  $ip         IP address (not used)
*   @return string              XML-formatted data
*/
function plugin_privacy_export_paypal($uid, $email='', $username='', $ip='')
{
    $U = Paypal\UserInfo::getInstance($uid);

    $retval = "<paypal>\n";

    foreach ($U->addresses as $addr) {
        foreach (Paypal\UserInfo::Fields() as $fld) {
            if ($addr[$fld] != '') {
                $d = addSlashes(htmlentities($addr[$fld]));
                $retval .= '<'.$fld.'>'.$d.'</'.$fld.">\n";
            }
        }
    }
    $retval .= "</paypal>\n";

    if ( function_exists('tidy_repair_string')) {
        $retval = tidy_repair_string($retval, array('input-xml' => 1));
    }
    return $retval;
}


/**
*   Get an array variable, first checking whether it's set
*
*   @param  array   $A          Array where var should be
*   @param  string  $varname    Name of array element
*   @param  string  $type       Type of return value
*   @param  mixed   $default    Default value, if any
*   @return     Array element or NULL, cast to $type
*/
function PP_getVar($A, $varname, $type = 'string', $default = NULL)
{
    $val = isset($A[$varname]) ? $A[$varname] : $default;
    switch ($type) {
    case 'int':
        return (int)$val;
        break;
    case 'bool':
        return (bool)$val;
        break;
    case 'float':
        return (float)$val;
        break;
    case 'array':
        if (!is_array($val)) {
            $val = array($val);
        }
        return $val;
        break;
    case 'string':
    default:
        return (string)$val;
        break;
    }
}


/**
*   Get the sales tax rate to charge.
*   Currently just returns the globally-configured rate
*
*   @return float   Tax Rate
*/
function PP_getTaxRate()
{
    global $_PP_CONF;

    return PP_getVar($_PP_CONF, 'tax_rate', 'float');
}


/**
*   Get the sales tax for a taxable amount
*
*   @param  float   $amount     Taxable amount
*   @return float               Sales Tax
*/
function PP_getTax($amount)
{
    return round($amount * PP_getTaxRate(), 2);
}


/**
*   Helper function to get a template object.
*
*   @param  string  $name   Base name of template file, e.g. "editor"
*   @param  string  $id     ID of template, e.g. "editform"
*   @param  string  $path   Optional path if not "templates/"
*   @return string      Full filename, e.g. "editor.uikit.thtml"
*/
function PP_getTemplate($name, $id='', $path='')
{
    global $_PP_CONF;

    $templates = array();

    // Put parameters into an array if not already.
    if (!is_array($name) && !empty($id)) {
        $templates = array($id => $name);
    } elseif (is_array($name)) {
        $templates = $name;
    }

    // Add additional path info if needed
    if (!empty($path) && $path[0] == '/') $path = substr($path, 1);
    if (substr($path, 0, 9) != 'templates') {
        $path = 'templates/' . $path;
    }

    // May use "switch" later, for now there are only two options
    $tpltype = $_PP_CONF['_is_uikit'] ? '.uikit' : '';
    foreach ($templates as $t_id =>$t_name) {
        if (file_exists(__DIR__ . "/$path/$t_name$tpltype.thtml")) {
            $templates[$t_id] = "$t_name$tpltype.thtml";
        } else {
            $templates[$t_id] = "$t_name.thtml";
        }
    }
    $T = new Template(__DIR__ . '/' . $path);
    $T->set_file($templates);
    return $T;
}

?>

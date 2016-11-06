<?php
/**
*   glFusion API functions for the Paypal plugin.
*   Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net
*   @copyright  Copyright (c) 2009-2016 Lee Garner
*   @copyright  Copyright (c) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.5.7
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

/** Include paypal config file  */
require_once dirname(__FILE__) . '/paypal.php';

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

// Define comment status
define('PP_COMMENTS_ENABLED',   0);
define('PP_COMMENTS_CLOSED' ,   1);
define('PP_COMMENTS_DISABLED',  -1);

// Order status codes
define('PP_STATUS_OPEN', 0);
define('PP_STATUS_PAID', 1);

/**  Define the main $_SESSION variable name.  We store things like the
*    shopping cart ID and previous/next page views here. */
define('PP_CART_VAR', 'glPPcart');

if (file_exists(PAYPAL_PI_PATH . '/config.php')) {
    /** Include local configuration overrides */
    include_once PAYPAL_PI_PATH . '/config.php';
}

// Load the site's language file, falling back to English.
$langpath = PAYPAL_PI_PATH . '/language';
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

//  Set user ID = 1 for anonymous users.
if (empty($_USER['uid']) || $_USER['uid'] < 2) {
    $_USER['uid'] = 1;
    $_USER['username'] = 'anonymous';
    $_USER['fullname'] = 'Anonymous';
}

/** Import plugin-specific functions */
function USES_paypal_functions()
{   require_once PAYPAL_PI_PATH . '/paypal_functions.inc.php';  }

/** Import product class */
function USES_paypal_class_product()
{   require_once PAYPAL_PI_PATH . '/classes/product.class.php';  }

/** Import category class */
function USES_paypal_class_category()
{   require_once PAYPAL_PI_PATH . '/classes/category.class.php';  }

/** Import CSS-based menu */
function USES_paypal_class_menu()
{   require_once PAYPAL_PI_PATH . '/classes/menu.class.php';  }

/** Import IPN handler class.
*   Imports only the required IPN class.
*
*   @param  string  $gateway    Gateway being used.
*/
function USES_paypal_class_ipn($gateway='paypal')
{
    $filename = PAYPAL_PI_PATH . '/classes/ipn/' . $gateway . '_ipn.class.php';
    if (is_file($filename))
        require_once $filename;
}

/** Import image handling class for products */
function USES_paypal_class_productimage()
{   require_once PAYPAL_PI_PATH . '/classes/ProductImage.class.php';   }

/** Import image handling class for categories */
function USES_paypal_class_categoryimage()
{   require_once PAYPAL_PI_PATH . '/classes/CategoryImage.class.php';   }

/** Import image handling class for categories */
function USES_paypal_class_attribute()
{   require_once PAYPAL_PI_PATH . '/classes/attribute.class.php';   }

/** Import file upload handling class */
function USES_paypal_class_ppFile()
{   require_once PAYPAL_PI_PATH . '/classes/ppFile.class.php';   }

/** Import shopping cart class */
function USES_paypal_class_cart()
{   require_once PAYPAL_PI_PATH . '/classes/cart.class.php';    }

/** Import user information class */
function USES_paypal_class_userinfo()
{   require_once PAYPAL_PI_PATH . '/classes/UserInfo.class.php';    }

/** Import order class */
function USES_paypal_class_order()
{   require_once PAYPAL_PI_PATH . '/classes/order.class.php';    }

/** Upgrade functions */
function USES_paypal_upgrade()
{   require_once PAYPAL_PI_PATH . '/upgrade.inc.php';   }

/** Import workflow handling */
function USES_paypal_class_workflow()
{   require_once PAYPAL_PI_PATH . '/classes/workflow.class.php';    }

/** Import order status workflow handling */
function USES_paypal_class_orderstatus()
{   require_once PAYPAL_PI_PATH . '/classes/orderstatus.class.php'; }

/** Import currency class */
function USES_paypal_class_currency()
{   require_once PAYPAL_PI_PATH . '/classes/currency.class.php';    }

/** Define global configuration variable to hold payment gateways */
$_PP_CONF['gateways'] = array();

/** Store the current SQL-formatted timestamp since it's used often */
USES_class_date();
$_PP_CONF['now'] = new Date('now', $_CONF['timezone']);

// Mark whether this is a uikit theme. Save to global conf var
// so it's available where needed.
$_PP_CONF['_is_uikit'] = $_SYSTEM['framework'] == 'uikit' ? true : false;

global $ppGCart;
$ppGCart = NULL;
function PAYPAL_setCart()
{
    global $ppGCart;
    if ($ppGCart === NULL) {
        USES_paypal_class_cart();
        $ppGCart = new ppCart();
    }
}


/**
*   Import a single payment gateway.
*   This function imports the requested gateway, regardless of whether
*   it's enabled in the configuration.
*   If the gateway name is omitted, then only the base class is imported.
*
*   @param  string  $gw_name    Name of gateway (class file) to import
*   @return boolean             True on success, False on failure
*/
function USES_paypal_gateway($gw_name='')
{
    if ($gw_name == '') {
        $classfile = PAYPAL_PI_PATH . '/classes/paymentgw.class.php';
    } else {
        $classfile = PAYPAL_PI_PATH . '/classes/gateways/' . 
                    $gw_name . '.class.php';
    }

    if (is_file($classfile)) {
        require_once $classfile;
        return true;
    } else {
        return false;
    }
}


/**
*   Import the payment gateways.
*
*   All installed gateways can be loaded (for administration), or only
*   the enabled gateways can be loaded (the default).
*
*   @param  boolean $all    True to get all gateways, False for only enabled
*/
function PAYPAL_loadGateways($all = false)
{
    global $_PP_CONF, $_TABLES;

    // Might be called multiple times, only needs to populate the array once.
    if (!empty($_PP_CONF['gateways'])) {
        return;
    }

    // Include the base gateway class
    require_once PAYPAL_PI_PATH . '/classes/paymentgw.class.php';

    // Load the enabled gateways
    $sql = "SELECT id, enabled, services
            FROM {$_TABLES['paypal.gateways']}";
    // If not loading all gateways, get just then enabled ones
    if (!$all) $sql .= ' WHERE enabled=1';
    $sql .= ' ORDER BY orderby';
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        // For each available gateway, load its class file and add it
        // to the global array
        if (USES_paypal_gateway($A['id'])) {
            $A['services'] = @unserialize($A['services']);
            $_PP_CONF['gateways'][] = $A;
        }
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
    global $_PP_CONF, $ppGCart;

    $display = '';

    // Only show if no topic is requested
    if ($_PP_CONF['centerblock'] == 1 && $topic == '') {
        USES_paypal_functions();
        USES_paypal_class_cart();
        $ppGCart = new ppCart();
        $display = PAYPAL_siteHeader();
        $T = new Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file('title', 'paypal_title.thtml');
        $T->set_var('title', $LANG_PP['main_title']);
        $display .= $T->parse('', 'title');
        $display .= PAYPAL_userMenu('');
        $display .= PAYPAL_ProductList();
        $display .= PAYPAL_siteFooter();
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
    global $_TABLES, $_PP_CONF;

    $pi_name = $_PP_CONF['pi_name'];

    // Get the current version, compare to the version in the plugin code
    $current_ver = DB_getItem($_TABLES['plugins'], 'pi_version',
        "pi_name = '$pi_name'");
    if (!$current_ver) {
        COM_errorLog("Error getting the $pi_name Plugin version",1);
        return '03';
    }

    // if we're at the current version, what are we doing here?
    if ($current_ver >= $_PP_CONF['pi_version']) {
        COM_errorLog("Current version $current_ver is already up to date",1);
        return '06';
    }

    // If we're still here, perform the upgrade functions.
    USES_paypal_upgrade();
    $error = PAYPAL_do_upgrade($current_ver);
    if ($error) {
        COM_errorLog("Error updating the $pi_name Plugin version",1);
        return '04';
    }

    // now update the current version number.
    DB_query("UPDATE {$_TABLES['plugins']} SET
            pi_version = '{$_PP_CONF['pi_version']}',
            pi_gl_version = '{$_PP_CONF['gl_version']}',
            pi_homepage = '{$_PP_CONF['pi_url']}'
        WHERE pi_name = '$pi_name'");

    if (DB_error()) {
        COM_errorLog("Error updating the $pi_name Plugin version",1);
        return '05';
    }

    CTL_clearCache($_PP_CONF['pi_name']);
    COM_errorLog("Succesfully updated the $pi_name Plugin!",1);
    return true;

}


/**
 *  Returns the main menu items for the plugin.
 *
 *  @return array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_paypal() {
    global $_CONF, $_PP_CONF, $LANG_PP;

    $menuitems = array();
    if (SEC_hasRights('paypal.user,paypal.admin,paypal.view', 'OR') &&
            $_PP_CONF['menuitem']) {
        $menuitems[$LANG_PP['mnu_paypal']] = PAYPAL_URL . '/index.php';
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
    global $_CONF, $LANG03, $_TABLES;

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_paypal())
        return;

    $id = (int)$id;
    $title = strip_tags($title);    // No HTML in the comment title
    $pid = COM_applyFilter($pid, true);
    $postmode = COM_applyFilter ($postmode);

    // Actually save the comment
    $ret = CMT_saveComment($title, $comment, $id, $pid, 'paypal', $postmode);

    if ($ret > 0) {     // failure
        return COM_siteHeader()
            . CMT_commentform ($title, $comment, $id, $pid,
                    'paypal', $LANG03[14], $postmode)
            . COM_siteFooter();
    } else {        // success - update the comment counter for this item
        $comments = (int)DB_count($_TABLES['comments'], 
                        array('type', 'sid'), 
                        array('paypal', $id));

        DB_query("UPDATE {$_TABLES['paypal.products']}
                SET comments = $comments
                WHERE id = $id", 1);

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
    global $_USER, $LANG_ACCESS;

    $retval = '';

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_paypal())
        return $retval;

    USES_paypal_class_product();
    $item = new Product($id);
    if ($item->isNew)
        return $retval;

    $allowed = 1;

    if ($allowed == 1) {
        $delete_option = (SEC_hasRights('paypal.admin'));
        $retval .= CMT_userComments($id, $title, 'paypal', $order, $format,
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
    global $_CONF,$_FM_TABLES, $_TABLES;

    if (SEC_hasRights('paypal.admin')) {
        if (CMT_deleteComment($cid, $id, 'paypal') == 0) {
            $count = DB_count($_TABLES['comments'],
                        array('sid', 'type'),
                        array($id, 'paypal'));
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
 *  Add an option for the plugin in the command and control area.
 *
 *  @return array   Array containing (pi_name, admin_url, icon_url)
 */
function plugin_cclabel_paypal()
{
    global $_PP_CONF;

    if (SEC_hasRights('paypal.admin')) {
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

    if (SEC_hasRights('paypal.admin')) {
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
    global $LANG_PP, $_TABLES, $ppGCart, $_USER;

    $retval = array();
    if (!COM_isAnonUser() &&
        DB_count($_TABLES['paypal.orders'], 'uid', $_USER['uid']) > 0) {
        $retval[] = array($LANG_PP['purchase_history'],
                PAYPAL_URL . '/index.php?orderhist=x');
    }
    PAYPAL_setCart();
    if ($ppGCart->hasItems()) {
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
 *  Performs operations when a user (or admin) deletes an account
 *
 *  Remove purchases from the paypal purchases table.
 *
 *  @TODO   Rather than drop rows, archive them
 *  @param  int $uid    User ID that is being deleted
 */
function plugin_user_delete_paypal($uid) {
    global $_TABLES;

    $sql = "DELETE FROM {$_TABLES['paypal.purchases']} WHERE user_id = $uid";
    DB_query($sql);
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
function plugin_autotags_paypal ($op, $content = '', $autotag = '') {
    global $_CONF, $_TABLES;

    if ($op == 'tagname' ) {
        return array('paypal','paypal_cat');
    } else if ($op == 'parse') {
        switch ($autotag['tag']) {
        case 'paypal':
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

        case 'paypal_cat':
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
    global $_USER, $_SERVER, $LANG_PP, $_PLUGINS;

    // If this plugin or a required plugin isn't available, abort gracefully
    if (!in_array('paypal', $_PLUGINS) ||
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
*   Plugins should call PLG_invokeService('paypal, 'genButton'...) instead.
*
*   Provided $vars should include:
*       'item_number', 'item_name', 'amount', and optionally 'item_type'
*
*   @deprecated version 0.5.0
*   @uses   PaymentGw::ExternalButton()
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
            $T = new Template(PAYPAL_PI_PATH . '/templates');
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
        PAYPAL_loadGateways();
        $gw_id = '';
        if (!empty($_PP_CONF['gateways'])) {

            // Get the first gateway that supports the button type
            foreach ($_PP_CONF['gateways'] as $gw_info) {
                if ($gw_info[$btn_type] == 1 && class_exists($gw_info['id'])) {
                    $gw = new $gw_info['id'];
                    $retval = $gw->ExternalButton($vars, $btn_type);
                    break;
                }
            }
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
        PAYPAL_PI_PATH . '/js/toggleEnabled.js',
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
    if (version_compare(GVERSION, '1.6.0', '<')) {
        $file = 'config.legacy.html';
    } else {
        $file = 'config.html';
    }
    $doc_url = PAYPAL_getDocURL($file, $doclang);
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
*   @see    Product::showForm()
*   @param  string  $file       HTML file
*   @param  string  $doclang    Documentation language
*   @return string              URL to document directory or file
*/
function PAYPAL_getDocURL($file='', $doclang='english')
{
    $doc_path = PAYPAL_PI_PATH . "/docs/$doclang/";
    $doc_url = '';

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
                'paypal.specials',
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
        ),
        // List all vars by name
        'vars'=> array('paypal_gid'),
    );
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
    global $_TABLES;

    $enabled = $enabled == true ? 1 : 0;
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
*   Each item in the cart is shown along with its quantity, and links
*   to view the cart or check out.
*
*   @return string      HTML for the shopping cart block.
*/
function phpblock_paypal_cart()
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_USER, $_TABLES;

    if ($_PP_CONF['ena_cart'] == 0) return '';

    USES_paypal_class_cart();
    USES_paypal_class_product();

    $cart = new ppCart();

    // If the cart is empty, the block isn't shown
    if (!$cart->hasItems()) return '';

    $T = new Template(PAYPAL_PI_PATH . '/templates');
    $T->set_file('shopping_cart', 'blk_cart.thtml');

    $T->set_block('shopping_cart', 'CartRow', 'cRow');

    foreach ($cart->Cart() as $id=>$item) {
        $link = true;
        // See if this is a plugin item, will have a ':' in the item number
        if (strstr($item['item_id'], ':')) {
            $item_info = explode(':', $item['item_id']);
            if (PAYPAL_is_plugin_item($item_info[0])) $link = false;
        }

        list($item_id, $options) = explode('|', $item['item_id']);
        $P = new Product($item_id);
        $item_total = $item['price'] * $item['quantity'];

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
        $text_names = explode('|', $P->custom);

        if (!empty($text_names) && is_array($item['extras']['custom'])) {
            foreach ($item['extras']['custom'] as $id=>$val) {
                $opt_arr[] = $text_names[$id] . ': ' . $val;
            }
        }
        $opt_str = implode('<br />', $opt_arr);

        $T->set_var(array(
            'item_id'       => $item_id,
            'options'       => $opt_str,
            'item_name'     => $item['descrip'],
            'price'         => COM_numberFormat($item['price'], 2),
            'quantity'      => $item['quantity'],
            'item_total'    => COM_numberFormat($item_total, 2),
            'pi_url'        => PAYPAL_URL,
            'link_item'     => $link ? 'true' : '',
        ) );
        $T->parse('cRow', 'CartRow', true);
        $total += $item_total;            
    }
    $T->set_var(array(
        'grand_total'   => COM_numberFormat($total, 2),
    ) );
    $T->parse('output', 'shopping_cart');
    $block = $T->finish ($T->get_var('output'));

    return $block;
}


/**
*   Catalog search block
*
*   @return string  HTML for search form
*/
function phpblock_paypal_user()
{
    global $_CONF, $_TABLES, $LANG_PP;

    $T = new Template(PAYPAL_PI_PATH . '/templates/');
    $T->set_file('search', 'blk_search.thtml');

    $category_options = COM_optionList($_TABLES['paypal.categories'], 
       'cat_id, cat_name', '', 1, '1=1 ' . COM_getPermSQL('AND'));

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
            AND c.parent_id = 0
            " . COM_getPermSQL('AND', 0, 2, 'c') . "
            GROUP BY c.cat_id
            ORDER BY c.cat_name";
                //HAVING cnt > 0
    //echo $sql;die;
    $result = DB_query($sql);
    if (!$result || DB_numRows($result) == 0)
        return '';

    $T = new Template(PAYPAL_PI_PATH . '/templates');
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
            AND pr.show_popular = 1
            " . COM_getPermSQL('AND', 0, 2, 'c') . "
            HAVING cnt > 0
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
            AND pr.show_popular = 1
            " . COM_getPermSQL('AND', 0, 2, 'c') . "
            HAVING cnt > 0
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
*   Plugins should call PLG_invokeService('paypal, 'getCurrency'...) instead.
*
*   @deprecated 0.5.0
*   @return string      Our configured currency code.
*/
function PAYPAL_getCurrency()
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
            COM_getPermSQL('AND', 0, 2, 'c') . "
            $where
            ORDER BY RAND()
            $limit";
    //COM_errorLog($sql);

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

        $T = new Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file(array(
            'blk_random' => 'blk_random.thtml',
            'btn_details' => 'buttons/btn_details.thtml',
        ));

        USES_paypal_class_product();
        $P = new Product();
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
*   @uses   ppCart::SaveUserCart()
*   @since  version 0.5.0
*   @param  integer $uid    User ID (not really used
*/
function plugin_user_logout_paypal($uid)
{
    global $_PP_CONF;

    // Does not apply to anonymouse users or if cart is disabled
    if ($uid < 2 || $_PP_CONF['ena_cart'] == 0) return;

    USES_paypal_class_cart();
    $Cart = new ppCart();
    $Cart->SaveUserCart($uid);
    $Cart->Clear($uid);
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

    USES_paypal_class_cart();
    $C = new ppCart();
    $C->Merge();
}


/**
*   Run scheduled tasks.
*   1. Delete old carts that aren't assigned to any user.
*/
function plugin_runScheduledTask_paypal()
{
    global $_PP_CONF, $_TABLES, $_CONF;

    if ($_PP_CONF['ena_cart'] == 1) {
        // Delete old shopping carts, if the cart is enabled
        $sql = "DELETE FROM {$_TABLES['paypal.cart']} WHERE
            last_update < DATE_SUB(NOW(), INTERVAL 7 DAY)";
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
    $dirs = glob(PAYPAL_PI_PATH . '/templates/detail/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $opt = end(explode('/', $dir));
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
    $dirs = glob(PAYPAL_PI_PATH . '/templates/list/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $opt = end(explode('/', $dir));
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
    USES_paypal_class_currency();

    $A = array();
    $tmp = ppCurrency::GetAll();
    foreach ($tmp as $code => $data) {
        $A["{$code} - {$data['name']}"] = $code;
    }
    return $A;
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
            AND c.parent_id = $parent_cat
            " . COM_getPermSQL('AND', 0, 2, 'c') . "
            GROUP BY c.cat_id
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
    list($item_id, $options) = explode('|', $item);

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

require_once PAYPAL_PI_PATH . '/services.inc.php';

?>

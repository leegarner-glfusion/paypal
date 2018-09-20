<?php
/**
*   Plugin-specific functions for the Paypal plugin for glFusion.
*   Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net
*   @copyright  Copyright (c) 2009-2018 Lee Garner
*   @copyright  Copyright (C) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Order History View.
*   Displays the purchase history for the current user.  Admins
*   can view any user's histor, or all users
*
*   @param  boolean $admin  True if called for admin access, False otherwise
*   @param  integer $uid    User ID to view, current user by default
*   @return string          HTML for order list
*/
function listOrders($admin = false, $uid = 0)
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $LANG_PP_HELP;

    if (!$admin) {
        $uid = $_USER['uid'];
    }
    $uid = (int)$uid;

    USES_lib_admin();

    if (isset($_REQUEST['filt_status']) && !empty($_REQUEST['filt_status'])) {
        $filt_status = DB_escapeString($_REQUEST['filt_status']);
        $where = " WHERE ord.status = '$filt_status'";
    } else {
        $filt_status = '';
        $where = " WHERE ord.status != 'cart'";
    }
    if ($uid > 0) {
        $where .= " AND ord.uid = '" . (int)$uid . "'";
    }

    $isAdmin = $admin == true ? 1 : 0;

    $sql = "SELECT ord.*,
            SUM(itm.quantity * itm.price) as ord_total,
            u.username, $isAdmin as isAdmin
        FROM {$_TABLES['paypal.orders']} AS ord
        LEFT JOIN {$_TABLES['users']} AS u
            ON ord.uid = u.uid
        LEFT JOIN {$_TABLES['paypal.purchases']} AS itm
            ON ord.order_id = itm.order_id";

    $base_url = $admin ? PAYPAL_ADMIN_URL : PAYPAL_URL;
    $header_arr = array(
        array(
            'text' => $LANG_PP['purch_date'],
            'field' => 'order_date',
            'sort' => true,
        ),
        array(
            'text' => $LANG_PP['order_number'],
            'field' => 'order_id',
            'sort' => true,
        ),
        array(
            'text' => $LANG_PP['total'] .
                '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                $LANG_PP_HELP['orderlist_total'] . '"></i>',
            'field' => 'ord_total',
            'sort' => false,
            'align' => 'right',
        ),
        array(
            'text' => $LANG_PP['status'],
            'field' => 'status',
            'sort' => true,
        ),
    );
    if ($admin) {
        $header_arr[] = array(
            'text' => $LANG_PP['username'],
            'field' => 'username',
            'sort' => true,
        );
    }

    $defsort_arr = array(
        'field' => 'ord.order_date',
        'direction' => 'DESC',
    );

    if ($admin) {
        $options = array(
            'chkdelete' => false, 'chkselect' => true, 'chkfield' => 'order_id',
            'chkname' => 'upd_orders',
            'chkactions' => '<input name="updateorderstatus" type="image" src="'
            . PAYPAL_URL . '/images/update.png'
            . '" style="vertical-align:text-bottom;" title="' . $LANG_PP['update_status']
            . '" class="gl_mootip" />&nbsp;' . $LANG_PP['update_status'],
        );
    } else {
        $options = '';
    }

    $query_arr = array('table' => 'paypal.orders',
            'sql' => $sql,
            'query_fields' => array(
                    'billto_name', 'billto_company', 'billto_address1',
                    'billto_address2','billto_city', 'billto_state',
                    'billto_country', 'billto_zip',
                    'shipto_name', 'shipto_company', 'shipto_address1',
                    'shipto_address2','shipto_city', 'shipto_state',
                    'shipto_country', 'shipto_zip',
                    'phone', 'buyer_email', 'ord.order_id',
            ),
            'default_filter' => $where,
            'group_by' => 'ord.order_id',
        );

    $text_arr = array(
        'has_extras' => $admin ? true : false,
        'form_url' => $base_url . '/index.php?orderhist=x&filt_status=' . $filt_status,
        'has_limit' => true,
        'has_paging' => true,
    );

    $filter = "select_event: <select name=\"filt_status\">" . LB .
        '<option value=""';
    if ($filt_status == '') $filter .= ' selected="selected"';
    $filter .= '>All Statuses</option>' . LB;
    foreach (OrderStatus::getAll() as $stat) {
        $sel = $filt_status == $stat->getName() ? 'selected="selected"' : '';
        $filter .= '<option value="' . $stat->getName() . '" ' . $sel . '>' .
            PP_getVar($LANG_PP['orderstatus'], $stat->getName(), 'string', $stat->getName()) .
            '</option>' . LB;
    }
    $filter .= '</select>' . LB;

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display = COM_startBlock('', '',
        COM_getBlockTemplate('_admin_block', 'header'));
    $display .= \ADMIN_list('paypal_orderlog',
        __NAMESPACE__ . '\getPurchaseHistoryField',
        $header_arr, $text_arr, $query_arr, $defsort_arr, $filter);
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
 * Gift Card activity list
 * Allow users to view gift card redemption and application
 *
 * @return  string      HTML for activity listing
 */
function CouponLog($uid = 0)
{
    global $_TABLES, $_USER, $LANG_PP;

    if ($uid == 0) $uid = $_USER['uid'];
    $uid = (int)$uid;

    USES_lib_admin();

    $sql = "SELECT * FROM {$_TABLES['paypal.coupon_log']}";
    $header_arr = array(
        array(
            'text' => $LANG_PP['datetime'],
            'field' => 'ts',
            'sort' => true,
        ),
        array(
            'text' => $LANG_PP['description'],
            'field' => 'msg',
            'sort' => false,
        ),
        array(
            'text' => $LANG_PP['amount'],
            'field' => 'amount',
            'sort' => false,
            'align' => 'right',
        ),
    );

    $defsort_arr = array(
        'field' => 'ts',
        'direction' => 'DESC',
    );

    $query_arr = array(
        'table' => 'paypal.coupon_log',
        'sql' => $sql,
        'query_fields' => array(),
        'default_filter' => 'WHERE uid = ' . $uid,
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => PAYPAL_URL . '/index.php?couponlog=x',
        'has_limit' => true,
        'has_paging' => true,
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display = COM_startBlock('', '',
        COM_getBlockTemplate('_admin_block', 'header'));
    $gc_bal = Coupon::getUserBalance();
    $display .= $LANG_PP['gc_bal'] . ': ' . Currency::getInstance()->Format($gc_bal);
    $display .= \ADMIN_list('paypal_couponlog', __NAMESPACE__ . '\getCouponLogField',
            $header_arr, $text_arr, $query_arr, $defsort_arr);
    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;
}


/**
*   Get an individual field for the gift cart activity screen.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function getCouponLogField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_USER;

    static $dt = NULL;
    $retval = '';

    if ($dt === NULL) {
        // Instantiate a date object once
        $dt = new \Date('now', $_USER['tzid']);
    }

    switch($fieldname) {
    case 'ts':
        $dt->setTimestamp($fieldvalue);
        $retval = '<span class="tooltip" title="' .
                $dt->format($_PP_CONF['datetime_fmt'], false) . '">' .
                $dt->format($_PP_CONF['datetime_fmt'], true) . '</span>';
        break;

    case 'msg':
        switch ($fieldvalue) {
        case 'gc_redeemed':
            // Redeemed and added to account
            $retval = sprintf($LANG_PP['msg_gc_redeemed'], Coupon::maskForDisplay($A['code']));
            break;
        case 'gc_applied':
            // Applied as payment against an order
            $order = COM_createLink($A['order_id'],
                    PAYPAL_URL . '/index.php?order=' . $A['order_id']);
            $retval = sprintf($LANG_PP['msg_gc_applied'], $order);
            //$line['amount'] *= -1;
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        break;
    case 'amount':
        if ($A['msg'] == 'gc_applied') $fieldvalue *= -1;
        $retval = Currency::getInstance()->format($fieldvalue);
        break;
    }
    return $retval;
}


/**
*   Get an individual field for the history screen.
*
*   @param  string  $fieldname  Name of field (from the array, not the db)
*   @param  mixed   $fieldvalue Value of the field
*   @param  array   $A          Array of all fields from the database
*   @param  array   $icon_arr   System icon array (not used)
*   @param  object  $EntryList  This entry list object
*   @return string              HTML for field display in the table
*/
function getPurchaseHistoryField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_USER;

    static $dt = NULL;
    static $Cur = NULL;
    $retval = '';

    if ($dt === NULL) {
        // Instantiate a date object once
        $dt = new \Date('now', $_USER['tzid']);
    }
    if ($Cur === NULL) {
        $Cur = Currency::getInstance();
    }

    switch($fieldname) {
    case 'order_date':
        $dt->setTimestamp($fieldvalue);
        $retval = '<span class="tooltip" title="' .
                $dt->format($_PP_CONF['datetime_fmt'], false) . '">' .
                $dt->format($_PP_CONF['datetime_fmt'], true) . '</span>';
        break;

    case 'name':
        list($item_id, $item_opts) = PAYPAL_explode_opts($A['product_id']);
        if (is_numeric($item_id)) {
            // One of our catalog items, so link to it
            $retval = COM_createLink($fieldvalue,
                PAYPAL_URL . '/index.php?detail=x&amp;id=' . $item_id);
        } else {
            // Probably came from a plugin, just show the product name
            $retval = htmlspecialchars($A['product_id'], ENT_QUOTES, COM_getEncodingt());
        }
        break;

    case 'username':
        if ($A['isAdmin']) {
            $retval = COM_createLink($fieldvalue,
                PAYPAL_ADMIN_URL . '/index.php?orderhist=x&uid=' . $A['uid']);
        } else {
            $retval = COM_createLink($fieldvalue,
                $_CONF['site_url'] . '/users.php?mode=profile&uid=' .
                $A['uid']);
        }
        break;

    case 'quantity':
        $retval = '<div class="alignright">' . $fieldvalue . "</div>";
        break;

    case 'txn_id':
        $base_url = $A['isAdmin'] ? PAYPAL_ADMIN_URL : PAYPAL_URL;
        // Admins get a link to the transaction log, regular users just
        // get the ID to check against their Paypal account.
        if ($A['isAdmin'] == 1) {
            $retval = COM_createLink($fieldvalue,
                $base_url . '/index.php?ipnlog=x&amp;op=single&amp;txn_id=' .
                $fieldvalue);
        } else {
            $retval = $fieldvalue;
        }
        break;

    case 'prod_type':
        // Return the plain-language product type description
        //$retval = $LANG_PP['prod_types'][$fieldvalue];
        $retval = $LANG_PP['prod_types'][$A['prod_type']];
        //if ($fieldvalue == PP_PROD_DOWNLOAD && $A['exptime'] > time() ) {
        if ($A['file'] != '' && $A['exptime'] > time() ) {
            $retval = COM_createLink($retval,
                    PAYPAL_URL . "/download.php?id={$A['product_id']}");
        }
        break;

    case 'short_description':
        // If this is a plugin item, there should be a description recorded
        // in the purchase file.  If not, just take it from the product
        // table.
        if (!empty($A['description'])) {
            $retval = $A['description'];
        } else {
            $retval = $fieldvalue;
        }
        break;

    case 'status':
        if ($A['isAdmin'] && is_array($LANG_PP['orderstatus'])) {
            $retval = OrderStatus::Selection($A['order_id'], 0, $fieldvalue);
        } else {
            $retval = PP_getVar($LANG_PP['orderstatus'], $fieldvalue, 'string', 'Unknown');
        }
        break;

    case 'order_id':
        $base_url = $A['isAdmin'] ? PAYPAL_ADMIN_URL : PAYPAL_URL;
        $retval = COM_createLink($fieldvalue,
                $base_url. '/index.php?order=' . $fieldvalue,
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_PP['vieworder'],
                )
        );
        $retval .= '&nbsp;&nbsp;' . COM_createLink('<i class="uk-icon-mini uk-icon-print gl_mootip"></i>',
                PAYPAL_URL . '/index.php?printorder=' . $fieldvalue,
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_PP['print'],
                    'target' => '_new',
                )
        );
        if (!$_PP_CONF['_is_uikit']) {
            $retval .= '(print)';
        }
        $retval .= '</a>';
        break;

    case 'ord_total':
        $total = (float)$fieldvalue;
        $tip = '<table width=&quot;50%&quot; align=&quot;center&quot;>' . LB;
        $tip .= '<tr><td>' . $LANG_PP['item_total'] .
            ': </td><td style=&quot;text-align:right&quot;>' . $Cur->Format($fieldvalue) . '</td></tr>' . LB;
        foreach (array('tax', 'shipping', 'handling') as $fld) {
            if (is_numeric($A[$fld]) && $A[$fld] > 0) {
                $tip .= '<tr><td>' . $LANG_PP[$fld] .
                    ': </td><td style=&quot;text-align:right&quot;>' . $Cur->FormatValue($A[$fld]) . '</td></tr>' . LB;
                $total += (float)$A[$fld];
            }
        }
        if ($total > $fieldvalue) {
            $tip .= '<tr><td>' . $LANG_PP['total'] .
                    ': </td><td style=&quot;text-align:right&quot;>' . $Cur->Format($total) . '</td></tr>' . LB;
        }
        $tip .= '</table>' . LB;
        $retval = '<span class="tooltip" title="' . $tip . '">' . $Cur->FormatValue($fieldvalue) . '</span>';
        break;
    default:
        $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
        break;
    }
    return $retval;
}


/**
*   Diaplay the product catalog items.
*
*   @param  integer $cat_id     Optional category ID to limit display
*   @return string      HTML for product catalog.
*/
function ProductList($cat_id = 0)
{
    global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $_USER, $_PLUGINS,
            $_IMAGE_TYPE, $_GROUPS, $LANG13;

    $isAdmin = plugin_ismoderator_paypal() ? true : false;
    $cat_name = '';
    $breadcrumbs = '';
    $cat_img_url = '';
    $display = '';
    $cat_sql = '';
    $Cat = Category::getInstance($cat_id);

    // If a cat ID is requested but doesn't exist or the user can't access
    // it, redirect to the homepage.
    if ($cat_id > 0 && ($Cat->isNew || !$Cat->hasAccess())) {
        echo COM_refresh(PAYPAL_URL);
        exit;
    }
    $RootCat = Category::getRoot();
    if ($cat_id > 0 && $cat_id != $RootCat->cat_id) {
        // A specific subcategory is being viewed
        $cats = Category::getPath($cat_id);
        foreach ($cats as $cat) {
            // Root category already shown in top header
            if ($cat->cat_id == $RootCat->cat_id) continue;
            if (!$cat->hasAccess()) continue;
            if ($cat->cat_id == $cat_id) {
                $breadcrumbs .= "<li class=\"uk-active\"><span>{$cat->cat_name}</span></li>" . LB;
            } else {
                $breadcrumbs .= "<li>" . COM_createLink($cat->cat_name,
                    PAYPAL_URL . '/index.php?category=' .
                        (int)$cat->cat_id) . '</li>' . LB;
            }
        }
        $show_plugins = false;
    } else {
        // Only show plugins on the root category page
        $show_plugins = true;
    }

    $cat_name = $Cat->cat_name;
    $cat_desc = $Cat->description;
    $cat_img_url = $Cat->ImageUrl();
    if ($Cat->parent_id > 0) {
        // Get the sql to limit by category
        $tmp = Category::getTree($Cat->cat_id);
        $cats = array();
        foreach ($tmp as $cat_id=>$info) {
            $cats[] = $cat_id;
        }
        if (!empty($cats)) {
            $cat_sql = implode(',', $cats);
            $cat_sql = " AND c.cat_id IN ($cat_sql)";
        }
    }

    // Display top-level categories
    $tmp = Category::getTree();
    $A = array(
        $RootCat->cat_id => array(
            'name' => $RootCat->cat_name,
        ),
    );
    foreach ($tmp as $cat_id=>$C) {
        if ($C->parent_id == $RootCat->cat_id && $C->hasAccess()) {
            $A[$C->cat_id] = array(
                'name' => $C->cat_name,
                'count' => $C->cnt,
            );
        }
    }

    $cat_cols = PP_getVar($_PP_CONF, 'cat_columns', 'integer', 0);
    if ($cat_cols > 0) {
        // Now get categories from plugins
        foreach ($_PLUGINS as $pi_name) {
            $pi_cats = PLG_callFunctionForOnePlugin('plugin_paypal_getcategories_' . $pi_name);
            if (is_array($pi_cats) && !empty($pi_cats)) {
                foreach ($pi_cats as $data) {
                    $A[] = $data;
                }
            }
        }

        $i = 1;
        $catrows = count($A);
        if ($catrows > 0) {
            $CT = PP_getTemplate(array(
                    'table'    => 'category_table',
                    'row'      => 'category_row',
                    'category' => 'category',
            ) );
            //$CT->set_var('breadcrumbs', $breadcrumbs);
            if ($cat_img_url != '') {
                $CT->set_var('catimg_url', $cat_img_url);
            }

            $CT->set_var('width', floor (100 / $cat_cols));
            foreach ($A as $category => $info) {
                if (isset($info['url'])) {
                    $url = $info['url'];
                } else {
                    $url = PAYPAL_URL . '/index.php?category=' . urlencode($category);
                }
                $CT->set_var(array(
                    'category_name' => $info['name'],
                    'category_link' => $url,
                    //'count'         => $info['count'],
                ) );
                $CT->parse('catrow', 'category', true);
                if ($i % $cat_cols == 0) {
                    $CT->parse('categories', 'row', true);
                    $CT->set_var('catrow', '');
                }
                $i++;
            }
            if ($catrows % $cat_cols != 0) {
                $CT->parse('categories', 'row', true);
            }
            $display .= $CT->parse('', 'table');
        }
    }

    $sortby = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : $_PP_CONF['order'];
    switch ($sortby){
    case 'price_l2h':   // price, low to high
        $sql_sortby = 'price';
        $sql_sortdir = 'ASC';
        break;
    case 'price_h2l':   // price, high to low
        $sql_sortby = 'price';
        $sql_sortdir = 'DESC';
        break;
    case 'top_rated':
        $sql_sortby = 'rating';
        $sql_sortdir = 'DESC';
        break;
    case 'newest':
        $sql_sortby = 'dt_add';
        $sql_sortdir = 'DESC';
        break;
    case 'name':
        $sql_sortby = 'short_description';
        $sql_sortdir = 'ASC';
        break;
    default:
        $sortby = $_PP_CONF['order'];
        $sql_sortby = $sortby;
        $sql_sortdir = 'ASC';
        break;
    }
    $sortby_options = '';
    foreach ($LANG_PP['list_sort_options'] as $value=>$text) {
        $sel = $value == $sortby ? ' selected="selected"' : '';
        $sortby_options .= "<option value=\"$value\" $sel>$text</option>\n";
    }

    // Get products from database. "c.enabled is null" is to allow products
    // with no category defined
    $today = PAYPAL_now()->format('Y-m-d', true);
    $sql = " FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON p.cat_id = c.cat_id
            WHERE p.enabled=1
            AND p.avail_beg <= '$today' AND p.avail_end >= '$today'
            AND (
                (c.enabled=1 " . SEC_buildAccessSql('AND', 'c.grp_access') . ")
                OR c.enabled IS NULL
                )
            AND (
                p.track_onhand = 0 OR p.onhand > 0 OR p.oversell < 2
                ) $cat_sql";

    $search = '';
    // Add search query, if any
    if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && !isset($_REQUEST['clearsearch'])) {
        $search = DB_escapeString($_REQUEST['query']);
        $fields = array('p.name', 'c.cat_name', 'p.short_description', 'p.description',
                'p.keywords');
        $srches = array();
        foreach ($fields as $fname) {
            $srches[] = "$fname like '%$search%'";
        }
        $srch = ' AND (' . implode(' OR ', $srches) . ')';
        $sql .= $srch;
    }
    $pagenav_args = array();

    // If applicable, order by
    $sql .= " ORDER BY $sql_sortby $sql_sortdir";
    $sql_key = md5($sql);
    //echo $sql;die;

    // Count products from database
    $cache_key = Cache::makeKey('prod_cnt_' . $sql_key);
    $count = Cache::get($cache_key);
    if ($count === NULL) {
        $res = DB_query('SELECT COUNT(*) as cnt ' . $sql);
        $x = DB_fetchArray($res, false);
        $count = PP_getVar($x, 'cnt', 'integer');
        Cache::set($cache_key, $count, array('products', 'categories'));
    }

    // If applicable, handle pagination of query
    $prod_per_page = PP_getVar($_PP_CONF, 'prod_per_page', 'integer');
    if ($prod_per_page > 0) {
        // Make sure page requested is reasonable, if not, fix it
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] <= 0) {
            $_REQUEST['page'] = 1;
        }
        $page = (int)$_REQUEST['page'];
        $start_limit = ($page - 1) * $prod_per_page;
        if ($start_limit > $count) {
            $page = ceil($count / $prod_per_page);
        }
        // Add limit for pagination (if applicable)
        if ($count > $prod_per_page) {
            $sql .= " LIMIT $start_limit, $prod_per_page";
        }
    }

    // Re-execute query with the limit clause in place
    //$res = DB_query('SELECT DISTINCT p.id ' . $sql);
    $sql_key = md5($sql);
    $cache_key = Cache::makeKey('prod_list_' . $sql_key);
    $Products = Cache::get($cache_key);
    if ($Products === NULL) {
        $res = DB_query('SELECT p.* ' . $sql);
        $Products = array();
        while ($A = DB_fetchArray($res, false)) {
            $Products[] = Product::getInstance($A);
        }
        Cache::set($cache_key, $Products, array('products', 'categories'));
    }

    // Create product template
    if (empty($_PP_CONF['list_tpl_ver'])) $_PP_CONF['list_tpl_ver'] = 'v1';
    $T = PP_getTemplate(array(
        'wrapper'   => 'list/' . $_PP_CONF['list_tpl_ver'] . '/wrapper',
        'start'   => 'product_list_start',
        'end'     => 'product_list_end',
        //'product' => 'list/' . $_PP_CONF['list_tpl_ver'] .'/product_list_item',
        //    'product' => 'product_list',
        //'buy'     => 'buttons/btn_buy_now',
        //'cart'    => 'buttons/btn_add_cart',
        'download'  => 'buttons/btn_download',
        'login_req' => 'buttons/btn_login_req',
        'btn_details' => 'buttons/btn_details',
    ) );
    $T->set_var(array(
        'pi_url'        => PAYPAL_URL,
        'user_id'       => $_USER['uid'],
        'currency'      => $_PP_CONF['currency'],
        'breadcrumbs'   => $breadcrumbs,
        'search_text'   => $search,
        'uikit'         => $_PP_CONF['_is_uikit'] ? 'true' : '',
        'tpl_ver'       => $_PP_CONF['list_tpl_ver'],
        'sortby_options' => $sortby_options,
        'sortby'        => $sortby,
    ) );

    if (!empty($cat_name)) {
        $T->set_var(array(
            'title'     => $cat_name,
            'cat_desc'  => $cat_desc,
            'cat_img_url' => $cat_img_url,
        ) );
    } else {
        $T->set_var('title', $LANG_PP['blocktitle']);
    }

    // Adjust the number of product block columns depending on whether
    // glFusion blocks are shown
    if ($_PP_CONF['displayblocks'] > 0) {
        $T->set_var(array(
            'lg_cols'   => 4,
            'med_cols'  => 3,
        ) );
    } else {
        $T->set_var(array(
            'lg_cols'   => 5,
            'med_cols'  => 4,
        ) );
    }

    $display .= $T->parse('', 'start');

    if ($_PP_CONF['ena_ratings'] == 1) {
        $PP_ratedIds = RATING_getRatedIds($_PP_CONF['pi_name']);
    }

    // Display each product
    $prodrows = 0;
    $T->set_block('wrapper', 'ProductItems', 'PI');
    foreach ($Products as $P) {
        // Don't display products if the viewer doesn't have access
        if (!$P->hasAccess()) {
            continue;
        }

        $prodrows++;

        if ( @in_array($P->id, $ratedIds)) {
            $static = true;
            $voted = 1;
        } else {
            $static = 0;
            $voted = 0;
        }

        if ($_PP_CONF['ena_ratings'] == 1 && $P->rating_enabled == 1) {
            $static = 1;
            $rating_box = RATING_ratingBar($_PP_CONF['pi_name'], $P->id,
                    $P->votes, $P->rating,
                    $voted, 5, $static, 'sm');
            $T->set_var('rating_bar', $rating_box);
        } else {
            $T->set_var('rating_bar', '');
        }

        $pic_filename = DB_getItem($_TABLES['paypal.images'], 'filename',
                "product_id = '{$P->id}'");
        $T->set_var(array(
            'id'            => $P->id,
            'name'          => htmlspecialchars($P->name),
            'short_description' => htmlspecialchars(PLG_replacetags($P->short_description)),
            'img_cell_width' => ($_PP_CONF['max_thumb_size'] + 20),
            'encrypted'     => '',
            'item_url'      => PAYPAL_URL . '/detail.php?id='. $P->id,
            'img_cell_width' => ($_PP_CONF['max_thumb_size'] + 20),
            'track_onhand'  => $P->track_onhand ? 'true' : '',
            'qty_onhand'    => $P->onhand,
            'has_discounts' => $P->hasDiscounts() ? 'true' : '',
            'price'         => $P->getDisplayPrice(),
            'orig_price'    => $P->getDisplayPrice($P->price),
            'on_sale'       => $P->isOnSale(),
            'small_pic'     => $pic_filename ? PAYPAL_ImageUrl($pic_filename) : '',
            'tpl_ver'       => $_PP_CONF['list_tpl_ver'],
        ) );

        if ($isAdmin) {
            $T->set_var(array(
                'is_admin'  => 'true',
                'pi_admin_url' => PAYPAL_ADMIN_URL,
                'edit_icon' =>
                    "{$_CONF['layout_url']}/images/edit.$_IMAGE_TYPE",
            ) );
        }

        // Get the product buttons for the list
        $T->set_block('product', 'BtnBlock', 'Btn');
        if (!$P->hasAttributes() && !$P->hasCustomFields() && !$P->hasSpecialFields()) {
            // Buttons only show in the list if there are no options to select
            $buttons = $P->PurchaseLinks('list');
            foreach ($buttons as $name=>$html) {
                $T->set_var('button', $html);
                $T->parse('Btn', 'BtnBlock', true);
            }
        } else {
            if ($_PP_CONF['ena_cart']) {
                // If the product has attributes, then the cart must be
                // enabled to allow purchasing
                $button = $T->parse('', 'btn_details') . '&nbsp;';
                $T->set_var('button', $button);
                $T->parse('Btn', 'BtnBlock', true);
            }
        }

        $T->parse('PI', 'ProductItems', true);
        $T->clear_var('Btn');
    }

    // Get products from plugins.
    // For now, this hack shows plugins only on the first page, since
    // they're not included in the page calculation.
    if ($_PP_CONF['show_plugins']&& $page == 1 && $show_plugins && empty($search)) {
        // Get the currency class for formatting prices
        $Cur = Currency::getInstance();
        $T->clear_var('rating_bar');  // no ratings for plugins (yet)
        foreach ($_PLUGINS as $pi_name) {
            $status = LGLIB_invokeService($pi_name, 'getproducts',
                    array(), $plugin_data, $svc_msg);
            if ($status != PLG_RET_OK || empty($plugin_data)) continue;

            foreach ($plugin_data as $A) {
                // Reset button values
                $buttons = '';
                if (!isset($A['buttons'])) $A['buttons'] = array();

                // If the plugin has a getDetailPage service function, use it
                // to wrap the item's detail page in the catalog page.
                // Otherwise just use a link to the product's url.
                if (isset($A['have_detail_svc'])) {
                    $item_url = PAYPAL_URL . '/index.php?pidetail=' . $A['id'];
                } elseif (isset($A['url'])) {
                    $item_url = $A['url'];
                } else {
                    $item_url = '';
                }
                $item_name = PP_getVar($A, 'name', 'string', $A['id']);
                $item_dscp = PP_getVar($A, 'short_description', 'string', $item_name);
                $img = PP_getVar($A, 'image', 'string', '');
                $price = PP_getVar($A, 'price', 'float', 0);
                $T->set_var(array(
                    'id'        => $A['id'],        // required
                    'name'      => $item_name,
                    'short_description' => $item_dscp,
                    'encrypted' => '',
                    'item_url'  => $item_url,
                    'track_onhand' => '',   // not available for plugins
                    'small_pic' => $img,
                    'on_sale'   => '',
                ) );
                if ($price > 0) {
                    $T->set_var('price', $Cur->Format($price));
                } else {
                    $T->clear_var('price');
                }

                if ( $price > 0 &&
                        $_USER['uid'] == 1 &&
                        !$_PP_CONF['anon_buy'] ) {
                    $buttons .= $T->set_var('', 'login_req') . '&nbsp;';
                } elseif ( (!isset($A['prod_type']) || $A['prod_type'] > PP_PROD_PHYSICAL) &&
                            $A['price'] == 0 ) {
                    // Free items or items purchases and not expired, download.
                    $buttons .= $T->set_var('', 'download') . '&nbsp;';
                } elseif (is_array($A['buttons'])) {
                    // Buttons for everyone else
                    $T->set_block('wrapper', 'BtnBlock', 'Btn');
                    foreach ($A['buttons'] as $type=>$html) {
                        $T->set_var('button', $html);
                        $T->parse('Btn', 'BtnBlock', true);
                    }
                }
                $T->clear_var('Btn');
                $prodrows++;
                $T->parse('PI', 'ProductItems', true);
            }   // foreach plugin_data

        }   // foreach $_PLUGINS

    }   // if page == 1

    //$T->parse('output', 'wrapper');
    $display .= $T->parse('', 'wrapper');

    if ($catrows == 0 && COM_isAnonUser()) {
        $T->set_var('anon_and_empty', 'true');
    }

    $pagenav_args = empty($pagenav_args) ? '' : '?'.implode('&', $pagenav_args);
    // Display pagination
    if ($prod_per_page > 0 && $count > $prod_per_page) {
        $T->set_var('pagination',
            COM_printPageNavigation(PAYPAL_URL . '/index.php' . $pagenav_args,
                        $page,
                        ceil($count / $prod_per_page)));
    } else {
        $T->set_var('pagination', '');
    }

    // Display a "not found" message if count == 0
    if ($prodrows == 0) {
        if ($_PP_CONF['_is_uikit']) {
            $display .= '<div class="uk-alert uk-alert-danger">' . $LANG_PP['no_products_match'] . '</div>';
        } else {
            $display .= '<span class="alert">' . $LANG_PP['no_products_match'] . '</span>';
        }
    }

    $display .= $T->parse('', 'end');
    return $display;
}


/**
 *  Display a single row from the IPN log.
 *
 *  @param  integer $id     Log Entry ID
 *  @param  string  $txn_id Transaction ID from Paypal
 *  @return string          HTML of the ipnlog row specified by $id
 */
function ipnlogSingle($id, $txn_id)
{
    global $_TABLES, $_CONF, $LANG_PP;

    $sql = "SELECT * FROM {$_TABLES['paypal.ipnlog']} ";
    $sql .= $id > 0 ? "WHERE id = $id" : "WHERE txn_id = '$txn_id'";

    $res = DB_query($sql);
    $A = DB_fetchArray($res, false);
    if (empty($A))
        return "Nothing Found";

    // Allow all serialized data to be available to the template
    $ipn = @unserialize($A['ipn_data']);

    // Create ipnlog template
    $T = PP_getTemplate('ipnlog_detail', 'ipnlog');

    $gw = Gateway::getInstance($A['gateway']);
    if ($gw !== NULL) {
        $vals = $gw->ipnlogVars($ipn);

        // Display the specified ipnlog row
        $T->set_var(array(
            'id'        => $A['id'],
            'ip_addr'   => $A['ip_addr'],
            'time'      => $A['time'],
            'txn_id'    => $A['txn_id'],
            'gateway'   => $A['gateway'],
            //'pmt_gross' => $vals['pmt_gross'],
            //'verified'  => $vals['verified'],
            //'pmt_status' => $vals['pmt_status'],
        ) );

        if (!empty($vals)) {
            $T->set_block('ipnlog', 'DataBlock', 'Data');
            foreach ($vals as $key=>$value) {
                $T->set_var(array(
                    'prompt'    => isset($LANG_PP[$key]) ? $LANG_PP[$key] : $key,
                    'value'     => htmlspecialchars($value, ENT_QUOTES, COM_getEncodingt()),
                ) );
                $T->parse('Data', 'DataBlock', true);
            }
        }
    }
    /*if ($A['verified']) {
        $T->set_var('verified', 'true');
    } else {
        $T->set_var('verified', 'false');
    }*/

    if ($ipn) {
        $ipn_data = "<table><th class=\"admin-list-headerfield\">Name<th class=\"admin-list-headerfield\">Value\n";
        foreach ($ipn as $name => $value) {
            //$ipnlog->set_var($name, $value);
            $ipn_data .= "<tr><td>$name</td><td>$value</td></tr>\n";
        }
        $ipn_data .= "</table>\n";
    } else {
        $ipn_data = "Error decoding IPN transaction data";
    }
    $T->set_var('raw', $ipn_data);
    return $T->parse('output', 'ipnlog');
}


/**
*   Display an error message.
*   Uses glFusion's typography to display an "alert" type message.
*   The provided message may be a single message string or array of strings.
*   An array will be formatted as an unnumbered list.
*
*   @param  array|string    $msg    Single message string or array
*   @param  string          $title  Optional title string, shown above list
*   @return string          Complete error message
*/
function PAYPAL_errMsg($msg = array(), $title = '')
{
    if (empty($msg)) return '';

    $retval = '<span class="alert paypalErrorMsg">' . "\n";
    if (!empty($title)) {
        $retval .= "<p>$title</p>\n";
    }

    if (is_array($msg)) {
        $retval .= '<ul>';
        foreach ($msg as $m) {
            $retval .= '<li>' . $m . '</li>' . LB;
        }
        $retval .= '<ul>' . LB;
    } else {
        $retval .= $msg;
    }
    $retval .= "/span>\n";
    return $retval;
}


/**
*   Create the user menu
*
*   @param  string  $view   View being shown, so set the help text
*   @return string      Administrator menu
*/
function PAYPAL_userMenu($view='')
{
    global $_CONF, $LANG_PP, $_PP_CONF;

    USES_lib_admin();

    $hdr_txt = PP_getVar($LANG_PP, 'user_hdr_' . $view);
    $menu_arr = array(
        array(
            'url'  => PAYPAL_URL,
            'text' => $LANG_PP['back_to_catalog'],
        ),
    );

    $active = $view == 'orderhist' ? true : false;
    $menu_arr[] = array(
        'url'  => PAYPAL_URL . '/index.php?orderhist=x',
        'text' => $LANG_PP['purchase_history'],
        'active' => $active,
    );

    if ($_PP_CONF['gc_enabled']) {
        $active = $view == 'couponlog' ? true : false;
        $menu_arr[] = array(
            'url'  => PAYPAL_URL . '/index.php?couponlog=x',
            'text' => $LANG_PP['gc_activity'],
            'active' => $active,
        );
    }

    return \ADMIN_createMenu($menu_arr, $hdr_txt);
}


/**
*   Display the site header, with or without blocks according to configuration.
*
*   @param  string  $title  Title to put in header
*   @param  string  $meta   Optional header code
*   @return string          HTML for site header, from COM_siteHeader()
*/
function siteHeader($title='', $meta='')
{
    global $_PP_CONF, $LANG_PP;

    $retval = '';

    switch($_PP_CONF['displayblocks']) {
    case 2:     // right only
    case 0:     // none
        $retval .= COM_siteHeader('none', $title, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $title, $meta);
        break;
    }

    return $retval;
}


/**
*   Display the site footer, with or without blocks as configured.
*
*   @return string      HTML for site footer, from COM_siteFooter()
*/
function siteFooter()
{
    global $_PP_CONF;

    $retval = '';

    switch($_PP_CONF['displayblocks']) {
    case 2 : // right only
    case 3 : // left and right
        $retval .= COM_siteFooter();
        break;

    case 0: // none
    case 1: // left only
    default :
        $retval .= COM_siteFooter();
        break;
    }

    return $retval;
}

?>

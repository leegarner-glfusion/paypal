<?php
/**
*   Plugin-specific functions for the Paypal plugin for glFusion.
*   Based on the gl-paypal Plugin for Geeklog CMS by Vincent Furia.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @author     Vincent Furia <vinny01@users.sourceforge.net
*   @copyright  Copyright (c) 2009-2012 Lee Garner
*   @copyright  Copyright (C) 2005-2006 Vincent Furia
*   @package    paypal
*   @version    0.5.7
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

USES_paypal_class_orderstatus();

/**
*   Order History View.
*   Displays the purchase history for the current user.  Admins
*   can view any user's histor, or all users
*
*   @param  boolean $admin  True if called for admin access, False otherwise
*   @param  integer $uid    User ID to view, current user by default
*   @return string          HTML for order list
*/
function PAYPAL_orders($admin = false, $uid = '')
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER;

    if (!$admin) {
        $uid = $_USER['uid'];
    }
    $where = '';

    USES_lib_admin();

    if (!empty($uid)) {
        $where = " WHERE ord.uid = '" . (int)$uid . "'";
    }

    $isAdmin = $admin == true ? 1 : 0;

    $sql = "SELECT ord.*, SUM(itm.quantity * itm.price) as ord_total,
            u.username, $isAdmin as isAdmin
        FROM {$_TABLES['paypal.orders']} AS ord
        LEFT JOIN {$_TABLES['users']} AS u 
            ON ord.uid = u.uid
        LEFT JOIN {$_TABLES['paypal.purchases']} AS itm
            ON ord.order_id = itm.order_id
        $where
        GROUP BY ord.order_id";
    //echo $sql;die;

    $base_url = $admin ? PAYPAL_ADMIN_URL : PAYPAL_URL;
    $header_arr = array(
        array('text' => $LANG_PP['purch_date'],
                'field' => 'order_date', 'sort' => true),
        array('text' => $LANG_PP['order_number'],
                'field' => 'order_id', 'sort' => true),
        array('text' => $LANG_PP['total'],
                'field' => 'ord_total', 'sort' => false),
        array('text' => $LANG_PP['status'],
                'field' => 'status', 'sort' => true),
    );
    if ($admin) {
        $header_arr[] = array('text' => $LANG_PP['username'],
                'field' => 'username', 'sort' => true);
    }

    $defsort_arr = array('field' => 'ord.order_date',
            'direction' => 'DESC');

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
 
    $display = COM_startBlock('', '', 
                COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.orders',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => $where,
        );

    $text_arr = array(
        //'has_extras' => $admin ? true : false,
        //'form_url' => $base_url . '/index.php?orderhist=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getPurchaseHistoryField',
            $header_arr, $text_arr, $query_arr, $defsort_arr);

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}


/**
*   Purchase History View.
*   Displays the purchase history for the current user.  Admins
*   can view any user's histor, or all users
*
*   @param  boolean $admin  True if called for admin access, False otherwise
*   @param  integer $uid    User ID to view, current user by default
*   @return string          HTML for order list
*/
function PAYPAL_history($admin = false, $uid = '')
{
    global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER;

    // Not available to anonymous users
    if (COM_isAnonUser())
        return '';

    USES_lib_admin();

    $isAdmin = $admin == true ? 1 : 0;

    $sql = "SELECT 
            p.*, UNIX_TIMESTAMP(p.expiration) AS exptime, 
            d.name, d.short_description, d.file, d.prod_type,
            $isAdmin as isAdmin, 
            u.uid, u.username
        FROM {$_TABLES['paypal.purchases']} AS p 
        LEFT JOIN {$_TABLES['paypal.products']} AS d 
            ON d.id = p.product_id 
        LEFT JOIN {$_TABLES['users']} AS u 
            ON p.user_id = u.uid ";

    $base_url = PAYPAL_ADMIN_URL;
    if (!$isAdmin) {
        $where = " WHERE p.user_id = '" . (int)$_USER['uid'] . "'";
        $base_url = PAYPAL_URL;
    } elseif (!empty($uid)) {
        $where = " WHERE p.user_id = '" . (int)$uid . "'";
    }

    $header_arr = array(
        array('text' => $LANG_PP['product_id'], 
                'field' => 'name', 'sort' => true),
        array('text' => $LANG_PP['qty'], 
                'field' => 'quantity', 'sort' => true),
        array('text' => $LANG_PP['description'],
                'field' => 'short_description', 'sort' => true),
        array('text' => $LANG_PP['purch_date'],
                'field' => 'purchase_date', 'sort' => true),
        array('text' => $LANG_PP['txn_id'],
                'field' => 'txn_id', 'sort' => true),
        array('text' => $LANG_PP['expiration'],
                'field' => 'expiration', 'sort' => true),
        array('text' => $LANG_PP['prod_type'],
                'field' => 'prod_type', 'sort' => true),
    );
    if ($isAdmin) {
        $header_arr[] = array('text' => $LANG_PP['username'], 
                'field' => 'username', 'sort' => true);
    }

    $defsort_arr = array('field' => 'p.purchase_date',
            'direction' => 'DESC');

    $display = COM_startBlock('', '', 
                COM_getBlockTemplate('_admin_block', 'header'));

    $query_arr = array('table' => 'paypal.purchases',
            'sql' => $sql,
            'query_fields' => array('d.name', 'd.short_description', 'p.txn_id'),
            'default_filter' => $where,
        );

    $text_arr = array(
        'has_extras' => true,
        'form_url' => $base_url . '/index.php?history=x',
    );

    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;

    $display .= ADMIN_list('paypal', 'PAYPAL_getPurchaseHistoryField',
            $header_arr, $text_arr, $query_arr, $defsort_arr);

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

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
function PAYPAL_getPurchaseHistoryField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_PP_CONF, $LANG_PP, $_USER;

    static $dt = NULL;
    if ($dt === NULL) $dt = new Date('now', $_USER['tzid']);

    $retval = '';

    switch($fieldname) {
    case 'order_date':
        $dt->setTimestamp(strtotime($fieldvalue));
        $retval = '<span title="' . $dt->format($_PP_CONF['datetime_fmt'], false) . '">' .
                $dt->format($_PP_CONF['datetime_fmt'], true) . '</span>';
        break;

    case 'name':
        list($item_id, $item_opts) = explode('|', $A['product_id']);
        //if (is_numeric($A['product_id'])) {
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
        $retval = COM_createLink($fieldvalue, 
                $_CONF['site_url'] . '/users.php?mode=profile&uid=' . 
                $A['uid']);
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
            $retval = ppOrderStatus::Selection($A['order_id'], 0, $fieldvalue);
        } elseif (isset($LANG_PP['orderstatus'][$fieldvalue])) {
            $retval = $LANG_PP['orderstatus'][$fieldvalue];
        } else {
            $retval = 'Unknown';
        }
        break;

    case 'order_id':
        $base_url = $A['isAdmin'] ? PAYPAL_ADMIN_URL : PAYPAL_URL;
        $retval = COM_createLink($fieldvalue, 
                $base_url. '/index.php?order=' . $fieldvalue);
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
*   @return string      HTML for product catalog.
*/
function PAYPAL_ProductList($cat=0, $search='')
{
    global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $_USER, $_PLUGINS, 
            $_IMAGE_TYPE, $_GROUPS, $LANG13;

    USES_paypal_class_product();

    if (SEC_hasRights('paypal.admin')) {
        $isAdmin = true;
    } else {
        $isAdmin = false;
    }

    $my_groups = implode(',', $_GROUPS);

    $cat_name = '';
    $breadcrumbs = '';
    $img_url = '';
    $display = '';
    if ($cat != 0) {
        $cat = (int)$cat;
        $A = DB_fetchArray(DB_query("SELECT cat_name, image, description
                FROM {$_TABLES['paypal.categories']}
                WHERE cat_id='$cat' " .
                PAYPAL_buildAccessSql()));
        if (!empty($A)) {
            $breadcrumbs = PAYPAL_Breadcrumbs($cat);
            $cat_name = $A['cat_name'];
            $cat_desc = $A['description'];
            if (!empty($A['image']) && 
                is_file($_CONF['path_html'] . $_PP_CONF['pi_name'] . 
                        '/images/categories/' . $A['image'])) {
                $img_url = PAYPAL_URL . '/images/categories/' . $A['image'];
            }
        }
    }

    // Display categories
    if (isset($_PP_CONF['cat_columns']) && 
            $_PP_CONF['cat_columns'] > 0) {
        $sql = "SELECT cat.cat_id, cat.cat_name, count(prod.id) AS cnt
            FROM {$_TABLES['paypal.categories']} cat
            LEFT JOIN {$_TABLES['paypal.products']} prod
                ON prod.cat_id = cat.cat_id
            WHERE cat.enabled = '1' AND cat.parent_id = '$cat' 
                AND prod.enabled = '1' " .
            PAYPAL_buildAccessSql('AND', 'cat.grp_access') .
            " GROUP BY cat.cat_id
            ORDER BY cat.cat_name";
            //HAVING cnt > 0
        //echo $sql;die;

        $res = DB_query($sql);
        $A = array();
        while ($C = DB_fetchArray($res, false)) {
            $A[$C['cat_id']] = array($C['cat_name'], $C['cnt']);
        }

        // Now get categories from plugins
        foreach ($_PLUGINS as $pi_name) {
            $function = 'USES_' . $pi_name . '_paypal';
            if (function_exists($function)) {
                $function();
                $function = 'plugin_paypal_getcategories_' . $pi_name;
                if (function_exists($function)) {
                    $pi_cats = $function();
                    foreach ($pi_cats as $catid => $data) {
                        $A[$catid] = $data;
                    }
                }
            }
        }

        $i = 1;
        $catrows = count($A);
        if ($catrows > 0) {
            $CT = new Template(PAYPAL_PI_PATH . '/templates');
            $CT->set_file(array('table'    => 'category_table.thtml',
                        'row'      => 'category_row.thtml',
                        'category' => 'category.thtml'));
            $CT->set_var('breadcrumbs', $breadcrumbs);
            if ($img_url != '') {
                $CT->set_var('catimg_url', $img_url);
            }

            $CT->set_var('width', floor (100 / $_PP_CONF['cat_columns']));
            foreach ($A as $category => $info) {
                $CT->set_var(array(
                    'category_name' => $info[0],
                    'category_link' => PAYPAL_URL . '/index.php?category=' . 
                                    urlencode($category),
                    //'count'         => $info[1],
                ) );
                /*if ($category == $cat) {
                    $CT->set_var('curr', 'current');
                    $cat_name = $info[0];
                } else {
                    $CT->set_var('curr', 'other');
                }*/
                $CT->parse('catrow', 'category', true);
                if ($i % $_PP_CONF['cat_columns'] == 0) {
                    $CT->parse('categories', 'row', true);
                    $CT->set_var('catrow', '');
                }
                $i++;
            }
            if ($catrows % $_PP_CONF['cat_columns'] != 0) {
                $CT->parse('categories', 'row', true);
            }
            $display .= $CT->parse('', 'table');
        }
    }

    //$sortdir = $_REQUEST['sortdir'] == 'DESC' ? 'DESC' : 'ASC';
    //$sql_sortdir = $sortdir;
    $sortby = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : $_PP_CONF['order'];
    switch ($sortby){
    case 'price_l2h':   // price, low to high
        $sql_sortby = 'price';
        $sql_sortdir = 'ASC';
        break;
    case 'price_h2l':
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
    /*case 'price':
    case 'dt_add':
        $sql_sortby = $sortby;
        break;
    case 'rating':
        $sql_sortby = 'rating';
        break;*/
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

    //$sortby = $_PP_CONF['order'];
    //$sortdir = 'ASC';

    // Get products from database. "c.enabled is null" is to allow products
    // with no category defined
    $sql = " FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
                ON p.cat_id = c.cat_id
            WHERE p.enabled=1 
            AND (
                (c.enabled=1 " . PAYPAL_buildAccessSql('AND', 'c.grp_access') . ")
                OR c.enabled IS NULL
                )
            AND (
                p.track_onhand = 0 OR p.onhand > 0 OR p.oversell < 2
                )";

    // Add search query, if any
    if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && !isset($_REQUEST['clearsearch'])) {
        $srchitem = DB_escapeString($_REQUEST['query']);
        $fields = array('p.name', 'c.cat_name', 'p.short_description', 'p.description',
                'p.keywords');
        $srches = array();
        foreach ($fields as $fname) {
            $srches[] = "$fname like '%$srchitem%'";
        }
        $srch = ' AND (' . implode(' OR ', $srches) . ')';
        $sql .= $srch;
    }
    $pagenav_args = array();
    // If applicable, limit by category
    if (!empty($_REQUEST['category'])) {
        $cat_list = $_REQUEST['category'];
        $cat_list .=  PAYPAL_recurseCats('PAYPAL_callbackCatCommaList', 0,
                $_REQUEST['category']);
        if (!empty($cat_list)) {
            $sql .= " AND c.cat_id IN ($cat_list)";
        }
        $pagenav_args[] = 'category=' . urlencode($_REQUEST['category']);
    } else {
        $cat_list = '';
    }

    // If applicable, limit by search string
    if (!empty($_REQUEST['search_name'])) {
        $srch = DB_escapeString($_REQUEST['search_name']);
        $sql .= " AND (p.name like '%$srch%' OR 
                p.short_description like '%$srch%' OR
                p.description like '%$srch%' OR
                p.keywords like '%$srch%')";
        //if (!$isAdmin) $sql .= " AND p.grp_access IN ($my_groups) ";
        $pagenav_args[] = 'search_name=' . urlencode($_REQUEST['search_name']);
    }

    // If applicable, order by
    $sql .= " ORDER BY $sql_sortby $sql_sortdir";
    //echo $sql;die;

    // If applicable, handle pagination of query
    if (isset($_PP_CONF['prod_per_page']) && $_PP_CONF['prod_per_page'] > 0) {
        // Count products from database
        $res = DB_query('SELECT COUNT(*) as cnt ' . $sql);
        $x = DB_fetchArray($res, false);
        if (isset($x['cnt']))
            $count = (int)$x['cnt'];
        else
            $count = 0;

        // Make sure page requested is reasonable, if not, fix it
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] <= 0) {
            $_REQUEST['page'] = 1;
        }
        $page = (int)$_REQUEST['page'];
        $start_limit = ($page - 1) * $_PP_CONF['prod_per_page'];
        if ($start_limit > $count) {
            $page = ceil($count / $_PP_CONF['prod_per_page']);
        }
        // Add limit for pagination (if applicable)
        if ($count > $_PP_CONF['prod_per_page']) {
            $sql .= " LIMIT $start_limit, {$_PP_CONF['prod_per_page']}";
        }
    }

    // Re-execute query with the limit clause in place
    $res = DB_query('SELECT DISTINCT p.id ' . $sql);

    // Create product template
    if (empty($_PP_CONF['list_tpl_ver'])) $_PP_CONF['list_tpl_ver'] = '/v1';
    $product = new Template(PAYPAL_PI_PATH . '/templates');
    $product->set_file(array(
                'start'   => 'product_list_start.thtml',
                'end'     => 'product_list_end.thtml',
                'product' => 'list' . $_PP_CONF['list_tpl_ver'] .'/product_list_item.thtml',
          //    'product' => 'product_list.thtml',
                //'buy'     => 'buttons/btn_buy_now.thtml',
                //'cart'    => 'buttons/btn_add_cart.thtml',
                'download'  => 'buttons/btn_download.thtml',
                'login_req' => 'buttons/btn_login_req.thtml',
                'btn_details' => 'buttons/btn_details.thtml',
    ));
    $product->set_var(array(
            'pi_url'        => PAYPAL_URL,
            'user_id'       => $_USER['uid'],
            'currency'      => $_PP_CONF['currency'],
            'breadcrumbs'   => $breadcrumbs,
            'search_text'   => $srchitem,
    ) );

    if (!empty($cat_name)) {
        $product->set_var('title', $cat_name);
        $product->set_var('cat_desc', $cat_desc);
    } else {
        $product->set_var('title', $LANG_PP['blocktitle']);
    }
    $product->set_var('sortby_options', $sortby_options);
    /*if ($sortdir == 'DESC') {
        $product->set_var('sortdir_desc_sel', ' selected="selected"');
    } else {
        $product->set_var('sortdir_asc_sel', ' selected="selected"');
    }*/
    $product->set_var('sortby', $sortby);
    //$product->set_var('sortdir', $sortdir);

    $display .= $product->parse('', 'start');

    // Create an empty product object
    $P = new Product();

    if ($_PP_CONF['ena_ratings'] == 1) {
        $PP_ratedIds = RATING_getRatedIds('paypal');
    }

    // Display each product
    $prodrows = 0;
    while ($A = DB_fetchArray($res, false)) {
        $prodrows++;

        $P->Read($A['id']);

        if ($_PP_CONF['ena_ratings'] == 1 && $P->rating_enabled == 1) {
            // can't rate from list page, messes with product links
            $static = true;
            $rating_box = RATING_ratingBar('paypal', $A['id'], 
                    $P->votes, $P->rating, 
                    $voted, 5, $static, 'sm');
            $product->set_var('rating_bar', $rating_box);
        } else {
            $product->set_var('rating_bar', '');
        }

        $product->set_var(array(
            'id'        => $A['id'],
            'name'      => htmlspecialchars($P->name),
            //'name'      => $A['name'],
            //'short_description' => PLG_replacetags($A['short_description']),
            'short_description' => htmlspecialchars(PLG_replacetags($P->short_description)),
            'img_cell_width' => ($_PP_CONF['max_thumb_size'] + 20),
            'encrypted' => '',
            'item_url'  => COM_buildURL(PAYPAL_URL . 
                    '/detail.php?id='. $A['id']),
            'img_cell_width'    => ($_PP_CONF['max_thumb_size'] + 20),
            'track_onhand' => $P->track_onhand ? 'true' : '',
            'qty_onhand' => $P->onhand,
        ) );

        if ($P->price > 0) {
            //$product->set_var('price', COM_numberFormat($P->price, 2));
            $product->set_var('price', $P->currency->Format($P->price));
        } else {
            $product->clear_var('price');
        }

        if ($isAdmin) {
            $product->set_var('is_admin', 'true');
            $product->set_var('pi_admin_url', PAYPAL_ADMIN_URL);
            $product->set_var('edit_icon', 
                    "{$_CONF['layout_url']}/images/edit.$_IMAGE_TYPE");
        }

        $pic_filename = DB_getItem($_TABLES['paypal.images'], 'filename',
                "product_id = '{$A['id']}'");
        if ($pic_filename) {
            $product->set_var('small_pic', PAYPAL_ImageUrl($pic_filename));
         } else {
            $product->set_var('small_pic', '');
        }

        // FIXME: If a user purchased once with no expiration, this query 
        // will not operate correctly
        /*$time = DB_getItem($_TABLES['paypal.purchases'], 
                    'MAX(UNIX_TIMESTAMP(expiration))',
                    "user_id = {$_USER['uid']} AND product_id ='{$A['id']}'");
        */
        $product->set_block('product', 'BtnBlock', 'Btn');
        if (!$P->hasAttributes()) {
            // Buttons only show in the list if there are no options to select
            $buttons = $P->PurchaseLinks();
            foreach ($buttons as $name=>$html) {
                $product->set_var('button', $html);
                $product->parse('Btn', 'BtnBlock', true);
            }
        } else {
            if ($_PP_CONF['ena_cart']) {
                // If the product has attributes, then the cart must be
                // enabled to allow purchasing
                $button = $product->parse('', 'btn_details') . '&nbsp;';
                $product->set_var('button', $button);
                $product->parse('Btn', 'BtnBlock', true);
            }
        }
        $display .= $product->parse('', 'product');
        $product->clear_var('Btn');
    }

    // Get products from plugins.
    // For now, this hack shows plugins only on the first page, since
    // they're not included in the page calculation.
    if ($page == 1 && empty($cat_list)) {
        // Get the currency class for formatting prices
        USES_paypal_class_currency();
        $Cur = new ppCurrency($_PP_CONF['currency']);
        $product->clear_var('rating_bar');  // no ratings for plugins (yet)
        foreach ($_PLUGINS as $pi_name) {
            $status = LGLIB_invokeService($pi_name, 'getproducts',
                    array(), $plugin_data, $svc_msg);
            if ($status != PLG_RET_OK || empty($plugin_data)) continue;

            foreach ($plugin_data as $A) {
                // Reset button values
                $buttons = '';

                $product->set_var(array(
                    'id'        => $A['id'],
                    'name'      => $A['name'],
                    'short_description' => $A['short_description'],
                    'display'   => '; display: none',
                    'small_pic' => '',
                    'encrypted' => '',
                    'item_url'  => $A['url'],
                    'track_onhand' => '',   // not available for plugins
                ) );
                if ($A['price'] > 0) {
                    $product->set_var('price', $Cur->Format($A['price']));
                } else {
                    $product->clear_var('price');
                }

                if ( $A['price'] > 0 && 
                        $_USER['uid'] == 1 && 
                        !$_PP_CONF['anon_buy'] ) {
                    $buttons .= $product->set_var('', 'login_req') . '&nbsp;';
                } elseif ( $A['prod_type'] > PP_PROD_PHYSICAL && 
                            $A['price'] == 0 ) {
                    // Free items or items purchases and not expired, download.
                    $buttons .= $product->set_var('', 'download') . '&nbsp;';
                } elseif (is_array($A['buttons'])) {
                    // Buttons for everyone else
                    $product->set_block('product', 'BtnBlock', 'Btn');
                    foreach ($A['buttons'] as $type=>$html) {
                        $product->set_var('button', $html);
                        $product->parse('Btn', 'BtnBlock', true);
                    }
                }
                //$product->set_var('buttons', $buttons);
                $display .= $product->parse('', 'product');
                $product->clear_var('Btn');
                $prodrows++;
            }   // foreach plugin_data

        }   // foreach $_PLUGINS

    }   // if page == 1

    if ($catrows == 0 && COM_isAnonUser()) {
        $product->set_var('anon_and_empty', 'true');
    }

    $pagenav_args = empty($pagenav_args) ? '' : '?'.implode('&', $pagenav_args);
    // Display pagination
    if (isset($_PP_CONF['prod_per_page']) && 
            $_PP_CONF['prod_per_page'] > 0 &&
            $count > $_PP_CONF['prod_per_page'] ) {
        $product->set_var('pagination', 
            COM_printPageNavigation(PAYPAL_URL . '/index.php' . $pagenav_args,
                        $page, 
                        ceil($count / $_PP_CONF['prod_per_page'])));
    } else {
        $product->set_var('pagination', '');
    }

    $display .= $product->parse('', 'end');

    return $display;

}


/**
 *  Display a single row from the IPN log.
 *
 *  @param  integer $id     Log Entry ID
 *  @param  string  $txn_id Transaction ID from Paypal
 *  @return string          HTML of the ipnlog row specified by $id
 */
function PAYPAL_ipnlogSingle($id, $txn_id)
{
    global $_TABLES, $_CONF, $LANG_PP;

    $sql = "SELECT * FROM {$_TABLES['paypal.ipnlog']} ";
    if ($id > 0) {
        $sql .= "WHERE id = $id";
    } else {
        $sql .= "WHERE txn_id = '$txn_id'";
    }
    $res = DB_query($sql);
    $A = DB_fetchArray($res, false);
    if (empty($A))
        return "Nothing Found";

    // Allow all serialized data to be available to the template
    $ipn = @unserialize($A['ipn_data']);

    if (USES_paypal_gateway($A['gateway'])) {

        $gw = new $A['gateway'];
        $vals = $gw->ipnlogVars($ipn);

        // Create ipnlog template
        $T = new Template($_CONF['path'] . 'plugins/paypal/templates');
        $T->set_file(array('ipnlog' => 'ipnlog_detail.thtml'));

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
    $display .= $T->parse('output', 'ipnlog');

    return $display;
}


/**
 *  Displays the list of ipn history from the log stored in the database.
 *
*   @deprecated
 *  @return string      HTML string containing the contents of the ipnlog
 */
function X_PAYPAL_ipnlogList()
{
    global $_TABLES, $_CONF;

    $display = COM_startBlock('IPN History');

    // Get ipnlog from database
    $sql = "SELECT * 
            FROM {$_TABLES['paypal.ipnlog']} 
            ORDER BY time DESC";
    $res = DB_query($sql);

    // Create ipnlog template
    $ipnlog = new Template($_CONF['path'] . 'plugins/paypal/templates');
    $ipnlog->set_file(array('ipnlog' => 'ipnlog_item.thtml',
                            'start'  => 'ipnlog_start.thtml',
                            'end'    => 'ipnlog_end.thtml') );
    $ipnlog->set_var('site_url', $_CONF['site_url']);

    // Display the begging of the ipnlog list
    $display .= $ipnlog->parse('output', 'start');

    // Display each ipnlog row
    while ($A = DB_fetchArray($res)) {
        $ipnlog->set_var('id', $A['id']);
        $ipnlog->set_var('ip_addr', $A['ip_addr']);
        $ipnlog->set_var('time', $A['time']);
        if ($A['verified']) {
            $ipnlog->set_var('verified', 'true');
        } else {
            $ipnlog->set_var('verified', 'false');
        }
        $ipn = unserialize($A['ipn_data']);
        $ipnlog->set_var('txn_id', $ipn['txn_id']);
        $ipnlog->set_var('status', $ipn['payment_status']);
        $ipnlog->set_var('purchaser', $ipn['custom']);
        $display .= $ipnlog->parse('output', 'ipnlog');
    }

    // Display the end of the ipnlog list
    $display .= $ipnlog->parse('output', 'end');
    $display .= COM_endBlock();
    return $display;
}


/**
*   Send an email with attachments.
*   This is a verbatim copy of COM_mail(), but with the $attachments
*   paramater added and 3 extra lines of code near the end.
*
*   @param  string  $to         Receiver's email address
*   @param  string  $from       Sender's email address
*   @param  string  $subject    Message Subject
*   @param  string  $message    Message Body
*   @param  boolean $html       True for HTML message, False for Text
*   @param  integer $priority   Message priority value
*   @param  string  $cc         Other recipients
*   @param  string  $altBody    Alt. body (text)
*   @param  array   $attachments    Array of attachments
*   @return boolean             True on success, False on Failure
*/
function PAYPAL_mailAttachment(
    $to, 
    $subject, 
    $message, 
    $from = '', 
    $html = false, 
    $priority = 0, 
    $cc = '', 
    $altBody = '',
    $attachments = array()
) {
    global $_CONF;

    $subject = substr( $subject, 0, strcspn( $subject, "\r\n" ));
    $subject = COM_emailEscape( $subject );

    require_once $_CONF['path'] . 'lib/phpmailer/class.phpmailer.php';

    $mail = new PHPMailer();
    $mail->SetLanguage('en',$_CONF['path'].'lib/phpmailer/language/');
    $mail->CharSet = COM_getCharset();
    if ($_CONF['mail_backend'] == 'smtp' ) {
        $mail->IsSMTP();
        $mail->Host     = $_CONF['mail_smtp_host'];
        $mail->Port     = $_CONF['mail_smtp_port'];
        if ( $_CONF['mail_smtp_secure'] != 'none' ) {
            $mail->SMTPSecure = $_CONF['mail_smtp_secure'];
        }
        if ( $_CONF['mail_smtp_auth'] ) {
            $mail->SMTPAuth   = true;
            $mail->Username = $_CONF['mail_smtp_username'];
            $mail->Password = $_CONF['mail_smtp_password'];
        }
        $mail->Mailer = "smtp";

    } elseif ($_CONF['mail_backend'] == 'sendmail') {
        $mail->Mailer = "sendmail";
        $mail->Sendmail = $_CONF['mail_sendmail_path'];
    } else {
        $mail->Mailer = "mail";
    }
    $mail->WordWrap = 76;
    $mail->IsHTML($html);
    $mail->Body = ($message);

    if ( $altBody != '' ) {
        $mail->AltBody = $altBody;
    }

    $mail->Subject = $subject;

    if (is_array($from) && isset($from[0]) && $from[0] != '' ) {
        if ( $_CONF['use_from_site_mail'] == 1 ) {
            $mail->From = $_CONF['site_mail'];
            $mail->AddReplyTo($from[0]);
        } else {
            $mail->From = $from[0];
        }
    } else {
        $mail->From = $_CONF['site_mail'];
    }

    if ( is_array($from) && isset($from[1]) && $from[1] != '' ) {
        $mail->FromName = $from[1];
    } else {
        $mail->FromName = $_CONF['site_name'];
    }
    if ( is_array($to) && isset($to[0]) && $to[0] != '' ) {
        if ( isset($to[1]) && $to[1] != '' ) {
            $mail->AddAddress($to[0],$to[1]);
        } else {
            $mail->AddAddress($to[0]);
        }
    } else {
        // assume old style....
        $mail->AddAddress($to);
    }

    if ( isset($cc[0]) && $cc[0] != '' ) {
        if ( isset($cc[1]) && $cc[1] != '' ) {
            $mail->AddCC($cc[0],$cc[1]);
        } else {
            $mail->AddCC($cc[0]);
        }
    } else {
        // assume old style....
        if ( isset($cc) && $cc != '' ) {
            $mail->AddCC($cc);
        }
    }

    if ( $priority ) {
        $mail->Priority = 1;
    }

    PAYPAL_debug('Attachments: ' . print_r($attachments, true));
    // Add attachments
    foreach($attachments as $key => $value) { 
        $mail->AddAttachment($value);
    }

    if(!$mail->Send()) {
        COM_errorLog("Email Error: " . $mail->ErrorInfo);
        return false;
    }
    return true;
}


/**
 *  Display a popup text message
 *
 *  @param string $msg Text to display 
 */
function PAYPAL_popupMsg($msg)
{
    global $_CONF;

    $msg = htmlspecialchars($msg, ENT_QUOTES, COM_getEncodingt());
    $popup = COM_showMessageText($msg);
    return $popup;

}


/**
*   Display an error message.
*   Uses glFusion's typography to display an "alert" type message.
*   The provided message should be a string containing one or more
*   list elements, e.g. "<li>Bad input</li>".
*
*   @param  string  $msg    List elements of the error message
*   @param  string  $title  Optional title string, shown above list
*   @return string          Complete error message
*/
function PAYPAL_errMsg($msg, $title = '')
{
    $retval = '<span class="alert paypalErrorMsg">' . "\n";
    if (!empty($title))
        $retval .= "<p>$title</p>\n";

    $retval .= "<ul>$msg</ul>\n";
    $retval .= "</span>\n";
    return $retval;
}


/**
*   Recurse through the category table building an option list
*   sorted by id.
*
*   @param integer  $sel        Category ID to be selected in list
*   @param integer  $parent_id  Parent category ID
*   @param string   $char       Separator characters
*   @param string   $not        'NOT' to exclude $items, '' to include
*   @param string   $items      Optional comma-separated list of items to include or exclude
*   @return string              HTML option list, without <select> tags
*/
function PAYPAL_recurseCats(
        $function, $sel=0, $parent_id=0, $char='', $not='', $items='', 
        $level=0, $maxlevel=0, $prepost = array())
{
    global $_TABLES, $_GROUPS;

    $str = '';
    if (empty($prepost)) {
        $prepost = array('', '');
    }

    // Locate the parent category of this one, or the root categories
    // if papa_id is 0.
    $sql = "SELECT cat_id, cat_name, parent_id, description
        FROM {$_TABLES['paypal.categories']}
        WHERE parent_id = $parent_id " .
        PAYPAL_buildAccessSql();

    if (!empty($items)) {
        $sql .= " AND cat_id $not IN ($items) ";
    }
    $sql .= ' ORDER BY cat_name ASC ';
    //echo $sql;die;
    $result = DB_query($sql);
    // If there is no top-level category, just return.
    if (!$result)
        return '';

    while ($row = DB_fetchArray($result, false)) {
        $txt = $char . $row['cat_name'];
        $selected = $row['cat_id'] == $sel ? 'selected="selected"' : '';

        if (!function_exists($function))
            $function = 'PAYPAL_callbackCatOptionList';
        $str .= $function($row, $sel, $parent_id, $txt);
        if ($maxlevel == 0 || $level < $maxlevel) {
            $str .= $prepost[0] . 
                    PAYPAL_recurseCats($function, $sel, $row['cat_id'], 
                        $char."-", $not, $items, $level++, $maxlevel) .
                    $prepost[1];
        }
    }
    return $str;
}   // function PAYPAL_recurseCats()


/**
*   Callback function to create a comma-separated list of categories.
*   Used to create a SQL IN() clause. Will return a prepended comma, need
*   to call trim($str, ',') in the calling function.
*
*   @param  array   $A      Complete category record
*   @param  integer $sel    Selectd item (optional)
*   @param  integer $parent_id  Parent ID from which we've started searching
*   @param  string  $txt    Different text to use for category name.
*   @return string          Option list element for a category
*/
function PAYPAL_callbackCatCommaList($A, $sel=0, $parent=0, $txt='')
{
    return ',' . $A['cat_id'];
}


/**
*   Callback function to create text for option list items.
*
*   @param  array   $A      Complete category record
*   @param  integer $sel    Selectd item (optional)
*   @param  integer $parent_id  Parent ID from which we've started searching
*   @param  string  $txt    Different text to use for category name.
*   @return string          Option list element for a category
*/
function PAYPAL_callbackCatOptionList($A, $sel=0, $parent_id=0, $txt='')
{
    if ($sel > 0 && $A['cat_id'] == $sel) {
        $selected = 'selected="selected"';
    } else {
        $selected = '';
    }

    if ($A['parent_id'] == 0) {
        $style = 'class="paypalCategorySelectRoot"';
    } else {
        $style = '';
    }

    if ($txt == '')
        $txt = $A['cat_name'];

    $str = "<option value=\"{$A['cat_id']}\" $style $selected $disabled>";
    $str .= $txt;
    $str .= "</option>\n";
    return $str;

}


/**
*   Display the site header, with or without blocks according to configuration.
*
*   @param  string  $title  Title to put in header
*   @param  string  $meta   Optional header code
*   @return string          HTML for site header, from COM_siteHeader()
*/
function PAYPAL_siteHeader($title='', $meta='')
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
function PAYPAL_siteFooter()
{
    global $_PP_CONF;

    $retval = '';

    switch($_PP_CONF['displayblocks']) {
    case 2 : // right only
    case 3 : // left and right
        $retval .= COM_siteFooter(true);
        break;

    case 0: // none
    case 1: // left only
    default :
        $retval .= COM_siteFooter();
        break;
    }

    return $retval;
}


/**
*   Create the breadcrumb display, with links.
*
*   @param  integer $id ID of current category
*   @return string      Location string ready for display
*/
function PAYPAL_Breadcrumbs($id)
{
    global $_TABLES, $LANG_PP;

    $A = array();
    $location = '';

    $id = (int)$id;
    if ($id < 1) {
        return $location;
    } else {
        $parent = $id;
    }

    while (true) {
        $sql = "SELECT cat_name, cat_id, parent_id
            FROM {$_TABLES['paypal.categories']}
            WHERE cat_id='$parent' " .
            PAYPAL_buildAccessSql();
        $result = DB_query($sql);
        if (!$result) 
            break;

        $row = DB_fetchArray($result, false);
        $url = '<a href="' . PAYPAL_URL . '/index.php?category=' . 
                (int)$row['cat_id'] . '">' . $row['cat_name'] . '</a>';
        $A[] = $url;

        $parent = (int)$row['parent_id'];
        if ($parent == 0) {
            $url = '<a href="' . 
                    COM_buildURL(PAYPAL_URL . '/index.php') . 
                    '">' . $LANG_PP['home'] . '</a>';
            $A[] = $url;
            break;
        }
    }

    $B = array_reverse($A);
    $location = implode(' :: ', $B);
    return $location;
}


function PAYPAL_clearNextView()
{
    $_SESSION[PP_CART_VAR]['prevpage'] = '';
}


/**
*   Create the tabbed user menu.
*   Provides a common menu creation for user-facing files such as index.php
*   and detail.php
*
*   @deprecated 0.5.7
*   @param  string  $selected   Currently-select menu option text
*   @return string              HTML for tabbed menu
*/
function PAYPAL_userMenu($selected = '')
{
    global $LANG_PP, $ppGCart;

    USES_class_navbar();

    $menu = new navbar();
    $menu->add_menuitem($LANG_PP['product_list'], PAYPAL_URL . '/index.php');
    if (!COM_isAnonUser()) {
        $menu->add_menuitem($LANG_PP['purchase_history'],
                PAYPAL_URL . '/index.php?view=history');
    }
    if ($ppGCart->hasItems()) {
        $menu->add_menuitem($LANG_PP['viewcart'],
                PAYPAL_URL . '/index.php?view=cart');
    }
    if (SEC_hasRights('paypal.admin')) {
        $menu->add_menuitem($LANG_PP['mnu_admin'],
                PAYPAL_ADMIN_URL . '/index.php');
    }
    if ($selected != '') $menu->set_selected($selected);
    return $menu->generate();
}

/**
*   Common function used to build group access SQL
*   Modified version of SEC_buildAccessSql. This one allow a field name
*   to be provided, which can include a table identifier if needed.
*
*   @param  string  $clause     Optional parm 'WHERE' - default is 'AND'
*   @param  string  $fld        Optional field, including table id if needed
*   @return string  $groupsql   Formatted SQL string to be appended 
*/
function PAYPAL_buildAccessSql($clause='AND', $fld='grp_access')
{
    global $_USER, $_GROUPS;

    $groupsql = '';
    if (count($_GROUPS) == 1) {
        $groupsql .= " $clause $fld = '" . current($_GROUPS) ."'";
    } else {
        $groupsql .= " $clause $fld IN (" . implode(',',array_values($_GROUPS)) .")";
    }

    return $groupsql;
}

?>

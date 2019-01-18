<?php
/**
 * Handle the headline autotag for the Paypal plugin.
 * Based on the glFusion headline autotag.
 *
 * @package     paypal
 * @copyright   Copyright (c) 2018 Lee Garner <lee AT leegarner DOT com>
 * @license     GNU General Public License version 2 or later
 */
namespace Paypal\Autotags;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

/**
 * Headline autotag class.
 * @package paypal
 */
class headlines
{
    /**
     * Parse the autotag and render the output.
     *
     * @param   string  $p1         First option after the tag name
     * @param   string  $opts       Name=>Vaue array of other options
     * @param   string  $fulltag    Full autotag string
     * @return  string      Replacement HTML, if applicable.
     */
    public function parse($p1, $opts=array(), $fulltag='')
    {
        global $_CONF, $_TABLES, $_USER, $LANG01;

        // display = how many stories to display, if 0, then all
        // meta = show meta data (i.e.; who when etc)
        // titleLink - make title a hot link
        // featured - 0 = show all, 1 = only featured, 2 = all except featured
        // frontpage - 1 = show only items marked for frontpage - 0 = show all
        // cols - number of columns to show
        // sort - sort by date, views, rating, featured (implies date)
        // order - desc, asc
        // template - the template name

        $cacheID = md5($p1 . $fulltag);
        $retval = \Paypal\Cache::get($cacheID);
        if ($retval !== NULL) {
            return $retval;
        } else {
            $retval = '';
        }

        $display    = 10;       // display 10 articles
        $featured   = 0;        // 0 = show all, 1 = only featured
        $cols       = 3;        // number of columns
        $truncate   = 0;        // maximum number of characters to include in story text
        $sortby     = 'rating'; // sort by: date, views, rating, featured
        $orderby    = 'desc';   // order by - desc or asc
        $autoplay   = 'true';
        $interval   = 7000;
        $template   = 'headlines.thtml';
        $category   = 0;

        $valid_sortby = array('dt_add','views','rating','featured');
        foreach ($opts as $key=>$val) {
            $val = strtolower($val);
            switch ($key) {
            case 'sortby':
                // Make sure the selected sortby value is valid
                if (in_array($val, $valid_sortby)) {
                    $sortby = $val;
                } else {
                    $sortby = 'featured';
                }
                break;
            case 'orderby':
                $valid_order = array('desc','asc');
                if (in_array($val, $valid_order)) {
                    $orderby = $val;
                } else {
                    $orderby = 'desc';
                }
                break;
            case 'featured':
                $$key = $val == 1 ? 1 : 0;
                break;
            case 'autoplay':
                $autoplay = $val == 'true' ? 'true' : 'false';
                break;
            case 'display':
            case 'cols':
            case 'interval':
            case 'category':
                $$key = (int)$val;
                break;
            default:
                $$key = $val;
                break;
            }
        }

        $where = '';
        if ($display != 0) {
            $limit = " LIMIT $display";
        } else {
            $limit = '';
        }
        if ($featured == 1) {
            $where .= ' AND featured = 1';
        }
        if ($category > 0) {
            $objects = \Paypal\Category::getTree($category);
            foreach ($objects as $Obj) {
                $cats[] = $Obj->cat_id;
            }
            if (!empty($cats)) {
                $cats = DB_escapeString(implode(',', $cats));
                $where .= ' AND p.cat_id IN (' . $cats . ')';
            }
        }

        // The "c.enabled IS NULL" is to allow products which have
        // no category record, as long as the product is enabled.
        $sql = "SELECT id
            FROM {$_TABLES['paypal.products']} p
            LEFT JOIN {$_TABLES['paypal.categories']} c
            ON p.cat_id=c.cat_id
            WHERE
                p.enabled=1 AND (c.enabled=1 OR c.enabled IS NULL) AND
                (p.track_onhand = 0 OR p.onhand > 0 OR p.oversell < 2) $where " .
            SEC_buildAccessSql('AND', 'c.grp_access') . "
            ORDER BY $sortby $orderby
            $limit";
        $res = DB_query($sql);
        $allItems = DB_fetchAll($res, false);
        $numRows = @count($allItems);

        if ($numRows < $cols) {
            $cols = $numRows;
        }
        if ($cols > 6) {
            $cols = 6;
        }

        if ($numRows > 0) {
            $T = new \Template(__DIR__ . '/../../templates/autotags');
            $T->set_file('page',$template);
            $T->set_var('columns',$cols);
            $T->set_block('page','headlines','hl');

            foreach ($allItems as $A) {
                $P = \Paypal\Product::getInstance($A['id']);
                $img = $P->getOneImage();
                $T->set_var(array(
                    'url'       => PAYPAL_URL . '/detail.php?id='. $P->id,
                    'text'      => trim($P->description),
                    'title'     => $P->short_description,
                    'thumb_url' => $img != '' ? PAYPAL_ImageUrl($img) : '',
                    'large_url' => $img != '' ? PAYPAL_ImageUrl($img, 1024, 1024) : '',
                    'autoplay'  => $autoplay,
                    'autoplay_interval' => $interval,
                ) );
                $T->parse('hl', 'headlines', true);
            }
            $retval = $T->finish($T->parse('output', 'page'));
            \Paypal\Cache::set($cacheID, $retval, array('products', 'categories'));
        }
        return $retval;
    }

}

?>

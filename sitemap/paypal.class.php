<?php
/**
*   Sitemap driver for the Paypal plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017-2018 Lee Garner
*   @package    paypal
*   @version    0.5.10
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

class sitemap_paypal extends sitemap_base
{
    protected $name = 'paypal';

    public function getEntryPoint()
    {
        return PAYPAL_URL;
    }

 
    public function getDisplayName()
    {
        global $LANG_PP;
        return $LANG_PP['main_title'];
    }


    public function getItems($cat_id = 0)
    {
        global $_TABLES, $_USER;

        $entries = array();
        $opts = array();
        if ($cat_id > 0) {
            $opts['cat_id'] = $cat_id;
        }
        $items = PLG_getItemInfo('paypal', '*', 'id,title,date,url', $_USER['uid'], $opts);
        if (is_array($items)) {
            foreach ($items as $A) {
                $entries[] = array(
                    'id'    => $A['id'],
                    'title' => $A['title'],
                    'uri'   => $A['url'],
                    'date'  => $A['date'],
                    'image_uri' => false,
                );
            }
        }
        return $entries;
    }

    public function getChildCategories($base = false)
    {
        global $_TABLES;

        if (!$base) $base = 0;      // make numeric
        $base = (int)$base;
        $retval = array();

        $sql = "SELECT * FROM {$_TABLES['paypal.categories']}
                WHERE parent_id = $base";
        $res = DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Paypal getChildCategories error: $sql");
            return $retval;
        }

        while ($A = DB_fetchArray($res, false)) {
            $retval[] = array(
                'id'        => $A['cat_id'],
                'title'     => $A['cat_name'],
                'uri'       => PAYPAL_URL . '/index.php?category=' . $A['cat_id'],
                'date'      => false,
                'image_uri' => PAYPAL_URL . '/images/categories/' . $A['image'],
            );
        }
        return $retval;
    }

}

?>

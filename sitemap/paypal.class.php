<?php

class sitemap_paypal extends sitemap_base
{

    public function getEntryPoint()
    {
        return PAYPAL_URL;
    }

 
    public function getName()
    {
        global $_PP_CONF;
        return $_PP_CONF['pi_name'];
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
        $sql = "SELECT * FROM {$_TABLES['paypal.products']} p
                LEFT JOIN {$_TABLES['paypal.categories']} c
                    ON p.cat_id = c.cat_id "
            . SEC_buildAccessSql('WHERE', 'c.grp_access');
        if ($cat_id > 0) {
            $sql .= ' AND p.cat_id = ' . (int)$cat_id;
        }
        $result = DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("sitemap_paypal::getItems() SQL error: $sql");
            return $entries;
        }
        while ($A = DB_fetchArray($result, false)) {
            $entries[] = array(
                'id'    => $A['id'],
                'title' => $A['short_description'],
                'uri'   => COM_buildURL(
                    PAYPAL_URL . '/index.php?detail=detail&amp;id='
                    . rawurlencode($A['id'])),
                'date'  => strtotime($A['dt_add']),
                'image_uri' => false,
            );
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

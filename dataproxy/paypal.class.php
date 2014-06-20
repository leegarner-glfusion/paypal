<?php
// +--------------------------------------------------------------------------+
// | Data Proxy Plugin for glFusion                                           |
// +--------------------------------------------------------------------------+
// | paypal.class.php                                                         |
// |                                                                          |
// | Paypal Plugin interface                                                  |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2008 by the following authors:                             |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// |                                                                          |
// | Based on the Data Proxy Plugin for Geeklog CMS                           |
// | Copyright (C) 2007-2008 by the following authors:                        |
// |                                                                          |
// | Authors: mystral-kk        - geeklog AT mystral-kk DOT net               |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+
/**
*   @author     Mark R. Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2008 Mark R. Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2007-2008 Mystral-kk <geeklog@mystral-kk.net>
*   @package    paypal
*   @version    0.4.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
*   Implementation of class DataproxyDriver for the Paypal plugin
*   @package paypal
*/
class Dataproxy_paypal extends DataproxyDriver
{
    var $driver_name = 'paypal';

    function isLoginRequired()
    {
        global $_PP_CONF;

        return !$_PP_CONF['anon_buy'];
    }

    /*
    * Returns the location of index.php of each plugin
    */
    function getEntryPoint()
    {
        return PAYPAL_URL . '/index.php';
    }

    /**
    * @param $pid int/string/boolean id of the parent category.  False means
    *        the top category (with no parent)
    * @return array(
    *   'id'        => $id (string),
    *   'pid'       => $pid (string: id of its parent)
    *   'title'     => $title (string),
    *   'uri'       => $uri (string),
    *   'date'      => $date (int: Unix timestamp),
    *   'image_uri' => $image_uri (string)
    *  )
    */
    function getChildCategories($pid = false)
    {
        global $_CONF, $_TABLES, $_PP_CONF;

        $entries = array();

        if ($this->uid == 1 $this->isLoginRequired() === true) {
            return $entries;
        }

        if ($pid === false) {
            $pid = 0;
        }

        $sql = "SELECT * 
                FROM {$_TABLES['paypal.categories']}
                WHERE parent_id='$pid' ";

        /*if ($this->uid > 0) {
            $sql .= COM_getPermSQL('AND ', $this->uid);

        }*/
        $sql .= " ORDER BY cat_id";

        $result = DB_query($sql);
        if (DB_error()) {
            return $entries;
        }

        while (($A = DB_fetchArray($result)) !== false) {
            $entry = array();

            $entry['id']        = $A['cat_id'];
            $entry['pid']       = $A['parent_id'];
            $entry['title']     = stripslashes($A['cat_name']);
            $entry['uri']       = COM_buildUrl(PAYPAL_URL 
                                . '/index.php?category='
                                . urlencode($entry['id']));
            $entry['date']      = 'false';
            if (!empty($A['image'])) {
                $entry['image_uri'] = PAYPAL_URL . '/images/categories/' .
                                urlencode($A['image']);
            } else {
                $entry['image_uri'] = false;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
    * Returns array of (
    *   'id'        => $id (string),
    *   'title'     => $title (string),
    *   'uri'       => $uri (string),
    *   'date'      => $date (int: Unix timestamp),
    *   'image_uri' => $image_uri (string),
    *   'raw_data'  => raw data of the item (stripslashed)
    * )
    */
    function getItemById($id, $all_langs = false)
    {
        global $_CONF, $_TABLES, $_PP_CONF;

        $retval = array();

        if (($this->uid == 1) && ($this->isLoginRequired() === true)) {
            return $retval;
        }

        $sql = "SELECT 
                    p.id, p.name, p.dt_add
                FROM {$_TABLES['paypal.products']} p
                WHERE (id ='" . DB_escapeString($id) . "') ";

        $result = DB_query($sql);
        if (DB_error()) {
            return $retval;
        }

        if (DB_numRows($result) == 1) {
            $A = DB_fetchArray($result, false);
            $A = array_map('stripslashes', $A);

            $retval['id']        = $A['id'];
            $retval['title']     = $A['name'];

            $retval['uri']       = COM_buildUrl(PAYPAL_URL
                                . '/detail.php?id='. urlencode($A['id']));
            $retval['date']      = $A['dt_add'];
            if (!empty($A['filename'])) {
                $retval['image_uri'] = PAYPAL_URL . '/images/products/'
                                . urlencode($A['filename']);
            } else {
                $retval['image_uri'] = false;
            }
            $retval['raw_data']  = $A;
        }

        return $retval;
    }

    /**
    * Returns an array of (
    *   'id'        => $id (string),
    *   'title'     => $title (string),
    *   'uri'       => $uri (string),
    *   'date'      => $date (int: Unix timestamp),
    *   'image_uri' => $image_uri (string)
    * )
    */
    function getItems($category, $all_langs = false)
    {
        global $_CONF, $_TABLES, $_PP_CONF;

        $entries = array();
        $category = (int)$category;

        if ($this->uid == 1 && $this->isLoginRequired() === true) {
            return $entries;
        }

        $sql = "SELECT p.id, p.name, p.dt_add
                FROM {$_TABLES['paypal.products']} p
                WHERE (p.cat_id ='" . DB_escapeString($category) . "') 
                ORDER BY id";

        $result = DB_query($sql);
        if (DB_error()) {
            return $entries;
        }
        while (($A = DB_fetchArray($result, false)) !== false) {
            $entry = array();

            $entry['id']        = $A['id'];
            $entry['title']     = $A['name'];

            $entry['uri']       = COM_buildUrl(PAYPAL_URL 
                                . '/detail.php?id=' . urlencode($A['id']));
            $entry['date']      = $A['dt_add'];
            $entry['image_uri'] = PAYPAL_URL . '/images/products/'
                                . urlencode($A['filename']);

            $entries[] = $entry;
        }

        return $entries;
    }
}
?>

<?php
/**
*   Class to cache DB and web lookup results
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.12
*   @since      0.5.12
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for Paypal Cache
*   @package paypal
*/
class Cache
{
    private static $tag = 'paypal'; // fallback tag

    /**
    *   Update the cache.
    *   Adds an array of tags including the plugin name
    *
    *   @param  string  $key    Item key
    *   @param  mixed   $data   Data, typically an array
    *   @param  mixed   $tag    Tag, or array of tags.
    *   @param  integer $cache_mins Cache minutes
    */
    public static function set($key, $data, $tag='', $cache_mins=0)
    {
        if (version_compare(GVERSION, '1.8.0', '<')) {
            return;     // caching requires glFusion 1.8.0 or higher
        }

        $cache_mins = (int)$cache_mins;
        if ($cache_mins < 10) {     // 10-minute minimum
            $cache_mins = 30;       // 30-minute default
        }
        // Always make sure the base tag is included
        $tags = array(self::$tag);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        $key = self::makeKey($key);
        \glFusion\Cache::getInstance()
            ->set($key, $data, $tags, $cache_mins * 60);
    }


    /**
    *   Delete a single item from the cache by key
    *
    *   @param  string  $key    Base key, e.g. item ID
    */
    public static function delete($key)
    {
        if (version_compare(GVERSION, '1.8.0', '<')) {
            return;     // caching requires glFusion 1.8.0 or higher
        }
        $key = self::makeKey($key);
        \glFusion\Cache::getInstance()->delete($key);
    }


    /**
    *   Completely clear the cache.
    *   Called after upgrade.
    *
    *   @param  array   $tag    Optional array of tags, base tag used if undefined
    */
    public static function clear($tag = array())
    {
        if (version_compare(GVERSION, '1.8.0', '<')) {
            return;     // caching requires glFusion 1.8.0 or higher
        }
        $tags = array(self::$tag);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        \glFusion\Cache::getInstance()->deleteItemsByTagsAll($tags);
    }


    /**
    *   Create a unique cache key.
    *   Intended for internal use, but public in case it is needed.
    *
    *   @param  string  $key    Base key, e.g. Item ID
    *   @return string          Encoded key string to use as a cache ID
    */
    public static function makeKey($key)
    {
        //$key = \glFusion\Cache::getInstance()->createKey(self::$tag . '_' . $key);
        $key = self::$tag . '_' . $key;
        return $key;
    }


    /**
    *   Get an item from cache.
    *
    *   @param  string  $key    Key to retrieve
    *   @return mixed       Value of key, or NULL if not found
    */
    public static function get($key)
    {
        if (version_compare(GVERSION, '1.8.0', '<')) {
            return NULL;     // caching requires glFusion 1.8.0 or higher
        }
        $key = self::makeKey($key);
        if (\glFusion\Cache::getInstance()->has($key)) {
            return \glFusion\Cache::getInstance()->get($key);
        } else {
            return NULL;
        }
    }

}   // class Paypal\Cache

?>

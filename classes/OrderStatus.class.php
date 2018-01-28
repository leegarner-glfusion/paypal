<?php
/**
*   Class to manage order processing statuses.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2017 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.10
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

namespace Paypal;

/**
*   Class for order processing workflow items
*   Order statuses are defined in the database and can be re-ordered and 
*   individually enabled or disabled.
*   @package paypal
*/
class OrderStatus extends Workflow
{
    static $table = 'paypal.orderstatus';

    /**
    *   Constructor.
    *   Initializes the array of orderstatus.
    *
    *   @uses   Load()
    */
    function __construct()
    {
        self::Init();
    }


    /**
    *   Load the orderstatus into the global workflow array.
    */
    public static function Load()
    {
        global $_PP_CONF, $_TABLES;

        $_PP_CONF['orderstatus'] = array();
        $sql = "SELECT name, notify_buyer
                FROM {$_TABLES[self::$table]}
                WHERE enabled = 1
                ORDER BY orderby ASC";
        //echo $sql;die;
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $_PP_CONF['orderstatus'][$A['name']] = array(
                'notify_buyer' => $A['notify_buyer'],
            );
        }
    }


    /**
    *   Initilize the workflow array, if not already done.
    *
    *   @uses   Load()
    */
    public static function Init($force=false)
    {
        global $_PP_CONF;

        if ($force || !isset($_PP_CONF['orderstatus']) || 
            !is_array($_PP_CONF['orderstatus'])) {
            self::Load();
        }
    }


    /**
    *   Creates the complete selection HTML for order status updates.
    *
    *   @param  string  $order_id   ID of order being edited
    *   @param  integer $showlog    1 to add to the onscreen log, 0 to not
    *   @param  string  $selected   Current order status
    *   @return string      HTML for select block
    */
    public static function Selection($order_id, $showlog=0, $selected = '')
    {
        global $LANG_PP, $_PP_CONF;

        self::Init();

        $T = new \Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file('ordstat', 'orderstatus.thtml');
        $T->set_var(array(
            'order_id'  => $order_id,
            'oldvalue'  => $selected,
            'showlog'   => $showlog == 1 ? 1 : 0,
        ) );
        $T->set_block('ordstat', 'StatusSelect', 'Sel');
        foreach ($_PP_CONF['orderstatus'] as $key => $data) {
            $T->set_var(array(
                'selected' => $key == $selected ?
                                'selected="selected"' : '',
                'stat_key' => $key,
                'stat_descr' => isset($LANG_PP['orderstatus'][$key]) ?
                        $LANG_PP['orderstatus'][$key] : $key,
            ) );
            $T->parse('Sel', 'StatusSelect', true);
        }
        $T->parse('output', 'ordstat');
        return $T->finish ($T->get_var('output'));
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @param  integer $id         ID number of element to modify
    *   @param  string  $field      Database fieldname to change
    *   @param  integer $oldvalue   Original value to change
    *   @return         New value, or old value upon failure
    */
    public function Toggle($id, $field, $oldvalue)
    {
        global $_TABLES;

        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $id = (int)$id;
        if ($id < 1)
            return $oldvalue;
        $field = DB_escapeString($field);

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES[self::$table]}
                SET $field = $newvalue
                WHERE id='$id'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (!DB_error()) {
            return $newvalue;
        } else {
            COM_errorLog("OrderStatus::Toggle() SQL error: $sql", 1);
            return $oldvalue;
        }
    }

}   // class OrderStatus

?>

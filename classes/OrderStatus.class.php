<?php
/**
*   Class to manage order processing statuses.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
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
    public static function Toggle($id, $field, $oldvalue)
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


    /**
    *   Move a workflow up or down the admin list.
    *
    *   @param  string  $id     Workflow database ID
    *   @param  string  $where  Direction to move (up or down)
    */
    public static function moveRow($id, $where)
    {
        global $_TABLES;

        $retval = '';
        $id = DB_escapeString($id);

        switch ($where) {
        case 'up':
            $oper = '-';
            break;
        case 'down':
            $oper = '+';
            break;
        default:
            return;
        }
        $sql = "UPDATE {$_TABLES[self::$table]}
                SET orderby = orderby $oper 11
                WHERE id = '$id'";
        //echo $sql;die;
        DB_query($sql, 1);
        if (!DB_error()) {
            self::ReOrder();
        } else {
            COM_errorLog("Workflow::moveRow() SQL error: $sql", 1);
        }
    }


    /**
    *   Reorder all workflow items.
    */
    public static function ReOrder()
    {
        global $_TABLES;

        $sql = "SELECT id, orderby
                FROM {$_TABLES[self::$table]}
                ORDER BY orderby ASC;";
        //echo $sql;die;
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        $changed = false;
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $changed = true;
                $sql = "UPDATE {$_TABLES[self::$table]}
                    SET orderby = '$order'
                    WHERE id = '{$A['id']}'";
                DB_query($sql, 1);
                if (DB_error()) {
                    COM_errorLog("Workflow::ReOrder() SQL error: $sql", 1);
                }
            }
            $order += $stepNumber;
        }
        if ($changed) Cache::clear('orderstatuses');
    }

}   // class OrderStatus

?>

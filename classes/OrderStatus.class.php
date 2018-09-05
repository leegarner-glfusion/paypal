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

    private $name;
    private $enabled;
    private $orderby;
    private $notify_buyer;
    private $notify_admin;

    /**
    *   Constructor.
    *   Initializes the array of orderstatus.
    *
    *   @see    self::getAll()
    *   @param  array   $A  Array of data from the DB
    */
    public function __construct($A=array())
    {
        if (is_array($A)) {
            $this->notify_buyer = (int)$A['notify_buyer'];
            $this->notify_admin = (int)$A['notify_buyer'];
            $this->name = $A['name'];
            $this->enabled = (int)$A['enabled'];
            $this->orderby = (int)$A['orderby'];
        } else {
            $this->name = 'undefined';
            $this->enabled = 0;
            $this->orderby = 0;
            $this->notify_buyer = 0;
            $this->notify_admin = 0;
        }
    }


    /**
    *   Get all order status objects into an array
    */
    public static function getAll()
    {
        global $_TABLES;
        static $statuses = NULL;

        if ($statuses === NULL) {
            $statuses = array();
            $sql = "SELECT *
                    FROM {$_TABLES[self::$table]}
                    ORDER BY orderby ASC";
            //echo $sql;die;
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $statuses[$A['name']] = new self($A);
            }
        }
        return $statuses;
    }


    /**
     * Get a single status instance
     *
     * @param   string  Name of status to get
     * @return  array   Array of status info
     */
    public static function getInstance($name)
    {
        $statuses = self::getAll();
        if (isset($statuses[$name])) {
            return $statuses[$name];
        } else {
            return new self();
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
        global $LANG_PP;

        $T = PP_getTemplate('orderstatus', 'ordstat');
        $T->set_var(array(
            'order_id'  => $order_id,
            'oldvalue'  => $selected,
            'showlog'   => $showlog == 1 ? 1 : 0,
        ) );
        $T->set_block('ordstat', 'StatusSelect', 'Sel');
        foreach (self::getAll() as $key => $data) {
            if (!$data->enabled) continue;
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


    public function notifyBuyer()
    {
        return $this->notify_buyer == 1 ? true : false;
    }


    /**
     * Find out whether this status requires notification to the buyer.
     *
     * @return  boolean     True or False
     */
    public function notifyBuyer()
    {
        return $this->notify_buyer == 1 ? true : false;
    }


    /**
     * Find out whether this status requires notification to the administrator
     *
     * @return  boolean     True or False
     */
    public function notifyAdmin()
    {
        return $this->notify_admin == 1 ? true : false;
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
     *   Called after moveRow()
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

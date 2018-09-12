<?php
/**
*   Class to manage workflow.
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
*   Class for workflow items
*   Workflows are defined in the database and can be re-ordered and
*   individually enabled or disabled.  The workflows determine which screens
*   are displayed during checkout and in what order they appear.
*   @package paypal
*/
class Workflow
{
    public const DISABLED = 0;
    public const REQ_PHYSICAL = 1;
    public const REQ_VIRTUAL = 2;   // unused placeholder
    public const REQ_ALL = 3;

    static $table = 'paypal.workflows';
    public $wf_name;
    public $orderby;
    public $wf_id;
    public $enabled;

    /**
    *   Constructor.
    *   Initializes the array of workflows.
    *
    *   @uses   Load()
    */
    public function __construct($A = array())
    {
        if (!empty($A)) {
            $this->wf_name = $A['wf_name'];
            $this->enabled = (int)$A['enabled'];
            $this->wf_id = (int)$A['id'];
            $this->orderby = (int)$A['orderby'];
        }
    }


    /**
    *   Load the workflows into the global workflow array.
    */
    public static function Load()
    {
        global $_TABLES, $_PP_CONF;

        if (!isset($_PP_CONF['workflows'])) {
            $_PP_CONF['workflows'] = array();
            $sql = "SELECT wf_name
                    FROM {$_TABLES[self::$table]}
                    WHERE enabled > 0
                    ORDER BY orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $_PP_CONF['workflows'][] = $A['wf_name'];
            }
        }
    }


    /**
     * Get all workflow items, in order of processing.
     * If a cart is supplied, get the appropriate enabled workflows based
     * on the cart contents.
     * If the cart is NULL, get all workflows.
     *
     * @return  array   Array of workflow names
     */
    public static function getAll($Cart = NULL)
    {
        global $_TABLES;

        if ($Cart) {
            $statuses = array(self::REQ_ALL, self::REQ_VIRTUAL);
            if ($Cart->hasPhysical()) $statuses[] = self::REQ_PHYSICAL;
            $statuslist = implode(',', $statuses);
            $where = " WHERE enabled IN ($statuslist)";
        } else {
            $where = '';
            $statuslist = '0';
        }
        $cache_key = 'workflows_enabled_' . $statuslist;
        $workflows = Cache::get($cache_key);
        if (!$workflows) {
            $sql = "SELECT * FROM {$_TABLES[self::$table]}
                $where
                ORDER BY orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $workflows[] = new self($A);
            }
            Cache::set($cache_key, $workflows, 'workflows');
        }
        return $workflows;
    }


    /**
     * Get an instance of a workflow step
     *
     * @uses    self::getall() to take advantage of caching
     * @param   integer $id     Workflow record ID
     * @return  object          Workflow object, or NULL if not defined/disabled
     */
    public static function getInstance($id)
    {
        global $_TABLES;

        $workflows = self::getAll();
        foreach ($workflows as $wf) {
            if ($wf->wf_id == $id) {
                return $wf;
            }
        }
        return NULL;
    }


    /**
    *   Set a boolean field to the specified value.
    *
    *   @param  integer $id ID number of element to modify
    *   @param  integer $value New value to set
    *   @return         New value, or old value upon failure
    */
    protected static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // If it's still an invalid ID, return the old value
        if ($id < 1)
            return $oldvalue;

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES[self::$table]}
                SET $varname=$newvalue
                WHERE id='$id'";
        //echo $sql;die;
        DB_query($sql);

        return $newvalue;
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @param  integer $id         ID number of element to modify
    *   @param  string  $field      Database fieldname to change
    *   @param  integer $oldvalue   Original value to change
    *   @return         New value, or old value upon failure
    */
    public static function setValue($id, $field, $newvalue)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id < 1)
            return -1;
        $field = DB_escapeString($field);

        // Determing the new value (opposite the old)
        $newvalue = (int)$newvalue;

        $sql = "UPDATE {$_TABLES[self::$table]}
                SET $field = $newvalue
                WHERE id='$id'";
        DB_query($sql, 1);
        if (!DB_error()) {
            Cache::clear('workflows');
            COM_errorLog("returning $newvalue");
            return $newvalue;
        } else {
            COM_errorLog("Workflow::Toggle() SQL error: $sql", 1);
            return -1;
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
        if ($changed) Cache::clear('workflows');
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
    *   Get the next view in the workflow to be displayed.
    *   This function receives the name of the current view, then looks
    *   in it's array of views to return the next in line.
    *
    *   @param  string  $currview   Current view
    *   @return string              Next view in line
    */
    public static function getNextView($currview = '')
    {
        global $_PP_CONF;

        /** Load the views, if not done already */
        $workflows = self::getAll();

        // If the current view is empty, or isn't part of our array,
        // then set the current key to -1 so we end up returning value 0.
        if ($currview == '') {
            $curr_key = -1;
        } else {
            $curr_key = array_search($currview, $workflows);
            if ($curr_key === false) $curr_key = -1;
        }

        if ($curr_key > -1) {
            Cart::setSession('prevpage', $workflows[$curr_key]);
        }
        if (isset($workflows[$curr_key + 1])) {
            $view = $workflows[$curr_key + 1];
        } else {
            $view = 'checkoutcart';
        }
        return $view;
    }

}   // class Workflow

?>

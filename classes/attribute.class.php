<?php
/**
 *  Class to manage product options
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2010 Lee Garner <lee@leegarner.com>
 *  @package    paypal
 *  @version    0.5.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 *  @filesource
 */


/**
 *  Class for product options
 *  @package paypal
 */
class Attribute
{
    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    var $properties;

    /** Indicate whether the current object is a new entry or not.
    *   @var boolean */
    var $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     *  @var array */
    var $Errors = array();


    /**
    *   Constructor.
    *   Reads in the specified class, if $id is set.  If $id is zero,
    *   then a new entry is being created.
    *
    *   @param  integer $id Attributeal type ID
    */
    public function __construct($id=0)
    {
        $this->properties = array();
        $this->isNew = true;

        $id = (int)$id;
        if ($id < 1) {
            // New entry, set defaults
            $this->attr_id = 0;
            $this->attr_name = 0;
            $this->attr_value = '';
            $this->attr_price = 0;
            $this->item_id = 0;
            $this->enabled = 1;
            $this->orderby = 9999;
        } else {
            $this->attr_id =  $id;
            if (!$this->Read()) {
                $this->attr_id = 0;
            }
        }
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'attr_id':
        case 'item_id':
        case 'orderby':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'attr_value':
        case 'attr_name':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        case 'attr_price':
            // Floating-point values
            $this->properties[$var] = (float)$value;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
    *   Get the value of a property.
    *
    *   @param  string  $var    Name of property to retrieve.
    *   @return mixed           Value of property, NULL if undefined.
    */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
    *   Sets all variables to the matching values from $row
    *
    *   @param array $row Array of values, from DB or $_POST
    */
    public function SetVars($row)
    {
        if (!is_array($row)) return;

        $this->attr_id = $row['attr_id'];
        $this->item_id = $row['item_id'];
        $this->attr_name = $row['attr_name'];
        $this->attr_value = $row['attr_value'];
        $this->attr_price = $row['attr_price'];
        $this->enabled = $row['enabled'];
        $this->orderby = $row['orderby'];
    }


    /**
    *   Read a specific record and populate the local values.
    *
    *   @param  integer $id Attributeal ID.  Current ID is used if zero.
    *   @return boolean     True if a record was read, False on failure
    */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->attr_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query("SELECT *
                    FROM {$_TABLES['paypal.prod_attr']}
                    WHERE attr_id='$id'");
        if (!$result || DB_numRows($result != 1)) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row);
            $this->isNew = false;
            return true;
        }
    }


    /**
    *   Save the current values to the database.
    *
    *   @param  array   $A      Attributeal array of values from $_POST
    *   @return boolean         True if no errors, False otherwise
    */
    public function Save($A = array())
    {
        global $_TABLES, $_PP_CONF;

        if (is_array($A)) {
            // Put this field at the end of the line by default
            if (empty($A['orderby']))
                $A['orderby'] = 65535;

            $this->SetVars($A);
        }

        // Get the option group in from the text field, or selection
        if (isset($_POST['attr_name']) && !empty($_POST['attr_name'])) {
            $this->attr_name = $_POST['attr_name'];
        } else {
            $this->attr_name = $_POST['attr_name_sel'];
        }

        // Make sure the necessary fields are filled in
        if (!$this->isValidRecord()) {
            return false;
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['paypal.prod_attr']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['paypal.prod_attr']}";
            $sql3 = " WHERE attr_id={$this->attr_id}";
        }

        $sql2 = " SET item_id='{$this->item_id}',
                attr_name='" . DB_escapeString($this->attr_name) . "',
                attr_value='" . DB_escapeString($this->attr_value) . "',
                orderby='{$this->orderby}',
                attr_price='{$this->attr_price}',
                enabled='{$this->enabled}'";
        $sql = $sql1 . $sql2 . $sql3;

        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            if ($this->isNew) {
                $this->attr_id = DB_insertID();
            }
            $this->ReOrder();
            return true;
        } else {
            $this->AddError($err);
            return false;
        }
    }


    /**
    *   Delete the current category record from the database.
    *   Callable as a static function Attribute::Delete($attr_id)
    *
    *   @param  integer $attr_id    Attribute ID, empty for current object
    *   @return boolean     True on success, False on invalid ID
    */
    public function Delete($attr_id = 0)
    {
        global $_TABLES;

        if ($attr_id == 0 && is_object($this)) {
            $attr_id = $this->attr_id;
            $this->attr_id = 0;
        }
        if ($attr_id <= 0)
            return false;

        DB_delete($_TABLES['paypal.prod_attr'], 'attr_id', $attr_id);

        //$this->ReOrder();

        return true;
    }


    /**
    *   Determines if the current record is valid.
    *
    *   @return boolean     True if ok, False when first test fails.
    */
    public function isValidRecord()
    {
        // Check that basic required fields are filled in
        if ($this->item_id == 0 ||
            $this->attr_name == '' ||
            $this->attr_value == '') {
            return false;
        }

        return true;
    }


    /**
    *   Creates the edit form.
    *
    *   @param  integer $id Attributeal ID, current record used if zero
    *   @return string      HTML for edit form
    */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $_SYSTEM;

        // If there are no products defined, return a formatted error message
        // instead of the form.
        if (DB_count($_TABLES['paypal.products']) == 0) {
            return PAYPAL_errMsg($LANG_PP['todo_noproducts']);
        }

        $T = new Template(PAYPAL_PI_PATH . '/templates');
        if ($_SYSTEM['disable_jquery_slimbox']) {
            $T->set_file('attrform', 'attribute_form.uikit.thtml');
        } else {
            $T->set_file('attrform', 'attribute_form.thtml');
        }

        $id = $this->attr_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_PP['edit'] . ': ' . $this->attr_value);
            $T->set_var('attr_id', $id);
        } else {
            $retval = COM_startBlock($LANG_PP['new_option']);
            $T->set_var('attr_id', '');
        }

        $T->set_var(array(
            'action_url'    => PAYPAL_ADMIN_URL,
            'pi_url'        => PAYPAL_URL,
            'doc_url'       => PAYPAL_getDocURL('attribute_form',
                                            $_CONF['language']),
            'attr_value'    => $this->attr_value,
            'attr_price'    => $this->attr_price,
            'product_select' => COM_optionList($_TABLES['paypal.products'],
                    'id,name', $this->item_id),
            'option_group_select' => COM_optionList(
                        $_TABLES['paypal.prod_attr'],
                        'DISTINCT attr_name,attr_name',
                        $this->attr_name, 1),
            'orderby'       => $this->orderby,
            'ena_chk'       => $this->enabled == 1 ? ' checked="checked"' : '',
        ) );

        $retval .= $T->parse('output', 'attrform');
        $retval .= COM_endBlock();
        return $retval;

    }   // function Edit()


    /**
    *   Sets a boolean field to the specified value.
    *
    *   @param  integer $oldvalue   Original value of field
    *   @param  integer $varname    Name of field to change
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    private function _toggle($oldvalue, $varname, $id=0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) {
            if (is_object($this))
                $id = $this->id;
            else
                return;
        }

        // If it's still an invalid ID, return the old value
        if ($id < 1)
            return $oldvalue;

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['paypal.prod_attr']}
                SET $varname=$newvalue
                WHERE attr_id=$id";
        //echo $sql;die;
        DB_query($sql);

        return $newvalue;
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @uses   _toggle()
    *   @param  integer $oldvalue   Original field value
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    public function toggleEnabled($oldvalue, $id=0)
    {
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $id = (int)$id;
        if ($id == 0) {
            if (is_object($this))
                $id = $this->attr_id;
            else
                return $oldvalue;
        }
        return Attribute::_toggle($oldvalue, 'enabled', $id);
    }


    /**
    *   Add an error message to the Errors array.  Also could be used to
    *   log certain errors or perform other actions.
    *
    *   @param  string  $msg    Error message to append
    */
    public function AddError($msg)
    {
        $this->Errors[] = $msg;
    }


    /**
    *   Create a formatted display-ready version of the error messages.
    *
    *   @return string      Formatted error messages.
    */
    public function PrintErrors()
    {
        $retval = '';

        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }


    /**
    *   Reorder all option items
    */
    public function ReOrder()
    {
        global $_TABLES;

        $sql = "SELECT attr_id, orderby
                FROM {$_TABLES['paypal.prod_attr']}
                WHERE item_id = '" . $this->item_id . "'
                AND attr_name = '" . DB_escapeString($this->attr_name) . "'
                ORDER BY orderby ASC;";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        while ($A = DB_fetchArray($result, false)) {

            if ($A['orderby'] != $order) {  // only update incorrect ones
                $sql = "UPDATE {$_TABLES['paypal.prod_attr']}
                    SET orderby = '$order'
                    WHERE attr_id = '{$A['attr_id']}'";
                DB_query($sql);
            }
            $order += $stepNumber;
        }

    }

}   // class Attribute

?>

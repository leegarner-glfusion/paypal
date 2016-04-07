<?php
/**
*   Class to manage product categories
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.4.6
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Class for categories
*   @package paypal
*/
class Category
{
    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    var $properties;

    /** Indicate whether the current user is an administrator
    *   @var boolean */
    var $isAdmin;

    var $isNew;

    //var $button_types = array();

    /** Array of error messages, to be accessible by the calling routines.
     *  @var array */
    var $Errors = array();


    /**
     *  Constructor.
     *  Reads in the specified class, if $id is set.  If $id is zero, 
     *  then a new entry is being created.
     *
     *  @param integer $id Optional type ID
     */
    public function __construct($id=0)
    {
        global $_USER, $_VARS;

        $this->properties = array();
        $this->isNew = true;

        $this->cat_id = $id;
        $this->parent_id = 0;
        $this->cat_name = '';
        $this->description = '';
        $this->grp_access = 13;
        $this->image = '';
        $this->enabled = 1;

        if ($this->cat_id > 0) {
            if (!$this->Read()) {
                $this->cat_id = 0;
            }
        }

        $this->isAdmin = SEC_hasRights('paypal.admin') ? 1 : 0;
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    public function __set($var, $value)
    {
        switch ($var) {
        case 'cat_id':
        case 'parent_id':
        case 'grp_access':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'cat_name':
        case 'description':
        case 'image':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
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
    *   Sets all variables to the matching values from $rows
    *
    *   @param  array   $row    Array of values, from DB or $_POST
    *   @param  boolean $fromDB True if read from DB, false if from form
    */
    public function SetVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->cat_id = $row['cat_id'];
        $this->parent_id = $row['parent_id'];
        $this->description = $row['description'];
        $this->enabled = $row['enabled'];
        $this->cat_name = $row['cat_name'];
        $this->keywords = $row['keywords'];
        $this->image = $row['image'];
        $this->grp_access = $row['grp_access'];
    }


    /**
     *  Read a specific record and populate the local values.
     *
     *  @param  integer $id Optional ID.  Current ID is used if zero.
     *  @return boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->cat_id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return;
        }

        $result = DB_query("SELECT * 
                    FROM {$_TABLES['paypal.categories']} 
                    WHERE cat_id='$id'");
        if (!$result || DB_numRows($result != 1)) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            $this->isNew = false;
            return true;
        }
    }


    /**
     *  Save the current values to the database.
     *
     *  @param  array   $A      Optional array of values from $_POST
     *  @return boolean         True if no errors, False otherwise
     */
    public function Save($A = array())
    {
        global $_TABLES, $_PP_CONF;

        if (is_array($A)) {
            $this->SetVars($A);
        }

        // Handle image uploads.
        // We don't want to delete the existing image if one isn't 
        // uploaded, we should leave it unchanged.  So we'll first 
        // retrieve the existing image filename, if any.
        if (!$this->isNew) {
            $img_filename = DB_getItem($_TABLES['paypal.categories'], 
                        'image', "cat_id='" . $this->cat_id . "'");
        } else {
            // New entry, assume no image
            $img_filename = '';
        }
        if (is_uploaded_file($_FILES['imagefile']['tmp_name'])) {
            $img_filename =  rand(100,999) .  "_" . 
                     COM_sanitizeFilename($_FILES['imagefile']['name'], true);
            $status = IMG_resizeImage($_FILES['imagefile']['tmp_name'],
                    $_PP_CONF['catimgpath']."/$img_filename",
                    $_PP_CONF['max_thumb_size'], $_PP_CONF['max_thumb_size'],
                    '', true);
            //if (!@move_uploaded_file($_FILES['imagefile']['tmp_name'],
            //                $_PP_CONF['catimgpath']."/$img_filename")) {
            if ($status[0] == false) {
                $this->AddError('Error Moving Image');
            } else {
                // If a new image was uploaded, and this is an existing category,
                // then delete the old image, if any.  The DB still has the old 
                // filename at this point.
                if (!$this->isNew) {
                    $this->DeleteImage();
                }
            }
        }
        $this->image = $img_filename;

        // Insert or update the record, as appropriate, as long as a
        // previous error didn't occur.
        if (empty($this->Errors)) {
            if ($this->isNew) {
                $sql1 = "INSERT INTO {$_TABLES['paypal.categories']} SET ";
                $sql3 = '';
            } else {
                $sql1 = "UPDATE {$_TABLES['paypal.categories']} SET ";
                $sql3 = " WHERE cat_id='{$this->cat_id}'";
            }
            $sql2 = "parent_id='" . $this->parent_id . "',
                cat_name='" . DB_escapeString($this->cat_name) . "',
                description='" . DB_escapeString($this->description) . "',
                enabled='{$this->enabled}',
                grp_access ='{$this->grp_access}',
                image='" . DB_escapeString($this->image) . "'";
            $sql = $sql1 . $sql2 . $sql3;

            DB_query($sql, 1);
            if (!DB_error()) {
                if ($this->isNew) {
                    $this->cat_id = DB_insertID();
                }
            } else {
                $this->AddError('Failed to insert or update record');
            }
        }

        if (empty($this->Errors)) {
            return true;
        } else {
            return false;
        }

    }   // function Save()


    /**
     *  Delete the current category record from the database
     */
    public function Delete()
    {
        global $_TABLES, $_PP_CONF;

        if ($this->cat_id <= 0)
            return false;

        $this->DeleteImage();

        DB_delete($_TABLES['paypal.categories'], 'cat_id', $this->cat_id);

        $this->cat_id = 0;
        return true;
    }


    /**
    *   Deletes a single image from disk.
    *   Only needs the $img_id value, so this function may be called as a
    *   standalone function.
    *
    *   @param  integer $img_id     DB ID of image to delete
    */
    public function DeleteImage()
    {
        global $_TABLES, $_PP_CONF;

        $filename = $this->image;
        if (is_file("{$_PP_CONF['catimgpath']}/{$filename}")) {
            @unlink("{$_PP_CONF['catimgpath']}/{$filename}");
        }

        DB_query("UPDATE {$_TABLES['paypal.categories']}
                SET image=''
                WHERE cat_id='" . $this->cat_id . "'");
        $this->image = '';
    }


    /**
     *  Determines if the current record is valid.
     *
     *  @return boolean     True if ok, False when first test fails.
     */
    public function isValidRecord()
    {
        // Check that basic required fields are filled in
        if ($this->cat_name == '') {
            return false;
        }

        return true;
    }


    /**
     *  Creates the edit form.
     *
     *  @param  integer $id Optional ID, current record used if zero
     *  @return string      HTML for edit form
     */
    public function showForm()
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP;

        $T = new Template(PAYPAL_PI_PATH . '/templates');
        $T->set_file(array('category' => 'category_form.thtml'));

        $id = $this->cat_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            //if (!$this->Read($id)) {
            //    return PAYPAL_errorMessage($LANG_PP['invalid_category_id'], 'info');
            //}
            //$id = $this->cat_id;
            $retval = COM_startBlock($LANG_PP['edit'] . ': ' . $this->cat_name);
            $T->set_var('cat_id', $id);
            $not = 'NOT';
            $items = $id;
        } else {
            //$id = $this->cat_id;
            $retval = COM_startBlock($LANG_PP['create_category']);
            $T->set_var('cat_id', '');
            $not = '';
            $items = '';
        }

        $T->set_var(array(
            'action_url'    => PAYPAL_ADMIN_URL,
            'pi_url'        => PAYPAL_URL,
            'cat_name'      => $this->cat_name,
            'description'   => $this->description,
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'parent_sel'    => PAYPAL_recurseCats(
                                    'PAYPAL_callbackCatOptionList',
                                    $this->parent_id, 0, '', 
                                    $not, $items),
            'group_sel'     => SEC_getGroupDropdown($this->grp_access, 3, 'grp_access'),
        ) );

        if ($this->image != '') {
            $T->set_var('img_url', PAYPAL_PI_URL . '/images/categories/' . 
                $this->image);
        }

        if (!$this->isUsed()) {
            $T->set_var('can_delete', 'true');
        }

        /*
        // Might want this later to set default buttons per category
        $T->set_block('product', 'BtnRow', 'BRow');
        foreach ($LANG_PP['buttons'] as $key=>$value) {
            $T->set_var(array(
                'btn_type'  => $key,
                'btn_chk'   => isset($this->buttons[$key]) ? 
                                'checked="checked"' : '',
                'btn_name'  => $value,
            ));
            $T->parse('BRow', 'BtnRow', true);
        }*/

        if ($this->image != '') {
            $T->set_var('img_url', 
                    PAYPAL_URL . '/images/categories/' . $this->image);
            $T->set_var('del_img_url', 
                    PAYPAL_ADMIN_URL . '/index.php?action=delete_img&amp;img_id=' . 
                        $prow['img_id']. '&amp;id=' . $this->id);
        }

        $retval .= $T->parse('output', 'category');

        @setcookie($_CONF['cookie_name'].'fckeditor', 
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200, $_CONF['cookie_path'],
                $_CONF['cookiedomain'], $_CONF['cookiesecure']);

        $retval .= COM_endBlock();
        return $retval;

    }   // function showForm()


    /**
     *  Sets the "enabled" field to the specified value.
     *
     *  @param  integer $id ID number of element to modify
     *  @param  integer $value New value to set
     *  @return         New value, or old value upon failure
     */
    function _toggle($oldvalue, $varname, $id=0)
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

        $sql = "UPDATE {$_TABLES['paypal.categories']}
                SET $varname=$newvalue
                WHERE cat_id=$id";
        //echo $sql;die;
        DB_query($sql);

        return $newvalue;
    }


    /**
     *  Sets the "enabled" field to the specified value.
     *
     *  @param  integer $id ID number of element to modify
     *  @param  integer $value New value to set
     *  @return         New value, or old value upon failure
     */
    function toggleEnabled($oldvalue, $id=0)
    {
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $id = (int)$id;
        if ($id == 0) {
            if (is_object($this))
                $id = $this->cat_id;
            else
                return $oldvalue;
        }
        return Category::_toggle($oldvalue, 'enabled', $id);
    }


    /**
     *  Determine if this product is mentioned in any purchase records.
     *  Typically used to prevent deletion of product records that have
     *  dependencies.
     *
     *  @return boolean True if used, False if not
     */
    function isUsed($cat_id=0)
    {
        global $_TABLES;

        if ($cat_id == 0 && is_object($this)) {
            $cat_id = $this->cat_id;
        } else {
            $cat_id = (int)$cat_id;
        }

        // Check if any products are under this category
        //if (DB_count($_TABLES['paypal.prodXcat'], 'cat_id', $cat_id) > 0) {
        if (DB_count($_TABLES['paypal.products'], 'cat_id', $cat_id) > 0) {
            return true;
        }

        // Check if any categories are under this one.
        if (DB_count($_TABLES['paypal.categories'], 
                        'parent_id', $cat_id) > 0) {
            return true;
        }

        return false;

    }


    /**
    *   Add an error message to the Errors array.  Also could be used to
    *   log certain errors or perform other actions.
    *
    *   @param  string  $msg    Error message to append
    */
    function AddError($msg)
    {
        $this->Errors[] = $msg;
    }


    /**
    *   Create a formatted display-ready version of the error messages.
    *
    *   @return string      Formatted error messages.
    */
    function PrintErrors()
    {
        $retval = '';

        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }

 
}   // class Category


?>

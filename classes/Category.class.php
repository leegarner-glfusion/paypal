<?php
/**
*   Class to manage product categories
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.12
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

namespace Paypal;

/**
*   Class for categories
*   @package paypal
*/
class Category
{
    const DEF_DATE = '1900-01-01';

    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    var $properties = array();

    /** Indicate whether the current user is an administrator
    *   @var boolean */
    var $isAdmin;

    /** Indicate whether this is a new record or not
    *   @var boolean */
    var $isNew;

    /** Array of error messages, to be accessible by the calling routines.
     *  @var array */
    var $Errors = array();

    private static $tag = 'ppcat_';     // Cache tag

    /**
    *   Constructor.
    *   Reads in the specified class, if $id is set.  If $id is zero,
    *   then a new entry is being created.
    *
    *   @param integer $id Optional type ID
    */
    public function __construct($id=0)
    {
        global $_USER, $_VARS;

        $this->properties = array();
        $this->isNew = true;

        $this->cat_id = 0;
        $this->parent_id = 0;
        $this->cat_name = '';
        $this->description = '';
        $this->grp_access = 2;  // All users have access by default
        $this->image = '';
        $this->enabled = 1;
        $this->disp_name = '';
        $this->lft = 0;
        $this->rgt = 0;
        if (is_array($id)) {
            $this->SetVars($id);
        } elseif ($id > 0) {
            $this->cat_id = $id;
            if (!$this->Read()) {
                $this->cat_id = 0;
            }
        }
        $this->isAdmin = plugin_ismoderator_paypal() ? 1 : 0;
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
        case 'lft':
        case 'rgt':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'cat_name':
        case 'description':
        case 'image':
        case 'disp_name':   // display name in option list
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
        $this->grp_access = $row['grp_access'];
        $this->disp_name = isset($row['disp_name']) ? $row['disp_name'] : $row['description'];
        $this->lft = isset($row['lft']) ? $row['lft'] : 0;
        $this->rgt = isset($row['rgt']) ? $row['rgt'] : 0;
        if ($fromDB) {
            $this->image = $row['image'];
        }
    }


    /**
    *   Read a specific record and populate the local values.
    *   Caches the object for later use.
    *
    *   @param  integer $id Optional ID.  Current ID is used if zero.
    *   @return boolean     True if a record was read, False on failure
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
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            $this->isNew = false;
            Cache::set(self::_makeCacheKey($id), $this, 'categories');
            return true;
        }
    }


    /**
    *   Get a category instance.
    *   Checks cache first and creates a new object if not found.
    *
    *   @param  integer $cat_id     Category ID
    *   @return object              Category object
    */
    public static function getInstance($cat_id)
    {
        static $cats = array();
        if (!isset($cats[$cat_id])) {
            $key = self::_makeCacheKey($cat_id);
            $cats[$cat_id] = Cache::get($key);
            if (!$cats[$cat_id]) {
                $cats[$cat_id] = new self($cat_id);
            }
        }
        return $cats[$cat_id];
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
            if ($status[0] == false) {
                $this->AddError('Error Moving Image');
            } else {
                // If a new image was uploaded, and this is an existing
                // category, then delete the old image file, if any.
                // The DB still has the old filename at this point.
                if (!$this->isNew) {
                    $this->deleteImage(false);
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
            //echo $sql;die;
            DB_query($sql);
            if (!DB_error()) {
                if ($this->isNew) {
                    $this->cat_id = DB_insertID();
                }
                if (isset($_POST['old_parent']) && $_POST['old_parent'] != $this->parent_id) {
                    self::rebuildTree();
                }
                /*if (isset($_POST['old_grp']) && $_POST['old_grp'] > 0 &&
                        $_POST['old_grp'] != $this->grp_access) {
                    $this->_propagatePerms($_POST['old_grp']);
                }*/
                Cache::clear('categories');
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
    *   Propagate permissions to sub-categories
    *
    */
    private function _propagatePerms($grp_id)
    {
        global $_TABLES;

        if ($grp_id == $this->grp_access) return;   // nothing to do

        $c = self::getTree($this->cat_id);
        $upd_cats = array();
        foreach ($c as $cat) {
            if ($cat->cat_id == $this->cat_id) continue; // already saved
            $upd_cats[] = $cat->cat_id;
        }
        if (!empty($upd_cats)) {
            $upd_cats = implode(',', $upd_cats);
            $sql = "UPDATE {$_TABLES['paypal.categories']}
                    SET grp_access = {$this->grp_access}
                    WHERE cat_id IN ($upd_cats)";
            DB_query($sql);
        }
    }


    /**
     *  Delete the current category record from the database
     */
    public function Delete()
    {
        global $_TABLES, $_PP_CONF;

        if ($this->cat_id <= 1)
            return false;

        $this->deleteImage(false);
        DB_delete($_TABLES['paypal.categories'], 'cat_id', $this->cat_id);
        PLG_itemDeleted($this->cat_id, 'paypal_category');
        Cache::clear('categories');
        $this->cat_id = 0;
        return true;
    }


    /**
    *   Deletes a single image from disk.
    *   Only needs the $img_id value, so this function may be called as a
    *   standalone function.
    *   $del_db is used to save a DB call if this is called from Save().
    *
    *   @param  boolean $del_db     True to update the database.
    */
    public function deleteImage($del_db = true)
    {
        global $_TABLES, $_PP_CONF;

        $filename = $this->image;
        if (is_file("{$_PP_CONF['catimgpath']}/{$filename}")) {
            @unlink("{$_PP_CONF['catimgpath']}/{$filename}");
        }

        if ($del_db) {
            DB_query("UPDATE {$_TABLES['paypal.categories']}
                    SET image=''
                    WHERE cat_id='" . $this->cat_id . "'");
        }
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
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $_SYSTEM;

        $T = PP_getTemplate('category_form', 'category');
        $id = $this->cat_id;

        // If we have a nonzero category ID, then we edit the existing record.
        // Otherwise, we're creating a new item.  Also set the $not and $items
        // values to be used in the parent category selection accordingly.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_PP['edit'] . ': ' . $this->cat_name);
            $T->set_var('cat_id', $id);
            //$not = 'NOT';
            //$items = $id;
        } else {
            $retval = COM_startBlock($LANG_PP['create_category']);
            $T->set_var('cat_id', '');
            //$not = '';
            //$items = '';
        }

        // If this is the root category, don't display the option list.
        if ($this->cat_id > 0 && $this->parent_id == 0) {
            $T->set_var('parent_sel', false);
        } else {
            $T->set_var('parent_sel', self::optionList($this->parent_id, $this->cat_id));
        }
        $T->set_var(array(
            'action_url'    => PAYPAL_ADMIN_URL,
            'pi_url'        => PAYPAL_URL,
            'cat_name'      => $this->cat_name,
            'description'   => $this->description,
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'old_parent'    => $this->parent_id,
            'old_grp'       => $this->grp_access,
            'group_sel'     => SEC_getGroupDropdown($this->grp_access, 3, 'grp_access'),
            'doc_url'       => PAYPAL_getDocURL('category_form'),
            'iconset'   => $_PP_CONF['_iconset'],
        ) );

        if ($this->image != '') {
            $T->set_var('img_url', PAYPAL_URL . '/images/categories/' .
                $this->image);
        }

        if (!self::isUsed($this->id)) {
            $T->set_var('can_delete', 'true');
        }

        // Display any time-based sales pricing for this category
        $Disc = Sales::getCategory($this->cat_id);
        if (!empty($Disc)) {
            $DT = PP_getTemplate('sales_table', 'stable');
            $DT->set_var('edit_sale_url',
                PAYPAL_ADMIN_URL . '/index.php?sales');
            $DT->set_block('stable', 'SaleList', 'SL');
            foreach ($Disc as $D) {
                if ($D->discount_type == 'amount') {
                    $amount = Currency::getInstance()->format($D->amount);
                } else {
                    $amount = $D->amount;
                }
                $DT->set_var(array(
                    'sale_start' => $D->start,
                    'sale_end'  => $D->end,
                    'sale_type' => $D->discount_type,
                    'sale_amt'  => $amount,
                ) );
                $DT->parse('SL', 'SaleList', true);
            }
            $DT->parse('output', 'stable');
            $T->set_var('sale_prices', $DT->finish($DT->get_var('output')));
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

        // If there's an image for this category, display it and offer
        // a link to delete it
        if ($this->image != '') {
            $T->set_var('img_url',
                    PAYPAL_URL . '/images/categories/' . $this->image);
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
    *   Sets a boolean field to the specified value.
    *
    *   @param  integer $oldvalue   Old value to change
    *   @param  string  $varname    Field name to change
    *   @param  integer $id ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['paypal.categories']}
                SET $varname=$newvalue
                WHERE cat_id=$id";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            COM_errorLog("Category::_toggle() SQL error: $sql", 1);
            return $oldvalue;
        } else {
            return $newvalue;
        }
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @param  integer $id ID number of element to modify
    *   @param  integer $value New value to set
    *   @return         New value, or old value upon failure
    */
    public static function toggleEnabled($oldvalue, $id=0)
    {
        $id = (int)$id;
        if ($id == 0) {
            if (is_object($this))
                $id = $this->cat_id;
            else
                return $oldvalue;
        }
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
    *   Determine if this product is mentioned in any purchase records.
    *   Typically used to prevent deletion of product records that have
    *   dependencies.
    *
    *   @return boolean True if used, False if not
    */
    public static function isUsed($cat_id=0)
    {
        global $_TABLES;

        $cat_id = (int)$cat_id;

        // Check if any products are under this category
        if (DB_count($_TABLES['paypal.products'], 'cat_id', $cat_id) > 0) {
            return true;
        }

        // Check if any categories are under this one.
        if (DB_count($_TABLES['paypal.categories'], 'parent_id', $cat_id) > 0) {
            return true;
        }

        $C = self::getRoot();
        if ($C->cat_id == $cat_id) {
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
    *   Determine if the current user has access to this category.
    *
    *   @return boolean     True if user has access, False if not
    */
    public function hasAccess()
    {
        global $_GROUPS;

        return (in_array($this->grp_access, $_GROUPS)) ? true : false;
    }


    /**
    *   Get the URL to the category image.
    *   Returns an empty string if no image defined or found.
    *
    *   @return string  URL of image
    */
    public function ImageUrl()
    {
        global $_CONF, $_PP_CONF;

        if ($this->image != '' &&
                is_file($_CONF['path_html'] . $_PP_CONF['pi_name'] .
                '/images/categories/' . $A['image'])) {
            $retval = PAYPAL_URL . '/images/categories/' . $this->image;
        } else {
            $retval = '';
        }
    }


    /**
    *   Create the breadcrumb display, with links.
    *   Creating a static breadcrumb field in the category record won't
    *   work because of the group access control. If a category is
    *   encountered that the current user can't access, it is simply
    *   skipped.
    *
    *   @param  integer $id ID of current category
    *   @return string      Location string ready for display
    */
    public static function Breadcrumbs($id)
    {
        global $_TABLES, $LANG_PP;

        $A = array();
        $location = '';

        $id = (int)$id;
        if ($id < 1) {
            return $location;
        } else {
            $parent = $id;
        }

        while (true) {
            $sql = "SELECT cat_name, cat_id, parent_id, grp_access
                FROM {$_TABLES['paypal.categories']}
                WHERE cat_id='$parent'";

            $result = DB_query($sql);
            if (!$result || DB_numRows($result) == 0)
                break;

            $row = DB_fetchArray($result, false);
            $parent = (int)$row['parent_id'];
            if (!SEC_inGroup($row['grp_access'])) {
                continue;
            }
            $A[] = '<li>' . COM_createLink($row['cat_name'],
                    PAYPAL_URL . '/index.php?category=' .
                        (int)$row['cat_id']) . '<li>';
            if ($parent == 0) {
                break;
            }
        }

        // Always add link to shop home
        //$A[] = COM_createLink($LANG_PP['home'],
        //        COM_buildURL(PAYPAL_URL . '/index.php'));
        $B = array_reverse($A);
        //$location = implode(' :: ', $B);
        return $location;
    }


    /**
    *   Helper function to create the cache key.
    *
    *   @return string  Cache key
    */
    private static function _makeCacheKey($id)
    {
        return self::$tag . $id;
    }


    /**
    *   Read all the categories into a static array.
    *
    *   @param  integer $root   Root category ID
    *   @return array           Array of category objects
    */
    public static function getTree($root=0, $prefix='&nbsp;')
    {
        global $_TABLES;

        if (!PP_isMinVersion('0.6.0')) return array();

        $between = '';
        $root = (int)$root;
        $p = $prefix == '&nbsp;' ? 'x_' : $prefix . '_';
        $cache_key = self::_makeCacheKey('cat_tree_' . $p . (string)$root);
        $All = Cache::get($cache_key);
        if (!$All) {        // not found in cache, build the tree
            if ($root > 0) {
                $result = DB_query("SELECT lft, rgt FROM {$_TABLES['paypal.categories']}
                            WHERE cat_id = $root");
                $row = DB_fetchArray($result, false);
                $between = ' AND parent.lft BETWEEN ' . (int)$row['lft'] .
                        ' AND ' . (int)$row['rgt'];
            }
            $prefix = DB_escapeString($prefix);
            $sql = "SELECT node.*, CONCAT( REPEAT( '$prefix', (COUNT(parent.cat_name) - 1) ), node.cat_name) AS disp_name
                FROM {$_TABLES['paypal.categories']} AS node,
                    {$_TABLES['paypal.categories']} AS parent
                WHERE node.lft BETWEEN parent.lft AND parent.rgt
                $between
                GROUP BY node.cat_name
                ORDER BY node.lft";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $All[$A['cat_id']] = new self($A);
            }
            Cache::set($cache_key, $All, 'categories');
        }
        return $All;
    }


    /**
    *   Get the full path to a category, optionally including sub-categories
    *
    *   @param  integer $cat_id     Category ID
    *   @param  boolean $incl_sub   True to include sub-categories
    *   @return array       Array of category objects
    */
    public static function getPath($cat_id, $incl_sub = true)
    {
        $key = 'cat_path_' . $cat_id . '_' . (int)$incl_sub;
        $path = Cache::get($key);
        if (!$path) {
            $cats = self::getTree();    // need the full tree to find parents
            $path = array();

            // if node doesn't exist, return. Don't bother setting cache
            if (!isset($cats[$cat_id])) return $path;

            $Cat = $cats[$cat_id];      // save info for the current node
            foreach ($cats as $id=>$C) {
                if ($C->lft < $Cat->lft && $C->rgt > $Cat->rgt) {
                    $path[$C->cat_id] = $C;
                }
            }

            // Now append the node, or the subtree
            if ($incl_sub) {
                $subtree = self::getTree($cat_id);
                foreach ($subtree as $id=>$C) {
                    $path[$C->cat_id] = $C;
                }
            } else {
                $path[$Cat->cat_id] = $Cat;
            }
            Cache::set($key, $path, 'categories');
        }
        return $path;
    }


    /**
    *   Get the options for a selection list.
    *   Used in the product form and to select a parent category.
    *   $exclude indicates a category to disable, to prevent selecting a
    *   category as its own parent.
    *
    *   @uses   self::getTree()
    *   @param  integer $sel        Selected category ID
    *   @param  integer $exclude    Category to disable in the list
    *   @return string          Option elements for Select
    */
    public static function optionList($sel = 0, $exclude = 0)
    {
        $Cats = self::getTree(0, '-');
        $opts = '';
        foreach ($Cats as $Cat) {
            $disabled = $Cat->cat_id == $exclude ? 'disabled="disabled"' : '';
            $selected = $Cat->cat_id == $sel ? 'selected="selected"' : '';
            $opts .= '<option value="' . $Cat->cat_id . '" ' . $disabled .
                    ' ' . $selected . '>' . $Cat->disp_name . '</option>' . LB;
        }
        return $opts;
    }


    /**
    *   Get the root category.
    *   Depending on how Paypal was installed or updated this might not be #1.
    *
    *   @return mixed   Category object
    */
    public static function getRoot()
    {
        global $_TABLES;

        $parent = (int)DB_getItem($_TABLES['paypal.categories'], 'cat_id',
                'parent_id = 0');
        return self::getInstance($parent);
    }


    /**
    *   Rebuild the MPT tree starting at a given parent and "left" value
    *
    *   @param  integer $parent     Starting category ID
    *   @param  integer $left       Left value of the given category
    *   @return integer         New Right value (only when called recursively)
    */
    public static function rebuildTree($parent = 0, $left = 1)
    {
        global $_TABLES;

        // If parent is undefined, get the root category ID
        if ($parent == 0) {
            $parent = self::getRoot();
            $parent = $parent->cat_id;
        }

        // the right value of this node is the left value + 1
        $right = $left + 1;

        // get all children of this node
        $sql = "SELECT cat_id FROM {$_TABLES['paypal.categories']}
                WHERE parent_id ='$parent'";
        $result = DB_query($sql);
        while ($row = DB_fetchArray($result, false)) {
            // recursive execution of this function for each
            // child of this node
            // $right is the current right value, which is
            // incremented by the rebuild_tree function
            $right = self::rebuildTree($row['cat_id'], $right);
        }

        // we've got the left value, and now that we've processed
        // the children of this node we also know the right value
        $sql1 = "UPDATE {$_TABLES['paypal.categories']}
                SET lft = '$left', rgt = '$right'
                WHERE cat_id = '$parent'";
        DB_query($sql1);

        // return the right value of this node + 1
        return $right + 1;
    }

}   // class Category

?>

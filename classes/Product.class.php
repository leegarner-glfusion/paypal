<?php
/**
*   Class to manage products
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.7
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for product
*   @package paypal
*/
class Product
{
    const MIN_DATE = '1900-01-01';
    const MAX_DATE = '9999-12-31';

    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    protected $properties;

    /** Product Attributes
    *   @var array */
    public $options;

    /** Indicate whether the current user is an administrator
    *   @var boolean */
    public $isAdmin;

    public $isNew;

    /** Array of error messages
     *  @var mixed */
    var $Errors = array();

    //var $buttons = array();
    public $buttons;
    protected $special_fields = array();
    public $Cat;        // Category object
    public $override_price = false;
    private $_uid = 0;  // user id, for pricing
    private $_view = 'detail';  // type of button to create (list or detail)

    /**
     *  Constructor.
     *  Reads in the specified class, if $id is set.  If $id is zero,
     *  then a new entry is being created.
     *
     *  @param integer $id Optional type ID
     */
    public function __construct($id=0)
    {
        global $_PP_CONF;

        $this->properties = array();
        $this->isNew = true;
        $this->pi_name = $_PP_CONF['pi_name'];
        $this->btn_text = '';

        if (is_array($id)) {
            $this->setVars($id, true);
            $this->isNew = false;
            $this->Cat = Category::getInstance($this->cat_id);
        } elseif ($id == 0) {
            $this->item_id = '';
            $this->id = 0;
            $this->name = '';
            $this->cat_id = '';
            $this->short_description = '';
            $this->description = '';
            $this->price = 0;
            $this->prod_type = PP_PROD_VIRTUAL;
            $this->weight = 0;
            $this->file = '';
            $this->expiration = $_PP_CONF['def_expiration'];
            $this->enabled = $_PP_CONF['def_enabled'];
            $this->featured = $_PP_CONF['def_featured'];
            $this->taxable = $_PP_CONF['def_taxable'];
            $this->dt_add = PAYPAL_now()->toMySQL();
            $this->views = 0;
            $this->rating = 0;
            $this->votes = 0;
            $this->shipping_type = 0;
            $this->shipping_amt = 0;
            $this->show_random = 1;
            $this->show_popular = 1;
            $this->keywords = '';
            $this->comments_enabled = $_PP_CONF['ena_comments'] == 1 ?
                    PP_COMMENTS_ENABLED : PP_COMMENTS_DISABLED;
            $this->rating_enabled = $_PP_CONF['ena_ratings'] == 1 ? 1 : 0;
            $this->track_onhand = $_PP_CONF['def_track_onhand'];
            $this->oversell = $_PP_CONF['def_oversell'];
            $this->qty_discounts = array();
            $this->custom = '';
            $this->Cat = NULL;
        } else {
            $this->id = $id;
            if (!$this->Read()) {
                $this->id = 0;
            }
        }
        $this->isAdmin = plugin_ismoderator_paypal() ? 1 : 0;
    }


    /**
    *   Gets an instance of a product object.
    *   Figures out the type of product (plugin, catalog, etc.)
    *   and instantiates an object if necessary.
    *   $A can be a single item id or an array (DB record) of values.
    *
    *   @param  mixed   $A      Single item ID or array of values
    *   @param  array   $mods   Optional array of product modifiers
    *   @return object          Product Object
    */
    public static function getInstance($A, $mods=array())
    {
        global $_TABLES;
        static $P = array();    // cache for internal products

        if (is_array($A)) {
            // A complete product record
            $id = isset($A['id']) ? $A['id'] : NULL;
        } else {
            // A single product ID
            $id = $A;
        }
        if (!$id) {
            // Missing product ID
            return NULL;
        }

        $item = explode('|', $id);
        if (self::isPluginItem($item[0])) {
            // Product provided by another plugin
            return new PluginProduct($item[0], $mods);
        } else {
            if (!array_key_exists($id, $P)) {
                // Product internal to this plugin
                if (!is_array($A)) {
                    $cache_key = self::_makeCacheKey($item[0]);
                    $A = Cache::get($cache_key);
                    if (!$A) {
                        // If not found in cache
                        $sql = "SELECT * FROM {$_TABLES['paypal.products']}
                                WHERE id = " . (int)$item[0];
                        $res = DB_query($sql);
                        $A = DB_fetchArray($res, false);
                        if (isset($A['id'])) {
                            Cache::set($cache_key, $A, array('product', $A['id']));
                        }
                    }
                }
                if (isset($A['prod_type']) && $A['prod_type'] == PP_PROD_COUPON) {
                    $P[$id] = new Coupon($A);
                } else {
                    $P[$id] = new self($A);
                }
                if ($P[$id]->hasAccess()) {
                    $P[$id]->loadAttributes();
                }
            }
            return $P[$id];
        }
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
        case 'views':
        case 'votes':
        case 'prod_type':
        case 'cat_id':
        case 'shipping_type':
        case 'comments_enabled':
        case 'onhand':
        case 'oversell':
        case 'expiration':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'price':
        case 'rating':
        case 'weight':
        case 'shipping_amt':
        case '_act_price':      // actual price, sale or nonsale
        case '_orig_price':     // original price
            // Float values
            $this->properties[$var] = (float)$value;
            break;

        case 'avail_end':
            // available to end of time by default
            if (empty($value) || $value == '0000-00-00')
                $value = self::MAX_DATE;
            $this->properties[$var] = trim($value);
            break;

        case 'avail_beg':
            // sale dates and beginning availability default to 0000-00-00
            if (empty($value)) $value = self::MIN_DATE;
            $this->properties[$var] = trim($value);
            break;

        case 'dt_add':
        case 'description':
        case 'short_description':
        case 'name':
        case 'file':
        case 'keywords':
        case 'btn_type':
        case 'item_id':
        case 'btn_text':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
        case 'featured':
        case 'taxable':
        case 'show_random':
        case 'show_popular':
        case 'rating_enabled':
        case 'track_onhand':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        case 'categories':
            // Saving the category, or category list
            if (!is_array($value)) {
                $value = array($value);
            }
            $this->properties[$var] = $value;
            break;

        case 'qty_discounts':
            if (!is_array($value)) {
                $value = @unserialize($value);
                if ($value === false) $value = array();
            }
            ksort($value);
            $this->properties[$var] = $value;
            break;

        case 'id':
            // Item ID may be a string if this is a plugin,
            // otherwise sanitize as an integer.
            if (!self::isPluginItem($value)) {
                $value = (int)$value;
            }
        default:
            // Other value types (array?). Save it as-is.
            $this->properties[$var] = $value;
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
     *  Sets all variables to the matching values from $rows.
     *
     *  @param  array   $row        Array of values, from DB or $_POST
     *  @param  boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->id = $row['id'];
        $this->item_id = $row['id'];
        $this->description = $row['description'];
        $this->enabled = isset($row['enabled']) ? $row['enabled'] : 0;
        $this->featured = isset($row['featured']) ? $row['featured'] : 0;
        $this->name = $row['name'];
        $this->cat_id = $row['cat_id'];
        $this->short_description = $row['short_description'];
        $this->price = $row['price'];
        $this->file = $row['file'];
        $this->expiration = $row['expiration'];
        $this->keywords = $row['keywords'];
        $this->prod_type = isset($row['prod_type']) ? $row['prod_type'] : 0;
        $this->weight = $row['weight'];
        $this->taxable = isset($row['taxable']) ? $row['taxable'] : 0;
        $this->shipping_type = $row['shipping_type'];
        $this->shipping_amt = $row['shipping_amt'];
        $this->show_random = isset($row['show_random']) ? $row['show_random'] : 0;
        $this->show_popular = isset($row['show_popular']) ? $row['show_popular'] : 0;
        $this->track_onhand = isset($row['track_onhand']) ? $row['track_onhand'] : 0;
        $this->onhand = $row['onhand'];
        $this->oversell = isset($row['oversell']) ? $row['oversell'] : 0;
        $this->custom = $row['custom'];
        $this->avail_beg = $row['avail_beg'];
        $this->avail_end = $row['avail_end'];

        // Get the quantity discount table. If coming from a form,
        // there will be two array variables for qty and discount percent.
        // From the DB there's a single serialized string
        if ($fromDB) {
            // unserialization happens in __set()
            $this->qty_discounts = $row['qty_discounts'];
            $this->dt_add = $row['dt_add'];
        } else {
            $this->dt_add = PAYPAL_now()->toMySQL();
            $qty_discounts = array();
            for ($i = 0; $i < count($row['disc_qty']); $i++) {
                $disc_qty = (int)$row['disc_qty'][$i];
                if ($disc_qty < 1) continue;
                if (isset($row['disc_amt'][$i])) {
                    $qty_discounts[$disc_qty] = abs($row['disc_amt'][$i]);
                }
            }
            $this->qty_discounts = $qty_discounts;
        }

        if (isset($row['categories'])) {
            $this->categories = $row['categories'];
        } else {
            $this->categories = array();
        }

        $this->votes = isset($row['votes']) ? $row['votes'] : 0;
        $this->rating = isset($row['rating']) ? $row['rating'] : 0;
        $this->comments_enabled = $row['comments_enabled'];
        $this->rating_enabled = isset($row['rating_enabled']) ? $row['rating_enabled'] : 0;
        $this->btn_type = $row['buttons'];
        if ($fromDB) {
            $this->views = $row['views'];
        }
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
        if ($id == 0) $id = $this->id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        $cache_key = self::_makeCacheKey($id);
        $row = Cache::get($cache_key);
        if ($row === NULL) {
            $result = DB_query("SELECT *
                        FROM {$_TABLES['paypal.products']}
                        WHERE id='$id'");
            if (!$result || DB_numRows($result) != 1) {
                return false;
            } else {
                $row = DB_fetchArray($result, false);
            }
        }
        if (!empty($row)) {
            $this->setVars($row, true);
            $this->isNew = false;
            $this->loadAttributes();
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Load the product attributs into the options array
    */
    protected function loadAttributes()
    {
        global $_TABLES;

        $cache_key = 'prod_attr_' . $this->id;
        $this->options = Cache::get($cache_key);
        if ($this->options === NULL) {
            $sql = "SELECT * FROM {$_TABLES['paypal.prod_attr']}
                    WHERE item_id = '{$this->id}' AND enabled = 1
                    ORDER BY attr_name, orderby ASC";
            $result = DB_query($sql);
            $this->options = array();
            while ($A = DB_fetchArray($result, false)) {
                $this->options[$A['attr_id']] = array(
                    'attr_name' => $A['attr_name'],
                    'attr_value' => $A['attr_value'],
                    'attr_price' => $A['attr_price'],
                );
            }
            Cache::set($cache_key, $this->options, array('product', $this->id));
        }
    }


    /**
     *  Save the current values to the database.
     *  Appends error messages to the $Errors property.
     *
     *  @param  array   $A      Optional array of values from $_POST
     *  @return boolean         True if no errors, False otherwise
     */
    public function Save($A = '')
    {
        global $_TABLES, $_PP_CONF;

        if (is_array($A)) {
            $this->setVars($A);
        }

        // Zero out the shipping amount if a non-fixed value is chosen
        if ($this->shipping_type < 2) {
            $this->shipping_amt = 0;
        }

        // Handle file uploads.  This is done first so we know whether
        // there is a valid filename for a download product
        // No weight or shipping for downloads
        if (!empty($_FILES['uploadfile']['tmp_name'])) {
            $F = new File('uploadfile');
            $filename = $F->uploadFiles();
            if ($F->areErrors() > 0) {
                $this->Errors[] = $F->printErrors(true);
            } elseif ($filename != '') {
                $this->file = $filename;
            }
            PAYPAL_debug('Uploaded file: ' . $this->file);
        }

        // For downloadable files, physical product options don't apply
        if ($this->prod_type == PP_PROD_DOWNLOAD) {
            $this->weight = 0;
            $this->shipping_type = 0;
            $this->shipping_amt = 0;
        }

        // Serialize the quantity discount array
        $qty_discounts = $this->qty_discounts;
        if (!is_array($qty_discounts)) $qty_discounts = array();
        $qty_discounts = DB_escapeString(@serialize($qty_discounts));

        // Insert or update the record, as appropriate
        if ($this->id > 0) {
            PAYPAL_debug('Preparing to update product id ' . $this->id);
            $sql1 = "UPDATE {$_TABLES['paypal.products']} SET ";
            $sql3 = " WHERE id='{$this->id}'";
        } else {
            PAYPAL_debug('Preparing to save a new product.');
            $sql1 = "INSERT INTO {$_TABLES['paypal.products']} SET
                dt_add = UTC_TIMESTAMP(), ";
            $sql3 = '';
        }
        //$options = DB_escapeString(@serialize($this->options));
        $sql2 = "name='" . DB_escapeString($this->name) . "',
                cat_id='" . (int)$this->cat_id . "',
                short_description='" . DB_escapeString($this->short_description) . "',
                description='" . DB_escapeString($this->description) . "',
                keywords='" . DB_escapeString($this->keywords) . "',
                price='" . number_format($this->price, 2, '.', '') . "',
                prod_type='" . (int)$this->prod_type. "',
                weight='" . number_format($this->weight, 2, '.', '') . "',
                file='" . DB_escapeString($this->file) . "',
                expiration='" . (int)$this->expiration. "',
                enabled='" . (int)$this->enabled. "',
                featured='" . (int)$this->featured. "',
                views='" . (int)$this->views. "',
                taxable='" . (int)$this->taxable . "',
                shipping_type='" . (int)$this->shipping_type . "',
                shipping_amt='" . number_format($this->shipping_amt, 2, '.', '') . "',
                comments_enabled='" . (int)$this->comments_enabled . "',
                rating_enabled='" . (int)$this->rating_enabled . "',
                show_random='" . (int)$this->show_random . "',
                show_popular='" . (int)$this->show_popular . "',
                onhand='{$this->onhand}',
                track_onhand='{$this->track_onhand}',
                oversell = '{$this->oversell}',
                qty_discounts = '{$qty_discounts}',
                custom='" . DB_escapeString($this->custom) . "',
                avail_beg='" . DB_escapeString($this->avail_beg) . "',
                avail_end='" . DB_escapeString($this->avail_end) . "',
                buttons= '" . DB_escapeString($this->btn_type) . "'";
                //options='$options',
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            if ($this->isNew) {
                $this->id = DB_insertID();
            }
            $status = true;
        } else {
            COM_errorLog("Paypal- SQL error in Product::Save: $sql", 1);
            $status = false;
        }

        Cache::delete('prod_attr_' . $this->id);
        Cache::clear('product');

        //PAYPAL_debug('Status of last update: ' . print_r($status,true));
        if ($status) {
            // Handle image uploads.  This is done last because we need
            // the product id to name the images filenames.
            if (!empty($_FILES['images'])) {
                $U = new ProductImage($this->id, 'images');
                $U->uploadFiles();

                if ($U->areErrors() > 0) {
                    $this->Errors[] = $U->printErrors(false);
                }
            }

            // Clear the button cache
            self::deleteButtons($this->id);
        }

        if (empty($this->Errors)) {
            PAYPAL_debug('Update of product ' . $this->id . ' succeeded.');
            PLG_itemSaved($this->id, $_PP_CONF['pi_name']);
            return true;
        } else {
            PAYPAL_debug('Update of product ' . $this->id . ' failed.');
            return false;
        }
    }


    /**
    *   Delete the current product record from the database.
    *   Deletes the item, item attributes, images and buttons. Does not
    *   update the purchases or IPN log at all. Does not delete an item
    *   that has orders associated with it.
    *
    *   @uses   deleteImage()
    *   @uses   deleteButtons()
    *   @return boolean     True when deleted, False if invalid ID
    */
    public function Delete()
    {
        global $_TABLES, $_PP_CONF;

        if ($this->id <= 0 || self::isUsed($this->id))
            return false;

        // Locate and delete photos
        $sql = "SELECT img_id, filename
            FROM {$_TABLES['paypal.images']}
            WHERE product_id='". $this->id . "'";
        //echo $sql;
        $photo= DB_query($sql);
        if ($photo) {
            while ($prow = DB_fetchArray($photo, false)) {
                self::deleteImage($prow['img_id'], $prow['filename']);
            }
        }
        DB_delete($_TABLES['paypal.products'], 'id', $this->id);
        DB_delete($_TABLES['paypal.prod_attr'], 'item_id', $this->id);
        self::deleteButtons($this->id);
        PLG_itemDeleted($this->id, $_PP_CONF['pi_name']);
        $this->id = 0;
        return true;
    }


    /**
    *   Delete all buttons for a product.
    *   Called when a product is updated so the buttons will be recreated
    *   when needed.
    *
    *   @param  integer $item_id    Product ID to delete
    */
    private static function deleteButtons($item_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['paypal.buttons'], 'item_id', $item_id);
    }


    /**
    *   Deletes a single image from disk.
    *   Only needs the $img_id value, so this function may be called as a
    *   standalone function.
    *
    *   @param  integer $img_id     DB ID of image to delete
    *   @param  string  $filename   Filename, if known
    */
    public static function deleteImage($img_id, $filename='')
    {
        global $_TABLES, $_PP_CONF;

        $img_id = (int)$img_id;
        if ($img_id < 1) return;

        if ($filename == '') {
            $filename = DB_getItem($_TABLES['paypal.images'], 'filename',
                "img_id=$img_id");
        }

        if (is_file("{$_PP_CONF['image_dir']}/{$filename}"))
                unlink("{$_PP_CONF['image_dir']}/{$filename}");

        DB_delete($_TABLES['paypal.images'], 'img_id', $img_id);
    }


    /**
    *   Determines if the current record is valid.
    *   Checks various items that can't be empty or combinations that
    *   don't make sense.
    *   Accumulates all error messages in the Errors array.
    *   As of version 0.5.0, the category is allowed to be empty.
    *
    *   @deprecated
    *   @return boolean     True if ok, False when first test fails.
    */
    private function isValidRecord()
    {
        global $LANG_PP;

        // Check that basic required fields are filled in
        if ($this->name == '')
            $this->Errors[] = $LANG_PP['err_missing_name'];

        if ($this->short_description == '')
            $this->Errors[] = $LANG_PP['err_missing_desc'];

        if ($this->prod_type == PP_PROD_DOWNLOAD) {
            if ($this->file == '') {
                // Must have a file for a downloadable product
                $this->Errors[] = $LANG_PP['err_missing_file'];
            }
            if ($this->expiration < 1) {
                // Must have an expiration period for downloads
                $this->Errors[] = $LANG_PP['err_missing_exp'];
            }
        } elseif ($this->prod_type == PP_PROD_PHYSICAL &&
                $this->price < 0.01) {
            // Paypal won't accept a zero amount, so non-downloadable items
            // must have a positive price.  Use "Other Virtual" for free items.
            $this->Errors[] = $LANG_PP['err_phys_need_price'];
        }

        if (!empty($this->Errors)) {
            PAYPAL_debug('Errors encountered: ' . print_r($this->Errors,true));
            return false;
        } else {
            PAYPAL_debug('isValidRecord(): No errors');
            return true;
        }
    }


    /**
    *   Creates the product edit form.
    *
    *   Creates the form for editing a product.  If a product ID is supplied,
    *   then that product is read and becomes the current product.  If not,
    *   then the current product is edited.  If an empty product was created,
    *   then a new product is created here.
    *
    *   @uses   PAYPAL_getDocUrl()
    *   @uses   PAYPAL_errorMessage()
    *   @param  integer $id     Optional ID, current record used if zero
    *   @return string          HTML for edit form
    */
    public function showForm($id = 0)
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $LANG24, $LANG_postmodes,
                $_SYSTEM;

        $id = (int)$id;
        if ($id > 0) {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return PAYPAL_errorMessage($LANG_PP['invalid_product_id'], 'info');
            }
        }
        $id = $this->id;
        $T = PP_getTemplate('product_form', 'product');

        // Set up the wysiwyg editor, if available
        $tpl_var = $_PP_CONF['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_PP_CONF['pi_name'], $tpl_var, 'ckeditor_paypal.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_PP_CONF['pi_name'], $tpl_var, 'tinymce_paypal.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        // Add the current product ID to the form if it's an existing product.
        if ($id > 0) {
            $T->set_var('id', '<input type="hidden" name="id" value="' .
                        $this->id .'" />');
            $retval = COM_startBlock($LANG_PP['edit'] . ': ' . $this->name);

        } else {
            $T->set_var('id', '');
            $retval = COM_startBlock($LANG_PP['new_product']);

        }

        $T->set_var(array(
            //'post_options'  => $post_options,
            'name'          => htmlspecialchars($this->name, ENT_QUOTES, COM_getEncodingt()),
            'category'      => $this->cat_id,
            'short_description' => htmlspecialchars($this->short_description, ENT_QUOTES, COM_getEncodingt()),
            'description'   => htmlspecialchars($this->description, ENT_QUOTES, COM_getEncodingt()),
            'price'         => sprintf('%.2f', $this->price),
            'file'          => htmlspecialchars($this->file, ENT_QUOTES, COM_getEncodingt()),
            'expiration'    => $this->expiration,
            'pi_admin_url'  => PAYPAL_ADMIN_URL,
            'file_selection' => $this->FileSelector(),
            'keywords'      => htmlspecialchars($this->keywords, ENT_QUOTES, COM_getEncodingt()),
            'cat_select'    => Category::optionList($this->cat_id),
            'currency'      => $_PP_CONF['currency'],
            'pi_url'        => PAYPAL_URL,
            'doc_url'       => PAYPAL_getDocURL('product_form',
                                            $_CONF['language']),
            'prod_type'     => $this->prod_type,
            'weight'        => $this->weight,
            'feat_chk'      => $this->featured == 1 ? 'checked="checked"' : '',
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'tax_chk'       => $this->taxable == 1 ? 'checked="checked"' : '',
            'show_random_chk'  => $this->show_random == 1 ? 'checked="checked"' : '',
            'show_popular_chk' => $this->show_popular == 1 ?
                                    'checked="checked"' : '',
            'ship_sel_' . $this->shipping_type => 'selected="selected"',
            'shipping_type' => $this->shipping_type,
            'track_onhand'  => $this->track_onhand,
            'shipping_amt'  => sprintf('%.2f', $this->shipping_amt),
            'sel_comment_' . $this->comments_enabled =>
                                    'selected="selected"',
            'rating_chk'    => $this->rating_enabled == 1 ?
                                    'checked="checked"' : '',
            'trk_onhand_chk' => $this->track_onhand== 1 ?
                                    'checked="checked"' : '',
            'onhand'        => $this->onhand,
            "oversell_sel{$this->oversell}" => 'selected="selected"',
            'custom' => $this->custom,
            'avail_beg'     => self::_InputDtFormat($this->avail_beg),
            'avail_end'     => self::_InputDtFormat($this->avail_end),
            'iconset'       => $_PP_CONF['_iconset'],
            //'limit_availability_chk' => $this->limit_availability ? 'checked="checked"' : '',
        ) );

        // Create the button type selections. New products get the default
        // button selected, existing products get the saved button selected
        // or "none" if there is no button.
        $T->set_block('product', 'BtnRow', 'BRow');
        $have_chk = false;
        foreach ($_PP_CONF['buttons'] as $key=>$checked) {
            if ($key == $this->btn_type || ($this->isNew && $checked)) {
                $btn_chk = 'checked="checked"';
                $have_chk = true;
            } else {
                $btn_chk = '';
            }
            $T->set_var(array(
                'btn_type'  => $key,
                'btn_chk'   => $key == $this->btn_type ||
                        ($this->isNew && $checked) ? 'checked="checked"' : '',
                'btn_name'  => $LANG_PP['buttons'][$key],
            ));
            $T->parse('BRow', 'BtnRow', true);
        }
        // Set the "none" selection if nothing was already selected
        $T->set_var('none_chk', $have_chk ? '' : 'checked="checked"');

        $T->set_block('product', 'ProdTypeRadio', 'ProdType');
        foreach ($LANG_PP['prod_types'] as $value=>$text) {
            if ($value == PP_PROD_COUPON && $_PP_CONF['gc_enabled'] == 0) {
                continue;
            }
            $T->set_var(array(
                'type_val'  => $value,
                'type_txt'  => $text,
                'type_sel'  => $this->prod_type == $value ? 'selected="selected"' : '',
            ));
            $T->parse('ProdType', 'ProdTypeRadio', true);
        }

        if (!self::isUsed($this->id)) {
            $T->set_var('candelete', 'true');
        }

        // Set up the photo fields.  Use $photocount defined above.
        // If there are photos, read the $photo result.  Otherwise,
        // or if this is a new ad, just clear the photo area
        $T->set_block('product', 'PhotoRow', 'PRow');
        $i = 0;

        // Get the existing photos.  Will only have photos with an
        // existing product entry.
        $photocount = 0;
        if ($this->id != NULL) {
            $sql = "SELECT img_id, filename
                FROM {$_TABLES['paypal.images']}
                WHERE product_id='" . $this->id . "'";
            $photo = DB_query($sql);

            // save the count of photos for later use
            if ($photo) {
                $photocount = DB_numRows($photo);
            }

            // While we're checking the ID, set it as a hidden value
            // for updating this record
            $T->set_var('product_id', $this->id);
        } else {
            $T->set_var('product_id', '');
        }

        // If there are any images, retrieve and display the thumbnails.
        if ($photocount > 0) {
            while ($prow = DB_fetchArray($photo)) {
                $i++;
                $T->set_var('img_url',
                    PAYPAL_URL . "/images/products/{$prow['filename']}");
                $T->set_var('thumb_url', PAYPAL_ImageUrl($prow['filename']));
                $T->set_var('seq_no', $i);
                $T->set_var('del_img_url', PAYPAL_ADMIN_URL . '/index.php' .
                        '?delete_img=x' .
                        '&img_id=' . $prow['img_id'] .
                        '&id=' . $this->id );
                $T->parse('PRow', 'PhotoRow', true);
            }
        } else {
            $T->parse('PRow', '');
        }

        // add upload fields for unused images
        $T->set_block('product', 'UploadFld', 'UFLD');
        for ($j = $i; $j < $_PP_CONF['max_images']; $j++) {
            $T->parse('UFLD', 'UploadFld', true);
        }

        $i = 0;
        foreach ($this->qty_discounts as $qty=>$amt) {
            $T->set_var(array(
                'disc_qty' . $i => $qty,
                'disc_amt' . $i => $amt,
            ) );
            $i++;
        }

        $Disc = Sales::getProduct($this->id);
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

        $retval .= $T->parse('output', 'product');
        $retval .= COM_endBlock();
        return $retval;
    }   // function showForm()


    /**
    *   Sets a boolean field to the opposite of the supplied value
    *
    *   @param  integer $oldvalue   Old (current) value
    *   @param  string  $varname    Name of DB field to set
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        $id = (int)$id;

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 1 ? 1 : 0;
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['paypal.products']}
                SET $varname=$newvalue
                WHERE id=$id";
        //echo $sql;die;
        // Ignore SQL errors since varname is indeterminate
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Product::_toggle() SQL error: $sql", 1);
            return $oldvalue;
        } else {
            return $newvalue;
        }
    }


    /**
    *   Toggles the "enabled field
    *
    *   @uses   _toggle()
    *   @param  integer $oldvalue   Original value
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    public static function toggleEnabled($oldvalue, $id=0)
    {
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
    *   Toggles the "featured" field
    *
    *   @uses   _toggle()
    *   @param  integer $oldvalue   Original value
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    public static function toggleFeatured($oldvalue, $id=0)
    {
        return self::_toggle($oldvalue, 'featured', $id);
    }



    /**
    *   Determine if this product is mentioned in any purchase records.
    *   Typically used to prevent deletion of product records that have
    *   dependencies.
    *   Can be called as Product::isUsed($item_id)
    *
    *   @return boolean True if used, False if not
    */
    public static function isUsed($item_id = 0)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        if (DB_count($_TABLES['paypal.purchases'], 'product_id', $item_id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Display the detail page for the product.
    *
    *   @return string      HTML for the product page.
    */
    public function Detail()
    {
        global $_CONF, $_PP_CONF, $_TABLES, $LANG_PP, $_USER, $_SYSTEM;

        USES_lib_comments();

        $prod_id = $this->id;
        if ($prod_id < 1 || !$this->enabled ||!$this->isAvailable()) {
            return '';
        }

        $retval = COM_startBlock();

        // Set the template dir based on the configured template version
        $T = PP_getTemplate('product_detail_attrib', 'product',
                'detail/' . $_PP_CONF['product_tpl_ver']);
        $JT = new \Template(__DIR__ . '/../templates/detail');
        $JT->set_file('js', 'detail_js.thtml');

        $name = $this->name;
        $l_desc = PLG_replaceTags($this->description);
        $s_desc = PLG_replaceTags($this->short_description);

        // Highlight the query terms if coming from a search
        if (isset($_REQUEST['query']) && !empty($_REQUEST['query'])) {
            $name   = COM_highlightQuery($name, $_REQUEST['query']);
            $l_desc = COM_highlightQuery($l_desc, $_REQUEST['query']);
            $s_desc = COM_highlightQuery($s_desc, $_REQUEST['query']);
        }

        //$onsale = $this->isOnSale();
        $this->_act_price = $this->getSalePrice();

        $qty_disc_txt = '';
        foreach ($this->qty_discounts as $qty=>$pct) {
            $qty_disc_txt .= sprintf('Buy %d, save %.02f%%<br />', $qty, $pct);
        }

        // Get custom text input fields
        if ('' != $this->custom) {
            $T->set_block('product', 'CustAttrib', 'cAttr');
            $text_field_names = explode('|', $this->custom);
            foreach ($text_field_names as $id=>$text_field_name) {
                $T->set_var(array(
                    'fld_id'    => "cust_text_fld_$id",
                    'fld_name'  => htmlspecialchars($text_field_name),
                ) );
                $T->parse('cAttr', 'CustAttrib', true);
            }
        }

        // Retrieve the photos and put into the template
        $sql = "SELECT img_id, filename
                FROM {$_TABLES['paypal.images']}
                WHERE product_id='$prod_id'";
        //echo $sql;die;
        $img_res = DB_query($sql);
        $photo_detail = '';
        if ($img_res && DB_numRows($img_res) > 0) {
            for ($i = 0; $prow = DB_fetchArray($img_res, false); $i++) {
                if ($prow['filename'] != '' &&
                    file_exists("{$_PP_CONF['image_dir']}/{$prow['filename']}")) {
                    if ($i == 0) {
                        $T->set_var('main_img', PAYPAL_ImageUrl($prow['filename'], 0, 0));
                        $T->set_var('main_imgfile', $prow['filename']);
                    }
                    $T->set_block('product', 'Thumbnail', 'PBlock');
                    $T->set_var(array(
                        'is_uikit' => $_PP_CONF['_is_uikit'],
                        'img_file'      => $prow['filename'],
                        'disp_img'      => PAYPAL_ImageUrl($prow['filename'], 0, 0),
                        'lg_img'        => PAYPAL_URL.'/images/products/'.$prow['filename'],
                        'img_url'       => PAYPAL_URL.'/images/products',
                        'thumb_url'     => PAYPAL_ImageUrl($prow['filename']),
                        'tn_width'      => $_PP_CONF['max_thumb_size'],
                        'tn_height'     => $_PP_CONF['max_thumb_size'],
                        'session_id'    => session_id(),
                        'small_imgfile' => $prow['filename'],
                    ) );
                    $T->parse('PBlock', 'Thumbnail', true);
                }
            }
        }

        // Get the product options, if any, and set them into the form
        $cbrk = '';
        $init_price_adj = NULL;
        $this->_orig_price = $this->price;
        $T->set_block('product', 'AttrSelect', 'attrSel');
        if (is_array($this->options)) {
            foreach ($this->options as $id=>$Attr) {
                /*if ($Attr['attr_value'] === '') {
                    $type = 'text';
                } else {
                    $type = 'select';
                }*/
                $type = 'select';
                if ($Attr['attr_name'] != $cbrk) {
                    // Adjust the price for cases where all attributes have prices
                    if ($init_price_adj !== NULL) {
                        $this->act_price += $init_price_adj;
                        $this->_orig_price += $init_price_adj;
                    }
                    $init_price_adj = NULL;
                    if ($cbrk != '') {      // end block if not the first element
                        $T->set_var(array(
                            'attr_name' => $cbrk,
                            'attr_options' => $attributes,
                            'opt_id' => $id,
                        ) );
                        $T->parse('attrSel', 'AttrSelect', true);
                    }
                    $cbrk = $Attr['attr_name'];
                    $attributes = '';
                }

                if ($type == 'select') {
                    if ($init_price_adj === NULL) $init_price_adj = $Attr['attr_price'];
                    if ($Attr['attr_price'] != 0) {
                        $attr_str = sprintf(" ( %+.2f )", $Attr['attr_price']);
                    } else {
                        $attr_str = '';
                    }
                    $attributes .= '<option value="' . $id . '|' .
                        $Attr['attr_value'] . '|' . $Attr['attr_price'] . '">' .
                        $Attr['attr_value'] . $attr_str .
                        '</option>' . LB;
                /*} else {
                    $attributes .= "<input type=\"hidden\" name=\"on{$i}\"
                        value=\"{$Attr['attr_name']}\">\n";
                    $attributes .= $Attr['attr_name'] . ':</td>
                        <td><input class="uk-contrast uk-form" type"text" name="os' . $i. '" value="" size="32" /></td></tr>';
                */
                }
            }
            if ($cbrk != '') {      // finish off the last selection
                if ($init_price_adj !== NULL) {
                    $this->_act_price += $init_price_adj;
                    $this->_orig_price += $init_price_adj;
                }
                $T->set_var(array(
                    'attr_name' => $cbrk,
                    'attr_options' => $attributes,
                    'opt_id' => $id,
                ) );
                $T->parse('attrSel', 'AttrSelect', true);
            }
        }

        if ($this->getShipping()) {
            $shipping_txt = sprintf($LANG_PP['plus_shipping'], Currency::getInstance()->formatValue($this->shipping_amt));
        } else {
            $shipping_txt = '';
        }
        $T->set_var(array(
            'is_uikit' => $_PP_CONF['_is_uikit'],
            'have_attributes'   => $this->hasAttributes(),
            //'currency'          => $_PP_CONF['currency'],
            'id'                => $prod_id,
            'name'              => $name,
            'short_description' => $s_desc,
            'description'       => $l_desc,
            'cur_decimals'      => Currency::getInstance()->Decimals(),
            'init_price'        => Currency::getInstance()->FormatValue($this->_act_price),
            'price'             => Currency::getInstance()->FormatValue($this->getPrice()),
            'orig_price'        => Currency::getInstance()->Format($this->_orig_price),
            'on_sale'           => $this->isOnSale(),
            'img_cell_width'    => ($_PP_CONF['max_thumb_size'] + 20),
            'price_prefix'      => Currency::getInstance()->Pre(),
            'price_postfix'     => Currency::getInstance()->Post(),
            'onhand'            => $this->track_onhand ? $this->onhand : '',
            'qty_disc'          => $qty_disc_txt,
            'session_id'        => session_id(),
            'shipping_txt'      => $shipping_txt,
        ) );
        $T->set_block('product', 'SpecialFields', 'SF');
        foreach ($this->special_fields as $fld) {
            $T->set_var(array(
                'sf_name' => $fld['name'],
                'sf_text' => $fld['text'],
                'sf_class' => isset($fld['class']) ? $fld['class'] : '',
            ) );
            $T->parse('SF', 'SpecialFields', true);
        }

        $buttons = $this->PurchaseLinks();
        $T->set_block('product', 'BtnBlock', 'Btn');
        foreach ($buttons as $name=>$html) {
            if ($name == 'add_cart') {
                // Set the add to cart button in the main form
                $T->set_var('add_cart_button', $html);
            } else {
                $T->set_var('buy_now_button', $html);
                $T->parse('Btn', 'BtnBlock', true);
            }
        }

        // Show the user comments if enabled globally and for this product
        if (plugin_commentsupport_paypal() &&
                $this->comments_enabled != PP_COMMENTS_DISABLED) {
                // if enabled or closed
            if ($_CONF['commentsloginrequired'] == 1 && COM_isAnonUser()) {
                // Set mode to "disabled"
                $mode = -1;
            } else {
                $mode = $this->comments_enabled;
            }
            $T->set_var('usercomments',
                CMT_userComments($prod_id, $this->short_description, $_PP_CONF['pi_name'],
                    '', '', 0, 1, false, false, $mode));
        }

        if ($this->rating_enabled == 1) {
            $PP_ratedIds = RATING_getRatedIds($_PP_CONF['pi_name']);
            if (in_array($prod_id, $PP_ratedIds)) {
                $static = true;
                $voted = 1;
            } elseif (plugin_canuserrate_paypal($this->id, $_USER['uid'])) {
                $static = 0;
                $voted = 0;
            } else {
                $static = 1;
                $voted = 0;
            }
            $rating_box = RATING_ratingBar($_PP_CONF['pi_name'], $prod_id,
                    $this->votes, $this->rating,
                    $voted, 5, $static, 'sm');
            $T->set_var('rating_bar', $rating_box);
        } else {
            $T->set_var('ratign_bar', '');
        }

        if ($this->isAdmin) {
            // Add the quick-edit link for administrators
            $T->set_var(array(
                'pi_admin_url'  => PAYPAL_ADMIN_URL,
                'can_edit'      => 'true',
            ) );
        }
        $JT->set_var(array(
            'have_attributes'   => $T->get_var('have_attributes'),
            'price'             => $T->get_var('price'),
            'id'                => $T->get_var('id'),
            'cur_decimals'      => $T->get_var('cur_decimals'),
            'session_id'        => session_id(),
        ) );
        $JT->parse('output', 'js');
        $T->set_var('javascript', $JT->finish ($JT->get_var('output')));

        $retval .= $T->parse('output', 'product');

        // Update the hit counter
        DB_query("UPDATE {$_TABLES['paypal.products']}
                SET views = views + 1
                WHERE id = '$prod_id'");

        $retval .= COM_endBlock();
        //CACHE_create_instance($cacheInstance, $retval, 0);
        return $retval;
    }


    /**
    *   Provide the file selector options for files already uploaded.
    *
    *   @return string      HTML for file selection dialog options
    */
    public function FileSelector()
    {
        global $_PP_CONF;

        $retval = '';

        $dh = opendir($_PP_CONF['download_path']);
        if ($dh) {
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..')
                    continue;

                $sel = $file == $this->file ? 'selected="selected" ' : '';
                $retval .= "<option value=\"$file\" $sel>$file</option>\n";
            }
            closedir($dh);
        }

        return $retval;
    }


    /**
    *   Create a formatted display-ready version of the error messages.
    *   Returns the errors as a set of list items to be displayed inside
    *   <ul></ul> tags.
    *
    *   @return string      Formatted error messages.
    */
    public function PrintErrors()
    {
        $retval = array();
        foreach($this->Errors as $key=>$msg) {
            $retval[] = $msg;
        }
        return $retval;
    }


    /**
    *   Gets the purchase links appropriate for the product.
    *   May be Paypal buttons, login-required link, or download button.
    *
    *   @return array   Array of buttons as name=>html.
    */
    public function PurchaseLinks($type='detail')
    {
        global $_CONF, $_USER, $_PP_CONF, $_TABLES;

        $buttons = array();
        $this->_view = $type;

        // Indicate that an "add to cart" button should be returned along with
        // the "buy now" button.  If the product has already been purchased
        // and is available for immediate download, this will be turned off.
        $add_cart = $_PP_CONF['ena_cart'] == 1 ? true : false;

        // Get the free download button, if this is a downloadable product
        // already purchased and not expired
        $exptime = DB_getItem($_TABLES['paypal.purchases'],
                "MAX(UNIX_TIMESTAMP(CONVERT_TZ(`expiration`, '+00:00', @@session.time_zone)))",
                "user_id = {$_USER['uid']} AND product_id = '" .
                DB_escapeString($this->id) . "'");

        if ($this->prod_type == PP_PROD_DOWNLOAD &&
            ( $this->price == 0 || ($_USER['uid'] > 1 && $exptime > time()) )
            ) {
            // Free, or unexpired downloads for non-anymous
            $T = PP_getTemplate('btn_download', 'download', 'buttons');
            $T->set_var('pi_url', PAYPAL_URL);
            $T->set_var('id', $this->id);
            $buttons['download'] = $T->parse('', 'download');
            $add_cart = false;

        } elseif ($this->track_onhand == 1 && $this->onhand < 1 &&
                $this->oversell == 1) {
            // Do nothing but show the download link (see above).
            // Make sure the add_cart button isn't shown, either.
            $add_cart = false;
        } elseif ($_USER['uid'] == 1 && !$_PP_CONF['anon_buy'] &&
                !$this->hasAttributes() && $this->price > 0) {
            // Requires login before purchasing
            $T = PP_getTemplate('btn_login_req', 'login_req', 'buttons');
            $buttons['login'] = $T->parse('', 'login_req');
        } else {
            // Normal buttons for everyone else
            if ($this->canBuyNow() && $this->btn_type != '') {
                // Gateway buy-now buttons only used if no options
                foreach (Gateway::getAll() as $gw) {
                    if ($gw->Supports($this->btn_type)) {
                        $buttons[$gw->Name()] = $gw->ProductButton($this);
                    }
                }
            }
        }

        // All users and products get an add-to-cart button, if price > 0
        // and cart is enabled, and product is not a donation. Donations
        // can't be mixed with products, so don't allow adding to the cart.
        if ($add_cart && $this->btn_type != 'donation' &&
                ($this->price > 0 || !$this->canBuyNow()) ) {
            $T = PP_getTemplate('btn_add_cart_attrib', 'cart', 'buttons');
            $T->set_var(array(
                'item_name'     => htmlspecialchars($this->name),
                'item_number'   => $this->id,
                'short_description' => htmlspecialchars($this->short_description),
                'amount'        => $this->getPrice(),
                'pi_url'        => PAYPAL_URL,
                //'form_url'  => $this->hasAttributes() ? '' : 'true',
                //'form_url'  => false,
                'form_url'  => $this->_view == 'list' ? true : false,
                'tpl_ver'   => $_PP_CONF['product_tpl_ver'],
                'frm_id'    => md5($this->id . rand()),
                'iconset'   => $_PP_CONF['_iconset'],
            ) );
            $buttons['add_cart'] = $T->parse('', 'cart');
        }
        return $buttons;
    }


    /**
    *   Determine if this product has any attributes.
    *
    *   @return boolean     True if attributes exist, False if not.
    */
    public function hasAttributes()
    {
        return empty($this->options) ? false : true;
    }


    /**
    *   Determine if this product has any quantity-based discounts.
    *   Used to display "discounts available" message in the product liet.
    *
    *   @return boolean     True if attributes exist, False if not.
    */
    public function hasDiscounts()
    {
        // Have to assign to temp var to get empty() to work
        $discounts = $this->qty_discounts;
        return empty($discounts) ? false : true;
    }


    /**
    *   Check if this product uses custom per-product text-input fields
    *
    *   @return boolean     True if custom fields are configured
    */
    public function hasCustomFields()
    {
        $cust = $this->custom;
        return empty($cust) ? false : true;
    }


    /**
    *   Check if this product type uses special text-input fields
    *
    *   @return boolean     True if special fields are configured
    */
    public function hasSpecialFields()
    {
        return empty($this->special_fields) ? false : true;
    }


    /**
    *   Add a special field to a product.
    *   The field will not be added if $fld_name already exists for the
    *   product.
    *   The prompt string may be supplied or, if blank, then $fld_name is used
    *   to find a string in $LANG_PP. Final fallback is to use the field name
    *   as the prompt.
    *   Plugins should be sure to set $fld_lang.
    *
    *   @param  string  $fld_name   Field Name
    *   @param  stirng  $fld_lang   Field prompt, language string
    */
    public function addSpecialField($fld_name, $fld_lang = '', $opts=array())
    {
        global $LANG_PP;

        if (!array_key_exists($fld_name, $this->special_fields)) {
            // Only add if the field doesn't already exist
            if (empty($fld_lang)) {
                // No text supplied, try to get one from the language file.
                if (array_key_exists($fld_name, $LANG_PP)) {
                    $fld_lang = $LANG_PP[$fld_name];
                } else {
                    // If all else fails, use the field name as the prompt.
                    $fld_lang = $fld_name;
                }
            }
            $this->special_fields[$fld_name] = array(
                'name' => $fld_name,
                'text' => $fld_lang,
            );
            foreach ($opts as $opt_name=>$opt_data) {
                $this->special_fields[$fld_name][$opt_name] = $opt_data;
            }
        }
    }


    /**
    *   Determine if a "Buy Now" button is allowed for this item.
    *   Items with attributes or a quantity discount schedule must be
    *   purchased through the shopping cart to allow for proper price
    *   calculation.
    *
    *   @return boolean     True to allow Buy Now, False to disable
    */
    public function canBuyNow()
    {
        if ($this->hasAttributes()      // no attributes to select
            || $this->hasDiscounts()    // no quantity-based discounts
            || $this->hasCustomFields() // no text fields to fill in
            || $this->hasSpecialFields()    // no special fields to fill in
        ) {
            // If any of the above apply, then the buy-now button can't be used.
            return false;
        }
        return true;
    }


    /**
    *   Get the unit price of this product, considering the specified options.
    *   Quantity discounts are considered, the return value is the effictive
    *   price per unit.
    *
    *   @param  array   $options    Array of integer option values
    *   @param  integer $quantity   Quantity, used to calculate discounts
    *   @param  array   $override   Override elements (price, uid)
    *   @return float       Product price, including option
    */
    public function getPrice($options = array(), $quantity = 1, $override = array())
    {
        if (!is_array($options)) $options = array($options);
        if ($this->override_price && isset($override['price'])) {
            // If an override price is specified, just return it.
            return round((float)$override['price'], Currency::getInstance()->Decimals());
        } else {
            $price = $this->getSalePrice();
        }

        // future: return sale price if on sale, otherwise base price
        foreach ($options as $key) {
            $parts = explode('|', $key); // in case of "7|Black|1.50" option
            $key = $parts[0];
            if (isset($this->options[$key])) {
                $price += (float)$this->options[$key]['attr_price'];
            }
        }
        $discount_factor = 1;
        if (is_array($this->qty_discounts)) {
            foreach ($this->qty_discounts as $qty=>$discount) {
                $qty = (int)$qty;
                if ($quantity < $qty) {     // haven't reached this discount level
                    break;
                } else {
                    $discount = (float)$discount;
                    $discount_factor = (100 - $discount) / 100;
                }
            }
        }
        $price *= $discount_factor;
        $price = round($price, Currency::getInstance()->Decimals());
        return $price;
    }


    /**
    *   Get the formatted price for display.
    *   Used mainly to allow child classes to override the displayed price.
    *
    *   @param  mixed   $price  Fixed price to use, NULL to use getPrice()
    *   @return string          Formatted price for display
    */
    public function getDisplayPrice($price = NULL)
    {
        if ($price === NULL) $price = $this->getPrice();
        return Currency::getInstance()->Format($price);
    }


    /**
    *   Get the sales tax for this item based on the configured tax rate.
    *
    *   @param  float   $price  Unit price
    *   @param  integer $qty    Item quantity
    *   @return float           Sales tax ammount
    */
    public function getTax($price, $qty = 1)
    {
        if ($this->taxable) {
            return round(PP_getTaxRate() * $price * $qty, 2);
        } else {
            return 0;
        }
    }


    /**
    *   Get the options display to be shown in the cart and on the order
    *   Returns a string like so:
    *       -- option1: option1_value
    *       -- option2: optoin2_value
    *
    *   @param  object  $item   Specific OrderItem object from the cart
    *   @return string      Option display
    */
    public function getOptionDisplay($item)
    {
        $retval = '';
        $opts = array();

        // Get attributes selected from the available options
        if (!empty($item->options)) {
            $options = explode(',', $item->options);
            foreach ($options as $option) {
                $opts[] = array(
                    'opt_name'  => $this->options[$option]['attr_name'],
                    'opt_value' => $this->options[$option]['attr_value'],
                );
            }
        }

        // Get special fields submitted with the purchase
        if (is_array($item->extras)) {
            if (isset($item->extras['special']) && is_array($item->extras['special'])) {
                $sp_flds = $this->getSpecialFields($item->extras['special']);
                foreach ($sp_flds as $txt=>$val) {
                    $opts[] = array(
                        'opt_name'  => $txt,
                        'opt_value' => $val,
                    );
                }
            }
        }

        // Get text fields defined with the product
        $text_names = explode('|', $this->custom);
        if (!empty($text_names) &&
                isset($item->extras['custom']) &&
                is_array($item->extras['custom'])) {
            foreach ($item->extras['custom'] as $tid=>$val) {
                if (isset($text_names[$tid])) {
                    $opts[] = array(
                        'opt_name'  => htmlspecialchars($text_names[$tid]),
                        'opt_value' => htmlspecialchars($val),
                    );
                }
            }
        }

        if (!empty($opts)) {
            $T = PP_getTemplate('view_options', 'options');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($opts as $opt) {
                $T->set_var(array(
                    'opt_name'  => $opt['opt_name'],
                    'opt_value' => $opt['opt_value'],
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }


    /**
    *   Get the descriptive values for a specified set of options.
    *
    *   @param  array   $options    Array of integer option values
    *   @return string      Comma-separate list of text values, or empty
    */
    public function getOptionDesc($options = array())
    {
        $opts = array();
        foreach ($options as $key) {
            if (strpos($key, '|') !== false) {  // complete option strings
                list($key, $junk) = explode('|', $key);
            }
            if (isset($this->options[$key])) {
                $opts[] = $this->options[$key]['attr_value'];
            }
        }
        if (!empty($opts)) {
            $retval = implode(', ', $opts);
        } else {
            $retval = '';
        }
        return $retval;
    }


    /**
    *   Get all the attributes for this product that appear in a list
    *
    *   @param  array   $options    Array of options
    *   @return array       Array of a
    */
    public function getAttributes($options = array())
    {
        global $_TABLES;

        $sql = "SELECT attr_name, attr_value
                FROM {$_TABLES['paypal.prod_attr']}
                WHERE attr_id IN ($options)";
        $optres = DB_query($sql);
        $opt_str = '';
        while ($O = DB_fetchArray($optres, false)) {
            $opt_str .= ', ' . $O['attr_value'];
            $option_desc[] = $O['attr_name'] . ': ' . $O['attr_value'];
        }
    }


    /**
    *   Handle the purchase of this item.
    *   1. Update qty on hand if track_onhand is set (min. value 0)
    *
    *   @param  integer $qty        Quantity ordered
    *   @param  object  $Item       Item record, to get options, etc.
    *   @param  object  $Order      Optional order (not used yet)
    *   @param  array   $ipn_data   IPN data (not used in this class)
    *   @return integer     Zero or error value
    */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data = array())
    {
        global $_TABLES;

        $status = 0;

        // update the qty on hand, if tracking and not already zero
        if ($this->track_onhand && $this->onhand > 0) {
            $sql = "UPDATE {$_TABLES['paypal.products']} SET
                    onhand = GREATEST(0, onhand - {$Item->quantity})
                    WHERE id = '{$this->id}'";
            DB_query($sql, 1);
            if (DB_error()) {
                COM_errorLog("Product::handlePurchase() SQL errror: $sql", 1);
                $status = 1;
            }
        }
        return $status;
    }


    public function handleRefund($Order, $ipn_data = array())
    {
    }

    public function cancelPurchase($qty, $order_id='')
    {
    }


    public function getOption($key)
    {
        if (isset($this->options[$key])) {
            return array(
                'name' => $this->options[$key]['attr_name'],
                'value' => $this->options[$key]['attr_value'],
                'price' => $this->options[$key]['attr_price'],
            );
        } else {
            return false;
        }
    }


    /**
    *   Get the prompt for a custom field.
    *   Returns "Undefined" if for some reason the field isn't defined.
    *
    *   @param  integer $key    Array key into the $custom fields
    *   @return string      Custom field name, or "undefined"
    */
    public function getCustom($key)
    {
        static $custom = NULL;
        if ($custom === NULL) {
            $custom = explode('|', $this->custom);
        }
        if (isset($custom[$key])) {
            return $custom[$key];
        } else {
            return 'Undefined';
        }
    }


    /**
    *   Duplicate a product.
    *   1 - Creates a new product record
    *   2 - Copies all images
    *   3 - Creates image records
    *
    *   @return boolean     True on success, False on failure
    */
    public function Duplicate()
    {
        global $_TABLES, $_PP_CONF;

        $old_id = $this->id;
        if ($old_id < 1) return false;      // nothing to do

        // Set product variables to indicate a new product and save it.
        $this->isNew = true;
        $this->id = 0;
        $this->name = $this->name . ' - Copy';
        $this->Save();
        if ($this->id < 1) {
            COM_errorLog("Error duplicating product id $old_id", 1);
            return false;
        }
        $new_id = $this->id;

        // Copy all the image files
        $sql = "SELECT * FROM {$_TABLES['paypal.images']}
                WHERE product_id = $old_id;";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $parts = explode('_', $A['filename']);
                $new_fname = "{$new_id}_$parts[1]";
                $src_f = $_PP_CONF['image_dir'] . '/' . $A['filename'];
                $dst_f = $_PP_CONF['image_dir'] . '/' . $new_fname;
                if (@copy($src_f, $dst_f)) {
                    // copy successful, insert record into table
                    $sql = "INSERT INTO {$_TABLES['paypal.images']}
                                (product_id, filename)
                            VALUES ('$new_id', '" . DB_escapeString($new_fname) . "')";
                    DB_query($sql);
                } else {
                    COM_errorLog("Error copying file $src_f to $dst_f, continuing", 1);
                }
            }
        } else {
            COM_errorLog("Bad image query for product $old_id, continuing", 1);
        }

        return true;
    }


    /**
    *   Determine if this product is on sale
    *
    *   @return boolean True if on sale, false if not
    */
    public function isOnSale()
    {
        $sp = $this->getSalePrice();
        return $this->price > 0 && $sp < $this->price ? true : false;
    }


    /**
    *   Get the sale price for this item, if any.
    *   First checks for an item-specific sale price and sale period,
    *   then traverses up the category tree to find the first parent
    *   category with an effective sale price.
    *   Prices are cached for repeated calls.
    *
    *   @see    self::isOnSale()
    *   @return float   Sale price, normal price if not on sale
    */
    public function getSalePrice()
    {
        return Sales::getEffective($this)->calcPrice($this->price);
    }


    /**
    *   Determine if a product is available for sale based on dates
    *   Default availability dates are from 1900-01-01 to 9999-12-31
    *
    *   @param  boolean $isadmin    True if this is an admin, can view all
    *   @return boolean True if on sale, false if not
    */
    public function isAvailable($isadmin = false)
    {
        global $_PP_CONF;

        if ($isadmin) return true;  // Admin can always view

        $today = PAYPAL_now()->format('Y-m-d', true);
        if ($today < $this->avail_beg || $today > $this->avail_end) {
            return false;
        } else {
            return true;
        }
    }


    /**
    *   Check if tax should be charged on this item.
    *   Checks both the product taxable flag and the configured tax
    *   rate.
    *
    *   @return boolean     True if taxable and there is a tax rate
    */
    public function isTaxable()
    {
        global $_PP_CONF;
        return $this->taxable && PP_getTaxRate() > 0;
    }


    /**
    *   Display the date, if present, or a blank field if effectively null.
    *
    *   @param  string  $str    Date string, "0000-00-00" indicates empty
    *   @return string      Supplied date string, or "" if zeroes
    */
    private static function _InputDtFormat($str)
    {
        if ($str == '0000-00-00' || $str == self::MAX_DATE || $str == self::MIN_DATE)
            return '';
        else
            return $str;
    }


    /**
    *   Determine if a given item number belongs to a plugin.
    *   Looks for a colon in the item number, which will indicate a plugin
    *   item number formated as "pi_name:item_number:other_opts"
    *
    *   @since  0.6.0
    *   @param  mixed   $item_number    Item Number to check
    *   @return boolean     True if it's a plugin item, false if it's ours
    */
    public static function isPluginItem($item_number)
    {
        if (strpos($item_number, ':') > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Get the text string and value for special fields.
    *   Used when displaying cart info
    *
    *   @param  array   $values     Special field values
    *   @return array       Array of text=>value
    */
    public function getSpecialFields($values = array())
    {
        global $LANG_PP;

        $retval = array();
        if (!is_array($values)) {
            return $retval;
        }
        foreach ($this->special_fields as $fld_name=>$fld) {
            if (array_key_exists($fld_name, $values) && !empty($values[$fld_name])) {
                $retval[$fld['text']] = $values[$fld_name];
            }
        }
        return $retval;
    }


    /**
    *   Helper function to create the cache key.
    *
    *   @since  0.6.0
    *   @return string  Cache key
    */
    private static function _makeCacheKey($id)
    {
        $id = (int)$id;
        return 'product_' . $id;
    }


    /**
    *   Determine if the current user has access to view this product.
    *   Checks the related category for access.
    *
    *   @return boolean     True if access and purchase is allowed.
    */
    public function hasAccess()
    {
        // Make sure the category is set
        if (!$this->Cat) $this->Cat = Category::getInstance($this->cat_id);
        return $this->Cat->hasAccess();
    }


    /**
    *   Get the product name. Allows for an override
    *
    *   @since  0.6.0
    *   @param  string  $overrride  Optional name override
    *   @return string              Product Name
    */
    public function getName($override = '')
    {
        return $override == '' ? $this->name : $override;
    }


    /**
    *   Get the product shoryt description. Allows for an override
    *
    *   @since  0.6.0
    *   @param  string  $overrride  Optional description override
    *   @return string              Product sort description
    */
    public function getDscp($override = '')
    {
        return $override == '' ? $this->short_description : $override;
    }


    /**
    *   Get the URL to the item detail page
    *
    *   @since  0.6.0
    *   @return string      Item detail URL
    */
    public function getLink()
    {
        return PAYPAL_URL . '/detail.php?id=' . $this->id;
    }


    /**
    *   Get additional text to add to the buyer's recipt for a product
    *
    *   @since  0.6.0
    */
    public function EmailExtra($orderitem)
    {
        return '';
    }


    /**
     * Get the quantity for a buy-now button.
     * This is used to either set a fixed quantity, or if zero then
     * the buyer can enter a quantity.
     * Catalog products allow any quantity to be purchased.
     *
     * @return  integer     Allowed quantity
     */
    public function buynowQty()
    {
        return 0;
    }


    /**
     * Get the total shipping amount for this item based on quantity purchased
     *
     * @param   integer $qty    Quantity purchased
     * @return  float           Total item shipping charge
     */
    public function getShipping($qty = 1)
    {
        if ($this->shipping_type == 2) {
            // fixed per-item shipping
            return (float)$this->shipping_amt * $qty;
        } else {
            // no shipping, or calculated by gateway
            return 0;
        }
    }


    /**
     * Get the total handling fee for this item based on quantity purchased
     *
     * @param   integer $qty    Quantity purchased
     * @return  float           Total handling charge
     */
    public function getHandling($qty = 1)
    {
        return (float)$this->handling * $qty;
    }

}   // class Product

?>

<?php
/**
 *  Class to handle images
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
 *  @package    paypal
 *  @version    0.0.1
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *  GNU Public License v2 or later
 *  @filesource
 */

// Import core glFusion upload functions
USES_class_upload();

/**
 *  Image-handling class
 *  @package paypal
 */
class ProductImage extends upload
{
    /** Path to actual image (without filename)
     *  @var string */
    var $pathImage;

    /** ID of the current ad
     *  @var string */
    var $product_id;

    /** Array of the names of successfully uploaded files
     *  @var array */
    var $goodfiles = array();

    /**
     *  Constructor
     *  @param string $name Optional image filename
     */
    function __construct($product_id, $varname='photo')
    {
        global $_PP_CONF, $_CONF;

        $this->setContinueOnError(true);
        $this->setLogFile('/tmp/warn.log');
        $this->setDebug(true);
        $this->_setAvailableMimeTypes();

        // Before anything else, check the upload directory
        if (!$this->setPath($_PP_CONF['image_dir'])) {
            return;
        }
        $this->product_id = trim($product_id);
        $this->pathImage = $_PP_CONF['image_dir'];
        $this->setAllowedMimeTypes(array(
                'image/pjpeg' => '.jpg,.jpeg',
                'image/jpeg'  => '.jpg,.jpeg',
        ));
        $this->setMaxFileSize($_PP_CONF['max_image_size']);
        $this->setMaxDimensions(
                $_PP_CONF['img_max_width'],
                $_PP_CONF['img_max_height']
        );
        $this->setAutomaticResize(true);
        $this->setFieldName($varname);

        $filenames = array();
        for ($i = 0; $i < $this->numFiles(); $i++) {
            $filenames[] = $this->product_id . '_' . rand(100,999) . '.jpg';
        }
        $this->setFileNames($filenames);
    }


    /**
    *   Perform the file upload
    *
    *   Calls the parent function to upload the files, then calls
    *   MakeThumbs() to create thumbnails.
    */
    public function uploadFiles()
    {
        global $_TABLES;

        // Perform the actual upload
        parent::uploadFiles();

        // Seed image cache with thumbnails
        $this->MakeThumbs();

        foreach ($this->goodfiles as $filename) {
            $sql = "INSERT INTO {$_TABLES['paypal.images']}
                    (product_id, filename)
                VALUES (
                    '{$this->product_id}', '".
                    DB_escapeString($filename)."'
                )";
            $result = DB_query($sql);
            if (!$result) {
                $this->addError("uploadFiles() : Failed to insert {$filename}");
            }
        }
 
    }


    /**
    *   Calculate the new dimensions needed to keep the image within
    *   the provided width & height while preserving the aspect ratio.
    *
    *   @deprecated
    *   @param string  $srcfile     Source filepath/name
    *   @param integer $width       New width, in pixels
    *   @param integer $height      New height, in pixels
    *   @return array  $newwidth, $newheight
    */
    function reDim($srcfile, $width=0, $height=0)
    {
        list($s_width, $s_height) = @getimagesize($srcfile);

        // get both sizefactors that would resize one dimension correctly
        if ($width > 0 && $s_width > $width)
            $sizefactor_w = (double) ($width / $s_width);
        else
            $sizefactor_w = 1;

        if ($height > 0 && $s_height > $height)
            $sizefactor_h = (double) ($height / $s_height);
        else
            $sizefactor_h = 1;

        // Use the smaller factor to stay within the parameters
        $sizefactor = min($sizefactor_w, $sizefactor_h);

        $newwidth = (int)($s_width * $sizefactor);
        $newheight = (int)($s_height * $sizefactor);

        return array($newwidth, $newheight);
    }

    /**
    *   Seed the image cache with the product image thumbnails.
    *
    *   @uses   LGLIB_ImageUrl()
    *   @return string      Blank, error messages are now in parent::_errors
    */
    private function MakeThumbs()
    {
        global $_PP_CONF;

        $thumbsize = (int)$_PP_CONF['max_thumb_size'];
        if ($thumbsize < 50) $thumbsize = 100;

        if (!is_array($this->_fileNames))
            return '';

        foreach ($this->_fileNames as $filename) {
            $src = "{$this->pathImage}/{$filename}";
            $url = LGLIB_ImageUrl($src, $thumbsize, $thumbsize, true);
            if (!empty($url)) {
                $this->goodfiles[] = $filename;
            } else {
                $this->_addError("MakeThumbs() : $filename - $msg");
            }
        }
        return '';

    }   // function MakeThumbs()


    /**
     *  Delete an image from disk.  Called by Entry::Delete if disk
     *  deletion is requested.
     */
    public function Delete()
    {
        // If we're deleting from disk also, get the filename and 
        // delete it and its thumbnail from disk.
        if ($this->filename == '') {
            return;
        }

        $this->_deleteOneImage($this->pathImage);
    }

    /**
     *  Delete a single image using the current name and supplied path
     *  @access private
     *  @param string $imgpath Path to file
     */
    function _deleteOneImage($imgpath)
    {
        if (file_exists($imgpath . '/' . $this->filename))
            unlink($imgpath . '/' . $this->filename);
    }

    /**
    *   @deprecated
     *  Handles the physical file upload and storage.
     *  If the image isn't validated, the upload doesn't happen.
     *  @param array $file $_FILES array
     */
    function Upload($file)
    {
        if (!is_array($file))
            return "Invalid file given to Upload()";

        $msg = $this->Validate($file);
        if ($msg != '')
            return $msg;

        $this->filename = $this->product_id . '.' . rand(10,99) . $this->filetype;

        if (!@move_uploaded_file($file['tmp_name'],
                $this->pathImage . '/' . $this->filename)) {
            return 'upload_failed_msg';
        }

        // Create the display and thumbnail versions.  Errors here
        // aren't good, but aren't fatal.
        $this->ReSize('thumb');
        $this->ReSize('disp');

    }   // function Upload()


    /**
    *   @deprecated
     *  Validate the uploaded image, checking for size constraints and other errors
     *  @param array $file $_FILES array
     *  @return boolean True if valid, False otherwise
     */
    function Validate($file)
    {
        if (!is_array($file))
            return;

        $msg = '';
        // verify that the image is a jpeg or other acceptable format.
        // Don't trust user input for the mime-type
        if (function_exists('exif_imagetype')) {
            switch (exif_imagetype($file['tmp_name'])) {
            case IMAGETYPE_JPEG:
                $this->filetype = 'jpg';
                $filetype_mime = 'image/jpeg';
                break;
            default:    // other
                $msg .= 'upload_invalid_filetype';
                break;
            }
        } else {
            return "System Error: Missing exif_imagetype function";
        }

        // Now check for error messages in the file upload: too large, etc.
        switch ($file['error']) {
        case UPLOAD_ERR_OK:
            if ($file['size'] > $_CONF['max_image_size']) {
                $msg .= "<li>upload_too_big'</li>\n";
            }
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $msg = "<li>upload_too_big</li>\n"; 
            break;
        case UPLOAD_ERR_NO_FILE:
            $msg = "<li>upload_missing_msg</li>\n";
            break;
        default:
            $msg = "<li>upload_failed_msg</li>\n";
            break;
        }

        return $msg;

    }


}   // class Image

?>

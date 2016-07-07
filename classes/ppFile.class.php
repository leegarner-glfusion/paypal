<?php
/**
 *  Class to handle file uploads
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
 *  @package    paypal
 *  @version    0.4.0
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
class ppFile extends upload
{
    //var $properties;

    var $filenames;

    /** Array of the names of successfully uploaded files
     *  @var array */
    var $goodfiles = array();

    /**
     *  Constructor
     *  @param  string  $varname        Optional form variable name
     */
    function ppFile($varname='uploadfile')
    {
        global $_PP_CONF, $_CONF;

        $this->filenames = array();
        $this->setContinueOnError(true);
        $this->setLogFile('/tmp/warn.log');
        $this->setDebug(true);
        $this->_setAvailableMimeTypes();

        // Before anything else, check the upload directory
        if (!$this->setPath($_PP_CONF['download_path'])) {
            return;
        }
   
        // For now, this is ok.  Later maybe duplicate the $_PP_CONF array
        // for downloaded mime-types.  For some reason, upload.class.php and
        // download.class.php have their array key=>values reversed. 
        $this->setAllowAnyMimeType(true);
        //$this->setAllowedMimeTypes($_PP_CONF['allowedextensions']);

        // Max size for uploads?  This is only accessible to admins anyway.
        $this->setMaxFileSize((int)$_PP_CONF['max_file_size'] * 1048576);

        // Set the name of the form variable used.
        $this->setFieldName($varname);

        // For a single file this is a simple one-element array.
        // To allow multiple files per product, a "real" array needs
        // to be populated and another DB table will be needed.
        //$filenames = array();
        //for ($i = 0; $i < count($_FILES[$varname]['name']); $i++) {
        //    $this->filenames[] = $_FILES[$varname][$i]['name'];
        //}
        $this->filenames[] = $_FILES[$varname]['name'];
        $this->setFileNames($this->filenames);

    }


    /**
    *   Actually handle the file upload.
    *   Currently, all this does is return the filename so it can be
    *   updated in the product record.
    *
    *   @return string      Name of uploaded file
    */
    function uploadFiles()
    {
        global $_TABLES;

        parent::uploadFiles();

        return $this->filenames[0];
 
    }


    /**
     *  Delete a single image using the current name and supplied path
     *  @access private
     *  @param string $imgpath Path to file
     */
    function Delete()
    {
        if (file_exists($this->getPath() . '/' . $this->filename))
            unlink($this->getPath() . '/' . $this->filename);
    }

}   // class ppFile

?>

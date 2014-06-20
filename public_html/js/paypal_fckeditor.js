/**
*   FCKEditor Integration for the Paypal Plugin
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.4.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*   GNU Public License v2 or later
*   @filesource
*/

// Add the following code to the template file.  For some reason it doesn't
// work if it's included with the rest of this file
/*
    var undefined;
    window.addEvent('load', function() {
        var oFCKeditor1 = new FCKeditor( 'adv_content' ) ;
        oFCKeditor1.BasePath = glfusionEditorBasePath;
        oFCKeditor1.Config['CustomConfigurationsPath'] = glfusionEditorBaseUrl + '/fckeditor/myconfig.js';
        if ( undefined != window.glfusionStyleBasePath ) {
            oFCKeditor1.Config['EditorAreaCSS'] = glfusionStyleCSS;
            oFCKeditor1.Config['StylesXmlPath'] = glfusionStyleBasePath + 'fckstyles.xml';
        }
        oFCKeditor1.ToolbarSet = 'editor-toolbar2' ;
        oFCKeditor1.Height = 200;
        oFCKeditor1.Width = 500;
        oFCKeditor1.AutoGrowMax = 1200;
        oFCKeditor1.ReplaceTextarea() ;
   });
*/

   function changeToolbar(toolbar) {
        var oEditor1 = FCKeditorAPI.GetInstance('adv_content');
        oEditor1.ToolbarSet.Load( toolbar ) ;
   }

    function change_editmode(obj) {
        if (obj.value == 'adveditor') {
            document.getElementById('advanced_editarea').style.display='';
            //document.getElementById('sel_toolbar').style.display='';
            document.getElementById('html_editarea').style.display='none';
            swapEditorContent('advanced');
        } else {
            document.getElementById('advanced_editarea').style.display='none';
            //document.getElementById('sel_toolbar').style.display='none';
            document.getElementById('html_editarea').style.display='';
            swapEditorContent('html');
        }
    }

    function swapEditorContent(curmode) {
        var content = '';
        var oEditor = FCKeditorAPI.GetInstance('adv_content');
        if (curmode == 'advanced') {
            content = document.getElementById('html_content').value;
            oEditor.SetHTML(content);
        } else {
            content = oEditor.GetXHTML( true );
            document.getElementById('html_content').value = content;
       }
    }

    function set_postcontent() {
        if (document.getElementById('sel_editmode').value == 'adveditor') {
            var oEditor = FCKeditorAPI.GetInstance('adv_content');
            content = oEditor.GetXHTML( true );
            document.getElementById('html_content').value = content;
        }
    }

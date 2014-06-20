<?php
// +--------------------------------------------------------------------------+
// | PayPal Plugin - glFusion CMS                                             |
// +--------------------------------------------------------------------------+
// | ipn.php                                                                  |
// |                                                                          |
// | page that accepts IPN transaction information from the paypal servers.   |
// | A link to this page needs to be associated with your paypal business     |
// | account.                                                                 |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | Based on the gl-paypal Plugin for Geeklog CMS                            |
// | Copyright (C) 2005-2006 by the following authors:                        |
// |                                                                          |
// | Authors: Vincent Furia     - vinny01 AT users DOT sourceforge DOT net    |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

/**
 *  Page that accepts IPN information from the paypal servers.
 *  A link to this page needs to be associated with your paypal business i
 *  account.
 *
 *  @author Vincent Furia <vinny01 AT users DOT sourceforge DOT net>
 *  @copyright Vincent Furia 2005 - 2006
 *  @package paypal
 */
    
/** Import core glFusion functions */
require_once '../../lib-common.php';

USES_paypal_functions();

/** Import IPN class */
USES_paypal_class_ipn('paypal');

if ($_PP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog("Recieved IPN:");
    COM_errorLog(var_export($_POST, true));
}

if (GVERSION < '1.3.0') {
    $_POST = LGLIB_stripslashes($_POST);
}

// Process IPN request
$ipn = new PaypalIPN($_POST);
$ipn->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";

?>

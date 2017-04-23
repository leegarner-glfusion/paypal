<?php
/**
*   Common admistrative AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.10
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!plugin_ismoderator_paypal()) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the classifieds admin ajax function.");
    exit;
}
switch ($_REQUEST['action']) {
case 'updatestatus':
    if (!empty($_POST['order_id']) &&
        !empty($_POST['newstatus'])) {
        $showlog = $_POST['showlog'] == 1 ? 1 : 0;
        USES_paypal_class_order();
        $log_ts = '';
        $log_user = '';
        $log_msg = '';
        $newstatus = $_POST['newstatus'];
        $order_id = $_POST['order_id'];
        $showlog = $_POST['showlog'] == 1 ? 1 : 0;
        $ord = new ppOrder($_POST['order_id']);
        if ($ord->isNew) break;     // non-existant order
        if ($ord->UpdateStatus($newstatus)) {
            $sql = "SELECT * FROM {$_TABLES['paypal.order_log']}
                WHERE order_id = '" . DB_escapeString($order_id) . "'
                ORDER BY ts DESC
                LIMIT 1";
            //echo $sql;die;
            $L = DB_fetchArray(DB_query($sql), false);
            if (!empty($L)) {
                // Add flag to indicate whether to update on-screen log
                $L['showlog'] = $showlog;
                $dt = new Date($L['ts'], $_CONF['timezone']);
                $L['ts'] = $dt->format($_PP_CONF['datetime_fmt'], true);
                $L['newstatus'] = $newstatus;
            }
        }
        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        echo json_encode($L);
        break;
    }
    break;

case 'toggle':
    switch ($_POST['component']) {
    case 'product':
        USES_paypal_class_product();

        switch ($_POST['type']) {
        case 'enabled':
            $newval = ppProduct::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;

        case 'featured':
            $newval = ppProduct::toggleFeatured($_POST['oldval'], $_POST['id']);
            break;

         default:
            exit;
        }
        break;

    case 'category':
        USES_paypal_class_category();

        switch ($_POST['type']) {
        case 'enabled':
            $newval = ppCategory::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

         default:
            exit;
        }
        break;

    case 'attribute':
        USES_paypal_class_attribute();

        switch ($_POST['type']) {
        case 'enabled':
            $newval = ppAttribute::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

         default:
            exit;
        }

       break;

    case 'gateway':
        USES_paypal_gateway();

        switch ($_POST['type']) {
        case 'enabled':
            $newval = PaymentGw::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

        case 'buy_now':
            $newval = PaymentGw::toggleBuyNow($_REQUEST['oldval'], $_REQUEST['id']);
            break;

        case 'donation':
            $newval = PaymentGw::toggleDonation($_REQUEST['oldval'], $_REQUEST['id']);
            break;

        default:
            exit;
        }
        break;

    case 'workflow':
        USES_paypal_class_workflow();
        $field = $_POST['type'];
        switch ($field) {
        case 'enabled':
            $newval = ppWorkflow::Toggle($_REQUEST['id'], $field, $_REQUEST['oldval']);
            break;

        default:
            exit;
        }
        break;

    case 'orderstatus':
        USES_paypal_class_orderstatus();
        $field = $_POST['type'];
        switch ($field) {
        case 'enabled':
        case 'notify_buyer':
            $newval = ppOrderStatus::Toggle($_REQUEST['id'], $field, $_REQUEST['oldval']);
            break;

        default:
            exit;
        }
        break;

    default:
        exit;
    }

    // Common output for all toggle functions.
    $retval = array(
        'id'    => $_POST['id'],
        'type'  => $_POST['type'],
        'component' => $_POST['component'],
        'newval'    => $newval,
        'statusMessage' => $newval != $_POST['oldval'] ? 
                $LANG_PP['msg_updated'] : $LANG_PP['msg_unchanged'],
    );

    header('Content-Type: text/xml');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    break; 
}

?>

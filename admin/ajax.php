<?php
/**
*   Common admistrative AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2011 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!SEC_hasRights('paypal.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the classifieds admin ajax function.");
    exit;
}

switch ($_GET['action']) {
case 'updatestatus':
    if (!empty($_GET['order_id']) &&
        !empty($_GET['newstatus'])) {
        $showlog = $_GET['showlog'] == 1 ? 1 : 0;
        USES_paypal_class_order();
        $log_ts = '';
        $log_user = '';
        $log_msg = '';
        $newstatus = $_GET['newstatus'];
        $order_id = $_GET['order_id'];
        $retstatus = $_GET['oldstatus'];
        $ord = new ppOrder($_GET['order_id']);
        if ($ord->isNew) break;     // non-existant order
        if ($ord->UpdateStatus($newstatus)) {
        //if (ppOrder::UpdateStatus($newstatus, $order_id)) {
            $sql = "SELECT * FROM {$_TABLES['paypal.order_log']}
                WHERE order_id = '" . DB_escapeString($order_id) . "'
                ORDER BY ts DESC
                LIMIT 1";
            //echo $sql;die;
            $L = DB_fetchArray(DB_query($sql, 1), false);
            if (!empty($L)) {
                $log_ts = $L['ts'];
                $log_user = $L['username'];
                $log_msg = $L['message'];
                $retstatus = $_GET['newstatus'];
            }
        }
        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<log_ts>$log_ts</log_ts>\n";
        echo "<log_user>$log_user</log_user>\n";
        echo "<log_msg>$log_msg</log_msg>\n";
        echo "<showlog>$showlog</showlog>\n";
        echo "<newstatus>$retstatus</newstatus>\n";
        echo "<order_id>$order_id</order_id>\n";
        echo "</info>\n";
        break;

    }
    break;

case 'toggle':
    switch ($_GET['component']) {
    case 'product':
        USES_paypal_class_product();

        switch ($_GET['type']) {
        case 'enabled':
            $newval = Product::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

        case 'featured':
            $newval = Product::toggleFeatured($_REQUEST['oldval'], $_REQUEST['id']);
            break;

         default:
            exit;
        }

        $img_url = PAYPAL_URL . '/images/';
        $img_url .= $newval == 1 ? 'on.png' : 'off.png';

        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<newval>$newval</newval>\n";
        echo "<id>{$_REQUEST['id']}</id>\n";
        echo "<type>{$_REQUEST['type']}</type>\n";
        echo "<component>{$_REQUEST['component']}</component>\n";
        echo "<imgurl>$img_url</imgurl>\n";
        echo "<baseurl>" . PAYPAL_ADMIN_URL . "</baseurl>\n";
        echo "</info>\n";
        break;

    case 'category':
        USES_paypal_class_category();

        switch ($_GET['type']) {
        case 'enabled':
            $newval = Category::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

         default:
            exit;
        }

        $img_url = PAYPAL_URL . '/images/';
        $img_url .= $newval == 1 ? 'on.png' : 'off.png';

        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<newval>$newval</newval>\n";
        echo "<id>{$_REQUEST['id']}</id>\n";
        echo "<type>{$_REQUEST['type']}</type>\n";
        echo "<component>{$_REQUEST['component']}</component>\n";
        echo "<imgurl>$img_url</imgurl>\n";
        echo "<baseurl>" . PAYPAL_ADMIN_URL . "</baseurl>\n";
        echo "</info>\n";
        break;

    case 'attribute':
        USES_paypal_class_attribute();

        switch ($_GET['type']) {
        case 'enabled':
            $newval = Attribute::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

         default:
            exit;
        }

        $img_url = PAYPAL_URL . '/images/';
        $img_url .= $newval == 1 ? 'on.png' : 'off.png';

        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<newval>$newval</newval>\n";
        echo "<id>{$_REQUEST['id']}</id>\n";
        echo "<type>{$_REQUEST['type']}</type>\n";
        echo "<component>{$_REQUEST['component']}</component>\n";
        echo "<imgurl>$img_url</imgurl>\n";
        echo "<baseurl>" . PAYPAL_ADMIN_URL . "</baseurl>\n";
        echo "</info>\n";
        break;

    case 'gateway':
        USES_paypal_gateway();

        switch ($_GET['type']) {
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

        $img_url = PAYPAL_URL . '/images/';
        $img_url .= $newval == 1 ? 'on.png' : 'off.png';

        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<newval>$newval</newval>\n";
        echo "<id>{$_REQUEST['id']}</id>\n";
        echo "<type>{$_REQUEST['type']}</type>\n";
        echo "<component>{$_REQUEST['component']}</component>\n";
        echo "<imgurl>$img_url</imgurl>\n";
        echo "<baseurl>" . PAYPAL_ADMIN_URL . "</baseurl>\n";
        echo "</info>\n";
        break;

    case 'workflow':
        USES_paypal_class_workflow();
        $field = $_GET['type'];
        switch ($field) {
        case 'enabled':
            $newval = ppWorkflow::Toggle($_REQUEST['id'], $field, $_REQUEST['oldval']);
            break;

        default:
            exit;
        }

        $img_url = PAYPAL_URL . '/images/';
        $img_url .= $newval == 1 ? 'on.png' : 'off.png';

        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<newval>$newval</newval>\n";
        echo "<id>{$_REQUEST['id']}</id>\n";
        echo "<type>{$_REQUEST['type']}</type>\n";
        echo "<component>{$_REQUEST['component']}</component>\n";
        echo "<imgurl>$img_url</imgurl>\n";
        echo "<baseurl>" . PAYPAL_ADMIN_URL . "</baseurl>\n";
        echo "</info>\n";
        break;

    case 'orderstatus':
        USES_paypal_class_orderstatus();
        $field = $_GET['type'];
        switch ($field) {
        case 'enabled':
        case 'notify_buyer':
            $newval = ppOrderStatus::Toggle($_REQUEST['id'], $field, $_REQUEST['oldval']);
            break;

        default:
            exit;
        }

        $img_url = PAYPAL_URL . '/images/';
        $img_url .= $newval == 1 ? 'on.png' : 'off.png';

        header('Content-Type: text/xml');
        header("Cache-Control: no-cache, must-revalidate");
        //A date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        echo '<?xml version="1.0" encoding="ISO-8859-1"?>
        <info>'. "\n";
        echo "<newval>$newval</newval>\n";
        echo "<id>{$_REQUEST['id']}</id>\n";
        echo "<type>{$_REQUEST['type']}</type>\n";
        echo "<component>{$_REQUEST['component']}</component>\n";
        echo "<imgurl>$img_url</imgurl>\n";
        echo "<baseurl>" . PAYPAL_ADMIN_URL . "</baseurl>\n";
        echo "</info>\n";
        break;

    default:
        exit;
    }

}

?>

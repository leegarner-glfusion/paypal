<?php
/**
*   Class to handle coupon operations
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*
*   Based on https://github.com/joashp/simple-php-coupon-code-generator
*/
namespace Paypal;

/**
*   Class for coupons
*   @package paypal
*/
class Coupon extends Product
{
    const MAX_EXP = '9999-12-31';   // Max expiration date

    public function __construct($prod_id = 0)
    {
        parent::__construct($prod_id);
        $this->prod_type == PP_PROD_COUPON;
        $this->taxable = 0; // coupons are not taxable

        // Add special fields for Coupon products
        // Relies on $LANG_PP for the text prompts
        $this->addSpecialField('recipient_email');
        $this->addSpecialField('sender_name');
    }


    /**
    *   Generate a single coupon code based on options given.
    *   Mask, if used, is "XXXX-XXXX" where "X" indicates a character and any
    *   other character is passed through.
    *
    *   @author     Joash Pereira
    *   @author     Alex Rabinovich
    *   @see        https://github.com/joashp/simple-php-coupon-code-generator
    *   @param array $options
    *   @return string
    */
    public static function generate($options = array())
    {
        global $_PP_CONF;

        $length = PP_getVar($options, 'length', 'int', $_PP_CONF['gc_length']);
        $prefix = PP_getVar($options, 'prefix', 'string', $_PP_CONF['gc_prefix']);
        $suffix = PP_getVar($options, 'suffix', 'string', $_PP_CONF['gc_suffix']);
        $useLetters = PP_getVar($options, 'letters', 'int', $_PP_CONF['gc_letters']);
        $useNumbers = PP_getVar($options, 'numbers', 'int', $_PP_CONF['gc_numbers']);
        $useSymbols = PP_getVar($options, 'symbols', 'int', $_PP_CONF['gc_symbols']);
        $mask = PP_getVar($options, 'mask', 'string', $_PP_CONF['gc_mask']);

        $uppercase = array('Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P',
                            'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L',
                            'Z', 'X', 'C', 'V', 'B', 'N', 'M',
        );
        $lowercase = array('q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p',
                            'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l',
                            'z', 'x', 'c', 'v', 'b', 'n', 'm',
        );
        $numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $symbols = array('`', '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')',
                        '-', '_', '=', '+', '\\', '|', '/', '[', ']', '{', '}',
                        '"', "'", ';', ':', '<', '>', ',', '.', '?',
        );
        $characters = array();
        $coupon = '';

        switch ($useLetters) {
        case 1:     // uppercase only
            $characters = $uppercase;
            break;
        case 2:     // lowercase only
            $characters = $lowercase;
            break;
        case 3:     // both upper and lower
            $characters = array_merge($characters, $uppercase, $lowercase);
            break;
        case 0:     // no letters
        default:
            break;
        }
        if ($useNumbers) {
            $characters = array_merge($characters, $numbers);
        }
        if ($useSymbols) {
            $characters = array_merge($characters, $symbols);
        }
        $charcount = count($characters);

        // If a mask is specified, use it and substitute 'X' for coupon chars.
        // Otherwise use the specified length.
        if ($mask) {
            $len = strlen($mask);
            for ($i = 0; $i < $len; $i++) {
                if ($mask[$i] === 'X') {
                    $coupon .= $characters[mt_rand(0, $charcount - 1)];
                } else {
                    $coupon .= $mask[$i];
                }
            }
        } else {
            // if neither mask nor length given use a default length
            if ($length == 0) $length = 16;
            for ($i = 0; $i < $length; $i++) {
                $coupon .= $characters[mt_rand(0, $charcount - 1)];
            }
        }
        return $prefix . $coupon . $suffix;
    }


    /**
    *   Generate a number of coupon codes.
    *
    *   @param  integer $num        Number of coupon codes
    *   @param  array   $options    Options for code creation
    *   @return array       Array of coupon codes
    */
    public static function generate_coupons($num = 1, $options = array())
    {
        $coupons = array();
        for ($i = 0; $i < $num; $i++) {
            $coupons[] = self::generate($options);
        }
        return $coupons;
    }


    /**
    *   Record a coupon purchase
    *
    *   @param  float   $amount     Coupon value
    *   @param  integer $uid        User ID, default = current user
    *   @return mixed       Coupon code, or false on error
    */
    public static function Purchase($amount = 0, $uid = 0, $exp = self::MAX_EXP)
    {
        global $_TABLES, $_USER;

        if ($amount == 0) return false;
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $options = array();     // Use all options from global config
        do {
            // Make sure there are no duplicates
            $code = self::generate($options);
            $code = DB_escapeString($code);
        } while (DB_count($_TABLES['paypal.coupons'], 'code', $code));

        $uid = (int)$uid;
        $exp = DB_escapeString($exp);
        $amount = (float)$amount;
        $sql = "INSERT INTO {$_TABLES['paypal.coupons']} SET
                code = '$code',
                buyer = $uid,
                amount = $amount,
                balance = $amount,
                purchased = UNIX_TIMESTAMP(),
                expires = '$exp'";
        DB_query($sql);
        return DB_error() ? false : $code;
    }


    /**
    *   Apply a coupon to the user's account.
    *   Adds the value to the gc_bal field in user info, and marks the coupon
    *   as "redeemed" so it can't be used again.
    *   Status code returned will be 0=success, 1=already done, 2=error
    *
    *   @param  string  $code   Coupon code
    *   @param  integer $uid    Optional user ID, current user by default
    *   @return array       Array of (Status code, Message)
    */
    public static function Redeem($code, $uid = 0)
    {
        global $_TABLES, $_USER, $LANG_PP, $_CONF;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) {
            return array(2, sprintf($LANG_PP['coupon_apply_msg2'], $_CONF['site_email']));
        }

        $code = DB_escapeString($code);
        $sql = "SELECT * FROM {$_TABLES['paypal.coupons']}
                WHERE code = '$code'";
        $res = DB_query($sql);
        if (DB_numRows($res) == 0) {
            COM_errorLog("Attempting to redeem coupon $code, not found in database");
            return array(3, sprintf($LANG_PP['coupon_apply_msg3'], $_CONF['site_mail']));;
        } else {
            $A = DB_fetchArray($res, false);
            if ($A['redeemed'] > 0 && $A['redeemer'] > 0) {
                COM_errorLog("Coupon code $code was already redeemed");
                return array(1, $LANG_PP['coupon_apply_msg1']);
            }
        }
        $amount = (float)$A['amount'];
        if ($amount > 0) {
            DB_query("UPDATE {$_TABLES['paypal.coupons']} SET
                    redeemer = $uid,
                    redeemed = UNIX_TIMESTAMP()
                    WHERE code = '$code'");
            Cache::delete('coupons_' . $uid);
            self::writeLog($code, $uid, $amount, 'gc_redeemed');
            if (DB_error()) {
                COM_errorLog("A DB error occurred marking coupon $code as redeemed");
                return array(2, sprintf($LANG_PP['coupon_apply_msg2'], $_CONF['site_email']));
            }
        }
        return array(0, sprintf($LANG_PP['coupon_apply_msg0'], Currency::getInstance()->Format($A['amount'])));
    }


    /**
    *   Apply a coupon value against an order.
    *   Does not update the coupon table, but deducts the maximum of the
    *   coupon balance or the order value from the userinfo table.
    *
    *   @param  float   $amount     Amount to redeem (order value)
    *   @return float               Remaining order value, if any
    */
    public static function Apply($amount, $uid = 0, $Order = NULL)
    {
        global $_TABLES, $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        if ($uid < 2) return 0;
        $coupons = self::getUserCoupons($uid);
        $remain = (float)$amount;
        foreach ($coupons as $coupon) {
            $bal = (float)$coupon['balance'];
            if ($bal > $remain) {
                $bal -= $remain;
                $remain = 0;
            } else {
                $remain -= $bal;
                $bal = 0;
            }
            $code = DB_escapeString($coupon['code']);
            $order_id = '';
            if ($Order !== NULL) {
                $order_id = DB_escapeString($Order->order_id);
            }
            $uid = $Order->uid;
            $sql = "UPDATE {$_TABLES['paypal.coupons']}
                    SET balance = $bal
                    WHERE code = '$code';";
            self::writeLog($code, $uid, $amount, 'gc_applied', $order_id);
            DB_query($sql);
            if ($remain == 0) break;
        }
        Cache::delete('coupons_' . $uid);
        return $remain;     // Return unapplied balance
    }


    /**
    *   Handle the purchase of this item.
    *
    *   @param  object  $Item       Item object, to get options, etc.
    *   @param  object  $Order      Order object
    *   @param  array   $ipn_data   Paypal IPN data
    *   @return integer     Zero or error value
    */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data=array())
    {
        global $LANG_PP;

        $status = 0;
        $amount = (float)$Item->price;
        $special = PP_getVar($Item->extras, 'special', 'array');
        $recip_email = PP_getVar($special, 'recipient_email', 'string');
        $sender_name = PP_getVar($special, 'sender_name', 'string');
        $uid = $Item->getOrder()->uid;
        $gc_code = self::Purchase($amount, $uid);
        // Add the code to the options text. Saving the item will happen
        // next during addSpecial
        $Item->addOptionText($LANG_PP['code'] . ': ' . $gc_code, false);
        $Item->addSpecial('gc_code', $gc_code);

        parent::handlePurchase($Item, $Order);
        self::Notify($gc_code, $recip_email, $amount, $sender_name);
        return $status;
    }


    /**
    *   Send a notification email to the recipient of the gift card.
    *
    *   @param  string  $gc_code    Gift Cart Code
    *   @param  string  $recip      Recipient Email, from the custom text field
    *   @param  float   $amount     Gift Card Amount
    *   @param  string  $sender     Optional sender, from the custom text field
    */
    public static function Notify($gc_code, $recip, $amount, $sender='', $exp=self::MAX_EXP)
    {
        global $_CONF, $LANG_PP_EMAIL;

        if ($recip!= '') {
            PAYPAL_debug("Sending Coupon to " . $recip);
            $T = PP_getTemplate('coupon_email_message', 'message');
            if ($exp != self::MAX_EXP) {
                $dt = new \Date($exp, $_CONF['timezone']);
                $exp = $dt->format($_CONF['shortdate']);
            }
            $T->set_var(array(
                'gc_code'   => $gc_code,
                'sender_name' => $sender,
                'expires'   => $exp,
            ) );
            $T->parse('output', 'message');
            $msg_text = $T->finish($T->get_var('output'));
            COM_emailNotification(array(
                    'to' => array(array('email'=>$recip, 'name' => $recip)),
                    'from' => $_CONF['site_mail'],
                    'htmlmessage' => $msg_text,
                    'subject' => $LANG_PP_EMAIL['coupon_subject'],
            ) );
        }
    }


    /**
    *   Get additional text to add to the buyer's recipt for a product
    *
    *   @param  object  $item   Order Item object, to get the code
    *   @return string          Additional message to include in email
    */
    public function EmailExtra($item)
    {
        global $LANG_PP;
        $code = PP_getVar($item->extras['special'], 'gc_code', 'string');
        $s = '';
        if (!empty($code)) {
            $s = sprintf($LANG_PP['apply_gc_email'],
                PAYPAL_URL . '/index.php?redeem=x&code=' . $code,
                PAYPAL_URL . '/index.php?apply_gc&code=' . $code,
                PAYPAL_URL . '/index.php?apply_gc&code=' . $code);
        }
        return $s;
    }


    /**
    *   Get the display price for the catalog.
    *   Returns "See Details" if the price is zero, or the price if
    *   one is set.
    *
    *   @param  mixed   $price  Fixed price override (not used)
    *   @return string          Formatted price, or "See Details"
    */
    public function getDisplayPrice($price = NULL)
    {
        global $LANG_PP;

        $price = $this->getPrice();
        if ($price == 0) {
            return $LANG_PP['see_details'];
        } else {
            return Currency::getInstance()->Format($price);
        }
    }


    /**
    *   Get all the current Gift Card records for a user
    *   If $all is true then all records are returned, if false then only
    *   those that are not redeemed and not expired are returned.
    *
    *   @param  integer $uid    User ID, default = curent user
    *   @param  boolean $all    True to get all, False to get currently usable
    *   @return array           Array of gift card records
    */
    public static function getUserCoupons($uid = 0, $all = false)
    {
        global $_TABLES, $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        if ($uid < 2) return array();   // Can't get anonymous coupons here

        $cache_key = 'coupons_' . $uid;
        $updatecache = false;       // indicator that cache must be updated
        $coupons = Cache::get($cache_key);
        $coupons = null;
        $today = date('Y-m-d');
        if (!$coupons) {
            $coupons = array();
            $sql = "SELECT * FROM {$_TABLES['paypal.coupons']}
                WHERE redeemer = '$uid'";
            if (!$all) {
                $sql .= " AND expires >= '$today' AND balance > 0";
            }
            $sql .= " ORDER BY redeemed ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $coupons[] = $A;
            }
            $updatecache = true;
        } else {
            // Check the expiration dates in case any expired while in cache
            foreach ($coupons as $idx=>$coupon) {
                if ($coupon['expires'] < $today) {
                    unset($coupons[$idx]);
                    $updatecache = true;
                }
            }
        }

        // If coupons were read from the DB, or any cached ones expired,
        // update the cache
        if ($updatecache) {
            Cache::set($cache_key, $coupons, 'coupons', 3600);
        }
        return $coupons;
    }


    /**
    *   Get the current unused Gift Card balance for a user
    *
    *   @param  integer $uid    User ID, default = current user
    *   @return float           User's gift card balance
    */
    public static function getUserBalance($uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        if ($uid == 1) return 0;    // no coupon bal for anonymous

        // Total up the available balances from the coupons table
        $bal = (float)0;
        $coupons = self::getUserCoupons($uid);
        foreach ($coupons as $coupon) {
            $bal += $coupon['balance'];
        }
        return (float)$bal;
    }


    /**
    *   Verifies that the given user has sufficient Gift Card balances
    *   to cover an amount.
    *
    *   @uses   self::getUserBalance()
    *   @param  float   $amount     Amount to check
    *   @param  integer $uid        User ID, default = current user
    *   @return boolean             True if the GC balance is sufficient.
    */
    public static function verifyBalance($amount, $uid = 0)
    {
        $amount = (float)$amount;
        $balance = self::getUserBalance($uid);
        return $amount <= $balance ? true : false;
    }


    /**
     * Write a log entry
     *
     * @param   string  $code       Gift card code
     * @param   integer $uid        User ID
     * @param   float   $amount     Gift card amount or amount applied
     * @param   string  $msg        Message to log
     * @param   string  $order_id   Order ID (when applying)
     */
    public static function writeLog($code, $uid, $amount, $msg, $order_id = '')
    {
        global $_TABLES;

        $msg = DB_escapeString($msg);
        $order_id = DB_escapeString($order_id);
        $code = DB_escapeString($code);
        $amount = (float)$amount;
        $uid = (int)$uid;

        $sql = "INSERT INTO {$_TABLES['paypal.coupon_log']}
                (code, uid, order_id, ts, amount, msg)
                VALUES
                ('{$code}', '{$uid}', '{$order_id}', UNIX_TIMESTAMP(), '$amount', '{$msg}');";
        DB_query($sql);
    }


    /**
     * Get the log entries for a user ID to show in their account.
     * Optionally specify a gift card code to get only entries
     * pertaining to that gift card.
     *
     * @param   integer $uid    User ID
     * @param   string  $code   Optional gift card code
     * @return  array           Array of log messages
     */
    public static function getLog($uid, $code = '')
    {
        global $_TABLES, $LANG_PP;

        $log = array();
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['paypal.coupon_log']}
                WHERE uid = $uid";
        if ($code != '') {
            $sql .= " AND code = '" . DB_escapeString($code) . "'";
        }
        $sql .= ' ORDER BY ts DESC';
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $log[] = $A;
            }
        }
        return $log;
    }


    /**
     * Mask a gift card code for display in the log.
     * Replaces characters, except symbols, with "X".
     * Leaves the last 4 characters alone for identification if the total
     * string length is > 10 characters, otherwise all characters are replaced.
     *
     * @param   string  $code   Original gift card code
     * @return  string          Masked code
     */
    public static function maskForDisplay($code)
    {
        $len = strlen($code);
        // If the code length is > 10 characters, leave the last 4 alone.
        if ($len > 10) {
            $len -= 4;
        }
        for ($i = 0; $i < $len; $i++) {
            if (ctype_alnum($code[$i])) {
                $code[$i] = 'X';
            }
        }
        return $code;
    }


    /**
     * From a cart, get the total items that can be paid by gift card.
     * Start with the order total and deduct any coupon items.
     *
     * @param   object  $cart   Shopping Cart
     * @return  float           Total payable by gift card
     */
    public static function canPayByGC($cart)
    {
        $gc_can_apply = $cart->getTotal();
        $items = $cart->Cart();
        foreach ($items as $item) {
            $P = $item->getProduct();
            if ($P->isNew || $P->prod_type == PP_PROD_COUPON) {
                $gc_can_apply -= $P->getPrice($item->options, $item->quantity) * $item->quantity;
            }
        }
        if ($gc_can_apply < 0) $gc_can_apply = 0;
        return $gc_can_apply;
    }


    /**
    *   Determine if the current user has access to view this product.
    *   Checks that gift cards are enabled in the configuration, then
    *   checks the general product hasAccess() function.
    *
    *   @return boolean     True if access and purchase is allowed.
    */
    public function hasAccess()
    {
        global $_PP_CONF;

        if (!$_PP_CONF['gc_enabled']) {
            return false;
        } else {
            return parent::hasAccess();
        }
    }


    /**
     * Get the fixed quantity that can be ordered per item view.
     * If this is zero, then an input box will be shown for the buyer to enter
     * a quantity. If nonzero, then the input box is a hidden variable with
     * the value set to the fixed quantity
     *
     * return   @integer    Fixed quantity number, zero for varible qty
     */
    public function getFixedQuantity()
    {
        return 1;
    }


    /**
     * Determine if like items can be accumulated in the cart under a single
     * line item.
     *
     * @return  boolean     True if items can be accumulated, False if not
     */
    public function cartCanAccumulate()
    {
        return false;
    }

}

?>

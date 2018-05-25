<?php
/**
*   Class to handle coupon operations
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.10
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for coupons
*   @package paypal
*/
class Coupon extends Product
{
    const MIN_LENGTH = 8;

    public function __construct($prod_id = 0)
    {
        parent::__construct($prod_id);
        $this->prod_type == PP_PROD_COUPON;

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
    *   @throws Exception
    */
    public static function generate($options = [])
    {
        $length         = (isset($options['length']) ? filter_var($options['length'], FILTER_VALIDATE_INT, ['options' => ['default' => self::MIN_LENGTH, 'min_range' => 1]]) : self::MIN_LENGTH );
        $prefix         = (isset($options['prefix']) ? self::cleanString(filter_var($options['prefix'], FILTER_SANITIZE_STRING)) : '' );
        $suffix         = (isset($options['suffix']) ? self::cleanString(filter_var($options['suffix'], FILTER_SANITIZE_STRING)) : '' );
        $useLetters     = (isset($options['letters']) ? filter_var($options['letters'], FILTER_VALIDATE_BOOLEAN) : true );
        $useNumbers     = (isset($options['numbers']) ? filter_var($options['numbers'], FILTER_VALIDATE_BOOLEAN) : false );
        $useSymbols     = (isset($options['symbols']) ? filter_var($options['symbols'], FILTER_VALIDATE_BOOLEAN) : false );
        $useMixedCase   = (isset($options['mixed_case']) ? filter_var($options['mixed_case'], FILTER_VALIDATE_BOOLEAN) : false );
        $mask           = (isset($options['mask']) ? filter_var($options['mask'], FILTER_SANITIZE_STRING) : false );

        $uppercase    = ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'C', 'V', 'B', 'N', 'M'];
        $lowercase    = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p', 'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'z', 'x', 'c', 'v', 'b', 'n', 'm'];
        $numbers      = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        $symbols      = ['`', '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '=', '+', '\\', '|', '/', '[', ']', '{', '}', '"', "'", ';', ':', '<', '>', ',', '.', '?'];

        $characters   = [];
        $coupon = '';

        if ($useLetters) {
            if ($useMixedCase) {
                $characters = array_merge($characters, $lowercase, $uppercase);
            } else {
                $characters = array_merge($characters, $uppercase);
            }
        }

        if ($useNumbers) {
            $characters = array_merge($characters, $numbers);
        }

        if ($useSymbols) {
            $characters = array_merge($characters, $symbols);
        }
        $charcount = count($characters);

        if ($mask) {
            for ($i = 0; $i < strlen($mask); $i++) {
                if ($mask[$i] === 'X') {
                    $coupon .= $characters[mt_rand(0, $charcount - 1)];
                } else {
                    $coupon .= $mask[$i];
                }
            }
        } else {
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
    *   Strip all characters but letters and numbers
    *
    *   @param  string  $string     Original string
    *   @param  array   $options    Options for string formatting
    *   @return string      Stripped string
    *   @throws Exception
    */
    static private function cleanString($string, $options = array())
    {
        $toUpper = (isset($options['uppercase']) ? filter_var($options['uppercase'], FILTER_VALIDATE_BOOLEAN) : false);
        $toLower = (isset($options['lowercase']) ? filter_var($options['lowercase'], FILTER_VALIDATE_BOOLEAN) : false);

        $stripped = preg_replace('/[^a-zA-Z0-9]/', '', $string);

        // make uppercase
        if ($toLower && $toUpper) {
            throw new Exception('You cannot set both options (uppercase|lowercase) to "true"!');
        } else if ($toLower) {
            return strtolower($stripped);
        } else if ($toUpper) {
            return strtoupper($stripped);
        } else {
            return $stripped;
        }
    }


    /**
    *   Record a coupon purchase
    *
    *   @param  float   $amount     Coupon value
    *   @param  integer $uid        User ID, default = current user
    *   @return mixed       Coupon code, or false on error
    */
    public static function Purchase($amount = 0, $uid = 0)
    {
        global $_TABLES, $_USER;

        $amount = (float)$amount;
        if ($amount == 0) return false;
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        $options = array(
        //    'length'    => 12,    // not used if mask is specified
            'letters'   => true,
            'numbers'   => true,
            'symbols'   => false,   // alphanumeric only
            'mixed_case' => false,  // only upper-case
            'mask'      => 'XXXX-XXXX-XXXX-XXXX',
        );

        $code = self::generate($options);
        while (DB_count($_TABLES['paypal.coupons'], 'code', $code)) {
            // Make sure there are no duplicates
            $code = self::generate($options);
        }
        $code = DB_escapeString($code);
        DB_save($_TABLES['paypal.coupons'],
            'code,buyer,amount', "'$code',$uid,$amount");
        return $code;
    }


    /**
    *   Apply a coupon to the user's account.
    *   Adds the value to the gc_bal field in user info, and marks the coupon
    *   as "redeemed" so it can't be used again.
    *
    *   @param  string  $code   Coupon code
    *   @param  integer $uid    Optional user ID, current user by default
    *   @return string          Message text
    */
    public static function Apply($code, $uid = 0)
    {
        global $_TABLES, $_USER, $_PP_CONF;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        $code = DB_escapeString($code);
        $res = DB_query("SELECT * FROM {$_TABLES['paypal.coupons']}
                WHERE code = '$code'");
        if (DB_numRows($res) == 0) {
            return "Not Found";
        } else {   
            $A = DB_fetchArray($res, false);
            if (!is_null($A['redeemed'])) {
                return "Already Redeemed by {$A['redeemer']} on {$A['redeemed']}";
            }
        }
        $amount = (float)$A['amount'];
        if ($amount > 0) {
            $U = new UserInfo($uid);
            $U->gc_bal = $U->gc_bal + $amount;
            $U->SaveUser();
            DB_query("UPDATE {$_TABLES['paypal.coupons']} SET
                    redeemer = $uid,
                    redeemed = '" . PAYPAL_now()->toMySQL(true) . "'
                    WHERE code = '$code'");
        }
        return 'OK';
    }


    /**
    *   Redeem a coupon value against an order.
    *   Does not update the coupon table, but deducts the maximum of the
    *   coupon balance or the order value from the userinfo table.
    *
    *   @param  float   $amount     Amount to redeem (order value)
    *   @return float               Remaining order value, if any
    */
    public static function Redeem($amount, $uid = 0)
    {
        global $_TABLES, $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $U = UserInfo::getInstance($uid);
        $remain = $amount - $U->gc_bal;
        if ($remain >= 0) {
            // Entire coupon balance applied, may be some order value left
            $U->gc_bal = 0;
        } else {
            // Coupon balance exceeds order value
            $U->gc_bal = $U->gc_bal - $amount;
            $remain = 0;
        }
        $U->SaveUser();
        return $remain;     // Return unapplied balance
    }


    /**
    *   Handle the purchase of this item.
    *   1. Update qty on hand if track_onhand is set (min. value 0)
    *
    *   @param  integer $qty        Quantity ordered
    *   @param  object  $Order      Order object
    *   @param  array   $ipn_data   Paypal IPN data
    *   @return integer     Zero or error value
    */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data=array())
    {
        global $_TABLES, $LANG_PP_EMAIL, $_PP_CONF, $_CONF;

        $status = 0;
        $qty = (int)$Item['quantity'];
        $amount = (float)$Item['price'];
        $item_id = (int)$Item['id'];
        $extras = @json_decode($Item['extras'],true);
        $recip_email = '';
        $sender_name = '';
        if (is_array($extras) && isset($extras['special'])) {
            if (isset($extras['special']['recipient_email'])) {
                $recip_email = $extras['special']['recipient_email'];
            }
            if (isset($extras['special']['sender_name'])) {
                $sender_name = $extras['special']['sender_name'];
            }
        }

        $gc_code = self::Purchase($amount, $Item['user_id']);
        parent::handlePurchase($Item, $Order);

        if ($recip_email != '') {
            PAYPAL_debug("Sending email to " . $recip_email);

            $T = new \Template(PAYPAL_PI_PATH . '/templates');
            $T->set_file('message', 'coupon_email_message.thtml');
            $T->set_var(array(
                'gc_code'   => $gc_code,
            ) );
            $T->parse('output', 'message');
            $msg_text = $T->finish($T->get_var('output'));
            COM_emailNotification(array(
                    'to' => array($recip_email),
                    'from' => $_CONF['site_mail'],
                    'htmlmessage' => $msg_text,
                    'subject' => $LANG_PP_EMAIL['coupon_subject'],
            ) );
        }
        return $status;
    }


    public static function verifyBalance($uid, $amount)
    {
        $amount = (float)$amount;
        $U = UserInfo::getInstance($uid);
        return $amount <= $U->gc_bal ? true : false;
    }
 
}

?>

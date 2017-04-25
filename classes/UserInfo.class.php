<?php
/**
*   Class to handle user account info for the Classifieds plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.5.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal;

/**
*   Class for user info such as addresses
*   @since  0.5.0
*   @package paypal
*/
class UserInfo
{
    /** User ID
    *   @var integer */
    var $uid;

    /** Addresses stored for this user
    *   @var array */
    var $addresses = array();

    private $formaction = NULL;
    private $extravars = array();

    /**
    *   Constructor.
    *   Reads in the specified user, if $id is set.  If $id is zero, 
    *   then the current user is used.
    *
    *   @param  integer     $uid    Optional user ID
    */
    function __construct($uid=0)
    {
        global $_USER;

        $uid = (int)$uid;
        if ($uid < 1) {
            $uid = (int)$_USER['uid'];
        }
        $this->uid = $uid;  // Save the user ID
        $this->ReadUser();  // Load the user's stored addresses
    }


    /**
    *   Read one user from the database
    *
    *   @param  integer $uid Optional User ID.  Current user if zero.
    */
    private function ReadUser($uid = 0)
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid == 0) $uid = $this->uid;
        if ($uid == 0) {
            return;
        }

        $result = DB_query("SELECT * FROM {$_TABLES['paypal.address']} 
                            WHERE uid=$uid");
        while ($A = DB_fetchArray($result, false)) {
            $this->addresses[] = $A;
            //$this->SetAddresses($A);
        }
    }


    /**
    *   Get an address
    *
    *   @param  integer $add_id     DB Id of address
    *   @return array               Array of address values
    */
    public function getAddress($add_id)
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['paypal.address']}
                WHERE id='" . (int)$add_id . "'";
        $A = DB_fetchArray(DB_query($sql), false);
        return $A;
    }


    /**
    *   Get the default billing or shipping address.
    *   If no default found, return the first address available.
    *   If no addresses are defined, return NULL
    *
    *   @param  string  $type   Type of address to get
    *   @return mixed   Address array (name, street, city, etc.), or NULL
    */
    public function getDefaultAddress($type='billto')
    {
        if ($type != 'billto') $type = 'shipto';

        foreach ($this->addresses as $addr) {
            if ($addr[$type.'_def'] == 1)
                return $addr;
        }
        if (isset($this->addresses[0]))
            return $this->addresses[0];
        else
            return NULL;
    }


    /**
    *   Save the current values to the database.
    *
    *   @param  array   $A      Array of data ($_POST)
    *   @param  string  $type   Type of address (billing or shipping)
    *   @return array       Array of DB record ID, -1 for failure and message
    */
    public function SaveAddress($A, $type='')
    {
        global $_TABLES, $_USER;

        // Don't save invalid addresses, or anonymous
        if ($_USER['uid'] < 2 || !is_array($A)) return array(-1, '');
        if ($type != '') {
            if ($type != 'billto') $type = 'shipto';
            $type .= '_';
        }

        $id = isset($A['addr_id']) && !empty($A['addr_id']) ? 
            (int)$A['addr_id'] : 0;

        $msg = self::isValidAddress($A, $type);
        if (!empty($msg)) return array(-1, $msg);

        if ($id > 0) {
            $sql1 = "UPDATE {$_TABLES['paypal.address']} SET ";
            $sql2 = " WHERE id='$id'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['paypal.address']} SET ";
            $sql2 = '';
        }

        $is_default = isset($A['is_default']) ? 1 : 0;
        $sql = "uid = '" . $this->uid . "',
                name = '" . DB_escapeString($A['name']) . "',
                company = '" . DB_escapeString($A['company']) . "',
                address1 = '" . DB_escapeString($A['address1']) . "',
                address2 = '" . DB_escapeString($A['address2']) . "',
                city = '" . DB_escapeString($A['city']) . "',
                state = '" . DB_escapeString($A['state']) . "',
                country = '" . DB_escapeString($A['country']) . "',
                zip = '" . DB_escapeString($A['zip']) . "',
                {$type}def = '$is_default'";
        $sql = $sql1 . $sql . $sql2;
        //echo $sql;die;
        DB_query($sql);
        if ($id == 0) {
            $id = DB_insertID();
        }

        // If this is the new default address, turn off the other default
        if ($is_default) {
            DB_query("UPDATE {$_TABLES['paypal.address']}
                    SET {$type}def = 0
                    WHERE id <> $id AND {$type}def = 1");
        }

        return array($id, '');
    }


    /**
    *   Delete the current user info record from the database
    */
    public function Delete($id)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id < 1) return false;
        DB_delete($_TABLES['paypal.address'], 'id', $this->id);
    }


    /**
    *   Validate the address components
    *
    *   @param  array   $A      Array of parameters, e.g. $_POST
    *   @param  string  $type   Type of address (billing or shipping)
    *   @return string      Invalid items, or empty string for success
    */
    public function isValidAddress($A)
    {
        global $LANG_PP, $_PP_CONF;

        $invalid = array();
        $retval = '';

        if (empty($A['name']) && empty($A['company'])) {
            $invalid[] = 'name_or_company';
        }

        if ($_PP_CONF['get_street'] == 2 && empty($A['address1']))
            $invalid[] = 'address1';
        if ($_PP_CONF['get_city'] == 2 && empty($A['city']))
            $invalid[] = 'city';
        if ($_PP_CONF['get_state'] == 2 && empty($A['state']))
            $invalid[] = 'state';
        if ($_PP_CONF['get_postal'] == 2 && empty($A['zip']))
            $invalid[] = 'zip';
        if ($_PP_CONF['get_country'] == 2 && empty($A['country']))
            $invalid[] = 'country';

        if (!empty($invalid)) {
            foreach ($invalid as $id) {
                $retval .= '<li> ' . $LANG_PP[$id] . '</li>' . LB;
            }
        }

        return $retval;
    }


    /**
    *   Creates the address edit form.
    *   Pre-fills values from another address if supplied
    *
    *   @param  string  $type   Address type (billing or shipping)
    *   @param  array   $A      Optional values to pre-fill form
    *   @return string          HTML for edit form
    */
    public function AddressForm($type='billto', $A=array())
    {
        global $_TABLES, $_CONF, $_PP_CONF, $LANG_PP, $_USER;

        if ($type != 'billto') $type = 'shipto';
        if (empty($this->formaction)) $this->formaction = 'save' . $type;

        $T = new \Template(PAYPAL_PI_PATH . '/templates');
        if ($_PP_CONF['_is_uikit']) {
            $T->set_file('address', 'address.uikit.thtml');
        } else {
            $T->set_file('address', 'address.thtml');
        }

        // Set the address to select by default.  Start by using the one
        // already stored in the cart, if any.
        $addr_id = isset($A['addr_id']) ? $A['addr_id'] : '';
        $count = 0;
        $def_addr = 0;

        $T->set_block('address', 'SavedAddress', 'sAddr');
        foreach($this->addresses as $ad_id => $address) {
            $count++;
            if ($address[$type.'_def'] == 1) {
                $is_default = true;
                $def_addr = $address['id'];
            } else {
                $is_default = false;
            }

            // If this is the default address, or this is the already-stored
            // address, then check it's radio button.
            if ( (empty($addr_id) && $is_default) ||
                    $addr_id == $address['id'] ) {
                $ad_checked = 'checked="checked"';
                $addr_id = $address['id'];
            } else {
                $ad_checked = '';
            }

            $T->set_var(array(
                'id'        => $address['id'],
                'ad_name'   => $address['name'],
                'ad_company' => $address['company'],
                'ad_addr_1' => $address['address1'],
                'ad_addr_2' => $address['address2'],
                'ad_city'   => $address['city'],
                'ad_state'  => $address['state'],
                'ad_country' => $address['country'],
                'ad_zip'    => $address['zip'],
                'ad_checked' => $ad_checked,
            ) );
            $T->parse('sAddr', 'SavedAddress', true);
        }

        $hiddenvars = '';
        foreach ($this->extravars as $var) {
            $hiddenvars .= $var . LB;
        }

        $T->set_var(array(
            'pi_url'        => PAYPAL_URL,
            'billship'      => $type,
            'order_id'      => $this->order_id,
            'sel_addr_text' => $LANG_PP['sel_' . $type . '_addr'],
            'addr_type'     => $LANG_PP[$type . '_info'],
            'allow_default' => $this->uid > 1 ? 'true' : '',
            'have_addresses' => $count > 0 ? 'true' : '',
            'addr_id'   => empty($addr_id) ? '' : $addr_id,
            'name'      => isset($A['name']) ? $A['name'] : '',
            'company'   => isset($A['company']) ? $A['company'] : '',
            'address1'  => isset($A['address1']) ? $A['address1'] : '',
            'address2'  => isset($A['address2']) ? $A['address2'] : '',
            'city'      => isset($A['city']) ? $A['city'] : '',
            'state'     => isset($A['state']) ? $A['state'] : '',
            'zip'       => isset($A['zip']) ? $A['zip'] : '',
            'country'   => isset($A['country']) ? $A['country'] : '',
            'def_checked' => $def_addr > 0 && $def_addr == $addr_id ?
                                'checked="checked"' : '',

            'req_street'    => $_PP_CONF['get_street'] == 2 ? 'true' : '',
            'req_city'      => $_PP_CONF['get_city'] == 2 ? 'true' : '',
            'req_state'     => $_PP_CONF['get_state'] == 2 ? 'true' : '',
            'req_country'   => $_PP_CONF['get_country'] == 2 ? 'true' : '',
            'req_postal'    => $_PP_CONF['get_postal'] == 2 ? 'true' : '',
            'get_street'    => $_PP_CONF['get_street'] > 0 ? 'true' : '',
            'get_city'      => $_PP_CONF['get_city'] > 0 ? 'true' : '',
            'get_state'     => $_PP_CONF['get_state'] > 0 ? 'true' : '',
            'get_country'   => $_PP_CONF['get_country'] > 0 ? 'true' : '',
            'get_postal'    => $_PP_CONF['get_postal'] > 0 ? 'true' : '',

            'hiddenvars'    => $hiddenvars,
            'action'        => $this->formaction,
        ) );

        $T->parse('output','address');
        return $T->finish($T->get_var('output'));

    }


    /**
    *   Provide a public method to set the private formaction variable
    *
    *   @param  string  $action     Value to set as form action
    */
    public function setFormAction($action)
    {
        $this->formaction = $action;
    }


    /**
    *   Add a hidden form value
    *
    *   @param  string  $name   Name of form variable
    *   @param  string  $value  Value of variable
    */
    public function addFormVar($name, $value)
    {
        $this->extravars[] = '<input type="hidden" name="' . $name . 
                '" value="' . $value . '" />';
    }

}   // class UserInfo

?>

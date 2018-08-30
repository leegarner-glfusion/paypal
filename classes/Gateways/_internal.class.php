<?php
/**
*   Class to manage payment by gift card.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    paypal
*   @version    0.6.0
*   @since      0.6.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Paypal\Gateways;

/**
 *  Internal gateway class, just to support zero-balance orders
 */
class _internal extends \Paypal\Gateway
{
    /**
    *   Constructor.
    *   Set gateway-specific items and call the parent constructor.
    */
    public function __construct()
    {
        // These are used by the parent constructor, set them first.
        $this->gw_name = '_internal';
        $this->gw_desc = 'Internal Payment Gateway';
        parent::__construct();
    }

}

?>

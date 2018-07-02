# Paypal
Shopping plugin for glFusion. Supports Paypal and other gateways.

This plugin provides a product catalog and shopping cart for physical
and virtual products. The following payment gateways are supported:
- Paypal
- Authorize.Net

You must sign up with the payment providers and enter your keys in the
plugin configuration. You should also sign up for a developer or
sandbox account for testing.

If you use the Bad Behavior plugin, be sure that you whitelist your Paypal IPN
URL (paypal/ipn/paypal_ipn.php). Bad Behavior may block IPN messages from
Paypal otherwise.

This version of the Paypal plugin requires at least version 0.0.6 of
the lgLib plugin for supporting functions.

## Plugin APIs
Plugins may leverage this plugin to process payments and have their products included in the catalog.
Functions are called via LGLIB_invokeServie(), which is similar to PLG_invokeService() for web services.
Arguments are passed in an array, an "output" variable receives the output, and the return is a standard PLG_RET_* value.

### `service_getprodctinfo_<plugin_name>`
Gets general information about the product for inclusion in the catalog or to determine pricing when processing an order.
```
$args = array(
    // Item ID components
    'item_id' => array(item_id, sub_item1, sub_item2),
    // Item modifiers. May be periodically updated
    'mods'    => array('uid' => current user ID),
);

$output = array(
    'product_id'        => implode(':', $args['item_id'],
    'name'              => 'Product Name or SKU',
    'short_description' => 'Short Product Description',
    'price'             => Unit price
    'override_price' => 1,      // set if the payment price will be accepted as full payment
);
```

### `service_handlePurchase_<plugin_name`
Handles the purchase of the item
```
$args = array(
    'item'  => array(
        'item_id' => $Item->product_id, // Product ID as a string (item:subitem1:subitem2)
        'quantity' => $Item->quantity,  // Quantity
        'name' => $Item->item_name,     // Item name supplied by IPN
        'price' => $Item->price,        // Unit price determined from getproductinfo()
        'paid' => $Item->paid,          // Total amount paid for the line item
    ),
    'ipn_data'  => $ipn_data,   // Complete IPN information array
    'order' => $Order,      // Pass the order object, may be used in the future
 );

$output = array(        // Note: currently not used for plugin items
    'name' => $item['name'],                // Product Name
    'short_description' => $item['name'],   // Short Description
    'price' => (float)$item['price'],   // Unit price
    'expiration' => NULL,       // expiration, for downloads
    'download' => 0,            // 1 if this is a downloadable product
    'file' => '',               // download file
);
```

### `service_addCartItem_paypal()`
This is a function provided by the Paypal plugin to allow other plugins to add their items to the shopping cart.
```
$args = array(
    'item_id'   => Item number string, including plugin name (plugin:item_id:sub1:sub2),
    'quantity'  => Quantity,
    'item_name' => Item Name or SKU,
    'price'     => Unit Price,
    'short_description' => Item Description
    'options'   => Array of product options
    'extras'    => Array of product extras (comments, custom strings)
    'override'  => Set to force the price to the supplied value
    'uid'       => User ID, default Anonymous
    'unique'    => Set if only one of these items should be added to the cart
    'update'    => Array of fields that may be updated if 'unique' is set. e.g. New price
);

$output is not set
```

### `service_formatAmount_paypal()`
Get a currency amount formatted based on the default currency.
```
$args = array(
    'amount' => Floating-point amount
);
//or//
$args = amount

$ouptut contains the formatted string
```

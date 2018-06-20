# Paypal plugin for glFusion - Changelog

## Ver 0.6.0
Released TBD
- Use cookie instead of session to store cart IDs since session changes at login
- Add privacy_export function
- Additional refactoring to support multiple product types
- Support for selling gift cards
- Implement glFusion 1.8.0 caching
- Implement class autoloader, refactor gateway classes
- Change post to get for redirect from Paypal
- Removed unused comment counter field
- Remove configured file download path, use data/paypal/files always.
- Implement gift card sales and redemption.
- Redirect back to catalog if requested product is not available.
- Add order token to further allow and secure anonymous viewers
- Refactor order and IPN processing to better handle tax calculations

## Ver 0.5.11
Released 2017-07-23
- Change zip code field to varchar(40)

## Ver 0.5.10
Released 2016-12-16
- Don&apos;t instantiate a global date object if not needed
- Use TimThumb from lglib plugin to size and display images
- Use AJAX to add items to cart (requires the cart block to be enabled)
- Ensure floating-point numbers are formatted correctly for MySQL
- Includes Sitemap V2 driver
- Add a category selector to the product admin list
- Implement namespace, standardize file and class names
- Handle redirect after add-to-cart via ajax

## Ver. 0.5.9
Released 2016-12-08
- Change upgrade function to use COM_checkVersion() for better version checking
- Add catalog search block
- UIKit templates
- Make sure item short_desc is included in purchase record
- Improve formatting of addresses on printed order
- Make CSS more cascading for product lists
- Add store phone and email configuration items

## Ver. 0.5.8
- Simplify permissions, use just group ID for categories, no product perms
- Remove unused access group field from products
- Add templates for Uikit-based themes
- Remove Amazon Simple Pay
- Enable custom text fields for products, e.g. engraving information
- Save option and custom string text in the purchase records for display
- Enable sale pricing with dates for products
- Enable availability dates for products
- Add Paypal SSL postback endpoints (ipnpb.paypal.com)

## Ver. 0.5.7
- Add quantity-based item discounts
- Allow custom text fields for products, e.g. monogram or engraving info
- Change Paypal IPN to use cart fields for product info
- Fully implement new template types for product list and detail view
- Add paypal_cat autotag. Usage: [paypal_cat:ID Optional Text]

## PayPal - 0.4.5 (Released 2010-06-02)
- 0000603: [General] Add a method for anonymous buyers to download files
- 0000605: [Catalog] Add product options or attributes
- 0000679: [Paypal Interface] IPN handler is not stripping slashes when magic_gpc_quotes is on

## PayPal - 0.4.3 ( Released 2010-01-30)
Fixes several issues with buttons not working under IE, and problems with IPN
messages when magic_quotes_gpc is on
- 0000597: [General] Missing description field in the purchases table for new installations
- 0000598: [General] Purchased files cannot be downloaded from the catalog
- 0000599: [Paypal Interface] Paypal's custom field is not handled properly if magic_quotes_gpc is on
- 0000600: [General] Add default product expiration time to the configuration

## PayPal - 0.4.2 (Released 2010-01-28)
Fixed problem with IE unable to save products
- 0000596: [Catalog] Cannot save product records with IE
- 0000591: [Catalog] Enable or disable ratings per product
- 0000592: [Catalog] Comments always enabled for new products despite global config

## PayPal - 0.4.1
Misc. additional features. Also added a debugging option to the main configuration to help troubleshoot the difficulty that some users have reported when saving new products.
- 0000588: [Catalog] Add new "Other Virtual" product type
- 0000583: [General] Add dataproxy driver
- 0000582: [Catalog] Comments-enabled flag isn't saved when saving a product record.

## Version 0.4.0
- Moved the configuration from config.php to online configuration
- Added support for creating encrypted PayPal buttons.
- Added product category table for more structured category assignments.
- Added donation support.
- Added support for multiple images uploaded with the product record.
- Allow selection of file or upload of new one.
- Added support for physical products.  Added new "weight" field for shipping.
  Shipping can be set up in the PayPal account profile.
- Added "taxable" field to override PayPal-profile tax setup per item.
- Expanded currency support to all PayPal-supported currencies.
- Added selection of button types per product (buy now, add to cart, etc).
- Added support for plugin-supplied products.  Added API functions to allow
- plugins to generate encrypted and plain buttons.
- Enhanced the IPN handler to notify plugins of purchases related to them.
- Integrated with glFusion's site search, added keyword field to products.
- Added user comment support to products.  Enabled by default.
- Added user ratings support to products (glFusion 1.1.7 or later only).
- Added purchase notifications to admin.
- Revised the user interface and product catalog using tabs and admin lists.
- Added slimbox for viewing expanded thumbnail images.
- Added blocks for random, popular and featured products.

## Version 0.3.2
- Fixed issue where advanced editor would not allow image uploads.
- Fixed security issue where parameters were not properly escaped prior to
  being used in SQL calls.

## Version 0.3.1
- Implemented glFusion auto install support

## Version 0.3.0
- Port to glFusion

## Version 0.2
- Added support for auto tags "tags". [Samuel Ken]
- Added support for the "advanced editor": FCKEditor [Gary Moncrieff]
- Added support for categories.
- Added ability to selectively order product list
- Added support for currencies beyond US Dollars. The plugin now supports any
  one currency that is also supported by Paypal.
- Added support for encrypted postbacks to paypal for IPN verification,
  previously only unencrypted verification was supported.
- Added ability to upgrade database from the install.php script in the /admin
  directory.  Included upgrade instructions in docs/upgrade.
- Added some additional plugin API functions to functions.inc for
  a tighter integration of functionality.
- The paypal plugin now supports purchases of quantity > 1.  For digital items,
  purchasing more than one item doesn't get you anything more than a single
  purchase by default.  This behavior can be overridden in IPN.class.php.
- handleFailure function added to BaseIPN.class.php which is called in the case
  of a failure during processing.  This can be overridden in IPN.class.php.
- Created a function to generate button html (paypal_genButton), no interface
  as of yet
- Marked required fields on product edit form and now check that all required
  fields are completed.
- Removed 'COMMENT' fields from DDL to support older versions of MySQL
- Fully support Anonymous purchases
- Added option to send out email upon completed purchase, including option to
  attach purchased "downloads"
- Made IPN.class.php extend BaseIPN.class.php so users could customize
  IPN.class.php without worrying about merging upgrades.
- Required fields are now marked as such.  SQL errors will no longer results
  when required fields are missing.
- When pictures are specified, will use the CSS style display: none to hide
  image tags on user pages.
- Added Google like pagination to the products page

## Version 0.1.1
- Added INSTALL instructions to fix bug in downloader.class.php for 1.3.11
- Fixed typo in allowedextensions config option
- Changed default paypal_url to www.paypal.com from www.sandbox.paypal.com
- Fixed malformed sql string when txn_id specified in admin/purchase_history.php
- Configuration option added to allow anonymous users to purchase
  - no email, no download currently available
- Fixed bug that under strange condiitions showed download link instead of
  puchase buttons to logged in users

## Version 0.1
- Security improvements for anonymous users
- No purchases necessary for free ($0.00) products
- Minor template/documentatino tweaks

## Version 0.1rc1
- Initial Rlease

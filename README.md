paypal
======

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


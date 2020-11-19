=== WooCommerce Credit Card Offline Gateway ===

 - Contributors: Michele Vomera
 - Tags: woocommerce, payment gateway, gateway, manual payment, credit card
 - Donate link
 - Requires at least: 3.8
 - Tested up to: 4.3
 - Requires WooCommerce at least: 4.6.0 
 - Tested WooCommerce up to: 4.6.0 
 - Stable Tag: 1.0.0
 - License: GPLv3
 - License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatically generate WooCommerce product SKUs from the product slug and/or variation attributes.

== Description ==

> **Requires: WooCommerce 4.6.0+**

This plugin clones the Cheque gateway to create a credit card offline payment method.
The data of the credit card will be stored in custom fields that can be created using the plugin [Advanced Custom Fields](https://it.wordpress.org/plugins/advanced-custom-fields/)

When an order is submitted via the Credit Card Offline payment method, the order will be placed "on-hold".

= More Details =


== Installation ==

1. Be sure you're running WooCommerce 4.6.0 + in your shop.
2. You can: (1) upload the entire `woocommerce-creditcard-offline-gateway` folder to the `/wp-content/plugins/` directory, (2) upload the .zip file with the plugin under **Plugins &gt; Add New &gt; Upload**
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Checkout** and select "Credit Card Offline" to configure

== Frequently Asked Questions ==

**What is the text domain for translations?**
The text domain is `wc-gateway-offline`.

**Can I fork this?**
Please do! This is meant to be a simple starter offline gateway, and can be modified easily.

== Changelog ==

= 2020.10.20 - version 1.0.0 =
 * Initial Release
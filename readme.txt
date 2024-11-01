=== Shoppingtail WooCommerce  ===
Contributors: maxmst
Tags: woocommerce, e-commerce, sharing, tracking, affiliate, advertising
Requires at least: 4.4
Tested up to: 4.5.3
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Shoppingtail Integration plugin for WooCommerce.

== Description ==

This plugin enables you to connect your WooCommerce store to Shoppingtail.
The plugin will transmit information about orders that come in through Shoppingtail
campaigns. This is required for conversions to be registered so that people who share
your campaigns can get paid.

No identifying customer information is collected. The information transmitted is:

* Order ID
* Order completion timestamp
* Tracking ID of a Shoppingtail Link
* Currency of the order
* List of items purchased
* Order creation timestamp
* Order modification timestamps

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Enter a valid API key from the Shoppingtail App under WooCommerce->Settings->Integration->Shoppingtail API key.

== Changelog ==

= 1.0.2 =

Tracking cookies live longer.
New links now overwrite existing cookie.
Use latest API.
Expose API key hmac signature and plugin version in meta tags.

= 1.0.1 =

Fix a bug causing warning message "The plugin generated X characters of unexpected output".

= 1.0 =

First version.

=== Qliro One for WooCommerce ===
Contributors: krokedil
Tags: woocommerce, qliro, ecommerce, e-commerce, checkout
Donate link: https://krokedil.com
Requires at least: 5.9
Tested up to: 6.1.1
Requires PHP: 7.0
WC requires at least: 4.0.0
WC tested up to: 7.3.0
Stable tag: 0.4.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html


== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Qliro settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/qliro-one-for-woocommerce/).

== Changelog ==
= 2023.02.13    - version 0.5.0 =
* Feature       - Added support for Table Rate Shipping plugin.
* Fix           - Fixed an issue caused by relying on the WooCommece cart hash if we should update an order to Qliro or not. This caused issues with anything that did not change the totals or the contents of the cart.
* Fix           - Fixed so the Qliro order id is logged with each request, making log parsing easier.
* Enhancement   - The Qliro HTML snippet is now stripped from the request logs.
* Enhancement   - Add order note to WooCommerce orders when a Qliro order has been placed that contain the Qliro order id.

= 2023.01.31    - version 0.4.0 =
* Feature       - Added support for OnHold callbacks from Qliro.
* Tweak         - Minor change to style to make the order review take up the space its allowed to take up
* Fix           - Fixed an issue with adminapi endpoints containing a extra backslash causing 404 errors.
* Fix           - Fixed sending a incorrect field name for MinimumCustomerAge.
* Fix           - Fixed some error handling on the order confirmation page.

= 2022.06.21    - version 0.3.1 =
* Fix           - Fixed an issue with order management callbacks. The confirmation string was not being passed in the URL for the callback.

= 2022.05.23    - version 0.3.0 =
* Feature       - Add support for the Post Purchase Upsell for WooCommerce plugin.
* Tweak         - Changed the ID of the order review in our checkout template.

= 2022.03.22    - version 0.2.1 =
* Tweak         - Readme changes.

= 2022.03.22    - version 0.2.0 =
* Tweak         - Readme changes.

= 2022.03.22    - version 0.1.0 =
* Initial release.

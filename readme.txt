=== Qliro One for WooCommerce ===
Contributors: krokedil
Tags: woocommerce, qliro, ecommerce, e-commerce, checkout
Donate link: https://krokedil.com
Requires at least: 5.9
Tested up to: 6.6.1
Requires PHP: 7.4
WC requires at least: 5.0.0
WC tested up to: 9.2.0
Stable tag: 1.3.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html


== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Qliro settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/qliro-one-for-woocommerce/).

== Changelog ==
= 2024.08.20    - version 1.3.1 =
* Fix           - Sometimes the session ID would be missing, this would result in a critical error in the AJAX when attempting to retrieve the order from Qliro. This has now been fixed.
* Fix           - Fixed an issue due to undefined variable in the JavaScript.

= 2024.07.08    - version 1.3.0 =
* Feature       - Support for integrated shipping with nShift using the Krokedil Shipping Connector plugin.
* Enhancement   - The shipping methods in WooCommerce will be hidden until Qliro has retrieved the selected shipping option from Qliro when showing shipping options in the Qliro iFrame.
* Enhancement   - Scoped the plugin dependencies for the plugin to avoid conflicts with other plugins using the same dependencies with different versions.
* Fix           - Fixed support for syncing the area returned by Qliro with the region field in WooCommerce.
* Fix           - Fixed an issue where some plugins used the same dependencies as Qliro but with different versions, causing a conflict and potential PHP errors.

= 2024.03.19    - version 1.2.0 =
* Feature       - The plugin now supports WooCommerce's "High-Performance Order Storage" ("HPOS") feature.
* Tweak         - Adjusted the timeout timer (4.5 → 29 seconds).
* Fix           - Resolved string interpolation deprecation warning.

= 2024.02.29    - version 1.1.1 =
* Enhancement   - Adds a log message when a timeout occurs during the order creation process.

= 2024.01.22    - version 1.1.0 =
* Feature       - Adds support for delivery date (date start + date end) in shipping option displayed in checkout.
* Tweak         - Adds filter qliro_one_shipping_option so other plugins can hook into shipping option about to be sent to Qliro.
* Tweak         - Do not put the order to on-hold during callback handling if it already has been processed. Prevents possible issues where Trustly payments triggers two callbacks with different status simultaneously.
* Fix           - Fix undefined index warning.

= 2023.10.19    - version 1.0.1 =
* Tweak         - Adds timer to allow max 4.5 seconds for order creation in Woo when customer completes purchase in Qliro One. If time limit exceeds, the purchase is denied. Avoids payments in Qliro where the order is missing in Woo.

= 2023.10.16    - version 1.0.0 =
* Feature       - Adds support for sending pickup points to Qliro when displaying shipping methods in the Qliro One checkout. Needs to be supported by the individual shipping method.
* Enhancement   - Improved logging related to events during checkout process, when customer places the order.
* Enhancement   - Improved logging related to order management & callbacks.

= 2023.08.01    - version 0.6.4 =
* Fix           - Resolved an issue where the currency was incorrectly fetched from the store settings instead of the available order currency. This issue led to discrepancies when using a multi-currency plugin.

= 2023.06.14    - version 0.6.3 =
* Fix           - Fixed issues with Table Rate shipping caused by shipping settings introduced in 0.6.0. If you personalized the shipping section using the feature added in 0.6.0 you may need to reapply those settings.

= 2023.06.14    - version 0.6.2 =
* Fix           - Updated our JavaScript to no longer use the deprecated getOrderUpdates function from Qliros JavaScript API, but rather use the onOrderUpdated function. Resolves issues with postcode based shipping.

= 2023.06.09    - version 0.6.1 =
* Fix           - Fixed an undefined index notice that happened due to missing a default value.

= 2023.06.08    - version 0.6.0 =
* Feature       - You now have the ability to personalize the shipping section on the checkout page by using shipping labels. You can find these settings by navigating to WooCommerce → Shipping → Select a shipping method.
* Feature       - You now have the option to show a brief introductory text in the shipping section of the payment form on the checkout page.
* Feature       - You now have the convenience of placing a payment widget on the product page or any other location using the shortcode 'qliro_one_payment_widget'.
* Feature       - You are now able to place a banner widget on the shopping cart page or anywhere else using the shortcode 'qliro_one_banner_widget'.
* Fix           - We've resolved an issue where a JavaScript error would be triggered if a customer fails to identify themselves using the PNO after having used it for identification previously.
* Tweak         - We have reorganized the settings page of Qliro One to streamline navigation and improve user experience.
* Enhancement   - We have incorporated more detailed error messages to provide better clarity in case an order management action is unsuccessful.

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

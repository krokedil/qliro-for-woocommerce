=== Qliro for WooCommerce ===
Contributors: krokedil
Tags: woocommerce, qliro, ecommerce, e-commerce, checkout
Donate link: https://krokedil.com
Requires at least: 5.9
Tested up to: 6.8.1
Requires PHP: 7.4
WC requires at least: 5.0.0
WC tested up to: 9.9.3
Stable tag: 1.11.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html


== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Qliro settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/qliro-one-for-woocommerce/).

== Changelog ==
= 2025.06.12    - version 1.11.1 =
* Fix           - Pay for order should now be working as expected.
* Fix           - Fixed a potential fatal error that could occur if dependencies failed to install properly.
* Fix           - Fixed the province data sometimes being incorrectly set on the order confirmation page.
* Tweak         - Added the option to include a merchant integrity policy URL link in the Qliro iframe.
* Tweak         - Readme changes.

= 2025.05.20    - version 1.11.0 =
* Feature       - Added support for adding a return fee when making a refund on a Qliro order.
* Feature       - Added a setting to automatically calculate a return fee for Qliro orders, if a refund is made when the full amount of a order line is not refunded.
* Feature       - Added a setting to display a country selector on the checkout page or through the shortcode 'qliro_country_selector'. 
* Enhancement   - Changed so the refund with Qliro button is not shown for orders that have not been completed with Qliro, preventing the refund from causing a API error when it can't be made.
* Enhancement   - Improved logging to handle situations where the remote requests returns WP_Error that is not properly logged.
* Tweak         - The sync order button will now always be available on the order edit page for all Qliro orders regardless of payment method.
* Tweak         - Checks if the order was paid with Qliro before showing the partial capture button on the order edit page.
* Tweak         - Removed pending, refunded and failed order statuses from part of the statuses in the order management where it is not relevant.
* Tweak         - Relabeling from "Qliro One" to "Qliro".

= 2025.04.28    - version 1.10.1 =
* Fix           - Fixed an undefined array key warning.
* Fix           - Added 'Tax status' setting for shipping method to be compatible with WooCommerce 9.7+.

= 2025.04.14    - version 1.10.0 =
* Feature       - Added compatibility with the 'WooCommerce PostNord Shipping' plugin by Redlight Media.
* Fix           - Fixed country sometimes being set to null in checkout, when restricting selling locations.
* Fix           - Limited the max size of a log message from the frontend to 1000 characters to prevent large logs from being created.

= 2025.04.09    - version 1.9.2 =
* Fix           - Include cart hash when testing if we need to update a Qliro session or not to catch changes that effects products but not the order total.
* Fix           - Add a check when reading the Qliro order before submitting the WooCommerce order to ensure the Qliro order has not already been completed.

= 2025.04.07    - version 1.9.1 =
* Fix           - Fixed a critical error that could occur when the merchant reference for fees was too long or included special characters.
* Fix           - Fixed an undefined array key warning.
* Tweak         - Improved logging by keeping track of user sessions with unique IDs.

= 2025.03.25    - version 1.9.0 =
* Feature       - Added 'Type' (discount, fee, shipping) to order lines.
* Fix           - Fixed an issue where free shipping coupons did not always update the order in checkout.
* Fix           - Fixed an issue where a removed coupon sometimes remained in the order.
* Fix           - Fixed an issue where refunds sometimes failed for pickup point orders.
* Fix           - Fixed 'MerchantReference' being incorrectly set for fees.
* Fix           - Fix so that negative fees are set as discounts.

= 2025.03.17    - version 1.8.1 =
* Fix           - Fixed an undefined array key warning.
* Fix           - Fixed a critical error that could occur while editing the checkout page.

= 2025.02.17    - version 1.8.0 =
* Feature       - Added support for gift cards.
* Feature       - Added support for subscriptions.
* Feature       - Added support for pay for order.
* Feature       - Added support for gift cards.
* Feature       - Added 'qoc_order_confirmed' to enable newsletter support, together with other custom actions when a Qliro order is confirmed.
* Tweak         - Allow order status 'Completed' if order is captured through portal.
* Tweak         - Added redirect to 'Thank you' page if order is already completed, but user is still on the checkout page.
* Fix           - Fixed PHP 8.3.0 array_sum warnings.
* Fix           - Fixed missing address data from Qliro API during update.
* Fix           - Fixed issue with unsetting other shipping methods.

= 2025.01.22    - version 1.7.3 =
* Fix           - Fixed an issue where we would print Qliro error messages on API calls to WooCommerce on Qliro orders in some cases, causing the response to not be a valid JSON output. This could cause issues when other services tried to for example set the order status on an order placed with Qliro.

= 2024.12.13    - version 1.7.2 =
* Enhancement   - Improved the error handling when placing an order in WooCommerce when the session from Qliro has expired or is missing in WooCommerce, which would cause a timeout error.
* Enhancement   - When matching Ingrid shipping tax rates to WooCommerce, allow a diff of 0.1 when comparing the tax rates to avoid rounding discrepancies between the two systems. This will prevent the wrong tax rate from being used when calculating the shipping tax in WooCommerce.
* Fix           - Fixed an issue when refunding an order line without specifying the order line quantity causing a division by zero error.
* Fix           - Fixed an issue when refunding a shipment order line with the Ingrid integration, where metadata from the order line was not copied over to the refund order line.
* Fix           - Fixed trying to access a setting before it has been saved, causing a PHP warning.

= 2024.11.19    - version 1.7.1 =
* Fix           - Fixed an issue where an incorrect shipment reference was being used for Instabox integrated shipping in order management requests.

= 2024.11.13    - version 1.7.0 =
* Feature       - Added support for shipping with Ingrid.

= 2024.11.12    - version 1.6.0 =
* Feature       - Added support for partial capture.
* Feature       - Added the 'qliro_one_enforced_juridical_type' filter for modifying the name of the cookie that refers to the customer type.
* Tweak         - Tweaked the metabox's design.

= 2024.11.11    - version 1.5.1 =
* Fix           - Fixed not handling Completed Qliro orders correctly if the customer landed back on the checkout page without the confirmation step being completed. The customer will now be redirected to a thankyou page for their order.

= 2024.10.15    - version 1.5.0 =
* Feature       - Added the ability to flag all products as high-risk through plugin settings or individually at the product level. Flagged products may disable certain payment methods.
* Tweak         - Enhanced compatibility with currency switchers by initializing a new session when the currency changes.
* Tweak         - Adjusted the styling of the "Sync Order with Qliro" button.
* Tweak         - Enabled the plugin to handle zero-sum orders, with an option to override via the 'qliro_check_if_needs_payment' filter.
* Fix           - Resolved a critical error caused by the logger.

= 2024.09.11    - version 1.4.0 =
* Feature       - Added a metabox to Qliro order pages to show information about the Qliro order in WooCommerce.
* Feature       - Added a toggle to detach specific orders from the automatic order management. This is useful if you want to manually handle specific orders in WooCommerce.
* Feature       - Added a sync order with Qliro button that allows you to manually sync an order with Qliro if any changes have been made to it in WooCommerce. This is not available for card payments.
* Fix           - Fixed support for Finish localization by passing it correctly to Qliro's API.
* Fix           - Fixed not properly clearing the current session with Qliro when the currency is changed in the store.

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

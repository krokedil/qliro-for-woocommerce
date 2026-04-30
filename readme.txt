=== Qliro for WooCommerce ===
Contributors: krokedil
Tags: woocommerce, qliro, ecommerce, checkout, payment-gateway
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.4
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Qliro Checkout payment gateway for WooCommerce. Accept payments via Qliro in your WooCommerce store.

== Description ==

Qliro for WooCommerce connects your WooCommerce store to the Qliro Checkout. Once configured, customers can complete their purchase using the payment methods offered by Qliro, such as invoice, partial payment, card, and direct bank transfers, directly from your checkout page.

The plugin replaces the standard WooCommerce checkout with the embedded Qliro Checkout iframe, and synchronises the WooCommerce order with Qliro throughout the order lifecycle, including capture, cancel, and refund.

= Features =

- Embedded Qliro Checkout on the WooCommerce checkout page.
- Order management from the WooCommerce admin: capture, cancel, and refund orders in Qliro.
- Partial capture and partial refund support.
- Support for WooCommerce Subscriptions, including renewal orders.
- Support for pay for order.
- Support for gift cards.
- Support for integrated shipping with Ingrid and nShift via the Krokedil Shipping Connector.
- Support for High-Performance Order Storage (HPOS).
- Country selector for multi-market stores.
- Payment widget and banner widget shortcodes for product and cart pages.

= Requirements =

- WooCommerce 5.0 or higher.
- PHP 7.4 or higher.
- A merchant agreement with Qliro. Visit [qliro.com](https://www.qliro.com/) to sign up.

= Documentation =

Setup instructions and reference material are available in the [plugin documentation](https://docs.krokedil.com/qliro-one-for-woocommerce/).

== Installation ==

1. Upload the plugin folder to the "/wp-content/plugins/" directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to WooCommerce Settings -> Payment Gateways and configure your Qliro settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/qliro-one-for-woocommerce/).

== Frequently Asked Questions ==

= Where do I get a Qliro merchant account? =

You need to sign a merchant agreement with Qliro. Visit [qliro.com](https://www.qliro.com/) to get started.

= Where can I find the documentation? =

The plugin documentation is available at [docs.krokedil.com/qliro-one-for-woocommerce](https://docs.krokedil.com/qliro-one-for-woocommerce/).

= Does the plugin work with WooCommerce Subscriptions? =

Yes. The plugin supports recurring payments via WooCommerce Subscriptions, including renewals, switches, and resubscriptions.

= Does the plugin support High-Performance Order Storage (HPOS)? =

Yes. The plugin is compatible with WooCommerce's High-Performance Order Storage feature.

= Where do I report a bug or request a feature? =

Please open an issue in the [GitHub repository](https://github.com/krokedil/qliro-one-for-woocommerce/issues).

= Where do I get support? =

For support related to the plugin, contact [Krokedil](https://krokedil.com/support/). For support related to your Qliro merchant account, contact Qliro.

== Screenshots ==

1. The Qliro Checkout displayed on the WooCommerce checkout page.
2. The Qliro settings page in the WooCommerce admin.
3. The Qliro order metabox on the WooCommerce order edit page.

== Changelog ==

The full changelog is available at [github.com/krokedil/qliro-one-for-woocommerce/blob/master/CHANGELOG.md](https://github.com/krokedil/qliro-one-for-woocommerce/blob/master/CHANGELOG.md).

= 2.2.4 =
Released 2026-04-07.

- Tweak - Added a locking mechanism in the confirmation step to prevent a race condition that could lead to an order being processed more than once.
- Tweak - Added preauthorization processing for subscription renewal. This puts a renewal order on-hold until it has been confirmed by Qliro for further processing.
- Fix - Prevent the order from entering an invalid needs payment state when the 'qliro_check_if_needs_payment' filter is active and set to false.

= 2.2.3 =
Released 2026-03-18.

- Enhancement - Extended logging in callback handling card tokenization for subscription.
- Enhancement - Adds Matkahuolto as a selectable shipping Brand for Qliro shipping method instance settings.
- Tweak - The chosen payment option is now shown in the subscription's payment method description.
- Tweak - Fixes error message not shown during payment processing due to WC changes.
- Fix - Fixed a critical error when attempting to retrieve a deleted payment token on subscription renewal.

= 2.2.2 =
Released 2026-02-16.

- Fix - Fixed renewal failing for virtual, downloadable subscriptions by defaulting the shipping address to the billing address when it was missing.
- Fix - Fixed an issue where integrated shipping could become out of sync with the WC pick-up point selector.

= 2.2.1 =
Released 2026-02-10.

- Fix - Prevented a visible "0" from appearing in the shipping section when pickup point coordinates are zero.
- Fix - Fixed an issue where reopening a payment link for an order pay order could fail with an existing Qliro order error.

= 2.2.0 =
Released 2026-01-22.

- Feature - Add support for adding VAT rate to order discounts applied to the Qliro order through the admin page.
- Enhancement - Updated the design of the modal for the discount.
- Enhancement - Added more error messages for when a discount fails to be applied to a Qliro order.
- Fix - Fixed a few incorrect function names leftover from the release on WordPress.org.
- Fix - Fixed an issue that could cause a callback from Qliro to cancel an order when applying a discount to it.
- Tweak - Cancel requests are no longer triggered for orders that have already been refunded.

= 2.1.0 =
Released 2025-12-18. Bumped WordPress version to be compatible with version 1.18.1.

= 2.0.0 =
Released 2025-12-18. Initial release of Qliro for WooCommerce on wordpress.org, compatible with version 1.15.0.

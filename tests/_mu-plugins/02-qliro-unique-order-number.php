<?php
/**
 * Plugin Name: Qliro Tests, unique order numbers
 * Description: Suffixes order numbers so no two runs hand Qliro the same merchant reference. Loaded only inside the Codeception test WP install.
 *
 * WPDb restores the dump before every test, so WooCommerce hands out the same order ids
 * run after run, and Qliro refuses a merchant reference its test merchant has seen before
 * ("UPDATE_MERCHANT_REFERENCE_NOT_SUPPORTED"), which leaves every purchase unconfirmed.
 * The order key is random per order and stored with it, so the number stays stable.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter(
    'woocommerce_order_number',
    static function ($number, $order) {
        $key = $order instanceof WC_Order ? (string) $order->get_order_key() : '';

        return $key === '' ? $number : $number . '-' . substr($key, -6);
    },
    10,
    2
);

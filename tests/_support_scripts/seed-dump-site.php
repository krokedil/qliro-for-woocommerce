<?php
/**
 * Seeds the freshly installed tests/_wordpress site that build-dump.sh exports:
 * plugins, theme, store options, the checkout page, one product and placeholder Qliro
 * settings. Boots WordPress itself, so WP-CLI is not needed.
 */

$root = dirname(__DIR__, 2);

$_SERVER['HTTP_HOST']   = 'localhost:5512';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

// No cron request to the running built-in server: it would race the export, and serves
// the site with the real admin password.
define('DISABLE_WP_CRON', true);

require $root . '/tests/_wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// The dump is built per run and gitignored, so it carries the real test password and
// credentials, which DumpBuilder passes in from tests/.env.
$admin = get_user_by('login', 'admin');
wp_set_password(getenv('WORDPRESS_ADMIN_PASSWORD') ?: 'password', $admin->ID);

// Dismissed the way an admin would: WooCommerce's "Cha-ching! Order number one" modal
// covers the first order screen of every test, and the compression test is one more
// admin-ajax round trip per screen.
update_user_meta($admin->ID, '_wc_egg_opted_out', '1');
update_site_option('can_compress_scripts', 0);
update_option('blogname', 'Qliro Checkout Test');

update_option('permalink_structure', '/%postname%/');

foreach (['woocommerce/woocommerce.php', 'qliro-for-woocommerce/qliro-for-woocommerce.php'] as $file) {
    $result = activate_plugin($file);
    if (is_wp_error($result)) {
        fwrite(STDERR, "Could not activate {$file}: " . $result->get_error_message() . "\n");
        exit(1);
    }
}

switch_theme('storefront');

foreach ([
    'woocommerce_default_country'  => 'SE',
    'woocommerce_currency'         => 'SEK',
    'woocommerce_store_address'    => 'Test Street 1',
    'woocommerce_store_city'       => 'Stockholm',
    'woocommerce_store_postcode'   => '11122',
    'woocommerce_calc_taxes'       => 'yes',
    'woocommerce_admin_install_timestamp' => time(),
    // Fresh installs launch in "coming soon" mode, which hides the shop from a logged-out browser.
    'woocommerce_coming_soon'      => 'no',
] as $option => $value) {
    update_option($option, $value);
}
delete_transient('_wc_activation_redirect');

// The Qliro checkout replaces the shortcode checkout, not the checkout block.
$checkout_id = wc_get_page_id('checkout');
if ($checkout_id <= 0) {
    fwrite(STDERR, "Checkout page not found.\n");
    exit(1);
}
wp_update_post(['ID' => $checkout_id, 'post_content' => '[woocommerce_checkout]', 'post_status' => 'publish']);

$terms_id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Terms and conditions', 'post_name' => 'terms']);
update_option('woocommerce_terms_page_id', $terms_id);

$product = new WC_Product_Simple();
$product->set_name('Test Product');
$product->set_slug('test-product');
$product->set_regular_price('100');
$product->set_status('publish');
$product->save();

// Every setting at its default, the way a saved store carries them.
require_once $root . '/classes/class-qliro-one-fields.php';
$settings = [];
foreach (Qliro_One_Fields::fields() as $key => $field) {
    if (! in_array($field['type'] ?? 'text', ['title', 'krokedil_section_start', 'krokedil_section_end'], true)) {
        $settings[$key] = $field['default'] ?? '';
    }
}
update_option(
    'woocommerce_qliro_one_settings',
    array_merge(
        $settings,
        [
            'enabled'         => 'yes',
            'testmode'        => 'yes',
            'logging'         => 'yes',
            'test_api_key'    => (string) getenv('QLIRO_TEST_API_KEY'),
            'test_api_secret' => (string) getenv('QLIRO_TEST_API_SECRET'),
        ]
    )
);

// HPOS, as a fresh install has it. WooCommerce creates the tables in a deferred job, which
// with cron off never runs before the export.
wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class)->create_database_tables();
update_option('woocommerce_custom_orders_table_enabled', 'yes');

flush_rewrite_rules(true);

echo "Seeded the dump site.\n";

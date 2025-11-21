<?php
/**
 * Main assets file.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Assets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qliro_One_Assets class.
 */
class Qliro_One_Assets {

	/**
	 * True if inside WordPress administration interface.
	 *
	 * @var bool
	 */
	public $admin_request;

	/**
	 * INB_Assets constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'qoc_load_js' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'qoc_load_css' ) );
		add_action( 'admin_init', array( $this, 'register_admin_assets' ) );

		// Admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_order_script' ) );
	}

	/**
	 *
	 * Checks whether a SCRIPT_DEBUG constant exists.
	 * If there is, the plugin will use minified files.
	 *
	 * @return string
	 */
	protected function qoc_is_script_debug_enabled() {
		// return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		return '';
	}

	/**
	 * Loads scripts for the plugin.
	 */
	public function qoc_load_js() {
		$settings = get_option( 'woocommerce_qliro_one_settings', array() );
		if ( ! isset( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
			return;
		}
		// If we are not on the checkout page, or we are on the order received page, or the pay for order page.
		if ( ! is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		$script_version               = $this->qoc_is_script_debug_enabled();
		$src                          = QLIRO_WC_PLUGIN_URL . '/assets/js/qliro-one-for-woocommerce' . $script_version . '.js';
		$dependencies                 = array( 'jquery' );
		$standard_woo_checkout_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_country',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_company',
			'terms',
			'terms-field',
			'_wp_http_referer',
		);
		$pay_for_order                = false;
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$pay_for_order = true;
		}
		wp_register_script( 'qliro-one-for-woocommerce', $src, $dependencies, QLIRO_WC_VERSION, false );

		wp_localize_script(
			'qliro-one-for-woocommerce',
			'qliroOneParams',
			array(
				'isEnabled'                   => $settings['enabled'],
				'shipping_in_iframe'          => $settings['shipping_in_iframe'],
				'change_payment_method_url'   => WC_AJAX::get_endpoint( 'qliro_one_wc_change_payment_method' ),
				'change_payment_method_nonce' => wp_create_nonce( 'qliro_one_wc_change_payment_method' ),
				'standardWooCheckoutFields'   => $standard_woo_checkout_fields,
				'submitOrder'                 => WC_AJAX::get_endpoint( 'checkout' ),
				'get_order_url'               => WC_AJAX::get_endpoint( 'qliro_one_get_order' ),
				'get_order_nonce'             => wp_create_nonce( 'qliro_one_get_order' ),
				'log_to_file_url'             => WC_AJAX::get_endpoint( 'qliro_one_wc_log_js' ),
				'log_to_file_nonce'           => wp_create_nonce( 'qliro_one_wc_log_js' ),
				'payForOrder'                 => $pay_for_order,
				'iframeSnippet'               => qliro_wc_get_snippet(),
				'customerTypeCookieName'      => apply_filters( 'qliro_one_customer_type_cookie_name', 'krokedil_customer_type' ),
			)
		);
		wp_enqueue_script( 'qliro-one-for-woocommerce' );
	}


	/**
	 * Loads style for the plugin.
	 */
	public function qoc_load_css() {
		if ( ! is_checkout() ) {
			return;
		}
		if ( is_order_received_page() ) {
			return;
		}
		$style_version = $this->qoc_is_script_debug_enabled();
		wp_register_style(
			'qliro-one-style',
			QLIRO_WC_PLUGIN_URL . '/assets/css/qliro-one-for-woocommerce' . $style_version . '.css',
			array(),
			QLIRO_WC_VERSION
		);
		wp_enqueue_style( 'qliro-one-style' );
	}

	/**
	 * Register admin assets.
	 */
	public function register_admin_assets() {
		$script_version = $this->qoc_is_script_debug_enabled();
		wp_register_script( 'qliro-one-metabox', QLIRO_WC_PLUGIN_URL . '/assets/js/qliro-one-metabox' . $script_version . '.js', array( 'jquery', 'jquery-blockui' ), QLIRO_WC_VERSION, false );
	}

	/**
	 * Enqueues the admin order script.
	 * This script is used on the WooCommerce order page.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_admin_order_script( $hook ) {

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( ! in_array( $hook, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) && ! in_array( $screen_id, array( 'shop_order' ), true ) ) {
			return;
		}

		$order_id = qliro_get_the_ID();
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$captured_items = qliro_get_captured_items( $order );

		// Script Params.
		$params = array(
			'ajax_url'                                => admin_url( 'admin-ajax.php' ),
			'make_capture_url'                        => WC_AJAX::get_endpoint( 'qliro_one_make_capture' ),
			'make_capture_nonce'                      => wp_create_nonce( 'qliro_one_make_capture' ),
			'order_id'                                => $order_id,
			'make_capture_confirm'                    => __( 'Are you sure you wish to process this capture?', 'qliro-one-for-woocommerce' ),
			'make_capture_no_items'                   => __( 'You must select at least one item to deliver.', 'qliro-one-for-woocommerce' ),
			'captured_items'                          => ! empty( $captured_items ) ? wp_json_encode( $captured_items ) : '{}',
			'shipping_checkbox_text'                  => __( 'Check this checkbox to include this shipping line in this capture.', 'qliro-one-for-woocommerce' ),
			'fee_checkbox_text'                       => __( 'Check this checkbox to include this fee line in this capture.', 'qliro-one-for-woocommerce' ),
			'with_return_fee_text'                    => __( 'with a return fee of', 'qliro-one-for-woocommerce' ),
			'refund_amount_less_than_return_fee_text' => __( 'Refund amount is less than the return fee.', 'qliro-one-for-woocommerce' ),
		);

		// Checkout script.
		wp_register_script(
			'qoc_admin',
			QLIRO_WC_PLUGIN_URL . '/assets/js/admin-order.js',
			array( 'jquery', 'jquery-tiptip' ),
			QLIRO_WC_VERSION,
			true
		);

		// Localize the script and add the params.
		wp_localize_script(
			'qoc_admin',
			'qoc_admin_params',
			$params
		);

		// Enqueue the script.
		wp_enqueue_script( 'qoc_admin' );

		self::enqueue_admin_style();
	}

	/**
	 * Enqueues the admin style.
	 *
	 * @return void
	 */
	private static function enqueue_admin_style() {
		wp_register_style(
			'qoc_admin_style',
			QLIRO_WC_PLUGIN_URL . '/assets/css/admin.css',
			array(),
			QLIRO_WC_VERSION
		);

		wp_enqueue_style( 'qoc_admin_style' );
	}
}
new Qliro_One_Assets();

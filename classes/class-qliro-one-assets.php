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

	}

	/**
	 *
	 * Checks whether a SCRIPT_DEBUG constant exists.
	 * If there is, the plugin will use minified files.
	 * @return string
	 */
	protected function qoc_is_script_debug_enabled() {
		return  ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )  ? '.min' : '';
	}

	/**
	 * Loads scripts for the plugin.
	 */
	public function qoc_load_js() {
		// load front js.
		if ( ! is_checkout() ) {
			return;
		}
		if ( is_order_received_page() ) {
			return;
		}
		$script_version = $this->qoc_is_script_debug_enabled();
		$src          = QLIRO_WC_PLUGIN_URL . '/assets/js/qliro-one-for-woocommerce' . $script_version . '.js';
		$dependencies = array( 'jquery' );
		wp_register_script( 'qliro-one-for-woocommerce', $src, $dependencies, QLIRO_WC_VERSION, true );
		wp_localize_script(
			'qliro-one-for-woocommerce',
			'qliroOneParams',
			array(
				'isEnabled' => 'yes'
			)
		);
		wp_enqueue_script( 'qliro-one-for-woocommerce');
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
			array()
		);
		wp_enqueue_style( 'qliro-one-style');
	}
}
new Qliro_One_Assets();

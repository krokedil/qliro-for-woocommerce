<?php
/**
 * Qliro One payment widget.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro One payment widget class.
 */
class Qliro_One_Payment_Widget {


	/**
	 * The settings for the plugin.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings = wp_parse_args(
			get_option( 'woocommerce_qliro_one_settings', array() ),
			array(
				'payment_widget_enabled'            => 'no',
				'payment_widget_condensed'          => 'no',
				'payment_widget_placement_location' => '15',
			)
		);

		// Hooks.
		add_shortcode(
			'qliro_one_payment_widget',
			array(
				$this,
				'qliro_one_payment_widget',
			)
		);

		if ( 'yes' === $this->settings['payment_widget_enabled'] ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'payment_widget_hook' ), 1 );
		}

	}

	/**
	 * Add banner widget via hook.
	 */
	public function add_payment_widget_to_product_page() {
		echo wp_kses(
			$this->get_payment_widget_html(),
			array(
				'div' => array(
					'class'          => array(),
					'data-amount'    => array(),
					'data-condensed' => array(),
				),
			)
		);
	}

	/**
	 * Add banner widget via shortcode.
	 *
	 * @param array $atts The attributes for the shortcode.
	 */
	public function qliro_one_payment_widget( $atts ) {
		return $this->get_payment_widget_html( $atts );
	}

	/**
	 * Hook onto the product page (or custom hooks) to add the banner widget.
	 *
	 * @return void
	 */
	public function payment_widget_hook() {
		add_action(
			'woocommerce_single_product_summary',
			array(
				$this,
				'add_payment_widget_to_product_page',
			),
			absint( $this->settings['payment_widget_placement_location'] )
		);
	}

	/**
	 * HTML for banner widget.
	 *
	 * @param array|null $atts The attributes for the shortcode. null if called via hook.
	 */
	private function get_payment_widget_html( $atts = null ) {

		// If called via shortcode, use the amount attribute.
		if ( isset( $atts['amount'] ) ) {
			$price = max( 0, $atts['amount'] );
		} else {
			$product = wc_get_product();
			if ( ! $product ) {
				return;
			}

			if ( $product->is_type( 'variable' ) ) {
				$price = $product->get_variation_price( 'min' );
			} else {
				$price = wc_get_price_to_display( $product );
			}
		}

		$data_condensed = 'yes' === $this->settings['payment_widget_condensed'] ? ' data-condensed' : '';

		$lang = substr( get_locale(), 0, 2 );
		$lang = in_array( $lang, array( 'sv', 'no', 'fi', 'da' ) ) ? $lang : 'en';

		$country = ( new WC_Countries() )->get_base_country();
		$country = in_array( $country, array( 'SE', 'NO', 'FI', 'DK' ) ) ? $country : '';

		$src = "https://widgets.qliro.com/?c={$country}&l={$lang}";
		wp_enqueue_script( 'qliro-one-widget', $src, array(), QLIRO_WC_VERSION, true );

		$widget = "<div class='qliro-widget' data-amount='{$price}' {$data_condensed}></div>";

		return $widget;
	}
}

new Qliro_One_Payment_Widget();

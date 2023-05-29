<?php
/**
 * This file is responsible for creating the Qliro One branding widget (available in Appearance → Widgets).
 *
 * @package Qliro_One_For_WooCommerce/Classes/Widgets
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro One banner widget class.
 */
class Qliro_One_Banner_Widget {


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
				'banner_widget_enabled'            => 'no',
				'banner_widget_data_shadow'        => 'no',
				'banner_widget_data_method'        => 'campaign',
				'banner_widget_placement_location' => '15',
			)
		);

		// Hooks.
		add_shortcode(
			'qliro_one_banner_widget',
			array(
				$this,
				'qliro_one_banner_widget',
			)
		);

		if ( 'yes' === $this->settings['banner_widget_enabled'] ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'banner_widget_hook' ), 1 );
		}

	}

	/**
	 * Add banner widget via hook.
	 */
	public function add_banner_widget_to_product_page() {
		echo wp_kses(
			$this->get_banner_widget_html(),
			array(
				'div' => array(
					'class'       => array(),
					'data-method' => array(),
					'data-color'  => array(),
					'data-shadow' => array(),
				),
			)
		);
	}

	/**
	 * Add banner widget via shortcode.
	 */
	public function qliro_one_banner_widget() {
		return $this->get_banner_widget_html();
	}

	/**
	 * Hook onto the product page (or custom hooks) to add the banner widget.
	 *
	 * @return void
	 */
	public function banner_widget_hook() {
		add_action(
			'woocommerce_single_product_summary',
			array(
				$this,
				'add_banner_widget_to_product_page',
			),
			absint( $this->settings['banner_widget_placement_location'] )
		);
	}


	/**
	 * HTML for banner widget.
	 */
	private function get_banner_widget_html() {
		$lang = substr( get_locale(), 0, 2 );
		$lang = in_array( $lang, array( 'sv', 'no', 'fi', 'da' ) ) ? $lang : 'en';

		$country = ( new WC_Countries() )->get_base_country();
		$country = in_array( $country, array( 'SE', 'NO', 'FI', 'DK' ) ) ? $country : '';

		$src = "https://widgets.qliro.com/?c={$country}&l={$lang}";
		wp_enqueue_script( 'qliro-one-widget', $src, array(), QLIRO_WC_VERSION, true );

		$widget = '<div class="qliro-banner" data-method="' . esc_attr( $this->settings['banner_widget_data_method'] ) . '" data-color="mint"' . ( 'yes' === $this->settings['banner_widget_data_shadow'] ? ' data-shadow' : '' ) . '></div>';

		return $widget;
	}
}

new Qliro_One_Banner_Widget();

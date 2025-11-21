<?php
/**
 * Add Qliro shipping options as shipping instance settings.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping Method class.
 */
class Qliro_One_Shipping_Method_Instance {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shipping_instance_settings' ) );
	}

	/**
	 * Register the shipping instance settings for Qliro for each shipping method that exists.
	 */
	public function register_shipping_instance_settings() {
		$available_shipping_methods = WC()->shipping()->load_shipping_methods();
		foreach ( $available_shipping_methods as $shipping_method ) {
			$shipping_method_id = $shipping_method->id;
			add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method_id, array( $this, 'add_shipping_method_fields' ), 9 );
		}
	}

	/**
	 * Add External delivery method to shipping method fields.
	 *
	 * @param array $shipping_method_fields      Array of shipping method fields.
	 *
	 * @return array
	 */
	public function add_shipping_method_fields( $shipping_method_fields ) {
		$settings_fields = array(
			'qliro_shipping_settings'     => array(
				'title'       => __( 'Qliro shipping settings', 'qliro-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'These settings let you customize the shipping methods in Qliro checkout, and only apply when you show the shipping options in the iframe. ', 'qliro-for-woocommerce' ),
				'default'     => '',
			),
			'qliro_description'           => array(
				'title'       => __( 'Description', 'qliro-for-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Description presented as extra lines of information to the customer.', 'qliro-for-woocommerce' ),
				'placeholder' => __( 'Maximum length is 100 characters per line and up to 3 lines.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'qliro_delivery_date_start'   => array(
				'title'   => __( 'Delivery start date', 'qliro-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none' => __( 'None', 'qliro-for-woocommerce' ),
					'1'    => __( 'Tomorrow', 'qliro-for-woocommerce' ),
					'2'    => __( 'In 2 days', 'qliro-for-woocommerce' ),
					'3'    => __( 'In 3 days', 'qliro-for-woocommerce' ),
					'4'    => __( 'In 4 days', 'qliro-for-woocommerce' ),
					'5'    => __( 'In 5 days', 'qliro-for-woocommerce' ),
					'6'    => __( 'In 6 days', 'qliro-for-woocommerce' ),
					'7'    => __( 'In 7 days', 'qliro-for-woocommerce' ),
					'8'    => __( 'In 8 days', 'qliro-for-woocommerce' ),
					'9'    => __( 'In 9 days', 'qliro-for-woocommerce' ),
					'10'   => __( 'In 10 days', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_delivery_date_end'     => array(
				'title'   => __( 'Delivery end date', 'qliro-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none' => __( 'None', 'qliro-for-woocommerce' ),
					'1'    => __( 'Tomorrow', 'qliro-for-woocommerce' ),
					'2'    => __( 'In 2 days', 'qliro-for-woocommerce' ),
					'3'    => __( 'In 3 days', 'qliro-for-woocommerce' ),
					'4'    => __( 'In 4 days', 'qliro-for-woocommerce' ),
					'5'    => __( 'In 5 days', 'qliro-for-woocommerce' ),
					'6'    => __( 'In 6 days', 'qliro-for-woocommerce' ),
					'7'    => __( 'In 7 days', 'qliro-for-woocommerce' ),
					'8'    => __( 'In 8 days', 'qliro-for-woocommerce' ),
					'9'    => __( 'In 9 days', 'qliro-for-woocommerce' ),
					'10'   => __( 'In 10 days', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_category_display_name' => array(
				'title'   => __( 'Category Display Name', 'qliro-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'          => __( 'None', 'qliro-for-woocommerce' ),
					'HOME_DELIVERY' => __( 'Home Delivery', 'qliro-for-woocommerce' ),
					'PICKUP'        => __( 'Pickup Point', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_label_display_name'    => array(
				'title'   => __( 'Label Display Name', 'qliro-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'    => __( 'None', 'qliro-for-woocommerce' ),
					'express' => __( 'Express', 'qliro-for-woocommerce' ),
					'economy' => __( 'Economy', 'qliro-for-woocommerce' ),
					'free'    => __( 'Free', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_brand'                 => array(
				'title'       => __( 'Brand', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Leave as "None" if your shipping company of choice is not available as an option.', 'qliro-for-woocommerce' ),
				'options'     => array(
					'none'        => __( 'None', 'qliro-for-woocommerce' ),
					'Airmee'      => 'Airmee',
					'Aramex'      => 'Aramex',
					'Best'        => 'Best',
					'Bring'       => 'Bring',
					'Budbee'      => 'Budbee',
					'Dao'         => 'DAO',
					'Dhl'         => 'DHL',
					'Dsv'         => 'DSV',
					'EarlyBird'   => 'Early Bird',
					'FedEx'       => 'FedEx',
					'Helthjem'    => 'Helthjem',
					'Instabikes'  => 'Instabikes',
					'Instabox'    => 'Instabox',
					'Oda'         => 'ODA Delivery',
					'Posti'       => 'Posti',
					'PostNord'    => 'PostNord',
					'Porterbuddy' => 'Porterbuddy',
					'Schenker'    => 'Schenker',
					'Svosj'       => 'Svosj',
					'Ups'         => 'UPS',
				),
			),
			'qliro_option_label_eco'      => array(
				'title'       => __( 'ECO friendly label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_option_label_express'  => array(
				'title'       => __( 'Express shipping label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_option_label_evening'  => array(
				'title'       => __( 'Evening delivery label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_option_label_morning'  => array(
				'title'       => __( 'Morning delivery label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_option_label_home'     => array(
				'title'       => __( 'Home delivery label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Cannot be used together with BOX or PICKUP label.', 'qliro-for-woocommerce' ),
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_option_label_box'      => array(
				'title'       => __( 'Box delivery label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Cannot be used together with HOME or PICKUP label.', 'qliro-for-woocommerce' ),
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'qliro_option_label_pickup'   => array(
				'title'       => __( 'Pickup label', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Cannot be used together with HOME or BOX label.', 'qliro-for-woocommerce' ),
				'options'     => array(
					'none'     => __( 'None', 'qliro-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-for-woocommerce' ),
				),
			),
			'tax_status'                  => array(
				'title'   => __( 'Tax status', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					// 'none'    => _x( 'None', 'Tax status', 'woocommerce' ), @todo Implement logic for this.
				),
			),
		);

		$shipping_method_fields = array_merge( $shipping_method_fields, $settings_fields );
		return $shipping_method_fields;
	}
}
new Qliro_One_Shipping_Method_Instance();

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
			'qliro_shipping_settings' => array(
				'title'       => __( 'Qliro shipping settings', 'qliro-one-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'These settings let you customize the shipping methods in Qliro One checkout, and only apply when you show the shipping options in the iframe. ', 'qliro-one-for-woocommerce' ),
				'default'     => '',
			),
			'description'             => array(
				'title'       => __( 'Description', 'qliro-one-for-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'description' => __( 'Description presented as extra lines of information to the customer.', 'qliro-one-for-woocommerce' ),
				'placeholder' => __( 'Maximum length is 100 characters per line and up to 3 lines.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'category_display_name'   => array(
				'title'   => __( 'Category Display Name', 'qliro-one-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'          => __( 'None', 'qliro-one-for-woocommerce' ),
					'HOME_DELIVERY' => __( 'Home Delivery', 'qliro-one-for-woocommerce' ),
					'PICKUP'        => __( 'Pickup Point', 'qliro-one-for-woocommerce' ),
				),
			),
			'label_display_name'      => array(
				'title'   => __( 'Label Display Name', 'qliro-one-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'    => __( 'None', 'qliro-one-for-woocommerce' ),
					'express' => __( 'Express', 'qliro-one-for-woocommerce' ),
					'economy' => __( 'Economy', 'qliro-one-for-woocommerce' ),
					'free'    => __( 'Free', 'qliro-one-for-woocommerce' ),
				),
			),
			'brand'                   => array(
				'title'       => __( 'Brand', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Leave as "None" if your shipping company of choice is not available as an option.', 'qliro-one-for-woocommerce' ),
				'options'     => array(
					'none'        => __( 'None', 'qliro-one-for-woocommerce' ),
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
			'option_label_eco'        => array(
				'title'       => __( 'ECO friendly label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
			'option_label_express'    => array(
				'title'       => __( 'Express shipping label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
			'option_label_evening'    => array(
				'title'       => __( 'Evening delivery label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
			'option_label_morning'    => array(
				'title'       => __( 'Morning delivery label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Display a label next to the shipping name.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
			'option_label_home'       => array(
				'title'       => __( 'Home delivery label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Cannot be used together with BOX or PICKUP label.', 'qliro-one-for-woocommerce' ),
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
			'option_label_box'        => array(
				'title'       => __( 'Box delivery label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Cannot be used together with HOME or PICKUP label.', 'qliro-one-for-woocommerce' ),
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
			'option_label_pickup'     => array(
				'title'       => __( 'Pickup label', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'none',
				'description' => __( 'Cannot be used together with HOME or BOX label.', 'qliro-one-for-woocommerce' ),
				'options'     => array(
					'none'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'text'     => __( 'Text', 'qliro-one-for-woocommerce' ),
					'icon'     => __( 'Icon', 'qliro-one-for-woocommerce' ),
					'textIcon' => __( 'Text and icon', 'qliro-one-for-woocommerce' ),
				),
			),
		);

		$shipping_method_fields = array_merge( $shipping_method_fields, $settings_fields );
		return $shipping_method_fields;
	}

}
new Qliro_One_Shipping_Method_Instance();

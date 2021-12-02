<?php
/**
 * Class for Qliro One gateway settings.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qliro_One_Fields class.
 *
 * Qliro_One_Fields for WooCommerce settings fields.
 */
class Qliro_One_Fields {

	/**
	 * Returns the fields.
	 */
	public static function fields() {
		$settings = array(
			'enabled'                    => array(
				'title'       => __( 'Enable/Disable', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable Qliro One payment', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                      => array(
				'title'       => __( 'Title', 'qliro-one-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title.', 'qliro-one-for-woocommerce' ),
				'default'     => 'Qliro One',
				'desc_tip'    => true,
			),
			'description'                => array(
				'title'       => __( 'Description', 'qliro-one-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'qliro-one-for-woocommerce' ),
				'default'     => 'Payment method description.',
				'desc_tip'    => true,
			),
			'testmode'                   => array(
				'title'       => __( 'Test mode', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'qliro-one-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'                    => array(
				'title'       => __( 'Logging', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'qliro-one-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			// credentials.
			'credentials'                => array(
				'title' => 'API Credentials',
				'type'  => 'title',
			),
			'api_key'                => array(
				'title'             => __( 'Production Qliro One API key', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'api_secret'              => array(
				'title'             => __( 'Production Qliro One API Secret', 'qliro-one-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_api_key'           => array(
				'title'             => __( 'Test Qliro One API key', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_api_secret'         => array(
				'title'             => __( 'Test Qliro One API Secret', 'qliro-one-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),


		);
		return apply_filters( 'qliro_one_gateway_settings', $settings );
	}
}
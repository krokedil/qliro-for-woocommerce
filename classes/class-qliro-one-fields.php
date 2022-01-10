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
			'enabled'                                    => array(
				'title'       => __( 'Enable/Disable', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable Qliro One payment', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                                      => array(
				'title'       => __( 'Title', 'qliro-one-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title.', 'qliro-one-for-woocommerce' ),
				'default'     => 'Qliro One',
				'desc_tip'    => true,
			),
			'description'                                => array(
				'title'       => __( 'Description', 'qliro-one-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'qliro-one-for-woocommerce' ),
				'default'     => 'Payment method description.',
				'desc_tip'    => true,
			),
			'other_payment_method_button_text'           => array(
				'title'             => __( 'Other payment method button text', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Qliro One. Leave blank to use the default (and translatable) text.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'testmode'                                   => array(
				'title'       => __( 'Test mode', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'qliro-one-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'                                    => array(
				'title'       => __( 'Logging', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'qliro-one-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			// credentials.
			'credentials'                                => array(
				'title' => 'API Credentials',
				'type'  => 'title',
			),
			'api_key'                                    => array(
				'title'             => __( 'Production Qliro One API key', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'api_secret'                                 => array(
				'title'             => __( 'Production Qliro One API Secret', 'qliro-one-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_api_key'                               => array(
				'title'             => __( 'Test Qliro One API key', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_api_secret'                            => array(
				'title'             => __( 'Test Qliro One API Secret', 'qliro-one-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Use API key and API secret you downloaded in the Qliro One Merchant Portal. Don’t use your email address.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),

			'qliro_one_bg_color'                         => array(
				'title'       => __( 'Background color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Hex color code to use as background color in Qliro One.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'qliro_one_primary_color'                    => array(
				'title'       => __( 'Primary color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the color for the selected options throughout the checkout.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'qliro_one_call_action_color'                => array(
				'title'       => __( 'Call to action color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the color for the CTA buttons throughout the checkout.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'qliro_one_call_action_hover_color'          => array(
				'title'       => __( 'Call to action hover color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the color for the CTA buttons hoovered throughout the checkout.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'qliro_one_corner_radius'                    => array(
				'title'       => __( 'Corner radius', 'qliro-one-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'A pixel value to be used on corners throughout Qliro One.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'css'         => 'width: 100px',
			),

			'qliro_one_button_corner_radius'             => array(
				'title'       => __( 'Button corner radius', 'qliro-one-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Define the corners for the CTA buttons. Can be either boxy or rounded.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'css'         => 'width: 100px',
			),

			'qliro_one_button_ask_for_newsletter_signup' => array(
				'title'       => __( 'Ask for newsletter signup', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If true, Qliro One will set signup newsletter as checked.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),

			'qliro_one_button_ask_for_newsletter_signup_checked' => array(
				'title'       => __( 'Ask for newsletter signup checked', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If true, Qliro One will set signup newsletter as checked.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),

			'checkout_layout'                            => array(
				'title'       => __( 'Checkout layout', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'one_column_checkout' => __( 'One column checkout', 'qliro-one-for-woocommerce' ),
					'two_column_right'    => __( 'Two column checkout (Qliro One in right column)', 'qliro-one-for-woocommerce' ),
					'two_column_left'     => __( 'Two column checkout (Qliro One in left column)', 'qliro-one-for-woocommerce' ),
					'two_column_left_sf'  => __( 'Two column checkout (Qliro One in left column) - Storefront light', 'qliro-one-for-woocommerce' ),
				),
				'description' => __( 'Select the Checkout layout.', 'qliro-one-for-woocommerce' ),
				'default'     => 'one_column_checkout',
				'desc_tip'    => false,
			),

			'qliro_one_enforced_juridical_type'          => array(
				'title'       => __( 'Enforced juridical type', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'Physical' => __( 'Physical', 'qliro-one-for-woocommerce' ),
					'Company'  => __( 'Company', 'qliro-one-for-woocommerce' ),
				),
				'description' => __( 'Select juridical type', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => false,
			),

			'qliro_one_order_management'                 => array(
				'title'   => __( 'Enable Order Management', 'qliro-one-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Qliro One order capture on WooCommerce order completion.', 'qliro-one-for-woocommerce' ),
				'default' => 'no',
			),

		);
		return apply_filters( 'qliro_one_gateway_settings', $settings );
	}
}

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
		$order_statuses_trigger           = wc_get_order_statuses();
		$order_statuses_trigger['manual'] = __( 'Manual trigger', 'qliro-one-for-woocommerce' );
		$order_statuses                   = wc_get_order_statuses();
		$order_statuses['none']           = __( 'None', 'qliro-one-for-woocommerce' );

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
			'debugging'                                  => array(
				'title' => 'Debug',
				'type'  => 'title',
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
			'shipping_settings'                          => array(
				'title' => 'Shipping',
				'type'  => 'title',
			),
			'shipping_in_iframe'                         => array(
				'title'       => __( 'Display Shipping in the iframe', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Will display the shipping options inside of the Qliro One checkout iframe.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'options'     => array(
					'no'                  => __( 'No', 'qliro-one-for-woocommerce' ),
					'wc_shipping'         => __( 'WooCommerce shipping methods', 'qliro-one-for-woocommerce' ),
					'integrated_shipping' => __( 'Qliro integrated shipping methods', 'qliro-one-for-woocommerce' ),
				),
			),
			'shipping_additional_header'                 => array(
				'title'       => __( 'Shipping additional header', 'qliro-one-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( "An introductory text to be displayed in the payment form's shipping section. Up to 300 characters.", 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'checkout_settings'                          => array(
				'title' => 'Checkout settings',
				'type'  => 'title',
			),
			'qliro_one_enforced_juridical_type'          => array(
				'title'       => __( 'Enforced juridical type', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'None'     => __( 'None', 'qliro-one-for-woocommerce' ),
					'Physical' => __( 'Physical', 'qliro-one-for-woocommerce' ),
					'Company'  => __( 'Company', 'qliro-one-for-woocommerce' ),
				),
				'description' => __( 'Select juridical type', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => false,
			),
			'minimum_age'                                => array(
				'title'       => __( 'Minimum age', 'qliro-one-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'The minimum customer age for all purchases. Can also be set on an individual product level.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'require_id_verification'                    => array(
				'title'       => __( 'Require identity verification', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If this box is checked and the customer is from Sweden, BankID identity verification will be mandatory for each purchase. As a consequence, there will be a double BankID verification requirement in certain instances. We recommend setting this requirement on an individual product level instead, as it should only be applied when there are additional perceived risks.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'has_risk'                                   => array(
				'title'       => __( 'Has risk', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If this box is checked, the product is a risk item.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'qliro_one_button_ask_for_newsletter_signup' => array(
				'title'       => __( 'Ask for newsletter signup', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Will display an unchecked checkbox for newsletter sign-up in the Qliro One Checkout.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'qliro_one_button_ask_for_newsletter_signup_checked' => array(
				'title'       => __( 'Ask for newsletter signup checked', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Will display an already checked checkbox for newsletter sign-up on the Qliro One Checkout', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			// Order Management.
			'order_management'                           => array(
				'title' => 'Order Management',
				'type'  => 'title',
			),
			'capture_status'                             => array(
				'title'       => __( 'Capture status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses_trigger,
				'description' => __( 'Select what order status to use to initiate capturing the order in Qliros system.', 'qliro-one-for-woocommerce' ),
				'default'     => 'wc-completed',
				'desc_tip'    => false,
			),
			'capture_pending_status'                     => array(
				'title'       => __( 'Pending capture status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what order status to set the order to while we wait for Qliro to tell us if the capture was successful or not.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
			),
			'capture_ok_status'                          => array(
				'title'       => __( 'OK capture status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what order status to set the order to when we get notified of a successful order capture.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
			),
			'cancel_status'                              => array(
				'title'       => __( 'Cancel status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses_trigger,
				'description' => __( 'Select what order status to use to initiate canceling the order in Qliros system.', 'qliro-one-for-woocommerce' ),
				'default'     => 'wc-cancelled',
				'desc_tip'    => false,
			),
			'cancel_pending_status'                      => array(
				'title'       => __( 'Pending cancel status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what order status to set the order to while we wait for Qliro to tell us if the cancelation was successful or not.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
			),
			'cancel_ok_status'                           => array(
				'title'       => __( 'OK cancel status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what order status to set the order to when we get notified of a successful order cancelation.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
			),
			// Checkout customization.
			'checkout_customization'                     => array(
				'title' => 'Checkout Customization',
				'type'  => 'title',
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
			'banner_widget_section'                      => array(
				'title' => __( 'Banner widget', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
			),
			'banner_widget_enabled'                      => array(
				'type'    => 'checkbox',
				'default' => 'no',
				'title'   => __( 'Enable banner widget on product page', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_placement_location'           => array(
				'title'   => __( 'Banner widget product page placement', 'qliro-one-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'4'  => __( 'Above Title', 'qliro-one-for-woocommerce' ),
					'7'  => __( 'Between Title and Price', 'qliro-one-for-woocommerce' ),
					'15' => __( 'Between Price and Excerpt', 'qliro-one-for-woocommerce' ),
					'25' => __( 'Between Excerpt and Add to cart button', 'qliro-one-for-woocommerce' ),
					'35' => __( 'Between Add to cart button and Product meta', 'qliro-one-for-woocommerce' ),
					'45' => __( 'Between Product meta and Product sharing buttons', 'qliro-one-for-woocommerce' ),
					'55' => __( 'After Product sharing-buttons', 'qliro-one-for-woocommerce' ),
				),
				'default' => '15',
				'desc'    => __( 'Select where to display the widget in your product pages.', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_cart_enabled'                 => array(
				'type'    => 'checkbox',
				'default' => 'no',
				'title'   => __( 'Enable banner widget on cart page', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_cart_placement_location'      => array(
				'title'   => __( 'Banner widget cart placement', 'qliro-one-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'woocommerce_cart_collaterals'    => __( 'Above cross-sell', 'qliro-one-for-woocommerce' ),
					'woocommerce_before_cart_totals'  => __( 'Above cart totals', 'qliro-one-for-woocommerce' ),
					'woocommerce_proceed_to_checkout' => __( 'Between cart totals and proceed to checkout button', 'qliro-one-for-woocommerce' ),
					'woocommerce_after_cart_totals'   => __( 'After proceed to checkout button', 'qliro-one-for-woocommerce' ),
					'woocommerce_after_cart'          => __( 'Bottom of the page', 'qliro-one-for-woocommerce' ),
				),
				'default' => 'woocommerce_cart_collaterals',
				'desc'    => __( 'Select where to display the widget on the cart page.', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_data_method'                  => array(
				'type'        => 'select',
				'default'     => 'campaign',
				'title'       => __( 'Payment method', 'qliro-one-for-woocommerce' ),
				'description' => __( 'The payment method to be presented in the banner . ', 'woocommerce' ),
				'options'     => array(
					'campaign'     => __( 'Campaign', 'qliro-one-for-woocommerce' ),
					'invoice'      => __( 'Invoice', 'qliro-one-for-woocommerce' ),
					'part_payment' => __( 'Part payment', 'qliro-one-for-woocommerce' ),
				),
			),
			'banner_widget_data_shadow'                  => array(
				'type'    => 'checkbox',
				'default' => 'no',
				'title'   => __( 'Display with a Qliro style shadow', 'qliro-one-for-woocommerce' ),
			),

			'payment_widget_section'                     => array(
				'title' => __( 'Payment widget', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
			),
			'payment_widget_enabled'                     => array(
				'type'    => 'checkbox',
				'default' => 'no',
				'title'   => __( 'Enable payment widget', 'qliro-one-for-woocommerce' ),
			),
			'payment_widget_placement_location'          => array(
				'title'   => __( 'Payment widget placement', 'qliro-one-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'4'  => __( 'Above Title', 'qliro-one-for-woocommerce' ),
					'7'  => __( 'Between Title and Price', 'qliro-one-for-woocommerce' ),
					'15' => __( 'Between Price and Excerpt', 'qliro-one-for-woocommerce' ),
					'25' => __( 'Between Excerpt and Add to cart button', 'qliro-one-for-woocommerce' ),
					'35' => __( 'Between Add to cart button and Product meta', 'qliro-one-for-woocommerce' ),
					'45' => __( 'Between Product meta and Product sharing buttons', 'qliro-one-for-woocommerce' ),
					'55' => __( 'After Product sharing-buttons', 'qliro-one-for-woocommerce' ),
				),
				'default' => '15',
				'desc'    => __( 'Select where to display the widget in your product pages.', 'qliro-one-for-woocommerce' ),
			),
			'payment_widget_data_condensed'              => array(
				'type'    => 'checkbox',
				'default' => 'no',
				'title'   => __( 'Display in a condensed (shorter) style', 'qliro-one-for-woocommerce' ),
			),
			'country_selector_section'                   => array(
				'title' => __( 'Country selector', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
			),
			'country_selector'                           => array(
				'type'    => 'checkbox',
				'default' => 'no',
				'title'   => __( 'Enable country selector', 'qliro-one-for-woocommerce' ),
			),
			'country_selector_placement'                 => array(
				'title'   => __( 'Country selector placement', 'qliro-one-for-woocommerce' ),
				'type'    => 'select',
				// TODO: Update placeholder hooks.
				'options' => array(
					'4'  => __( 'Above Title', 'qliro-one-for-woocommerce' ),
					'7'  => __( 'Between Title and Price', 'qliro-one-for-woocommerce' ),
					'15' => __( 'Between Price and Excerpt', 'qliro-one-for-woocommerce' ),
					'25' => __( 'Between Excerpt and Add to cart button', 'qliro-one-for-woocommerce' ),
					'35' => __( 'Between Add to cart button and Product meta', 'qliro-one-for-woocommerce' ),
					'45' => __( 'Between Product meta and Product sharing buttons', 'qliro-one-for-woocommerce' ),
					'55' => __( 'After Product sharing-buttons', 'qliro-one-for-woocommerce' ),
				),
				'default' => '15',
				'desc'    => __( 'Select where to display the selector on the checkout page.', 'qliro-one-for-woocommerce' ),
			),
		);

		// Add upsell section if plugin is installed.
		if ( class_exists( 'PPU' ) ) {
			$settings['upsell']            = array(
				'title' => 'Upsell',
				'type'  => 'title',
			);
			$settings['upsell_percentage'] = array(
				'title'             => __( 'Upsell Percentage', 'qliro-one-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Set the max amount above the order value a customer can add to a Qliro order paid with a After Delivery payment . The default is 10 % , if you want higher than that you will first need to contact Qliro . ', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'default'           => 10,
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
			);
		}

		return apply_filters( 'qliro_one_gateway_settings', $settings );
	}
}

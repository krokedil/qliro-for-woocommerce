<?php
/**
 * Class for Qliro gateway settings.
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
		$wc_order_statuses = wc_get_order_statuses();

		unset( $wc_order_statuses['wc-pending'] );
		unset( $wc_order_statuses['wc-refunded'] );
		unset( $wc_order_statuses['wc-failed'] );

		$order_statuses_capture = array( 'manual' => __( 'None', 'qliro-for-woocommerce' ) ) + $wc_order_statuses;
		$order_statuses_cancel  = array( 'manual' => __( 'None', 'qliro-for-woocommerce' ) ) + $wc_order_statuses;

		// Set recommended order statuses for capture and cancel.
		$order_statuses_capture['wc-completed'] = __( 'Completed (recommended)', 'qliro-for-woocommerce' );
		$order_statuses_cancel['wc-cancelled']  = __( 'Cancelled (recommended)', 'qliro-for-woocommerce' );

		$advanced_order_statuses = array( 'none' => __( 'None (recommended)', 'qliro-for-woocommerce' ) ) + $wc_order_statuses;

		$wc_logs_url = admin_url( 'admin.php?page=wc-status&tab=logs&source=qliro-for-woocommerce&paged=1' );
		$ppu_status  = class_exists( 'PPU' ) ? ' active' : ' inactive';

		$settings = get_option( 'woocommerce_qliro_one_settings', array() );

		// If no custom order statuses are set, default the advanced order management settings to not be enabled.
		$custom_statuses_used = (
			( isset( $settings['capture_pending_status'] ) && 'none' !== $settings['capture_pending_status'] ) ||
			( isset( $settings['capture_ok_status'] ) && 'none' !== $settings['capture_ok_status'] ) ||
			( isset( $settings['cancel_pending_status'] ) && 'none' !== $settings['cancel_pending_status'] ) ||
			( isset( $settings['cancel_ok_status'] ) && 'none' !== $settings['cancel_ok_status'] )
		) ? 'yes' : 'no';

		$settings = array(
			// general.
			'general'                                    => array(
				'id'          => 'general',
				'title'       => __( 'General configuration', 'qliro-for-woocommerce' ),
				'type'        => 'krokedil_section_start',
				'description' => __( 'Get started with Qliro for WooCommerce by adding the API credentials you recieved from Qliro and enable Qliro as a payment method.', 'qliro-for-woocommerce' ),
			),
			'enabled'                                    => array(
				'title'       => __( 'Enable/Disable', 'qliro-for-woocommerce' ),
				'label'       => __( 'Enable Qliro as a payment method', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                                      => array(
				'title'       => __( 'Title', 'qliro-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The title is used throughout WooCommerce to display the payment method name at checkout and on order/payment admin pages.', 'qliro-for-woocommerce' ),
				'default'     => 'Qliro',
				'desc_tip'    => true,
			),
			'description'                                => array(
				'title'       => __( 'Description', 'qliro-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'The description could, depending on your theme and setup, be shown in relation to the payment method name at the checkout.', 'qliro-for-woocommerce' ),
				'default'     => 'Safe and simple payments.',
				'desc_tip'    => true,
			),
			// API credentials.
			'api_credentials_title'                      => array(
				'title' => __( 'API credentials', 'qliro-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
			),
			'api_key'                                    => array(
				'title'             => __( 'Production Qliro merchant API key', 'qliro-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Please contact your onboarding agent or email integration@qliro.com to get your API credentials.', 'qliro-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'api_secret'                                 => array(
				'title'             => __( 'Production Qliro merchant API secret', 'qliro-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Please contact your onboarding agent or email integration@qliro.com to get your API credentials.', 'qliro-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_api_key'                               => array(
				'title'             => __( 'Test Qliro merchant API key', 'qliro-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Used when test mode is enabled below. More information about how to test before becoming a Qliro customer <a target="_blank" href="https://krokedil.com/product/qliro-for-woocommerce/">here</a>.', 'qliro-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_api_secret'                            => array(
				'title'             => __( 'Test Qliro merchant API secret', 'qliro-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Used when test mode is enabled below. More information about how to test before becoming a Qliro customer <a target="_blank" href="https://krokedil.com/product/qliro-for-woocommerce/">here</a>.', 'qliro-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			// Debug.
			'debug_title'                                => array(
				'title' => __( 'Debug', 'qliro-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
			),
			'testmode'                                   => array(
				'title'       => __( 'Test mode', 'qliro-for-woocommerce' ),
				'label'       => __( 'Enable test mode', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Disable test mode when testing is complete and you want to go live.', 'qliro-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => false,
			),
			'logging'                                    => array(
				'title'       => __( 'Logging', 'qliro-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					// translators: %s is the link to the WooCommerce logs.
					__( 'Save debug messages from the plugin to the WooCommerce logs. Existing plugin logs can be found %s.', 'qliro-for-woocommerce' ),
					'<a target="_blank" href="' . esc_url( $wc_logs_url ) . '">' . __( 'here', 'qliro-for-woocommerce' ) . '</a>'
				),
				'default'     => 'yes',
				'desc_tip'    => false,
			),
			'general_end'                                => array(
				'type' => 'krokedil_section_end',
			),
			// Checkout configuration.
			'checkout_configuration'                     => array(
				'id'          => 'checkout_configuration',
				'title'       => 'Checkout configuration',
				'type'        => 'krokedil_section_start',
				'description' => __( 'Configure your checkout in relation to shipping, B2C/B2B purchases, newsletter signup and risk mitigations.', 'qliro-for-woocommerce' ),
			),
			'shipping_in_iframe'                         => array(
				'title'       => __( 'Shipping within the Qliro iframe', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Integrating with shipping service like nShift, Ingrid or Fraktjakt? Want to customize how WooCommerce shipping methods looks within the Qliro iframe? Read more about shipping related compatibility and configurations <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/shipping-settings/">here</a>.', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_toggler krokedil_toggler_iframe_shipping toggler_option_wc_shipping',
				'options'     => array(
					'no'                  => __( 'No', 'qliro-for-woocommerce' ),
					'wc_shipping'         => __( 'WooCommerce shipping methods', 'qliro-for-woocommerce' ),
					'integrated_shipping' => __( 'Qliro integrated shipping with Ingrid', 'qliro-for-woocommerce' ),
				),
			),
			'shipping_additional_header'                 => array(
				'title'       => __( 'Shipping section description', 'qliro-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Display a custom text right under the shipping section’s main title. Useful to eg inform about a certain campaign or discounts on delivery for purchases over certain amount.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_iframe_shipping',
			),
			'country_selector_placement'                 => array(
				'title'       => __( 'Country selector placement', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'shortcode'                        => __( 'Inactive/shortcode placement', 'qliro-for-woocommerce' ),
					'qliro_one_wc_before_wrapper'      => __( 'Above checkout form', 'qliro-for-woocommerce' ),
					'qliro_one_wc_before_order_review' => __( 'Above order review', 'qliro-for-woocommerce' ),
					'qliro_one_wc_before_snippet'      => __( 'Above payment form', 'qliro-for-woocommerce' ),
					'qliro_one_wc_after_order_review'  => __( 'Below order review', 'qliro-for-woocommerce' ),
					'qliro_one_wc_after_snippet'       => __( 'Below payment form', 'qliro-for-woocommerce' ),
					'qliro_one_wc_after_wrapper'       => __( 'Below checkout form', 'qliro-for-woocommerce' ),
				),
				'default'     => 'shortcode', // Disabled by default.
				'description' => __( 'Enables the possibility to switch billing country from the checkout page. Choose where on the checkout page you want it to be visible. Please note that you can also display it with the shortcode [qliro_country_selector], read more about it <a href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/introduction/#country-selector">here</a>.', 'qliro-for-woocommerce' ),
				'desc_tip'    => false,
			),
			'qliro_one_enforced_juridical_type'          => array(
				'title'       => __( 'Allowed customer types', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'None'     => __( 'Both B2B & B2C', 'qliro-for-woocommerce' ),
					'Physical' => __( 'Only B2C', 'qliro-for-woocommerce' ),
					'Company'  => __( 'Only B2B', 'qliro-for-woocommerce' ),
				),
				'description' => __( 'Qliro supports both B2C and B2B checkout flows. Change the setting if you only want to allow B2C or B2B checkout flow.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'qliro_one_button_ask_for_newsletter_signup' => array(
				'title'       => __( 'Ask for newsletter signup', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show signup newsletter field', 'qliro-for-woocommerce' ),
				'description' => __( 'If enabled, Qliro Checkout will ask the customer if they want to sign up for a newsletter. Read more about how you can use the response from this field <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/newsletter-settings/">here</a>.', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_toggler krokedil_toggler_newsletter_settings',
			),
			'qliro_one_button_ask_for_newsletter_signup_checked' => array(
				'title'       => __( 'Ask for newsletter signup checked by default', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Set signup newsletter field as checked by default', 'qliro-for-woocommerce' ),
				'description' => __( 'If enabled, Qliro Checkout will set signup newsletter as checked by default.', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_newsletter_settings',
			),
			// Risk mitigation.
			'risk_mitigation'                            => array(
				'title'       => __( 'Risk mitigation', 'qliro-for-woocommerce' ),
				'type'        => 'title',
				'class'       => 'krokedil_settings_title',
				'description' => sprintf(
					/* translators: %s: link to product-level settings documentation */
					__( 'Below you have the possibility to apply site-wide risk mitigation settings. Please note that you also have the possibility to set these settings on an individual product level, read more about it %s.', 'qliro-for-woocommerce' ),
					'<a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/introduction/#product-level-settings">' . __( 'here', 'qliro-for-woocommerce' ) . '</a>'
				),
			),
			'minimum_age'                                => array(
				'title'       => __( 'Minimum customer age', 'qliro-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Sets minimum customer age for all purchases, which then also prevents B2B purchases.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'css'         => 'width: 100px',
				'desc_tip'    => true,
			),
			'require_id_verification'                    => array(
				'title'       => __( 'Require identity verification', 'qliro-for-woocommerce' ),
				'label'       => __( 'Verify customers identity with BankID', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled and the order country is Sweden, the customer will always be asked to verify their identity with BankID when completing the purchase. This could lead to double BankID verification requirement in certain instances.', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'has_risk'                                   => array(
				'title'       => __( 'Has risk', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Flag all products as has risk products', 'qliro-for-woocommerce' ),
				'description' => __( 'If enabled, all products in the order will be flagged as has risk products. This can be used to eg limit the list of available payment methods shown for customer.', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			// Risk mitigation.
			'ppu_upsell'                                 => array(
				'title'       => __( 'Upsell on WooCommerce thank you page', 'qliro-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'The plugin "Post Purchase Upsell for WooCommerce" is needed to enable upsell on the WooCommerce thank you page. Read more about it <a target="_blank" href="https://krokedil.com/product/post-purchase-upsell-for-woocommerce/">here</a>.', 'qliro-for-woocommerce' ),
				'class'       => 'krokedil_settings_title krokedil_ppu_setting__title' . $ppu_status,
			),
			'upsell_percentage'                          => array(
				'title'             => __( 'Upsell percentage', 'qliro-for-woocommerce' ),
				'type'              => 'number',
				'css'               => 'width: 100px',
				'description'       => __( 'Set the max amount above the order value a customer can add to a Qliro order paid with a After Delivery payment. If you want higher than 10% you will first need to contact Qliro. Read more about upsell <a target="_blank" href="https://docs.krokedil.com/post-purchase-upsell-for-woocommerce/">here</a>.', 'qliro-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'default'           => 10,
				'class'             => 'krokedil_ppu_setting' . $ppu_status,
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
			),
			'checkout_configuration_end'                 => array(
				'type' => 'krokedil_section_end',
			),
			// Order Management.
			'order_management'                           => array(
				'id'          => 'order_management',
				'title'       => 'Order management',
				'type'        => 'krokedil_section_start',
				'description' => __( 'Manage settings related to an order after it has been created, such as when capture and cancelation with Qliro should be initiated etc.', 'qliro-for-woocommerce' ),
			),
			'capture_status'                             => array(
				'title'       => __( 'Capture order status', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses_capture,
				'description' => __( 'Select WooCommerce order status used to initiate capturing the order in Qliros system. Suggested and default is to use Completed. Please note that you also have the possibility to disable order management on specific orders.', 'qliro-for-woocommerce' ),
				'default'     => 'wc-completed',
				'desc_tip'    => true,
			),
			'cancel_status'                              => array(
				'title'       => __( 'Cancel order status', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses_cancel,
				'description' => __( 'Select WooCommerce order status used to initiate canceling the order in Qliros system. Suggested and default is to use Cancelled. Please note that you also have the possibility to disable order management on specific orders.', 'qliro-for-woocommerce' ),
				'default'     => 'wc-cancelled',
				'desc_tip'    => true,
			),
			'calculate_return_fee'                       => array(
				'title'       => __( 'Calculate return fee', 'qliro-for-woocommerce' ),
				'label'       => __( 'Automatically calculate return fee on refunds', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'description' => __( 'If enabled, then the Qliro return fee will be automatically calculated in the background if a refunded order line is less than the unit amount.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'om_advanced_settings'                       => array(
				'title'       => __( 'Advanced pending status configuration', 'qliro-for-woocommerce' ),
				'label'       => __( 'Enable advanced pending status configuration', 'qliro-for-woocommerce' ),
				'description' => __( 'There is a delay when a capture or cancellation is initiated and WooCommerce receives the response. Therefore you have the possibility to customize what order status an order should have during this process. Use only in advanced situations.', 'qliro-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => $custom_statuses_used,
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_toggler krokedil_toggler_om_advanced_settings',
			),
			'capture_pending_status'                     => array(
				'title'       => __( 'Pending capture order status', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $advanced_order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to while WooCommerce wait for Qliro to tell us if the capture was successful or not.', 'qliro-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'capture_ok_status'                          => array(
				'title'       => __( 'OK capture order status', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $advanced_order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to when we get notified of a successful order capture from Qliro.', 'qliro-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'cancel_pending_status'                      => array(
				'title'       => __( 'Pending cancel order status', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $advanced_order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to while we wait for Qliro to tell us if the cancelation was successful or not.', 'qliro-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'cancel_ok_status'                           => array(
				'title'       => __( 'OK cancel order status', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $advanced_order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to when we get notified of a successful order cancelation from Qliro.', 'qliro-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'order_management_end'                       => array(
				'type' => 'krokedil_section_end',
			),
			// Checkout customization.
			'checkout_customization'                     => array(
				'id'          => 'checkout_customization',
				'title'       => 'Checkout customization',
				'type'        => 'krokedil_section_start',
				'description' => __( 'Customize your checkout related to page layout, other payment method button text, colors and corner radius to make the look and feel of the checkout fit into your website in the best possible way.', 'qliro-for-woocommerce' ),
			),
			'checkout_layout'                            => array(
				'title'       => __( 'Checkout page layout', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'one_column_checkout' => __( 'One column checkout', 'qliro-for-woocommerce' ),
					'two_column_right'    => __( 'Two column checkout (Qliro in right column)', 'qliro-for-woocommerce' ),
					'two_column_left'     => __( 'Two column checkout (Qliro in left column)', 'qliro-for-woocommerce' ),
					'two_column_left_sf'  => __( 'Two column checkout (Qliro in left column) - Storefront light', 'qliro-for-woocommerce' ),
				),
				'description' => __( 'Choose layout to use on the Qliro checkout page. Read more about the options and how the checkout page template can be further customized <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/introduction/#checkout-customization">here</a>.', 'qliro-for-woocommerce' ),
				'default'     => 'two_column_right',
				'desc_tip'    => false,
			),
			'other_payment_method_button_text'           => array(
				'title'             => __( 'Customize other payment method button text', 'qliro-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Customize the <em>Select another payment method</em> button text that is displayed on the checkout page if other payment methods than Qliro is enabled. Leave blank to use the default (and translatable) text.', 'qliro-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			// Look and feel.
			'look_and_feel_title'                        => array(
				'title' => __( 'Look and feel', 'qliro-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
			),
			'qliro_one_bg_color'                         => array(
				'title'       => __( 'Background color', 'qliro-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'If the background should be something else than white, set the preferred hex color. Only colors with saturation <= 10% are supported. If a color with saturation > 10% is provided, the saturation will be lowered to 10%.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_primary_color'                    => array(
				'title'       => __( 'Primary color', 'qliro-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the hex color for the selected options throughout the checkout. The spinner for loading in the checkout will have the same color.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_call_action_color'                => array(
				'title'       => __( 'Call to action color', 'qliro-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the hex color for the CTA buttons throughout the checkout.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_call_action_hover_color'          => array(
				'title'       => __( 'Call to action hover color', 'qliro-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the hex color for the CTA buttons hoovered throughout the checkout. If not provided, the hover color will be a blend between the call to action color and the background color.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_corner_radius'                    => array(
				'title'       => __( 'Corner radius', 'qliro-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'A pixel value to be used on corners throughout Qliro Checkout, eg for the outline of payment or shipping methods. Changes will also apply on all fields to be filled in by customer, e.g. fields when customer authenticate.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'css'         => 'width: 100px',
			),
			'qliro_one_button_corner_radius'             => array(
				'title'       => __( 'Button corner radius', 'qliro-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'A pixel value to be used on corners of CTA buttons throughout Qliro Checkout.', 'qliro-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'css'         => 'width: 100px',
			),
			'checkout_customization_end'                 => array(
				'type' => 'krokedil_section_end',
			),
			// Widgets.
			'widgets'                                    => array(
				'id'          => 'widgets',
				'title'       => __( 'Widgets', 'qliro-for-woocommerce' ),
				'type'        => 'krokedil_section_start',
				'description' => __( 'Setup Qliro payment widgets and banner widgets on your website.', 'qliro-for-woocommerce' ),
			),
			'banner_widget_title'                        => array(
				'title'       => __( 'Banner widget', 'qliro-for-woocommerce' ),
				'type'        => 'title',
				'class'       => 'krokedil_settings_title',
				'description' => __(
					'Promote Qliro payment methods and campaigns, without having to manually update banners continuously. You can also display it with the shortcode [qliro_one_banner_widget], read more about it <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/customization/display-widget-via-shortcode/">here</a>.',
					'qliro-for-woocommerce'
				),
			),
			'banner_widget_data_method'                  => array(
				'type'        => 'select',
				'default'     => 'campaign',
				'title'       => __( 'Banner widget payment method', 'qliro-for-woocommerce' ),
				'description' => __( 'Choose the payment method to be presented in the banner widget.', 'qliro-for-woocommerce' ),
				'options'     => array(
					'campaign'     => __( 'Campaign', 'qliro-for-woocommerce' ),
					'invoice'      => __( 'Invoice', 'qliro-for-woocommerce' ),
					'part_payment' => __( 'Part payment', 'qliro-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'banner_widget_placement_location'           => array(
				'title'       => __( 'Banner widget placement on product pages', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose where on the product page that you want to display the banner widget.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none' => __( 'Inactive/shortcode placement', 'qliro-for-woocommerce' ),
					'4'    => __( 'Above Title', 'qliro-for-woocommerce' ),
					'7'    => __( 'Between Title and Price', 'qliro-for-woocommerce' ),
					'15'   => __( 'Between Price and Excerpt', 'qliro-for-woocommerce' ),
					'25'   => __( 'Between Excerpt and Add to cart button', 'qliro-for-woocommerce' ),
					'35'   => __( 'Between Add to cart button and Product meta', 'qliro-for-woocommerce' ),
					'45'   => __( 'Between Product meta and Product sharing buttons', 'qliro-for-woocommerce' ),
					'55'   => __( 'After Product sharing-buttons', 'qliro-for-woocommerce' ),
				),
				'default'     => 'none',
				'desc'        => __( 'Select where to display the widget in your product pages.', 'qliro-for-woocommerce' ),
			),
			'banner_widget_cart_placement_location'      => array(
				'title'       => __( 'Banner widget placement on cart page', 'qliro-for-woocommerce' ),
				'description' => __( 'Choose where on the cart page that you want to display the banner widget.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'options'     => array(
					'none'                            => __( 'Inactive/shortcode placement', 'qliro-for-woocommerce' ),
					'woocommerce_cart_collaterals'    => __( 'Above cross-sell', 'qliro-for-woocommerce' ),
					'woocommerce_before_cart_totals'  => __( 'Above cart totals', 'qliro-for-woocommerce' ),
					'woocommerce_proceed_to_checkout' => __( 'Between cart totals and proceed to checkout button', 'qliro-for-woocommerce' ),
					'woocommerce_after_cart_totals'   => __( 'After proceed to checkout button', 'qliro-for-woocommerce' ),
					'woocommerce_after_cart'          => __( 'Bottom of the page', 'qliro-for-woocommerce' ),
				),
				'default'     => 'woocommerce_cart_collaterals',
				'desc'        => __( 'Select where to display the widget on the cart page.', 'qliro-for-woocommerce' ),
			),
			'banner_widget_data_shadow'                  => array(
				'type'        => 'checkbox',
				'title'       => __( 'Banner widget styled shadow', 'qliro-for-woocommerce' ),
				'description' => __( 'Whether or not the banner should be rendered with a Qliro style shadow.', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'label'       => __( 'Display with a Qliro style shadow', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'payment_widget_title'                       => array(
				'title'       => __( 'Product widget', 'qliro-for-woocommerce' ),
				'type'        => 'title',
				'class'       => 'krokedil_settings_title',
				'description' => sprintf(
					/* translators: %s: Link to the documentation for displaying the widget via shortcode. */
					__(
						'Presents a suitable payment method based on the price of the current product. You can also display it with the shortcode [qliro_one_payment_widget], read more about it %s.',
						'qliro-for-woocommerce'
					),
					'<a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/customization/display-widget-via-shortcode/">' . __( 'here', 'qliro-for-woocommerce' ) . '</a>'
				),
			),
			'payment_widget_placement_location'          => array(
				'title'       => __( 'Payment widget placement on product pages', 'qliro-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( ' Choose where on the product page that you want to display the product widget.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none' => __( 'Inactive/shortcode placement', 'qliro-for-woocommerce' ),
					'4'    => __( 'Above Title', 'qliro-for-woocommerce' ),
					'7'    => __( 'Between Title and Price', 'qliro-for-woocommerce' ),
					'15'   => __( 'Between Price and Excerpt', 'qliro-for-woocommerce' ),
					'25'   => __( 'Between Excerpt and Add to cart button', 'qliro-for-woocommerce' ),
					'35'   => __( 'Between Add to cart button and Product meta', 'qliro-for-woocommerce' ),
					'45'   => __( 'Between Product meta and Product sharing buttons', 'qliro-for-woocommerce' ),
					'55'   => __( 'After Product sharing-buttons', 'qliro-for-woocommerce' ),
				),
				'default'     => '15',
				'desc'        => __( 'Select where to display the widget in your product pages.', 'qliro-for-woocommerce' ),
			),
			'payment_widget_data_condensed'              => array(
				'type'        => 'checkbox',
				'title'       => __( 'Payment widget condensed copy', 'qliro-for-woocommerce' ),
				'label'       => __( 'Display with a condensed and shorter copy', 'qliro-for-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'If enabled, the product widget will be rendered with shorter copy.', 'qliro-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'widgets_end'                                => array(
				'type' => 'krokedil_section_end',
			),
		);

			return apply_filters( 'qliro_one_gateway_settings', $settings );
	}
}

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
		$order_statuses_trigger = wc_get_order_statuses();
		$order_statuses_trigger = array( 'manual' => __( 'None', 'qliro-one-for-woocommerce' ) ) + $order_statuses_trigger;
		$order_statuses         = wc_get_order_statuses();
		$order_statuses         = array( 'none' => __( 'None', 'qliro-one-for-woocommerce' ) ) + $order_statuses;
		$wc_logs_url            = admin_url( 'admin.php?page=wc-status&tab=logs&source=qliro-for-woocommerce&paged=1' );
		$ppu_status             = class_exists( 'PPU' ) ? ' active' : ' inactive';

		$settings = array(
			// general.
			'general'                                    => array(
				'id'          => 'general',
				'title'       => __( 'General configuration', 'qliro-one-for-woocommerce' ),
				'type'        => 'krokedil_section_start',
				'description' => __( 'Get started with Qliro for WooCommerce by adding the API credentials you recieved from Qliro and enable Qliro as a payment method.', 'qliro-one-for-woocommerce' ),
			),
			'enabled'                                    => array(
				'title'       => __( 'Enable/Disable', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable Qliro as a payment method', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                                      => array(
				'title'       => __( 'Title', 'qliro-one-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The title is used throughout WooCommerce to display the payment method name at checkout and on order/payment admin pages.', 'qliro-one-for-woocommerce' ),
				'default'     => 'Qliro',
				'desc_tip'    => true,
			),
			'description'                                => array(
				'title'       => __( 'Description', 'qliro-one-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'The description could, depending on your theme and setup, be shown in relation to the payment method name at the checkout.', 'qliro-one-for-woocommerce' ),
				'default'     => 'Safe and simple payments.',
				'desc_tip'    => true,
			),
			// API credentials.
			'api_credentials_title'                      => array(
				'title' => __( 'API credentials', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
			),
			'api_key'                                    => array(
				'title'             => __( 'Production Qliro merchant API key', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Please contact your onboarding agent or email integration@qliro.com to get your API credentials.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'api_secret'                                 => array(
				'title'             => __( 'Production Qliro merchant API secret', 'qliro-one-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Please contact your onboarding agent or email integration@qliro.com to get your API credentials.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_api_key'                               => array(
				'title'             => __( 'Test Qliro merchant API key', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Used when test mode is enabled below. More information about how to test before becoming a Qliro customer <a target="_blank" href="https://krokedil.com/product/qliro-for-woocommerce/">here</a>.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_api_secret'                            => array(
				'title'             => __( 'Test Qliro merchant API secret', 'qliro-one-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Used when test mode is enabled below. More information about how to test before becoming a Qliro customer <a target="_blank" href="https://krokedil.com/product/qliro-for-woocommerce/">here</a>.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			// Debug.
			'debug_title'                                => array(
				'title' => __( 'Debug', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
			),
			'testmode'                                   => array(
				'title'       => __( 'Test mode', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable test mode', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Disable test mode when testing is complete and you want to go live.', 'qliro-one-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => false,
			),
			'logging'                                    => array(
				'title'       => __( 'Logging', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages from the plugin to the WooCommerce logs. Existing plugin logs can be found <a target="_blank" href="' . $wc_logs_url . '">here</a>.', 'qliro-one-for-woocommerce' ),
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
				'description' => __( 'Configure your checkout in relation to shipping, B2C/B2B purchases, newsletter signup and risk mitigations.', 'qliro-one-for-woocommerce' ),
			),
			'shipping_in_iframe'                         => array(
				'title'       => __( 'Shipping within the Qliro iframe', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Integrating with shipping service like nShift, Ingrid or Fraktjakt? Want to customize how WooCommerce shipping methods looks within the Qliro iframe? Read more about shipping related compatibility and configurations <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/shipping-settings/">here</a>.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_toggler krokedil_toggler_iframe_shipping toggler_option_wc_shipping',
				'options'     => array(
					'no'                  => __( 'No', 'qliro-one-for-woocommerce' ),
					'wc_shipping'         => __( 'WooCommerce shipping methods', 'qliro-one-for-woocommerce' ),
					'integrated_shipping' => __( 'Qliro integrated shipping with Ingrid', 'qliro-one-for-woocommerce' ),
				),
			),
			'shipping_additional_header'                 => array(
				'title'       => __( 'Shipping section description', 'qliro-one-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Display a custom text right under the shipping section’s main title. Useful to eg inform about a certain campaign or discounts on delivery for purchases over certain amount.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_iframe_shipping',
			),
			'qliro_one_enforced_juridical_type'          => array(
				'title'       => __( 'Allowed customer types', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'None'     => __( 'Both B2B & B2C', 'qliro-one-for-woocommerce' ),
					'Physical' => __( 'Only B2C', 'qliro-one-for-woocommerce' ),
					'Company'  => __( 'Only B2B', 'qliro-one-for-woocommerce' ),
				),
				'description' => __( 'Qliro supports both B2C and B2B checkout flows. Change the setting if you only want to allow B2C or B2B checkout flow.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'qliro_one_button_ask_for_newsletter_signup' => array(
				'title'       => __( 'Ask for newsletter signup', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show signup newsletter field', 'qliro-one-for-woocommerce' ),
				'description' => __( 'If enabled, Qliro Checkout will ask the customer if they want to sign up for a newsletter. Read more about how you can use the response from this field <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/newsletter-settings/">here</a>.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_toggler krokedil_toggler_newsletter_settings',
			),
			'qliro_one_button_ask_for_newsletter_signup_checked' => array(
				'title'       => __( 'Ask for newsletter signup checked by default', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Set signup newsletter field as checked by default', 'qliro-one-for-woocommerce' ),
				'description' => __( 'If enabled, Qliro Checkout will set signup newsletter as checked by default.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_newsletter_settings',
			),
			// Risk mitigation.
			'risk_mitigation'                            => array(
				'title' => __( 'Risk mitigation', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
				'description' => __(
					'Below you have the possibility to apply site-wide risk mitigation settings. Please note that you also have the possibility to set these settings on an individual product level, read more about it <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/get-started/introduction/#product-level-settings">here</a>.'
				),
			),
			'minimum_age'                                => array(
				'title'       => __( 'Minimum customer age', 'qliro-one-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Sets minimum customer age for all purchases, which then also prevents B2B purchases.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'css'         => 'width: 100px',
				'desc_tip'    => true,
			),
			'require_id_verification'                    => array(
				'title'       => __( 'Require identity verification', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Verify customers identity with BankID', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled and the order country is Sweden, the customer will always be asked to verify their identity with BankID when completing the purchase. This could lead to double BankID verification requirement in certain instances.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'has_risk'                                   => array(
				'title'       => __( 'Has risk', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Flag all products as has risk products', 'qliro-one-for-woocommerce' ),
				'description' => __( 'If enabled, all products in the order will be flagged as has risk products. This can be used to eg limit the list of available payment methods shown for customer.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			// Risk mitigation.
			'ppu_upsell'                                 => array(
				'title'       => __( 'Upsell on WooCommerce thank you page', 'qliro-one-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'The plugin "Post Purchase Upsell for WooCommerce" is needed to enable upsell on the WooCommerce thank you page. Read more about it <a target="_blank" href="https://krokedil.com/product/post-purchase-upsell-for-woocommerce/">here</a>.', 'qliro-one-for-woocommerce' ),
				'class'       => 'krokedil_settings_title krokedil_ppu_setting__title' . $ppu_status,
			),
			'upsell_percentage'                          => array(
				'title'             => __( 'Upsell percentage', 'qliro-one-for-woocommerce' ),
				'type'              => 'number',
				'css'               => 'width: 100px',
				'description'       => __( 'Set the max amount above the order value a customer can add to a Qliro order paid with a After Delivery payment. If you want higher than 10% you will first need to contact Qliro. Read more about upsell <a target="_blank" href="https://docs.krokedil.com/post-purchase-upsell-for-woocommerce/">here</a>.', 'qliro-one-for-woocommerce' ),
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
				'description' => __( 'Manage settings related to an order after it has been created, such as when capture and cancelation with Qliro should be initiated etc.', 'qliro-one-for-woocommerce' ),
			),
			'capture_status'                             => array(
				'title'       => __( 'Capture order status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses_trigger,
				'description' => __( 'Select WooCommerce order status used to initiate capturing the order in Qliros system. Suggested and default is to use Completed. Please note that you also have the possibility to disable order management on specific orders.', 'qliro-one-for-woocommerce' ),
				'default'     => 'wc-completed',
				'desc_tip'    => true,
			),
			'cancel_status'                              => array(
				'title'       => __( 'Cancel order status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses_trigger,
				'description' => __( 'Select WooCommerce order status used to initiate canceling the order in Qliros system. Suggested and default is to use Cancelled. Please note that you also have the possibility to disable order management on specific orders.', 'qliro-one-for-woocommerce' ),
				'default'     => 'wc-cancelled',
				'desc_tip'    => true,
			),
			'om_advanced_settings'                       => array(
				'title'       => __( 'Advanced pending status configuration', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Enable advanced pending status configuration', 'qliro-one-for-woocommerce' ),
				'description' => __( 'There is a delay when a capture or cancellation is initiated and WooCommerce receives the response. Therefore you have the possibility to customize what order status an order should have during this process. Use only in advanced situations.', 'qliro-one-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_toggler krokedil_toggler_om_advanced_settings',
			),
			'capture_pending_status'                     => array(
				'title'       => __( 'Pending capture order status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to while WooCommerce wait for Qliro to tell us if the capture was successful or not.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'capture_ok_status'                          => array(
				'title'       => __( 'OK capture order status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to when we get notified of a successful order capture from Qliro.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'cancel_pending_status'                      => array(
				'title'       => __( 'Pending cancel order status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to while we wait for Qliro to tell us if the cancelation was successful or not.', 'qliro-one-for-woocommerce' ),
				'default'     => 'none',
				'desc_tip'    => false,
				'class'       => 'krokedil_conditional_setting krokedil_conditional_om_advanced_settings',
			),
			'cancel_ok_status'                           => array(
				'title'       => __( 'OK cancel order status', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => $order_statuses,
				'description' => __( 'Select what WooCommerce order status to set the order to when we get notified of a successful order cancelation from Qliro.', 'qliro-one-for-woocommerce' ),
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
				'description' => __( 'Customize your checkout related to page layout, other payment method button text, colors and corner radius to make the look and feel of the checkout fit into your website in the best possible way.', 'qliro-one-for-woocommerce' ),
			),
			'checkout_layout'                            => array(
				'title'       => __( 'Checkout page layout', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'one_column_checkout' => __( 'One column checkout', 'qliro-one-for-woocommerce' ),
					'two_column_right'    => __( 'Two column checkout (Qliro One in right column)', 'qliro-one-for-woocommerce' ),
					'two_column_left'     => __( 'Two column checkout (Qliro One in left column)', 'qliro-one-for-woocommerce' ),
					'two_column_left_sf'  => __( 'Two column checkout (Qliro One in left column) - Storefront light', 'qliro-one-for-woocommerce' ),
				),
				'description' => __( 'Choose layout to use on the Qliro checkout page. Read more about the options and how the checkout page template can be further customized here.', 'qliro-one-for-woocommerce' ),
				'default'     => 'two_column_right',
				'desc_tip'    => false,
			),
			'other_payment_method_button_text'           => array(
				'title'             => __( 'Customize other payment method button text', 'qliro-one-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Customize the <em>Select another payment method</em> button text that is displayed on the checkout page if other payment methods than Qliro is enabled. Leave blank to use the default (and translatable) text.', 'qliro-one-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			// Look and feel.
			'look_and_feel_title'                        => array(
				'title' => __( 'Look and feel', 'qliro-one-for-woocommerce' ),
				'type'  => 'title',
				'class' => 'krokedil_settings_title',
			),
			'qliro_one_bg_color'                         => array(
				'title'       => __( 'Background color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'If the background should be something else than white, set the preferred hex color. Only colors with saturation <= 10% are supported. If a color with saturation > 10% is provided, the saturation will be lowered to 10%.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_primary_color'                    => array(
				'title'       => __( 'Primary color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the hex color for the selected options throughout the checkout. The spinner for loading in the checkout will have the same color.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_call_action_color'                => array(
				'title'       => __( 'Call to action color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the hex color for the CTA buttons throughout the checkout.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_call_action_hover_color'          => array(
				'title'       => __( 'Call to action hover color', 'qliro-one-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Define the hex color for the CTA buttons hoovered throughout the checkout. If not provided, the hover color will be a blend between the call to action color and the background color.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'qliro_one_corner_radius'                    => array(
				'title'       => __( 'Corner radius', 'qliro-one-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'A pixel value to be used on corners throughout Qliro Checkout, eg for the outline of payment or shipping methods. Changes will also apply on all fields to be filled in by customer, e.g. fields when customer authenticate.', 'qliro-one-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'css'         => 'width: 100px',
			),
			'qliro_one_button_corner_radius'             => array(
				'title'       => __( 'Button corner radius', 'qliro-one-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'A pixel value to be used on corners of CTA buttons throughout Qliro Checkout.', 'qliro-one-for-woocommerce' ),
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
				'title'       => __( 'Widgets', 'qliro-one-for-woocommerce' ),
				'type'        => 'krokedil_section_start',
				'description' => __( 'Setup Qliro payment widgets and banner widgets on your website.', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_title'                        => array(
				'title'       => __( 'Banner widget', 'qliro-one-for-woocommerce' ),
				'type'        => 'title',
				'class'       => 'krokedil_settings_title',
				'description' => __(
					'Promote Qliro payment methods and campaigns, without having to manually update banners continuously. You can also display it with the shortcode [qliro_one_banner_widget], read more about it <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/customization/display-widget-via-shortcode/)">here</a>.',
					'qliro-one-for-woocommerce'
				),
			),
			'banner_widget_data_method'                  => array(
				'type'        => 'select',
				'default'     => 'campaign',
				'title'       => __( 'Banner widget payment method', 'qliro-one-for-woocommerce' ),
				'description' => __( 'Choose the payment method to be presented in the banner widget.', 'woocommerce' ),
				'options'     => array(
					'campaign'     => __( 'Campaign', 'qliro-one-for-woocommerce' ),
					'invoice'      => __( 'Invoice', 'qliro-one-for-woocommerce' ),
					'part_payment' => __( 'Part payment', 'qliro-one-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'banner_widget_placement_location'           => array(
				'title'       => __( 'Banner widget placement on product pages', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose where on the product page that you want to display the banner widget.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none' => __( 'Inactive/shortcode placement', 'qliro-one-for-woocommerce' ),
					'4'    => __( 'Above Title', 'qliro-one-for-woocommerce' ),
					'7'    => __( 'Between Title and Price', 'qliro-one-for-woocommerce' ),
					'15'   => __( 'Between Price and Excerpt', 'qliro-one-for-woocommerce' ),
					'25'   => __( 'Between Excerpt and Add to cart button', 'qliro-one-for-woocommerce' ),
					'35'   => __( 'Between Add to cart button and Product meta', 'qliro-one-for-woocommerce' ),
					'45'   => __( 'Between Product meta and Product sharing buttons', 'qliro-one-for-woocommerce' ),
					'55'   => __( 'After Product sharing-buttons', 'qliro-one-for-woocommerce' ),
				),
				'default'     => 'none',
				'desc'        => __( 'Select where to display the widget in your product pages.', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_cart_placement_location'      => array(
				'title'       => __( 'Banner widget placement on cart page', 'qliro-one-for-woocommerce' ),
				'description' => __( 'Choose where on the cart page that you want to display the banner widget.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'options'     => array(
					'none'                            => __( 'Inactive/shortcode placement', 'qliro-one-for-woocommerce' ),
					'woocommerce_cart_collaterals'    => __( 'Above cross-sell', 'qliro-one-for-woocommerce' ),
					'woocommerce_before_cart_totals'  => __( 'Above cart totals', 'qliro-one-for-woocommerce' ),
					'woocommerce_proceed_to_checkout' => __( 'Between cart totals and proceed to checkout button', 'qliro-one-for-woocommerce' ),
					'woocommerce_after_cart_totals'   => __( 'After proceed to checkout button', 'qliro-one-for-woocommerce' ),
					'woocommerce_after_cart'          => __( 'Bottom of the page', 'qliro-one-for-woocommerce' ),
				),
				'default'     => 'woocommerce_cart_collaterals',
				'desc'        => __( 'Select where to display the widget on the cart page.', 'qliro-one-for-woocommerce' ),
			),
			'banner_widget_data_shadow'                  => array(
				'type'        => 'checkbox',
				'title'       => __( 'Banner widget styled shadow', 'qliro-one-for-woocommerce' ),
				'description' => __( 'Whether or not the banner should be rendered with a Qliro style shadow.', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'label'       => __( 'Display with a Qliro style shadow', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'payment_widget_title'                       => array(
				'title'       => __( 'Product widget', 'qliro-one-for-woocommerce' ),
				'type'        => 'title',
				'class'       => 'krokedil_settings_title',
				'description' => __(
					'Presents a suitable payment method based on the price of the current product. You can also display it with the shortcode [qliro_one_payment_widget], read more about it <a target="_blank" href="https://docs.krokedil.com/qliro-for-woocommerce/customization/display-widget-via-shortcode/">here</a>.'
				),
			),
			'payment_widget_placement_location'          => array(
				'title'       => __( 'Payment widget placement on product pages', 'qliro-one-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( ' Choose where on the product page that you want to display the product widget.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'none' => __( 'Inactive/shortcode placement', 'qliro-one-for-woocommerce' ),
					'4'    => __( 'Above Title', 'qliro-one-for-woocommerce' ),
					'7'    => __( 'Between Title and Price', 'qliro-one-for-woocommerce' ),
					'15'   => __( 'Between Price and Excerpt', 'qliro-one-for-woocommerce' ),
					'25'   => __( 'Between Excerpt and Add to cart button', 'qliro-one-for-woocommerce' ),
					'35'   => __( 'Between Add to cart button and Product meta', 'qliro-one-for-woocommerce' ),
					'45'   => __( 'Between Product meta and Product sharing buttons', 'qliro-one-for-woocommerce' ),
					'55'   => __( 'After Product sharing-buttons', 'qliro-one-for-woocommerce' ),
				),
				'default'     => '15',
				'desc'        => __( 'Select where to display the widget in your product pages.', 'qliro-one-for-woocommerce' ),
			),
			'payment_widget_data_condensed'              => array(
				'type'        => 'checkbox',
				'title'       => __( 'Payment widget condensed copy', 'qliro-one-for-woocommerce' ),
				'label'       => __( 'Display with a condensed and shorter copy', 'qliro-one-for-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'If enabled, the product widget will be rendered with shorter copy.', 'qliro-one-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'widgets_end'                                => array(
				'type' => 'krokedil_section_end',
			),
		);

			return apply_filters( 'qliro_one_gateway_settings', $settings );
	}
}

<?php
/**
 * Class for the request to create order.
 *
 * @package Qliro_One_Create_Order/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Request_Create_Order class.
 */
class Qliro_One_Request_Create_Order extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		// todo order id.
		$this->log_title = 'Create order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/merchantapi/orders';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$merchant_urls = QOC_WC()->merchant_urls->get_urls();
		$mer_ref       = null;
		$session       = WC()->session;

		$billing_country = WC()->checkout()->get_value( 'billing_country' );
		$session->set( 'qliro_one_billing_country', $billing_country );

		// Merchant reference.
		if ( $session->get( 'qliro_one_merchant_reference' ) ) {
			$mer_ref = $session->get( 'qliro_one_merchant_reference' );
		} else {
			$mer_ref = uniqid( 'q1' );
			$session->set( 'qliro_one_merchant_reference', $mer_ref );
		}

		$body = array(
			'MerchantReference'                    => $mer_ref,
			'Currency'                             => get_woocommerce_currency(),
			'Country'                              => WC()->checkout()->get_value( 'billing_country' ),
			'Language'                             => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantConfirmationUrl'              => $merchant_urls['confirmation'],
			'MerchantCheckoutStatusPushUrl'        => $merchant_urls['push'],
			'MerchantOrderManagementStatusPushUrl' => $merchant_urls['om_push'],
			'MerchantTermsUrl'                     => get_permalink( wc_get_page_id( 'terms' ) ),
			'AskForNewsletterSignup'               => $this->get_ask_for_newsletter(),
			'AskForNewsletterSignupChecked'        => $this->get_asked_for_newsletter_checked(),
			'OrderItems'                           => Qliro_One_Helper_Cart::get_cart_items(),
			'MerchantApiKey'                       => $this->get_qliro_key(),
			'AvailableShippingMethods'             => Qliro_One_Helper_Shipping_Methods::get_shipping_methods(),
		);

		if ( ! empty( $this->get_enforced_juridicial_type() ) ) {
			$body['EnforcedJuridicalType'] = $this->get_enforced_juridicial_type();
		}

		if ( ! empty( $this->get_primary_color() ) ) {
			$body['PrimaryColor'] = $this->get_primary_color();
		}

		if ( ! empty( $this->get_call_to_action_color() ) ) {
			$body['CallToActionColor'] = $this->get_call_to_action_color();
		}

		if ( ! empty( $this->get_call_to_action_hover_color() ) ) {
			$body['CallToActionHoverColor'] = $this->get_call_to_action_hover_color();
		}

		if ( ! empty( $this->get_background_color() ) ) {
			$body['BackgroundColor'] = $this->get_background_color();
		}

		if ( ! empty( $this->get_corder_radius() ) ) {
			$body['CornerRadius'] = $this->get_corder_radius();
		}

		if ( ! empty( $this->get_button_corder_radius() ) ) {
			$body['ButtonCornerRadius'] = $this->get_button_corder_radius();
		}

		if ( QOC_WC()->checkout()->is_integrated_shipping_enabled() ) {
			$body['MerchantProvidedMetadata'] = Qliro_One_Helper_Cart::get_ingrid_merchant_provided_metadata();
		}

		return Qliro_One_Helper_Order_Limitations::set_limitations( $body );
	}
}

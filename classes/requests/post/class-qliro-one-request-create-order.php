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
	 * The order id for the request, if it exists.
	 *
	 * @var int|null
	 */
	private $order_id;

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Create order';
		$this->order_id  = $arguments['order_id'] ?? null;
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
		if ( null === $this->order_id ) {
			return $this->get_body_from_cart();
		}

		return $this->get_body_from_order();
	}

	/**
	 * Get the body for a request using the cart data.
	 *
	 * @return array
	 */
	protected function get_body_from_cart() {
		$merchant_urls = QOC_WC()->merchant_urls->get_urls();
		$mer_ref       = null;
		$session       = WC()->session;

		$base_location   = wc_get_base_location();
		$billing_country = apply_filters( 'qliro_one_billing_country', WC()->checkout()->get_value( 'billing_country' ) ?? $base_location['country'] );
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
			'Country'                              => $billing_country,
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

		if ( ! empty( $merchant_urls['save_card'] ) ) {
			$body['MerchantSavedCreditCardPushURL'] = $merchant_urls['save_card'];
		}

		return Qliro_One_Helper_Order_Limitations::set_limitations( $body );
	}

	/**
	 * Get the body for a request using the order data.
	 *
	 * @return array
	 */
	protected function get_body_from_order() {
		$order = wc_get_order( $this->order_id );

		if ( ! $order ) {
			return array();
		}

		$merchant_urls = QOC_WC()->merchant_urls->get_urls( $order );
		$session       = WC()->session;

		$billing_country = WC()->checkout()->get_value( 'billing_country' );
		$session->set( 'qliro_one_billing_country', $billing_country );

		$body = array(
			'MerchantReference'                    => $order->get_order_number(),
			'Currency'                             => $order->get_currency(),
			'Country'                              => $order->get_billing_country(),
			'Language'                             => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantConfirmationUrl'              => $merchant_urls['confirmation'],
			'MerchantCheckoutStatusPushUrl'        => $merchant_urls['push'],
			'MerchantOrderManagementStatusPushUrl' => $merchant_urls['om_push'],
			'MerchantTermsUrl'                     => get_permalink( wc_get_page_id( 'terms' ) ),
			'AskForNewsletterSignup'               => $this->get_ask_for_newsletter(),
			'AskForNewsletterSignupChecked'        => $this->get_asked_for_newsletter_checked(),
			'OrderItems'                           => Qliro_One_Helper_Order::get_order_lines( $this->order_id ),
			'MerchantApiKey'                       => $this->get_qliro_key(),
			'EnforcedJuridicalType'                => $this->get_enforced_juridicial_type(),
			'PrimaryColor'                         => $this->get_primary_color(),
			'CallToActionColor'                    => $this->get_call_to_action_color(),
			'CallToActionHoverColor'               => $this->get_call_to_action_hover_color(),
			'BackgroundColor'                      => $this->get_background_color(),
			'CornerRadius'                         => $this->get_corder_radius(),
			'ButtonCornerRadius'                   => $this->get_button_corder_radius(),
		);

		if ( isset( $merchant_urls['save_card'] ) ) {
			$body['MerchantSavedCreditCardPushURL'] = $merchant_urls['save_card'];
		}

		// Remove any empty values from the body.
		$body = array_filter( $body );

		return Qliro_One_Helper_Order_Limitations::set_limitations( $body );
	}
}

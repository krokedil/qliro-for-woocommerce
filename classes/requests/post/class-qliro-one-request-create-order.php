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
		// todo temp merchant ref. save merchant ref to the session.
		$mer_ref = null;
		$session = WC()->session;

		$billing_country = WC()->checkout()->get_value( 'billing_country' );
		$session->set( 'qliro_one_billing_country', $billing_country );

		// todo if order is null do the else part.

		// merchant reference.
		if ( $session->get( 'qliro_one_merchant_reference' ) ) {
			$mer_ref = $session->get( 'qliro_one_merchant_reference' );
		} else {
			$mer_ref = uniqid( 'q1' );
			$session->set( 'qliro_one_merchant_reference', $mer_ref );
		}

		// todo check if billing_country is null.

		// todo save country to the session.

		return array(
			'MerchantReference'                    => $mer_ref,
			'Currency'                             => get_woocommerce_currency(),
			'Country'                              => WC()->checkout()->get_value( 'billing_country' ),
			'Language'                             => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantConfirmationUrl'              => $merchant_urls['confirmation'],
			'MerchantCheckoutStatusPushUrl'        => $merchant_urls['push'],
			'MerchantOrderManagementStatusPushUrl' => $merchant_urls['om_push'],
			'MerchantTermsUrl'                     => get_permalink( wc_get_page_id( 'terms' ) ),
			'PrimaryColor'                         => $this->get_primary_color(),
			'CallToActionColor'                    => $this->get_call_to_action_color(),
			'OrderItems'                           => Qliro_One_Helper_Cart::get_cart_items(),
			'MerchantApiKey'                       => $this->get_qliro_key(),
		);
	}
}

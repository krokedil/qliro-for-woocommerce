<?php
/**
 * Class for the request to create order.
 *
 * @package Qliro_One_Create_Order/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Create_Order class.
 */
class Qliro_One_Create_Order extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
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

		return array(
			'MerchantReference'                    => 'MerchantUniqueReference98',
			'Currency'                             => get_woocommerce_currency(),
			'Country'                              => WC()->checkout()->get_value( 'billing_country' ),
			'Language'                             => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantCheckoutStatusPushUrl'        => 'https://Merchant.com/push/',
			'MerchantConfirmationUrl'              => 'http://Merchant.com/confirmation/',
			'MerchantOrderManagementStatusPushUrl' => 'https://Merchant.com/push/',
			'MerchantTermsUrl'                     => get_permalink( wc_get_page_id( 'terms' ) ),
			'PrimaryColor'                         => '#00FF00',
			'CallToActionColor'                    => '#0000FF',
			'OrderItems'                           =>
			array(
				array(
					'MerchantReference'  => 'XXX',
					'Description'        => 'ZZZ',
					'Quantity'           => 4,
					'PricePerItemIncVat' => 450,
					'PricePerItemExVat'  => 450,
				),
			),
			'MerchantApiKey'                       => 'KROKE',
		);
	}
}

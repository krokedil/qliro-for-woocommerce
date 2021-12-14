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
		$merchant_urls = QOC_WC()->merchant_urls->get_urls();
		return array(
			'MerchantReference'                    => 'qliro-one-98',
			'Currency'                             => get_woocommerce_currency(),
			'Country'                              => WC()->checkout()->get_value( 'billing_country' ),
			'Language'                             => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantCheckoutStatusPushUrl'        => 'https://Merchant.com/push/',
			'MerchantConfirmationUrl'              => $merchant_urls['confirmation'],
			'MerchantOrderManagementStatusPushUrl' => 'https://Merchant.com/push/',
			'MerchantTermsUrl'                     => get_permalink( wc_get_page_id( 'terms' ) ),
			'PrimaryColor'                         => $this->get_primary_color(),
			'CallToActionColor'                    => $this->get_call_to_action_color(),
			'OrderItems'                           => array(
				array(
					'MerchantReference'  => 'XXX',
					'Description'        => 'ZZZ',
					'Quantity'           => 4,
					'PricePerItemIncVat' => 450,
					'PricePerItemExVat'  => 450,
				),
			),
			'MerchantApiKey'                       => $this->get_qliro_key(),
		);
	}
}

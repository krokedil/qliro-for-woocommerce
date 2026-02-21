<?php
/**
 * Request class for the generating a token for a saved credit card in Qliro.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Post
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Request_Save_Credit_Card
 */
class Qliro_One_Request_Save_Credit_Card extends Qliro_One_Request_Post {
	/**
	 * The log title to use.
	 *
	 * @var string
	 */
	protected $log_title = 'Save credit card';

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/merchantapi/CreateMerchantSavedCreditCard';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_data       = new Qliro_One_Helper_Order();
		$subscription     = wc_get_order( $this->arguments['order_id'] );
		$parent           = wc_get_order( $subscription->get_parent_id() );
		$merchant_urls    = QLIRO_WC()->merchant_urls->get_urls( $parent );
		$confirmation_url = Qliro_One_Subscriptions::get_add_card_confirmation_url( $subscription );

		$body = array(
			'RequestId'                      => $order_data->generate_request_id(),
			'MerchantApiKey'                 => $this->get_qliro_key(),
			'MerchantReference'              => uniqid( 'q1' ),
			'Currency'                       => $subscription->get_currency(),
			'Country'                        => $subscription->get_billing_country(),
			'Language'                       => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantSavedCreditCardPushUrl' => $merchant_urls['save_card'],
			'MerchantConfirmationUrl'        => $confirmation_url,
			'Customer'                       => array(
				'PersonalNumber' => $subscription->get_meta( '_qliro_personal_number' ) ?? '',
				'Email'          => $subscription->get_billing_email(),
				'JuridicalType'  => empty( $subscription->get_billing_company() ) ? 'Physical' : 'Company',
				'MobileNumber'   => $subscription->get_billing_phone(),
			),
		);

		return $body;
	}
}

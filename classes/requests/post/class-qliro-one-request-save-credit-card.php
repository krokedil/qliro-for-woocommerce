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
	 * The merchant reference sent with the request.
	 *
	 * @var string
	 */
	private $merchant_reference;

	/**
	 * The confirmation id sent with the request.
	 *
	 * @var string
	 */
	private $confirmation_id;

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args. Requires 'order_id', the id of the subscription.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );

		// Qliro registers each card as a new order, and rejects a merchant reference that has already been used.
		$this->merchant_reference = uniqid( 'q1card' );
		$this->confirmation_id    = Qliro_One_Merchant_URLS::generate_confirmation_id();
	}

	/**
	 * Get the merchant reference sent with the request.
	 *
	 * @return string
	 */
	public function get_merchant_reference() {
		return $this->merchant_reference;
	}

	/**
	 * Get the confirmation id sent with the request.
	 *
	 * It is included in the push URL Qliro delivers the card to, and must be stored on the
	 * subscription so the callback can be verified against it.
	 *
	 * @return string
	 */
	public function get_confirmation_id() {
		return $this->confirmation_id;
	}

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
		$order_data      = new Qliro_One_Helper_Order();
		$subscription    = wc_get_order( $this->arguments['order_id'] );
		$parent          = $subscription->get_parent();
		$push_url        = QLIRO_WC()->merchant_urls->get_save_card_push_url( $this->confirmation_id );
		$personal_number = $subscription->get_meta( '_qliro_personal_number' );

		if ( empty( $personal_number ) && ! empty( $parent ) ) {
			$personal_number = $parent->get_meta( '_qliro_personal_number' );
		}

		return array(
			'RequestId'                      => $order_data->generate_request_id(),
			'MerchantApiKey'                 => $this->get_qliro_key(),
			'MerchantReference'              => $this->merchant_reference,
			'Currency'                       => $subscription->get_currency(),
			'Country'                        => $subscription->get_billing_country(),
			'Language'                       => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantSavedCreditCardPushUrl' => $push_url,
			'MerchantConfirmationUrl'        => Qliro_One_Subscriptions::get_add_card_confirmation_url( $subscription ),
			'Customer'                       => array(
				'PersonalNumber' => $personal_number,
				'Email'          => $subscription->get_billing_email(),
				'JuridicalType'  => empty( $subscription->get_billing_company() ) ? 'Physical' : 'Company',
				'MobileNumber'   => $subscription->get_billing_phone(),
			),
		);
	}
}

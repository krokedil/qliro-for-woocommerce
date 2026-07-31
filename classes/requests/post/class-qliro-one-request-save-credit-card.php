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
	 * Class constructor.
	 *
	 * @param array $arguments The request args. Requires 'order_id', the id of the subscription.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );

		// Qliro registers each card as a new order, and rejects a merchant reference that has already been used.
		$this->merchant_reference = uniqid( 'q1card' );
	}

	/**
	 * Get the merchant reference sent with the request.
	 *
	 * Unique per registration, so it doubles as the reference the push URL is signed for.
	 *
	 * @return string
	 */
	public function get_merchant_reference() {
		return $this->merchant_reference;
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
		$push_url        = Qliro_One_Callback_Auth::add_token(
			Qliro_One_Subscriptions::absolutize_url( QLIRO_WC()->api_registry()->get_request_path( Qliro_One_API_Controller_Save_Card::class, 'save-card' ) ),
			$this->merchant_reference
		);
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

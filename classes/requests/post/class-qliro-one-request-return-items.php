<?php
/**
 * Class Qliro_One_Request_Return_Items.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Cancel_Order class.
 */
class Qliro_One_Request_Return_Items extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Return items';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/checkout/adminapi/v2/ReturnItems';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		// todo change request id and order id.
		return array(
			'RequestId'      => sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 16384, 20479 ), random_int( 32768, 49151 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ) ),
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => 5452321,
			'Currency'       => get_woocommerce_currency(),
			'Returns'        =>
				array(
					array(
						'PaymentTransactionId' => 5451215,
						'OrderItems'           =>
							array(

								array(
									'MerchantReference'  => 'Fancy RedHat from HM',
									'Type'               => 'Product',
									'Quantity'           => 2,
									'PricePerItemIncVat' => 375.55,
								),
							),
						'Fees'                 =>
							array(

								array(
									'MerchantReference'  => 'ReturnFee',
									'Description'        => 'Return Fee',
									'PricePerItemIncVat' => 100,
									'PricePerItemExVat'  => 66.66,
								),
							),
						'Discounts'            =>
							array(
								array(
									'MerchantReference'  => 'DiscountItem_1',
									'Description'        => 'Discount item',
									'PricePerItemIncVat' => -20,
									'PricePerItemExVat'  => -16,
								),
							),
					),
				),
		);
	}
}

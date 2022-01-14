<?php
/**
 * Qliro_One_Update_Merchant_Reference
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Update_Merchant_Reference class.
 */
class Qliro_One_Update_Merchant_Reference extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update merchant reference';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/checkout/adminapi/v2/updatemerchantreference';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_id       = $this->arguments['order_id'];
		$order          = wc_get_order( $order_id );
		$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id' );
		$order_data     = new Qliro_One_Request_Order();
		$request_id     = $order_data->generate_request_id();

		return array(
			'RequestId'            => $request_id,
			'MerchantApiKey'       => $this->get_qliro_key(),
			'OrderId'              => $qliro_order_id,
			'NewMerchantReference' => $order->get_order_number(),
		);
	}
}

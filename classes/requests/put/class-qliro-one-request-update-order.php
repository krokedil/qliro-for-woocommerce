<?php
/**
 * Class for the request to create order.
 *
 * @package Qliro_One_Update_Order/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for the request to add an item to the Qliro One order.
 */
class Qliro_One_Update_Order extends Qliro_One_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$order_id = $this->arguments['order_id'];
		return $this->get_api_url_base() . 'checkout/merchantapi/orders/' . $order_id;
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array(
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderItems'     => Qliro_One_Helper_Cart::get_cart_items(),
		);
	}
}

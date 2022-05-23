<?php
/**
 * Class for the request to upsell order.
 *
 * @package Qliro_One_Create_Order/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Request_Upsell_Order class.
 */
class Qliro_One_Request_Upsell_Order extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		// todo order id.
		$this->log_title = 'Upsell order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/merchantapi/Upsell';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_id          = $this->arguments['order_id'];
		$order             = wc_get_order( $order_id );
		$upsell_request_id = $this->arguments['upsell_request_id'];

		$body = array(
			'MerchantApiKey' => $this->get_qliro_key(),
			'RequestId'      => $upsell_request_id,
			'Currency'       => $order->get_currency(),
			'OrderId'        => $order->get_transaction_id(),
			'OrderItems'     => Qliro_One_Helper_Order::get_upsell_order_lines( $order_id, $upsell_request_id ),
		);

		return $body;
	}
}

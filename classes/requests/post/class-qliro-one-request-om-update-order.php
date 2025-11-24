<?php
/**
 * Class for the request to update order.
 *
 * @package Qliro_One_Create_Order/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Request_OM_Update_Order class.
 */
class Qliro_One_Request_OM_Update_Order extends Qliro_One_Request_Post {

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
		return $this->get_api_url_base() . 'checkout/adminapi/v2/UpdateItems';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_id   = $this->arguments['order_id'];
		$order      = wc_get_order( $order_id );
		$request_id = $this->arguments['request_id'];

		$this->qliro_order_id = $order->get_transaction_id(); // TODO: Test if this can use the meta data _qliro_one_order_id like all other requests.
		$transaction_id       = $order->get_meta( '_qliro_payment_transaction_id' );

		$body = array(
			'MerchantApiKey' => $this->get_qliro_key(),
			'RequestId'      => $request_id,
			'Currency'       => $order->get_currency(),
			'OrderId'        => $order->get_transaction_id(),
			'Updates'        => array(
				array(
					'PaymentTransactionId' => $transaction_id,
					'OrderItems'           => Qliro_One_Helper_Order::get_order_items( $order_id ),
				),
			),
		);

		return $body;
	}
}

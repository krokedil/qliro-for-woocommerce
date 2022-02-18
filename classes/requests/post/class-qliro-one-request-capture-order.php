<?php
/**
 * Class for the request to capture the order.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Capture_Order class.
 */
class Qliro_One_Capture_Order extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		// todo order id.
		$this->log_title = 'Capture order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/checkout/adminapi/v2/MarkItemsAsShipped';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_id               = $this->arguments['order_id'];
		$payment_transaction_id = get_post_meta( $order_id, '_payment_transaction_id', true );
		$order_data             = new Qliro_One_Helper_Order();
		// todo update the params.
		$body = array(
			'RequestId'      => $order_data->generate_request_id(),
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => get_post_meta( $order_id, '_qliro_one_order_id', true ),
			'Currency'       => get_woocommerce_currency(),
			'Shipments'      => array(
				array(
					'OrderItems' => $order_data::get_order_lines( $order_id ),
				),
			),
		);

		if ( ! empty( $payment_transaction_id ) ) {
			$body['Shipments'][0]['PaymentTransactionId'] = $payment_transaction_id;
		}

		return $body;
	}
}

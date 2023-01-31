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
		return $this->get_api_url_base() . 'checkout/adminapi/v2/ReturnItems';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_data             = new Qliro_One_Helper_Order();
		$request_id             = $order_data->generate_request_id();
		$order_id               = $this->arguments['order_id'];
		$refund_order_id        = $this->arguments['refund_order_id'];
		$qliro_one_order_id     = get_post_meta( $order_id, '_qliro_one_order_id', true );
		$capture_transaction_id = get_post_meta( $order_id, '_qliro_order_captured', true );
		return array(
			'RequestId'      => $request_id,
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => $qliro_one_order_id,
			'Currency'       => get_woocommerce_currency(),
			'Returns'        => array(
				array(
					'PaymentTransactionId' => $capture_transaction_id,
					'OrderItems'           => $order_data->get_return_items( $refund_order_id ),
					'Fees'                 => apply_filters( 'qliro_one_return_fees', array() ),
				),
			),
		);
	}
}

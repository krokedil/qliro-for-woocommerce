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
		$this->log_title = 'Capture order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/adminapi/v2/MarkItemsAsShipped';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_id                = $this->arguments['order_id'];
		$items                   = $this->arguments['items'];
		$order                   = wc_get_order( $order_id );
		$payment_transaction_id  = $order->get_meta( '_qliro_payment_transaction_id' );

		// Check if the transaction id is empty. If it is we need to get it from the old transaction id meta.
		if ( empty( $payment_transaction_id ) ) {
			$payment_transaction_id = $order->get_meta( '_payment_transaction_id' );
		}

		$order_data           = new Qliro_One_Helper_Order();
		$this->qliro_order_id = $order->get_meta( '_qliro_one_order_id' );
		$order_items          = $order_data::get_order_items( $order_id, $items );

		$shipments = Qliro_Order_Utility::maybe_convert_to_split_transactions( $order_items, $order );
		// If we failed to convert the order items to shipments, use the old logic to send the shipments in a single shipment.
		if ( empty( $shipments ) ) {
			$shipments = array(
				array(
					'PaymentTransactionId' => $payment_transaction_id,
					'OrderItems'           => $order_items,
				),
			);
		}

		$body = array(
			'RequestId'      => $order_data->generate_request_id(),
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => $this->qliro_order_id,
			'Currency'       => $order->get_currency(),
			'Shipments'      => $shipments,
		);

		return $body;
	}
}

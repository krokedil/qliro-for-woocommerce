<?php
/**
 * Class Qliro_One_Request_Update_Items.
 *
 * Update items on order if it has not been shipped and contains at least one item that has not been marked as shipped. If the order has already been marked as shipped, use the the Qliro_One_Request_Add_Items class to add items to the order instead.
 *
 * See https://developers.qliro.com/docs/api/v2UpdateItems-post
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Request_Update_Items class.
 */
class Qliro_One_Request_Update_Items extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update items';
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
		$order_data = new Qliro_One_Helper_Order();

		$order_id             = $this->arguments['order_id'];
		$order                = wc_get_order( $order_id );
		$this->qliro_order_id = $order->get_meta( '_qliro_one_order_id' );

		$transaction_id = $order->get_meta( '_qliro_order_captured' );
		if ( empty( $transaction_id ) ) {
			$transaction_id = $order->get_meta( '_qliro_payment_transaction_id' );
		}

		$updates = Qliro_Order_Utility::maybe_convert_to_split_transactions( $this->arguments['items'], $order );
		// If we failed to convert the order items to updates, use the old logic to send the updates in a single update.
		if ( empty( $updates ) ) {
			$updates = array(
				array(
					'PaymentTransactionId' => $transaction_id,
					'OrderItems'           => $this->arguments['items'],
				),
			);
		}

		return array(
			'RequestId'      => $order_data->generate_request_id(),
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => $this->qliro_order_id,
			'Currency'       => $order->get_currency(),
			'Updates'        => $updates,
		);
	}
}

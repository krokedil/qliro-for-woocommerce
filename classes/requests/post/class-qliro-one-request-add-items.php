<?php
/**
 * Class Qliro_One_Request_Add_Items.
 *
 * Use this feature to add discounts to an invoice after the order has been submitted and activated. Additional fees may apply.
 *
 * For details on the request/response parameters as well as error codes, please continue to the https://developers.qliro.com/docs/api/v2AddItemsToInvoice-post
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Request_Add_Items class.
 */
class Qliro_One_Request_Add_Items extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Add items';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/adminapi/v2/AddItemsToInvoice';
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

		$additions = Qliro_Order_Utility::maybe_convert_to_split_transactions( $this->arguments['items'], $order );
		// If we failed to convert the order items to additions, use the old logic to send the additions in a single addition.
		if ( empty( $additions ) ) {
			$additions = array(
				array(
					'PaymentTransactionId' => $transaction_id,
					'OrderItems'           => $this->arguments['items'],
				),
			);
		}

		return array(
			'MerchantApiKey' => $this->get_qliro_key(),
			'RequestId'      => $order_data->generate_request_id(),
			'OrderId'        => $this->qliro_order_id,
			'Currency'       => $order->get_currency(),
			'Additions'      => $additions,
		);
	}
}

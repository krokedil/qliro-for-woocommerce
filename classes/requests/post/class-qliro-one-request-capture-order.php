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
		$order                  = wc_get_order( $order_id );
		$payment_transaction_id = get_post_meta( $order_id, '_payment_transaction_id' );
		// todo update the params.
		return array(
			'RequestId'      => sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 16384, 20479 ), random_int( 32768, 49151 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ) ),
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => get_post_meta( $order_id, '_qliro_one_order_id', true ),
			'Currency'       => 'SEK',
			'Shipments'      => array(
				array(
					'PaymentTransactionId' => $payment_transaction_id,
					'OrderItems'           => array(
						array(
							'MerchantReference'  => 'Fancy RedHat from HM',
							'Type'               => 'Product',
							'Quantity'           => 2,
							'PricePerItemIncVat' => 375.55,
							'Metadata'           => array(
								'HeaderLines' => array(
									'string',
								),
								'FooterLines' => array(
									'string',
								),
							),
						),
					),
				),
			),
		);
	}
}

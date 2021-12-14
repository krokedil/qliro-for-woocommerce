<?php
/**
 * Class for the request to cancel order.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Cancel_Order class.
 */
class Qliro_One_Cancel_Order extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Cancel order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/checkout/adminapi/v2/cancelOrder';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		// todo change request id and order id.
		return array(
			'RequestId'      => 'd92b8c60-6781-4946-9854-e70b27452d5a',
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => 5452321,
		);
	}
}

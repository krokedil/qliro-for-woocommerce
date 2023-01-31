<?php
/**
 * Class Qliro_One_Request_Admin_Get_Order.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Get
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get admin Qliro One order.
 */
class Qliro_One_Request_Admin_Get_Order extends Qliro_One_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Get order ( admin )';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$order_id = $this->arguments['order_id'];
		return $this->get_api_url_base() . 'checkout/adminapi/v2/orders/' . $order_id;
	}

}

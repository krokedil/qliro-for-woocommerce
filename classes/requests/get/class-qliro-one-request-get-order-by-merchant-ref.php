<?php
/**
 * Class for the request to get order information
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/GET
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get Qliro order by merchant reference.
 */
class Qliro_One_Request_Get_Order_By_Merchant_Ref extends Qliro_One_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Get order by merchant reference';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$merchant_reference = $this->arguments['merchant_reference'];
		// todo get merchant reference from session or post meta.
		// todo check docs, it's unclear.
		return $this->get_api_url_base() . 'checkout/merchantapi/orders/' . $merchant_reference;
	}
}

<?php
/**
 * Qliro_One_Update_Merchant_Reference
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Update_Merchant_Reference class.
 */
class Qliro_One_Update_Merchant_Reference extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update merchant reference';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/checkout/adminapi/v2/updatemerchantreference';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		// todo change request id and order id.
		return array(
			'RequestId'            => 'fd421f5f-d5cb-442b-a45e-de46dc38b586',
			'MerchantApiKey'       => $this->get_qliro_key(),
			'OrderId'              => 1215412,
			'NewMerchantReference' => 'merchant-reference-new',
		);
	}
}

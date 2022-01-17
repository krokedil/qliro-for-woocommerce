<?php
/**
 * Main class for PATCH requests.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * The main class for PATCH requests.
 */
abstract class Qliro_One_Request_Put extends Qliro_One_Request {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->method = 'PUT';
	}

	/**
	 * Builds the request args for a PUT request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		$body  = wp_json_encode( $this->get_body() );
		$array = array(
			'headers'    => $this->get_request_headers( $body ),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'body'       => $body,
		);

		return $array;
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}

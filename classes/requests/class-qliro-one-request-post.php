<?php
/**
 * Base class for all POST requests.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Request
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 *  The main class for POST requests.
 */
abstract class Qliro_One_Request_Post extends Qliro_One_Request {

	/**
	 * Qliro_One_Request_Post constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'POST';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		$body = $this->get_body();

		// Format exception. Finnish needs to be formated to fi-fi.
		if ( 'fi' === wc_get_var( $body['Language'] ) ) {
			$body['Language'] = 'fi-fi';
		}

		$encoded_body = wp_json_encode( apply_filters( 'qliro_one_request_args', $body ) );

		return array(
			'headers'    => $this->get_request_headers( $encoded_body ),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'timeout'    => apply_filters( 'qliro_one_request_timeout', 10 ),
			'body'       => $encoded_body,
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}

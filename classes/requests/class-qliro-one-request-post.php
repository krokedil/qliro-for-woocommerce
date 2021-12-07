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
	 * Calculates the Qliro One auth.
	 *
	 * @return string
	 */
	protected function calculate_auth() {
		$secret = 'yes' === $this->settings['testmode'] ? 'test_api_secret' : 'api_secret';
		return 'Qliro ' . base64_encode( hex2bin( hash( 'sha256', wp_json_encode( $this->get_body() ) . $this->settings[ $secret ] ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'timeout'    => apply_filters( 'qliro_one_request_timeout', 10 ),
			'body'       => wp_json_encode( apply_filters( 'qliro_one_request_args', $this->get_body() ) ),
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}

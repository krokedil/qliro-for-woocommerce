<?php
/**
 * Main class for GET requests.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * The main class for GET requests.
 */
abstract class Qliro_One_Request_Get extends Qliro_One_Request {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->method = 'GET';
	}

	/**
	 * Builds the request args for a GET request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
		);
	}

	/**
	 * Calculates the basic auth.
	 *
	 * @param $payload array
	 * @return string
	 */
	protected function calculate_auth() {
		$secret = 'yes' === $this->settings['testmode'] ? 'test_api_secret' : 'api_secret';
		return 'Qliro ' . base64_encode( hex2bin( hash( 'sha256', '' . $this->settings[ $secret ] ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
	}
}

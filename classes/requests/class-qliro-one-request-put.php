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
	 * Calculates the Qliro One auth.
	 *
	 * @return string
	 */
	protected function calculate_auth() {
		$secret = 'yes' === $this->settings['testmode'] ? 'test_api_secret' : 'api_secret';
		return 'Qliro ' . base64_encode( hex2bin( hash( 'sha256', wp_json_encode( $this->get_body() ) . $this->settings[ $secret ] ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
	}

	/**
	 * Builds the request args for a PUT request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		$array = array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'body'       => wp_json_encode( $this->get_body() ),
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

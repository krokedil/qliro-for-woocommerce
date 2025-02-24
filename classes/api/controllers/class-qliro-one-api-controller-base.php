<?php
/**
 * Base controller class for the Avarda Checkout API.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package Qliro_One_For_WooCommerce/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_API_Controller_Base
 */
abstract class Qliro_One_API_Controller_Base {
	/**
	 * The namespace of the controller.
	 *
	 * @var string
	 */
	protected $namespace = 'qliro';

	/**
	 * The version of the controller.
	 *
	 * @var string
	 */
	protected $version = 'v1';

	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Get the base path for the controller.
	 *
	 * @return string
	 */
	protected function get_base_path() {
		// Combine the version and path to create the base path, ensuring that the path doesn't start or end with a slash.
		return trim( "{$this->version}/{$this->path}", '/' );
	}

	/**
	 * Get the request path for a specific endpoint.
	 *
	 * @param string $endpoint The endpoint to get the path for.
	 *
	 * @return string
	 */
	public function get_request_path() {
		$base_path = $this->get_base_path();
		return trim( "{$base_path}", '/' );
	}

	/**
	 * Send a response.
	 *
	 * @param object|array|null|WP_Error $response Response object.
	 * @param int                        $status_code Status code.
	 *
	 * @return void
	 */
	protected function send_response( $response, $status_code = 200 ) {
		// Check if the response is a WP_Error.
		if ( is_wp_error( $response ) ) {
			$this->send_error_response( $response );
		}
	}

	/**
	 * Send a error response.
	 *
	 * @param WP_Error $wp_error The error object.
	 *
	 * @return void
	 */
	protected function send_error_response( $wp_error ) {
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	abstract public function register_routes();
}

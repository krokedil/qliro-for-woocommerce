<?php
/**
 * Main request class
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
 */
abstract class Qliro_One_Request {

	/**
	 * The request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The request title.
	 *
	 * @var string
	 */
	protected $log_title;

	/**
	 * The Qliro One order id.
	 *
	 * @var string
	 */
	protected $qliro_order_id;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;


	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments = $arguments;
		$this->load_settings();
	}

	/**
	 * Loads the Qliro settings and sets them to be used here.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_qliro_one_settings' );
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		if ( 'yes' === $this->settings['testmode'] ) {
			return 'https://pago.qit.nu/';
		}

		return 'https://payments.qit.nu/';
	}

	/**
	 * Get the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Content-type'  => 'application/json',
			'Authorization' => $this->calculate_auth(),
		);
	}

	/**
	 * Calculates the basic auth.
	 *
	 * @return string
	 */
	abstract protected function calculate_auth();

	/**
	 * Get the user agent.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		) . ' - WooCommerce: ' . WC()->version . ' - QLIRO ONE: ' . QLIRO_WC_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	abstract protected function get_request_args();

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Make the request.
	 *
	 * @return object|WP_Error
	 */
	public function request() {
		$url      = $this->get_request_url();
		$args     = $this->get_request_args();
		$response = wp_remote_request( $url, $args );
		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );

				foreach ( $errors as $key => $value ) {
					$error_message .= ' ' . $key . ': ' . $value . ',';
				}
			}
			$response = new WP_Error( wp_remote_retrieve_response_code( $response ), $response['body'] . $error_message, $data );
		} else {
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		$this->log_response( $response, $request_args, $request_url, $response_code );

		return $response;
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request URL.
	 * @param int|string      $code The response code.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url, $code ) {
		$method = $this->method;
		$title  = "{$this->log_title} - URL: {$request_url}";
		$log    = Qliro_One_Logger::format_log( 'qliro_order_id_todo', $method, $title, $request_args, $response, $code, $request_url );
		Qliro_One_Logger::log( $log );
	}

	/**
	 * Get the api secret.
	 *
	 * @return string
	 */
	protected function get_qliro_secret() {
		if ( 'yes' === $this->settings['testmode'] ) {
			return $this->settings['test_api_secret'];
		}
		return $this->settings['api_secret'];
	}

	/**
	 * Get the api key.
	 *
	 * @return string
	 */
	protected function get_qliro_key() {
		if ( 'yes' === $this->settings['testmode'] ) {
			return $this->settings['test_api_key'];
		}
		return $this->settings['api_key'];
	}

	/**
	 * Get the primary color.
	 *
	 * @return string
	 */
	public function get_primary_color() {
		// todo maybe option.
		$default_value = '#00FF00';
		if ( empty( $this->settings['qliro_one_primary_color'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_primary_color'];
	}

	/**
	 * Get the call to action color.
	 *
	 * @return string
	 */
	public function get_call_to_action_color() {
		// todo maybe option.
		$default_value = '#0000FF';
		if ( empty( $this->settings['qliro_one_call_action_color'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_call_action_color'];
	}
}

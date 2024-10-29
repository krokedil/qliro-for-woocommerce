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

		if ( $arguments['qliro_order_id'] ?? false ) {
			$this->qliro_order_id = $arguments['qliro_order_id'];
		}

		$this->load_settings();

		error_log( 'settings qliro_one_enforced_juridical_type ' . var_export( $this->settings['qliro_one_enforced_juridical_type'], true ) );
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
	 * @param string $body json_encoded body.
	 * @return array
	 */
	protected function get_request_headers( $body = '' ) {
		return array(
			'Content-type'  => 'application/json',
			'Authorization' => $this->calculate_auth( $body ),
		);
	}

	/**
	 * Calculates the basic auth.
	 *
	 * @param string $body json_encoded body.
	 * @return string
	 */
	protected function calculate_auth( $body ) {
		$secret = 'yes' === $this->settings['testmode'] ? 'test_api_secret' : 'api_secret';
		return 'Qliro ' . base64_encode( hex2bin( hash( 'sha256', $body . $this->settings[ $secret ] ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
	}

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

				foreach ( $errors as $error ) {
					$error_message .= ' ' . $error;
				}
			}
			$code          = wp_remote_retrieve_response_code( $response );
			$error_message = empty( $response['body'] ) ? "API Error {$code}" : json_decode( $response['body'], true )['ErrorMessage'];
			$return        = new WP_Error( $code, $error_message, $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		$this->log_response( $response, $request_args, $request_url );
		return $return;
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request URL.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url ) {
		$method        = $this->method;
		$title         = "{$this->log_title} - URL: {$request_url}";
		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$qliro_order_id = $this->qliro_order_id ?? $response_body['OrderId'] ?? null; // Get the qliro order id if its set, else get it from the response body if possible. Else set it to null.
		$log            = Qliro_One_Logger::format_log( $qliro_order_id, $method, $title, $request_args, $response, $code, $request_url );
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
		$default_value = '';
		if ( empty( $this->settings['qliro_one_primary_color'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_primary_color'];
	}

	/**
	 * Get the primary color.
	 *
	 * @return string
	 */
	public function get_background_color() {
		$default_value = '';
		if ( empty( $this->settings['qliro_one_bg_color'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_bg_color'];
	}

	/**
	 * Get the call to action color.
	 *
	 * @return string
	 */
	public function get_call_to_action_color() {
		$default_value = '';
		if ( empty( $this->settings['qliro_one_call_action_color'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_call_action_color'];
	}

	/**
	 * Get the call to action hover color.
	 *
	 * @return string
	 */
	public function get_call_to_action_hover_color() {
		$default_value = '';
		if ( empty( $this->settings['qliro_one_call_action_hover_color'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_call_action_hover_color'];
	}

	/**
	 * Get the corner radius.
	 *
	 * @return int
	 */
	public function get_corder_radius() {
		$default_value = 0;
		if ( empty( $this->settings['qliro_one_corner_radius'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_corner_radius'];
	}

	/**
	 * Get the button corner radius.
	 *
	 * @return int
	 */
	public function get_button_corder_radius() {
		$default_value = 0;
		if ( empty( $this->settings['qliro_one_button_corner_radius'] ) ) {
			return $default_value;
		}

		return $this->settings['qliro_one_button_corner_radius'];
	}

	/**
	 * Get the enforced juridicial type.
	 *
	 * @return string
	 */
	public function get_enforced_juridicial_type() {
		$default_value = '';
		if ( empty( $this->settings['qliro_one_enforced_juridical_type'] ) || 'None' === $this->settings['qliro_one_enforced_juridical_type'] ) {
			return apply_filters( 'qliro_one_enforced_juridical_type', $default_value );
		}

		return apply_filters( 'qliro_one_enforced_juridical_type', $this->settings['qliro_one_enforced_juridical_type'] );
	}

	/**
	 * Get if we should ask for newsletter signup.
	 *
	 * @return bool
	 */
	public function get_ask_for_newsletter() {
		$default_value = false;
		if ( empty( $this->settings['qliro_one_button_ask_for_newsletter_signup'] ) ) {
			return $default_value;
		}

		return 'yes' === $this->settings['qliro_one_button_ask_for_newsletter_signup'];
	}

	/**
	 * Get if newsletter signup should be checked by default.
	 *
	 * @return bool
	 */
	public function get_asked_for_newsletter_checked() {
		$default_value = false;
		if ( empty( $this->settings['qliro_one_button_ask_for_newsletter_signup'] ) ) {
			return $default_value;
		}

		return 'yes' === $this->settings['qliro_one_button_ask_for_newsletter_signup'];
	}
}

<?php
/**
 * Helper for authenticating callbacks from Qliro.
 *
 * @package Qliro_One_For_WooCommerce/Classes/API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Callback_Auth
 */
class Qliro_One_Callback_Auth {

	/**
	 * Query arg holding the reference the token was signed for.
	 *
	 * @var string
	 */
	const REF_PARAM = 'qliro_callback_ref';

	/**
	 * Query arg holding the signature.
	 *
	 * @var string
	 */
	const TOKEN_PARAM = 'qliro_callback_token';

	/**
	 * Option name for the site-wide signing secret.
	 *
	 * @var string
	 */
	const SECRET_OPTION = 'qliro_one_callback_secret';

	/**
	 * Get the site-wide signing secret, creating it on first use.
	 *
	 * @return string
	 */
	private static function get_secret() {
		$secret = get_option( self::SECRET_OPTION );

		if ( empty( $secret ) ) {
			$secret = bin2hex( random_bytes( 32 ) );
			// Not autoloaded - only read when generating callback URLs or verifying callbacks.
			update_option( self::SECRET_OPTION, $secret, false );
		}

		return $secret;
	}

	/**
	 * Sign a reference with the site-wide secret.
	 *
	 * @param string $reference The reference to sign (e.g. merchant reference or confirmation id).
	 *
	 * @return string The hex-encoded HMAC-SHA256 signature.
	 */
	public static function sign( $reference ) {
		return hash_hmac( 'sha256', (string) $reference, self::get_secret() );
	}

	/**
	 * Append the reference and signature to a callback URL.
	 *
	 * @param string $url       The callback URL to authenticate.
	 * @param string $reference A value that is unique per order/checkout (e.g. merchant reference).
	 *
	 * @return string The URL with the authentication query args appended.
	 */
	public static function add_token( $url, $reference ) {
		return add_query_arg(
			array(
				self::REF_PARAM   => (string) $reference,
				self::TOKEN_PARAM => self::sign( $reference ),
			),
			$url
		);
	}

	/**
	 * Check whether a token is the valid signature for a given reference.
	 *
	 * Use this for callbacks that are not served through the REST API and therefore
	 * have no WP_REST_Request to pass to verify_request().
	 *
	 * @param string $reference The reference the token should have been signed for.
	 * @param string $token     The signature received in the callback.
	 *
	 * @return bool True when the token is a valid signature for the reference.
	 */
	public static function is_valid_token( $reference, $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		return hash_equals( self::sign( $reference ), (string) $token );
	}

	/**
	 * Verify the authentication token on an incoming callback request.
	 *
	 * Intended for use as a REST permission_callback. Returns true when the request
	 * is authenticated, or a WP_Error (401) when the token is missing or invalid.
	 *
	 * For legacy reasons there is a filter to allow unauthenticated callbacks,
	 * but it is recommended to ensure that all callbacks are authenticated.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return true|WP_Error
	 */
	public static function verify_request( $request ) {
		$reference = $request->get_param( self::REF_PARAM );
		$token     = $request->get_param( self::TOKEN_PARAM );

		if ( empty( $token ) ) {
			Qliro_One_Logger::log( '[CALLBACK AUTH]: Received a callback without an authentication token. Allowing during the grace period for legacy orders. Enabled via the "qliro_one_allow_unauthenticated_callbacks" filter if you have issues with legacy orders.' );

			/**
			 * Filter whether to allow callbacks that arrive without an authentication token.
			 *
			 * @param bool            $allow   Whether to allow the unauthenticated callback. Default false.
			 * @param WP_REST_Request $request The incoming request.
			 */
			$allow = apply_filters( 'qliro_one_allow_unauthenticated_callbacks', false, $request );

			if ( $allow ) {
				return true;
			}

			return new WP_Error( 'qliro_missing_callback_token', 'Missing callback authentication token.', array( 'status' => 401 ) );
		}

		if ( ! self::is_valid_token( $reference, $token ) ) {
			Qliro_One_Logger::log( "[CALLBACK AUTH]: Rejected a callback with an invalid authentication token for reference '{$reference}'." );
			return new WP_Error( 'qliro_invalid_callback_token', 'Invalid callback authentication token.', array( 'status' => 401 ) );
		}

		return true;
	}
}

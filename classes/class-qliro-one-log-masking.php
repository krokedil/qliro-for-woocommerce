<?php
/**
 * Log masking class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

use KrokedilQliroDeps\Krokedil\WpApi\FieldMasker;
use KrokedilQliroDeps\Krokedil\WpApi\KeyMasker;

/**
 * What the plugin masks out of its logs.
 *
 * The configured rules describe the Qliro payloads we know about. The key names are the
 * safety net for everything else that reaches a log entry.
 */
class Qliro_One_Log_Masking {
	/**
	 * The address fields kept readable, since a rejected address is a common support case
	 * and none of these identify a person on their own.
	 */
	const ADDRESS_KEPT = array( 'PostalCode', 'City', 'Country', 'CountryCode' );

	/**
	 * The customer fields kept readable. The addresses are described by their own rules.
	 */
	const CUSTOMER_KEPT = array( 'JuridicalType', 'Address', 'ShippingAddress' );

	/**
	 * Key names masked wherever they appear, on top of the package defaults.
	 *
	 * @var string[]
	 */
	private static $key_names = array(
		'FirstName',
		'LastName',
		'first_name',
		'last_name',
		'Street',
		'CareOf',
		'CompanyName',
		'OrganizationNumber',
		'Email',
		'MobileNumber',
		'Phone',
		'PersonalNumber',
		// A saved card id is what a renewal is charged with.
		'SavedCreditCardId',
		// The checkout snippet and payment links let whoever holds them pay for the order.
		'HtmlSnippet',
		'PaymentLink',
		'UpsellLink',
	);

	/**
	 * Widen the package key name masking with the names Qliro uses.
	 *
	 * @return void
	 */
	public static function register() {
		KeyMasker::add_keys( self::$key_names );
	}

	/**
	 * The rules for a Qliro request or response body.
	 *
	 * @return array
	 */
	public static function body_fields() {
		return array(
			'Address'                 => array( 'keep' => self::ADDRESS_KEPT ),
			'BillingAddress'          => array( 'keep' => self::ADDRESS_KEPT ),
			'ShippingAddress'         => array( 'keep' => self::ADDRESS_KEPT ),
			'Customer'                => array( 'keep' => self::CUSTOMER_KEPT ),
			'CustomerInformation'     => array( 'keep' => self::CUSTOMER_KEPT ),
			'MerchantSavedCreditCard' => array( 'Id' ),
		);
	}

	/**
	 * The rules for a whole set of request args.
	 *
	 * @return array
	 */
	public static function request_fields() {
		return array(
			'headers' => array( 'Authorization' ),
			'body'    => self::body_fields(),
		);
	}

	/**
	 * Mask a set of request args.
	 *
	 * @param array $request_args The request args.
	 * @return array|string The masked args, or the failure marker.
	 */
	public static function mask_request( $request_args ) {
		try {
			// Decode the body that was really sent, so the rules can reach into it.
			if ( isset( $request_args['body'] ) && is_string( $request_args['body'] ) ) {
				$decoded              = json_decode( $request_args['body'], true );
				$request_args['body'] = is_array( $decoded ) ? $decoded : $request_args['body'];
			}

			return FieldMasker::mask( $request_args, self::request_fields() );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask a decoded response body.
	 *
	 * @param array|null $body The decoded response body.
	 * @return array|string|null The masked body, or the failure marker.
	 */
	public static function mask_response( $body ) {
		if ( empty( $body ) || ! is_array( $body ) ) {
			return $body;
		}

		try {
			return FieldMasker::mask( $body, self::body_fields() );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask a finished log entry by key name, and the credentials carried in URLs.
	 *
	 * @param mixed $data The log entry.
	 * @return mixed The masked entry, or the failure marker.
	 */
	public static function mask_entry( $data ) {
		// A failure here costs the entry, it never lets an unmasked one through.
		try {
			$data = KeyMasker::mask( $data );

			if ( is_string( $data ) ) {
				return self::mask_urls( $data );
			}

			if ( is_array( $data ) ) {
				array_walk_recursive(
					$data,
					function ( &$value ) {
						if ( is_string( $value ) ) {
							$value = self::mask_urls( $value );
						}
					}
				);
			}

			return $data;
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask the callback token and the order key in any URL within a string. The rest of the
	 * URL is left readable, since it is what a support case needs to follow a callback.
	 *
	 * @param string $value The string.
	 * @return string
	 */
	public static function mask_urls( $value ) {
		$masked = preg_replace(
			array(
				'/(' . preg_quote( Qliro_One_Callback_Auth::TOKEN_PARAM, '/' ) . '=)[^&#\s"\'\\\\]+/',
				'/\b(key=)wc_order_[A-Za-z0-9_]+/',
			),
			'$1' . KeyMasker::REDACTED,
			$value
		);

		return null === $masked ? KeyMasker::FAILED : $masked;
	}
}

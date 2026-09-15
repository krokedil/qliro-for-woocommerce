<?php
/**
 * Helper class to build the customer information sent to Qliro.
 *
 * @package Qliro_One/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper class to build the customer information sent to Qliro.
 */
class Qliro_One_Helper_Customer {

	/**
	 * Get the customer information that Qliro can use to identify the customer without user interaction.
	 *
	 * @param WC_Customer|WC_Order|null $source The customer or order to read the data from. Both expose the same getters.
	 * @param string                    $enforced_juridical_type The enforced juridical type, if any.
	 *
	 * @return array Empty if the customer cannot be identified by Qliro.
	 */
	public static function get_customer_information( $source, $enforced_juridical_type = '' ) {
		if ( empty( $source ) ) {
			return array();
		}

		$customer_information = array_filter(
			array(
				'Email'           => $source->get_billing_email(),
				'MobileNumber'    => qliro_one_format_mobile_number( $source->get_billing_phone(), $source->get_billing_country() ),
				'JuridicalType'   => self::get_juridical_type( $source, $enforced_juridical_type ),
				'Address'         => self::get_address( $source, 'billing' ),
				'ShippingAddress' => self::get_address( $source, 'shipping' ),
			)
		);

		// Qliro cannot identify the customer from an address alone, so skip the block entirely.
		if ( empty( $customer_information['Email'] ) && empty( $customer_information['MobileNumber'] ) ) {
			$customer_information = array();
		}

		return apply_filters( 'qliro_one_customer_information', $customer_information, $source );
	}

	/**
	 * Get the juridical type of the customer.
	 *
	 * @param WC_Customer|WC_Order $source The customer or order to read the data from.
	 * @param string               $enforced_juridical_type The enforced juridical type, if any.
	 *
	 * @return string Physical or Company.
	 */
	private static function get_juridical_type( $source, $enforced_juridical_type ) {
		if ( ! empty( $enforced_juridical_type ) ) {
			return $enforced_juridical_type;
		}

		if ( ! empty( $source->get_billing_company() ) ) {
			return 'Company';
		}

		$cookie_name   = apply_filters( 'qliro_one_customer_type_cookie_name', 'krokedil_customer_type' );
		$customer_type = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only used to pick between B2B and B2C.

		return 'business' === $customer_type ? 'Company' : 'Physical';
	}

	/**
	 * Get an address for the customer information.
	 *
	 * @param WC_Customer|WC_Order $source The customer or order to read the data from.
	 * @param string               $type Either billing or shipping.
	 *
	 * @return array Empty if no address data is available.
	 */
	private static function get_address( $source, $type ) {
		$fields = array(
			'FirstName'  => 'first_name',
			'LastName'   => 'last_name',
			'Street'     => 'address_1',
			'PostalCode' => 'postcode',
			'City'       => 'city',
		);

		$address = array();
		foreach ( $fields as $key => $field ) {
			$getter = "get_{$type}_{$field}";
			$value  = method_exists( $source, $getter ) ? $source->$getter() : '';

			// Fall back to the billing value if the shipping value is missing.
			if ( empty( $value ) && 'shipping' === $type ) {
				$billing_getter = "get_billing_{$field}";
				$value          = method_exists( $source, $billing_getter ) ? $source->$billing_getter() : '';
			}

			$address[ $key ] = $value;
		}

		return array_filter( $address );
	}
}

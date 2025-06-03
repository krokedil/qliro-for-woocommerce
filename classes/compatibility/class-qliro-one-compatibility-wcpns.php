<?php
/**
 * Qliro One compatibility class for WooCommerce PostNord Shipping (WCPNS).
 */

use KrokedilQliroDeps\Krokedil\Shipping\PickupPoint\PickupPoint;

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Compatibility_WCPNS class.
 */
class Qliro_One_Compatibility_WCPNS {
	/**
	 * The WCPNS checkout object.
	 *
	 * @var WCPNS_Checkout
	 */
	private $wcpns_checkout;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {

		if ( ! class_exists( 'WCPNS_Checkout' ) ) {
			return;
		}

		$this->wcpns_checkout = WCPNS_Checkout::get_instance();

		add_filter( 'woocommerce_package_rates', array( $this, 'maybe_set_postnord_servicepoints' ), 10, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_postnord_servicepoint_data_to_order' ), 10, 3 );
	}

	/**
	 * Maybe add PostNord pickup point data to the shipping rate metadata.
	 *
	 * @param array $package['rates'] Package rates.
	 * @param array $package          Package of cart items.
	 *
	 * @return array
	 */
	public function maybe_set_postnord_servicepoints( $rates ) {

		foreach ( $rates as $rate ) {
			$shipping_method = $this->wcpns_checkout::get_shipping_method_from_rate( $rate );

			// If the shipping method is empty or does not support PostNord service points, skip it.
			if ( empty( $shipping_method ) || ! wc_string_to_bool( $shipping_method->get_instance_option( 'postnord_servicepoints' ) ) ) {
				continue;
			}

			$wcpns_pickup_points = $this->get_pickup_points();
			$pickup_points       = ! empty( $wcpns_pickup_points ) ? $this->format_pickup_points( $wcpns_pickup_points ) : array();

			if ( ! empty( $pickup_points ) ) {
				$selected_pickup_point = $pickup_points[0];
				$rate->add_meta_data( 'krokedil_pickup_points', json_encode( $pickup_points ) );
				$rate->add_meta_data( 'krokedil_selected_pickup_point', json_encode( $selected_pickup_point ) );
				$rate->add_meta_data( 'krokedil_selected_pickup_point_id', $selected_pickup_point->get_id() );
			}
		}
		return $rates;
	}

	/**
	 * Get the PostNord pickup points for the shipping rate.
	 *
	 * @param string $rate_id The shipping rate from WooCommerce.
	 *
	 * @return array
	 */
	private function get_pickup_points() {
		// Find an appropriate address.
		$address      = WC()->checkout->get_value( 'shipping_address_1' ) ?? WC()->checkout->get_value( 'billing_address_1' );
		$zip          = WC()->checkout->get_value( 'shipping_postcode' ) ?? WC()->checkout->get_value( 'billing_postcode' );
		$city         = WC()->checkout->get_value( 'shipping_city' ) ?? WC()->checkout->get_value( 'billing_city' );
		$country_code = WC()->checkout->get_value( 'shipping_country' ) ?? WC()->checkout->get_value( 'billing_country' );

		// Sanitize above variables.
		$address      = sanitize_text_field( $address );
		$zip          = sanitize_text_field( $zip );
		$city         = sanitize_text_field( $city );
		$country_code = sanitize_text_field( $country_code );

		// Get the pickup points from the WCPNS API.
		$wcpns_pickup_points_json = $this->wcpns_checkout->get_postnord_servicepoints_for_address(
			$country_code,
			$zip,
			$city,
			$address,
			false,
			false
		) ?? array();

		if ( empty( $wcpns_pickup_points_json ) ) {
			return array();
		}

		$wcpns_pickup_points = json_decode( $wcpns_pickup_points_json )->servicePointInformationResponse->servicePoints ?? array();
		WC()->session->set( 'wcpns_pickup_points', $wcpns_pickup_points );

		return $wcpns_pickup_points;
	}

	/**
	 * Format the PostNord pickup points to the PickupPoint object.
	 *
	 * @param array $wcpns_pickup_points The PostNord pickup points.
	 *
	 * @return PickupPoint[]
	 */
	private function format_pickup_points( $wcpns_pickup_points ) {
		$pickup_points = array();
		foreach ( $wcpns_pickup_points as $wcpns_pickup_point ) {
			if ( empty( $wcpns_pickup_point->servicePointId ) ) {
				continue;
			}

			$pickup_point = ( new PickupPoint() )
				->set_id( $wcpns_pickup_point->servicePointId )
				->set_name( $wcpns_pickup_point->name )
				->set_address( $wcpns_pickup_point->deliveryAddress->streetName . ' ' . $wcpns_pickup_point->deliveryAddress->streetNumber, $wcpns_pickup_point->deliveryAddress->city, $wcpns_pickup_point->deliveryAddress->postalCode, $wcpns_pickup_point->deliveryAddress->countryCode )
				->set_coordinates( $wcpns_pickup_point->deliveryAddress->coordinate->latitude, $wcpns_pickup_point->deliveryAddress->coordinate->longitude );

			$pickup_points[] = $pickup_point;
		}

		return $pickup_points;
	}


	/**
	 * Save Postnord service point data to the order.
	 *
	 * @param int    $order_id   The order ID.
	 * @param array  $posted_data The posted data.
	 * @param object $order      The order object.
	 *
	 * @return void
	 */
	public function save_postnord_servicepoint_data_to_order( $order_id, $posted_data, $order ) {
		$wcpns_pickup_points = WC()->session->get( 'wcpns_pickup_points' );

		if ( empty( $wcpns_pickup_points ) ) {
			return;
		}

		WC()->session->__unset( 'wcpns_pickup_points' );

		$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );
		if ( empty( $qliro_order_id ) ) {
			return;
		}

		$shipping_data = get_transient( 'qoc_shipping_data_' . $qliro_order_id );

		if ( empty( $shipping_data ) ) {
			return;
		}

		$chosen_pickup_id = $shipping_data['secondaryOption'] ?? array();

		if ( empty( $chosen_pickup_id ) ) {
			return;
		}

		$wcpns_pickup_point = array_filter(
			$wcpns_pickup_points,
			function ( $pickup_point ) use ( $chosen_pickup_id ) {
				return isset( $pickup_point->servicePointId ) && $chosen_pickup_id === $pickup_point->servicePointId;
			}
		);

		if ( empty( $wcpns_pickup_point ) ) {
			return;
		}

		$order->add_meta_data( '_postnord_servicepoint', reset( $wcpns_pickup_point ) );
		$order->save();
	}
}

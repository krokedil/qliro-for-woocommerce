<?php
/**
 * Qliro One compatibility class for WooCommerce PostNord Shipping (WCPNS).
 */

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
	 * Maybe set the pickup points for the shipping rates set by Webshipper.
	 *
	 * @param array  $options         The shipping options.
	 * @param object $method          The shipping method.
	 * @param array  $method_settings The method settings.
	 *
	 * @return array
	 */
	public function maybe_set_postnord_servicepoints( $params, $rate ) {
		// Check if the rate is a PostNord rate.
		if ( ! $this->wcpns_checkout::is_postnord_shipping_rate( $rate ) ) {
			return $params;
		}
		error_log( print_r( $rate->get_id(), true ) );
		$shipping_method = $this->wcpns_checkout::get_shipping_method_from_rate( $rate );
		$service_code    = ! empty( $shipping_method ) ? $shipping_method->get_instance_option( 'postnord_service' ) : 'none';

		if ( ( empty( $service_code ) || 'none' === $service_code ) ) {
			return $params;
		}

		$meta_data = $params['meta_data'] ?? array();

		if ( empty( $meta_data ) ) {
			return $params;
		}

		// Check if the rate has pickup points.
		if ( ! $rate['shipping_rate']['require_drop_point'] ?? false ) {
			return $params;
		}

		$wcpns_pickup_points = $this->get_pickup_points();
		$pickup_points       = ! empty( $wcpns_pickup_points ) ? $this->format_pickup_points( $wcpns_pickup_points ) : array();

		if ( ! empty( $pickup_points ) ) {
			$selected_pickup_point                                    = $pickup_points[0];
			$params['meta_data']['krokedil_pickup_points']            = wp_json_encode( $pickup_points );
			$params['meta_data']['krokedil_selected_pickup_point']    = wp_json_encode( $selected_pickup_point );
			$params['meta_data']['krokedil_selected_pickup_point_id'] = $selected_pickup_point->get_id();
		}

		return $params;
	}

	/**
	 * Get the Webshipper pickup points for the shipping rate.
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

		// Get the pickup points from the Webshipper API.
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

		return $wcpns_pickup_points;
	}

	/**
	 * Format the Webshipper pickup points to the PickupPoint object.
	 *
	 * @param array $ws_pickup_points The Webshipper pickup points.
	 *
	 * @return PickupPoint[]
	 */
	private function format_pickup_points( $wcpns_pickup_points ) {
		error_log( 'WCPNS pickup points: ' . print_r( $wcpns_pickup_points, true ) );
		$pickup_points = array();
		foreach ( $wcpns_pickup_points as $wcpns_pickup_point ) {
			if ( empty( $wcpns_pickup_point->servicePointId ) ) {
				continue;
			}

			$pickup_point = ( new PickupPoint() )
				->set_id( $wcpns_pickup_point->servicePointId )
				->set_name( $wcpns_pickup_point->name )
				->set_address( $wcpns_pickup_point->deliveryAddress->streetName . ' ' . $wcpns_pickup_point->deliveryAddress->streetNumber, $wcpns_pickup_point->deliveryAddress->City, $wcpns_pickup_point->deliveryAddress->postalCode, $wcpns_pickup_point->deliveryAddress->countryCode );

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

		$shipping_pickup_location = $shipping_data['pickupLocation'] ?? array();

		if ( empty( $shipping_pickup_location ) ) {
			return;
		}

		$wcpns_pickup_point = array_filter(
			$wcpns_pickup_points,
			function ( $pickup_point ) use ( $shipping_pickup_location ) {
				return isset( $pickup_point->name ) && $shipping_pickup_location['name'] === $pickup_point->name;
			}
		);

		if ( empty( $wcpns_pickup_point ) ) {
			return;
		}

		$order->add_meta_data( '_postnord_servicepoint', reset( $wcpns_pickup_point ) );
		$order->save();
	}
}

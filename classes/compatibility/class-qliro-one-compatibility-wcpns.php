<?php
/**
 * Qliro One compatibility class for WooCommerce PostNord Shipping (WCPNS).
 *
 * @see https://wordpress.org/plugins/webshipper-automated-shipping/
 * @package  Avarda_Checkout/Classes/Compatibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Compatibility_WCPNS class.
 */
class Qliro_One_Compatibility_WCPNS {
	/**
	 * WCPNS api class instance.
	 *
	 * @var WebshipperAPI
	 */
	private $wcpns_api;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( ! function_exists( 'wcpns' ) ) {
			return;
		}

		exit( 'here' );

		$this->wcpns_api = wcpns()->api;

		add_filter( 'qliro_one_shipping_option', array( $this, 'maybe_set_postnord_servicepoints' ), 10, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_postnord_servicepoint_data_to_order' ), 10, 3 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'wcpns_chosen_servicepoint' ) );
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
	public function maybe_set_postnord_servicepoints( $options, $method, $method_settings ) {
		$instance_id     = $method->get_instance_id();
		$shipping_method = null;
		$shipping_zones  = WC_Shipping_Zones::get_zones();

		foreach ( $shipping_zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( $method->instance_id == $instance_id ) {
					$shipping_method = $method;
					break 2;
				}
			}
		}

		if ( ! $shipping_method ) {
			return $options;
		}

		$service_code = $shipping_method->get_instance_option( 'postnord_service' ) ?? 'none';

		if ( empty( $service_code ) || 'none' === $service_code ) {
			return $options;
		}

		$address      = WC()->checkout->get_value( 'shipping_address_1' ) ?? WC()->checkout->get_value( 'billing_address_1' );
		$zip          = WC()->checkout->get_value( 'shipping_postcode' ) ?? WC()->checkout->get_value( 'billing_postcode' );
		$city         = WC()->checkout->get_value( 'shipping_city' ) ?? WC()->checkout->get_value( 'billing_city' );
		$country_code = WC()->checkout->get_value( 'shipping_country' ) ?? WC()->checkout->get_value( 'billing_country' );

		// Sanitize above variables.
		$address      = $address ? sanitize_text_field( $address ) : '';
		$zip          = $zip ? sanitize_text_field( $zip ) : '';
		$city         = $city ? sanitize_text_field( $city ) : '';
		$country_code = $country_code ? sanitize_text_field( $country_code ) : '';

		if ( empty( $zip ) || empty( $country_code ) ) {
			return $options;
		}

		$wcpns_pickup_points_json = $this->wcpns_api->get_postnord_servicepoints(
			$country_code,
			$zip,
			$city,
			$address,
			false,
			false
		) ?? array();

		$wcpns_pickup_points = json_decode( $wcpns_pickup_points_json )->servicePointInformationResponse->servicePoints ?? array();

		WC()->session->set( 'wcpns_pickup_points', $wcpns_pickup_points_json );

		if ( empty( $wcpns_pickup_points ) ) {
			return $options;
		}

		foreach ( $wcpns_pickup_points as $wcpns_pickup_point ) {
			// If the id is empty, skip.
			if ( empty( $wcpns_pickup_point->servicePointId ) ) {
				continue;
			}

			$secondary_options[] = array(
				'MerchantReference' => $wcpns_pickup_point->servicePointId,
				'DisplayName'       => $wcpns_pickup_point->name,
				'Descriptions'      => array( // Can max have 3 lines.
					trim( mb_substr( $wcpns_pickup_point->deliveryAddress->streetName . ' ' . $wcpns_pickup_point->deliveryAddress->streetNumber, 0, 100 ) ),
					trim( mb_substr( $wcpns_pickup_point->deliveryAddress->postalCode, 0, 100 ) ),
					trim( mb_substr( $wcpns_pickup_point->deliveryAddress->additionalDescription, 0, 100 ) ),
				),
				'Coordinates'       => array(
					'Lat' => $wcpns_pickup_point->deliveryAddress->coordinate->latitude,
					'Lng' => $wcpns_pickup_point->deliveryAddress->coordinate->longitude,
				),
				'DeliveryDateInfo'  => array(
					'DateStart' => 'Not sure what to put here',
				),
			);

			if ( ! empty( $secondary_options ) ) {
				$options['SecondaryOptions'] = $secondary_options;
			}
		}

		return $options;
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
		$postnord_servicepoints = WC()->session->get( 'wcpns_pickup_points' );
		error_log( 'Postnord servicepoint object: ' . print_r( $postnord_servicepoints, true ) );
		if ( empty( $postnord_servicepoints ) ) {
			return;
		}
		$order->add_meta_data( '_postnord_servicepoint', $postnord_servicepoints );
		$order->save();
	}

	/**
	 * Add a hidden field for the chosen pickup point.
	 *
	 * @param array $fields The checkout fields.
	 *
	 * @return array
	 */
	public function wcpns_chosen_servicepoint( $fields ) {
		$fields['billing']['wcpns_chosen_servicepoint'] = array(
			'type'    => 'text',
			'class'   => array( 'wcpns-chosen' ),
			'default' => '',
		);
		return $fields;
	}
}

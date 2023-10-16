<?php
use Krokedil\Shipping\PickupPoints;

/**
 * Class for managing actions during the checkout process.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing actions during the checkout process.
 */
class Qliro_One_Checkout {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_shipping_data_input' ) );
		add_filter( 'woocommerce_shipping_packages', array( $this, 'maybe_set_selected_pickup_point' ) );

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ), 1 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_qliro_order' ), 9999 );
	}

	/**
	 * Add a hidden input field for the shipping data from Qliro One.
	 *
	 * @param array $fields The WooCommerce checkout fields.
	 * @return array
	 */
	public function add_shipping_data_input( $fields ) {
		$default = '';

		if ( is_checkout() ) {
			$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );
			$shipping_data  = get_transient( 'qoc_shipping_data_' . $qliro_order_id );
			$default        = wp_json_encode( $shipping_data );
		}

		$fields['billing']['qoc_shipping_data'] = array(
			'type'    => 'hidden',
			'class'   => array( 'qoc_shipping_data' ),
			'default' => $default,
		);

		return $fields;
	}

	/**
	 * Update the shipping method in WooCommerce based on what Qliro has sent us.
	 *
	 * @return void
	 */
	public function update_shipping_method() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		// Check Setting.
		$settings = get_option( 'woocommerce_qliro_one_settings' );
		if ( 'yes' !== $settings['shipping_in_iframe'] ) {
			return;
		}

		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore
			parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore
			if ( isset( $post_data['qoc_shipping_data'] ) ) {
				WC()->session->set( 'qoc_shipping_data', $post_data['qoc_shipping_data'] );
				WC()->session->set( 'qoc_shipping_data_set', true );
				$data = json_decode( $post_data['qoc_shipping_data'], true );
				qoc_update_wc_shipping( $data );
			}
		}
	}

	/**
	 * Update the Qliro One order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_qliro_order() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );

		if ( empty( $qliro_order_id ) ) {
			return;
		}

		if ( WC()->session->get( 'qoc_shipping_data_set' ) ) {
			WC()->session->__unset( 'qoc_shipping_data_set' );
		}

		// Check if the cart hash has been changed since last update.
		$hash       = $this->calculate_hash();
		$saved_hash = WC()->session->get( 'qliro_one_last_update_hash' );

		// If they are the same, return.
		if ( $hash === $saved_hash ) {
			return;
		}

		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_order_id );

		if ( 'InProcess' === $qliro_order['CustomerCheckoutStatus'] ) {
			$qliro_order = QOC_WC()->api->update_qliro_one_order( $qliro_order_id );
		}

		WC()->session->set( 'qliro_one_last_update_hash', $hash );
	}

	public function calculate_hash() {
		// Get values to use for the combined hash calculation.
		$total            = array_sum( WC()->cart->get_totals() );
		$billing_address  = WC()->customer->get_billing();
		$shipping_address = WC()->customer->get_shipping();
		$shipping_method  = WC()->session->get( 'chosen_shipping_methods' );

		// Calculate a hash from the values.
		$hash = md5( wp_json_encode( array( $total, $billing_address, $shipping_address, $shipping_method ) ) );

		return $hash;
	}

	/**
	 * Maybe set the selected pickup point in the shipping method.
	 *
	 * @param array $packages The shipping packages.
	 * @return array
	 */
	function maybe_set_selected_pickup_point( $packages ) {
		$data            = get_transient( 'qoc_shipping_data_' . WC()->session->get( 'qliro_one_order_id' ) );
		$selected_option = $data['secondaryOption'] ?? '';

		if ( empty( $selected_option ) ) {
			return $packages;
		}

		// Loop each package.
		foreach ( $packages as $package ) {
			// Loop each rate in the package.
			foreach ( $package['rates'] as $rate ) {
				/** @var WC_Shipping_Rate $rate */
				$pickup_point = QOC_WC()->pickup_points_service()->get_pickup_point_from_rate_by_id( $rate, $selected_option );

				if ( ! $pickup_point ) {
					continue;
				}

				QOC_WC()->pickup_points_service()->save_selected_pickup_point_to_rate( $rate, $pickup_point );
			}
		}

		return $packages;
	}
}
new Qliro_One_Checkout();

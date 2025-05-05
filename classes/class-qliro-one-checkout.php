<?php
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
	 * Settings array
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_qliro_one_settings', array() );

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
		if ( ! $this->is_shipping_in_iframe_enabled() ) {
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
		$hash       = self::calculate_hash();
		$saved_hash = WC()->session->get( 'qliro_one_last_update_hash' );

		// If they are the same, return.
		if ( $hash === $saved_hash ) {
			return;
		}

		// If cart doesn't need payment anymore - reload the checkout page.
		if ( apply_filters( 'qliro_check_if_needs_payment', true ) ) {
			if ( ! WC()->cart->needs_payment() ) {
				WC()->session->reload_checkout = true;
			}
		}

		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_order_id );
		if ( is_wp_error( $qliro_order ) ) {
			qliro_one_print_error_message( $qliro_order );
			return;
		}

		if ( qliro_one_is_completed( $qliro_order ) ) {
			Qliro_One_Logger::log( "[CHECKOUT]: The Qliro order (id: $qliro_order_id) is already completed, but the customer is still on checkout page. Redirecting to thankyou page." );
			qliro_one_redirect_to_thankyou_page();
		}

		// Validate the order.
		if ( ! qliro_one_is_valid_order( $qliro_order ) ) {

			// Verify if the order is not already completed in Qliro, set the WC Session to be reload the page.
			if ( ! qliro_one_verify_not_completed( $qliro_order ) ) {
				WC()->session->reload_checkout = true;
				return;
			}

			qliro_one_unset_sessions();
			$qliro_order = qliro_one_maybe_create_order();
		}

		if ( 'InProcess' === $qliro_order['CustomerCheckoutStatus'] ) {
			$qliro_order = QOC_WC()->api->update_qliro_one_order( $qliro_order_id );
		}

		WC()->session->set( 'qliro_one_last_update_hash', $hash );
	}

	public static function calculate_hash() {
		// Get values to use for the combined hash calculation.
		$totals = WC()->cart->get_totals();
		$total  = 0;

		// PHP 8.3.0: Now emits E_WARNING when array values cannot be converted to int or float. Previously arrays and objects where ignored whilst every other value was cast to int.
		foreach ( $totals as $value ) {
			if ( is_numeric( $value ) ) {
				$total += $value;
			}
		}
		$billing_address  = WC()->customer->get_billing();
		$shipping_address = WC()->customer->get_shipping();
		$shipping_method  = WC()->session->get( 'chosen_shipping_methods' );
		// A billing country change warrants a reload since Qliro does not support changing of an on-going session.
		$billing_country = WC()->customer->get_billing_country();

		// Calculate a hash from the values.
		$hash = md5( wp_json_encode( array( $total, $billing_address, $billing_country, $shipping_address, $shipping_method ) ) );

		return $hash;
	}

	/**
	 * Maybe set the selected pickup point in the shipping method.
	 *
	 * @param array $packages The shipping packages.
	 * @return array
	 */
	public function maybe_set_selected_pickup_point( $packages ) {
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

	/**
	 * Is shipping methods in iframe enabled.
	 *
	 * @return bool
	 */
	public function is_shipping_in_iframe_enabled() {
		return isset( $this->settings['shipping_in_iframe'] ) && 'no' !== $this->settings['shipping_in_iframe'];
	}

	/**
	 * Is integrated shipping methods enabled in Qliro One.
	 *
	 * @return bool
	 */
	public function is_integrated_shipping_enabled() {
		return isset( $this->settings['shipping_in_iframe'] ) && 'integrated_shipping' === $this->settings['shipping_in_iframe'];
	}

	/**
	 * Is WooCommerce shipping in iframe enabled.
	 *
	 * @return bool
	 */
	public function is_wc_shipping_in_iframe_enabled() {
		return isset( $this->settings['shipping_in_iframe'] ) && 'wc_shipping' === $this->settings['shipping_in_iframe'];
	}
}

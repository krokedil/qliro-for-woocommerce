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
	 * Whether the current request is WooCommerce's update_shipping_method AJAX request.
	 *
	 * @var bool
	 */
	private $is_shipping_method_request = false;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_qliro_one_settings', array() );

		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_shipping_data_input' ) );
		add_filter( 'woocommerce_shipping_packages', array( $this, 'maybe_set_selected_pickup_point' ) );

		foreach ( array( 'wc_ajax_update_shipping_method', 'wp_ajax_woocommerce_update_shipping_method', 'wp_ajax_nopriv_woocommerce_update_shipping_method' ) as $hook ) {
			add_action( $hook, array( $this, 'flag_shipping_method_request' ), 0 );
		}

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ), 1 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_qliro_order' ), 9999 );

		add_filter( 'woocommerce_states', array( $this, 'maybe_unset_states_from_countries' ), PHP_INT_MAX ); // Make sure we run this last.

		add_filter( 'krokedil_shipping_should_verify_shipping', array( $this, 'maybe_verify_shipping' ) );
	}

	/**
	 * Add a hidden input field for the shipping data from Qliro.
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

		$shipping_data = $this->get_posted_shipping_data();

		if ( '' === $shipping_data ) {
			return;
		}

		WC()->session->set( 'qoc_shipping_data', $shipping_data );
		WC()->session->set( 'qoc_shipping_data_set', true );

		$data = json_decode( $shipping_data, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			Qliro_One_Logger::log( '[CHECKOUT]: Failed to decode the shipping data from Qliro: ' . json_last_error_msg() . ' Data: ' . $shipping_data );
		}

		qliro_update_wc_shipping( $data );
	}

	/**
	 * Get the JSON encoded shipping data that Qliro has sent us from the posted checkout data.
	 *
	 * @return string The raw shipping data, or an empty string if none was posted.
	 */
	private function get_posted_shipping_data() {
		$post_data = array();

		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data ); // phpcs:ignore
		}

		$shipping_data = $post_data['qoc_shipping_data'] ?? wp_unslash( $_POST['qoc_shipping_data'] ?? '' ); // phpcs:ignore

		return is_string( $shipping_data ) ? $shipping_data : '';
	}

	/**
	 * Update the Qliro order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_qliro_order() {
		if ( ! $this->is_checkout_context() ) {
			return;
		}

		if ( 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		// This check must happen before we retrieve the Qliro order ID as the ID will always be empty if the customer changes the billing country from the Qliro checkout page. This is because the billing country is only saved during a create order call which only happens when Qliro is available for that country, and emptied when changing to an unsupported country.
		if ( qliro_one_has_country_changed() ) {
			qliro_one_unset_sessions();

			WC()->session->reload_checkout = true;
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
				WC()->session->set( 'reload_checkout', true );
			}
		}

		$qliro_order = QLIRO_WC()->api->get_qliro_one_order( $qliro_order_id );
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
			$updated_order = QLIRO_WC()->api->update_qliro_one_order( $qliro_order_id );

			// Keep the old hash so the next recalculation retries. Saving it here would leave Qliro on a stale amount until something else in the cart changes.
			// The API layer has already reported the error to the customer, so returning is all that is left to do.
			if ( is_wp_error( $updated_order ) ) {
				return;
			}
		}

		WC()->session->set( 'qliro_one_last_update_hash', $hash );
	}

	/**
	 * Flag the current request as WooCommerce's update_shipping_method AJAX request.
	 *
	 * @return void
	 */
	public function flag_shipping_method_request() {
		$this->is_shipping_method_request = true;
	}

	/**
	 * Whether the Qliro checkout should be kept in sync during the current request.
	 *
	 * WooCommerce only defines WOOCOMMERCE_CHECKOUT for its own update_order_review request, so is_checkout()
	 * is false when a shipping plugin recalculates rates through the core update_shipping_method endpoint.
	 *
	 * @return bool
	 */
	private function is_checkout_context() {
		return is_checkout() || $this->is_shipping_method_request;
	}

	/**
	 * Calculate a hash based on the current cart and checkout data, to be able to compare if anything has changed since last update.
	 *
	 * @return string
	 */
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
		$coupon_code      = WC()->cart->applied_coupons ? implode( ',', WC()->cart->applied_coupons ) : '';
		$cart_hash        = WC()->cart->get_cart_hash();
		$shipping_rates   = self::get_available_shipping_rates();

		// Calculate a hash from the values.
		$hash = md5( wp_json_encode( array( $total, $billing_address, $shipping_address, $shipping_method, $coupon_code, $cart_hash, $shipping_rates ) ) );

		return $hash;
	}

	/**
	 * Get the price and label of every shipping rate currently available to the customer.
	 *
	 * These are sent to Qliro as AvailableShippingMethods, so a repriced rate must change the hash even when the
	 * selected rate, and with it the cart total, stays the same.
	 *
	 * @return array
	 */
	private static function get_available_shipping_rates() {
		if ( ! wc_shipping_enabled() || empty( WC()->shipping() ) ) {
			return array();
		}

		$rates = array();

		// Already calculated at this point, so this reads the cached packages rather than triggering a recalculation.
		foreach ( WC()->shipping()->get_packages() as $package_key => $package ) {
			foreach ( $package['rates'] ?? array() as $rate_id => $rate ) {
				$rates[ "$package_key:$rate_id" ] = array( $rate->get_cost(), $rate->get_shipping_tax(), $rate->get_label() );
			}
		}

		return $rates;
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
				$pickup_point = QLIRO_WC()->pickup_points_service()->get_pickup_point_from_rate_by_id( $rate, $selected_option );
				if ( ! $pickup_point ) {
					continue;
				}

				QLIRO_WC()->pickup_points_service()->save_selected_pickup_point_to_rate( $rate, $pickup_point );
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
	 * Is integrated shipping methods enabled in Qliro.
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

	/**
	 * Maybe unset states from countries list for Qliro checkout.
	 *
	 * Needed since Qliro checkout has a field for states that is a text input, and WooCommerce renders a select field if a country has states listed.
	 * This makes it hard or almost impossible to select the correct state in WooCommerce based on the user input in Qliro checkout.
	 *
	 * @param array $country_states The states.
	 * @return array
	 */
	public function maybe_unset_states_from_countries( $country_states ) {
		// Only do this if Qliro is the selected payment method.
		if ( empty( WC()->session ) || 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return $country_states;
		}

		// Ensure each country (key) has a empty array as a value.
		foreach ( $country_states as $cc => $states ) {
			/*
			* If the country has states that are defined, set them to null, this will force it to be shown as a text input.
			* An empty array would only render it as a hidden field,
			* causing WooCommerce to ignore the field when submitting the form during updates etc.
			*
			* @see woocommerce/includes/wc-template-functions.php::woocommerce_form_field case 'state'
			*/
			if ( is_array( $states ) && ! empty( $states ) ) {
				$country_states[ $cc ] = null;
			}
		}

		return $country_states;
	}

	/**
	 * Check if we should verify the shipping rate during checkout, and report errors to the customer instead of WooCommerce silently changing the shipping method.
	 *
	 * @param bool $should_verify_shipping If we should verify the shipping rate.
	 *
	 * @return bool
	 */
	public function maybe_verify_shipping( $should_verify_shipping ) {
		// If its already true, no need to do anything.
		if ( $should_verify_shipping ) {
			return $should_verify_shipping;
		}

		$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
		// If Qliro is the chosen payment method, and we are showing shipping methods inside the Qliro iframe.
		if ( $this->is_shipping_in_iframe_enabled() && 'qliro_one' === $chosen_payment_method ) {
			return true;
		}

		return $should_verify_shipping;
	}
}

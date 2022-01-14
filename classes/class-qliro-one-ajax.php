<?php
/**
 * Ajax class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class Qliro_One_Ajax extends WC_AJAX {
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'qliro_one_wc_change_payment_method' => true,
			'qliro_one_get_order'                => true,
			'qliro_one_wc_log_js'                => true,
			'qliro_one_wc_update_order'          => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Refresh checkout fragment.
	 */
	public static function qliro_one_wc_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'qliro_one_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways  = WC()->payment_gateways()->get_available_payment_gateways();
		$switch_to_qliro_one = isset( $_POST['qliro_one'] ) ? sanitize_text_field( wp_unslash( $_POST['qliro_one'] ) ) : '';
		if ( 'false' === $switch_to_qliro_one ) {
			// Set chosen payment method to first gateway that is not Qliro One Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'qliro_one' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'qliro_one' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);

		wp_send_json_success( $data );
		wp_die();
	}


	/**
	 * Gets the Qliro One order.
	 */
	public static function qliro_one_get_order() {

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'qliro_one_get_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$order_id        = WC()->session->get( 'qliro_one_order_id' );
		$qliro_one_order = QOC_WC()->api->get_qliro_one_order( $order_id );
		$billing_data    = $qliro_one_order['BillingAddress'];
		$shipping_data   = $qliro_one_order['ShippingAddress'];
		$customer        = $qliro_one_order['Customer'];

		wp_send_json_success(
			array(
				'billingAddress'  => $billing_data,
				'shippingAddress' => $shipping_data,
				'customer'        => $customer,
			)
		);
		wp_die();
	}

	/**
	 * Updates qliro order.
	 */
	public static function qliro_one_wc_update_order() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'qliro_one_wc_update_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$order_id        = WC()->session->get( 'qliro_one_order_id' );
		$update_response = QOC_WC()->api->update_qliro_one_order( $order_id );
		if ( ! is_wp_error( $update_response ) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Bad request' );
		}
	}
}
Qliro_One_Ajax::init();

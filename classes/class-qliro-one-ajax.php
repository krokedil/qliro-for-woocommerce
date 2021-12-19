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
}
Qliro_One_Ajax::init();

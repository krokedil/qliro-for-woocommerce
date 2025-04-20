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
			'qliro_one_wc_update_order'          => true,
			'qliro_one_wc_log_js'                => true,
			'qliro_one_wc_set_order_sync'        => false,
			'qliro_one_make_capture'             => true,
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
	}


	/**
	 * Gets the Qliro One order.
	 */
	public static function qliro_one_get_order() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'qliro_one_get_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		$order_id        = WC()->session->get( 'qliro_one_order_id' );
		$qliro_one_order = QOC_WC()->api->get_qliro_one_order( $order_id );
		if ( is_wp_error( $qliro_one_order ) ) {
			wp_send_json_error( $qliro_one_order->get_error_message() );
		}

		wp_send_json_success(
			array(
				'billingAddress'  => $qliro_one_order['BillingAddress'],
				'shippingAddress' => $qliro_one_order['ShippingAddress'],
				'customer'        => $qliro_one_order['Customer'],
			)
		);
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function qliro_one_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'qliro_one_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}
		$posted_message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );
		$message        = "Frontend JS $qliro_order_id: $posted_message";
		Qliro_One_Logger::log( $message );
		wp_send_json_success();
	}

	/**
	 * Makes the partial capture.
	 *
	 * @return void
	 */
	public static function qliro_one_make_capture() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! wp_verify_nonce( $nonce, 'qliro_one_make_capture' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		try {
			$items    = filter_input( INPUT_POST, 'items', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$order_id = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_SPECIAL_CHARS );
			$order    = wc_get_order( $order_id );

			$response = QOC_WC()->api->capture_qliro_one_order( $order_id, $items );
			if ( is_wp_error( $response ) ) {
				$prefix        = 'Evaluation, ';
				$error_message = trim( str_replace( $prefix, '', $response->get_error_message() ) );

				// translators: %s is the error message from Qliro.
				$order->update_status( 'on-hold', sprintf( __( 'The order failed to be partially captured with Qliro: %s.', 'qliro-one-for-woocommerce' ), $error_message ) );
				wp_send_json_error( $error_message );
			}

			$payment_transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'] ?? '';

			foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
				if ( isset( $items[ $order_item->get_id() ] ) ) {
					// Save captured data to the order line.
					$captured_history = ! empty( $order_item->get_meta( '_qliro_captured_data' ) ) ? $order_item->get_meta( '_qliro_captured_data' ) . ',' : '';
					$order_item->update_meta_data( '_qliro_captured_data', $captured_history . $payment_transaction_id . ':' . intval( $items[ $order_item->get_id() ] ) );

					$order_item->save();
				}
			}

			// Add order note.
			// translators: %s is transaction ID.
			$order_note = sprintf( __( 'The order has been requested to be partially captured with Qliro and is in process. Payment transaction id: %s ', 'qliro-one-for-woocommerce' ), $payment_transaction_id );
			$order->add_order_note( $order_note );

			wp_send_json_success( $order_id );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Set order sync status.
	 *
	 * @return void
	 */
	public static function qliro_one_wc_set_order_sync() {
		$nonce    = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
		$enabled  = filter_input( INPUT_POST, 'enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! wp_verify_nonce( $nonce, 'qliro_one_wc_set_order_sync' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		if ( ! $order_id ) {
			wp_send_json_error( 'no_order_id' );
			exit;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'no_order' );
			exit;
		}

		$order->update_meta_data( '_qliro_order_sync_enabled', $enabled );
		$order->save();

		wp_send_json_success();
	}
}
Qliro_One_Ajax::init();

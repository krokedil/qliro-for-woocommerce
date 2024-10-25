<?php
/**
 * Order management class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Order management class.
 */
class Qliro_One_Order_Management {

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );

		$this->settings = get_option( 'woocommerce_qliro_one_settings' );
	}

	/**
	 * Maybe triggers capture or cancel order requests on order status changes.
	 *
	 * @param int      $order_id The WooCommerce Order ID.
	 * @param string   $status_from The status the order was originally.
	 * @param string   $status_to The status the order is changing to.
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function order_status_changed( $order_id, $status_from, $status_to, $order ) {
		// If this order wasn't created using Qliro One payment method, bail.
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return;
		}

		// Check if the order has been paid.
		if ( null === $order->get_date_paid() ) {
			return;
		}

		// Skip the order is order sync is not enabled for it.
		if ( ! self::is_order_sync_enabled( $order ) ) {
			return;
		}

		$cancel_status  = str_replace( 'wc-', '', $this->settings['cancel_status'] );
		$capture_status = str_replace( 'wc-', '', $this->settings['capture_status'] );

		if ( $cancel_status === $status_to ) {
			$this->cancel_qliro_one_order( $order_id, $order );
		}

		if ( $capture_status === $status_to ) {
			$this->capture_qliro_one_order( $order_id, $order );
		}
	}

	/**
	 * Captures a Qliro One order.
	 *
	 * @param int      $order_id The WooCommerce order ID.
	 * @param WC_Order $order The WooCommerce order.
	 */
	public function capture_qliro_one_order( $order_id, $order ) {
		if ( qoc_is_fully_captured( $order ) ) {
			return;
		}

		$items = qoc_is_partially_captured( $order ) ? qoc_get_remaining_items_to_capture( $order ) : '';

		$response = QOC_WC()->api->capture_qliro_one_order( $order_id, $items );
		if ( is_wp_error( $response ) ) {
			$prefix        = 'Evaluation, ';
			$error_message = trim( str_replace( $prefix, '', $response->get_error_message() ) );

			// translators: %s is the error message from Qliro.
			$order->update_status( 'on-hold', sprintf( __( 'The order failed to be captured with Qliro: %s.', 'qliro-one-for-woocommerce' ), $error_message ) );
			return;
		}

		$payment_transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'];

		// Save the captured data to the order.
		if ( empty( $items ) ) {
			// Full order capture - save captured data to the order.
			$order->update_meta_data( '_qliro_order_captured', $payment_transaction_id );
		} else {
			// Partial capture.
			foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
				if ( isset( $items[ $order_item->get_id() ] ) ) {
					// Save captured data to the order line.
					$captured_history = ! empty( $order_item->get_meta( '_qliro_captured_data' ) ) ? $order_item->get_meta( '_qliro_captured_data' ) . ',' : '';
					$order_item->update_meta_data( '_qliro_captured_data', $captured_history . $payment_transaction_id . ':' . intval( $items[ $order_item->get_id() ] ) );
					$order_item->save();
				}
			}
		}

		// translators: %s is transaction ID.
		$order_note = sprintf( __( 'The order has been requested to be captured with Qliro and is in process. Payment transaction id: %s ', 'qliro-one-for-woocommerce' ), $payment_transaction_id );
		if ( 'none' !== $this->settings['capture_pending_status'] ) {
			$order->update_status( $this->settings['capture_pending_status'], $order_note );
		} else {
			$order->add_order_note( $order_note );
		}
		$order->save();
	}

	/**
	 * Cancels a Qliro One order.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order The WooCommerce order.
	 */
	public function cancel_qliro_one_order( $order_id, $order ) {
		if ( $order->get_meta( '_qliro_order_canceled' ) ) {
			return;
		}

		$response = QOC_WC()->api->cancel_qliro_one_order( $order_id );
		if ( is_wp_error( $response ) ) {
			$prefix        = 'Evaluation, ';
			$error_message = trim( str_replace( $prefix, '', $response->get_error_message() ) );

			// translators: %s is the error message from Qliro.
			$order->update_status( 'on-hold', sprintf( __( 'The order failed to be cancelled with Qliro: %s.', 'qliro-one-for-woocommerce' ), $error_message ) );
			$order->save();
			return;
		}

		$payment_transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'];
		$order->update_meta_data( '_qliro_order_canceled', true );
		$order_note = __( 'The order has been requested to be cancelled with Qliro and is in process. Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $payment_transaction_id;
		if ( 'none' !== $this->settings['cancel_pending_status'] ) {
			$order->update_status( $this->settings['cancel_pending_status'], $order_note );
		} else {
			$order->add_order_note( $order_note );
		}
		$order->save();
	}

	/**
	 * Request for refunding a Qliro One Order.
	 *
	 * @param int   $order_id The WooCommerce order ID.
	 * @param float $amount The refund amount.
	 * @return bool|WP_Error
	 */
	public function refund( $order_id, $amount ) {
		$order = wc_get_order( $order_id );

		// Skip the order is order sync is not enabled for it, and return an error.
		if ( ! self::is_order_sync_enabled( $order ) ) {
			return new WP_Error( 'qliro_one_order_sync_disabled', __( 'The order synchronization with Qliro is disabled for this order, either enable it and try again or use the manual refund option.', 'qliro-one-for-woocommerce' ) );
		}

		$refund_order_id = $order->get_refunds()[0]->get_id();
		$refund_order    = wc_get_order( $refund_order_id );

		// If the order has been fully captured, we can refund the order based on the capture id stored in the order meta.
		if ( $order->get_meta( '_qliro_order_captured' ) ) {
			return $this->create_refund( $order, $amount, $refund_order_id );
		}

		// If the order has been partially captured, we need to get the capture id from the order item meta.
		foreach ( $refund_order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
			$refunded_items[ $order_item->get_meta( '_refunded_item_id' ) ] = $order_item->get_quantity();
		}

		// Prepare the items to refund.
		$prepped_items = $this->get_formatted_items_to_refund( $refunded_items, $order );

		// If the prepped items array is empty, return false.
		if ( empty( $prepped_items ) ) {
			// translators: %s is the error message from Qliro.
			$order->add_order_note( sprintf( __( 'Failed to refund the order with Qliro One: %s', 'qliro-one-for-woocommerce' ), __( 'No captured data found for the order items.', 'qliro-one-for-woocommerce' ) ) );
			return false;
		}

		// Structure the prepped items array so that each capture id has its own array of items.
		$prepped_items = array_reduce(
			$prepped_items,
			function ( $carry, $item ) {
				$carry[ $item['capture_id'] ][] = array(
					'item_id'  => $item['item_id'],
					'quantity' => $item['quantity'],
				);
				return $carry;
			},
			array()
		);

		// Do not allow refunds with more than one capture id.
		if ( count( $prepped_items ) > 1 ) {
			// translators: %s is the error message from Qliro.
			$order->add_order_note( sprintf( __( 'Failed to refund the order with Qliro One: %s', 'qliro-one-for-woocommerce' ), __( 'Multiple capture IDs found for the order items.', 'qliro-one-for-woocommerce' ) ) );
			return new WP_Error( 'qliro_one_refund_issue', __( 'Failed to refund the order with Qliro One. Multiple capture IDs can not be used in one refund request.', 'qliro-one-for-woocommerce' ) );
		}

		// Create one or more refunds based on the prepped items array.
		foreach ( $prepped_items as $capture_id => $items ) {
			$response = $this->create_refund( $order, $amount, $refund_order_id, $capture_id, $items );
			// If the response is an error, continue to the next capture id.
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$this->save_refunded_data_to_order_lines( $order, $capture_id, $items );
		}

		return $response;
	}

	/**
	 * Get the items to refund.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $refunded_items The refunded items.
	 * @return array
	 */
	public function get_formatted_items_to_refund( $refunded_items, $order ) {
		$prepped_items = array();
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {

			if ( isset( $refunded_items[ $order_item->get_id() ] ) ) {
				$captured_data                = explode( ',', $order_item->get_meta( '_qliro_captured_data' ) );
				$qliro_previous_refunded_data = explode( ',', $order_item->get_meta( '_qliro_refunded_data' ) );

				// Loop through the captured data to assign correct refund quantity to each capture_id.
				foreach ( $captured_data as $key => $capture ) {
					$capture = explode( ':', $capture );

					// Check if the capture id is in the Qliro previous refunded data. If so, skip the capture id.
					foreach ( $qliro_previous_refunded_data as $qliro_previous_refunded ) {
						$qliro_previous_refunded = explode( ':', $qliro_previous_refunded );
						if ( $capture[0] === $qliro_previous_refunded[0] ) {
							continue 2;
						}
					}

					// If the captured quantity is greater than or equal to the refunded quantity, add it to the prepped items array.
					if ( $capture[1] >= abs( $refunded_items[ $order_item->get_id() ] ) ) {
						$prepped_items[] = array(
							'item_id'    => $order_item->get_id(),
							'quantity'   => abs( $refunded_items[ $order_item->get_id() ] ),
							'capture_id' => $capture[0],
						);
						break;
					} else {
						// If the captured quantity is less than the refunded quantity, add it to the prepped items array and subtract the captured quantity from the refunded quantity.
						$prepped_items[]                          = array(
							'item_id'    => $order_item->get_id(),
							'quantity'   => $capture[1],
							'capture_id' => $capture[0],
						);
						$refunded_items[ $order_item->get_id() ] -= $capture[1];
					}
				}
			}
		}

		return $prepped_items;
	}

	/**
	 * Save refunded data to the order lines.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $capture_id The capture ID.
	 * @param array    $items The refunded items.
	 */
	public function save_refunded_data_to_order_lines( $order, $capture_id, $items ) {

		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {

			// If the order item is in the refunded items (multidimensional) array, save the refunded data to the order line.
			foreach ( $items as $item ) {
				if ( isset( $item['item_id'] ) ) {
					if ( $order_item->get_id() == $item['item_id'] ) {
						// Save refunded data to the order line.
						$refunded_history = ! empty( $order_item->get_meta( '_qliro_refunded_data' ) ) ? $order_item->get_meta( '_qliro_refunded_data' ) . ',' : '';
						$order_item->update_meta_data( '_qliro_refunded_data', $refunded_history . $capture_id . ':' . intval( $item['quantity'] ) );
						$order->save();
					}
				}
			}
		}
	}

	/**
	 * Create the refund.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param float    $amount The refund amount.
	 * @param int      $refund_order_id The refund order ID.
	 * @param string   $capture_id The capture ID.
	 * @param array    $items The items to refund.
	 * @return bool|WP_Error
	 */
	public function create_refund( $order, $amount, $refund_order_id, $capture_id = '', $items = '' ) {
		$response = QOC_WC()->api->refund_qliro_one_order( $order->get_id(), $refund_order_id, $capture_id, $items );

		if ( is_wp_error( $response ) ) {
			preg_match_all( '/Message: (.*?)(?=Property:|$)/s', $response->get_error_message(), $matches );

			// translators: %s is the error message from Qliro (if any).
			$note = sprintf( __( 'Failed to refund the order with Qliro One%s', 'qliro-one-for-woocommerce' ), isset( $matches[1] ) ? ': ' . trim( implode( ' ', $matches[1] ) ) : '' );
			$order->add_order_note( $note );
			$response->errors[ $response->get_error_code() ] = array( $note );
			return $response;
		}
		// translators: refund amount, refund id.
		$text           = __( 'Processing a refund of %1$s with Qliro One', 'qliro-one-for-woocommerce' );
		$formatted_text = sprintf( $text, wc_price( $amount ) );
		$order->add_order_note( $formatted_text );
		return true;
	}

	/**
	 * Check if the order has order sync enabled on it or not.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	public static function is_order_sync_enabled( $order ) {
		$sync_enabled = $order->get_meta( '_qliro_order_sync_enabled' );

		// Return true if the value is not set to no, since empty metadata is considered as enabled and will be returned as an empty string.
		return 'no' !== $sync_enabled;
	}
}

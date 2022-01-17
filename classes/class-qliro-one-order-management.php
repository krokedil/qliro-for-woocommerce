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
		// Capture an order.
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_qliro_one_order' ) );
		// Cancel order.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_qliro_one_order' ) );

		$this->settings = get_option( 'woocommerce_qliro_one_settings' );
	}


	/**
	 * Captures a Qliro One order.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function capture_qliro_one_order( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using Qliro One payment method, bail.
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return;
		}

		// Check qliro settings to see if we have the order management enabled.
		$order_management = 'yes' === $this->settings['qliro_one_order_management'];
		if ( ! $order_management ) {
			return;
		}

		// todo check if this reservation was already activated, do nothing.
		if ( get_post_meta( $order_id, '_qliro_order_captured', true ) ) {
			$order->add_order_note( __( 'The order has already been captured with Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		$response = QOC_WC()->api->capture_qliro_one_order( $order_id );
		if ( ! is_wp_error( $response ) ) {
			// all ok.
			$payment_transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'];
			update_post_meta( $order_id, '_qliro_order_captured', true );
			$order->add_order_note( __( 'The order has been successfully captured with Qliro. Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $payment_transaction_id );
		} else {
			$order->update_status( 'on-hold', __( 'The order failed to be captured with Qliro. Please try again.', 'qliro-one-for-woocommerce' ) );
		}
		$order->save();
	}


	/**
	 * Cancels a Qliro One order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function cancel_qliro_one_order( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using Qliro One payment method, bail.
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return;
		}

		// Check settings to see if we have the order management enabled.
		$order_management = 'yes' === $this->settings['qliro_one_order_management'];
		if ( ! $order_management ) {
			return;
		}

		// Check if the order has been paid.
		if ( null === $order->get_date_paid() ) {
			return;
		}

		// Not going to do this for non Qliro One orders.
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return;
		}

		// todo get request id from post meta.
		$args     = array(
			'request_id' => get_post_meta( $order_id, '_qliro_request_id' ),
			'order_id'   => $order,
		);
		$response = new Qliro_One_Cancel_Order( $args );
		if ( ! is_wp_error( $response ) ) {

		}
	}

	/**
	 *
	 */
	public function refund( $order_id, $amount ) {
		$query_args = array(
			'fields'         => 'id=>parent',
			'post_type'      => 'shop_order_refund',
			'post_status'    => 'any',
			'posts_per_page' => - 1,
		);

		$refunds         = get_posts( $query_args );
		$refund_order_id = array_search( $order_id, $refunds, true );
		if ( is_array( $refund_order_id ) ) {
			foreach ( $refund_order_id as $key => $value ) {
				$refund_order_id = $value;
				break;
			}
		}
		$order = wc_get_order( $order_id );

		// todo change args.
		$args     = array(
			'order_id'       => $refund_order_id,
			'qliro_order_id' => get_post_meta( $order_id, '_qliro_order_id', true ),
		);
		$response = QOC_WC()->api->refund_qliro_one_order( $args );

		if ( is_wp_error( $response ) ) {
			// TODO add error handler.
			$order->add_order_note( __( 'Failed to refund the order with Qliro One', 'qliro-one-for-woocommerce' ) );
			return false;
		}
		// translators: refund amount, refund id.
		$text           = __( '%1$s successfully refunded in Qliro One.. RefundID: %2$s', 'qliro-one-for-woocommerce' );
		$formatted_text = sprintf( $text, wc_price( $amount ), $response['refundid'] );
		$order->add_order_note( $formatted_text );
		return true;

	}

}

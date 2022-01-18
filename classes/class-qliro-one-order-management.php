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
		if ( get_post_meta( $order_id, '_qliro_order_captured', true ) ) {
			return;
		}

		$response = QOC_WC()->api->capture_qliro_one_order( $order_id );
		if ( is_wp_error( $response ) ) {
			$order->update_status( 'on-hold', __( 'The order failed to be captured with Qliro. Please try again.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return;
		}

		$payment_transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'];
		update_post_meta( $order_id, '_qliro_order_captured', true );
		$order_note = __( 'The order has been requested to be captured with Qliro and is pending. Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $payment_transaction_id;
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
		if ( get_post_meta( $order_id, '_qliro_order_canceled', true ) ) {
			return;
		}

		$response = QOC_WC()->api->cancel_qliro_one_order( $order_id );
		if ( is_wp_error( $response ) ) {
			$order->update_status( 'on-hold', __( 'The order failed to be cancelled with Qliro. Please try again.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return;
		}

		$payment_transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'];
		update_post_meta( $order_id, '_qliro_order_canceled', true );
		$order_note = __( 'The order has been requested to be cancelled with Qliro and is pending. Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $payment_transaction_id;
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
	 * @return bool
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
} new Qliro_One_Order_Management();

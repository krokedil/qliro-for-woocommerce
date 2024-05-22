<?php
/**
 * Handles metaboxes for Qliro One.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

use Krokedil\WooCommerce\OrderMetabox;

/**
 * Qliro_One_Metabox class.
 */
class Qliro_One_Metabox extends OrderMetabox {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'qliro-one', __( 'Qliro', 'qliro-one' ), 'qliro_one' );
	}

	/**
	 * Render the metabox.
	 *
	 * @param WP_Post $post The WordPress post.
	 *
	 * @return void
	 */
	public function render_metabox( $post ) {
		// Get the WC Order from the post.
		$order = null;
		if ( is_a( $post, WC_Order::class ) ) {
			$order = $post;
		} else {
			$order = wc_get_order( $post->ID );
		}

		if ( ! $order ) {
			return;
		}

		$qliro_order_id  = $order->get_meta( '_qliro_one_order_id' );
		$qliro_reference = $order->get_meta( '_qliro_one_merchant_reference' );

		$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );

		if ( is_wp_error( $qliro_order ) ) {
			self::output_error( $qliro_order->get_error_message() );
			return;
		}

		$last_transaction = self::get_last_transaction( $qliro_order['PaymentTransactions'] ?? array() );

		self::output_info( __( 'Payment method', 'qliro-one' ), self::get_payment_method_name( $order ) );
		self::output_info( __( 'Qliro order id', 'qliro-one' ), $qliro_order_id );
		self::output_info( __( 'Qliro reference', 'qliro-one' ), $qliro_reference );
		self::output_info( __( 'Qliro order status', 'qliro-one' ), self::get_order_status( $last_transaction ) );
		self::output_info( __( 'Amount', 'qliro-one' ), self::get_amount( $last_transaction ) );
	}

	/**
	 * Get the last transaction from a Qliro One order.
	 *
	 * @param array $transactions
	 *
	 * @return array
	 */
	private static function get_last_transaction( $transactions ) {
		// Sort the transactions based on the timestamp.
		usort(
			$transactions,
			function ( $a, $b ) {
				return strtotime( $a['Timestamp'] ?? '' ) - strtotime( $b['Timestamp'] ?? '' );
			}
		);

		// Get the last transaction.
		$last_transaction = end( $transactions );

		return $last_transaction;
	}

	/**
	 * Get the amount from the payment transaction.
	 *
	 * @param array $transaction
	 *
	 * @return string
	 */
	private static function get_amount( $transaction ) {
		$amount   = $transaction['Amount'] ?? '0';
		$currency = $transaction['Currency'] ?? '';
		$amount   = wc_price( $amount, array( 'currency' => $currency ) );

		return $amount;
	}

	/**
	 * Get the status of a Qliro One order from the payment transaction.
	 *
	 * @param array $transaction
	 *
	 * @return string
	 */
	private static function get_order_status( $transaction ) {
		// Get the status and type from the transaction.
		$status = $transaction['Status'];
		$type   = $transaction['Type'];

		return $type . wc_help_tip( $status );
	}

	/**
	 * Get the Qliro payment method name.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	private static function get_payment_method_name( $order ) {
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );
		$subtype        = $order->get_meta( 'qliro_one_payment_method_subtype_code' );

		// If the payment method starts with QLIRO_, it is a Qliro One payment method.
		if ( strpos( $payment_method, 'QLIRO_' ) === 0 ) {
			$payment_method = str_replace( 'QLIRO_', '', $payment_method );
			$subtype        = __( 'Qliro payment method', 'qliro-one' );
		}

		// Replace any _ with a space.
		$payment_method = str_replace( '_', ' ', $payment_method );

		// Return the method but ensure only the first letter is uppercase.
		return ucfirst( strtolower( $payment_method ) ) . wc_help_tip( $subtype );
	}
}

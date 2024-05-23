<?php
/**
 * Handles metaboxes for Qliro One.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

use KrokedilQliroDeps\Krokedil\WooCommerce\OrderMetabox;

/**
 * Qliro_One_Metabox class.
 */
class Qliro_One_Metabox extends OrderMetabox {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'qliro-one', __( 'Qliro', 'qliro-one' ), 'qliro_one' );

		add_action( 'init', array( $this, 'handle_sync_order_action' ), 9999 );
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

		$qliro_order_id       = $order->get_meta( '_qliro_one_order_id' );
		$qliro_reference      = $order->get_meta( '_qliro_one_merchant_reference' );
		$qliro_payment_method = $order->get_meta( 'qliro_one_payment_method_name' );
		$is_captured          = $order->get_meta( '_qliro_order_captured' );

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
		self::output_sync_order_button( $order, $qliro_order_id );
	}

	/**
	 * Handle the sync order action request.
	 *
	 * @return void
	 */
	public function handle_sync_order_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce          = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action         = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id       = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$qliro_order_id = filter_input( INPUT_GET, 'qliro_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $action ) || empty( $order_id ) ) {
			return;
		}

		if ( 'qliro_one_sync_order' !== $action ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'qliro_one_sync_order' ) ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
			exit;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || $this->payment_method_id !== $order->get_payment_method() ) {
			return;
		}

		$response = QOC_WC()->api->om_update_qliro_one_order( $qliro_order_id, $order_id );

		if ( is_wp_error( $response ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to sync order with Qliro. Error: %s', 'qliro-one' ),
					$response->get_error_message()
				)
			);
			return;
		}

		// Get the new payment transaction id from the response, and update the order meta with it.
		$transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'] ?? '';
		$order->update_meta_data( '_qliro_payment_transaction_id', $transaction_id );
		$order->save();

		$order->add_order_note(
			// translators: %s: new transaction id from Qliro.
			sprintf( __( 'Order synced with Qliro. Transaction ID: %s', 'qliro-one' ), $transaction_id )
		);

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
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

	/**
	 * Output the sync order action button.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $qliro_order_id The Qliro order id.
	 *
	 * @return void
	 */
	private static function output_sync_order_button( $order, $qliro_order_id ) {
		$is_captured    = $order->get_meta( '_qliro_order_captured' );
		$is_cancelled   = $order->get_meta( '_qliro_order_cancelled' );
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );

		// Only output the sync button if the order is a Qliro payment method order. Cant update card orders for example.
		if ( strpos( $payment_method, 'QLIRO_' ) !== 0 ) {
			return;
		}

		// If the order is captured or cancelled, do not output the sync button.
		if ( $is_captured || $is_cancelled ) {
			return;
		}

		$query_args = array(
			'action'         => 'qliro_one_sync_order',
			'order_id'       => $order->get_id(),
			'qliro_order_id' => $qliro_order_id,
		);

		$action_url = wp_nonce_url(
			add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) ),
			'qliro_one_sync_order'
		);

		self::output_action_button(
			__( 'Sync order with Qliro', 'qliro-one' ),
			$action_url,
			false,
			'button-primary'
		);
	}
}

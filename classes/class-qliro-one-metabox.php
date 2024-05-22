<?php
/**
 * Handles metaboxes for Qliro One.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Qliro_One_Metabox class.
 */
class Qliro_One_Metabox {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
	}

	/**
	 * Is HPOS enabled.
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled() {
		if ( class_exists( CustomOrdersTableController::class ) ) {
			return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
		}

		return false;
	}

	/**
	 * Check if the current screen is the edit order screen.
	 *
	 * @param string $post_type The post type to check.
	 *
	 * @return bool
	 */
	public static function is_edit_order_screen( $post_type ) {
		$valid_screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		return in_array( $post_type, $valid_screens, true );
	}

	/**
	 * Get the order ID from the current screen.
	 *
	 * @return int|null
	 */
	public static function get_order_id() {
		$hpos_enabled = self::is_hpos_enabled();
		$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
		if ( empty( $order_id ) ) {
			return false;
		}

		return $order_id;
	}

	/**
	 * Add metabox to order edit screen.
	 *
	 * @param string $post_type
	 *
	 * @return void
	 */
	public function add_metabox( $post_type ) {
		if ( ! self::is_edit_order_screen( $post_type ) ) {
			return;
		}

		// Ensure we are on a order page.
		$order_id = self::get_order_id();
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order_id || ! $order ) {
			return;
		}

		// Ensure the order has a Qliro One payment method.
		$payment_method = $order->get_payment_method();
		if ( 'qliro_one' !== $payment_method ) {
			return;
		}

		add_meta_box(
			'qliro-one',
			__( 'Qliro', 'qliro-one' ),
			array( $this, 'render_metabox' ),
			$post_type,
			'side',
			'core'
		);
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
	public static function get_payment_method_name( $order ) {
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
	 * Print a error message into the metabox.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function output_error( $message ) {
		?>
		<p class="error">
			<?php echo esc_html( $message ); ?>
		</p>
		<?php
	}

	/**
	 * Output labeled text info for the metabox.
	 *
	 * @param string $label
	 * @param string $text
	 *
	 * @return void
	 */
	private static function output_info( $label, $text ) {
		?>
		<p>
			<strong><?php echo esc_html( $label ); ?>:</strong>
			<span><?php echo wp_kses_post( $text ); ?></span>
		</p>
		<?php
	}
}

<?php
/**
 * Handles metaboxes for Qliro.
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
		parent::__construct( 'qliro-one', 'Qliro order data', 'qliro_one' );

		add_action( 'init', array( $this, 'set_metabox_title' ) );
		add_action( 'init', array( $this, 'handle_sync_order_action' ), 9999 );
		add_action( 'init', array( $this, 'handle_add_order_discount_action' ), 9999 );

		$this->scripts[] = 'qliro-one-metabox';
	}

	/**
	 * Set the metabox title.
	 *
	 * @return void
	 */
	public function set_metabox_title() {
		$this->title = __( 'Qliro order data', 'qliro-one-for-woocommerce' );
	}

	/**
	 * Render the metabox.
	 *
	 * @param WP_Post $post The WordPress post.
	 *
	 * @return void
	 */
	public function metabox_content( $post ) {
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
		$order_sync      = $order->get_meta( '_qliro_order_sync_enabled' );

		$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );

		if ( is_wp_error( $qliro_order ) ) {
			self::output_error( $qliro_order->get_error_message() );
			return;
		}

		$last_transaction    = self::get_last_transaction( $qliro_order['PaymentTransactions'] ?? array() );
		$transaction_type    = $last_transaction['Type'] ?? __( 'Not found', 'qliro-one-for-woocommerce' );
		$transaction_status  = $last_transaction['Status'] ?? __( 'Order status was not found.', 'qliro-one-for-woocommerce' );
		$order_sync_disabled = 'no' === $order_sync;

		self::output_info( __( 'Payment method', 'qliro-one-for-woocommerce' ), self::get_payment_method_name( $order ), self::get_payment_method_subtype( $order ) );
		self::output_info( __( 'Order id', 'qliro-one-for-woocommerce' ), $qliro_order_id );
		self::output_info( __( 'Reference', 'qliro-one-for-woocommerce' ), $qliro_reference );
		self::output_info( __( 'Order status', 'qliro-one-for-woocommerce' ), $transaction_type, $transaction_status );
		self::output_info( __( 'Total amount', 'qliro-one-for-woocommerce' ), self::get_amount( $last_transaction ) );

		if ( QOC_WC()->checkout()->is_integrated_shipping_enabled() ) {
			self::maybe_output_shipping_reference( $qliro_order );
		}

		if ( $order_sync_disabled ) {
			self::output_info( __( 'Order management', 'qliro-one-for-woocommerce' ), __( 'Disabled', 'qliro-one-for-woocommerce' ) );
		}
		echo '<br />';

		self::output_sync_order_button( $order, $qliro_order, $last_transaction, $order_sync_disabled );
		self::output_order_discount_button( $order, $qliro_order );
		self::output_collapsable_section( 'qliro-advanced', __( 'Advanced', 'qliro-one' ), self::get_advanced_section_content( $order ) );
	}

	/**
	 * Maybe localize the script with data.
	 *
	 * @param string $handle The script handle.
	 *
	 * @return void
	 */
	public function maybe_localize_script( $handle ) {
		if ( 'qliro-one-metabox' === $handle ) {
			$localize_data = array(
				'ajax'    => array(
					'setOrderSync' => array(
						'url'    => admin_url( 'admin-ajax.php' ),
						'action' => 'woocommerce_qliro_one_wc_set_order_sync',
						'nonce'  => wp_create_nonce( 'qliro_one_wc_set_order_sync' ),
					),
				),
				'orderId' => $this->get_id(),
			);
			wp_localize_script( 'qliro-one-metabox', 'qliroMetaboxParams', $localize_data );
		}
	}

	/**
	 * Get the advanced section content.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	private static function get_advanced_section_content( $order ) {
		$order_sync = $order->get_meta( '_qliro_order_sync_enabled' );

		// Default the order sync to be enabled. Unset metadata is returned as a empty string.
		if ( empty( $order_sync ) ) {
			$order_sync = 'yes';
		}

		$title   = __( 'Order management', 'qliro-one-for-woocommerce' );
		$tip     = __( 'Disable this to turn off the automatic synchronization with the Qliro Merchant Portal. When disabled, any changes in either system have to be done manually.', 'qliro-one-for-woocommerce' );
		$enabled = 'yes' === $order_sync;

		ob_start();
		self::output_toggle_switch( $title, $enabled, $tip, 'qliro-toggle-order-sync', array( 'qliro-order-sync' => $order_sync ) );
		return ob_get_clean();
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

		Qliro_One_Order_Management::sync_order_with_qliro( $order_id, $qliro_order_id );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
	}

	/**
	 * Handle the add order discount action request.
	 *
	 * @return void
	 */
	public function handle_add_order_discount_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce           = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$action          = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id        = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key       = filter_input( INPUT_GET, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_amount = filter_input( INPUT_GET, 'discount_amount', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_id     = filter_input( INPUT_GET, 'discount_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! isset( $action, $order_id, $order_key, $discount_amount, $discount_id ) ) {
			return;
		}

		// Check if this event even concerns us.
		if ( 'qliro_add_order_discount' !== $action ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) || $this->payment_method_id !== $order->get_payment_method() ) {
			return;
		}

		$order_key = $order->get_order_key();
		if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return;
		}

		try {

			// These controls should throw to inform the customer about what happened.
			if ( ! wp_verify_nonce( $nonce, 'qliro_add_order_discount' ) ) {
				throw new Exception( __( 'Invalid nonce.', 'qliro' ) );
			}

			// Description length allowed by Qliro.
			$discount_id = substr( $discount_id, 0, 200 );
			// Ensure there is actually a discounted amount, and that is less than the total amount.
			$order_total = $order->get_total();
			if ( ( $discount_amount * 100 ) >= ( $order_total * 100 ) ) {
				throw new Exception( __( 'Discount amount must be less than the order total.', 'qliro' ) );
			}

			foreach ( $order->get_fees() as $fee ) {
				// To avoid the form being submitted multiple times, we check if the discount already exists.
				if ( $fee->get_meta( 'qliro_discount_id' ) === $discount_id ) {
					throw new Exception( __( 'Discount already added to order.', 'qliro' ) );
				}
			}

			$fee = new WC_Order_Item_Fee();
			$fee->set_name( $discount_id );
			$fee->set_total( -1 * $discount_amount );
			$fee->set_total_tax( 0 );
			$fee->add_meta_data( 'qliro_discount_id', $discount_id );
			$fee->save();

			$fee_item = array(
				array(
					'MerchantReference'  => $discount_id,
					'Description'        => $fee->get_name(),
					'Quantity'           => $fee->get_quantity(),
					'Type'               => 'Discount',
					'PricePerItemIncVat' => $fee->get_total(),
					'PricePerItemExVat'  => $fee->get_total(),
				),
			);

			// Since a "shipped" Qliro order cannot be updated, the AddItemsToInvoice endpoint must be used instead.
			if ( qoc_is_fully_captured( $order ) ) {
				$response = QOC_WC()->api->add_items_qliro_order( $order_id, $fee_item );
			} else {
				// When updating an order, all items from the preauthorization must be included when updating an order that hasn't been "shipped" yet.
				$items    = array_merge( Qliro_One_Helper_Order::get_order_lines( $order_id ), $fee_item );
				$response = QOC_WC()->api->update_items_qliro_order( $order_id, $items );
			}

			if ( is_wp_error( $response ) ) {
				throw new Exception( __( 'Failed to add discount to Qliro order.', 'qliro' ) );
			}

			// Get the new payment transaction id from the response, and update the order meta with it.
			$transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'] ?? '';
			$order->update_meta_data( '_qliro_payment_transaction_id', $transaction_id );

			$order->add_item( $fee );
			$order->add_order_note( __( 'Discount added to order.', 'qliro' ) );
			$order->save();

		} catch ( Exception $e ) {
			$order->add_order_note( $e->getMessage() );
		} finally {
			wp_safe_redirect( $order->get_edit_order_url() );
			exit;
		}
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
	 * Get the status of a Qliro order from the payment transaction.
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

		// Replace any _ with a space.
		$payment_method = str_replace( '_', ' ', $payment_method );

		// Return the method but ensure only the first letter is uppercase.
		return ucfirst( strtolower( $payment_method ) );
	}

	/**
	 * Get the subtype of the Qliro payment method.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	private static function get_payment_method_subtype( $order ) {
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );
		$subtype        = $order->get_meta( 'qliro_one_payment_method_subtype_code' );

		// If the payment method starts with QLIRO_, it is a Qliro payment method.
		if ( strpos( $payment_method, 'QLIRO_' ) === 0 ) {
			$payment_method = str_replace( 'QLIRO_', '', $payment_method );
			$subtype        = __( 'Qliro payment method', 'qliro-one-for-woocommerce' );
		}

		return $subtype;
	}

	/**
	 * Output the sync order action button.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $qliro_order The Qliro order.
	 * @param array    $last_transaction The last transaction from the Qliro order.
	 *
	 * @return void
	 */
	private static function output_sync_order_button( $order, $qliro_order, $last_transaction, $order_sync_disabled ) {
		$is_captured             = qoc_is_fully_captured( $order ) || qoc_is_partially_captured( $order );
		$is_cancelled            = $order->get_meta( '_qliro_order_cancelled' );
		$last_transaction_amount = $last_transaction['Amount'] ?? 0;

		// If the order is captured or cancelled, do not output the sync button.
		if ( $is_captured || $is_cancelled ) {
			return;
		}

		$query_args = array(
			'action'         => 'qliro_one_sync_order',
			'order_id'       => $order->get_id(),
			'qliro_order_id' => $qliro_order['OrderId'] ?? '',
		);

		$action_url = wp_nonce_url(
			add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) ),
			'qliro_one_sync_order'
		);

		$classes = ( floatval( $order->get_total() ) === $last_transaction_amount ) ? 'button-secondary' : 'button-primary';

		if ( $order_sync_disabled ) {
			$classes .= ' disabled';
		}

		self::output_action_button(
			__( 'Sync order with Qliro', 'qliro-one-for-woocommerce' ),
			$action_url,
			false,
			$classes
		);
	}

	/**
	 * Output the "Add discount" action button.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $qliro_order The Qliro order.
	 *
	 * @return void
	 */
	private static function output_order_discount_button( $order, $qliro_order ) {
		// Referenced within the template.
		$action_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'qliro_add_order_discount',
					'order_id'        => $order->get_id(),
					'order_key'       => $order->get_order_key(),
					'qliro_order_id'  => $qliro_order['OrderId'] ?? '',
					'discount_amount' => 0,
					'discount_id'     => '',
				),
				admin_url( 'admin-ajax.php' )
			),
			'qliro_add_order_discount'
		);

		$classes = 'krokedil_wc__metabox_button krokedil_wc__metabox_action button button-secondary';
		echo "<a id='qliro_add_order_discount' class='{$classes}'>Add Qliro discount</a>";

		include_once QLIRO_WC_PLUGIN_PATH . '/includes/admin/views/html-order-add-discount.php';
	}

	/**
	 * Maybe output the shipping reference from the Qliro shipping line.
	 *
	 * @param array $qliro_order The Qliro order.
	 *
	 * @return void
	 */
	private static function maybe_output_shipping_reference( $qliro_order ) {
		// Get any order lines from the Qliro order with the type shipping.
		$shipping_line = array_filter(
			$qliro_order['OrderItemActions'] ?? array(),
			function ( $line ) {
				return 'Shipping' === $line['Type'];
			}
		);

		// If empty, just return.
		if ( empty( $shipping_line ) ) {
			return;
		}

		// Get the metadata from the shipping line and then the ShippingMethodMerchantReference if it exists.
		$shipping_line = reset( $shipping_line );
		$shipping_ref  = $shipping_line['MetaData']['ShippingMethodMerchantReference'] ?? '';

		// If its empty, just return.
		if ( empty( $shipping_ref ) ) {
			return;
		}

		self::output_info( __( 'Shipping reference', 'qliro-one-for-woocommerce' ), $shipping_ref );
	}
}

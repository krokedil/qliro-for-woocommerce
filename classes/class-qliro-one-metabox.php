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

		add_action( 'admin_notices', array( $this, 'output_admin_notices' ) );

		add_action( 'init', array( $this, 'set_metabox_title' ) );
		add_action( 'init', array( $this, 'handle_sync_order_action' ), 9999 );

		$this->scripts[] = 'qliro-one-metabox';
	}

	/**
	 * Set the metabox title.
	 *
	 * @return void
	 */
	public function set_metabox_title() {
		$this->title = __( 'Qliro order data', 'qliro-for-woocommerce' );
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

		$qliro_order = QLIRO_WC()->api->get_qliro_one_admin_order( $qliro_order_id, $order );

		if ( is_wp_error( $qliro_order ) ) {
			self::output_error( $qliro_order->get_error_message() );
			return;
		}

		$last_transaction    = Qliro_Order_Utility::get_last_transaction( $qliro_order );
		$qliro_total         = Qliro_Order_Utility::get_qliro_order_total( $qliro_order );
		$currency            = Qliro_Order_Utility::get_qliro_order_currency( $qliro_order );
		$transaction_type    = Qliro_Order_Utility::get_transaction_type( $last_transaction );
		$transaction_status  = Qliro_Order_Utility::get_transaction_status( $last_transaction );
		$order_sync_disabled = 'no' === $order_sync;

		self::output_info( __( 'Payment method', 'qliro-for-woocommerce' ), self::get_payment_method_name( $order ), self::get_payment_method_subtype( $order ) );
		self::output_info( __( 'Order id', 'qliro-for-woocommerce' ), $qliro_order_id );
		self::output_info( __( 'Reference', 'qliro-for-woocommerce' ), $qliro_reference );
		self::output_info( __( 'Order status', 'qliro-for-woocommerce' ), $transaction_type, $transaction_status );
		self::output_info( __( 'Total amount', 'qliro-for-woocommerce' ), wc_price( $qliro_total, array( 'currency' => $currency ) ) );

		if ( QLIRO_WC()->checkout()->is_integrated_shipping_enabled() ) {
			self::maybe_output_shipping_reference( $qliro_order );
		}

		if ( $order_sync_disabled ) {
			self::output_info( __( 'Order management', 'qliro-for-woocommerce' ), __( 'Disabled', 'qliro-for-woocommerce' ) );
		}
		echo '<br />';

		self::output_sync_order_button( $order, $qliro_order, $last_transaction, $order_sync_disabled, $qliro_total );
		Qliro_Order_Discount::output_order_discount_button( $order, $qliro_order );
		self::output_collapsable_section( 'qliro-advanced', __( 'Advanced', 'qliro-for-woocommerce' ), self::get_advanced_section_content( $order ) );
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
	 * Print an admin notice for errors that come from the metabox.
	 *
	 * @return void
	 */
	public function output_admin_notices() {
		if ( ! isset( $_GET['qliro_metabox_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['qliro_metabox_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cause  = sanitize_text_field( wp_unslash( $_GET['cause'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $notice ) {
			case 'invalid_nonce':
				$notice = __( 'Could not verify the security token. Please try again.', 'qliro-for-woocommerce' );
				break;
			case 'permission_denied':
				$notice = __( 'You do not have permission to add a discount to this order.', 'qliro-for-woocommerce' );
				break;
			case 'not_qliro_order':
				$notice = __( 'The order is not a Qliro order and a discount cannot be added.', 'qliro-for-woocommerce' );
				break;
			case 'invalid_hash':
				$notice = __( 'The order key is invalid. Please try again.', 'qliro-for-woocommerce' );
				break;
			case 'missing_parameters':
				$notice = __( 'Missing parameters to add the discount. Please try again.', 'qliro-for-woocommerce' );
				break;
			default:
				return;
		}

		$notice  = '<div class="notice notice-error is-dismissible">';
		$notice .= "<p>{$notice}</p>";
		$notice .= '</div>';

		echo wp_kses_post( $notice );
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

		$title   = __( 'Order management', 'qliro-for-woocommerce' );
		$tip     = __( 'Disable this to turn off the automatic synchronization with the Qliro Merchant Portal. When disabled, any changes in either system have to be done manually.', 'qliro-for-woocommerce' );
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
		exit;
	}

	/**
	 * Get the Qliro payment method name.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	public static function get_payment_method_name( $order ) {
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );

		// Replace any _ with a space.
		$payment_method = str_replace( '_', ' ', $payment_method );

		// Return the method but ensure only the first letter is uppercase.
		return ucfirst( strtolower( $payment_method ) );
	}

	/**
	 * Get the subtype of the Qliro payment method.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	private static function get_payment_method_subtype( $order ) {
		$payment_method = $order->get_meta( 'qliro_one_payment_method_name' );
		$subtype        = $order->get_meta( 'qliro_one_payment_method_subtype_code' );

		// If the payment method starts with QLIRO_, it is a Qliro payment method.
		if ( strpos( $payment_method, 'QLIRO_' ) === 0 ) {
			$payment_method = str_replace( 'QLIRO_', '', $payment_method );
			$subtype        = __( 'Qliro payment method', 'qliro-for-woocommerce' );
		}

		return $subtype;
	}

	/**
	 * Output the sync order action button.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $qliro_order The Qliro order.
	 * @param array    $last_transaction The last transaction from the Qliro order.
	 * @param bool     $order_sync_disabled Whether the order sync is disabled.
	 * @param float    $qliro_total The total amount from the Qliro order.
	 *
	 * @return void
	 */
	private static function output_sync_order_button( $order, $qliro_order, $last_transaction, $order_sync_disabled, $qliro_total ) {
		$is_captured  = qliro_is_fully_captured( $order ) || qliro_is_partially_captured( $order );
		$is_cancelled = $order->get_meta( '_qliro_order_cancelled' );

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

		$classes = ( floatval( $order->get_total() ) === $qliro_total ) ? 'button-secondary' : 'button-primary';

		if ( $order_sync_disabled ) {
			$classes .= ' disabled';
		}

		self::output_action_button(
			__( 'Sync order with Qliro', 'qliro-for-woocommerce' ),
			$action_url,
			false,
			$classes
		);
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

		// If empty, just return since there is no shipping line.
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

		self::output_info( __( 'Shipping reference', 'qliro-for-woocommerce' ), $shipping_ref );
	}
}

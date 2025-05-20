<?php
/**
 * File for the Partial Capture Admin order page class.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handing the admin order page.
 */
class Qliro_One_Partial_Capture_Admin_Order_Page {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Add a button and HTML code for partial capture.
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_partial_capture_button' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'include_partial_capture_html_code' ) );
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'add_order_item_header' ) );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'add_order_item_value' ), 10, 3 );
	}

	/**
	 * Adds a button on the order page next to the refund button to start the process for partial capture.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_partial_capture_button( $order ) {
		// Only show for shop orders.
		if ( 'shop_order' !== $order->get_type() ) {
			return;
		}

		// Only show if the order is paid via Qliro.
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return;
		}

		if ( qoc_is_fully_captured( $order ) ) {
			return;
		}

		if ( in_array( $order->get_status(), $this->get_allowed_statuses(), true ) ) {
			?>
			<button type="button" class="button partial-capture"><?php esc_html_e( 'Partial capture', 'qliro-one-for-woocommerce' ); ?></button>
			<?php
		}
	}

	/**
	 * Adds the HTML code needed for Partial capture on the order page.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function include_partial_capture_html_code( $order ) {
		if ( in_array( $order->get_status(), $this->get_allowed_statuses(), true ) ) {
			include_once QLIRO_WC_PLUGIN_PATH . '/includes/admin/views/html-order-partial-capture.php';
		}
	}

	/**
	 * Adds the order item header to the order item list.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function add_order_item_header( $order ) {
		// Only add the column for orders that are partially delivered.
		if ( ! qoc_is_partially_captured( $order ) ) {
			return;
		}

		?>
		<th class="qoc-captured-items-head" style="width:1%;"> <?php esc_html_e( 'Captured', 'qliro-one-for-woocommerce' ); ?></th>
		<?php
	}

	/**
	 * Adds value to our order list item header.
	 *
	 * @param WC_Product    $product The WooCommerce product.
	 * @param WC_Order_Item $order_item The WooCommerce order item.
	 * @param int           $order_item_id The WooCommerce order item id.
	 * @return void
	 */
	public function add_order_item_value( $product, $order_item, $order_item_id ) {
		// Skip any refund order items, since they are also triggering this action.
		if ( $order_item->get_type() === 'shop_order_refund' ) {
			return;
		}

		// Only add the values for orders that are partially captured.
		$order = wc_get_order( $order_item->get_order_id() );
		if ( ! qoc_is_partially_captured( $order ) ) {
			return;
		}

		// Make sure that order_item is a valid order item and not something else.
		if ( ! ( $order_item instanceof WC_Order_Item ) ) {
			return;
		}

		$captured_amount        = '';
		$qliro_captured_data    = $order_item->get_meta( '_qliro_captured_data' );
		$captured_item_quantity = qoc_get_captured_item_quantity( $qliro_captured_data );

		if ( ! empty( $captured_item_quantity ) ) {
			if ( $order_item->get_type() === 'line_item' ) {
				$captured_amount = $captured_item_quantity . ' ' . __( 'of', 'qliro-one-for-woocommerce' ) . ' ' . $order_item->get_quantity();
			} else {
				$captured_amount = __( 'Yes', 'qliro-one-for-woocommerce' );
			}
		}

		?>
		<td style="text-align:right;" class="qoc-captured-amount"><?php echo esc_html( $captured_amount ); ?></td>
		<?php
	}

	/**
	 * Get which order statuses to allow for creating captures.
	 *
	 * @return array
	 */
	protected function get_allowed_statuses() {
		$statuses = array( 'on-hold', 'processing', 'part-capture' );
		return apply_filters( 'qoc_allowed_statuses_for_creating_capture', $statuses );
	}
}
new Qliro_One_Partial_Capture_Admin_Order_Page();
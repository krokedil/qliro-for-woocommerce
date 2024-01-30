<?php
/**
 * Confirmation class file for the Qliro One gateway
 *
 * @package Thing
 */

 /**
  * Class Qliro_One_Confirmation
  */
class Qliro_One_Confirmation {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'confirm_order' ), 999 );
	}

	/**
	 * Confrims the order with Qliro and redirects the customer to the thankyou page.
	 *
	 * @return void
	 */
	public function confirm_order() {
		$confirmation_id = filter_input( INPUT_GET, 'qliro_one_confirm_page', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( empty( $confirmation_id ) ) {
			return;
		}

		$order = $this->get_order_by_confirmation_id( $confirmation_id );
		if ( empty( $order ) ) {
			return;
		}

		$order_id = $order->get_id();
		$result   = qliro_confirm_order( $order );

		qliro_one_unset_sessions();

		if ( $result ) {
			$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );
			Qliro_One_Logger::log( "Order ID $order_id confirmed on the confirmation page. Qliro Order ID: $qliro_order_id." );
		}

		header( 'Location:' . $order->get_checkout_order_received_url() );
		exit;
	}

	/**
	 * Gets the order from the confirmation id doing a database query for the meta field saved in the order.
	 *
	 * @param string $confirmation_id The confirmation id saved in the meta field.
	 * @return WC_Order|int WC_Order on success, otherwise 0.
	 */
	private function get_order_by_confirmation_id( $confirmation_id ) {
		$key    = '_qliro_one_order_confirmation_id';
		$orders = wc_get_orders(
			array(
				'meta_key'     => $key,
				'meta_value'   => $confirmation_id,
				'limit'        => 1,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'meta_compare' => '=',
			)
		);

		$order = reset( $orders );
		if ( empty( $order ) || $confirmation_id !== $order->get_meta( $key ) ) {
			return 0;
		}

		return $order;
	}
} new Qliro_One_Confirmation();

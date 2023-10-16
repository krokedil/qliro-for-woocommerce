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

		$result = qliro_confirm_order( $order );

		qliro_one_unset_sessions();

		if ( $result ) {
			$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id', true );
			Qliro_One_Logger::log( "Order ID $order_id confirmed on the confirmation page. Qliro Order ID: $qliro_order_id." );
		}

		header( 'Location:' . $order->get_checkout_order_received_url() );
		exit;
	}

	/**
	 * Gets the order from the confirmation id doing a database query for the meta field saved in the order.
	 *
	 * @param string $confirmation_id The confirmation id saved in the meta field.
	 * @return WC_Order
	 */
	private function get_order_by_confirmation_id( $confirmation_id ) {
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_qliro_one_order_confirmation_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'meta_value'  => $confirmation_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'date_query'  => array(
				array(
					'after' => '1 day ago',
				),
			),
		);

		$orders = get_posts( $query_args );
		if ( empty( $orders ) ) {
			return null;
		}

		$order = $orders[0]; // Get the first one in the array since it will be the newest. Good incase something goes wrong and multiple WC orders generate per Qliro order.
		return wc_get_order( $order );
	}
} new Qliro_One_Confirmation();

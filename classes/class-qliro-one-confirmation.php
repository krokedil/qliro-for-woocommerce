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
	 * Confirms the order with Qliro and redirects the customer to the thankyou page.
	 *
	 * @return void
	 */
	public function confirm_order() {
		$confirmation_id = filter_input( INPUT_GET, 'qliro_one_confirm_page', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( empty( $confirmation_id ) ) {
			return;
		}

		$order = qoc_get_order_by_confirmation_id( $confirmation_id );
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

} new Qliro_One_Confirmation();

<?php
/**
 * Confirmation class file for the Qliro gateway
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

		$order = qliro_get_order_by_confirmation_id( $confirmation_id );
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
	 * Lock a Qliro order id and WooCommerce order id combination to prevent multiple simultaneous confirmations.
	 *
	 * @param string $qliro_order_id The Qliro order id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return bool True if the lock was successful, false if there is already a lock for the given combination.
	 */
	public static function lock_qliro_confirmation( $qliro_order_id, $order_id ) {
		$key = "qliro_one_confirm_{$qliro_order_id}_{$order_id}";
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $key, true, 'qliro_one_locks', MINUTE_IN_SECONDS );
		}

		return set_transient( $key, true, MINUTE_IN_SECONDS );
	}

	/**
	 * Unlock a Qliro order id and WooCommerce order id combination after the confirmation process is done.
	 *
	 * @param string $qliro_order_id The Qliro order id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return void
	 */
	public static function unlock_qliro_confirmation( $qliro_order_id, $order_id ) {
		$key = "qliro_one_confirm_{$qliro_order_id}_{$order_id}";
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, 'qliro_one_locks' );
			return;
		}

		delete_transient( $key );
	}
}
new Qliro_One_Confirmation();

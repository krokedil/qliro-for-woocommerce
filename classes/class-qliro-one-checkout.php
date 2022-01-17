<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing actions during the checkout process.
 */
class Qliro_One_Checkout {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_qliro_order' ), 9999 );
	}

	/**
	 * Update the Qliro One order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_qliro_order() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );

		if ( empty( $qliro_order_id ) ) {
			return;
		}

		// Check if the cart hash has been changed since last update.
		$cart_hash  = WC()->cart->get_cart_hash();
		$saved_hash = WC()->session->get( 'qliro_one_last_update_hash' );

		// If they are the same, return.
		if ( $cart_hash === $saved_hash ) {
			return;
		}

		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_order_id );

		if ( 'InProcess' === $qliro_order['CustomerCheckoutStatus'] ) {
			$qliro_order = QOC_WC()->api->update_qliro_one_order( $qliro_order_id );
		}

		$saved_hash = WC()->session->set( 'qliro_one_last_update_hash', $cart_hash );
	}
} new Qliro_One_Checkout();

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

		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_order_id );

		if ( 'InProcess' === $qliro_order['CustomerCheckoutStatus'] ) {
			$qliro_order = QOC_WC()->api->update_qliro_one_order( $qliro_order_id );
		}
	}
} new Qliro_One_Checkout();

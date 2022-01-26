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
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_shipping_data_input' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ), 1 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_qliro_order' ), 9999 );
	}

	/**
	 * Add a hidden input field for the shipping data from Qliro One.
	 *
	 * @param array $fields The WooCommerce checkout fields.
	 * @return array
	 */
	public function add_shipping_data_input( $fields ) {
		$default = '';

		if ( is_checkout() ) {
			$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );
			$shipping_data  = get_transient( 'qoc_shipping_data_' . $qliro_order_id );
			$default        = wp_json_encode( $shipping_data );
		}

		$fields['billing']['qoc_shipping_data'] = array(
			'type'    => 'hidden',
			'class'   => array( 'qoc_shipping_data' ),
			'default' => $default,
		);

		return $fields;
	}

	/**
	 * Update the shipping method in WooCommerce based on what Klarna has sent us.
	 *
	 * @return void
	 */
	public function update_shipping_method() {
		if ( ! is_checkout() ) {
			return;
		}
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore
			parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore
			if ( isset( $post_data['qoc_shipping_data'] ) ) {
				WC()->session->set( 'qoc_shipping_data', $post_data['qoc_shipping_data'] );
				WC()->session->set( 'qoc_shipping_data_set', true );
				$data = json_decode( $post_data['qoc_shipping_data'], true );
				qoc_update_wc_shipping( $data );
			}
		}
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

		if ( WC()->session->get( 'qoc_shipping_data_set' ) ) {
			WC()->session->__unset( 'qoc_shipping_data_set' );
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

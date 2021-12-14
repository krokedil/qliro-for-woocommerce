<?php
/**
 * Functions file for the plugin.
 *
 * @package  Klarna_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Echoes Qliro One Checkout iframe snippet.
 *
 * @return array|void
 */
function qliro_one_create_or_update_order() {
	$session = WC()->session;
	// try to get id from session.
	$qliro_one_order_id = $session->get( 'qliro_one_order_id' );
	if ( $qliro_one_order_id ) {
		// try to update and then get the order again.
		$response = QOC_WC()->api->update_qliro_one_order( $qliro_one_order_id );
		if ( ! is_wp_error( $response ) ) {
			return QOC_WC()->api->get_qliro_one_order( $qliro_one_order_id );
		}
	}
	// create.
	$response = QOC_WC()->api->create_qliro_one_order();
	if ( is_wp_error( $response ) || ! isset( $response['OrderId'] ) ) {
		// If failed then bail.
		return;
	}
	// store id.
	$session->set( 'qliro_one_order_id', $response['OrderId'] );
	// get qliro order.
	return QOC_WC()->api->get_qliro_one_order( $session->get( 'qliro_one_order_id' ) );
}

/**
 * Echoes Qliro One Checkout iframe snippet.
 */
function qliro_wc_show_snippet() {
	$qliro_one_order = qliro_one_create_or_update_order();
	if ( null !== $qliro_one_order ) {
		do_action( 'qliro_one_wc_show_snippet', $qliro_one_order );
		echo $qliro_one_order['OrderHtmlSnippet'];// phpcs:ignore WordPress -- Can not escape this, since its the iframe snippet.
	}
	// todo here order is null.
}


/**
 * Calculates cart totals.
 */
function qliro_one_wc_calculate_totals() {
	WC()->cart->calculate_fees();
	WC()->cart->calculate_totals();
}


/**
 * Prints error message as notices.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function qliro_one_print_error_message( $wp_error ) {
	wc_print_notice( $wp_error->get_error_message(), 'error' );
}

/**
 * Unsets the sessions used by the plguin.
 *
 * @return void
 */
function qliro_one_unset_sessions() {
	// todo unset qliro variables from session.
	// todo clear qliro order id.
}

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
 * @return void
 */
function qliro_one_wc_show_snippet() {
	// todo
	// maybe create or update
	// save id instead of the response.
	if ( WC()->session->get( 'qliro_one_res' ) ) {
		// update
	} else {
		// create
	}
	$response = WC()->session->get( 'qliro_get_res' );

	echo $response['OrderHtmlSnippet'];// phpcs:ignore WordPress -- Can not escape this, since its the iframe snippet.
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

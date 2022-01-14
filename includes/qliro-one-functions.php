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
 * Create or update the qliro order
 *
 * @return array|void
 */
function qliro_one_create_or_update_order() {
	$cart    = WC()->cart;
	$session = WC()->session;
	// try to get id from session.
	$qliro_one_order_id = $session->get( 'qliro_one_order_id' );
	$cart->calculate_fees();
	$cart->calculate_shipping();
	$cart->calculate_totals();
	if ( $qliro_one_order_id ) {
		$update_response = QOC_WC()->api->update_qliro_one_order( $qliro_one_order_id );
		if ( ! is_wp_error( $update_response ) ) {
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
	WC()->session->__unset( 'qliro_order_confirmation_id' );
	WC()->session->__unset( 'qliro_one_billing_country' );
	WC()->session->__unset( 'qliro_one_merchant_reference' );
	WC()->session->__unset( 'qliro_one_order_id' );
}

/**
 * Shows select another payment method button in Qliro One Checkout page.
 */
function qliro_one_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_qliro_one_settings' );
		$select_another_method_text = isset( $settings['other_payment_method_button_text'] ) && '' !== $settings['other_payment_method_button_text'] ? $settings['other_payment_method_button_text'] : __( 'Select another payment method', 'qliro-one-checkout-for-woocommerce' );

		?>
		<p class="qliro-one-checkout-select-other-wrapper">
			<a class="checkout-button button" href="#" id="qliro-one-select-other">
				<?php echo esc_html( $select_another_method_text ); ?>
			</a>
		</p>
		<?php
	}
}

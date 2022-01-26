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
function qliro_one_maybe_create_order() {
	$cart    = WC()->cart;
	$session = WC()->session;
	// try to get id from session.
	$qliro_one_order_id = $session->get( 'qliro_one_order_id' );
	$cart->calculate_fees();
	$cart->calculate_shipping();
	$cart->calculate_totals();
	if ( $qliro_one_order_id ) {
		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_one_order_id );
		// If error, create new order.
		if ( is_wp_error( $qliro_order ) ) {
			$session->__unset( 'qliro_one_order_id' );
			return qliro_one_maybe_create_order();
		}
		return $qliro_order;
	}
	// create.
	$qliro_order = QOC_WC()->api->create_qliro_one_order();
	if ( is_wp_error( $qliro_order ) || ! isset( $qliro_order['OrderId'] ) ) {
		// If failed then bail.
		return;
	}
	// store id.
	$session->set( 'qliro_one_order_id', $qliro_order['OrderId'] );
	$session->set( 'qliro_one_last_update_hash', WC()->cart->get_cart_hash() );
	// get qliro order.
	return QOC_WC()->api->get_qliro_one_order( $session->get( 'qliro_one_order_id' ) );
}

/**
 * Echoes Qliro One Checkout iframe snippet.
 */
function qliro_wc_get_snippet() {
	$snippet = WC()->session->get( 'qliro_one_snippet' );

	if ( empty( $snippet ) ) {
		$qliro_one_order = qliro_one_maybe_create_order();
		$snippet         = $qliro_one_order['OrderHtmlSnippet'];
		WC()->session->set( 'qliro_one_snippet', $snippet );
	}
	if ( ! empty( $snippet ) ) {
		return $snippet;// phpcs:ignore WordPress -- Can not escape this, since its the iframe snippet.
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
	WC()->session->__unset( 'qliro_one_last_update_hash' );
	WC()->session->__unset( 'qliro_one_snippet' );
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

/**
 * Confirm a qliro order.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return void
 */
function qliro_confirm_order( $order ) {
	// Check if the order has been confirmed already.
	if ( ! empty( $order->get_date_paid() ) ) {
		return;
	}
	$order_id = $order->get_id();
	$response = QOC_WC()->api->update_qliro_one_merchant_reference( $order_id );

	if ( is_wp_error( $response ) ) {
		return;
	}

	if ( isset( $response['PaymentTransactionId'] ) && ! empty( $response['PaymentTransactionId'] ) ) {
		update_post_meta( $order_id, '_payment_transaction_id', $response['PaymentTransactionId'] );
	}

	$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id', true );
	$order->payment_complete( $qliro_order_id );

	$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );
	if ( is_wp_error( $qliro_order ) ) {
		Qliro_One_Logger::log( "Failed to get the admin order during confirmation. Qliro order id: $qliro_order_id, WooCommerce order id: $order_id" );
	}

	foreach ( $qliro_order['PaymentTransactions'] as $payment_transaction ) {
		if ( 'Success' === $payment_transaction['Status'] ) {
			$order->set_payment_method_title( "Qliro One - $payment_transaction[PaymentMethodSubtypeCode]" );
			$order->save();
		}
	}
}

/**
 * Undocumented function
 *
 * @param array|bool $data The shipping data from Qliro. False if not set.
 * @return void
 */
function qoc_update_wc_shipping( $data ) {
	// Set cart definition.
	$qliro_order_id = WC()->session->get( 'qliro_one_order_id' );

	// If we don't have a Klarna order, return void.
	if ( empty( $qliro_order_id ) ) {
		return;
	}

	// If the data is empty, return void.
	if ( empty( $data ) ) {
		return;
	}

	do_action( 'qoc_update_shipping_data', $data );

	set_transient( 'qoc_shipping_data_' . $qliro_order_id, $data, HOUR_IN_SECONDS );
	$chosen_shipping_methods   = array();
	$chosen_shipping_methods[] = wc_clean( $data['method'] );
	WC()->session->set( 'chosen_shipping_methods', apply_filters( 'qoc_chosen_shipping_method', $chosen_shipping_methods ) );
}

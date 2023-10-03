<?php
use Krokedil\Shipping\PickupPoints;

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
		if ( is_wp_error( $qliro_order ) || 'InProcess' !== $qliro_order['CustomerCheckoutStatus'] ) {
			qliro_one_unset_sessions();
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
 *
 * @return string
 */
function qliro_wc_get_snippet() {
	$qliro_one_order = qliro_one_maybe_create_order();
	$snippet         = $qliro_one_order['OrderHtmlSnippet'];

	if ( ! empty( $snippet ) ) {
		return $snippet;
	}
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
	$error_message = $wp_error->get_error_message();

	if ( is_array( $error_message ) ) {
		// Rather than assuming the first element is a string, we'll force a string conversion instead.
		$error_message = implode( ' ', $error_message );
	}

	if ( is_ajax() ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $error_message, 'error' );
		}
	} else {
		if ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $error_message, 'error' );
		}
	}
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
 * @return bool
 */
function qliro_confirm_order( $order ) {
	// Check if the order has been confirmed already.
	if ( ! empty( $order->get_date_paid() ) ) {
		return false;
	}

	$order_id       = $order->get_id();
	$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id', true );

	$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );

	if ( is_wp_error( $qliro_order ) ) {
		return false;
	}

	foreach ( $qliro_order['PaymentTransactions'] as $transaction ) {
		if ( 'Preauthorization' === $transaction['Type'] && 'OnHold' === $transaction['Status'] ) {
			$order->update_status( 'on-hold', __( 'The Qliro order is on-hold and awaiting a status update from Qliro.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return false;
		}
	}

	$response = QOC_WC()->api->update_qliro_one_merchant_reference( $order_id );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	if ( isset( $response['PaymentTransactionId'] ) && ! empty( $response['PaymentTransactionId'] ) ) {
		update_post_meta( $order_id, '_qliro_payment_transaction_id', $response['PaymentTransactionId'] );
		$order->add_order_note( __( 'Qliro One order successfully placed. (Qliro Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $response['PaymentTransactionId'] . ')' );
	}

	$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id', true );
	$note           = sprintf( __( 'Payment via Qliro One, Qliro order id: %s', 'qliro-one-for-woocommerce' ), sanitize_key( $qliro_order_id ) );

	$order->add_order_note( $note );
	$order->payment_complete( $qliro_order_id );

	$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );
	if ( is_wp_error( $qliro_order ) ) {
		Qliro_One_Logger::log( "Failed to get the admin order during confirmation. Qliro order id: $qliro_order_id, WooCommerce order id: $order_id" );
	}

	foreach ( $qliro_order['PaymentTransactions'] as $payment_transaction ) {
		if ( 'Success' === $payment_transaction['Status'] ) {
			update_post_meta( $order_id, 'qliro_one_payment_method_name', $payment_transaction['PaymentMethodName'] );
			update_post_meta( $order_id, 'qliro_one_payment_method_subtype_code', $payment_transaction['PaymentMethodSubtypeCode'] );
			if ( isset( $qliro_order['Upsell'] ) && isset( $qliro_order['Upsell']['EligibleForUpsellUntil'] ) ) {
				update_post_meta( $order_id, '_ppu_upsell_urgency_deadline', strtotime( $qliro_order['Upsell']['EligibleForUpsellUntil'] ) );
			}
		}
	}

	return true;
}

/**
 * Update WooCommerce shipping when shipping is controlled in the iframe.
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

/**
 * Get the Qliro order for the thankyou page. Helper function due to caching.
 *
 * @param string $qliro_order_id The Qliro Order id.
 * @return array
 */
function qoc_get_thankyou_page_qliro_order( $qliro_order_id ) {
	$qliro_order = json_decode( get_transient( "qliro_thankyou_order_$qliro_order_id" ), true );

	if ( empty( $qliro_order ) ) {
		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_order_id );

		if ( is_wp_error( $qliro_order ) ) {
			return $qliro_order;
		}

		set_transient( "qliro_thankyou_order_$qliro_order_id", json_encode( $qliro_order ), 10 );
	}

	return $qliro_order;
}

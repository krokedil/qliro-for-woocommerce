<?php
/**
 * Functions file for the plugin.
 *
 * @package  Klarna_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

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
		// Validate the order.
		if ( ! qliro_one_is_valid_order( $qliro_order ) ) {
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
 * @return string|null
 */
function qliro_wc_get_snippet() {
	$qliro_one_order = qliro_one_maybe_create_order();
	$snippet         = $qliro_one_order['OrderHtmlSnippet'] ?? null;

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
	} elseif ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $error_message, 'error' );
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
		// translators: %s - WooCommerce order number.
		Qliro_One_Logger::log( sprintf( __( 'Aborting qliro_confirm_order function. WooCommerce order %s already confirmed.', 'qliro-one-for-woocommerce' ), $order->get_order_number() ) );
		return false;
	}

	$order_id       = $order->get_id();
	$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );

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
		// translators: %s - Response error message.
		$note = sprintf( __( 'There was a problem updating merchant reference in Qliro\'s system. Error message: %s', 'qliro-one-for-woocommerce' ), $response->get_error_message() );
		$order->add_order_note( $note );
		return false;
	}

	if ( isset( $response['PaymentTransactionId'] ) && ! empty( $response['PaymentTransactionId'] ) ) {
		$order->update_meta_data( '_qliro_payment_transaction_id', $response['PaymentTransactionId'] );
		$order->add_order_note( __( 'Qliro One order successfully placed. (Qliro Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $response['PaymentTransactionId'] . ')' );
	}

	$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );
	// translators: %s - the Qliro order ID.
	$note = sprintf( __( 'Payment via Qliro One, Qliro order id: %s', 'qliro-one-for-woocommerce' ), sanitize_key( $qliro_order_id ) );

	$order->add_order_note( $note );
	$order->payment_complete( $qliro_order_id );

	$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );
	if ( is_wp_error( $qliro_order ) ) {
		Qliro_One_Logger::log( "Failed to get the admin order during confirmation. Qliro order id: $qliro_order_id, WooCommerce order id: $order_id" );
	}

	foreach ( $qliro_order['PaymentTransactions'] as $payment_transaction ) {
		if ( 'Success' === $payment_transaction['Status'] ) {
			$order->update_meta_data( 'qliro_one_payment_method_name', $payment_transaction['PaymentMethodName'] );
			$order->update_meta_data( 'qliro_one_payment_method_subtype_code', $payment_transaction['PaymentMethodSubtypeCode'] );
			if ( isset( $qliro_order['Upsell'] ) && isset( $qliro_order['Upsell']['EligibleForUpsellUntil'] ) ) {
				$order->update_meta_data( '_ppu_upsell_urgency_deadline', strtotime( $qliro_order['Upsell']['EligibleForUpsellUntil'] ) );
			}
		}
	}

	$order->save();
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
 * @return array|WP_Error
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

/**
 * Equivalent to WP's get_the_ID() with HPOS support.
 *
 * @return int|false the order ID or false.
 */
//phpcs:ignore -- ignore snake-case here to match get_the_ID().
function qoc_get_the_ID() {
	$hpos_enabled = qoc_is_hpos_enabled();
	$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
	if ( empty( $order_id ) ) {
		if ( ! $hpos_enabled ) {
			$order_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );
			return empty( $order_id ) ? false : $order_id;
		}
		return false;
	}

	return absint( $order_id );
}

/**
 * Whether HPOS is enabled.
 *
 * @return bool true if HPOS is enabled, otherwise false.
 */
function qoc_is_hpos_enabled() {
	// CustomOrdersTableController was introduced in WC 6.4.
	if ( class_exists( CustomOrdersTableController::class ) ) {
		return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
	}

	return false;
}


/**
 * Gets the order from the confirmation id doing a database query for the meta field saved in the order.
 *
 * @param string $confirmation_id The confirmation id saved in the meta field.
 * @return WC_Order|int WC_Order on success, otherwise 0.
 */
function qoc_get_order_by_confirmation_id( $confirmation_id ) {
	$key    = '_qliro_one_order_confirmation_id';
	$orders = wc_get_orders(
		array(
			'meta_key'     => $key,
			'meta_value'   => $confirmation_id,
			'limit'        => 1,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'meta_compare' => '=',
		)
	);

	$order = reset( $orders );
	if ( empty( $order ) || $confirmation_id !== $order->get_meta( $key ) ) {
		Qliro_One_Logger::log( "No order found with the confirmation id $confirmation_id" );
		return 0;
	}
	return $order;
}

/**
 * Validate qliro order's status, currency, and country settings.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return bool
 */
function qliro_one_is_valid_order( $order ) {
	if ( is_wp_error( $order ) || 'InProcess' !== $order['CustomerCheckoutStatus'] || $order['Currency'] !== get_woocommerce_currency() || $order['Country'] !== WC()->customer->get_billing_country() ) {
		return false;
	}
	return true;
}
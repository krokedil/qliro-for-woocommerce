<?php
/**
 * Functions file for the plugin.
 *
 * @package  Qliro_One_For_WooCommerce/Includes
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

	if ( qliro_one_has_country_changed() ) {
		qliro_one_unset_sessions();
		return qliro_one_maybe_create_order();
	}

	if ( $qliro_one_order_id ) {
		$qliro_order = QOC_WC()->api->get_qliro_one_order( $qliro_one_order_id );
		if ( is_wp_error( $qliro_order ) ) {
			qliro_one_print_error_message( $qliro_order );
			return;
		}

		if ( qliro_one_is_completed( $qliro_order ) ) {
			Qliro_One_Logger::log( "[CHECKOUT]: The Qliro order (id: $qliro_one_order_id) is already completed, but the customer is still on checkout page. Redirecting to thankyou page." );
			qliro_one_redirect_to_thankyou_page();
		}

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
	$session->set( 'qliro_one_last_update_hash', Qliro_One_Checkout::calculate_hash() );
	// get qliro order.
	return QOC_WC()->api->get_qliro_one_order( $session->get( 'qliro_one_order_id' ) );
}

/**
 * Echoes Qliro Checkout iframe snippet.
 *
 * @return string|null
 */
function qliro_wc_get_snippet() {

	if ( ! qliro_is_enabled_with_demo_check() ) {
		return null;
	}

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
	} elseif ( function_exists( 'wc_add_notice' ) ) {
		// Add to the queue to be printed later. This allows the notice to be displayed along the other notices.
		wc_add_notice( $error_message, 'error' );
	}
}

/**
 * Unsets the sessions used by the plugin.
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
 * Shows select another payment method button in Qliro Checkout page.
 */
function qliro_one_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_qliro_one_settings' );
		$select_another_method_text = isset( $settings['other_payment_method_button_text'] ) && '' !== $settings['other_payment_method_button_text'] ? $settings['other_payment_method_button_text'] : __( 'Select another payment method', 'qliro-one-for-woocommerce' );

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

	// Save SignupForNewsletter value to order meta if it exists.
	if ( isset( $qliro_order['SignupForNewsletter'] ) ) {
		$order->update_meta_data( '_qliro_one_signup_for_newsletter', wc_bool_to_string( $qliro_order['SignupForNewsletter'] ) );
		$order->save_meta_data();
	}

	foreach ( $qliro_order['PaymentTransactions'] as $transaction ) {
		if ( 'Preauthorization' === $transaction['Type'] && 'OnHold' === $transaction['Status'] ) {
			$order->update_status( 'on-hold', __( 'The Qliro order is on-hold and awaiting a status update from Qliro.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return false;
		}
	}

	$order = wc_get_order( $order_id );

	// If the order number and the qliro reference already match, we don't need to update the merchant reference.
	if ( $order->get_order_number() !== $qliro_order['MerchantReference'] ) {
		$qliro_order = QOC_WC()->api->update_qliro_one_merchant_reference( $order_id );

		if ( is_wp_error( $qliro_order ) ) {
			// translators: %s - Response error message.
			$note = sprintf( __( 'There was a problem updating merchant reference in Qliro\'s system. Error message: %s', 'qliro-one-for-woocommerce' ), $qliro_order->get_error_message() );
			$order->add_order_note( $note );
			return false;
		}
	}

	if ( isset( $qliro_order['PaymentTransactionId'] ) && ! empty( $qliro_order['PaymentTransactionId'] ) ) {
		$order->update_meta_data( '_qliro_payment_transaction_id', $qliro_order['PaymentTransactionId'] );
		$order->add_order_note( __( 'Qliro order successfully placed. (Qliro Payment transaction id: ', 'qliro-one-for-woocommerce' ) . $qliro_order['PaymentTransactionId'] . ')' );
	}

	$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );
	// translators: %s - the Qliro order ID.
	$note = sprintf( __( 'Payment via Qliro, Qliro order id: %s', 'qliro-one-for-woocommerce' ), sanitize_key( $qliro_order_id ) );

	$order->add_order_note( $note );
	$order->payment_complete( $qliro_order_id );

	$qliro_order = QOC_WC()->api->get_qliro_one_admin_order( $qliro_order_id );
	if ( is_wp_error( $qliro_order ) ) {
		Qliro_One_Logger::log( "Failed to get the admin order during confirmation. Qliro order id: $qliro_order_id, WooCommerce order id: $order_id" );
	}

	foreach ( $qliro_order['PaymentTransactions'] as $payment_transaction ) {
		if ( 'Success' === $payment_transaction['Status'] ) {
			$order->update_meta_data( 'qliro_one_payment_method_name', $payment_transaction['PaymentMethodName'] );

			// If the PaymentMethodSubtypeCode is missing, we can retrieve it from the PaymentMethodName (e.g., QLIRO_INVOICE).
			$subtype = implode( ' ', array_slice( explode( '_', $payment_transaction['PaymentMethodName'] ), 1 ) );
			$subtype = $payment_transaction['PaymentMethodSubtypeCode'] ?? $subtype ?? '';
			$order->update_meta_data( 'qliro_one_payment_method_subtype_code', $subtype );

			if ( isset( $qliro_order['Upsell'] ) && isset( $qliro_order['Upsell']['EligibleForUpsellUntil'] ) ) {
				$order->update_meta_data( '_ppu_upsell_urgency_deadline', strtotime( $qliro_order['Upsell']['EligibleForUpsellUntil'] ) );
			}

			if ( Qliro_One_Subscriptions::is_subscription( $order ) && 'QLIRO_CARD' !== $payment_transaction['PaymentMethodName'] ) {
				// Get the subscriptions for the order.
				$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );

				// If the WooCommerce order is a subscription order, we need to store the PersonalNumber if the payment method was not QLIRO_CARD.
				$personal_number = $qliro_order['Customer']['PersonalNumber'] ?? '';

				if ( ! empty( $personal_number ) ) {
					// Loop through the subscriptions and set the personal number.
					foreach ( $subscriptions as $subscription ) {
						$subscription->update_meta_data( '_qliro_personal_number', $qliro_order['Customer']['PersonalNumber'] );
						$subscription->save();
					}

					// If the personal number is not empty, we store it in the WooCommerce order.
					$order->update_meta_data( '_qliro_personal_number', $personal_number );
				}
			}
		}
	}

	do_action( 'qoc_order_confirmed', $qliro_order, $order );
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

	// If we don't have a Qliro order, return void.
	if ( empty( $qliro_order_id ) ) {
		return;
	}

	// If the data is empty, return void.
	if ( empty( $data ) ) {
		return;
	}

	do_action( 'qoc_update_shipping_data', $data );

	// If we are using integrated shipping, we don't need to set the chosen method, but we need to update the shipping.
	if ( QOC_WC()->checkout()->is_integrated_shipping_enabled() ) {
		$data['secondaryOption'] ??= $data['method'];
		set_transient( 'qoc_shipping_data_' . $qliro_order_id, $data, HOUR_IN_SECONDS );
		qliro_clear_shipping_package_hashes(); // Clear shipping packages to ensure we recalculate the shipping rates, and save the new pickup point.
		return;
	}

	set_transient( 'qoc_shipping_data_' . $qliro_order_id, $data, HOUR_IN_SECONDS );
	$chosen_shipping_methods   = array();
	$chosen_shipping_methods[] = wc_clean( $data['method'] );
	WC()->session->set( 'chosen_shipping_methods', apply_filters( 'qoc_chosen_shipping_method', $chosen_shipping_methods ) );
}

function qliro_clear_shipping_package_hashes() {
	// Get all package keys.
	$packages     = WC()->cart->get_shipping_packages();
	$package_keys = array_keys( $packages );

	// Loop them to ensure we clear the shipping rates for all of them.
	foreach ( $package_keys as $package_key ) {
		$wc_session_key = 'shipping_for_package_' . $package_key;
		WC()->session->__unset( $wc_session_key );
	}
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
			'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => $confirmation_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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
 * Get an order by the Qliro order id
 *
 * @param string $qliro_order_id The Qliro order id.
 * @return WC_Order|int WC_Order on success, otherwise 0.
 */
function qoc_get_order_by_qliro_id( $qliro_order_id ) {
	$key    = '_qliro_one_order_id';
	$orders = wc_get_orders(
		array(
			'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => strval( $qliro_order_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'limit'        => 1,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'meta_compare' => '=',
		)
	);

	$order = reset( $orders );
	if ( empty( $order ) || strval( $qliro_order_id ) !== $order->get_meta( $key ) ) {
		Qliro_One_Logger::log( "No order found with the Qliro order id $qliro_order_id" );
		return 0;
	}
	return $order;
}

/**
 * Validate qliro order's status, currency, and country settings.
 *
 * @param array $qliro_order The Qliro order.
 * @return bool
 */
function qliro_one_is_valid_order( $qliro_order ) {
	$is_in_process     = 'InProcess' === $qliro_order['CustomerCheckoutStatus'];
	$is_currency_match = get_woocommerce_currency() === $qliro_order['Currency'];
	$is_country_match  = WC()->customer->get_billing_country() === $qliro_order['Country'];

	if ( ! $is_in_process || ! $is_currency_match || ! $is_country_match ) {
		return false;
	}

	return true;
}

/**
 * Checks if the order is partially captured.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return bool
 */
function qoc_is_partially_captured( $order ) {
	$is_partially_captured             = false;
	$order_line_count                  = 0;
	$order_line_with_refund_data_count = 0;

	foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
		// Check if the order item has captured data and if the quantity is less than the captured quantity.
		if ( ! empty( $order_item->get_meta( '_qliro_captured_data' ) ) && $order_item->get_quantity() > qoc_get_captured_item_quantity( $order_item->get_meta( '_qliro_captured_data' ) ) ) {
			$is_partially_captured = true;
			break;
		}
		// Count the order lines with refund data.
		if ( ! empty( $order_item->get_meta( '_qliro_captured_data' ) ) ) {
			++$order_line_with_refund_data_count;
		}
		// Count the order lines.
		++$order_line_count;
	}

	// If the amount of order lines with refund data is larger than 0 but less than the amount of order lines, the order is partially captured.
	if ( $order_line_with_refund_data_count > 0 && $order_line_with_refund_data_count < $order_line_count ) {
		$is_partially_captured = true;
	}

	return $is_partially_captured;
}

/**
 * Checks if the order is fully captured.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return bool
 */
function qoc_is_fully_captured( $order ) {

	if ( $order->get_meta( '_qliro_order_captured' ) ) {
		return true;
	}

	$is_fully_captured = true;

	foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
		// Iterate over the order items and make sure that all line items have been captured.
		if ( $order_item->get_quantity() > qoc_get_captured_item_quantity( $order_item->get_meta( '_qliro_captured_data' ) ) ) {
			$is_fully_captured = false;
			break;
		}
	}

	return $is_fully_captured;
}

/*
 * Get the remaining items to capture for an order.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return array
 */
function qoc_get_remaining_items_to_capture( $order ) {
	$items = array();
	foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
		if ( $order_item->get_quantity() <= qoc_get_captured_item_quantity( $order_item->get_meta( '_qliro_captured_data' ) ) ) {
			continue;
		}
		$items[ $order_item->get_id() ] = $order_item->get_quantity() - qoc_get_captured_item_quantity( $order_item->get_meta( '_qliro_captured_data' ) );
	}

	return $items;
}

/**
 * Get the number of captured items from one order line.
 *
 * @param string $qliro_captured_data The WooCommerce order item meta data field _qliro_captured_data (each capture comma separated from the other and each capture gathered as {payment_transaction_id}:{amount_of_items}).
 * @return int
 */
function qoc_get_captured_item_quantity( $qliro_captured_data ) {

	// If the captured data is empty, return 0.
	if ( empty( $qliro_captured_data ) ) {
		return 0;
	}
	$captured_items = 0;
	$captured_data  = explode( ',', $qliro_captured_data );

	// If the captured data is empty, return 0.
	if ( empty( $captured_data ) ) {
		return $captured_items;
	}

	foreach ( $captured_data as $capture ) {
		$capture_data    = explode( ':', $capture );
		$captured_items += (int) $capture_data[1];
	}

	return $captured_items;
}

/**
 * Return captured items for an order.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return array
 */
function qoc_get_captured_items( $order ) {
	$captured_items = array();
	foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_item ) {
		$captured_items[ $order_item->get_id() ] = qoc_get_captured_item_quantity( $order_item->get_meta( '_qliro_captured_data' ) );
	}

	return $captured_items;
}

/**
 * Verify if that the order is not already completed in Qliro.
 *
 * @param array $qliro_order The Qliro order.
 *
 * @return bool
 */
function qliro_one_verify_not_completed( $qliro_order ) {
	// If the order from Qliro is already completed, we should not proceed with the order, and instead redirect the customer to the thankyou page.
	if ( 'Completed' === $qliro_order['CustomerCheckoutStatus'] ) {
		$qliro_id = $qliro_order['OrderId'];
		Qliro_One_Logger::log( "Qliro order {$qliro_id} is already completed. Redirecting to the thankyou page." );
		return false;
	}

	return true;
}

/**
 * Check if the Qliro order is considered completed.
 *
 * @param array $qliro_order The Qliro order.
 *
 * @return bool
 */
function qliro_one_is_completed( $qliro_order ) {
	$done_status = array( 'Completed', 'OnHold' );
	return in_array( $qliro_order['CustomerCheckoutStatus'], $done_status, true );
}

/**
 * Redirect the customer to the thankyou page for a completed qliro order.
 *
 * @return void
 */
function qliro_one_redirect_to_thankyou_page() {
	// Redirect the customer to the thankyou page for the order, with the orders confirmation id as a query parameter.
	$redirect_url = qliro_one_get_thankyou_page_redirect_url();
	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Get the thankyou page redirect URL for the order.
 *
 * @return string
 */
function qliro_one_get_thankyou_page_redirect_url() {
	// Get the WC Order for the Qliro order.
	$confirmation_id = WC()->session->get( 'qliro_order_confirmation_id' );
	$order           = qoc_get_order_by_confirmation_id( $confirmation_id );

	$redirect_url = '';
	if ( empty( $order ) ) {
		Qliro_One_Logger::log( "No order found with the confirmation id $confirmation_id when trying to redirect the customer to the thankyou page." );
		$redirect_url = wc_get_endpoint_url( 'order-received' );
	} else {
		Qliro_One_Logger::log( "Redirecting the customer to the thankyou page for the order with the confirmation id $confirmation_id." );
		$redirect_url = $order->get_checkout_order_received_url();
	}

	// Redirect the customer to the thankyou page for the order, with the orders confirmation id as a query parameter.
	$redirect_url = add_query_arg( 'qliro_one_confirm_page', $confirmation_id, $redirect_url );

	return $redirect_url;
}

/**
 * Format a merchant reference for fees sent to Qliro, either as a fee or a discount.
 *
 * @param string $fee_name The name of the fee to be formatted.
 *
 * @return string
 */
function qliro_one_format_fee_reference( $fee_name ) {
	$allowed_characters = "/[\p{L}\s(.)'\-_&,\/–+0-9:]/"; // Regex for the allowed characters in the merchant reference.
	// Limit the length of the merchant reference to 200 characters.
	$merchant_reference = mb_substr( $fee_name, 0, 200 );

	// Sanitize the reference.
	$merchant_reference = sanitize_title_with_dashes( $merchant_reference );

	// Match the allowed characters in the merchant reference and combine them into a single string.
	preg_match_all( $allowed_characters, $merchant_reference, $matches );
	$merchant_reference = implode( '', $matches[0] );

	return apply_filters( 'qliro_one_format_fee_reference', $merchant_reference, $fee_name );
}

/**
 * Get the billing country from the checkout, or the store base location if not set.
 *
 * @return string
 */
function qliro_one_get_billing_country() {
	$base_location = wc_get_base_location();
	return apply_filters( 'qliro_one_billing_country', WC()->checkout()->get_value( 'billing_country' ) ?? $base_location['country'] );
}

function qliro_one_has_country_changed() {
	$country_from_session  = WC()->session->get( 'qliro_one_billing_country' );
	$country_from_checkout = WC()->checkout()->get_value( 'billing_country' );

	if ( empty( $country_from_session ) || empty( $country_from_checkout ) ) {
		return false;
	}

	return $country_from_session !== $country_from_checkout;
}

/**
 * Ensure that a value is numeric. If the value is not numeric, it will attempt to convert it.
 * If the value is an empty value, it will be set to 0.
 * If the value cannot be converted to a numeric value, it will return the default value.
 *
 * @param mixed     $value The value to ensure is numeric.
 * @param float|int $default The default value to return if the value is not numeric and $throw_error is false. Default 0.
 *
 * @return float|int Returns the numeric value of the input, or the default value if the input is not numeric and cannot be converted.
 */
function qliro_ensure_numeric( $value, $default = 0 ) {
	if ( is_numeric( $value ) ) {
		return floatval( $value );
	}

	// If the value is empty, return 0 instead of default to reflect that the value is not set.
	if ( empty( $value ) ) {
		return 0;
	}

	// Try to convert the value to a numeric value.
	$converted_value = floatval( $value );

	if ( is_numeric( $converted_value ) ) {
		return $converted_value;
	}

	return $default; // Return the default value if the value is still not numeric.
}

/**
 * Check if Qliro One is enabled.
 *
 * Check if Qliro One is enabled in the settings, and if demo mode is enabled, check if the demo mode coupon is applied.
 *
 * @return bool
 */
function qliro_is_enabled_with_demo_check() {
	$settings   = get_option( 'woocommerce_qliro_one_settings', array() );
	$is_enabled = isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];

	// Only check for demo mode if we are on the checkout page, and not on the order received page or the pay for order page.
	if ( ! is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) ) {
		return $is_enabled;
	}

	if ( $is_enabled ) {

		$is_demomode = isset( $settings['demomode'] ) && 'yes' === $settings['demomode'];
		if ( $is_demomode ) {
			$demomode_coupon = isset( $settings['demomode_coupon'] ) ? $settings['demomode_coupon'] : '';

			// If we are not in demo mode, or the demo mode coupon is not set, return false.
			if ( empty( $demomode_coupon ) ) {
				return false;
			}

			// Check if the cart contains the demo mode coupon. If not, return false.
			$applied_coupons = WC()->cart->get_applied_coupons();
			if ( ! in_array( $demomode_coupon, $applied_coupons, true ) ) {
				return false;
			}
		}

		return true;
	}

	return false;
}


/**
 * Get all available tax rates in the store for a given location.
 *
 * If an order is provided, the tax rates will be determined using the billing country, state and postcode
 * associated with that order. If no order is provided, the tax rates will be determined
 * using the checkout data or the store's default country location.
 *
 * Example of a tax rate element in the returned array:
 * [
 *     'rate'     => 0,
 *     'label'    => '00',
 *     'shipping' => 'yes',
 *     'compound' => 'no',
 *     'tax_class'=> 'Zero Rate',
 * ]
 *
 * @param WC_Order|null $order The WC order. If provided, the tax rates will be limited to the order's billing country, state and postcode.
 * @return array An array of tax rates.
 */
function qliro_get_available_tax_rates( $order = null ) {
	$found_rates = array();

	// If an order is available, we'll limit the tax rates to the order's billing country and state.
	if ( ! empty( $order ) ) {
		$country = $order->get_billing_country();
		$args    = array(
			'country'  => $country,
			'state'    => $order->get_billing_state(),
			'postcode' => $order->get_billing_postcode(),
		);
	} else {
		$country = qliro_one_get_billing_country();
	}

	$args = wp_parse_args( $args, array( 'country' => $country ) );

	$tax_classes = WC_Tax::get_tax_classes();
	foreach ( $tax_classes as $tax_class ) {
		$args['tax_class'] = $tax_class;
		$found             = WC_TAX::find_rates( $args );
		if ( ! empty( $found ) ) {
			$found              = reset( $found );
			$found['tax_class'] = $tax_class;
			$found_rates[]      = $found;
		}
	}

	return $found_rates;
}

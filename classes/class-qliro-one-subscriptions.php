<?php
/**
 * Class to handle the integration with WooCommerce Subscriptions.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Subscriptions
 */
class Qliro_One_Subscriptions {
	public const GATEWAY_ID               = 'qliro_one';
	public const PENDING_PREAUTHORIZATION = self::GATEWAY_ID . '_pending_preauthorization';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_scheduled_subscription_payment_qliro_one', array( $this, 'process_scheduled_payment' ), 10, 2 );

		add_filter(
			'woocommerce_subscription_payment_method_to_display',
			array( $this, 'subscription_payment_method_title' ),
			10,
			2
		);
	}

	/**
	 * Change the payment method title for subscriptions to show the correct payment method.
	 *
	 * @hook woocommerce_subscription_payment_method_to_display
	 *
	 * @param string          $payment_method_to_display The payment method title to display.
	 * @param WC_Subscription $subscription The subscription object.
	 *
	 * @return string
	 */
	public function subscription_payment_method_title( $payment_method_to_display, $subscription ) {
		if ( 'qliro_one' !== $subscription->get_payment_method() ) {
			return $payment_method_to_display;
		}

		$parent         = $subscription->get_parent();
		$payment_method = $parent ? Qliro_One_Metabox::get_payment_method_name( $parent ) : $payment_method_to_display;
		return $payment_method;
	}

	/**
	 * Process subscription renewal.
	 *
	 * @param float    $amount_to_charge The amount to charge for the renewal.
	 * @param WC_Order $order The WooCommerce order that will be created as a result of the renewal.
	 *
	 * @return void
	 */
	public function process_scheduled_payment( $amount_to_charge, $order ) {
		// Get the order and the subscription objects.
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order->get_id() );

		foreach ( $subscriptions as $subscription ) {
			// See if we have a token stored on the subscription.
			$token_ids = $subscription->get_payment_tokens();
			if ( empty( $token_ids ) ) {
				$this->process_recurring_invoice_payment( $order, $subscription );
			} else {
				$this->process_recurring_card_payment( $order, $subscription, $token_ids );
			}
		}
	}

	/**
	 * Process recurring invoice payment.
	 *
	 * @param WC_Order        $order The order object.
	 * @param WC_Subscription $subscription The subscription object.
	 *
	 * @return void
	 */
	private function process_recurring_invoice_payment( $order, $subscription ) {
		$result = QLIRO_WC()->api->create_merchant_payment( $order->get_id() );

		// If the result is a WP_Error, fail the payment.
		if ( is_wp_error( $result ) ) {
			$subscription->payment_failed();
			$subscription->save();
			return;
		}

		// Set the required order meta for the renewal order.

		$transaction_id = $result['PaymentTransactions'][0]['PaymentTransactionId'];
		$order->add_meta_data( '_qliro_payment_transaction_id', $transaction_id, true );
		$order->add_meta_data( '_qliro_one_order_id', $result['OrderId'], true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'QLIRO_INVOICE', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', 'INVOICE', true );
		$order->add_meta_data( self::PENDING_PREAUTHORIZATION, time() );
		$order->set_transaction_id( $transaction_id );

		$note = sprintf(
			__( 'Subscription renewal is pending preauthorization via Qliro.', 'qliro-for-woocommerce' ),
		);
		$order->update_status( 'on-hold', $note );
	}

	/**
	 * Process recurring card payment.
	 *
	 * @param WC_Order        $order The order object.
	 * @param WC_Subscription $subscription The subscription object.
	 * @param int[]           $token_ids The payment token ids.
	 *
	 * @return void
	 */
	private function process_recurring_card_payment( $order, $subscription, $token_ids ) {
		// If there are multiple payment tokens, use the one thats default.
		foreach ( $token_ids as $token_id ) {
			$token = WC_Payment_Tokens::get( $token_id );

			if ( $token && $token->is_default() ) {
				break;
			}
		}

		if ( empty( $token ) ) {
			$message = __( 'The previously associated payment token for this subscription is no longer valid or available.', 'qliro-for-woocommerce' );

			$order->add_order_note( $message );
			$subscription->add_order_note( $message );
			$subscription->payment_failed_for_related_order();
			return;
		}

		$result = QLIRO_WC()->api->create_merchant_payment( $order->get_id(), $token->get_token() );

		// If the result is a WP_Error, fail the payment.
		if ( is_wp_error( $result ) ) {
			$message = sprintf(
				/* translators: %s: Error message from the Qliro API. */
				__( 'The recurring payment failed due to an error communicating with Qliro: %s', 'qliro-for-woocommerce' ),
				$result->get_error_message()
			);

			$order->add_order_note( $message );
			$subscription->add_order_note( $message );
			$subscription->payment_failed_for_related_order();
			return;
		}

		// Set the required order meta for the renewal order.
		$transaction_id = $result['PaymentTransactions'][0]['PaymentTransactionId'];
		$order->add_meta_data( '_qliro_payment_transaction_id', $transaction_id, true );
		$order->add_meta_data( '_qliro_one_order_id', $result['OrderId'], true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'CREDITCARDS', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', $token->get_card_type(), true );
		$order->set_transaction_id( $transaction_id );
		$order->add_meta_data( self::PENDING_PREAUTHORIZATION, time() );

		$note = sprintf(
			__( 'Subscription renewal is pending preauthorization via Qliro.', 'qliro-for-woocommerce' ),
		);
		$order->update_status( 'on-hold', $note );
	}

	/**
	 * Process the preauthorization for a subscription renewal order.
	 *
	 * @param int    $order_id The order ID of the renewal order.
	 * @param string $qliro_order_id The Qliro order ID associated with the renewal order.
	 *
	 * @return void
	 */
	public static function process_preauthorization( $order_id, $qliro_order_id ) {
		$renewal_order = wc_get_order( $order_id );
		if ( ! $renewal_order ) {
			Qliro_One_Logger::log( "[PREAUTHORIZATION]: Renewal order with ID #{$order_id}/#{$qliro_order_id} not found." );
			return;
		}

		// Remove the pending preauthorization meta and complete the payment.
		$renewal_order->delete_meta_data( self::PENDING_PREAUTHORIZATION );

		$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'any' ) );
		foreach ( $subscriptions as $subscription ) {
			$subscription->add_order_note(
				sprintf(
					/* translators: %s: Qliro order ID */
					__( 'Preauthorization for subscription renewal order was completed via Qliro. Qliro Order ID: %s', 'qliro-for-woocommerce' ),
					$qliro_order_id
				)
			);
			$subscription->save();
			$subscription->payment_complete( $qliro_order_id );
		}

		$renewal_order->add_order_note(
			sprintf(
				/* translators: %s: Qliro order ID */
				__( 'Preauthorization for this order was completed via Qliro. Qliro Order ID: %s', 'qliro-for-woocommerce' ),
				$qliro_order_id
			)
		);
		$renewal_order->payment_complete();
	}

	/**
	 * Check if the cart or order is a subscription of any type.
	 *
	 * @param WC_Order|null $order The WooCommerce order if available.
	 *
	 * @return bool
	 */
	public static function is_subscription( $order ) {
		if ( empty( $order ) ) {
			return self::cart_has_subscription();
		}

		return class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) );
	}

	/**
	 * Check if a cart contains a subscription.
	 *
	 * @return bool
	 */
	public static function cart_has_subscription() {
		if ( ! is_checkout() ) {
			return false;
		}

		return ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) ||
			( function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal() ) ||
			( function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) && wcs_cart_contains_failed_renewal_order_payment() ) ||
			( function_exists( 'wcs_cart_contains_resubscribe' ) && wcs_cart_contains_resubscribe() ) ||
			( function_exists( 'wcs_cart_contains_early_renewal' ) && wcs_cart_contains_early_renewal() ) ||
			( function_exists( 'wcs_cart_contains_switches' ) && wcs_cart_contains_switches() );
	}


	/**
	 * Check if the cart or order is a subscription of any type.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	public static function is_subscription_renewal( $order ) {
		if ( null !== $order && class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order, array( 'resubscribe', 'switch', 'renewal' ) ) ) {
			return true;
		}

		return false;
	}
}

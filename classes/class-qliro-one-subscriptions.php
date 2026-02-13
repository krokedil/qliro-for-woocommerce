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

		$parent = $subscription->get_parent();
		if ( $parent ) {
			$payment_method = Qliro_One_Metabox::get_payment_method_name( $parent );
		}

		return $payment_method;
	}

	/**
	 * Process subscription renewal.
	 *
	 * @param float    $amount_to_charge
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
		$order->add_meta_data( '_qliro_payment_transaction_id', $result['PaymentTransactions'][0]['PaymentTransactionId'], true );
		$order->add_meta_data( '_qliro_one_order_id', $result['OrderId'], true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'QLIRO_INVOICE', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', 'INVOICE', true );
		$order->add_order_note(
			sprintf(
				/* translators: %s: Order ID */
				__( 'Qliro recurring payment for order %s was successful.', 'qliro-for-woocommerce' ),
				$order->get_id()
			)
		);
		$order->save();

		// If the result is not a WP_Error, complete the payment.
		$subscription->payment_complete( $result['OrderId'] );
	}

	/**
	 * Process recurring card payment.
	 *
	 * @param WC_Order        $order The order object.
	 * @param WC_Subscription $subscription The subscription object.
	 * @param int[]           $token_ids The payment token ids
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

		$result = QLIRO_WC()->api->create_merchant_payment( $order->get_id(), $token->get_token() );

		// If the result is a WP_Error, fail the payment.
		if ( is_wp_error( $result ) ) {
			$subscription->payment_failed();
			$subscription->save();
			return;
		}

		// Set the required order meta for the renewal order.
		$order->add_meta_data( '_qliro_payment_transaction_id', $result['PaymentTransactions'][0]['PaymentTransactionId'], true );
		$order->add_meta_data( '_qliro_one_order_id', $result['OrderId'], true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'CREDITCARDS', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', $token->get_card_type(), true );
		$order->add_order_note(
			sprintf(
				/* translators: %s: Order ID */
				__( 'Qliro recurring payment for order %s was successful.', 'qliro-for-woocommerce' ),
				$order->get_id()
			)
		);
		$order->save();

		// If the result is not a WP_Error, complete the payment.
		$subscription->payment_complete( $result['OrderId'] );
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
		if ( $order !== null && class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order, array( 'resubscribe', 'switch', 'renewal' ) ) ) {
			return true;
		}

		return false;
	}
}

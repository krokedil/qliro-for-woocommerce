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
		$result = QOC_WC()->api->create_merchant_payment( $order->get_id() );

		// If the result is a WP_Error, fail the payment.
		if ( is_wp_error( $result ) ) {
			$subscription->payment_failed();
			$subscription->save();
			return;
		}

		// If the result is not a WP_Error, complete the payment.
		$subscription->payment_complete( $result['OrderId'] );

		// Set the required order meta for the renewal order.
		$order->add_meta_data( '_qliro_payment_transaction_id', $result['PaymentTransactions'][0]['PaymentTransactionId'], true );
		$order->add_meta_data( '_qliro_one_order_id', $result['OrderId'], true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'QLIRO_INVOICE', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', 'INVOICE', true );
		$order->add_order_note(
			sprintf(
				/* translators: %s: Order ID */
				__( 'Qliro One recurring payment for order %s was successful.', 'qliro-one-for-woocommerce' ),
				$order->get_id()
			)
		);
		$order->save();
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

		$result = QOC_WC()->api->create_merchant_payment( $order->get_id(), $token->get_token() );

		// If the result is a WP_Error, fail the payment.
		if ( is_wp_error( $result ) ) {
			$subscription->payment_failed();
			$subscription->save();
			return;
		}

		// If the result is not a WP_Error, complete the payment.
		$subscription->payment_complete( $result['OrderId'] );

		// Set the required order meta for the renewal order.
		$order->add_meta_data( '_qliro_payment_transaction_id', $result['PaymentTransactions'][0]['PaymentTransactionId'], true );
		$order->add_meta_data( '_qliro_one_order_id', $result['OrderId'], true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'CREDITCARDS', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', $token->get_card_type(), true );
		$order->add_order_note(
			sprintf(
				/* translators: %s: Order ID */
				__( 'Qliro One recurring payment for order %s was successful.', 'qliro-one-for-woocommerce' ),
				$order->get_id()
			)
		);
		$order->save();
	}

	/**
	 * Check if the cart or order is a subscription of any type.
	 *
	 * @param WC_Order|null $order The WooCommerce order if available.
	 *
	 * @return bool
	 */
	public static function is_subscription( $order ) {
		if ( $order === null && class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return true;
		}

		if ( $order !== null && class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) ) ) {
			return true;
		}

		return false;
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

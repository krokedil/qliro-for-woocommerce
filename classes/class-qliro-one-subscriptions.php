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
	 * Meta key holding the Qliro order id of an in-progress card registration.
	 *
	 * @var string
	 */
	public const SAVE_CARD_ORDER_ID_KEY = '_qliro_one_save_card_order_id';

	/**
	 * Meta key holding the merchant reference of an in-progress card registration.
	 *
	 * This is the reference the callback URL is signed for, so it is also how the push callback
	 * finds the subscription a registration belongs to.
	 *
	 * @var string
	 */
	public const SAVE_CARD_REFERENCE_KEY = '_qliro_one_save_card_merchant_reference';

	/**
	 * Meta key holding the time a card registration was started, while waiting for Qliro to push
	 * the registered card.
	 *
	 * @var string
	 */
	public const SAVE_CARD_PENDING_KEY = '_qliro_one_save_card_pending';

	/**
	 * Meta key holding the card form of an in-progress card registration, which Qliro returns only
	 * when the registration is created.
	 *
	 * @var string
	 */
	public const SAVE_CARD_FORM_KEY = '_qliro_one_save_card_form';

	/**
	 * How long a card registration is given before it is treated as never completed.
	 *
	 * Covers the customer filling in the form, including retrying within it, plus the delay before
	 * Qliro pushes the card.
	 *
	 * @var int
	 */
	public const SAVE_CARD_GRACE_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * How long a stored card form is shown again before a new registration is made instead.
	 *
	 * Kept well below the hour and a half after which the form Qliro returns expires.
	 *
	 * @var int
	 */
	public const SAVE_CARD_REUSE_SECONDS = 10 * MINUTE_IN_SECONDS;

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

		// On successful payment method change, the customer is redirected back to the subscription view page.
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'handle_redirect_from_change_payment_method' ), 5 );

		// WooCommerce Subscriptions renders its subscription receipt template on the order-pay endpoint, which is where we display the card form.
		add_action( 'woocommerce_receipt_' . self::GATEWAY_ID, array( __CLASS__, 'display_add_card_page' ) );
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

		$qliro_order_id = $result['OrderId'];
		$order->add_meta_data( '_qliro_payment_transaction_id', $result['PaymentTransactions'][0]['PaymentTransactionId'], true );
		$order->add_meta_data( '_qliro_one_order_id', $qliro_order_id, true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'QLIRO_INVOICE', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', 'INVOICE', true );
		$order->add_meta_data( self::PENDING_PREAUTHORIZATION, time(), true );
		$order->set_transaction_id( $qliro_order_id );

		$note = __( 'Renewal payment has been requested from Qliro and is awaiting preauthorization.', 'qliro-for-woocommerce' );

		$subscription->add_order_note( $note );
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
		$qliro_order_id = $result['OrderId'];
		$order->add_meta_data( '_qliro_payment_transaction_id', $result['PaymentTransactions'][0]['PaymentTransactionId'], true );
		$order->add_meta_data( '_qliro_one_order_id', $qliro_order_id, true );
		$order->add_meta_data( '_qliro_one_merchant_reference', $order->get_order_number(), true );
		$order->add_meta_data( 'qliro_one_payment_method_name', 'CREDITCARDS', true );
		$order->add_meta_data( 'qliro_one_payment_method_subtype_code', $token->get_card_type(), true );
		$order->set_transaction_id( $qliro_order_id );
		$order->add_meta_data( self::PENDING_PREAUTHORIZATION, time(), true );

		$note = __( 'Renewal payment has been requested from Qliro and is awaiting preauthorization.', 'qliro-for-woocommerce' );

		$subscription->add_order_note( $note );
		$order->update_status( 'on-hold', $note );
	}

	/**
	 * Process the preauthorization for a subscription renewal order.
	 *
	 * @param WC_Order $renewal_order The renewal order object.
	 * @param string   $qliro_order_id The Qliro order ID associated with the renewal order.
	 *
	 * @return void
	 */
	public static function process_preauthorization( $renewal_order, $qliro_order_id ) {
		// Remove the pending preauthorization meta and complete the payment.
		$renewal_order->delete_meta_data( self::PENDING_PREAUTHORIZATION );

		$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'renewal' ) );
		foreach ( $subscriptions as $subscription ) {
			$subscription->add_order_note(
				sprintf(
					/* translators: %s: Qliro order ID */
					__( 'Preauthorization for subscription renewal order was completed via Qliro. Qliro Order ID: %s', 'qliro-for-woocommerce' ),
					$qliro_order_id
				)
			);
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

	/**
	 * Whether the current request is the Qliro add card page for the given subscription.
	 *
	 * @param int $subscription_id The subscription ID the page is expected to belong to. Optional.
	 * @return bool
	 */
	public static function is_add_card_page( $subscription_id = 0 ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}

		if ( 'subscription' !== filter_input( INPUT_GET, 'qliro_redirect', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			return false;
		}

		if ( empty( $subscription_id ) ) {
			return true;
		}

		return absint( get_query_var( 'order-pay' ) ) === absint( $subscription_id );
	}

	/**
	 * Display the Qliro card form on the add card page.
	 *
	 * @param int $order_id The subscription ID.
	 * @return void
	 */
	public static function display_add_card_page( $order_id ) {
		$subscription = self::get_add_card_subscription( $order_id );
		if ( empty( $subscription ) ) {
			return;
		}

		// Registering again would leave the previous registration unable to find its way back here.
		$snippet = self::get_ongoing_card_registration( $subscription );

		if ( empty( $snippet ) ) {
			$snippet = self::create_card_registration( $subscription );
		}

		if ( is_wp_error( $snippet ) ) {
			wc_print_notice(
				sprintf(
					/* translators: %s: Error message from the Qliro API. */
					esc_html__( 'Could not load the card form. Reason: %s', 'qliro-for-woocommerce' ),
					esc_html( $snippet->get_error_message() )
				),
				'error'
			);
			return;
		}

		echo $snippet; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a HTML snippet that is generated by Qliro and needs to be outputted as is.
	}

	/**
	 * Get the subscription the current add card request belongs to, if the request is valid.
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return WC_Subscription|null
	 */
	private static function get_add_card_subscription( $subscription_id ) {
		if ( ! self::is_add_card_page( $subscription_id ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return null;
		}

		$subscription = wcs_get_subscription( $subscription_id );
		if ( empty( $subscription ) || self::GATEWAY_ID !== $subscription->get_payment_method() ) {
			return null;
		}

		if ( ! current_user_can( 'edit_shop_subscription_payment_method', $subscription->get_id() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Registered by WooCommerce Subscriptions.
			return null;
		}

		return $subscription;
	}

	/**
	 * Register a new card with Qliro and return the card form to display to the customer.
	 *
	 * @param WC_Subscription $subscription The subscription to register a card for.
	 * @return string|WP_Error The HTML snippet, or a WP_Error if the request failed.
	 */
	public static function create_card_registration( $subscription ) {
		$request  = new Qliro_One_Request_Save_Credit_Card( array( 'order_id' => $subscription->get_id() ) );
		$response = $request->request();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['OrderId'] ) || empty( $response['OrderForRegistrationTokenHtmlSnippet'] ) ) {
			return new WP_Error( 'qliro_missing_card_form', __( 'Qliro did not return a card form.', 'qliro-for-woocommerce' ) );
		}

		// The card is registered as a separate Qliro order, so store its id and the reference the
		// push URL was signed for. The reference is what the callback resolves this subscription by.
		$subscription->update_meta_data( self::SAVE_CARD_ORDER_ID_KEY, $response['OrderId'] );
		$subscription->update_meta_data( self::SAVE_CARD_REFERENCE_KEY, $request->get_merchant_reference() );
		$subscription->update_meta_data( self::SAVE_CARD_PENDING_KEY, strval( time() ) );
		$subscription->update_meta_data( self::SAVE_CARD_FORM_KEY, $response['OrderForRegistrationTokenHtmlSnippet'] );
		$subscription->save();

		return $response['OrderForRegistrationTokenHtmlSnippet'];
	}

	/**
	 * Get the card form of a registration the customer can still complete, if there is one.
	 *
	 * A registration that has already been resolved, either way, must not be shown again.
	 *
	 * @param WC_Subscription $subscription The subscription to look for a registration on.
	 * @return string The HTML snippet, or an empty string if there is nothing to show again.
	 */
	private static function get_ongoing_card_registration( $subscription ) {
		$started = $subscription->get_meta( self::SAVE_CARD_PENDING_KEY );
		$snippet = $subscription->get_meta( self::SAVE_CARD_FORM_KEY );

		if ( empty( $started ) || ! is_numeric( $started ) || empty( $snippet ) ) {
			return '';
		}

		if ( time() - intval( $started ) >= self::SAVE_CARD_REUSE_SECONDS ) {
			return '';
		}

		return $snippet;
	}

	/**
	 * Mark a card registration as no longer waiting for its card, discarding its form.
	 *
	 * Leaves saving to the caller.
	 *
	 * @param WC_Subscription $subscription The subscription the registration belongs to.
	 * @return void
	 */
	private static function clear_pending_card_registration( $subscription ) {
		$subscription->delete_meta_data( self::SAVE_CARD_PENDING_KEY );
		$subscription->delete_meta_data( self::SAVE_CARD_FORM_KEY );
	}

	/**
	 * Builds the URL to the page the customer adds a payment card from.
	 *
	 * @param WC_Subscription $subscription WooCommerce subscription instance used to generate the checkout payment URL.
	 * @return string URL to the order-pay endpoint with the Qliro subscription redirect flag.
	 */
	public static function get_add_card_page_url( $subscription ) {
		// Passing true omits 'pay_for_order', which makes the order-pay endpoint render the receipt template instead of the pay form.
		return add_query_arg(
			array(
				'qliro_redirect' => 'subscription',
			),
			$subscription->get_checkout_payment_url( true )
		);
	}

	/**
	 * Builds the redirect URL to the confirmation page used to confirm a card change for a subscription.
	 *
	 * @param WC_Subscription $subscription WooCommerce subscription instance used to generate the confirmation URL.
	 * @return string Fully-qualified URL with the Qliro subscription redirect flag.
	 */
	public static function get_add_card_confirmation_url( $subscription ) {
		return add_query_arg(
			array(
				'qliro_redirect' => 'subscription',
			),
			self::absolutize_url( $subscription->get_view_order_url() )
		);
	}

	/**
	 * Ensure a URL sent to Qliro is fully-qualified.
	 *
	 * Qliro resolves a relative URL against its own domain, so a site that makes its
	 * URLs relative would send the customer to Qliro instead of back to the store.
	 *
	 * @param string $url The URL to absolutize.
	 * @return string
	 */
	public static function absolutize_url( $url ) {
		if ( 0 === strpos( $url, 'http' ) ) {
			return $url;
		}

		return untrailingslashit( home_url() ) . '/' . ltrim( $url, '/' );
	}

	/**
	 * Handle the redirect from Qliro after the customer added a card.
	 *
	 * The card itself is delivered asynchronously to the save card callback, so this only
	 * reports the outcome to the customer.
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return void
	 */
	public function handle_redirect_from_change_payment_method( $subscription_id ) {
		// We use the 'qliro_redirect' query var to determine if we are redirected from Qliro after changing payment method, otherwise the customer is viewing a subscription.
		$qliro_redirect = filter_input( INPUT_GET, 'qliro_redirect', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'subscription' !== $qliro_redirect || ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );
		if ( empty( $subscription ) || self::GATEWAY_ID !== $subscription->get_payment_method() ) {
			return;
		}

		$qliro_order_id = $subscription->get_meta( self::SAVE_CARD_ORDER_ID_KEY );
		if ( empty( $qliro_order_id ) ) {
			return;
		}

		$started = $subscription->get_meta( self::SAVE_CARD_PENDING_KEY );

		// The callback beat us to it, and has already saved the card.
		if ( empty( $started ) ) {
			wc_print_notice( __( 'Your new payment card has been saved, and will be used for upcoming renewals.', 'qliro-for-woocommerce' ), 'success' );
			return;
		}

		// The customer may still be working in the card form, retrying inside it after a rejected
		// card, so a registration is only judged once it has had time to finish either way.
		if ( is_numeric( $started ) && time() - intval( $started ) < self::SAVE_CARD_GRACE_SECONDS ) {
			wc_print_notice( __( 'Your new payment card is being registered. It will be used for upcoming renewals as soon as Qliro has confirmed it.', 'qliro-for-woocommerce' ), 'notice' );
			return;
		}

		// Qliro reports no failure, so a registration the customer never finished is otherwise
		// indistinguishable from one whose push has not arrived, and the customer would be told
		// their card is on its way indefinitely.
		if ( ! self::customer_completed_registration( $qliro_order_id ) ) {
			self::clear_pending_card_registration( $subscription );
			$subscription->save();

			wc_print_notice(
				sprintf(
					/* translators: %1$s: Opening anchor tag to the add card page, %2$s: Closing anchor tag. */
					__( 'Your new payment card was not registered, so your subscription still uses the previous one. %1$sTry adding the card again%2$s.', 'qliro-for-woocommerce' ),
					'<a href="' . esc_url( self::get_add_card_page_url( $subscription ) ) . '">',
					'</a>'
				),
				'error'
			);
			return;
		}

		wc_print_notice( __( 'Your new payment card is being registered. It will be used for upcoming renewals as soon as Qliro has confirmed it.', 'qliro-for-woocommerce' ), 'notice' );
	}

	/**
	 * Whether the customer got to the end of a card registration.
	 *
	 * Asks Qliro rather than reading the saved card, because token registration is a separate
	 * operation that can lag behind the customer completing the form. A completed checkout with no
	 * card yet is still on its way, while one left in progress never will be.
	 *
	 * @param string $qliro_order_id The Qliro order id of the card registration.
	 * @return bool True when the customer completed it, or when Qliro could not be reached to ask.
	 */
	private static function customer_completed_registration( $qliro_order_id ) {
		$qliro_order = QLIRO_WC()->api->get_qliro_one_order( $qliro_order_id );

		if ( is_wp_error( $qliro_order ) ) {
			Qliro_One_Logger::log( "[ADD CARD]: Could not ask Qliro about card registration #{$qliro_order_id}: " . $qliro_order->get_error_message() );
			// Our own failure is no reason to tell the customer their card was rejected.
			return true;
		}

		return 'Completed' === ( $qliro_order['CustomerCheckoutStatus'] ?? '' );
	}

	/**
	 * Save a card registered with Qliro as the payment token to use for a subscription.
	 *
	 * Replaces any token already set on the subscription, so renewals use the new card.
	 *
	 * @param WC_Subscription $subscription The subscription to save the card for.
	 * @param array           $saved_card The MerchantSavedCreditCard as returned by the Qliro API.
	 * @return WC_Payment_Token_CC|WP_Error
	 */
	public static function save_card_to_subscription( $subscription, $saved_card ) {
		if ( empty( $saved_card['Id'] ) ) {
			return new WP_Error( 'qliro_missing_card_id', __( 'Qliro did not include a card id.', 'qliro-for-woocommerce' ) );
		}

		$customer_id = $subscription->get_customer_id();
		$token       = null;

		// Qliro may push the same card more than once, so reuse the token if we already have it.
		foreach ( WC_Payment_Tokens::get_customer_tokens( $customer_id, self::GATEWAY_ID ) as $existing_token ) {
			if ( $existing_token instanceof WC_Payment_Token_CC && $existing_token->get_token() === $saved_card['Id'] ) {
				$token = $existing_token;
				break;
			}
		}

		if ( empty( $token ) ) {
			$token = new WC_Payment_Token_CC();
			$token->set_gateway_id( self::GATEWAY_ID );
			$token->set_token( $saved_card['Id'] );
			$token->set_user_id( $customer_id );
			$token->set_last4( $saved_card['Last4Digits'] ?? '' );
			// Pad the month to ensure its always 2 digits.
			$token->set_expiry_month( str_pad( strval( $saved_card['ExpiryMonth'] ?? '' ), 2, '0', STR_PAD_LEFT ) );
			$token->set_expiry_year( self::normalize_expiry_year( $saved_card['ExpiryYear'] ?? '' ) );
			$token->set_card_type( strtolower( $saved_card['BrandName'] ?? '' ) );
			$token->save();
		}

		// Renewals pick the default token, so the card the customer just added has to become it.
		WC_Payment_Tokens::set_users_default( $customer_id, $token->get_id() );

		// Replace rather than append, so a renewal can not fall back to the card that was just changed away from.
		$subscription->get_data_store()->update_payment_token_ids( $subscription, array( $token->get_id() ) );
		self::clear_pending_card_registration( $subscription );
		$subscription->save();

		$subscription->add_order_note(
			sprintf(
				/* translators: 1: Card brand, 2: Last four digits of the card. */
				__( 'The payment card for this subscription was changed by the customer to %1$s ending in %2$s.', 'qliro-for-woocommerce' ),
				$token->get_card_type(),
				$token->get_last4()
			)
		);

		return $token;
	}

	/**
	 * Normalise a card expiry year to four digits.
	 *
	 * Qliro's documentation shows a two digit year while the API has been observed returning four.
	 * WooCommerce stores and displays whatever it is given, so a two digit value would show the card
	 * as expiring in the year 24.
	 *
	 * @param string|int $year The expiry year as Qliro gave it.
	 * @return string
	 */
	public static function normalize_expiry_year( $year ) {
		$year = trim( strval( $year ) );

		if ( 2 !== strlen( $year ) || ! ctype_digit( $year ) ) {
			return $year;
		}

		return substr( gmdate( 'Y' ), 0, 2 ) . $year;
	}

	/**
	 * Get the subscription an in-progress card registration belongs to.
	 *
	 * The reference is the value the push URL was signed for, so finding a subscription by it is
	 * what ties an authenticated callback to a registration we started.
	 *
	 * @param string $reference The merchant reference of the card registration.
	 * @return WC_Subscription|null
	 */
	public static function get_subscription_by_save_card_reference( $reference ) {
		if ( empty( $reference ) ) {
			return null;
		}

		$subscriptions = wc_get_orders(
			array(
				'type'         => 'shop_subscription',
				// Subscription statuses are not order statuses, which the query defaults to.
				'status'       => 'any',
				'limit'        => 1,
				'meta_key'     => self::SAVE_CARD_REFERENCE_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => $reference, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => '=',
			)
		);

		$subscription = reset( $subscriptions );
		return empty( $subscription ) ? null : $subscription;
	}
}

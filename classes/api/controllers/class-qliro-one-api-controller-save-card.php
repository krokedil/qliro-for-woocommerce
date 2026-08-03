<?php
/**
 * The controller to handle the save card callback from Qliro.
 *
 * @package Qliro_One_For_WooCommerce/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_API_Controller_Save_Card
 */
class Qliro_One_API_Controller_Save_Card extends Qliro_One_API_Controller_Base {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'callback';

	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the callback route for the controller.
		register_rest_route(
			$this->namespace,
			$this->get_request_path() . '/save-card',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_card' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);
	}

	/**
	 * Verify that the callback is authenticated. Used as the permission callback.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return true|WP_Error
	 */
	public function verify_request( $request ) {
		return Qliro_One_Callback_Auth::verify_request( $request );
	}

	/**
	 * Handle the save card callback.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function save_card( $request ) {
		$body = $request->get_json_params();

		Qliro_One_Logger::log( '[SAVE CARD]: Received save card callback. Received data: ' . wp_json_encode( $body ) );

		// Identify the order from the signed reference in the callback URL rather than the request body.
		$reference = $request->get_param( Qliro_One_Callback_Auth::REF_PARAM );
		if ( ! empty( $reference ) ) {
			$order = qliro_get_order_by_confirmation_id( $reference );
		} else {
			// Grace period: legacy callbacks arrive without a signed reference, so fall back to the body order id.
			$order = qliro_get_order_by_qliro_id( $body['OrderId'] ?? '' );
		}

		// If we did not get an order, return an error, and Qliro will try again later.
		if ( empty( $order ) ) {
			Qliro_One_Logger::log( '[SAVE CARD]: No matching order found in WooCommerce for the save card callback.' );
			return new WP_REST_Response( array( 'error' => 'Order not found in WooCommerce' ), 404 );
		}

		$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );

		// Defence in depth: the order resolved from the signed reference should match the order id in the body.
		if ( ! empty( $body['OrderId'] ) && strval( $body['OrderId'] ) !== strval( $qliro_order_id ) ) {
			Qliro_One_Logger::log( "[SAVE CARD]: Rejecting save card callback: body order id #{$body['OrderId']} does not match the order resolved from the callback reference (#{$qliro_order_id})." );
			return new WP_REST_Response( array( 'error' => 'Order id mismatch' ), 403 );
		}

		// Re-fetch the order from Qliro and source the saved card details from the API rather than the request body.
		$qliro_order = QLIRO_WC()->api->get_qliro_one_order( $qliro_order_id );
		if ( is_wp_error( $qliro_order ) ) {
			Qliro_One_Logger::log( "[SAVE CARD]: Could not retrieve Qliro order #{$qliro_order_id} to verify the save card callback: " . $qliro_order->get_error_message() );
			return new WP_REST_Response( array( 'error' => 'Could not verify order with Qliro' ), 503 );
		}

		$saved_card = $qliro_order['MerchantSavedCreditCard'] ?? array();
		$card_id    = $saved_card['Id'] ?? '';

		// If Qliro has no saved card on the order yet, ask Qliro to retry later instead of trusting the callback body.
		if ( empty( $card_id ) ) {
			Qliro_One_Logger::log( "[SAVE CARD]: No saved card available on Qliro order #{$qliro_order_id} yet. Asking Qliro to retry." );
			return new WP_REST_Response( array( 'error' => 'No saved card available on the Qliro order' ), 503 );
		}

		// Log if the card id in the body does not match the one returned by the API, as a tamper signal.
		if ( ! empty( $body['Id'] ) && $body['Id'] !== $card_id ) {
			Qliro_One_Logger::log( "[SAVE CARD]: Card id in the callback body ({$body['Id']}) does not match the saved card on the Qliro order ({$card_id}) for order #{$qliro_order_id}." );
		}

		// Get the orders subscription.
		$subscriptions = wcs_get_subscriptions_for_order( $order );

		// For each subscription, save the card as a payment token.
		foreach ( $subscriptions as $subscription ) {
			// First check if the customer already has any tokens.
			$customer_id = $subscription->get_customer_id();
			$tokens      = WC_Payment_Tokens::get_customer_tokens( $customer_id, 'qliro_one' );

			// If the customer already has a token, check if any of the tokens match the card we are trying to save.
			foreach ( $tokens as $existing_token ) {
				$existing_token_id = $existing_token->get_token();
				// If the token already exists, return a success response.
				if ( $existing_token_id === $card_id ) {
					Qliro_One_Logger::log( "[SAVE CARD]: Card already exists for token id: {$existing_token_id}. Adding to existing subscription." );
					// If its set, and the card already exists, save it to the subscription and return a success response.
					$subscription->add_payment_token( $existing_token );
					$subscription->save();

					return $this->success_response();
				}
			}

			// Create a token for the card using the data fetched from the Qliro API.
			$token = new WC_Payment_Token_CC();
			$token->set_gateway_id( 'qliro_one' );
			$token->set_token( $card_id );
			$token->set_last4( $saved_card['Last4Digits'] ?? '' );
			// Pad the month to ensure its always 2 digits.
			$token->set_expiry_month( str_pad( strval( $saved_card['ExpiryMonth'] ?? '' ), 2, '0', STR_PAD_LEFT ) );
			$token->set_expiry_year( strval( $saved_card['ExpiryYear'] ?? '' ) );
			$token->set_card_type( $saved_card['BrandName'] ?? '' );
			$token->set_user_id( $subscription->get_customer_id() );

			// Save the token.
			$token->save();

			// Add the token to the subscription.
			$subscription->add_payment_token( $token );
			$subscription->save();
		}

		$subscription_ids = wp_list_pluck( $subscriptions, 'id' );
		Qliro_One_Logger::log( "[SAVE CARD]: Successfully saved card #{$card_id} for Qliro order id #{$qliro_order_id} to subscriptions: " . implode( ', ', $subscription_ids ) );
		return $this->success_response();
	}

	/**
	 * Return a successful response.
	 *
	 * @return WP_REST_Response
	 */
	public function success_response() {
		$response_body = array(
			'CallbackResponse' => 'received',
		);

		return new WP_REST_Response( $response_body, 200 );
	}
}

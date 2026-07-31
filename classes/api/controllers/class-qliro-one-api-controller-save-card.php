<?php
/**
 * The controller to handle the save card callback from Qliro.
 *
 * @package Avarda_Checkout/Classes/API/Controllers
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
				'permission_callback' => array( $this, 'has_confirmation_id' ),
			)
		);
	}

	/**
	 * Whether the request carries a confirmation id at all.
	 *
	 * Which registration the id belongs to is settled in the callback, by looking one up with it.
	 * This only keeps a request without an id from getting that far.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	public function has_confirmation_id( $request ) {
		return ! empty( $this->get_confirmation_id( $request ) );
	}

	/**
	 * Get the confirmation id from the request.
	 *
	 * Only the query string is read. The request body is attacker controlled, and must never be
	 * able to satisfy the check.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return string
	 */
	private function get_confirmation_id( $request ) {
		$query_params = $request->get_query_params();

		return sanitize_text_field( $query_params['qliro_one_confirm_id'] ?? '' );
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

		// Everything is looked up by the confirmation id from the push URL, never by the Qliro order
		// id in the body. Qliro order ids are short sequential integers, so a caller can guess one,
		// but not the confirmation id that decides which registration a callback belongs to.
		$confirmation_id = $this->get_confirmation_id( $request );

		// A card the customer adds from their account is registered as its own Qliro order, tied to the subscription instead of to an order.
		$subscription = Qliro_One_Subscriptions::get_subscription_by_save_card_confirmation_id( $confirmation_id );
		if ( ! empty( $subscription ) ) {
			$result = Qliro_One_Subscriptions::save_card_to_subscription( $subscription, $body );

			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
			}

			return $this->success_response();
		}

		// The checkout flow reuses the confirmation id the order was created with, the same secret its other push callbacks are found by.
		$order = qliro_get_order_by_confirmation_id( $confirmation_id );

		// If we did not get an order, return an error, and Qliro will try again later.
		if ( empty( $order ) ) {
			return new WP_REST_Response( array( 'error' => 'Order not found in WooCommerce' ), 404 );
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
				if ( $existing_token_id === $body['Id'] ) {
					// If its set, and the card already exists, save it to the subscription and return a success response.
					$subscription->add_payment_token( $existing_token );
					$subscription->save();

					return $this->success_response();
				}
			}

			// Create a token for the card.
			$token = new WC_Payment_Token_CC();
			$token->set_gateway_id( 'qliro_one' );
			$token->set_token( $body['Id'] );
			$token->set_last4( $body['CardLast4Digits'] );
			// Pad the month to ensure its always 2 digits.
			$token->set_expiry_month( str_pad( $body['CardExpiryMonth'], 2, '0', STR_PAD_LEFT ) );
			$token->set_expiry_year( $body['CardExpiryYear'] );
			$token->set_card_type( $body['CardBrandName'] );
			$token->set_user_id( $subscription->get_customer_id() );

			// Save the token.
			$token->save();

			// Add the token to the order.
			$subscription->add_payment_token( $token );
			$subscription->save();
		}

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

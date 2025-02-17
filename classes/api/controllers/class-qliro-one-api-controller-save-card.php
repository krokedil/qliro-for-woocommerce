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
				'permission_callback' => '__return_true',
			)
		);
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

		// Get the Qliro order id.
		$qliro_order_id = $body['OrderId'];

		// Get the WooCommerce order by the Qliro order id.
		$order = qoc_get_order_by_qliro_id( $qliro_order_id );

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

<?php
/**
 * The controller to handle the notifications callbacks from Qliro.
 *
 * @package Avarda_Checkout/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_API_Controller_Notifications
 */
class Qliro_One_API_Controller_Notifications extends Qliro_One_API_Controller_Base {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'callback';

	/**
	 * The provider for the notifications.
	 *
	 * @var Qliro_One_Notifications_Provider
	 */
	protected $provider;

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Include the notifications provider class and create an instance of it.
		include_once QLIRO_WC_PLUGIN_PATH . '/classes/api/notifications/class-qliro-one-notifications-provider.php';
		$this->provider = new Qliro_One_Notifications_Provider();
	}

	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the callback route for the controller.
		register_rest_route(
			$this->namespace,
			$this->get_request_path() . '/notifications',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_notification' ),
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
	 * Verify that the authenticated token was issued for the order the request body names.
	 *
	 * @param WP_REST_Request $request        The request object.
	 * @param WC_Order|null   $order          The order resolved from the request body.
	 * @param string          $qliro_order_id The Qliro order id from the request body, used for logging.
	 *
	 * @return bool True when the notification may be dispatched.
	 */
	protected function token_issued_for_order( $request, $order, $qliro_order_id ) {
		// Nothing to target, and the order may legitimately not exist yet during checkout.
		if ( null === $order ) {
			return true;
		}

		$reference = $request->get_param( Qliro_One_Callback_Auth::REF_PARAM );

		if ( Qliro_One_Callback_Auth::reference_belongs_to_order( $reference, $order ) ) {
			return true;
		}

		Qliro_One_Logger::log( "[CALLBACK AUTH]: Rejected a notification for Qliro order {$qliro_order_id} because its token was not issued for that order. It can be allowed via the 'qliro_one_allow_unauthenticated_callbacks' filter." );

		/** This filter is documented in classes/api/class-qliro-one-callback-auth.php */
		return (bool) apply_filters( 'qliro_one_allow_unauthenticated_callbacks', false, $request );
	}

	/**
	 * Handle the save card callback.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_notification( $request ) {
		try {
			$body = $request->get_json_params();

			// Get the Qliro order id.
			$qliro_order_id = $body['OrderId'];
			$payload        = $body['Payload'] ?? array();

			// Get the event type and provider from the body, ensuring they are lowercase for consistency.
			$event_type = strtolower( $body['EventType'] ?? '' );
			$provider   = strtolower( $body['Provider'] ?? '' );

			// Get the WooCommerce order by the Qliro order id.
			$order = qliro_get_order_by_qliro_id( $qliro_order_id );

			// If the order is returned as 0, set it to null.
			if ( 0 === $order ) {
				$order = null;
			}

			// The order comes from the request body, so confirm the token was issued for it before dispatching anything.
			if ( ! $this->token_issued_for_order( $request, $order, $qliro_order_id ) ) {
				return new WP_REST_Response( array( 'error' => 'Callback token was not issued for this order.' ), 401 );
			}

			// Get the handler for the event type and provider.
			$handler = $this->provider->get_handler( $event_type, $provider );

			if ( null === $handler ) {
				do_action( "qliro_notification_{$event_type}_{$provider}", $qliro_order_id, $body, $order ); // Trigger the action to allow other plugins to handle the event.
				return $this->success_response(); // Return a success if nothing has thrown an exception.
			}

			$handler->handle_notification( $payload, $order );

			// Trigger an action to let other plugins know that a change has been made, and allow them to take action if needed.
			do_action( "qliro_notification_{$event_type}_{$provider}", $qliro_order_id, $body, $order );

			return $this->success_response();
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
		}
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

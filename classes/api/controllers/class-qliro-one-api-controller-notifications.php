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
	public function handle_notification( $request ) {
		try {
			$body = $request->get_json_params();

			// Get the Qliro order id.
			$qliro_order_id = $body['OrderId'];
			$payload 	    = $body['Payload'] ?? array();

			// Get the event type and provider from the body, ensuring they are lowercase for consistency.
			$event_type = strtolower( $body['EventType'] ?? '' );
			$provider   = strtolower( $body['Provider'] ?? '' );

			// Get the WooCommerce order by the Qliro order id.
			$order = qoc_get_order_by_qliro_id( $qliro_order_id );

			// If the order is returned as 0, set it to null.
			if ( 0 === $order ) {
				$order = null;
			}

			// Get the handler for the event type and provider.
			$handler = $this->provider->get_handler( $event_type, $provider );;

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

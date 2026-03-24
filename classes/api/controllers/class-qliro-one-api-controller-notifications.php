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

			// Get the handler for the event type and provider.
			$handler = $this->provider->get_handler( $event_type, $provider );

			if ( null === $handler ) {
				do_action( "qliro_notification_{$event_type}_{$provider}", $qliro_order_id, $body, $order ); // Trigger the action to allow other plugins to handle the event.
				return $this->success_response(); // Return a success if nothing has thrown an exception.
			}

			$handler->handle_notification( $payload, $order );

			// Store loyalty member information if applicable.
			$this->maybe_store_loyalty_meta( $event_type, $payload, $order );

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

	/**
	 * Store loyalty information from callback payload on the order and customer.
	 *
	 * @param string        $event_type The event type in lowercase.
	 * @param array         $payload    The callback payload.
	 * @param WC_Order|null $order      The WooCommerce order, if available.
	 *
	 * @return void
	 */
	private function maybe_store_loyalty_meta( $event_type, $payload, $order ) {

		if ( ! in_array( $event_type, array( 'loyalty_provider_member_create', 'loyalty_provider_member_update' ), true ) ) {
			return;
		}

		if ( ! $order || empty( $payload ) ) {
			return;
		}

		$loyalty = $payload['loyalty'] ?? array();

		// Store loyalty information as order meta.
		$order->update_meta_data( '_qliro_loyalty_id', $loyalty['id'] ?? null );
		$order->update_meta_data( '_qliro_loyalty_provider', $loyalty['provider'] ?? null );
		$order->update_meta_data( '_qliro_loyalty_is_member', $loyalty['isMember'] ?? null );
		$order->save_meta_data();

		// Maybe store loyalty information as user meta.
		if ( $order->get_user_id() ) {
			update_user_meta( $order->get_user_id(), '_qliro_loyalty_id', $loyalty['id'] ?? null );
			update_user_meta( $order->get_user_id(), '_qliro_loyalty_provider', $loyalty['provider'] ?? null );
			update_user_meta( $order->get_user_id(), '_qliro_loyalty_is_member', $loyalty['isMember'] ?? null );
		}
	}
}

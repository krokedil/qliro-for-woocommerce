<?php
/**
 * Class for handling the Ingrid shipping provider notification from Qliro.
 *
 * @package Qliro_One_Notifications/Classes/API/Notifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Shipping_Provider_Ingrid
 */
class Qliro_One_Notifications_Ingrid_Shipping extends Qliro_One_Notifications {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'shipping_provider_update';

	/**
	 * The provider for the notification.
	 *
	 * @var string
	 */
	protected $provider = 'ingrid';

	/**
	 * Handle the notification callback.
	 *
	 * @param array         $payload The payload from the notification.
	 * @param WC_Order|null $order   The order object, if available.
	 *
	 * @return void
	 * @throws WP_Exception If the notification cannot be handled.
	 */
	public function handle_notification( $payload, $order = null ) {
		// If we did not get an order, throw an exception.
		if ( null === $order ) {
			throw new WP_Exception( 'Order not found in WooCommerce.', 404 );
		}

		$session         = $payload['session'] ?? throw new WP_Exception( 'Session data is missing from the payload.', 401 );
		$delivery_groups = $session['delivery_groups'] ?? throw new WP_Exception( 'Delivery groups data is missing from the session data.', 401 );

		// From the first delivery group, get the tos_id.
		if ( empty( $delivery_groups ) || ! isset( $delivery_groups[0]['tos_id'] ) ) {
			throw new WP_Exception( 'Delivery groups data is missing or invalid.', 401 );
		}

		$tos_id = $delivery_groups[0]['tos_id'];

		// Update the order meta with the tos_id.
		$order->update_meta_data( 'ingrid_tos_id', $tos_id );
		$order->save_meta_data();
	}
}

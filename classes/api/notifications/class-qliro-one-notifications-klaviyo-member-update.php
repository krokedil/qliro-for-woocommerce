<?php
/**
 * Class for handling the Klaviyo loyalty provider member update notification from Qliro.
 *
 * @package Qliro_One_Notifications/Classes/API/Notifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Notifications_Klaviyo_Member_Update
 */
class Qliro_One_Notifications_Klaviyo_Member_Update extends Qliro_One_Notifications {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'loyalty_provider_member_update';

	/**
	 * The provider for the notification.
	 *
	 * @var string
	 */
	protected $provider = 'klaviyo';

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

		if ( ! isset( $payload['session'] ) ) {
			throw new WP_Exception( 'Session data is missing from the payload.', 401 );
		}
		$session = $payload['session'];
	}
}

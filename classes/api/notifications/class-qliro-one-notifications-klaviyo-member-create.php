<?php
/**
 * Class for handling the Klaviyo loyalty provider member create notification from Qliro.
 *
 * @package Qliro_One_Notifications/Classes/API/Notifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Notifications_Klaviyo_Member_Create
 */
class Qliro_One_Notifications_Klaviyo_Member_Create extends Qliro_One_Notifications {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'loyalty_provider_member_create';

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

		if ( ! isset( $payload['data'] ) ) {
			throw new WP_Exception( 'Data is missing from the payload.', 401 );
		}

		$loyalty = $payload['data'] ?? array();

		// Store loyalty information as order meta.
		$order->update_meta_data( '_qliro_loyalty_id', $loyalty['id'] ?? null );
		$order->update_meta_data( '_qliro_loyalty_provider', 'klaviyo' );
		$order->save_meta_data();

		// Maybe store loyalty information as user meta.
		if ( $order->get_user_id() ) {
			update_user_meta( $order->get_user_id(), '_qliro_loyalty_id', $loyalty['id'] ?? null );
			update_user_meta( $order->get_user_id(), '_qliro_loyalty_provider', 'klaviyo' );
		}
	}
}

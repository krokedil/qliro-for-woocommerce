<?php
/**
 * Base class for handling notifications from Qliro.
 *
 * @package Qliro_One_Notifications/Classes/API/Notifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Notifications_Base
 */
abstract class Qliro_One_Notifications {
	/**
	 * The event type for the notification. Should match the event type sent by Qliro in the notification request as a uppercase string.
	 *
	 * @var string
	 */
	protected $event_type;

	/**
	 * The provider for the notification. Should match the provider sent by Qliro in the notification request as a uppercase string.
	 *
	 * @var string
	 */
	protected $provider;

	/**
	 * Check if the event type and provider matches the notification handler.
	 *
	 * @param string $event_type The event type to check.
	 * @param string $provider   The provider to check.
	 */
	public function matches( $event_type, $provider ) {
		// Uppercase the event type and provider for case-insensitive comparison.
		$event_type = strtoupper( $event_type );
		$provider   = strtoupper( $provider );
		return $this->event_type === $event_type && $this->provider === $provider;
	}

	/**
	 * Handle the notification callback.
	 *
	 * @param array         $payload The payload from the notification.
	 * @param WC_Order|null $order The order object, if available.
	 *
	 * @return void
	 * @throws WP_Exception If the notification cannot be handled.
	 */
	abstract public function handle_notification( $payload, $order = null );
}

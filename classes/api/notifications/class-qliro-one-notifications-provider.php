<?php
/**
 * Class that provides the handlers for notifications from Qliro.
 *
 * @package Qliro_One_Notifications/Classes/API/Notifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Notifications_Provider
 */
class Qliro_One_Notifications_Provider {
	/**
	 * Array of handlers for notifications.
	 *
	 * @var Qliro_One_Notifications[]
	 */
	protected $handlers = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Include the base notification class, and the specific notification handler for Ingrid shipping.
		include_once QLIRO_WC_PLUGIN_PATH . '/classes/api/notifications/class-qliro-one-notifications.php';
		include_once QLIRO_WC_PLUGIN_PATH . '/classes/api/notifications/class-qliro-one-notifications-ingrid-shipping.php';

		$this->handlers = array(
			new Qliro_One_Notifications_Ingrid_Shipping(),
		);
	}

	/**
	 * Get the handler for the given event type and provider.
	 *
	 * @param string $event_type The event type to check.
	 * @param string $provider   The provider to check.
	 *
	 * @return Qliro_One_Notifications|null The handler if found, null otherwise.
	 */
	public function get_handler( $event_type, $provider ) {
		foreach ( $this->handlers as $handler ) {
			if ( $handler->matches( $event_type, $provider ) ) {
				return $handler;
			}
		}

		return null;
	}
}

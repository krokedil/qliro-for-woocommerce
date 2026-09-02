<?php
/**
 * Class for getting the scheduled actions for an order.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_Scheduled_Actions
 */
class Qliro_Scheduled_Actions {

	/**
	 * Gets the scheduled actions for the order.
	 *
	 * @param string $confirmation_id The confirmation ID.
	 * @return array
	 */
	public static function get_scheduled_actions( $confirmation_id ) {
		$statuses          = array( 'complete', 'failed', 'pending' );
		$scheduled_actions = array(
			'complete' => array(),
			'failed'   => array(),
			'pending'  => array(),
		);

		foreach ( $statuses as $status ) {
			$scheduled_actions[ $status ] = as_get_scheduled_actions(
				array(
					// Exact args match is indexed in Action Scheduler, unlike the LIKE-based 'search' parameter.
					'args'     => array( $confirmation_id ),
					'status'   => array( $status ),
					'per_page' => -1,
					'group'    => Qliro_One_Callbacks::CHECKOUT_CALLBACKS,
				),
				'ids'
			);
		}

		return $scheduled_actions;
	}
}

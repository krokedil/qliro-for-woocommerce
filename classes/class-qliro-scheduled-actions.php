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
	 * @param string $order_created_date The order creation date.
	 * @return array
	 */
	public static function get_scheduled_actions( $confirmation_id, $order_created_date ) {
		$statuses          = array( 'complete', 'failed', 'pending' );
		$scheduled_actions = array(
			'complete' => array(),
			'failed'   => array(),
			'pending'  => array(),
		);

		$order_created_timestamp = strtotime( $order_created_date );
		$three_months_ago        = strtotime( '-3 months' );

		if ( $order_created_timestamp >= $three_months_ago ) {
			foreach ( $statuses as $status ) {
				$scheduled_actions[ $status ] = as_get_scheduled_actions(
					array(
						'search'       => $confirmation_id,
						'status'       => array( $status ),
						'per_page'     => -1,
						'group'        => 'qliro_checkout_callbacks',
						'date'         => $order_created_date,
						'date_compare' => '>=',
					),
					'ids'
				);
			}
		}

		return $scheduled_actions;
	}
}

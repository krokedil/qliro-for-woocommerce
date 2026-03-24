<?php
/**
 * Helper class to get loyalty configuration for the Qliro order.
 *
 * @package Qliro_One/Classes/Requests/Helpers
 */

/**
 * Helper class to get loyalty configuration for the Qliro order.
 */
class Qliro_One_Helper_Loyalty {
	/**
	 * Get the loyalty configuration for the Qliro order.
	 *
	 * @return array
	 */
	public static function get_loyalty_configuration() {
		return apply_filters( 'qliro_one_loyalty_configuration', null );
	}
}

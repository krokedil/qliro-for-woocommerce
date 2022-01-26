<?php
/**
 * Helper class to set limits for an order. Minimum age, require id verification.
 *
 * @package Qliro_One/Classes/Requests/Helpers
 */

 /**
  * Helper class to set limits for an order. Minimum age, require id verification.
  */
class Qliro_One_Helper_Order_Limitations {
	/**
	 * Set the order limitations.
	 *
	 * @param array $body The request body.
	 * @return array
	 */
	public static function set_limitations( $body ) {
		return self::maybe_set_minimum_age(
			self::maybe_set_require_id_verification(
				$body
			)
		);
	}

	/**
	 * Maybe sets the minimum age for the Qliro order.
	 *
	 * @param array $body The request body.
	 * @return array
	 */
	public static function maybe_set_minimum_age( $body ) {
		$settings    = get_option( 'woocommerce_qliro_one_settings' );
		$minimum_age = ( ! empty( $settings['minimum_age'] ) ) ? $settings['minimum_age'] : 0;

		foreach ( $body['OrderItems'] as $order_item ) {
			$product_min_age = get_post_meta( wc_get_product_id_by_sku( $order_item['MerchantReference'] ), 'qoc_min_age', true );
			// If products min age is not set.
			if ( empty( $product_min_age ) ) {
				continue;
			}

			if ( 0 === $minimum_age || $product_min_age < $minimum_age ) {
				$minimum_age = $product_min_age;
			}
		}

		$body['MinimumCustomerAge'] = $minimum_age;
		return $body;
	}

	/**
	 * Maybe sets the require identity verification for the Qliro order.
	 *
	 * @param array $body The request body.
	 * @return array
	 */
	public static function maybe_set_require_id_verification( $body ) {
		$settings                = get_option( 'woocommerce_qliro_one_settings' );
		$require_id_verification = 'yes' === $settings['require_id_verification'];

		if ( ! $require_id_verification ) {
			foreach ( $body['OrderItems'] as $order_item ) {
				$product_require_id_verification = 'yes' === get_post_meta( wc_get_product_id_by_sku( $order_item['MerchantReference'] ), 'qoc_require_id_verification', true );
				// If products require id verification is not set or false, continue.
				if ( empty( $product_require_id_verification ) || ! $product_require_id_verification ) {
					continue;
				}

				// If order item sets the flag to true, break.
				$require_id_verification = true;
				break;
			}
		}

		$body['RequireIdentityVerification'] = $require_id_verification;
		return $body;
	}
}

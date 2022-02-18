<?php
/**
 * Helper class to get availabe shipping methods for the Qliro One order.
 *
 * @package Qliro_One/Classes/Requests/Helpers
 */

 /**
  * Helper class to get available shipping methods for the Qliro One order.
  */
class Qliro_One_Helper_Shipping_Methods {
	/**
	 * Get the available shipping methods.
	 *
	 * @return array
	 */
	public static function get_shipping_methods() {
		$settings = get_option( 'woocommerce_qliro_one_settings' );
		if ( 'yes' !== $settings['shipping_in_iframe'] ) {
			return array();
		}

		if ( ! WC()->cart->needs_shipping() ) {
			return array();
		}

		$shipping_options = array();
		$packages         = WC()->shipping->get_packages();
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				$method_id   = $method->id;
				$method_name = $method->label;

				$method_price_inc_tax = round( $method->cost + array_sum( $method->taxes ), 2 );
				$method_price_ex_tax  = round( $method->cost, 2 );
				$shipping_options[]   = array(
					'MerchantReference' => $method_id,
					'DisplayName'       => $method_name,
					'PriceIncVat'       => $method_price_inc_tax,
					'PriceExVat'        => $method_price_ex_tax,
				);
			}
		}
		return apply_filters( 'qliro_one_shipping_options', $shipping_options );
	}
}

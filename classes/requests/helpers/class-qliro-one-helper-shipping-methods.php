<?php
/**
 * Helper class to get availabe shipping methods for the Qliro One order.
 *
 * @package Qliro_One/Classes/Requests/Helpers
 */

use Krokedil\Shipping\PickupPoints;

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
			/** @var WC_Shipping_Rate $method */
			foreach ( $package['rates'] as $method ) {
				$method_id   = $method->id;
				$method_name = $method->label;

				$method_price_inc_tax = round( $method->cost + array_sum( $method->taxes ), 2 );
				$method_price_ex_tax  = round( $method->cost, 2 );
				$options              = array(
					'MerchantReference' => $method_id,
					'DisplayName'       => $method_name,
					'PriceIncVat'       => $method_price_inc_tax,
					'PriceExVat'        => $method_price_ex_tax,
				);

				$method_settings = get_option( "woocommerce_{$method->method_id}_{$method->instance_id}_settings", array() );
				$description     = isset( $method_settings['qliro_description'] ) ? $method_settings['qliro_description'] : '';
				if ( ! empty( $description ) ) {
					// The trim is necessary to remove invisible characters (even when printed) such as "\n", otherwise, we'll end up with "empty" elements. The array_filter without arguments removes empty lines.
					$lines = array_filter( array_map( 'trim', explode( "\n", $method_settings['qliro_description'] ) ) );

					// Maximum length is 100 characters per line and up to 3 lines.
					$description = array_map(
						function ( $line ) {
							return trim( mb_substr( $line, 0, 100 ) );
						},
						$lines
					);

					$options['Descriptions'] = array_slice( $description, 0, 3 );
				}

				$category_display_name = isset( $method_settings['qliro_category_display_name'] ) ? $method_settings['qliro_category_display_name'] : 'none';
				if ( 'none' !== $category_display_name ) {
					$options['CategoryDisplayName'] = $category_display_name;
				}

				$label_display_name = isset( $method_settings['qliro_label_display_name'] ) ? $method_settings['qliro_label_display_name'] : 'none';
				if ( 'none' !== $label_display_name ) {
					$options['LabelDisplayName'] = $label_display_name;
				}

				$brand = isset( $method_settings['qliro_brand'] ) ? $method_settings['qliro_brand'] : 'none';
				if ( 'none' !== $brand ) {
					$options['Brand'] = $brand;
				}

				$option_labels = array();
				foreach ( $method_settings as $key => $value ) {
					if ( false !== strpos( $key, 'qliro_option_label_' ) && 'none' !== $value ) {
						$option_labels[] = array(
							'Name'        => substr( $key, strlen( 'qliro_option_label_' ) ),
							'DisplayType' => $value,
						);
					}
				}

				if ( ! empty( $option_labels ) ) {
					$options['OptionLabels'] = $option_labels;
				}

				self::set_pickup_points( $options, $method );
				$shipping_options[] = $options;

			}
		}
		return apply_filters( 'qliro_one_shipping_options', $shipping_options );
	}

	/**
	 * Set pickup points for the shipping method.
	 *
	 * @param array $options The shipping options for the Qliro api.
	 * @param WC_Shipping_Rate $method The shipping method rate from WooCommerce.
	 */
	private static function set_pickup_points( &$options, $method ) {
		// Get any pickup points for the shipping method.
		$pickup_points = new PickupPoints( $method );

		// Loop through the pickup points and set the pickup point data for the Qliro api.
		$secondary_options = array();
		foreach ( $pickup_points->get_pickup_points() as $pickup_point ) {
			// If the id is empty, skip.
			if ( empty( $pickup_point->get_id() ) ) {
				continue;
			}

			$secondary_options[] = array(
				'MerchantReference' => $pickup_point->get_id(),
				'DisplayName'       => $pickup_point->get_name(),
				'Descriptions'      => array( // Can max have 3 lines.
					$pickup_point->get_address()->get_street(),
					$pickup_point->get_address()->get_postcode() . ' ' . $pickup_point->get_address()->get_city(),
					$pickup_point->get_description(),
				),
				'Coordinates'       => array(
					'Lat' => $pickup_point->get_coordinates()->get_latitude(),
					'Lng' => $pickup_point->get_coordinates()->get_longitude(),
				),
				'DeliveryDateInfo'  => array(
					'DateStart' => $pickup_point->get_eta()->get_utc(),
				),
			);
		}

		if ( ! empty( $secondary_options ) ) {
			$options['SecondaryOptions'] = $secondary_options;
		}
	}
}

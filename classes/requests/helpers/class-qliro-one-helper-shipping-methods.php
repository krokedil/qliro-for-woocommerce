<?php
/**
 * Helper class to get available shipping methods for the Qliro One order.
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
		if ( ! QOC_WC()->checkout()->is_shipping_in_iframe_enabled() ) {
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
				// If the method id contains the qliro_shipping string, skip.
				if ( false !== strpos( $method->id, 'qliro_shipping' ) ) {
					continue;
				}

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

				// Description.
				$description = isset( $method_settings['qliro_description'] ) ? $method_settings['qliro_description'] : '';
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

				// Category display name.
				$category_display_name = isset( $method_settings['qliro_category_display_name'] ) ? $method_settings['qliro_category_display_name'] : 'none';
				if ( 'none' !== $category_display_name ) {
					$options['CategoryDisplayName'] = $category_display_name;
				}

				// Label display name.
				$label_display_name = isset( $method_settings['qliro_label_display_name'] ) ? $method_settings['qliro_label_display_name'] : 'none';
				if ( 'none' !== $label_display_name ) {
					$options['LabelDisplayName'] = $label_display_name;
				}

				// Brand.
				$brand = isset( $method_settings['qliro_brand'] ) ? $method_settings['qliro_brand'] : 'none';
				if ( 'none' !== $brand ) {
					$options['Brand'] = $brand;
				}

				// Option labels.
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

				// Delivery date.
				$delivery_date_start = isset( $method_settings['qliro_delivery_date_start'] ) ? $method_settings['qliro_delivery_date_start'] : 'none';
				$delivery_date_end   = isset( $method_settings['qliro_delivery_date_end'] ) ? $method_settings['qliro_delivery_date_end'] : 'none';
				if ( 'none' !== $delivery_date_start ) {
					$options['DeliveryDateInfo']['DateStart'] = date( 'Y-m-d', strtotime( "+$delivery_date_start days" ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				}
				if ( 'none' !== $delivery_date_end ) {
					$options['DeliveryDateInfo']['DateEnd'] = date( 'Y-m-d', strtotime( "+$delivery_date_end days" ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				}

				// Pickup points.
				self::set_pickup_points( $options, $method );
				$shipping_options[] = apply_filters( 'qliro_one_shipping_option', $options, $method, $method_settings );

			}
		}
		return apply_filters( 'qliro_one_shipping_options', $shipping_options );
	}

	/**
	 * Set pickup points for the shipping method.
	 *
	 * @param array            $options The shipping options for the Qliro api.
	 * @param WC_Shipping_Rate $method The shipping method rate from WooCommerce.
	 */
	private static function set_pickup_points( &$options, $method ) {
		// Get any pickup points for the shipping method.
		$pickup_points = QOC_WC()->pickup_points_service()->get_pickup_points_from_rate( $method ) ?? array();

		// Loop through the pickup points and set the pickup point data for the Qliro api.
		$secondary_options = array();
		foreach ( $pickup_points as $pickup_point ) {
			// If the id is empty, skip.
			if ( empty( $pickup_point->get_id() ) ) {
				continue;
			}

			$secondary_options[] = array(
				'MerchantReference' => $pickup_point->get_id(),
				'DisplayName'       => $pickup_point->get_name(),
				'Descriptions'      => array( // Can max have 3 lines.
					trim( mb_substr( $pickup_point->get_address()->get_street(), 0, 100 ) ),
					trim( mb_substr( $pickup_point->get_address()->get_postcode() . ' ' . $pickup_point->get_address()->get_city(), 0, 100 ) ),
					trim( mb_substr( $pickup_point->get_description(), 0, 100 ) ),
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

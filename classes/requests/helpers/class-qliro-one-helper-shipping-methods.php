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
				$options              = array(
					'MerchantReference' => $method_id,
					'DisplayName'       => $method_name,
					'PriceIncVat'       => $method_price_inc_tax,
					'PriceExVat'        => $method_price_ex_tax,
				);

				$method_settings = get_option( "woocommerce_{$method->method_id}_{$method->instance_id}_settings" );
				$description     = isset( $method_settings['description'] ) ? $method_settings['description'] : '';
				if ( ! empty( $description ) ) {
					// The trim is necessary to remove invisible characters (even when printed) such as "\n", otherwise, we'll end up with "empty" elements. The array_filter without arguments removes empty lines.
					$lines = array_filter( array_map( 'trim', explode( "\n", $method_settings['description'] ) ) );

					// Maximum length is 100 characters per line and up to 3 lines.
					$description = array_map(
						function ( $line ) {
							return trim( mb_substr( $line, 0, 100 ) );
						},
						$lines
					);

					$options['Descriptions'] = array_slice( $description, 0, 3 );
				}

				// The category name is not predefined, however, we've limited it to HOME DELIVERY and PICKUP. Accordingly, Qliro will display the category name on the checkout as-is. For this reason, we replace the underscore with a space.
				$category_display_name = isset( $method_settings['categoryDisplayName'] ) ? $method_settings['categoryDisplayName'] : 'none';
				if ( 'none' !== $category_display_name ) {
					$options['CategoryDisplayName'] = str_replace( '_', ' ', $category_display_name );
				}

				$label_display_name = isset( $method_settings['labelDisplayName'] ) ? $method_settings['labelDisplayName'] : 'none';
				if ( 'none' !== $label_display_name ) {
					$options['LabelDisplayName'] = $label_display_name;
				}

				$brand = isset( $method_settings['brand'] ) ? $method_settings['brand'] : 'none';
				if ( 'none' !== $brand ) {
					$options['Brand'] = $brand;
				}

				$eco_friendly = isset( $method_settings['isEcoFriendly'] ) ? $method_settings['isEcoFriendly'] : 'no';
				if ( 'no' !== $eco_friendly ) {
					$options['IsEcoFriendly'] = true;
				}

				$option_labels = array();
				foreach ( $method_settings as $key => $value ) {
					if ( false !== strpos( $key, 'option_label_' ) && 'none' !== $value ) {
						$option_labels[] = array(
							'Name'        => substr( $key, strlen( 'option_label_' ) ),
							'DisplayType' => $value,
						);
					}
				}

				if ( ! empty( $option_labels ) ) {
					$options['OptionLabels'] = $option_labels;
				}

				$shipping_options[] = $options;
			}
		}
		return apply_filters( 'qliro_one_shipping_options', $shipping_options );
	}
}

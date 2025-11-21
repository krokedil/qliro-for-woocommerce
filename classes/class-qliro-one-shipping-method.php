<?php
/**
 * Class for the Qliro shipping method that is used when using a third party shipping provider through Qliro.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

use KrokedilQliroDeps\Krokedil\Shipping\PickupPoint\PickupPoint;

/**
 * Qliro shipping method class
 */
class Qliro_One_Shipping_Method extends WC_Shipping_Method {
	/**
	 * Class constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'qliro_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->title              = __( 'Qliro Shipping', 'qliro-for-woocommerce' );
		$this->method_title       = __( 'Qliro Shipping', 'qliro-for-woocommerce' );
		$this->method_description = __( 'A dynamic shipping method, that will get its prices set by Qliro and the integration towards a shipping provider. When Qliro is the selected payment method, the other shipping methods for this region wont be shown to the customer.', 'qliro-for-woocommerce' );
		$this->supports           = array(
			'shipping-zones',
			// 'instance-settings',
			// 'instance-settings-modal',
		);

		add_filter( 'woocommerce_package_rates', array( $this, 'maybe_unset_other_rates' ), 10 );
	}

	/**
	 * If the shipping method is available or not for the current checkout.
	 *
	 * @param array $package The package.
	 */
	public function is_available( $package ) {
		// Only if the integrated shipping setting is enabled.
		if ( ! QLIRO_WC()->checkout()->is_integrated_shipping_enabled() ) {
			return false;
		}

		// If Avarda is not the chosen payment method, or its not the first option in the payment method lists, return false.
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		reset( $available_gateways );

		if ( 'qliro_one' === WC()->session->get( 'chosen_payment_method' ) || ( empty( WC()->session->get( 'chosen_payment_method' ) ) && 'qliro_one' === key( $available_gateways ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Maybe unset other rates.
	 *
	 * @param array $rates The rates.
	 *
	 * @return array
	 */
	public function maybe_unset_other_rates( $rates ) {
		// If any rate is the qliro_shipping method, unset all others.
		foreach ( $rates as $rate ) {
			if ( 'qliro_shipping' === $rate->method_id ) {
				$rates = array( $this->get_rate_id() => $rate );
				break;
			}
		}

		return $rates;
	}

	/**
	 * Register the shipping method with WooCommerce.
	 *
	 * @param array $methods WooCommerce shipping methods.
	 *
	 * @return array
	 */
	public static function register( $methods ) {
		// Only if the integrated shipping setting is enabled.
		if ( ! QLIRO_WC()->checkout()->is_integrated_shipping_enabled() ) {
			return $methods;
		}

		$methods['qliro_shipping'] = self::class;
		return $methods;
	}

	/**
	 * Calculate shipping.
	 *
	 * @param array $package Package data.
	 *
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		// Get the data from the transient.
		$data = get_transient( 'qoc_shipping_data_' . WC()->session->get( 'qliro_one_order_id' ) );
		// If the data is not set, return.
		if ( ! $data ) {
			$this->add_error( __( 'No shipping data found.', 'qliro-for-woocommerce' ) );
			return;
		}

		$tax_rate     = $this->get_shipping_tax_rate( $data );
		$shipping_tax = WC_Tax::calc_shipping_tax( $data['totalShippingPriceExVat'], $tax_rate );

		$rate = array(
			'id'        => $this->get_rate_id( $data['method'] ),
			'label'     => $data['methodName'],
			'cost'      => $data['totalShippingPriceExVat'],
			'taxes'     => $shipping_tax,
			'calc_tax'  => 'per_order',
			'meta_data' => array(
				'qliro_shipping_method' => $data['method'],
				'qliro_shipping_option' => wp_json_encode( $data ),
			),
		);

		if ( ! empty( $data['pickupLocation'] ) ) {
			self::add_pickup_point_meta( $rate, $data['pickupLocation'], $data['method'] );
		}

		$this->add_rate( $rate );
	}

	/**
	 * Add the pickup point meta to the rate.
	 *
	 * @param array  $rate The shipping rate to add the metadata to.
	 * @param array  $location The pickup location from Qliro.
	 * @param string $method The shipping method id from Qliro.
	 *
	 * @return void
	 */
	public static function add_pickup_point_meta( &$rate, $location, $method ) {
		$name        = $location['name'] ?? '';
		$address     = $location['address'] ?? '';
		$city        = $location['city'] ?? '';
		$postal_code = $location['postalCode'] ?? '';
		$description = $location['description'] ?? array();

		$pickup_point = ( new PickupPoint() )
			->set_id( $method )
			->set_name( $name )
			->set_address( $address, $city, $postal_code, '' )
			->set_description( implode( ' ', $description ) );

		$rate['meta_data']['krokedil_pickup_points']            = wp_json_encode( array( $pickup_point ) );
		$rate['meta_data']['krokedil_selected_pickup_point']    = wp_json_encode( $pickup_point );
		$rate['meta_data']['krokedil_selected_pickup_point_id'] = $pickup_point->get_id();
	}

	/**
	 * Get shipping tax rate that matches the Qliro tax rate from the shipping integration.
	 *
	 * @param array $data The shipping data from Qliro.
	 *
	 * @return array
	 */
	public static function get_shipping_tax_rate( $data ) {
		$ex_vat              = $data['totalShippingPriceExVat'] ?? 0;
		$inc_vat             = $data['totalShippingPrice'] ?? 0;
		$tax_rates           = \WC_Tax::get_shipping_tax_rates();
		$calculated_tax_rate = 0;

		if ( ! empty( $ex_vat ) ) {
			// Calculate the tax rate from the prices.
			$calculated_tax_rate = round( ( $inc_vat - $ex_vat ) / $ex_vat, 4 ) * 100;
		}

		foreach ( $tax_rates as $key => $tax_rate ) {
			// Calculate the difference between the rate and the calculated rate.
			$diff = abs( $tax_rate['rate'] - $calculated_tax_rate );

			// If the diff is less than or equal to 0.1 we have a match. This avoid rounding issues for tax rate calculations, and also covers the cases where the tax rates have decimals.
			if ( $diff <= 0.1 ) {
				return array( $key => $tax_rate );
			}
		}

		return array(
			array(
				'label'    => 'Qliro Shipping VAT',
				'rate'     => $calculated_tax_rate,
				'shipping' => 'yes',
				'compound' => 'no',
			),
		);
	}
}

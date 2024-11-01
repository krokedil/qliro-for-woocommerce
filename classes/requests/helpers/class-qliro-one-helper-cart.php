<?php
/**
 * Cart helper class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Helper_Cart
 */
class Qliro_One_Helper_Cart {


	/**
	 * Qliro_One_Helper_Cart constructor.
	 */
	private function __construct() {}

	/**
	 * Gets formatted cart items.
	 *
	 * @param object $cart The WooCommerce cart object.
	 * @return array Formatted cart items.
	 */
	public static function get_cart_items( $cart = null ) {
		$formatted_cart_items = array();

		if ( null === $cart ) {
			$cart = WC()->cart->get_cart();
		}

		// Get cart items.
		foreach ( $cart as $cart_item ) {
			$formatted_cart_items[] = self::get_cart_item( $cart_item );
		}

		/**
		 * Get cart fees.
		 *
		 * @var $cart_fees WC_Cart_Fees
		 */
		$cart_fees = WC()->cart->get_fees();
		foreach ( $cart_fees as $fee ) {
			$formatted_cart_items[] = self::get_fee( $fee );
		}

		// Get cart shipping.
		$settings = get_option( 'woocommerce_qliro_one_settings' );
		if ( WC()->cart->needs_shipping() && ! QOC_WC()->checkout()->is_shipping_in_iframe_enabled() ) {
			$shipping = self::get_shipping();
			if ( null !== $shipping ) {
				$formatted_cart_items[] = $shipping;
			}
		}

		return $formatted_cart_items;
	}

	/**
	 * Gets formatted cart item.
	 *
	 * @param object $cart_item WooCommerce cart item object.
	 * @return array Formatted cart item.
	 */
	public static function get_cart_item( $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}
		return array(
			'MerchantReference'  => self::get_product_sku( $product ),
			'Description'        => self::get_product_name( $cart_item ),
			'Quantity'           => $cart_item['quantity'],
			'PricePerItemIncVat' => self::get_product_unit_price( $cart_item ),
			'PricePerItemExVat'  => self::get_product_unit_price_no_tax( $cart_item ),
		);
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $cart_item The cart item.
	 * @return string
	 */
	public static function get_product_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$cart_item_name = $cart_item_data->get_name();
		$item_name      = apply_filters( 'qliro_one_cart_item_name', $cart_item_name, $cart_item );
		return strip_tags( $item_name );//phpcs:ignore
	}

	/**
	 * Gets the products unit price.
	 *
	 * @param object $cart_item The cart item.
	 * @return string
	 */
	public static function get_product_unit_price( $cart_item ) {
		return wc_format_decimal( ( $cart_item['line_total'] + $cart_item['line_tax'] ) / $cart_item['quantity'], min( wc_get_price_decimals(), 2 ) );
	}

	/**
	 * Gets the products unit price.
	 *
	 * @param object $cart_item The cart item.
	 * @return string
	 */
	public static function get_product_unit_price_no_tax( $cart_item ) {
		return wc_format_decimal( ( $cart_item['line_total'] ) / $cart_item['quantity'], min( wc_get_price_decimals(), 2 ) );
	}

	/**
	 * Gets the tax rate for the product.
	 *
	 * @param object $cart_item The cart item.
	 * @return float
	 */
	public static function get_product_tax_rate( $cart_item ) {
		if ( 0 === intval( $cart_item['line_total'] ) ) {
			return 0;
		}
		return ( round( $cart_item['line_tax'] / $cart_item['line_total'], 2 ) );
	}

	/**
	 * Undocumented static function
	 *
	 * @param object $product The WooCommerce Product.
	 * @return string
	 */
	public static function get_product_sku( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return $item_reference;
	}

	/**
	 * Formats the fee.
	 *
	 * @param object $fee A WooCommerce Fee.
	 * @return array
	 */
	public static function get_fee( $fee ) {
		// todo.
		return array(
			'MerchantReference'  => 'fee:' . $fee->id,
			'Description'        => $fee->name,
			'Quantity'           => 1,
			'PricePerItemIncVat' => wc_format_decimal( $fee->amount + $fee->tax, min( wc_get_price_decimals(), 2 ) ),
			'PricePerItemExVat'  => wc_format_decimal( $fee->amount, min( wc_get_price_decimals(), 2 ) ),
		);

	}

	/**
	 * Formats the shipping.
	 *
	 * @return array
	 */
	public static function get_shipping() {
		$packages        = WC()->shipping()->get_packages();
		$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				if ( $chosen_shipping === $method->id ) {
					if ( $method->cost > 0 ) {
						return array(
							'MerchantReference'  => $method->id,
							'Description'        => $method->label,
							'Quantity'           => 1,
							'PricePerItemIncVat' => wc_format_decimal( WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax(), min( wc_get_price_decimals(), 2 ) ),
							'PricePerItemExVat'  => wc_format_decimal( WC()->cart->get_shipping_total(), min( wc_get_price_decimals(), 2 ) ),
						);
					}

					return array(
						'MerchantReference'  => $method->id,
						'Description'        => $method->label,
						'Quantity'           => 1,
						'PricePerItemIncVat' => 0,
						'PricePerItemExVat'  => 0,
					);
				}
			}
		}
	}

	/**
	 * Returns a product type.
	 *
	 * @param WC_Product $product WC product.
	 *
	 * @return string
	 */
	public static function get_product_type( $product ) {
		return $product->is_virtual() ? 'digital' : 'physical';
	}
}

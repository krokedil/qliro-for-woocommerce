<?php
/**
 * Cart helper class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Helpers
 */

use KrokedilQliroDeps\Krokedil\WooCommerce\Subscription;

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
		if ( WC()->cart->needs_shipping() && ! QOC_WC()->checkout()->is_shipping_in_iframe_enabled() ) {
			$shipping = self::get_shipping();
			if ( null !== $shipping ) {
				$formatted_cart_items[] = $shipping;
			}
		}

		foreach ( QOC_WC()->krokedil->compatibility()->giftcards() as $giftcards ) {
			if ( false !== ( strpos( get_class( $giftcards ), 'WCGiftCards', true ) ) && ! function_exists( 'WC_GC' ) ) {
				continue;
			}

			$retrieved_giftcards = $giftcards->get_cart_giftcards();
			foreach ( $retrieved_giftcards as $retrieved_giftcard ) {

				$formatted_cart_items[] = array(
					'MerchantReference'  => $retrieved_giftcard->get_sku(),
					'Description'        => $retrieved_giftcard->get_name(),
					'Type'               => 'Discount',
					'Quantity'           => $retrieved_giftcard->get_quantity(),
					'PricePerItemIncVat' => $retrieved_giftcard->get_total_amount(),
					'PricePerItemExVat'  => $retrieved_giftcard->get_total_amount(),
				);
			}
		}

		return $formatted_cart_items;
	}

	/**
	 * Gets formatted cart item.
	 *
	 * @param array $cart_item WooCommerce cart item object.
	 * @return array Formatted cart item.
	 */
	public static function get_cart_item( $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}

		$formatted_cart_item = array(
			'MerchantReference'  => self::get_product_sku( $product ),
			'Description'        => self::get_product_name( $cart_item ),
			'Type'               => 'Product',
			'Quantity'           => $cart_item['quantity'],
			'PricePerItemIncVat' => self::get_product_unit_price( $cart_item ),
			'PricePerItemExVat'  => self::get_product_unit_price_no_tax( $cart_item ),
		);

		if ( QOC_WC()->checkout()->is_integrated_shipping_enabled() ) {
			$formatted_cart_item = self::get_ingrid_metadata( $formatted_cart_item, $cart_item, $product );
		}

		// If the cart item is a subscription item, we need to add the metadata to the cart item.
		if ( Subscription::is_subscription_item( $cart_item ) ) {
			$formatted_cart_item['Metadata']['Subscription']['Enabled'] = true;
		}

		return $formatted_cart_item;
	}

	/**
	 * Gets the product name.
	 *
	 * @param array $cart_item The cart item.
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
	 * @param array $cart_item The cart item.
	 * @return string
	 */
	public static function get_product_unit_price( $cart_item ) {
		return wc_format_decimal( ( $cart_item['line_total'] + $cart_item['line_tax'] ) / $cart_item['quantity'], min( wc_get_price_decimals(), 2 ) );
	}

	/**
	 * Gets the products unit price.
	 *
	 * @param array $cart_item The cart item.
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
	 * Gets the product SKU for the merchant reference.
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
		return array(
			'MerchantReference'  => qliro_one_format_fee_reference( $fee->name ),
			'Description'        => $fee->name,
			'Quantity'           => 1,
			'Type'               => $fee->amount < 0 ? 'Discount' : 'Fee',
			'PricePerItemIncVat' => wc_format_decimal( abs( $fee->amount + $fee->tax ), min( wc_get_price_decimals(), 2 ) ),
			'PricePerItemExVat'  => wc_format_decimal( abs( $fee->amount ), min( wc_get_price_decimals(), 2 ) ),
		);
	}

	/**
	 * Formats the shipping.
	 *
	 * @return array|null
	 */
	public static function get_shipping() {
		$packages        = WC()->shipping()->get_packages();
		$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				$method_cost = qliro_ensure_numeric( $method->cost );

				if ( $chosen_shipping === $method->id ) {
					if ( $method_cost > 0 ) {
						return array(
							'MerchantReference'  => $method->id,
							'Description'        => $method->label,
							'Type'               => 'Shipping',
							'Quantity'           => 1,
							'PricePerItemIncVat' => wc_format_decimal( WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax(), min( wc_get_price_decimals(), 2 ) ),
							'PricePerItemExVat'  => wc_format_decimal( WC()->cart->get_shipping_total(), min( wc_get_price_decimals(), 2 ) ),
						);
					}

					return array(
						'MerchantReference'  => $method->id,
						'Description'        => $method->label,
						'Type'               => 'Shipping',
						'Quantity'           => 1,
						'PricePerItemIncVat' => 0,
						'PricePerItemExVat'  => 0,
					);
				}
			}
		}

		return null;
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

	/**
	 * Set the ingrid metadata for an order item to the formatted cart item.
	 *
	 * @param array      $formatted_cart_item The formatted cart item.
	 * @param array      $cart_item The cart item.
	 * @param WC_Product $product The product.
	 *
	 * @return array
	 */
	public static function get_ingrid_metadata( $formatted_cart_item, $cart_item, $product ) {
		$weight = $product->get_weight();

		// Default empty values to 0.
		$weight = empty( $weight ) ? 0 : $weight;

		$metadata = array(
			'Weight'     => self::get_product_weight( $product ),
			'Sku'        => self::get_product_sku( $product ),
			'Dimensions' => self::get_product_dimensions( $product ),
			'OutOfStock' => ! $product->is_in_stock(),
			'Discount'   => self::get_cart_item_discount_amount( $cart_item ),
		);

		// Add shipping attributes if there is any shipping class available.
		$shipping_class = $product->get_shipping_class();

		if ( ! empty( $shipping_class ) ) {
			$metadata['Attributes'] = array( $shipping_class );
		}

		$formatted_cart_item['Metadata']['Ingrid'] = $metadata;

		return $formatted_cart_item;
	}

	/**
	 * Get the product weight.
	 *
	 * @param WC_Product $product The product.
	 * @param string     $unit The unit to convert to, default is 'g'.
	 *
	 * @return int
	 */
	private static function get_product_weight( $product, $unit = 'g' ) {
		$weight = $product->get_weight();

		// Default empty value to 0.
		$weight = empty( $weight ) ? 0 : $weight;

		return round( wc_get_weight( $weight, $unit ) );
	}

	/**
	 * Get the product dimensions in mm.
	 *
	 * @param WC_Product $product The product.
	 * @param string     $unit The unit to convert to, default is 'mm'.
	 *
	 * @return array<string, int>
	 */
	private static function get_product_dimensions( $product, $unit = 'mm' ) {
		$length = $product->get_length();
		$width  = $product->get_width();
		$height = $product->get_height();

		// Default empty values to 0.
		$length = empty( $length ) ? 0 : $length;
		$width  = empty( $width ) ? 0 : $width;
		$height = empty( $height ) ? 0 : $height;

		return array(
			'Length' => round( wc_get_dimension( $length, $unit ) ),
			'Width'  => round( wc_get_dimension( $width, $unit ) ),
			'Height' => round( wc_get_dimension( $height, $unit ) ),
		);
	}

	/**
	 * Get the discount amount for the cart item.
	 *
	 * @param array $cart_item The cart item.
	 *
	 * @return float
	 */
	private static function get_cart_item_discount_amount( $cart_item ) {
		$line_total    = $cart_item['line_total'] + $cart_item['line_tax'];
		$line_subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];

		return round( $line_subtotal - $line_total, 2 );
	}

	/**
	 * Get the merchant provided metadata for the cart for ingrid.
	 *
	 * @return array
	 */
	public static function get_ingrid_merchant_provided_metadata() {
		$metadata = array();

		self::set_ingrid_vouchers( $metadata );
		self::set_ingrid_cart_attributes( $metadata );

		return $metadata;
	}

	/**
	 * Set the vouchers used in the cart to the metadata array for ingrid.
	 *
	 * @param array $metadata The metadata array.
	 *
	 * @return void
	 */
	private static function set_ingrid_vouchers( &$metadata ) {
		// If no coupons are applied, add an empty voucher to trigger an update of the Qliro shipping options.
		if ( empty( WC()->cart->get_applied_coupons() ) ) {
			$metadata[] = array(
				'Key'   => 'Ingrid.Vouchers',
				'Value' => '',
			);
			return;
		}

		foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
			$metadata[] = array(
				'Key'   => 'Ingrid.Vouchers',
				'Value' => $coupon,
			);
		}
	}

	/**
	 * Set the cart item attributes to the metadata array for ingrid.
	 *
	 * @param array $metadata The metadata array.
	 *
	 * @return void
	 */
	private static function set_ingrid_cart_attributes( &$metadata ) {
		$cart = WC()->cart->get_cart();

		foreach ( $cart as $cart_item ) {
			/**
			 * Get the product from the cart item.
			 *
			 * @var WC_Product $product The product from the WooCommerce cart item.
			 */
			$product        = $cart_item['data'];
			$shipping_class = $product->get_shipping_class();

			if ( ! empty( $shipping_class ) ) {
				$metadata[] = array(
					'Key'   => 'Ingrid.CartAttributes',
					'Value' => $shipping_class,
				);
			}
		}
	}
}

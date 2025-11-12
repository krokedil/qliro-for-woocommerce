<?php
/**
 * Gets the order information from an order.
 *
 * @package * @package Qliro_One_For_WooCommerce/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for processing order lines from a WooCommerce order.
 */
class Qliro_One_Helper_Order {
	/**
	 * Gets the order lines for the order.
	 *
	 * @param int   $order_id The WooCommerce order id.
	 * @param array $items The order items (optional).
	 * @return array
	 */
	public static function get_order_lines( $order_id, $items = array() ) {
		$order       = wc_get_order( $order_id );
		$order_lines = array();

		/**
		 * Process order item products.
		 *
		 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
		 */
		foreach ( $order->get_items() as $order_item ) {
			// Maybe get the quantity of the item to send.
			$item_quantity = $items[ $order_item->get_id() ] ?? null;

			// If the list of items is not empty, and we do not have a quantity for this item, skip it.
			if ( ! empty( $items ) && empty( $item_quantity ) ) {
				continue;
			}

			$order_lines[] = self::get_order_line_items( $order_item, $order, $item_quantity );
		}

		/**
		 * Process order item shipping.
		 *
		 * @var WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
		 */
		foreach ( $order->get_items( 'shipping' ) as $order_item ) {
			if ( ! empty( $items ) ) {
				if ( isset( $items[ $order_item->get_id() ] ) ) {
					$order_lines[] = self::process_order_item_shipping( $order_item, $order );
				} else {
					continue;
				}
			} else {
				$order_lines[] = self::process_order_item_shipping( $order_item, $order );
			}
		}

		/**
		 * Process order item fee.
		 *
		 * @var WC_Order_Item_Fee $order_item WooCommerce order item fee.
		 */
		foreach ( $order->get_items( 'fee' ) as $order_item ) {
			if ( ! empty( $items ) ) {
				if ( isset( $items[ $order_item->get_id() ] ) ) {
					$order_lines[] = self::process_order_item_fee( $order_item, $order );
				} else {
					continue;
				}
			} else {
				$order_lines[] = self::process_order_item_fee( $order_item, $order );
			}
		}

		// Process gift cards.
		$order_lines = self::process_gift_cards( $order_id, $order, $order_lines );

		return apply_filters( 'qliro_one_helper_cart_items', array_values( $order_lines ), $order );
	}

	/**
	 * Gets the upsell order lines for the order.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_request_id The order line upsell id.
	 * @return array
	 */
	public static function get_upsell_order_lines( $order_id, $upsell_request_id ) {
		$order       = wc_get_order( $order_id );
		$order_lines = array();

		/**
		 * Process order item products.
		 *
		 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
		 */
		foreach ( $order->get_items() as $order_item ) {
			if ( $upsell_request_id === $order_item->get_meta( '_ppu_upsell_id' ) ) {
				$order_lines[] = self::get_order_line_items( $order_item, $order );
			}
		}

		return array_values( $order_lines );
	}

	/**
	 * Formats the order lines for a refund request.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return array
	 */
	public static function get_return_items( $order_id ) {
		$order_lines  = self::get_order_lines( $order_id );
		$return_lines = array();

		foreach ( $order_lines as $order_line ) {
			// Discount must be negative.
			$price = $order_line['PricePerItemIncVat'];
			// A negative fee is a discount. On refund, Woo returns the fee as a positive value,
			// we must inverse the price as Qliro expects price <= 0 for discounts.
			$price = 'Discount' === $order_line['Type'] ? -1 * $price : abs( $price );

			$return_lines[] = array(
				'MerchantReference'  => $order_line['MerchantReference'],
				'Type'               => $order_line['Type'],
				'Quantity'           => abs( $order_line['Quantity'] ),
				'PricePerItemIncVat' => wc_format_decimal( $price, min( wc_get_price_decimals(), 2 ) ),
			);
		}

		return $return_lines;
	}

	/**
	 * Formats the order lines for a refund request.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return array
	 */
	public static function get_return_items_from_items( $items, $order_id ) {
		$return_lines = array();
		$order        = wc_get_order( $order_id );
		foreach ( $items as $item ) {
			$order_item = $order->get_item( $item['item_id'] );

			if ( ! $order_item ) {
				continue;
			}
			$return_lines[] = self::get_order_line_items( $order_item, $order, $item['quantity'] );

		}

		return $return_lines;
	}

	/**
	 * Gets the formatted order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order line item.
	 * @param WC_Order|null         $order The WooCommerce order.
	 * @param int|null              $quantity The quantity of the order line item. Defaults to null.
	 * @return array
	 */
	public static function get_order_line_items( $order_item, $order, $quantity = null ) {
		$order_id = $order_item->get_order_id();
		$order    = wc_get_order( $order_id );
		$quantity = $quantity ? $quantity : $order_item->get_quantity();
		return array(
			'MerchantReference'  => self::get_reference( $order_item ),
			'Description'        => $order_item->get_name(),
			'Quantity'           => $quantity,
			'Type'               => 'Product',
			'PricePerItemIncVat' => self::get_unit_price_inc_vat( $order_item ),
			'PricePerItemExVat'  => self::get_unit_price_ex_vat( $order_item ),
			'VatRate'            => self::get_tax_rate( $order, $order_item ),
		);
	}

	/**
	 * Gets the formated order line shipping.
	 *
	 * @param WC_Order_Item_Shipping $order_item The WooCommerce order line item.
	 * @param WC_Order|null          $order The WooCommerce order.
	 * @return array
	 */
	public static function process_order_item_shipping( $order_item, $order ) {
		return array(
			'MerchantReference'  => self::get_reference( $order_item ),
			'Description'        => $order_item->get_name(),
			'Quantity'           => 1,
			'Type'               => 'Shipping',
			'PricePerItemIncVat' => self::get_unit_price_inc_vat( $order_item ),
			'PricePerItemExVat'  => self::get_unit_price_ex_vat( $order_item ),
			'VatRate'            => self::get_tax_rate( $order, $order_item ),
		);
	}

	/**
	 * Gets the formatted order line fees.
	 *
	 * @param WC_Order_Item_Fee $order_item The order item fee.
	 * @param WC_Order|null     $order The WooCommerce order.
	 * @return array
	 */
	public static function process_order_item_fee( $order_item, $order ) {
		$type = $order_item->get_total() < 0 ? 'Discount' : 'Fee';
		if ( ! empty( $order_item->get_meta( '_refunded_item_id' ) ) ) {
			$parent_order_item = new WC_Order_Item_Fee( $order_item->get_meta( '_refunded_item_id' ) );
			$type              = $parent_order_item->get_total() < 0 ? 'Discount' : 'Fee';
		}

		return array(
			'MerchantReference'  => self::get_reference( $order_item ),
			'Description'        => $order_item->get_name(),
			'Quantity'           => 1,
			'Type'               => $type,
			'PricePerItemIncVat' => self::get_unit_price_inc_vat( $order_item ),
			'PricePerItemExVat'  => self::get_unit_price_ex_vat( $order_item ),
			'VatRate'            => self::get_tax_rate( $order, $order_item ),
		);
	}

	/**
	 * Gets the reference for the order line.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_reference( $order_item ) {
		if ( 'line_item' === $order_item->get_type() ) {
			$product = $order_item['variation_id'] ? wc_get_product( $order_item['variation_id'] ) : wc_get_product( $order_item['product_id'] );
			if ( $product->get_sku() ) {
				$reference = $product->get_sku();
			} else {
				$reference = $product->get_id();
			}
		} elseif ( 'shipping' === $order_item->get_type() ) {
			// We need to get any potential shipping reference from the order if possible.
			$order              = wc_get_order( $order_item->get_order_id() );
			$shipping_reference = ! empty( $order ) ? $order->get_meta( '_qliro_one_shipping_reference' ) : '';

			if ( empty( $shipping_reference ) && $order->get_parent_id() ) {
				$parent_order       = wc_get_order( $order->get_parent_id() );
				$shipping_reference = $parent_order ? $parent_order->get_meta( '_qliro_one_shipping_reference' ) : '';
			}

			// If the shipping method used is the qliro_shipping method, we should use the order line meta.
			if ( 'qliro_shipping' === $order_item->get_method_id() ) {
				// If this is a refund order line, we need to get the parent to ensure we get the correct shipping reference.
				if ( ! empty( $order_item->get_meta( '_refunded_item_id' ) ) ) {
					$order_item = new WC_Order_Item_Shipping( $order_item->get_meta( '_refunded_item_id' ) );
				}

				$shipping_reference = $order_item->get_meta( 'qliro_shipping_method' );
			}

			// If the shipping reference is an empty value, use the method id and instance id.
			$reference = empty( $shipping_reference ) ? $order_item->get_method_id() . ':' . $order_item->get_instance_id() : $shipping_reference;
		} elseif ( 'fee' === $order_item->get_type() ) {
			$reference = qliro_one_format_fee_reference( $order_item->get_name() );
		} else {
			$reference = $order_item->get_id();
		}

		return $reference;
	}

	/**
	 * Get the unit price.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_unit_price_inc_vat( $order_item ) {
		$quantity = empty( $order_item->get_quantity() ) ? 1 : $order_item->get_quantity();

		$unit_price = wc_format_decimal( ( qliro_ensure_numeric( $order_item->get_total() ) + $order_item->get_total_tax() ) / $quantity, min( wc_get_price_decimals(), 2 ) );
		return $unit_price;
	}

	/**
	 * Get the unit price.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_unit_price_ex_vat( $order_item ) {
		$quantity = empty( $order_item->get_quantity() ) ? 1 : $order_item->get_quantity();

		$unit_price = wc_format_decimal( ( qliro_ensure_numeric( $order_item->get_total() ) ) / $quantity, min( wc_get_price_decimals(), 2 ) );
		return $unit_price;
	}

	/**
	 * Generate a request ID
	 *
	 * @return string
	 */
	public function generate_request_id() {
		return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 16384, 20479 ), random_int( 32768, 49151 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ) );
	}

	/**
	 * Process gift cards.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param object $order The WooCommerce order.
	 * @param array  $items The items about to be sent to Qliro.
	 * @return array
	 */
	public static function process_gift_cards( $order_id, $order, $items ) {
		foreach ( QOC_WC()->krokedil->compatibility()->giftcards() as $giftcards ) {
			if ( false !== ( strpos( get_class( $giftcards ), 'WCGiftCards', true ) ) && ! function_exists( 'WC_GC' ) ) {
				continue;
			}

			$retrieved_giftcards = $giftcards->get_order_giftcards( $order );
			foreach ( $retrieved_giftcards as $retrieved_giftcard ) {
				$items[] = array(
					'MerchantReference'  => $retrieved_giftcard->get_sku(),
					'Description'        => $retrieved_giftcard->get_name(),
					'Quantity'           => $retrieved_giftcard->get_quantity(),
					'Type'               => 'Discount',
					'PricePerItemIncVat' => $retrieved_giftcard->get_total_amount(),
					'PricePerItemExVat'  => $retrieved_giftcard->get_total_amount(),
				);
			}
		}

		return $items;
	}

	/**
	 * Get the return fees.
	 *
	 * @param array    $return_fees The array of return fees.
	 * @param array    $order_items The order items to send to Qliro for refund.
	 * @param WC_Order $order The WooCommerce order that is refunded.
	 *
	 * @return array
	 */
	public function get_return_fees( $return_fees, &$order_items, $order, $calc_return_fee ) {
		$fees = array();
		if ( ! empty( $return_fees ) ) {
			$fees = array_map( function( $return_fee ) {
				if ( $return_fee['amount'] > 0 ) {
					return array(
						'MerchantReference'   => 'return-fee',
						'PricePerItemExVat'   => $return_fee['amount'],
						'PricePerItemIncVat'  => $return_fee['amount'] + $return_fee['tax_amount'],
					);
				}
				return null; // Exclude fees with a zero or negative amount.
			}, $return_fees );

			// Remove null values from the array.
			$fees = array_filter( $fees );
		}

		if( $calc_return_fee ) {
			$calculated_return_fee = $this->calculate_return_fee( $order_items, $order );
			if ( ! empty( $calculated_return_fee ) && $calculated_return_fee['PricePerItemIncVat'] > 0 ) {
				$fees[] = $calculated_return_fee;
			}
		}

		return $fees;
	}

	/**
	 * Calculate the return fee.
	 *
	 * @param array    $items The items included in the refund.
	 * @param WC_Order $order The WooCommerce order that is refunded.
	 *
	 * @return array
	 */
	public function calculate_return_fee( &$items, $order ) {
		$fee = array(
			'MerchantReference'   => 'return-fee-calculated',
			'PricePerItemIncVat' => 0,
			'PricePerItemExVat' => 0,
		);

		$original_order_items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );
		// Loop each item that we are sending to Qliro, and compare the quantity, price and tax amount to the original order. If a difference is found, we should add the difference as a return fee.
		foreach ( $items as &$item ) {
			$reference = $item['MerchantReference'] ?? null;
			$original_order_item = array_filter( $original_order_items, function( $original_item ) use ( $reference, $order ) {
				switch( $original_item->get_type() ) {
					case 'line_item':
						/** @var WC_Order_Item_Product $original_item */
						return $original_item->get_product()->get_sku() === $reference || $original_item->get_product_id() === $reference || $original_item->get_variation_id() === $reference;
					case 'shipping':
						/** @var WC_Order_Item_Shipping $original_item */
						return $original_item->get_method_id() === $reference ||  $original_item->get_meta('qliro_shipping_method') === $reference || $order->get_meta( '_qliro_one_shipping_reference' ) === $reference;
					case 'fee':
						/** @var WC_Order_Item_Fee $original_item */
						return qliro_one_format_fee_reference( $original_item->get_name() ) === $reference;
					default:
						return false;
				}
			} );

			if ( empty( $original_order_item ) ) {
				continue;
			}

			$original_order_item         = reset( $original_order_item );
			$original_unit_price_inc_vat = Qliro_One_Helper_Order::get_unit_price_inc_vat( $original_order_item );
			$quantity                    = $item['Quantity'] ?? 1;

			$inc_vat_diff = $original_unit_price_inc_vat - $item['PricePerItemIncVat'];

			$fee['PricePerItemIncVat'] += round( $inc_vat_diff * $quantity, 2 );
			$fee['PricePerItemExVat']  += round( $inc_vat_diff * $quantity, 2 );

			// Set the price per item to the original price to ensure we send the correct value to Qliro.
			$item['PricePerItemIncVat'] = $original_unit_price_inc_vat;
		}

		return $fee;
	}

	/**
	 * Get the tax rate.
	 *
	 * @param WC_Order                                                       $order The WooCommerce order.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_tax_rate( $order, $order_item ) {
		// If we don't have any tax, return 0.
		if ( '0' === $order_item->get_total_tax() ) {
			return Qliro_One_Helper_Cart::format_vat_rate( 0 );
		}

		$tax_items = $order->get_items( 'tax' );

		/**
		 * Process the tax items.
		 *
		 * @var WC_Order_Item_Tax $tax_item The WooCommerce order tax item.
		 */
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( key( $order_item->get_taxes()['total'] ) === $rate_id ) {
				// Return the tax rate percent value.
				$rate = (float) WC_Tax::get_rate_percent_value( $rate_id );
				return Qliro_One_Helper_Cart::format_vat_rate( $rate * 100 );
			}
		}
		return Qliro_One_Helper_Cart::format_vat_rate( 0 );
	}
}

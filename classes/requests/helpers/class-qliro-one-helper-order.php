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

		return array_values( $order_lines );
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
			$price = 'Discount' === $order_line['Type'] ? $price : abs( $price );

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
		return array(
			'MerchantReference'  => self::get_reference( $order_item ),
			'Description'        => $order_item->get_name(),
			'Quantity'           => 1,
			'Type'               => $order_item->get_total() > 0 ? 'Fee' : 'Discount',
			'PricePerItemIncVat' => self::get_unit_price_inc_vat( $order_item ),
			'PricePerItemExVat'  => self::get_unit_price_ex_vat( $order_item ),
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
			$reference = sanitize_title_with_dashes( $order_item->get_name() );
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

		$unit_price = wc_format_decimal( ( $order_item->get_total() + $order_item->get_total_tax() ) / $quantity, min( wc_get_price_decimals(), 2 ) );
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

		$unit_price = wc_format_decimal( ( $order_item->get_total() ) / $quantity, min( wc_get_price_decimals(), 2 ) );
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
}

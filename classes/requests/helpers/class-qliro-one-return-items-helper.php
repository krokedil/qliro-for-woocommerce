<?php
/**
 * Gets the order information from an order.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for processing order lines from a WooCommerce order.
 */
class Qliro_One_Return_Items_Helper {


	/**
	 * Returns body params for return request.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array[]
	 */
	public function get_return_items_params( $order_id ) {
		$payment_transaction_id = get_post( $order_id, '_payment_transaction_id', true );
		$order                  = wc_get_order( $order_id );
		$order_lines            = array();
		$fees                   = array();

		foreach ( $order->get_items() as $item ) {
			$order_lines[] = $this->get_order_line_items( $item );
		}
		foreach ( $order->get_fees() as $fee ) {
			$fees[] = $this->get_order_line_fees( $fee );
		}
		return array(
			array(
				'PaymentTransactionId' => $payment_transaction_id,
				'OrderItems'           => $order_lines,
				'Fees'                 => $fees,
				'Discounts'            => // todo change.
					array(
						array(
							'MerchantReference'  => 'DiscountItem_1',
							'Description'        => 'Discount item',
							'PricePerItemIncVat' => -20,
							'PricePerItemExVat'  => -16,
						),
					),
			),
		);
	}

	/**
	 * Gets the order amount
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return int
	 */
	public function get_order_amount( $order_id ) {
		$order = wc_get_order( $order_id );
		return round( $order->get_total() * 100 );
	}

	/**
	 * Get total tax of order.
	 *
	 * @return int
	 */
	public function get_total_tax() {
		return round( $this->total_tax );
	}

	/**
	 * Gets the formatted order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order line item.
	 * @return array
	 */
	public function get_order_line_items( $order_item ) {
		$order_id = $order_item->get_order_id();
		$order    = wc_get_order( $order_id );
		return array(
			'MerchantReference'  => $order_item->get_name(),
			'Description'        => $order_item->get_name(),
			'Quantity'           => $order_item->get_quantity(),
			'PricePerItemIncVat' => $this->get_item_unit_price( $order, $order_item ),
			'PricePerItemExVat'  => $this->get_item_unit_price_no_price( $order, $order_item ),
		);
	}

	/**
	 * Gets the formated order line shipping.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array
	 */
	public function get_order_line_shipping( $order ) {
		return array(
			'MerchantReference'  => $order->get_order_number(),
			'Description'        => $order->get_shipping_method(),
			'Quantity'           => 1,
			'PricePerItemIncVat' => $this->get_shipping_total_amount( $order ),
			'PricePerItemExVat'  => $this->get_shipping_total_amount( $order ) - $order->get_shipping_tax(),
		);
	}

	/**
	 * Gets the formatted order line fees.
	 *
	 * @param WC_Order_Item_Fee $order_fee The order item fee.
	 * @return array
	 */
	public function get_order_line_fees( $order_fee ) {
		return array(
			'MerchantReference'  => $order_fee->get_id(),
			'Description'        => $order_fee->get_name(),
			'Quantity'           => 1,
			'PricePerItemIncVat' => $order_fee->get_amount(),
			'PricePerItemExVat'  => $order_fee->get_amount() - $order_fee->get_total_tax(),
		);
	}

	/**
	 * Gets the order line tax rate.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param mixed    $order_item If not false the WooCommerce order item WC_Order_Item.
	 * @return int
	 */
	public function get_order_line_tax_rate( $order, $order_item = false ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( ( '' !== $value ) && $rate_id === $key ) {
					return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] );
				}
			}
		}
		// If we get here, there is no tax set for the order item. Return zero.
		return 0;
	}

	/**
	 * Get item total amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_item Order item.
	 * @return int
	 */
	public function get_item_total_amount( $order, $order_item = false ) {

		$item_total_amount     = ( number_format( $order_item->get_total(), wc_get_price_decimals(), '.', '' ) + number_format( $order_item->get_total_tax(), wc_get_price_decimals(), '.', '' ) );
		$max_order_line_amount = ( number_format( ( $order_item->get_total() + $order_item->get_total_tax() ) * 100, wc_get_price_decimals(), '.', '' ) * $order_item->get_quantity() );
		// Check so the line_total isn't greater than product price x quantity.
		// This can happen when having price display set to 0 decimals.
		if ( $item_total_amount > $max_order_line_amount ) {
			$item_total_amount = $max_order_line_amount;
		}
		return round( $item_total_amount );
	}

	/**
	 * Get item unit price.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_item Order item.
	 * @return int
	 */
	public function get_item_unit_price( $order, $order_item = false ) {
		$item_subtotal = $order_item->get_total() / $order_item->get_quantity();
		return round( $item_subtotal );
	}

	/**
	 * Get item price ( excluding ).
	 *
	 * @param WC_Order       $order The WooCommerce order.
	 * @param boolean|object $order_item Order item.
	 *
	 * @return float
	 */
	public function get_item_unit_price_no_price( $order, $order_item = false ) {
		$item_subtotal = ( $order_item->get_total() - $this->get_item_total_tax_amount( $order, $order_item ) ) / $order_item->get_quantity();
		return round( $item_subtotal );
	}

	/**
	 * Get item total tax amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_item Order item.
	 * @return int
	 */
	public function get_item_total_tax_amount( $order, $order_item = false ) {

		$item_total_amount        = $this->get_item_total_amount( $order, $order_item );
		$item_total_excluding_tax = $item_total_amount / ( 1 + ( $this->get_order_line_tax_rate( $order, $order_item ) ) );
		$item_tax_amount          = $item_total_amount - $item_total_excluding_tax;
		$this->total_tax         += round( $item_tax_amount );
		return round( $item_tax_amount );
	}

	/**
	 * Get shipping total amount.
	 *
	 * @param WC_Order $order WC order.
	 * @return int
	 */
	public function get_shipping_total_amount( $order ) {
		return number_format( $order->get_shipping_total() + $order->get_shipping_tax(), wc_get_price_decimals(), '.', '' );
	}

	/**
	 * Get shipping total tax amount.
	 *
	 * @param WC_Order $order WC order.
	 * @return int
	 */
	public function get_shipping_total_tax_amount( $order ) {
		$shipping_total_amount       = $this->get_shipping_total_amount( $order );
		$shipping_tax_rate           = ( '0' !== $order->get_shipping_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0;
		$shipping_total_exluding_tax = $shipping_total_amount / ( 1 + ( $shipping_tax_rate ) );
		$shipping_tax_amount         = $shipping_total_amount - $shipping_total_exluding_tax;
		$this->total_tax            += round( $shipping_tax_amount );
		return round( $shipping_tax_amount );
	}

	/**
	 * Get fee total amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_fee Order fee.
	 * @return int
	 */
	public function get_fee_total_amount( $order, $order_fee ) {
		$fee_total_amount      = number_format( ( $order_fee->get_total() ) * ( 1 + ( $this->get_order_line_tax_rate( $order, $order_fee ) ) ), wc_get_price_decimals(), '.', '' );
		$max_order_line_amount = ( number_format( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100, wc_get_price_decimals(), '.', '' ) * $order_fee->get_quantity() );
		// Check so the line_total isn't greater than product price x quantity.
		// This can happen when having price display set to 0 decimals.
		if ( $fee_total_amount > $max_order_line_amount ) {
			$fee_total_amount = $max_order_line_amount;
		}
		return round( $fee_total_amount );
	}

	/**
	 * Get fee unit price.
	 *
	 * @param boolean|object $order_fee Order fee.
	 * @return int
	 */
	public function get_fee_unit_price( $order_fee ) {
		$fee_subtotal = ( $order_fee->get_total() + $order_fee->get_total_tax() );
		$fee_price    = number_format( $fee_subtotal, wc_get_price_decimals(), '.', '' );
		return round( $fee_price );
	}

	/**
	 * Get fee total tax amount.
	 *
	 * @param WC_Order       $order WC order.
	 * @param boolean|object $order_fee Order fee.
	 * @return int
	 */
	public function get_fee_total_tax_amount( $order, $order_fee ) {
		$fee_total_amount        = $this->get_fee_total_amount( $order, $order_fee );
		$fee_tax_rate            = ( '0' !== $order->get_total_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'fee' ) ) ) : 0;
		$fee_total_excluding_tax = $fee_total_amount / ( 1 + ( $fee_tax_rate ) );
		$fee_tax_amount          = $fee_total_amount - $fee_total_excluding_tax;
		$this->total_tax        += round( $fee_tax_amount );
		return round( $fee_tax_amount );
	}

	/**
	 * Generates request id.
	 *
	 * @return string
	 * @throws Exception If an appropriate source of randomness cannot be found.
	 */
	public function generate_request_id() {
		return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 16384, 20479 ), random_int( 32768, 49151 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ) );
	}
}

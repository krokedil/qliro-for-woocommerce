<?php
/**
 * Class Qliro_One_Request_Return_Items.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Qliro_One_Cancel_Order class.
 */
class Qliro_One_Request_Return_Items extends Qliro_One_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Return items';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/adminapi/v2/ReturnItems';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_data             = new Qliro_One_Helper_Order();
		$request_id             = $order_data->generate_request_id();
		$order_id               = $this->arguments['order_id'];
		$order                  = wc_get_order( $order_id );
		$refund_order_id        = $this->arguments['refund_order_id'];
		$items                  = $this->arguments['items'];
		$return_fee             = $this->arguments['return_fee'];

		$this->qliro_order_id   = $order->get_meta( '_qliro_one_order_id' );
		$capture_transaction_id = ! empty( $this->arguments['capture_id'] ) ? $this->arguments['capture_id'] : $order->get_meta( '_qliro_order_captured' );

		$order_items = ! empty( $items ) ? $order_data->get_return_items_from_items( $items, $refund_order_id ) : $order_data->get_return_items( $refund_order_id );
		$fees = $this->get_return_fees( $return_fee, $order_items, $order );

		return array(
			'RequestId'      => $request_id,
			'MerchantApiKey' => $this->get_qliro_key(),
			'OrderId'        => $this->qliro_order_id,
			'Currency'       => $order->get_currency(),
			'Returns'        => array(
				array(
					'PaymentTransactionId' => $capture_transaction_id,
					'OrderItems'           => $order_items,
					'Fees'                 => apply_filters( 'qliro_one_return_fees', $fees, $order_id, $refund_order_id, $order_items ),
				),
			),
		);
	}

	/**
	 * Get the return fees.
	 *
	 * @param array    $return_fee The return fee.
	 * @param array    $order_items The order items to send to Qliro for refund.
	 * @param WC_Order $order The WooCommerce order that is refunded.
	 * @TODO - Move to helper class for orders
	 * @return array
	 */
	protected function get_return_fees( $return_fee, &$order_items, $order ) {
		$fees = array();
		if ( ! empty( $return_fee ) && $return_fee['amount'] > 0 ) {
			$fees[] = array(
				'MerchantReference'   => 'return-fee',
				'PricePerItemExVat'  => $return_fee['amount'],
				'PricePerItemIncVat' => $return_fee['amount'] + $return_fee['tax_amount'],
			);
		}

		$calculated_return_fee = $this->calculate_return_fee( $order_items, $order );
		if ( ! empty( $calculated_return_fee ) && $calculated_return_fee['PricePerItemIncVat'] > 0 ) {
			$fees[] = $calculated_return_fee;
		}

		return $fees;
	}

	/**
	 * Calculate the return fee.
	 *
	 * @param array    $items The items included in the refund.
	 * @param WC_Order $order The WooCommerce order that is refunded.
	 * @TODO - Move to helper class for orders
	 * @return array
	 */
	protected function calculate_return_fee( &$items, $order ) {
		$calc_return_fee = $this->settings['calculate_return_fee'] ?? 'no';
		$fee = array(
			'MerchantReference'   => 'return-fee-calculated',
			'PricePerItemIncVat' => 0,
			'PricePerItemExVat' => 0,
		);
		if( $calc_return_fee === 'no' ) {
			return $fee;
		}

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
}

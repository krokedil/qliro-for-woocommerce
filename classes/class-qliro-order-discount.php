<?php
/**
 * Class for handling the Qliro order discounts.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_Order_Discount
 */
class Qliro_Order_Discount {
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'handle_add_order_discount_action' ), 9999 );
		add_action( 'woocommerce_order_item_fee_after_calculate_taxes', array( $this, 'fix_qliro_discount_tax_calculations' ) );
	}

	/**
	 * Output the "Add discount" action button.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public static function output_order_discount_button( $order ) {
		$classes = 'krokedil_wc__metabox_button krokedil_wc__metabox_action button button-secondary';
		echo wp_kses_post( "<a id='qliro_add_order_discount' class='{$classes}'>Add Qliro discount</a>" );

		$qliro_discount_data = self::get_order_discount_modal_data( $order );
		// Enqueue the script and style for the discount modal.
		wp_enqueue_script( 'qliro-admin-order-discount' );
		wp_localize_script(
			'qliro-admin-order-discount',
			'qliro_discount_data',
			array(
				'orderTotalAmount'    => round( $qliro_discount_data['total_amount'], 2 ),
				'discountableAmount'  => round( $qliro_discount_data['available_amount'], 2 ),
				'actionUrl'           => $qliro_discount_data['action_url'],
				'previousDiscountIds' => $qliro_discount_data['fees'],
				true,
				'i18n'                => array(
					'invalidDiscountId' => __( 'Please enter a unique Discount ID.', 'qliro-for-woocommerce' ),
					'invalidAmount'     => __( 'Please enter a valid Discount Amount.', 'qliro-for-woocommerce' ),
					'invalidPercent'    => __( 'Please enter a valid Discount Percent.', 'qliro-for-woocommerce' ),
				),
			)
		);
		wp_enqueue_style( 'qliro-admin-order-discount' );

		include_once QLIRO_WC_PLUGIN_PATH . '/includes/admin/views/html-order-add-discount.php';
	}

	/**
	 * Fix the tax calculations for Qliro discounts on order calculations.
	 *
	 * @param WC_Order_Item_Fee $fee The fee item.
	 *
	 * @return void
	 */
	public function fix_qliro_discount_tax_calculations( $fee ) {
		// Only do this for Qliro discounts.
		if ( ! $fee->get_meta( 'qliro_discount_id' ) ) {
			return;
		}

		$rate_id    = $fee->get_meta( 'qliro_discount_vat_rate_id' );
		$vat_rate   = $fee->get_meta( 'qliro_discount_vat_rate' );
		$vat_amount = $fee->get_meta( 'qliro_discount_vat_amount' );

		// If any metadata is missing, we cannot fix the tax calculations.
		if ( ! isset( $rate_id, $vat_rate, $vat_amount ) ) {
			return;
		}

		$fee->set_taxes(
			array(
				'total'    => array( $rate_id => $vat_amount ),
				'subtotal' => array( $rate_id => $vat_amount ),
			)
		);
		$fee->set_total_tax( $vat_amount );
		$fee->set_tax_status( ( $vat_rate > 0 ) ? 'taxable' : 'none' );
		$fee->save();
	}

	/**
	 * Calculate the discount vat amount.
	 *
	 * @param float $vat_rate The vat rate percentage.
	 * @param float $discount_amount The discount amount.
	 *
	 * @return float|int
	 */
	private function calculate_discount_vat_amount( $vat_rate, $discount_amount ) {
		return $discount_amount * ( $vat_rate / 100 );
	}

	/**
	 * Add the discount to the order as a fee.
	 *
	 * @param string   $discount_id The discount id.
	 * @param int      $rate_id The vat rate id.
	 * @param float    $vat_rate The vat rate percentage.
	 * @param float    $vat_amount The vat amount.
	 * @param float    $discount_amount The discount amount.
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return int The fee item id.
	 */
	private function add_discount_to_order( $discount_id, $rate_id, $vat_rate, $vat_amount, $discount_amount, $order ) {
		$fee = new WC_Order_Item_Fee();
		$fee->add_meta_data( 'qliro_discount_id', $discount_id );
		$fee->add_meta_data( 'qliro_discount_vat_rate_id', $rate_id );
		$fee->add_meta_data( 'qliro_discount_vat_rate', $vat_rate );
		$fee->add_meta_data( 'qliro_discount_vat_amount', $vat_amount );

		$fee->set_amount( $discount_amount );
		$fee->set_total( $discount_amount );
		$fee->set_total_tax( $vat_amount );
		$fee->set_taxes(
			array(
				'total'    => array( $rate_id => $vat_amount ),
				'subtotal' => array( $rate_id => $vat_amount ),
			)
		);
		$fee->set_tax_status( ( $vat_rate > 0 ) ? 'taxable' : 'none' );
		$fee->set_name( $discount_id );
		$fee_id = $fee->save();

		// NOTE! Do not call WC_Order::add_fee(). That method is deprecated, and results in the fee losing all its data when saved to the order, appearing as a generic fee with missing amount.
		$order->add_item( $fee );
		$order->calculate_totals();

		return $fee_id;
	}

	/**
	 * Validate the discount before adding it to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param float    $discount_amount The discount amount.
	 * @param int      $discount_id The discount ID.
	 *
	 * @throws Exception When the discount is invalid.
	 * @return void
	 */
	private function validate_discount( $order, $discount_amount, $discount_id ) {
		$items_total_amount = array_reduce( $order->get_items( 'line_item' ), fn( $total_amount, $item ) => $total_amount + ( floatval( $item->get_total() ) * 100 + floatval( $item->get_total_tax() ) * 100 ) ) ?? 0;

		// Get the amount of any previous Qliro discounts applied to the order so we can exclude that from the available amount.
		$previous_discount_amount = 0;
		foreach ( $order->get_fees() as $fee ) {
			$id = $fee->get_meta( 'qliro_discount_id' );
			if ( ! empty( $id ) ) {
				$previous_discount_amount += ( floatval( $fee->get_total() ) * 100 + floatval( $fee->get_total_tax() ) * 100 );
			}
		}
		$available_amount = $items_total_amount - abs( $previous_discount_amount );

		// Ensure there is actually a discounted amount, and that is less than the total amount.
		if ( ( $discount_amount * 100 ) > ( $available_amount ) ) {
			// translators: %s: Available amount formatted as price.
			throw new Exception( esc_html( sprintf( __( 'Discount amount must be less than the remaining amount of %s.', 'qliro-for-woocommerce' ), wc_price( max( 0, $available_amount ) ) ) ) );
		}

		foreach ( $order->get_fees() as $fee ) {
			// To avoid the form being submitted multiple times, we check if the discount already exists.
			if ( $fee->get_meta( 'qliro_discount_id' ) === $discount_id ) {
				// translators: %s: Discount ID.
				throw new Exception( esc_html( sprintf( __( 'Discount [%s] already added to order.', 'qliro-for-woocommerce' ), $discount_id ) ) );
			}
		}
	}

	/**
	 * Handle the add order discount action request.
	 *
	 * @return void
	 * @throws Exception When something goes wrong while adding the discount.
	 */
	public function handle_add_order_discount_action() {
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Check if this event even concerns us. Just return if its not our action.
		if ( 'qliro_add_order_discount' !== $action ) {
			return;
		}

		// Ensure the nonce is valid.
		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( wp_verify_nonce( $nonce, 'qliro_add_order_discount' ) === false ) {
			$this->handle_error_redirect( 'invalid_nonce', 'metabox_discount' );
		}

		// Get the parameters from the request.
		$order_id         = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key        = filter_input( INPUT_GET, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_amount  = filter_input( INPUT_GET, 'discount_amount', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_id      = filter_input( INPUT_GET, 'discount_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_rate_id = filter_input( INPUT_GET, 'discount_vat_rate', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Ensure we have the needed parameters to create the discount.
		if ( ! isset( $order_id, $order_key, $discount_amount, $discount_id ) ) {
			$this->handle_error_redirect( 'missing_parameters', 'metabox_discount' );
		}

		// Ensure the user has permission to edit shop orders.
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			$this->handle_error_redirect( 'permission_denied', 'metabox_discount' );
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) || 'qliro_one' !== $order->get_payment_method() ) {
			$this->handle_error_redirect( 'not_qliro_order', 'metabox_discount' );
		}

		$order_key = $order->get_order_key();
		if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
			$this->handle_error_redirect( 'invalid_hash', 'metabox_discount' );
		}

		try {
			$discount_amount = floatval( $discount_amount );

			// Description length allowed by Qliro.
			$discount_id = mb_substr( trim( wc_clean( $discount_id ) ), 0, 200 );

			// We must exclude shipping and any fees from the available discount amount.
			$this->validate_discount( $order, $discount_amount, $discount_id );

			// Calculate VAT for the discount.
			$vat_rate   = WC_Tax::get_rate_percent_value( $discount_rate_id ); // Ensure the rate exists.
			$vat_amount = $this->calculate_discount_vat_amount( $vat_rate, $discount_amount );

			// Adjust amounts for fee item.
			$discount_amount -= $vat_amount; // Discount amount excluding VAT.
			$discount_amount *= -1; // Fees are positive amounts, so we invert the discount to be negative.
			$vat_amount      *= -1; // VAT on fees is also negative.

			// Add the fee to the order.
			$fee_id = $this->add_discount_to_order( $discount_id, $discount_rate_id, $vat_rate, $vat_amount, $discount_amount, $order );

			// Since a "shipped" Qliro order cannot be updated, the AddItemsToInvoice endpoint must be used instead.
			if ( qliro_is_fully_captured( $order ) ) {
				$response = QLIRO_WC()->api->add_items_qliro_order( $order_id, array() );
			} else {
				// When updating an order, all items from the preauthorization must be included when updating an order that hasn't been "shipped" yet.
				$items    = array_merge( Qliro_One_Helper_Order::get_order_items( $order_id ) );
				$response = QLIRO_WC()->api->update_items_qliro_order( $order_id, $items );
			}

			if ( is_wp_error( $response ) ) {
				// Remove the fee from the order since the update to Qliro failed.
				$order->remove_item( $fee_id );
				$order->calculate_totals();

				// translators: %1$s: Discount ID, %2$s: Error message.
				throw new Exception( sprintf( __( 'Failed to add discount [%1$s] to Qliro order. Reason: %2$s', 'qliro-for-woocommerce' ), $discount_id, $response->get_error_message() ) );
			}

			// Get the new payment transaction id from the response, and update the order meta with it.
			$transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'] ?? '';
			$order->update_meta_data( '_qliro_payment_transaction_id', $transaction_id );

			// Add order note with the discount id, amount and transaction id to the order.
			$order->add_order_note(
				sprintf(
					// translators: %1$s: Discount ID, %2$s: Discount amount formatted as price, %3$s: Payment transaction id.
					__( 'Added a discount [%1$s] of %2$s to the Qliro order. Payment transaction ID: %3$s', 'qliro-for-woocommerce' ),
					$discount_id,
					wc_price( abs( $discount_amount + $vat_amount ), array( 'currency' => $order->get_currency() ) ),
					$transaction_id
				)
			);
		} catch ( Exception $e ) {
			$order->add_order_note( $e->getMessage() );
		} finally {
			wp_safe_redirect( $order->get_edit_order_url() );
			exit;
		}
	}

	/**
	 * Get the valid tax rates from the order that can be used for the discount.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array{ id: int, class: string, label: string, percentage: float }[]
	 */
	private static function get_order_tax_rates( $order ) {
		/**
		 * The tax rates available in the order.
		 *
		 * @var array{ id: int, class: string, label: string, percentage: float }[]
		 */
		$tax_rates = array();

		/**
		 * Get the tax items from the order and extract the rates.
		 *
		 * @var WC_Order_Item_Tax $tax_item
		 */
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			$rate_id     = $tax_item->get_rate_id();
			$tax_rates[] = array(
				'id'         => $rate_id,
				'class'      => $tax_item->get_tax_class(),
				'label'      => $tax_item->get_name(),
				'percentage' => floatval( WC_Tax::get_rate_percent( $rate_id ) ),
			);
		}

		// Sort the ID based on the percentage, highest to lowest.
		usort(
			$tax_rates,
			function ( $a, $b ) {
				return $b['percentage'] <=> $a['percentage'];
			}
		);

		return $tax_rates;
	}

	/**
	 * Get the item total amount for the order excluding shipping and fees.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return float
	 */
	private static function get_item_total_amount( $order ) {
		return array_reduce( $order->get_items( 'line_item' ), fn( $total_amount, $item ) => $total_amount + ( floatval( $item->get_total() ) + floatval( $item->get_total_tax() ) ) ) ?? 0;
	}

	/**
	 * Get the previous discount amount applied to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return float
	 */
	private static function get_previous_discount_amount( $order ) {
		$previous_discount_amount = 0;
		foreach ( $order->get_fees() as $fee ) {
			$id = $fee->get_meta( 'qliro_discount_id' );
			if ( ! empty( $id ) ) {
				$previous_discount_amount += ( floatval( $fee->get_total() ) + floatval( $fee->get_total_tax() ) );
			}
		}
		return abs( $previous_discount_amount );
	}

	/**
	 * Get the previous fees applied to the order as a json string.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string[]
	 */
	private static function get_previous_fees( $order ) {
		$fees = array();
		foreach ( $order->get_fees() as $fee ) {
			$id = $fee->get_meta( 'qliro_discount_id' );
			if ( ! empty( $id ) ) {
				$fees[] = $id;
			}
		}

		return $fees;
	}

	/**
	 * Get the action url for adding a discount to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return string
	 */
	private static function get_add_discount_action_url( $order ) {
		$action_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'            => 'qliro_add_order_discount',
					'order_id'          => $order->get_id(),
					'order_key'         => $order->get_order_key(),
					'qliro_order_id'    => $qliro_order['OrderId'] ?? '',
					'discount_amount'   => 0,
					'discount_id'       => '',
					'discount_vat_rate' => 0,
				),
				admin_url( 'admin-ajax.php' )
			),
			'qliro_add_order_discount'
		);
		return $action_url;
	}

	/**
	 * Handle error redirect for the order metabox. Exits after redirect.
	 *
	 * @param string $notice The notice code.
	 * @param string $cause The cause of the error.
	 *
	 * @return void
	 */
	private function handle_error_redirect( $notice, $cause ) {
		$redirect_to = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'qliro_metabox_notice' => $notice,
					'cause'                => $cause,
				),
				$redirect_to
			)
		);
		exit;
	}


	/**
	 * Get the data for the modal to add a discount to an order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array{ vat_rates: array<int, string>, action_url: string, items_total_amount: float, available_amount: float, total_amount: float, currency: string, order: WC_Order|null }
	 */
	public static function get_order_discount_modal_data( $order ) {
		$items_total_amount       = self::get_item_total_amount( $order );
		$previous_discount_amount = self::get_previous_discount_amount( $order );
		$available_amount         = $items_total_amount - $previous_discount_amount;
		$total_amount             = wc_format_decimal( $order->get_total() );
		return array(
			'vat_rates'          => self::get_order_tax_rates( $order ),
			'action_url'         => self::get_add_discount_action_url( $order ),
			'items_total_amount' => $items_total_amount,
			'available_amount'   => $available_amount,
			'total_amount'       => $total_amount,
			'fees'               => self::get_previous_fees( $order ),
		);
	}
}

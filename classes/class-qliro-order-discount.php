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
	 * @param array    $qliro_order The Qliro order.
	 *
	 * @return void
	 */
	public static function output_order_discount_button( $order, $qliro_order ) {
		$classes   = 'krokedil_wc__metabox_button krokedil_wc__metabox_action button button-secondary';
		echo "<a id='qliro_add_order_discount' class='{$classes}'>Add Qliro discount</a>";

		$qliro_discount_data = self::get_order_discount_modal_data( $order );

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
			error_log( 'Qliro Order Discount: Not a Qliro discount fee, skipping tax calculations fix.' );
			return;
		}

		$rate_id    = $fee->get_meta( 'qliro_discount_vat_rate_id' );
		$vat_rate   = $fee->get_meta( 'qliro_discount_vat_rate' );
		$vat_amount = $fee->get_meta( 'qliro_discount_vat_amount' );

		// If any metadata is missing, we cannot fix the tax calculations.
		if ( ! isset( $rate_id, $vat_rate, $vat_amount ) ) {
			error_log( 'Qliro Order Discount: Missing metadata for discount tax calculations fix.' );
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
	 * Handle the add order discount action request.
	 *
	 * @return void
	 */
	public function handle_add_order_discount_action() {
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Check if this event even concerns us.
		if ( 'qliro_add_order_discount' !== $action ) {
			return;
		}

		$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( wp_verify_nonce( $nonce, 'qliro_add_order_discount' ) === false ) {
			return;
		}

		$order_id          = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key         = filter_input( INPUT_GET, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_amount   = filter_input( INPUT_GET, 'discount_amount', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_id       = filter_input( INPUT_GET, 'discount_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$discount_vat_rate = filter_input( INPUT_GET, 'discount_vat_rate', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! isset( $order_id, $order_key, $discount_amount, $discount_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			$redirect_to = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' );
			wp_safe_redirect(
				add_query_arg(
					array(
						'qliro_metabox_notice' => 'permission_denied',
						'cause'                => 'metabox_discount',
					),
					$redirect_to
				)
			);
			exit;
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) || 'qliro_one' !== $order->get_payment_method() ) {
			return;
		}

		$order_key = $order->get_order_key();
		if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return;
		}

		try {
			$discount_amount = floatval( $discount_amount );

			// These controls should throw to inform the customer about what happened.
			if ( ! wp_verify_nonce( $nonce, 'qliro_add_order_discount' ) ) {
				throw new Exception( __( 'Invalid nonce.', 'qliro' ) );
			}

			// Description length allowed by Qliro.
			$discount_id = mb_substr( trim( wc_clean( $discount_id ) ), 0, 200 );

			// We must exclude shipping and any fees from the available discount amount.
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
				throw new Exception( sprintf( __( 'Discount amount must be less than the remaining amount of %s.', 'qliro' ), wc_price( max( 0, $available_amount ) ) ) );
			}

			foreach ( $order->get_fees() as $fee ) {
				// To avoid the form being submitted multiple times, we check if the discount already exists.
				if ( $fee->get_meta( 'qliro_discount_id' ) === $discount_id ) {
					// translators: %s: Discount ID.
					throw new Exception( sprintf( __( 'Discount [%s] already added to order.', 'qliro' ), $discount_id ) );
				}
			}

			// Calculate VAT for the discount.
			$rate_id    = $discount_vat_rate;
			$vat_rate   = WC_Tax::get_rate_percent_value( $rate_id ); // Ensure the rate exists.
			$vat_amount = ( $discount_amount * ( $vat_rate / 100 ) );

			$discount_amount -= $vat_amount; // Discount amount excluding VAT.
			$discount_amount *= -1; // Fees are positive amounts, so we invert the discount to be negative.
			$vat_amount	     *= -1; // VAT on fees is also negative.

			// Refer to WC_AJAX::add_order_fee() for how to add a fee to an order.
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
					'total' => array( $rate_id => $vat_amount ),
					'subtotal' => array( $rate_id => $vat_amount ),
				)
			);
			$fee->set_tax_status( ( $vat_rate > 0 ) ? 'taxable' : 'none' );
			$fee->set_name( $discount_id );
			$fee_id = $fee->save();

			// NOTE! Do not call WC_Order::add_fee(). That method is deprecated, and results in the fee losing all its data when saved to the order, appearing as a generic fee with missing amount.
			$order->add_item( $fee );
			$order->calculate_totals();

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
				throw new Exception( sprintf( __( 'Failed to add discount [%1$s] to Qliro order. Reason: %2$s', 'qliro' ), $discount_id, $response->get_error_message() ) );
			}

			// Get the new payment transaction id from the response, and update the order meta with it.
			$transaction_id = $response['PaymentTransactions'][0]['PaymentTransactionId'] ?? '';
			$order->update_meta_data( '_qliro_payment_transaction_id', $transaction_id );

			// translators: %s: Discount ID.
			$order->add_order_note( sprintf( __( 'Discount [%s] added to order.', 'qliro' ), $discount_id ) );
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
		 * @var WC_Order_Item_Tax $tax_item
		 */
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			$tax_rates[] = array(
				'id'        => $rate_id,
				'class'     => $tax_item->get_tax_class(),
				'label'     => $tax_item->get_name(),
				'percentage'=> floatval( WC_Tax::get_rate_percent( $rate_id ) ),
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
	 * @return string
	 */
	private static function get_previous_fees_json( $order ) {
		$fees = array();
		foreach ( $order->get_fees() as $fee ) {
			$id = $fee->get_meta( 'qliro_discount_id' );
			if ( ! empty( $id ) ) {
				$fees[] = $id;
			}
		}

		return json_encode( $fees );
	}

	/**
	 * Get the action url for adding a discount to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array $qliro_order The Qliro order.
	 *
	 * @return string
	 */
	private static function get_add_discount_action_url( $order ) {
		$action_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'qliro_add_order_discount',
					'order_id'        => $order->get_id(),
					'order_key'       => $order->get_order_key(),
					'qliro_order_id'  => $qliro_order['OrderId'] ?? '',
					'discount_amount' => 0,
					'discount_id'     => '',
				),
				admin_url( 'admin-ajax.php' )
			),
			'qliro_add_order_discount'
		);
		return $action_url;
	}

	/**
	 * Get the discount id section fields.
	 *
	 * @return array
	 */
	public static function get_discount_id_section_fields() {
		return array(
			'section_title' => array(
				'name' => '',
				'type' => 'title',
			),
			'discount_id'   => array(
				'name'     => __( 'Discount ID', 'qliro-one-for-woocommerce' ),
				'desc_tip' => true,
				'desc'     => __( 'Contains article number and discount number. E.g. articleno_discount01', 'qliro-one-for-woocommerce' ),
				'id'       => 'qliro-discount-id',
				'type'     => 'text',
			),
			'section_end'   => array(
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Get the discount amount section fields.
	 *
	 * @param string $currency The currency code.
	 * @param float  $total_amount The total amount of the order.
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array
	 */
	public static function get_discount_amount_section_fields( $currency, $total_amount, $order ) {
		$vat_rates = self::get_order_tax_rates( $order );
		$vat_options = array();
		foreach ( $vat_rates as $rate ) {
			$id                 = $rate['class'] . $rate['id'];
			$vat_options[ $id ] = wc_format_decimal( $rate['percentage'], 2 ) . '%';
		}

		$section =  array(
			'section_title'       => array(
				'name' => __( 'Enter amount or percentage', 'qliro-one-for-woocommerce' ),
				'type' => 'title',
			),
			'discount_amount'     => array(
				// translators: %s: Currency code, e.g. SEK.
				'name'              => sprintf( __( 'Total amount (%s) incl. VAT', 'qliro-one-for-woocommerce' ), $currency ),
				'id'                => 'qliro-discount-amount',
				'type'              => 'number',
				'placeholder'       => $currency,
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0.00',
					'max'  => $total_amount,
				),
			),
			'discount_percentage' => array(
				'name'              => __( 'Percentage (%)', 'qliro-one-for-woocommerce' ),
				'id'                => 'qliro-discount-percentage',
				'type'              => 'number',
				'placeholder'       => '%',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0.00',
					'max'  => '100.00',
				),
			),
			'vat_rate'            => array(
				'name' => __( 'VAT rate (%)', 'qliro-one-for-woocommerce' ),
				'id'   => 'qliro-discount-vat-rate',
				'type' => 'select',
				'options' => $vat_options,
			),
			'section_end'         => array(
				'type' => 'sectionend',
			),
		);

		if ( count( $vat_options ) <= 1 ) {
			unset( $section['vat_rate'] );
		}

		return $section;
	}

	/**
	 * Get the summary section fields.
	 *
	 * @param string $currency The currency code.
	 * @param float  $items_total_amount The total amount of the order items.
	 *
	 * @return array
	 */
	public static function get_summary_section_fields( $currency, $items_total_amount ) {
		return array(
			'section_title'           => array(
				'name' => __( 'New amount to pay', 'qliro-one-for-woocommerce' ),
				'type' => 'title',
			),
			'total_amount'            => array(
				'name'              => __( 'Total amount before discount', 'qliro-one-for-woocommerce' ),
				'id'                => 'qliro-total-amount',
				'type'              => 'text',
				'value'             => wp_strip_all_tags( wc_price( $items_total_amount, array( 'currency' => $currency ) ) ),
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			'new_discount_percentage' => array(
				'name'              => __( 'Discount', 'qliro-one-for-woocommerce' ),
				'id'                => 'qliro-new-discount-percentage',
				'type'              => 'text',
				'value'             => '0%',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			'new_total_amount'        => array(
				'name'              => __( 'New total amount to pay', 'qliro-one-for-woocommerce' ),
				'id'                => 'qliro-new-total-amount',
				'type'              => 'text',
				'value'             => wp_strip_all_tags( wc_price( $items_total_amount, array( 'currency' => $currency ) ) ),
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			'section_end'             => array(
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Get the data for the modal to add a discount to an order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array{ action_url: string, items_total_amount: float, available_amount: float, total_amount: float, currency: string, order: WC_Order|null }
	 */
	public static function get_order_discount_modal_data( $order ) {
		$items_total_amount 	  = self::get_item_total_amount( $order );
		$previous_discount_amount = self::get_previous_discount_amount( $order );
		$available_amount    	  = $items_total_amount - $previous_discount_amount;
		$total_amount        	  = wc_format_decimal( $order->get_total() );
		$currency				  = $order->get_currency();
		return array(
			'action_url'         => self::get_add_discount_action_url( $order ),
			'items_total_amount' => $items_total_amount,
			'available_amount'   => $available_amount,
			'total_amount'       => $total_amount,
			'currency'           => $currency,
			'fees'               => self::get_previous_fees_json( $order ),
			'order'              => $order,
		);
	}
}

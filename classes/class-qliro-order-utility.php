<?php
/**
 * A utility class to get data from a Qliro order after it has been placed.
 *
 * @package  Qliro_One_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_Order_Utility
 */
class Qliro_Order_Utility {
	public const TRANSACTION_TYPE_DEBIT   = 'Debit';
	public const TRANSACTION_TYPE_PREAUTH = 'Preauthorization';
	public const TRANSACTION_TYPE_CAPTURE = 'Capture';

	public const PROCESSING_TRANSACTION_TYPES = array(
		self::TRANSACTION_TYPE_DEBIT,
		self::TRANSACTION_TYPE_PREAUTH,
	);

	/**
	 * Get the total amount of the Qliro order.
	 *
	 * @param array $qliro_order The Qliro order.
	 *
	 * @return float|int The total amount of the Qliro order.
	 */
	public static function get_qliro_order_total( $qliro_order ) {
		// Loop each transaction and get the sum of all the debit transactions.
		$total        = 0;
		$transactions = $qliro_order['PaymentTransactions'] ?? array();
		foreach ( $transactions as $transaction ) {
			$type   = $transaction['Type'] ?? '';
			$status = $transaction['Status'] ?? '';

			// Only consider successful processing transactions that have been successful.
			if ( ! in_array( $type, self::PROCESSING_TRANSACTION_TYPES, true ) || 'Success' !== $status ) {
				continue;
			}

			$total += floatval( $transaction['Amount'] ?? 0 );
		}
		return $total;
	}

	/**
	 * Get the currency of the Qliro order.
	 *
	 * @param array $qliro_order The Qliro order.
	 *
	 * @return string The currency of the Qliro order.
	 */
	public static function get_qliro_order_currency( $qliro_order ) {
		return $qliro_order['Currency'] ?? '';
	}

	/**
	 * Get the last transaction from the Qliro order.
	 *
	 * @param array $qliro_order The Qliro order.
	 *
	 * @return array The last transaction of the Qliro order.
	 */
	public static function get_last_transaction( $qliro_order ) {
		$transactions = $qliro_order['PaymentTransactions'] ?? array();

		// Sort the transactions based on the timestamp.
		usort(
			$transactions,
			function ( $a, $b ) {
				return strtotime( $a['Timestamp'] ?? '' ) - strtotime( $b['Timestamp'] ?? '' );
			}
		);

		// Get the last transaction.
		$last_transaction = end( $transactions );

		return $last_transaction;
	}

	/**
	 * Get the type of a Qliro transaction.
	 *
	 * @param array $transaction The Qliro transaction.
	 *
	 * @return string The type of the Qliro transaction.
	 */
	public static function get_transaction_type( $transaction ) {
		return $transaction['Type'] ?? __( 'Not found', 'qliro-one-for-woocommerce' );
	}

	/**
	 * Get the status of a Qliro transaction.
	 *
	 * @param array $transaction The Qliro transaction.
	 *
	 * @return string The status of the Qliro transaction.
	 */
	public static function get_transaction_status( $transaction ) {
		return $transaction['Status'] ?? __( 'Order status was not found.', 'qliro-one-for-woocommerce' );
	}

	/**
	 * Get all PaymentTransactionIds for a Qliro order.
	 *
	 * @param array    $qliro_order The Qliro order.
	 * @param string[] $types Optional. The types of transactions to filter by.
	 *
	 * @return string[] An array of PaymentTransactionIds.
	 */
	public static function get_payment_transaction_ids( $qliro_order, $types = array() ) {
		$transaction_ids = array();
		$transactions    = $qliro_order['PaymentTransactions'] ?? array();

		foreach ( $transactions as $transaction ) {
			$type                   = $transaction['Type'] ?? '';
			$payment_transaction_id = $transaction['PaymentTransactionId'] ?? '';

			if ( empty( $payment_transaction_id ) ) {
				continue;
			}

			if ( ! empty( $types ) && ! in_array( $type, $types, true ) ) {
				continue;
			}

			$transaction_ids[] = $payment_transaction_id;
		}

		return $transaction_ids;
	}

	/**
	 * Get all the transaction ids for the successful transaction ids for transactions from the payment.
	 *
	 * @param array $transactions The Qliro transactions [.
	 *      @type array {
	 *          @type string $PaymentTransactionId The payment transaction id.
	 *          @type string $Type The type of the transaction.
	 *          @type string $Status The status of the transaction.
	 *      }
	 * ]
	 * @return array
	 */
	private static function get_successful_transaction_ids( $transactions ) {
		$transaction_ids = array();
		foreach ( $transactions as $transaction ) {
			$status = self::get_transaction_status( $transaction );
			// Skip any transactions that were not successful.
			if ( 'Success' !== $status ) {
				continue;
			}
			$transaction_ids[] = $transaction['PaymentTransactionId'] ?? '';
		}

		return $transaction_ids;
	}

	/**
	 * Get all order lines for a specific payment transaction id from a Qliro order.
	 *
	 * @param array  $order_lines The Qliro order lines.
	 * @param string $payment_transaction_id The payment transaction id.
	 *
	 * @return array The filtered order lines for the specific payment transaction id.
	 */
	private static function get_qliro_transaction_order_lines( $order_lines, $payment_transaction_id ) {
		return array_filter(
			$order_lines,
			function ( $line ) use ( $payment_transaction_id ) {
				$line_transaction_id = $line['PaymentTransactionId'] ?? '';
				// Filter for lines that belong to this transaction.
				return ( $line_transaction_id === $payment_transaction_id );
			}
		);
	}

	/**
	 * Format the order lines for a specific payment transaction id from a Qliro order.
	 *
	 * @param array $transaction_order_lines The Qliro order lines for a specific payment transaction id.
	 *
	 * @return array[] The formatted order lines. [
	 *      @type array {
	 *          @type string $reference     The order line reference. Either an SKU, shipping, fee or discount reference.
	 *          @type string $type          The order line type. One of: Product, Shipping, Fee, Discount.
	 *          @type int    $qliro_line_id The Qliro order line id.
	 *          @type int    $quantity      The quantity items for the order line.
	 *      }
	 *  ]
	 */
	private static function format_qliro_transaction_order_lines( $transaction_order_lines ) {
		$formatted_transaction_order_lines = array_map(
			function ( $line ) {
				return array(
					'reference'     => $line['MerchantReference'] ?? '',
					'qliro_line_id' => $line['Id'] ?? 0,
					'quantity'      => $line['Quantity'] ?? 0,
					'type'          => $line['Type'] ?? '',
				);
			},
			$transaction_order_lines
		);

		// Re-index the array to have sequential keys and return.
		return array_values( $formatted_transaction_order_lines );
	}

	/**
	 * Format the transactions meta data array from the Qliro order and list of transactions.
	 *
	 * @param string $current_hash The current hash of the transactions.
	 * @param array  $qliro_order  The Qliro order.
	 * @param array  $transactions The Qliro transactions.
	 *
	 * @return array[] The formatted transactions meta data. {
	 *      @type string  $hash         A hash of the transactions to quickly check if an update is needed.
	 *      @type array[] $transactions An array of payment transaction ids. [
	 *          @type array {
	 *              @type string  $transaction_id The payment transaction id.
	 *              @type string  $type           The type of the transaction.
	 *              @type array[] $order_lines    An array of order lines associated with the payment transaction id. [
	 *                  @type array {
	 *                      @type string $reference     The order line reference. Either an SKU, shipping, fee or discount reference.
	 *                      @type string $type          The order line type. One of: Product, Shipping, Fee, Discount.
	 *                      @type int    $qliro_line_id The Qliro order line id.
	 *                      @type int    $quantity      The quantity items for the order line.
	 *                  }
	 *              ]
	 *          }
	 *      ]
	 * }
	 */
	private static function format_transactions_meta_data( $current_hash, $qliro_order, $transactions ) {
		$transactions_meta = array(
			'hash'         => $current_hash,
			'transactions' => array(),
		);

		// Loop each order line in the Qliro order, and set them to the correct transaction.
		$order_lines = $qliro_order['OrderItemActions'] ?? array();
		foreach ( $transactions as $transaction ) {
			$type   = self::get_transaction_type( $transaction );
			$status = self::get_transaction_status( $transaction );

			// Only consider successful transactions.
			if ( 'Success' !== $status ) {
				continue;
			}

			$payment_transaction_id = $transaction['PaymentTransactionId'] ?? '';

			// Get all order lines for this transaction and format them correctly for the meta data.
			$transaction_order_lines           = self::get_qliro_transaction_order_lines( $order_lines, $payment_transaction_id );
			$formatted_transaction_order_lines = self::format_qliro_transaction_order_lines( $transaction_order_lines );

			// Create an entry for the transaction with its order lines.
			$transaction_entry = array(
				'transaction_id' => $payment_transaction_id,
				'type'           => $type,
				'order_lines'    => $formatted_transaction_order_lines,
			);

			$transactions_meta['transactions'][] = $transaction_entry;
		}

		return $transactions_meta;
	}

	/**
	 * Maybe update the WooCommerce order metadata for the Qliro orders transactions and their order lines.
	 *
	 * @param array    $qliro_order The Qliro order.
	 * @param WC_Order $wc_order The WooCommerce order.
	 *
	 * @return void
	 */
	public static function maybe_update_transaction_meta( $qliro_order, $wc_order ) {
		$needs_update = false;
		/**
		 * The Qliro payment transactions metadata stored to help with order management for orders with multiple transactions.
		 *
		 * @var array|null $transactions_meta The transactions meta if it exists, @see Qliro_Order_Utility::format_transactions_meta_data() for the format.
		 */
		$transactions_meta = $wc_order->get_meta( '_qliro_payment_transactions' );
		$transactions      = $qliro_order['PaymentTransactions'] ?? array();

		// Get all the ids of the transactions in the Qliro order to create a hash.
		$transaction_ids = self::get_successful_transaction_ids( $transactions );

		// Get the hash of the current transactions and calculate the new hash.
		$meta_hash    = $transactions_meta['hash'] ?? '';
		$current_hash = md5( implode( ',', $transaction_ids ) );

		// If the hash is different, we need to update the metadata.
		$needs_update = ( $meta_hash !== $current_hash );

		// If we don't need to update, just return.
		if ( ! $needs_update ) {
			return;
		}

		// Format the new transactions meta.
		$transactions_meta = self::format_transactions_meta_data( $current_hash, $qliro_order, $transactions );

		// Update the WooCommerce order meta. Also store it as metadata in JSON format to make it easier to read in some cases.
		$wc_order->update_meta_data( '_qliro_payment_transactions', $transactions_meta );
		$wc_order->update_meta_data( '_qliro_payment_transactions_json', wp_json_encode( $transactions_meta ) );
		$wc_order->save_meta_data();
	}

	/**
	 * Convert the order items for an admin action into split order items for each transaction id.
	 *
	 * @param array    $order_items The order items for an admin action request to possibly be split.
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param string[] $transaction_types Optional. The transaction types to filter by.
	 *
	 * @return array|false
	 */
	public static function maybe_convert_to_split_transactions( $order_items, $wc_order, $transaction_types = self::PROCESSING_TRANSACTION_TYPES ) {
		$transactions_meta = $wc_order->get_meta( '_qliro_payment_transactions' );

		if ( empty( $transactions_meta ) || ! is_array( $transactions_meta ) ) {
			return false;
		}

		// Get the map of order items by their MerchantReference for quick lookup.
		$items_map          = self::build_item_reference_map( $order_items );
		$split_transactions = array();
		$transactions       = $transactions_meta['transactions'] ?? array();

		// Loop each transaction and try to satisfy its order lines from the initial order items.
		foreach ( $transactions as $transaction ) {
			// Get the split transaction for this transaction.
			$split_transaction = self::process_single_transaction(
				$transaction,
				$order_items, // Note: passed by reference (&) to track quantity depletion between all the transactions.
				$items_map,
				$transaction_types
			);

			// If we got a valid split transaction, add it to the list.
			if ( ! empty( $split_transaction ) ) {
				$split_transactions[] = $split_transaction;
			}
		}

		// Process any remaining order items that still have quantities left and assign them to the first shipment.
		self::assign_remaining_items( $split_transactions, $order_items );

		return $split_transactions;
	}

	/**
	 * Builds a map of MerchantReferences to their array keys in the order items list.
	 *
	 * @param array $order_items The order items to build the map from.
	 *
	 * @return array
	 */
	private static function build_item_reference_map( $order_items ) {
		$map = array();
		foreach ( $order_items as $key => $item ) {
			$reference = $item['MerchantReference'] ?? '';
			if ( ! isset( $map[ $reference ] ) ) {
				$map[ $reference ] = array();
			}
			$map[ $reference ][] = $key;
		}
		return $map;
	}

	/**
	 * Processes a single transaction entry against the order items for a request.
	 * IMPORTANT: $order_items is passed by reference (&) to track quantity depletion.
	 *
	 * @param array $transaction The transaction entry to process.
	 * @param array $order_items       The order items to modify, passed by reference.
	 * @param array $items_map         The pre-built map of MerchantReferences to order item keys.
	 * @param array $allowed_types     The allowed transaction types to process.
	 *
	 * @return array|null
	 */
	private static function process_single_transaction( $transaction, &$order_items, $items_map, $allowed_types ) {
		$type = $transaction['type'] ?? '';

		// If this transaction type is not allowed, skip it and return null.
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return null;
		}

		$t_id              = $transaction['transaction_id'] ?? '';
		$t_order_lines     = $transaction['order_lines'] ?? array();
		$transaction_lines = array();

		foreach ( $t_order_lines as $t_order_line ) {
			$t_reference = $t_order_line['reference'] ?? '';
			$t_quantity  = $t_order_line['quantity'] ?? 0;

			// If the reference does not exist in our order items or the quantity for the transaction line is zero, skip it.
			if ( ! isset( $items_map[ $t_reference ] ) || $t_quantity <= 0 ) {
				continue;
			}

			// Look up the item in our pre-built map and set the quantities to match the transaction line.
			foreach ( $items_map[ $t_reference ] as $order_item_key ) {
				// Break the loop if we've satisfied the transaction line quantity to avoid adding a line item with zero quantity.
				if ( $t_quantity <= 0 ) {
					break;
				}

				$current_item_qty = $order_items[ $order_item_key ]['Quantity'] ?? 0;

				// If the current item quantity is zero, skip it.
				if ( $current_item_qty <= 0 ) {
					continue;
				}

				// Get the quantity we can actually take from this item for the transaction line by taking the smaller of the two.
				$qty_to_take = min( $current_item_qty, $t_quantity );

				// Create the line for this shipment.
				$line_data             = $order_items[ $order_item_key ];
				$line_data['Quantity'] = $qty_to_take;
				$transaction_lines[]   = $line_data;

				// Update the main list (via reference) and local counter.
				$order_items[ $order_item_key ]['Quantity'] -= $qty_to_take;
				$t_quantity                                 -= $qty_to_take;
			}
		}

		// If we have no lines for this transaction, return null.
		if ( empty( $transaction_lines ) ) {
			return null;
		}

		return array(
			'PaymentTransactionId' => $t_id,
			'OrderItems'           => $transaction_lines,
		);
	}

	/**
	 * Appends any unassigned order items to the first shipment.
	 *
	 * @param array $shipments Array of shipments to modify, passed by reference.
	 * @param array $order_items The original order items with remaining quantities.
	 *
	 * @return void
	 */
	private static function assign_remaining_items( &$shipments, $order_items ) {
		// Ensure we have at least one shipment to add the remaining items to.
		if ( empty( $shipments ) ) {
			return;
		}

		foreach ( $order_items as $order_item ) {
			if ( ( $order_item['Quantity'] ?? 0 ) > 0 ) {
				$shipments[0]['OrderItems'][] = $order_item;
			}
		}
	}
}

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
		$total = 0;
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
	 * @param array  $qliro_order The Qliro order.
	 * @param string[] $types Optional. The types of transactions to filter by.
	 *
	 * @return string[] An array of PaymentTransactionIds.
	 */
	public static function get_payment_transaction_ids( $qliro_order, $types = array() ) {
		$transaction_ids = array();
		$transactions    = $qliro_order['PaymentTransactions'] ?? array();

		foreach ( $transactions as $transaction ) {
			$type = $transaction['Type'] ?? '';
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
	 * @param array $transactions The Qliro transactions. [
	 * 		@type array {
	 *  		@type string $PaymentTransactionId The payment transaction id.
	 *  	 	@type string $Type The type of the transaction.
	 *  	 	@type string $Status The status of the transaction.
	 * 		}
	 * ]
	 * @return array
	 */
	private static function get_successful_transaction_ids( $transactions ) {
		$transaction_ids = array();
	    foreach ( $transactions as $transaction ) {
	    	$type   = self::get_transaction_type( $transaction );
	    	$status = self::get_transaction_status( $transaction );
	    	// Skip any transactions that are not of type Debit or Preauthorization and not successful.
	    	if ( ! in_array( $type, self::PROCESSING_TRANSACTION_TYPES, true ) && 'Success' !== $status ) {
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
	 * 		@type array {
	 * 			@type string $reference     The order line reference. Either an SKU, shipping, fee or discount reference.
	 * 			@type string $type          The order line type. One of: Product, Shipping, Fee, Discount.
	 * 			@type int    $qliro_line_id The Qliro order line id.
	 * 			@type int    $quantity      The quantity items for the order line.
	 * 		}
	 * 	]
	 */
	private static function format_qliro_transaction_order_lines( $transaction_order_lines ) {
	    $formatted_transaction_order_lines =  array_map(
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
	 * 		@type string  $hash         A hash of the transactions to quickly check if an update is needed.
	 * 		@type array[] $transactions An array of payment transaction ids. [
	 *			@type array {
	 * 				@type string  $transaction_id The payment transaction id.
	 * 				@type string  $type           The type of the transaction.
	 * 				@type array[] $order_lines    An array of order lines associated with the payment transaction id. [
	 * 					@type array {
	 * 						@type string $reference     The order line reference. Either an SKU, shipping, fee or discount reference.
	 * 						@type string $type          The order line type. One of: Product, Shipping, Fee, Discount.
	 * 						@type int    $qliro_line_id The Qliro order line id.
	 * 						@type int    $quantity      The quantity items for the order line.
	 * 					}
	 * 				]
	 * 			}
	 * 		]
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
	    	$transaction_order_lines           = self::get_qliro_transaction_order_lines($order_lines, $payment_transaction_id);
	    	$formatted_transaction_order_lines = self::format_qliro_transaction_order_lines($transaction_order_lines);

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
	 * @param array $qliro_order The Qliro order.
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
			//return;
		}

		// Format the new transactions meta.
		$transactions_meta = self::format_transactions_meta_data( $current_hash, $qliro_order, $transactions );

		// Update the WooCommerce order meta. Also store it as metadata in JSON format to make it easier to read in some cases.
		$wc_order->update_meta_data( '_qliro_payment_transactions', $transactions_meta );
		$wc_order->update_meta_data( '_qliro_payment_transactions_json', wp_json_encode( $transactions_meta ) );
		$wc_order->save_meta_data();
	}

	/**
	 * Convert the order items for a admin action into split order items for each transaction id.
	 *
	 * @param array    $order_items The order items for a admin action request to possibly be split.
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param string[] $transaction_types Optional. The transaction types to filter by.
	 *
	 * @return array|false The converted shipments with the payment transaction id set, or false if no conversion was possible.
	 */
	public static function maybe_convert_to_split_transactions( $order_items, $wc_order, $transaction_types = self::PROCESSING_TRANSACTION_TYPES ) {
		// Try to get the metadata from the WooCommerce order that was stored when reading the Qliro order.
		$transactions_meta = $wc_order->get_meta( '_qliro_payment_transactions' );

		// If we could not get any metadata, return false to indicate no conversion was possible.
		if ( empty( $transactions_meta ) || ! is_array( $transactions_meta ) ) {
			return false;
		}
		$shipments = array();

		// Ensure each order line ends up in a shipment with the correct payment transaction id set, and the quantities match for the transaction based on the metadata from the WooCommerce order.
		foreach ( $transactions_meta['transactions'] as $transaction_entry ) {
			$type                   = $transaction_entry['type'] ?? '';
			// Skip any transactions that are not of the specified types.
			if ( ! in_array( $type, $transaction_types, true ) ) {
				continue;
			}

			$payment_transaction_id = $transaction_entry['transaction_id'] ?? '';
			$transaction_order_lines = $transaction_entry['order_lines'] ?? array();

			$shipment_lines = array();

			// Loop each order item and see if it belongs to this transaction.
			foreach ( $order_items as $key => $order_item ) {
				$item_reference = $order_item['MerchantReference'] ?? '';
				$item_quantity  = $order_item['Quantity'] ?? 0;

				// If the quantity is zero, then we can skip this item.
				if ( $item_quantity <= 0 ) {
					continue;
				}

				// Find the matching order line in the transaction order lines.
				foreach ( $transaction_order_lines as $transaction_line ) {
					$transaction_reference = $transaction_line['reference'] ?? '';
					$transaction_quantity  = $transaction_line['quantity'] ?? 0;

					// If the references match, update the item with the quantity for this transaction and add it to the shipments lines.
					if ( $item_reference !== $transaction_reference ) {
						continue;
					}

					// Get the correct quantity for this transaction in-case it is lower then the order item quantity.
					$quantity = min( $item_quantity, $transaction_quantity );

					// Update the order item quantity to match the transaction quantity before adding it to the shipment lines.
					$order_item['Quantity'] = $quantity;
					$shipment_lines[]       = $order_item;

					// Reduce the quantity of the original order item to reflect that part of it has been assigned to this transaction.
					$item_quantity -= $quantity;
				}
				// Update the original order items quantity to reflect any remaining quantity that has not been assigned to a transaction yet.
				$order_items[ $key ]['Quantity'] = $item_quantity;
			}

			// If we have shipment lines for this transaction, create a shipment entry.
			if ( ! empty( $shipment_lines ) ) {
				$shipments[] = array(
					'PaymentTransactionId' => $payment_transaction_id,
					'OrderItems'           => $shipment_lines,
				);
			}
		}

		// If we have any order items that still have a quantity over zero, we need to add them to the first shipment as a fallback.
		foreach ( $order_items as $order_item ) {
			$item_quantity = $order_item['Quantity'] ?? 0;

			if ( $item_quantity <= 0 ) {
				continue;
			}

			// Add the remaining order item to the first shipment.
			if ( ! empty( $shipments ) ) {
				$shipments[0]['OrderItems'][] = $order_item;
			}
		}

		return $shipments;
	}
}

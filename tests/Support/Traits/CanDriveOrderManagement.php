<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Canned Qliro admin API responses for order management. Capture, cancel and refund all
 * read the admin order first, so a test that expects the action to happen queues both.
 */
trait CanDriveOrderManagement {

	/**
	 * A successful payment transaction, in the shape the admin order and the
	 * order management responses carry them.
	 */
	protected function qliroTransaction( array $overrides = [] ): array {
		return array_merge(
			[
				'PaymentTransactionId' => (int) self::QLIRO_TRANSACTION_ID,
				'Type'                 => 'Preauthorization',
				'Status'               => 'Success',
				'Amount'               => 250.0,
				'Currency'             => 'SEK',
				'PaymentMethodName'    => 'QLIRO_INVOICE',
				'Timestamp'            => '2026-01-01T12:00:00',
			],
			$overrides
		);
	}

	/**
	 * Queues the answer to `GET checkout/adminapi/v2/orders/{id}`: by default an order
	 * with one successful preauthorization of 250.00.
	 */
	protected function willRetrieveAdminOrder( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'OrderId'             => (int) self::QLIRO_ORDER_ID,
					'MerchantReference'   => 'merchant-reference-1',
					'Currency'            => 'SEK',
					'Customer'            => [ 'Email' => 'karl@example.com' ],
					'PaymentTransactions' => [ $this->qliroTransaction() ],
					'OrderItemActions'    => [],
				],
				$overrides
			),
			200,
			'adminapi/v2/orders/'
		);
	}

	/** Queues the answer to an order management call, which echoes a new transaction. */
	private function willRespondWithTransaction( string $endpoint, string $transaction_id, string $type ): void {
		$this->willRespondWith(
			[
				'PaymentTransactions' => [
					[
						'PaymentTransactionId' => (int) $transaction_id,
						'Type'                 => $type,
						'Status'               => 'Created',
					],
				],
			],
			200,
			$endpoint
		);
	}

	/** Queues a successful `MarkItemsAsShipped`. */
	protected function willCapture( string $transaction_id = '1111111' ): void {
		$this->willRespondWithTransaction( 'MarkItemsAsShipped', $transaction_id, 'Capture' );
	}

	/** Queues a successful `cancelOrder`. */
	protected function willCancel( string $transaction_id = '2222222' ): void {
		$this->willRespondWithTransaction( 'cancelOrder', $transaction_id, 'Reversal' );
	}

	/** Queues a successful `ReturnItems`. */
	protected function willRefund( string $transaction_id = '3333333' ): void {
		$this->willRespondWithTransaction( 'ReturnItems', $transaction_id, 'Refund' );
	}

	/** Queues a successful `UpdateItems`. */
	protected function willAcceptTheUpdate( string $transaction_id = '4444444' ): void {
		$this->willRespondWithTransaction( 'UpdateItems', $transaction_id, 'Preauthorization' );
	}

	/** Queues a successful `updatemerchantreference`. */
	protected function willUpdateMerchantReference( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge( [ 'PaymentTransactionId' => (int) self::QLIRO_TRANSACTION_ID ], $overrides ),
			200,
			'updatemerchantreference'
		);
	}
}

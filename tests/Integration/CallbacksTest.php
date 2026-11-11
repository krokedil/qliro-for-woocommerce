<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The scheduled work Qliro's push callbacks hand off to. The push entry points read
 * `php://input` and exit, so they are reached through the handlers they schedule.
 *
 * @covers \Qliro_One_Callbacks
 */
class CallbacksTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const CONFIRMATION_ID = 'confirmation-id-1';

	public function test_a_completed_checkout_confirms_the_order(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'MerchantReference' => $order->get_order_number() ] );
		$this->willRetrieveAdminOrder( [ 'MerchantReference' => $order->get_order_number() ] );

		$this->callbacks()->complete_checkout( self::CONFIRMATION_ID );

		$this->assertSame( 'processing', $this->statusOf( $order ) );
	}

	public function test_an_unknown_confirmation_id_is_ignored(): void {
		$order = $this->havePlacedOrder();

		$this->callbacks()->complete_checkout( 'someone-elses-id' );

		$this->assertSame( 'pending', $this->statusOf( $order ) );
		$this->assertNoGatewayRequests();
	}

	public function test_a_refused_checkout_fails_the_order(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'PaymentTransactions' => [] ] );

		$this->callbacks()->fail_checkout( self::CONFIRMATION_ID );

		$this->assertSame( 'failed', $this->statusOf( $order ) );
	}

	public function test_a_refused_push_is_ignored_when_qliro_has_a_successful_payment(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder();

		$this->callbacks()->fail_checkout( self::CONFIRMATION_ID );

		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	public function test_an_on_hold_checkout_puts_the_order_on_hold(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'PaymentTransactions' => [] ] );

		$this->callbacks()->onhold_checkout( self::CONFIRMATION_ID );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
	}

	public function test_a_late_on_hold_push_leaves_a_paid_order_alone(): void {
		$order = $this->havePlacedOrder( [ 'paid' => true, 'status' => 'processing' ] );

		$this->callbacks()->onhold_checkout( self::CONFIRMATION_ID );

		$this->assertSame( 'processing', $this->statusOf( $order ) );
		$this->assertNoGatewayRequests();
	}

	/** @dataProvider provide_failed_om_callbacks */
	public function test_a_failed_order_management_callback_puts_the_order_on_hold( string $handler ): void {
		$order = $this->havePlacedOrder( [ 'paid' => true, 'status' => 'processing' ] );

		$this->callbacks()->$handler( self::CONFIRMATION_ID, [ 'Status' => 'Error' ] );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
	}

	/** @return array<string, array{0: string}> */
	public function provide_failed_om_callbacks(): array {
		return [
			'capture' => [ 'complete_capture' ],
			'cancel'  => [ 'complete_cancel' ],
			'refund'  => [ 'complete_refund' ],
		];
	}

	/** @dataProvider provide_successful_om_callbacks */
	public function test_a_successful_order_management_callback_notes_the_order( string $handler, string $note ): void {
		$order = $this->havePlacedOrder( [ 'paid' => true, 'status' => 'processing' ] );

		$this->callbacks()->$handler( self::CONFIRMATION_ID, [ 'Status' => 'Success' ] );

		$this->assertSame( 'processing', $this->statusOf( $order ) );
		$this->assertOrderHasNote( $order, $note );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_successful_om_callbacks(): array {
		return [
			'capture' => [ 'complete_capture', 'The order has been successfully captured by Qliro.' ],
			'cancel'  => [ 'complete_cancel', 'The order has been successfully cancelled by Qliro.' ],
			'refund'  => [ 'complete_refund', 'The order has been successfully refunded by Qliro.' ],
		];
	}

	public function test_the_cancel_ok_status_is_applied_when_set(): void {
		$this->haveGatewayCredentials(
			[
				'om_advanced_settings' => 'yes',
				'cancel_ok_status'     => 'wc-cancelled',
				'cancel_status'        => 'wc-refunded',
			]
		);
		$order = $this->havePlacedOrder( [ 'paid' => true, 'status' => 'processing' ] );

		$this->callbacks()->complete_cancel( self::CONFIRMATION_ID, [ 'Status' => 'Success' ] );

		$this->assertSame( 'cancelled', $this->statusOf( $order ) );
	}

	/** A fresh instance, since the plugin's own reads the settings once at load. */
	private function callbacks(): \Qliro_One_Callbacks {
		return new \Qliro_One_Callbacks();
	}

	/** A 250.00 gateway order Qliro knows by CONFIRMATION_ID. */
	private function havePlacedOrder( array $args = [] ): \WC_Order {
		$order = $this->haveGatewayOrder(
			array_merge(
				[
					'items'  => [ [ $this->haveGatewayOrderProduct(), 2 ] ],
					'status' => 'pending',
				],
				$args
			)
		);
		$order->update_meta_data( '_qliro_one_order_confirmation_id', self::CONFIRMATION_ID );
		$order->save();

		return $order;
	}
}

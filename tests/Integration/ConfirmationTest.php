<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * `qliro_confirm_order()`: what Qliro's answer about a placed order does to it.
 *
 * @covers ::qliro_confirm_order
 * @covers \Qliro_Order_Utility
 */
class ConfirmationTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	public function test_a_paid_order_is_completed_with_the_qliro_order_id(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'MerchantReference' => $order->get_order_number() ] );
		$this->willRetrieveAdminOrder( [ 'MerchantReference' => $order->get_order_number() ] );

		$this->assertTrue( qliro_confirm_order( $order ) );

		$order = $this->reload( $order );
		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( self::QLIRO_ORDER_ID, $order->get_transaction_id() );
		$this->assertSame( 'QLIRO_INVOICE', $order->get_meta( 'qliro_one_payment_method_name' ) );
		$this->assertSame( 'INVOICE', $order->get_meta( 'qliro_one_payment_method_subtype_code' ) );
		$this->assertOrderHasNote( $order, 'Payment via Qliro, Qliro order id: ' . self::QLIRO_ORDER_ID );
		$this->assertGatewayRequestCount( 0, 'updatemerchantreference' );
	}

	public function test_a_differing_merchant_reference_is_updated_to_the_order_number(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'MerchantReference' => 'q1-session-reference' ] );
		$this->willUpdateMerchantReference( [ 'PaymentTransactionId' => 5555555 ] );
		$this->willRetrieveAdminOrder();

		$this->assertTrue( qliro_confirm_order( $order ) );

		$this->assertGatewayRequestCount( 1, 'updatemerchantreference' );
		$this->assertSame( '5555555', (string) $this->reload( $order )->get_meta( '_qliro_payment_transaction_id' ) );
	}

	public function test_a_failed_merchant_reference_update_stops_the_confirmation(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'MerchantReference' => 'q1-session-reference' ] );
		$this->willRejectWith( 'updatemerchantreference', 'Not allowed' );

		$this->assertFalse( qliro_confirm_order( $order ) );

		$this->assertSame( 'pending', $this->statusOf( $order ) );
		$this->assertOrderHasNote( $order, 'There was a problem updating merchant reference' );
	}

	public function test_an_on_hold_preauthorization_puts_the_order_on_hold(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'PaymentTransactions' => [ $this->qliroTransaction( [ 'Status' => 'OnHold' ] ) ] ] );

		$this->assertFalse( qliro_confirm_order( $order ) );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
		$this->assertNull( $this->reload( $order )->get_date_paid() );
	}

	public function test_an_order_without_a_successful_payment_is_left_unpaid(): void {
		$order = $this->havePlacedOrder();
		$this->willRetrieveAdminOrder( [ 'PaymentTransactions' => [ $this->qliroTransaction( [ 'Status' => 'Error' ] ) ] ] );

		$this->assertFalse( qliro_confirm_order( $order ) );

		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	public function test_an_amount_mismatch_puts_the_order_on_hold_unpaid(): void {
		$order = $this->havePlacedOrder();
		$mismatch = [
			'MerchantReference'   => $order->get_order_number(),
			'PaymentTransactions' => [ $this->qliroTransaction( [ 'Amount' => 100.0 ] ) ],
		];
		$this->willRetrieveAdminOrder( $mismatch );
		$this->willRetrieveAdminOrder( $mismatch );

		$this->assertTrue( qliro_confirm_order( $order ) );

		$order = $this->reload( $order );
		$this->assertSame( 'on-hold', $order->get_status() );
		$this->assertSame( 'yes', $order->get_meta( '_qliro_amount_mismatch' ) );
		$this->assertOrderHasNote( $order, 'does not match the order total' );
	}

	public function test_an_already_paid_order_is_not_confirmed_again(): void {
		$order = $this->havePlacedOrder( [ 'paid' => true, 'status' => 'processing' ] );

		$this->assertFalse( qliro_confirm_order( $order ) );

		$this->assertNoGatewayRequests();
	}

	public function test_an_order_flagged_for_a_mismatch_is_not_confirmed_again(): void {
		$order = $this->havePlacedOrder();
		$order->update_meta_data( '_qliro_amount_mismatch', 'yes' );
		$order->save();

		$this->assertFalse( qliro_confirm_order( $order ) );

		$this->assertNoGatewayRequests();
	}

	public function test_an_unreachable_api_leaves_the_order_unpaid(): void {
		$order = $this->havePlacedOrder();

		$this->assertFalse( qliro_confirm_order( $order ) );

		$this->assertSame( 'pending', $this->statusOf( $order ) );
	}

	/** A gateway order for 2 x 100.00 + 25% VAT (250.00), placed but not yet confirmed. */
	private function havePlacedOrder( array $args = [] ): \WC_Order {
		return $this->haveGatewayOrder(
			array_merge(
				[
					'items'  => [ [ $this->haveGatewayOrderProduct(), 2 ] ],
					'status' => 'pending',
				],
				$args
			)
		);
	}
}

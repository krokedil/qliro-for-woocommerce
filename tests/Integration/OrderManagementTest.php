<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Capture, cancel and refund: when a status change reaches Qliro, what it sends,
 * and what an answer leaves on the order.
 *
 * @covers \Qliro_One_Order_Management
 * @covers \Qliro_One_Capture_Order
 * @covers \Qliro_One_Cancel_Order
 * @covers \Qliro_One_Request_Return_Items
 */
class OrderManagementTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	public function test_completing_a_paid_order_captures_it(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willCapture( '1111111' );

		$order->update_status( 'completed' );

		$this->assertGatewayRequestCount( 1, 'MarkItemsAsShipped' );
		$this->assertSame( '1111111', (string) $this->reload( $order )->get_meta( '_qliro_order_captured' ) );
		$this->assertOrderHasNote( $order, 'Payment transaction id: 1111111' );
	}

	public function test_the_capture_body_matches_the_snapshot(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willCapture();

		$order->update_status( 'completed' );

		$request = $this->gatewayRequestTo( 'MarkItemsAsShipped' );
		$this->assertRequestMatchesSnapshot( $request, 'om-capture-se', $this->volatileIdsOf( $order, $request ) );
	}

	public function test_cancelling_a_paid_order_cancels_it(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willCancel( '2222222' );

		$order->update_status( 'cancelled' );

		$this->assertGatewayRequestCount( 1, 'cancelOrder' );
		$this->assertTrue( (bool) $this->reload( $order )->get_meta( '_qliro_order_canceled' ) );
		$this->assertOrderHasNote( $order, 'Payment transaction id: 2222222' );
	}

	public function test_the_cancel_body_matches_the_snapshot(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willCancel();

		$order->update_status( 'cancelled' );

		$request = $this->gatewayRequestTo( 'cancelOrder' );
		$this->assertRequestMatchesSnapshot( $request, 'om-cancel-se', $this->volatileIdsOf( $order, $request ) );
	}

	/** @dataProvider provide_orders_that_are_left_alone */
	public function test_a_status_change_is_not_sent_for( array $order_args, string $status ): void {
		$order = $this->haveGatewayOrder( $order_args );

		$order->update_status( $status );

		$this->assertNoGatewayRequests();
	}

	/** @return array<string, array{0: array, 1: string}> */
	public function provide_orders_that_are_left_alone(): array {
		return [
			// Only cancelling can reach the unpaid guard: WooCommerce stamps a paid date on completion.
			'an unpaid order being cancelled' => [ [ 'status' => 'on-hold' ], 'cancelled' ],
			'a status that is neither'        => [ [ 'status' => 'processing', 'paid' => true ], 'on-hold' ],
		];
	}

	public function test_an_order_with_sync_disabled_is_left_alone(): void {
		$order = $this->haveCapturableGatewayOrder();
		$order->update_meta_data( '_qliro_order_sync_enabled', 'no' );
		$order->save();

		$order->update_status( 'completed' );

		$this->assertNoGatewayRequests();
	}

	public function test_another_gateways_order_is_left_alone(): void {
		$order = $this->haveCapturableGatewayOrder();
		$order->set_payment_method( 'bacs' );
		$order->save();

		$order->update_status( 'completed' );

		$this->assertNoGatewayRequests();
	}

	public function test_the_capture_status_setting_decides_which_status_captures(): void {
		$this->haveGatewayCredentials( [ 'capture_status' => 'wc-on-hold' ] );
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willCapture();

		$order->update_status( 'on-hold' );

		$this->assertGatewayRequestCount( 1, 'MarkItemsAsShipped' );
	}

	public function test_a_fully_captured_order_is_not_captured_again(): void {
		$order = $this->haveCapturableGatewayOrder();
		$order->update_meta_data( '_qliro_order_captured', '1111111' );
		$order->save();

		$order->update_status( 'completed' );

		$this->assertGatewayRequestCount( 0, 'MarkItemsAsShipped' );
	}

	public function test_a_rejected_capture_puts_the_order_on_hold(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willRejectWith( 'MarkItemsAsShipped', 'Evaluation, The amount is too high' );

		$order->update_status( 'completed' );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
		$this->assertOrderHasNote( $order, 'The order failed to be captured with Qliro: The amount is too high.' );
	}

	public function test_a_capture_qliro_already_made_is_recorded_rather_than_failed(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willRejectWith( 'MarkItemsAsShipped', 'Nothing left', 400, [ 'ErrorCode' => 'NO_ITEMS_LEFT_IN_RESERVATION' ] );

		$order->update_status( 'completed' );

		$this->assertSame( 'completed', $this->statusOf( $order ) );
		$this->assertNotEmpty( $this->reload( $order )->get_meta( '_qliro_order_captured' ) );
		$this->assertOrderHasNote( $order, 'The order has already been captured.' );
	}

	public function test_a_rejected_cancel_puts_the_order_on_hold(): void {
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willRejectWith( 'cancelOrder', 'Evaluation, Already shipped' );

		$order->update_status( 'cancelled' );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
		$this->assertOrderHasNote( $order, 'The order failed to be cancelled with Qliro: Already shipped.' );
	}

	public function test_the_capture_pending_status_is_applied_when_set(): void {
		$this->haveGatewayCredentials(
			[
				'om_advanced_settings'   => 'yes',
				'capture_pending_status' => 'on-hold',
			]
		);
		$order = $this->haveCapturableGatewayOrder();
		$this->willRetrieveAdminOrder();
		$this->willCapture();

		$order->update_status( 'completed' );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
	}

	public function test_a_refund_of_a_captured_order_returns_the_items(): void {
		$order = $this->haveCapturedOrder();
		$this->willRetrieveAdminOrder();
		$this->willRefund();

		$refund = $this->haveRefundForItems( $order );
		$result = QLIRO_WC()->order_management->refund( $order->get_id(), $refund->get_amount() );

		$this->assertTrue( $result );
		$request = $this->gatewayRequestTo( 'ReturnItems' );
		$this->assertSame( '1111111', (string) $request['json']['Returns'][0]['PaymentTransactionId'] );
		$this->assertTrue( (bool) $this->reload( $order )->get_meta( '_qliro_order_refunded' ) );
		$this->assertOrderHasNote( $order, 'Processing a refund of' );
	}

	public function test_the_refund_body_matches_the_snapshot(): void {
		$order = $this->haveCapturedOrder();
		$this->willRetrieveAdminOrder();
		$this->willRefund();

		$refund = $this->haveRefundForItems( $order );
		QLIRO_WC()->order_management->refund( $order->get_id(), $refund->get_amount() );

		$request = $this->gatewayRequestTo( 'ReturnItems' );
		$this->assertRequestMatchesSnapshot( $request, 'om-refund-se', $this->volatileIdsOf( $order, $request ) );
	}

	public function test_a_refund_is_refused_when_sync_is_disabled(): void {
		$order = $this->haveCapturedOrder();
		$order->update_meta_data( '_qliro_order_sync_enabled', 'no' );
		$order->save();

		$this->haveRefundForItems( $order );
		$result = QLIRO_WC()->order_management->refund( $order->get_id(), 250 );

		$this->assertWpErrorCode( 'qliro_one_order_sync_disabled', $result );
		$this->assertNoGatewayRequests();
	}

	public function test_a_rejected_refund_is_reported_on_the_order(): void {
		$order = $this->haveCapturedOrder();
		$this->willRetrieveAdminOrder();
		$this->willRejectWith( 'ReturnItems', 'Message: Return amount exceeds captured amount' );

		$refund = $this->haveRefundForItems( $order );
		$result = QLIRO_WC()->order_management->refund( $order->get_id(), $refund->get_amount() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertOrderHasNote( $order, 'Failed to refund the order with Qliro: Return amount exceeds captured amount' );
	}

	/** @dataProvider provide_refundable_states */
	public function test_the_gateway_only_offers_refunds_for_captured_orders( bool $captured, string $sync, bool $expected ): void {
		$order = $captured ? $this->haveCapturedOrder() : $this->haveCapturableGatewayOrder();
		$order->update_meta_data( '_qliro_order_sync_enabled', $sync );
		$order->save();

		$this->assertSame( $expected, ( new \Qliro_One_Gateway() )->can_refund_order( $order ) );
	}

	/** @return array<string, array{0: bool, 1: string, 2: bool}> */
	public function provide_refundable_states(): array {
		return [
			'captured'               => [ true, 'yes', true ],
			'not captured'           => [ false, 'yes', false ],
			'captured, sync disabled' => [ true, 'no', false ],
		];
	}

	/** A paid, fully captured, completed gateway order (2 x 100.00 + 25% VAT). */
	private function haveCapturedOrder(): \WC_Order {
		$order = $this->haveCapturableGatewayOrder();
		$order->update_meta_data( '_qliro_order_captured', '1111111' );
		$order->save();

		// Marked captured first, so completing it sends nothing.
		$order->update_status( 'completed' );

		return $order;
	}

	/** @return array<string, scalar> */
	private function volatileIdsOf( \WC_Order $order, array $request ): array {
		$ids = [ '<request-id>' => $request['json']['RequestId'] ?? '' ];

		foreach ( $order->get_items( [ 'line_item', 'shipping', 'fee' ] ) as $item ) {
			$ids[ '<item-' . $item->get_name() . '>' ] = $item->get_id();
		}

		foreach ( $order->get_refunds() as $refund ) {
			$ids['<refund-id>'] = $refund->get_id();
		}

		$ids['<order-id>'] = $order->get_id();

		return $ids;
	}
}

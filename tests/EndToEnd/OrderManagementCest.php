<?php

namespace Tests\EndToEnd;

use Tests\Support\Data\{TestProducts, TestTaxRates};
use Tests\Support\EndToEndTester;

/**
 * Qliro's own view of an order as WooCommerce captures, refunds and cancels it. The
 * purchase is bought the way CheckoutCest buys one, so a failure in purchase() is the
 * checkout rather than the order management under test.
 */
class OrderManagementCest
{
	/**
	 * The notes a refused operation leaves, checked at the end of each lifecycle. Named
	 * rather than matched on "failed", which also catches the mail WooCommerce cannot send
	 * from the test environment.
	 */
	private const FAILURE_NOTES = [
		'failed to be captured with Qliro',
		'failed to be cancelled with Qliro',
		'Failed to refund the order with Qliro',
		'failed to be captured by Qliro',
		'failed to be cancelled by Qliro',
		'failed to be refunded by Qliro',
	];

	public function can_capture_and_refund_an_order(EndToEndTester $I): void
	{
		$orderId = $this->purchase($I);

		$I->waitForGatewayTransactionSettled($orderId);
		$I->seeGatewayOrderStatusIs('Preauthorization');

		$I->changeOrderStatusTo('completed');
		$I->seeOrderNotes($orderId, [ 'requested to be captured with Qliro' ]);
		$I->seeOrderMeta($orderId, [ '_qliro_order_captured' => null ]);
		$I->seeGatewayOrderStatusIs('Capture');

		// The refund surviving is itself the assertion: wc_create_refund() deletes it again
		// when the gateway answers with a WP_Error, so Qliro took the body.
		$I->refundLineItemViaGateway();
		$I->seeRefundCount($orderId, 1);
		$I->seeOrderMeta($I->grabLatestRefundId($orderId), [ '_refund_amount' => '99.99' ]);
		$I->seeOrderNotes($orderId, [ 'Processing a refund of' ]);

		$I->dontSeeOrderNotes($orderId, self::FAILURE_NOTES);
	}

	public function can_cancel_an_order(EndToEndTester $I): void
	{
		$orderId = $this->purchase($I);

		$I->waitForGatewayTransactionSettled($orderId);
		$I->seeGatewayOrderStatusIs('Preauthorization');

		$I->changeOrderStatusTo('cancelled');
		$I->seeOrderNotes($orderId, [ 'requested to be cancelled with Qliro' ]);
		$I->seeOrderMeta($orderId, [ '_qliro_order_canceled' => '1' ]);
		$I->seeGatewayOrderStatusIs('Reversal');

		$I->dontSeeOrderNotes($orderId, self::FAILURE_NOTES);
	}

	/**
	 * Buys one 25% VAT product as a Swedish customer and returns the order's id once Qliro
	 * has confirmed it, since only a paid order is captured or cancelled.
	 */
	private function purchase(EndToEndTester $I): int
	{
		$I->haveStoreOptionsInDatabase([ 'woocommerce_prices_include_tax' => 'yes' ]);
		$I->haveTaxClassesInDatabase([ TestTaxRates::TAX_RATE_25 ]);
		$I->haveCartWith([ TestProducts::SIMPLE_25 ]);

		$I->amOnCheckoutPageWithGateway();
		$I->fillBillingAddressForm();
		$I->placeOrder();
		$I->waitForThankYouPage();

		$orderId = $I->grabOrderIdFromThankYouPage();
		$I->waitForOrderStatus($orderId, 'wc-processing');

		return $orderId;
	}
}

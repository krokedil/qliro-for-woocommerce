<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Canned Qliro merchant API responses for the purchase itself: creating, reading and
 * updating the Qliro order the checkout iframe renders from.
 */
trait CanDriveCheckout {

	/**
	 * Queues the answer to `POST checkout/merchantapi/orders`. Qliro answers with the new
	 * order's id, and a payment link the pay for order page redirects to.
	 */
	protected function willCreateOrder( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'OrderId'     => (int) self::QLIRO_ORDER_ID,
					'PaymentLink' => 'https://pago.qit.nu/checkout?orderId=' . self::QLIRO_ORDER_ID,
				],
				$overrides
			),
			201,
			'merchantapi/orders'
		);
	}

	/**
	 * The order `GET checkout/merchantapi/orders/{id}` answers with, carrying the keys the
	 * plugin reads. $omit drops keys outright, which `$overrides` cannot.
	 */
	protected function qliroCheckoutOrder( array $overrides = [], array $omit = [] ): array {
		$order = array_merge(
			[
				'OrderId'                => (int) self::QLIRO_ORDER_ID,
				'MerchantReference'      => 'merchant-reference-1',
				'Country'                => 'SE',
				'Currency'               => 'SEK',
				'Language'               => 'sv-se',
				'TotalPrice'             => 125.0,
				'CustomerCheckoutStatus' => 'InProcess',
				'OrderHtmlSnippet'       => '<div id="qliro-one-snippet"></div>',
				'OrderItems'             => [
					[
						'MerchantReference'  => 'qliro-test-product',
						'Description'        => 'Qliro test product',
						'Type'               => 'Product',
						'Quantity'           => 1,
						'PricePerItemIncVat' => 125.0,
						'PricePerItemExVat'  => 100.0,
					],
				],
			],
			$overrides
		);

		return array_diff_key( $order, array_flip( $omit ) );
	}

	/** Queues the answer to `GET checkout/merchantapi/orders/{id}`. */
	protected function willRetrieveOrder( array $overrides = [], array $omit = [] ): void {
		$this->willRespondWith(
			$this->qliroCheckoutOrder( $overrides, $omit ),
			200,
			'merchantapi/orders/' . ( $overrides['OrderId'] ?? self::QLIRO_ORDER_ID )
		);
	}

	/** Queues the answer to `PUT checkout/merchantapi/orders/{id}`, which has no body. */
	protected function willUpdateOrder(): void {
		$this->willRespondWith( [], 200, 'merchantapi/orders/' );
	}
}

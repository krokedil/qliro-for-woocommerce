<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Order lines built from a WooCommerce order (renewals, captures, order updates,
 * discounts and upsells), as opposed to lines built from the cart at checkout.
 *
 * @covers \Qliro_One_Helper_Order
 */
class OrderHelperTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * The VAT rate goes out as a percentage with two decimals, like the checkout sends it.
	 *
	 * @dataProvider provide_tax_rates
	 */
	public function test_the_vat_rate_is_sent_as_a_percentage( string $rate, string $expected ): void {
		$tax_class = $this->haveTaxClass( "VAT {$rate}", $rate );
		$product   = $this->haveSimpleProduct( [ 'tax_class' => $tax_class ] );
		$order     = $this->haveGatewayOrder( [ 'items' => [ [ $product, 1 ] ] ] );

		$items = \Qliro_One_Helper_Order::get_order_items( $order->get_id() );

		$this->assertSame( $expected, $items[0]['VatRate'] );
	}

	public function provide_tax_rates(): array {
		return [
			'25 %' => [ '25.0000', '25.00' ],
			'12 %' => [ '12.0000', '12.00' ],
			'6 %'  => [ '6.0000', '6.00' ],
			'0 %'  => [ '0.0000', '0.00' ],
		];
	}
}

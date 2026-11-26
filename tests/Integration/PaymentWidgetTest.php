<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The [qliro_one_payment_widget] shortcode markup.
 *
 * @covers \Qliro_One_Payment_Widget
 */
class PaymentWidgetTest extends IntegrationTestCase {

	/** @dataProvider provide_amounts */
	public function test_the_amount_attribute_renders_as_a_number( string $amount, string $expected ): void {
		$html = do_shortcode( '[qliro_one_payment_widget amount="' . $amount . '"]' );

		$dom = new \DOMDocument();
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
		$div = $dom->getElementsByTagName( 'div' )->item( 0 );

		$this->assertSame( 1, $dom->childNodes->length, $html );
		$this->assertSame( [ 'class', 'data-amount' ], array_keys( iterator_to_array( $div->attributes ) ), $html );
		$this->assertSame( $expected, $div->getAttribute( 'data-amount' ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_amounts(): array {
		return [
			'decimal'            => [ '1299.50', '1299.5' ],
			'negative'           => [ '-5', '0' ],
			'attribute breakout' => [ "1' onmouseover='alert(1)", '1' ],
			'markup'             => [ '<script>alert(1)</script>', '0' ],
		];
	}
}

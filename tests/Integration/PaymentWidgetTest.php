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
	public function test_the_amount_renders_as_a_non_negative_number_in_the_store_format( string $decimal_separator, string $amount, string $expected ): void {
		update_option( 'woocommerce_price_decimal_sep', $decimal_separator );

		$div = $this->render_widget( $amount );

		$this->assertSame( $expected, $div->getAttribute( 'data-amount' ) );
	}

	/** @return array<string, array{0: string, 1: string, 2: string}> */
	public function provide_amounts(): array {
		return [
			'decimal point' => [ '.', '1299.50', '1299.5' ],
			'decimal comma' => [ ',', '1299,50', '1299.5' ],
			'negative'      => [ '.', '-5', '0' ],
		];
	}

	/** @dataProvider provide_markup */
	public function test_markup_in_the_amount_never_reaches_the_page( string $amount ): void {
		$div = $this->render_widget( $amount );

		foreach ( $div->attributes as $attribute ) {
			$this->assertStringStartsNotWith( 'on', $attribute->name, $div->ownerDocument->saveHTML() );
		}
		$this->assertIsNumeric( $div->getAttribute( 'data-amount' ) );
	}

	/** @return array<string, array{0: string}> */
	public function provide_markup(): array {
		return [
			'single quote breakout' => [ "1' onmouseover='alert(1)" ],
			'double quote breakout' => [ '1" onmouseover="alert(1)' ],
			'script tag'            => [ '<script>alert(1)</script>' ],
		];
	}

	/**
	 * Renders the shortcode and returns its only element.
	 * Delimits with the quote the amount lacks, so the whole value reaches the widget.
	 */
	private function render_widget( string $amount ): \DOMElement {
		$quote     = str_contains( $amount, '"' ) ? "'" : '"';
		$attribute = "amount={$quote}{$amount}{$quote}";
		$this->assertSame( $amount, shortcode_parse_atts( $attribute )['amount'] );

		$html = do_shortcode( "[qliro_one_payment_widget {$attribute}]" );

		$dom = new \DOMDocument();
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
		$this->assertSame( 1, $dom->childNodes->length, $html );

		return $dom->getElementsByTagName( 'div' )->item( 0 );
	}
}

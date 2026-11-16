<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Creating the Qliro order the checkout iframe renders from, and `process_payment()`
 * handing the placed WooCommerce order over to it.
 *
 * @covers \Qliro_One_Request_Create_Order
 * @covers \Qliro_One_Gateway::process_payment
 * @covers \Qliro_One_Merchant_URLS
 */
class CheckoutTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** @dataProvider provide_carts */
	public function test_the_create_body_matches_the_snapshot( string $snapshot, callable $arrange ): void {
		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$arrange( $this );
		$this->willCreateOrder();

		QLIRO_WC()->api->create_qliro_one_order();

		$request = $this->gatewayRequestTo( 'merchantapi/orders' );
		$this->assertRequestMatchesSnapshot(
			$request,
			$snapshot,
			[
				'<merchant-reference>' => WC()->session->get( 'qliro_one_merchant_reference' ),
				'<confirmation-id>'    => WC()->session->get( 'qliro_order_confirmation_id' ),
				// The zone's instance id comes from an autoincrement the rollback does not reset.
				'<shipping-rate>'      => WC()->session->get( 'chosen_shipping_methods' )[0] ?? '',
			] + $this->callbackTokensIn( $request )
		);
	}

	/** @return array<string, array{0: string, 1: callable}> */
	public function provide_carts(): array {
		return [
			'one taxed product'   => [
				'create-se-b2c',
				static function ( self $test ): void {
					$test->haveCartWith( [ [ $test->haveSnapshotProduct(), 2 ] ] );
				},
			],
			'with a cart fee'     => [
				'create-cart-fee',
				static function ( self $test ): void {
					$test->haveCartWith( [ [ $test->haveSnapshotProduct(), 1 ] ] );
					$test->haveCartFee( 'Handling fee', 20.0, true );
				},
			],
			'with flat rate shipping' => [
				'create-shipping',
				static function ( self $test ): void {
					$test->haveCartWith( [ [ $test->haveSnapshotProduct(), 1 ] ] );
					$test->haveChosenFlatRateShipping( 'SE', '49.00' );
				},
			],
		];
	}

	public function test_the_merchant_reference_is_kept_for_the_session(): void {
		$this->haveCartWith( [ [ $this->haveSimpleProduct(), 1 ] ] );
		$this->willCreateOrder();
		$this->willCreateOrder();

		QLIRO_WC()->api->create_qliro_one_order();
		QLIRO_WC()->api->create_qliro_one_order();

		$references = array_map(
			static function ( $request ) {
				return $request['json']['MerchantReference'];
			},
			$this->gatewayRequestsTo( 'merchantapi/orders' )
		);
		$this->assertCount( 2, $references );
		$this->assertSame( $references[0], $references[1] );
		$this->assertSame( WC()->session->get( 'qliro_one_merchant_reference' ), $references[0] );
	}

	/** @dataProvider provide_optional_settings */
	public function test_an_optional_setting_is_only_sent_when_set( array $settings, string $key, $expected ): void {
		$this->haveGatewayCredentials( $settings );
		$this->haveCartWith( [ [ $this->haveSimpleProduct(), 1 ] ] );
		$this->willCreateOrder();

		QLIRO_WC()->api->create_qliro_one_order();

		$body = $this->gatewayRequestTo( 'merchantapi/orders' )['json'];
		if ( null === $expected ) {
			$this->assertArrayNotHasKey( $key, $body );
		} else {
			$this->assertSame( $expected, $body[ $key ] ?? null );
		}
	}

	/** @return array<string, array{0: array, 1: string, 2: mixed}> */
	public function provide_optional_settings(): array {
		return [
			'fasttrack off'          => [ [], 'EnableFastTrack', null ],
			'fasttrack on'           => [ [ 'enable_fasttrack' => 'yes' ], 'EnableFastTrack', true ],
			'no enforced type'       => [ [], 'EnforcedJuridicalType', null ],
			'enforced business'      => [ [ 'qliro_one_enforced_juridical_type' => 'Company' ], 'EnforcedJuridicalType', 'Company' ],
			'no primary colour'      => [ [], 'PrimaryColor', null ],
			'primary colour'         => [ [ 'qliro_one_primary_color' => '#ff0000' ], 'PrimaryColor', '#ff0000' ],
			'newsletter not offered' => [ [], 'AskForNewsletterSignup', false ],
			'newsletter offered'     => [ [ 'qliro_one_button_ask_for_newsletter_signup' => 'yes' ], 'AskForNewsletterSignup', true ],
		];
	}

	public function test_process_payment_hands_the_session_over_to_the_order(): void {
		WC()->session->set( 'qliro_one_order_id', self::QLIRO_ORDER_ID );
		WC()->session->set( 'qliro_order_confirmation_id', 'confirmation-id-1' );
		WC()->session->set( 'qliro_one_merchant_reference', 'q1-reference' );
		WC()->session->set( 'chosen_shipping_methods', [ 'flat_rate:3' ] );
		$order = $this->haveOrder( [ 'items' => [ [ $this->haveSimpleProduct(), 1 ] ] ] );

		$result = ( new \Qliro_One_Gateway() )->process_payment( $order->get_id() );

		$this->assertSame( [ 'result' => 'success' ], $result );
		$order = $this->reload( $order );
		$this->assertSame( self::QLIRO_ORDER_ID, $order->get_meta( '_qliro_one_order_id' ) );
		$this->assertSame( 'confirmation-id-1', $order->get_meta( '_qliro_one_order_confirmation_id' ) );
		$this->assertSame( 'q1-reference', $order->get_meta( '_qliro_one_merchant_reference' ) );
		$this->assertSame( 'flat_rate:3', $order->get_meta( '_qliro_one_shipping_reference' ) );
		$this->assertNoGatewayRequests();
	}

	/** @dataProvider provide_missing_session_keys */
	public function test_process_payment_refuses_an_incomplete_session( string $missing ): void {
		foreach ( [ 'qliro_one_order_id' => self::QLIRO_ORDER_ID, 'qliro_order_confirmation_id' => 'confirmation-id-1', 'qliro_one_merchant_reference' => 'q1-reference' ] as $key => $value ) {
			if ( $key !== $missing ) {
				WC()->session->set( $key, $value );
			}
		}
		$order = $this->haveOrder( [ 'items' => [ [ $this->haveSimpleProduct(), 1 ] ] ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'The order could not be processed. Please reload the page and try again.' );

		( new \Qliro_One_Gateway() )->process_payment( $order->get_id() );
	}

	/** @return array<string, array{0: string}> */
	public function provide_missing_session_keys(): array {
		return [
			'no Qliro order id'       => [ 'qliro_one_order_id' ],
			'no confirmation id'      => [ 'qliro_order_confirmation_id' ],
			'no merchant reference'   => [ 'qliro_one_merchant_reference' ],
		];
	}

	/** A 100.00 product with a fixed SKU, so the snapshots stay stable. */
	public function haveSnapshotProduct(): \WC_Product_Simple {
		return $this->haveGatewayOrderProduct();
	}

	/**
	 * The per-request callback auth tokens in the merchant URLs, so they can be masked.
	 *
	 * @return array<string, string>
	 */
	private function callbackTokensIn( array $request ): array {
		$tokens = [];

		foreach ( $request['json'] as $key => $value ) {
			if ( ! is_string( $value ) || 0 !== strpos( $key, 'Merchant' ) ) {
				continue;
			}

			parse_str( (string) wp_parse_url( $value, PHP_URL_QUERY ), $query );
			foreach ( $query as $param => $param_value ) {
				if ( is_string( $param_value ) && strlen( $param_value ) >= 16 ) {
					$tokens[ "<{$param}>" ] = $param_value;
				}
			}
		}

		return $tokens;
	}
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Whether the gateway offers itself at checkout.
 *
 * @covers \Qliro_One_Gateway::is_available
 * @covers ::qliro_is_enabled_with_demo_check
 */
class GatewayAvailabilityTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	public function test_the_plugin_is_loaded(): void {
		$this->assertTrue( function_exists( 'QLIRO_WC' ) );
		$this->assertArrayHasKey( 'qliro_one', WC()->payment_gateways()->payment_gateways() );
	}

	/** @dataProvider provide_enabled_settings */
	public function test_the_gateway_follows_the_enabled_setting( string $enabled, bool $expected ): void {
		$this->haveGatewayCredentials( [ 'enabled' => $enabled ] );

		$this->assertSame( $expected, $this->gateway()->is_available() );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_enabled_settings(): array {
		return [
			'enabled'  => [ 'yes', true ],
			'disabled' => [ 'no', false ],
		];
	}

	/** @dataProvider provide_subscription_countries */
	public function test_a_subscription_cart_is_only_offered_to_swedish_customers( string $country, bool $expected ): void {
		$this->haveCustomerAddress( $this->swedishAddress( [ 'country' => $country ] ) );
		$this->haveCartContaining( 'renewal' );
		$this->simulateCheckoutPage();

		$this->assertSame( $expected, $this->gateway()->is_available() );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_subscription_countries(): array {
		return [
			'Sweden' => [ 'SE', true ],
			'Norway' => [ 'NO', false ],
		];
	}

	public function test_a_plain_cart_is_offered_outside_sweden(): void {
		$this->haveCustomerAddress( $this->swedishAddress( [ 'country' => 'NO' ] ) );

		$this->assertTrue( $this->gateway()->is_available() );
	}

	/** @dataProvider provide_demo_mode_carts */
	public function test_demo_mode_hides_the_gateway_at_checkout_until_the_coupon_is_applied( bool $with_coupon, bool $expected ): void {
		$this->haveGatewayCredentials(
			[
				'demomode'        => 'yes',
				'demomode_coupon' => 'demo-mode',
			]
		);
		$this->haveCartWith( [ [ $this->haveSimpleProduct(), 1 ] ] );

		if ( $with_coupon ) {
			$coupon = new \WC_Coupon();
			$coupon->set_code( 'demo-mode' );
			$coupon->save();
			WC()->cart->apply_coupon( 'demo-mode' );
		}

		$this->simulateCheckoutPage();

		$this->assertSame( $expected, qliro_is_enabled_with_demo_check() );
	}

	/** @return array<string, array{0: bool, 1: bool}> */
	public function provide_demo_mode_carts(): array {
		return [
			'without the coupon' => [ false, false ],
			'with the coupon'    => [ true, true ],
		];
	}

	private function gateway(): \Qliro_One_Gateway {
		return new \Qliro_One_Gateway();
	}
}

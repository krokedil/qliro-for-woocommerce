<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Host routing, the request signature and how API errors are reported.
 *
 * @covers \Qliro_One_Request
 */
class RequestsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** @dataProvider provide_environments */
	public function test_the_environment_picks_the_host( bool $testmode, string $host ): void {
		$this->haveGatewayCredentials( [], $testmode );
		$this->willRetrieveOrder();

		QLIRO_WC()->api->get_qliro_one_order( self::QLIRO_ORDER_ID );

		$this->assertSame( $host . 'checkout/merchantapi/orders/' . self::QLIRO_ORDER_ID, $this->gatewayRequestTo( 'merchantapi/orders' )['url'] );
	}

	/** @return array<string, array{0: bool, 1: string}> */
	public function provide_environments(): array {
		return [
			'test mode' => [ true, 'https://pago.qit.nu/' ],
			'live mode' => [ false, 'https://payments.qit.nu/' ],
		];
	}

	/** @dataProvider provide_environments */
	public function test_the_request_is_signed_with_the_secret_of_its_environment( bool $testmode ): void {
		$this->haveGatewayCredentials( [ ( $testmode ? 'test_' : '' ) . 'api_secret' => 'the-right-secret' ], $testmode );
		$this->willCancel();

		QLIRO_WC()->api->cancel_qliro_one_order( $this->haveGatewayOrder()->get_id() );

		$request  = $this->gatewayRequestTo( 'cancelOrder' );
		$expected = 'Qliro ' . base64_encode( hex2bin( hash( 'sha256', $request['body'] . 'the-right-secret' ) ) );

		$this->assertSame( $expected, $request['headers']['Authorization'] );
		$this->assertSame( 'WooCommerce', $request['headers']['x-platform'] );
	}

	public function test_the_merchant_api_key_of_the_environment_goes_in_the_body(): void {
		$this->haveGatewayCredentials( [ 'test_api_key' => 'test-key' ] );
		$this->willCancel();

		QLIRO_WC()->api->cancel_qliro_one_order( $this->haveGatewayOrder()->get_id() );

		$this->assertSame( 'test-key', $this->gatewayRequestTo( 'cancelOrder' )['json']['MerchantApiKey'] );
	}

	/** @dataProvider provide_error_codes */
	public function test_a_known_error_code_gets_a_readable_message( string $code, string $expected ): void {
		$this->willRejectWith( 'merchantapi/orders', 'Raw API message', 400, [ 'ErrorCode' => $code ] );

		$result = ( new \Qliro_One_Request_Get_Order( [ 'qliro_order_id' => self::QLIRO_ORDER_ID ] ) )->request();

		$this->assertWpErrorCode( 400, $result );
		$this->assertStringContainsString( $expected, $result->get_error_message() );
		$this->assertSame( $code, $result->get_error_data()['ErrorCode'] );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_error_codes(): array {
		return [
			'not configured for the market' => [ 'PAYMENT_METHOD_NOT_CONFIGURED', 'Qliro is not configured for the selected country and currency' ],
			'invalid input'                 => [ 'INVALID_INPUT', 'Error code INVALID_INPUT' ],
			'already captured'              => [ 'NO_ITEMS_LEFT_IN_RESERVATION', 'The order has already been captured.' ],
			'unknown code'                  => [ 'SOMETHING_ELSE', 'Raw API message' ],
		];
	}

	public function test_an_error_without_a_message_reports_the_status_code(): void {
		$this->willRespondWith( [], 503, 'merchantapi/orders' );

		$result = ( new \Qliro_One_Request_Get_Order( [ 'qliro_order_id' => self::QLIRO_ORDER_ID ] ) )->request();

		$this->assertWpErrorCode( 503, $result );
		$this->assertSame( 'API Error 503', $result->get_error_message() );
	}

	public function test_a_successful_response_is_returned_decoded(): void {
		$this->willRetrieveOrder( [ 'MerchantReference' => 'ref-42' ] );

		$result = ( new \Qliro_One_Request_Get_Order( [ 'qliro_order_id' => self::QLIRO_ORDER_ID ] ) )->request();

		$this->assertSame( 'ref-42', $result['MerchantReference'] );
	}
}

<?php

declare(strict_types=1);

namespace Tests\Harness\Reporting;

use Tests\Support\IntegrationTestCase;

/**
 * Pins that recorded API traffic reaches the report, and reaches it
 * scrubbed. A leak here would land in a downloadable CI artifact.
 */
class PayloadAttachmentTest extends IntegrationTestCase {

	public function test_describes_recorded_requests_as_scrubbed_json(): void {
		$hash = base64_encode( hex2bin( hash( 'sha256', 'body' . 'sup3r-s3cret-value' ) ) );

		wp_remote_post(
			'https://pago.qit.nu/checkout/merchantapi/orders',
			[
				'headers' => [ 'Authorization' => 'Qliro ' . $hash ],
				'body'    => wp_json_encode(
					[
						'MerchantApiKey' => 'merchant-key-1',
						'TotalPrice'     => 250,
					]
				),
			]
		);

		$json = $this->describeHttpRequestsForReport();

		$this->assertNotNull( $json );
		$this->assertStringContainsString( 'merchantapi/orders', $json );
		$this->assertStringContainsString( '250', $json );
		$this->assertStringNotContainsString( $hash, $json );
		$this->assertStringNotContainsString( 'merchant-key-1', $json );
	}

	public function test_describes_nothing_when_no_request_was_made(): void {
		$this->assertNull( $this->describeHttpRequestsForReport() );
	}
}

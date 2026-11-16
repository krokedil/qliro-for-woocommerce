<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Assert;
use Tests\Support\EndToEndTester;

/**
 * Qliro's push callbacks reach the site through the tunnel, which every purchase depends
 * on: without them an order is only confirmed if the confirmation page gets there first.
 * Broken, it reads as an order stuck in pending. See tests/_mu-plugins/01-qliro-public-url.php.
 */
class TunnelCest
{
	public function a_qliro_push_through_the_tunnel_reaches_the_plugin(EndToEndTester $I): void
	{
		$public = rtrim((string) ($_ENV['QLIRO_WORDPRESS_URL'] ?? ''), '/');

		Assert::assertNotSame('', $public, 'QLIRO_WORDPRESS_URL is not set in tests/.env.');

		// The checkout push endpoint with an empty body: received and acknowledged, nothing else.
		$response = (string) @file_get_contents(
			$public . '/wc-api/QOC_Checkout_Status/?qliro_one_confirm_id=tunnel-probe',
			false,
			stream_context_create(
				[
					'http' => [
						'method'          => 'POST',
						'header'          => 'Content-Type: application/json',
						'content'         => '{}',
						'timeout'         => 30,
						'follow_location' => 0,
						'ignore_errors'   => true,
					],
				]
			)
		);

		Assert::assertStringContainsString('"CallbackResponse": "received"', $response, "The push through the tunnel got: {$response}");
	}
}

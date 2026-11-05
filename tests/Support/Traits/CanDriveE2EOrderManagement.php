<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Facebook\WebDriver\Exception\WebDriverException;
use PHPUnit\Framework\Assert;

/**
 * The admin half of an order's life: drive the WooCommerce order screen and the gateway's
 * Order Management metabox, then read back what the gateway and WooCommerce made of it.
 *
 * Sibling of CanDriveE2ECheckout, which buys the order these steps then manage.
 */
trait CanDriveE2EOrderManagement {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/** The Qliro order data metabox, which is only on the screen once the order page has rendered. */
	private const KOM_METABOX = '#qliro-one';

	/** The type of Qliro's latest payment transaction, which the metabox shows as the order status. */
	private const KOM_STATUS = '//div[@id="qliro-one"]//h4[contains(.,"Order status")]/following-sibling::span[1]';

	/** The latest transaction's status, which the metabox shows as a tip on the order status. */
	private const KOM_STATUS_TIP = '//div[@id="qliro-one"]//h4[contains(.,"Order status")]//span[contains(@class,"woocommerce-help-tip")]';

	/** The marker a save leaves on the document it is about to navigate away from. */
	private const KOM_LEAVING = 'qliroLeavingOrderScreen';

	/** How long a save that talks to the gateway may take, in seconds. */
	private const KOM_SAVE_TIMEOUT = 90;

	/** Whether this test has already moved the browser to wp-admin and logged in. */
	private bool $inOrderAdmin = false;

	/**
	 * Opens the order edit screen, which is itself one live GET of the gateway's order.
	 * Logs in once per test.
	 */
	public function amEditingOrder( int $orderId ): void {
		if ( ! $this->inOrderAdmin ) {
			$this->loginAsAdmin();

			// loginAsAdmin() returns once the auth cookie exists, which a retried login has
			// before its redirect to wp-admin lands; navigating then gets aborted by that redirect.
			$this->waitForJS( "return location.pathname.indexOf('/wp-admin') === 0 && document.readyState === 'complete';", 30 );

			$this->inOrderAdmin = true;
		}

		$this->waitOutOrderScreen( fn () => $this->amOnAdminPage( "admin.php?page=wc-orders&action=edit&id={$orderId}" ) );
	}

	/** The gateway's own status for the order, as the metabox just read it back. */
	public function seeGatewayOrderStatusIs( string $status ): void {
		// Waited for rather than read outright: the status is one live GET of the gateway's
		// order, so the metabox can be on the screen a moment before the status is.
		$this->waitForElement( self::KOM_STATUS, self::KOM_SAVE_TIMEOUT );

		$actual = trim( (string) $this->grabTextFrom( self::KOM_STATUS ) );

		Assert::assertSame( $status, $actual, "The gateway says the order is '{$actual}', expected '{$status}'." );
	}

	/**
	 * Reloads the order screen until Qliro's latest transaction has settled. Qliro refuses
	 * an operation while one is still in process ("Another transaction is already in
	 * process"), which right after a purchase is the preauthorization.
	 */
	public function waitForGatewayTransactionSettled( int $orderId, int $timeout = 90 ): void {
		$deadline = microtime( true ) + $timeout;

		do {
			$this->amEditingOrder( $orderId );
			$status = (string) $this->grabAttributeFrom( self::KOM_STATUS_TIP, 'aria-label' );
			if ( $status === 'Success' ) {
				return;
			}

			$this->wait( 2 );
		} while ( microtime( true ) < $deadline );

		Assert::fail( "Qliro's latest transaction for order {$orderId} is still '{$status}' after {$timeout}s." );
	}

	/** Moves the order to a status and waits out whatever it makes the plugin send. */
	public function changeOrderStatusTo( string $status ): void {
		$this->selectOption( '#order_status', "wc-{$status}" );
		$this->saveOrderScreen();
	}

	/**
	 * Refunds the first line item's full quantity through the gateway. A line rather than
	 * an amount, since Qliro returns items and a refund without one has nothing to return.
	 */
	public function refundLineItemViaGateway(): void {
		$this->click( '.button.refund-items' );
		$this->waitForElementVisible( '#refund_amount' );

		// WooCommerce fills the line's amounts from the quantity on a change event, which
		// typing does not fire.
		$this->executeJS(
			"const qty = jQuery('#order_line_items tr.item:first input.refund_order_item_qty');"
			. " qty.val(qty.attr('max')).trigger('change');"
		);

		// Both dialogs block the driver; take them over so the refund goes through and
		// whatever WooCommerce would have alerted stays readable.
		$this->executeJS(
			'window.qliroRefunding = true; window.qliroRefundError = null;'
			. ' window.confirm = () => true;'
			. ' window.alert = message => { window.qliroRefundError = message; };'
		);

		$this->click( 'button.do-api-refund' );

		// WooCommerce reloads the screen on success, which is what drops the marker.
		$this->waitForJS(
			'return window.qliroRefunding === undefined || !!window.qliroRefundError;',
			self::KOM_SAVE_TIMEOUT
		);

		$error = $this->executeJS( 'return window.qliroRefundError || null;' );
		if ( ! empty( $error ) ) {
			Assert::fail( "The refund was refused: {$error}" );
		}

		$this->waitForElement( self::KOM_METABOX, 30 );
	}

	/** Asserts every needle appears somewhere in the order's notes. */
	public function seeOrderNotes( int $orderId, array $needles ): void {
		$notes = $this->grabOrderNotes( $orderId );

		foreach ( $needles as $needle ) {
			Assert::assertStringContainsString( $needle, $notes, "Order {$orderId} notes:\n{$notes}" );
		}
	}

	/** Asserts no needle appears in the order's notes, ignoring case. */
	public function dontSeeOrderNotes( int $orderId, array $needles ): void {
		$notes = $this->grabOrderNotes( $orderId );

		foreach ( $needles as $needle ) {
			Assert::assertStringNotContainsStringIgnoringCase( $needle, $notes, "Order {$orderId} notes:\n{$notes}" );
		}
	}

	/** Asserts post meta, where a null expected value means "any non-empty value". */
	public function seeOrderMeta( int $orderId, array $expected ): void {
		foreach ( $expected as $meta_key => $meta_value ) {
			// Read back rather than asserted, so a failure reports what was written.
			$actual = $this->grabFromDatabase(
				'wp_wc_orders_meta',
				'meta_value',
				[
					'order_id' => $orderId,
					'meta_key' => $meta_key,
				]
			);

			if ( $meta_value === null ) {
				Assert::assertNotEmpty( $actual, "Order {$orderId} has no {$meta_key}." );
				continue;
			}

			Assert::assertSame(
				$meta_value,
				$actual,
				"Order {$orderId} has {$meta_key} = " . var_export( $actual, true )
					. ', expected ' . var_export( $meta_value, true )
			);
		}
	}

	/** Asserts the order carries none of the given meta keys. */
	public function dontSeeOrderMeta( int $orderId, array $metaKeys ): void {
		foreach ( $metaKeys as $meta_key ) {
			$actual = $this->grabFromDatabase(
				'wp_wc_orders_meta',
				'meta_value',
				[
					'order_id' => $orderId,
					'meta_key' => $meta_key,
				]
			);

			Assert::assertEmpty(
				$actual,
				"Order {$orderId} has {$meta_key} = " . var_export( $actual, true ) . ', expected none.'
			);
		}
	}

	/**
	 * How many refunds the order has. WooCommerce deletes the refund again when the
	 * gateway errors, so the count surviving is the gateway having taken the body.
	 */
	public function seeRefundCount( int $orderId, int $expected ): void {
		$actual = $this->countRowsInDatabase(
			'wp_wc_orders',
			[
				'parent_order_id' => $orderId,
				'type'            => 'shop_order_refund',
			]
		);

		Assert::assertSame( $expected, $actual, "Order {$orderId} has {$actual} refunds, expected {$expected}." );
	}

	/** The newest refund on the order, so its meta can be asserted with seeOrderMeta(). */
	public function grabLatestRefundId( int $orderId ): int {
		$refund_ids = $this->grabColumnFromDatabase(
			'wp_wc_orders',
			'id',
			[
				'parent_order_id' => $orderId,
				'type'            => 'shop_order_refund',
			]
		);

		Assert::assertNotEmpty( $refund_ids, "Order {$orderId} has no refund." );

		return (int) max( array_map( 'intval', $refund_ids ) );
	}

	/** Submits the order screen and waits for the page the save redirects to. */
	private function saveOrderScreen(): void {
		// Out from under the admin bar, which otherwise swallows the click.
		$this->scrollTo( 'button.save_order', 0, -120 );

		// Marks the document being left. The metabox is on the screen before the save as well
		// as after it, so waiting for it cannot tell the two apart, and the click returns as
		// soon as the form is submitted - before the POST has even been sent. Without this
		// every read that follows races the reload: the notes are read before WordPress has
		// written them, and the metabox is read in the instant the new document replaces it.
		$this->executeJS( 'window.' . self::KOM_LEAVING . ' = true;' );

		try {
			$this->click( 'button.save_order' );
		} catch ( WebDriverException $e ) {
			$this->comment( 'qliro: the save outran the page load timeout, waiting it out anyway' );
		}

		$this->waitForOrderScreenReload();
		$this->waitForElement( self::KOM_METABOX, self::KOM_SAVE_TIMEOUT );
	}

	/**
	 * Waits until the browser is on the document the save loaded, which is the marker set
	 * before the click being gone: a fresh document has a fresh window. Polled rather than
	 * waited on with waitForJS(), since a command that lands in the unload window belongs to
	 * neither document and errors instead of answering.
	 */
	private function waitForOrderScreenReload(): void {
		$deadline = microtime( true ) + self::KOM_SAVE_TIMEOUT;

		while ( microtime( true ) < $deadline ) {
			try {
				if ( $this->executeJS( 'return window.' . self::KOM_LEAVING . ' === undefined;' ) ) {
					return;
				}
			} catch ( WebDriverException $e ) {
				// Asked mid-navigation; the next poll reads the document that replaced it.
			}

			$this->wait( 0.25 );
		}

		Assert::fail( 'The order screen never reloaded after the save was submitted.' );
	}

	/**
	 * Loads an order screen and waits for the metabox, tolerating a page load that outruns
	 * the driver's budget: opening one is an API round trip before the response even starts.
	 * The driver stops the load when it gives up, which leaves the screen it had already
	 * rendered, so the metabox is the real signal.
	 */
	private function waitOutOrderScreen( callable $navigate ): void {
		try {
			$navigate();
		} catch ( WebDriverException $e ) {
			$this->comment( 'qliro: the order screen outran the page load timeout, reading it anyway' );
		}

		$this->waitForElement( self::KOM_METABOX, self::KOM_SAVE_TIMEOUT );
	}

	/** Every note on the order as one string, so a failure prints all of them. */
	private function grabOrderNotes( int $orderId ): string {
		return implode(
			"\n",
			$this->grabColumnFromDatabase(
				'wp_comments',
				'comment_content',
				[
					'comment_post_ID' => $orderId,
					'comment_type'    => 'order_note',
				]
			)
		);
	}
}

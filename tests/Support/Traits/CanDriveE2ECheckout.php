<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Codeception\Exception\ElementNotFound;
use Facebook\WebDriver\Exception\WebDriverException;
use PHPUnit\Framework\Assert;

/**
 * The browser half of a purchase: shape the store and the cart, then drive the
 * shortcode checkout through Qliro's iframe. Not a scripted sequence, because Qliro
 * serves differently built screens for the same step: read, make one move, look again.
 */
trait CanDriveE2ECheckout {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/**
	 * The shopper a purchase starts from. Qliro looks the personal number up and fills
	 * the address from its own test register, so the WooCommerce order ends up with that
	 * address rather than one typed here.
	 */
	public const QLIRO_SHOPPER = [
		'personal_number' => '7906255307',
		'postcode'        => '11152',
		'phone'           => '0701234567',
		'first_name'      => 'John',
		'last_name'       => 'Doe',
		'address_1'       => 'Testgatan 1',
		'city'            => 'Stockholm',
	];

	/**
	 * The payment method a purchase takes by default, as Qliro's own option value.
	 * Qliro Invoice is the one a test can finish on its own: it needs nothing but the
	 * test personal number above, where card and the bank methods need a form or an app.
	 */
	public const DEFAULT_QLIRO_METHOD = 'QLIRO_INVOICE::INVOICE';

	/** The wording a method's card starts with, on the screens that have no radios. */
	private const QLIRO_METHOD_LABELS = [
		'QLIRO_INVOICE::INVOICE' => 'Qliro Invoice',
		'CREDITCARDS::'          => 'Pay by card',
		'SWISH::'                => 'Pay by Swish',
	];

	/** The container the plugin renders Qliro's snippet into, and Qliro's iframe in it. */
	private const CHECKOUT_CONTAINER = '#qliro-one-iframe';
	private const CHECKOUT_IFRAME    = '#qliro-one-iframe iframe';

	/** How long to keep advancing Qliro's screens before giving up, in seconds. */
	private const QLIRO_TIMEOUT = 120;

	/** How long to wait between reading Qliro's screen and reading it again. */
	private const QLIRO_POLL = 250_000; // 0.25s

	/** How long to leave a pressed button alone before pressing the same one again, in seconds. */
	private const QLIRO_PRESS_PATIENCE = 5;

	/**
	 * The fields Qliro asks for, by input name, and the QLIRO_SHOPPER key each takes.
	 * The names differ between the screens Qliro serves for the same step.
	 */
	private const QLIRO_FIELDS = [
		'emailPhone'           => 'email',
		'email'                => 'email',
		'customerEmail'        => 'email',
		'personalNumber'       => 'personal_number',
		'postalCode'           => 'postcode',
		'shippingPostalCode'   => 'postcode',
		'phone'                => 'phone',
		'customerMobileNumber' => 'phone',
		'shippingFirstName'    => 'first_name',
		'shippingLastName'     => 'last_name',
		'shippingStreet'       => 'address_1',
		'shippingCity'         => 'city',
	];

	/**
	 * Reads Qliro's screen and decides the one move to make, without making it. Runs
	 * inside Qliro's iframe; the placeholders are the field names, the method and its wording.
	 */
	private const QLIRO_READ_SCREEN_JS = <<<'JS'
const visible = el => {
	if (!el || el.closest('[aria-hidden="true"]')) return false;
	const r = el.getBoundingClientRect(), s = getComputedStyle(el);
	return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none';
};
document.querySelectorAll('[data-qliro-e2e]').forEach(e => e.removeAttribute('data-qliro-e2e'));
const fields = %s;
const method = %s;
const methodLabel = %s;
const label = el => (el.innerText || el.value || '').replace(/\s+/g, ' ').trim();
const buttons = [...document.querySelectorAll('button')].filter(visible);
const empty = [...document.querySelectorAll('input[name]')]
	.filter(i => visible(i) && !i.value && fields.includes(i.name))
	.map(i => i.name);
const radios = [...document.querySelectorAll('input[type=radio]')];
// Some screens offer the methods as cards without a radio, so fall back on the wording.
const card = [...document.querySelectorAll('[class*=selectable_container]')]
	.find(c => visible(c) && label(c).toLowerCase().startsWith(methodLabel.toLowerCase()));
const option = radios.find(r => r.value === method) || card;
if (option) option.setAttribute('data-qliro-e2e', 'option');
const onward = /^(continue|proceed to payment|complete purchase|pay\b)/i;
const enabled = b => !b.disabled && b.getAttribute('aria-disabled') !== 'true';
// "Try again" is Qliro's own recovery from a purchase it failed to process, and wins
// over the screen behind it.
const retry = buttons.find(b => enabled(b) && /^try again$/i.test(label(b)));
const press = retry || buttons.find(b => enabled(b) && onward.test(label(b)));
const addPin = [...document.querySelectorAll('button, a, [role=button], span, p')]
	.find(b => visible(b) && b.childElementCount === 0 && /^add personal identity number$/i.test(label(b)));
if (press) press.setAttribute('data-qliro-e2e', 'press');
if (addPin) addPin.setAttribute('data-qliro-e2e', 'add-pin');
return JSON.stringify({
	empty,
	addPin: !!addPin && !document.querySelector('input[name=personalNumber]'),
	options: radios.map(r => r.value),
	optionFound: !!option,
	optionChecked: !!option && (option.checked === true || /selected|active|checked/i.test(option.className) || !!option.querySelector('input:checked')),
	press: press ? label(press) : null,
	retry: !!retry,
	busy: !!document.querySelector('[aria-busy="true"]'),
	buttons: buttons.map(b => label(b).slice(0, 40) + (b.disabled ? ' (disabled)' : '')),
	text: (document.body ? document.body.innerText : '').replace(/\s+/g, ' ').slice(0, 600),
});
JS;

	/** The shopper this purchase was filled in with, so placeOrder() can carry on with it. */
	private array $qliroShopper = [];

	/** Sets the store options a scenario needs, keyed by option name. */
	public function haveStoreOptionsInDatabase( array $options ): void {
		foreach ( $options as $name => $value ) {
			$this->haveOptionInDatabase( $name, $value );
		}
	}

	/** Creates the tax classes and rates a scenario needs. */
	public function haveTaxClassesInDatabase( array $rates ): void {
		foreach ( $rates as $rate ) {
			$this->haveTaxClassInDatabase( $rate );
		}
	}

	/**
	 * Creates the products a scenario needs and adds them to the cart. An item is a
	 * SKU from TestProducts or `[ SKU, quantity ]`.
	 *
	 * @return array<string, int> The created product ids, keyed by SKU.
	 */
	public function haveCartWith( array $items ): array {
		$product_ids = [];

		foreach ( $items as $item ) {
			[ $sku, $quantity ] = is_array( $item ) ? [ $item[0], $item[1] ?? 1 ] : [ $item, 1 ];

			$product_ids[ $sku ] = $this->haveProductInDatabase( $sku );

			// WooCommerce's own add-to-cart, which leaves the cart in the session the
			// checkout reads. Doing it over wc-ajax builds a second cart instead.
			$this->amOnPage( "/?add-to-cart={$product_ids[ $sku ]}&quantity={$quantity}" );
		}

		return $product_ids;
	}

	/** Opens the WooCommerce checkout page. */
	public function amOnCheckoutPage(): void {
		$this->amOnPage( '/checkout/' );
	}

	/**
	 * Opens the checkout and waits for Qliro's iframe, which is the gateway availability
	 * assertion: the container is the plugin's, the iframe in it is Qliro's order having
	 * been created.
	 */
	public function amOnCheckoutPageWithGateway( int $timeout = 30 ): void {
		$this->amOnCheckoutPage();
		$this->waitForElement( self::CHECKOUT_CONTAINER, $timeout );
		$this->waitForElement( self::CHECKOUT_IFRAME, $timeout );
	}

	/**
	 * Opens the checkout and waits for another gateway's radio to render, for the
	 * scenarios that buy with something other than the gateway under test.
	 */
	public function amOnCheckoutPageWithPaymentMethod( string $paymentMethodId, int $timeout = 15 ): void {
		$this->amOnCheckoutPage();
		$this->waitForElement( "#payment_method_{$paymentMethodId}", $timeout );
	}

	/**
	 * Fills the shopper's details in Qliro's checkout and leaves it on the screen that
	 * offers the payment methods. The plugin writes WooCommerce's own billing fields from
	 * what Qliro reports, so this is the billing form's replacement.
	 */
	public function fillBillingAddressForm( array $overrides = [] ): void {
		$this->qliroShopper = array_replace(
			self::QLIRO_SHOPPER,
			[ 'email' => $this->uniqueShopperEmail() ],
			$overrides
		);

		$this->driveQliroCheckout( $this->qliroShopper, self::DEFAULT_QLIRO_METHOD, false );
	}

	/** A fresh address, so Qliro never recognises the shopper from an earlier run. */
	private function uniqueShopperEmail(): string {
		return 'qliro-e2e+' . bin2hex( random_bytes( 6 ) ) . '@example.com';
	}

	/**
	 * Buys with Qliro's own purchase button, through whatever it asks for on the way,
	 * and leaves the browser where it sends it.
	 */
	public function placeOrder( string $paymentMethod = self::DEFAULT_QLIRO_METHOD ): void {
		if ( $this->qliroShopper === [] ) {
			$this->qliroShopper = array_replace( self::QLIRO_SHOPPER, [ 'email' => $this->uniqueShopperEmail() ] );
		}

		$this->driveQliroCheckout( $this->qliroShopper, $paymentMethod, true );
	}

	/** Waits for the browser to land on the thank you page. */
	public function waitForThankYouPage( int $timeout = 60 ): void {
		$this->switchToIFrame();
		$this->waitForJS( "return location.pathname.indexOf('order-received') !== -1;", $timeout );
	}

	/** The order the thank you page belongs to, asserted to exist before it is returned. */
	public function grabOrderIdFromThankYouPage(): int {
		$order_id = $this->grabFromCurrentUrl( '/\/checkout\/order-received\/(\d+)\//' );
		if ( $order_id === null ) {
			Assert::fail( 'Could not extract order ID from URL' );
		}

		$this->seeInDatabase(
			'wp_wc_orders',
			[
				'id'   => $order_id,
				'type' => 'shop_order',
			]
		);

		return (int) $order_id;
	}

	/**
	 * Verifies the order the thank you page belongs to. `$expectedColumns` are further
	 * `wp_wc_orders` columns, e.g. `tax_amount`; amounts compare as numbers, since the
	 * columns store them with eight decimals.
	 */
	public function verifyOrderOnThankYouPage( string $paymentMethod, string $orderTotal, array $expectedColumns = [] ): void {
		$order_id = $this->grabOrderIdFromThankYouPage();

		$expectedColumns = array_merge(
			[
				'payment_method' => $paymentMethod,
				'total_amount'   => $orderTotal,
			],
			$expectedColumns
		);

		foreach ( $expectedColumns as $column => $expected ) {
			// Read back rather than asserted, so a failure reports the value
			// WooCommerce actually wrote.
			$actual = $this->grabFromDatabase( 'wp_wc_orders', $column, [ 'id' => $order_id ] );

			Assert::assertSame(
				$this->normalizeAmount( (string) $expected ),
				$this->normalizeAmount( (string) $actual ),
				"Order {$order_id} has {$column} = " . var_export( $actual, true )
					. ', expected ' . var_export( $expected, true )
			);
		}
	}

	/** `74.93000000` and `74.93` alike, so a decimal column compares with what a row says. */
	private function normalizeAmount( string $value ): string {
		return is_numeric( $value ) && str_contains( $value, '.' ) ? rtrim( rtrim( $value, '0' ), '.' ) : $value;
	}

	/**
	 * Waits for an order to reach a status, running WP-Cron meanwhile. Qliro usually
	 * settles an invoice after the confirmation page, and its push schedules the
	 * confirmation 30s out through Action Scheduler.
	 */
	public function waitForOrderStatus( int $order_id, string $status, int $timeout = 120 ): void {
		$deadline = microtime( true ) + $timeout;
		$cron     = rtrim( (string) $_ENV['WORDPRESS_URL'], '/' ) . '/wp-cron.php?doing_wp_cron=';

		do {
			$actual = $this->grabFromDatabase( 'wp_wc_orders', 'status', [ 'id' => $order_id ] );
			if ( $actual === $status ) {
				return;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions -- A bare GET outside WordPress.
			@file_get_contents( $cron . microtime( true ), false, stream_context_create( [ 'http' => [ 'timeout' => 30 ] ] ) );
			usleep( 2_000_000 );
		} while ( microtime( true ) < $deadline );

		Assert::assertSame( $status, $actual, "Order {$order_id} did not reach {$status} within {$timeout}s." );
	}

	/**
	 * Advances Qliro's checkout until it offers the payment methods, or, with `$buy`,
	 * until the browser lands on the thank you page.
	 */
	private function driveQliroCheckout( array $shopper, string $paymentMethod, bool $buy ): void {
		$deadline = microtime( true ) + self::QLIRO_TIMEOUT;
		$screen   = [];
		$moves    = [];
		$pressed  = [ 'label' => null, 'at' => 0.0 ];

		while ( microtime( true ) < $deadline ) {
			$this->switchToIFrame();
			if ( $buy && false !== strpos( (string) $this->executeJS( 'return location.pathname;' ), 'order-received' ) ) {
				return;
			}

			try {
				$this->switchToIFrame( self::CHECKOUT_IFRAME );
				$screen = $this->readQliroScreen( $paymentMethod );
			} catch ( WebDriverException | ElementNotFound $e ) {
				// The checkout re-renders Qliro's iframe on update_checkout; read the new one next poll.
				usleep( self::QLIRO_POLL );
				continue;
			}

			if ( $screen['addPin'] ) {
				$moves[] = 'add personal number';
				$this->executeJS( "document.querySelector('[data-qliro-e2e=add-pin]').click();" );
			} elseif ( $screen['empty'] !== [] ) {
				$moves[] = 'fill ' . implode( ', ', $screen['empty'] );
				foreach ( $screen['empty'] as $name ) {
					$this->fillField( "input[name={$name}]", (string) $shopper[ self::QLIRO_FIELDS[ $name ] ] );
				}
				// Fields to fill mean a new screen, whose button is pressed once it is filled.
				$pressed = [ 'label' => null, 'at' => 0.0 ];
			} elseif ( $screen['optionFound'] && ! $buy ) {
				// The payment methods are on offer, which is as far as filling in goes.
				return;
			} elseif ( $screen['optionFound'] && ! $screen['optionChecked'] ) {
				$moves[] = "select {$paymentMethod}";
				$this->executeJS( "document.querySelector('[data-qliro-e2e=option]').click();" );
			} elseif ( $screen['press'] !== null && ( $screen['retry'] || ! $screen['busy'] ) && $this->mayPress( $screen['press'], $pressed ) ) {
				$moves[] = "press \"{$screen['press']}\"";
				$this->executeJS( "document.querySelector('[data-qliro-e2e=press]').click();" );
				$pressed = [ 'label' => $screen['press'], 'at' => microtime( true ) ];
			}

			usleep( self::QLIRO_POLL );
		}

		$this->switchToIFrame();
		Assert::fail(
			sprintf(
				"Qliro's checkout did not %s within %ds.\nMoves: %s\nLast screen: %s",
				$buy ? 'reach the thank you page' : 'offer the payment methods',
				self::QLIRO_TIMEOUT,
				implode( ' > ', $moves ) ?: 'none',
				wp_json_encode( $screen, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			)
		);
	}

	/**
	 * Whether a button may be pressed: a new one at once, the one pressed last only once
	 * Qliro has had QLIRO_PRESS_PATIENCE seconds to act on the first press.
	 */
	private function mayPress( string $label, array $pressed ): bool {
		return $label !== $pressed['label'] || microtime( true ) - $pressed['at'] >= self::QLIRO_PRESS_PATIENCE;
	}

	/** What is on Qliro's screen right now, read from inside its iframe. */
	private function readQliroScreen( string $paymentMethod ): array {
		$js = sprintf(
			self::QLIRO_READ_SCREEN_JS,
			wp_json_encode( array_keys( self::QLIRO_FIELDS ) ),
			wp_json_encode( $paymentMethod ),
			wp_json_encode( self::QLIRO_METHOD_LABELS[ $paymentMethod ] ?? $paymentMethod )
		);

		return (array) json_decode( (string) $this->executeJS( $js ), true );
	}
}

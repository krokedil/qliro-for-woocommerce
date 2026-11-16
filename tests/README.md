# Qliro for WooCommerce test suites

Three suites, all booted by [lucatume/wp-browser](https://wpbrowser.wptestkit.dev/)
against a real WordPress + WooCommerce + Qliro install:

| Suite | Runner | What it covers |
|---|---|---|
| `Integration` | `composer test:integration` | PHP-level tests with WordPress and WooCommerce loaded in-process (WPLoader). No browser, no ngrok, no API calls. |
| `Harness` | `composer test:harness` | The test harness itself: what the WooCommerce Subscriptions fakes answer, and that artifact redaction keeps a Qliro secret out of the report. |
| `EndToEnd` | `composer test:e2e` | Browser-driven purchases against the local built-in server and Qliro's test environment, with an ngrok HTTPS tunnel that carries nothing but Qliro's own traffic. |

Start with the Integration suite. It needs no credentials and runs in under a
minute.

`Harness` boots the same WordPress and shares the same test database as
`Integration`, so **never run the two at once**. Concurrent runs surface as bogus
assertion failures rather than lock errors.

[CONVENTIONS.md](CONVENTIONS.md) covers what to write and what to leave out. Read
it before adding a test. This file is the mechanics.

## Prerequisites

All suites:

- **PHP 8.0+** with `pdo_mysql`, `mysqli`, `mbstring`, `gd`, `zip`, `curl`, `dom`,
  `xml`. CI runs 8.4. The plugin itself still supports 7.4; only the
  dev dependencies need 8.0, which is why `composer.json` pins the platform to 8.0.
- **Composer**.
- **No database server to install.** The suites run on a real MySQL 8.4, which wp-browser
  downloads once and runs as a normal background process on port 8906, from
  `~/.cache/qliro-for-woocommerce-tests/mysql`. No Docker, no sudo. On Ubuntu 24.04 and
  later it needs the `libaio1t64` package, which is usually installed already.

EndToEnd only:

- **Chrome**, any recent version. `composer test:chromedriver` matches the driver
  to your local Chrome.
- **ngrok** on `PATH`, with an account that supports reserved or branded domains.

Nothing else. The E2E database fixture is built with PHP alone, no WP-CLI.

## Setup

The checkout has to be in a folder named `qliro-for-woocommerce`, the repository's own
name. The Symlinker links the plugin into the test install under that folder name, and
the E2E fixture activates `qliro-for-woocommerce/qliro-for-woocommerce.php`.

1. Copy the env template and fill it in:

   ```bash
   cp tests/.env.example tests/.env
   ```

   Integration and Harness need only the *WordPress test install* block. The
   ngrok and Qliro values can stay blank.

   EndToEnd also needs:
   - `NGROK_DOMAIN`, the CLI argument for `ngrok http --domain=`. With an internal
     ngrok domain this is `<name>.internal`, not the public URL.
   - `QLIRO_WORDPRESS_URL`, the public HTTPS URL that domain resolves to, for example
     `https://qliro-test.krokedil.ngrok.io`. Only Qliro sees it.
   - `WORDPRESS_URL`, which is where the site is actually served and driven:
     `http://localhost:<BUILTIN_SERVER_PORT>`.
   - `NGROK_AUTHTOKEN` from <https://dashboard.ngrok.com/get-started/your-authtoken>.
   - `QLIRO_TEST_API_KEY` and `QLIRO_TEST_API_SECRET` from the Qliro test merchant.
     Qliro has one pair per merchant, whatever the country or currency.
   - `WORDPRESS_ADMIN_PASSWORD`, which the suite logs in with. The E2E fixture is built
     with it.

2. Install dependencies:

   ```bash
   composer install   # scaffolds tests/_wordpress/ via post-autoload-dump-dev
   ```

### The E2E database fixture is built per run

`tests/Support/Data/dump.sql`, which WPDb restores before every E2E test, is not
committed. `Tests\Support\Extension\DumpBuilder` builds it at the start of every EndToEnd
run (about 8 seconds) with `_support_scripts/build-dump.sh`: a clean WordPress,
WooCommerce and the plugin activated, SE/SEK, the `[woocommerce_checkout]` shortcode
page, one product and the Qliro settings at their defaults. So it always matches the
WooCommerce version composer installed, and bumping WooCommerce needs nothing more than
`composer update`.

The build writes the Qliro test credentials and the admin password from `tests/.env`
into the fixture, which `codeception.yml` passes to `DumpBuilder`. That is safe because
the fixture is gitignored and never uploaded: CI only uploads `tests/_output/`. The seed
also dismisses what an admin would (WooCommerce's first-order modal, the script
compression check), and runs with WP-Cron off so nothing asynchronous races the export.

It is a fresh install, so whatever WooCommerce now does on one is what the tests get:
HPOS order storage, which the E2E steps read from (`wp_wc_orders`,
`wp_wc_orders_meta`), and the "coming soon" mode, which `seed-dump-site.php` turns off.

## Running the tests

```bash
composer test:integration          # whole Integration suite
composer test:harness              # whole Harness suite, never alongside Integration
composer test:e2e                  # whole EndToEnd suite

vendor/bin/codecept run Integration:OrderManagementTest                  # one file
vendor/bin/codecept run Integration:OrderManagementTest --debug          # with output
vendor/bin/codecept run Integration OrderManagementTest:test_completing_a_paid_order_captures_it
```

`composer test:e2e` starts the MySQL server, the PHP built-in server, ChromeDriver and
ngrok, builds the dump and restores it before each test, runs the suite, then stops
everything again. Every `composer test:*` command starts MySQL if it is not running, so
before running `codecept run` by hand, run `composer test:setup` once. `composer dev:stop`
stops all of it; a built-in server left over from an earlier run keeps the environment it
was started with, so stop it after changing `tests/.env`.

There are two databases on that server: `wordpress` for the E2E site and
`wordpress_tests` for the Integration and Harness suites (`WORDPRESS_DB_URL` and
`TEST_DB_URL`), so restoring the E2E fixture, which drops its whole database, never
touches the other suites.

## CI

`.github/workflows/pr-e2e-integration-tests.yml` runs Harness, then Integration,
then EndToEnd on every pull request. The redaction tests gate everything that
follows, and the first two share a database.

It reads the `NGROK_AUTHTOKEN`, `QLIRO_TEST_API_KEY`, `QLIRO_TEST_API_SECRET` and
`WORDPRESS_ADMIN_PASSWORD` secrets from the repository's `e2e` environment and fails
the build if any is empty, since empty secrets make the redaction tests pass
vacuously. The ngrok domain carries the PR number so two open PRs never share a
tunnel, EndToEnd is skipped on fork PRs, and `verify-no-secrets.php` runs whatever
the outcome, with `tests/_output/` uploaded only on failure and only after that scan
comes back clean.

## The Integration suite

One file per area of the plugin:

| File | Covers |
|---|---|
| `CheckoutTest.php` | Creating the Qliro order the iframe renders from (the body it carries, snapshot pinned), and `process_payment()` handing the session over to the order. |
| `GatewayAvailabilityTest.php` | Whether the gateway offers itself: the enabled setting, subscription carts, and demo mode. |
| `OrderManagementTest.php` | Capture, cancel and refund on status changes, the guards they share, and what a rejection leaves on the order. |
| `ConfirmationTest.php` | `qliro_confirm_order()`: what Qliro's answer about a placed order does to it, including the merchant reference update and amount mismatches. |
| `CallbacksTest.php` | The scheduled work Qliro's checkout and order management pushes hand off to. |
| `RequestsTest.php` | Host routing, the request signature, and how API errors are reported. |

Most methods are data-provider driven, so the case count runs well ahead of the
method count.

### Writing one

Extend `Tests\Support\IntegrationTestCase` and declare a store profile:

```php
class MyTest extends IntegrationTestCase {

    protected ?string $storeProfile = 'se';

    public function test_something(): void {
        $order = $this->haveCapturableGatewayOrder();
        $this->willRetrieveAdminOrder();
        $this->willCapture();

        $order->update_status( 'completed' );

        $this->assertGatewayRequestCount( 1, 'MarkItemsAsShipped' );
    }
}
```

| Profile | Store |
|---|---|
| `'se'` | SE / SEK, one 25% VAT rate, test credentials. |
| `'se-no-tax'` | Same, taxes off. |
| `'us'` | US:CA / USD, one 8.5% sales tax rate, a US customer address, test credentials. |
| `null` | WooCommerce defaults (US / USD, taxes off) with no credentials. |

The profile is applied before every test. Override `setUp()` only for genuinely
extra arrange, and call `parent::setUp()` first.

`haveGatewayCredentials()` writes every setting at its default from
`Qliro_One_Fields::fields()`, the way a saved store carries them, because the plugin
reads many of them off the raw option without a fallback.

The base class resets the store between tests and carries the assertions for
things plugin code leaves behind: order notes (`orderNotes()`,
`assertOrderHasNote()`, `assertOrderHasNoNote()`) and failures
(`assertWpErrorCode()`), plus `reload()` and `statusOf()` for reading an order back
after plugin code has written to it.

It pulls in the fixture traits:

| Trait | Gives you |
|---|---|
| `CanConfigureStore` | `configureStore()`, `configureSwedishStore()`, `configureUsStore()`, `haveTaxRate()`, `haveTaxClass()`, `setGatewaySettings()`, `haveGatewayCredentials()`, `defaultGatewaySettings()`, `reloadPaymentGateways()`, `resetGatewaySession()`. |
| `CanManageProducts` | `haveSimpleProduct()`, `haveVariableProduct()`. |
| `CanBuildCartsAndOrders` | `haveCustomerAddress()`, `haveCartWith()`, `haveCartFee()`, `haveChosenFlatRateShipping()`, `simulateCheckoutPage()`, `haveOrder()`, `haveGatewayOrder()`, `haveCapturableGatewayOrder()`, `haveGatewayOrderProduct()`, `markAsGatewayOrder()`, `haveRefundForItems()`, plus the `swedishAddress()` and `usAddress()` presets. |
| `CanInterceptHttp` | `willRespondWith()`, `willRejectWith()`, `httpRequests()`, `gatewayRequests()`, `gatewayRequestTo()`, `gatewayRequestsTo()`, `assertGatewayRequestCount()`, `assertNoGatewayRequests()`. |
| `CanDriveCheckout` | `willCreateOrder()`, `willRetrieveOrder()`, `willUpdateOrder()`, `qliroCheckoutOrder()`. |
| `CanDriveOrderManagement` | `willRetrieveAdminOrder()`, `qliroTransaction()`, `willCapture()`, `willCancel()`, `willRefund()`, `willAcceptTheUpdate()`, `willUpdateMerchantReference()`. |
| `CanFakeSubscriptions` | `haveSubscription()`, `haveSubscriptionFor()`, `haveRenewalOrderFor()`, `markAsSubscription()`, `haveCartContaining()`. |
| `CanSnapshotRequests` | `assertRequestMatchesSnapshot()`, `assertMatchesSnapshot()`. |
| `CanCaptureLogs` | `haveLoggingEnabled()`, `loggedText()`, `loggedEntry()`, `assertNotLogged()`. |

`haveOrder()` takes `status`, `paid`, `qliro` and `created_via`. `qliro => true`
stamps the meta a completed purchase leaves: `_qliro_one_order_id`,
`_qliro_one_merchant_reference`, `_qliro_payment_transaction_id`,
`_qliro_order_sync_enabled` and the `qliro_one` payment method.

```php
$order = $this->haveOrder(
    [
        'items'   => [ [ $this->haveSimpleProduct(), 2 ] ],
        'billing' => $this->swedishAddress(),
        'qliro'   => true,          // or [ 'order_id' => ... ]
        'paid'    => true,
        'status'  => 'processing',
    ]
);

// Or, for the common case, the preset with overrides:
$order = $this->haveGatewayOrder( [ 'status' => 'on-hold' ] );
```

The gateway order presets sell `haveGatewayOrderProduct()`, which has a fixed SKU so
the snapshots that carry it stay stable.

### Request body snapshots

Outgoing payloads are pinned against JSON fixtures in `Support/Data/snapshots/`
rather than asserted key by key:

```php
$request = $this->gatewayRequestTo( 'MarkItemsAsShipped' );
$this->assertRequestMatchesSnapshot( $request, 'om-capture-se', [ '<request-id>' => $request['json']['RequestId'] ] );
```

The fixture records the HTTP method, the full URL and the decoded body. Anything
random per request (`RequestId`, the session's merchant reference, the confirmation
id and the callback tokens in the merchant URLs) goes in as a placeholder. The
`MerchantApiKey` is masked by the redactor. To rewrite the fixtures after an
intended change:

```bash
composer test:integration:snapshots
```

Then read the diff before committing. That diff is the review.

### Outbound HTTP is blocked

`IntegrationTestCase` hooks `pre_http_request` for every test, so nothing reaches
Qliro. An unexpected call is short-circuited with a `WP_Error` and recorded, so you
can assert on it. Queue a canned response for calls that need to succeed:

```php
$this->willRespondWith(
    [ 'PaymentTransactions' => [ [ 'PaymentTransactionId' => 1, 'Status' => 'Created' ] ] ],
    200,
    'MarkItemsAsShipped'   // optional: only answer matching URLs
);
```

Responses are consumed in the order they were queued, first URL match wins.
Capturing, cancelling and refunding all read the Qliro admin order first, so a test
that expects the action to happen queues `willRetrieveAdminOrder()` before the
action itself. `willRejectWith()` answers in Qliro's error shape (`ErrorMessage`,
plus `ErrorCode` if you pass one).

### WooCommerce Subscriptions is faked, always

The `wcs_*` functions the plugin calls are declared in
[_subscriptions-fakes.php](_subscriptions-fakes.php), which the Integration and
Harness bootstraps require for the whole run, together with empty `WC_Subscription`
and `WC_Subscriptions_Order` classes, since the plugin guards on both. They answer
from `Tests\Support\Fakes\SubscriptionsRegistry`, which is empty until a test puts
something in it through `CanFakeSubscriptions`. `WC_Subscriptions_Cart` is
deliberately not faked; `haveCartContaining( 'renewal' )` and its siblings are the
switch for cart states.

### Other things to know about the environment

- WPLoader wraps each test in a database transaction, but WooCommerce keeps state
  outside the database: the session cart, `WC()->customer`, tax rate caches, the
  shipping method count transient, and the Qliro session keys.
  `IntegrationTestCase::resetStore()` clears all of it. If you add a fixture that
  writes somewhere new, reset it there too.
- `Qliro_One_Order_Management` and `Qliro_One_Checkout` read the gateway settings
  once, at plugin load. `setGatewaySettings()` refreshes both, so the fixtures
  handle it; call `flushGatewaySettingsCache()` yourself only if a test writes the
  option directly. `Qliro_One_Callbacks` does the same, so `CallbacksTest` builds a
  fresh instance per test.
- Product SKUs are unique store wide and the lookup table outlives the rollback,
  which is why `haveGatewayOrderProduct()` reuses its product rather than making a
  second one.
- Shipping zone instance ids come from an autoincrement the rollback does not reset,
  so a snapshot carrying a shipping line masks the rate id.
- `assertNoGatewayRequests()` filters to `qit.nu` on purpose. WooCommerce core makes
  requests of its own.
- Anything reading `filter_input( INPUT_GET, ... )`, `INPUT_POST` or `php://input` is
  unreachable here: the push callback entry points, the confirmation page and
  `process_payment()`'s change-payment-method branch. The callbacks are covered
  through the handlers they schedule instead.

## The EndToEnd suite

| File | Covers |
|---|---|
| `CheckoutCest.php` | A purchase through the shortcode checkout and Qliro's iframe, one row per store and cart shape, ending with the order confirmed to `processing`. |
| `OrderManagementCest.php` | Capturing and refunding, and cancelling, a real purchase from wp-admin, read back from Qliro's own view of the order in the metabox. |
| `TunnelCest.php` | That a Qliro push through the tunnel reaches the plugin, which confirming orders depends on. |

### How a purchase is driven

`CanDriveE2ECheckout` fills Qliro's iframe with the test shopper (personal number
`790625-5307`, which Qliro's test register resolves to an address) and pays with
Qliro Invoice, the one method a test can finish without a card form or an app.

It is not a scripted sequence. Qliro serves differently built screens for the same
step, with the same field under different input names, and payment methods as
radios on some screens and as cards on others. So every step is the same loop: read
the screen, make the one move that gets past it, look again. A dead end fails with
the moves it made and the last screen it saw.

After the thank you page, the order is usually still `pending`: Qliro settles the
invoice after the confirmation page has been shown, and its push schedules the
confirmation 30 seconds out through Action Scheduler. `waitForOrderStatus()` polls
the order and runs WP-Cron meanwhile, the way a real store gets there.

### Running one row

A purchase is around twenty seconds of browser plus the confirmation wait.
`QLIRO_ONLY` narrows `CheckoutCest`'s table to the rows whose names match, comma
separated:

```bash
QLIRO_ONLY=rounding composer test:e2e
QLIRO_ONLY="four VAT,virtual and" vendor/bin/codecept run EndToEnd CheckoutCest --steps
```

It fails loudly listing every row name if nothing matches. **Get expected totals from
WooCommerce, never from arithmetic.**

### Only Qliro goes through the tunnel

The browser drives the site on `WORDPRESS_URL`, the built-in server, with no tunnel
in the way, so the assets ChromeDriver re-fetches on every load never spend the ngrok
request allowance. Qliro accepts the local URL for everything the browser follows (the
confirmation and terms pages), and only wants https for the push callbacks it makes
itself. `_mu-plugins/01-qliro-public-url.php` handles those:

- **Outbound.** It swaps the site URL for `QLIRO_WORDPRESS_URL` in the push, OM push,
  notification and save-card URLs through the plugin's own `qliro_one_wc_merchant_urls`
  filter, before the request body is built and signed.
- **Inbound.** ngrok forwards the public `Host` header, which WordPress would answer with
  a redirect Qliro does not follow, so a request through the tunnel is served as the
  local one. [TunnelCest.php](EndToEnd/TunnelCest.php) is the guard.

### Environment traps that look like plugin bugs

- **Every purchase stays `pending`, and the log shows
  `UPDATE_MERCHANT_REFERENCE_NOT_SUPPORTED`.** A merchant reference must be unique for
  the Qliro test merchant, and WPDb restores the dump before every test, so WooCommerce
  hands out the same order ids every run. `_mu-plugins/02-qliro-unique-order-number.php`
  suffixes the order number with part of the order key.
- **A capture or cancel refused with "Another transaction is already in process".**
  Qliro refuses any operation while the purchase's preauthorization is still being
  processed, which is the first few seconds after the order is confirmed.
  `OrderManagementCest` waits for it with `waitForGatewayTransactionSettled()`, which
  reads the transaction status off the metabox.
- **A wp-admin click that never lands.** WooCommerce's "Cha-ching! Order number one"
  modal covers the first order screen of every test, since each starts with no orders.
  The seed dismisses it for the admin, through WooCommerce's own opt-out.
- **API Error 401 on the checkout page with valid credentials.** A built-in server left
  over from a run without credentials. `composer dev:stop`.
- **A tunnel that goes away.** `NgrokController` checks the URL before every test and
  restarts the agent if it has stalled. The agent's log is kept at
  `tests/_output/ngrok.log`.

## Test reports

All three suites write [Allure](https://allurereport.org/) results, and one report
renders them together:

```bash
composer test:report               # writes tests/_output/report/index.html
composer test:report:share         # one self-contained tests/_output/report-share/index.html
composer test:report:reset         # clears every suite's results
```

Integration tests carry `http-requests.json`, every API call the test provoked.
EndToEnd failures carry `screenshot.png`, `page-source.html`, `browser-console.log`
and `network.json`, and every EndToEnd test carries its own slice of `debug.log` and
the WooCommerce logs, `qliro-one-checkout-for-woocommerce` included.

**The redaction guarantee.** Every text artifact is scrubbed through
`Tests\Support\Reporting\Redactor` before it is attached: the secrets in
`SecretRegistry::SECRET_KEYS` in every encoding, `Authorization: Qliro <hash>`
headers, and `MerchantApiKey` values. Afterwards `verify-no-secrets.php` re-scans the
results and the raw `tests/_output` artifacts; if a credential survived, the report is
deleted and the build fails, naming the env var that leaked and never its value. It
does **not** scan the raw logs under `tests/_wordpress/wp-content/`, which stay on
your machine.

**Adding a new secret.** Add the env var name to `SecretRegistry::SECRET_KEYS`.

## Layout

| Path | Purpose |
|---|---|
| `tests/CONVENTIONS.md` | What to write and what to leave out. |
| `tests/phpcs.xml` | Test-suite style, applied by `composer lint:tests`. WPCS does not apply under `tests/`. |
| `codeception.yml` (repo root) | Shared config: ChromeDriver, built-in server, Symlinker. |
| `tests/*.suite.yml` | Per-suite modules and extensions. |
| `tests/Integration/`, `tests/Harness/`, `tests/EndToEnd/` | The suites. |
| `tests/_subscriptions-fakes.php` | The `wcs_*` stand-ins. |
| `tests/_bootstrap.php` | Syncs `_mu-plugins/` into the install and writes `WP_HOME` / `WP_SITEURL` into `wp-config.php`. |
| `tests/Support/IntegrationTestCase.php` | Base class: store profiles, store reset, note and `WP_Error` assertions. |
| `tests/Support/Traits/` | The fixture and driver traits listed above. |
| `tests/Support/Data/TestProducts.php`, `TestTaxRates.php` | The SKUs and rates the E2E rows refer to. |
| `tests/Support/Data/snapshots/` | The request body fixtures. |
| `tests/Support/Data/dump.sql` | The fixture WPDb restores before each E2E test. Built per run by `DumpBuilder`, gitignored, carries no credentials. |
| `tests/Support/Extension/` | The dump builder, ngrok, failure artifacts, per-test WordPress logs, example names. |
| `tests/Support/Reporting/` | `Redactor`, `SecretRegistry` and `LogTail`. |
| `tests/_mu-plugins/` | Test-only mu-plugins, synced into the install. Each one's header says why it exists. |
| `tests/_support_scripts/` | Install, dump build (`build-dump.sh` + `seed-dump-site.php`), report and leak-gate scripts. |
| `tests/_plugins/`, `tests/_themes/` | WooCommerce and Storefront, installed by composer. |

/**
 * WordPress Playground dev-environment config for this plugin.
 * Consumed by @krokedil/wp-playground-tools — see its README for the full schema.
 */
import { envSecret } from '@krokedil/wp-playground-tools';

export default {
	slug: 'qliro-for-woocommerce',
	siteName: 'Qliro for WooCommerce',

	// Claimed in the org port registry (wp-playground-tools README):
	// start 8900, development 8901, demo 8902 (--https proxies on +400).
	basePort: 8900,

	// The Qliro settings screen — the same URL the dev-zip smoke test asserts
	// on (.github/plugin-meta.json).
	landingPage:
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=qliro_one',

	// wpify-scoper writes the scoped krokedil/* packages to vendor/dependencies
	// (see composer.json "extra"), and that is the autoloader the plugin
	// requires at runtime. vendor/ can exist while the scoped tree is missing,
	// so both markers are needed to trigger an install. Installing pulls
	// private krokedil/* repos over SSH.
	composer: {
		markers: ['vendor/autoload.php', 'vendor/dependencies/autoload.php'],
	},

	// No build config: the minified assets (assets/js/*.min.js and the cssmin
	// output) are committed. Rebuild manually with `npm run build` when
	// working on assets/js/ or assets/css/.

	// setSiteOptions REPLACES each option wholesale, so this map is the entire
	// gateway settings array — WooCommerce only writes the form_fields defaults
	// when the settings screen is saved, and nothing here ever saves it. Keys
	// omitted below are therefore absent from the DB, and the plugin reads some
	// of them straight off `get_option( 'woocommerce_qliro_one_settings' )`
	// without an isset()/default (see Qliro_One_Order_Management), so a missing
	// key is an undefined-array-key warning plus wrong behavior — not a
	// fallback to the documented default. Keep this in sync with the defaults
	// in classes/class-qliro-one-fields.php.
	//
	// When this lands in the site: the blueprint (and with it setSiteOptions) is
	// applied on FRESH PROVISION ONLY for the persistent `start` site — warm
	// boots skip the blueprint entirely, which is what lets edits made in
	// wp-admin survive a restart. Changes here therefore need
	// `playground.mjs start --fresh` (resets site data) to take effect. The
	// ephemeral `server development|demo` modes re-apply the blueprint on every
	// run, so there they land on each boot and overwrite any admin edits.
	options: {
		all: {
			woocommerce_qliro_one_settings: {
				enabled: 'yes',
				testmode: 'yes',
				logging: 'yes',
				// Qliro test credentials from the central playground .env —
				// missing values warn by name and leave the gateway
				// unconfigured (the site still boots).
				test_api_key: envSecret('QLIRO_TEST_API_KEY'),
				test_api_secret: envSecret('QLIRO_TEST_API_SECRET'),

				// Order management. Read unguarded by
				// Qliro_One_Order_Management::order_status_changed(), so
				// without these no status change ever matches and completing an
				// order silently never captures (MarkItemsAsShipped) — which
				// also hides the admin return-fee row, since it only renders on
				// fully captured orders.
				capture_status: 'wc-completed',
				cancel_status: 'wc-cancelled',

				// The advanced pending/OK statuses are opt-in in the UI, but the
				// capture/cancel paths read them unguarded and only skip on the
				// literal string 'none'. Absent, the reads are null, the 'none'
				// guard passes, and update_status( null ) falls back to the
				// default status — sending a just-captured order to "Pending
				// payment". 'no' + 'none' is the plugin's own default: advanced
				// configuration off, statuses left untouched.
				om_advanced_settings: 'no',
				capture_pending_status: 'none',
				capture_ok_status: 'none',
				cancel_pending_status: 'none',
				cancel_ok_status: 'none',

				// The remaining keys are all at their plugin defaults and only
				// listed because they too are read without a guard, on paths hot
				// enough that the warnings drown out real output: this one is
				// read in wp_localize_script() on every checkout page load, so
				// the warning HTML is emitted into the page ahead of the inline
				// script (classes/class-qliro-one-assets.php).
				shipping_in_iframe: 'no',
				// Read on every checkout update-order request; the empty() guard
				// there sits one line after the array access, so absence warns
				// on each API round-trip
				// (classes/requests/put/class-qliro-one-request-update-order.php).
				shipping_additional_header: '',
				// Read unguarded while building the create/update-order body
				// (classes/requests/helpers/class-qliro-one-helper-order-limitations.php).
				require_id_verification: 'no',
			},
		},
	},

	// Qliro requires https on port 443 for the merchant push URLs — creating an
	// order over plain http fails with "Only https schema on default port(443)
	// is supported", so checkout work here needs --tunnel. Note --https is not
	// an alternative: the local mkcert proxy listens on the mode port +400.
	// The company wildcard gives this checkout (and every worktree of it) its
	// own stable host, so parallel runs don't collide.
	tunnel: { provider: 'ngrok', domain: '*.krokedil.ngrok.io' },
};

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

<?php
/**
 * Shared test bootstrap, required by every suite bootstrap.
 *
 * Runs at SUITE_INIT, which is too late to scaffold WordPress, so the install lives in
 * composer's post-autoload-dump-dev hook. What stays here: a sentinel so the file is safe
 * to require twice, syncing tests/_mu-plugins/ into the install, and pinning the site URL.
 */

if (defined('QLIRO_TEST_BOOTSTRAP_DONE')) {
    return;
}
define('QLIRO_TEST_BOOTSTRAP_DONE', true);

$qliro_mu_source  = dirname(__DIR__) . '/tests/_mu-plugins';
$qliro_mu_plugins = dirname(__DIR__) . '/tests/_wordpress/wp-content/mu-plugins';

if (is_dir($qliro_mu_plugins) && is_dir($qliro_mu_source)) {
    $qliro_wanted = [];

    foreach (glob($qliro_mu_source . '/*.php') as $qliro_src) {
        $qliro_wanted[] = basename($qliro_src);
        $qliro_dest     = $qliro_mu_plugins . '/' . basename($qliro_src);
        if (! is_file($qliro_dest) || filemtime($qliro_src) > filemtime($qliro_dest)) {
            copy($qliro_src, $qliro_dest);
        }
    }

    // Drop the ones an earlier checkout installed and this one no longer has, matched on
    // our own plugin header so anything else in mu-plugins is left alone.
    foreach (glob($qliro_mu_plugins . '/*.php') as $qliro_installed) {
        if (in_array(basename($qliro_installed), $qliro_wanted, true)) {
            continue;
        }
        if (strpos((string) file_get_contents($qliro_installed), 'Plugin Name: Qliro Tests,') !== false) {
            unlink($qliro_installed);
        }
    }
}

unset($qliro_mu_source, $qliro_mu_plugins, $qliro_src, $qliro_dest, $qliro_wanted, $qliro_installed);

// Load the env vars from .env in the tests/ directory. This is where the test WP installation's URL, DB connection string, and other config live.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/*
 * Pin WP_HOME and WP_SITEURL to WORDPRESS_URL, the local built-in server. Constants rather
 * than option filters, because wp_plugin_directory_constants() freezes WP_CONTENT_URL from
 * siteurl before mu-plugins load; written here rather than committed, because WPDb reloads
 * a dump carrying whoever generated it. The tunnel is dealt with in
 * tests/_mu-plugins/01-qliro-public-url.php.
 */
$wpConfig = dirname(__DIR__) . '/tests/_wordpress/wp-config.php';
if (is_file($wpConfig)) {
	$wpUrl = rtrim($_ENV['WORDPRESS_URL'], '/');
	echo "Pinning WP_HOME and WP_SITEURL in {$wpConfig} to {$wpUrl}\n";

	$block = <<<PHP
	// >>> Qliro tests: the site answers as WORDPRESS_URL, tunnelled requests included.
	if (! defined('WP_HOME')) {
	    define('WP_HOME', '{$wpUrl}');
	    define('WP_SITEURL', '{$wpUrl}');
	}
	// Nothing but Qliro's API: update checks, news feeds and the like fail fast instead of hanging.
	if (! defined('WP_HTTP_BLOCK_EXTERNAL')) {
	    define('WP_HTTP_BLOCK_EXTERNAL', true);
	    define('WP_ACCESSIBLE_HOSTS', '*.qit.nu');
	}
	// <<< Qliro tests
	PHP;

	$contents = file_get_contents($wpConfig);

	// Drop whatever a previous run left behind, then re-insert.
	$contents = preg_replace('/\n?\/\/ >>> Qliro tests:.*?\/\/ <<< Qliro tests\n/s', '', $contents);
	$contents = preg_replace("/\n?define\('WP_(HOME|SITEURL)',[^;]*\);/", '', $contents);
	$contents = preg_replace('/^<\?php/', "<?php\n\n" . $block . "\n", $contents, 1);

	file_put_contents($wpConfig, $contents);
}

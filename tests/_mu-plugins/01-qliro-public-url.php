<?php
/**
 * Plugin Name: Qliro Tests, public URL for Qliro's callbacks
 * Description: Hands Qliro the tunnel's public URL for its push callbacks, and serves those callbacks as local requests. Loaded only inside the Codeception test WP install.
 *
 * The browser drives the site on WORDPRESS_URL, so its uncached assets never spend the
 * ngrok request allowance, and Qliro accepts that local URL for everything the browser
 * follows (the confirmation and terms pages). Only the push callbacks, which Qliro calls
 * itself and wants on https, go through the tunnel.
 */

if (! defined('ABSPATH')) {
    exit;
}

// EndToEnd only: codeception.yml hands these to the built-in server, so the Integration
// and Harness suites, which never see them, are left alone.
$qliro_local  = rtrim((string) getenv('WORDPRESS_URL'), '/');
$qliro_public = rtrim((string) getenv('QLIRO_WORDPRESS_URL'), '/');

if ($qliro_local === '' || $qliro_public === '' || $qliro_local === $qliro_public) {
    return;
}

// Outbound: before the request body is built and signed, so nothing needs re-signing.
add_filter(
    'qliro_one_wc_merchant_urls',
    static function ($urls) use ($qliro_local, $qliro_public) {
        foreach (['push', 'om_push', 'notifications', 'save_card'] as $key) {
            if (isset($urls[$key]) && strpos($urls[$key], $qliro_local) === 0) {
                $urls[$key] = $qliro_public . substr($urls[$key], strlen($qliro_local));
            }
        }

        return $urls;
    }
);

// Inbound: ngrok keeps the public Host header, which WordPress would redirect to WP_HOME,
// and Qliro follows no redirects. So it is served as the local request it is.
$qliro_public_host = (string) parse_url($qliro_public, PHP_URL_HOST);
$qliro_forwarded   = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '')));
$qliro_host        = (string) strtok((string) ($_SERVER['HTTP_HOST'] ?? ''), ':');

if (PHP_SAPI !== 'cli' && ($qliro_host === $qliro_public_host || in_array($qliro_public_host, $qliro_forwarded, true))) {
    $_SERVER['HTTP_HOST']   = (string) parse_url($qliro_local, PHP_URL_HOST) . (parse_url($qliro_local, PHP_URL_PORT) ? ':' . parse_url($qliro_local, PHP_URL_PORT) : '');
    $_SERVER['SERVER_NAME'] = (string) parse_url($qliro_local, PHP_URL_HOST);
    unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
}

unset($qliro_local, $qliro_public, $qliro_public_host, $qliro_forwarded, $qliro_host);

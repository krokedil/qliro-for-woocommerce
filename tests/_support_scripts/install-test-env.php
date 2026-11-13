<?php
/**
 * Sets up the test WordPress install, and with `--fresh` reinstalls the site's tables.
 * Idempotent. Runs before Codeception, since the Symlinker needs WordPress configured,
 * so it starts the MySQL server itself; MysqlServerController then finds it running.
 */

use lucatume\WPBrowser\ManagedProcess\MysqlServer;
use lucatume\WPBrowser\Utils\Db as DbUtil;
use lucatume\WPBrowser\WordPress\Database\MysqlDatabase;
use lucatume\WPBrowser\WordPress\Installation;
use lucatume\WPBrowser\WordPress\InstallationState\Multisite;
use lucatume\WPBrowser\WordPress\InstallationState\Single;

$root         = dirname(__DIR__, 2);
$wpRoot       = $root . '/tests/_wordpress';
$muSource     = $root . '/tests/_mu-plugins';
$muPluginsDir = $wpRoot . '/wp-content/mu-plugins';
$sitePrefix   = 'wp_';

require $root . '/vendor/autoload.php';

// The database URL and site URL live in tests/.env, which only Codeception loads otherwise.
Dotenv\Dotenv::createImmutable($root . '/tests')->safeLoad();
\Codeception\Configuration::config($root . '/codeception.yml');

$db = DbUtil::parseDbUrl((string) ($_ENV['WORDPRESS_DB_URL'] ?? ''));
if (($db['type'] ?? '') !== 'mysql') {
    fwrite(STDERR, "WORDPRESS_DB_URL in tests/.env must be a mysql:// URL.\n");
    exit(1);
}
$dbHost = $db['host'];
$dbPort = (string) ($db['port'] ?: '3306');

// The MySQL server wp-browser downloads and runs, unless something already answers.
try {
    new PDO("mysql:host={$dbHost};port={$dbPort}", $db['user'], $db['password'], [PDO::ATTR_TIMEOUT => 1]);
} catch (PDOException $e) {
    echo "Starting MySQL server on port {$dbPort} (downloads MySQL once)...\n";

    // Outside the checkout: MySQL refuses a socket path over 107 characters, which the
    // extension's own tests/_output/_mysql_server makes in a deeply nested checkout.
    $serverDir = getenv('HOME') . '/.cache/qliro-for-woocommerce-tests/mysql';

    // Ubuntu 24.04+ ships libaio as libaio.so.1t64, and MySQL's build asks for libaio.so.1.
    $libaio = '/usr/lib/x86_64-linux-gnu/libaio.so.1t64';
    if (is_file($libaio) && ! is_file('/usr/lib/x86_64-linux-gnu/libaio.so.1')) {
        $libDir = $serverDir . '/lib';
        if (! is_dir($libDir)) {
            mkdir($libDir, 0777, true);
        }
        if (! is_link($libDir . '/libaio.so.1')) {
            symlink($libaio, $libDir . '/libaio.so.1');
        }
        // In all three, since Symfony Process builds mysqld's environment from $_SERVER and $_ENV too.
        $libPath = rtrim($libDir . ':' . (string) getenv('LD_LIBRARY_PATH'), ':');
        putenv("LD_LIBRARY_PATH={$libPath}");
        $_ENV['LD_LIBRARY_PATH'] = $_SERVER['LD_LIBRARY_PATH'] = $libPath;
    }

    $server = new MysqlServer($serverDir, (int) $dbPort, $db['name'], $db['user'], $db['password']);
    $server->start();
}

// One database for the E2E site and one for the Integration and Harness suites, so
// restoring the E2E fixture, which drops its whole database, never touches the other.
$testDbName = DbUtil::parseDbUrl((string) ($_ENV['TEST_DB_URL'] ?? ''))['name'];
$pdo        = new PDO("mysql:host={$dbHost};port={$dbPort}", $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (in_array('--fresh', $argv, true)) {
    echo "Dropping the site's database...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$db['name']}`");
}

foreach (array_filter([$db['name'], $testDbName]) as $name) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}`");
}

$database = new MysqlDatabase($db['name'], $db['user'], $db['password'], "{$dbHost}:{$dbPort}", $sitePrefix);

if (! is_file($wpRoot . '/wp-load.php')) {
    echo "Scaffolding WordPress into tests/_wordpress/ (downloads core once)...\n";
    if (! is_dir($wpRoot) && ! mkdir($wpRoot, 0777, true) && ! is_dir($wpRoot)) {
        fwrite(STDERR, "Failed to create {$wpRoot}\n");
        exit(1);
    }
    Installation::scaffold($wpRoot);
}

$installation = new Installation($wpRoot, false);
if (! $installation->isConfigured()) {
    echo "Configuring WordPress against MySQL...\n";
    $installation->configure($database);
}

// Patch WP_DEBUG in place so notices land in debug.log. Injecting a second define()
// would be silently dropped, because the generated file defines it first.
$wp_config_path = $wpRoot . '/wp-config.php';
if (is_file($wp_config_path)) {
    $wp_config_contents = (string) file_get_contents($wp_config_path);
    $updated_contents   = $wp_config_contents;

    // Flip the template's `define( 'WP_DEBUG', false );` to true.
    $updated_contents = (string) preg_replace(
        "/define\(\s*'WP_DEBUG'\s*,\s*false\s*\);/",
        "define( 'WP_DEBUG', true );",
        $updated_contents,
        1
    );

    if (false === strpos($updated_contents, 'WP_DEBUG_LOG')) {
        $updated_contents = (string) preg_replace(
            "/(define\(\s*'WP_DEBUG'\s*,\s*true\s*\);)/",
            "$1\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );\ndefine( 'SCRIPT_DEBUG', true );",
            $updated_contents,
            1
        );
    }

    if ($updated_contents !== $wp_config_contents) {
        file_put_contents($wp_config_path, $updated_contents);
        echo "Patched wp-config.php (WP_DEBUG=true, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false, SCRIPT_DEBUG=true).\n";
    }
}

$state = $installation->getState();
if (! ($state instanceof Single || $state instanceof Multisite)) {
    echo "Installing WordPress (DB tables + admin user)...\n";
    $installation->install(
        rtrim((string) ($_ENV['WORDPRESS_URL'] ?? 'http://localhost'), '/'),
        'admin',
        'password',
        'admin@localhost.test',
        'Qliro Checkout Test'
    );
}

if (! is_dir($muPluginsDir) && ! mkdir($muPluginsDir, 0777, true) && ! is_dir($muPluginsDir)) {
    fwrite(STDERR, "Failed to create {$muPluginsDir}\n");
    exit(1);
}

$copied = 0;
$wanted = [];
foreach (glob($muSource . '/*.php') as $file) {
    $wanted[] = basename($file);
    $dest     = $muPluginsDir . '/' . basename($file);
    if (copy($file, $dest)) {
        ++$copied;
    }
}

// Drop the ones an earlier checkout installed and this one no longer has, matched on our
// own plugin header so anything else in mu-plugins is left alone.
foreach (glob($muPluginsDir . '/*.php') as $installed) {
    if (in_array(basename($installed), $wanted, true)) {
        continue;
    }
    if (strpos((string) file_get_contents($installed), 'Plugin Name: Qliro Tests,') !== false) {
        unlink($installed);
    }
}

echo "Test environment ready ({$copied} project mu-plugin(s) installed).\n";

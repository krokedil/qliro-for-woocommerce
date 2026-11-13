<?php
/**
 * Waits until the MySQL server `dev:stop` killed has released its port. The stop only
 * sends SIGTERM, and MySQL keeps answering while it shuts down, so a server started
 * right after it can attach to the dying one and lose it a few seconds later.
 */

use lucatume\WPBrowser\Utils\Db as DbUtil;

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable($root . '/tests')->safeLoad();

$db   = DbUtil::parseDbUrl((string) ($_ENV['WORDPRESS_DB_URL'] ?? ''));
$host = (string) ($db['host'] ?? '127.0.0.1');
$port = (int) (($db['port'] ?? '') ?: 3306);

$timeout  = 30;
$deadline = microtime(true) + $timeout;

while (microtime(true) < $deadline) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($socket === false) {
        exit(0);
    }

    fclose($socket);
    usleep(250_000);
}

// Not fatal: something else may own the port, and install-test-env.php reports that better.
echo "MySQL on {$host}:{$port} still answers {$timeout}s after dev:stop, continuing anyway.\n";

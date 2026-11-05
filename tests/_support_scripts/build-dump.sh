#!/usr/bin/env bash
#
# Build tests/Support/Data/dump.sql from a clean WordPress install. Run by the
# Tests\Support\Extension\DumpBuilder extension before every EndToEnd suite, so the
# dump always matches the WooCommerce version composer installed and is not committed.
#
# This is the documented wp-browser workflow (see `vendor/bin/codecept
# wp:db:export`) plus one extra post-processing step: every WP boot
# (including the one wp:db:export performs) schedules Action Scheduler
# actions whose `schedule` column stores a PHP-serialized object. The
# SQLite Database Integration plugin can't round-trip that data without
# truncating it at the first NUL byte (`\0` from protected-property
# prefixes), so importing the dump on a fresh suite run triggers
# `unserialize(): Error at offset N` and aborts WP load. We work around
# that by stripping the AS data rows from the dump after export, the
# schema stays, and Action Scheduler repopulates the tables at runtime
# (those runtime rows are not read back in the same request, so the
# corruption is harmless to test execution).
#
# Pre-conditions: `composer install` has run, so tests/_wordpress exists, and the
# Symlinker has linked WooCommerce, Storefront and the plugin into it.

set -euo pipefail

WP_ROOT="tests/_wordpress"
DB_FILE="${WP_ROOT}/data/db.sqlite"
DB_SNAPSHOT="${WP_ROOT}/data/db.sqlite_snapshot"
DUMP_PATH="tests/Support/Data/dump.sql"

echo "==> Wiping SQLite DB..."
rm -f "${DB_FILE}" "${DB_FILE}-wal" "${DB_FILE}-shm" "${DB_SNAPSHOT}"

echo "==> Installing WordPress..."
php tests/_support_scripts/install-test-env.php

echo "==> Seeding plugins, theme, store, product and Qliro settings..."
php tests/_support_scripts/seed-dump-site.php

echo "==> Truncating Action Scheduler tables (must be empty in the dump, see header comment)..."
php -r "
\$pdo = new PDO('sqlite:${DB_FILE}');
\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['wp_actionscheduler_actions','wp_actionscheduler_claims','wp_actionscheduler_groups','wp_actionscheduler_logs'] as \$t) {
    try { \$pdo->exec(\"DELETE FROM \$t\"); } catch (Throwable \$e) {}
}
"

echo "==> Exporting dump via wp:db:export..."
vendor/bin/codecept wp:db:export "${WP_ROOT}" "${DUMP_PATH}"

echo "==> Stripping any AS data INSERTs that wp:db:export re-added during its own WP boot..."
php tests/_support_scripts/strip-actionscheduler-inserts.php "${DUMP_PATH}"

echo "==> Done. Dump: $(wc -c < "${DUMP_PATH}") bytes."

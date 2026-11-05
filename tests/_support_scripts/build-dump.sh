#!/usr/bin/env bash
#
# Build tests/Support/Data/dump.sql from a clean WordPress install. Run by the
# Tests\Support\Extension\DumpBuilder extension before every EndToEnd suite, so the
# dump always matches the WooCommerce version composer installed and is not committed.
#
# Pre-conditions: `composer install` has run, so tests/_wordpress exists, and the
# Symlinker has linked WooCommerce, Storefront and the plugin into it.

set -euo pipefail

WP_ROOT="tests/_wordpress"
DUMP_PATH="tests/Support/Data/dump.sql"

echo "==> Installing WordPress into an empty site database..."
php tests/_support_scripts/install-test-env.php --fresh

echo "==> Seeding plugins, theme, store, product and Qliro settings..."
php tests/_support_scripts/seed-dump-site.php

echo "==> Exporting dump via wp:db:export..."
vendor/bin/codecept wp:db:export "${WP_ROOT}" "${DUMP_PATH}"

echo "==> Done. Dump: $(wc -c < "${DUMP_PATH}") bytes."

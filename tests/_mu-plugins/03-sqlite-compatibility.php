<?php
/**
 * Plugin Name: Qliro Tests, SQLite compatibility
 * Description: Covers for what the MySQL-to-SQLite translation gets wrong: the unique indexes the schema drops, MySQL's "no limit" number, and WooCommerce Admin's report queries. Loaded only inside the Codeception test WP install.
 *
 * WordPress and WooCommerce save these rows with `INSERT ... ON DUPLICATE KEY UPDATE` or
 * `INSERT IGNORE`, which only update when the unique index exists. Without it every save
 * appends a row and reads pick one at random: a cart that loses items, or an HPOS order
 * whose paid date reads back empty, so the plugin skips capturing or cancelling it.
 *
 * Created here rather than in the dump, which WPDb reloads before every test.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('FQDB') || ! is_file(FQDB)) {
    return;
}

// WooCommerce Admin's report queries use SQL the translation cannot run (CONCAT_WS, among
// others), so every wp-admin screen hangs on its /wc-admin and /wc-analytics REST calls.
add_filter('woocommerce_admin_disabled', '__return_true');

// The filter above stops the features, not the admin bundle's calls to them.
add_filter(
    'rest_pre_dispatch',
    static function ($result, $server, $request) {
        return preg_match('#^/wc-admin/|^/wc-analytics/#', $request->get_route()) ? new WP_REST_Response([], 200) : $result;
    },
    0,
    3
);

// WooCommerce asks for "no limit" as MySQL's `LIMIT 0, 18446744073709551615`, which overflows
// SQLite's integer and fails the query; `get_refunds()` then answers empty on HPOS.
add_filter(
    'query',
    static function ($query) {
        return is_string($query)
            ? str_replace(', 18446744073709551615', ', 9223372036854775807', $query)
            : $query;
    }
);

add_action(
    'muplugins_loaded',
    static function (): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        global $wpdb;
        $prefix = $wpdb instanceof wpdb ? $wpdb->prefix : 'wp_';

        // Table => the columns of the unique key the MySQL schema has.
        $indexes = [
            'woocommerce_sessions'       => 'session_key',
            'options'                    => 'option_name',
            'wc_order_operational_data'  => 'order_id',
            'wc_order_addresses'         => 'address_type, order_id',
        ];

        // Its own connection: this is SQLite DDL, not something the MySQL-to-SQLite
        // translation in front of $wpdb can carry.
        try {
            $sqlite = new PDO('sqlite:' . FQDB);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Throwable $e) {
            return;
        }

        foreach ($indexes as $table => $columns) {
            $name  = 'qliro_tests_unique_' . $table;
            $table = $prefix . $table;

            try {
                $exists = $sqlite
                    ->query("SELECT name FROM sqlite_master WHERE type = 'index' AND name = '{$name}'")
                    ->fetchColumn();
                if ($exists !== false) {
                    continue;
                }

                // Duplicates already written go first, newest kept, so the index can be made.
                $sqlite->exec(
                    "DELETE FROM `{$table}` WHERE rowid NOT IN ("
                    . "SELECT MAX(rowid) FROM `{$table}` GROUP BY {$columns})"
                );
                $sqlite->exec("CREATE UNIQUE INDEX {$name} ON `{$table}` ({$columns})");
            } catch (Throwable $e) {
                // No table yet, or a database busy reloading the dump. The next request
                // is a new process and tries again.
            }
        }
    },
    0
);

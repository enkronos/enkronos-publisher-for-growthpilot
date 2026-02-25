<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class DB {
    
    private static string $table_name;
    
    private static function get_safe_table_name(): string {
        return (string)preg_replace('/[^A-Za-z0-9_]/', '', self::get_table_name());
    }
    
    public static function init(): void {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'enkrpufo_logs';
    }
    
    public static function get_table_name(): string {
        if (empty(self::$table_name)) {
            self::init();
        }
        return self::$table_name;
    }
    
    public static function create_table(): void {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            key_id VARCHAR(64) NOT NULL,
            ip VARCHAR(64) NOT NULL,
            method VARCHAR(8) NOT NULL,
            route VARCHAR(255) NOT NULL,
            status_code INT NOT NULL,
            duration_ms INT NOT NULL,
            body_hash CHAR(64) NOT NULL,
            wp_object_id BIGINT(20) UNSIGNED NULL,
            error_code VARCHAR(64) NULL,
            PRIMARY KEY (id),
            KEY key_id (key_id),
            KEY created_at (created_at),
            KEY request_id (request_id)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    public static function log_request(array $data): void {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional write to plugin-owned log table.
        $wpdb->insert(
            self::get_table_name(),
            [
                'request_id' => $data['request_id'] ?? '',
                'created_at' => gmdate('Y-m-d H:i:s'),
                'key_id' => $data['key_id'] ?? 'unknown',
                'ip' => $data['ip'] ?? '',
                'method' => $data['method'] ?? '',
                'route' => $data['route'] ?? '',
                'status_code' => $data['status_code'] ?? 0,
                'duration_ms' => $data['duration_ms'] ?? 0,
                'body_hash' => $data['body_hash'] ?? '',
                'wp_object_id' => $data['wp_object_id'] ?? null,
                'error_code' => $data['error_code'] ?? null,
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s'
            ]
        );
    }
    
    public static function get_logs(array $args = []): array {
        global $wpdb;
        
        $table = self::get_safe_table_name();
        $per_page = max(1, (int)($args['per_page'] ?? 20));
        $page = max(1, (int)($args['page'] ?? 1));
        $offset = ($page - 1) * $per_page;
        $key_id = isset($args['key_id']) ? sanitize_text_field((string)$args['key_id']) : '';
        $route_contains = isset($args['route_contains']) ? sanitize_text_field((string)$args['route_contains']) : '';
        $status_code = isset($args['status_code']) ? (int)$args['status_code'] : 0;
        $route_like = '%' . $wpdb->esc_like($route_contains) . '%';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional read from plugin-owned log table.
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i
                WHERE (%s = '' OR key_id = %s)
                    AND (%s = '' OR route LIKE %s)
                    AND (%d = 0 OR status_code = %d)
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $table,
                $key_id,
                $key_id,
                $route_contains,
                $route_like,
                $status_code,
                $status_code,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional aggregate query on plugin-owned log table.
        $total = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i
                WHERE (%s = '' OR key_id = %s)
                    AND (%s = '' OR route LIKE %s)
                    AND (%d = 0 OR status_code = %d)",
                $table,
                $key_id,
                $key_id,
                $route_contains,
                $route_like,
                $status_code,
                $status_code
            )
        );
        
        return [
            'logs' => $logs ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }
    
    public static function delete_old_logs(): void {
        global $wpdb;
        
        $retention_days = max(1, (int)get_option('enkrpufo_log_retention_days', 30));
        $table = self::get_safe_table_name();
        $cutoff_date = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is prepared inline and targets plugin-owned log table.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE created_at < %s",
                $table,
                $cutoff_date
            )
        );
    }
    
    public static function delete_all_logs(): void {
        global $wpdb;
        
        $table = self::get_safe_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared delete used to clear plugin-owned log table.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE id >= %d",
                $table,
                0
            )
        );
    }
    
    public static function export_logs_csv(array $args = []): string {
        $result = self::get_logs(array_merge($args, ['per_page' => 10000]));
        $logs = $result['logs'];
        
        if (empty($logs)) {
            return '';
        }
        
        $headers = array_keys($logs[0]);
        $lines = [
            implode(',', array_map([self::class, 'escape_csv_value'], $headers)),
        ];
        
        foreach ($logs as $log) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = self::escape_csv_value($log[$header] ?? '');
            }
            $lines[] = implode(',', $row);
        }
        
        return implode("\n", $lines) . "\n";
    }
    
    private static function escape_csv_value($value): string {
        $string = (string)$value;
        $string = str_replace('"', '""', $string);
        
        if (preg_match('/[",\r\n]/', $string) === 1) {
            return '"' . $string . '"';
        }
        
        return $string;
    }
}

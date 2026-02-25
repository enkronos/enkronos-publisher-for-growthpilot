<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    
    private static ?string $request_id = null;
    private static ?float $start_time = null;
    private static array $log_data = [];
    
    public static function start_request(): void {
        $request_method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash((string)$_SERVER['REQUEST_METHOD']))
            : '';
        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? esc_url_raw(wp_unslash((string)$_SERVER['REQUEST_URI']))
            : '';
        
        self::$request_id = Utils::generate_uuid_v4();
        self::$start_time = microtime(true);
        self::$log_data = [
            'request_id' => self::$request_id,
            'ip' => Utils::get_client_ip(),
            'method' => $request_method,
            'route' => $request_uri,
            'body_hash' => hash('sha256', file_get_contents('php://input')),
        ];
    }
    
    public static function get_request_id(): string {
        if (self::$request_id === null) {
            self::start_request();
        }
        return self::$request_id;
    }
    
    public static function set_key_id(string $key_id): void {
        self::$log_data['key_id'] = $key_id;
    }
    
    public static function set_wp_object_id(int $object_id): void {
        self::$log_data['wp_object_id'] = $object_id;
    }
    
    public static function log_response(int $status_code, ?string $error_code = null): void {
        if (!get_option('enkrpufo_logging_enabled', true)) {
            return;
        }
        
        $duration_ms = self::$start_time 
            ? (int)((microtime(true) - self::$start_time) * 1000) 
            : 0;
        
        self::$log_data['status_code'] = $status_code;
        self::$log_data['duration_ms'] = $duration_ms;
        
        if ($error_code !== null) {
            self::$log_data['error_code'] = $error_code;
        }
        
        DB::log_request(self::$log_data);
    }
}

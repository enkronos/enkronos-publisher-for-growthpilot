<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class Utils {
    
    private static function get_server_var(string $key): string {
        if (!isset($_SERVER[$key])) {
            return '';
        }
        
        return sanitize_text_field(wp_unslash((string)$_SERVER[$key]));
    }
    
    public static function generate_uuid_v4(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    public static function generate_key_id(): string {
        return 'kp_' . bin2hex(random_bytes(16));
    }
    
    public static function generate_secret(): string {
        return 'ks_' . bin2hex(random_bytes(32));
    }
    
    public static function hash_secret(string $secret): string {
        return hash_hmac('sha256', $secret, wp_salt('auth'));
    }
    
    public static function verify_secret(string $secret, string $hash): bool {
        return hash_equals($hash, self::hash_secret($secret));
    }

    public static function encrypt_secret(string $secret): string {
        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        if ($iv_length === false) {
            throw new \RuntimeException('Secret encryption is unavailable on this PHP runtime.');
        }

        $iv = random_bytes($iv_length);
        $key = hash('sha256', wp_salt('auth'), true);
        $encrypted = openssl_encrypt($secret, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Secret encryption failed.');
        }

        return base64_encode($iv . $encrypted);
    }

    public static function decrypt_secret(string $encoded): ?string {
        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        if ($iv_length === false) return null;

        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) <= $iv_length) return null;

        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
        $key = hash('sha256', wp_salt('auth'), true);
        $secret = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return is_string($secret) && $secret !== '' ? $secret : null;
    }
    
    public static function get_client_ip(): string {
        $forwarded_for = self::get_server_var('HTTP_X_FORWARDED_FOR');
        if ($forwarded_for !== '') {
            $ips = array_map('trim', explode(',', $forwarded_for));
            $candidate = $ips[0] ?? '';
            
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        
        $real_ip = self::get_server_var('HTTP_X_REAL_IP');
        if ($real_ip !== '' && filter_var($real_ip, FILTER_VALIDATE_IP)) {
            return $real_ip;
        }
        
        $remote_addr = self::get_server_var('REMOTE_ADDR');
        if ($remote_addr !== '' && filter_var($remote_addr, FILTER_VALIDATE_IP)) {
            return $remote_addr;
        }
        
        return '';
    }
    
    public static function ip_in_range(string $ip, string $range): bool {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        [$subnet, $mask] = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = -1 << (32 - (int)$mask);
            
            return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
        }
        
        // IPv6 support would go here
        return false;
    }
    
    public static function success_response($data, array $meta = []): array {
        return [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'request_id' => Logger::get_request_id(),
                'server_time' => (int)(microtime(true) * 1000),
            ], $meta),
        ];
    }
    
    public static function error_response(string $code, string $message, array $details = [], int $status = 400): \WP_Error {
        $data = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => [
                'request_id' => Logger::get_request_id(),
                'server_time' => (int)(microtime(true) * 1000),
            ],
        ];
        
        return new \WP_Error($code, $message, ['status' => $status, 'data' => $data]);
    }
    
    public static function sanitize_post_payload(array $payload): array {
        return [
            'post_type' => sanitize_key($payload['post_type'] ?? 'post'),
            'title' => sanitize_text_field($payload['title'] ?? ''),
            'slug' => sanitize_title($payload['slug'] ?? ''),
            'status' => sanitize_key($payload['status'] ?? get_option('enkrpufo_default_post_status', 'draft')),
            'content_html' => wp_kses_post($payload['content_html'] ?? ''),
            'excerpt' => sanitize_textarea_field($payload['excerpt'] ?? ''),
            'author_id' => (int)($payload['author_id'] ?? get_option('enkrpufo_default_author_id', 1)),
            'date_gmt' => sanitize_text_field($payload['date_gmt'] ?? ''),
            'categories' => array_map('intval', (array)($payload['categories'] ?? [])),
            'tags' => array_map('intval', (array)($payload['tags'] ?? [])),
            'featured_media' => $payload['featured_media'] ?? null,
            'seo' => $payload['seo'] ?? null,
            'custom_fields' => (array)($payload['custom_fields'] ?? []),
        ];
    }
    
    public static function validate_post_type(string $post_type): bool {
        return in_array($post_type, ['post', 'page'], true);
    }
    
    public static function validate_post_status(string $status): bool {
        return in_array($status, ['draft', 'publish', 'future', 'pending', 'private'], true);
    }
}

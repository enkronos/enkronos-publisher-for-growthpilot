<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class Auth {
    
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes
    private const NONCE_EXPIRY = 600; // 10 minutes
    
    public static function authenticate(\WP_REST_Request $request, array $required_scopes = []): bool|\WP_Error {
        
        if (!get_option('enkrpufo_enabled', true)) {
            return Utils::error_response(
                'PLUGIN_DISABLED',
                'The Enkronos Publisher for GrowthPilot.cloud plugin is currently disabled',
                [],
                503
            );
        }
        
        $headers = self::extract_headers($request);
        
        if (!$headers['key_id'] || !$headers['timestamp'] || !$headers['nonce'] || !$headers['signature']) {
            return Utils::error_response(
                'AUTH_MISSING_HEADERS',
                'Missing required authentication headers',
                ['required' => ['X-GP-KEY-ID', 'X-GP-TIMESTAMP', 'X-GP-NONCE', 'X-GP-SIGNATURE']],
                401
            );
        }
        
        Logger::set_key_id($headers['key_id']);
        
        // Validate timestamp
        $timestamp_error = self::validate_timestamp($headers['timestamp']);
        if ($timestamp_error !== true) {
            return $timestamp_error;
        }
        
        // Get and validate API key
        $key_data = self::get_api_key($headers['key_id']);
        if (is_wp_error($key_data)) {
            return $key_data;
        }
        
        // Check nonce replay
        $nonce_error = self::check_nonce_replay($headers['key_id'], $headers['nonce']);
        if ($nonce_error !== true) {
            return $nonce_error;
        }
        
        // Validate signature
        $signature_error = self::validate_signature($request, $headers, $key_data['secret']);
        if ($signature_error !== true) {
            return $signature_error;
        }
        
        // Check IP restrictions
        $ip_error = self::check_ip_restrictions($key_data['allowed_ips']);
        if ($ip_error !== true) {
            return $ip_error;
        }
        
        // Check scopes
        $scope_error = self::check_scopes($key_data['scopes'], $required_scopes);
        if ($scope_error !== true) {
            return $scope_error;
        }
        
        // Rate limiting
        $rate_error = self::check_rate_limit($headers['key_id']);
        if ($rate_error !== true) {
            return $rate_error;
        }
        
        // Update last_used_at
        self::update_key_last_used($headers['key_id']);
        
        return true;
    }
    
    private static function extract_headers(\WP_REST_Request $request): array {
        return [
            'key_id' => $request->get_header('X-GP-KEY-ID'),
            'timestamp' => $request->get_header('X-GP-TIMESTAMP'),
            'nonce' => $request->get_header('X-GP-NONCE'),
            'signature' => $request->get_header('X-GP-SIGNATURE'),
        ];
    }
    
    private static function validate_timestamp($timestamp): bool|\WP_Error {
        $now = time() * 1000;
        $timestamp = (int)$timestamp;
        $diff = abs($now - $timestamp) / 1000;
        
        if ($diff > self::TIMESTAMP_TOLERANCE) {
            return Utils::error_response(
                'AUTH_TIMESTAMP_SKEW',
                'Request timestamp is outside acceptable range',
                ['max_skew_seconds' => self::TIMESTAMP_TOLERANCE],
                401
            );
        }
        
        return true;
    }
    
    private static function get_api_key(string $key_id): array|\WP_Error {
        $keys = get_option('enkrpufo_api_keys', []);
        
        foreach ($keys as $key) {
            if ($key['key_id'] === $key_id) {
                if (!empty($key['revoked_at'])) {
                    return Utils::error_response(
                        'AUTH_REVOKED_KEY',
                        'This API key has been revoked',
                        [],
                        401
                    );
                }
                
                $secret = null;
                if (!empty($key['secret_encrypted']) && is_string($key['secret_encrypted'])) {
                    $secret = Utils::decrypt_secret($key['secret_encrypted']);
                } elseif (!empty($key['secret']) && is_string($key['secret'])) {
                    // Compatibility for keys created by an older installation
                    // that retained the secret in the option value.
                    $secret = $key['secret'];
                }
                if ($secret === null) {
                    return Utils::error_response(
                        'AUTH_KEY_MATERIAL_UNAVAILABLE',
                        'This API key must be regenerated before it can authenticate requests.',
                        [],
                        401
                    );
                }

                $key['secret'] = $secret;
                return $key;
            }
        }
        
        return Utils::error_response(
            'AUTH_INVALID_KEY',
            'Invalid API key',
            [],
            401
        );
    }
    
    private static function check_nonce_replay(string $key_id, string $nonce): bool|\WP_Error {
        $transient_key = 'enkrpufo_nonce_' . $key_id . '_' . $nonce;
        
        if (get_transient($transient_key)) {
            return Utils::error_response(
                'AUTH_NONCE_REPLAY',
                'Nonce has already been used',
                [],
                401
            );
        }
        
        set_transient($transient_key, true, self::NONCE_EXPIRY);
        
        return true;
    }
    
    private static function validate_signature(\WP_REST_Request $request, array $headers, string $secret): bool|\WP_Error {
        $body = $request->get_body();
        $body_hash = hash('sha256', $body);
        
        $route = ltrim((string)$request->get_route(), '/');
        $rest_path = wp_parse_url(rest_url($route), PHP_URL_PATH);
        $path = is_string($rest_path) && $rest_path !== '' ? $rest_path : '/' . $route;
        $method = strtoupper((string)$request->get_method());
        
        $payload_string = $headers['timestamp'] . "\n"
            . $headers['nonce'] . "\n"
            . $method . "\n"
            . $path . "\n"
            . $body_hash;
        
        $expected_signature = base64_encode(hash_hmac('sha256', $payload_string, $secret, true));
        
        if (!hash_equals($expected_signature, $headers['signature'])) {
            return Utils::error_response(
                'AUTH_INVALID_SIGNATURE',
                'Invalid request signature',
                [],
                401
            );
        }
        
        return true;
    }
    
    private static function check_ip_restrictions(array $allowed_ips): bool|\WP_Error {
        if (empty($allowed_ips)) {
            return true;
        }
        
        $client_ip = Utils::get_client_ip();
        
        foreach ($allowed_ips as $allowed) {
            if (Utils::ip_in_range($client_ip, trim($allowed))) {
                return true;
            }
        }
        
        return Utils::error_response(
            'AUTH_IP_NOT_ALLOWED',
            'Your IP address is not authorized',
            ['ip' => $client_ip],
            403
        );
    }
    
    private static function check_scopes(array $key_scopes, array $required_scopes): bool|\WP_Error {
        if (empty($required_scopes)) {
            return true;
        }
        
        foreach ($required_scopes as $required) {
            if (!in_array($required, $key_scopes, true)) {
                return Utils::error_response(
                    'AUTH_SCOPE_DENIED',
                    'Insufficient permissions',
                    ['required_scopes' => $required_scopes],
                    403
                );
            }
        }
        
        return true;
    }
    
    private static function check_rate_limit(string $key_id): bool|\WP_Error {
        $limit = (int)get_option('enkrpufo_rate_limit_per_minute', 120);
        $transient_key = 'enkrpufo_rate_' . $key_id . '_' . floor(time() / 60);
        
        $count = (int)get_transient($transient_key);
        
        if ($count >= $limit) {
            return Utils::error_response(
                'RATE_LIMIT_EXCEEDED',
                'Rate limit exceeded',
                ['limit' => $limit, 'window' => '1 minute'],
                429
            );
        }
        
        set_transient($transient_key, $count + 1, 60);
        
        return true;
    }
    
    private static function update_key_last_used(string $key_id): void {
        $keys = get_option('enkrpufo_api_keys', []);
        
        foreach ($keys as &$key) {
            if ($key['key_id'] === $key_id) {
                $key['last_used_at'] = gmdate('Y-m-d H:i:s');
                break;
            }
        }
        
        update_option('enkrpufo_api_keys', $keys);
    }
    
    public static function create_api_key(string $name, array $scopes, array $allowed_ips = []): array {
        $key_id = Utils::generate_key_id();
        $secret = Utils::generate_secret();
        $key_hash = Utils::hash_secret($secret);
        
        $key_data = [
            'key_id' => $key_id,
            'key_hash' => $key_hash,
            'name' => sanitize_text_field($name),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'last_used_at' => null,
            'revoked_at' => null,
            'scopes' => array_map('sanitize_key', $scopes),
            'allowed_ips' => array_map('sanitize_text_field', $allowed_ips),
            'secret' => $secret,
        ];
        
        $keys = get_option('enkrpufo_api_keys', []);
        
        $stored_key = $key_data;
        unset($stored_key['secret']);
        $stored_key['secret_encrypted'] = Utils::encrypt_secret($secret);
        
        $keys[] = $stored_key;
        update_option('enkrpufo_api_keys', $keys);
        
        return $key_data;
    }
    
    public static function revoke_api_key(string $key_id): bool {
        $keys = get_option('enkrpufo_api_keys', []);
        
        foreach ($keys as &$key) {
            if ($key['key_id'] === $key_id) {
                $key['revoked_at'] = gmdate('Y-m-d H:i:s');
                update_option('enkrpufo_api_keys', $keys);
                return true;
            }
        }
        
        return false;
    }
    
    public static function get_available_scopes(): array {
        return [
            'admin:read' => 'View system status and settings',
            'content:read' => 'Read posts, pages, and taxonomies',
            'content:write' => 'Create and update posts and pages',
            'content:delete' => 'Delete posts and pages',
            'media:write' => 'Upload media files',
            'taxonomy:write' => 'Create and manage terms',
        ];
    }
}

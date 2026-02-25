<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    private static function input_string(int $type, string $key, string $default = ''): string {
        $value = filter_input($type, $key, FILTER_UNSAFE_RAW);
        if ($value === null || $value === false) {
            return $default;
        }
        
        return sanitize_text_field(wp_unslash((string)$value));
    }
    
    private static function input_array(int $type, string $key): array {
        $value = filter_input($type, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        if (!is_array($value)) {
            return [];
        }
        
        return array_map(
            static fn($item): string => sanitize_text_field(wp_unslash((string)$item)),
            $value
        );
    }
    
    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_post_enkrpufo_generate_key', [self::class, 'handle_generate_key']);
        add_action('admin_post_enkrpufo_revoke_key', [self::class, 'handle_revoke_key']);
        add_action('admin_post_enkrpufo_save_defaults', [self::class, 'handle_save_defaults']);
        add_action('admin_post_enkrpufo_delete_logs', [self::class, 'handle_delete_logs']);
        add_action('admin_post_enkrpufo_export_logs', [self::class, 'handle_export_logs']);
    }
    
    public static function add_menu(): void {
        add_menu_page(
            'Enkronos Publisher for GrowthPilot.cloud',
            'Enkronos Publisher',
            'manage_options',
            'enkrpufo-publisher',
            [self::class, 'render_connection_page'],
            'dashicons-rest-api',
            30
        );
        
        add_submenu_page(
            'enkrpufo-publisher',
            'Connection',
            'Connection',
            'manage_options',
            'enkrpufo-publisher',
            [self::class, 'render_connection_page']
        );
        
        add_submenu_page(
            'enkrpufo-publisher',
            'Defaults',
            'Defaults',
            'manage_options',
            'enkrpufo-publisher-defaults',
            [self::class, 'render_defaults_page']
        );
        
        add_submenu_page(
            'enkrpufo-publisher',
            'Logs',
            'Logs',
            'manage_options',
            'enkrpufo-publisher-logs',
            [self::class, 'render_logs_page']
        );
        
        add_submenu_page(
            'enkrpufo-publisher',
            'Health',
            'Health',
            'manage_options',
            'enkrpufo-publisher-health',
            [self::class, 'render_health_page']
        );
    }
    
    public static function enqueue_assets($hook): void {
        if (strpos($hook, 'enkrpufo-publisher') === false) {
            return;
        }
        
        wp_enqueue_style('enkrpufo-pub-admin', ENKRPUFO_PLUGIN_URL . 'assets/admin.css', [], ENKRPUFO_VERSION);
        wp_enqueue_script('enkrpufo-pub-admin', ENKRPUFO_PLUGIN_URL . 'assets/admin.js', ['jquery'], ENKRPUFO_VERSION, true);
    }
    
    // Connection page
    public static function render_connection_page(): void {
        $enabled = get_option('enkrpufo_enabled', true);
        $keys = get_option('enkrpufo_api_keys', []);
        $new_key = get_transient('enkrpufo_new_key');
        
        if ($new_key) {
            delete_transient('enkrpufo_new_key');
        }
        
        ?>
        <div class="wrap enkrpufo-pub-admin">
            <h1>Enkronos Publisher for GrowthPilot.cloud - Connection</h1>
            
            <?php if ($new_key): ?>
                <div class="notice notice-success">
                    <h3>⚠️ API Key Generated - Save These Credentials Now!</h3>
                    <p><strong>This is the only time you'll see the secret. Store it securely.</strong></p>
                    <table class="widefat fixed">
                        <tr>
                            <th>Key ID:</th>
                            <td><code><?php echo esc_html($new_key['key_id']); ?></code></td>
                        </tr>
                        <tr>
                            <th>Secret:</th>
                            <td><code style="color: red; font-weight: bold;"><?php echo esc_html($new_key['secret']); ?></code></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="enkrpufo-pub-card">
                <h2>Plugin Status</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('enkrpufo_defaults', 'enkrpufo_nonce'); ?>
                    <input type="hidden" name="action" value="enkrpufo_save_defaults">
                    <label>
                        <input type="checkbox" name="enkrpufo_enabled" value="1" <?php checked($enabled); ?>>
                        Enable Enkronos Publisher for GrowthPilot.cloud
                    </label>
                    <button type="submit" class="button button-primary">Save</button>
                </form>
            </div>
            
            <div class="enkrpufo-pub-card">
                <h2>Generate API Key</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('enkrpufo_generate_key', 'enkrpufo_nonce'); ?>
                    <input type="hidden" name="action" value="enkrpufo_generate_key">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="key_name">Key Name</label></th>
                            <td><input type="text" id="key_name" name="key_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th>Scopes</th>
                            <td>
                                <?php foreach (Auth::get_available_scopes() as $scope => $description): ?>
                                    <label style="display: block;">
                                        <input type="checkbox" name="scopes[]" value="<?php echo esc_attr($scope); ?>">
                                        <strong><?php echo esc_html($scope); ?></strong> - <?php echo esc_html($description); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="allowed_ips">Allowed IPs</label></th>
                            <td>
                                <textarea id="allowed_ips" name="allowed_ips" rows="4" class="large-text" placeholder="One IP or CIDR per line&#10;e.g., 192.168.1.1&#10;or 10.0.0.0/8"></textarea>
                                <p class="description">Leave empty to allow all IPs</p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" class="button button-primary">Generate API Key</button>
                </form>
            </div>
            
            <div class="enkrpufo-pub-card">
                <h2>API Keys</h2>
                <?php if (empty($keys)): ?>
                    <p>No API keys created yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Key ID</th>
                                <th>Scopes</th>
                                <th>Created</th>
                                <th>Last Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keys as $key): ?>
                                <tr>
                                    <td><?php echo esc_html($key['name']); ?></td>
                                    <td><code><?php echo esc_html($key['key_id']); ?></code></td>
                                    <td><?php echo esc_html(implode(', ', $key['scopes'])); ?></td>
                                    <td><?php echo esc_html($key['created_at']); ?></td>
                                    <td><?php echo esc_html($key['last_used_at'] ?? 'Never'); ?></td>
                                    <td>
                                        <?php if ($key['revoked_at']): ?>
                                            <span style="color: red;">Revoked</span>
                                        <?php else: ?>
                                            <span style="color: green;">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$key['revoked_at']): ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                                <?php wp_nonce_field('enkrpufo_revoke_key', 'enkrpufo_nonce'); ?>
                                                <input type="hidden" name="action" value="enkrpufo_revoke_key">
                                                <input type="hidden" name="key_id" value="<?php echo esc_attr($key['key_id']); ?>">
                                                <button type="submit" class="button button-small" onclick="return confirm('Revoke this key?');">Revoke</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    // Defaults page
    public static function render_defaults_page(): void {
        $default_author = (int)get_option('enkrpufo_default_author_id', 1);
        $default_status = get_option('enkrpufo_default_post_status', 'draft');
        $default_category = (int)get_option('enkrpufo_default_category_id', 0);
        $rate_limit = (int)get_option('enkrpufo_rate_limit_per_minute', 120);
        $logging_enabled = (bool)get_option('enkrpufo_logging_enabled', true);
        $log_retention = (int)get_option('enkrpufo_log_retention_days', 30);
        
        ?>
        <div class="wrap enkrpufo-pub-admin">
            <h1>Enkronos Publisher for GrowthPilot.cloud - Defaults</h1>
            
            <div class="enkrpufo-pub-card">
                <h2>Default Settings</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('enkrpufo_defaults', 'enkrpufo_nonce'); ?>
                    <input type="hidden" name="action" value="enkrpufo_save_defaults">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="default_author">Default Author</label></th>
                            <td>
                                <?php wp_dropdown_users([
                                    'name' => 'enkrpufo_default_author_id',
                                    'id' => 'default_author',
                                    'selected' => $default_author,
                                ]); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="default_status">Default Post Status</label></th>
                            <td>
                                <select name="enkrpufo_default_post_status" id="default_status">
                                    <option value="draft" <?php selected($default_status, 'draft'); ?>>Draft</option>
                                    <option value="publish" <?php selected($default_status, 'publish'); ?>>Publish</option>
                                    <option value="pending" <?php selected($default_status, 'pending'); ?>>Pending Review</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="default_category">Default Category</label></th>
                            <td>
                                <?php wp_dropdown_categories([
                                    'name' => 'enkrpufo_default_category_id',
                                    'id' => 'default_category',
                                    'selected' => $default_category,
                                    'show_option_none' => 'None',
                                    'option_none_value' => 0,
                                ]); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="rate_limit">Rate Limit (per minute)</label></th>
                            <td>
                                <input type="number" id="rate_limit" name="enkrpufo_rate_limit_per_minute" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="1000">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="logging_enabled">Enable Logging</label></th>
                            <td>
                                <input type="checkbox" id="logging_enabled" name="enkrpufo_logging_enabled" value="1" <?php checked($logging_enabled); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="log_retention">Log Retention (days)</label></th>
                            <td>
                                <input type="number" id="log_retention" name="enkrpufo_log_retention_days" value="<?php echo esc_attr($log_retention); ?>" min="1" max="365">
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" class="button button-primary">Save Settings</button>
                </form>
            </div>
        </div>
        <?php
    }
    
    // Logs page
    public static function render_logs_page(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin filters.
        $page = max(1, (int)self::input_string(INPUT_GET, 'paged', '1'));
        $key_id = self::input_string(INPUT_GET, 'key_id');
        $route_contains = self::input_string(INPUT_GET, 'route_contains');
        $status_code = (int)self::input_string(INPUT_GET, 'status_code', '0');
        // phpcs:enable
        
        $result = DB::get_logs([
            'page' => $page,
            'per_page' => 20,
            'key_id' => $key_id,
            'route_contains' => $route_contains,
            'status_code' => $status_code ?: null,
        ]);
        
        ?>
        <div class="wrap enkrpufo-pub-admin">
            <h1>Enkronos Publisher for GrowthPilot.cloud - Logs</h1>
            
            <div class="enkrpufo-pub-card">
                <h2>Filter Logs</h2>
                <form method="get">
                    <input type="hidden" name="page" value="enkrpufo-publisher-logs">
                    <table class="form-table">
                        <tr>
                            <th><label for="key_id">Key ID</label></th>
                            <td><input type="text" id="key_id" name="key_id" value="<?php echo esc_attr($key_id); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="route_contains">Route Contains</label></th>
                            <td><input type="text" id="route_contains" name="route_contains" value="<?php echo esc_attr($route_contains); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="status_code">Status Code</label></th>
                            <td><input type="number" id="status_code" name="status_code" value="<?php echo esc_attr($status_code); ?>"></td>
                        </tr>
                    </table>
                    <button type="submit" class="button">Filter</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=enkrpufo-publisher-logs')); ?>" class="button">Clear</a>
                </form>
            </div>
            
            <div class="enkrpufo-pub-card">
                <div style="margin-bottom: 10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                        <?php wp_nonce_field('enkrpufo_export_logs', 'enkrpufo_nonce'); ?>
                        <input type="hidden" name="action" value="enkrpufo_export_logs">
                        <button type="submit" class="button">Export CSV</button>
                    </form>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                        <?php wp_nonce_field('enkrpufo_delete_logs', 'enkrpufo_nonce'); ?>
                        <input type="hidden" name="action" value="enkrpufo_delete_logs">
                        <button type="submit" class="button" onclick="return confirm('Delete all logs?');">Delete All Logs</button>
                    </form>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Key ID</th>
                            <th>Method</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result['logs'])): ?>
                            <tr><td colspan="7">No logs found</td></tr>
                        <?php else: ?>
                            <?php foreach ($result['logs'] as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log['created_at']); ?></td>
                                    <td><code><?php echo esc_html($log['key_id']); ?></code></td>
                                    <td><?php echo esc_html($log['method']); ?></td>
                                    <td><?php echo esc_html($log['route']); ?></td>
                                    <td><?php echo esc_html($log['status_code']); ?></td>
                                    <td><?php echo esc_html($log['duration_ms']); ?>ms</td>
                                    <td><?php echo esc_html($log['error_code'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($result['total_pages'] > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            echo wp_kses_post((string)paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $result['total_pages'],
                                'current' => $page,
                            ]));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    // Health page
    public static function render_health_page(): void {
        $checks = [
            'rest_enabled' => rest_url() !== false,
            'permalinks' => get_option('permalink_structure') !== '',
            'plugin_enabled' => (bool)get_option('enkrpufo_enabled', true),
            'cron_enabled' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
        ];
        
        ?>
        <div class="wrap enkrpufo-pub-admin">
            <h1>Enkronos Publisher for GrowthPilot.cloud - Health</h1>
            
            <div class="enkrpufo-pub-card">
                <h2>System Status</h2>
                <table class="widefat">
                    <tr>
                        <th>Plugin Version:</th>
                        <td><?php echo esc_html(ENKRPUFO_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>WordPress Version:</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version:</th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>SEO Plugin:</th>
                        <td><?php echo esc_html(SEO::detect_seo_plugin()); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="enkrpufo-pub-card">
                <h2>Health Checks</h2>
                <table class="widefat">
                    <tr>
                        <th>REST API Enabled:</th>
                        <td><?php echo esc_html($checks['rest_enabled'] ? '✅ Yes' : '❌ No'); ?></td>
                    </tr>
                    <tr>
                        <th>Permalinks Not Plain:</th>
                        <td><?php echo esc_html($checks['permalinks'] ? '✅ Yes' : '❌ No'); ?></td>
                    </tr>
                    <tr>
                        <th>Plugin Enabled:</th>
                        <td><?php echo esc_html($checks['plugin_enabled'] ? '✅ Yes' : '❌ No'); ?></td>
                    </tr>
                    <tr>
                        <th>Cron Enabled:</th>
                        <td><?php echo esc_html($checks['cron_enabled'] ? '✅ Yes' : '❌ No'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="enkrpufo-pub-card">
                <h2>API Endpoint</h2>
                <p><strong>Base URL:</strong></p>
                <code><?php echo esc_url(rest_url('growthpilot/v1')); ?></code>
            </div>
        </div>
        <?php
    }
    
    // Action handlers
    public static function handle_generate_key(): void {
        check_admin_referer('enkrpufo_generate_key', 'enkrpufo_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $name = self::input_string(INPUT_POST, 'key_name');
        $scopes = array_map('sanitize_key', self::input_array(INPUT_POST, 'scopes'));
        $allowed_ips_raw = sanitize_textarea_field(self::input_string(INPUT_POST, 'allowed_ips'));
        $allowed_ips = array_filter(array_map('trim', explode("\n", $allowed_ips_raw)));
        
        $key = Auth::create_api_key($name, $scopes, $allowed_ips);
        
        set_transient('enkrpufo_new_key', $key, 60);
        
        wp_safe_redirect(admin_url('admin.php?page=enkrpufo-publisher'));
        exit;
    }
    
    public static function handle_revoke_key(): void {
        check_admin_referer('enkrpufo_revoke_key', 'enkrpufo_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $key_id = self::input_string(INPUT_POST, 'key_id');
        Auth::revoke_api_key($key_id);
        
        wp_safe_redirect(admin_url('admin.php?page=enkrpufo-publisher'));
        exit;
    }
    
    public static function handle_save_defaults(): void {
        check_admin_referer('enkrpufo_defaults', 'enkrpufo_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (filter_input(INPUT_POST, 'enkrpufo_enabled', FILTER_UNSAFE_RAW) !== null) {
            update_option('enkrpufo_enabled', true);
        } else {
            update_option('enkrpufo_enabled', false);
        }
        
        $default_author_id = self::input_string(INPUT_POST, 'enkrpufo_default_author_id');
        if ($default_author_id !== '') {
            update_option('enkrpufo_default_author_id', (int)$default_author_id);
        }
        
        $default_post_status = self::input_string(INPUT_POST, 'enkrpufo_default_post_status');
        if ($default_post_status !== '') {
            update_option('enkrpufo_default_post_status', sanitize_key($default_post_status));
        }
        
        $default_category_id = self::input_string(INPUT_POST, 'enkrpufo_default_category_id');
        if ($default_category_id !== '') {
            update_option('enkrpufo_default_category_id', (int)$default_category_id);
        }
        
        $rate_limit = self::input_string(INPUT_POST, 'enkrpufo_rate_limit_per_minute');
        if ($rate_limit !== '') {
            update_option('enkrpufo_rate_limit_per_minute', (int)$rate_limit);
        }
        
        if (filter_input(INPUT_POST, 'enkrpufo_logging_enabled', FILTER_UNSAFE_RAW) !== null) {
            update_option('enkrpufo_logging_enabled', true);
        } else {
            update_option('enkrpufo_logging_enabled', false);
        }
        
        $log_retention_days = self::input_string(INPUT_POST, 'enkrpufo_log_retention_days');
        if ($log_retention_days !== '') {
            update_option('enkrpufo_log_retention_days', (int)$log_retention_days);
        }
        
        wp_safe_redirect(admin_url('admin.php?page=enkrpufo-publisher-defaults'));
        exit;
    }
    
    public static function handle_delete_logs(): void {
        check_admin_referer('enkrpufo_delete_logs', 'enkrpufo_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        DB::delete_all_logs();
        
        wp_safe_redirect(admin_url('admin.php?page=enkrpufo-publisher-logs'));
        exit;
    }
    
    public static function handle_export_logs(): void {
        check_admin_referer('enkrpufo_export_logs', 'enkrpufo_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $csv = DB::export_logs_csv();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="enkrpufo-pub-logs-' . gmdate('Y-m-d') . '.csv"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV output must remain raw.
        echo $csv;
        exit;
    }
}

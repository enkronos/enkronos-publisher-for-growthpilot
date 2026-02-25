<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    
    private static ?Plugin $instance = null;
    
    public static function get_instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    public function init(): void {
        DB::init();
        
        add_action('rest_api_init', [REST::class, 'register_routes']);
        
        if (is_admin()) {
            Admin::init();
        }
        
        // Schedule cron for log cleanup
        if (!wp_next_scheduled('enkrpufo_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'enkrpufo_cleanup_logs');
        }
        
        add_action('enkrpufo_cleanup_logs', [DB::class, 'delete_old_logs']);
    }
    
    public static function activate(): void {
        DB::init();
        DB::create_table();
        
        self::set_default_options();
        
        flush_rewrite_rules();
    }
    
    public static function deactivate(): void {
        wp_clear_scheduled_hook('enkrpufo_cleanup_logs');
        flush_rewrite_rules();
    }
    
    private static function set_default_options(): void {
        if (get_option('enkrpufo_enabled') === false) {
            add_option('enkrpufo_enabled', true);
        }
        
        if (get_option('enkrpufo_api_keys') === false) {
            add_option('enkrpufo_api_keys', []);
        }
        
        if (get_option('enkrpufo_default_author_id') === false) {
            add_option('enkrpufo_default_author_id', 1);
        }
        
        if (get_option('enkrpufo_default_post_status') === false) {
            add_option('enkrpufo_default_post_status', 'draft');
        }
        
        if (get_option('enkrpufo_default_category_id') === false) {
            add_option('enkrpufo_default_category_id', 0);
        }
        
        if (get_option('enkrpufo_rate_limit_per_minute') === false) {
            add_option('enkrpufo_rate_limit_per_minute', 120);
        }
        
        if (get_option('enkrpufo_logging_enabled') === false) {
            add_option('enkrpufo_logging_enabled', true);
        }
        
        if (get_option('enkrpufo_log_retention_days') === false) {
            add_option('enkrpufo_log_retention_days', 30);
        }
    }
}
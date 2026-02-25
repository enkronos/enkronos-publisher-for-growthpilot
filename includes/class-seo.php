<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class SEO {
    
    public static function save_seo_fields(int $post_id, array $seo_data): void {
        $plugin = self::detect_seo_plugin();
        
        $title = sanitize_text_field($seo_data['title'] ?? '');
        $description = sanitize_textarea_field($seo_data['description'] ?? '');
        $focus_keyword = sanitize_text_field($seo_data['focus_keyword'] ?? '');
        $canonical = esc_url_raw($seo_data['canonical'] ?? '');
        
        switch ($plugin) {
            case 'rankmath':
                if ($title) update_post_meta($post_id, 'rank_math_title', $title);
                if ($description) update_post_meta($post_id, 'rank_math_description', $description);
                if ($focus_keyword) update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
                if ($canonical) update_post_meta($post_id, 'rank_math_canonical_url', $canonical);
                break;
                
            case 'yoast':
                if ($title) update_post_meta($post_id, '_yoast_wpseo_title', $title);
                if ($description) update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
                if ($focus_keyword) update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
                if ($canonical) update_post_meta($post_id, '_yoast_wpseo_canonical', $canonical);
                break;
                
            default:
                if ($title) update_post_meta($post_id, 'enkrpufo_seo_title', $title);
                if ($description) update_post_meta($post_id, 'enkrpufo_seo_description', $description);
                if ($focus_keyword) update_post_meta($post_id, 'enkrpufo_seo_focus_keyword', $focus_keyword);
                if ($canonical) update_post_meta($post_id, 'enkrpufo_seo_canonical', $canonical);
                break;
        }
    }
    
    public static function get_seo_fields(int $post_id): array {
        $plugin = self::detect_seo_plugin();
        
        $seo = [
            'title' => '',
            'description' => '',
            'focus_keyword' => '',
            'canonical' => '',
            'plugin' => $plugin,
        ];
        
        switch ($plugin) {
            case 'rankmath':
                $seo['title'] = get_post_meta($post_id, 'rank_math_title', true);
                $seo['description'] = get_post_meta($post_id, 'rank_math_description', true);
                $seo['focus_keyword'] = get_post_meta($post_id, 'rank_math_focus_keyword', true);
                $seo['canonical'] = get_post_meta($post_id, 'rank_math_canonical_url', true);
                break;
                
            case 'yoast':
                $seo['title'] = get_post_meta($post_id, '_yoast_wpseo_title', true);
                $seo['description'] = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $seo['focus_keyword'] = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
                $seo['canonical'] = get_post_meta($post_id, '_yoast_wpseo_canonical', true);
                break;
                
            default:
                $seo['title'] = get_post_meta($post_id, 'enkrpufo_seo_title', true);
                $seo['description'] = get_post_meta($post_id, 'enkrpufo_seo_description', true);
                $seo['focus_keyword'] = get_post_meta($post_id, 'enkrpufo_seo_focus_keyword', true);
                $seo['canonical'] = get_post_meta($post_id, 'enkrpufo_seo_canonical', true);
                break;
        }
        
        return $seo;
    }
    
    public static function detect_seo_plugin(): string {
        if (function_exists('rank_math')) {
            return 'rankmath';
        }
        
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            return 'yoast';
        }
        
        return 'none';
    }
}

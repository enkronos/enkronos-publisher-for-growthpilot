<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional WPML integration for bilingual content published by the connector.
 *
 * The connector remains usable when WPML is not installed. In that case the
 * requested language and translation group are retained as connector metadata
 * and returned by the read-back response for auditability.
 */
class WPML {
    private const LANGUAGE_META = '_enkrpufo_language';
    private const GROUP_META = '_enkrpufo_translation_group';
    private const TRANSLATION_OF_META = '_enkrpufo_translation_of';

    public static function is_active(): bool {
        return defined('ICL_SITEPRESS_VERSION')
            || defined('WPML_VERSION')
            || has_filter('wpml_element_trid') !== false;
    }

    public static function normalize_language(mixed $language): string {
        $language = strtolower(trim((string)$language));
        if ($language === '') {
            return '';
        }

        $language = preg_replace('/[_-].*$/', '', $language) ?? $language;
        return preg_match('/^[a-z]{2,8}$/', $language) ? $language : '';
    }

    public static function normalize_translation_group(mixed $group): string {
        $group = trim(sanitize_text_field((string)$group));
        return strlen($group) <= 150 ? $group : substr($group, 0, 150);
    }

    public static function apply_post_translation(int $post_id, array $translation): void {
        $language = self::normalize_language($translation['language'] ?? '');
        $group = self::normalize_translation_group($translation['translation_group'] ?? '');
        $translation_of = max(0, (int)($translation['translation_of'] ?? 0));

        if ($language !== '') {
            update_post_meta($post_id, self::LANGUAGE_META, $language);
        }
        if ($group !== '') {
            update_post_meta($post_id, self::GROUP_META, $group);
        }
        if ($translation_of > 0 && $translation_of !== $post_id) {
            update_post_meta($post_id, self::TRANSLATION_OF_META, $translation_of);
        }

        if (!$language || !self::is_active()) {
            return;
        }

        $element_type = 'post_' . (get_post_type($post_id) ?: 'post');
        $trid = null;
        $source_language_code = null;

        if ($translation_of > 0 && get_post($translation_of)) {
            $trid = apply_filters('wpml_element_trid', null, $translation_of, $element_type);
            $source_language_code = self::normalize_language(get_post_meta($translation_of, self::LANGUAGE_META, true)) ?: null;
        }

        if (!$trid && $group !== '') {
            $existing = get_posts([
                'post_type' => get_post_type($post_id) ?: 'post',
                'post_status' => 'any',
                'meta_key' => self::GROUP_META,
                'meta_value' => $group,
                'posts_per_page' => 1,
                'fields' => 'ids',
                'exclude' => [$post_id],
                'no_found_rows' => true,
            ]);
            if (!empty($existing)) {
                $trid = apply_filters('wpml_element_trid', null, (int)$existing[0], $element_type);
                $source_language_code = self::normalize_language(get_post_meta((int)$existing[0], self::LANGUAGE_META, true)) ?: null;
            }
        }

        do_action('wpml_set_element_language_details', [
            'element_id' => $post_id,
            'element_type' => $element_type,
            'trid' => $trid ?: null,
            'language_code' => $language,
            'source_language_code' => $source_language_code,
        ]);
    }

    public static function get_post_translation_data(int $post_id): array {
        $language = self::normalize_language(get_post_meta($post_id, self::LANGUAGE_META, true));
        $group = self::normalize_translation_group(get_post_meta($post_id, self::GROUP_META, true));
        $translation_of = (int)get_post_meta($post_id, self::TRANSLATION_OF_META, true);
        $element_type = 'post_' . (get_post_type($post_id) ?: 'post');
        $trid = null;

        if (self::is_active()) {
            $trid = apply_filters('wpml_element_trid', null, $post_id, $element_type);
        }

        return [
            'language' => $language ?: null,
            'translation_group' => $group ?: null,
            'translation_of' => $translation_of ?: null,
            'wpml_active' => self::is_active(),
            'wpml_trid' => $trid ? (int)$trid : null,
        ];
    }
}

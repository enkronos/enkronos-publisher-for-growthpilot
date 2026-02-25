<?php
declare(strict_types=1);

namespace Enkrpufo;

if (!defined('ABSPATH')) {
    exit;
}

class REST {
    
    private const NAMESPACE = 'growthpilot/v1';
    
    public static function register_routes(): void {
        // Status
        register_rest_route(self::NAMESPACE, '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_status'],
            'permission_callback' => [self::class, 'check_auth_admin_read'],
        ]);
        
        // Taxonomies
        register_rest_route(self::NAMESPACE, '/taxonomies', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_taxonomies'],
            'permission_callback' => [self::class, 'check_auth_content_read'],
        ]);
        
        // Terms
        register_rest_route(self::NAMESPACE, '/terms', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_terms'],
                'permission_callback' => [self::class, 'check_auth_content_read'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'create_term'],
                'permission_callback' => [self::class, 'check_auth_taxonomy_write'],
            ],
        ]);
        
        // Posts
        register_rest_route(self::NAMESPACE, '/posts', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_posts'],
                'permission_callback' => [self::class, 'check_auth_content_read'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'create_post'],
                'permission_callback' => [self::class, 'check_auth_content_write'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_post'],
                'permission_callback' => [self::class, 'check_auth_content_read'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'update_post'],
                'permission_callback' => [self::class, 'check_auth_content_write'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'delete_post'],
                'permission_callback' => [self::class, 'check_auth_content_delete'],
            ],
        ]);
        
        // Pages
        register_rest_route(self::NAMESPACE, '/pages', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_pages'],
                'permission_callback' => [self::class, 'check_auth_content_read'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'create_page'],
                'permission_callback' => [self::class, 'check_auth_content_write'],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/pages/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_page'],
                'permission_callback' => [self::class, 'check_auth_content_read'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'update_page'],
                'permission_callback' => [self::class, 'check_auth_content_write'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'delete_page'],
                'permission_callback' => [self::class, 'check_auth_content_delete'],
            ],
        ]);
        
        // Media
        register_rest_route(self::NAMESPACE, '/media', [
            'methods' => 'POST',
            'callback' => [self::class, 'upload_media'],
            'permission_callback' => [self::class, 'check_auth_media_write'],
        ]);
        
        // Bulk
        register_rest_route(self::NAMESPACE, '/bulk/posts', [
            'methods' => 'POST',
            'callback' => [self::class, 'bulk_posts'],
            'permission_callback' => [self::class, 'check_auth_content_write'],
        ]);
    }
    
    // Permission callbacks
    public static function check_auth_admin_read(\WP_REST_Request $request): bool|\WP_Error {
        Logger::start_request();
        return Auth::authenticate($request, ['admin:read']);
    }
    
    public static function check_auth_content_read(\WP_REST_Request $request): bool|\WP_Error {
        Logger::start_request();
        return Auth::authenticate($request, ['content:read']);
    }
    
    public static function check_auth_content_write(\WP_REST_Request $request): bool|\WP_Error {
        Logger::start_request();
        return Auth::authenticate($request, ['content:write']);
    }
    
    public static function check_auth_content_delete(\WP_REST_Request $request): bool|\WP_Error {
        Logger::start_request();
        return Auth::authenticate($request, ['content:delete']);
    }
    
    public static function check_auth_media_write(\WP_REST_Request $request): bool|\WP_Error {
        Logger::start_request();
        return Auth::authenticate($request, ['media:write']);
    }
    
    public static function check_auth_taxonomy_write(\WP_REST_Request $request): bool|\WP_Error {
        Logger::start_request();
        return Auth::authenticate($request, ['taxonomy:write']);
    }
    
    // Status endpoint
    public static function get_status(\WP_REST_Request $request): \WP_REST_Response {
        $data = [
            'plugin_version' => ENKRPUFO_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'enabled' => (bool)get_option('enkrpufo_enabled', true),
            'auth_mode' => 'api_key_hmac',
            'rate_limit' => (int)get_option('enkrpufo_rate_limit_per_minute', 120),
            'scopes_supported' => array_keys(Auth::get_available_scopes()),
            'seo_plugin' => SEO::detect_seo_plugin(),
        ];
        
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data), 200);
    }
    
    // Taxonomies
    public static function get_taxonomies(\WP_REST_Request $request): \WP_REST_Response {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        
        $data = [];
        foreach ($taxonomies as $tax) {
            $data[] = [
                'name' => $tax->name,
                'label' => $tax->label,
                'hierarchical' => $tax->hierarchical,
            ];
        }
        
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data), 200);
    }
    
    // Terms
    public static function get_terms(\WP_REST_Request $request): \WP_REST_Response {
        $taxonomy = sanitize_key($request->get_param('taxonomy') ?: 'category');
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $page = max(1, (int)$request->get_param('page') ?: 1);
        $per_page = min(100, max(1, (int)$request->get_param('per_page') ?: 20));
        
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];
        
        if ($search) {
            $args['search'] = $search;
        }
        
        $terms = get_terms($args);
        
        if (is_wp_error($terms)) {
            Logger::log_response(400, 'VALIDATION_ERROR');
            return new \WP_REST_Response(
                Utils::error_response('VALIDATION_ERROR', $terms->get_error_message())->data['data'],
                400
            );
        }
        
        $data = array_map(function($term) {
            return [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'taxonomy' => $term->taxonomy,
                'parent' => $term->parent,
                'count' => $term->count,
            ];
        }, $terms);
        
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data), 200);
    }
    
    public static function create_term(\WP_REST_Request $request): \WP_REST_Response {
        $body = $request->get_json_params();
        
        $taxonomy = sanitize_key($body['taxonomy'] ?? '');
        $name = sanitize_text_field($body['name'] ?? '');
        $slug = sanitize_title($body['slug'] ?? '');
        $parent = (int)($body['parent'] ?? 0);
        
        if (empty($taxonomy) || empty($name)) {
            Logger::log_response(400, 'VALIDATION_ERROR');
            return new \WP_REST_Response(
                Utils::error_response('VALIDATION_ERROR', 'Missing required fields: taxonomy, name')->data['data'],
                400
            );
        }
        
        $args = ['slug' => $slug ?: $name];
        if ($parent) {
            $args['parent'] = $parent;
        }
        
        $result = wp_insert_term($name, $taxonomy, $args);
        
        if (is_wp_error($result)) {
            Logger::log_response(400, 'WP_INSERT_FAILED');
            return new \WP_REST_Response(
                Utils::error_response('WP_INSERT_FAILED', $result->get_error_message())->data['data'],
                400
            );
        }
        
        $term = get_term($result['term_id']);
        $data = [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'taxonomy' => $term->taxonomy,
        ];
        
        Logger::set_wp_object_id($term->term_id);
        Logger::log_response(201);
        return new \WP_REST_Response(Utils::success_response($data), 201);
    }
    
    // Posts/Pages helpers
    private static function handle_post_create_update(array $payload, ?int $post_id = null): array|\WP_Error {
        $sanitized = Utils::sanitize_post_payload($payload);
        
        if (!Utils::validate_post_type($sanitized['post_type'])) {
            return Utils::error_response('INVALID_POST_TYPE', 'Invalid post type', [], 400);
        }
        
        if (!Utils::validate_post_status($sanitized['status'])) {
            return Utils::error_response('INVALID_STATUS', 'Invalid post status', [], 400);
        }
        
        $post_data = [
            'post_title' => $sanitized['title'],
            'post_content' => $sanitized['content_html'],
            'post_excerpt' => $sanitized['excerpt'],
            'post_status' => $sanitized['status'],
            'post_type' => $sanitized['post_type'],
            'post_author' => $sanitized['author_id'],
            'post_name' => $sanitized['slug'],
        ];
        
        if ($sanitized['date_gmt']) {
            $post_data['post_date_gmt'] = $sanitized['date_gmt'];
            $post_data['post_date'] = get_date_from_gmt($sanitized['date_gmt']);
        }
        
        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            return Utils::error_response(
                $post_id ? 'WP_UPDATE_FAILED' : 'WP_INSERT_FAILED',
                $result->get_error_message(),
                [],
                500
            );
        }
        
        $post_id = $result;
        
        // Set categories
        if ($sanitized['post_type'] === 'post' && !empty($sanitized['categories'])) {
            wp_set_post_categories($post_id, $sanitized['categories']);
        }
        
        // Set tags
        if ($sanitized['post_type'] === 'post' && !empty($sanitized['tags'])) {
            wp_set_post_tags($post_id, $sanitized['tags']);
        }
        
        // Handle featured media
        if (!empty($sanitized['featured_media'])) {
            $media = $sanitized['featured_media'];
            
            if ($media['mode'] === 'media_id' && !empty($media['media_id'])) {
                set_post_thumbnail($post_id, (int)$media['media_id']);
            } elseif ($media['mode'] === 'upload') {
                $upload_result = self::handle_media_upload($media);
                if (!is_wp_error($upload_result)) {
                    set_post_thumbnail($post_id, $upload_result['id']);
                }
            }
        }
        
        // Handle SEO
        if (!empty($sanitized['seo'])) {
            SEO::save_seo_fields($post_id, $sanitized['seo']);
        }
        
        // Handle custom fields
        foreach ($sanitized['custom_fields'] as $key => $value) {
            update_post_meta($post_id, 'enkrpufo_' . sanitize_key($key), sanitize_text_field($value));
        }
        
        return ['post_id' => $post_id];
    }
    
    private static function format_post_response(\WP_Post $post): array {
        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'content_html' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'author_id' => (int)$post->post_author,
            'date_gmt' => $post->post_date_gmt,
            'modified_gmt' => $post->post_modified_gmt,
            'post_type' => $post->post_type,
            'url' => get_permalink($post->ID),
        ];
        
        if ($post->post_type === 'post') {
            $data['categories'] = wp_get_post_categories($post->ID);
            $data['tags'] = wp_get_post_tags($post->ID, ['fields' => 'ids']);
        }
        
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $data['featured_media'] = [
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_url($thumbnail_id),
            ];
        }
        
        $data['seo'] = SEO::get_seo_fields($post->ID);
        
        return $data;
    }
    
    // Posts endpoints
    public static function get_posts(\WP_REST_Request $request): \WP_REST_Response {
        return self::get_posts_query($request, 'post');
    }
    
    public static function get_pages(\WP_REST_Request $request): \WP_REST_Response {
        return self::get_posts_query($request, 'page');
    }
    
    private static function get_posts_query(\WP_REST_Request $request, string $post_type): \WP_REST_Response {
        $page = max(1, (int)$request->get_param('page') ?: 1);
        $per_page = min(100, max(1, (int)$request->get_param('per_page') ?: 20));
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $status = sanitize_key($request->get_param('status') ?: 'any');
        
        $args = [
            'post_type' => $post_type,
            'post_status' => $status,
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => $search,
        ];
        
        if ($after = $request->get_param('after')) {
            $args['date_query'][] = ['after' => sanitize_text_field($after)];
        }
        
        if ($before = $request->get_param('before')) {
            $args['date_query'][] = ['before' => sanitize_text_field($before)];
        }
        
        $query = new \WP_Query($args);
        
        $data = array_map([self::class, 'format_post_response'], $query->posts);
        
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data, [
            'total' => $query->found_posts,
            'page' => $page,
            'per_page' => $per_page,
        ]), 200);
    }
    
    public static function get_post(\WP_REST_Request $request): \WP_REST_Response {
        return self::get_single_post($request, 'post');
    }
    
    public static function get_page(\WP_REST_Request $request): \WP_REST_Response {
        return self::get_single_post($request, 'page');
    }
    
    private static function get_single_post(\WP_REST_Request $request, string $post_type): \WP_REST_Response {
        $post_id = (int)$request->get_param('id');
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== $post_type) {
            Logger::log_response(404, 'NOT_FOUND');
            return new \WP_REST_Response(
                Utils::error_response('NOT_FOUND', 'Post not found')->data['data'],
                404
            );
        }
        
        $data = self::format_post_response($post);
        
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data), 200);
    }
    
    public static function create_post(\WP_REST_Request $request): \WP_REST_Response {
        $payload = $request->get_json_params();
        $payload['post_type'] = 'post';
        
        $result = self::handle_post_create_update($payload);
        
        if (is_wp_error($result)) {
            Logger::log_response($result->data['status'], $result->get_error_code());
            return new \WP_REST_Response($result->data['data'], $result->data['status']);
        }
        
        $post = get_post($result['post_id']);
        $data = self::format_post_response($post);
        
        Logger::set_wp_object_id($result['post_id']);
        Logger::log_response(201);
        return new \WP_REST_Response(Utils::success_response($data), 201);
    }
    
    public static function create_page(\WP_REST_Request $request): \WP_REST_Response {
        $payload = $request->get_json_params();
        $payload['post_type'] = 'page';
        
        $result = self::handle_post_create_update($payload);
        
        if (is_wp_error($result)) {
            Logger::log_response($result->data['status'], $result->get_error_code());
            return new \WP_REST_Response($result->data['data'], $result->data['status']);
        }
        
        $post = get_post($result['post_id']);
        $data = self::format_post_response($post);
        
        Logger::set_wp_object_id($result['post_id']);
        Logger::log_response(201);
        return new \WP_REST_Response(Utils::success_response($data), 201);
    }
    
    public static function update_post(\WP_REST_Request $request): \WP_REST_Response {
        $post_id = (int)$request->get_param('id');
        $payload = $request->get_json_params();
        $payload['post_type'] = 'post';
        
        $result = self::handle_post_create_update($payload, $post_id);
        
        if (is_wp_error($result)) {
            Logger::log_response($result->data['status'], $result->get_error_code());
            return new \WP_REST_Response($result->data['data'], $result->data['status']);
        }
        
        $post = get_post($post_id);
        $data = self::format_post_response($post);
        
        Logger::set_wp_object_id($post_id);
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data), 200);
    }
    
    public static function update_page(\WP_REST_Request $request): \WP_REST_Response {
        $post_id = (int)$request->get_param('id');
        $payload = $request->get_json_params();
        $payload['post_type'] = 'page';
        
        $result = self::handle_post_create_update($payload, $post_id);
        
        if (is_wp_error($result)) {
            Logger::log_response($result->data['status'], $result->get_error_code());
            return new \WP_REST_Response($result->data['data'], $result->data['status']);
        }
        
        $post = get_post($post_id);
        $data = self::format_post_response($post);
        
        Logger::set_wp_object_id($post_id);
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response($data), 200);
    }
    
    public static function delete_post(\WP_REST_Request $request): \WP_REST_Response {
        return self::delete_post_handler($request, 'post');
    }
    
    public static function delete_page(\WP_REST_Request $request): \WP_REST_Response {
        return self::delete_post_handler($request, 'page');
    }
    
    private static function delete_post_handler(\WP_REST_Request $request, string $post_type): \WP_REST_Response {
        $post_id = (int)$request->get_param('id');
        $force = filter_var($request->get_param('force'), FILTER_VALIDATE_BOOLEAN);
        
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== $post_type) {
            Logger::log_response(404, 'NOT_FOUND');
            return new \WP_REST_Response(
                Utils::error_response('NOT_FOUND', 'Post not found')->data['data'],
                404
            );
        }
        
        $result = $force ? wp_delete_post($post_id, true) : wp_trash_post($post_id);
        
        if (!$result) {
            Logger::log_response(500, 'WP_DELETE_FAILED');
            return new \WP_REST_Response(
                Utils::error_response('WP_DELETE_FAILED', 'Failed to delete post')->data['data'],
                500
            );
        }
        
        Logger::set_wp_object_id($post_id);
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response(['deleted' => true, 'id' => $post_id]), 200);
    }
    
    // Media upload
    public static function upload_media(\WP_REST_Request $request): \WP_REST_Response {
        $body = $request->get_json_params();
        
        $result = self::handle_media_upload($body);
        
        if (is_wp_error($result)) {
            Logger::log_response($result->data['status'], $result->get_error_code());
            return new \WP_REST_Response($result->data['data'], $result->data['status']);
        }
        
        Logger::set_wp_object_id($result['id']);
        Logger::log_response(201);
        return new \WP_REST_Response(Utils::success_response($result), 201);
    }
    
    private static function handle_media_upload(array $data): array|\WP_Error {
        if (empty($data['file_name']) || empty($data['base64'])) {
            return Utils::error_response('VALIDATION_ERROR', 'Missing file_name or base64', [], 400);
        }
        
        $file_name = sanitize_file_name($data['file_name']);
        $mime_type = sanitize_mime_type($data['mime_type'] ?? '');
        $base64 = $data['base64'];
        
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return Utils::error_response('VALIDATION_ERROR', 'Invalid base64 data', [], 400);
        }
        
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/' . wp_unique_filename($upload_dir['path'], $file_name);
        
        if (file_put_contents($temp_file, $decoded) === false) {
            return Utils::error_response('WP_UPLOAD_FAILED', 'Failed to write file', [], 500);
        }
        
        $file = [
            'name' => $file_name,
            'type' => $mime_type,
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        ];
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        $upload = wp_handle_sideload($file, ['test_form' => false]);
        
        if (!empty($upload['error'])) {
            wp_delete_file($temp_file);
            return Utils::error_response('WP_UPLOAD_FAILED', $upload['error'], [], 500);
        }
        
        $attachment_data = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_text_field($data['title'] ?? $file_name),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        
        $attachment_id = wp_insert_attachment($attachment_data, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            return Utils::error_response('WP_UPLOAD_FAILED', $attachment_id->get_error_message(), [], 500);
        }
        
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        if (!empty($data['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['alt_text']));
        }
        
        if (!empty($data['caption'])) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_excerpt' => sanitize_textarea_field($data['caption']),
            ]);
        }
        
        return [
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'mime_type' => $upload['type'],
        ];
    }
    
    // Bulk posts
    public static function bulk_posts(\WP_REST_Request $request): \WP_REST_Response {
        $body = $request->get_json_params();
        
        $items = $body['items'] ?? [];
        $transaction_mode = $body['transaction_mode'] ?? 'partial';
        
        $results = [];
        $created_ids = [];
        
        foreach ($items as $index => $item) {
            $result = self::handle_post_create_update($item);
            
            if (is_wp_error($result)) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'error' => [
                        'code' => $result->get_error_code(),
                        'message' => $result->get_error_message(),
                    ],
                ];
                
                if ($transaction_mode === 'all_or_none') {
                    foreach ($created_ids as $id) {
                        wp_delete_post($id, true);
                    }
                    
                    Logger::log_response(400, 'BULK_TRANSACTION_FAILED');
                    return new \WP_REST_Response(
                        Utils::error_response('BULK_TRANSACTION_FAILED', 'Transaction rolled back due to failure', ['results' => $results])->data['data'],
                        400
                    );
                }
            } else {
                $created_ids[] = $result['post_id'];
                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'id' => $result['post_id'],
                ];
            }
        }
        
        Logger::log_response(200);
        return new \WP_REST_Response(Utils::success_response(['results' => $results]), 200);
    }
}

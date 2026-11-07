<?php
/**
 * Plugin Name: Headless Preview
 * Description: Ajoute un bouton de preview pour les brouillons (JWT).
 */

use Firebase\JWT\JWT;

add_action('admin_bar_menu', 'headless_add_preview_button', 100);
add_action('rest_api_init', 'headless_register_preview_endpoint');

function headless_add_preview_button($wp_admin_bar) {
    if (!is_admin()) {
        return;
    }

    global $post;
    if (!$post || $post->post_status !== 'draft') {
        return;
    }

    $token = JWT::encode([
        'user_id' => get_current_user_id(),
        'post_id' => $post->ID,
        'exp' => time() + 3600, // 1 heure
    ], getenv('JWT_SECRET'), 'HS256');

    $preview_url = sprintf(
        'http://localhost:3000/preview/%s/%s?token=%s',
        $post->post_type,
        $post->post_name,
        $token
    );

    $wp_admin_bar->add_node([
        'id' => 'preview-headless',
        'title' => 'Prévisualiser (Headless)',
        'href' => $preview_url,
        'parent' => 'top-secondary',
    ]);
}

function headless_register_preview_endpoint() {
    register_rest_route('headless/v1', '/preview/(?P<post_type>[a-z0-9_-]+)/(?P<post_name>[a-z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'headless_preview_callback',
        'permission_callback' => '__return_true',
    ]);
}

function headless_preview_callback($request) {
    $post_type = $request->get_param('post_type');
    $post_name = $request->get_param('post_name');
    $token = $request->get_param('token');

    try {
        $decoded = JWT::decode($token, getenv('JWT_SECRET'), ['HS256']);
        $post_id = $decoded->post_id;

        $post = get_post($post_id);
        if (!$post || $post->post_name !== $post_name) {
            return new WP_Error('invalid_post', 'Post introuvable', ['status' => 404]);
        }

        // --- Récupérer les données via Headless Bridge ---
        $data = apply_filters('headless_bridge_data', $post, [
            'include_fields' => true,
            'include_taxonomies' => true,
        ]);

        return new WP_REST_Response($data, 200);
    } catch (Exception $e) {
        return new WP_Error('invalid_token', 'Token invalide', ['status' => 401]);
    }
}

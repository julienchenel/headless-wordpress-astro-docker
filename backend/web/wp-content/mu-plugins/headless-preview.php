<?php
/**
 * Plugin Name: Headless Preview
 * Description: Le bouton « Prévisualiser » ouvre le brouillon sur le frontend Astro (/preview?token=...).
 *              Le token (JWT, 1 h) est signé et vérifié ici : le frontend n'a aucun secret.
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

const HEADLESS_PREVIEW_TTL = HOUR_IN_SECONDS;

/**
 * Lien de preview vers le frontend pour un contenu que l'utilisateur peut modifier.
 */
function headless_preview_url(WP_Post $post): ?string
{
    if (!defined('FRONTEND_URL') || !FRONTEND_URL || !defined('JWT_SECRET') || strlen(JWT_SECRET) < 32) {
        return null;
    }
    if (!current_user_can('edit_post', $post->ID)) {
        return null;
    }

    $token = JWT::encode([
        'post_id' => $post->ID,
        'user_id' => get_current_user_id(),
        'exp' => time() + HEADLESS_PREVIEW_TTL,
    ], JWT_SECRET, 'HS256');

    return untrailingslashit(FRONTEND_URL) . '/preview?token=' . rawurlencode($token);
}

// --- Bouton « Prévisualiser » (éditeur de blocs et classique) ---
add_filter('preview_post_link', function ($link, $post) {
    return headless_preview_url($post) ?? $link;
}, 10, 2);

// --- Endpoint lu par le frontend : GET /wp-json/headless/v1/preview?token=... ---
add_action('rest_api_init', function () {
    register_rest_route('headless/v1', '/preview', [
        'methods' => 'GET',
        'callback' => 'headless_preview_callback',
        'permission_callback' => '__return_true', // L'accès est contrôlé par le token.
        'args' => [
            'token' => ['type' => 'string', 'required' => true],
        ],
    ]);
});

function headless_preview_callback(WP_REST_Request $request)
{
    try {
        $decoded = JWT::decode((string) $request->get_param('token'), new Key(JWT_SECRET, 'HS256'));
    } catch (Throwable $e) {
        return new WP_Error('invalid_token', 'Token invalide ou expiré', ['status' => 401]);
    }

    $post = get_post((int) ($decoded->post_id ?? 0));
    if (!$post || !user_can((int) ($decoded->user_id ?? 0), 'edit_post', $post->ID)) {
        return new WP_Error('invalid_post', 'Contenu introuvable', ['status' => 404]);
    }

    // Brouillon : dernière autosave de l'auteur si elle est plus récente.
    $autosave = wp_get_post_autosave($post->ID, (int) $decoded->user_id);
    $source = ($autosave && strtotime($autosave->post_modified_gmt) > strtotime($post->post_modified_gmt)) ? $autosave : $post;

    $response = new WP_REST_Response([
        'id' => $post->ID,
        'uri' => wp_make_link_relative(get_permalink($post)),
        'title' => get_the_title($source),
        'content' => apply_filters('the_content', $source->post_content),
        'excerpt' => $source->post_excerpt,
        'date' => get_post_time('c', true, $post) ?: null, // null pour un brouillon jamais publié
    ], 200);
    $response->header('Cache-Control', 'no-store');

    return $response;
}

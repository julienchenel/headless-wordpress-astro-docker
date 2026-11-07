<?php
/**
 * Plugin Name: Headless Webhook (Cloudflare Pages)
 * Description: Déclenche un rebuild Cloudflare Pages à chaque sauvegarde de post.
 */

add_action('save_post', 'headless_trigger_cloudflare_rebuild');
add_action('delete_post', 'headless_trigger_cloudflare_rebuild');
add_action('wp_trash_post', 'headless_trigger_cloudflare_rebuild');

function headless_trigger_cloudflare_rebuild($post_id) {
    $build_hook = getenv('CLOUDFLARE_BUILD_HOOK');
    if (empty($build_hook)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    $args = [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'event' => current_filter(),
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_name' => $post->post_name,
            'post_status' => $post->post_status,
        ]),
    ];

    wp_remote_post($build_hook, $args);
}

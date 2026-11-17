<?php
/**
 * Plugin Name: Headless Webhook (rebuild du frontend)
 * Description: Quand un contenu publié change, demande à GitHub de reconstruire le frontend
 *              (événement repository_dispatch « wordpress-content-changed »), une fois par minute au plus.
 *
 * Configuration (.env) :
 *   GITHUB_REBUILD_REPO=proprietaire/depot
 *   GITHUB_REBUILD_TOKEN=jeton GitHub « fine-grained » limité à ce dépôt, permission Contents: write
 */

const HEADLESS_REBUILD_HOOK = 'headless_trigger_frontend_rebuild';
const HEADLESS_REBUILD_DELAY = MINUTE_IN_SECONDS;

// --- Publication, dépublication ou modification d'un contenu publié ---
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
        return;
    }
    if (!is_post_type_viewable($post->post_type)) {
        return;
    }
    if ($new_status === 'publish' || $old_status === 'publish') {
        headless_schedule_rebuild();
    }
}, 10, 3);

add_action('deleted_post', function ($post_id, $post) {
    if ($post && $post->post_status === 'publish') {
        headless_schedule_rebuild();
    }
}, 10, 2);

/**
 * Regroupe les modifications rapprochées en un seul rebuild.
 */
function headless_schedule_rebuild(): void
{
    if (!wp_next_scheduled(HEADLESS_REBUILD_HOOK)) {
        wp_schedule_single_event(time() + HEADLESS_REBUILD_DELAY, HEADLESS_REBUILD_HOOK);
    }
}

add_action(HEADLESS_REBUILD_HOOK, function () {
    $repo = defined('GITHUB_REBUILD_REPO') ? GITHUB_REBUILD_REPO : '';
    $token = defined('GITHUB_REBUILD_TOKEN') ? GITHUB_REBUILD_TOKEN : '';
    if (!$repo || !$token) {
        return;
    }

    $response = wp_remote_post("https://api.github.com/repos/{$repo}/dispatches", [
        'headers' => [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . $token,
            'X-GitHub-Api-Version' => '2022-11-28',
        ],
        'body' => wp_json_encode(['event_type' => 'wordpress-content-changed']),
        'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 204) {
        error_log('[headless-webhook] Échec du déclenchement du rebuild : '
            . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)));
    }
});

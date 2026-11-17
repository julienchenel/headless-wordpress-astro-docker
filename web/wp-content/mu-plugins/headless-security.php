<?php
/**
 * Plugin Name: Headless Security
 * Description: Sécurise WordPress pour un usage headless.
 */

// --- Désactiver XML-RPC ---
add_filter('xmlrpc_enabled', '__return_false');

// --- Désactiver les emojis ---
function headless_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'headless_disable_emojis');

// --- Désactiver oEmbed ---
function headless_disable_embed() {
    wp_deregister_script('wp-embed');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
}
add_action('init', 'headless_disable_embed');

// --- Désactiver jQuery Migrate ---
function headless_disable_jquery_migrate($scripts) {
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $script = $scripts->registered['jquery'];
        if ($script->deps) {
            $script->deps = array_filter($script->deps, function($dep) {
                return $dep !== 'jquery-migrate';
            });
        }
    }
}
add_action('wp_default_scripts', 'headless_disable_jquery_migrate');

// --- Limiter les tentatives de connexion (5 échecs = blocage 5 min) ---
function headless_login_key($username) {
    return 'headless_login_' . md5(strtolower(trim((string) $username)));
}

function headless_block_locked_login($user, $username) {
    if ($username !== '' && $username !== null && get_transient(headless_login_key($username) . '_lock')) {
        return new WP_Error('too_many_attempts', 'Trop de tentatives de connexion. Réessayez plus tard.');
    }
    return $user;
}
add_filter('authenticate', 'headless_block_locked_login', 30, 2);

function headless_track_failed_login($username) {
    $key = headless_login_key($username);
    $attempts = (int) get_transient($key . '_attempts') + 1;
    if ($attempts >= 5) {
        set_transient($key . '_lock', true, 5 * MINUTE_IN_SECONDS);
        delete_transient($key . '_attempts');
    } else {
        set_transient($key . '_attempts', $attempts, 5 * MINUTE_IN_SECONDS);
    }
}
add_action('wp_login_failed', 'headless_track_failed_login');

function headless_reset_login_attempts($username) {
    delete_transient(headless_login_key($username) . '_attempts');
}
add_action('wp_login', 'headless_reset_login_attempts');

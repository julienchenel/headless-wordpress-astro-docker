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

// --- Limiter les tentatives de connexion ---
function headless_limit_login_attempts($user) {
    if (get_transient('headless_login_lock_' . $user)) {
        return new WP_Error('too_many_attempts', 'Trop de tentatives de connexion. Réessayez plus tard.');
    }
    return $user;
}
add_filter('wp_authenticate_user', 'headless_limit_login_attempts');

function headless_track_login_attempts($user, $username) {
    if (!$user) {
        $attempts = get_transient('headless_login_attempts_' . $username) ?: 0;
        if ($attempts >= 5) {
            set_transient('headless_login_lock_' . $username, true, 300); // 5 min
        } else {
            set_transient('headless_login_attempts_' . $username, $attempts + 1, 300);
        }
    } else {
        delete_transient('headless_login_attempts_' . $username);
    }
    return $user;
}
add_filter('wp_authenticate_user', 'headless_track_login_attempts', 10, 2);

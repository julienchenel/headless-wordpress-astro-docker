<?php
/**
 * Plugin Name: Headless Optimize
 * Description: Optimise WordPress pour le headless.
 */

// --- Désactiver les révisions ---
define('WP_POST_REVISIONS', 3);

// --- Désactiver les pingbacks/trackbacks ---
function headless_disable_pingbacks($methods) {
    unset($methods['pingback.ping']);
    return $methods;
}
add_filter('xmlrpc_methods', 'headless_disable_pingbacks');

// --- Désactiver les heartbeats ---
function headless_disable_heartbeat() {
    wp_deregister_script('heartbeat');
}
add_action('init', 'headless_disable_heartbeat', 1);

// --- Désactiver les embeds ---
function headless_disable_embeds() {
    remove_action('rest_api_init', 'wp_oembed_register_route');
    remove_filter('oembed_dataparse', 'wp_filter_oembed_result');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
}
add_action('init', 'headless_disable_embeds');

// --- Désactiver les scripts inutiles ---
function headless_disable_scripts() {
    if (!is_admin()) {
        wp_deregister_script('wp-embed');
        wp_deregister_style('wp-block-library');
    }
}
add_action('wp_enqueue_scripts', 'headless_disable_scripts');

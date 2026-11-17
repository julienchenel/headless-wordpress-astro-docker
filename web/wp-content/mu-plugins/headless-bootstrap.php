<?php
/**
 * Plugin Name: Headless Bootstrap
 * Description: Configure WordPress une fois installé : thème headless, plugins requis, permaliens.
 *              Remplace WP-CLI là où il n'est pas disponible (hébergement mutualisé).
 *              Incrémenter HEADLESS_BOOTSTRAP_VERSION pour rejouer la configuration.
 */

const HEADLESS_BOOTSTRAP_VERSION = 1;

add_action('init', function () {
    if (!is_blog_installed() || (int) get_option('headless_bootstrap_version') >= HEADLESS_BOOTSTRAP_VERSION) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    // --- Thème minimal (le rendu est fait par Astro) ---
    if (get_stylesheet() !== 'headless' && wp_get_theme('headless')->exists()) {
        switch_theme('headless');
    }

    // --- Plugins requis (installés par Composer) ---
    $plugins = ['wp-graphql/wp-graphql.php', 'pods/init.php', 'polylang/polylang.php'];
    if (defined('WP_ENV') && WP_ENV === 'development') {
        $plugins[] = 'query-monitor/query-monitor.php';
    }
    foreach ($plugins as $plugin) {
        if (file_exists(WP_PLUGIN_DIR . '/' . $plugin) && !is_plugin_active($plugin)) {
            activate_plugin($plugin);
        }
    }

    // --- Permaliens (nécessaires pour /graphql et les URI du frontend) ---
    if (get_option('permalink_structure') !== '/%postname%/') {
        update_option('permalink_structure', '/%postname%/');
    }
    flush_rewrite_rules(false);

    update_option('headless_bootstrap_version', HEADLESS_BOOTSTRAP_VERSION);
}, 1);

<?php
/**
 * Configuration application pour Bedrock
 */

use Dotenv\Dotenv;
use Symfony\Component\Dotenv\Path;

// --- Charger les variables d'environnement ---
if (file_exists(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/../', '.env')->load();
}

// --- Configurer WordPress ---
define('WP_ENV', env('WP_ENV') ?: 'development');
define('WP_HOME', env('WP_HOME'));
define('WP_SITEURL', env('WP_SITEURL'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASSWORD', env('DB_PASSWORD'));
define('DB_HOST', env('DB_HOST') ?: 'mysql');
define('DB_CHARSET', env('DB_CHARSET') ?: 'utf8mb4');
define('DB_COLLATE', env('DB_COLLATE') ?: 'utf8mb4_unicode_ci');
define('WP_DEBUG', env('WP_DEBUG') ?: false);
define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?: false);

// --- Cache ---
define('WP_CACHE', env('WP_CACHE') ?: false);
define('WP_CACHE_KEY_SALT', env('WP_CACHE_KEY_SALT') ?: 'headless_wordpress');

// --- Headless Bridge ---
define('HEADLESS_BRIDGE_PODS_SUPPORT', env('HEADLESS_BRIDGE_PODS_SUPPORT') ?: true);
define('HEADLESS_BRIDGE_CACHE_ENABLED', env('HEADLESS_BRIDGE_CACHE_ENABLED') ?: true);
define('HEADLESS_BRIDGE_CACHE_DRIVER', env('HEADLESS_BRIDGE_CACHE_DRIVER') ?: 'memcached');
define('HEADLESS_BRIDGE_CACHE_TTL', env('HEADLESS_BRIDGE_CACHE_TTL') ?: 3600);

// --- LiteSpeed Cache ---
define('LSCACHE_OBJECT', env('LSCACHE_OBJECT') ?: true);
define('LSCACHE_OBJECT_MEMCACHED_HOST', env('LSCACHE_OBJECT_MEMCACHED_HOST') ?: 'memcached');
define('LSCACHE_OBJECT_MEMCACHED_PORT', env('LSCACHE_OBJECT_MEMCACHED_PORT') ?: 11211);

// --- JWT ---
define('JWT_SECRET', env('JWT_SECRET') ?: 'ta_clef_secrete_jwt_12345');

// --- Cloudflare ---
define('CLOUDFLARE_BUILD_HOOK', env('CLOUDFLARE_BUILD_HOOK') ?: '');

// --- Désactiver les mises à jour automatiques ---
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

// --- Désactiver l'éditeur de fichiers ---
define('DISALLOW_FILE_EDIT', true);

// --- Désactiver l'installation de plugins/thèmes ---
define('DISALLOW_FILE_MODS', true);

// --- Limiter les révisions ---
define('WP_POST_REVISIONS', 3);

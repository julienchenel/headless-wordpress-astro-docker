<?php
/**
 * Configuration WordPress (Bedrock).
 *
 * Chargée par web/wp-config.php. Toutes les valeurs viennent de backend/.env ;
 * une variable déjà définie dans l'environnement (ex. DB_HOST=127.0.0.1:3307
 * pour lancer WP-CLI depuis WSL) est prioritaire sur le fichier.
 */

use Dotenv\Dotenv;

use function Env\env;

$root_dir = dirname(__DIR__);
$webroot_dir = $root_dir . '/web';

// --- Charger les variables d'environnement ---
// « Unsafe » = renseigne aussi getenv(), utilisé par Env\env().
if (file_exists($root_dir . '/.env')) {
    $dotenv = Dotenv::createUnsafeImmutable($root_dir);
    $dotenv->load();
    $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'WP_HOME', 'WP_SITEURL']);
}

// --- Environnement et URLs ---
define('WP_ENV', env('WP_ENV') ?: 'production');
define('WP_HOME', env('WP_HOME'));
define('WP_SITEURL', env('WP_SITEURL'));

// --- Dossier de contenu (en dehors du cœur, qui est dans web/wp) ---
define('CONTENT_DIR', '/wp-content');
define('WP_CONTENT_DIR', $webroot_dir . CONTENT_DIR);
define('WP_CONTENT_URL', WP_HOME . CONTENT_DIR);
define('WP_DEFAULT_THEME', 'headless');

// --- Base de données ---
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASSWORD', env('DB_PASSWORD'));
define('DB_HOST', env('DB_HOST') ?: 'mysql');
define('DB_CHARSET', env('DB_CHARSET') ?: 'utf8mb4');
define('DB_COLLATE', env('DB_COLLATE') ?: 'utf8mb4_unicode_ci');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

// --- Clés de sécurité ---
foreach ([
    'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
    'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
] as $key) {
    define($key, env($key));
}

// --- Debug ---
$is_dev = WP_ENV === 'development';
define('WP_DEBUG', $is_dev && (bool) env('WP_DEBUG'));
define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', $is_dev);
ini_set('display_errors', '0');

// --- Cache ---
define('WP_CACHE', (bool) env('WP_CACHE'));
define('WP_CACHE_KEY_SALT', env('WP_CACHE_KEY_SALT') ?: 'headless_wordpress');

// --- LiteSpeed Cache ---
define('LSCACHE_OBJECT', env('LSCACHE_OBJECT') ?? true);
define('LSCACHE_OBJECT_MEMCACHED_HOST', env('LSCACHE_OBJECT_MEMCACHED_HOST') ?: 'memcached');
define('LSCACHE_OBJECT_MEMCACHED_PORT', env('LSCACHE_OBJECT_MEMCACHED_PORT') ?: 11211);

// --- Frontend Astro (liens de preview) ---
define('FRONTEND_URL', env('FRONTEND_URL') ?: '');

// --- JWT (preview) ---
define('JWT_SECRET', env('JWT_SECRET') ?: '');

// --- Rebuild du frontend via GitHub Actions (mu-plugin headless-webhook) ---
define('GITHUB_REBUILD_REPO', env('GITHUB_REBUILD_REPO') ?: '');
define('GITHUB_REBUILD_TOKEN', env('GITHUB_REBUILD_TOKEN') ?: '');

// --- Mises à jour gérées par Composer, pas par WordPress ---
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);

// --- Limiter les révisions ---
define('WP_POST_REVISIONS', 3);

// --- Chemin du cœur WordPress ---
if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}

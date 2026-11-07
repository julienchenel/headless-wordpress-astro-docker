#!/bin/sh

# --- Attendre que MySQL soit prêt ---
echo "⏳ Attente de MySQL..."
while ! mysqladmin ping -h mysql -u ${WORDPRESS_DB_USER} -p${WORDPRESS_DB_PASSWORD} --silent; do
    sleep 1
done
echo "✅ MySQL est prêt !"

# --- Configurer WordPress (si non installé) ---
if ! wp core is-installed --allow-root; then
    echo "⏳ Installation de WordPress..."
    wp core install \
        --url=${WP_HOME} \
        --title="Headless WordPress" \
        --admin_user=admin \
        --admin_password=${WORDPRESS_DB_PASSWORD} \
        --admin_email=admin@localhost \
        --skip-email \
        --allow-root

    # --- Activer les plugins ---
    wp plugin install pods headless-bridge litespeed-cache query-monitor ithemes-security --activate --allow-root

    # --- Configurer Headless Bridge ---
    wp option update headless_bridge_pods_support true --allow-root
    wp option update headless_bridge_cache_enabled true --allow-root
    wp option update headless_bridge_cache_driver memcached --allow-root
    wp option update headless_bridge_cache_ttl 3600 --allow-root

    # --- Configurer LiteSpeed Cache ---
    wp option update litespeed-cache object true --allow-root
    wp option update litespeed-cache object_memcached_host memcached --allow-root
    wp option update litespeed-cache object_memcached_port 11211 --allow-root

    # --- Configurer les permaliens ---
    wp rewrite structure '/%postname%/' --hard --allow-root
    wp rewrite flush --allow-root

    # --- Créer un utilisateur par défaut ---
    wp user create neiluj neiluj@localhost --user_pass=${WORDPRESS_DB_PASSWORD} --role=administrator --allow-root

    echo "✅ WordPress installé et configuré !"
else
    echo "✅ WordPress déjà installé."
fi

# --- Copier les mu-plugins ---
cp -r /var/www/html/web/wp-content/mu-plugins/* /var/www/html/web/wp-content/mu-plugins/ 2>/dev/null || true

# --- Démarrer PHP-FPM ---
echo "🚀 Démarrage de PHP-FPM..."
exec "$@"

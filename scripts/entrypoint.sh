#!/bin/sh
# Démarrage du conteneur WordPress : dépendances, installation au premier
# lancement, puis exécution de la commande (Supervisor : PHP-FPM + Nginx).
set -e

cd /var/www/html

# --- Dépendances : backend/ est monté depuis l'hôte, vendor/ peut manquer ---
if [ ! -f vendor/autoload.php ] || [ ! -d web/wp ]; then
    echo "⏳ composer install..."
    su-exec www-data composer install --no-interaction --no-progress
fi

# --- Lire une variable de backend/.env (même parsing que WordPress) ---
env_get() {
    php -r 'require "vendor/autoload.php";
        Dotenv\Dotenv::createImmutable(".")->safeLoad();
        echo $_ENV[$argv[1]] ?? "";' "$1"
}

# --- Installer WordPress si la base est vide ---
if ! wp core is-installed 2>/dev/null; then
    echo "⏳ Installation de WordPress..."
    wp core install \
        --url="$(env_get WP_HOME)" \
        --title="$(env_get WP_TITLE)" \
        --admin_user="$(env_get WP_ADMIN_USER)" \
        --admin_password="$(env_get WP_ADMIN_PASSWORD)" \
        --admin_email="$(env_get WP_ADMIN_EMAIL)" \
        --skip-email

    # Thème, plugins et permaliens : mu-plugin headless-bootstrap (au premier chargement).
    wp eval 'echo "Configuration : ok\n";'

    echo "✅ WordPress installé : $(env_get WP_HOME)/wp-admin (utilisateur $(env_get WP_ADMIN_USER))"
else
    echo "✅ WordPress déjà installé."
fi

exec "$@"

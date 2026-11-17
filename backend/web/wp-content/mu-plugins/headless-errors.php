<?php
/**
 * Plugin Name: Headless Errors
 * Description: Masque les dépréciations PHP (E_DEPRECATED) émises par les plugins tiers
 *              pas encore à jour pour PHP 8.5 (ex. Pods), pour garder debug.log lisible.
 *
 * Chargé avant les plugins normaux : s'applique à leur compilation.
 * Mettre HEADLESS_SHOW_DEPRECATIONS=true dans .env pour les réafficher.
 */

if (!filter_var(getenv('HEADLESS_SHOW_DEPRECATIONS'), FILTER_VALIDATE_BOOL)) {
    error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

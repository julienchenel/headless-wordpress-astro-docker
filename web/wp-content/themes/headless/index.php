<?php
/**
 * Le site public est rendu par Astro : WordPress ne sert que l'admin et les API.
 */

status_header(200);
header('Content-Type: text/plain; charset=utf-8');
echo "WordPress headless : contenu disponible via /graphql et /wp-json.\n";

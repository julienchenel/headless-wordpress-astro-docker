<?php
/**
 * Plugin Name: Headless GraphQL (Bedrock)
 * Description: Sert WPGraphQL sur /graphql à la racine du site.
 *
 * WPGraphQL calcule son endpoint avec site_url(), qui vaut WP_HOME/wp avec Bedrock :
 * sans ce correctif, l'API ne répond que sur /wp/graphql.
 */

add_filter('graphql_pre_is_graphql_http_request', function ($is_graphql_request) {
    if ($is_graphql_request !== null || empty($_SERVER['REQUEST_URI'])) {
        return $is_graphql_request;
    }

    $route = class_exists('\WPGraphQL\Router') ? \WPGraphQL\Router::$route : 'graphql';
    $endpoint = (string) wp_parse_url(home_url($route), PHP_URL_PATH);
    $request = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);

    return trim($request, '/') === trim($endpoint, '/') ? true : null;
});

// --- URL publique de WordPress (WP_HOME) : generalSettings.url renvoie WP_SITEURL (…/wp) avec Bedrock ---
add_action('graphql_register_types', function () {
    register_graphql_field('RootQuery', 'homeUrl', [
        'type' => 'String',
        'description' => 'URL publique de WordPress (WP_HOME), base des liens internes du contenu.',
        'resolve' => fn () => home_url(),
    ]);
});

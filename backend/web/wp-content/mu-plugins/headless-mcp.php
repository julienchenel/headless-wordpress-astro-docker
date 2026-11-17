<?php
/**
 * Plugin Name: Headless MCP
 * Description: Capacités (Abilities API) de gestion du contenu, exposées comme outils MCP
 *              sur le serveur /wp-json/mcp/headless (plugin MCP Adapter).
 *
 * Authentification : mot de passe d'application WordPress (en-tête Authorization: Basic).
 * Chaque outil vérifie les droits de l'utilisateur connecté (edit_posts, publish_posts…).
 * La suppression met à la corbeille (réversible), jamais de suppression définitive.
 */

const HEADLESS_MCP_SERVER = 'headless';
const HEADLESS_MCP_STATUSES = ['draft', 'pending', 'publish', 'private', 'future'];

// ---------------------------------------------------------------------------
// Utilitaires
// ---------------------------------------------------------------------------

function headless_mcp_has_polylang(): bool
{
    return function_exists('pll_get_post_language');
}

/** Résumé d'un contenu, renvoyé par la plupart des outils. */
function headless_mcp_post_summary(WP_Post $post): array
{
    $data = [
        'id' => $post->ID,
        'type' => $post->post_type,
        'status' => $post->post_status,
        'title' => get_the_title($post),
        'slug' => $post->post_name,
        'date' => get_post_time('c', true, $post) ?: null,
        'modified' => get_post_modified_time('c', true, $post) ?: null,
        'uri' => wp_make_link_relative(get_permalink($post)),
        'edit_url' => get_edit_post_link($post, 'raw'),
    ];
    if (headless_mcp_has_polylang()) {
        $data['language'] = pll_get_post_language($post->ID) ?: null;
        $data['translations'] = pll_get_post_translations($post->ID);
    }
    return $data;
}

function headless_mcp_get_editable_post(int $id)
{
    $post = get_post($id);
    if (!$post) {
        return new WP_Error('not_found', "Contenu #$id introuvable.");
    }
    if (!current_user_can('edit_post', $post->ID)) {
        return new WP_Error('forbidden', "Droits insuffisants pour modifier le contenu #$id.");
    }
    return $post;
}

/** Applique catégories, étiquettes et langue après création ou mise à jour. */
function headless_mcp_apply_terms(int $post_id, array $input): void
{
    if (isset($input['categories'])) {
        $ids = array_map(fn ($name) => headless_mcp_term_id($name, 'category', $input['language'] ?? null), (array) $input['categories']);
        wp_set_post_categories($post_id, array_filter($ids));
    }
    if (isset($input['tags'])) {
        wp_set_post_tags($post_id, (array) $input['tags']);
    }
}

/** Identifiant d'un terme par nom, ID ou slug (créé s'il n'existe pas). */
function headless_mcp_term_id($name, string $taxonomy, ?string $language): int
{
    if (is_numeric($name)) {
        return (int) $name;
    }
    $term = get_term_by('name', $name, $taxonomy) ?: get_term_by('slug', sanitize_title($name), $taxonomy);
    if ($term) {
        return (int) $term->term_id;
    }
    $created = wp_insert_term($name, $taxonomy);
    if (is_wp_error($created)) {
        return 0;
    }
    if ($language && function_exists('pll_set_term_language')) {
        pll_set_term_language($created['term_id'], $language);
    }
    return (int) $created['term_id'];
}

function headless_mcp_validate_language(?string $language)
{
    if (!$language) {
        return null;
    }
    if (!headless_mcp_has_polylang()) {
        return new WP_Error('no_polylang', 'Polylang n\'est pas actif.');
    }
    if (!in_array($language, pll_languages_list(), true)) {
        return new WP_Error('unknown_language', "Langue inconnue : $language. Langues disponibles : " . implode(', ', pll_languages_list()));
    }
    return $language;
}

// Schémas réutilisés
function headless_mcp_post_fields_schema(): array
{
    return [
        'title' => ['type' => 'string', 'description' => 'Titre.'],
        'content' => ['type' => 'string', 'description' => 'Contenu HTML (ou blocs Gutenberg).'],
        'excerpt' => ['type' => 'string', 'description' => 'Extrait.'],
        'slug' => ['type' => 'string', 'description' => 'Slug d\'URL (généré à partir du titre si absent).'],
        'status' => ['type' => 'string', 'enum' => HEADLESS_MCP_STATUSES, 'description' => 'Statut. « future » demande une date.'],
        'date' => ['type' => 'string', 'description' => 'Date de publication (ISO 8601, heure locale du site).'],
        'categories' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Catégories (noms, créées si besoin).'],
        'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Étiquettes (noms).'],
    ];
}

// ---------------------------------------------------------------------------
// Capacités
// ---------------------------------------------------------------------------

add_action('wp_abilities_api_categories_init', function () {
    wp_register_ability_category('headless-content', [
        'label' => 'Contenu headless',
        'description' => 'Gestion des articles, pages, traductions et taxonomies du WordPress headless.',
    ]);
});

add_action('wp_abilities_api_init', function () {
    $can_edit = fn () => current_user_can('edit_posts');
    $common_meta = fn (array $annotations) => [
        'annotations' => $annotations,
        'mcp' => ['public' => true],
    ];

    // --- Infos du site ---
    wp_register_ability('headless/site-info', [
        'label' => 'Informations du site',
        'description' => 'Titre, URLs (WordPress et frontend Astro), langues Polylang, types de contenu et taxonomies disponibles.',
        'category' => 'headless-content',
        'input_schema' => ['type' => 'object', 'properties' => new stdClass()],
        'execute_callback' => function () {
            $types = array_values(array_map(
                fn ($t) => ['name' => $t->name, 'label' => $t->label],
                get_post_types(['show_ui' => true, 'public' => true], 'objects')
            ));
            $taxonomies = array_values(array_map(
                fn ($t) => ['name' => $t->name, 'label' => $t->label],
                get_taxonomies(['show_ui' => true, 'public' => true], 'objects')
            ));
            return [
                'title' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'wordpress_url' => home_url(),
                'frontend_url' => defined('FRONTEND_URL') ? FRONTEND_URL : null,
                'environment' => defined('WP_ENV') ? WP_ENV : null,
                'languages' => headless_mcp_has_polylang() ? pll_languages_list(['fields' => 'slug']) : [],
                'default_language' => headless_mcp_has_polylang() ? pll_default_language() : null,
                'post_types' => $types,
                'taxonomies' => $taxonomies,
            ];
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => true, 'idempotent' => true]),
    ]);

    // --- Lister ---
    wp_register_ability('headless/list-posts', [
        'label' => 'Lister les contenus',
        'description' => 'Liste les articles, pages ou contenus Pods, avec filtres (statut, langue, recherche, catégorie) et pagination.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_type' => ['type' => 'string', 'default' => 'post', 'description' => 'post, page ou un type Pods.'],
                'status' => ['type' => 'string', 'enum' => array_merge(HEADLESS_MCP_STATUSES, ['any', 'trash']), 'default' => 'any'],
                'language' => ['type' => 'string', 'description' => 'Code langue Polylang (ex. fr, en). Vide = toutes.'],
                'search' => ['type' => 'string'],
                'category' => ['type' => 'string', 'description' => 'Slug de catégorie.'],
                'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
            ],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            $language = headless_mcp_validate_language($input['language'] ?? null);
            if (is_wp_error($language)) {
                return $language;
            }
            $args = [
                'post_type' => $input['post_type'] ?? 'post',
                'post_status' => $input['status'] ?? 'any',
                's' => $input['search'] ?? '',
                'category_name' => $input['category'] ?? '',
                'posts_per_page' => $input['per_page'] ?? 20,
                'paged' => $input['page'] ?? 1,
                'orderby' => 'modified',
                'order' => 'DESC',
            ];
            // Polylang filtre par langue via « lang » ; '' = toutes les langues.
            if (headless_mcp_has_polylang()) {
                $args['lang'] = $language ?? '';
            }
            $query = new WP_Query($args);
            return [
                'total' => (int) $query->found_posts,
                'pages' => (int) $query->max_num_pages,
                'items' => array_map('headless_mcp_post_summary', $query->posts),
            ];
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => true, 'idempotent' => true]),
    ]);

    // --- Lire ---
    wp_register_ability('headless/get-post', [
        'label' => 'Lire un contenu',
        'description' => 'Contenu complet d\'un article ou d\'une page : titre, contenu, extrait, catégories, étiquettes, langue et traductions.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ],
        'execute_callback' => function ($input) {
            $post = headless_mcp_get_editable_post((int) $input['id']);
            if (is_wp_error($post)) {
                return $post;
            }
            return headless_mcp_post_summary($post) + [
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'categories' => wp_get_post_categories($post->ID, ['fields' => 'names']),
                'tags' => wp_get_post_tags($post->ID, ['fields' => 'names']),
                'author' => get_the_author_meta('display_name', $post->post_author),
            ];
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => true, 'idempotent' => true]),
    ]);

    // --- Créer ---
    wp_register_ability('headless/create-post', [
        'label' => 'Créer un contenu',
        'description' => 'Crée un article (ou une page, un contenu Pods). Brouillon par défaut ; status=publish pour publier directement.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => headless_mcp_post_fields_schema() + [
                'post_type' => ['type' => 'string', 'default' => 'post'],
                'language' => ['type' => 'string', 'description' => 'Code langue Polylang (défaut : langue par défaut du site).'],
            ],
            'required' => ['title'],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            $language = headless_mcp_validate_language($input['language'] ?? null);
            if (is_wp_error($language)) {
                return $language;
            }
            $status = $input['status'] ?? 'draft';
            if (in_array($status, ['publish', 'future'], true) && !current_user_can('publish_posts')) {
                return new WP_Error('forbidden', 'Droits insuffisants pour publier.');
            }
            $id = wp_insert_post([
                'post_type' => $input['post_type'] ?? 'post',
                'post_title' => $input['title'],
                'post_content' => $input['content'] ?? '',
                'post_excerpt' => $input['excerpt'] ?? '',
                'post_name' => $input['slug'] ?? '',
                'post_status' => $status,
                'post_date' => $input['date'] ?? '',
            ], true);
            if (is_wp_error($id)) {
                return $id;
            }
            if (headless_mcp_has_polylang()) {
                pll_set_post_language($id, $language ?? pll_default_language());
            }
            headless_mcp_apply_terms($id, $input + ['language' => $language]);
            return headless_mcp_post_summary(get_post($id));
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
    ]);

    // --- Modifier ---
    wp_register_ability('headless/update-post', [
        'label' => 'Modifier un contenu',
        'description' => 'Met à jour les champs fournis d\'un contenu existant (titre, contenu, statut, catégories…). Les champs absents ne changent pas.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']] + headless_mcp_post_fields_schema(),
            'required' => ['id'],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            $post = headless_mcp_get_editable_post((int) $input['id']);
            if (is_wp_error($post)) {
                return $post;
            }
            if (in_array($input['status'] ?? '', ['publish', 'future'], true) && !current_user_can('publish_post', $post->ID)) {
                return new WP_Error('forbidden', 'Droits insuffisants pour publier.');
            }
            $map = ['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'slug' => 'post_name', 'status' => 'post_status', 'date' => 'post_date'];
            $data = ['ID' => $post->ID];
            foreach ($map as $field => $column) {
                if (array_key_exists($field, $input)) {
                    $data[$column] = $input[$field];
                }
            }
            $result = wp_update_post($data, true);
            if (is_wp_error($result)) {
                return $result;
            }
            $language = headless_mcp_has_polylang() ? pll_get_post_language($post->ID) : null;
            headless_mcp_apply_terms($post->ID, $input + ['language' => $language]);
            return headless_mcp_post_summary(get_post($post->ID));
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => false, 'destructive' => false, 'idempotent' => true]),
    ]);

    // --- Traduire ---
    wp_register_ability('headless/translate-post', [
        'label' => 'Traduire un contenu',
        'description' => 'Crée (ou met à jour) la traduction d\'un contenu dans une autre langue Polylang, liée à l\'original. '
            . 'Fournir le texte déjà traduit (titre, contenu…). Catégories et étiquettes peuvent être précisées dans la langue cible.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'source_id' => ['type' => 'integer', 'description' => 'Contenu original.'],
                'language' => ['type' => 'string', 'description' => 'Langue cible (ex. en).'],
            ] + headless_mcp_post_fields_schema(),
            'required' => ['source_id', 'language', 'title'],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            if (!headless_mcp_has_polylang()) {
                return new WP_Error('no_polylang', 'Polylang n\'est pas actif.');
            }
            $source = headless_mcp_get_editable_post((int) $input['source_id']);
            if (is_wp_error($source)) {
                return $source;
            }
            $language = headless_mcp_validate_language($input['language']);
            if (is_wp_error($language)) {
                return $language;
            }
            $source_language = pll_get_post_language($source->ID) ?: pll_default_language();
            if ($language === $source_language) {
                return new WP_Error('same_language', "Le contenu #{$source->ID} est déjà en $language.");
            }
            $status = $input['status'] ?? 'draft';
            if (in_array($status, ['publish', 'future'], true) && !current_user_can('publish_posts')) {
                return new WP_Error('forbidden', 'Droits insuffisants pour publier.');
            }

            $translations = pll_get_post_translations($source->ID);
            $postarr = [
                'post_type' => $source->post_type,
                'post_title' => $input['title'],
                'post_content' => $input['content'] ?? '',
                'post_excerpt' => $input['excerpt'] ?? '',
                'post_name' => $input['slug'] ?? '',
                'post_status' => $status,
                'post_parent' => $source->post_parent,
                'menu_order' => $source->menu_order,
            ];
            if (!empty($translations[$language])) {
                $postarr['ID'] = $translations[$language]; // traduction existante : mise à jour
                $id = wp_update_post($postarr, true);
            } else {
                $id = wp_insert_post($postarr, true);
            }
            if (is_wp_error($id)) {
                return $id;
            }

            pll_set_post_language($id, $language);
            $translations[$source_language] = $source->ID;
            $translations[$language] = $id;
            pll_save_post_translations($translations);
            headless_mcp_apply_terms($id, $input + ['language' => $language]);

            return headless_mcp_post_summary(get_post($id));
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => false, 'destructive' => false, 'idempotent' => true]),
    ]);

    // --- Corbeille ---
    wp_register_ability('headless/trash-post', [
        'label' => 'Mettre un contenu à la corbeille',
        'description' => 'Déplace un contenu dans la corbeille (récupérable depuis l\'admin, ou via restore=true). Jamais de suppression définitive.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'restore' => ['type' => 'boolean', 'default' => false, 'description' => 'true = sortir de la corbeille.'],
            ],
            'required' => ['id'],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            $post = headless_mcp_get_editable_post((int) $input['id']);
            if (is_wp_error($post)) {
                return $post;
            }
            if (!current_user_can('delete_post', $post->ID)) {
                return new WP_Error('forbidden', 'Droits insuffisants.');
            }
            $result = !empty($input['restore']) ? wp_untrash_post($post->ID) : wp_trash_post($post->ID);
            if (!$result) {
                return new WP_Error('failed', 'Opération impossible.');
            }
            return headless_mcp_post_summary(get_post($post->ID));
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => false, 'destructive' => true, 'idempotent' => true]),
    ]);

    // --- Lien de preview ---
    wp_register_ability('headless/preview-link', [
        'label' => 'Lien de prévisualisation',
        'description' => 'Lien vers le frontend Astro pour prévisualiser un brouillon (valable 1 heure).',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ],
        'execute_callback' => function ($input) {
            $post = headless_mcp_get_editable_post((int) $input['id']);
            if (is_wp_error($post)) {
                return $post;
            }
            $url = function_exists('headless_preview_url') ? headless_preview_url($post) : null;
            return ['id' => $post->ID, 'preview_url' => $url ?? get_preview_post_link($post)];
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => true, 'idempotent' => false]),
    ]);

    // --- Termes ---
    wp_register_ability('headless/list-terms', [
        'label' => 'Lister catégories ou étiquettes',
        'description' => 'Termes d\'une taxonomie (category, post_tag…), avec leur langue Polylang.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'taxonomy' => ['type' => 'string', 'default' => 'category'],
                'language' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            $args = ['taxonomy' => $input['taxonomy'] ?? 'category', 'hide_empty' => false];
            if (headless_mcp_has_polylang()) {
                $args['lang'] = $input['language'] ?? '';
            }
            $terms = get_terms($args);
            if (is_wp_error($terms)) {
                return $terms;
            }
            return array_map(fn ($t) => [
                'id' => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
                'count' => $t->count,
                'language' => function_exists('pll_get_term_language') ? (pll_get_term_language($t->term_id) ?: null) : null,
            ], $terms);
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => true, 'idempotent' => true]),
    ]);

    wp_register_ability('headless/create-term', [
        'label' => 'Créer une catégorie ou une étiquette',
        'description' => 'Crée un terme dans une taxonomie, éventuellement dans une langue et lié à sa traduction.',
        'category' => 'headless-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'taxonomy' => ['type' => 'string', 'default' => 'category'],
                'name' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'language' => ['type' => 'string'],
                'translation_of' => ['type' => 'integer', 'description' => 'ID du terme dont celui-ci est la traduction.'],
            ],
            'required' => ['name'],
        ],
        'execute_callback' => function ($input) {
            $input = (array) $input;
            $taxonomy = $input['taxonomy'] ?? 'category';
            if (!current_user_can(get_taxonomy($taxonomy)->cap->manage_terms ?? 'manage_categories')) {
                return new WP_Error('forbidden', 'Droits insuffisants.');
            }
            $language = headless_mcp_validate_language($input['language'] ?? null);
            if (is_wp_error($language)) {
                return $language;
            }
            $created = wp_insert_term($input['name'], $taxonomy, [
                'slug' => $input['slug'] ?? '',
                'description' => $input['description'] ?? '',
            ]);
            if (is_wp_error($created)) {
                return $created;
            }
            $id = (int) $created['term_id'];
            if ($language && function_exists('pll_set_term_language')) {
                pll_set_term_language($id, $language);
                if (!empty($input['translation_of'])) {
                    $source = (int) $input['translation_of'];
                    $translations = pll_get_term_translations($source);
                    $translations[pll_get_term_language($source) ?: pll_default_language()] = $source;
                    $translations[$language] = $id;
                    pll_save_term_translations($translations);
                }
            }
            return ['id' => $id, 'taxonomy' => $taxonomy, 'name' => $input['name'], 'language' => $language];
        },
        'permission_callback' => $can_edit,
        'meta' => $common_meta(['readonly' => false, 'destructive' => false, 'idempotent' => false]),
    ]);
});

// ---------------------------------------------------------------------------
// Serveur MCP dédié : /wp-json/mcp/headless (chaque capacité = un outil MCP)
// ---------------------------------------------------------------------------

add_action('mcp_adapter_init', function ($adapter) {
    $adapter->create_server(
        HEADLESS_MCP_SERVER,
        'mcp',
        HEADLESS_MCP_SERVER,
        'WordPress headless',
        'Gestion du contenu du WordPress headless : articles, pages, traductions Polylang, catégories.',
        'v1.0.0',
        [\WP\MCP\Transport\HttpTransport::class],
        \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
        null,
        [
            'headless/site-info',
            'headless/list-posts',
            'headless/get-post',
            'headless/create-post',
            'headless/update-post',
            'headless/translate-post',
            'headless/trash-post',
            'headless/preview-link',
            'headless/list-terms',
            'headless/create-term',
        ],
        [],
        [],
        fn () => current_user_can('edit_posts')
    );
});

/**
 * Accès à WordPress : API GraphQL (WPGraphQL) et preview des brouillons.
 */
import { WP_URL } from 'astro:env/server';

export interface WpLanguage {
  /** Slug Polylang (fr, en…) : préfixe d'URL et identifiant interne. */
  slug: string;
  /** Code GraphQL (FR, EN…) : valeur des filtres `language`. */
  code: string;
  name: string;
  /** Locale WordPress (fr_FR, en_GB…). */
  locale: string;
  /** URI de l'accueil de la langue (/ pour la langue par défaut, /fr/…). */
  homeUri: string;
}

export interface WpTranslation {
  uri: string;
  language: { slug: string } | null;
}

export interface WpNode {
  id: string;
  uri: string;
  title?: string;
  content?: string;
  excerpt?: string;
  date?: string;
  /** Langue Polylang (articles et pages ; absente si Polylang n'est pas actif). */
  language?: { slug: string } | null;
  translations?: WpTranslation[] | null;
}

export interface WpLanguages {
  languages: WpLanguage[];
  /** Langue par défaut, ou null sans Polylang. */
  defaultLanguage: WpLanguage | null;
}

export interface WpSettings {
  title: string;
  description: string;
  /** URL publique de WordPress (WP_HOME). */
  url: string;
}

/** Exécute une requête GraphQL sur WPGraphQL. */
export async function wpQuery<T>(query: string, variables: Record<string, unknown> = {}): Promise<T> {
  const res = await fetch(`${WP_URL}/graphql`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ query, variables }),
  });
  if (!res.ok) {
    throw new Error(`WPGraphQL a répondu ${res.status} (${WP_URL}/graphql)`);
  }
  const json = (await res.json()) as { data?: T; errors?: { message: string }[] };
  if (json.errors?.length) {
    throw new Error(json.errors.map((e) => e.message).join('\n'));
  }
  return json.data as T;
}

// Langue et traductions : ajoutées par WPGraphQL Polylang sur Post et Page.
const LANGUAGE_FIELDS = `language { slug } translations { uri language { slug } }`;

const COMMON_FIELDS = `
  id
  uri
  ... on NodeWithTitle { title }
  ... on NodeWithContentEditor { content }
  ... on NodeWithExcerpt { excerpt }
  ... on ContentNode { date }
`;

/** Champs d'un contenu de type quelconque (contentNodes, nodeByUri). */
const NODE_FIELDS = `${COMMON_FIELDS}
  ... on Post { ${LANGUAGE_FIELDS} }
  ... on Page { ${LANGUAGE_FIELDS} }
`;

/** Champs d'un article (requête posts : pas de fragment Page possible). */
const POST_FIELDS = `${COMMON_FIELDS} ${LANGUAGE_FIELDS}`;

/** Langues Polylang (vide si Polylang ou WPGraphQL Polylang n'est pas actif). */
export async function getLanguages(): Promise<WpLanguages> {
  try {
    const data = await wpQuery<{
      languages: (Omit<WpLanguage, 'homeUri'> & { homeUrl: string })[] | null;
      defaultLanguage: { slug: string } | null;
    }>(`{ languages { slug code name locale homeUrl } defaultLanguage { slug } }`);
    const languages = (data.languages ?? []).map(({ homeUrl, ...language }) => ({
      ...language,
      homeUri: new URL(homeUrl).pathname,
    }));
    const defaultLanguage = languages.find((l) => l.slug === data.defaultLanguage?.slug) ?? languages[0] ?? null;
    // Langue par défaut en premier (ordre du sélecteur de langue).
    languages.sort((a, b) => Number(b === defaultLanguage) - Number(a === defaultLanguage));
    return { languages, defaultLanguage };
  } catch {
    return { languages: [], defaultLanguage: null };
  }
}

export async function getSettings(): Promise<WpSettings> {
  // homeUrl : champ ajouté par le mu-plugin headless-graphql (WP_HOME).
  const data = await wpQuery<{ generalSettings: Omit<WpSettings, 'url'>; homeUrl: string }>(
    `{ generalSettings { title description } homeUrl }`,
  );
  return { ...data.generalSettings, url: data.homeUrl };
}

/** Tous les contenus publics (articles, pages, CPT Pods exposés dans GraphQL). */
export async function getAllNodes(): Promise<WpNode[]> {
  const nodes: WpNode[] = [];
  let after: string | null = null;
  // WPGraphQL limite à 100 éléments par requête : on pagine.
  do {
    const data: {
      contentNodes: { nodes: WpNode[]; pageInfo: { hasNextPage: boolean; endCursor: string | null } };
    } = await wpQuery(
      `query ($after: String) {
        contentNodes(first: 100, after: $after) {
          nodes { ${NODE_FIELDS} }
          pageInfo { hasNextPage endCursor }
        }
      }`,
      { after },
    );
    nodes.push(...data.contentNodes.nodes);
    after = data.contentNodes.pageInfo.hasNextPage ? data.contentNodes.pageInfo.endCursor : null;
  } while (after);
  return nodes.filter((node) => node.uri && node.uri !== '/');
}

/** Derniers articles, dans une langue (code GraphQL, ex. FR) ou toutes langues confondues. */
export async function getLatestPosts(count = 10, languageCode?: string): Promise<WpNode[]> {
  const data = await wpQuery<{ posts: { nodes: WpNode[] } }>(
    languageCode
      ? `query ($count: Int, $language: LanguageCodeFilterEnum) {
          posts(first: $count, where: { language: $language }) { nodes { ${POST_FIELDS} } }
        }`
      : `query ($count: Int) { posts(first: $count) { nodes { ${POST_FIELDS} } } }`,
    languageCode ? { count, language: languageCode } : { count },
  );
  return data.posts.nodes;
}

/**
 * Brouillon à prévisualiser : WordPress vérifie le token (JWT signé côté WordPress).
 * Renvoie null si le token est invalide ou expiré.
 */
export async function getPreview(token: string): Promise<WpNode | null> {
  const res = await fetch(`${WP_URL}/wp-json/headless/v1/preview?token=${encodeURIComponent(token)}`);
  if (!res.ok) {
    return null;
  }
  return (await res.json()) as WpNode;
}

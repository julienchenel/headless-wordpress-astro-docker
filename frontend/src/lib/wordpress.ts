/**
 * Accès à WordPress : API GraphQL (WPGraphQL) et preview des brouillons.
 */
import { WP_URL } from 'astro:env/server';

export interface WpNode {
  id: string;
  uri: string;
  title?: string;
  content?: string;
  excerpt?: string;
  date?: string;
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

const NODE_FIELDS = `
  id
  uri
  ... on NodeWithTitle { title }
  ... on NodeWithContentEditor { content }
  ... on NodeWithExcerpt { excerpt }
  ... on ContentNode { date }
`;

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

export async function getLatestPosts(count = 10): Promise<WpNode[]> {
  const data = await wpQuery<{ posts: { nodes: WpNode[] } }>(
    `query ($count: Int) { posts(first: $count) { nodes { ${NODE_FIELDS} } } }`,
    { count },
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

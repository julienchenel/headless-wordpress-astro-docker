/**
 * Rend relatifs les liens internes du contenu WordPress
 * (https://cms.exemple.fr/mon-article/ → /mon-article/), pour qu'ils pointent
 * vers le frontend. Les médias (/wp-content/...) et l'admin (/wp/...) restent
 * servis par WordPress.
 */
export function rewriteUrls(html: string | undefined, wordpressUrl: string): string {
  if (!html) {
    return '';
  }
  const base = wordpressUrl.replace(/\/+$/, '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return html.replace(new RegExp(`${base}/(?!wp-content/|wp/)`, 'g'), '/');
}

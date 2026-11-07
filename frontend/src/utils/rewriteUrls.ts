/**
 * Remplace les URLs du backend (WordPress) par celles du frontend (Astro).
 * @param html - HTML ou JSON à traiter
 * @param backendUrl - URL du backend (ex: http://localhost:8080)
 * @param frontendUrl - URL du frontend (ex: http://localhost:3000)
 */
export function rewriteUrls(
  content: string,
  backendUrl: string = import.meta.env.VITE_WP_URL,
  frontendUrl: string = ''
): string {
  if (!backendUrl) {
    return content;
  }

  // Si frontendUrl n'est pas fourni, on utilise la même base que le backend
  // (utile pour les images, PDFs, etc.)
  if (!frontendUrl) {
    frontendUrl = backendUrl;
  }

  // Remplacer les URLs absolues du backend
  const backendRegex = new RegExp(backendUrl.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g');
  const rewritten = content.replace(backendRegex, frontendUrl);

  // Remplacer les URLs relatives (/wp-content/...)
  return rewritten.replace(/\/wp-content\//g, `${frontendUrl}/wp-content/`);
}

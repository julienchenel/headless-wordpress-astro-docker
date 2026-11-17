import { defineConfig, envField } from 'astro/config';
import node from '@astrojs/node';
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';
import { loadEnv } from 'vite';

// .env / .env.production (le fichier de config n'est pas chargé automatiquement)
const env = { ...loadEnv(process.env.NODE_ENV ?? 'production', process.cwd(), ''), ...process.env };

export default defineConfig({
  // URL publique du frontend (sitemap, URLs canoniques)
  site: env.SITE_URL || 'http://localhost:3000',

  // Pages générées au build (SSG). Seules les routes avec `prerender = false`
  // (la preview des brouillons) sont rendues à la demande par le serveur Node.
  output: 'static',
  adapter: node({ mode: 'standalone' }),

  env: {
    schema: {
      // URL de WordPress vue depuis le serveur Astro (build et preview).
      // Pas un secret : figée au build.
      WP_URL: envField.string({ context: 'server', access: 'public', default: 'http://localhost:8080' }),
    },
  },

  integrations: [sitemap({ filter: (page) => !page.includes('/preview') })],

  vite: {
    plugins: [tailwindcss()],
  },

  server: {
    port: 3000,
  },
});

import { defineConfig } from 'astro/config';
import headlessBridge from '@headless-bridge/astro';
import sitemap from '@astrojs/sitemap';
import tailwind from '@astrojs/tailwind';

export default defineConfig({
  site: process.env.VITE_WP_URL || 'http://localhost:8080',
  server: {
    port: 3000,
  },
  integrations: [
    headlessBridge({
      endpoint: process.env.VITE_WP_API_URL || 'http://localhost:8080/wp-json/headless-bridge/v1',
      includeFields: true,
      includeTaxonomies: true,
      batchSize: 50,
    }),
    sitemap(),
    tailwind(),
  ],
  output: 'static', // SSG
  adapter: '@astrojs/netlify', // Optionnel (si tu veux utiliser Netlify)
});

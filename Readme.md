# 🚀 WordPress Headless + Pods + WPGraphQL + Astro (Docker)

Une stack **100% reproductible** pour créer des sites WordPress headless avec :
- **Backend** : WordPress ([Bedrock](https://roots.io/bedrock/) de roots.io) + Pods (CPT/Repeater) + WPGraphQL (API GraphQL)
- **Frontend** : Astro (SSG) avec SEO, preview, et URL rewriting
- **Performance** : OPcache + Memcached + LiteSpeed Cache
- **Déploiement** : GitHub Actions → Hostinger (WordPress en PHP + Astro en Node.js)

## ✅ Prérequis

- [Docker](https://www.docker.com/) (v20+)
- [Docker Compose](https://docs.docker.com/compose/) (v2+)
- [Git](https://git-scm.com/)
- Un compte [Hostinger](https://www.hostinger.fr/) (Business Web Hosting ou supérieur : Node.js requis pour le frontend)

## 📁 Structure du projet

```bash
headless-wordpress-astro-docker/
├── backend/                  # WordPress + Bedrock
│   ├── composer.json         # Dépendances (WordPress, Pods, WPGraphQL, etc.)
│   ├── wp-cli.yml            # Indique à WP-CLI que WordPress est dans web/wp
│   ├── .htaccess             # Interdit l'accès à tout sauf web/ (production)
│   ├── .env                  # Variables d'environnement (non versionné)
│   ├── config/
│   │   └── application.php   # Configuration WordPress (lit le .env)
│   ├── web/                  # Racine web (servie par Nginx)
│   │   ├── index.php         # Front controller
│   │   ├── wp-config.php     # Charge config/application.php
│   │   ├── wp/               # Cœur WordPress (installé par Composer)
│   │   └── wp-content/       # Plugins, thèmes, mu-plugins
│   │       ├── mu-plugins/   # Nos mu-plugins
│   │       │   ├── headless-errors.php    # Masque les dépréciations PHP des plugins tiers
│   │       │   ├── headless-graphql.php   # Sert WPGraphQL sur /graphql
│   │       │   ├── headless-optimize.php
│   │       │   ├── headless-preview.php   # Preview des brouillons (JWT)
│   │       │   ├── headless-security.php
│   │       │   └── headless-webhook.php   # Rebuild du frontend à chaque publication
│   │       ├── plugins/      # Installés par Composer (non versionnés)
│   │       ├── .htaccess     # Règles LiteSpeed (production Hostinger)
│   │       └── themes/
│   │           └── headless/ # Thème minimal (le rendu est fait par Astro)
│   └── scripts/
│       ├── entrypoint.sh     # Démarrage du conteneur : installation de WordPress
│       └── setup.php         # Auto-configuration WordPress (à compléter)
│
├── frontend/                 # Astro 7 (SSG + serveur Node pour la preview)
│   ├── src/
│   │   ├── components/SEO.astro
│   │   ├── layouts/Base.astro
│   │   ├── lib/wordpress.ts  # Requêtes WPGraphQL + preview
│   │   ├── pages/
│   │   │   ├── index.astro       # Accueil (derniers articles)
│   │   │   ├── [...uri].astro    # Toutes les pages WordPress, générées au build
│   │   │   └── preview.astro     # Preview des brouillons (rendue à la demande)
│   │   └── utils/rewriteUrls.ts  # Liens internes du contenu → frontend
│   ├── astro.config.mjs      # Config Astro
│   ├── package.json          # Dépendances Node.js (Node 22.23+)
│   ├── .env.example          # Variables de dev
│   └── .env.production       # URLs de production (versionné, sans secret)
│
├── docker/                   # Configurations Docker
│   ├── nginx/conf.d/
│   │   └── wordpress.conf    # Vhost Nginx de WordPress
│   ├── php/                  # Config PHP (php.ini, OPcache, Memcached)
│   │   ├── php.ini
│   │   ├── wp                # Raccourci `wp` (WP-CLI en www-data)
│   │   └── conf.d/
│   │       ├── opcache.ini
│   │       └── memcached.ini
│   ├── supervisor/
│   │   └── supervisord.conf  # Lance PHP-FPM + Nginx dans le conteneur
│   └── mysql/
│       └── my.cnf            # Config MySQL
│
├── .github/workflows/
│   └── deploy.yml            # Branches deploy/backend et deploy/frontend pour Hostinger
│
├── docker-compose.yml        # Orchestration Docker
├── Dockerfile.backend        # Image WordPress (PHP-FPM 8.5 + Nginx)
├── Dockerfile.frontend       # Image Astro (dev)
├── .env.exemple              # Variables globales (MySQL)
└── Readme.md                 # Documentation
```

> **Bedrock** : le cœur WordPress est dans `web/wp`. L'administration est donc sur
> **`/wp/wp-admin`** (et la connexion sur `/wp/wp-login.php`). `/wp-admin` y redirige.

## 🛠️ Installation

### 1. Cloner le dépôt
```bash
git clone https://github.com/ton-utilisateur/headless-wordpress-astro-docker.git
cd headless-wordpress-astro-docker
```

### 2. Configurer les variables d'environnement
```bash
cp .env.exemple .env
cp backend/.env.exemple backend/.env
cp frontend/.env.example frontend/.env
```
(En Docker, l'URL de WordPress pour Astro est déjà définie dans `docker-compose.yml`.)
Édite ensuite les fichiers :
- `.env` et `backend/.env` : les identifiants MySQL (`DB_NAME`, `DB_USER`, `DB_PASSWORD`) doivent être **identiques** dans les deux fichiers.
- `backend/.env` : `WP_ADMIN_PASSWORD`, `JWT_SECRET` (32 caractères minimum) et les clés de sécurité `AUTH_KEY`… (à générer sur [roots.io/salts.html](https://roots.io/salts.html)).

### 3. Démarrer les conteneurs Docker
```bash
docker compose up -d --build
```
⚠️ Première exécution : le conteneur WordPress va :
- installer WordPress,
- activer le thème `headless` et les plugins WPGraphQL, Pods, Polylang et Query Monitor,
- configurer les permaliens,
- créer l'utilisateur admin (`WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` dans `backend/.env`).

Suivre l'installation : `docker compose logs -f wordpress`.

### 4. Accéder aux services

| Service | URL | Identifiants |
| --- | --- | --- |
| Admin WordPress | [http://localhost:8080/wp/wp-admin](http://localhost:8080/wp/wp-admin) | `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` |
| API GraphQL | [http://localhost:8080/graphql](http://localhost:8080/graphql) | - |
| API REST | [http://localhost:8080/wp-json](http://localhost:8080/wp-json) | - |
| MySQL | `127.0.0.1:3307` | `DB_USER` / `DB_PASSWORD` |
| Astro (Dev) | [http://localhost:3000](http://localhost:3000) | - |

> MySQL est exposé sur le port **3307** de l'hôte (le 3306 est souvent déjà pris par un MySQL local).

## 🧪 Développement

### Backend (WordPress)
- Admin : http://localhost:8080/wp/wp-admin
- API GraphQL : http://localhost:8080/graphql (IDE GraphiQL dans l'admin : **GraphQL → GraphiQL IDE**)
- Preview : le bouton **Prévisualiser** de l'éditeur ouvre le brouillon sur Astro (`/preview?token=…`, valable 1 h).
- Webhook : à chaque publication (ou modification d'un contenu publié), demande à GitHub de reconstruire le frontend (`headless-webhook.php`, une fois par minute au plus).

WP-CLI, dans le conteneur :
```bash
docker exec wp_headless wp plugin list
```
ou depuis l'hôte (WSL), en pointant vers le MySQL exposé :
```bash
cd backend && DB_HOST=127.0.0.1:3307 vendor/bin/wp plugin list
```

Les plugins sont gérés par **Composer** (l'installation depuis l'admin est désactivée) :
```bash
cd backend && composer require wpackagist-plugin/nom-du-plugin
docker exec wp_headless wp plugin activate nom-du-plugin
```

### Frontend (Astro)
- Dev server : http://localhost:3000 (conteneur `astro`, ou `npm run dev` dans `frontend/`)
- Build : `npm run build` → `dist/client` (pages statiques) + `dist/server` (serveur Node pour la preview)
- Démarrer le build : `npm start`
- Les données viennent de `src/lib/wordpress.ts` (WPGraphQL). L'URL de WordPress est `WP_URL`.

> Utilise Node.js **22.23 ou plus** (Astro 7). Sous WSL, lance `npm` dans le conteneur
> (`docker compose exec astro npm …`) plutôt qu'avec le npm de Windows.

## 📦 Configuration des Plugins

### Pods
1. Va dans **Pods Admin → Add New**.
2. Crée un Custom Post Type (ex : Portfolio).
3. Ajoute des champs (dont Repeater ou Flexible Content).
4. Active Polylang pour la traduction (désactive la synchronisation des métas).

### WPGraphQL
- Endpoint : `/graphql` (POST ou GET).
- Pour exposer un CPT Pods dans GraphQL : dans les réglages du Pod, active **Show in GraphQL** et renseigne les noms GraphQL (singulier / pluriel).
- Réglages : **GraphQL → Settings** dans l'admin.

### LiteSpeed Cache
Active Object Cache (Memcached) dans les paramètres.

## 🔄 Déploiement sur Hostinger

Tout est hébergé sur Hostinger, sur deux (sous-)domaines :

| Site | Exemple | Type Hostinger | Branche déployée |
| --- | --- | --- | --- |
| WordPress (CMS + API) | `cms.exemple.fr` | PHP 8.5, racine web sur `web/` | `deploy/backend` |
| Frontend Astro | `exemple.fr` | Node.js 24, app `astro`, dossier `frontend` | `deploy/frontend` |

À chaque push sur `main`, GitHub Actions (`.github/workflows/deploy.yml`) met à jour ces deux branches, et Hostinger les redéploie automatiquement :
- **`deploy/backend`** contient `backend/` avec `vendor/`, WordPress et les plugins déjà installés par Composer. Le `.env` de production et les uploads restent sur le serveur et ne sont jamais écrasés.
- **`deploy/frontend`** est une copie de `main` : Hostinger lance `npm run build` dans `frontend/` et sert le site.

Quand un contenu est publié dans WordPress, le mu-plugin `headless-webhook.php` déclenche le même workflow, qui ne reconstruit que le frontend.

### Mise en place (une fois)
1. **Hostinger** : connecter GitHub dans hPanel (section Git), puis créer les deux sites.
2. **WordPress** : créer une base MySQL, déployer `deploy/backend` et faire pointer la racine web du domaine du CMS sur `web/`. Le `.htaccess` de `backend/` bloque tout le reste.
3. **`.env` de production**, à créer sur le serveur à côté de `composer.json`, à partir de `backend/.env.exemple` :
   - `WP_ENV=production`, `WP_HOME=https://cms.exemple.fr`, identifiants de la base Hostinger (`DB_HOST=localhost`) ;
   - nouvelles clés de sécurité et nouveau `JWT_SECRET` ;
   - `LSCACHE_OBJECT=false` (pas de Memcached sur l'hébergement mutualisé) ;
   - `FRONTEND_URL=https://exemple.fr` ;
   - `GITHUB_REBUILD_REPO` et `GITHUB_REBUILD_TOKEN`, un jeton GitHub *fine-grained* limité au dépôt, avec la permission *Contents: write*.
4. **Frontend** : renseigner `frontend/.env.production` (`WP_URL`, `SITE_URL`), puis déployer `deploy/frontend` en Node.js (app `astro`, dossier `frontend`, Node 24).

Aucun secret GitHub n'est nécessaire : le workflow n'utilise que le jeton fourni automatiquement par GitHub Actions.

## 🔧 Commandes Utiles

| Commande | Description |
| --- | --- |
| `docker compose up -d` | Démarrer WordPress, MySQL, Memcached et Astro |
| `docker compose down` | Arrêter tous les conteneurs |
| `docker compose down -v` | Arrêter + supprimer les volumes (⚠️ **supprime la base de données**) |
| `docker compose logs -f wordpress` | Voir les logs WordPress (Nginx + PHP) |
| `docker compose logs -f astro` | Voir les logs Astro |
| `docker exec wp_headless wp cli info` | Info WP-CLI |
| `docker exec wp_headless wp plugin list` | Lister les plugins |
| `docker exec -it wp_mysql mysql -u root -p` | Accès MySQL (mot de passe = `DB_ROOT_PASSWORD`) |

## 📊 Performances

| Optimisation | Statut | Description |
| --- | --- | --- |
| **OPcache** | ✅ | Cache PHP bytecode |
| **Memcached** | ✅ | Sessions PHP + cache objet (via LiteSpeed Cache) |
| **WPGraphQL** | ✅ | Une seule requête pour récupérer exactement les champs utiles |
| **Batch Queries** | ✅ | DataLoader de WPGraphQL : évite les N+1 queries |
| **Pages statiques** | ✅ | Site généré au build, reconstruit à chaque publication |
| **Gzip** | ✅ | Compression HTTP |
| **Cache Browser** | ✅ | Cache des assets statiques (1 an) |

## 🌍 Multilingue (Polylang + Pods)

### Configuration
1. Polylang est déjà inclus dans `composer.json` et activé.
2. Va dans **Langues → Ajouter une langue** (ex : Français, Anglais).
3. Dans Pods, pour chaque CPT :
   - désactive « Synchronize with Polylang » (pour éviter les conflits),
   - crée des champs séparés par langue si nécessaire (ex : `title_fr`, `title_en`).

> Polylang n'expose pas les langues dans GraphQL tout seul : il faudra ajouter
> l'extension **WPGraphQL Polylang** pour filtrer par langue (`language: FR`).

### Récupérer les données dans Astro

```ts
import { wpQuery } from '../lib/wordpress';

const data = await wpQuery<{ posts: { nodes: { title: string; uri: string }[] } }>(
  `{ posts(first: 10) { nodes { title uri } } }`,
);
```

## 🔍 Dépannage

### WordPress ne se connecte pas à MySQL
- Vérifie que le conteneur `mysql` est démarré et *healthy* : `docker compose ps`.
- Vérifie que `DB_NAME`, `DB_USER` et `DB_PASSWORD` sont identiques dans `.env` et `backend/.env`.
- Consulte les logs : `docker compose logs mysql`.

### L'API GraphQL ne répond pas
- Vérifie que le plugin est activé : `docker exec wp_headless wp plugin list`.
- Teste l'API directement :
  ```bash
  curl -X POST -H 'Content-Type: application/json' -d '{"query":"{ generalSettings { title } }"}' http://localhost:8080/graphql
  ```
- Si `/graphql` renvoie la page du thème, vérifie que le mu-plugin `headless-graphql.php` est présent.

### Astro ne récupère pas les données
- Vérifie `WP_URL` : `frontend/.env` en local, `docker-compose.yml` en Docker, `frontend/.env.production` pour Hostinger.
- Vérifie que le conteneur `wordpress` est accessible depuis `astro` (via le réseau `headless_network`).

### Les images ne s'affichent pas
- Vérifie que les URLs sont réécrites (`rewriteUrls.ts`).
- Les uploads sont dans `backend/web/wp-content/uploads` (non versionné).
- Les images sont servies par WordPress (`https://cms.exemple.fr/wp-content/uploads/...`) : seuls les liens internes sont réécrits vers le frontend.

## 📚 Ressources

- [Bedrock Documentation](https://roots.io/bedrock/docs/)
- [WPGraphQL Documentation](https://www.wpgraphql.com/docs/introduction)
- [Pods Documentation](https://docs.pods.io/)
- [Astro Documentation](https://docs.astro.build/)
- [Hostinger : déployer une app Node.js](https://www.hostinger.com/support/how-to-deploy-a-nodejs-website-in-hostinger/)
- [Hostinger FTP](https://www.hostinger.fr/tutoriels/comment-se-connecter-a-ftp)

## 🎯 Comment Démarrer ?

**Étape 1 : Clone ce dépôt**
```bash
git clone https://github.com/ton-utilisateur/headless-wordpress-astro-docker.git
cd headless-wordpress-astro-docker
```

**Étape 2 : Configure les .env** (voir [Installation](#2-configurer-les-variables-denvironnement))

**Étape 3 : Lance Docker**
```bash
docker compose up -d --build
```
⏳ Attends 1 à 2 minutes le temps que WordPress s'installe (`docker compose logs -f wordpress`).

**Étape 4 : Accède à WordPress**
- Admin : [http://localhost:8080/wp/wp-admin](http://localhost:8080/wp/wp-admin)
- Identifiant / mot de passe : `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` (dans `backend/.env`)
- Frontend (Astro) : http://localhost:3000

**Étape 5 : Configure Pods**
1. Va dans **Pods Admin → Add New**.
2. Crée un Custom Post Type (ex : Portfolio) et active **Show in GraphQL**.
3. Ajoute des champs : Repeater Field (ex : `gallery_images`), Flexible Content (ex : `content_sections`).
4. Active Polylang si tu veux du multilingue.

**Étape 6 : Teste l'API GraphQL**
```bash
curl -X POST -H 'Content-Type: application/json' \
  -d '{"query":"{ portfolios { nodes { title uri } } }"}' \
  http://localhost:8080/graphql
```
(`portfolios` = nom GraphQL pluriel défini dans le Pod.)

**Étape 7 : Développe ton Frontend Astro**

Le serveur de dev tourne déjà dans le conteneur `astro` : http://localhost:3000.

Exemple de page pour le CPT Portfolio :

```astro
---
// src/pages/portfolio/[slug].astro
import Base from '../../layouts/Base.astro';
import { wpQuery } from '../../lib/wordpress';

export async function getStaticPaths() {
  const data = await wpQuery<{ portfolios: { nodes: { slug: string }[] } }>(
    `{ portfolios(first: 100) { nodes { slug } } }`,
  );
  return data.portfolios.nodes.map(({ slug }) => ({ params: { slug } }));
}

const { slug } = Astro.params;
const { portfolio } = await wpQuery<{ portfolio: { title: string; content: string } }>(
  `query ($slug: ID!) { portfolio(id: $slug, idType: SLUG) { title content } }`,
  { slug },
);
---
<Base title={portfolio.title}>
  <h1>{portfolio.title}</h1>
  <div set:html={portfolio.content} />
</Base>
```

**Étape 8 : Déploie sur Hostinger**
1. Suis la [mise en place](#mise-en-place-une-fois) (une seule fois).
2. Pousse sur `main` : `git push origin main`.
3. Suis les déploiements dans l'onglet **Actions** de GitHub, puis dans hPanel.

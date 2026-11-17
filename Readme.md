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
│   │   ├── components/       # SEO (hreflang), LanguageSwitcher, HomePage
│   │   ├── layouts/Base.astro
│   │   ├── lib/
│   │   │   ├── wordpress.ts  # Requêtes WPGraphQL (langues, contenus) + preview
│   │   │   └── i18n.ts       # Langue des pages, hreflang, textes de l'interface
│   │   ├── pages/
│   │   │   ├── index.astro       # Accueil de la langue par défaut
│   │   │   ├── [lang]/index.astro # Accueil des autres langues (/fr/, /en/…)
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

Les sites locaux ont leur propre nom en `*.localhost` (un nom par site pour le gestionnaire de mots de passe). Ils passent par un aiguilleur Traefik partagé entre tous tes projets, dans `~/projects/local-proxy`. Il se démarre une seule fois, puis redémarre avec Docker :
```bash
cd ~/projects/local-proxy && docker compose up -d
```
L'aiguilleur a besoin du port 80, que l'Apache d'Ubuntu occupe s'il est installé : `sudo systemctl disable --now apache2`.

Puis, dans ce projet :
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
| Admin WordPress | [http://cms.headless.localhost/wp/wp-admin](http://cms.headless.localhost/wp/wp-admin) | `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` |
| API GraphQL | [http://cms.headless.localhost/graphql](http://cms.headless.localhost/graphql) | - |
| API REST | [http://cms.headless.localhost/wp-json](http://cms.headless.localhost/wp-json) | - |
| MySQL | `127.0.0.1:3307` | `DB_USER` / `DB_PASSWORD` |
| Astro (Dev) | [http://headless.localhost](http://headless.localhost) | - |

> MySQL est exposé sur le port **3307** de l'hôte (le 3306 est souvent déjà pris par un MySQL local).
> En secours, sans l'aiguilleur : WordPress répond aussi sur `localhost:8080` (redirigé vers son nom) et Astro sur `localhost:3000`.

## 🧪 Développement

### Backend (WordPress)
- Admin : http://cms.headless.localhost/wp/wp-admin
- API GraphQL : http://cms.headless.localhost/graphql (IDE GraphiQL dans l'admin : **GraphQL → GraphiQL IDE**)
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
- Dev server : http://headless.localhost (conteneur `astro`, ou `npm run dev` dans `frontend/`)
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

Tout est hébergé sur Hostinger (forfait Business), sur deux sites :

| Site | URL actuelle (domaine temporaire) | Type Hostinger | Branche déployée |
| --- | --- | --- | --- |
| WordPress (CMS + API) | https://evident-snow-eland-65.hostingersite.com | PHP 8.5, déploiement Git dans `public_html/app` | `deploy/backend` |
| Frontend Astro | https://nocturnal-orangered-turkey-22.hostingersite.com | Node.js 24, app `astro`, dossier `frontend` | `deploy/frontend` |

Admin WordPress : https://evident-snow-eland-65.hostingersite.com/wp/wp-admin

### Comment ça marche
À chaque push sur `main`, GitHub Actions (`.github/workflows/deploy.yml`) met à jour deux branches :
- **`deploy/backend`** contient `backend/` avec `vendor/`, WordPress et les plugins déjà installés par Composer. Une fois la branche à jour, le workflow appelle le webhook de déploiement Hostinger (secret GitHub `HOSTINGER_BACKEND_WEBHOOK`). Hostinger fait alors un `git pull` dans `public_html/app`, puis un `composer install`. Le `.env` de production et les uploads, non suivis par Git, restent en place.
- **`deploy/frontend`** est une copie de `main`. Hostinger le détecte, lance `npm run build` dans `frontend/` et redémarre le serveur Node.

Quand un contenu est publié dans WordPress, le mu-plugin `headless-webhook.php` déclenche le même workflow, qui ne reconstruit que le frontend. Il faut pour cela renseigner `GITHUB_REBUILD_REPO` et `GITHUB_REBUILD_TOKEN` dans le `.env` du serveur (voir plus bas).

### Organisation du site WordPress
```
public_html/
├── .htaccess        # Redirige tout vers app/web/ (copie : docs/hostinger/public_html.htaccess)
└── app/             # Clone de deploy/backend
    ├── .env         # Configuration de production (hors Git, à créer sur le serveur)
    ├── config/  vendor/  composer.json …
    └── web/         # Seul dossier servi (index.php, wp/, wp-content/)
```
Hostinger ne permet pas de faire pointer la racine d'un domaine temporaire vers un sous-dossier. C'est donc le `.htaccess` de `public_html` qui sert `app/web/`. `.env`, `config/`, `vendor/` et `composer.json` ne sont jamais servis.

> Le gestionnaire de fichiers et l'API Hostinger n'affichent pas les dossiers `vendor/`.
> Ils sont pourtant bien présents sur le serveur.

### `.env` de production
À créer dans `public_html/app/.env` à partir de `backend/.env.exemple` :
- `WP_ENV=production`, `WP_HOME` (URL du CMS), identifiants de la base Hostinger (`DB_HOST=localhost`) ;
- clés de sécurité et `JWT_SECRET` propres à la production ;
- `LSCACHE_OBJECT=false` (pas de Memcached sur l'hébergement mutualisé) ;
- `FRONTEND_URL` (URL du frontend, pour le bouton « Prévisualiser ») ;
- `GITHUB_REBUILD_REPO=julienchenel/headless-wordpress-astro-docker` et `GITHUB_REBUILD_TOKEN`, un jeton GitHub *fine-grained* limité au dépôt, avec la permission *Contents: write*.

### Changer de domaine
1. Dans hPanel, rattacher le domaine (ou des sous-domaines) aux deux sites.
2. Mettre à jour `WP_HOME` et `FRONTEND_URL` dans `public_html/app/.env`.
3. Mettre à jour `WP_URL` et `SITE_URL` dans `frontend/.env.production`, puis pousser sur `main`.

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

### Dans le frontend Astro
Les URLs suivent celles de Polylang : la langue par défaut est à la racine, les autres sous leur préfixe (`/fr/`, `/en/`).
- Chaque langue a son accueil, avec les derniers articles dans cette langue.
- `<html lang>` et `og:locale` suivent la langue du contenu (`fr-FR`, `en-GB`…).
- Les balises `<link rel="alternate" hreflang>` relient chaque page à ses traductions.
- Le sélecteur de langue mène à la traduction de la page, ou à l'accueil de la langue si la page n'est pas traduite.
- Les textes de l'interface (« Derniers articles »…) sont dans `src/lib/i18n.ts`. Ajoute une entrée pour toute nouvelle langue.

> Un contenu sans langue (créé avant l'activation de Polylang) n'apparaît sur aucun accueil.
> Assigne-lui une langue dans l'admin.

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
  curl -X POST -H 'Content-Type: application/json' -d '{"query":"{ generalSettings { title } }"}' http://cms.headless.localhost/graphql
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
- Admin : [http://cms.headless.localhost/wp/wp-admin](http://cms.headless.localhost/wp/wp-admin)
- Identifiant / mot de passe : `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` (dans `backend/.env`)
- Frontend (Astro) : http://headless.localhost

**Étape 5 : Configure Pods**
1. Va dans **Pods Admin → Add New**.
2. Crée un Custom Post Type (ex : Portfolio) et active **Show in GraphQL**.
3. Ajoute des champs : Repeater Field (ex : `gallery_images`), Flexible Content (ex : `content_sections`).
4. Active Polylang si tu veux du multilingue.

**Étape 6 : Teste l'API GraphQL**
```bash
curl -X POST -H 'Content-Type: application/json' \
  -d '{"query":"{ portfolios { nodes { title uri } } }"}' \
  http://cms.headless.localhost/graphql
```
(`portfolios` = nom GraphQL pluriel défini dans le Pod.)

**Étape 7 : Développe ton Frontend Astro**

Le serveur de dev tourne déjà dans le conteneur `astro` : http://headless.localhost.

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
1. Pousse sur `main` : `git push origin main` (voir [Déploiement sur Hostinger](#-déploiement-sur-hostinger)).
2. Le backend et le frontend se redéploient automatiquement.
3. Suis les déploiements dans l'onglet **Actions** de GitHub, puis dans hPanel.

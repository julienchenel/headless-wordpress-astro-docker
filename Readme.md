# 🚀 WordPress Headless + Pods + Headless Bridge + Astro (Docker)

Une stack **100% reproductible** pour créer des sites WordPress headless avec :
- **Backend** : WordPress (Solution Bedrick de roots.io) + Pods (CPT/Repeater) + Headless Bridge (API ultra-rapide)
- **Frontend** : Astro (SSG) avec SEO, preview, et URL rewriting
- **Performance** : OPcache + Memcached + LiteSpeed Cache
- **Déploiement** : GitHub Actions → Hostinger (Backend) + Cloudflare Pages (Frontend)

## ✅ Prérequis

- [Docker](https://www.docker.com/) (v20+)
- [Docker Compose](https://docs.docker.com/compose/) (v2+)
- [Git](https://git-scm.com/)
- Un compte [Hostinger](https://www.hostinger.fr/) (Business Web Hosting)
- Un compte [Cloudflare](https://www.cloudflare.com/) (pour le frontend)

## 📁 Structure du projet 

headless-wordpress-astro-docker/
├── backend/                  # WordPress + Bedrock
│   ├── composer.json         # Dépendances (Pods, Headless Bridge, etc.)
│   ├── .env                  # Variables d'environnement
│   ├── config/               # Config WordPress (Bedrock)
│   │   └── application.php   # Config PHP/WordPress
│   ├── web/                  # Racine WordPress
│   │   ├── wp-content/       # Plugins, thèmes, mu-plugins
│   │   │   ├── mu-plugins/   # Nos mu-plugins (webhook, preview, etc.)
│   │   │   │   ├── headless-webhook.php
│   │   │   │   ├── headless-preview.php
│   │   │   │   ├── headless-security.php
│   │   │   │   └── headless-optimize.php
│   │   │   └── plugins/      # Plugins à installer
│   │   └── .htaccess         # Config LiteSpeed/Nginx
│   └── scripts/              # Scripts d'initialisation
│       └── setup.php         # Auto-configuration WordPress
│
├── frontend/                 # Astro (SSG)
│   ├── src/                  # Code source
│   │   ├── components/       # SEO.astro, FlexibleContent.astro, etc.
│   │   ├── pages/            # Pages Astro + API (preview)
│   │   └── utils/            # rewriteUrls.ts, etc.
│   ├── astro.config.mjs      # Config Astro
│   ├── package.json          # Dépendances Node.js
│   └── .env.example          # Variables frontend
│
├── docker/                   # Configurations Docker
│   ├── nginx/                # Config Nginx (ou Apache)
│   │   └── conf.d/
│   │       └── wordpress.conf
│   ├── php/                  # Config PHP (php.ini, OPcache, etc.)
│   │   ├── php.ini
│   │   └── conf.d/
│   │       ├── opcache.ini
│   │       └── memcached.ini
│   └── mysql/                # Config MySQL
│       └── my.cnf
│
├── .github/                  # GitHub Actions
│   └── workflows/
│       └── deploy.yml        # Déploiement Hostinger + Cloudflare
│
├── docker-compose.yml        # Orchestration Docker
├── Dockerfile.backend       # Image WordPress
├── Dockerfile.frontend      # Image Astro
├── .env.example              # Variables globales
└── README.md                 # Documentation

## 🛠️ Installation

### 1. Cloner le dépôt
```bash
git clone https://github.com/ton-utilisateur/headless-wordpress-astro-docker.git
cd headless-wordpress-astro-docker
```
2. Configurer les variables d'environnement
Copie les fichiers .env.example et adapte-les :
```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
cp .env.example .env
```
Édite les fichiers .env avec tes propres valeurs (mot de passe DB, JWT, etc.).

3. Démarrer les conteneurs Docker
```bash
docker-compose up -d --build
```
⚠️ Première exécution : Le conteneur WordPress va :

Installer WordPress
Configurer les plugins (Pods, Headless Bridge, etc.)
Créer un utilisateur admin (mot de passe = DB_PASSWORD dans .env)

4. Accéder aux services

| Service | URL | Identifiants |
| --- | --- | --- |
| WordPress | [http://localhost:8080](http://localhost:8080) | admin / `DB_PASSWORD` |
| PhpMyAdmin | [http://localhost:8081](http://localhost:8081) | root / `DB_ROOT_PASSWORD` |
| Astro (Dev) | [http://localhost:3000](http://localhost:3000) | - |
| Frontend (Prod) | [http://localhost](http://localhost) | - |

-
🧪 Développement

Backend (WordPress)
Accès admin :  http://localhost:8080/wp-admin
API Headless Bridge :  http://localhost:8080/wp-json/headless-bridge/v1
Webhook : Déclenche un rebuild Cloudflare à chaque save_post (configuré dans headless-webhook.php)

Frontend (Astro)
Dev server : npm run dev ( http://localhost:3000)
Build : ```bash npm run build (génère /dist) ```
Preview : ```bash npm run preview (pour tester le build)```

📦 Configuration des Plugins
Pods
Va dans Pods → Add New.
Crée un Custom Post Type (ex: Portfolio).
Ajoute des champs (dont Repeater ou Flexible Content).
Active Polylang pour la traduction (désactive la synchronisation des métas).
Headless Bridge
Déjà configuré pour :
Supporter Pods (pods_support: true)
Utiliser Memcached (cache_driver: memcached)
Batch size de 50 (batch_size: 50)
LiteSpeed Cache
Active Object Cache (Memcached) dans les paramètres.

🔄 Workflow de Déploiement

1. Configurer les secrets GitHub
Va dans Settings → Secrets → Actions et ajoute :

| Secret | Valeur | Description |
| --- | --- | --- |
| `HOSTINGER_FTP_SERVER` | `ftp.ton-hebergeur.com` | Serveur FTP Hostinger |
| `HOSTINGER_FTP_USER` | `ton_utilisateur_ftp` | Utilisateur FTP |
| `HOSTINGER_FTP_PASSWORD` | `ton_mot_de_passe_ftp` | Mot de passe FTP |
| `DB_NAME` | `wp_headless` | Nom de la base de données |
| `DB_USER` | `wp_user` | Utilisateur DB |
| `DB_PASSWORD` | `ton_mot_de_passe_db` | Mot de passe DB |
| `DB_HOST` | `localhost` | Hôte DB (Hostinger utilise `localhost`) |
| `JWT_SECRET` | `ta_clef_secrete` | Clé pour les tokens JWT (preview) |
| `CLOUDFLARE_BUILD_HOOK` | `https://api.cloudflare.com/...` | Webhook Cloudflare Pages |
| `CLOUDFLARE_ACCOUNT_ID` | `ton_id_compte` | ID du compte Cloudflare |
| `CLOUDFLARE_PROJECT_NAME` | `ton-projet` | Nom du projet Cloudflare Pages |
| `CLOUDFLARE_API_TOKEN` | `ton_token_api` | Token API Cloudflare |

2. Pousser sur main
```bash
git add .
git commit -m "feat: initial setup"
git push origin main
```

✅ GitHub Actions va automatiquement déployer :

Le backend sur Hostinger (via FTP)
Le frontend sur Cloudflare Pages

🔧 Commandes Utiles

| Commande | Description |
| --- | --- |
| `docker-compose up -d` | Démarrer tous les conteneurs |
| `docker-compose down` | Arrêter tous les conteneurs |
| `docker-compose down -v` | Arrêter + supprimer les volumes (⚠️ **attention aux données**) |
| `docker-compose logs -f wordpress` | Voir les logs WordPress |
| `docker-compose logs -f astro` | Voir les logs Astro |
| `docker exec -it wp_headless wp cli info` | Info WP-CLI |
| `docker exec -it wp_headless wp plugin list` | Lister les plugins |
| `docker exec -it wp_mysql mysql -u root -p` | Accès MySQL (mot de passe = `DB_ROOT_PASSWORD`) |


📊 Performances

| Optimisation | Statut | Description |
| --- | --- | --- |
| **OPcache** | ✅ | Cache PHP bytecode |
| **Memcached** | ✅ | Cache objet (via LiteSpeed Cache) |
| **Headless Bridge** | ✅ | API ultra-rapide (10x plus rapide que WPGraphQL) |
| **Batch Queries** | ✅ | Évite les N+1 queries |
| **CDN Cloudflare** | ✅ | Cache statique + ISR |
| **Gzip** | ✅ | Compression HTTP |
| **Cache Browser** | ✅ | Cache des assets statiques (1 an) |

Benchmark (Local)

| Requête | Temps (ms) |
| --- | --- |
| Page simple | **< 50ms** |
| Page avec Repeater (10 items) | **< 100ms** |
| API Headless Bridge (batch) | **< 20ms** |

🌍 Multilingue (Polylang + Pods)

Configuration
Installe Polylang (déjà inclus dans composer.json).
Va dans Langues → Ajouter une langue (ex: Français, Anglais).
Dans Pods, pour chaque CPT :
Désactive "Synchronize with Polylang" (pour éviter les conflits).
Crée des champs séparés par langue (ex: title_fr, title_en).

Utilise Headless Bridge pour récupérer les données dans la bonne langue :

```js
// Dans Astro
const { data } = await fetch(
  `${import.meta.env.VITE_WP_API_URL}/data?lang=fr`
).then(res => res.json());
```

🔍 Dépannage
Problème : WordPress ne se connecte pas à MySQL
Solution :

Vérifie que le conteneur mysql est bien démarré (docker-compose ps).
Attends 30 secondes après le démarrage (MySQL met du temps à initialiser).
Vérifie les logs : ```bash docker-compose logs mysql```.
Problème : Headless Bridge ne répond pas
Solution :

Vérifie que le plugin est activé (docker exec -it wp_headless wp plugin list).
Vérifie l'URL de l'API : [API de Wordpress (Bedrock de Roots.io) Headless](http://localhost:8080/wp-json/headless-bridge/v1/data).
Vérifie que Pods Support est activé dans les paramètres de Headless Bridge.
Problème : Astro ne récupère pas les données
Solution :

Vérifie que ```bash VITE_WP_API_URL ``` est correct dans frontend/.env.
Vérifie que le conteneur wordpress est accessible depuis astro (via le réseau headless_network).
Teste l'API directement : curl http://localhost:8080/wp-json/headless-bridge/v1/data.
Problème : Les images ne s'affichent pas
Solution :

Vérifie que les URLs sont réécrites (rewriteUrls.ts).
Vérifie que le volume wp_uploads est bien monté.
Si tu utilises Cloudflare, configure un CNAME pour wp-content/uploads vers ton backend.

## **📚 Ressources**

- [Headless Bridge Documentation](https://www.headless-bridge.com/docs/)
- [Pods Documentation](https://pods.io/docs/)
- [Astro Documentation](https://docs.astro.build/)
- [Cloudflare Pages](https://developers.cloudflare.com/pages/)
- [Hostinger FTP](https://www.hostinger.fr/tutoriels/comment-se-connecter-a-ftp)


🎯 Comment Démarrer ?

Étape 1 : Clone ce dépôt sur ton PC

```bash
git clone https://github.com/ton-utilisateur/headless-wordpress-astro-docker.git
cd headless-wordpress-astro-docker
```

Étape 2 : Configure les .env

```bash
# Copie les exemples
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
cp .env.example .env
```

```bash
# Édite les fichiers avec tes valeurs (DB, JWT, etc.)
nano backend/.env  # ou utilise VS Code / Notepad++
```
Étape 3 : Lance Docker
```bash
docker-compose up -d --build
```

⏳ Attends 2-3 minutes le temps que WordPress s'installe.

Étape 4 : Accède à WordPress
Backend :  [Administration du Backend Wordpress Headless](http://localhost:8080/wp-admin
Identifiant : admin
Mot de passe : celui que tu as mis dans ```bash DB_PASSWORD``` (dans backend/.env)
Frontend (Astro) :  ```bash http://localhost:3000```

Étape 5 : Configure Pods
Va dans Pods → Add New.
Crée un Custom Post Type (ex: Portfolio).
Ajoute des champs :
Repeater Field (ex: ```bash gallery_images ```)
Flexible Content (ex: ```bash content_sections```)
Active Polylang si tu veux du multilingue.

Étape 6 : Teste l'API Headless Bridge
URL :  ```bash http://localhost:8080/wp-json/headless-bridge/v1/data```
Exemple de requête :
```bash curl http://localhost:8080/wp-json/headless-bridge/v1/data?post_type=portfolio ```

Étape 7 : Développe ton Frontend Astro
Dev server : ```bash cd frontend && npm run dev ```

Crée une page :

```js
// src/pages/portfolio/[...slug].astro
import { getEntry } from '@headless-bridge/astro';

const { slug } = Astro.params;
const post = await getEntry('portfolio', slug);


<html>
  <head>
    <SEO data={post} />
  </head>
  <body>
    <h1>{post.title}</h1>
    <div set:html={post.content} />
  </body>
</html>
```
Étape 8 : Déploie sur Hostinger + Cloudflare
Configure les secrets GitHub (voir section Workflow de Déploiement).
Pousse sur main :

```bash
git add .
git commit -m "feat: initial setup"
git push origin main
```

Vérifie le déploiement :
Backend :  https://ton-backend.com (A définir)
Frontend : https://ton-frontend.pages.dev (A définir)

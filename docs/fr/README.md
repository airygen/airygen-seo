# Airygen SEO

Un [plugin SEO WordPress](https://www.airygen.com/fr) modulaire combinant contrôles SEO on-page, données structurées, SEO technique, flux de travail de liens internes et sortie prête pour l'IA — tout en gardant l'expérience d'édition dans WordPress.

## Prérequis système

- WordPress 6.3+
- PHP 8.1+

## Fonctionnalités

### SEO on-page
- Titre, description, URL canonique et directives robots personnalisés par article/page
- Vérification de largeur en pixels et calculateur de score SEO
- Analyse de phrase-clé cible

### Données structurées
- Schema JSON-LD pour articles, site web, organisation, auteurs et fil d'Ariane
- Schema de produits WooCommerce

### Social & Meta
- Métadonnées Open Graph (Facebook) et cartes Twitter/X
- Signatures RSS et vérification de site (Google, Bing, Yandex)

### SEO technique
- Génération et gestion du sitemap XML
- Contrôle de robots.txt et des directives robots meta
- Gestion des redirections (301 / 302 / 410) et journalisation des 404
- Surveillance des liens brisés et indexation instantanée (Google Indexing API)
- Liens alternatifs Hreflang

### Contenu & UX
- Fil d'Ariane (HTML + Schema)
- Bloc table des matières
- Bloc articles connexes
- SEO des images — injection automatique de `alt`/`title` à l'exécution avec templates personnalisables
- Gestionnaire de snippets de code (injection dans head / body / footer)

### Liens internes
- Comptage et suivi de la distribution des liens
- Suggestions de liens internes
- Visualisation de clusters thématiques

### Extensions
- SEO local, SEO auteur, SEO taxonomies
- SEO WooCommerce
- Intégrations de notifications

### Utilitaires IA
- `llms.txt` — index du site lisible par les agents IA
- Markdown for Agents — export du contenu en Markdown pour les LLMs

## Développement

### Prérequis

- Docker
- pnpm 9+

### Démarrer l'environnement de développement

```bash
make dev
```

Lance les conteneurs Docker, installe WordPress, active le plugin et démarre les serveurs de développement frontend. Accédez à `http://localhost:9000` (admin : `admin` / `admin`).

> **Base de données vierge :** Par défaut, le conteneur MySQL importe les données depuis `.docker/wp/schema/backup.sql`. Pour démarrer avec une installation WordPress propre, commentez cette ligne dans `docker-compose.yml` avant d'exécuter `make up` :
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### Commandes principales

| Commande | Description |
|---|---|
| `make up` / `make down` | Démarrer / arrêter les conteneurs Docker |
| `make wp.init-dev-site` | Installer WordPress + activer plugin + WooCommerce |
| `make dev.admin` | Serveur de développement SPA admin |
| `make dev.block-editor` | Serveur de développement de l'éditeur de blocs |
| `make dev.classic-editor` | Serveur de développement de l'éditeur classique |
| `make tests` | Exécuter les tests d'intégration PHPUnit |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | Analyse statique PHP |
| `make lint` | ESLint |
| `make lint.types` | Vérification des types TypeScript |
| `make i18n.check` | Régénérer POT et synchroniser les fichiers PO |
| `make i18n.build` | Générer les fichiers de traduction MO et JSON |

### Stack technique

- **PHP** 8.1+ — autoload PSR-4, espace de noms `Airygen\`, architecture modulaire orientée fonctionnalités
- **React + TypeScript** 18 / 5 — trois points d'entrée : SPA admin, éditeur de blocs, éditeur classique
- **Build** — `@wordpress/scripts` (webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **Tests** — PHPUnit 8, `wp-phpunit`, environnement WordPress basé sur Docker

## Architecture

Le code source se trouve dans `src/Modules/<Feature>`, chaque module étant divisé en couches `Admin/`, `Public/`, `Runtime/` et `Domain/`. L'infrastructure partagée se trouve dans `src/Admin`, `src/Public` et `src/Support`. Consultez `guidelines/structure.md` pour le guide complet de structure.

## Version payante

Un plugin payant distinct avec des fonctionnalités d'analyse IA avancées est disponible sur **[airygen.com](https://www.airygen.com/fr)**. Il nécessite que ce plugin gratuit soit installé et actif.

## Licence

GPLv3 ou ultérieure

---

Airygen SEO © TerryL.in / Airygen Team

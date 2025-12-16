# Airygen SEO

A modular [WordPress SEO plugin](https://www.airygen.com/en) combining on-page SEO controls, structured data, technical SEO, internal link workflows, and AI-ready output in one plugin — while keeping the editing experience inside WordPress.

[繁體中文](docs/zh/README.md) · [简体中文](docs/zh-Hans/README.md) · [日本語](docs/ja/README.md) · [한국어](docs/ko/README.md) · [Español](docs/es/README.md) · [Português](docs/pt/README.md) · [Français](docs/fr/README.md) · [Deutsch](docs/de/README.md) · [Italiano](docs/it/README.md) · [Русский](docs/ru/README.md)

## Requirements

- WordPress 6.3+
- PHP 8.1+

## Features

### On-page SEO
- Custom title, description, canonical URL, and robots directives per post/page
- Pixel-width checks and SEO score calculator
- Focus keyphrase analysis

### Structured Data
- JSON-LD schema for articles, website, organization, authors, and breadcrumbs
- WooCommerce product schema

### Social & Meta
- Open Graph (Facebook) and Twitter/X card metadata
- RSS feed signatures and site verification (Google, Bing, Yandex)

### Technical SEO
- XML sitemap generation and management
- robots.txt and robots meta directive controls
- Redirect management (301 / 302 / 410) and 404 logging
- Broken link monitoring and instant indexing (Google Indexing API)
- Hreflang alternate language links

### Content & UX
- Breadcrumbs (HTML + Schema)
- Table of contents block
- Related posts block
- Image SEO — runtime `alt`/`title` injection with customizable templates
- Code snippet manager (head / body / footer injection)

### Internal Links
- Link counter and distribution tracking
- Internal link suggestions
- Topic cluster visualization

### Extensions
- Local SEO, Author SEO, Taxonomy SEO
- WooCommerce SEO
- Notification integrations

### AI Utilities
- `llms.txt` — agent-readable site index
- Markdown for Agents — export content as Markdown for LLM consumption

## Development

### Prerequisites

- Docker
- pnpm 9+

### Start the dev environment

```bash
make dev
```

This starts Docker containers, installs WordPress, activates the plugin, and launches the frontend dev servers. Visit `http://localhost:9000`(admin: `admin` / `admin`).

> **Fresh database:** By default the MySQL container seeds from `.docker/wp/schema/backup.sql`. To start with a clean WordPress install instead, comment out that line in `docker-compose.yml` before running `make up`:
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### Common commands

| Command | Description |
|---|---|
| `make up` / `make down` | Start / stop Docker containers |
| `make wp.init-dev-site` | Install WordPress + activate plugin + WooCommerce |
| `make dev.admin` | Admin SPA dev server |
| `make dev.block-editor` | Block editor dev server |
| `make dev.classic-editor` | Classic editor dev server |
| `make tests` | Run PHPUnit integration tests |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | PHP static analysis |
| `make lint` | ESLint |
| `make lint.types` | TypeScript type checking |
| `make i18n.check` | Rebuild POT and sync PO files |
| `make i18n.build` | Generate MO and JSON translation artifacts |

### Tech stack

- **PHP** 8.1+ — PSR-4 autoloading under `Airygen\`, feature-first module layout
- **React + TypeScript** 18 / 5 — three entry points: admin SPA, block editor, classic editor
- **Build** — `@wordpress/scripts` (webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **Testing** — PHPUnit 8, `wp-phpunit`, Docker-based WordPress environment

## Architecture

Source code lives under `src/Modules/<Feature>` with each module split into `Admin/`, `Public/`, `Runtime/`, and `Domain/` layers. Shared infrastructure sits in `src/Admin`, `src/Public`, and `src/Support`. See `guidelines/structure.md` for the full layout guide.

## Paid Version

A separate paid plugin with advanced AI analysis features is available at **[airygen.com](https://www.airygen.com/en)**. It requires this free plugin to be installed and active.

## License

GPLv3 or later

---

Airygen SEO © TerryL.in / Airygen Team

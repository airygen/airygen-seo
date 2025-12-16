# Airygen SEO Code Structure

This document describes the current repository layout and bootstrap flow so contributors can keep the codebase aligned with its feature-first architecture.

## Top-level layout

- `airygen-seo.php` — plugin entrypoint. It loads the Composer autoloader, boots the bundled Action Scheduler when available, defines plugin constants, registers activation hooks, and calls `Airygen\Launcher::boot()` on `plugins_loaded`.
- `config/` — declarative configuration such as `routes.php` and `score_rules.php`.
- `src/` — PHP application code under the `Airygen\` namespace.
- `packages/` — React and TypeScript sources compiled by `@wordpress/scripts`.
- `build/` — generated JavaScript, CSS, and `.asset.php` files produced by the frontend build.
- `tests/` — PHPUnit and WordPress integration tests.
- `assets/`, `resources/`, `languages/` — static assets, templates or demo resources, and translation files.

## PHP application layout

### `src/Launcher.php`

`Launcher` is the main bootstrap coordinator. It loads translations, registers shared infrastructure, and dispatches to the correct hook registry for the current request context.

### `src/Admin/`

Admin-only infrastructure lives here. Typical responsibilities include:

- settings and dashboard bootstrapping
- admin menus and assets
- REST controllers and route handlers used by wp-admin workflows
- migrations and admin-side integrations
- post list columns and editor integrations
- the admin hook registry in `src/Admin/Hooks.php`

Keep shared admin infrastructure here instead of scattering it across modules.

### `src/Public/`

Frontend-only integrations live here, including the public hook registry in `src/Public/Hooks.php` and shared public helpers such as the admin bar integration.

### `src/Modules/`

Each product feature lives in its own module directory, for example `src/Modules/LinkCounter` or `src/Modules/Sitemap`.

Modules can contain only the layers they need. Common subdirectories include:

- `Admin/` — wp-admin UI and WordPress integrations for the feature
- `Public/` — frontend behavior
- `Runtime/` — hooks or jobs that can run regardless of request context
- `Domain/` — pure business logic with no direct WordPress API calls

Use module-local structure when the code is truly feature-specific. Keep cross-feature infrastructure in `src/Admin`, `src/Public`, or `src/Support`.

### `src/Support/`

Shared infrastructure and reusable helpers live here. Current areas include routing, metadata registration, updates, templating, debugging, database support, request rules, and utility helpers.

### `src/Constants.php`

Project-wide constants live here, including custom metadata keys and other shared identifiers that should not be hardcoded throughout the codebase.

## Frontend package layout

- `packages/admin/` — the admin React SPA used for dashboard and settings screens
- `packages/block-editor/` — Gutenberg-specific editor bundle
- `packages/classic-editor/` — Classic Editor bundle
- `packages/shared/` — shared TypeScript utilities, phrase collections, and cross-bundle helpers

The current build outputs are:

- `build/admin/airygen-app.js`
- `build/block-editor/airygen-editor.js`
- `build/classic-editor/airygen-editor.js`

Each bundle also produces matching `.asset.php` metadata and CSS outputs.

## Test layout

- `tests/Admin/` — WordPress integration tests for admin behavior, assets, subscriptions, post list columns, and REST endpoints
- `tests/Admin/Rest/` — route coverage for endpoints declared in `config/routes.php`
- `tests/Modules/` — module-specific tests, including pure domain tests and feature integration coverage
- `tests/Support/` — shared fixtures and helpers
- `tests/BaseTestCase.php` — shared WordPress integration test base class

## Bootstrap flow

1. `airygen-seo.php` loads the autoloader, initializes Action Scheduler if bundled, defines plugin constants, and instantiates `Airygen\Admin\Activation`.
2. On `plugins_loaded`, it calls `Airygen\Launcher::boot()`.
3. `Launcher::boot()` loads translations, registers script translation path fixes, registers updates, registers routes through the routing support layer, wires runtime hooks, and registers shared post meta.
4. `Launcher` then decides whether to boot `Airygen\Admin\Hooks` or `Airygen\Public\Hooks` based on the current environment.
5. The selected hook registry iterates feature hook classes and calls their static `register()` methods.
6. Public hooks additionally respect module settings for features that can be toggled off.

## Structural conventions

- Keep the feature-first module layout intact.
- Domain classes must remain free of direct WordPress API calls.
- Register REST endpoints through `config/routes.php` and the routing support classes rather than calling `register_rest_route()` directly.
- Put hooks that must run in admin, public, REST, cron, or CLI contexts inside the relevant module's `Runtime/` directory.
- Keep custom metadata keys centralized in `src/Constants.php`.
- Treat files under `build/` as generated artifacts.

Update this document whenever the repository layout or bootstrap flow changes in a meaningful way.

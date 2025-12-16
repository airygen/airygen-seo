# AGENTS.md — Airygen SEO Project Guidance

This file contains repository-specific guidance for coding agents working in Airygen SEO.

## Core principles

- Prefer the current architecture over legacy compatibility unless backward compatibility is explicitly requested.
- Keep project-facing documentation and newly added guidance in English.
- Make precise changes that fit existing patterns instead of introducing parallel implementations.
- When bootstrap flow or directory structure changes, update `guidelines/structure.md`.
- When testing workflow changes, update `guidelines/testing.md`.

## Architecture rules

- Keep the feature-first layout under `src/Modules/<Feature>`.
- Keep shared infrastructure in `src/Admin`, `src/Public`, or `src/Support` when it is not feature-specific.
- Domain classes must not call WordPress APIs directly.
- Hooks or jobs that need to run across admin, frontend, REST, cron, or CLI contexts belong in a module `Runtime/` directory.
- Define custom metadata keys in `src/Constants.php` and reference the constants instead of hardcoding `'_airygen_*'` strings in feature code.
- Preserve existing `_airygen_*` metadata keys unless the task explicitly includes a migration.

## REST and testing rules

- Register new REST endpoints through `config/routes.php` and `Airygen\Support\Routing\Route`.
- Do not call `register_rest_route()` directly in feature code.
- State-changing REST endpoints must enforce the same capability and nonce expectations used by the existing route layer.
- Any change to `config/routes.php` or any new REST endpoint must be covered in `tests/Admin/Rest`.
- Before writing or changing tests, read `guidelines/testing.md`.
- Use `make tests` as the canonical PHPUnit entrypoint because the suite depends on the Docker-based WordPress environment.

## Localization rules

- Keep source strings clear and reusable.
- If a translatable string needs HTML such as `<code>` or links, build the final string with `sprintf()` placeholders instead of embedding markup directly inside the translation string.
- Before adding a new string, check whether an existing shared format string can be reused with placeholders for module names, tokens, or snippet types.
- Treat a module name as a proper noun only when the sentence clearly refers to the module itself. Generic UI terms such as "breadcrumb link" or "breadcrumb separator" should stay generic.

## Editor and admin UI rules

- Load `packages/block-editor/*` assets only in Gutenberg contexts.
- Keep Classic Editor assets separate from block editor assets.
- For Gutenberg sidebar integrations, prefer `PluginSidebar` and SlotFill-based patterns instead of injecting UI into unrelated core panels.
- Prefer `@wordpress/components` and core data hooks over custom UI primitives when extending editor interfaces.
- Keep shared block editor logic in the existing `packages/block-editor` support files such as `config.ts`, `types.ts`, and `hooks/` unless there is a better established pattern nearby.
- Admin styling is driven by Tailwind from `packages/admin/styles/tailwind.css`; extend existing component-layer utilities or Tailwind config instead of adding one-off CSS files when possible.
- Scope admin typography and overrides to Airygen-owned roots such as `#airygen-root` or `#airygen-editor-root`.
- When adding or changing interactive UI, add stable `data-airygen-e2e` locators instead of relying on translated text or DOM order.
- Reuse the existing `airygen-setting-card__*` wrapper conventions and current admin layout patterns rather than inventing new card structures for similar settings screens.

## Build and validation rules

- `pnpm build` should regenerate the admin, block editor, and classic editor bundles under `build/`, along with their `.asset.php` files.
- Use the existing repository commands for validation, including `make phpcs`, `make phpstan`, `make lint`, `make lint.types`, and `make tests`, according to the surfaces you changed.
- Do not hand-edit generated build artifacts unless the workflow for the task explicitly requires committed build outputs.
- In PHP, avoid the short ternary operator (`?:`); use a full ternary or null coalescing for clarity and PHPCS compliance.

## Background processing guidance

- Follow the existing Action Scheduler and runtime hook patterns already used by background-capable modules such as Link Counter, Link Suggestions, Broken Link Checker, and Instant Indexing.
- Keep background workflows feature-local unless the code is genuinely shared infrastructure.

## Maintenance note

Keep this file focused on durable repository guidance. Remove stale feature-specific migration notes instead of preserving them after the implementation has settled.

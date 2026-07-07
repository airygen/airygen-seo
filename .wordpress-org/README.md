# WordPress.org SVN assets

Source-of-truth for the plugin's WordPress.org **`/assets`** images (icon, banner,
screenshots). These live in SVN only — they are NOT bundled into the plugin zip, and
are unrelated to the runtime `assets/` directory at the repo root. This `README.md` is
an internal index and is never published to SVN.

See: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

## Layout

Screenshots are organised by **locale subdirectory** for easy review; the deploy
workflow flattens them into the flat filenames WordPress.org expects.

```
.wordpress-org/
├── README.md                 (this file — not deployed)
├── icon-256x256.png          (root: non-localized, deployed as-is)
├── banner-772x250.png        (root: non-localized, deployed as-is)
├── banner-1544x500.png       (root: non-localized, deployed as-is)
├── en/screenshot-1.png …     (default English → screenshot-N.png)
├── zh_TW/screenshot-1.png …  (→ screenshot-N-zh_TW.png)
├── ja/screenshot-1.png …     (→ screenshot-N-ja.png)
└── <locale>/screenshot-N.png (→ screenshot-N-<locale>.png)
```

**Deploy flattening** (`.github/workflows/deploy-assets.yml`):

| Repo path                          | SVN `/assets` filename        |
| ---------------------------------- | ----------------------------- |
| `en/screenshot-N.png`              | `screenshot-N.png` (default)  |
| `<locale>/screenshot-N.png`        | `screenshot-N-<locale>.png`   |
| root `icon-*.png` / `banner-*.png` | same name, as-is              |
| `README.md`                        | *(skipped)*                   |

The default (no-suffix) screenshots come from the `en/` subdirectory; WordPress.org
shows a visitor the localized asset for their locale and falls back to the default
otherwise.

## Icon / banner

| Purpose            | Filename (repo root)               | Size       |
| ------------------ | ---------------------------------- | ---------- |
| Plugin icon        | `icon-256x256.png`                 | 256 × 256  |
| Header banner      | `banner-772x250.png`               | 772 × 250  |
| Header banner (2x) | `banner-1544x500.png`              | 1544 × 500 |

Brand/design artifacts (not screenshots), deployed to SVN `/assets` as-is.

## Screenshot → module map

Each `screenshot-N` (identical across locales) maps to one Airygen SEO screen. Captions
come from the `== Screenshots ==` section in `readme.txt`, matched by number `N`. All
shots are 1920×1080, captured from post 50 (tuned to a green content score).

| N | Module / screen        | Admin location                                    | What it shows |
| - | ---------------------- | ------------------------------------------------- | ------------- |
| 1 | Dashboard              | Airygen SEO → Dashboard                            | Module overview grid; enable/disable each feature |
| 2 | Content Score (editor) | Post editor → Airygen SEO sidebar → Content Score | In-editor SEO score (green) with the pass/fail rule checklist expanded |
| 3 | Topic Cluster          | Airygen SEO → Topic Cluster → Mind map            | Pillar / cluster / support relationship mind map |
| 4 | Score Calculator       | Airygen SEO → Settings → Score Calculator → Rules | Per-rule weight sliders for the content score engine |
| 5 | On-Page SEO            | Airygen SEO → Settings → On-Page SEO              | Meta tag output toggles + token-based title/description templates |
| 6 | Schema Markup          | Airygen SEO → Settings → Schema Markup → Preview   | Live JSON-LD structured-data preview |
| 7 | XML Sitemap            | Airygen SEO → Settings → Sitemap                  | Post-type / taxonomy scope and pagination |
| 8 | Robots Control         | Airygen SEO → Settings → Robots Control            | Default robots meta directives + robots.txt additions |

## Locales

| Subdir  | WP locale | Dev subsite | SVN suffix        |
| ------- | --------- | ----------- | ----------------- |
| `en`    | en_US     | `/en/`      | *(none, default)* |
| `zh_TW` | zh_TW     | `/` (main)  | `-zh_TW`          |
| `ja`    | ja        | `/ja/`      | `-ja`             |
| `ko_KR` | ko_KR     | `/ko/`      | `-ko_KR`          |
| `ru_RU` | ru_RU     | `/ru/`      | `-ru_RU`          |
| `pt_PT` | pt_PT     | `/pt/`      | `-pt_PT`          |
| `fr_FR` | fr_FR     | `/fr/`      | `-fr_FR`          |
| `de_DE` | de_DE     | `/de/`      | `-de_DE`          |
| `it_IT` | it_IT     | `/it/`      | `-it_IT`          |
| `es_ES` | es_ES     | `/es/`      | `-es_ES`          |

10 locales × 8 screenshots = 80 files.

## Regenerating

Screenshots are produced by the Playwright recorder (`scripts/admin-screenshots/record.mjs`)
against the multisite dev environment (`make up && make wp.init-dev-site`). Each subsite
is shot while logged in as its locale-matched admin test user, so both the WordPress core
chrome and the plugin UI render in that language, then placed under `<locale>/screenshot-N.png`.
The host needs CJK fonts installed (`fonts-noto-cjk`) for Chinese/Japanese/Korean glyphs.

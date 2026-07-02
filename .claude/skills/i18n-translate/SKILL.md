---
name: i18n-translate
description: Fully automated agent-quality translation pipeline for the airygen-seo plugin. Use when the user asks to update translations, translate new strings, rebuild language packs, add a new locale, or mentions i18n/翻譯/語言包/多國語言. Replaces the old Google machine-translation step with subagent translation.
---

# Airygen SEO i18n Agent Translation Pipeline

Run the whole pipeline end to end without asking for confirmation between steps.
Docker must be running for step 1 and step 6 (`docker compose ps` to verify; `make up` if not).

## Pipeline

### 1. Extract strings and sync PO files

```bash
make i18n.check
```

Rebuilds `languages/airygen-seo.pot` from source (runs `pnpm run build` first) and syncs
new/changed msgids into every `languages/airygen-seo-*.po`.

New locale? Add it to `SUPPORTED_LOCALES` in `scripts/create_po_locales.sh`, then run
`make i18n.new` before continuing.

### 2. Export only untranslated entries

```bash
make i18n.po2json.untranslated
```

Writes `languages/untranslated/<locale>.json` containing ONLY entries with empty msgstr.
The script prints an entry count per locale. Locales with `0 entries` need no work —
skip them in step 3.

### 3. Translate with subagents (one per locale, run concurrently)

For every non-empty `languages/untranslated/<locale>.json`, spawn a subagent
(general-purpose, in parallel) whose task is: read the file, fill in every empty
value with a translation of its key, write the file back. Each subagent prompt MUST
include the translation rules below and the target locale. If a file has more than
~300 entries, tell the subagent to work through the file in sections but still
complete the entire file.

#### Translation rules (copy into every subagent prompt)

- Source strings are English WordPress admin UI text for an SEO plugin.
- Fill every empty `""` value. Never modify keys. Never add or remove keys. Keep valid JSON (UTF-8, escaped quotes).
- Preserve placeholders EXACTLY as in the key, same count: `%s`, `%d`, `%1$s`, `%2$s`, `%%`, `{token}`, `${var}`, and HTML tags like `<code>`, `<strong>`, `<a href="...">`. Reordering positional placeholders (`%1$s`) to fit grammar is allowed; changing or dropping them is not.
- Do NOT translate these product/brand/feature terms (keep verbatim): Airygen, Google, WordPress, JSON-LD, SEO, API, URL, URLs, Markdown, Topic Cluster, Table of Contents, Related Posts, Local SEO, Schema Markup, Site Verification, Code Snippets, Link Suggestions, Link Counter, Content Blueprint, Article Builder, SERP CTR Booster, Instant Indexing, Broken Link Checker, Markdown for Agents, LLMs.txt, Robots Meta, On-Page SEO, Social Media Tags, WooCommerce SEO, Author SEO, Taxonomy SEO, Image SEO, Sitewide SEO, Score Calculator, Daily Digest, Microsoft Teams, Telegram, Discord, Elementor.
- Follow each locale's official WordPress core translation conventions and tone:
  formal/polite register (ja: です/ます体; de: Sie form; fr: vous), native punctuation
  (e.g. 「」 vs "" where conventional), and standard WP glossary terms for common UI
  words (Settings, Post, Page, Plugin, Dashboard...).
- Fixed glossary overrides:
  - vi: Meta tag → Thẻ meta; Schema Markup → Đánh dấu dữ liệu có cấu trúc; Structured Data → Dữ liệu có cấu trúc
  - id_ID: Meta tag → Tag meta; Schema Markup → Markup data terstruktur; Structured Data → Data terstruktur
  - ur: Meta tag → میٹا ٹیگ; Schema Markup → اسٹرکچرڈ ڈیٹا مارک اپ; Structured Data → اسٹرکچرڈ ڈیٹا
  - hi_IN: Meta tag → मेटा टैग; Schema Markup → स्ट्रक्चर्ड डेटा मार्कअप; Structured Data → संरचित डेटा
  - bn_BD: Meta tag → মেটা ট্যাগ; Schema Markup → স্ট্রাকচার্ড ডেটা মার্কআপ; Structured Data → স্ট্রাকচার্ড ডেটা
- Translate meaning, not word-by-word; keep UI strings concise (button labels stay short).

### 4. Lint the translated JSON

```bash
make i18n.lint
```

Fails on empty values, placeholder/HTML-tag mismatches. Fix reported entries yourself
(edit the JSON directly) and re-run until it exits 0. Do not proceed while it fails.

### 5. Write translations back into PO files

```bash
make i18n.json2po
```

### 6. Build the language packs (.mo + hashed .json)

```bash
make i18n.build
```

### 7. Verify and report

- `git status` should show updated `.po`, `.mo`, and hashed `.json` files under `languages/`.
- Report per-locale translated counts and any strings you were unsure about.
- Do not commit unless the user asks.

## Notes

- `docs/<lang>/README.md` files are separate hand-maintained docs, not part of this pipeline.

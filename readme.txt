=== Airygen SEO – Topic Clusters for SEO & GEO ===
Contributors: airygen, terrylin
Tags: topic-cluster, geo, llms-txt, seo, ai-seo
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 0.0.0
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl.html
Build topic clusters, find internal linking opportunities, and generate AI-readable files (llms.txt, Markdown). Works alongside your SEO plugin.

== Description ==

Airygen SEO helps WordPress publishers build stronger topic clusters, discover internal linking opportunities, and generate AI-readable site files such as `llms.txt` and Markdown exports.

Use it alongside your existing SEO plugin, or enable only the modules you need. It is built for content-heavy sites that want better structure, stronger internal links, and modern AI search visibility (GEO) — without replacing their whole SEO workflow. Designed for modern WordPress sites running PHP 8.1+.

= What makes it different =

* **Topic Cluster mind map** — visualize pillar, cluster, and support relationships across your content, and spot posts that sit outside any cluster.
* **Internal link suggestions and link counting** — surface where new internal links belong and see how well each post is connected.
* **Sitewide SEO evaluation** — score your whole site, not just one post at a time.
* **llms.txt and Markdown-for-agents** — publish AI-readable versions of your site so LLMs and agents can understand it.
* **Modular architecture** — turn on only the workflows you actually need.

= Works alongside your existing SEO plugin =

You do not have to replace your current SEO plugin to try Airygen SEO. Enable just the modules that add something new — topic clusters, internal linking, and AI-readable output — and leave your existing titles, meta, and schema untouched. When you are ready for more, you can hand additional SEO responsibilities to Airygen SEO one module at a time.

= A complete SEO toolkit when you want it =

Airygen SEO is also a full, modular SEO platform. When you choose to, it can take over on-page and technical SEO too:

* On-page SEO fields for titles, descriptions, canonical URLs, robots directives, and focus keyphrases, with a live content score and SERP snippet preview.
* Schema markup for article, website, organization, author, and breadcrumb contexts.
* XML sitemaps, robots controls, redirects, 404 logging, and broken link monitoring.
* Open Graph and Twitter / X social cards.
* Image SEO, Local SEO, author SEO, taxonomy SEO, hreflang, RSS feed signature, and WooCommerce SEO.
* Instant indexing, site verification, and notification integrations.

Full source code is available on GitHub. Feature requests, bug reports, and contributions are welcome: https://github.com/airygen/airygen-seo

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/airygen-seo` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open the Airygen SEO admin screens to enable the modules you want to use and configure their settings.
4. Review post, term, author, and sitewide SEO settings based on your content model.

== Frequently Asked Questions ==

= Can I use it alongside Yoast, Rank Math, AIOSEO, or SEOPress? =

Yes. You can install Airygen SEO next to your current SEO plugin and enable only the modules that add something new — such as Topic Clusters, Internal Linking, and llms.txt / Markdown export — while leaving on-page metadata and schema to your existing plugin. Every module is opt-in, so you decide exactly what Airygen SEO outputs.

= Do you provide a migration tool from other SEO plugins? =

Yes. Airygen SEO includes a built-in migration tool that imports your existing SEO data from Yoast SEO, Rank Math, All in One SEO (AIOSEO), and SEOPress.

The migration only reads from your current SEO plugin and copies the data into Airygen SEO's own fields. It never changes, overwrites, or deletes your original plugin's data — your existing SEO plugin keeps working exactly as before.

This makes migrating safe to try: if you are not happy with Airygen SEO after importing, simply deactivate and delete it. Your original SEO plugin and all of its data remain fully intact.

= What can I manage with Airygen SEO? =

You can manage on-page metadata, schema, social sharing metadata, sitemap and robots behavior, redirects, broken links, internal link workflows, indexing support, WooCommerce SEO output, and several content-structure features such as breadcrumbs, topic clusters, and table of contents.

= Does the plugin support social metadata? =

Yes. Airygen SEO outputs Open Graph and Twitter / X card metadata for supported content contexts.

= Does the plugin support structured data? =

Yes. The plugin includes schema markup support for key website and content contexts, including breadcrumb output.

= Does it include technical SEO tools? =

Yes. Current modules include sitemap controls, robots controls, redirects, 404 management, broken link checking, site verification, and instant indexing support.

= Does it work with WooCommerce? =

Yes. The plugin includes a WooCommerce SEO module for product-related SEO output and schema handling.

= Can it help with AI-readable outputs? =

Yes. Airygen SEO includes `llms.txt` and Markdown export features intended for agent-readable content workflows.

== Features ==

* Modular SEO architecture so site owners can enable the workflows they actually need.
* SEO title and description management with scoring support and search-result width reminders.
* Canonical and robots controls for posts, terms, and broader site contexts.
* Open Graph and Twitter / X card output for richer social sharing.
* Schema markup generation for important frontend contexts.
* XML sitemap support and related crawl controls.
* Breadcrumbs, table of contents, related posts, and topic cluster features to strengthen content structure.
* Internal link suggestions, link counting, and sitewide SEO evaluation to improve content quality over time.
* Redirects, 404 tools, and broken link checking for maintenance and cleanup.
* Runtime Image SEO to fill missing image attributes using configurable templates.
* Instant indexing, site verification, and notification modules for operational SEO workflows.
* Local SEO, author SEO, taxonomy SEO, hreflang, RSS feed signature, and WooCommerce SEO extensions.
* LLMs.txt and Markdown-for-agents tooling for modern publishing workflows.

== Source code ==

The full, human-readable source code for this plugin — including the unminified JavaScript, TypeScript, JSX, and SCSS sources used to produce every file under the distributed `build/` directory — is available on GitHub:

https://github.com/airygen/airygen-seo

Every compiled file shipped under `build/` carries an unminified header comment that points back to its source entry point, source directories, and the repository URL above. The mapping below documents that relationship in full so anyone can locate the human-readable source for any compiled asset.

= How the build/ directory is produced =

The `build/` directory is generated by `@wordpress/scripts` (which uses webpack 5, Babel, PostCSS, and Sass under the hood). The build entry points are declared in `package.json` (`scripts.build`):

* `wp-scripts build block-editor=./packages/block-editor/src/index.tsx --output-path=build/block-editor --output-filename=airygen-editor.js`
* `wp-scripts build classic-editor=./packages/classic-editor/src/index.tsx --output-path=build/classic-editor --output-filename=airygen-editor.js`
* `wp-scripts build ./packages/admin/app.tsx --output-path=build/admin --output-filename=airygen-app.js`

After `wp-scripts build` finishes, the `pnpm build` script runs `node scripts/build-banner.mjs`, which prepends a `/*! ... */` header to each emitted JS and CSS file. The header names the file's source entry, its source directories, the repository URL, and the build command. The script is idempotent: re-running it replaces an existing Airygen SEO banner rather than stacking another one on top.

= Source-to-build mapping =

Bundle: build/admin/

* `build/admin/airygen-app.js` — compiled from entry `packages/admin/app.tsx`. Pulls in modules under `packages/admin/components/`, `packages/admin/pages/`, `packages/admin/utils/`, `packages/admin/types/`, `packages/admin/constants/`, `packages/admin/styles/`, and shared modules under `packages/shared/`.
* `build/admin/app.tsx.css` and `build/admin/app.tsx-rtl.css` — compiled from `packages/admin/styles/tailwind.css`, with Tailwind CSS scanning class names across `packages/admin/**/*.{tsx,ts}` per `tailwind.config.js`, and PostCSS plugins declared in `postcss.config.js` (Tailwind CSS + Autoprefixer).
* `build/admin/airygen-app.asset.php` — auto-generated dependency manifest emitted by `@wordpress/dependency-extraction-webpack-plugin`. Not authored by hand.

Bundle: build/block-editor/

* `build/block-editor/airygen-editor.js` — compiled from entry `packages/block-editor/src/index.tsx`. Pulls in modules under `packages/block-editor/components/`, `packages/block-editor/hooks/`, `packages/block-editor/utils/`, `packages/block-editor/config.ts`, `packages/block-editor/types.ts`, and shared modules under `packages/shared/`.
* `build/block-editor/style-block-editor.css` and `build/block-editor/style-block-editor-rtl.css` — compiled from `packages/block-editor/style.scss` and any `.scss` files it imports under `packages/block-editor/`, processed by Sass and PostCSS.
* `build/block-editor/airygen-editor.asset.php` — auto-generated dependency manifest.

Bundle: build/classic-editor/

* `build/classic-editor/airygen-editor.js` — compiled from entry `packages/classic-editor/src/index.tsx`. Pulls in modules under `packages/classic-editor/components/` and shared modules under `packages/shared/`.
* `build/classic-editor/style-classic-editor.css` and `build/classic-editor/style-classic-editor-rtl.css` — compiled from `packages/classic-editor/style.scss` and any `.scss` files it imports under `packages/classic-editor/`, processed by Sass and PostCSS.
* `build/classic-editor/airygen-editor.asset.php` — auto-generated dependency manifest.

Assets that are not compiled

The following directories contain hand-written JavaScript and CSS that are shipped as-is (no build step) and enqueued directly by PHP. They are already in their human-readable form and have no `build/` counterpart:

* `resources/assets/js/` — `faq-preview.js`, `inline-script-host.js`, `metabox.js`, `toc-preview.js`, `topic-expansion-preview.js`.
* `resources/assets/css/style.css` — public-facing stylesheet.
* `resources/views/**` — PHP view templates.

= Reproducing the build locally =

1. Install Node.js 20+ and pnpm 9+, plus PHP 8.1+ and Composer 2+.
2. Clone the repository: `git clone https://github.com/airygen/airygen-seo.git`.
3. Install dependencies: `pnpm install` and `composer install`.
4. Build production assets: `pnpm build`.

After step 4 the same files shipped under `build/` in the distributed plugin will be regenerated locally. Each regenerated file will carry the same `/*! ... */` header described above, making every compiled file traceable back to a public source path.

== Screenshots ==

1. Central dashboard — enable or disable every SEO module from one screen.
2. In-editor content score — a live SEO score with a full pass/fail rule checklist, plus SERP snippet, keyphrases, and schema, right inside the post editor.
3. Topic Cluster mind map — visualize pillar, cluster, and support relationships across your content.
4. Score Calculator — tune the weight of each content-scoring rule to match your workflow.
5. On-Page SEO — control which meta tags are emitted and build title/description templates from tokens.
6. Schema Markup — live JSON-LD preview of the structured data printed in your page source.
7. XML Sitemap — choose which post types and taxonomies appear and tune pagination.
8. Robots Control — set default robots meta directives and manage robots.txt additions.

== Changelog ==

= 1.0.9 =

* First public release on WordPress.org.

== External services ==

This plugin connects to the following third-party services when the corresponding features are enabled by the site administrator. No data is sent to any external service unless the site administrator enables the feature.

= Google Maps =

When the Local SEO module is enabled and valid coordinates are configured, the plugin can embed a Google Maps iframe from `https://www.google.com/maps?...&output=embed` in two places: the frontend Local SEO business card output and the Local SEO admin preview. The request includes the configured latitude/longitude in the embed URL, and Google may receive standard embed request data such as the visitor or administrator IP address and browser metadata.

* [Google Maps Platform Terms of Service](https://cloud.google.com/maps-platform/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

= IndexNow endpoints =

When the Instant Indexing module is enabled and the site administrator configures an IndexNow key, the plugin sends JSON requests to each enabled IndexNow engine endpoint. The payload contains the site host, the configured key, the submitted URL list, and the optional key file location. Requests can occur during automatic publish or delete events, manual submissions from the Instant Indexing admin screen, and backfill jobs started by the site administrator. Built-in endpoints currently include:

* Microsoft Bing: `https://www.bing.com/indexnow` — [Microsoft Services Agreement](https://www.microsoft.com/en/servicesagreement), [Microsoft Privacy Statement](https://www.microsoft.com/en-us/privacy/privacystatement)
* Yandex: `https://yandex.com/indexnow` — [Yandex User Agreement](https://yandex.com/legal/rules/), [Yandex Privacy Policy](https://yandex.com/legal/confidential/)
* Seznam.cz: `https://search.seznam.cz/indexnow` — [Seznam Legal Information](https://o-seznam.cz/pravni-informace/), [Seznam Privacy Policy](https://o-seznam.cz/pravni-informace/ochrana-udaju/)
* Naver: `https://searchadvisor.naver.com/indexnow` — [Naver Terms of Service](https://policy.naver.com/policy/service_en.html), [Naver Privacy Policy](https://policy.naver.com/policy/privacy_en.html)
* Yep (Ahrefs): `https://indexnow.yep.com/indexnow` — [Ahrefs Terms of Service](https://ahrefs.com/legal/terms), [Ahrefs Privacy Policy](https://ahrefs.com/legal/privacy-policy)
* [IndexNow FAQ](https://www.indexnow.org/faq)

= Telegram Bot API =

When Telegram notifications are enabled in the Notify module, the plugin sends a `sendMessage` request to `https://api.telegram.org` using the bot token and chat ID configured by the site administrator. The transmitted payload contains the notification subject and message body, plus the optional Telegram topic ID when configured.

* [Telegram Terms of Service](https://telegram.org/tos)
* [Telegram Privacy Policy](https://telegram.org/privacy)

= Discord Webhooks =

When Discord notifications are enabled in the Notify module, the plugin sends a webhook request to the Discord webhook URL configured by the site administrator. The transmitted payload contains the notification subject as the main content field, the message body as an embed description, and optional webhook profile fields such as display name and avatar URL when configured.

* [Discord Terms of Service](https://discord.com/terms)
* [Discord Privacy Policy](https://discord.com/privacy)

= Microsoft Teams Webhooks =

When Microsoft Teams notifications are enabled in the Notify module, the plugin sends a webhook request to the Teams webhook URL configured by the site administrator. The transmitted payload contains the notification subject and message body.

* [Microsoft Services Agreement](https://www.microsoft.com/en/servicesagreement)
* [Microsoft Privacy Statement](https://www.microsoft.com/en-us/privacy/privacystatement)

= SMTP services =

When email notifications are enabled in the Notify module, the plugin connects directly to the SMTP server configured by the site administrator. The transmitted data includes the SMTP host and port, encryption/authentication settings, sender and recipient addresses, the notification subject, the digest message body, and any SMTP credentials required by the configured provider. The admin UI includes built-in presets for Gmail, Office 365 / Outlook, Mailgun, SendGrid, Amazon SES, Zoho Mail, Brevo, and Postmark, and the site administrator can also enter custom SMTP server details.

Only the SMTP host actually selected (or manually entered) by the site administrator is contacted; no SMTP host is contacted by default. When a built-in preset is chosen, the plugin connects to the following hosts:

* Gmail: `smtp.gmail.com:587` — [Google Terms of Service](https://policies.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy)
* Office 365 / Outlook: `smtp.office365.com:587` — [Microsoft Services Agreement](https://www.microsoft.com/en/servicesagreement), [Microsoft Privacy Statement](https://www.microsoft.com/en-us/privacy/privacystatement)
* Mailgun: `smtp.mailgun.org:587` — [Mailgun Terms of Service](https://www.mailgun.com/legal/terms/), [Mailgun Privacy Policy](https://www.mailgun.com/legal/privacy-policy/)
* SendGrid: `smtp.sendgrid.net:587` — [Twilio Terms of Service](https://www.twilio.com/en-us/legal/tos), [Twilio Privacy Policy](https://www.twilio.com/en-us/legal/privacy)
* Amazon SES: `email-smtp.us-east-1.amazonaws.com:587` — [AWS Service Terms](https://aws.amazon.com/service-terms/), [AWS Privacy Notice](https://aws.amazon.com/privacy/)
* Zoho Mail: `smtp.zoho.com:587` — [Zoho Terms of Service](https://www.zoho.com/terms.html), [Zoho Privacy Policy](https://www.zoho.com/privacy.html)
* Brevo: `smtp-relay.brevo.com:587` — [Brevo Terms of Use](https://www.brevo.com/legal/termsofuse/), [Brevo Privacy Policy](https://www.brevo.com/legal/privacypolicy/)
* Postmark: `smtp.postmarkapp.com:587` — [Postmark Terms of Service](https://postmarkapp.com/terms-of-service), [Postmark Privacy Policy](https://postmarkapp.com/privacy-policy)

== Copyright ==

Airygen SEO, Copyright 2025 Airygen.com
Airygen SEO is distributed under the terms of the GNU General Public License.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

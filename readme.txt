=== Airygen SEO – GEO & AEO for AI Search ===
Contributors: airygen, terrylin
Tags: seo, geo, aeo, ai-seo, wordpress-seo
Requires at least: 6.3
Tested up to: 7.0
Stable tag: 0.0.0
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl.html
Modular WordPress SEO plugin with on-page controls, schema, social cards, sitemap, redirects, internal linking, indexing, and content tooling.

== Description ==

Airygen SEO is a modular SEO toolkit for WordPress sites that need more than title and meta fields. It combines on-page SEO controls, structured data, technical SEO, internal link workflows, and automation tools in one plugin while keeping the editing experience inside WordPress.

Core areas included in the current plugin:

* On-page SEO fields for titles, descriptions, canonical URLs, robots directives, and focus keyphrases.
* Score calculator with title pixel-width checks and SEO analysis helpers.
* Social cards for Open Graph and Twitter / X sharing metadata.
* Schema markup for common page contexts, including article, website, organization, author, and breadcrumb data.
* XML sitemap support and robots controls.
* Breadcrumbs, table of contents, related posts, and topic cluster tooling.
* Image SEO attribute generation for missing image alt and title values.
* Redirect management, 404 log handling, broken link monitoring, and link counting.
* Internal link suggestions and sitewide SEO evaluation tools.
* Instant indexing workflows and site verification settings.
* Local SEO, author SEO, taxonomy SEO, hreflang, RSS feed signature, and WooCommerce SEO support.
* LLMs.txt and Markdown-for-agents utilities for AI-readable site output.
* Code snippet manager and notification integrations for operational workflows.

Airygen SEO is built for publishers, content teams, and site operators who want technical SEO controls, content optimization support, and maintenance workflows in a single plugin.

Full source code is available on GitHub. Feature requests, bug reports, and contributions are welcome: https://github.com/airygen/airygen-seo

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/airygen-seo` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open the Airygen SEO admin screens to enable the modules you want to use and configure their settings.
4. Review post, term, author, and sitewide SEO settings based on your content model.

== Frequently Asked Questions ==

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
* LLMs.txt, Markdown-for-agents, and code snippet tooling for modern publishing workflows.

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

= 0.0.0 =

* Initial development release.

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

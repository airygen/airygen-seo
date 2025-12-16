=== Airygen SEO ===
Contributors: airygen, terrylin
Tags: seo, schema, sitemap, redirects, woocommerce
Requires at least: 6.3
Tested up to: 6.9
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

* Microsoft Bing: `https://www.bing.com/indexnow`
* Yandex: `https://yandex.com/indexnow`
* Seznam.cz: `https://search.seznam.cz/indexnow`
* Naver: `https://www.naver.com/indexnow`
* Yep (Ahrefs): `https://yep.com/indexnow`
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

* Gmail: [Google Terms of Service](https://policies.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy)
* Office 365 / Outlook: [Microsoft Services Agreement](https://www.microsoft.com/en/servicesagreement), [Microsoft Privacy Statement](https://www.microsoft.com/en-us/privacy/privacystatement)
* Mailgun: [Mailgun Legal](https://www.mailgun.com/legal/)
* SendGrid: [Twilio Legal](https://www.twilio.com/legal)
* Amazon SES: [AWS Service Terms](https://aws.amazon.com/service-terms/), [AWS Privacy Notice](https://aws.amazon.com/privacy/)
* Zoho Mail: [Zoho Terms of Service](https://www.zoho.com/terms.html), [Zoho Privacy Policy](https://www.zoho.com/privacy.html)
* Brevo: [Brevo Terms of Use](https://www.brevo.com/legal/termsofuse/), [Brevo Privacy Policy](https://www.brevo.com/legal/privacypolicy/)
* Postmark: [Postmark Terms of Service](https://postmarkapp.com/terms-of-service), [Postmark Privacy Policy](https://postmarkapp.com/privacy-policy)

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

#!/usr/bin/env node
// Poll the WordPress.org support forum RSS feed for the plugin and open a GitHub
// Issue for every new topic. State is stored in GitHub itself: each forum topic
// maps to one issue carrying a hidden marker (<!-- wporg-topic: <link> -->), so
// re-runs never create duplicates and no external database is needed.
//
// Requires: GH_TOKEN in env (issues:write) and the `gh` CLI on PATH.
// Env knobs:
//   PLUGIN_SLUG   plugin slug on wordpress.org (default: airygen-seo)
//   ISSUE_LABEL   label applied to created issues (default: wporg-support)
//   GITHUB_REPO   owner/repo for gh (default: gh's inferred repo)

import { execFileSync } from 'node:child_process';

const SLUG = process.env.PLUGIN_SLUG || 'airygen-seo';
const LABEL = process.env.ISSUE_LABEL || 'wporg-support';
const REPO = process.env.GITHUB_REPO || '';
const FEED_URL = `https://wordpress.org/support/plugin/${SLUG}/feed/`;

const gh = (args) =>
  execFileSync('gh', REPO ? [...args, '--repo', REPO] : args, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });

const marker = (link) => `<!-- wporg-topic: ${link} -->`;

// Minimal RSS item parser. The WordPress.org feed is well-formed RSS 2.0 with
// CDATA-wrapped titles; a full XML parser would be overkill for this shape.
function parseItems(xml) {
  const items = [];
  for (const block of xml.split(/<item>/i).slice(1)) {
    const body = block.split(/<\/item>/i)[0];
    const pick = (tag) => {
      const m = body.match(new RegExp(`<${tag}[^>]*>([\\s\\S]*?)</${tag}>`, 'i'));
      if (!m) return '';
      return m[1]
        .replace(/^<!\[CDATA\[/, '')
        .replace(/\]\]>$/, '')
        .trim();
    };
    const link = pick('link');
    if (!link) continue;
    items.push({
      title: decodeEntities(pick('title')) || '(no title)',
      link,
      pubDate: pick('pubDate'),
      creator: decodeEntities(pick('dc:creator')),
    });
  }
  return items;
}

function decodeEntities(s) {
  return s
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;|&apos;/g, "'")
    .replace(/&#8217;/g, '’')
    .replace(/&#8220;/g, '“')
    .replace(/&#8221;/g, '”');
}

function ensureLabel() {
  try {
    gh(['label', 'create', LABEL, '--color', '1D76DB', '--description',
        'New topic on the WordPress.org support forum', '--force']);
  } catch (e) {
    // --force upserts; ignore any residual noise (e.g. permissions echo).
  }
}

function existingLinks() {
  const raw = gh(['issue', 'list', '--label', LABEL, '--state', 'all',
                  '--limit', '1000', '--json', 'body']);
  const seen = new Set();
  for (const issue of JSON.parse(raw || '[]')) {
    const m = (issue.body || '').match(/<!-- wporg-topic: (.+?) -->/);
    if (m) seen.add(m[1].trim());
  }
  return seen;
}

async function main() {
  const res = await fetch(FEED_URL, {
    headers: { 'User-Agent': `airygen-seo-support-watch (${SLUG})` },
  });
  if (!res.ok) {
    console.error(`::error::Failed to fetch feed: HTTP ${res.status}`);
    process.exit(1);
  }
  const xml = await res.text();
  const items = parseItems(xml);
  console.log(`Feed has ${items.length} topic(s).`);
  if (items.length === 0) return;

  ensureLabel();
  const seen = existingLinks();

  // Oldest first so issue numbers follow topic chronology.
  let created = 0;
  for (const item of items.reverse()) {
    if (seen.has(item.link)) continue;
    const body = [
      `New topic on the [WordPress.org support forum](https://wordpress.org/support/plugin/${SLUG}/):`,
      '',
      `**${item.title}**`,
      '',
      item.creator ? `- Author: ${item.creator}` : null,
      item.pubDate ? `- Posted: ${item.pubDate}` : null,
      `- Link: ${item.link}`,
      '',
      marker(item.link),
    ].filter((l) => l !== null).join('\n');

    const url = gh(['issue', 'create',
      '--title', `[Support] ${item.title}`,
      '--body', body,
      '--label', LABEL]).trim();
    console.log(`Opened issue for "${item.title}" -> ${url}`);
    seen.add(item.link);
    created++;
  }
  console.log(created ? `Created ${created} issue(s).` : 'No new topics.');
}

main().catch((err) => {
  console.error(`::error::${err.stack || err}`);
  process.exit(1);
});

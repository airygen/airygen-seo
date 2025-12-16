import { __, sprintf } from '@wordpress/i18n';

export type RuleGuide = {
	meaning: string;
	how: string;
	impact: string;
};

export const buildScoreRuleGuide = ( ruleId: string, label: string ): RuleGuide => {
	const guides: Record<string, RuleGuide> = {
		title_length_px: {
			meaning: __(
				'Your SEO title should be long enough to explain the topic, but short enough to avoid truncation.',
				'airygen-seo',
			),
			how: __(
				'Aim for about 50–60 characters. Front-load the most important words.',
				'airygen-seo',
			),
			impact: __(
				'Clear, visible titles improve click-through rate from search results.',
				'airygen-seo',
			),
		},
		keyword_in_title: {
			meaning: __(
				'Search engines expect the primary topic to appear in the title.',
				'airygen-seo',
			),
			how: __(
				'Include your focus keyphrase naturally near the start of the title.',
				'airygen-seo',
			),
			impact: __(
				'Improves topical relevance and makes the result more recognizable.',
				'airygen-seo',
			),
		},
		meta_description_present: {
			meaning: __(
				'A meta description helps search engines and users understand the page.',
				'airygen-seo',
			),
			how: __(
				'Write 1–2 sentences that summarize the page and invite a click.',
				'airygen-seo',
			),
			impact: __(
				'Better snippets can lift click-through rate even if rankings stay the same.',
				'airygen-seo',
			),
		},
		meta_description_length: {
			meaning: __(
				'Descriptions that are too short or too long get cut off in results.',
				'airygen-seo',
			),
			how: __(
				'Keep it roughly 140–160 characters and prioritize the main benefit.',
				'airygen-seo',
			),
			impact: __(
				'Full snippets look more professional and improve engagement.',
				'airygen-seo',
			),
		},
		meta_description_has_focus: {
			meaning: __(
				'Including the focus phrase helps confirm the page topic.',
				'airygen-seo',
			),
			how: __(
				'Work the focus keyphrase into the first sentence naturally.',
				'airygen-seo',
			),
			impact: __(
				'Stronger relevance signals can improve ranking and user confidence.',
				'airygen-seo',
			),
		},
		snippet_unique: {
			meaning: __(
				'Title and description should not repeat each other.',
				'airygen-seo',
			),
			how: __(
				'Use the title to state the topic and the description to add benefit or context.',
				'airygen-seo',
			),
			impact: __(
				'Distinct messaging gives users more reasons to click.',
				'airygen-seo',
			),
		},
		h1_one: {
			meaning: __(
				'Pages should have a single main heading (H1).',
				'airygen-seo',
			),
			how: __(
				'Keep one H1 that matches the page topic, and use H2/H3 for sections.',
				'airygen-seo',
			),
			impact: __(
				'Clear structure helps search engines understand the content hierarchy.',
				'airygen-seo',
			),
		},
		subheads_count: {
			meaning: __(
				'Subheadings break long content into scannable sections.',
				'airygen-seo',
			),
			how: __(
				'Add 2–8 H2/H3 headings that summarize key sections.',
				'airygen-seo',
			),
			impact: __(
				'Improves readability and keeps users engaged longer.',
				'airygen-seo',
			),
		},
		focus_in_subheads: {
			meaning: __(
				'Including the keyphrase in a subheading reinforces the main topic.',
				'airygen-seo',
			),
			how: __(
				'Place the focus keyphrase in one relevant H2 or H3.',
				'airygen-seo',
			),
			impact: __(
				'Helps search engines connect sections to the main theme.',
				'airygen-seo',
			),
		},
		intro_has_focus: {
			meaning: __(
				'The opening paragraph sets context for both users and search engines.',
				'airygen-seo',
			),
			how: __(
				'Use the focus keyphrase once in the first 1–2 sentences.',
				'airygen-seo',
			),
			impact: __(
				'Stronger topical clarity can improve relevance signals.',
				'airygen-seo',
			),
		},
		keyword_density: {
			meaning: __(
				'Keyphrase density shows how often the main topic appears.',
				'airygen-seo',
			),
			how: __(
				'Use the keyphrase naturally throughout the content without stuffing.',
				'airygen-seo',
			),
			impact: __(
				'Balanced usage supports relevance while avoiding spam signals.',
				'airygen-seo',
			),
		},
		long_tail_density: {
			meaning: __(
				'Long-tail phrases capture more specific search intent.',
				'airygen-seo',
			),
			how: __(
				'Add your long-tail phrases where they fit naturally in sections.',
				'airygen-seo',
			),
			impact: __(
				'Helps the page rank for more specific queries.',
				'airygen-seo',
			),
		},
		word_count: {
			meaning: __(
				'Very short pages often lack depth to satisfy readers.',
				'airygen-seo',
			),
			how: __(
				'Expand the content with examples, steps, or FAQs.',
				'airygen-seo',
			),
			impact: __(
				'More complete content tends to perform better for informational queries.',
				'airygen-seo',
			),
		},
		readability_flesch: {
			meaning: __(
				'Readable content keeps users engaged and reduces bounce.',
				'airygen-seo',
			),
			how: __(
				'Shorten sentences, use simpler words, and break up long paragraphs.',
				'airygen-seo',
			),
			impact: __(
				'Improved clarity boosts dwell time and user satisfaction.',
				'airygen-seo',
			),
		},
		readability_cjk_sentences: {
			meaning: __(
				'Long sentences are hard to read in CJK languages.',
				'airygen-seo',
			),
			how: __(
				'Split long sentences and add punctuation for clearer flow.',
				'airygen-seo',
			),
			impact: __(
				'Clearer reading experience helps users stay on the page.',
				'airygen-seo',
			),
		},
		has_image: {
			meaning: __(
				'Images support understanding and break up long text.',
				'airygen-seo',
			),
			how: __(
				'Add at least one relevant image that illustrates the topic.',
				'airygen-seo',
			),
			impact: __(
				'Visuals improve engagement and can help with image search.',
				'airygen-seo',
			),
		},
		images_alt_all: {
			meaning: __(
				'Alt text helps search engines and screen readers interpret images.',
				'airygen-seo',
			),
			how: __(
				'Describe each image in plain language, focusing on what it shows.',
				'airygen-seo',
			),
			impact: __(
				'Improves accessibility and adds extra relevance signals.',
				'airygen-seo',
			),
		},
		long_tail_spacing: {
			meaning: __(
				'Spacing long-tail phrases prevents keyword stuffing.',
				'airygen-seo',
			),
			how: __(
				'Spread similar phrases across different sections.',
				'airygen-seo',
			),
			impact: __(
				'Keeps content natural while still covering long-tail queries.',
				'airygen-seo',
			),
		},
		images_alt_focus_any: {
			meaning: __(
				'Including the keyphrase in one image alt text reinforces the topic.',
				'airygen-seo',
			),
			how: __(
				'Use the focus phrase in a single relevant image alt description.',
				'airygen-seo',
			),
			impact: __(
				'Adds topical relevance without over-optimizing every image.',
				'airygen-seo',
			),
		},
		internal_links: {
			meaning: __(
				'Internal links help users and search engines discover related pages.',
				'airygen-seo',
			),
			how: __(
				'Link to 1–3 relevant posts or pages using descriptive anchor text.',
				'airygen-seo',
			),
			impact: __(
				'Improves site structure and can pass authority to key pages.',
				'airygen-seo',
			),
		},
		external_links: {
			meaning: __(
				'External links show credibility and provide references.',
				'airygen-seo',
			),
			how: __(
				'Add at least one trusted source link that supports your content.',
				'airygen-seo',
			),
			impact: __(
				'Builds trust and can improve perceived quality.',
				'airygen-seo',
			),
		},
		rel_attributes: {
			meaning: __(
				'Rel attributes clarify how external links should be treated.',
				'airygen-seo',
			),
			how: __(
				'Add rel values like nofollow, sponsored, or ugc where appropriate.',
				'airygen-seo',
			),
			impact: __(
				'Helps avoid link spam signals and keeps compliance clean.',
				'airygen-seo',
			),
		},
		slug_words: {
			meaning: __(
				'Short, readable URLs are easier to understand and share.',
				'airygen-seo',
			),
			how: __(
				'Use 2–5 words that match the topic and remove stop words.',
				'airygen-seo',
			),
			impact: __(
				'Clean URLs improve clarity and can increase click confidence.',
				'airygen-seo',
			),
		},
		slug_has_focus: {
			meaning: __(
				'Including the focus phrase in the URL reinforces relevance.',
				'airygen-seo',
			),
			how: __(
				'Add the focus keyphrase to the slug without extra filler.',
				'airygen-seo',
			),
			impact: __(
				'Strengthens topical signals and makes URLs clearer.',
				'airygen-seo',
			),
		},
		canonical_valid: {
			meaning: __(
				'Canonical URLs tell search engines which version to index.',
				'airygen-seo',
			),
			how: __(
				'Ensure the canonical points to the main URL of the page.',
				'airygen-seo',
			),
			impact: __(
				'Prevents duplicate content issues and consolidates ranking signals.',
				'airygen-seo',
			),
		},
		jsonld_article: {
			meaning: __(
				'Article schema helps search engines understand your content type.',
				'airygen-seo',
			),
			how: __(
				'Enable Article schema in Schema Markup settings.',
				'airygen-seo',
			),
			impact: __(
				'Structured data can improve search appearance and visibility.',
				'airygen-seo',
			),
		},
		jsonld_breadcrumb: {
			meaning: __(
				'Breadcrumb schema shows navigation context in search results.',
				'airygen-seo',
			),
			how: __(
				'Enable Breadcrumbs and the BreadcrumbList schema output.',
				'airygen-seo',
			),
			impact: __(
				'Breadcrumb rich results can improve CTR and clarity.',
				'airygen-seo',
			),
		},
		site_health_good: {
			meaning: __(
				'Sitewide SEO reflects the overall health of your site setup.',
				'airygen-seo',
			),
			how: __(
				'Resolve site health warnings and keep SEO modules configured.',
				'airygen-seo',
			),
			impact: __(
				'A healthier site foundation supports stable rankings.',
				'airygen-seo',
			),
		},
	};

	if ( guides[ ruleId ] ) {
		return guides[ ruleId ];
	}

	return {
		meaning: sprintf(
			/* translators: %s is the rule label. */
			__( 'This rule checks: %s.', 'airygen-seo' ),
			label,
		),
		how: __(
			'Make sure the content aligns with the rule and update the page accordingly.',
			'airygen-seo',
		),
		impact: __(
			'Passing more rules improves the overall SEO score.',
			'airygen-seo',
		),
	};
};

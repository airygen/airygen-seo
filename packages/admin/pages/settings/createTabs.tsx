import type { ReactNode } from 'react';
import type { ShellTab } from '../../components/AdminShell';
import { __, sprintf } from '@wordpress/i18n';
import {
	OnPageSeoTabPanel,
	BreadcrumbsTabPanel,
	TocTabPanel,
	SocialTabPanel,
	AuthorSeoTabPanel,
	TaxonomySeoTabPanel,
	WooCommerceSeoTabPanel,
	LocalSeoTabPanel,
	SchemaTabPanel,
	RobotsTabPanel,
	ImageSeoTabPanel,
	HreflangTabPanel,
	SitemapTabPanel,
	CodeSnippetManagerTabPanel,
	SiteVerificationTabPanel,
	RssFeedSignatureTabPanel,
	InstantIndexingTabPanel,
	RedirectsTabPanel,
	LinkCounterTabPanel,
	BrokenLinkCheckerTabPanel,
	ScoreCalculatorTabPanel,
	SiteHealthTabPanel,
	LinkSuggestionsTabPanel,
	RelatedPostsTabPanel,
	NotFoundManagerTabPanel,
	MarkdownForAgentsTabPanel,
	LlmsTxtTabPanel,
} from '.';
import type { MetaPayload, NoticeState } from '../../types/api';
import type {
	BrokenLinkCheckerSettings,
	HreflangEntry,
	RedirectRule,
	SettingsState,
} from '../../types/settings';
import {
	OnPageSeoIcon,
	SocialCardsIcon,
	AuthorSeoIcon,
	LocalSeoIcon,
	SchemaMarkupIcon,
	WooCommerceSeoIcon,
	BreadcrumbsIcon,
	TocIcon,
	RobotsIcon,
	CodeSnippetsIcon,
	MarkdownForAgentsIcon,
	LlmsTxtIcon,
	SiteVerificationIcon,
	ImageSeoIcon,
	HreflangIcon,
	SitemapIcon,
	InstantIndexingIcon,
	RedirectsIcon,
	LinkCounterIcon,
	BrokenLinkCheckerIcon,
	ScoreCalculatorIcon,
	SiteHealthIcon,
} from '../../components/Icons';

type UpdateSection = <K extends keyof SettingsState>(
	key: K,
	value: SettingsState[K],
) => void;

type TabFactoryParams = {
	settings: SettingsState;
	meta: MetaPayload;
	restBase: string;
	robotsPreviewUrl: string;
	sitemapPreviewUrl: string;
	onCopySitemapLink: () => void;
	onCopyText: ( text: string, success: string, failure: string ) => void;
	updateSection: UpdateSection;
	updateManualLanguage: ( index: number, patch: Partial<HreflangEntry> ) => void;
	removeManualLanguage: ( index: number ) => void;
	addManualLanguage: () => void;
	onCreateRedirectRule: ( rule: Omit<RedirectRule, 'id'> ) => Promise<void>;
	onUpdateRedirectRule: ( id: string, patch: Partial<RedirectRule> ) => Promise<void>;
	onRemoveRedirectRule: ( id: string ) => Promise<void>;
	brokenLinkCheckerDefaults: BrokenLinkCheckerSettings;
	onLinkSuggestionsDirtyChange: ( dirty: boolean ) => void;
	registerLinkSuggestionsSubmit: ( submit: () => Promise<void> ) => void;
	linkSuggestionsSaving: boolean;
	onNotice: ( notice: NoticeState ) => void;
};

export type SettingsTab = ShellTab & {
	render: () => ReactNode;
};

const createSettingsTabs = ( {
	settings,
	meta,
	restBase,
	robotsPreviewUrl,
	sitemapPreviewUrl,
	onCopySitemapLink,
	onCopyText,
	updateSection,
	updateManualLanguage,
	removeManualLanguage,
	addManualLanguage,
	onCreateRedirectRule,
	onUpdateRedirectRule,
	onRemoveRedirectRule,
	brokenLinkCheckerDefaults,
	onLinkSuggestionsDirtyChange,
	registerLinkSuggestionsSubmit,
	linkSuggestionsSaving,
	onNotice,
}: TabFactoryParams ): SettingsTab[] => {
	const actionSchedulerAvailable = meta.linkCounter?.status?.actionSchedulerAvailable;

	return [
		{
			name: 'onPageSeo',
			title: __( 'On-Page SEO', 'airygen-seo' ),
			icon: OnPageSeoIcon,
			render: () => (
				<OnPageSeoTabPanel
					settings={ settings.onPageSeo }
					meta={ meta }
					onChange={ ( value ) => updateSection( 'onPageSeo', value ) }
				/>
			),
		},
		{
			name: 'social',
			title: __( 'Social Media Tags', 'airygen-seo' ),
			icon: SocialCardsIcon,
			render: () => (
				<SocialTabPanel
					settings={ settings.socialCards }
					onChange={ ( value ) => updateSection( 'socialCards', value ) }
				/>
			),
		},
		{
			name: 'authorSeo',
			title: __( 'Author SEO', 'airygen-seo' ),
			icon: AuthorSeoIcon,
			render: () => (
				<AuthorSeoTabPanel
					settings={ settings.authorSeo }
					onChange={ ( value ) => updateSection( 'authorSeo', value ) }
				/>
			),
		},
		{
			name: 'taxonomySeo',
			title: __( 'Taxonomy SEO', 'airygen-seo' ),
			icon: SitemapIcon,
			render: () => (
				<TaxonomySeoTabPanel
					settings={ settings.taxonomySeo }
					meta={ meta }
					onChange={ ( value ) => updateSection( 'taxonomySeo', value ) }
				/>
			),
		},
		{
			name: 'wooCommerceSeo',
			title: __( 'WooCommerce SEO', 'airygen-seo' ),
			icon: WooCommerceSeoIcon,
			render: () => (
				<WooCommerceSeoTabPanel
					settings={ settings.wooCommerceSeo }
					meta={ meta }
					onChange={ ( value ) => updateSection( 'wooCommerceSeo', value ) }
				/>
			),
		},
		{
			name: 'localSeo',
			title: __( 'Local SEO', 'airygen-seo' ),
			icon: LocalSeoIcon,
			render: () => (
				<LocalSeoTabPanel
					settings={ settings.localSeo }
					onChange={ ( value ) => updateSection( 'localSeo', value ) }
				/>
			),
		},
		{
			name: 'schema',
			title: __( 'Schema Markup', 'airygen-seo' ),
			icon: SchemaMarkupIcon,
			render: () => (
				<SchemaTabPanel
					settings={ settings.schemaMarkup }
					meta={ meta }
					onChange={ ( value ) => updateSection( 'schemaMarkup', value ) }
				/>
			),
		},
		{
			name: 'breadcrumbs',
			title: __( 'Breadcrumbs', 'airygen-seo' ),
			icon: BreadcrumbsIcon,
			render: () => (
				<BreadcrumbsTabPanel
					settings={ settings.breadcrumbs }
					onCopyToClipboard={ onCopyText }
					onChange={ ( value ) => updateSection( 'breadcrumbs', value ) }
				/>
			),
		},
		{
			name: 'toc',
			title: __( 'Table of Contents', 'airygen-seo' ),
			icon: TocIcon,
			render: () => (
				<TocTabPanel
					settings={ settings.toc }
					meta={ meta }
					onCopyToClipboard={ onCopyText }
					onChange={ ( value ) => updateSection( 'toc', value ) }
				/>
			),
		},
		{
			name: 'robots',
			title: __( 'Robots Control', 'airygen-seo' ),
			icon: RobotsIcon,
			render: () => (
				<RobotsTabPanel
					settings={ settings.robots }
					robotsPreviewUrl={ robotsPreviewUrl }
					onCopyToClipboard={ onCopyText }
					onChange={ ( value ) => updateSection( 'robots', value ) }
				/>
			),
		},
		{
			name: 'imageSeo',
			title: __( 'Image SEO', 'airygen-seo' ),
			icon: ImageSeoIcon,
			render: () => (
				<ImageSeoTabPanel
					settings={ settings.imageSeo }
					onChange={ ( value ) => updateSection( 'imageSeo', value ) }
				/>
			),
		},
		{
			name: 'hreflang',
			title: __( 'Language Versions', 'airygen-seo' ),
			icon: HreflangIcon,
			render: () => (
				<HreflangTabPanel
					settings={ settings.hreflang }
					onUpdateEntry={ updateManualLanguage }
					onRemoveEntry={ removeManualLanguage }
					onAddEntry={ addManualLanguage }
					onIncludeDefaultChange={ ( include ) =>
						updateSection( 'hreflang', {
							...settings.hreflang,
							include_x_default: include,
						} )
					}
				/>
			),
		},
		{
			name: 'sitemap',
			title: __( 'Sitemap', 'airygen-seo' ),
			icon: SitemapIcon,
			render: () => (
				<SitemapTabPanel
					settings={ settings.sitemap }
					meta={ meta }
					sitemapPreviewUrl={ sitemapPreviewUrl }
					onCopyPreviewLink={ onCopySitemapLink }
					onChange={ ( value ) => updateSection( 'sitemap', value ) }
				/>
			),
		},
		{
			name: 'codeSnippetManager',
			title: __( 'Code Snippets', 'airygen-seo' ),
			icon: CodeSnippetsIcon,
			render: () => (
				<CodeSnippetManagerTabPanel
					settings={ settings.codeSnippetManager }
					onChange={ ( value ) => updateSection( 'codeSnippetManager', value ) }
				/>
			),
		},
		{
			name: 'siteVerification',
			title: __( 'Site Verification', 'airygen-seo' ),
			icon: SiteVerificationIcon,
			render: () => (
				<SiteVerificationTabPanel
					settings={ settings.siteVerification }
					onChange={ ( value ) => updateSection( 'siteVerification', value ) }
				/>
			),
		},
		{
			name: 'rssFeedSignature',
			title: __( 'RSS Feed Signature', 'airygen-seo' ),
			icon: SitemapIcon,
			render: () => (
				<RssFeedSignatureTabPanel
					settings={ settings.rssFeedSignature }
					onChange={ ( value ) => updateSection( 'rssFeedSignature', value ) }
				/>
			),
		},
		{
			name: 'instantIndexing',
			title: __( 'Instant Indexing', 'airygen-seo' ),
			icon: InstantIndexingIcon,
			render: () => (
				<InstantIndexingTabPanel
					settings={ settings.instantIndexing }
					meta={ meta }
					restBase={ restBase }
					actionSchedulerAvailable={ actionSchedulerAvailable }
					onChange={ ( value ) => updateSection( 'instantIndexing', value ) }
				/>
			),
		},
		{
			name: 'linkCounter',
			title: __( 'Link Counter', 'airygen-seo' ),
			icon: LinkCounterIcon,
			render: () => (
				<LinkCounterTabPanel meta={ meta } restBase={ restBase } />
			),
		},
		{
			name: 'linkSuggestions',
			title: __( 'Link Suggestions', 'airygen-seo' ),
			icon: LinkCounterIcon,
			render: () => (
				<LinkSuggestionsTabPanel
					restBase={ restBase }
					meta={ meta }
					actionSchedulerAvailable={ actionSchedulerAvailable }
					onNotice={ onNotice }
					onDirtyChange={ onLinkSuggestionsDirtyChange }
					registerSubmit={ registerLinkSuggestionsSubmit }
					isSaving={ linkSuggestionsSaving }
				/>
			),
		},
		{
			name: 'relatedPosts',
			title: __( 'Related Posts', 'airygen-seo' ),
			icon: LinkCounterIcon,
			render: () => (
				<RelatedPostsTabPanel
					settings={ settings.relatedPosts }
					meta={ meta }
					onCopyToClipboard={ onCopyText }
					onChange={ ( value ) => updateSection( 'relatedPosts', value ) }
				/>
			),
		},
		{
			name: 'notFoundManager',
			title: sprintf(
				/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
				__( '%s Manager', 'airygen-seo' ),
				'404',
			),
			icon: RedirectsIcon,
			render: () => (
				<NotFoundManagerTabPanel
					settings={ settings.notFoundManager }
					restBase={ restBase }
					onNotice={ onNotice }
					onChange={ ( value ) => updateSection( 'notFoundManager', value ) }
				/>
			),
		},
		{
			name: 'markdownForAgents',
			title: __( 'Markdown for Agents', 'airygen-seo' ),
			icon: MarkdownForAgentsIcon,
			render: () => (
				<MarkdownForAgentsTabPanel
					settings={ settings.markdownForAgents }
					meta={ meta }
					restBase={ restBase }
					onChange={ ( value ) => updateSection( 'markdownForAgents', value ) }
				/>
			),
		},
		{
			name: 'llmsTxt',
			title: __( 'LLMs.txt', 'airygen-seo' ),
			icon: LlmsTxtIcon,
			render: () => (
				<LlmsTxtTabPanel
					settings={ settings.llmsTxt }
					meta={ meta }
					restBase={ restBase }
					topicClusterEnabled={ Boolean( settings.modules.topicCluster ) }
					markdownForAgentsEnabled={ Boolean( settings.modules.markdownForAgents ) }
					onChange={ ( value ) => updateSection( 'llmsTxt', value ) }
				/>
			),
		},
		{
			name: 'siteHealth',
			title: __( 'Sitewide SEO', 'airygen-seo' ),
			icon: SiteHealthIcon,
			render: () => (
				<SiteHealthTabPanel restBase={ restBase } />
			),
		},
		{
			name: 'scoreCalculator',
			title: __( 'Score Calculator', 'airygen-seo' ),
			icon: ScoreCalculatorIcon,
			render: () => (
				<ScoreCalculatorTabPanel
					settings={ settings.scoreCalculator }
					meta={ meta }
					restBase={ restBase }
					onChange={ ( value ) => updateSection( 'scoreCalculator', value ) }
				/>
			),
		},
		{
			name: 'brokenLinkChecker',
			title: __( 'Broken Link Checker', 'airygen-seo' ),
			icon: BrokenLinkCheckerIcon,
			render: () => (
				<BrokenLinkCheckerTabPanel
					settings={ settings.brokenLinkChecker }
					restBase={ restBase }
					linkCounterEnabled={ settings.modules.linkCounter }
					actionSchedulerAvailable={ actionSchedulerAvailable }
					status={ meta.brokenLinkChecker?.status }
					defaults={ brokenLinkCheckerDefaults }
					onChange={ ( value ) => updateSection( 'brokenLinkChecker', value ) }
				/>
			),
		},
		{
			name: 'redirects',
			title: __( 'Redirects', 'airygen-seo' ),
			icon: RedirectsIcon,
			render: () => (
				<RedirectsTabPanel
					settings={ settings.redirects }
					meta={ meta }
					onRemoveRule={ onRemoveRedirectRule }
					onUpdateRule={ onUpdateRedirectRule }
					onCreateRule={ onCreateRedirectRule }
				/>
			),
		},
	];
};

export default createSettingsTabs;

export type SocialCardsOgSettings = {
	enabled: boolean;
	defaultImageId: number;
	defaultImageUrl: string;
	imageWidth: number;
	imageHeight: number;
	fbAppId: string;
	fbAdmins: string;
	publisherUrl: string;
	domainVerification: string;
};

export type SocialCardsTwitterSettings = {
	enabled: boolean;
	cardType: 'summary' | 'summary_large_image';
	siteHandle: string;
	creatorHandle: string;
	inheritOgImage: boolean;
	defaultImageId: number;
	defaultImageUrl: string;
};

export type SocialCardsSettings = {
	og: SocialCardsOgSettings;
	twitter: SocialCardsTwitterSettings;
};

export type SchemaMarkupSettings = {
	organization_name: string;
	organization_type: string;
	organization_logo_id: number;
	organization_logo_url: string;
	article_type: string;
	post_type_defaults: Record<string, string>;
	article_show_author: boolean;
	article_only_post: boolean;
	visibility: {
		organization: boolean;
		website: boolean;
		breadcrumb: boolean;
		article: boolean;
	};
};

export type RobotsSettings = {
	default_directive: string;
	additional_rules: string[];
	enable_default_meta: boolean;
};

export type ImageSeoAttributeSettings = {
	enabled: boolean;
	format: string;
};

export type ImageSeoSettings = {
	alt: ImageSeoAttributeSettings;
	title: ImageSeoAttributeSettings;
	separator: string;
	customTokens: {
		custom1: string;
		custom2: string;
		custom3: string;
	};
};

export type HreflangEntry = {
	code: string;
	url: string;
	persisted?: boolean;
};

export type HreflangSettings = {
	manual_map: HreflangEntry[];
	include_x_default: boolean;
};

export type SitemapSettings = {
	enabled_post_types: string[];
	enabled_taxonomies: string[];
	exclude_empty_taxonomies: boolean;
	items_per_page: number;
};

export type RssFeedSignatureSettings = {
	enabled: boolean;
	before_content: string;
	after_content: string;
};

export type CodeSnippetPlacement = 'head' | 'body' | 'footer' | 'inactive';

export type CodeSnippet = {
	id: string;
	enabled: boolean;
	description: string;
	code: string;
	placement: CodeSnippetPlacement;
};

export type CodeSnippetManagerSettings = {
	snippets: CodeSnippet[];
};

export type SiteVerificationSettings = {
	google: string;
	bing: string;
	yandex: string;
	baidu: string;
	pinterest: string;
};

export type RedirectRule = {
	id: string;
	type: 'exact' | 'wildcard' | 'regex';
	source: string;
	target: string;
	status: number;
	enabled: boolean;
	note: string;
};

export type RedirectsSettings = {
	rules: RedirectRule[];
};

export type LinkCounterQueueSnapshot = {
	pending: number | null;
	inProgress: number | null;
	failed: number | null;
	completed: number | null;
};

export type LinkCounterStatusMeta = {
	actionSchedulerAvailable: boolean;
	nextRunGmt: string | null;
	lastRunGmt: string | null;
	pendingPosts: number;
	processingPosts: number;
	failedPosts?: number;
	processedPosts: number;
	queue: LinkCounterQueueSnapshot;
};

export type BrokenLinkCheckerStatusMeta = {
	actionSchedulerAvailable: boolean;
	nextRunGmt: string | null;
	lastRunGmt: string | null;
	queue: LinkCounterQueueSnapshot;
};

export type NotifyStatusMeta = {
	actionSchedulerAvailable: boolean;
	nextRunGmt: string | null;
	lastRunGmt: string | null;
	queue: LinkCounterQueueSnapshot;
};

export type BrokenLinkCheckerSettings = {
	enabled: boolean;
	enableDailyAlert: boolean;
	checkIntervalHours: number;
	maxRequestsPerRun: number;
	batchDelayMinutes: number;
	logRetentionDays: number;
	connectionTimeoutSeconds: number;
	operationTimeoutSeconds: number;
	treatRedirectsAsWarning: boolean;
	linkTypes: {
		external: boolean;
		internal: boolean;
	};
};

export type InstantIndexingEngineSettings = {
	enabled: boolean;
	endpoint: string;
};

export type InstantIndexingSettings = {
	enabled: boolean;
	autoSubmit: boolean;
	retryCooldownDays: number;
	key: string;
	keyLocation: string;
	maxEventsPerDay: number;
	batchSize: number;
	engines: Record<string, InstantIndexingEngineSettings>;
	backfill: {
		postTypes: string[];
	};
};

export type OnPageSeoTemplateGroup = {
	title: string;
	description: string;
};

export type OnPageSeoTemplates = {
	global: OnPageSeoTemplateGroup;
	postTypes: Record<string, OnPageSeoTemplateGroup>;
	separator: string;
	customTokens: {
		custom1: string;
		custom2: string;
		custom3: string;
	};
};

export type OnPageSeoSettings = {
	output: {
		title: boolean;
		description: boolean;
		canonical: boolean;
		robots: boolean;
	};
	templates: OnPageSeoTemplates;
};

export type BreadcrumbsSettings = {
	manualOutputEnabled: boolean;
	autoInjectionEnabled: boolean;
	injectionPosition: 'before_content' | 'after_content';
	separator: string;
	prefix: string;
	home: {
		display: boolean;
		label: string;
		url: string;
	};
	labels: {
		archive: string;
		search: string;
		error: string;
	};
	display: {
		showCurrent: boolean;
		showAncestors: boolean;
		showBlog: boolean;
		showPagination: boolean;
		hideTaxonomy: boolean;
	};
	style: {
		fontSize: number;
		textColor: string;
		linkColor: string;
		underlineLinks: boolean;
		borderWidth: number;
		borderColor: string;
		padding: number;
		bgColor: string;
	};
};

export type TocSettings = {
	manualOutputEnabled: boolean;
	autoInjectionEnabled: boolean;
	postTypes: string[];
	levels: number[];
	position: 'before-content' | 'after-first-paragraph';
	titleEnabled: boolean;
	title: string;
	titleLevel: 'h2' | 'h3' | 'h4';
	minHeadings: number;
	smoothScroll: boolean;
	anchorPrefix: string;
	addNumbers: boolean;
	excludeHeadings: string;
	collapseOnLoad: boolean;
	style: {
		preset: 'minimal' | 'card' | 'soft' | 'accent' | 'compact' | 'snow-slate' | 'honey-paper' | 'sky-breeze' | 'mint-calm' | 'rose-blush' | 'lavender-mist';
		borderStyle: 'solid' | 'dashed' | 'dotted';
		borderColor: string;
		borderRadius: number;
		bodyContainer?: {
			borderWidths: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
			paddings: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
			margins: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
		};
		tocPadding: number;
		linkColor: string;
		linkSize: number;
		fontStyle: {
			bold: boolean;
			italic: boolean;
			underline: boolean;
		};
		bgColor: string;
		headerContainer?: {
			borderWidths: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
			borderRadius: number;
			borderStyle: 'solid' | 'dashed' | 'dotted';
			borderColor: string;
			paddings: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
			bgColor: string;
			margins: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
		};
		headerTitle?: {
			fontStyle: {
				bold: boolean;
				italic: boolean;
				underline: boolean;
			};
			color: string;
			fontSize: number;
		};
	};
};

export type TopicClusterSettings = {
	manualOutputEnabled: boolean;
	autoInjectionEnabled: boolean;
	overrideBreadcrumbs: boolean;
	overrideWpAdjacent: boolean;
	insertPosition: 'before-content' | 'after-content';
	postTypes: string[];
	titleEnabled: boolean;
	titleText: string;
	relationTextL1: string;
	relationTextL2: string;
	relationTextL3: string;
	titleLevel: 'h2' | 'h3' | 'h4';
	styleType: string;
	style: {
		preset: string;
		showBorder: boolean;
		borderStyle: string;
		borderColor: string;
		borderWidths: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		borderRadius: number;
		paddings: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		margins: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		bgColor: string;
		itemTextColor: string;
		itemFontSize: number;
		itemBold: boolean;
		itemItalic: boolean;
		itemUnderline: boolean;
		itemListStyle: 'none' | 'disc' | 'decimal';
		itemGap: number;
		headerContainer?: {
			borderWidths: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
			borderRadius: number;
			borderStyle: 'solid' | 'dashed' | 'dotted';
			borderColor: string;
			paddings: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
			bgColor: string;
			margins: {
				top: number;
				right: number;
				bottom: number;
				left: number;
			};
		};
		headerTitle?: {
			fontStyle: {
				bold: boolean;
				italic: boolean;
				underline: boolean;
			};
			color: string;
			fontSize: number;
		};
	};
};

export type AuthorSeoSettings = {
	enabled: boolean;
	noindexAuthorArchives: boolean;
	titleTemplate: string;
	descriptionTemplate: string;
	separator: string;
	customTokens: {
		custom1: string;
		custom2: string;
		custom3: string;
	};
	socialProfiles: string[];
};

export type TaxonomySeoTemplateGroup = {
	title: string;
	description: string;
};

export type TaxonomySeoSettings = {
	enabled: boolean;
	enabledTaxonomies: string[];
	templates: {
		global: TaxonomySeoTemplateGroup;
		separator: string;
		customTokens: {
			custom1: string;
			custom2: string;
			custom3: string;
		};
	};
};

export type WooCommerceSeoTemplateGroup = {
	title: string;
	description: string;
};

export type WooCommerceSeoSettings = {
	enabled: boolean;
	brandAttribute: string;
	templates: {
		product: WooCommerceSeoTemplateGroup;
		separator: string;
		customTokens: {
			custom1: string;
			custom2: string;
			custom3: string;
		};
	};
};

export type LocalSeoSettings = {
	enabled: boolean;
	layoutTemplate:
		| 'sidebar_left'
		| 'sidebar_right'
		| 'sidebar_left_header'
		| 'sidebar_right_header';
	layoutShowCardBorder: boolean;
	layoutCardPadding: number;
	layoutLabelFontSize: number;
	layoutLabelColor: string;
	layoutLabelUppercase: boolean;
	layoutLabelBold: boolean;
	layoutLabelItalic: boolean;
	layoutValueFontSize: number;
	layoutValueColor: string;
	layoutTitleFontSize: number;
	layoutCardBackgroundColor: string;
	businessType: string;
	businessName: string;
	legalName: string;
	imageUrl: string;
	logoUrl: string;
	phone: string;
	priceRangeLevel: '$' | '$$' | '$$$' | '$$$$';
	priceRangeCustom: string;
	ratingValue: number;
	reviewCount: number;
	sameAsUrls: string[];
	streetAddress: string;
	city: string;
	region: string;
	postalCode: string;
	country: string;
	latitude: number;
	longitude: number;
	kmlInSitemap: boolean;
	openingHours: string;
	enableGeoTags: boolean;
	geoRegionCode: string;
	geoPlacename: string;
	mapZoom: number;
	serviceCatalogName: string;
	serviceCatalogItems: Array<{
		name: string;
		description: string;
	}>;
	layoutOrder: string[];
	layoutGrid: Array<{
		blockId: string;
		row: number;
		col: number;
		span: number;
		rowSpan: number;
	}>;
	footerNapLayoutOrder: string[];
	footerNapEnabled: boolean;
	footerNapFontSize: number;
	footerNapTextColor: string;
	footerNapTextAlign: 'left' | 'center' | 'right';
	footerNapFirstItemBold: boolean;
	footerNapMarginY: number;
	footerNapGap: number;
	footerNapContainerWidth: number;
	contactAutoMapEmbed: boolean;
	contactDetailedOpeningHours: boolean;
	serviceAreaCities: string[];
	serviceAreaPostalCodes: string[];
	serviceAreaRadiusKm: number;
	vatId: string;
	vatValidateChecksum: boolean;
	showVatInFooter: boolean;
	clickToCallEnabled: boolean;
	specialHours: string;
	branches: Array<{
		id: string;
		label: string;
		slug: string;
		enabled: boolean;
		businessName: string;
		phone: string;
		imageUrl: string;
		streetAddress: string;
		city: string;
		region: string;
		postalCode: string;
		country: string;
		latitude: number;
		longitude: number;
		openingHours: string;
		specialHours: string;
		serviceAreaCities: string[];
		serviceAreaPostalCodes: string[];
		serviceAreaRadiusKm: number;
		contactAutoMapEmbed: boolean;
		kmlInSitemap: boolean;
		geoRegionCode: string;
		geoPlacename: string;
	}>;
};

export type ScoreCalculatorSettings = {
	rules: Record<string, number>;
	postTypes: string[];
	customRules: Record<string, Record<string, number>>;
};

export type RelatedPostsBlockId =
	| 'featured_image'
	| 'title'
	| 'excerpt'
	| 'author'
	| 'date';
export type RelatedPostsLayoutRegion =
	| 'header'
	| 'body'
	| 'left_sidebar'
	| 'footer_left'
	| 'footer_center'
	| 'footer_right';

export type RelatedPostsSettings = {
	enabled: boolean;
	titleEnabled: boolean;
	titleText: string;
	titleLevel: 'h2' | 'h3' | 'h4';
	template: 'single_column' | 'sidebar_left';
	footerColumns: 1 | 2 | 3;
	blockOrder: RelatedPostsBlockId[];
	blockRegions: Partial<Record<RelatedPostsBlockId, RelatedPostsLayoutRegion>>;
	gridContainer: {
		borderWidths: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		borderRadius: number;
		borderStyle: 'solid' | 'dashed' | 'dotted';
		borderColor: string;
		bgColor: string;
		paddings: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		gap: number;
	};
	postContainer: {
		borderWidths: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		borderRadius: number;
		borderStyle: 'solid' | 'dashed' | 'dotted';
		borderColor: string;
		bgColor: string;
		paddings: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		gap: number;
	};
	headerContainer?: {
		borderWidths: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		borderRadius: number;
		borderStyle: 'solid' | 'dashed' | 'dotted';
		borderColor: string;
		paddings: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
		bgColor: string;
		margins: {
			top: number;
			right: number;
			bottom: number;
			left: number;
		};
	};
	headerTitle?: {
		fontStyle: {
			bold: boolean;
			italic: boolean;
			underline: boolean;
		};
		color: string;
		fontSize: number;
	};
	featuredImageSize: string;
	featuredImageRadius: number;
	titleFontSize: number;
	titleColor: string;
	titleBold: boolean;
	titleItalic: boolean;
	excerptFontSize: number;
	excerptColor: string;
	excerptMaxChars: number;
	excerptFadeMask: boolean;
	excerptFadeColor: string;
	excerptMaskHeight: number;
	authorFontSize: number;
	authorColor: string;
	authorBold: boolean;
	authorItalic: boolean;
	autoInjectEnabled: boolean;
	displayPreset: '2x2' | '3x2' | '4x2' | '4x1' | '1x4';
	enabledPostTypes: string[];
	insertPosition: 'before_content' | 'after_content';
};

export type NotFoundManagerSettings = {
	monitorMode: 'simple' | 'advanced';
	enableDailyAlert: boolean;
	ignoreQueryParams: boolean;
	logLimit: number;
	retentionDays: number;
	excludePatterns: string[];
	fallbackRedirectMode: 'off' | 'home' | 'custom';
	fallbackRedirectTarget: string;
	fallbackRedirectCode: 301 | 302 | 307 | 410 | 451;
};

export type NotifySettings = {
	enabled: boolean;
	custom: {
		visibleBlocks: string[];
		hiddenBlocks: string[];
	};
	message: {
		subject: string;
		intro: string;
		footer: string;
	};
	logs: {
		retentionDays: number;
	};
	schedule: {
		timezone: string;
		time: string;
	};
	channels: {
		email: {
			enabled: boolean;
			recipients: string[];
			smtp: {
				host: string;
				port: number;
				auth: boolean;
				secure: '' | 'tls' | 'ssl';
				username: string;
				password: string;
				timeout: number;
				fromEmail: string;
				fromName: string;
			};
		};
		telegram: {
			enabled: boolean;
			botToken: string;
			chatId: string;
			topicId: string;
		};
		discord: {
			enabled: boolean;
			webhook: string;
			username: string;
			avatar: string;
		};
		teams: {
			enabled: boolean;
			webhook: string;
		};
	};
};

export type MarkdownForAgentsSettings = {
	enabled: boolean;
	promptsForAgents: boolean;
	includeFrontmatter: boolean;
	postTypes: string[];
};

export type LlmsTxtSettings = {
	enabled: boolean;
	customDeclaration: string;
	autoSectionTitle: string;
	indexStrategy: 'curated_only' | 'curated_plus_auto' | 'auto_only';
	autoTopicClusterGroups: boolean;
	useMarkdownLinks: boolean;
	addToSitemap: boolean;
	excludeNoindex: boolean;
	excludePasswordProtected: boolean;
	minWordCount: number;
	sections: Array<{
		id: string;
		title: string;
		description: string;
		postIds: number[];
		maxItems: number;
		hidden: boolean;
	}>;
	extensions: Array<{
		id: string;
		title: string;
		description: string;
		path: string;
		customDeclaration: string;
		filename: 'llms.txt' | 'llms-small.txt' | 'llms-full.txt';
		enabled: boolean;
		sections: Array<{
			id: string;
			title: string;
			description: string;
			postIds: number[];
			maxItems: number;
			hidden: boolean;
		}>;
	}>;
	postTypes: string[];
};

export type ScoreCalculatorRuleMeta = {
	id: string;
	label: string;
	defaultWeight: number;
	group: 'base' | 'bonus';
	requiresFocus: boolean;
};

export type ScoreCalculatorMeta = {
	rules: ScoreCalculatorRuleMeta[];
	customRules: Array<{
		id: string;
		label: string;
		fields: Array<{
			key: string;
			label: string;
			help: string;
			min?: number;
			max?: number;
			step?: number;
			defaultValue: number;
			value: number;
		}>;
	}>;
	minWeight: number;
	maxWeight: number;
};

import type { ModuleKey, ModuleSettings, PanelKey } from './modules';

export type ContentBlockKey = 'toc' | 'breadcrumbs' | 'relatedPosts' | 'topicCluster';

export type SettingsState = {
	socialCards: SocialCardsSettings;
	schemaMarkup: SchemaMarkupSettings;
	breadcrumbs: BreadcrumbsSettings;
	robots: RobotsSettings;
	imageSeo: ImageSeoSettings;
	hreflang: HreflangSettings;
	sitemap: SitemapSettings;
	codeSnippetManager: CodeSnippetManagerSettings;
	siteVerification: SiteVerificationSettings;
	rssFeedSignature: RssFeedSignatureSettings;
	redirects: RedirectsSettings;
	brokenLinkChecker: BrokenLinkCheckerSettings;
	instantIndexing: InstantIndexingSettings;
	onPageSeo: OnPageSeoSettings;
	scoreCalculator: ScoreCalculatorSettings;
	toc: TocSettings;
	topicCluster: TopicClusterSettings;
	authorSeo: AuthorSeoSettings;
	taxonomySeo: TaxonomySeoSettings;
	wooCommerceSeo: WooCommerceSeoSettings;
	localSeo: LocalSeoSettings;
	relatedPosts: RelatedPostsSettings;
	notFoundManager: NotFoundManagerSettings;
	notify: NotifySettings;
	markdownForAgents: MarkdownForAgentsSettings;
	llmsTxt: LlmsTxtSettings;
	modules: ModuleSettings;
	moduleOrder: ModuleKey[];
	panelOrder: PanelKey[];
	panelVisibility: Record<PanelKey, boolean>;
	contentBlockOrder: ContentBlockKey[];
	contentBlockGap: number;
	contentBlockMarginTop: number;
};

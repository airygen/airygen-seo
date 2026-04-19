/* eslint-disable camelcase, no-nested-ternary */
import './styles/tailwind.css';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import {
	Fragment,
	render,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode, DragEvent } from 'react';
import Button from './components/Button';
import InstallWizard from './components/InstallWizard';
import Notice from './components/Notice';
import Spinner from './components/Spinner';
import {
	HreflangIcon,
	ImageSeoIcon,
	InstantIndexingIcon,
	LinkCounterIcon,
	RedirectsIcon,
	RobotsIcon,
	CodeSnippetsIcon,
	MarkdownForAgentsIcon,
	LlmsTxtIcon,
	SiteVerificationIcon,
	SchemaMarkupIcon,
	AuthorSeoIcon,
	SitemapIcon,
	SocialCardsIcon,
	BrokenLinkCheckerIcon,
	ScoreCalculatorIcon,
	OnPageSeoIcon,
	BreadcrumbsIcon,
	TocIcon,
	SiteHealthIcon,
	TopicClusterIcon,
	LocalSeoIcon,
	WooCommerceSeoIcon,
	NotifyModuleIcon,
} from './components/Icons';
import AdminShell, {
	type ShellPage,
	type ShellPageName,
	type ShellTabKey,
} from './components/AdminShell';
import DashboardPage from './pages/DashboardPage';
import DebugPage from './pages/DebugPage';
import MigrationPage from './pages/MigrationPage';
import MigrationYoastPage from './pages/MigrationYoastPage';
import MigrationRankMathPage from './pages/MigrationRankMathPage';
import MigrationAioseoPage from './pages/MigrationAioseoPage';
import MigrationSeoPressPage from './pages/MigrationSeoPressPage';
import TopicClusterPage from './pages/TopicClusterPage';
import { SettingsPage } from './pages/settings';
import createSettingsTabs, { type SettingsTab } from './pages/settings/createTabs';
import NotifyTab, { type NotifyView } from './pages/settings/tabs/NotifyTab';
import type { ModuleKey, ModuleMetadata, ModuleSettings, PanelKey, PanelMetadata } from './types/modules';
import type { DebugState } from './types/debug';
import type { ApiResponse, MetaPayload, NoticeState } from './types/api';
import {
	getLoadingAppLabel,
	getNoModuleSelectedLabel,
} from '../shared/i18nPhrases';
import type {
	ImageSeoAttributeSettings,
	ImageSeoSettings,
	HreflangEntry,
	RedirectRule,
	BrokenLinkCheckerSettings,
	InstantIndexingEngineSettings,
	InstantIndexingSettings,
	OnPageSeoTemplateGroup,
	OnPageSeoSettings,
	BreadcrumbsSettings,
	TocSettings,
	ScoreCalculatorSettings,
	TopicClusterSettings,
	AuthorSeoSettings,
	TaxonomySeoSettings,
	WooCommerceSeoSettings,
	LocalSeoSettings,
	RelatedPostsLayoutRegion,
	RelatedPostsSettings,
	NotFoundManagerSettings,
	NotifySettings,
	MarkdownForAgentsSettings,
	LlmsTxtSettings,
	SettingsState,
	ContentBlockKey,
} from './types/settings';
import type {
	RestModuleSettings,
	RawOgSettings,
	RawTwitterSettings,
	RawImageSeoAttributeSettings,
	RawImageSeoSettings,
	RawSocialSettings,
	RawSchemaSettings,
	RawBreadcrumbsSettings,
	RawTocSettings,
	RawRobotsSettings,
	RawHreflangSettings,
	RawSitemapSettings,
	RawCodeSnippetManagerSettings,
	RawRssFeedSignatureSettings,
	RawSiteVerificationSettings,
	RawRedirectSettings,
	RawInstantIndexingEngineSettings,
	RawInstantIndexingSettings,
	RawOnPageSeoSettings,
	RawBrokenLinkCheckerSettings,
	RawScoreCalculatorSettings,
	RawTopicClusterSettings,
	RawAuthorSeoSettings,
	RawTaxonomySeoSettings,
	RawWooCommerceSeoSettings,
	RawLocalSeoSettings,
	RawRelatedPostsSettings,
	RawNotFoundManagerSettings,
	RawNotifySettings,
	RawMarkdownForAgentsSettings,
	RawLlmsTxtSettings,
	RawSettingsPayload,
} from './types/raw-settings';
import {
	isSessionExpiredRestError,
	isSessionExpiredLocked,
	lockSessionExpired,
} from '../shared/rest/session';
import {
	ADMIN_PAGES_UPDATED_EVENT,
	getRegisteredAdminPages,
} from '../shared/extensions/adminPages';
export type {
	SocialCardsOgSettings,
	SocialCardsTwitterSettings,
	SocialCardsSettings,
	SchemaMarkupSettings,
	RobotsSettings,
	ImageSeoAttributeSettings,
	ImageSeoSettings,
	HreflangEntry,
	HreflangSettings,
	SitemapSettings,
	CodeSnippetManagerSettings,
	SiteVerificationSettings,
	RssFeedSignatureSettings,
	RedirectRule,
	RedirectsSettings,
	LinkCounterQueueSnapshot,
	BrokenLinkCheckerSettings,
	InstantIndexingEngineSettings,
	InstantIndexingSettings,
	OnPageSeoTemplateGroup,
	OnPageSeoTemplates,
	OnPageSeoSettings,
	ScoreCalculatorSettings,
	BreadcrumbsSettings,
	AuthorSeoSettings,
	WooCommerceSeoSettings,
	LocalSeoSettings,
	RelatedPostsSettings,
	NotFoundManagerSettings,
	NotifySettings,
	MarkdownForAgentsSettings,
	LlmsTxtSettings,
	SettingsState,
} from './types/settings';

declare global {
	interface Window {
		airygenAdmin?: {
			restPath?: string;
			sessionCheckUrl?: string;
			restRoot?: string;
			nonce?: string;
			adminUrl?: string;
			logoutUrl?: string;
			locale?: string;
			notifyTimezones?: Array<{
				value?: string;
				label?: string;
			}>;
			initialPage?:
				| 'dashboard'
				| 'settings'
				| 'notify'
				| 'topicCluster'
				| 'migration'
				| 'debug';
			initialSettingsTab?: string;
			debugRestPath?: string;
			debugEnablePath?: string;
			debugDisablePath?: string;
			debugEditorPath?: string;
			debugLevelPath?: string;
			extensionApiVersion?: string;
			pageRegistry?: Array<{
				key?: string;
				slug?: string;
				title?: string;
				order?: number;
			}>;
			migration?: {
				yoastActive?: boolean;
				rankMathActive?: boolean;
				aioseoActive?: boolean;
				seoPressActive?: boolean;
			};
			assets?: {
				logo?: string;
				aiPromptIcons?: {
					scenarios?: Record<string, string>;
					contentTypes?: Record<string, string>;
					tones?: Record<string, string>;
				};
			};
			themeStylesheets?: string[];
		};
		wpApiSettings?: {
			root?: string;
			nonce?: string;
		};
	}
}

const isRedirectRuleType = ( value: unknown ): value is RedirectRule['type'] =>
	value === 'exact' || value === 'wildcard' || value === 'regex';

const BROKEN_LINK_CHECKER_DEFAULTS: BrokenLinkCheckerSettings = {
	enabled: true,
	enableDailyAlert: true,
	checkIntervalHours: 24,
	maxRequestsPerRun: 10,
	batchDelayMinutes: 5,
	logRetentionDays: 7,
	connectionTimeoutSeconds: 2,
	operationTimeoutSeconds: 5,
	treatRedirectsAsWarning: true,
	linkTypes: {
		external: true,
		internal: false,
	},
};

const INSTANT_INDEXING_DEFAULTS: InstantIndexingSettings = {
	enabled: true,
	autoSubmit: true,
	retryCooldownDays: 7,
	key: '',
	keyLocation: '',
	maxEventsPerDay: 10000,
	batchSize: 100,
	engines: {},
	backfill: {
		postTypes: [],
	},
};

const ONPAGE_SEO_DEFAULTS: OnPageSeoSettings = {
	output: {
		title: true,
		description: true,
		canonical: true,
		robots: true,
	},
	templates: {
		global: {
			title: '%post_title% %separator% %site_name%',
			description: '%post_excerpt%',
		},
		separator: '–',
		postTypes: {},
		customTokens: {
			custom1: '',
			custom2: '',
			custom3: '',
		},
	},
};

const SCORE_WEIGHT_MIN = 0;
const SCORE_WEIGHT_MAX = 20;

const SCORE_CALCULATOR_DEFAULTS: ScoreCalculatorSettings = {
	rules: {},
	postTypes: [ 'post' ],
	customRules: {},
};

const DEFAULT_HOME_URL =
	typeof window !== 'undefined' && window.location
		? `${ window.location.origin }/`
		: '/';

const BREADCRUMBS_DEFAULTS: BreadcrumbsSettings = {
	manualOutputEnabled: true,
	autoInjectionEnabled: true,
	injectionPosition: 'before_content',
	separator: '›',
	prefix: '',
	home: {
		display: true,
		label: 'Home',
		url: DEFAULT_HOME_URL,
	},
	labels: {
		archive: 'Archives for %s',
		search: 'Results for %s',
		error: '404: Page not found',
	},
	display: {
		showCurrent: true,
		showAncestors: false,
		showBlog: false,
		showPagination: true,
		hideTaxonomy: false,
	},
	style: {
		fontSize: 14,
		textColor: '#1f2937',
		linkColor: '#2563eb',
		underlineLinks: false,
		borderWidth: 1,
		borderColor: '#e2e8f0',
		padding: 12,
		bgColor: 'transparent',
	},
};

const TOC_DEFAULTS: TocSettings = {
	manualOutputEnabled: true,
	autoInjectionEnabled: true,
	postTypes: [ 'post' ],
	levels: [ 2, 3 ],
	position: 'after-first-paragraph',
	titleEnabled: true,
	title: 'Table of contents',
	titleLevel: 'h2',
	minHeadings: 3,
	smoothScroll: true,
	anchorPrefix: 'toc-',
	addNumbers: true,
	excludeHeadings: '',
	collapseOnLoad: false,
	style: {
		preset: 'minimal',
		borderStyle: 'dashed',
		borderColor: '#dddddd',
		borderRadius: 6,
		bodyContainer: {
			borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
			paddings: { top: 9, right: 16, bottom: 16, left: 16 },
			margins: { top: 0, right: 0, bottom: 0, left: 0 },
		},
		tocPadding: 20,
		linkColor: '#2563eb',
		linkSize: 14,
		fontStyle: {
			bold: false,
			italic: false,
			underline: false,
		},
		bgColor: '#ffffff',
		headerContainer: {
			borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
			borderRadius: 0,
			borderStyle: 'solid',
			borderColor: '#a3a3a3',
			paddings: { top: 0, right: 0, bottom: 0, left: 15 },
			bgColor: 'transparent',
			margins: { top: 0, right: 0, bottom: 12, left: 0 },
		},
		headerTitle: {
			fontStyle: {
				bold: false,
				italic: false,
				underline: false,
			},
			color: '#0f172a',
			fontSize: 18,
		},
	},
};

const AUTHOR_SEO_DEFAULTS: AuthorSeoSettings = {
	enabled: true,
	noindexAuthorArchives: false,
	titleTemplate: '%author_name% | %site_name%',
	descriptionTemplate: '%author_bio%',
	separator: '|',
	customTokens: {
		custom1: '',
		custom2: '',
		custom3: '',
	},
	socialProfiles: [],
};

const TAXONOMY_SEO_DEFAULTS: TaxonomySeoSettings = {
	enabled: true,
	enabledTaxonomies: [ 'category', 'post_tag' ],
	templates: {
		global: {
			title: '%term_name% %separator% %site_name%',
			description: '%term_description%',
		},
		separator: '–',
		customTokens: {
			custom1: '',
			custom2: '',
			custom3: '',
		},
	},
};

const WOO_COMMERCE_SEO_DEFAULTS: WooCommerceSeoSettings = {
	enabled: true,
	brandAttribute: 'product_brand',
	templates: {
		product: {
			title: '%product_name% %separator% %site_name%',
			description: '%product_name% in %category_name%. SKU: %sku%.',
		},
		separator: '–',
		customTokens: {
			custom1: '',
			custom2: '',
			custom3: '',
		},
	},
};

const LOCAL_SEO_DEFAULTS: LocalSeoSettings = {
	enabled: true,
	layoutTemplate: 'sidebar_left_header',
	layoutShowCardBorder: true,
	layoutCardPadding: 16,
	layoutLabelFontSize: 12,
	layoutLabelColor: '#64748b',
	layoutLabelUppercase: true,
	layoutLabelBold: true,
	layoutLabelItalic: false,
	layoutValueFontSize: 14,
	layoutValueColor: '#334155',
	layoutTitleFontSize: 40,
	layoutCardBackgroundColor: '#ffffff',
	businessType: 'LocalBusiness',
	businessName: 'Terry Business',
	legalName: 'XX \u6709\u9650\u516c\u53f8',
	imageUrl: '',
	logoUrl: '',
	phone: '0929302168',
	priceRangeLevel: '$$',
	priceRangeCustom: '',
	ratingValue: 4.7,
	reviewCount: 6,
	sameAsUrls: [],
	streetAddress: 'No. 33, Ln 12, Zhongyi 2rd St.',
	city: 'Rende',
	region: '\u53f0\u5357\u5e02',
	postalCode: '717',
	country: 'TW',
	latitude: 23.005668,
	longitude: 120.2257917,
	kmlInSitemap: true,
	openingHours:
		'Mo 09:00-18:00\nTu 09:00-18:00\nWe 09:00-18:00\nTh 09:00-18:00\nFr 09:00-18:00\nSa 00:00-23:59\nSu 09:00-18:00',
	enableGeoTags: false,
	geoRegionCode: '',
	geoPlacename: '',
	mapZoom: 15,
	serviceCatalogName: 'ffffffffffffffffffff',
	serviceCatalogItems: [ { name: 'aaaaaaaaaaaaaaaa', description: 'bbbbb' } ],
	layoutOrder: [
		'business_name',
		'map',
		'phone',
		'legal_name',
		'vat_id',
		'pricing',
		'service_catalog',
		'address',
		'opening_hours',
		'service_areas',
		'special_hours',
	],
	layoutGrid: [
		{ blockId: 'business_name', row: 1, col: 1, span: 4, rowSpan: 1 },
		{ blockId: 'map', row: 2, col: 1, span: 2, rowSpan: 3 },
		{ blockId: 'phone', row: 2, col: 3, span: 1, rowSpan: 1 },
		{ blockId: 'legal_name', row: 2, col: 4, span: 1, rowSpan: 1 },
		{ blockId: 'vat_id', row: 2, col: 5, span: 1, rowSpan: 1 },
		{ blockId: 'pricing', row: 3, col: 3, span: 3, rowSpan: 1 },
		{ blockId: 'service_catalog', row: 4, col: 3, span: 3, rowSpan: 1 },
		{ blockId: 'address', row: 5, col: 1, span: 2, rowSpan: 1 },
		{ blockId: 'opening_hours', row: 5, col: 3, span: 3, rowSpan: 1 },
		{ blockId: 'service_areas', row: 6, col: 1, span: 2, rowSpan: 1 },
		{ blockId: 'special_hours', row: 6, col: 3, span: 3, rowSpan: 1 },
	],
	footerNapLayoutOrder: [ 'business_name', 'phone', 'address' ],
	footerNapEnabled: true,
	footerNapFontSize: 12,
	footerNapTextColor: '#334155',
	footerNapTextAlign: 'center',
	footerNapFirstItemBold: true,
	footerNapMarginY: 12,
	footerNapGap: 12,
	footerNapContainerWidth: 960,
	contactAutoMapEmbed: false,
	contactDetailedOpeningHours: false,
	serviceAreaCities: [ '\u53f0\u5357\u5e02', '\u9ad8\u96c4\u5e02' ],
	serviceAreaPostalCodes: [ '70114', '80223' ],
	serviceAreaRadiusKm: 4.9,
	vatId: '123123',
	vatValidateChecksum: false,
	showVatInFooter: false,
	clickToCallEnabled: false,
	specialHours: '2026-02-19 to 2026-02-20|closed',
	branches: [],
};

const LOCAL_SEO_LAYOUT_BLOCKS = [
	'business_name',
	'legal_name',
	'address',
	'phone',
	'map',
	'image_url',
	'logo_url',
	'vat_id',
	'pricing',
	'service_areas',
	'service_catalog',
	'opening_hours',
	'special_hours',
] as const;
const LOCAL_SEO_FOOTER_NAP_BLOCKS = [
	'business_name',
	'legal_name',
	'phone',
	'address',
	'tax_id',
] as const;
const LOCAL_SEO_GRID_ROWS = 15;
const LOCAL_SEO_GRID_COLS = 5;

const normalizeLocalSeoLayoutGrid = (
	value: unknown,
	_layoutOrder: string[],
): LocalSeoSettings['layoutGrid'] => {
	const allowedIds = LOCAL_SEO_LAYOUT_BLOCKS as readonly string[];
	const occupied = new Set<string>();
	const next: LocalSeoSettings['layoutGrid'] = [];
	const usedIds = new Set<string>();

	if ( Array.isArray( value ) ) {
		value.forEach( ( item ) => {
			if ( ! item || typeof item !== 'object' ) {
				return;
			}

			const candidate = item as {
				block_id?: unknown;
				blockId?: unknown;
				row?: unknown;
				col?: unknown;
				span?: unknown;
				row_span?: unknown;
				rowSpan?: unknown;
			};
			const blockId =
				typeof candidate.block_id === 'string'
					? candidate.block_id
					: typeof candidate.blockId === 'string'
						? candidate.blockId
						: '';
			if ( ! allowedIds.includes( blockId ) || usedIds.has( blockId ) ) {
				return;
			}

			const row = Number( candidate.row );
			const col = Number( candidate.col );
			const span = Number( candidate.span );
			const rowSpan = Number(
				undefined !== candidate.row_span
					? candidate.row_span
					: undefined !== candidate.rowSpan
						? candidate.rowSpan
						: 1,
			);
			const maxSpan = LOCAL_SEO_GRID_COLS - col + 1;
			const maxRowSpan = Math.min( 5, LOCAL_SEO_GRID_ROWS - row + 1 );
			if (
				! Number.isInteger( row ) ||
				! Number.isInteger( col ) ||
				! Number.isInteger( span ) ||
				! Number.isInteger( rowSpan ) ||
				row < 1 ||
				row > LOCAL_SEO_GRID_ROWS ||
				col < 1 ||
				col > LOCAL_SEO_GRID_COLS ||
				span < 1 ||
				span > maxSpan ||
				rowSpan < 1 ||
				rowSpan > maxRowSpan
			) {
				return;
			}

			for ( let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1 ) {
				for ( let colOffset = 0; colOffset < span; colOffset += 1 ) {
					const key = `${ row + rowOffset }-${ col + colOffset }`;
					if ( occupied.has( key ) ) {
						return;
					}
				}
			}

			for ( let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1 ) {
				for ( let colOffset = 0; colOffset < span; colOffset += 1 ) {
					occupied.add( `${ row + rowOffset }-${ col + colOffset }` );
				}
			}
			usedIds.add( blockId );
			next.push( { blockId, row, col, span, rowSpan } );
		} );
	}

	return next
		.slice()
		.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
};

const normalizeLocalSeoLayoutOrder = ( value: unknown ): string[] => {
	if ( ! Array.isArray( value ) ) {
		return [ ...LOCAL_SEO_DEFAULTS.layoutOrder ];
	}

	const selected = value.filter(
		( item ): item is string =>
			typeof item === 'string' &&
			( LOCAL_SEO_LAYOUT_BLOCKS as readonly string[] ).includes( item ),
	);
	return [ ...new Set( selected ) ];
};
const normalizeLocalSeoFooterNapLayoutOrder = ( value: unknown ): string[] => {
	if ( ! Array.isArray( value ) ) {
		return [ ...LOCAL_SEO_DEFAULTS.footerNapLayoutOrder ];
	}
	const selected = value.filter(
		( item ): item is string =>
			typeof item === 'string' &&
			( LOCAL_SEO_FOOTER_NAP_BLOCKS as readonly string[] ).includes( item ),
	);
	const unique = [ ...new Set( selected ) ];
	return unique.length > 0 ? unique : [ ...LOCAL_SEO_DEFAULTS.footerNapLayoutOrder ];
};
const normalizeIsoCountryCode = ( value: unknown ): string => {
	if ( typeof value !== 'string' ) {
		return '';
	}
	const code = value
		.trim()
		.toUpperCase()
		.replace( /[^A-Z]/g, '' );
	if ( code.length !== 2 ) {
		return '';
	}
	return code;
};

const buildLocalSeoLayoutGridFromOrder = (
	layoutOrder: string[],
): LocalSeoSettings['layoutGrid'] =>
	layoutOrder.map( ( blockId, index ) => ( {
		blockId,
		row: Math.floor( index / LOCAL_SEO_GRID_COLS ) + 1,
		col: ( index % LOCAL_SEO_GRID_COLS ) + 1,
		span: 1,
		rowSpan: 1,
	} ) );

const TOPIC_CLUSTER_DEFAULTS: TopicClusterSettings = {
	manualOutputEnabled: false,
	autoInjectionEnabled: false,
	overrideBreadcrumbs: true,
	overrideWpAdjacent: true,
	insertPosition: 'after-content',
	postTypes: [ 'post' ],
	titleEnabled: true,
	titleText: 'Featured topics',
	relationTextL1: 'Explore the main articles in this series.',
	relationTextL2: 'This article is part of the %s series. The links below expand on the topic.',
	relationTextL3: 'This article expands on %s.',
	titleLevel: 'h2',
	styleType: 'snow-slate',
	style: {
		preset: 'snow-slate',
		showBorder: true,
		borderStyle: 'solid',
		borderColor: '#cbd5e1',
		borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
		borderRadius: 8,
		paddings: { top: 9, right: 16, bottom: 16, left: 16 },
		margins: { top: 0, right: 0, bottom: 0, left: 0 },
		bgColor: 'transparent',
		itemTextColor: '#0f172a',
		itemFontSize: 14,
		itemBold: false,
		itemItalic: false,
		itemUnderline: false,
		itemListStyle: 'disc',
		itemGap: 0,
		headerContainer: {
			borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
			borderRadius: 0,
			borderStyle: 'solid',
			borderColor: '#a3a3a3',
			paddings: { top: 0, right: 0, bottom: 0, left: 15 },
			bgColor: 'transparent',
			margins: { top: 0, right: 0, bottom: 12, left: 0 },
		},
		headerTitle: {
			fontStyle: {
				bold: false,
				italic: false,
				underline: false,
			},
			color: '#0f172a',
			fontSize: 18,
		},
	},
};

const RELATED_POSTS_DEFAULTS: RelatedPostsSettings = {
	enabled: false,
	titleEnabled: true,
	titleText: 'Related Posts',
	titleLevel: 'h2',
	template: 'sidebar_left',
	footerColumns: 2,
	blockOrder: [ 'featured_image', 'title', 'excerpt', 'author', 'date' ],
	blockRegions: {
		featured_image: 'left_sidebar',
		title: 'header',
		excerpt: 'body',
		author: 'footer_left',
		date: 'footer_right',
	},
	gridContainer: {
		borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
		borderRadius: 6,
		borderStyle: 'dashed',
		borderColor: '#dddddd',
		bgColor: 'transparent',
		paddings: { top: 9, right: 16, bottom: 16, left: 16 },
		gap: 16,
	},
	postContainer: {
		borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
		borderRadius: 0,
		borderStyle: 'solid',
		borderColor: '#e2e8f0',
		bgColor: '#ffffff',
		paddings: { top: 12, right: 12, bottom: 12, left: 12 },
		gap: 10,
	},
	headerContainer: {
		borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
		borderRadius: 0,
		borderStyle: 'solid',
		borderColor: '#a3a3a3',
		paddings: { top: 0, right: 0, bottom: 0, left: 15 },
		bgColor: 'transparent',
		margins: { top: 0, right: 0, bottom: 12, left: 0 },
	},
	headerTitle: {
		fontStyle: {
			bold: false,
			italic: false,
			underline: false,
		},
		color: '#0f172a',
		fontSize: 18,
	},
	featuredImageSize: 'medium',
	featuredImageRadius: 4,
	titleFontSize: 18,
	titleColor: '#334155',
	titleBold: true,
	titleItalic: false,
	excerptFontSize: 14,
	excerptColor: '#334155',
	excerptMaxChars: 140,
	excerptFadeMask: false,
	excerptFadeColor: '#ffffff',
	excerptMaskHeight: 40,
	authorFontSize: 13,
	authorColor: '#475569',
	authorBold: false,
	authorItalic: false,
	autoInjectEnabled: true,
	displayPreset: '2x2',
	enabledPostTypes: [ 'post' ],
	insertPosition: 'after_content',
};

const getRelatedPostsDataLimitByPreset = (
	preset: RelatedPostsSettings['displayPreset'],
): number => {
	if ( preset === '4x2' ) {
		return 8;
	}
	if ( preset === '2x2' || preset === '4x1' || preset === '1x4' ) {
		return 4;
	}
	return 6;
};
const RELATED_POSTS_BLOCK_IDS: RelatedPostsSettings['blockOrder'] = [
	'featured_image',
	'title',
	'excerpt',
	'author',
	'date',
];

const NOT_FOUND_MANAGER_DEFAULTS: NotFoundManagerSettings = {
	monitorMode: 'simple',
	enableDailyAlert: true,
	ignoreQueryParams: true,
	logLimit: 1000,
	retentionDays: 30,
	excludePatterns: [ '/wp-admin/*', '/wp-json/*' ],
	fallbackRedirectMode: 'off',
	fallbackRedirectTarget: '',
	fallbackRedirectCode: 301,
};

const NOTIFY_DEFAULTS: NotifySettings = {
	enabled: false,
	custom: {
		visibleBlocks: [ 'not_found_logs', 'broken_link_logs' ],
		hiddenBlocks: [],
	},
	message: {
		subject: 'Airygen SEO Daily Digest',
		intro: 'This is a record summary generated from the modules you have subscribed to.',
		footer: 'This is a system message. Please do not reply.',
	},
	logs: {
		retentionDays: 30,
	},
	schedule: {
		timezone: 'UTC',
		time: '09:00',
	},
	channels: {
		email: {
			enabled: false,
			recipients: [],
			smtp: {
				host: 'smtp.gmail.com',
				port: 587,
				auth: true,
				secure: 'tls',
				username: '',
				password: '',
				timeout: 10,
				fromEmail: '',
				fromName: 'WordPress',
			},
		},
		telegram: {
			enabled: false,
			botToken: '',
			chatId: '',
			topicId: '',
		},
		discord: {
			enabled: false,
			webhook: '',
			username: '',
			avatar: '',
		},
		teams: {
			enabled: false,
			webhook: '',
		},
	},
};

const MARKDOWN_FOR_AGENTS_DEFAULTS: MarkdownForAgentsSettings = {
	enabled: true,
	promptsForAgents: false,
	includeFrontmatter: true,
	postTypes: [ 'post', 'page' ],
};

const LLMS_TXT_DEFAULTS: LlmsTxtSettings = {
	enabled: true,
	customDeclaration: '',
	autoSectionTitle: 'Additional content',
	indexStrategy: 'curated_plus_auto',
	autoTopicClusterGroups: false,
	useMarkdownLinks: false,
	addToSitemap: true,
	excludeNoindex: true,
	excludePasswordProtected: true,
	minWordCount: 150,
	sections: [],
	extensions: [],
	postTypes: [ 'post', 'page' ],
};
const canUseRootLlmsExtensionFilename = ( path: string ): boolean =>
	path.trim() !== '';

const DIGEST_BLOCK_NOT_FOUND = 'not_found_logs';
const DIGEST_BLOCK_BROKEN_LINK = 'broken_link_logs';

const enforceNotifyCustomAlertBlocks = (
	notify: NotifySettings['custom'],
	notFoundAlertEnabled: boolean,
	brokenLinkAlertEnabled: boolean,
): NotifySettings['custom'] => {
	const knownBlocks = [ DIGEST_BLOCK_NOT_FOUND, DIGEST_BLOCK_BROKEN_LINK ];
	const unique = ( list: string[] ): string[] => {
		const next: string[] = [];
		list.forEach( ( item ) => {
			if ( knownBlocks.includes( item ) && ! next.includes( item ) ) {
				next.push( item );
			}
		} );
		return next;
	};

	const visible = unique( notify.visibleBlocks );
	const hidden = unique( notify.hiddenBlocks ).filter( ( key ) => ! visible.includes( key ) );
	const setBlockState = ( blockId: string, enabled: boolean ) => {
		const visibleIndex = visible.indexOf( blockId );
		const hiddenIndex = hidden.indexOf( blockId );
		if ( enabled ) {
			if ( visibleIndex === -1 && hiddenIndex === -1 ) {
				visible.push( blockId );
			}
			return;
		}

		if ( visibleIndex !== -1 ) {
			visible.splice( visibleIndex, 1 );
		}
		if ( hiddenIndex === -1 ) {
			hidden.push( blockId );
		}
	};

	setBlockState( DIGEST_BLOCK_NOT_FOUND, notFoundAlertEnabled );
	setBlockState( DIGEST_BLOCK_BROKEN_LINK, brokenLinkAlertEnabled );

	return {
		visibleBlocks: visible,
		hiddenBlocks: hidden,
	};
};

function toBoolean( value: unknown, fallback = false ): boolean {
	if ( typeof value === 'boolean' ) {
		return value;
	}
	if ( typeof value === 'number' ) {
		return value === 1;
	}
	if ( typeof value === 'string' ) {
		const lowered = value.toLowerCase();
		if ( [ '1', 'true', 'yes', 'on' ].includes( lowered ) ) {
			return true;
		}
		if ( [ '0', 'false', 'no', 'off', '' ].includes( lowered ) ) {
			return false;
		}
	}
	return fallback;
}

function sanitizeString( value: unknown, fallback = '' ): string {
	if ( typeof value !== 'string' ) {
		return fallback;
	}

	const trimmed = value.trim();
	return trimmed === '' ? fallback : trimmed;
}

const CONTENT_BLOCK_KEYS: ContentBlockKey[] = [ 'toc', 'breadcrumbs', 'relatedPosts', 'topicCluster' ];

function normalizeContentBlockOrder( raw: unknown ): ContentBlockKey[] {
	const order: ContentBlockKey[] = [];
	if ( Array.isArray( raw ) ) {
		for ( const key of raw ) {
			if ( CONTENT_BLOCK_KEYS.includes( key as ContentBlockKey ) && ! order.includes( key as ContentBlockKey ) ) {
				order.push( key as ContentBlockKey );
			}
		}
	}
	for ( const key of CONTENT_BLOCK_KEYS ) {
		if ( ! order.includes( key ) ) {
			order.push( key );
		}
	}
	return order;
}

function sanitizeColor( value: unknown, fallback: string ): string {
	if ( typeof value !== 'string' ) {
		return fallback;
	}

	const trimmed = value.trim();
	if ( trimmed === '' ) {
		return fallback;
	}

	if ( /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test( trimmed ) ) {
		return trimmed;
	}

	if ( trimmed.toLowerCase() === 'transparent' ) {
		return 'transparent';
	}

	if (
		/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i.test(
			trimmed,
		)
	) {
		return trimmed;
	}

	return fallback;
}

function sanitizeFontSize( value: unknown, fallback: number ): number {
	const parsed = Number.parseInt( String( value ), 10 );
	const normalized = Number.isFinite( parsed ) ? parsed : fallback;
	return Math.min( 24, Math.max( 10, normalized ) );
}

const clampScoreWeight = ( value: number ): number => {
	const numeric = Number.isFinite( value ) ? value : SCORE_WEIGHT_MIN;
	return Math.max( SCORE_WEIGHT_MIN, Math.min( SCORE_WEIGHT_MAX, numeric ) );
};

const normalizeScoreOverrides = (
	value: RawScoreCalculatorSettings['rules'],
): Record<string, number> => {
	const normalized: Record<string, number> = {};
	if ( ! value || typeof value !== 'object' ) {
		return normalized;
	}

	Object.entries( value ).forEach( ( [ id, weight ] ) => {
		if ( typeof id !== 'string' ) {
			return;
		}

		const numeric = Number( weight );
		if ( Number.isNaN( numeric ) ) {
			return;
		}

		const trimmedId = id.trim();
		if ( '' === trimmedId ) {
			return;
		}

		normalized[ trimmedId ] = clampScoreWeight( numeric );
	} );

	return normalized;
};

const normalizeScoreCustomRules = (
	value: RawScoreCalculatorSettings['custom_rules'],
): Record<string, Record<string, number>> => {
	const normalized: Record<string, Record<string, number>> = {};
	if ( ! value || typeof value !== 'object' ) {
		return normalized;
	}

	Object.entries( value ).forEach( ( [ ruleId, rawFields ] ) => {
		if ( typeof ruleId !== 'string' ) {
			return;
		}
		const trimmedRuleId = ruleId.trim();
		if ( '' === trimmedRuleId || ! rawFields || typeof rawFields !== 'object' ) {
			return;
		}

		const fields: Record<string, number> = {};
		Object.entries( rawFields as Record<string, unknown> ).forEach(
			( [ key, rawValue ] ) => {
				if ( typeof key !== 'string' ) {
					return;
				}
				const trimmedKey = key.trim();
				if ( '' === trimmedKey ) {
					return;
				}

				const numeric = Number( rawValue );
				if ( Number.isNaN( numeric ) ) {
					return;
				}

				fields[ trimmedKey ] = numeric;
			},
		);

		if ( Object.keys( fields ).length > 0 ) {
			normalized[ trimmedRuleId ] = fields;
		}
	} );

	return normalized;
};

const IMAGE_SEO_DEFAULTS: ImageSeoSettings = {
	alt: {
		enabled: true,
		format: '%filename%',
	},
	title: {
		enabled: true,
		format: '%title% %counter%',
	},
	separator: '–',
	customTokens: {
		custom1: '',
		custom2: '',
		custom3: '',
	},
};

const config = window.airygenAdmin ?? {};
const adminLocale =
	typeof config.locale === 'string' && config.locale.trim() ? config.locale.trim() : 'en_US';
const configuredAdminPages = Array.isArray( config.pageRegistry )
	? config.pageRegistry
		.filter(
			(
				page,
			): page is {
				key: string;
				slug: string;
				title: string;
				order: number;
			} =>
				typeof page?.key === 'string' &&
				page.key !== '' &&
				typeof page?.slug === 'string' &&
				page.slug !== '' &&
				typeof page?.title === 'string' &&
				page.title !== '',
		)
		.map( ( page ) => ( {
			key: page.key,
			slug: page.slug,
			title: page.title,
			order: typeof page.order === 'number' ? page.order : 100,
		} ) )
	: [];
const configuredAdminPageKeys = new Set(
	[ 'debug', ...configuredAdminPages.map( ( page ) => page.key ) ],
);
const configuredAdminPageSlugMap = new Map(
	configuredAdminPages.map( ( page ) => [ page.key, page.slug ] ),
);
const CORE_ADMIN_PAGE_KEYS = new Set<ShellPageName>( [
	'dashboard',
	'settings',
	'topicCluster',
	'notify',
	'migration',
] );
const notifyTimezoneOptions: Array<{ value: string; label: string }> = Array.isArray( config.notifyTimezones )
	? config.notifyTimezones.filter(
		(
			option,
		): option is {
			value: string;
			label: string;
		} =>
			typeof option?.value === 'string' &&
			option.value !== '' &&
			typeof option?.label === 'string' &&
			option.label !== '',
	)
	: [];

const restPath = config.restPath ?? '/airygen/v1/settings';
const sessionCheckUrl =
	( typeof config.sessionCheckUrl === 'string' && config.sessionCheckUrl ) ||
	'/wp-json/airygen/v1/session-check';
const restRoot =
	( typeof config.restRoot === 'string' && config.restRoot ) ||
	( typeof window !== 'undefined' &&
		typeof window.wpApiSettings?.root === 'string' &&
		window.wpApiSettings.root ) ||
	'/wp-json/';
const restNonce =
	( typeof config.nonce === 'string' && config.nonce ) ||
	( typeof window !== 'undefined' &&
		typeof window.wpApiSettings?.nonce === 'string' &&
		window.wpApiSettings.nonce ) ||
	'';
let restAuthLocked = false;
const isRestAuthError = ( error: unknown ) => isSessionExpiredRestError( error );
const SESSION_EXPIRED_MESSAGE = __( 'Permission expired. Please log in again.', 'airygen-seo' );
const migrationBase = restPath.endsWith( '/settings' )
	? restPath.slice( 0, -'/settings'.length )
	: '/airygen/v1';
const logoUrl = config.assets?.logo ?? '';
const initialPageFromConfig =
	typeof config.initialPage === 'string' && configuredAdminPageKeys.has( config.initialPage )
		? config.initialPage
		: 'dashboard';
const debugRestPath = config.debugRestPath ?? '/airygen/v1/debug';
const debugEnablePath = config.debugEnablePath ?? '/airygen/v1/debug/enable';
const debugDisablePath = config.debugDisablePath ?? '/airygen/v1/debug/disable';
const debugEditorPath = config.debugEditorPath ?? '/airygen/v1/debug/editor-mode';
const debugLevelPath = config.debugLevelPath ?? '/airygen/v1/debug/level';
const MIGRATION_TAB_KEYS = [ 'yoast', 'rankmath', 'aioseo', 'seopress' ] as const;
const NOTIFY_TAB_KEYS = [ 'notifyDigest', 'notifyEmail', 'notifyTelegram', 'notifyDiscord', 'notifyTeams' ] as const;
const NOTIFY_HOME_TAB = 'notifyHome' as const;
type MigrationTabKey = ( typeof MIGRATION_TAB_KEYS )[ number ];
type NotifyTabKey = ( typeof NOTIFY_TAB_KEYS )[ number ];

const isMigrationTabKey = ( value: string ): value is MigrationTabKey =>
	( MIGRATION_TAB_KEYS as readonly string[] ).includes( value );
const isNotifyTabKey = ( value: string ): value is NotifyTabKey =>
	( NOTIFY_TAB_KEYS as readonly string[] ).includes( value );

const SETTINGS_HASH_TABS = [
	'onPageSeo',
	'social',
	'schema',
	'breadcrumbs',
	'robots',
	'toc',
	'imageSeo',
	'hreflang',
	'sitemap',
	'codeSnippetManager',
	'notFoundManager',
	'siteVerification',
	'rssFeedSignature',
	'siteHealth',
	'linkCounter',
	'scoreCalculator',
	'brokenLinkChecker',
	'redirects',
	'linkSuggestions',
	'relatedPosts',
	'instantIndexing',
	'authorSeo',
	'taxonomySeo',
	'wooCommerceSeo',
	'localSeo',
	'markdownForAgents',
	'llmsTxt',
] as const;

const isSettingsTabHash = ( value: string ): boolean =>
	( SETTINGS_HASH_TABS as readonly string[] ).includes( value );

type HashRoute = {
	page: ShellPageName;
	tab?: string;
};

const parseHashRoute = (): HashRoute | null => {
	if ( typeof window === 'undefined' ) {
		return null;
	}

	const rawHash = window.location.hash.replace( /^#/, '' ).trim();
	if ( rawHash === '' ) {
		return null;
	}

	const segments = rawHash.split( '/' ).map( ( segment ) => segment.trim() );
	const page = segments[ 0 ];
	const tab = segments[ 1 ];
	if ( page === 'dashboard' || page === 'topicCluster' || page === 'debug' ) {
		return { page };
	}
	if ( page === 'notify' ) {
		if ( tab && isNotifyTabKey( tab ) ) {
			return { page, tab };
		}
		return { page };
	}
	if ( page === 'settings' ) {
		if ( tab && isSettingsTabHash( tab ) ) {
			return { page, tab };
		}
		return { page };
	}
	if ( page === 'migration' ) {
		if ( tab && isMigrationTabKey( tab ) ) {
			return { page, tab };
		}
		return { page };
	}
	if ( configuredAdminPageKeys.has( page ) ) {
		return { page };
	}

	return null;
};
const initialHashRoute = parseHashRoute();
const initialPage = initialHashRoute?.page ?? initialPageFromConfig;
const initialMigrationView = ( () => {
	if ( initialHashRoute?.page === 'migration' && initialHashRoute.tab && isMigrationTabKey( initialHashRoute.tab ) ) {
		return initialHashRoute.tab;
	}
	if ( typeof window === 'undefined' ) {
		return 'list' as const;
	}

	const params = new URLSearchParams( window.location.search );
	const tab = params.get( 'tab' );
	if ( tab === 'yoast' ) {
		return 'yoast';
	}
	if ( tab === 'rankmath' ) {
		return 'rankmath';
	}
	if ( tab === 'aioseo' ) {
		return 'aioseo';
	}
	if ( tab === 'seopress' ) {
		return 'seopress';
	}
	return 'list';
} )();

const initialNotifyTab = ( () => {
	if ( initialHashRoute?.page === 'notify' && initialHashRoute.tab && isNotifyTabKey( initialHashRoute.tab ) ) {
		return initialHashRoute.tab;
	}
	if ( typeof window === 'undefined' ) {
		return NOTIFY_HOME_TAB;
	}
	const params = new URLSearchParams( window.location.search );
	const tab = params.get( 'tab' );
	return tab !== null && isNotifyTabKey( tab ) ? tab : NOTIFY_HOME_TAB;
} )();
const initialSettingsTab = ( () => {
	if ( typeof config.initialSettingsTab === 'string' && isSettingsTabHash( config.initialSettingsTab ) ) {
		return config.initialSettingsTab;
	}
	if ( initialHashRoute?.page === 'settings' && initialHashRoute.tab && isSettingsTabHash( initialHashRoute.tab ) ) {
		return initialHashRoute.tab;
	}
	if ( typeof window === 'undefined' ) {
		return '';
	}
	const params = new URLSearchParams( window.location.search );
	const tab = params.get( 'tab' );
	return tab !== null && isSettingsTabHash( tab ) ? tab : '';
} )();

apiFetch.use( apiFetch.createRootURLMiddleware( restRoot ) );

if ( restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( restNonce ) );
}
apiFetch.use( ( options, next ) => {
	if ( restAuthLocked || isSessionExpiredLocked( 'admin' ) ) {
		return Promise.reject( {
			code: 'rest_not_logged_in',
			message: __( 'REST authentication expired. Please refresh and log in again.', 'airygen-seo' ),
			data: { status: 403 },
		} );
	}

	return next( options ).catch( ( error ) => {
		if ( isRestAuthError( error ) ) {
			restAuthLocked = true;
			lockSessionExpired( 'admin' );
		}

		return Promise.reject( error );
	} );
} );

const generateId = () => {
	if ( typeof crypto !== 'undefined' && 'randomUUID' in crypto ) {
		return crypto.randomUUID();
	}

	return `airygen-${ Date.now() }-${ Math.random().toString( 36 ).slice( 2, 10 ) }`;
};

const MODULE_METADATA: ModuleMetadata[] = [
	{
		key: 'onPageSeo',
		title: __( 'On-Page SEO', 'airygen-seo' ),
		description: __( 'Control which metadata tags Airygen emits and how titles incorporate your site name.', 'airygen-seo' ),
		icon: OnPageSeoIcon,
		traits: {
			markup: true,
			sidebar: true,
		},
	},
	{
		key: 'social',
		title: __( 'Social Media Tags', 'airygen-seo' ),
		description: __( 'Manage Open Graph and Twitter defaults.', 'airygen-seo' ),
		icon: SocialCardsIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'schema',
		title: __( 'Schema Markup', 'airygen-seo' ),
		description: __( 'Configure JSON-LD output for organization, website, breadcrumbs, and articles.', 'airygen-seo' ),
		icon: SchemaMarkupIcon,
		traits: {
			markup: true,
			sidebar: true,
		},
	},
	{
		key: 'authorSeo',
		title: __( 'Author SEO', 'airygen-seo' ),
		description: __( 'Control author archive metadata, indexing, and author schema output.', 'airygen-seo' ),
		icon: AuthorSeoIcon,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'taxonomySeo',
		title: __( 'Taxonomy SEO', 'airygen-seo' ),
		description: __( 'Manage category, tag, and taxonomy archive metadata.', 'airygen-seo' ),
		icon: SitemapIcon,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'wooCommerceSeo',
		title: __( 'WooCommerce SEO', 'airygen-seo' ),
		description: __( 'Add product-specific SEO templates and rely on WooCommerce native Product schema.', 'airygen-seo' ),
		icon: WooCommerceSeoIcon,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'localSeo',
		title: __( 'Local SEO', 'airygen-seo' ),
		description: __( 'Publish LocalBusiness schema, geo meta tags, and reusable business info shortcodes.', 'airygen-seo' ),
		icon: LocalSeoIcon,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'breadcrumbs',
		title: __( 'Breadcrumbs', 'airygen-seo' ),
		description: __( 'Display breadcrumb trails in templates and expose a matching Schema graph.', 'airygen-seo' ),
		icon: BreadcrumbsIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'toc',
		title: __( 'Table of Contents', 'airygen-seo' ),
		description: __( 'Generate a linked outline of headings inside your content.', 'airygen-seo' ),
		icon: TocIcon,
		traits: {
			markup: true,
			sidebar: true,
		},
	},
	{
		key: 'topicCluster',
		title: __( 'Topic Cluster', 'airygen-seo' ),
		description: __( 'Organize pillar, cluster, and support content relationships.', 'airygen-seo' ),
		icon: TopicClusterIcon,
		traits: {
			sidebar: true,
			tool: true,
		},
	},
	{
		key: 'robots',
		title: __( 'Robots Control', 'airygen-seo' ),
		description: __( 'Set default robots meta directives and extend WordPress\' robots.txt.', 'airygen-seo' ),
		icon: RobotsIcon,
		traits: {
			markup: true,
			sidebar: true,
		},
	},
	{
		key: 'imageSeo',
		title: __( 'Image SEO', 'airygen-seo' ),
		description: __( 'Automatically fill missing image alt/title attributes with custom templates.', 'airygen-seo' ),
		icon: ImageSeoIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'hreflang',
		title: __( 'Language Versions', 'airygen-seo' ),
		description: __( 'Help search engines pick the right language URL for each visitor.', 'airygen-seo' ),
		icon: HreflangIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'sitemap',
		title: __( 'Sitemap', 'airygen-seo' ),
		description: __( 'Manage the sitemap index and exposed post types. When enabled, Airygen SEO replaces WordPress’ default sitemap.', 'airygen-seo' ),
		icon: SitemapIcon,
		traits: {
			tool: true,
		},
	},
	{
		key: 'codeSnippetManager',
		title: __( 'Code Snippets', 'airygen-seo' ),
		description: __( 'Manage custom inline JavaScript snippets for your site.', 'airygen-seo' ),
		icon: CodeSnippetsIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'siteVerification',
		title: __( 'Site Verification', 'airygen-seo' ),
		description: __( 'Add site verification tokens for search engines and webmaster platforms.', 'airygen-seo' ),
		icon: SiteVerificationIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'rssFeedSignature',
		title: __( 'RSS Feed Signature', 'airygen-seo' ),
		description: __( 'Add custom signature text before and after RSS feed entries.', 'airygen-seo' ),
		icon: SitemapIcon,
		traits: {
			markup: true,
		},
	},
	{
		key: 'instantIndexing',
		title: __( 'Instant Indexing', 'airygen-seo' ),
		description: __( 'Notify IndexNow partners when URLs change and manage quota/backfill jobs.', 'airygen-seo' ),
		icon: InstantIndexingIcon,
		hasSettings: true,
		traits: {
			background: true,
			tool: true,
		},
	},
	{
		key: 'linkCounter',
		title: __( 'Link Counter', 'airygen-seo' ),
		description: __( 'Track internal, external, and incoming links with automated rescans.', 'airygen-seo' ),
		icon: LinkCounterIcon,
		hasSettings: true,
		traits: {
			background: true,
			tool: true,
		},
	},
	{
		key: 'linkSuggestions',
		title: __( 'Link Suggestions', 'airygen-seo' ),
		description: __( 'Serve up smart internal link suggestions based on keyphrases.', 'airygen-seo' ),
		icon: LinkCounterIcon,
		hasSettings: true,
		traits: {
			background: true,
			tool: true,
		},
	},
	{
		key: 'relatedPosts',
		title: __( 'Related Posts', 'airygen-seo' ),
		description: __( 'Based on indexed content and taxonomies, automatically displays a related posts list inside articles.', 'airygen-seo' ),
		icon: LinkCounterIcon,
		hasSettings: true,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'notFoundManager',
		title: sprintf(
			/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
			__( '%s Manager', 'airygen-seo' ),
			'404',
		),
		description: __( 'Collect 404 requests, monitor missing URLs, and define fallback behaviors.', 'airygen-seo' ),
		icon: RedirectsIcon,
		hasSettings: true,
		traits: {
			background: true,
			tool: true,
		},
	},
	{
		key: 'notify',
		title: __( 'Alerts', 'airygen-seo' ),
		description: __( 'Send daily digest notifications via Email, Telegram, Discord, or Teams.', 'airygen-seo' ),
		icon: NotifyModuleIcon,
		hasSettings: true,
		traits: {
			background: true,
			tool: true,
		},
	},
	{
		key: 'markdownForAgents',
		title: __( 'Markdown for Agents', 'airygen-seo' ),
		description: __( 'Expose clean markdown output from published content for AI clients.', 'airygen-seo' ),
		icon: MarkdownForAgentsIcon,
		hasSettings: true,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'llmsTxt',
		title: __( 'LLMs.txt', 'airygen-seo' ),
		description: __( 'An important GEO factor. Configure it carefully to improve the chance of being recommended by LLMs.', 'airygen-seo' ),
		icon: LlmsTxtIcon,
		hasSettings: true,
		traits: {
			markup: true,
			tool: true,
		},
	},
	{
		key: 'siteHealth',
		title: __( 'Sitewide SEO', 'airygen-seo' ),
		description: __( 'Check basic sitewide SEO health and configuration.', 'airygen-seo' ),
		icon: SiteHealthIcon,
		hasSettings: true,
		traits: {
			tool: true,
		},
	},
	{
		key: 'scoreCalculator',
		title: __( 'Score Calculator', 'airygen-seo' ),
		description: __( 'Power the in-editor SEO score panel and REST endpoint.', 'airygen-seo' ),
		icon: ScoreCalculatorIcon,
		hasSettings: true,
		traits: {
			sidebar: true,
			tool: true,
		},
	},
	{
		key: 'brokenLinkChecker',
		title: __( 'Broken Link Checker', 'airygen-seo' ),
		description: __( 'Monitor outbound URLs for errors once link counting completes.', 'airygen-seo' ),
		icon: BrokenLinkCheckerIcon,
		hasSettings: true,
		traits: {
			background: true,
			tool: true,
		},
	},
	{
		key: 'redirects',
		title: __( 'Redirects', 'airygen-seo' ),
		description: __( 'Create and prioritize redirect rules for your site.', 'airygen-seo' ),
		icon: RedirectsIcon,
		traits: {
			tool: true,
		},
	},
];

const PANEL_KEYS: PanelKey[] = [
	'scoreCalculator',
	'serpSnippet',
	'keyphrases',
	'canonical',
	'schemaMarkup',
	'robots',
	'toc',
	'linkSuggestions',
	'promptsForAgents',
	'topicCluster',
];

const PANEL_METADATA: PanelMetadata[] = [
	{
		key: 'serpSnippet',
		title: __( 'SERP Snippet', 'airygen-seo' ),
		description: __( 'Preview how your page might appear in search results.', 'airygen-seo' ),
		relatedModule: 'onPageSeo',
	},
	{
		key: 'keyphrases',
		title: __( 'Keyphrases', 'airygen-seo' ),
		description: __( 'Set target keywords to analyze content relevance.', 'airygen-seo' ),
		relatedModule: 'onPageSeo',
	},
	{
		key: 'canonical',
		title: __( 'Canonical URL', 'airygen-seo' ),
		description: __( 'Define the authoritative URL for this content.', 'airygen-seo' ),
		relatedModule: 'onPageSeo',
	},
	{
		key: 'robots',
		title: __( 'Robots Meta', 'airygen-seo' ),
		description: __( 'Control indexing and following behavior for this specific post.', 'airygen-seo' ),
		relatedModule: 'robots',
	},
	{
		key: 'scoreCalculator',
		title: __( 'Content Score', 'airygen-seo' ),
		description: __( 'Analyze content quality based on SEO rules.', 'airygen-seo' ),
		relatedModule: 'scoreCalculator',
	},
	{
		key: 'linkSuggestions',
		title: __( 'Link Suggestions', 'airygen-seo' ),
		description: __( 'See relevant internal links while you edit.', 'airygen-seo' ),
		relatedModule: 'linkSuggestions',
	},
	{
		key: 'promptsForAgents',
		title: __( 'Prompts for Agents', 'airygen-seo' ),
		description: __( 'Add prompt notes, instructions, and context for LLM agents.', 'airygen-seo' ),
		relatedModule: 'markdownForAgents',
	},
	{
		key: 'schemaMarkup',
		title: __( 'Schema', 'airygen-seo' ),
		description: __( 'Customize structured data for this post.', 'airygen-seo' ),
		relatedModule: 'schema',
	},
	{
		key: 'toc',
		title: __( 'Table of Contents', 'airygen-seo' ),
		description: __( 'Control per-post TOC output and insert helpers.', 'airygen-seo' ),
		relatedModule: 'toc',
	},
	{
		key: 'topicCluster',
		title: __( 'Topic Cluster', 'airygen-seo' ),
		description: __( 'Assign pillar and cluster relationships while editing.', 'airygen-seo' ),
		relatedModule: 'topicCluster',
	},
];

const MODULE_KEYS: ModuleKey[] = [
	'onPageSeo',
	'social',
	'schema',
	'breadcrumbs',
	'robots',
	'toc',
	'topicCluster',
	'authorSeo',
	'taxonomySeo',
	'wooCommerceSeo',
	'localSeo',
	'imageSeo',
	'hreflang',
	'sitemap',
	'codeSnippetManager',
	'siteVerification',
	'rssFeedSignature',
	'instantIndexing',
	'linkCounter',
	'siteHealth',
	'scoreCalculator',
	'brokenLinkChecker',
	'redirects',
	'linkSuggestions',
	'relatedPosts',
	'notFoundManager',
	'notify',
	'markdownForAgents',
	'llmsTxt',
];

const isModuleKey = ( value: string ): value is ModuleKey =>
	( MODULE_KEYS as string[] ).includes( value );

const isPanelKey = ( value: string ): value is PanelKey =>
	( PANEL_KEYS as string[] ).includes( value );

const normalizeModuleOrder = ( value: unknown ): ModuleKey[] => {
	const order: ModuleKey[] = [];

	if ( Array.isArray( value ) ) {
		value.forEach( ( entry ) => {
			if ( typeof entry === 'string' && isModuleKey( entry ) && ! order.includes( entry ) ) {
				order.push( entry );
			}
		} );
	}

	MODULE_KEYS.forEach( ( key ) => {
		if ( ! order.includes( key ) ) {
			order.push( key );
		}
	} );

	return order;
};

const normalizePanelOrder = ( value: unknown ): PanelKey[] => {
	const order: PanelKey[] = [];

	if ( Array.isArray( value ) ) {
		value.forEach( ( entry ) => {
			if ( typeof entry === 'string' && isPanelKey( entry ) && ! order.includes( entry ) ) {
				order.push( entry );
			}
		} );
	}

	PANEL_KEYS.forEach( ( key ) => {
		if ( ! order.includes( key ) ) {
			order.push( key );
		}
	} );

	return order;
};

const normalizePanelVisibility = (
	value: unknown,
): Record<PanelKey, boolean> => {
	const defaults = PANEL_KEYS.reduce<Record<PanelKey, boolean>>( ( acc, key ) => {
		acc[ key ] = true;
		return acc;
	}, {} as Record<PanelKey, boolean> );

	if ( ! value || typeof value !== 'object' ) {
		return defaults;
	}

	PANEL_KEYS.forEach( ( key ) => {
		if ( key in ( value as Record<string, unknown> ) ) {
			defaults[ key ] = Boolean( ( value as Record<string, unknown> )[ key ] );
		}
	} );

	return defaults;
};

const MODULE_DEFAULTS: ModuleSettings = {
	onPageSeo: true,
	social: true,
	schema: true,
	breadcrumbs: true,
	robots: true,
	toc: true,
	topicCluster: true,
	authorSeo: true,
	taxonomySeo: true,
	wooCommerceSeo: true,
	localSeo: true,
	imageSeo: true,
	hreflang: true,
	sitemap: true,
	codeSnippetManager: true,
	siteVerification: true,
	rssFeedSignature: true,
	instantIndexing: true,
	linkCounter: true,
	siteHealth: true,
	scoreCalculator: true,
	brokenLinkChecker: true,
	redirects: true,
	linkSuggestions: true,
	relatedPosts: true,
	notFoundManager: true,
	notify: true,
	markdownForAgents: true,
	llmsTxt: true,
};

const SETTINGS_RESET_SECTION_BY_TAB: Partial<Record<ModuleKey, keyof SettingsState>> = {
	onPageSeo: 'onPageSeo',
	social: 'socialCards',
	authorSeo: 'authorSeo',
	taxonomySeo: 'taxonomySeo',
	wooCommerceSeo: 'wooCommerceSeo',
	localSeo: 'localSeo',
	schema: 'schemaMarkup',
	breadcrumbs: 'breadcrumbs',
	toc: 'toc',
	robots: 'robots',
	imageSeo: 'imageSeo',
	hreflang: 'hreflang',
	sitemap: 'sitemap',
	codeSnippetManager: 'codeSnippetManager',
	siteVerification: 'siteVerification',
	rssFeedSignature: 'rssFeedSignature',
	instantIndexing: 'instantIndexing',
	relatedPosts: 'relatedPosts',
	notFoundManager: 'notFoundManager',
	notify: 'notify',
	markdownForAgents: 'markdownForAgents',
	llmsTxt: 'llmsTxt',
	scoreCalculator: 'scoreCalculator',
	brokenLinkChecker: 'brokenLinkChecker',
	redirects: 'redirects',
};

const cloneDeep = <T, >( value: T ): T => JSON.parse( JSON.stringify( value ) ) as T;

const normalizeSettings = ( payload: Record<string, unknown> ): SettingsState => {
	const raw = payload as RawSettingsPayload;
	const robotsPayload = ( raw.robots ?? {} ) as RawRobotsSettings;
	const hreflangPayload = ( raw.hreflang ?? {} ) as RawHreflangSettings;
	const redirectsPayload = ( raw.redirects ?? {} ) as RawRedirectSettings;
	const modulesPayload = ( raw.modules ?? {} ) as RestModuleSettings;
	const brokenLinkPayload = ( raw.brokenLinkChecker ?? {} ) as RawBrokenLinkCheckerSettings;
	const rawImageSeo = ( raw.imageSeo ?? {} ) as RawImageSeoSettings;
	const rawOnPageSeo = ( raw.onPageSeo ?? {} ) as RawOnPageSeoSettings;
	const rawBreadcrumbs = ( raw.breadcrumbs ?? {} ) as RawBreadcrumbsSettings;
	const rawToc = ( raw.toc ?? {} ) as RawTocSettings;
	const rawScoreCalculator = ( raw.scoreCalculator ?? {} ) as RawScoreCalculatorSettings;
	const rawTopicCluster = ( raw.topicCluster ?? {} ) as RawTopicClusterSettings;
	const rawAuthorSeo = ( raw.authorSeo ?? {} ) as RawAuthorSeoSettings;
	const rawTaxonomySeo = ( raw.taxonomySeo ?? {} ) as RawTaxonomySeoSettings;
	const rawWooCommerceSeo = ( raw.wooCommerceSeo ?? {} ) as RawWooCommerceSeoSettings;
	const rawLocalSeo = ( raw.localSeo ?? {} ) as RawLocalSeoSettings;
	const rawRelatedPosts = ( raw.relatedPosts ?? {} ) as RawRelatedPostsSettings;
	const rawNotFoundManager = ( raw.notFoundManager ?? {} ) as RawNotFoundManagerSettings;
	const rawNotify = ( raw.notify ?? {} ) as RawNotifySettings;
	const rawMarkdownForAgents = ( raw.markdownForAgents ?? {} ) as RawMarkdownForAgentsSettings;
	const rawLlmsTxt = ( raw.llmsTxt ?? {} ) as RawLlmsTxtSettings;
	const normalizedLocalSeoLayoutOrder = normalizeLocalSeoLayoutOrder( rawLocalSeo?.layout_order );
	const normalizedLocalSeoLayoutGrid = Array.isArray( rawLocalSeo?.layout_grid )
		? normalizeLocalSeoLayoutGrid( rawLocalSeo.layout_grid, normalizedLocalSeoLayoutOrder )
		: buildLocalSeoLayoutGridFromOrder( normalizedLocalSeoLayoutOrder );

	const robotsRules = Array.isArray( robotsPayload?.additional_rules )
		? ( robotsPayload.additional_rules as unknown[] )
			.map( ( rule ) => ( typeof rule === 'string' ? rule : '' ) )
			.filter( ( rule ) => rule !== '' )
		: [];
	const moduleOrder = normalizeModuleOrder( raw.moduleOrder );
	const panelOrder = normalizePanelOrder( raw.panelOrder );
	const panelVisibility = normalizePanelVisibility( raw.panelVisibility );
	const markdownPostTypes = Array.isArray( rawMarkdownForAgents.post_types )
		? rawMarkdownForAgents.post_types
			.map( ( item ) => ( typeof item === 'string' ? item.trim() : '' ) )
			.filter( ( item ) => item !== '' )
		: [];

	const normalizeHreflangEntries = (
		input: RawHreflangSettings['manual_map'] | Record<string, unknown> | undefined,
	): HreflangEntry[] => {
		const toEntry = ( codeValue?: unknown, urlValue?: unknown ): HreflangEntry => ( {
			code: codeValue ? String( codeValue ) : '',
			url: urlValue ? String( urlValue ) : '',
			persisted: Boolean( codeValue ) && Boolean( urlValue ),
		} );

		if ( Array.isArray( input ) ) {
			return input.map( ( entry ) => toEntry( entry?.code, entry?.url ) );
		}

		if ( input && typeof input === 'object' ) {
			return Object.entries( input ).map( ( [ code, url ] ) => toEntry( code, url ) );
		}

		return [];
	};

	const hreflangMap = normalizeHreflangEntries( hreflangPayload.manual_map );

	const breadcrumbs: BreadcrumbsSettings = {
		manualOutputEnabled: ( () => {
			if ( typeof rawBreadcrumbs?.manual_output_enabled !== 'undefined' ) {
				return toBoolean(
					rawBreadcrumbs?.manual_output_enabled,
					BREADCRUMBS_DEFAULTS.manualOutputEnabled,
				);
			}

			return toBoolean(
				rawBreadcrumbs?.enabled,
				BREADCRUMBS_DEFAULTS.manualOutputEnabled,
			);
		} )(),
		autoInjectionEnabled: ( () => {
			if ( typeof rawBreadcrumbs?.auto_injection_enabled !== 'undefined' ) {
				return toBoolean(
					rawBreadcrumbs?.auto_injection_enabled,
					BREADCRUMBS_DEFAULTS.autoInjectionEnabled,
				);
			}

			return toBoolean(
				rawBreadcrumbs?.enabled,
				BREADCRUMBS_DEFAULTS.autoInjectionEnabled,
			);
		} )(),
		injectionPosition: rawBreadcrumbs?.injection_position === 'after_content'
			? 'after_content'
			: BREADCRUMBS_DEFAULTS.injectionPosition,
		separator: sanitizeString(
			rawBreadcrumbs?.separator,
			BREADCRUMBS_DEFAULTS.separator,
		),
		prefix: sanitizeString(
			rawBreadcrumbs?.prefix,
			BREADCRUMBS_DEFAULTS.prefix,
		),
		home: {
			display: toBoolean(
				rawBreadcrumbs?.home?.display,
				BREADCRUMBS_DEFAULTS.home.display,
			),
			label: sanitizeString(
				rawBreadcrumbs?.home?.label,
				BREADCRUMBS_DEFAULTS.home.label,
			),
			url: BREADCRUMBS_DEFAULTS.home.url,
		},
		labels: {
			archive: sanitizeString(
				rawBreadcrumbs?.labels?.archive,
				BREADCRUMBS_DEFAULTS.labels.archive,
			),
			search: sanitizeString(
				rawBreadcrumbs?.labels?.search,
				BREADCRUMBS_DEFAULTS.labels.search,
			),
			error: sanitizeString(
				rawBreadcrumbs?.labels?.error,
				BREADCRUMBS_DEFAULTS.labels.error,
			),
		},
		display: {
			showCurrent: toBoolean(
				rawBreadcrumbs?.display?.showCurrent,
				BREADCRUMBS_DEFAULTS.display.showCurrent,
			),
			showAncestors: toBoolean(
				rawBreadcrumbs?.display?.showAncestors,
				BREADCRUMBS_DEFAULTS.display.showAncestors,
			),
			showBlog: toBoolean(
				rawBreadcrumbs?.display?.showBlog,
				BREADCRUMBS_DEFAULTS.display.showBlog,
			),
			showPagination: toBoolean(
				rawBreadcrumbs?.display?.showPagination,
				BREADCRUMBS_DEFAULTS.display.showPagination,
			),
			hideTaxonomy: toBoolean(
				rawBreadcrumbs?.display?.hideTaxonomy,
				BREADCRUMBS_DEFAULTS.display.hideTaxonomy,
			),
		},
		style: {
			fontSize: sanitizeFontSize(
				rawBreadcrumbs?.style?.fontSize,
				BREADCRUMBS_DEFAULTS.style.fontSize,
			),
			textColor: sanitizeColor(
				typeof rawBreadcrumbs?.style?.textColor === 'string' &&
				rawBreadcrumbs.style.textColor.trim().toLowerCase() === 'transparent'
					? 'transparent'
					: rawBreadcrumbs?.style?.textColor,
				BREADCRUMBS_DEFAULTS.style.textColor,
			),
			linkColor: sanitizeColor(
				typeof rawBreadcrumbs?.style?.linkColor === 'string' &&
				rawBreadcrumbs.style.linkColor.trim().toLowerCase() === 'transparent'
					? 'transparent'
					: rawBreadcrumbs?.style?.linkColor,
				BREADCRUMBS_DEFAULTS.style.linkColor,
			),
			underlineLinks: toBoolean(
				rawBreadcrumbs?.style?.underlineLinks,
				BREADCRUMBS_DEFAULTS.style.underlineLinks,
			),
			borderWidth: Number.isFinite( Number( rawBreadcrumbs?.style?.borderWidth ) )
				? Math.max( 0, Math.min( 10, Number( rawBreadcrumbs?.style?.borderWidth ) ) )
				: BREADCRUMBS_DEFAULTS.style.borderWidth,
			borderColor: sanitizeColor(
				typeof rawBreadcrumbs?.style?.borderColor === 'string' &&
				rawBreadcrumbs.style.borderColor.trim().toLowerCase() === 'transparent'
					? 'transparent'
					: rawBreadcrumbs?.style?.borderColor,
				BREADCRUMBS_DEFAULTS.style.borderColor,
			),
			padding: Number.isFinite( Number( rawBreadcrumbs?.style?.padding ) )
				? Math.max( 0, Math.min( 64, Number( rawBreadcrumbs?.style?.padding ) ) )
				: BREADCRUMBS_DEFAULTS.style.padding,
			bgColor:
				typeof rawBreadcrumbs?.style?.bgColor === 'string' &&
				rawBreadcrumbs.style.bgColor.trim().toLowerCase() === 'transparent'
					? 'transparent'
					: sanitizeColor(
						rawBreadcrumbs?.style?.bgColor,
						BREADCRUMBS_DEFAULTS.style.bgColor,
					),
		},
	};

	const toc: TocSettings = {
		manualOutputEnabled: ( () => {
			if ( typeof rawToc?.manual_output_enabled !== 'undefined' ) {
				return toBoolean( rawToc?.manual_output_enabled, TOC_DEFAULTS.manualOutputEnabled );
			}
			const legacyEnabled = toBoolean( rawToc?.enabled, TOC_DEFAULTS.manualOutputEnabled );
			const legacyMode = sanitizeString( rawToc?.output_mode, 'auto' );
			if ( legacyMode === 'shortcode' || legacyMode === 'block' ) {
				return legacyEnabled;
			}
			return legacyEnabled;
		} )(),
		autoInjectionEnabled: ( () => {
			if ( typeof rawToc?.auto_injection_enabled !== 'undefined' ) {
				return toBoolean( rawToc?.auto_injection_enabled, TOC_DEFAULTS.autoInjectionEnabled );
			}
			const legacyEnabled = toBoolean( rawToc?.enabled, TOC_DEFAULTS.autoInjectionEnabled );
			const legacyMode = sanitizeString( rawToc?.output_mode, 'auto' );
			return legacyMode === 'auto' ? legacyEnabled : false;
		} )(),
		postTypes: Array.isArray( rawToc?.post_types )
			? ( rawToc?.post_types as unknown[] )
				.map( ( slug ) => String( slug ) )
				.filter( Boolean )
			: TOC_DEFAULTS.postTypes,
		levels: Array.isArray( rawToc?.levels )
			? ( rawToc?.levels as unknown[] )
				.map( ( level ) => Number( level ) )
				.filter( ( level ) => Number.isFinite( level ) )
			: TOC_DEFAULTS.levels,
		position:
			rawToc?.position === 'before-content' ||
			rawToc?.position === 'after-first-paragraph'
				? rawToc.position
				: 'after-first-paragraph',
		titleEnabled: toBoolean( rawToc?.title_enabled, TOC_DEFAULTS.titleEnabled ),
		title: sanitizeString( rawToc?.title, TOC_DEFAULTS.title ),
		titleLevel:
			rawToc?.title_level === 'h2' || rawToc?.title_level === 'h4'
				? rawToc.title_level
				: rawToc?.title_level === 'h3'
					? 'h3'
					: TOC_DEFAULTS.titleLevel,
		minHeadings: Number.isFinite( Number( rawToc?.min_headings ) )
			? Math.max( 1, Math.min( 20, Number( rawToc?.min_headings ) ) )
			: TOC_DEFAULTS.minHeadings,
		smoothScroll: toBoolean( rawToc?.smooth_scroll, TOC_DEFAULTS.smoothScroll ),
		anchorPrefix: sanitizeString( rawToc?.anchor_prefix, TOC_DEFAULTS.anchorPrefix ),
		addNumbers: toBoolean( rawToc?.add_numbers, TOC_DEFAULTS.addNumbers ),
		excludeHeadings: sanitizeString( rawToc?.exclude_headings, '' ),
		collapseOnLoad: toBoolean( rawToc?.collapse_on_load, TOC_DEFAULTS.collapseOnLoad ),
		style: {
			preset: ( () => {
				const preset = sanitizeString( rawToc?.style?.preset, TOC_DEFAULTS.style.preset );
				return [ 'minimal', 'card', 'soft', 'accent', 'compact' ].includes( preset )
					? ( preset as TocSettings['style']['preset'] )
					: TOC_DEFAULTS.style.preset;
			} )(),
			borderStyle:
					rawToc?.style?.border_style === 'dashed' ||
					rawToc?.style?.border_style === 'dotted'
						? rawToc.style.border_style
						: 'solid',
			borderColor: sanitizeColor( rawToc?.style?.border_color, TOC_DEFAULTS.style.borderColor ),
			borderRadius: Number.isFinite( Number( rawToc?.style?.border_radius ) )
				? Math.max( 0, Math.min( 50, Number( rawToc?.style?.border_radius ) ) )
				: TOC_DEFAULTS.style.borderRadius,
			bodyContainer: {
				borderWidths: {
					top: Number.isFinite( Number( ( rawToc?.style as { body_container?: { border_width_top?: unknown } } | undefined )?.body_container?.border_width_top ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { body_container?: { border_width_top?: unknown } } | undefined )?.body_container?.border_width_top ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.top ?? 1,
					right: Number.isFinite( Number( ( rawToc?.style as { body_container?: { border_width_right?: unknown } } | undefined )?.body_container?.border_width_right ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { body_container?: { border_width_right?: unknown } } | undefined )?.body_container?.border_width_right ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.right ?? 1,
					bottom: Number.isFinite( Number( ( rawToc?.style as { body_container?: { border_width_bottom?: unknown } } | undefined )?.body_container?.border_width_bottom ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { body_container?: { border_width_bottom?: unknown } } | undefined )?.body_container?.border_width_bottom ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.bottom ?? 1,
					left: Number.isFinite( Number( ( rawToc?.style as { body_container?: { border_width_left?: unknown } } | undefined )?.body_container?.border_width_left ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { body_container?: { border_width_left?: unknown } } | undefined )?.body_container?.border_width_left ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.left ?? 1,
				},
				paddings: {
					top: Number.isFinite( Number( ( rawToc?.style as { body_container?: { padding_top?: unknown } } | undefined )?.body_container?.padding_top ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { padding_top?: unknown } } | undefined )?.body_container?.padding_top ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.top ?? 16,
					right: Number.isFinite( Number( ( rawToc?.style as { body_container?: { padding_right?: unknown } } | undefined )?.body_container?.padding_right ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { padding_right?: unknown } } | undefined )?.body_container?.padding_right ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.right ?? 16,
					bottom: Number.isFinite( Number( ( rawToc?.style as { body_container?: { padding_bottom?: unknown } } | undefined )?.body_container?.padding_bottom ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { padding_bottom?: unknown } } | undefined )?.body_container?.padding_bottom ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.bottom ?? 16,
					left: Number.isFinite( Number( ( rawToc?.style as { body_container?: { padding_left?: unknown } } | undefined )?.body_container?.padding_left ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { padding_left?: unknown } } | undefined )?.body_container?.padding_left ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.left ?? 16,
				},
				margins: {
					top: Number.isFinite( Number( ( rawToc?.style as { body_container?: { margin_top?: unknown } } | undefined )?.body_container?.margin_top ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { margin_top?: unknown } } | undefined )?.body_container?.margin_top ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.top ?? 0,
					right: Number.isFinite( Number( ( rawToc?.style as { body_container?: { margin_right?: unknown } } | undefined )?.body_container?.margin_right ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { margin_right?: unknown } } | undefined )?.body_container?.margin_right ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.right ?? 0,
					bottom: Number.isFinite( Number( ( rawToc?.style as { body_container?: { margin_bottom?: unknown } } | undefined )?.body_container?.margin_bottom ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { margin_bottom?: unknown } } | undefined )?.body_container?.margin_bottom ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.bottom ?? 0,
					left: Number.isFinite( Number( ( rawToc?.style as { body_container?: { margin_left?: unknown } } | undefined )?.body_container?.margin_left ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { body_container?: { margin_left?: unknown } } | undefined )?.body_container?.margin_left ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.left ?? 0,
				},
			},
			tocPadding: Number.isFinite( Number( rawToc?.style?.toc_padding ) )
				? Math.max( 0, Math.min( 48, Number( rawToc?.style?.toc_padding ) ) )
				: TOC_DEFAULTS.style.tocPadding,
			linkColor: sanitizeColor( rawToc?.style?.link_color, TOC_DEFAULTS.style.linkColor ),
			linkSize: Number.isFinite( Number( rawToc?.style?.link_size ) )
				? Math.max( 10, Math.min( 22, Number( rawToc?.style?.link_size ) ) )
				: TOC_DEFAULTS.style.linkSize,
			fontStyle: {
				bold: toBoolean( rawToc?.style?.font_style?.bold, TOC_DEFAULTS.style.fontStyle.bold ),
				italic: toBoolean( rawToc?.style?.font_style?.italic, TOC_DEFAULTS.style.fontStyle.italic ),
				underline: toBoolean( rawToc?.style?.font_style?.underline, TOC_DEFAULTS.style.fontStyle.underline ),
			},
			bgColor: sanitizeColor( rawToc?.style?.bg_color, TOC_DEFAULTS.style.bgColor ),
			headerContainer: {
				borderWidths: {
					top: Number.isFinite( Number( ( rawToc?.style as { header_container?: { border_width_top?: unknown } } | undefined )?.header_container?.border_width_top ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { header_container?: { border_width_top?: unknown } } | undefined )?.header_container?.border_width_top ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.top ?? 0,
					right: Number.isFinite( Number( ( rawToc?.style as { header_container?: { border_width_right?: unknown } } | undefined )?.header_container?.border_width_right ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { header_container?: { border_width_right?: unknown } } | undefined )?.header_container?.border_width_right ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.right ?? 0,
					bottom: Number.isFinite( Number( ( rawToc?.style as { header_container?: { border_width_bottom?: unknown } } | undefined )?.header_container?.border_width_bottom ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { header_container?: { border_width_bottom?: unknown } } | undefined )?.header_container?.border_width_bottom ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.bottom ?? 0,
					left: Number.isFinite( Number( ( rawToc?.style as { header_container?: { border_width_left?: unknown } } | undefined )?.header_container?.border_width_left ) )
						? Math.max( 0, Math.min( 8, Number( ( rawToc?.style as { header_container?: { border_width_left?: unknown } } | undefined )?.header_container?.border_width_left ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.left ?? 0,
				},
				borderRadius: Number.isFinite( Number( ( rawToc?.style as { header_container?: { border_radius?: unknown } } | undefined )?.header_container?.border_radius ) )
					? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { border_radius?: unknown } } | undefined )?.header_container?.border_radius ) ) )
					: TOC_DEFAULTS.style.headerContainer?.borderRadius ?? 0,
				borderStyle: ( () => {
					const value = sanitizeString( ( rawToc?.style as { header_container?: { border_style?: unknown } } | undefined )?.header_container?.border_style, TOC_DEFAULTS.style.headerContainer?.borderStyle ?? 'solid' );
					return value === 'dashed' || value === 'dotted' ? value : 'solid';
				} )(),
				borderColor: sanitizeColor( ( rawToc?.style as { header_container?: { border_color?: unknown } } | undefined )?.header_container?.border_color, TOC_DEFAULTS.style.headerContainer?.borderColor ?? '#e2e8f0' ),
				paddings: {
					top: Number.isFinite( Number( ( rawToc?.style as { header_container?: { padding_top?: unknown } } | undefined )?.header_container?.padding_top ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { padding_top?: unknown } } | undefined )?.header_container?.padding_top ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.top ?? 0,
					right: Number.isFinite( Number( ( rawToc?.style as { header_container?: { padding_right?: unknown } } | undefined )?.header_container?.padding_right ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { padding_right?: unknown } } | undefined )?.header_container?.padding_right ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.right ?? 0,
					bottom: Number.isFinite( Number( ( rawToc?.style as { header_container?: { padding_bottom?: unknown } } | undefined )?.header_container?.padding_bottom ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { padding_bottom?: unknown } } | undefined )?.header_container?.padding_bottom ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.bottom ?? 0,
					left: Number.isFinite( Number( ( rawToc?.style as { header_container?: { padding_left?: unknown } } | undefined )?.header_container?.padding_left ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { padding_left?: unknown } } | undefined )?.header_container?.padding_left ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.left ?? 0,
				},
				bgColor: sanitizeColor( ( rawToc?.style as { header_container?: { bg_color?: unknown } } | undefined )?.header_container?.bg_color, TOC_DEFAULTS.style.headerContainer?.bgColor ?? 'transparent' ),
				margins: {
					top: Number.isFinite( Number( ( rawToc?.style as { header_container?: { margin_top?: unknown } } | undefined )?.header_container?.margin_top ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { margin_top?: unknown } } | undefined )?.header_container?.margin_top ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.top ?? 0,
					right: Number.isFinite( Number( ( rawToc?.style as { header_container?: { margin_right?: unknown } } | undefined )?.header_container?.margin_right ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { margin_right?: unknown } } | undefined )?.header_container?.margin_right ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.right ?? 0,
					bottom: Number.isFinite( Number( ( rawToc?.style as { header_container?: { margin_bottom?: unknown } } | undefined )?.header_container?.margin_bottom ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { margin_bottom?: unknown } } | undefined )?.header_container?.margin_bottom ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.bottom ?? 12,
					left: Number.isFinite( Number( ( rawToc?.style as { header_container?: { margin_left?: unknown } } | undefined )?.header_container?.margin_left ) )
						? Math.max( 0, Math.min( 50, Number( ( rawToc?.style as { header_container?: { margin_left?: unknown } } | undefined )?.header_container?.margin_left ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.left ?? 0,
				},
			},
			headerTitle: {
				fontStyle: {
					bold: toBoolean( ( rawToc?.style as { header_title?: { font_style?: { bold?: unknown } } } | undefined )?.header_title?.font_style?.bold, TOC_DEFAULTS.style.headerTitle?.fontStyle.bold ?? true ),
					italic: toBoolean( ( rawToc?.style as { header_title?: { font_style?: { italic?: unknown } } } | undefined )?.header_title?.font_style?.italic, TOC_DEFAULTS.style.headerTitle?.fontStyle.italic ?? false ),
					underline: toBoolean( ( rawToc?.style as { header_title?: { font_style?: { underline?: unknown } } } | undefined )?.header_title?.font_style?.underline, TOC_DEFAULTS.style.headerTitle?.fontStyle.underline ?? false ),
				},
				color: sanitizeColor( ( rawToc?.style as { header_title?: { color?: unknown } } | undefined )?.header_title?.color, TOC_DEFAULTS.style.headerTitle?.color ?? '#0f172a' ),
				fontSize: Number.isFinite( Number( ( rawToc?.style as { header_title?: { font_size?: unknown } } | undefined )?.header_title?.font_size ) )
					? Math.max( 10, Math.min( 40, Number( ( rawToc?.style as { header_title?: { font_size?: unknown } } | undefined )?.header_title?.font_size ) ) )
					: TOC_DEFAULTS.style.headerTitle?.fontSize ?? 18,
			},
		},
	};

	const topicCluster: TopicClusterSettings = {
		manualOutputEnabled: toBoolean(
			rawTopicCluster?.manual_output_enabled,
			TOPIC_CLUSTER_DEFAULTS.manualOutputEnabled,
		),
		autoInjectionEnabled: toBoolean(
			rawTopicCluster?.auto_injection_enabled,
			TOPIC_CLUSTER_DEFAULTS.autoInjectionEnabled,
		),
		overrideBreadcrumbs: toBoolean(
			rawTopicCluster?.override_breadcrumbs,
			TOPIC_CLUSTER_DEFAULTS.overrideBreadcrumbs,
		),
		overrideWpAdjacent: toBoolean(
			rawTopicCluster?.override_wp_adjacent,
			TOPIC_CLUSTER_DEFAULTS.overrideWpAdjacent,
		),
		insertPosition:
			rawTopicCluster?.insert_position === 'before-content' ||
			rawTopicCluster?.insert_position === 'after-content'
				? rawTopicCluster.insert_position
				: TOPIC_CLUSTER_DEFAULTS.insertPosition,
		postTypes: Array.isArray( rawTopicCluster?.post_types )
			? ( rawTopicCluster?.post_types as unknown[] )
				.map( ( slug ) => String( slug ) )
				.filter( Boolean )
			: TOPIC_CLUSTER_DEFAULTS.postTypes,
		titleEnabled: toBoolean(
			rawTopicCluster?.title_enabled,
			TOPIC_CLUSTER_DEFAULTS.titleEnabled,
		),
		titleText:
			typeof rawTopicCluster?.title_text === 'string' && rawTopicCluster.title_text.trim() !== ''
				? rawTopicCluster.title_text
				: TOPIC_CLUSTER_DEFAULTS.titleText,
		relationTextL1:
			typeof rawTopicCluster?.relation_text_l1 === 'string' && rawTopicCluster.relation_text_l1.trim() !== ''
				? rawTopicCluster.relation_text_l1
				: TOPIC_CLUSTER_DEFAULTS.relationTextL1,
		relationTextL2:
			typeof rawTopicCluster?.relation_text_l2 === 'string' && rawTopicCluster.relation_text_l2.trim() !== ''
				? rawTopicCluster.relation_text_l2
				: TOPIC_CLUSTER_DEFAULTS.relationTextL2,
		relationTextL3:
			typeof rawTopicCluster?.relation_text_l3 === 'string' && rawTopicCluster.relation_text_l3.trim() !== ''
				? rawTopicCluster.relation_text_l3
				: TOPIC_CLUSTER_DEFAULTS.relationTextL3,
		titleLevel:
			rawTopicCluster?.title_level === 'h2' ||
			rawTopicCluster?.title_level === 'h3' ||
			rawTopicCluster?.title_level === 'h4'
				? rawTopicCluster.title_level
				: TOPIC_CLUSTER_DEFAULTS.titleLevel,
		styleType:
			typeof rawTopicCluster?.style_type === 'string' && rawTopicCluster.style_type.trim() !== ''
				? rawTopicCluster.style_type.trim()
				: TOPIC_CLUSTER_DEFAULTS.styleType,
		style: {
			preset:
				typeof rawTopicCluster?.style?.preset === 'string' && rawTopicCluster.style.preset.trim() !== ''
					? rawTopicCluster.style.preset.trim()
					: TOPIC_CLUSTER_DEFAULTS.style.preset,
			showBorder: true,
			borderStyle:
				rawTopicCluster?.style?.border_style === 'dashed' || rawTopicCluster?.style?.border_style === 'dotted'
					? rawTopicCluster.style.border_style
					: TOPIC_CLUSTER_DEFAULTS.style.borderStyle,
			borderColor:
				typeof rawTopicCluster?.style?.border_color === 'string' && rawTopicCluster.style.border_color.trim() !== ''
					? rawTopicCluster.style.border_color.trim()
					: TOPIC_CLUSTER_DEFAULTS.style.borderColor,
			borderWidths: {
				top: Number.isFinite( Number( rawTopicCluster?.style?.border_width_top ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.border_width_top ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.top,
				right: Number.isFinite( Number( rawTopicCluster?.style?.border_width_right ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.border_width_right ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.right,
				bottom: Number.isFinite( Number( rawTopicCluster?.style?.border_width_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.border_width_bottom ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.bottom,
				left: Number.isFinite( Number( rawTopicCluster?.style?.border_width_left ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.border_width_left ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.left,
			},
			borderRadius: Number.isFinite( Number( rawTopicCluster?.style?.border_radius ) )
				? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.border_radius ) ) )
				: TOPIC_CLUSTER_DEFAULTS.style.borderRadius,
			paddings: {
				top: Number.isFinite( Number( rawTopicCluster?.style?.padding_top ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.padding_top ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.top,
				right: Number.isFinite( Number( rawTopicCluster?.style?.padding_right ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.padding_right ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.right,
				bottom: Number.isFinite( Number( rawTopicCluster?.style?.padding_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.padding_bottom ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.bottom,
				left: Number.isFinite( Number( rawTopicCluster?.style?.padding_left ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.padding_left ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.left,
			},
			margins: {
				top: Number.isFinite( Number( rawTopicCluster?.style?.margin_top ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.margin_top ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.top,
				right: Number.isFinite( Number( rawTopicCluster?.style?.margin_right ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.margin_right ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.right,
				bottom: Number.isFinite( Number( rawTopicCluster?.style?.margin_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.margin_bottom ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.bottom,
				left: Number.isFinite( Number( rawTopicCluster?.style?.margin_left ) )
					? Math.max( 0, Math.min( 50, Number( rawTopicCluster?.style?.margin_left ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.left,
			},
			bgColor:
				typeof rawTopicCluster?.style?.bg_color === 'string' && rawTopicCluster.style.bg_color.trim() !== ''
					? rawTopicCluster.style.bg_color.trim()
					: TOPIC_CLUSTER_DEFAULTS.style.bgColor,
			itemTextColor:
				typeof rawTopicCluster?.style?.item_text_color === 'string' && rawTopicCluster.style.item_text_color.trim() !== ''
					? rawTopicCluster.style.item_text_color.trim()
					: TOPIC_CLUSTER_DEFAULTS.style.itemTextColor,
			itemFontSize: Number.isFinite( Number( rawTopicCluster?.style?.item_font_size ) )
				? Math.max( 10, Math.min( 24, Number( rawTopicCluster?.style?.item_font_size ) ) )
				: TOPIC_CLUSTER_DEFAULTS.style.itemFontSize,
			itemBold: toBoolean(
				rawTopicCluster?.style?.item_bold,
				TOPIC_CLUSTER_DEFAULTS.style.itemBold,
			),
			itemItalic: toBoolean(
				rawTopicCluster?.style?.item_italic,
				TOPIC_CLUSTER_DEFAULTS.style.itemItalic,
			),
			itemUnderline: toBoolean(
				rawTopicCluster?.style?.item_underline,
				TOPIC_CLUSTER_DEFAULTS.style.itemUnderline,
			),
			itemListStyle:
				rawTopicCluster?.style?.item_list_style === 'disc' || rawTopicCluster?.style?.item_list_style === 'decimal'
					? rawTopicCluster.style.item_list_style
					: TOPIC_CLUSTER_DEFAULTS.style.itemListStyle,
			itemGap: Number.isFinite( Number( rawTopicCluster?.style?.item_gap ) )
				? Math.max( 0, Math.min( 20, Number( rawTopicCluster?.style?.item_gap ) ) )
				: TOPIC_CLUSTER_DEFAULTS.style.itemGap,
			headerContainer: {
				borderWidths: {
					top: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { border_width_top?: unknown } } | undefined )?.header_container?.border_width_top ) )
						? Math.max( 0, Math.min( 20, Number( ( rawTopicCluster?.style as { header_container?: { border_width_top?: unknown } } | undefined )?.header_container?.border_width_top ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.top ?? 0,
					right: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { border_width_right?: unknown } } | undefined )?.header_container?.border_width_right ) )
						? Math.max( 0, Math.min( 20, Number( ( rawTopicCluster?.style as { header_container?: { border_width_right?: unknown } } | undefined )?.header_container?.border_width_right ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.right ?? 0,
					bottom: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { border_width_bottom?: unknown } } | undefined )?.header_container?.border_width_bottom ) )
						? Math.max( 0, Math.min( 20, Number( ( rawTopicCluster?.style as { header_container?: { border_width_bottom?: unknown } } | undefined )?.header_container?.border_width_bottom ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.bottom ?? 0,
					left: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { border_width_left?: unknown } } | undefined )?.header_container?.border_width_left ) )
						? Math.max( 0, Math.min( 20, Number( ( rawTopicCluster?.style as { header_container?: { border_width_left?: unknown } } | undefined )?.header_container?.border_width_left ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.left ?? 0,
				},
				borderRadius: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { border_radius?: unknown } } | undefined )?.header_container?.border_radius ) )
					? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { border_radius?: unknown } } | undefined )?.header_container?.border_radius ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderRadius ?? 0,
				borderStyle: ( () => {
					const value = sanitizeString( ( rawTopicCluster?.style as { header_container?: { border_style?: unknown } } | undefined )?.header_container?.border_style, TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderStyle ?? 'solid' );
					return value === 'dashed' || value === 'dotted' ? value : 'solid';
				} )(),
				borderColor: sanitizeColor( ( rawTopicCluster?.style as { header_container?: { border_color?: unknown } } | undefined )?.header_container?.border_color, TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderColor ?? '#cbd5e1' ),
				paddings: {
					top: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { padding_top?: unknown } } | undefined )?.header_container?.padding_top ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { padding_top?: unknown } } | undefined )?.header_container?.padding_top ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.top ?? 0,
					right: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { padding_right?: unknown } } | undefined )?.header_container?.padding_right ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { padding_right?: unknown } } | undefined )?.header_container?.padding_right ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.right ?? 0,
					bottom: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { padding_bottom?: unknown } } | undefined )?.header_container?.padding_bottom ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { padding_bottom?: unknown } } | undefined )?.header_container?.padding_bottom ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.bottom ?? 0,
					left: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { padding_left?: unknown } } | undefined )?.header_container?.padding_left ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { padding_left?: unknown } } | undefined )?.header_container?.padding_left ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.left ?? 0,
				},
				bgColor: sanitizeColor( ( rawTopicCluster?.style as { header_container?: { bg_color?: unknown } } | undefined )?.header_container?.bg_color, TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.bgColor ?? '#f8fafc' ),
				margins: {
					top: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { margin_top?: unknown } } | undefined )?.header_container?.margin_top ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { margin_top?: unknown } } | undefined )?.header_container?.margin_top ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.top ?? 0,
					right: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { margin_right?: unknown } } | undefined )?.header_container?.margin_right ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { margin_right?: unknown } } | undefined )?.header_container?.margin_right ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.right ?? 0,
					bottom: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { margin_bottom?: unknown } } | undefined )?.header_container?.margin_bottom ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { margin_bottom?: unknown } } | undefined )?.header_container?.margin_bottom ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.bottom ?? 12,
					left: Number.isFinite( Number( ( rawTopicCluster?.style as { header_container?: { margin_left?: unknown } } | undefined )?.header_container?.margin_left ) )
						? Math.max( 0, Math.min( 50, Number( ( rawTopicCluster?.style as { header_container?: { margin_left?: unknown } } | undefined )?.header_container?.margin_left ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.left ?? 0,
				},
			},
			headerTitle: {
				fontStyle: {
					bold: toBoolean( ( rawTopicCluster?.style as { header_title?: { font_style?: { bold?: unknown } } } | undefined )?.header_title?.font_style?.bold, TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.fontStyle.bold ?? true ),
					italic: toBoolean( ( rawTopicCluster?.style as { header_title?: { font_style?: { italic?: unknown } } } | undefined )?.header_title?.font_style?.italic, TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.fontStyle.italic ?? false ),
					underline: toBoolean( ( rawTopicCluster?.style as { header_title?: { font_style?: { underline?: unknown } } } | undefined )?.header_title?.font_style?.underline, TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.fontStyle.underline ?? false ),
				},
				color: sanitizeColor( ( rawTopicCluster?.style as { header_title?: { color?: unknown } } | undefined )?.header_title?.color, TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.color ?? '#0f172a' ),
				fontSize: Number.isFinite( Number( ( rawTopicCluster?.style as { header_title?: { font_size?: unknown } } | undefined )?.header_title?.font_size ) )
					? Math.max( 10, Math.min( 40, Number( ( rawTopicCluster?.style as { header_title?: { font_size?: unknown } } | undefined )?.header_title?.font_size ) ) )
					: TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.fontSize ?? 18,
			},
		},
	};

	const scoreRules = normalizeScoreOverrides( rawScoreCalculator.rules );
	const scoreCustomRules = normalizeScoreCustomRules( rawScoreCalculator.custom_rules );
	const scoreCalculator: ScoreCalculatorSettings = {
		rules: { ...SCORE_CALCULATOR_DEFAULTS.rules, ...scoreRules },
		postTypes: Array.isArray( rawScoreCalculator.post_types )
			? ( rawScoreCalculator.post_types as unknown[] )
				.map( ( slug ) => String( slug ) )
				.filter( Boolean )
			: Array.isArray( ( rawScoreCalculator as { postTypes?: unknown[] } ).postTypes )
				? ( ( rawScoreCalculator as { postTypes?: unknown[] } ).postTypes as unknown[] )
					.map( ( slug ) => String( slug ) )
					.filter( Boolean )
				: SCORE_CALCULATOR_DEFAULTS.postTypes,
		customRules: {
			...SCORE_CALCULATOR_DEFAULTS.customRules,
			...scoreCustomRules,
			...normalizeScoreCustomRules(
				( rawScoreCalculator as { customRules?: Record<string, unknown> } ).customRules,
			),
		},
	};

	const redirectRules = Array.isArray( redirectsPayload.rules )
		? redirectsPayload.rules.map( ( rule ) => ( {
			id: rule?.id ? String( rule.id ) : generateId(),
			type: isRedirectRuleType( rule?.type ) ? rule.type : 'exact',
			source: rule?.source ? String( rule.source ) : '',
			target: rule?.target ? String( rule.target ) : '',
			status: [ 301, 302, 307, 308 ].includes( Number( rule?.status ) )
				? Number( rule.status )
				: 301,
			enabled:
				typeof rule?.enabled === 'boolean' ? rule.enabled : true,
			note: rule?.note ? String( rule.note ) : '',
		} ) )
		: [];

	const postTypeDefaults: Record<string, string> = {};
	const schemaPayload = ( raw.schemaMarkup ?? {} ) as RawSchemaSettings;

	const rawPostTypeDefaults =
		schemaPayload?.post_type_defaults &&
		typeof schemaPayload.post_type_defaults === 'object'
			? ( schemaPayload.post_type_defaults as Record<string, unknown> )
			: {};

	Object.entries( rawPostTypeDefaults ).forEach( ( [ key, value ] ) => {
		if ( typeof key !== 'string' || typeof value !== 'string' ) {
			return;
		}

		if ( value === '' ) {
			return;
		}

		postTypeDefaults[ key ] = value;
	} );

	const sitemapPayload = ( raw.sitemap ?? {} ) as RawSitemapSettings;
	const codeSnippetManagerPayload = ( raw.codeSnippetManager ?? {} ) as RawCodeSnippetManagerSettings;
	const rawSiteVerification = ( raw.siteVerification ?? {} ) as RawSiteVerificationSettings;
	const rssFeedSignaturePayload = ( raw.rssFeedSignature ?? {} ) as RawRssFeedSignatureSettings;

	const rawSocial = ( raw.socialCards ?? {} ) as RawSocialSettings;
	const rawOg = ( rawSocial?.og ?? {} ) as RawOgSettings;
	const rawTwitter = ( rawSocial?.twitter ?? {} ) as RawTwitterSettings;
	const ogEnabled = Boolean( rawOg?.enabled ?? true );
	const modules: ModuleSettings = { ...MODULE_DEFAULTS };
	const rawInstantIndexing = ( raw.instantIndexing ?? {} ) as RawInstantIndexingSettings;

	const parseNumericSetting = (
		value: unknown,
		fallback: number,
		min: number,
		max: number,
	): number => {
		const coerced =
			typeof value === 'string' && value !== ''
				? Number( value )
				: value;

		const numeric = Number.isFinite( coerced ) ? Number( coerced ) : fallback;
		return Math.min( max, Math.max( min, Math.round( numeric ) ) );
	};

	const normalizedInstantEngines: Record<string, InstantIndexingEngineSettings> =
		{};

	if ( rawInstantIndexing?.engines && typeof rawInstantIndexing.engines === 'object' ) {
		Object.entries( rawInstantIndexing.engines ).forEach( ( [ slug, engineValue ] ) => {
			const typedValue = engineValue as RawInstantIndexingEngineSettings;
			normalizedInstantEngines[ slug ] = {
				enabled: toBoolean( typedValue?.enabled, slug === 'bing' ),
				endpoint:
					typeof typedValue?.endpoint === 'string'
						? typedValue.endpoint
						: '',
			};
		} );
	}

	const instantIndexing: InstantIndexingSettings = {
		enabled: toBoolean(
			rawInstantIndexing?.enabled,
			INSTANT_INDEXING_DEFAULTS.enabled,
		),
		autoSubmit: toBoolean(
			rawInstantIndexing?.auto_submit,
			INSTANT_INDEXING_DEFAULTS.autoSubmit,
		),
		retryCooldownDays: parseNumericSetting(
			rawInstantIndexing?.retry_cooldown_days,
			INSTANT_INDEXING_DEFAULTS.retryCooldownDays,
			1,
			365,
		),
		key:
			typeof rawInstantIndexing?.key === 'string'
				? rawInstantIndexing.key
				: '',
		keyLocation:
			typeof rawInstantIndexing?.key_location === 'string'
				? rawInstantIndexing.key_location
				: '',
		maxEventsPerDay: parseNumericSetting(
			rawInstantIndexing?.max_events_per_day,
			INSTANT_INDEXING_DEFAULTS.maxEventsPerDay,
			0,
			100000,
		),
		batchSize: parseNumericSetting(
			rawInstantIndexing?.batch_size,
			INSTANT_INDEXING_DEFAULTS.batchSize,
			10,
			10000,
		),
		engines: normalizedInstantEngines,
		backfill: {
			postTypes: Array.isArray( rawInstantIndexing?.backfill?.post_types )
				? rawInstantIndexing.backfill.post_types
					.map( ( slug ) => String( slug ) )
					.filter( ( slug ) => slug !== '' )
				: [],
		},
	};

	const coerceModuleValue = ( value: unknown, key: ModuleKey ): boolean =>
		typeof value === 'undefined'
			? MODULE_DEFAULTS[ key ]
			: toBoolean( value, MODULE_DEFAULTS[ key ] );

	const TEMPLATE_LIMIT = 160;

	const normalizeImageAttribute = (
		input: RawImageSeoAttributeSettings | undefined,
		defaults: ImageSeoAttributeSettings,
	): ImageSeoAttributeSettings => {
		const enabled = toBoolean( input?.enabled, defaults.enabled );
		const rawFormat =
			typeof input?.format === 'string' ? input.format.trim() : '';
		const format =
			rawFormat && rawFormat.length > 0
				? rawFormat.slice( 0, TEMPLATE_LIMIT )
				: defaults.format;

		return {
			enabled,
			format,
		};
	};

	const sanitizeTemplateValue = ( value: unknown, fallback: string ): string => {
		if ( typeof value !== 'string' ) {
			return fallback;
		}

		return value.trim().slice( 0, TEMPLATE_LIMIT );
	};

	const sanitizeTemplateGroup = (
		input: unknown,
		defaults: OnPageSeoTemplateGroup,
	): OnPageSeoTemplateGroup => {
		if ( ! input || typeof input !== 'object' ) {
			return { ...defaults };
		}

		const group = input as { title?: unknown; description?: unknown };

		return {
			title: sanitizeTemplateValue( group.title, defaults.title ),
			description: sanitizeTemplateValue(
				group.description,
				defaults.description,
			),
		};
	};

	MODULE_KEYS.forEach( ( key ) => {
		if (
			! Object.prototype.hasOwnProperty.call( modulesPayload, key ) ||
			typeof modulesPayload[ key ] === 'undefined'
		) {
			modules[ key ] = MODULE_DEFAULTS[ key ];
			return;
		}

		modules[ key ] = coerceModuleValue( modulesPayload[ key ], key );
	} );

	const selectedLinkTypes = Array.isArray( brokenLinkPayload?.link_types )
		? ( brokenLinkPayload.link_types as unknown[] )
			.map( ( value ) =>
				typeof value === 'string' ? value.toLowerCase() : '',
			)
			.filter( ( value ) => value === 'external' || value === 'internal' )
		: [];

	const linkTypes: BrokenLinkCheckerSettings['linkTypes'] = {
		external:
			selectedLinkTypes.includes( 'external' ) || selectedLinkTypes.length === 0
				? true
				: false,
		internal: selectedLinkTypes.includes( 'internal' ),
	};

	if ( ! linkTypes.external && ! linkTypes.internal ) {
		linkTypes.external = BROKEN_LINK_CHECKER_DEFAULTS.linkTypes.external;
		linkTypes.internal = BROKEN_LINK_CHECKER_DEFAULTS.linkTypes.internal;
	}

	const brokenLinkChecker: BrokenLinkCheckerSettings = {
		...BROKEN_LINK_CHECKER_DEFAULTS,
		enabled: toBoolean(
			brokenLinkPayload?.enabled,
			BROKEN_LINK_CHECKER_DEFAULTS.enabled,
		),
		enableDailyAlert: toBoolean(
			brokenLinkPayload?.enable_daily_alert,
			BROKEN_LINK_CHECKER_DEFAULTS.enableDailyAlert,
		),
		checkIntervalHours: parseNumericSetting(
			brokenLinkPayload?.check_interval_hours,
			BROKEN_LINK_CHECKER_DEFAULTS.checkIntervalHours,
			1,
			168,
		),
		maxRequestsPerRun: parseNumericSetting(
			brokenLinkPayload?.max_requests_per_run,
			BROKEN_LINK_CHECKER_DEFAULTS.maxRequestsPerRun,
			1,
			50,
		),
		batchDelayMinutes: parseNumericSetting(
			brokenLinkPayload?.batch_delay_minutes,
			BROKEN_LINK_CHECKER_DEFAULTS.batchDelayMinutes,
			1,
			60,
		),
		logRetentionDays: parseNumericSetting(
			brokenLinkPayload?.log_retention_days,
			BROKEN_LINK_CHECKER_DEFAULTS.logRetentionDays,
			1,
			365,
		),
		connectionTimeoutSeconds: parseNumericSetting(
			brokenLinkPayload?.connection_timeout_seconds,
			BROKEN_LINK_CHECKER_DEFAULTS.connectionTimeoutSeconds,
			1,
			30,
		),
		operationTimeoutSeconds: parseNumericSetting(
			brokenLinkPayload?.operation_timeout_seconds,
			BROKEN_LINK_CHECKER_DEFAULTS.operationTimeoutSeconds,
			1,
			120,
		),
		treatRedirectsAsWarning: toBoolean(
			brokenLinkPayload?.treat_redirects_as_warning,
			BROKEN_LINK_CHECKER_DEFAULTS.treatRedirectsAsWarning,
		),
		linkTypes,
	};

	const imageSeparator =
		typeof rawImageSeo?.separator === 'string'
			? rawImageSeo.separator.trim()
			: '';
	const imageCustomTokens = {
		custom1: sanitizeTemplateValue(
			rawImageSeo?.custom_tokens?.custom_1,
			IMAGE_SEO_DEFAULTS.customTokens.custom1,
		),
		custom2: sanitizeTemplateValue(
			rawImageSeo?.custom_tokens?.custom_2,
			IMAGE_SEO_DEFAULTS.customTokens.custom2,
		),
		custom3: sanitizeTemplateValue(
			rawImageSeo?.custom_tokens?.custom_3,
			IMAGE_SEO_DEFAULTS.customTokens.custom3,
		),
	};

	const imageSeo: ImageSeoSettings = {
		alt: normalizeImageAttribute( rawImageSeo?.alt, IMAGE_SEO_DEFAULTS.alt ),
		title: normalizeImageAttribute(
			rawImageSeo?.title,
			IMAGE_SEO_DEFAULTS.title,
		),
		separator:
			imageSeparator !== ''
				? imageSeparator.slice( 0, 10 )
				: IMAGE_SEO_DEFAULTS.separator,
		customTokens: imageCustomTokens,
	};

	const rawTemplates = rawOnPageSeo?.templates;
	const globalTemplates = sanitizeTemplateGroup(
		rawTemplates?.global,
		ONPAGE_SEO_DEFAULTS.templates.global,
	);
	const rawTemplateSeparator =
		typeof rawTemplates?.separator === 'string'
			? rawTemplates.separator.trim()
			: '';
	let legacyBrandingSeparator = '';
	const legacySeparator =
			( rawOnPageSeo as { branding?: { separator?: unknown } } | undefined )?.branding
				?.separator;
	if ( typeof legacySeparator === 'string' ) {
		legacyBrandingSeparator = legacySeparator.trim();
	}
	const customTokens = {
		custom1: sanitizeTemplateValue(
			rawTemplates?.custom_tokens?.custom_1,
			ONPAGE_SEO_DEFAULTS.templates.customTokens.custom1,
		),
		custom2: sanitizeTemplateValue(
			rawTemplates?.custom_tokens?.custom_2,
			ONPAGE_SEO_DEFAULTS.templates.customTokens.custom2,
		),
		custom3: sanitizeTemplateValue(
			rawTemplates?.custom_tokens?.custom_3,
			ONPAGE_SEO_DEFAULTS.templates.customTokens.custom3,
		),
	};
	const postTypeTemplates: Record<string, OnPageSeoTemplateGroup> = {};

	if (
		rawTemplates?.post_types &&
		typeof rawTemplates.post_types === 'object'
	) {
		Object.entries( rawTemplates.post_types ).forEach(
			( [ slug, group ] ) => {
				if ( typeof slug !== 'string' ) {
					return;
				}

				const sanitized = sanitizeTemplateGroup( group, {
					title: '',
					description: '',
				} );

				if (
					sanitized.title === '' &&
					sanitized.description === ''
				) {
					return;
				}

				postTypeTemplates[ slug ] = sanitized;
			},
		);
	}

	const onPageSeo: OnPageSeoSettings = {
		output: {
			title: toBoolean(
				rawOnPageSeo?.output?.title,
				ONPAGE_SEO_DEFAULTS.output.title,
			),
			description: toBoolean(
				rawOnPageSeo?.output?.description,
				ONPAGE_SEO_DEFAULTS.output.description,
			),
			canonical: toBoolean(
				rawOnPageSeo?.output?.canonical,
				ONPAGE_SEO_DEFAULTS.output.canonical,
			),
			robots: toBoolean(
				rawOnPageSeo?.output?.robots,
				ONPAGE_SEO_DEFAULTS.output.robots,
			),
		},
		templates: {
			global: globalTemplates,
			separator: ( () => {
				if ( rawTemplateSeparator !== '' ) {
					return rawTemplateSeparator.slice( 0, 10 );
				}
				if ( legacyBrandingSeparator !== '' ) {
					return legacyBrandingSeparator.slice( 0, 10 );
				}
				return ONPAGE_SEO_DEFAULTS.templates.separator;
			} )(),
			postTypes: postTypeTemplates,
			customTokens,
		},
	};

	const authorSeo: AuthorSeoSettings = {
		enabled: toBoolean( rawAuthorSeo?.enabled, AUTHOR_SEO_DEFAULTS.enabled ),
		noindexAuthorArchives: toBoolean(
			rawAuthorSeo?.noindex_author_archives,
			AUTHOR_SEO_DEFAULTS.noindexAuthorArchives,
		),
		titleTemplate:
			typeof rawAuthorSeo?.title_template === 'string'
				? rawAuthorSeo.title_template.trim() || AUTHOR_SEO_DEFAULTS.titleTemplate
				: AUTHOR_SEO_DEFAULTS.titleTemplate,
		descriptionTemplate:
			typeof rawAuthorSeo?.description_template === 'string'
				? rawAuthorSeo.description_template.trim() || AUTHOR_SEO_DEFAULTS.descriptionTemplate
				: AUTHOR_SEO_DEFAULTS.descriptionTemplate,
		separator:
			typeof rawAuthorSeo?.separator === 'string'
				? rawAuthorSeo.separator.trim().slice( 0, 10 ) || AUTHOR_SEO_DEFAULTS.separator
				: AUTHOR_SEO_DEFAULTS.separator,
		customTokens: {
			custom1:
					rawAuthorSeo?.custom_tokens &&
					typeof rawAuthorSeo.custom_tokens === 'object' &&
					typeof ( rawAuthorSeo.custom_tokens as Record<string, unknown> ).custom1 === 'string'
						? ( ( rawAuthorSeo.custom_tokens as Record<string, unknown> ).custom1 as string ).trim()
						: AUTHOR_SEO_DEFAULTS.customTokens.custom1,
			custom2:
					rawAuthorSeo?.custom_tokens &&
					typeof rawAuthorSeo.custom_tokens === 'object' &&
					typeof ( rawAuthorSeo.custom_tokens as Record<string, unknown> ).custom2 === 'string'
						? ( ( rawAuthorSeo.custom_tokens as Record<string, unknown> ).custom2 as string ).trim()
						: AUTHOR_SEO_DEFAULTS.customTokens.custom2,
			custom3:
					rawAuthorSeo?.custom_tokens &&
					typeof rawAuthorSeo.custom_tokens === 'object' &&
					typeof ( rawAuthorSeo.custom_tokens as Record<string, unknown> ).custom3 === 'string'
						? ( ( rawAuthorSeo.custom_tokens as Record<string, unknown> ).custom3 as string ).trim()
						: AUTHOR_SEO_DEFAULTS.customTokens.custom3,
		},
		socialProfiles: Array.isArray( rawAuthorSeo?.social_profiles )
			? rawAuthorSeo.social_profiles
				.filter( ( value ): value is string => typeof value === 'string' )
				.map( ( value ) => value.trim() )
				.filter( ( value ) => '' !== value )
			: [],
	};

	const taxonomySeo: TaxonomySeoSettings = {
		enabled: toBoolean( rawTaxonomySeo?.enabled, TAXONOMY_SEO_DEFAULTS.enabled ),
		enabledTaxonomies: Array.isArray( rawTaxonomySeo?.enabled_taxonomies )
			? rawTaxonomySeo.enabled_taxonomies
				.map( ( slug ) => String( slug ) )
				.filter( ( slug ) => '' !== slug )
			: TAXONOMY_SEO_DEFAULTS.enabledTaxonomies,
		templates: {
			global: {
				title: sanitizeTemplateValue(
					rawTaxonomySeo?.templates?.global?.title,
					TAXONOMY_SEO_DEFAULTS.templates.global.title,
				),
				description: sanitizeTemplateValue(
					rawTaxonomySeo?.templates?.global?.description,
					TAXONOMY_SEO_DEFAULTS.templates.global.description,
				),
			},
			separator:
				typeof rawTaxonomySeo?.templates?.separator === 'string' &&
				rawTaxonomySeo.templates.separator.trim() !== ''
					? rawTaxonomySeo.templates.separator.trim().slice( 0, 10 )
					: TAXONOMY_SEO_DEFAULTS.templates.separator,
			customTokens: {
				custom1: sanitizeTemplateValue(
					rawTaxonomySeo?.templates?.custom_tokens?.custom_1,
					TAXONOMY_SEO_DEFAULTS.templates.customTokens.custom1,
				),
				custom2: sanitizeTemplateValue(
					rawTaxonomySeo?.templates?.custom_tokens?.custom_2,
					TAXONOMY_SEO_DEFAULTS.templates.customTokens.custom2,
				),
				custom3: sanitizeTemplateValue(
					rawTaxonomySeo?.templates?.custom_tokens?.custom_3,
					TAXONOMY_SEO_DEFAULTS.templates.customTokens.custom3,
				),
			},
		},
	};

	const wooCommerceSeo: WooCommerceSeoSettings = {
		enabled:
			toBoolean( rawWooCommerceSeo?.enabled, WOO_COMMERCE_SEO_DEFAULTS.enabled ) &&
			toBoolean( rawWooCommerceSeo?.enable_schema, true ),
		brandAttribute:
			typeof rawWooCommerceSeo?.brand_attribute === 'string'
				? rawWooCommerceSeo.brand_attribute.trim() || WOO_COMMERCE_SEO_DEFAULTS.brandAttribute
				: WOO_COMMERCE_SEO_DEFAULTS.brandAttribute,
		templates: {
			product: {
				title: sanitizeTemplateValue(
					rawWooCommerceSeo?.templates?.product?.title,
					WOO_COMMERCE_SEO_DEFAULTS.templates.product.title,
				),
				description: sanitizeTemplateValue(
					rawWooCommerceSeo?.templates?.product?.description,
					WOO_COMMERCE_SEO_DEFAULTS.templates.product.description,
				),
			},
			separator:
				typeof rawWooCommerceSeo?.templates?.separator === 'string' &&
				rawWooCommerceSeo.templates.separator.trim() !== ''
					? rawWooCommerceSeo.templates.separator.trim().slice( 0, 10 )
					: WOO_COMMERCE_SEO_DEFAULTS.templates.separator,
			customTokens: {
				custom1: sanitizeTemplateValue(
					rawWooCommerceSeo?.templates?.custom_tokens?.custom_1,
					WOO_COMMERCE_SEO_DEFAULTS.templates.customTokens.custom1,
				),
				custom2: sanitizeTemplateValue(
					rawWooCommerceSeo?.templates?.custom_tokens?.custom_2,
					WOO_COMMERCE_SEO_DEFAULTS.templates.customTokens.custom2,
				),
				custom3: sanitizeTemplateValue(
					rawWooCommerceSeo?.templates?.custom_tokens?.custom_3,
					WOO_COMMERCE_SEO_DEFAULTS.templates.customTokens.custom3,
				),
			},
		},
	};

	const localSeo: LocalSeoSettings = {
		enabled: toBoolean( rawLocalSeo?.enabled, LOCAL_SEO_DEFAULTS.enabled ),
		layoutTemplate:
			rawLocalSeo?.layout_template === 'sidebar_right' ||
			rawLocalSeo?.layout_template === 'sidebar_left' ||
			rawLocalSeo?.layout_template === 'sidebar_left_header' ||
			rawLocalSeo?.layout_template === 'sidebar_right_header'
				? rawLocalSeo.layout_template
				: LOCAL_SEO_DEFAULTS.layoutTemplate,
		layoutShowCardBorder: toBoolean(
			rawLocalSeo?.layout_show_card_border,
			LOCAL_SEO_DEFAULTS.layoutShowCardBorder,
		),
		layoutCardPadding: Number.isFinite( Number( rawLocalSeo?.layout_card_padding ) )
			? Math.max( 0, Math.min( 64, Number( rawLocalSeo?.layout_card_padding ) ) )
			: LOCAL_SEO_DEFAULTS.layoutCardPadding,
		layoutLabelFontSize: Number.isFinite( Number( rawLocalSeo?.layout_label_font_size ) )
			? Math.max( 10, Math.min( 32, Number( rawLocalSeo?.layout_label_font_size ) ) )
			: LOCAL_SEO_DEFAULTS.layoutLabelFontSize,
		layoutLabelColor:
			typeof rawLocalSeo?.layout_label_color === 'string' &&
			rawLocalSeo.layout_label_color.trim() !== ''
				? rawLocalSeo.layout_label_color.trim()
				: LOCAL_SEO_DEFAULTS.layoutLabelColor,
		layoutLabelUppercase: toBoolean(
			rawLocalSeo?.layout_label_uppercase,
			LOCAL_SEO_DEFAULTS.layoutLabelUppercase,
		),
		layoutLabelBold: toBoolean(
			rawLocalSeo?.layout_label_bold,
			LOCAL_SEO_DEFAULTS.layoutLabelBold,
		),
		layoutLabelItalic: toBoolean(
			rawLocalSeo?.layout_label_italic,
			LOCAL_SEO_DEFAULTS.layoutLabelItalic,
		),
		layoutValueFontSize: Number.isFinite( Number( rawLocalSeo?.layout_value_font_size ) )
			? Math.max( 10, Math.min( 40, Number( rawLocalSeo?.layout_value_font_size ) ) )
			: LOCAL_SEO_DEFAULTS.layoutValueFontSize,
		layoutValueColor:
			typeof rawLocalSeo?.layout_value_color === 'string' &&
			rawLocalSeo.layout_value_color.trim() !== ''
				? rawLocalSeo.layout_value_color.trim()
				: LOCAL_SEO_DEFAULTS.layoutValueColor,
		layoutTitleFontSize: Number.isFinite( Number( rawLocalSeo?.layout_title_font_size ) )
			? Math.max( 16, Math.min( 80, Number( rawLocalSeo?.layout_title_font_size ) ) )
			: LOCAL_SEO_DEFAULTS.layoutTitleFontSize,
		layoutCardBackgroundColor:
			typeof rawLocalSeo?.layout_card_background_color === 'string' &&
			rawLocalSeo.layout_card_background_color.trim() !== ''
				? rawLocalSeo.layout_card_background_color.trim()
				: LOCAL_SEO_DEFAULTS.layoutCardBackgroundColor,
		businessType:
			typeof rawLocalSeo?.business_type === 'string' && rawLocalSeo.business_type.trim() !== ''
				? rawLocalSeo.business_type.trim()
				: LOCAL_SEO_DEFAULTS.businessType,
		businessName:
			typeof rawLocalSeo?.business_name === 'string'
				? rawLocalSeo.business_name
				: LOCAL_SEO_DEFAULTS.businessName,
		legalName:
			typeof rawLocalSeo?.legal_name === 'string'
				? rawLocalSeo.legal_name
				: LOCAL_SEO_DEFAULTS.legalName,
		imageUrl:
			typeof rawLocalSeo?.image_url === 'string'
				? rawLocalSeo.image_url
				: LOCAL_SEO_DEFAULTS.imageUrl,
		logoUrl:
			typeof rawLocalSeo?.logo_url === 'string'
				? rawLocalSeo.logo_url
				: LOCAL_SEO_DEFAULTS.logoUrl,
		phone: typeof rawLocalSeo?.phone === 'string' ? rawLocalSeo.phone : LOCAL_SEO_DEFAULTS.phone,
		priceRangeLevel:
			rawLocalSeo?.price_range_level === '$' ||
			rawLocalSeo?.price_range_level === '$$' ||
			rawLocalSeo?.price_range_level === '$$$' ||
			rawLocalSeo?.price_range_level === '$$$$'
				? rawLocalSeo.price_range_level
				: LOCAL_SEO_DEFAULTS.priceRangeLevel,
		priceRangeCustom:
			typeof rawLocalSeo?.price_range_custom === 'string'
				? rawLocalSeo.price_range_custom
				: typeof rawLocalSeo?.price_range === 'string'
					? rawLocalSeo.price_range
					: LOCAL_SEO_DEFAULTS.priceRangeCustom,
		ratingValue: Number.isFinite( Number( rawLocalSeo?.rating_value ) )
			? Math.max( 0, Math.min( 5, Number( rawLocalSeo?.rating_value ) ) )
			: LOCAL_SEO_DEFAULTS.ratingValue,
		reviewCount: Number.isFinite( Number( rawLocalSeo?.review_count ) )
			? Math.max( 0, Number( rawLocalSeo?.review_count ) )
			: LOCAL_SEO_DEFAULTS.reviewCount,
		sameAsUrls: Array.isArray( rawLocalSeo?.same_as_urls )
			? rawLocalSeo.same_as_urls
				.filter( ( item ): item is string => typeof item === 'string' )
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' )
			: LOCAL_SEO_DEFAULTS.sameAsUrls,
		streetAddress:
			typeof rawLocalSeo?.street_address === 'string'
				? rawLocalSeo.street_address
				: LOCAL_SEO_DEFAULTS.streetAddress,
		city: typeof rawLocalSeo?.city === 'string' ? rawLocalSeo.city : LOCAL_SEO_DEFAULTS.city,
		region:
			typeof rawLocalSeo?.region === 'string' ? rawLocalSeo.region : LOCAL_SEO_DEFAULTS.region,
		postalCode:
			typeof rawLocalSeo?.postal_code === 'string'
				? rawLocalSeo.postal_code
				: LOCAL_SEO_DEFAULTS.postalCode,
		country: normalizeIsoCountryCode( rawLocalSeo?.country ),
		latitude: Number.isFinite( Number( rawLocalSeo?.latitude ) )
			? Number( rawLocalSeo?.latitude )
			: LOCAL_SEO_DEFAULTS.latitude,
		longitude: Number.isFinite( Number( rawLocalSeo?.longitude ) )
			? Number( rawLocalSeo?.longitude )
			: LOCAL_SEO_DEFAULTS.longitude,
		kmlInSitemap: toBoolean(
			rawLocalSeo?.kml_in_sitemap,
			LOCAL_SEO_DEFAULTS.kmlInSitemap,
		),
		openingHours:
			typeof rawLocalSeo?.opening_hours === 'string'
				? rawLocalSeo.opening_hours
				: LOCAL_SEO_DEFAULTS.openingHours,
		enableGeoTags: toBoolean( rawLocalSeo?.enable_geo_tags, LOCAL_SEO_DEFAULTS.enableGeoTags ),
		geoRegionCode:
			typeof rawLocalSeo?.geo_region_code === 'string'
				? rawLocalSeo.geo_region_code
				: LOCAL_SEO_DEFAULTS.geoRegionCode,
		geoPlacename:
			typeof rawLocalSeo?.geo_placename === 'string'
				? rawLocalSeo.geo_placename
				: LOCAL_SEO_DEFAULTS.geoPlacename,
		mapZoom: Number.isFinite( Number( rawLocalSeo?.map_zoom ) )
			? Number( rawLocalSeo?.map_zoom )
			: LOCAL_SEO_DEFAULTS.mapZoom,
		serviceCatalogName:
			typeof rawLocalSeo?.service_catalog_name === 'string'
				? rawLocalSeo.service_catalog_name
				: LOCAL_SEO_DEFAULTS.serviceCatalogName,
		serviceCatalogItems: Array.isArray( rawLocalSeo?.service_catalog_items )
			? rawLocalSeo.service_catalog_items
				.filter(
					( item ): item is { name?: unknown; description?: unknown } =>
						!! item && typeof item === 'object',
				)
				.map( ( item ) => ( {
					name: typeof item.name === 'string' ? item.name : '',
					description: typeof item.description === 'string' ? item.description : '',
				} ) )
			: LOCAL_SEO_DEFAULTS.serviceCatalogItems,
		layoutOrder: normalizedLocalSeoLayoutOrder,
		layoutGrid: normalizedLocalSeoLayoutGrid,
		footerNapLayoutOrder: normalizeLocalSeoFooterNapLayoutOrder(
			rawLocalSeo?.footer_nap_layout_order,
		),
		footerNapEnabled: toBoolean(
			rawLocalSeo?.footer_nap_enabled,
			LOCAL_SEO_DEFAULTS.footerNapEnabled,
		),
		footerNapFontSize: Number.isFinite( Number( rawLocalSeo?.footer_nap_font_size ) )
			? Math.max( 10, Math.min( 48, Number( rawLocalSeo?.footer_nap_font_size ) ) )
			: LOCAL_SEO_DEFAULTS.footerNapFontSize,
		footerNapTextColor:
			typeof rawLocalSeo?.footer_nap_text_color === 'string' &&
			rawLocalSeo.footer_nap_text_color.trim() !== ''
				? rawLocalSeo.footer_nap_text_color.trim()
				: LOCAL_SEO_DEFAULTS.footerNapTextColor,
		footerNapTextAlign:
			rawLocalSeo?.footer_nap_text_align === 'left' ||
			rawLocalSeo?.footer_nap_text_align === 'center' ||
			rawLocalSeo?.footer_nap_text_align === 'right'
				? rawLocalSeo.footer_nap_text_align
				: LOCAL_SEO_DEFAULTS.footerNapTextAlign,
		footerNapFirstItemBold: toBoolean(
			rawLocalSeo?.footer_nap_first_item_bold,
			LOCAL_SEO_DEFAULTS.footerNapFirstItemBold,
		),
		footerNapMarginY: Number.isFinite( Number( rawLocalSeo?.footer_nap_margin_y ) )
			? Math.max( 0, Math.min( 200, Number( rawLocalSeo?.footer_nap_margin_y ) ) )
			: LOCAL_SEO_DEFAULTS.footerNapMarginY,
		footerNapGap: Number.isFinite( Number( rawLocalSeo?.footer_nap_gap ) )
			? Math.max( 0, Math.min( 48, Number( rawLocalSeo?.footer_nap_gap ) ) )
			: LOCAL_SEO_DEFAULTS.footerNapGap,
		footerNapContainerWidth: Number.isFinite( Number( rawLocalSeo?.footer_nap_container_width ) )
			? Math.max( 280, Math.min( 1920, Number( rawLocalSeo?.footer_nap_container_width ) ) )
			: LOCAL_SEO_DEFAULTS.footerNapContainerWidth,
		contactAutoMapEmbed: toBoolean(
			rawLocalSeo?.contact_auto_map_embed,
			LOCAL_SEO_DEFAULTS.contactAutoMapEmbed,
		),
		contactDetailedOpeningHours: toBoolean(
			rawLocalSeo?.contact_detailed_opening_hours,
			LOCAL_SEO_DEFAULTS.contactDetailedOpeningHours,
		),
		serviceAreaCities: Array.isArray( rawLocalSeo?.service_area_cities )
			? rawLocalSeo.service_area_cities
				.filter( ( item ): item is string => typeof item === 'string' )
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' )
			: LOCAL_SEO_DEFAULTS.serviceAreaCities,
		serviceAreaPostalCodes: Array.isArray( rawLocalSeo?.service_area_postal_codes )
			? rawLocalSeo.service_area_postal_codes
				.filter( ( item ): item is string => typeof item === 'string' )
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' )
			: LOCAL_SEO_DEFAULTS.serviceAreaPostalCodes,
		serviceAreaRadiusKm: Number.isFinite( Number( rawLocalSeo?.service_area_radius_km ) )
			? Math.max( 0, Number( rawLocalSeo?.service_area_radius_km ) )
			: LOCAL_SEO_DEFAULTS.serviceAreaRadiusKm,
		vatId: typeof rawLocalSeo?.vat_id === 'string' ? rawLocalSeo.vat_id : LOCAL_SEO_DEFAULTS.vatId,
		vatValidateChecksum: false,
		showVatInFooter: toBoolean(
			rawLocalSeo?.show_vat_in_footer,
			LOCAL_SEO_DEFAULTS.showVatInFooter,
		),
		clickToCallEnabled: toBoolean(
			rawLocalSeo?.click_to_call_enabled,
			LOCAL_SEO_DEFAULTS.clickToCallEnabled,
		),
		specialHours:
			typeof rawLocalSeo?.special_hours === 'string'
				? rawLocalSeo.special_hours
				: LOCAL_SEO_DEFAULTS.specialHours,
		branches: Array.isArray( rawLocalSeo?.branches )
			? rawLocalSeo.branches
				.filter( ( item ): item is Record<string, unknown> => !! item && typeof item === 'object' )
				.map( ( item, index ) => ( {
					id:
							typeof item.id === 'string' && item.id.trim() !== ''
								? item.id.trim()
								: `branch-${ index + 1 }`,
					label:
							typeof item.label === 'string' && item.label.trim() !== ''
								? item.label.trim()
								: `Branch ${ index + 1 }`,
					slug:
							typeof item.slug === 'string' && item.slug.trim() !== ''
								? item.slug.trim()
								: `branch-${ index + 1 }`,
					enabled: toBoolean( item.enabled, true ),
					businessName:
							typeof item.business_name === 'string' ? item.business_name : '',
					phone: typeof item.phone === 'string' ? item.phone : '',
					imageUrl:
							typeof item.image_url === 'string' ? item.image_url : '',
					streetAddress:
							typeof item.street_address === 'string' ? item.street_address : '',
					city: typeof item.city === 'string' ? item.city : '',
					region: typeof item.region === 'string' ? item.region : '',
					postalCode:
							typeof item.postal_code === 'string' ? item.postal_code : '',
					country: normalizeIsoCountryCode( item.country ),
					latitude: Number.isFinite( Number( item.latitude ) ) ? Number( item.latitude ) : 0,
					longitude: Number.isFinite( Number( item.longitude ) ) ? Number( item.longitude ) : 0,
					openingHours:
							typeof item.opening_hours === 'string' ? item.opening_hours : '',
					specialHours:
							typeof item.special_hours === 'string' ? item.special_hours : '',
					serviceAreaCities: Array.isArray( item.service_area_cities )
						? item.service_area_cities
							.filter( ( value ): value is string => typeof value === 'string' )
							.map( ( value ) => value.trim() )
							.filter( ( value ) => value !== '' )
						: [],
					serviceAreaPostalCodes: Array.isArray( item.service_area_postal_codes )
						? item.service_area_postal_codes
							.filter( ( value ): value is string => typeof value === 'string' )
							.map( ( value ) => value.trim() )
							.filter( ( value ) => value !== '' )
						: [],
					serviceAreaRadiusKm: Number.isFinite( Number( item.service_area_radius_km ) )
						? Math.max( 0, Number( item.service_area_radius_km ) )
						: 0,
					contactAutoMapEmbed: toBoolean( item.contact_auto_map_embed, false ),
					kmlInSitemap: toBoolean( item.kml_in_sitemap, false ),
					geoRegionCode:
							typeof item.geo_region_code === 'string' ? item.geo_region_code : '',
					geoPlacename:
							typeof item.geo_placename === 'string' ? item.geo_placename : '',
				} ) )
				.slice( 0, 30 )
			: LOCAL_SEO_DEFAULTS.branches,
	};
	const normalizedRelatedPostsPreset: RelatedPostsSettings['displayPreset'] =
		rawRelatedPosts?.display_preset === '2x2' ||
		rawRelatedPosts?.display_preset === '4x2' ||
		rawRelatedPosts?.display_preset === '4x1' ||
		rawRelatedPosts?.display_preset === '1x4'
			? rawRelatedPosts.display_preset
			: '3x2';
	const rawRelatedPostsHeaderContainer = rawRelatedPosts?.header_container as {
		border_width_top?: unknown;
		border_width_right?: unknown;
		border_width_bottom?: unknown;
		border_width_left?: unknown;
		border_radius?: unknown;
		border_style?: unknown;
		border_color?: unknown;
		bg_color?: unknown;
		padding_top?: unknown;
		padding_right?: unknown;
		padding_bottom?: unknown;
		padding_left?: unknown;
		margin_top?: unknown;
		margin_right?: unknown;
		margin_bottom?: unknown;
		margin_left?: unknown;
	} | undefined;
	const rawRelatedPostsHeaderTitle = rawRelatedPosts?.header_title as {
		font_style?: { bold?: unknown; italic?: unknown; underline?: unknown };
		color?: unknown;
		font_size?: unknown;
	} | undefined;

	const relatedPosts: RelatedPostsSettings = {
		enabled: toBoolean( rawRelatedPosts?.enabled, RELATED_POSTS_DEFAULTS.enabled ),
		titleEnabled: toBoolean( rawRelatedPosts?.title_enabled, RELATED_POSTS_DEFAULTS.titleEnabled ),
		titleText: sanitizeString( rawRelatedPosts?.title_text, RELATED_POSTS_DEFAULTS.titleText ),
		titleLevel:
			rawRelatedPosts?.title_level === 'h2' ||
			rawRelatedPosts?.title_level === 'h4'
				? rawRelatedPosts.title_level
				: rawRelatedPosts?.title_level === 'h3'
					? 'h3'
					: RELATED_POSTS_DEFAULTS.titleLevel,
		template:
			rawRelatedPosts?.template === 'sidebar_left'
				? 'sidebar_left'
				: 'single_column',
		footerColumns: ( () => {
			const rawFooterColumns = Number( rawRelatedPosts?.footer_columns );
			return rawFooterColumns === 1 || rawFooterColumns === 2 || rawFooterColumns === 3
				? ( rawFooterColumns as RelatedPostsSettings['footerColumns'] )
				: RELATED_POSTS_DEFAULTS.footerColumns;
		} )(),
		blockOrder: Array.isArray( rawRelatedPosts?.block_order )
			? RELATED_POSTS_BLOCK_IDS.filter( ( blockId ) =>
				( rawRelatedPosts.block_order as unknown[] ).includes( blockId ),
			)
			: [ ...RELATED_POSTS_DEFAULTS.blockOrder ],
		blockRegions: RELATED_POSTS_BLOCK_IDS.reduce(
			( acc, blockId ) => {
				const rawRegion =
					typeof rawRelatedPosts?.block_regions === 'object' &&
					rawRelatedPosts.block_regions !== null &&
					typeof ( rawRelatedPosts.block_regions as Record<string, unknown> )[ blockId ] === 'string'
						? ( rawRelatedPosts.block_regions as Record<string, string> )[ blockId ]
						: RELATED_POSTS_DEFAULTS.blockRegions[ blockId ];
				const normalizedRegion = rawRegion === 'footer' ? 'footer_left' : rawRegion;
				if (
					normalizedRegion === 'header' ||
					normalizedRegion === 'body' ||
					normalizedRegion === 'left_sidebar' ||
					normalizedRegion === 'footer_left' ||
					normalizedRegion === 'footer_center' ||
					normalizedRegion === 'footer_right'
				) {
					acc[ blockId ] = normalizedRegion;
				}
				return acc;
			},
			{} as Partial<
				Record<
					( typeof RELATED_POSTS_BLOCK_IDS )[number],
					'header' | 'body' | 'left_sidebar' | 'footer_left' | 'footer_center' | 'footer_right'
				>
			>,
		),
		gridContainer: {
			borderWidths: {
				top: Number.isFinite( Number( rawRelatedPosts?.grid_container?.border_width_top ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.border_width_top ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.top,
				right: Number.isFinite( Number( rawRelatedPosts?.grid_container?.border_width_right ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.border_width_right ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.right,
				bottom: Number.isFinite( Number( rawRelatedPosts?.grid_container?.border_width_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.border_width_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.bottom,
				left: Number.isFinite( Number( rawRelatedPosts?.grid_container?.border_width_left ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.border_width_left ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.left,
			},
			borderRadius: Number.isFinite( Number( rawRelatedPosts?.grid_container?.border_radius ) )
				? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.border_radius ) ) )
				: RELATED_POSTS_DEFAULTS.gridContainer.borderRadius,
			borderStyle:
				rawRelatedPosts?.grid_container?.border_style === 'dashed' ||
				rawRelatedPosts?.grid_container?.border_style === 'dotted'
					? rawRelatedPosts.grid_container.border_style
					: RELATED_POSTS_DEFAULTS.gridContainer.borderStyle,
			borderColor: sanitizeColor(
				rawRelatedPosts?.grid_container?.border_color,
				RELATED_POSTS_DEFAULTS.gridContainer.borderColor,
			),
			bgColor: sanitizeColor(
				rawRelatedPosts?.grid_container?.bg_color,
				RELATED_POSTS_DEFAULTS.gridContainer.bgColor,
			),
			paddings: {
				top: Number.isFinite( Number( rawRelatedPosts?.grid_container?.padding_top ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.padding_top ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.top,
				right: Number.isFinite( Number( rawRelatedPosts?.grid_container?.padding_right ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.padding_right ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.right,
				bottom: Number.isFinite( Number( rawRelatedPosts?.grid_container?.padding_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.padding_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.bottom,
				left: Number.isFinite( Number( rawRelatedPosts?.grid_container?.padding_left ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.grid_container?.padding_left ) ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.left,
			},
			gap: Number.isFinite( Number( rawRelatedPosts?.grid_container?.gap ) )
				? Math.max( 0, Math.min( 64, Number( rawRelatedPosts?.grid_container?.gap ) ) )
				: RELATED_POSTS_DEFAULTS.gridContainer.gap,
		},
		postContainer: {
			borderWidths: {
				top: Number.isFinite( Number( rawRelatedPosts?.post_container?.border_width_top ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.border_width_top ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.top,
				right: Number.isFinite( Number( rawRelatedPosts?.post_container?.border_width_right ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.border_width_right ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.right,
				bottom: Number.isFinite( Number( rawRelatedPosts?.post_container?.border_width_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.border_width_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.bottom,
				left: Number.isFinite( Number( rawRelatedPosts?.post_container?.border_width_left ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.border_width_left ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.left,
			},
			borderRadius: Number.isFinite( Number( rawRelatedPosts?.post_container?.border_radius ) )
				? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.border_radius ) ) )
				: RELATED_POSTS_DEFAULTS.postContainer.borderRadius,
			borderStyle:
				rawRelatedPosts?.post_container?.border_style === 'dashed' ||
				rawRelatedPosts?.post_container?.border_style === 'dotted'
					? rawRelatedPosts.post_container.border_style
					: RELATED_POSTS_DEFAULTS.postContainer.borderStyle,
			borderColor: sanitizeColor(
				rawRelatedPosts?.post_container?.border_color,
				RELATED_POSTS_DEFAULTS.postContainer.borderColor,
			),
			bgColor: sanitizeColor(
				rawRelatedPosts?.post_container?.bg_color,
				RELATED_POSTS_DEFAULTS.postContainer.bgColor,
			),
			paddings: {
				top: Number.isFinite( Number( rawRelatedPosts?.post_container?.padding_top ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.padding_top ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.top,
				right: Number.isFinite( Number( rawRelatedPosts?.post_container?.padding_right ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.padding_right ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.right,
				bottom: Number.isFinite( Number( rawRelatedPosts?.post_container?.padding_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.padding_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.bottom,
				left: Number.isFinite( Number( rawRelatedPosts?.post_container?.padding_left ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPosts?.post_container?.padding_left ) ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.left,
			},
			gap: Number.isFinite( Number( rawRelatedPosts?.post_container?.gap ) )
				? Math.max( 0, Math.min( 64, Number( rawRelatedPosts?.post_container?.gap ) ) )
				: RELATED_POSTS_DEFAULTS.postContainer.gap,
		},
		headerContainer: {
			borderWidths: {
				top: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.border_width_top ) )
					? Math.max( 0, Math.min( 12, Number( rawRelatedPostsHeaderContainer?.border_width_top ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.top ?? 0,
				right: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.border_width_right ) )
					? Math.max( 0, Math.min( 12, Number( rawRelatedPostsHeaderContainer?.border_width_right ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.right ?? 0,
				bottom: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.border_width_bottom ) )
					? Math.max( 0, Math.min( 12, Number( rawRelatedPostsHeaderContainer?.border_width_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.bottom ?? 0,
				left: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.border_width_left ) )
					? Math.max( 0, Math.min( 12, Number( rawRelatedPostsHeaderContainer?.border_width_left ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.left ?? 0,
			},
			borderRadius: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.border_radius ) )
				? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.border_radius ) ) )
				: RELATED_POSTS_DEFAULTS.headerContainer?.borderRadius ?? 0,
			borderStyle:
					rawRelatedPostsHeaderContainer?.border_style === 'dashed' ||
					rawRelatedPostsHeaderContainer?.border_style === 'dotted'
						? rawRelatedPostsHeaderContainer.border_style
						: RELATED_POSTS_DEFAULTS.headerContainer?.borderStyle ?? 'solid',
			borderColor: sanitizeColor(
				rawRelatedPostsHeaderContainer?.border_color,
				RELATED_POSTS_DEFAULTS.headerContainer?.borderColor ?? '#e2e8f0',
			),
			bgColor: sanitizeColor(
				rawRelatedPostsHeaderContainer?.bg_color,
				RELATED_POSTS_DEFAULTS.headerContainer?.bgColor ?? 'transparent',
			),
			paddings: {
				top: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.padding_top ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.padding_top ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.top ?? 0,
				right: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.padding_right ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.padding_right ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.right ?? 0,
				bottom: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.padding_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.padding_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.bottom ?? 0,
				left: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.padding_left ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.padding_left ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.left ?? 0,
			},
			margins: {
				top: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.margin_top ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.margin_top ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.top ?? 0,
				right: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.margin_right ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.margin_right ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.right ?? 0,
				bottom: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.margin_bottom ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.margin_bottom ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.bottom ?? 12,
				left: Number.isFinite( Number( rawRelatedPostsHeaderContainer?.margin_left ) )
					? Math.max( 0, Math.min( 50, Number( rawRelatedPostsHeaderContainer?.margin_left ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.left ?? 0,
			},
		},
		headerTitle: {
			fontStyle: {
				bold: toBoolean( rawRelatedPostsHeaderTitle?.font_style?.bold, RELATED_POSTS_DEFAULTS.headerTitle?.fontStyle.bold ?? true ),
				italic: toBoolean( rawRelatedPostsHeaderTitle?.font_style?.italic, RELATED_POSTS_DEFAULTS.headerTitle?.fontStyle.italic ?? false ),
				underline: toBoolean( rawRelatedPostsHeaderTitle?.font_style?.underline, RELATED_POSTS_DEFAULTS.headerTitle?.fontStyle.underline ?? false ),
			},
			color: sanitizeColor(
				rawRelatedPostsHeaderTitle?.color,
				RELATED_POSTS_DEFAULTS.headerTitle?.color ?? '#0f172a',
			),
			fontSize: Number.isFinite( Number( rawRelatedPostsHeaderTitle?.font_size ) )
				? Math.max( 10, Math.min( 40, Number( rawRelatedPostsHeaderTitle?.font_size ) ) )
				: RELATED_POSTS_DEFAULTS.headerTitle?.fontSize ?? 18,
		},
		featuredImageSize:
			typeof rawRelatedPosts?.featured_image_size === 'string' &&
			rawRelatedPosts.featured_image_size.trim() !== ''
				? rawRelatedPosts.featured_image_size
				: RELATED_POSTS_DEFAULTS.featuredImageSize,
		featuredImageRadius: Number.isFinite( Number( rawRelatedPosts?.featured_image_radius ) )
			? Math.max( 0, Math.min( 64, Number( rawRelatedPosts.featured_image_radius ) ) )
			: RELATED_POSTS_DEFAULTS.featuredImageRadius,
		titleFontSize: Number.isFinite( Number( rawRelatedPosts?.title_font_size ) )
			? Math.max( 10, Math.min( 64, Number( rawRelatedPosts.title_font_size ) ) )
			: RELATED_POSTS_DEFAULTS.titleFontSize,
		titleColor: sanitizeColor( rawRelatedPosts?.title_color, RELATED_POSTS_DEFAULTS.titleColor ),
		titleBold: toBoolean( rawRelatedPosts?.title_bold, RELATED_POSTS_DEFAULTS.titleBold ),
		titleItalic: toBoolean( rawRelatedPosts?.title_italic, RELATED_POSTS_DEFAULTS.titleItalic ),
		excerptFontSize: Number.isFinite( Number( rawRelatedPosts?.excerpt_font_size ) )
			? Math.max( 10, Math.min( 48, Number( rawRelatedPosts.excerpt_font_size ) ) )
			: RELATED_POSTS_DEFAULTS.excerptFontSize,
		excerptColor: sanitizeColor( rawRelatedPosts?.excerpt_color, RELATED_POSTS_DEFAULTS.excerptColor ),
		excerptMaxChars: Number.isFinite( Number( rawRelatedPosts?.excerpt_max_chars ) )
			? Math.max( 30, Math.min( 1000, Number( rawRelatedPosts.excerpt_max_chars ) ) )
			: RELATED_POSTS_DEFAULTS.excerptMaxChars,
		excerptFadeMask: toBoolean( rawRelatedPosts?.excerpt_fade_mask, RELATED_POSTS_DEFAULTS.excerptFadeMask ),
		excerptFadeColor: sanitizeColor( rawRelatedPosts?.excerpt_fade_color, RELATED_POSTS_DEFAULTS.excerptFadeColor ),
		excerptMaskHeight: Number.isFinite( Number( rawRelatedPosts?.excerpt_mask_height ) )
			? Math.max( 8, Math.min( 200, Number( rawRelatedPosts.excerpt_mask_height ) ) )
			: RELATED_POSTS_DEFAULTS.excerptMaskHeight,
		authorFontSize: Number.isFinite( Number( rawRelatedPosts?.author_font_size ) )
			? Math.max( 10, Math.min( 48, Number( rawRelatedPosts.author_font_size ) ) )
			: RELATED_POSTS_DEFAULTS.authorFontSize,
		authorColor: sanitizeColor( rawRelatedPosts?.author_color, RELATED_POSTS_DEFAULTS.authorColor ),
		authorBold: toBoolean( rawRelatedPosts?.author_bold, RELATED_POSTS_DEFAULTS.authorBold ),
		authorItalic: toBoolean( rawRelatedPosts?.author_italic, RELATED_POSTS_DEFAULTS.authorItalic ),
		autoInjectEnabled: toBoolean( rawRelatedPosts?.auto_inject_enabled, RELATED_POSTS_DEFAULTS.autoInjectEnabled ),
		displayPreset: normalizedRelatedPostsPreset,
		enabledPostTypes: Array.isArray( rawRelatedPosts?.enabled_post_types )
			? rawRelatedPosts.enabled_post_types
				.filter( ( item ): item is string => typeof item === 'string' )
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' )
			: [ ...RELATED_POSTS_DEFAULTS.enabledPostTypes ],
		insertPosition:
			rawRelatedPosts?.insert_position === 'before_content'
				? 'before_content'
				: 'after_content',
	};
	{
		const remapFooterRegion = (
			region: RelatedPostsLayoutRegion,
			footerColumns: RelatedPostsSettings['footerColumns'],
		): RelatedPostsLayoutRegion => {
			if ( footerColumns === 1 && ( region === 'footer_center' || region === 'footer_right' ) ) {
				return 'footer_left';
			}
			if ( footerColumns === 2 && region === 'footer_center' ) {
				return 'footer_left';
			}
			return region;
		};
		RELATED_POSTS_BLOCK_IDS.forEach( ( blockId ) => {
			const region = relatedPosts.blockRegions[ blockId ];
			if ( ! region ) {
				return;
			}
			relatedPosts.blockRegions[ blockId ] = remapFooterRegion( region, relatedPosts.footerColumns );
		} );
	}
	if ( relatedPosts.enabledPostTypes.length === 0 ) {
		relatedPosts.enabledPostTypes = [ ...RELATED_POSTS_DEFAULTS.enabledPostTypes ];
	}

	const notFoundManager: NotFoundManagerSettings = {
		monitorMode:
			rawNotFoundManager?.monitor_mode === 'advanced'
				? 'advanced'
				: 'simple',
		enableDailyAlert: toBoolean(
			rawNotFoundManager?.enable_daily_alert,
			NOT_FOUND_MANAGER_DEFAULTS.enableDailyAlert,
		),
		ignoreQueryParams: toBoolean(
			rawNotFoundManager?.ignore_query_params,
			NOT_FOUND_MANAGER_DEFAULTS.ignoreQueryParams,
		),
		logLimit: Number.isFinite( Number( rawNotFoundManager?.log_limit ) )
			? Math.max( 100, Math.min( 100000, Number( rawNotFoundManager.log_limit ) ) )
			: NOT_FOUND_MANAGER_DEFAULTS.logLimit,
		retentionDays: Number.isFinite( Number( rawNotFoundManager?.retention_days ) )
			? Math.max( 1, Math.min( 3650, Number( rawNotFoundManager.retention_days ) ) )
			: NOT_FOUND_MANAGER_DEFAULTS.retentionDays,
		excludePatterns: Array.isArray( rawNotFoundManager?.exclude_patterns )
			? rawNotFoundManager.exclude_patterns
				.filter( ( item ): item is string => typeof item === 'string' )
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' )
			: [ ...NOT_FOUND_MANAGER_DEFAULTS.excludePatterns ],
		fallbackRedirectMode:
			rawNotFoundManager?.fallback_redirect_mode === 'home' ||
			rawNotFoundManager?.fallback_redirect_mode === 'custom'
				? rawNotFoundManager.fallback_redirect_mode
				: 'off',
		fallbackRedirectTarget: sanitizeString(
			rawNotFoundManager?.fallback_redirect_target,
			NOT_FOUND_MANAGER_DEFAULTS.fallbackRedirectTarget,
		),
		fallbackRedirectCode:
			Number( rawNotFoundManager?.fallback_redirect_code ) === 302 ||
			Number( rawNotFoundManager?.fallback_redirect_code ) === 307 ||
			Number( rawNotFoundManager?.fallback_redirect_code ) === 410 ||
			Number( rawNotFoundManager?.fallback_redirect_code ) === 451
				? ( Number( rawNotFoundManager?.fallback_redirect_code ) as 302 | 307 | 410 | 451 )
				: 301,
	};
	if ( notFoundManager.excludePatterns.length === 0 ) {
		notFoundManager.excludePatterns = [ ...NOT_FOUND_MANAGER_DEFAULTS.excludePatterns ];
	}

	const scheduleTimezone = sanitizeString(
		rawNotify?.schedule?.timezone,
		NOTIFY_DEFAULTS.schedule.timezone,
	);
	const scheduleTimeRaw = sanitizeString(
		rawNotify?.schedule?.time,
		NOTIFY_DEFAULTS.schedule.time,
	);
	const scheduleTime = /^([01]\d|2[0-3]):[0-5]\d$/.test( scheduleTimeRaw )
		? scheduleTimeRaw
		: NOTIFY_DEFAULTS.schedule.time;
	const notifyCustom = ( () => {
		const knownBlocks = [ DIGEST_BLOCK_NOT_FOUND, DIGEST_BLOCK_BROKEN_LINK ];
		const toList = ( value: unknown ): string[] => {
			if ( ! Array.isArray( value ) ) {
				return [];
			}
			const out: string[] = [];
			value.forEach( ( entry ) => {
				if ( typeof entry !== 'string' ) {
					return;
				}
				const key = entry.trim();
				if ( ! knownBlocks.includes( key ) || out.includes( key ) ) {
					return;
				}
				out.push( key );
			} );
			return out;
		};
		const visible = toList( rawNotify?.custom?.visible_blocks );
		const hidden = toList( rawNotify?.custom?.hidden_blocks ).filter(
			( key ) => ! visible.includes( key ),
		);
		knownBlocks.forEach( ( key ) => {
			if ( ! visible.includes( key ) && ! hidden.includes( key ) ) {
				visible.push( key );
			}
		} );
		return enforceNotifyCustomAlertBlocks(
			{
				visibleBlocks: visible,
				hiddenBlocks: hidden,
			},
			notFoundManager.enableDailyAlert,
			brokenLinkChecker.enableDailyAlert,
		);
	} )();

	const notify: NotifySettings = {
		enabled: toBoolean( rawNotify?.enabled, NOTIFY_DEFAULTS.enabled ),
		custom: notifyCustom,
		message: {
			subject: sanitizeString(
				rawNotify?.message?.subject,
				NOTIFY_DEFAULTS.message.subject,
			),
			intro: sanitizeString(
				rawNotify?.message?.intro,
				NOTIFY_DEFAULTS.message.intro,
			),
			footer: sanitizeString(
				rawNotify?.message?.footer,
				NOTIFY_DEFAULTS.message.footer,
			),
		},
		logs: {
			retentionDays: Number.isFinite( Number( rawNotify?.logs?.retention_days ) )
				? Math.max( 1, Math.min( 3650, Number( rawNotify?.logs?.retention_days ) ) )
				: NOTIFY_DEFAULTS.logs.retentionDays,
		},
		schedule: {
			timezone: scheduleTimezone !== '' ? scheduleTimezone : 'UTC',
			time: scheduleTime,
		},
		channels: {
			email: {
				enabled: toBoolean(
					rawNotify?.channels?.email?.enabled,
					NOTIFY_DEFAULTS.channels.email.enabled,
				),
				recipients: Array.isArray( rawNotify?.channels?.email?.recipients )
					? rawNotify.channels.email.recipients
						.filter( ( item ): item is string => typeof item === 'string' )
						.map( ( item ) => item.trim() )
						.filter( ( item ) => item !== '' )
					: [],
				smtp: {
					host: sanitizeString(
						rawNotify?.channels?.email?.smtp?.host,
						NOTIFY_DEFAULTS.channels.email.smtp.host,
					),
					port: Number.isFinite( Number( rawNotify?.channels?.email?.smtp?.port ) )
						? Math.max( 1, Math.min( 65535, Number( rawNotify?.channels?.email?.smtp?.port ) ) )
						: NOTIFY_DEFAULTS.channels.email.smtp.port,
					auth: toBoolean(
						rawNotify?.channels?.email?.smtp?.auth,
						NOTIFY_DEFAULTS.channels.email.smtp.auth,
					),
					secure: ( () => {
						const secure = rawNotify?.channels?.email?.smtp?.secure;
						if ( secure === 'tls' || secure === 'ssl' || secure === '' ) {
							return secure;
						}
						return NOTIFY_DEFAULTS.channels.email.smtp.secure;
					} )(),
					username: sanitizeString(
						rawNotify?.channels?.email?.smtp?.username,
						NOTIFY_DEFAULTS.channels.email.smtp.username,
					),
					password: sanitizeString(
						rawNotify?.channels?.email?.smtp?.password,
						NOTIFY_DEFAULTS.channels.email.smtp.password,
					),
					timeout: Number.isFinite( Number( rawNotify?.channels?.email?.smtp?.timeout ) )
						? Math.max( 1, Math.min( 120, Number( rawNotify?.channels?.email?.smtp?.timeout ) ) )
						: NOTIFY_DEFAULTS.channels.email.smtp.timeout,
					fromEmail: sanitizeString(
						rawNotify?.channels?.email?.smtp?.fromEmail,
						NOTIFY_DEFAULTS.channels.email.smtp.fromEmail,
					),
					fromName: sanitizeString(
						rawNotify?.channels?.email?.smtp?.fromName,
						NOTIFY_DEFAULTS.channels.email.smtp.fromName,
					),
				},
			},
			telegram: {
				enabled: toBoolean(
					rawNotify?.channels?.telegram?.enabled,
					NOTIFY_DEFAULTS.channels.telegram.enabled,
				),
				botToken: sanitizeString(
					rawNotify?.channels?.telegram?.botToken,
					NOTIFY_DEFAULTS.channels.telegram.botToken,
				),
				chatId: sanitizeString(
					rawNotify?.channels?.telegram?.chatId,
					NOTIFY_DEFAULTS.channels.telegram.chatId,
				),
				topicId: sanitizeString(
					rawNotify?.channels?.telegram?.topicId,
					NOTIFY_DEFAULTS.channels.telegram.topicId,
				),
			},
			discord: {
				enabled: toBoolean(
					rawNotify?.channels?.discord?.enabled,
					NOTIFY_DEFAULTS.channels.discord.enabled,
				),
				webhook: sanitizeString(
					rawNotify?.channels?.discord?.webhook,
					NOTIFY_DEFAULTS.channels.discord.webhook,
				),
				username: sanitizeString(
					rawNotify?.channels?.discord?.username,
					NOTIFY_DEFAULTS.channels.discord.username,
				),
				avatar: sanitizeString(
					rawNotify?.channels?.discord?.avatar,
					NOTIFY_DEFAULTS.channels.discord.avatar,
				),
			},
			teams: {
				enabled: toBoolean(
					rawNotify?.channels?.teams?.enabled,
					NOTIFY_DEFAULTS.channels.teams.enabled,
				),
				webhook: sanitizeString(
					rawNotify?.channels?.teams?.webhook,
					NOTIFY_DEFAULTS.channels.teams.webhook,
				),
			},
		},
	};
	const markdownForAgents: MarkdownForAgentsSettings = {
		enabled: toBoolean( rawMarkdownForAgents.enabled, MARKDOWN_FOR_AGENTS_DEFAULTS.enabled ),
		promptsForAgents: toBoolean(
			rawMarkdownForAgents.prompts_for_agents,
			MARKDOWN_FOR_AGENTS_DEFAULTS.promptsForAgents,
		),
		includeFrontmatter: toBoolean(
			rawMarkdownForAgents.include_frontmatter,
			MARKDOWN_FOR_AGENTS_DEFAULTS.includeFrontmatter,
		),
		postTypes: markdownPostTypes.length > 0
			? markdownPostTypes
			: [ ...MARKDOWN_FOR_AGENTS_DEFAULTS.postTypes ],
	};
	const llmsTxtPostTypes = Array.isArray( rawLlmsTxt.post_types )
		? rawLlmsTxt.post_types.map( ( value ) => sanitizeString( value ) ).filter( ( value ) => value !== '' )
		: [];
	const llmsTxtIndexStrategyRaw = sanitizeString(
		rawLlmsTxt.index_strategy,
		LLMS_TXT_DEFAULTS.indexStrategy,
	);
	const llmsTxtIndexStrategy =
		llmsTxtIndexStrategyRaw === 'curated_only' ||
		llmsTxtIndexStrategyRaw === 'auto_only' ||
		llmsTxtIndexStrategyRaw === 'curated_plus_auto'
			? llmsTxtIndexStrategyRaw
			: LLMS_TXT_DEFAULTS.indexStrategy;
	const llmsTxtSections = Array.isArray( rawLlmsTxt.sections )
		? rawLlmsTxt.sections
			.map( ( section, index ) => {
				const rawSection = section as Record<string, unknown>;
				const id = sanitizeString( rawSection.id, `section_${ index + 1 }` );
				const title = sanitizeString( rawSection.title, '' );
				const description = sanitizeString( rawSection.description, '' );
				const postIds = Array.isArray( rawSection.post_ids )
					? rawSection.post_ids
						.map( ( value ) => Number.parseInt( String( value ), 10 ) )
						.filter( ( value ) => Number.isFinite( value ) && value > 0 )
					: [];
				const maxItems = Number.isFinite( Number( rawSection.max_items ) )
					? Math.max( 1, Math.min( 100, Number( rawSection.max_items ) ) )
					: 20;

				return {
					id,
					title,
					description,
					postIds: Array.from( new Set( postIds ) ),
					maxItems,
					hidden: toBoolean( rawSection.hidden, false ),
				};
			} )
			.filter( ( section ) => section.id !== '' && section.title !== '' )
		: [];
	const llmsTxtExtensions: LlmsTxtSettings['extensions'] = Array.isArray( rawLlmsTxt.extensions )
		? rawLlmsTxt.extensions
			.map( ( rawExtension, index ) => {
				if ( ! rawExtension || typeof rawExtension !== 'object' ) {
					return null;
				}
				const extensionRecord = rawExtension as Record<string, unknown>;
				const rawSections = Array.isArray( extensionRecord.sections )
					? extensionRecord.sections
					: [];
				const sections: LlmsTxtSettings['sections'] = rawSections
					.map( ( rawSection, sectionIndex ) => {
						if ( ! rawSection || typeof rawSection !== 'object' ) {
							return null;
						}
						const sectionRecord = rawSection as Record<string, unknown>;
						const postIds = Array.isArray( sectionRecord.post_ids )
							? sectionRecord.post_ids
								.map( ( item ) => Number( item ) )
								.filter( ( item ) => Number.isFinite( item ) && item > 0 )
							: [];
						const maxItems = Number.isFinite( Number( sectionRecord.max_items ) )
							? Math.max( 1, Math.min( 100, Number( sectionRecord.max_items ) ) )
							: 20;

						return {
							id:
								typeof sectionRecord.id === 'string' && sectionRecord.id.trim() !== ''
									? sectionRecord.id.trim()
									: `selection_${ sectionIndex + 1 }`,
							title: sanitizeString( sectionRecord.title, '' ),
							description: sanitizeString( sectionRecord.description, '' ),
							postIds: Array.from( new Set( postIds ) ),
							maxItems,
							hidden: toBoolean( sectionRecord.hidden, false ),
						};
					} )
					.filter(
						(
							section,
						): section is NonNullable<( typeof sections )[ number ]> =>
							Boolean( section && section.id !== '' && section.title !== '' ),
					);

				return {
					id:
						typeof extensionRecord.id === 'string' && extensionRecord.id.trim() !== ''
							? extensionRecord.id.trim()
							: `extension_${ index + 1 }`,
					title: sanitizeString( extensionRecord.title, `Extension ${ index + 1 }` ),
					description: sanitizeString( extensionRecord.description, '' ),
					path: sanitizeString( extensionRecord.path, '' ).replace( /^\/+|\/+$/g, '' ),
					customDeclaration: sanitizeString( extensionRecord.custom_declaration, '' ),
					filename:
						extensionRecord.filename === 'llms-small.txt' || extensionRecord.filename === 'llms-full.txt'
							? extensionRecord.filename
							: 'llms.txt',
					enabled: toBoolean( extensionRecord.enabled, true ),
					sections,
				};
			} )
			.filter(
				(
					extension,
				): extension is LlmsTxtSettings['extensions'][ number ] =>
					Boolean( extension && extension.id !== '' ),
			)
		: [];
	const llmsTxt: LlmsTxtSettings = {
		enabled: toBoolean( rawLlmsTxt.enabled, LLMS_TXT_DEFAULTS.enabled ),
		customDeclaration: sanitizeString(
			rawLlmsTxt.custom_declaration,
			LLMS_TXT_DEFAULTS.customDeclaration,
		),
		autoSectionTitle: sanitizeString(
			rawLlmsTxt.auto_section_title,
			LLMS_TXT_DEFAULTS.autoSectionTitle,
		),
		indexStrategy: llmsTxtIndexStrategy,
		autoTopicClusterGroups: toBoolean(
			rawLlmsTxt.auto_topic_cluster_groups,
			LLMS_TXT_DEFAULTS.autoTopicClusterGroups,
		),
		useMarkdownLinks: toBoolean(
			rawLlmsTxt.use_markdown_links,
			LLMS_TXT_DEFAULTS.useMarkdownLinks,
		),
		addToSitemap: toBoolean(
			rawLlmsTxt.add_to_sitemap,
			LLMS_TXT_DEFAULTS.addToSitemap,
		),
		excludeNoindex: toBoolean(
			rawLlmsTxt.exclude_noindex,
			LLMS_TXT_DEFAULTS.excludeNoindex,
		),
		excludePasswordProtected: toBoolean(
			rawLlmsTxt.exclude_password_protected,
			LLMS_TXT_DEFAULTS.excludePasswordProtected,
		),
		minWordCount: Number.isFinite( Number( rawLlmsTxt.min_word_count ) )
			? Math.max( 0, Math.min( 5000, Number( rawLlmsTxt.min_word_count ) ) )
			: LLMS_TXT_DEFAULTS.minWordCount,
		sections: llmsTxtSections,
		extensions: llmsTxtExtensions,
		postTypes: llmsTxtPostTypes.length > 0
			? llmsTxtPostTypes
			: [ ...LLMS_TXT_DEFAULTS.postTypes ],
	};
	llmsTxt.extensions = llmsTxt.extensions.map( ( extension ) => ( {
		...extension,
		filename:
			! canUseRootLlmsExtensionFilename( extension.path ) && extension.filename === 'llms.txt'
				? 'llms-small.txt'
				: extension.filename,
	} ) );

	return {
		socialCards: {
			og: {
				enabled: ogEnabled,
				defaultImageId: Number( rawOg?.default_image_id ?? 0 ) || 0,
				defaultImageUrl: typeof rawOg?.default_image_url === 'string' ? rawOg.default_image_url : '',
				imageWidth: Number( rawOg?.image_width ?? 0 ) || 0,
				imageHeight: Number( rawOg?.image_height ?? 0 ) || 0,
				fbAppId: typeof rawOg?.fb_app_id === 'string' ? rawOg.fb_app_id : '',
				fbAdmins: typeof rawOg?.fb_admins === 'string' ? rawOg.fb_admins : '',
				publisherUrl: typeof rawOg?.publisher_url === 'string' ? rawOg.publisher_url : '',
				domainVerification: typeof rawOg?.domain_verification === 'string' ? rawOg.domain_verification : '',
			},
			twitter: {
				enabled: Boolean( rawTwitter?.enabled ?? true ),
				cardType:
					rawTwitter?.card_type === 'summary'
						? 'summary'
						: 'summary_large_image',
				siteHandle:
					typeof rawTwitter?.site_handle === 'string'
						? rawTwitter.site_handle
						: '',
				creatorHandle:
					typeof rawTwitter?.creator_handle === 'string'
						? rawTwitter.creator_handle
						: '',
				inheritOgImage: ogEnabled
					? Boolean( rawTwitter?.inherit_og_image ?? true )
					: false,
				defaultImageId: Number( rawTwitter?.default_image_id ?? 0 ) || 0,
				defaultImageUrl:
					typeof rawTwitter?.default_image_url === 'string'
						? rawTwitter.default_image_url
						: '',
			},
		},
		schemaMarkup: {
			organization_name: String( schemaPayload?.organization_name ?? '' ),
			organization_type: String( schemaPayload?.organization_type ?? 'Organization' ),
			organization_logo_id: Number( schemaPayload?.organization_logo_id ?? 0 ),
			organization_logo_url: String( schemaPayload?.organization_logo_url ?? '' ),
			article_type: String( schemaPayload?.article_type ?? 'Article' ),
			post_type_defaults: postTypeDefaults,
			article_show_author: Boolean(
				schemaPayload?.article_show_author ?? true,
			),
			article_only_post: Boolean(
				schemaPayload?.article_only_post ?? true,
			),
			visibility: {
				organization: Boolean( schemaPayload?.visibility?.organization ?? false ),
				website: Boolean( schemaPayload?.visibility?.website ?? false ),
				breadcrumb: Boolean( schemaPayload?.visibility?.breadcrumb ?? false ),
				article: Boolean( schemaPayload?.visibility?.article ?? false ),
			},
		},
		breadcrumbs,
		robots: {
			default_directive: String( robotsPayload?.default_directive ?? '' ),
			additional_rules: robotsRules,
			enable_default_meta:
				typeof robotsPayload?.enable_default_meta === 'boolean'
					? robotsPayload.enable_default_meta
					: ! Boolean( robotsPayload?.suppress_default_meta ?? false ),
		},
		imageSeo,
		onPageSeo,
		authorSeo,
		taxonomySeo,
		wooCommerceSeo,
		localSeo,
		relatedPosts,
		notFoundManager,
		notify,
		markdownForAgents,
		llmsTxt,
		hreflang: {
			manual_map: hreflangMap,
			include_x_default: Boolean(
				hreflangPayload?.include_x_default ?? true,
			),
		},
		sitemap: {
			enabled_post_types: Array.isArray(
				sitemapPayload?.enabled_post_types,
			)
				? ( sitemapPayload.enabled_post_types as unknown[] ).map( ( slug ) => String( slug ) )
				: [],
			enabled_taxonomies: Array.isArray(
				sitemapPayload?.enabled_taxonomies,
			)
				? ( sitemapPayload.enabled_taxonomies as unknown[] ).map( ( slug ) => String( slug ) )
				: [],
			exclude_empty_taxonomies: Boolean(
				sitemapPayload?.exclude_empty_taxonomies ?? true,
			),
			items_per_page: typeof sitemapPayload?.items_per_page === 'number'
				? Math.min(
					5000,
					Math.max(
						500,
						Math.round( sitemapPayload.items_per_page / 500 ) * 500,
					),
				)
				: 500,
		},
		codeSnippetManager: {
			snippets: Array.isArray( codeSnippetManagerPayload?.snippets )
				? codeSnippetManagerPayload.snippets
					.map( ( snippet, index ) => {
						const rawSnippet =
							snippet && typeof snippet === 'object'
								? ( snippet as Record<string, unknown> )
								: {};
						const placement = rawSnippet.placement;
						const normalizedPlacement:
							| 'head'
							| 'body'
							| 'footer'
							| 'inactive' =
							placement === 'head' ||
							placement === 'body' ||
							placement === 'footer' ||
							placement === 'inactive'
								? placement
								: 'inactive';

						return {
							id:
								typeof rawSnippet.id === 'string' && rawSnippet.id.trim() !== ''
									? rawSnippet.id
									: `snippet-${ index + 1 }`,
							enabled: typeof rawSnippet.enabled === 'boolean' ? rawSnippet.enabled : true,
							description:
								typeof rawSnippet.description === 'string' ? rawSnippet.description : '',
							code: typeof rawSnippet.code === 'string' ? rawSnippet.code : '',
							placement: normalizedPlacement,
						};
					} )
					.filter( ( snippet ) => snippet.code.trim() !== '' )
				: [],
		},
		siteVerification: {
			google:
				typeof rawSiteVerification?.google === 'string'
					? rawSiteVerification.google
					: '',
			bing:
				typeof rawSiteVerification?.bing === 'string'
					? rawSiteVerification.bing
					: '',
			yandex:
				typeof rawSiteVerification?.yandex === 'string'
					? rawSiteVerification.yandex
					: '',
			baidu:
				typeof rawSiteVerification?.baidu === 'string'
					? rawSiteVerification.baidu
					: '',
			pinterest:
				typeof rawSiteVerification?.pinterest === 'string'
					? rawSiteVerification.pinterest
					: '',
		},
		rssFeedSignature: {
			enabled: Boolean( rssFeedSignaturePayload?.enabled ?? false ),
			before_content:
				typeof rssFeedSignaturePayload?.before_content === 'string'
					? rssFeedSignaturePayload.before_content
					: '',
			after_content:
				typeof rssFeedSignaturePayload?.after_content === 'string'
					? rssFeedSignaturePayload.after_content
					: '',
		},
		redirects: {
			rules: redirectRules,
		},
		brokenLinkChecker,
		scoreCalculator,
		toc,
		topicCluster,
		instantIndexing,
		modules,
		moduleOrder,
		panelOrder,
		panelVisibility,
		contentBlockOrder: normalizeContentBlockOrder( raw.contentBlockOrder ),
		contentBlockGap: typeof raw.contentBlockGap === 'number' ? raw.contentBlockGap : 24,
		contentBlockMarginTop: typeof raw.contentBlockMarginTop === 'number' ? raw.contentBlockMarginTop : 16,
	};
};

const serializeSettings = ( settings: SettingsState ) => {
	return {
		breadcrumbs: {
			manual_output_enabled: Boolean( settings.breadcrumbs.manualOutputEnabled ),
			auto_injection_enabled: Boolean( settings.breadcrumbs.autoInjectionEnabled ),
			injection_position: settings.breadcrumbs.injectionPosition === 'after_content'
				? 'after_content'
				: 'before_content',
			separator: settings.breadcrumbs.separator,
			prefix: settings.breadcrumbs.prefix,
			home: {
				display: Boolean( settings.breadcrumbs.home.display ),
				label: settings.breadcrumbs.home.label,
				url: BREADCRUMBS_DEFAULTS.home.url,
			},
			labels: {
				archive: settings.breadcrumbs.labels.archive,
				search: settings.breadcrumbs.labels.search,
				error: settings.breadcrumbs.labels.error,
			},
			display: {
				showCurrent: Boolean( settings.breadcrumbs.display.showCurrent ),
				showAncestors: Boolean( settings.breadcrumbs.display.showAncestors ),
				showBlog: Boolean( settings.breadcrumbs.display.showBlog ),
				showPagination: Boolean( settings.breadcrumbs.display.showPagination ),
				hideTaxonomy: Boolean( settings.breadcrumbs.display.hideTaxonomy ),
			},
			style: {
				fontSize: Number.isFinite( settings.breadcrumbs.style.fontSize )
					? settings.breadcrumbs.style.fontSize
					: BREADCRUMBS_DEFAULTS.style.fontSize,
				textColor: settings.breadcrumbs.style.textColor,
				linkColor: settings.breadcrumbs.style.linkColor,
				underlineLinks: Boolean( settings.breadcrumbs.style.underlineLinks ),
				borderWidth: Number.isFinite( settings.breadcrumbs.style.borderWidth )
					? Math.max( 0, Math.min( 10, settings.breadcrumbs.style.borderWidth ) )
					: BREADCRUMBS_DEFAULTS.style.borderWidth,
				borderColor: settings.breadcrumbs.style.borderColor,
				padding: Number.isFinite( settings.breadcrumbs.style.padding )
					? Math.max( 0, Math.min( 64, settings.breadcrumbs.style.padding ) )
					: BREADCRUMBS_DEFAULTS.style.padding,
				bgColor: settings.breadcrumbs.style.bgColor,
			},
		},
		socialCards: {
			og: {
				enabled: Boolean( settings.socialCards.og.enabled ),
				default_image_id:
				Number.isFinite( settings.socialCards.og.defaultImageId )
					? Number( settings.socialCards.og.defaultImageId ) || 0
					: 0,
				default_image_url: settings.socialCards.og.defaultImageUrl.trim(),
				image_width:
				Number.isFinite( settings.socialCards.og.imageWidth )
					? Number( settings.socialCards.og.imageWidth ) || 0
					: 0,
				image_height:
				Number.isFinite( settings.socialCards.og.imageHeight )
					? Number( settings.socialCards.og.imageHeight ) || 0
					: 0,
				fb_app_id: settings.socialCards.og.fbAppId.trim(),
				fb_admins: settings.socialCards.og.fbAdmins.trim(),
				publisher_url: settings.socialCards.og.publisherUrl.trim(),
				domain_verification: settings.socialCards.og.domainVerification.trim(),
			},
			twitter: {
				enabled: Boolean( settings.socialCards.twitter.enabled ),
				card_type: settings.socialCards.twitter.cardType,
				site_handle: settings.socialCards.twitter.siteHandle.trim(),
				creator_handle: settings.socialCards.twitter.creatorHandle.trim(),
				inherit_og_image: settings.socialCards.twitter.inheritOgImage && settings.socialCards.og.enabled,
				default_image_id:
				Number.isFinite( settings.socialCards.twitter.defaultImageId )
					? Number( settings.socialCards.twitter.defaultImageId ) || 0
					: 0,
				default_image_url: settings.socialCards.twitter.defaultImageUrl.trim(),
			},
		},
		schemaMarkup: {
			...settings.schemaMarkup,
			organization_logo_id:
			Number( settings.schemaMarkup.organization_logo_id ) || 0,
			post_type_defaults: Object.entries(
				settings.schemaMarkup.post_type_defaults,
			).reduce<Record<string, string>>( ( acc, [ key, value ] ) => {
				if ( typeof value === 'string' && value !== '' ) {
					acc[ key ] = value;
				}
				return acc;
			}, {} ),
			article_show_author: Boolean( settings.schemaMarkup.article_show_author ),
			article_only_post: Boolean(
				settings.schemaMarkup.article_only_post,
			),
			visibility: {
				organization: Boolean( settings.schemaMarkup.visibility.organization ),
				website: Boolean( settings.schemaMarkup.visibility.website ),
				breadcrumb: Boolean( settings.schemaMarkup.visibility.breadcrumb ),
				article: Boolean( settings.schemaMarkup.visibility.article ),
			},
		},
		robots: {
			...settings.robots,
			additional_rules: settings.robots.additional_rules
				.map( ( rule ) => rule.trim() )
				.filter(
					( rule, index, list ) =>
						rule !== '' && list.indexOf( rule ) === index,
				),
		},
		imageSeo: {
			alt: {
				enabled: Boolean( settings.imageSeo.alt.enabled ),
				format: settings.imageSeo.alt.format.trim(),
			},
			title: {
				enabled: Boolean( settings.imageSeo.title.enabled ),
				format: settings.imageSeo.title.format.trim(),
			},
			separator: settings.imageSeo.separator.trim(),
			custom_tokens: {
				custom_1: settings.imageSeo.customTokens.custom1.trim(),
				custom_2: settings.imageSeo.customTokens.custom2.trim(),
				custom_3: settings.imageSeo.customTokens.custom3.trim(),
			},
		},
		hreflang: {
			include_x_default: settings.hreflang.include_x_default,
			manual_map: settings.hreflang.manual_map
				.map( ( entry ) => ( {
					code: entry.code.trim(),
					url: entry.url.trim(),
				} ) )
				.filter( ( entry ) => entry.code !== '' && entry.url !== '' ),
		},
		sitemap: {
			enabled_post_types: settings.sitemap.enabled_post_types,
			enabled_taxonomies: settings.sitemap.enabled_taxonomies,
			exclude_empty_taxonomies: settings.sitemap.exclude_empty_taxonomies,
			items_per_page: settings.sitemap.items_per_page,
		},
		codeSnippetManager: {
			snippets: settings.codeSnippetManager.snippets
				.map( ( snippet ) => ( {
					id: snippet.id,
					enabled: Boolean( snippet.enabled ),
					description: snippet.description,
					code: snippet.code,
					placement: snippet.placement,
				} ) )
				.filter( ( snippet ) => snippet.code.trim() !== '' ),
		},
		siteVerification: {
			google: settings.siteVerification.google.trim(),
			bing: settings.siteVerification.bing.trim(),
			yandex: settings.siteVerification.yandex.trim(),
			baidu: settings.siteVerification.baidu.trim(),
			pinterest: settings.siteVerification.pinterest.trim(),
		},
		rssFeedSignature: {
			enabled: Boolean( settings.rssFeedSignature.enabled ),
			before_content: settings.rssFeedSignature.before_content,
			after_content: settings.rssFeedSignature.after_content,
		},
		redirects: {
			rules: settings.redirects.rules.map( ( rule ) => ( {
				...rule,
				source: rule.source.trim(),
				target: rule.target.trim(),
				note: rule.note.trim(),
			} ) ),
		},
		brokenLinkChecker: {
			enabled: Boolean( settings.brokenLinkChecker.enabled ),
			enable_daily_alert: Boolean( settings.brokenLinkChecker.enableDailyAlert ),
			check_interval_hours: settings.brokenLinkChecker.checkIntervalHours,
			max_requests_per_run: settings.brokenLinkChecker.maxRequestsPerRun,
			batch_delay_minutes: settings.brokenLinkChecker.batchDelayMinutes,
			log_retention_days: settings.brokenLinkChecker.logRetentionDays,
			connection_timeout_seconds: settings.brokenLinkChecker.connectionTimeoutSeconds,
			operation_timeout_seconds: settings.brokenLinkChecker.operationTimeoutSeconds,
			treat_redirects_as_warning: Boolean(
				settings.brokenLinkChecker.treatRedirectsAsWarning,
			),
			link_types: ( () => {
				const selected: string[] = [];
				if ( settings.brokenLinkChecker.linkTypes.external ) {
					selected.push( 'external' );
				}
				if ( settings.brokenLinkChecker.linkTypes.internal ) {
					selected.push( 'internal' );
				}
				if ( selected.length === 0 ) {
					selected.push( 'external' );
				}
				return selected;
			} )(),
		},
		scoreCalculator: {
			rules: Object.entries( settings.scoreCalculator.rules ).reduce<
			Record<string, number>
		>( ( acc, [ id, weight ] ) => {
			const trimmedId = id.trim();
			if ( '' === trimmedId ) {
				return acc;
			}

			const numeric = Number( weight );
			if ( Number.isNaN( numeric ) ) {
				return acc;
			}

			acc[ trimmedId ] = clampScoreWeight( numeric );
			return acc;
		}, {} ),
			post_types: settings.scoreCalculator.postTypes,
			custom_rules: Object.entries( settings.scoreCalculator.customRules ).reduce<
			Record<string, Record<string, number>>
		>( ( acc, [ ruleId, rawFields ] ) => {
			const trimmedRuleId = ruleId.trim();
			if ( '' === trimmedRuleId || ! rawFields || typeof rawFields !== 'object' ) {
				return acc;
			}

			const fields = Object.entries( rawFields ).reduce<Record<string, number>>(
				( fieldAcc, [ key, value ] ) => {
					const trimmedKey = key.trim();
					if ( '' === trimmedKey ) {
						return fieldAcc;
					}
					const numeric = Number( value );
					if ( Number.isNaN( numeric ) ) {
						return fieldAcc;
					}
					fieldAcc[ trimmedKey ] = numeric;
					return fieldAcc;
				},
				{},
			);

			if ( Object.keys( fields ).length > 0 ) {
				acc[ trimmedRuleId ] = fields;
			}
			return acc;
		}, {} ),
		},
		instantIndexing: {
			enabled: Boolean( settings.instantIndexing.enabled ),
			auto_submit: Boolean( settings.instantIndexing.autoSubmit ),
			retry_cooldown_days: Math.max(
				1,
				Math.round( settings.instantIndexing.retryCooldownDays ),
			),
			key: settings.instantIndexing.key.trim(),
			key_location: settings.instantIndexing.keyLocation.trim(),
			max_events_per_day: settings.instantIndexing.maxEventsPerDay,
			batch_size: settings.instantIndexing.batchSize,
			engines: Object.entries( settings.instantIndexing.engines ).reduce<
			Record<string, { enabled: boolean; endpoint: string }>
		>( ( acc, [ slug, engineConfig ] ) => {
			acc[ slug ] = {
				enabled: Boolean( engineConfig?.enabled ),
				endpoint: ( engineConfig?.endpoint ?? '' ).trim(),
			};
			return acc;
		}, {} ),
			backfill: {
				post_types: settings.instantIndexing.backfill.postTypes,
			},
		},
		onPageSeo: {
			output: {
				title: Boolean( settings.onPageSeo.output.title ),
				description: Boolean( settings.onPageSeo.output.description ),
				canonical: Boolean( settings.onPageSeo.output.canonical ),
				robots: Boolean( settings.onPageSeo.output.robots ),
			},
			templates: {
				global: {
					title: settings.onPageSeo.templates.global.title.trim(),
					description:
					settings.onPageSeo.templates.global.description.trim(),
				},
				separator:
				settings.onPageSeo.templates.separator.trim() !== ''
					? settings.onPageSeo.templates.separator.trim()
					: ONPAGE_SEO_DEFAULTS.templates.separator,
				custom_tokens: {
					custom_1: settings.onPageSeo.templates.customTokens.custom1.trim(),
					custom_2: settings.onPageSeo.templates.customTokens.custom2.trim(),
					custom_3: settings.onPageSeo.templates.customTokens.custom3.trim(),
				},
				post_types: Object.entries(
					settings.onPageSeo.templates.postTypes,
				).reduce<Record<string, { title: string; description: string }>>(
					( acc, [ slug, group ] ) => {
						const title = group.title.trim();
						const description = group.description.trim();
						if ( title || description ) {
							acc[ slug ] = { title, description };
						}
						return acc;
					},
					{},
				),
			},
		},
		toc: {
			manual_output_enabled: Boolean( settings.toc.manualOutputEnabled ),
			auto_injection_enabled: Boolean( settings.toc.autoInjectionEnabled ),
			post_types: settings.toc.postTypes,
			levels: settings.toc.levels,
			position: settings.toc.position,
			title_enabled: Boolean( settings.toc.titleEnabled ),
			title: settings.toc.title.trim(),
			title_level: settings.toc.titleLevel,
			min_headings: Number.isFinite( settings.toc.minHeadings )
				? settings.toc.minHeadings
				: TOC_DEFAULTS.minHeadings,
			smooth_scroll: Boolean( settings.toc.smoothScroll ),
			anchor_prefix: settings.toc.anchorPrefix.trim(),
			add_numbers: Boolean( settings.toc.addNumbers ),
			exclude_headings: settings.toc.excludeHeadings.trim(),
			collapse_on_load: Boolean( settings.toc.collapseOnLoad ),
			style: {
				preset: settings.toc.style.preset,
				border_style: settings.toc.style.borderStyle,
				border_color: settings.toc.style.borderColor,
				border_radius: Number.isFinite( settings.toc.style.borderRadius )
					? Math.max( 0, Math.min( 50, settings.toc.style.borderRadius ) )
					: TOC_DEFAULTS.style.borderRadius,
				body_container: {
					border_width_top: Number.isFinite( settings.toc.style.bodyContainer?.borderWidths.top )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.bodyContainer?.borderWidths.top ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.top ?? 1,
					border_width_right: Number.isFinite( settings.toc.style.bodyContainer?.borderWidths.right )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.bodyContainer?.borderWidths.right ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.right ?? 1,
					border_width_bottom: Number.isFinite( settings.toc.style.bodyContainer?.borderWidths.bottom )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.bodyContainer?.borderWidths.bottom ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.bottom ?? 1,
					border_width_left: Number.isFinite( settings.toc.style.bodyContainer?.borderWidths.left )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.bodyContainer?.borderWidths.left ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.borderWidths.left ?? 1,
					padding_top: Number.isFinite( settings.toc.style.bodyContainer?.paddings.top )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.paddings.top ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.top ?? 16,
					padding_right: Number.isFinite( settings.toc.style.bodyContainer?.paddings.right )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.paddings.right ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.right ?? 16,
					padding_bottom: Number.isFinite( settings.toc.style.bodyContainer?.paddings.bottom )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.paddings.bottom ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.bottom ?? 16,
					padding_left: Number.isFinite( settings.toc.style.bodyContainer?.paddings.left )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.paddings.left ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.paddings.left ?? 16,
					margin_top: Number.isFinite( settings.toc.style.bodyContainer?.margins.top )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.margins.top ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.top ?? 0,
					margin_right: Number.isFinite( settings.toc.style.bodyContainer?.margins.right )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.margins.right ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.right ?? 0,
					margin_bottom: Number.isFinite( settings.toc.style.bodyContainer?.margins.bottom )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.margins.bottom ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.bottom ?? 0,
					margin_left: Number.isFinite( settings.toc.style.bodyContainer?.margins.left )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.bodyContainer?.margins.left ) ) )
						: TOC_DEFAULTS.style.bodyContainer?.margins.left ?? 0,
				},
				toc_padding: Number.isFinite( settings.toc.style.tocPadding )
					? settings.toc.style.tocPadding
					: TOC_DEFAULTS.style.tocPadding,
				link_color: settings.toc.style.linkColor,
				link_size: Number.isFinite( settings.toc.style.linkSize )
					? settings.toc.style.linkSize
					: TOC_DEFAULTS.style.linkSize,
				font_style: {
					bold: Boolean( settings.toc.style.fontStyle.bold ),
					italic: Boolean( settings.toc.style.fontStyle.italic ),
					underline: Boolean( settings.toc.style.fontStyle.underline ),
				},
				bg_color: settings.toc.style.bgColor,
				header_container: {
					border_width_top: Number.isFinite( settings.toc.style.headerContainer?.borderWidths.top )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.headerContainer?.borderWidths.top ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.top ?? 0,
					border_width_right: Number.isFinite( settings.toc.style.headerContainer?.borderWidths.right )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.headerContainer?.borderWidths.right ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.right ?? 0,
					border_width_bottom: Number.isFinite( settings.toc.style.headerContainer?.borderWidths.bottom )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.headerContainer?.borderWidths.bottom ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.bottom ?? 0,
					border_width_left: Number.isFinite( settings.toc.style.headerContainer?.borderWidths.left )
						? Math.max( 0, Math.min( 8, Number( settings.toc.style.headerContainer?.borderWidths.left ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderWidths.left ?? 0,
					border_radius: Number.isFinite( settings.toc.style.headerContainer?.borderRadius )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.borderRadius ) ) )
						: TOC_DEFAULTS.style.headerContainer?.borderRadius ?? 0,
					border_style: settings.toc.style.headerContainer?.borderStyle ?? TOC_DEFAULTS.style.headerContainer?.borderStyle ?? 'solid',
					border_color: settings.toc.style.headerContainer?.borderColor ?? TOC_DEFAULTS.style.headerContainer?.borderColor ?? '#e2e8f0',
					padding_top: Number.isFinite( settings.toc.style.headerContainer?.paddings.top )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.paddings.top ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.top ?? 0,
					padding_right: Number.isFinite( settings.toc.style.headerContainer?.paddings.right )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.paddings.right ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.right ?? 0,
					padding_bottom: Number.isFinite( settings.toc.style.headerContainer?.paddings.bottom )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.paddings.bottom ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.bottom ?? 0,
					padding_left: Number.isFinite( settings.toc.style.headerContainer?.paddings.left )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.paddings.left ) ) )
						: TOC_DEFAULTS.style.headerContainer?.paddings.left ?? 0,
					bg_color: settings.toc.style.headerContainer?.bgColor ?? TOC_DEFAULTS.style.headerContainer?.bgColor ?? 'transparent',
					margin_top: Number.isFinite( settings.toc.style.headerContainer?.margins.top )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.margins.top ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.top ?? 0,
					margin_right: Number.isFinite( settings.toc.style.headerContainer?.margins.right )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.margins.right ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.right ?? 0,
					margin_bottom: Number.isFinite( settings.toc.style.headerContainer?.margins.bottom )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.margins.bottom ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.bottom ?? 12,
					margin_left: Number.isFinite( settings.toc.style.headerContainer?.margins.left )
						? Math.max( 0, Math.min( 50, Number( settings.toc.style.headerContainer?.margins.left ) ) )
						: TOC_DEFAULTS.style.headerContainer?.margins.left ?? 0,
				},
				header_title: {
					font_style: {
						bold: Boolean( settings.toc.style.headerTitle?.fontStyle.bold ),
						italic: Boolean( settings.toc.style.headerTitle?.fontStyle.italic ),
						underline: Boolean( settings.toc.style.headerTitle?.fontStyle.underline ),
					},
					color: settings.toc.style.headerTitle?.color ?? TOC_DEFAULTS.style.headerTitle?.color ?? '#0f172a',
					font_size: Number.isFinite( settings.toc.style.headerTitle?.fontSize )
						? Math.max( 10, Math.min( 40, Number( settings.toc.style.headerTitle?.fontSize ) ) )
						: TOC_DEFAULTS.style.headerTitle?.fontSize ?? 18,
				},
			},
		},
		topicCluster: {
			manual_output_enabled: Boolean( settings.topicCluster.manualOutputEnabled ),
			auto_injection_enabled: Boolean( settings.topicCluster.autoInjectionEnabled ),
			override_breadcrumbs: Boolean( settings.topicCluster.overrideBreadcrumbs ),
			override_wp_adjacent: Boolean( settings.topicCluster.overrideWpAdjacent ),
			insert_position: settings.topicCluster.insertPosition,
			post_types: settings.topicCluster.postTypes,
			title_enabled: Boolean( settings.topicCluster.titleEnabled ),
			title_text: settings.topicCluster.titleText,
			relation_text_l1: settings.topicCluster.relationTextL1,
			relation_text_l2: settings.topicCluster.relationTextL2,
			relation_text_l3: settings.topicCluster.relationTextL3,
			title_level: settings.topicCluster.titleLevel,
			style_type: settings.topicCluster.styleType,
			style: {
				preset: settings.topicCluster.style.preset,
				show_border: true,
				border_style: settings.topicCluster.style.borderStyle,
				border_color: settings.topicCluster.style.borderColor,
				border_width_top: Number.isFinite( settings.topicCluster.style.borderWidths.top )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.borderWidths.top ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.top,
				border_width_right: Number.isFinite( settings.topicCluster.style.borderWidths.right )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.borderWidths.right ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.right,
				border_width_bottom: Number.isFinite( settings.topicCluster.style.borderWidths.bottom )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.borderWidths.bottom ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.bottom,
				border_width_left: Number.isFinite( settings.topicCluster.style.borderWidths.left )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.borderWidths.left ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderWidths.left,
				border_radius: Number.isFinite( settings.topicCluster.style.borderRadius )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.borderRadius ) )
					: TOPIC_CLUSTER_DEFAULTS.style.borderRadius,
				padding_top: Number.isFinite( settings.topicCluster.style.paddings.top )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.paddings.top ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.top,
				padding_right: Number.isFinite( settings.topicCluster.style.paddings.right )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.paddings.right ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.right,
				padding_bottom: Number.isFinite( settings.topicCluster.style.paddings.bottom )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.paddings.bottom ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.bottom,
				padding_left: Number.isFinite( settings.topicCluster.style.paddings.left )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.paddings.left ) )
					: TOPIC_CLUSTER_DEFAULTS.style.paddings.left,
				margin_top: Number.isFinite( settings.topicCluster.style.margins.top )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.margins.top ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.top,
				margin_right: Number.isFinite( settings.topicCluster.style.margins.right )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.margins.right ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.right,
				margin_bottom: Number.isFinite( settings.topicCluster.style.margins.bottom )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.margins.bottom ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.bottom,
				margin_left: Number.isFinite( settings.topicCluster.style.margins.left )
					? Math.max( 0, Math.min( 50, settings.topicCluster.style.margins.left ) )
					: TOPIC_CLUSTER_DEFAULTS.style.margins.left,
				bg_color: settings.topicCluster.style.bgColor,
				item_text_color: settings.topicCluster.style.itemTextColor,
				item_font_size: settings.topicCluster.style.itemFontSize,
				item_bold: Boolean( settings.topicCluster.style.itemBold ),
				item_italic: Boolean( settings.topicCluster.style.itemItalic ),
				item_underline: Boolean( settings.topicCluster.style.itemUnderline ),
				item_list_style: settings.topicCluster.style.itemListStyle,
				item_gap: settings.topicCluster.style.itemGap,
				header_container: {
					border_width_top: Number.isFinite( settings.topicCluster.style.headerContainer?.borderWidths.top )
						? Math.max( 0, Math.min( 20, Number( settings.topicCluster.style.headerContainer?.borderWidths.top ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.top ?? 0,
					border_width_right: Number.isFinite( settings.topicCluster.style.headerContainer?.borderWidths.right )
						? Math.max( 0, Math.min( 20, Number( settings.topicCluster.style.headerContainer?.borderWidths.right ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.right ?? 0,
					border_width_bottom: Number.isFinite( settings.topicCluster.style.headerContainer?.borderWidths.bottom )
						? Math.max( 0, Math.min( 20, Number( settings.topicCluster.style.headerContainer?.borderWidths.bottom ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.bottom ?? 0,
					border_width_left: Number.isFinite( settings.topicCluster.style.headerContainer?.borderWidths.left )
						? Math.max( 0, Math.min( 20, Number( settings.topicCluster.style.headerContainer?.borderWidths.left ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderWidths.left ?? 0,
					border_radius: Number.isFinite( settings.topicCluster.style.headerContainer?.borderRadius )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.borderRadius ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderRadius ?? 0,
					border_style: settings.topicCluster.style.headerContainer?.borderStyle ?? TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderStyle ?? 'solid',
					border_color: settings.topicCluster.style.headerContainer?.borderColor ?? TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.borderColor ?? '#cbd5e1',
					padding_top: Number.isFinite( settings.topicCluster.style.headerContainer?.paddings.top )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.paddings.top ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.top ?? 0,
					padding_right: Number.isFinite( settings.topicCluster.style.headerContainer?.paddings.right )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.paddings.right ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.right ?? 0,
					padding_bottom: Number.isFinite( settings.topicCluster.style.headerContainer?.paddings.bottom )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.paddings.bottom ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.bottom ?? 0,
					padding_left: Number.isFinite( settings.topicCluster.style.headerContainer?.paddings.left )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.paddings.left ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.paddings.left ?? 0,
					bg_color: settings.topicCluster.style.headerContainer?.bgColor ?? TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.bgColor ?? 'transparent',
					margin_top: Number.isFinite( settings.topicCluster.style.headerContainer?.margins.top )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.margins.top ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.top ?? 0,
					margin_right: Number.isFinite( settings.topicCluster.style.headerContainer?.margins.right )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.margins.right ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.right ?? 0,
					margin_bottom: Number.isFinite( settings.topicCluster.style.headerContainer?.margins.bottom )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.margins.bottom ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.bottom ?? 12,
					margin_left: Number.isFinite( settings.topicCluster.style.headerContainer?.margins.left )
						? Math.max( 0, Math.min( 50, Number( settings.topicCluster.style.headerContainer?.margins.left ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerContainer?.margins.left ?? 0,
				},
				header_title: {
					font_style: {
						bold: Boolean( settings.topicCluster.style.headerTitle?.fontStyle.bold ),
						italic: Boolean( settings.topicCluster.style.headerTitle?.fontStyle.italic ),
						underline: Boolean( settings.topicCluster.style.headerTitle?.fontStyle.underline ),
					},
					color: settings.topicCluster.style.headerTitle?.color ?? TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.color ?? '#0f172a',
					font_size: Number.isFinite( settings.topicCluster.style.headerTitle?.fontSize )
						? Math.max( 10, Math.min( 40, Number( settings.topicCluster.style.headerTitle?.fontSize ) ) )
						: TOPIC_CLUSTER_DEFAULTS.style.headerTitle?.fontSize ?? 18,
				},
			},
		},
		authorSeo: {
			enabled: Boolean( settings.authorSeo.enabled ),
			noindex_author_archives: Boolean( settings.authorSeo.noindexAuthorArchives ),
			title_template: settings.authorSeo.titleTemplate.trim(),
			description_template: settings.authorSeo.descriptionTemplate.trim(),
			separator: settings.authorSeo.separator.trim(),
			custom_tokens: {
				custom1: settings.authorSeo.customTokens.custom1.trim(),
				custom2: settings.authorSeo.customTokens.custom2.trim(),
				custom3: settings.authorSeo.customTokens.custom3.trim(),
			},
			social_profiles: settings.authorSeo.socialProfiles
				.map( ( profile ) => profile.trim() )
				.filter( ( profile ) => '' !== profile ),
		},
		taxonomySeo: {
			enabled: Boolean( settings.taxonomySeo.enabled ),
			enabled_taxonomies: settings.taxonomySeo.enabledTaxonomies
				.map( ( slug ) => slug.trim() )
				.filter( ( slug ) => '' !== slug ),
			templates: {
				global: {
					title: settings.taxonomySeo.templates.global.title.trim(),
					description: settings.taxonomySeo.templates.global.description.trim(),
				},
				separator:
				settings.taxonomySeo.templates.separator.trim() !== ''
					? settings.taxonomySeo.templates.separator.trim()
					: TAXONOMY_SEO_DEFAULTS.templates.separator,
				custom_tokens: {
					custom_1: settings.taxonomySeo.templates.customTokens.custom1.trim(),
					custom_2: settings.taxonomySeo.templates.customTokens.custom2.trim(),
					custom_3: settings.taxonomySeo.templates.customTokens.custom3.trim(),
				},
			},
		},
		wooCommerceSeo: {
			enabled: Boolean( settings.wooCommerceSeo.enabled ),
			brand_attribute:
			settings.wooCommerceSeo.brandAttribute.trim() !== ''
				? settings.wooCommerceSeo.brandAttribute.trim()
				: WOO_COMMERCE_SEO_DEFAULTS.brandAttribute,
			templates: {
				product: {
					title: settings.wooCommerceSeo.templates.product.title.trim(),
					description: settings.wooCommerceSeo.templates.product.description.trim(),
				},
				separator:
				settings.wooCommerceSeo.templates.separator.trim() !== ''
					? settings.wooCommerceSeo.templates.separator.trim()
					: WOO_COMMERCE_SEO_DEFAULTS.templates.separator,
				custom_tokens: {
					custom_1: settings.wooCommerceSeo.templates.customTokens.custom1.trim(),
					custom_2: settings.wooCommerceSeo.templates.customTokens.custom2.trim(),
					custom_3: settings.wooCommerceSeo.templates.customTokens.custom3.trim(),
				},
			},
		},
		localSeo: {
			enabled: Boolean( settings.localSeo.enabled ),
			layout_show_card_border: Boolean( settings.localSeo.layoutShowCardBorder ),
			layout_card_padding: Number.isFinite( settings.localSeo.layoutCardPadding )
				? Math.max( 0, Math.min( 64, settings.localSeo.layoutCardPadding ) )
				: LOCAL_SEO_DEFAULTS.layoutCardPadding,
			layout_label_font_size: Number.isFinite( settings.localSeo.layoutLabelFontSize )
				? Math.max( 10, Math.min( 32, settings.localSeo.layoutLabelFontSize ) )
				: LOCAL_SEO_DEFAULTS.layoutLabelFontSize,
			layout_label_color:
			settings.localSeo.layoutLabelColor.trim() !== ''
				? settings.localSeo.layoutLabelColor.trim()
				: LOCAL_SEO_DEFAULTS.layoutLabelColor,
			layout_label_uppercase: Boolean( settings.localSeo.layoutLabelUppercase ),
			layout_label_bold: Boolean( settings.localSeo.layoutLabelBold ),
			layout_label_italic: Boolean( settings.localSeo.layoutLabelItalic ),
			layout_value_font_size: Number.isFinite( settings.localSeo.layoutValueFontSize )
				? Math.max( 10, Math.min( 40, settings.localSeo.layoutValueFontSize ) )
				: LOCAL_SEO_DEFAULTS.layoutValueFontSize,
			layout_value_color:
			settings.localSeo.layoutValueColor.trim() !== ''
				? settings.localSeo.layoutValueColor.trim()
				: LOCAL_SEO_DEFAULTS.layoutValueColor,
			layout_title_font_size: Number.isFinite( settings.localSeo.layoutTitleFontSize )
				? Math.max( 16, Math.min( 80, settings.localSeo.layoutTitleFontSize ) )
				: LOCAL_SEO_DEFAULTS.layoutTitleFontSize,
			layout_card_background_color:
			settings.localSeo.layoutCardBackgroundColor.trim() !== ''
				? settings.localSeo.layoutCardBackgroundColor.trim()
				: LOCAL_SEO_DEFAULTS.layoutCardBackgroundColor,
			business_type: settings.localSeo.businessType.trim(),
			business_name: settings.localSeo.businessName.trim(),
			legal_name: settings.localSeo.legalName.trim(),
			image_url: settings.localSeo.imageUrl.trim(),
			logo_url: settings.localSeo.logoUrl.trim(),
			phone: settings.localSeo.phone.trim(),
			price_range_level: settings.localSeo.priceRangeLevel,
			price_range_custom: settings.localSeo.priceRangeCustom.trim(),
			price_range:
			settings.localSeo.priceRangeCustom.trim() !== ''
				? settings.localSeo.priceRangeCustom.trim()
				: settings.localSeo.priceRangeLevel,
			rating_value: Number.isFinite( settings.localSeo.ratingValue )
				? Math.max( 0, Math.min( 5, settings.localSeo.ratingValue ) )
				: 0,
			review_count: Number.isFinite( settings.localSeo.reviewCount )
				? Math.max( 0, settings.localSeo.reviewCount )
				: 0,
			same_as_urls: settings.localSeo.sameAsUrls
				.map( ( url ) => url.trim() )
				.filter( ( url ) => url !== '' ),
			street_address: settings.localSeo.streetAddress.trim(),
			city: settings.localSeo.city.trim(),
			region: settings.localSeo.region.trim(),
			postal_code: settings.localSeo.postalCode.trim(),
			country: settings.localSeo.country.trim(),
			latitude: Number.isFinite( settings.localSeo.latitude ) ? settings.localSeo.latitude : 0,
			longitude: Number.isFinite( settings.localSeo.longitude ) ? settings.localSeo.longitude : 0,
			kml_in_sitemap:
			Boolean( settings.localSeo.kmlInSitemap ) &&
			Number.isFinite( settings.localSeo.latitude ) &&
			Number.isFinite( settings.localSeo.longitude ) &&
			settings.localSeo.latitude >= -90 &&
			settings.localSeo.latitude <= 90 &&
			settings.localSeo.longitude >= -180 &&
			settings.localSeo.longitude <= 180 &&
			settings.localSeo.latitude !== 0 &&
			settings.localSeo.longitude !== 0,
			opening_hours: settings.localSeo.openingHours.trim(),
			enable_geo_tags: Boolean( settings.localSeo.enableGeoTags ),
			geo_region_code: settings.localSeo.geoRegionCode.trim(),
			geo_placename: settings.localSeo.geoPlacename.trim(),
			map_zoom: Number.isFinite( settings.localSeo.mapZoom ) ? settings.localSeo.mapZoom : 15,
			service_catalog_name: settings.localSeo.serviceCatalogName.trim(),
			service_catalog_items: settings.localSeo.serviceCatalogItems
				.map( ( item ) => ( {
					name: item.name.trim(),
					description: item.description.trim(),
				} ) )
				.filter( ( item ) => item.name !== '' || item.description !== '' ),
			layout_order: [
				...new Set(
					settings.localSeo.layoutGrid
						.slice()
						.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) )
						.map( ( item ) => item.blockId )
						.filter( ( item ) =>
							( LOCAL_SEO_LAYOUT_BLOCKS as readonly string[] ).includes( item ),
						),
				),
			],
			layout_template: settings.localSeo.layoutTemplate,
			layout_grid: settings.localSeo.layoutGrid
				.filter( ( item ) =>
					( LOCAL_SEO_LAYOUT_BLOCKS as readonly string[] ).includes( item.blockId ),
				)
				.map( ( item ) => ( {
					block_id: item.blockId,
					row: Number.isFinite( item.row ) ? Math.max( 1, Math.min( 15, Math.floor( item.row ) ) ) : 1,
					col: Number.isFinite( item.col ) ? Math.max( 1, Math.min( 5, Math.floor( item.col ) ) ) : 1,
					span: Number.isFinite( item.span ) ? Math.max( 1, Math.min( 5, Math.floor( item.span ) ) ) : 1,
					row_span: Number.isFinite( item.rowSpan )
						? Math.max( 1, Math.min( 5, Math.floor( item.rowSpan ) ) )
						: 1,
				} ) ),
			footer_nap_layout_order: settings.localSeo.footerNapLayoutOrder.filter( ( item ) =>
				( LOCAL_SEO_FOOTER_NAP_BLOCKS as readonly string[] ).includes( item ),
			),
			footer_nap_enabled: Boolean( settings.localSeo.footerNapEnabled ),
			footer_nap_font_size: Number.isFinite( settings.localSeo.footerNapFontSize )
				? Math.max( 10, Math.min( 48, settings.localSeo.footerNapFontSize ) )
				: LOCAL_SEO_DEFAULTS.footerNapFontSize,
			footer_nap_text_color:
			settings.localSeo.footerNapTextColor.trim() !== ''
				? settings.localSeo.footerNapTextColor.trim()
				: LOCAL_SEO_DEFAULTS.footerNapTextColor,
			footer_nap_text_align:
			settings.localSeo.footerNapTextAlign === 'left' ||
			settings.localSeo.footerNapTextAlign === 'right'
				? settings.localSeo.footerNapTextAlign
				: 'center',
			footer_nap_first_item_bold: Boolean( settings.localSeo.footerNapFirstItemBold ),
			footer_nap_margin_y: Number.isFinite( settings.localSeo.footerNapMarginY )
				? Math.max( 0, Math.min( 200, settings.localSeo.footerNapMarginY ) )
				: LOCAL_SEO_DEFAULTS.footerNapMarginY,
			footer_nap_gap: Number.isFinite( settings.localSeo.footerNapGap )
				? Math.max( 0, Math.min( 48, settings.localSeo.footerNapGap ) )
				: LOCAL_SEO_DEFAULTS.footerNapGap,
			footer_nap_container_width: Number.isFinite( settings.localSeo.footerNapContainerWidth )
				? Math.max( 280, Math.min( 1920, settings.localSeo.footerNapContainerWidth ) )
				: LOCAL_SEO_DEFAULTS.footerNapContainerWidth,
			contact_auto_map_embed:
			Boolean( settings.localSeo.contactAutoMapEmbed ) &&
			Number.isFinite( settings.localSeo.latitude ) &&
			Number.isFinite( settings.localSeo.longitude ) &&
			settings.localSeo.latitude >= -90 &&
			settings.localSeo.latitude <= 90 &&
			settings.localSeo.longitude >= -180 &&
			settings.localSeo.longitude <= 180 &&
			settings.localSeo.latitude !== 0 &&
			settings.localSeo.longitude !== 0,
			contact_detailed_opening_hours: Boolean( settings.localSeo.contactDetailedOpeningHours ),
			service_area_cities: settings.localSeo.serviceAreaCities
				.map( ( city ) => city.trim() )
				.filter( ( city ) => city !== '' ),
			service_area_postal_codes: settings.localSeo.serviceAreaPostalCodes
				.map( ( code ) => code.trim() )
				.filter( ( code ) => code !== '' ),
			service_area_radius_km: Number.isFinite( settings.localSeo.serviceAreaRadiusKm )
				? Math.max( 0, settings.localSeo.serviceAreaRadiusKm )
				: 0,
			vat_id: settings.localSeo.vatId.trim(),
			vat_validate_checksum: false,
			show_vat_in_footer: Boolean( settings.localSeo.showVatInFooter ),
			click_to_call_enabled: Boolean( settings.localSeo.clickToCallEnabled ),
			special_hours: settings.localSeo.specialHours.trim(),
			branches: settings.localSeo.branches.map( ( branch ) => ( {
				id: branch.id.trim(),
				label: branch.label.trim(),
				slug: branch.slug.trim(),
				enabled: Boolean( branch.enabled ),
				business_name: branch.businessName.trim(),
				phone: branch.phone.trim(),
				image_url: branch.imageUrl.trim(),
				street_address: branch.streetAddress.trim(),
				city: branch.city.trim(),
				region: branch.region.trim(),
				postal_code: branch.postalCode.trim(),
				country: branch.country.trim(),
				latitude: Number.isFinite( branch.latitude ) ? branch.latitude : 0,
				longitude: Number.isFinite( branch.longitude ) ? branch.longitude : 0,
				opening_hours: branch.openingHours.trim(),
				special_hours: branch.specialHours.trim(),
				service_area_cities: branch.serviceAreaCities
					.map( ( city ) => city.trim() )
					.filter( ( city ) => city !== '' ),
				service_area_postal_codes: branch.serviceAreaPostalCodes
					.map( ( code ) => code.trim() )
					.filter( ( code ) => code !== '' ),
				service_area_radius_km: Number.isFinite( branch.serviceAreaRadiusKm )
					? Math.max( 0, branch.serviceAreaRadiusKm )
					: 0,
				contact_auto_map_embed: Boolean( branch.contactAutoMapEmbed ),
				kml_in_sitemap: Boolean( branch.kmlInSitemap ),
				geo_region_code: branch.geoRegionCode.trim(),
				geo_placename: branch.geoPlacename.trim(),
			} ) ),
		},
		relatedPosts: {
			enabled: Boolean( settings.relatedPosts.enabled ),
			title_enabled: Boolean( settings.relatedPosts.titleEnabled ),
			title_text: settings.relatedPosts.titleText.trim(),
			title_level: settings.relatedPosts.titleLevel,
			template: settings.relatedPosts.template,
			footer_columns:
				settings.relatedPosts.footerColumns === 1 ||
				settings.relatedPosts.footerColumns === 2
					? settings.relatedPosts.footerColumns
					: 3,
			block_order: RELATED_POSTS_BLOCK_IDS.filter( ( blockId ) =>
				settings.relatedPosts.blockOrder.includes( blockId ),
			),
			block_regions: RELATED_POSTS_BLOCK_IDS.reduce<Record<string, string>>( ( acc, blockId ) => {
				const region = settings.relatedPosts.blockRegions[ blockId ];
				if (
					region === 'header' ||
					region === 'body' ||
					region === 'left_sidebar' ||
					region === 'footer_left' ||
					region === 'footer_center' ||
					region === 'footer_right'
				) {
					acc[ blockId ] = region;
				}
				return acc;
			}, {} ),
			grid_container: {
				border_width_top: Number.isFinite( settings.relatedPosts.gridContainer.borderWidths.top )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.borderWidths.top ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.top,
				border_width_right: Number.isFinite( settings.relatedPosts.gridContainer.borderWidths.right )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.borderWidths.right ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.right,
				border_width_bottom: Number.isFinite( settings.relatedPosts.gridContainer.borderWidths.bottom )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.borderWidths.bottom ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.bottom,
				border_width_left: Number.isFinite( settings.relatedPosts.gridContainer.borderWidths.left )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.borderWidths.left ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderWidths.left,
				border_radius: Number.isFinite( settings.relatedPosts.gridContainer.borderRadius )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.borderRadius ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.borderRadius,
				border_style: settings.relatedPosts.gridContainer.borderStyle,
				border_color: settings.relatedPosts.gridContainer.borderColor,
				bg_color: settings.relatedPosts.gridContainer.bgColor,
				padding_top: Number.isFinite( settings.relatedPosts.gridContainer.paddings.top )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.paddings.top ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.top,
				padding_right: Number.isFinite( settings.relatedPosts.gridContainer.paddings.right )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.paddings.right ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.right,
				padding_bottom: Number.isFinite( settings.relatedPosts.gridContainer.paddings.bottom )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.paddings.bottom ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.bottom,
				padding_left: Number.isFinite( settings.relatedPosts.gridContainer.paddings.left )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.gridContainer.paddings.left ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.paddings.left,
				gap: Number.isFinite( settings.relatedPosts.gridContainer.gap )
					? Math.max( 0, Math.min( 64, settings.relatedPosts.gridContainer.gap ) )
					: RELATED_POSTS_DEFAULTS.gridContainer.gap,
			},
			post_container: {
				border_width_top: Number.isFinite( settings.relatedPosts.postContainer.borderWidths.top )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.borderWidths.top ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.top,
				border_width_right: Number.isFinite( settings.relatedPosts.postContainer.borderWidths.right )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.borderWidths.right ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.right,
				border_width_bottom: Number.isFinite( settings.relatedPosts.postContainer.borderWidths.bottom )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.borderWidths.bottom ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.bottom,
				border_width_left: Number.isFinite( settings.relatedPosts.postContainer.borderWidths.left )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.borderWidths.left ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderWidths.left,
				border_radius: Number.isFinite( settings.relatedPosts.postContainer.borderRadius )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.borderRadius ) )
					: RELATED_POSTS_DEFAULTS.postContainer.borderRadius,
				border_style: settings.relatedPosts.postContainer.borderStyle,
				border_color: settings.relatedPosts.postContainer.borderColor,
				bg_color: settings.relatedPosts.postContainer.bgColor,
				padding_top: Number.isFinite( settings.relatedPosts.postContainer.paddings.top )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.paddings.top ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.top,
				padding_right: Number.isFinite( settings.relatedPosts.postContainer.paddings.right )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.paddings.right ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.right,
				padding_bottom: Number.isFinite( settings.relatedPosts.postContainer.paddings.bottom )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.paddings.bottom ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.bottom,
				padding_left: Number.isFinite( settings.relatedPosts.postContainer.paddings.left )
					? Math.max( 0, Math.min( 50, settings.relatedPosts.postContainer.paddings.left ) )
					: RELATED_POSTS_DEFAULTS.postContainer.paddings.left,
				gap: Number.isFinite( settings.relatedPosts.postContainer.gap )
					? Math.max( 0, Math.min( 64, settings.relatedPosts.postContainer.gap ) )
					: RELATED_POSTS_DEFAULTS.postContainer.gap,
			},
			header_container: {
				border_width_top: Number.isFinite( settings.relatedPosts.headerContainer?.borderWidths.top )
					? Math.max( 0, Math.min( 12, Number( settings.relatedPosts.headerContainer?.borderWidths.top ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.top ?? 0,
				border_width_right: Number.isFinite( settings.relatedPosts.headerContainer?.borderWidths.right )
					? Math.max( 0, Math.min( 12, Number( settings.relatedPosts.headerContainer?.borderWidths.right ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.right ?? 0,
				border_width_bottom: Number.isFinite( settings.relatedPosts.headerContainer?.borderWidths.bottom )
					? Math.max( 0, Math.min( 12, Number( settings.relatedPosts.headerContainer?.borderWidths.bottom ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.bottom ?? 0,
				border_width_left: Number.isFinite( settings.relatedPosts.headerContainer?.borderWidths.left )
					? Math.max( 0, Math.min( 12, Number( settings.relatedPosts.headerContainer?.borderWidths.left ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderWidths.left ?? 0,
				border_radius: Number.isFinite( settings.relatedPosts.headerContainer?.borderRadius )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.borderRadius ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.borderRadius ?? 0,
				border_style: settings.relatedPosts.headerContainer?.borderStyle ?? RELATED_POSTS_DEFAULTS.headerContainer?.borderStyle ?? 'solid',
				border_color: settings.relatedPosts.headerContainer?.borderColor ?? RELATED_POSTS_DEFAULTS.headerContainer?.borderColor ?? '#e2e8f0',
				bg_color: settings.relatedPosts.headerContainer?.bgColor ?? RELATED_POSTS_DEFAULTS.headerContainer?.bgColor ?? 'transparent',
				padding_top: Number.isFinite( settings.relatedPosts.headerContainer?.paddings.top )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.paddings.top ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.top ?? 0,
				padding_right: Number.isFinite( settings.relatedPosts.headerContainer?.paddings.right )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.paddings.right ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.right ?? 0,
				padding_bottom: Number.isFinite( settings.relatedPosts.headerContainer?.paddings.bottom )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.paddings.bottom ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.bottom ?? 0,
				padding_left: Number.isFinite( settings.relatedPosts.headerContainer?.paddings.left )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.paddings.left ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.paddings.left ?? 0,
				margin_top: Number.isFinite( settings.relatedPosts.headerContainer?.margins.top )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.margins.top ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.top ?? 0,
				margin_right: Number.isFinite( settings.relatedPosts.headerContainer?.margins.right )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.margins.right ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.right ?? 0,
				margin_bottom: Number.isFinite( settings.relatedPosts.headerContainer?.margins.bottom )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.margins.bottom ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.bottom ?? 12,
				margin_left: Number.isFinite( settings.relatedPosts.headerContainer?.margins.left )
					? Math.max( 0, Math.min( 50, Number( settings.relatedPosts.headerContainer?.margins.left ) ) )
					: RELATED_POSTS_DEFAULTS.headerContainer?.margins.left ?? 0,
			},
			header_title: {
				font_style: {
					bold: Boolean( settings.relatedPosts.headerTitle?.fontStyle.bold ),
					italic: Boolean( settings.relatedPosts.headerTitle?.fontStyle.italic ),
					underline: Boolean( settings.relatedPosts.headerTitle?.fontStyle.underline ),
				},
				color: settings.relatedPosts.headerTitle?.color ?? RELATED_POSTS_DEFAULTS.headerTitle?.color ?? '#0f172a',
				font_size: Number.isFinite( settings.relatedPosts.headerTitle?.fontSize )
					? Math.max( 10, Math.min( 40, Number( settings.relatedPosts.headerTitle?.fontSize ) ) )
					: RELATED_POSTS_DEFAULTS.headerTitle?.fontSize ?? 18,
			},
			featured_image_size: settings.relatedPosts.featuredImageSize.trim(),
			featured_image_radius: Number.isFinite( settings.relatedPosts.featuredImageRadius )
				? Math.max( 0, Math.min( 64, settings.relatedPosts.featuredImageRadius ) )
				: RELATED_POSTS_DEFAULTS.featuredImageRadius,
			title_font_size: Number.isFinite( settings.relatedPosts.titleFontSize )
				? Math.max( 10, Math.min( 64, settings.relatedPosts.titleFontSize ) )
				: RELATED_POSTS_DEFAULTS.titleFontSize,
			title_color: settings.relatedPosts.titleColor,
			title_bold: Boolean( settings.relatedPosts.titleBold ),
			title_italic: Boolean( settings.relatedPosts.titleItalic ),
			excerpt_font_size: Number.isFinite( settings.relatedPosts.excerptFontSize )
				? Math.max( 10, Math.min( 48, settings.relatedPosts.excerptFontSize ) )
				: RELATED_POSTS_DEFAULTS.excerptFontSize,
			excerpt_color: settings.relatedPosts.excerptColor,
			excerpt_max_chars: Number.isFinite( settings.relatedPosts.excerptMaxChars )
				? Math.max( 30, Math.min( 1000, settings.relatedPosts.excerptMaxChars ) )
				: RELATED_POSTS_DEFAULTS.excerptMaxChars,
			excerpt_fade_mask: Boolean( settings.relatedPosts.excerptFadeMask ),
			excerpt_fade_color: settings.relatedPosts.excerptFadeColor,
			excerpt_mask_height: Number.isFinite( settings.relatedPosts.excerptMaskHeight )
				? Math.max( 8, Math.min( 200, settings.relatedPosts.excerptMaskHeight ) )
				: RELATED_POSTS_DEFAULTS.excerptMaskHeight,
			author_font_size: Number.isFinite( settings.relatedPosts.authorFontSize )
				? Math.max( 10, Math.min( 48, settings.relatedPosts.authorFontSize ) )
				: RELATED_POSTS_DEFAULTS.authorFontSize,
			author_color: settings.relatedPosts.authorColor,
			author_bold: Boolean( settings.relatedPosts.authorBold ),
			author_italic: Boolean( settings.relatedPosts.authorItalic ),
			auto_inject_enabled: Boolean( settings.relatedPosts.autoInjectEnabled ),
			display_preset: settings.relatedPosts.displayPreset,
			data_limit: getRelatedPostsDataLimitByPreset( settings.relatedPosts.displayPreset ),
			enabled_post_types: settings.relatedPosts.enabledPostTypes
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' ),
			insert_position: settings.relatedPosts.insertPosition,
		},
		notFoundManager: {
			monitor_mode: settings.notFoundManager.monitorMode,
			enable_daily_alert: Boolean( settings.notFoundManager.enableDailyAlert ),
			ignore_query_params: Boolean( settings.notFoundManager.ignoreQueryParams ),
			log_limit: Number.isFinite( settings.notFoundManager.logLimit )
				? Math.max( 100, Math.min( 100000, settings.notFoundManager.logLimit ) )
				: NOT_FOUND_MANAGER_DEFAULTS.logLimit,
			retention_days: Number.isFinite( settings.notFoundManager.retentionDays )
				? Math.max( 1, Math.min( 3650, settings.notFoundManager.retentionDays ) )
				: NOT_FOUND_MANAGER_DEFAULTS.retentionDays,
			exclude_patterns: settings.notFoundManager.excludePatterns
				.map( ( pattern ) => pattern.trim() )
				.filter( ( pattern ) => pattern !== '' ),
			fallback_redirect_mode: settings.notFoundManager.fallbackRedirectMode,
			fallback_redirect_target: settings.notFoundManager.fallbackRedirectTarget.trim(),
			fallback_redirect_code: settings.notFoundManager.fallbackRedirectCode,
		},
		notify: {
			enabled: Boolean( settings.notify.enabled ),
			custom: {
				visible_blocks: settings.notify.custom.visibleBlocks,
				hidden_blocks: settings.notify.custom.hiddenBlocks,
			},
			message: {
				subject: settings.notify.message.subject.trim(),
				intro: settings.notify.message.intro.trim(),
				footer: settings.notify.message.footer.trim(),
			},
			logs: {
				retention_days: Number.isFinite( settings.notify.logs.retentionDays )
					? Math.max( 1, Math.min( 3650, settings.notify.logs.retentionDays ) )
					: NOTIFY_DEFAULTS.logs.retentionDays,
			},
			schedule: {
				timezone: settings.notify.schedule.timezone.trim(),
				time: settings.notify.schedule.time,
			},
			channels: {
				email: {
					enabled: Boolean( settings.notify.channels.email.enabled ),
					recipients: settings.notify.channels.email.recipients
						.map( ( item ) => item.trim() )
						.filter( ( item ) => item !== '' ),
					smtp: {
						host: settings.notify.channels.email.smtp.host.trim(),
						port: Number.isFinite( settings.notify.channels.email.smtp.port )
							? Math.max( 1, Math.min( 65535, settings.notify.channels.email.smtp.port ) )
							: NOTIFY_DEFAULTS.channels.email.smtp.port,
						auth: Boolean( settings.notify.channels.email.smtp.auth ),
						secure:
							settings.notify.channels.email.smtp.secure === 'tls' ||
							settings.notify.channels.email.smtp.secure === 'ssl'
								? settings.notify.channels.email.smtp.secure
								: '',
						username: settings.notify.channels.email.smtp.username.trim(),
						password: settings.notify.channels.email.smtp.password,
						timeout: Number.isFinite( settings.notify.channels.email.smtp.timeout )
							? Math.max( 1, Math.min( 120, settings.notify.channels.email.smtp.timeout ) )
							: NOTIFY_DEFAULTS.channels.email.smtp.timeout,
						fromEmail: settings.notify.channels.email.smtp.fromEmail.trim(),
						fromName: settings.notify.channels.email.smtp.fromName.trim(),
					},
				},
				telegram: {
					enabled: Boolean( settings.notify.channels.telegram.enabled ),
					botToken: settings.notify.channels.telegram.botToken.trim(),
					chatId: settings.notify.channels.telegram.chatId.trim(),
					topicId: settings.notify.channels.telegram.topicId.trim(),
				},
				discord: {
					enabled: Boolean( settings.notify.channels.discord.enabled ),
					webhook: settings.notify.channels.discord.webhook.trim(),
					username: settings.notify.channels.discord.username.trim(),
					avatar: settings.notify.channels.discord.avatar.trim(),
				},
				teams: {
					enabled: Boolean( settings.notify.channels.teams.enabled ),
					webhook: settings.notify.channels.teams.webhook.trim(),
				},
			},
		},
		markdownForAgents: {
			enabled: Boolean( settings.markdownForAgents.enabled ),
			prompts_for_agents: Boolean( settings.markdownForAgents.promptsForAgents ),
			include_frontmatter: Boolean( settings.markdownForAgents.includeFrontmatter ),
			post_types: settings.markdownForAgents.postTypes
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' ),
		},
		llmsTxt: {
			enabled: Boolean( settings.llmsTxt.enabled ),
			custom_declaration: settings.llmsTxt.customDeclaration.trim(),
			auto_section_title: settings.llmsTxt.autoSectionTitle.trim(),
			index_strategy: settings.llmsTxt.indexStrategy,
			auto_topic_cluster_groups: Boolean( settings.llmsTxt.autoTopicClusterGroups ),
			use_markdown_links: Boolean( settings.llmsTxt.useMarkdownLinks ),
			add_to_sitemap: Boolean( settings.llmsTxt.addToSitemap ),
			exclude_noindex: Boolean( settings.llmsTxt.excludeNoindex ),
			exclude_password_protected: Boolean( settings.llmsTxt.excludePasswordProtected ),
			min_word_count: Number.isFinite( settings.llmsTxt.minWordCount )
				? Math.max( 0, Math.min( 5000, settings.llmsTxt.minWordCount ) )
				: LLMS_TXT_DEFAULTS.minWordCount,
			sections: settings.llmsTxt.sections
				.filter( ( section ) => section.id.trim() !== '' && section.title.trim() !== '' )
				.map( ( section ) => ( {
					id: section.id.trim(),
					title: section.title.trim(),
					description: section.description.trim(),
					post_ids: section.postIds
						.map( ( value ) => Number( value ) )
						.filter( ( value ) => Number.isFinite( value ) && value > 0 ),
					max_items: Number.isFinite( section.maxItems )
						? Math.max( 1, Math.min( 100, section.maxItems ) )
						: 20,
					hidden: Boolean( section.hidden ),
				} ) ),
			extensions: settings.llmsTxt.extensions.map( ( extension ) => ( {
				id: extension.id.trim(),
				title: extension.title.trim(),
				description: extension.description.trim(),
				path: extension.path.trim().replace( /^\/+|\/+$/g, '' ),
				custom_declaration: extension.customDeclaration.trim(),
				filename: extension.filename,
				enabled: Boolean( extension.enabled ),
				sections: extension.sections
					.filter( ( section ) => section.id.trim() !== '' && section.title.trim() !== '' )
					.map( ( section ) => ( {
						id: section.id.trim(),
						title: section.title.trim(),
						description: section.description.trim(),
						post_ids: section.postIds
							.map( ( value ) => Number( value ) )
							.filter( ( value ) => Number.isFinite( value ) && value > 0 ),
						max_items: Number.isFinite( section.maxItems )
							? Math.max( 1, Math.min( 100, section.maxItems ) )
							: 20,
						hidden: Boolean( section.hidden ),
					} ) ),
			} ) ),
			post_types: settings.llmsTxt.postTypes
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' ),
		},
		modules: MODULE_KEYS.reduce<RestModuleSettings>( ( acc, key ) => {
			acc[ key ] = Boolean( settings.modules?.[ key ] );
			return acc;
		}, {} ),
		moduleOrder: settings.moduleOrder,
		panelOrder: settings.panelOrder,
		panelVisibility: settings.panelVisibility,
		contentBlockOrder: settings.contentBlockOrder,
		contentBlockGap: settings.contentBlockGap,
		contentBlockMarginTop: settings.contentBlockMarginTop,
	};
};

const App = () => {
	const [ settings, setSettings ] = useState<SettingsState | null>( null );
	const [ original, setOriginal ] = useState<SettingsState | null>( null );
	const [ meta, setMeta ] = useState<MetaPayload | null>( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ linkSuggestionsDirty, setLinkSuggestionsDirty ] = useState( false );
	const linkSuggestionsSubmitRef = useRef<null |( () => Promise<void> )>( null );
	const [ notice, setNotice ] = useState<NoticeState | null>( null );
	const [ loadErrorMessage, setLoadErrorMessage ] = useState<string | null>( null );
	const [ wizardDismissed, setWizardDismissed ] = useState<boolean>( true );
	const [ activePage, setActivePage ] = useState<ShellPageName>( initialPage );
	const [ registeredAdminPages, setRegisteredAdminPages ] = useState(
		() => getRegisteredAdminPages(),
	);
	const registeredAdminPageMap = useMemo(
		() => new Map( registeredAdminPages.map( ( page ) => [ page.key, page ] ) ),
		[ registeredAdminPages ],
	);
	const initialTab =
		initialPage === 'notify' && initialNotifyTab
			? initialNotifyTab
			: initialPage === 'migration' && initialMigrationView !== 'list'
				? initialMigrationView
				: initialPage === 'settings' && initialSettingsTab
					? initialSettingsTab
					: initialPage === 'settings'
						? 'onPageSeo'
						: '';
	const [ activeTab, setActiveTab ] = useState<ShellTabKey>( initialTab );
	const [ migrationView, setMigrationView ] = useState<'list' | 'yoast' | 'rankmath' | 'aioseo' | 'seopress'>(
		initialMigrationView,
	);
	const [ debugState, setDebugState ] = useState<DebugState | null>( null );
	const [ isDebugLoading, setIsDebugLoading ] = useState( false );
	const [ debugError, setDebugError ] = useState<string | null>( null );
	const [ isDebugEnabling, setIsDebugEnabling ] = useState( false );
	const defaultSettingsSnapshot = useMemo( () => normalizeSettings( {} ), [] );

	const adminBaseUrl =
		typeof config.adminUrl === 'string' && config.adminUrl
			? config.adminUrl
			: '';

	const logoutUrl = useMemo( () => {
		const redirectTo = typeof window !== 'undefined' ? window.location.href : '';
		const configuredLogoutUrl =
			typeof config.logoutUrl === 'string' && config.logoutUrl ? config.logoutUrl : '';
		const fallback = `/wp-login.php?action=logout${ redirectTo ? `&redirect_to=${ encodeURIComponent( redirectTo ) }` : '' }`;
		const base = adminBaseUrl || ( typeof window !== 'undefined' ? window.location.origin : '' );

		try {
			const url = configuredLogoutUrl
				? new URL( configuredLogoutUrl, base || undefined )
				: new URL( 'wp-login.php?action=logout', base || undefined );
			if ( redirectTo ) {
				url.searchParams.set( 'redirect_to', redirectTo );
			}
			return url.toString();
		} catch {
			return fallback;
		}
	}, [ adminBaseUrl ] );
	const isSessionExpiredLoadError = loadErrorMessage === SESSION_EXPIRED_MESSAGE;

	const buildAdminPageUrl = useCallback(
		( page: ShellPageName, tab?: string ) => {
			const pageSlug = configuredAdminPageSlugMap.get( page ) ?? 'airygen-dashboard';
			const base = adminBaseUrl || `${ window.location.origin }/wp-admin/`;

			try {
				const url = new URL( 'admin.php', base );
				url.searchParams.set( 'page', pageSlug );
				if ( tab ) {
					url.searchParams.set( 'tab', tab );
				}
				return url.toString();
			} catch {
				return '';
			}
		},
		[ adminBaseUrl ],
	);

	const buildHashFromState = useCallback(
		( page: ShellPageName, tab?: string ) => {
			if ( page === 'settings' && tab && isSettingsTabHash( tab ) ) {
				return `#settings/${ tab }`;
			}
			if ( page === 'notify' && tab && isNotifyTabKey( tab ) ) {
				return `#notify/${ tab }`;
			}
			if ( page === 'migration' && tab && isMigrationTabKey( tab ) ) {
				return `#migration/${ tab }`;
			}
			return `#${ page }`;
		},
		[],
	);

	const updateBrowserUrl = useCallback(
		(
			page: ShellPageName,
			tab?: string,
			mode: 'push' | 'replace' = 'push',
		) => {
			let normalizedTab = '';
			if ( page === 'settings' && tab && isSettingsTabHash( tab ) ) {
				normalizedTab = tab;
			} else if ( page === 'notify' && tab && isNotifyTabKey( tab ) ) {
				normalizedTab = tab;
			} else if ( page === 'migration' && tab && isMigrationTabKey( tab ) ) {
				normalizedTab = tab;
			}

			const nextUrl = buildAdminPageUrl(
				page,
				page === 'settings' || page === 'notify' || page === 'migration'
					? normalizedTab !== ''
						? normalizedTab
						: undefined
					: undefined,
			);
			const hash = buildHashFromState( page, normalizedTab );
			const state = {
				airygenPage: page,
				airygenTab: normalizedTab,
			};
			const method = mode === 'replace' ? 'replaceState' : 'pushState';

			if ( nextUrl ) {
				window.history[ method ]( state, '', `${ nextUrl }${ hash }` );
				return;
			}

			window.history[ method ](
				state,
				'',
				`${ window.location.pathname }${ window.location.search }${ hash }`,
			);
		},
		[ buildAdminPageUrl, buildHashFromState ],
	);

	const setPageWithUrl = useCallback(
		( page: ShellPageName, tab?: string ) => {
			setActivePage( page );
			if ( page === 'migration' ) {
				if ( tab && isMigrationTabKey( tab ) ) {
					setMigrationView( tab );
					setActiveTab( tab );
				} else {
					setMigrationView( 'list' );
				}
			}
			if ( page === 'settings' && tab && isSettingsTabHash( tab ) ) {
				setActiveTab( tab );
			}
			if ( page === 'notify' && tab && isNotifyTabKey( tab ) ) {
				setActiveTab( tab );
			} else if ( page === 'notify' ) {
				setActiveTab( NOTIFY_HOME_TAB );
			}
			updateBrowserUrl( page, tab );
		},
		[ updateBrowserUrl ],
	);

	const openMigrationYoast = useCallback( () => {
		setPageWithUrl( 'migration', 'yoast' );
	}, [ setPageWithUrl ] );

	const openMigrationRankMath = useCallback( () => {
		setPageWithUrl( 'migration', 'rankmath' );
	}, [ setPageWithUrl ] );

	const openMigrationAioseo = useCallback( () => {
		setPageWithUrl( 'migration', 'aioseo' );
	}, [ setPageWithUrl ] );

	const openMigrationSeoPress = useCallback( () => {
		setPageWithUrl( 'migration', 'seopress' );
	}, [ setPageWithUrl ] );

	const pages: ShellPage[] = useMemo( () => {
		return configuredAdminPages.map( ( page ) => ( {
			name: page.key,
			title: page.title,
		} ) );
	}, [] );

	useEffect( () => {
		const syncRegisteredAdminPages = () => {
			setRegisteredAdminPages( getRegisteredAdminPages() );
		};

		syncRegisteredAdminPages();
		window.addEventListener( ADMIN_PAGES_UPDATED_EVENT, syncRegisteredAdminPages );

		return () => {
			window.removeEventListener( ADMIN_PAGES_UPDATED_EVENT, syncRegisteredAdminPages );
		};
	}, [] );

	useEffect( () => {
		if ( activePage !== 'debug' && ! configuredAdminPageKeys.has( activePage ) ) {
			setPageWithUrl( 'dashboard' );
		}
	}, [ activePage, setPageWithUrl ] );

	const handleSelectTab = useCallback(
		( tab: ShellTabKey ) => {
			if (
				activePage === 'migration' &&
				( tab === 'yoast' || tab === 'rankmath' || tab === 'aioseo' || tab === 'seopress' )
			) {
				setActiveTab( tab );
				setMigrationView( tab );
				updateBrowserUrl( 'migration', tab );
				return;
			}
			if (
				activePage === 'notify' &&
				( tab === 'notifyDigest' ||
					tab === 'notifyEmail' ||
					tab === 'notifyTelegram' ||
					tab === 'notifyDiscord' ||
					tab === 'notifyTeams' )
			) {
				setActiveTab( tab );
				updateBrowserUrl( 'notify', tab );
				return;
			}

			setActiveTab( tab );
			if ( activePage === 'settings' ) {
				updateBrowserUrl( 'settings', tab );
			}
		},
		[ activePage, updateBrowserUrl ],
	);
	const applyHashRoute = useCallback( ( route: HashRoute | null ) => {
		if ( ! route ) {
			return;
		}

		setActivePage( route.page );

		if ( route.page === 'migration' ) {
			if ( route.tab && isMigrationTabKey( route.tab ) ) {
				setMigrationView( route.tab );
				setActiveTab( route.tab );
				return;
			}
			setMigrationView( 'list' );
			return;
		}

		if ( route.page === 'settings' && route.tab && isSettingsTabHash( route.tab ) ) {
			setActiveTab( route.tab );
			return;
		}
		if ( route.page === 'notify' && route.tab && isNotifyTabKey( route.tab ) ) {
			setActiveTab( route.tab );
			return;
		}
		if ( route.page === 'notify' ) {
			setActiveTab( NOTIFY_HOME_TAB );
		}
	}, [] );

	const moduleMetadata = MODULE_METADATA;
	const moduleOrder = settings?.moduleOrder ?? MODULE_KEYS;
	const [ draggingModule, setDraggingModule ] = useState<ModuleKey | null>( null );
	const panelOrder = settings?.panelOrder ?? PANEL_KEYS;
	const [ draggingPanel, setDraggingPanel ] = useState<PanelKey | null>( null );

	const orderedModuleMetadata = useMemo( () => {
		const map = new Map( moduleMetadata.map( ( module ) => [ module.key, module ] ) );
		const ordered: ModuleMetadata[] = [];

		moduleOrder.forEach( ( key ) => {
			const entry = map.get( key );
			if ( entry ) {
				ordered.push( entry );
			}
		} );

		moduleMetadata.forEach( ( module ) => {
			if ( ! moduleOrder.includes( module.key ) ) {
				ordered.push( module );
			}
		} );

		return ordered;
	}, [ moduleMetadata, moduleOrder ] );

	const orderedPanelMetadata = useMemo( () => {
		const map = new Map( PANEL_METADATA.map( ( panel ) => [ panel.key, panel ] ) );
		const ordered: PanelMetadata[] = [];

		panelOrder.forEach( ( key ) => {
			const entry = map.get( key );
			if ( entry ) {
				ordered.push( entry );
				map.delete( key );
			}
		} );

		map.forEach( ( panel ) => ordered.push( panel ) );

		return ordered;
	}, [ panelOrder ] );

	const handleModuleDragStart = useCallback(
		( event: DragEvent<HTMLDivElement>, key: ModuleKey ) => {
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( 'text/plain', key );
			setDraggingModule( key );
		},
		[],
	);

	const handleModuleDragOver = useCallback( ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
	}, [] );

	const handleModuleDrop = useCallback(
		( event: DragEvent<HTMLDivElement>, targetKey: ModuleKey ) => {
			event.preventDefault();
			const payload = draggingModule ?? event.dataTransfer.getData( 'text/plain' );
			if ( ! payload || payload === targetKey ) {
				setDraggingModule( null );
				return;
			}

			const sourceKey = typeof payload === 'string' ? payload : ( payload as ModuleKey );

			if ( ! isModuleKey( sourceKey ) ) {
				setDraggingModule( null );
				return;
			}

			const currentOrder = settings?.moduleOrder ?? MODULE_KEYS;
			const fromIndex = currentOrder.indexOf( sourceKey );
			const toIndex = currentOrder.indexOf( targetKey );

			if ( fromIndex === -1 || toIndex === -1 || fromIndex === toIndex ) {
				setDraggingModule( null );
				return;
			}

			const next = currentOrder.slice();
			next.splice( fromIndex, 1 );
			next.splice( toIndex, 0, sourceKey );

			setSettings( ( prev ) => ( prev ? { ...prev, moduleOrder: next } : prev ) );
			setNotice( null );
			setDraggingModule( null );
		},
		[ draggingModule, settings, setNotice ],
	);

	const handleModuleDragEnd = useCallback( () => {
		setDraggingModule( null );
	}, [] );

	const handlePanelDragStart = useCallback(
		( event: DragEvent<HTMLDivElement>, key: PanelKey ) => {
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( 'text/plain', key );
			setDraggingPanel( key );
		},
		[],
	);

	const handlePanelDragOver = useCallback( ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
	}, [] );

	const handlePanelDrop = useCallback(
		( event: DragEvent<HTMLDivElement>, targetKey: PanelKey ) => {
			event.preventDefault();
			const payload = draggingPanel ?? event.dataTransfer.getData( 'text/plain' );
			if ( ! payload || payload === targetKey ) {
				setDraggingPanel( null );
				return;
			}

			const sourceKey = typeof payload === 'string' ? payload : ( payload as PanelKey );

			if ( ! isPanelKey( sourceKey ) ) {
				setDraggingPanel( null );
				return;
			}

			const currentOrder = settings?.panelOrder ?? PANEL_KEYS;
			const fromIndex = currentOrder.indexOf( sourceKey );
			const toIndex = currentOrder.indexOf( targetKey );

			if ( fromIndex === -1 || toIndex === -1 || fromIndex === toIndex ) {
				setDraggingPanel( null );
				return;
			}

			const next = currentOrder.slice();
			next.splice( fromIndex, 1 );
			next.splice( toIndex, 0, sourceKey );

			setSettings( ( prev ) => ( prev ? { ...prev, panelOrder: next } : prev ) );
			setNotice( null );
			setDraggingPanel( null );
		},
		[ draggingPanel, settings, setNotice ],
	);

	const handlePanelDragEnd = useCallback( () => {
		setDraggingPanel( null );
	}, [] );

	const handlePanelToggle = useCallback(
		( key: PanelKey, value: boolean ) => {
			setSettings( ( prev ) => {
				if ( ! prev ) {
					return prev;
				}
				return {
					...prev,
					panelVisibility: {
						...prev.panelVisibility,
						[ key ]: value,
					},
				};
			} );
			setNotice( null );
		},
		[ setNotice ],
	);

	const handleContentBlockOrderChange = useCallback( ( next: ContentBlockKey[] ) => {
		setSettings( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			return { ...prev, contentBlockOrder: next };
		} );
	}, [] );

	const handleContentBlockGapChange = useCallback( ( next: number ) => {
		setSettings( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			return { ...prev, contentBlockGap: next };
		} );
	}, [] );

	const handleContentBlockMarginTopChange = useCallback( ( next: number ) => {
		setSettings( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			return { ...prev, contentBlockMarginTop: next };
		} );
	}, [] );

	const handleContentBlockZoneChange = useCallback( ( key: ContentBlockKey, zone: 'before' | 'after' ) => {
		setSettings( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			const next = { ...prev };
			switch ( key ) {
				case 'toc':
					next.toc = { ...next.toc, position: zone === 'before' ? 'before-content' : 'after-first-paragraph' };
					break;
				case 'breadcrumbs':
					next.breadcrumbs = { ...next.breadcrumbs, injectionPosition: zone === 'before' ? 'before_content' : 'after_content' };
					break;
				case 'relatedPosts':
					next.relatedPosts = { ...next.relatedPosts, insertPosition: zone === 'before' ? 'before_content' : 'after_content' };
					break;
				case 'topicCluster':
					next.topicCluster = { ...next.topicCluster, insertPosition: zone === 'before' ? 'before-content' : 'after-content' };
					break;
			}
			return next;
		} );
	}, [] );

	const openModuleSettings = useCallback(
		( key: ModuleKey ) => {
			if ( 'notify' === key ) {
				setPageWithUrl( 'notify' );
				return;
			}
			if ( 'topicCluster' === key ) {
				setPageWithUrl( 'topicCluster' );
				return;
			}
			const settingsTab = isSettingsTabHash( key ) ? key : 'onPageSeo';
			setPageWithUrl( 'settings', settingsTab );
		},
		[ setPageWithUrl ],
	);
	const restBase = useMemo( () => restPath.replace( /\/[^/]+$/, '' ), [] );

	const sitemapPreviewUrl = useMemo( () => {
		const origin =
			typeof window !== 'undefined' && window.location
				? window.location.origin
				: '';
		return `${ origin.replace( /\/$/, '' ) }/sitemap.xml`;
	}, [] );

	const robotsPreviewUrl = useMemo( () => {
		const origin =
			typeof window !== 'undefined' && window.location
				? window.location.origin
				: '';
		return `${ origin.replace( /\/$/, '' ) }/robots.txt`;
	}, [] );

	const copyTextWithFeedback = useCallback(
		( value: string, successMessage: string, failureMessage: string ) => {
			const handleSuccess = () =>
				setNotice( {
					status: 'success',
					message: successMessage,
				} );

			const handleFailure = () =>
				setNotice( {
					status: 'error',
					message: failureMessage,
				} );

			if ( ! value ) {
				handleFailure();
				return;
			}

			if ( typeof navigator !== 'undefined' && navigator?.clipboard?.writeText ) {
				navigator.clipboard.writeText( value ).then( handleSuccess ).catch( handleFailure );
				return;
			}

			if ( typeof document === 'undefined' ) {
				handleFailure();
				return;
			}

			try {
				const temp = document.createElement( 'textarea' );
				temp.value = value;
				temp.setAttribute( 'readonly', '' );
				temp.style.position = 'absolute';
				temp.style.left = '-9999px';
				document.body.appendChild( temp );
				temp.select();
				document.execCommand( 'copy' );
				document.body.removeChild( temp );
				handleSuccess();
			} catch {
				handleFailure();
			}
		},
		[ setNotice ],
	);

	const handleCopySitemapLink = () => {
		copyTextWithFeedback(
			sitemapPreviewUrl,
			__( 'Copied to clipboard!', 'airygen-seo' ),
			__( 'Failed to copy to clipboard.', 'airygen-seo' ),
		);
	};

	const fetchDebugState = useCallback( () => {
		setIsDebugLoading( true );
		setDebugError( null );

		apiFetch<DebugState>( { path: debugRestPath } )
			.then( ( response ) => {
				setDebugState( response );
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error ? error.message : __( 'Failed to load debug info.', 'airygen-seo' );
				setDebugError( message );
			} )
			.finally( () => {
				setIsDebugLoading( false );
			} );
	}, [] );

	const handleDisableDebug = useCallback( () => {
		setIsDebugEnabling( true );
		setDebugError( null );

		apiFetch<DebugState>( {
			path: debugDisablePath,
			method: 'POST',
		} )
			.then( ( response ) => {
				setDebugState( response );
				setNotice( {
					status: 'success',
					message: __( 'Debug mode disabled.', 'airygen-seo' ),
				} );
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: __( 'Failed to disable debug mode.', 'airygen-seo' ),
				} );
			} )
			.finally( () => {
				setIsDebugEnabling( false );
			} );
	}, [] );

	const handleEnableDebug = useCallback( () => {
		setIsDebugEnabling( true );
		setDebugError( null );

		apiFetch<DebugState>( {
			path: debugEnablePath,
			method: 'POST',
		} )
			.then( ( response ) => {
				setDebugState( response );
				setNotice( {
					status: 'success',
					message: __( 'Debug mode enabled.', 'airygen-seo' ),
				} );
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: __( 'Failed to enable debug mode.', 'airygen-seo' ),
				} );
			} )
			.finally( () => {
				setIsDebugEnabling( false );
			} );
	}, [] );

	const handleClassicEditorToggle = useCallback( ( enabledValue: boolean ) => {
		setIsDebugEnabling( true );
		setDebugError( null );

		apiFetch<DebugState>( {
			path: debugEditorPath,
			method: 'POST',
			data: { forceClassic: enabledValue },
		} )
			.then( ( response ) => {
				setDebugState( response );
				setNotice( {
					status: 'success',
					message: enabledValue
						? __( 'Classic editor mode enabled for testing.', 'airygen-seo' )
						: __( 'Classic editor mode disabled.', 'airygen-seo' ),
				} );
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: __( 'Failed to update editor mode.', 'airygen-seo' ),
				} );
			} )
			.finally( () => {
				setIsDebugEnabling( false );
			} );
	}, [] );

	const handleDebugLevelChange = useCallback(
		( level: 'error' | 'warning' | 'info' ) => {
			setIsDebugEnabling( true );
			setDebugError( null );

			apiFetch<DebugState>( {
				path: debugLevelPath,
				method: 'POST',
				data: { level },
			} )
				.then( ( response ) => {
					setDebugState( response );
					setNotice( {
						status: 'success',
						message: __( 'Debug level updated.', 'airygen-seo' ),
					} );
				} )
				.catch( () => {
					setNotice( {
						status: 'error',
						message: __( 'Failed to update debug level.', 'airygen-seo' ),
					} );
				} )
				.finally( () => {
					setIsDebugEnabling( false );
				} );
		},
		[],
	);

	const fetchSettings = useCallback( () => {
		setIsLoading( true );

		apiFetch<ApiResponse>( { path: restPath } )
			.then( ( response ) => {
				const normalized = normalizeSettings( response.settings );
				setSettings( normalized );
				setOriginal( normalizeSettings( response.settings ) );
				setMeta( response.meta );
				setWizardDismissed( response.wizardDismissed ?? true );
				setLoadErrorMessage( null );
			} )
			.catch( ( error: unknown ) => {
				let message: string = __( 'Failed to load settings.', 'airygen-seo' );
				if ( isRestAuthError( error ) ) {
					message = SESSION_EXPIRED_MESSAGE;
				} else if ( error instanceof Error ) {
					message = error.message;
				}
				setLoadErrorMessage( message );
				setNotice( {
					status: 'error',
					message,
				} );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [] );

	const preflightSessionCheck = useCallback( async (): Promise<boolean> => {
		try {
			const response = await window.fetch( sessionCheckUrl, {
				method: 'GET',
				credentials: 'same-origin',
				headers: restNonce
					? {
						'X-WP-Nonce': restNonce,
						Accept: 'application/json',
					}
					: {
						Accept: 'application/json',
					},
			} );

			if ( response.ok ) {
				return true;
			}

			const payload = ( await response.json().catch( () => ( {} ) ) ) as {
				code?: string;
				message?: string;
			};

			if ( isRestAuthError( { status: response.status, ...payload } ) ) {
				restAuthLocked = true;
				lockSessionExpired( 'admin' );
				return false;
			}
		} catch {
			return true;
		}

		return true;
	}, [] );

	useEffect( () => {
		let isMounted = true;

		void ( async () => {
			const validSession = await preflightSessionCheck();
			if ( ! isMounted ) {
				return;
			}

			if ( ! validSession ) {
				setIsLoading( false );
				setLoadErrorMessage( SESSION_EXPIRED_MESSAGE );
				setNotice( {
					status: 'error',
					message: SESSION_EXPIRED_MESSAGE,
				} );
				return;
			}

			fetchSettings();
		} )();

		return () => {
			isMounted = false;
		};
	}, [ fetchSettings, preflightSessionCheck ] );

	useEffect( () => {
		if ( activePage !== 'debug' ) {
			return;
		}

		if ( debugState || isDebugLoading ) {
			return;
		}

		fetchDebugState();
	}, [ activePage, debugState, isDebugLoading, fetchDebugState ] );

	const isDirtySettings = useMemo( () => {
		if ( ! settings || ! original ) {
			return false;
		}

		return (
			JSON.stringify( serializeSettings( settings ) ) !==
			JSON.stringify( serializeSettings( original ) )
		);
	}, [ settings, original ] );
	const isDirty = useMemo(
		() => isDirtySettings || linkSuggestionsDirty,
		[ isDirtySettings, linkSuggestionsDirty ],
	);

	const updateSection = <K extends keyof SettingsState>(
		key: K,
		value: SettingsState[K],
	) => {
		setSettings( ( prev ) => ( prev ? { ...prev, [ key ]: value } : prev ) );
		setNotice( null );
	};

	useEffect( () => {
		setSettings( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}

			const nextCustom = enforceNotifyCustomAlertBlocks(
				prev.notify.custom,
				prev.notFoundManager.enableDailyAlert,
				prev.brokenLinkChecker.enableDailyAlert,
			);
			if (
				JSON.stringify( nextCustom.visibleBlocks ) === JSON.stringify( prev.notify.custom.visibleBlocks ) &&
				JSON.stringify( nextCustom.hiddenBlocks ) === JSON.stringify( prev.notify.custom.hiddenBlocks )
			) {
				return prev;
			}

			return {
				...prev,
				notify: {
					...prev.notify,
					custom: nextCustom,
				},
			};
		} );
	}, [
		settings?.notFoundManager.enableDailyAlert,
		settings?.brokenLinkChecker.enableDailyAlert,
		settings?.notify.custom.visibleBlocks,
		settings?.notify.custom.hiddenBlocks,
	] );

	const handleResetActiveModuleSettings = useCallback( () => {
		if ( ! settings || activePage !== 'settings' || ! isSettingsTabHash( activeTab ) ) {
			return;
		}

		const sectionKey = SETTINGS_RESET_SECTION_BY_TAB[ activeTab as ModuleKey ];
		if ( ! sectionKey ) {
			return;
		}

		const defaultSection = cloneDeep( defaultSettingsSnapshot[ sectionKey ] );
		setSettings( {
			...settings,
			[ sectionKey ]: defaultSection,
		} );
		setNotice( null );
	}, [ activePage, activeTab, defaultSettingsSnapshot, settings ] );

	const handleSave = () => {
		if ( ! settings ) {
			return;
		}

		if ( activeTab === 'linkSuggestions' ) {
			if ( ! linkSuggestionsSubmitRef.current ) {
				return;
			}

			setIsSaving( true );
			setNotice( null );

			linkSuggestionsSubmitRef.current()
				.then( () => {
					setNotice( {
						status: 'success',
						message: __( 'Link Suggestions settings saved.', 'airygen-seo' ),
					} );
					setLinkSuggestionsDirty( false );
				} )
				.catch( ( error: unknown ) => {
					const message =
						error instanceof Error
							? error.message
							: __( 'Failed to save Link Suggestions settings.', 'airygen-seo' );
					setNotice( {
						status: 'error',
						message,
					} );
				} )
				.finally( () => {
					setIsSaving( false );
				} );

			return;
		}

		setIsSaving( true );
		setNotice( null );

		apiFetch<ApiResponse>( {
			path: restPath,
			method: 'POST',
			data: {
				settings: serializeSettings( settings ),
			},
		} )
			.then( ( response ) => {
				const normalized = normalizeSettings( response.settings );
				setSettings( normalized );
				setOriginal( normalizeSettings( response.settings ) );
				setMeta( response.meta );
				setNotice( {
					status: 'success',
					message: __( 'Settings saved.', 'airygen-seo' ),
				} );
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error ? error.message : __( 'Failed to save settings.', 'airygen-seo' );
				setNotice( {
					status: 'error',
					message,
				} );
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	};

	const persistRedirectRules = async (
		nextRules: RedirectRule[],
		baseSettings: SettingsState,
	) => {
		const payloadSettings = {
			...baseSettings,
			redirects: { rules: nextRules },
		};

		try {
			const response = await apiFetch<ApiResponse>( {
				path: restPath,
				method: 'POST',
				data: {
					settings: serializeSettings( payloadSettings ),
				},
			} );
			const normalized = normalizeSettings( response.settings );
			setSettings( normalized );
			setOriginal( normalized );
			setMeta( response.meta );
		} catch ( error ) {
			const message =
				error instanceof Error ? error.message : __( 'Failed to save settings.', 'airygen-seo' );
			setNotice( {
				status: 'error',
				message,
			} );
			setSettings( baseSettings );
			throw error;
		}
	};

	const createRedirectRule = async ( rule: Omit<RedirectRule, 'id'> ) => {
		if ( ! settings ) {
			return;
		}

		const baseSettings = settings;
		const next: RedirectRule = {
			...rule,
			id: generateId(),
		};
		const nextRules = [ next, ...baseSettings.redirects.rules ];

		setSettings( {
			...baseSettings,
			redirects: { rules: nextRules },
		} );

		await persistRedirectRules( nextRules, baseSettings );
	};

	const removeRedirectRule = async ( id: string ) => {
		if ( ! settings ) {
			return;
		}

		const baseSettings = settings;
		const nextRules = baseSettings.redirects.rules.filter(
			( rule ) => rule.id !== id,
		);

		setSettings( {
			...baseSettings,
			redirects: { rules: nextRules },
		} );

		await persistRedirectRules( nextRules, baseSettings );
	};

	const updateManualLanguage = (
		index: number,
		patch: Partial<HreflangEntry>,
	) => {
		if ( ! settings ) {
			return;
		}

		const entries = settings.hreflang.manual_map.map( ( entry, idx ) =>
			idx === index ? { ...entry, ...patch } : entry,
		);

		updateSection( 'hreflang', {
			...settings.hreflang,
			manual_map: entries,
		} );
	};

	const removeManualLanguage = ( index: number ) => {
		if ( ! settings ) {
			return;
		}

		const entries = settings.hreflang.manual_map.filter(
			( _, idx ) => idx !== index,
		);

		updateSection( 'hreflang', {
			...settings.hreflang,
			manual_map: entries,
		} );
	};

	const addManualLanguage = () => {
		if ( ! settings ) {
			return;
		}

		updateSection( 'hreflang', {
			...settings.hreflang,
			manual_map: [
				...settings.hreflang.manual_map,
				{ code: '', url: '', persisted: false },
			],
		} );
	};

	useEffect( () => {
		if ( ! settings || activePage !== 'settings' ) {
			return;
		}

		const enabled = MODULE_KEYS.filter(
			( key ) => settings.modules[ key ],
		);

		if ( 0 === enabled.length ) {
			return;
		}

		if ( ! ( enabled as string[] ).includes( activeTab ) ) {
			setActiveTab( enabled[ 0 ] );
		}
	}, [ settings, activeTab, setActiveTab, activePage ] );

	useEffect( () => {
		if ( activePage !== 'migration' || migrationView === 'list' ) {
			return;
		}

		if ( activeTab !== migrationView ) {
			setActiveTab( migrationView );
		}
	}, [ activePage, migrationView, activeTab ] );

	useEffect( () => {
		if ( activePage !== 'notify' ) {
			return;
		}
		if ( ! isNotifyTabKey( activeTab ) && activeTab !== NOTIFY_HOME_TAB ) {
			setActiveTab( NOTIFY_HOME_TAB );
		}
	}, [ activePage, activeTab ] );

	useEffect( () => {
		let tabForUrl: string | undefined;
		if ( activePage === 'settings' ) {
			tabForUrl = activeTab;
		} else if ( activePage === 'notify' && isNotifyTabKey( activeTab ) ) {
			tabForUrl = activeTab;
		} else if ( activePage === 'migration' && migrationView !== 'list' ) {
			tabForUrl = migrationView;
		}
		updateBrowserUrl( activePage, tabForUrl, 'replace' );
	}, [ activePage, activeTab, migrationView, updateBrowserUrl ] );

	useEffect( () => {
		const handleHistoryRouteChange = () => {
			applyHashRoute( parseHashRoute() );
		};

		window.addEventListener( 'popstate', handleHistoryRouteChange );
		window.addEventListener( 'hashchange', handleHistoryRouteChange );
		return () => {
			window.removeEventListener( 'popstate', handleHistoryRouteChange );
			window.removeEventListener( 'hashchange', handleHistoryRouteChange );
		};
	}, [ applyHashRoute ] );

	const applyModuleStateChange = useCallback(
		(
			currentSettings: SettingsState,
			key: ModuleKey,
			enabled: boolean,
		): SettingsState => {
			const nextModules = {
				...currentSettings.modules,
				[ key ]: enabled,
			};
			if ( key === 'linkSuggestions' && ! enabled ) {
				nextModules.relatedPosts = false;
			}

			const nextSettings: SettingsState = {
				...currentSettings,
				modules: nextModules,
			};

			if ( ! enabled ) {
				const nextPanelVisibility = { ...currentSettings.panelVisibility };
				PANEL_METADATA.forEach( ( panel ) => {
					if ( panel.relatedModule === key ) {
						nextPanelVisibility[ panel.key ] = false;
					}
				} );
				nextSettings.panelVisibility = nextPanelVisibility;
			}

			return nextSettings;
		},
		[],
	);

	const updateModuleState = ( key: ModuleKey, enabled: boolean ) => {
		if ( ! settings ) {
			return;
		}

		if ( key === 'relatedPosts' && enabled && ! settings.modules.linkSuggestions ) {
			setNotice( {
				status: 'error',
				message: __( 'Related Posts requires enabling Link Suggestions first.', 'airygen-seo' ),
			} );
			return;
		}

		setSettings( ( prev ) => (
			prev ? applyModuleStateChange( prev, key, enabled ) : prev
		) );
		setNotice( null );
	};

	if ( isLoading ) {
		return (
			<div className="airygen-shell flex min-h-screen items-center justify-center bg-slate-100">
				<div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
					<Spinner />
					<span className="text-sm text-slate-600">{ getLoadingAppLabel( __( 'Airygen SEO', 'airygen-seo' ) ) }</span>
				</div>
			</div>
		);
	}

	if ( ! settings || ! meta ) {
		return (
			<div className="airygen-shell flex min-h-screen items-center justify-center bg-slate-100 px-4">
				<div className="w-full max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
					<h1 className="text-lg font-semibold text-slate-900">
						{ __( 'Airygen SEO', 'airygen-seo' ) }
					</h1>
					<Notice status="error" dismissible={ false }>
						<p className="mb-3">
							{ loadErrorMessage ?? __( 'Failed to load settings.', 'airygen-seo' ) }
						</p>
						{ isSessionExpiredLoadError ? (
							<Button
								variant="gradient"
								onClick={ () => {
									if ( typeof window !== 'undefined' ) {
										window.location.href = logoutUrl;
									}
								} }
							>
								{ __( 'Log in again', 'airygen-seo' ) }
							</Button>
						) : (
							<Button variant="gradient" onClick={ fetchSettings }>
								{ __( 'Retry', 'airygen-seo' ) }
							</Button>
						) }
					</Notice>
				</div>
			</div>
		);
	}

	const updateRedirectRule = async (
		id: string,
		patch: Partial<RedirectRule>,
	) => {
		if ( ! settings ) {
			return;
		}

		const baseSettings = settings;
		const nextRules = baseSettings.redirects.rules.map( ( rule ) =>
			rule.id === id ? { ...rule, ...patch } : rule,
		);

		setSettings( {
			...baseSettings,
			redirects: { rules: nextRules },
		} );

		await persistRedirectRules( nextRules, baseSettings );
	};

	const migrationTabs = [
		{
			name: 'yoast',
			// dont translate the title as it's a proper noun and brand name
			title: 'Yoast',
			render: () => null,
		},
		{
			name: 'rankmath',
			// dont translate the title as it's a proper noun and brand name
			title: 'Rank Math',
			render: () => null,
		},
		{
			name: 'seopress',
			// dont translate the title as it's a proper noun and brand name
			title: 'SEOPress',
			render: () => null,
		},
		{
			name: 'aioseo',
			// // dont translate the title as it's a proper noun and brand name
			title: 'AIOSEO',
			render: () => null,
		},
	];
	const notifyTabs: SettingsTab[] = [
		{ name: 'notifyDigest', title: __( 'Daily Digest', 'airygen-seo' ), icon: SiteHealthIcon, render: () => null },
		{ name: 'notifyEmail', title: __( 'Email', 'airygen-seo' ), icon: SocialCardsIcon, render: () => null },
		{ name: 'notifyTelegram', title: __( 'Telegram', 'airygen-seo' ), icon: SitemapIcon, render: () => null },
		{ name: 'notifyDiscord', title: __( 'Discord', 'airygen-seo' ), icon: LinkCounterIcon, render: () => null },
		{ name: 'notifyTeams', title: __( 'Teams', 'airygen-seo' ), icon: RobotsIcon, render: () => null },
	];
	const tabs = createSettingsTabs( {
		settings,
		meta,
		restBase,
		robotsPreviewUrl,
		sitemapPreviewUrl,
		onCopySitemapLink: handleCopySitemapLink,
		onCopyText: copyTextWithFeedback,
		updateSection,
		updateManualLanguage,
		removeManualLanguage,
		addManualLanguage,
		onCreateRedirectRule: createRedirectRule,
		onUpdateRedirectRule: updateRedirectRule,
		onRemoveRedirectRule: removeRedirectRule,
		brokenLinkCheckerDefaults: BROKEN_LINK_CHECKER_DEFAULTS,
		onLinkSuggestionsDirtyChange: setLinkSuggestionsDirty,
		registerLinkSuggestionsSubmit: ( submit ) => {
			linkSuggestionsSubmitRef.current = submit;
		},
		linkSuggestionsSaving: isSaving && activeTab === 'linkSuggestions',
		onNotice: setNotice,
	} );

	const orderedTabs = ( () => {
		const tabMap = new Map( tabs.map( ( tab ) => [ tab.name, tab ] ) );
		const ordered: typeof tabs = [];
		const seen = new Set<ModuleKey>();

		moduleOrder.forEach( ( key ) => {
			const entry = tabMap.get( key );
			if ( entry ) {
				ordered.push( entry );
				seen.add( key );
			}
		} );

		tabs.forEach( ( tab ) => {
			if ( ! seen.has( tab.name as ModuleKey ) ) {
				ordered.push( tab );
			}
		} );

		return ordered;
	} )();

	const enabledTabs = settings
		? orderedTabs.filter( ( entry ) => settings.modules[ entry.name as ModuleKey ] )
		: orderedTabs;

	let tabsForShell: SettingsTab[] = [];
	if ( activePage === 'settings' ) {
		tabsForShell = enabledTabs;
	} else if ( activePage === 'notify' ) {
		tabsForShell = activeTab === NOTIFY_HOME_TAB ? [] : notifyTabs;
	} else if ( activePage === 'migration' && migrationView !== 'list' ) {
		tabsForShell = migrationTabs;
	}
	const activeConfig =
		activePage === 'settings'
			? enabledTabs.find( ( entry ) => entry.name === activeTab )
			: null;
	const activeExtensionPage = registeredAdminPageMap.get( activePage );

	let pageContent: ReactNode;
	if ( activePage === 'settings' ) {
		const activePanel = activeConfig ? activeConfig.render() : null;
		pageContent = (
			<SettingsPage
				panel={ activePanel }
				isDirty={ isDirty }
				isSaving={ isSaving }
				onReset={ handleResetActiveModuleSettings }
				onSave={ handleSave }
				showFooter={ activeTab !== 'redirects' }
				emptyMessage={ __( 'Enable at least one module to configure its settings.', 'airygen-seo' ) }
			/>
		);
	} else if ( activePage === 'migration' ) {
		if ( migrationView === 'yoast' ) {
			pageContent = (
				<MigrationYoastPage
					restBase={ migrationBase }
					isActive={ Boolean( config.migration?.yoastActive ) }
				/>
			);
		} else if ( migrationView === 'rankmath' ) {
			pageContent = (
				<MigrationRankMathPage
					restBase={ migrationBase }
					isActive={ Boolean( config.migration?.rankMathActive ) }
				/>
			);
		} else if ( migrationView === 'seopress' ) {
			pageContent = (
				<MigrationSeoPressPage
					restBase={ migrationBase }
					isActive={ Boolean( config.migration?.seoPressActive ) }
				/>
			);
		} else if ( migrationView === 'aioseo' ) {
			pageContent = (
				<MigrationAioseoPage
					restBase={ migrationBase }
					isActive={ Boolean( config.migration?.aioseoActive ) }
				/>
			);
		} else {
			pageContent = (
				<MigrationPage
					restBase={ migrationBase }
					yoastActive={ Boolean( config.migration?.yoastActive ) }
					onOpenYoast={ openMigrationYoast }
					rankMathActive={ Boolean( config.migration?.rankMathActive ) }
					onOpenRankMath={ openMigrationRankMath }
					aioseoActive={ Boolean( config.migration?.aioseoActive ) }
					onOpenAioseo={ openMigrationAioseo }
					seoPressActive={ Boolean( config.migration?.seoPressActive ) }
					onOpenSeoPress={ openMigrationSeoPress }
					onOpenDebug={ () => setPageWithUrl( 'debug' ) }
				/>
			);
		}
	} else if ( activePage === 'debug' ) {
		pageContent = (
			<DebugPage
				debugState={ debugState }
				isDebugLoading={ isDebugLoading }
				isDebugEnabling={ isDebugEnabling }
				onEnableDebug={ handleEnableDebug }
				onDisableDebug={ handleDisableDebug }
				onRefresh={ fetchDebugState }
				onToggleClassicEditor={ handleClassicEditorToggle }
				onChangeDebugLevel={ handleDebugLevelChange }
				debugError={ debugError }
				onDismissError={ () => setDebugError( null ) }
			/>
		);
	} else if ( activePage === 'notify' ) {
		const notifyViewMap: Record<string, NotifyView> = {
			notifyDigest: 'digest',
			notifyEmail: 'email',
			notifyTelegram: 'telegram',
			notifyDiscord: 'discord',
			notifyTeams: 'teams',
			[ NOTIFY_HOME_TAB ]: 'home',
		};
		pageContent = settings ? (
			<SettingsPage
				panel={
					<NotifyTab
						settings={ settings.notify }
						restBase={ restBase }
						onNotice={ setNotice }
						timezoneOptions={ notifyTimezoneOptions }
						status={ meta?.notify?.status }
						onChange={ ( value ) => updateSection( 'notify', value ) }
						view={ notifyViewMap[ activeTab ] ?? 'home' }
						onViewChange={ ( next ) => {
							const tabByView: Record<NotifyView, string> = {
								home: NOTIFY_HOME_TAB,
								digest: 'notifyDigest',
								email: 'notifyEmail',
								telegram: 'notifyTelegram',
								discord: 'notifyDiscord',
								teams: 'notifyTeams',
							};
							handleSelectTab( tabByView[ next ] );
						} }
					/>
				}
				isDirty={ isDirty }
				isSaving={ isSaving }
				onReset={ handleResetActiveModuleSettings }
				onSave={ handleSave }
				showFooter={ activeTab !== NOTIFY_HOME_TAB }
				emptyMessage={ getNoModuleSelectedLabel( __( 'notify module', 'airygen-seo' ) ) }
			/>
		) : null;
	} else if ( activeExtensionPage ) {
		const ActiveExtensionPage = activeExtensionPage.render;
		pageContent = (
			<ActiveExtensionPage
				adminConfig={ config }
				pageKey={ activePage }
				navigate={ ( pageKey ) => setPageWithUrl( pageKey ) }
				notify={ setNotice }
			/>
		);
	} else if ( configuredAdminPageKeys.has( activePage ) && ! CORE_ADMIN_PAGE_KEYS.has( activePage ) ) {
		pageContent = (
			<div className="flex min-h-[16rem] items-center justify-center rounded-2xl border border-slate-200 bg-white shadow-sm">
				<Spinner />
			</div>
		);
	} else if ( activePage === 'topicCluster' ) {
		pageContent = settings && meta ? (
			<TopicClusterPage
				settings={ settings.topicCluster }
				meta={ meta }
				isDirty={ isDirty }
				isSaving={ isSaving }
				onSave={ handleSave }
				onChange={ ( next ) => updateSection( 'topicCluster', next ) }
				onNotice={ setNotice }
				restBase={ restBase }
			/>
		) : null;
	} else if ( settings ) {
		pageContent = (
			<DashboardPage
				settings={ settings }
				orderedModules={ orderedModuleMetadata }
				orderedPanels={ orderedPanelMetadata }
				onModuleToggle={ updateModuleState }
				onOpenSettings={ openModuleSettings }
				onDragStart={ handleModuleDragStart }
				onDragOver={ handleModuleDragOver }
				onDrop={ handleModuleDrop }
				onDragEnd={ handleModuleDragEnd }
				onPanelDragStart={ handlePanelDragStart }
				onPanelDragOver={ handlePanelDragOver }
				onPanelDrop={ handlePanelDrop }
				onPanelDragEnd={ handlePanelDragEnd }
				onPanelToggle={ handlePanelToggle }
				onSave={ handleSave }
				isDirty={ isDirty }
				isSaving={ isSaving }
				wizardDismissed={ wizardDismissed }
				contentBlockOrder={ settings.contentBlockOrder }
				contentBlockGap={ settings.contentBlockGap }
				contentBlockMarginTop={ settings.contentBlockMarginTop }
				onContentBlockOrderChange={ handleContentBlockOrderChange }
				onContentBlockGapChange={ handleContentBlockGapChange }
				onContentBlockMarginTopChange={ handleContentBlockMarginTopChange }
				onContentBlockZoneChange={ handleContentBlockZoneChange }
			/>
		);
	} else {
		pageContent = null;
	}

	return (
		<>
			<AdminShell
				title={ __( 'Airygen SEO Settings', 'airygen-seo' ) }
				logoUrl={ logoUrl }
				pages={ pages }
				activePage={ activePage }
				onSelectPage={ setPageWithUrl }
				tabs={ tabsForShell }
				activeTab={ activeTab }
				onSelectTab={ handleSelectTab }
				onSave={ handleSave }
				isSaving={ isSaving }
				isLoading={ isLoading }
				isDirty={ isDirty }
				notice={ notice }
				onDismissNotice={ () => setNotice( null ) }
				loadErrorMessage={ loadErrorMessage }
				onRetryLoad={ fetchSettings }
			>
				{ pageContent }
			</AdminShell>
			{ ! wizardDismissed && (
				<InstallWizard
					onDismiss={ () => setWizardDismissed( true ) }
					onApply={ ( enabledModules ) => {
						setSettings( ( prev ) => {
							if ( ! prev ) {
								return prev;
							}
							return { ...prev, modules: { ...prev.modules, ...enabledModules } };
						} );
					} }
					restBase={ restBase }
				/>
			) }
		</>
	);
};

const ensureContainer = (): HTMLElement | null => {
	let container = document.getElementById( 'airygen-root' );

	if ( container ) {
		container.setAttribute( 'data-locale', adminLocale );
		return container as HTMLElement;
	}

	const wrap =
		document.querySelector<HTMLElement>( '.wrap.airygen-settings' ) ||
		document.getElementById( 'wpbody-content' ) ||
		document.body;

	if ( ! wrap ) {
		return null;
	}

	const wpContent = document.getElementById( 'wpcontent' );
	if ( wpContent ) {
		wpContent.classList.add( 'airygen-wpcontent' );
	}

	wrap.classList.add( 'airygen-wrap' );

	container = document.createElement( 'div' );
	container.id = 'airygen-root';
	container.className = 'airygen-admin-root';
	container.setAttribute( 'data-locale', adminLocale );
	wrap.appendChild( container );

	return container;
};

const mountApp = () => {
	const container = ensureContainer();

	if ( ! container ) {
		return;
	}

	render(
		<Fragment>
			<App />
		</Fragment>,
		container,
	);
};

domReady( mountApp );

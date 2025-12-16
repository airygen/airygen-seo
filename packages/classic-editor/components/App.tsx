/* eslint-disable @typescript-eslint/no-explicit-any, no-nested-ternary, no-alert */
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import type { ComponentType, KeyboardEvent, ReactNode } from 'react';
import apiFetch from '@wordpress/api-fetch';
import {
	analyseDescriptionPixels,
	analyseTitlePixels,
	measureSerpDescription,
	measureSerpTitle,
} from '../../block-editor/utils/textMetrics';
import type { LinkSuggestionsConfig, ScoreApiConfig, ScoreResponse } from '../../block-editor/types';
import { buildScoreRuleGuide } from '../../shared/scoreRuleGuide';
import {
	getDescriptionFocusMessage,
	getDescriptionLengthStatusMessage,
	getTitleFocusMessage,
	getTitleLengthStatusMessage,
} from '../../shared/seoAnalysisPhrases';
import { clearScoreCache, loadScoreCache, saveScoreCache } from '../../shared/scoreCache';
import { ScorePanel } from './panels/ScorePanel';
import { SerpSnippetPanel } from './panels/SerpSnippetPanel';
import { KeyphrasesPanel } from './panels/KeyphrasesPanel';
import { CanonicalPanel } from './panels/CanonicalPanel';
import { RobotsPanel } from './panels/RobotsPanel';
import { TocPanel } from './panels/TocPanel';
import { SchemaPanel } from './panels/SchemaPanel';
import { TopicClusterPanel } from './panels/TopicClusterPanel';
import { LinkSuggestionsPanel } from './panels/LinkSuggestionsPanel';
import PromptsForAgentsPanel from './panels/PromptsForAgentsPanel';
import {
	EDITOR_EXTENSIONS_UPDATED_EVENT,
	getRegisteredClassicEditorTabs,
	type ClassicEditorExtensionProps,
	type ClassicEditorMetaField,
} from '../../shared/extensions/editorPanels';

const MAX_LONG_TAIL = 5;

const ROBOTS_EXTRA_OPTIONS = [
	{ key: 'noarchive', label: __( 'Prevent caching', 'airygen-seo' ) },
	{ key: 'nosnippet', label: __( 'Hide snippets', 'airygen-seo' ) },
	{ key: 'noimageindex', label: __( 'Block image indexing', 'airygen-seo' ) },
	{ key: 'notranslate', label: __( 'Disable translation prompts', 'airygen-seo' ) },
] as const;

const KNOWN_ROBOTS_TOKENS = new Set(
	[ 'index', 'noindex', 'follow', 'nofollow', ...ROBOTS_EXTRA_OPTIONS.map( ( opt ) => opt.key ) ].map(
		( token ) => token.toLowerCase(),
	),
);

const parseRobotsTokens = ( value: string ): string[] =>
	value
		.split( ',' )
		.map( ( token ) => token.trim() )
		.filter( Boolean );

type IndexChoice = '' | 'index' | 'noindex';
type FollowChoice = '' | 'follow' | 'nofollow';
type MaxImagePreviewChoice = '' | 'none' | 'standard' | 'large';
type MaxVideoPreviewChoice = '' | '-1' | '0' | '30' | '60';

const formatRobotsValue = (
	index: IndexChoice,
	follow: FollowChoice,
	extra: Set< string >,
	custom: string,
	maxImagePreview: MaxImagePreviewChoice,
	maxVideoPreview: MaxVideoPreviewChoice,
): string => {
	const tokens: string[] = [];

	if ( index ) {
		tokens.push( index );
	}

	if ( follow ) {
		tokens.push( follow );
	}

	tokens.push( ...Array.from( extra ) );

	if ( maxImagePreview ) {
		tokens.push( `max-image-preview:${ maxImagePreview }` );
	}

	if ( maxVideoPreview ) {
		tokens.push( `max-video-preview:${ maxVideoPreview }` );
	}

	const customTokens = parseRobotsTokens( custom ).filter(
		( token ) => ! KNOWN_ROBOTS_TOKENS.has( token.toLowerCase() ),
	);

	return [ ...tokens, ...customTokens ].join( ', ' );
};

const escapeHtml = ( value: string ) =>
	value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );

const addTagToList = ( list: string[], value: string ): string[] => {
	const normalized = value.trim();
	if ( ! normalized ) {
		return list;
	}

	if ( list.includes( normalized ) ) {
		return list;
	}

	if ( list.length >= MAX_LONG_TAIL ) {
		return list;
	}

	return [ ...list, normalized ];
};

type OutputModesValue = {
	toc?: string;
	faq?: string;
	topicExpansion?: string;
};

const buildOutputModesValue = (
	tocMode: string,
	faqMode: string,
	topicMode: string,
): string =>
	JSON.stringify( {
		toc: tocMode,
		faq: faqMode,
		topicExpansion: topicMode,
	} satisfies OutputModesValue );

const buildPostDataValue = ( values: {
	title: string;
	description: string;
	keyphrase: string;
	longTail: string;
	agentPrompt: string;
	canonical: string;
	robots: string;
	schemaType: string;
} ): string =>
	JSON.stringify( {
		title: values.title,
		description: values.description,
		focusKeyphrase: values.keyphrase,
		focusLongTail: values.longTail,
		agentPrompt: values.agentPrompt,
		canonical: values.canonical,
		robots: values.robots,
		schemaArticleType: values.schemaType,
	} );

export type ClassicEditorConfig = {
	currentBlogId?: number;
	modules?: Partial<Record<string, boolean>>;
	restNonce?: string;
	sessionCheckUrl?: string;
	panelVisibility?: Partial<
		Record<
			| 'serpSnippet'
			| 'keyphrases'
			| 'canonical'
			| 'robots'
			| 'scoreCalculator'
			| 'schemaMarkup'
			| 'linkSuggestions'
			| 'promptsForAgents'
			| 'toc'
			| 'topicCluster',
			boolean
		>
	>;
	scoreApi?: {
		root?: string;
		nonce?: string;
	};
	scoreCalculator?: {
		postTypes?: string[];
		scoreCache?: ScoreResponse | null;
	};
	linkSuggestions?: LinkSuggestionsConfig;
	topicCluster?: {
		list?: string;
		save?: string;
		summary?: string;
		mindMapUrl?: string;
		nonce?: string;
	};
	markdownForAgents?: {
		promptsForAgents?: boolean;
	};
	schemaMarkup?: {
		article_type?: string;
		post_type_defaults?: Record<string, string>;
	};
	robots?: {
		default_directive?: string;
	};
};

type ClassicAppProps = {
	initialTitle: string;
	initialDescription: string;
	initialKeyphrase: string;
	initialLongTail: string;
	initialAgentPrompt: string;
	initialCanonical: string;
	initialRobots: string;
	initialTocMode: string;
	initialFaqMode: string;
	initialTopicMode: string;
	initialSchemaType: string;
	postId: number;
	mode: string;
	editorConfig: ClassicEditorConfig;
};

type LinkSuggestion = {
	id: number;
	title: string;
	permalink: string;
	post_type: string;
	score: number;
};

type LinkSuggestionsResponse = {
	suggestions: LinkSuggestion[];
	meta?: Record< string, unknown >;
};

type LinkSuggestionsState =
	| { status: 'idle' | 'loading'; data: null; error: null }
	| { status: 'ready'; data: LinkSuggestionsResponse; error: null }
	| { status: 'error'; data: null; error: string };

type CoreClassicTabKey =
	| 'score'
	| 'snippet'
	| 'keyphrases'
	| 'canonical'
	| 'schema'
	| 'robots'
	| 'links'
	| 'prompts'
	| 'toc'
	| 'topic';

type ClassicTabEntry = {
	id: string;
	label: string;
	order: number;
	component?: ComponentType<ClassicEditorExtensionProps>;
};

const CORE_TAB_ORDER: CoreClassicTabKey[] = [
	'score',
	'snippet',
	'keyphrases',
	'canonical',
	'schema',
	'robots',
	'links',
	'prompts',
	'toc',
	'topic',
];

const CORE_TAB_ORDER_INDEX = new Map<string, number>(
	CORE_TAB_ORDER.map( ( key, index ) => [ key, ( index + 1 ) * 10 ] ),
);

const HiddenInputs = ( {
	title,
	description,
	keyphrase,
	longTail,
	agentPrompt,
	canonical,
	robots,
	tocMode,
	faqMode,
	topicMode,
	schemaType,
}: {
	title: string;
	description: string;
	keyphrase: string;
	longTail: string;
	agentPrompt: string;
	canonical: string;
	robots: string;
	tocMode: string;
	faqMode: string;
	topicMode: string;
	schemaType: string;
} ) => (
	<>
		<input type="hidden" name="airygen_title" value={ title } />
		<input type="hidden" name="airygen_description" value={ description } />
		<input type="hidden" name="airygen_focus_keyphrase" value={ keyphrase } />
		<input type="hidden" name="airygen_focus_long_tail" value={ longTail } />
		<input type="hidden" name="airygen_agent_prompt" value={ agentPrompt } />
		<input type="hidden" name="airygen_canonical" value={ canonical } />
		<input type="hidden" name="airygen_robots" value={ robots } />
		<input type="hidden" name="airygen_toc_mode" value={ tocMode } />
		<input type="hidden" name="airygen_faq_mode" value={ faqMode } />
		<input type="hidden" name="airygen_topic_mode" value={ topicMode } />
		<input type="hidden" name="airygen_schema_article_type" value={ schemaType } />
	</>
);

const FieldGroup = ( {
	label,
	description,
	id,
	children,
}: {
	label: string;
	description?: string;
	id: string;
	children: ReactNode;
} ) => (
	<div className="airygen-classic-field">
		<label className="airygen-classic-label" htmlFor={ id } aria-label={ label }>
			<div className="airygen-classic-label-row">
				<span className="airygen-classic-label-text">{ label }</span>
			</div>
		</label>
		{ children }
		{ description && (
			<span className="airygen-field-helper">{ description }</span>
		) }
	</div>
);

type ScoreState =
	| { status: 'idle' | 'loading'; data: null; error: null }
	| { status: 'ready'; data: ScoreResponse; error: null }
	| { status: 'error'; data: null; error: string };

type ScoreRuleStatus = 'na' | 'warn' | 'pass' | 'fail';

type RuleResult = {
	id: string;
	label: string;
	score: number;
	weight: number;
	status: ScoreRuleStatus;
	value?: unknown;
};

const isScoreRuleStatus = ( status: string ): status is ScoreRuleStatus =>
	( [ 'na', 'warn', 'pass', 'fail' ] as const ).includes( status as ScoreRuleStatus );

const fetchScore = async (
	api: ScoreApiConfig,
	postId: number,
	metaTitlePx: number,
	metaDescriptionPx: number,
): Promise< ScoreResponse > => {
	const query = new URLSearchParams( {
		post: String( postId ),
		meta_title_length_px: String( Math.max( 0, Math.round( metaTitlePx ) ) ),
		meta_description_length_px: String( Math.max( 0, Math.round( metaDescriptionPx ) ) ),
	} );
	return apiFetch< ScoreResponse >( {
		url: `${ api.root }?${ query.toString() }`,
		method: api.method ?? 'GET',
		headers: {
			'X-WP-Nonce': api.nonce,
		},
	} );
};

const truncateScore = ( value: number | string ): number => {
	const numeric = typeof value === 'string' ? parseFloat( value ) : value;
	return Math.floor( numeric );
};

const clampScore = ( score: number ): number => {
	if ( ! Number.isFinite( score ) ) {
		return 0;
	}
	return Math.max( 0, Math.min( 100, score ) );
};

const scoreTone = ( score: number ): { background: string; border: string; text: string } => {
	if ( ! Number.isFinite( score ) || score < 60 ) {
		return { background: '#feefed', border: '#f35d4a', text: '#f35d4a' };
	}
	if ( score < 80 ) {
		return { background: '#fef6eb', border: '#f8a738', text: '#f8a738' };
	}
	return { background: '#eefaf1', border: '#51c975', text: '#51c975' };
};

const normalizeRules = ( rules: unknown ): RuleResult[] => {
	if ( ! Array.isArray( rules ) ) {
		return [];
	}

	return rules
		.map( ( rule ) => ( rule && typeof rule === 'object' ? ( rule as Record< string, unknown > ) : null ) )
		.filter( ( rule ): rule is Record< string, unknown > => !! rule && 'id' in rule && 'label' in rule )
		.map( ( rule ) => {
			const statusStr = String( rule.status ?? '' );
			const status: ScoreRuleStatus = isScoreRuleStatus( statusStr ) ? statusStr : 'na';
			return {
				id: String( rule.id ?? '' ),
				label: String( rule.label ?? '' ),
				score: Number( rule.score ?? 0 ),
				weight: Number( rule.weight ?? 0 ),
				status,
				value: rule.value,
			};
		} )
		.filter( ( rule ) => rule.status !== 'na' );
};

const ClassicApp = ( {
	initialTitle,
	initialDescription,
	initialKeyphrase,
	initialLongTail,
	initialAgentPrompt,
	initialCanonical,
	initialRobots,
	initialTocMode,
	initialFaqMode,
	initialTopicMode,
	initialSchemaType,
	postId,
	mode,
	editorConfig,
}: ClassicAppProps ) => {
	const getEditorTitle = () => {
		const titleField = document.getElementById( 'title' ) as HTMLInputElement | null;
		return titleField?.value ?? '';
	};

	const getEditorExcerpt = () => {
		const excerptField = document.getElementById( 'excerpt' ) as HTMLTextAreaElement | null;
		return excerptField?.value ?? '';
	};

	const getPermalink = () => {
		const link = document.querySelector( '#sample-permalink a' ) as HTMLAnchorElement | null;
		return link?.textContent ?? '';
	};

	const [ title, setTitle ] = useState( initialTitle );
	const [ description, setDescription ] = useState( initialDescription );
	const [ excerpt, setExcerpt ] = useState( getEditorExcerpt() );
	const [ keyphrase, setKeyphrase ] = useState( initialKeyphrase );
	const [ longTail, setLongTail ] = useState( initialLongTail );
	const [ agentPrompt, setAgentPrompt ] = useState( initialAgentPrompt );
	const [ longTailPending, setLongTailPending ] = useState( '' );
	const [ canonical, setCanonical ] = useState( initialCanonical );
	const [ canonicalError, setCanonicalError ] = useState( '' );
	const [ robots, setRobots ] = useState( initialRobots );
	const [ tocMode, setTocMode ] = useState( initialTocMode || 'auto' );
	const [ faqMode, setFaqMode ] = useState( initialFaqMode || 'auto' );
	const [ topicMode, setTopicMode ] = useState( initialTopicMode || 'auto' );
	const [ schemaType, setSchemaType ] = useState( initialSchemaType || '' );
	const [ activeTab, setActiveTab ] = useState( 'score' );
	const [ previewChoice, setPreviewChoice ] = useState<
		'default' | 'custom'
	>( initialTitle.trim() || initialDescription.trim() ? 'custom' : 'default' );
	const [ canonicalChoice, setCanonicalChoice ] = useState< 'default' | 'custom' >(
		initialCanonical.trim() ? 'custom' : 'default',
	);
	const [ robotsSource, setRobotsSource ] = useState< 'default' | 'custom' >(
		initialRobots.trim() ? 'custom' : 'default',
	);
	const [ scoreState, setScoreState ] = useState< ScoreState >( {
		status: 'idle',
		data: null,
		error: null,
	} );
	const [ showTips, setShowTips ] = useState( false );
	const [ suggestionsExpanded, setSuggestionsExpanded ] = useState( true );
	const [ showPassedRules, setShowPassedRules ] = useState( false );
	const [ openRuleId, setOpenRuleId ] = useState< string | null >( null );
	const [ snippetSubTab, setSnippetSubTab ] = useState<'preview' | 'custom'>( 'preview' );
	const [ schemaSubTab, setSchemaSubTab ] = useState<'preview' | 'custom'>( 'preview' );
	const [ canonicalSubTab, setCanonicalSubTab ] = useState<'preview' | 'custom'>( 'preview' );
	const [ robotsSubTab, setRobotsSubTab ] = useState<'preview' | 'custom'>( 'preview' );
	const [ topicSubTab, setTopicSubTab ] = useState<'settings' | 'summary'>( 'settings' );
	const [ linksSubTab, setLinksSubTab ] = useState<'suggestions'>( 'suggestions' );
	const [ keyphraseSubTab, setKeyphraseSubTab ] = useState<'preview' | 'focus' | 'longtail'>(
		'preview',
	);
	const [ , setCtrApplyNotice ] = useState<string | null>( null );
	const [ , setCtrApplyLocked ] = useState( false );
	const [ , setAiApplyNotice ] = useState<Record<string, string | null>>( {} );
	const defaultRobotsDirective = editorConfig?.robots?.default_directive?.trim() ?? '';
	const [ robotsIndexChoice, setRobotsIndexChoice ] = useState< IndexChoice >( '' );
	const [ robotsFollowChoice, setRobotsFollowChoice ] = useState< FollowChoice >( '' );
	const [ robotsExtras, setRobotsExtras ] = useState< Set< string > >( new Set() );
	const [ robotsCustomDirectives, setRobotsCustomDirectives ] = useState( '' );
	const [ robotsMaxImagePreview, setRobotsMaxImagePreview ] =
		useState< MaxImagePreviewChoice >( '' );
	const [ robotsMaxVideoPreview, setRobotsMaxVideoPreview ] =
		useState< MaxVideoPreviewChoice >( '' );
	const [ aiApplyDirty, setAiApplyDirty ] = useState( false );
	const [ saveLoading, setSaveLoading ] = useState( false );
	const [ saveError, setSaveError ] = useState<string | null>( null );
	const [ extensionDirty, setExtensionDirty ] = useState( false );
	const [ registeredTabVersion, setRegisteredTabVersion ] = useState( 0 );
	const restBaseCache = useRef<Record<string, string>>( {} );

	const initialRef = useRef( {
		title: initialTitle,
		description: initialDescription,
		excerpt: getEditorExcerpt(),
		keyphrase: initialKeyphrase,
		longTail: initialLongTail,
		agentPrompt: initialAgentPrompt,
		canonical: initialCanonical,
		robots: initialRobots,
		tocMode: initialTocMode,
		faqMode: initialFaqMode,
		topicMode: initialTopicMode,
		schemaType: initialSchemaType,
	} );

	const isClassicPanelModuleEnabled = useCallback(
		( moduleKey: string ): boolean => {
			if ( ! ( moduleKey in ( editorConfig?.modules ?? {} ) ) ) {
				return true;
			}

			return Boolean( editorConfig?.modules?.[ moduleKey ] );
		},
		[ editorConfig?.modules ],
	);
	const linkConfig = editorConfig?.linkSuggestions;
	const currentPostType = useMemo( () => {
		if ( typeof document === 'undefined' ) {
			return 'post';
		}
		const postTypeField = document.getElementById( 'post_type' ) as HTMLInputElement | null;
		return postTypeField?.value ?? 'post';
	}, [] );
	const isScoreCalculatorVisible = useMemo( () => {
		const supportedPostTypes = Array.isArray( editorConfig?.scoreCalculator?.postTypes )
			? editorConfig.scoreCalculator.postTypes
				.map( ( slug ) => String( slug ).trim() )
				.filter( Boolean )
			: [];

		if ( supportedPostTypes.length === 0 ) {
			return true;
		}

		return supportedPostTypes.includes( currentPostType );
	}, [ currentPostType, editorConfig?.scoreCalculator?.postTypes ] );
	const [ linkState, setLinkState ] = useState< LinkSuggestionsState >( {
		status: 'idle',
		data: null,
		error: null,
	} );

	const getEditorContent = () => {
		const textarea = document.getElementById( 'content' ) as HTMLTextAreaElement | null;
		const tiny = ( window as any ).tinyMCE;
		if ( typeof tiny !== 'undefined' ) {
			const editor = tiny.get( 'content' );
			if ( editor && typeof editor.getContent === 'function' ) {
				return editor.getContent( { format: 'raw' } );
			}
		}
		return textarea ? textarea.value : '';
	};

	const setEditorContent = ( html: string ) => {
		const textarea = document.getElementById( 'content' ) as HTMLTextAreaElement | null;
		const tiny = ( window as any ).tinyMCE;
		if ( typeof tiny !== 'undefined' ) {
			const editor = tiny.get( 'content' );
			if ( editor && typeof editor.setContent === 'function' ) {
				editor.setContent( html );
				return;
			}
		}
		if ( textarea ) {
			textarea.value = html;
		}
	};

	const insertLinkSuggestion = ( item: LinkSuggestion ) => {
		const suggestionTitle = item.title || __( '(No title)', 'airygen-seo' );
		const safeTitle = escapeHtml( suggestionTitle );
		const safeHref = escapeHtml( item.permalink );
		const anchor = `<a href="${ safeHref }">${ safeTitle }</a>`;

		const tiny = ( window as any ).tinyMCE;
		if ( typeof tiny !== 'undefined' ) {
			const editor = tiny.get( 'content' );
			if ( editor && typeof editor.execCommand === 'function' ) {
				editor.execCommand( 'mceInsertContent', false, anchor );
				return;
			}
		}

		const textarea = document.getElementById( 'content' ) as HTMLTextAreaElement | null;
		if ( ! textarea ) {
			return;
		}
		const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
		const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : textarea.value.length;
		textarea.value = textarea.value.slice( 0, start ) + anchor + textarea.value.slice( end );
		textarea.focus();
		const nextPos = start + anchor.length;
		textarea.selectionStart = nextPos;
		textarea.selectionEnd = nextPos;
	};

	const insertShortcode = useCallback( ( shortcode: string ) => {
		const tiny = ( window as any ).tinyMCE;
		if ( typeof tiny !== 'undefined' ) {
			const editor = tiny.get( 'content' );
			if ( editor && typeof editor.execCommand === 'function' ) {
				editor.execCommand( 'mceInsertContent', false, shortcode );
				return;
			}
		}

		const textarea = document.getElementById( 'content' ) as HTMLTextAreaElement | null;
		if ( ! textarea ) {
			return;
		}
		const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
		const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : textarea.value.length;
		textarea.value = textarea.value.slice( 0, start ) + shortcode + textarea.value.slice( end );
		textarea.focus();
		const nextPos = start + shortcode.length;
		textarea.selectionStart = nextPos;
		textarea.selectionEnd = nextPos;
	}, [] );

	const getMetaValue = useCallback(
		( field: ClassicEditorMetaField ): string => {
			switch ( field ) {
				case 'title':
					return title;
				case 'description':
					return description;
				case 'keyphrase':
					return keyphrase;
				case 'longTail':
					return longTail;
				case 'agentPrompt':
					return agentPrompt;
				case 'canonical':
					return canonical;
				case 'robots':
					return robots;
				case 'tocMode':
					return tocMode;
				case 'faqMode':
					return faqMode;
				case 'topicMode':
					return topicMode;
				case 'schemaType':
					return schemaType;
				default:
					return '';
			}
		},
		[
			agentPrompt,
			canonical,
			description,
			faqMode,
			keyphrase,
			longTail,
			robots,
			schemaType,
			title,
			tocMode,
			topicMode,
		],
	);

	const setMetaValue = useCallback(
		( field: ClassicEditorMetaField, value: string ) => {
			switch ( field ) {
				case 'title':
					setTitle( value );
					return;
				case 'description':
					setDescription( value );
					return;
				case 'keyphrase':
					setKeyphrase( value );
					return;
				case 'longTail':
					setLongTail( value );
					return;
				case 'agentPrompt':
					setAgentPrompt( value );
					return;
				case 'canonical':
					setCanonical( value );
					return;
				case 'robots':
					setRobots( value );
					return;
				case 'tocMode':
					setTocMode( value );
					return;
				case 'faqMode':
					setFaqMode( value );
					return;
				case 'topicMode':
					setTopicMode( value );
					return;
				case 'schemaType':
					setSchemaType( value );
					break;

				default:
			}
		},
		[],
	);

	const setPostTitle = useCallback( ( value: string ) => {
		const titleField = document.getElementById( 'title' ) as HTMLInputElement | null;
		if ( ! titleField ) {
			return;
		}

		titleField.value = value;
		titleField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		titleField.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}, [] );

	const setClassicExcerpt = useCallback( ( value: string ) => {
		const excerptField = document.getElementById( 'excerpt' ) as HTMLTextAreaElement | null;
		if ( excerptField ) {
			excerptField.value = value;
		}

		setExcerpt( value );
	}, [] );

	const markExtensionDirty = useCallback( () => {
		setExtensionDirty( true );
	}, [] );

	const notifySuccess = useCallback( ( message: string ) => {
		const wpData = (
			window as Window & {
				wp?: {
					data?: {
						dispatch?: ( store: string ) => {
							createNotice: ( status: string, text: string, options?: { type?: string } ) => void;
						};
					};
				};
			}
		).wp?.data;
		if ( wpData?.dispatch ) {
			wpData.dispatch( 'core/notices' ).createNotice( 'success', message, { type: 'snackbar' } );
		}
	}, [] );

	const coreTabs = useMemo(
		() =>
			[
				{
					id: 'score',
					label: __( 'Content Score', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'score' ) ?? 10,
				},
				{
					id: 'snippet',
					label: __( 'SERP Snippet', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'snippet' ) ?? 20,
				},
				{
					id: 'keyphrases',
					label: __( 'Keyphrases', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'keyphrases' ) ?? 30,
				},
				{
					id: 'canonical',
					label: __( 'Canonical URL', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'canonical' ) ?? 40,
				},
				{
					id: 'schema',
					label: __( 'Schema', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'schema' ) ?? 50,
				},
				{
					id: 'robots',
					label: __( 'Robots Meta', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'robots' ) ?? 60,
				},
				{
					id: 'links',
					label: __( 'Link Suggestions', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'links' ) ?? 70,
				},
				{
					id: 'prompts',
					label: __( 'Prompts for Agents', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'prompts' ) ?? 80,
				},
				{
					id: 'toc',
					label: __( 'Table of Contents', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'toc' ) ?? 90,
				},
				{
					id: 'topic',
					label: __( 'Topic Cluster', 'airygen-seo' ),
					order: CORE_TAB_ORDER_INDEX.get( 'topic' ) ?? 100,
				},
			].filter( ( tab ) => {
				if ( ! isScoreCalculatorVisible && tab.id === 'score' ) {
					return false;
				}

				const relatedModuleByTab: Record<string, string> = {
					snippet: 'onPageSeo',
					keyphrases: 'onPageSeo',
					canonical: 'onPageSeo',
					schema: 'schema',
					robots: 'robots',
					score: 'scoreCalculator',
					links: 'linkSuggestions',
					prompts: 'markdownForAgents',
					toc: 'toc',
					topic: 'topicCluster',
				};
				const relatedModule = relatedModuleByTab[ tab.id ];
				if ( relatedModule && ! isClassicPanelModuleEnabled( relatedModule ) ) {
					return false;
				}

				if (
					tab.id === 'prompts' &&
					editorConfig?.markdownForAgents?.promptsForAgents === false
				) {
					return false;
				}

				const panelKeyByTab: Record<
					string,
					| 'scoreCalculator'
					| 'serpSnippet'
					| 'keyphrases'
					| 'canonical'
					| 'schemaMarkup'
					| 'robots'
					| 'linkSuggestions'
					| 'promptsForAgents'
					| 'toc'
					| 'topicCluster'
				> = {
					score: 'scoreCalculator',
					snippet: 'serpSnippet',
					keyphrases: 'keyphrases',
					canonical: 'canonical',
					schema: 'schemaMarkup',
					robots: 'robots',
					links: 'linkSuggestions',
					prompts: 'promptsForAgents',
					toc: 'toc',
					topic: 'topicCluster',
				};
				const panelKey = panelKeyByTab[ tab.id ];
				if ( ! panelKey ) {
					return true;
				}

				return editorConfig?.panelVisibility?.[ panelKey ] !== false;
			} ),
		[
			isClassicPanelModuleEnabled,
			editorConfig?.markdownForAgents?.promptsForAgents,
			editorConfig?.panelVisibility,
			isScoreCalculatorVisible,
		],
	);

	const registeredClassicTabs = useMemo(
		() =>
			getRegisteredClassicEditorTabs()
				.filter( ( tab ) => {
					if ( ! isClassicPanelModuleEnabled( tab.moduleKey ?? tab.key ) ) {
						return false;
					}

					const visibility = editorConfig?.panelVisibility as Record<string, boolean | undefined> | undefined;

					return visibility?.[ tab.visibilityKey ?? tab.key ] !== false;
				} )
				.map(
					( tab, index ): ClassicTabEntry => ( {
						id: tab.key,
						label: tab.title,
						order:
							typeof tab.order === 'number' && Number.isFinite( tab.order )
								? tab.order
								: 1000 + index,
						component: tab.render,
					} ),
				),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			editorConfig?.panelVisibility,
			isClassicPanelModuleEnabled,
			registeredTabVersion,
		],
	);

	const tabs = useMemo(
		() =>
			[ ...coreTabs, ...registeredClassicTabs ].sort( ( left, right ) => {
				if ( left.order === right.order ) {
					return left.id.localeCompare( right.id );
				}

				return left.order - right.order;
			} ),
		[ coreTabs, registeredClassicTabs ],
	);

	const activeExtensionTab = useMemo(
		() => registeredClassicTabs.find( ( tab ) => tab.id === activeTab ),
		[ activeTab, registeredClassicTabs ],
	);

	useEffect( () => {
		if ( tabs.length === 0 ) {
			return;
		}
		if ( tabs.some( ( tab ) => tab.id === activeTab ) ) {
			return;
		}
		setActiveTab( tabs[ 0 ].id );
	}, [ activeTab, tabs ] );

	useEffect( () => {
		if ( typeof window === 'undefined' ) {
			return () => {};
		}

		const handleRegistryUpdate = () => {
			setRegisteredTabVersion( ( current ) => current + 1 );
		};

		window.addEventListener( EDITOR_EXTENSIONS_UPDATED_EVENT, handleRegistryUpdate );

		return () => {
			window.removeEventListener( EDITOR_EXTENSIONS_UPDATED_EVENT, handleRegistryUpdate );
		};
	}, [] );

	const requestScore = useCallback( async ( options?: { forceRefresh?: boolean } ) => {
		if ( ! isScoreCalculatorVisible ) {
			return;
		}
		const apiConfig = editorConfig?.scoreApi;
		const currentBlogId = editorConfig?.currentBlogId ?? 1;
		if ( ! apiConfig?.root || ! postId ) {
			return;
		}
		if ( options?.forceRefresh ) {
			clearScoreCache( postId, currentBlogId );
		}
		const scoreApiConfig: ScoreApiConfig = {
			root: apiConfig.root,
			nonce: apiConfig.nonce ?? editorConfig?.restNonce ?? '',
		};

		const descriptionText = description.trim() ? description : getEditorExcerpt();
		const titleText = title.trim() ? title : getEditorTitle();
		const titlePx = measureSerpTitle( titleText ?? '' );
		const descriptionPx = measureSerpDescription( descriptionText ?? '' );

		setScoreState( { status: 'loading', data: null, error: null } );
		try {
			const data = await fetchScore( scoreApiConfig, postId, titlePx, descriptionPx );
			saveScoreCache( postId, data, currentBlogId );
			setScoreState( { status: 'ready', data, error: null } );
		} catch ( error ) {
			const message =
				error && typeof error === 'object' && 'message' in error
					? String( ( error as Error ).message )
					: __( 'Unable to fetch SEO score.', 'airygen-seo' );
			setScoreState( { status: 'error', data: null, error: message } );
		}
	}, [
		isScoreCalculatorVisible,
		editorConfig?.currentBlogId,
		editorConfig?.scoreApi,
		editorConfig?.restNonce,
		postId,
		description,
		title,
	] );

	const requestLinkSuggestions = useCallback( async () => {
		if ( ! linkConfig?.api?.root || ! postId ) {
			return;
		}

		setLinkState( { status: 'loading', data: null, error: null } );

		try {
			const query = new URLSearchParams( { post: String( postId ) } );
			const response = await window.fetch(
				`${ linkConfig.api.root }?${ query.toString() }`,
				{
					method: linkConfig.api.method ?? 'GET',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': linkConfig.api.nonce ?? editorConfig?.restNonce ?? '',
						Accept: 'application/json',
					},
				},
			);
			const payload = ( await response.json().catch( () => ( {} ) ) ) as
				| LinkSuggestionsResponse
				| { code?: string; message?: string };

			if ( ! response.ok ) {
				const errorPayload = payload as { code?: string; message?: string };
				throw {
					status: response.status,
					code: typeof errorPayload.code === 'string' ? errorPayload.code : '',
					message:
						typeof errorPayload.message === 'string' && errorPayload.message
							? errorPayload.message
							: __( 'Unable to fetch link suggestions.', 'airygen-seo' ),
				};
			}

			const data = payload as LinkSuggestionsResponse;
			setLinkState( { status: 'ready', data, error: null } );
		} catch ( error ) {
			const message =
				error && typeof error === 'object' && 'message' in error
					? String( ( error as Error ).message )
					: __( 'Unable to fetch link suggestions.', 'airygen-seo' );
			setLinkState( { status: 'error', data: null, error: message } );
		}
	}, [ editorConfig?.restNonce, linkConfig?.api, postId ] );

	useEffect( () => {
		const apiConfig = editorConfig?.scoreApi;
		const currentBlogId = editorConfig?.currentBlogId ?? 1;
		if ( ! postId || ! apiConfig?.root ) {
			return;
		}

		const cached = loadScoreCache< ScoreResponse >( postId, currentBlogId );
		if ( cached ) {
			setScoreState( { status: 'ready', data: cached, error: null } );
			return;
		}

		const persisted = editorConfig?.scoreCalculator?.scoreCache;
		if ( persisted && persisted.post_id === postId ) {
			setScoreState( { status: 'ready', data: persisted, error: null } );
			return;
		}

		void requestScore();
	}, [
		postId,
		editorConfig?.currentBlogId,
		editorConfig?.scoreApi,
		editorConfig?.scoreCalculator?.scoreCache,
		requestScore,
	] );

	useEffect( () => {
		if ( activeTab !== 'links' ) {
			return;
		}

		if ( linkState.status !== 'idle' ) {
			return;
		}

		if ( postId && linkConfig?.api?.root ) {
			void requestLinkSuggestions();
		}
	}, [ activeTab, linkState.status, postId, linkConfig?.api?.root, requestLinkSuggestions ] );

	useEffect( () => {
		const source = robots.trim() || defaultRobotsDirective;
		const tokens = parseRobotsTokens( source );
		let nextIndex: IndexChoice = '';
		let nextFollow: FollowChoice = '';
		const nextExtras = new Set< string >();
		let nextImagePreview: MaxImagePreviewChoice = '';
		let nextVideoPreview: MaxVideoPreviewChoice = '';
		const customTokens: string[] = [];

		tokens.forEach( ( rawToken ) => {
			const token = rawToken.toLowerCase();
			if ( token === 'index' || token === 'noindex' ) {
				nextIndex = token;
				return;
			}

			if ( token === 'follow' || token === 'nofollow' ) {
				nextFollow = token;
				return;
			}

			if ( token.startsWith( 'max-image-preview:' ) ) {
				const value = token.split( ':' )[ 1 ] ?? '';
				if ( value === 'none' || value === 'standard' || value === 'large' ) {
					nextImagePreview = value as MaxImagePreviewChoice;
					return;
				}
			}

			if ( token.startsWith( 'max-video-preview:' ) ) {
				const value = token.split( ':' )[ 1 ] ?? '';
				if ( value === '-1' || value === '0' || value === '30' || value === '60' ) {
					nextVideoPreview = value as MaxVideoPreviewChoice;
					return;
				}
			}

			if ( KNOWN_ROBOTS_TOKENS.has( token ) ) {
				nextExtras.add( token );
				return;
			}

			customTokens.push( rawToken );
		} );

		setRobotsIndexChoice( nextIndex );
		setRobotsFollowChoice( nextFollow );
		setRobotsExtras( nextExtras );
		setRobotsCustomDirectives( customTokens.join( ', ' ) );
		setRobotsMaxImagePreview( nextImagePreview );
		setRobotsMaxVideoPreview( nextVideoPreview );
	}, [ robots, defaultRobotsDirective ] );

	const updateRobotsFromState = (
		nextIndex: IndexChoice = robotsIndexChoice,
		nextFollow: FollowChoice = robotsFollowChoice,
		nextExtras: Set< string > = robotsExtras,
		nextCustom: string = robotsCustomDirectives,
		nextImagePreview: MaxImagePreviewChoice = robotsMaxImagePreview,
		nextVideoPreview: MaxVideoPreviewChoice = robotsMaxVideoPreview,
	) => {
		const value = formatRobotsValue(
			nextIndex,
			nextFollow,
			nextExtras,
			nextCustom,
			nextImagePreview,
			nextVideoPreview,
		);
		setRobots( value.trim() );
	};

	const toggleRobotsExtra = ( key: string, checked: boolean ) => {
		const next = new Set( robotsExtras );
		if ( checked ) {
			next.add( key );
		} else {
			next.delete( key );
		}
		setRobotsExtras( next );
		updateRobotsFromState(
			robotsIndexChoice,
			robotsFollowChoice,
			next,
			robotsCustomDirectives,
			robotsMaxImagePreview,
			robotsMaxVideoPreview,
		);
	};

	const clearRobotsOverride = () => {
		setRobotsIndexChoice( '' );
		setRobotsFollowChoice( '' );
		setRobotsExtras( new Set() );
		setRobotsCustomDirectives( '' );
		setRobotsMaxImagePreview( '' );
		setRobotsMaxVideoPreview( '' );
		setRobots( '' );
	};

	const previewTitle =
		previewChoice === 'custom'
			? title.trim()
			: getEditorTitle().trim();
	const previewDescription =
		previewChoice === 'custom'
			? description.trim()
			: getEditorExcerpt().trim();
	const previewUrl = getPermalink();
	const defaultTitle = getEditorTitle().trim();
	const defaultDescription = getEditorExcerpt().trim();

	const selectedTitle =
		( previewChoice === 'custom' ? title : defaultTitle )?.trim() ?? '';
	const selectedDescription =
		( previewChoice === 'custom' ? description : defaultDescription )?.trim() ?? '';

	const titleAnalysis = useMemo(
		() => analyseTitlePixels( title.trim() ),
		[ title ],
	);
	const descriptionAnalysis = useMemo(
		() => analyseDescriptionPixels( description.trim() ),
		[ description ],
	);
	const checklistTitleAnalysis = useMemo(
		() => analyseTitlePixels( selectedTitle ),
		[ selectedTitle ],
	);
	const checklistDescriptionAnalysis = useMemo(
		() => analyseDescriptionPixels( selectedDescription ),
		[ selectedDescription ],
	);

	const titlePixels = titleAnalysis.pixels;
	const descriptionPixels = descriptionAnalysis.pixels;

	const titleHasFocus =
		Boolean( keyphrase ) &&
		selectedTitle.toLowerCase().includes( keyphrase.toLowerCase() );
	const descriptionHasFocus =
		Boolean( keyphrase ) &&
		selectedDescription.toLowerCase().includes( keyphrase.toLowerCase() );

	const countOccurrences = ( textValue: string, phrase: string ): number => {
		if ( ! phrase ) {
			return 0;
		}

		const escaped = phrase.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		const match = textValue.match( new RegExp( escaped, 'gi' ) );

		return match ? match.length : 0;
	};

	const titleFocusCount = useMemo(
		() => countOccurrences( selectedTitle, keyphrase ?? '' ),
		[ selectedTitle, keyphrase ],
	);
	const descriptionFocusCount = useMemo(
		() => countOccurrences( selectedDescription, keyphrase ?? '' ),
		[ selectedDescription, keyphrase ],
	);

	const hasCustomCanonical = canonical.trim() !== '';
	const effectiveCanonical =
		canonicalChoice === 'custom' && hasCustomCanonical ? canonical.trim() : previewUrl || '';

	const validateCanonicalUrl = ( value: string ) => {
		if ( value.trim() === '' ) {
			setCanonicalError( '' );
			return true;
		}

		try {
			// eslint-disable-next-line no-new
			new URL( value );
			setCanonicalError( '' );
			return true;
		} catch {
			setCanonicalError( __( 'Enter a valid URL (including http/https).', 'airygen-seo' ) );
			return false;
		}
	};

	const handleCanonicalChange = ( value: string ) => {
		validateCanonicalUrl( value );
		setCanonical( value );
	};

	const normalizeLongTail = ( value: string ) =>
		value
			.split( /[\n,]+/ )
			.map( ( item ) => item.trim() )
			.filter( Boolean )
			.slice( 0, MAX_LONG_TAIL );

	const getPlainText = ( html: string ) => {
		if ( typeof document === 'undefined' ) {
			return html.replace( /<[^>]*>/g, ' ' );
		}
		const div = document.createElement( 'div' );
		div.innerHTML = html;
		return ( div.textContent || div.innerText || '' ).trim();
	};

	const contentText = getPlainText( getEditorContent() );
	const contentWordCount = useMemo( () => {
		if ( ! contentText ) {
			return 0;
		}
		return contentText.split( /\s+/ ).filter( Boolean ).length;
	}, [ contentText ] );

	const focusStats = useMemo( () => {
		const occurrences = keyphrase ? countOccurrences( contentText, keyphrase ) : 0;
		const density = contentWordCount > 0 ? ( occurrences / contentWordCount ) * 100 : 0;
		return {
			occurrences,
			density,
		};
	}, [ contentText, contentWordCount, keyphrase ] );

	const longTailList = useMemo( () => normalizeLongTail( longTail ), [ longTail ] );
	const longTailStats = useMemo(
		() =>
			longTailList.map( ( tag ) => {
				const occurrences = countOccurrences( contentText, tag );
				const density = contentWordCount > 0 ? ( occurrences / contentWordCount ) * 100 : 0;
				return {
					tag,
					occurrences,
					density,
				};
			} ),
		[ longTailList, contentText, contentWordCount ],
	);

	const persistLongTailTags = ( next: string[] ) => {
		setLongTail( next.join( ', ' ) );
	};

	const removeLongTailTag = ( index: number ) => {
		const next = longTailList.filter( ( _, i ) => i !== index );
		persistLongTailTags( next );
	};

	const handleLongTailInputChange = ( value: string ) => {
		if ( value.includes( ',' ) ) {
			const parts = value.split( ',' );
			const last = parts.pop() ?? '';
			let next = [ ...longTailList ];
			parts.forEach( ( part ) => {
				next = addTagToList( next, part );
			} );
			persistLongTailTags( next );
			setLongTailPending( last );
			return;
		}

		setLongTailPending( value );
	};

	const handleLongTailKeyDown = ( event: KeyboardEvent< HTMLInputElement > ) => {
		if ( event.key === 'Enter' || event.key === ',' ) {
			event.preventDefault();
			const next = addTagToList( longTailList, longTailPending );
			persistLongTailTags( next );
			setLongTailPending( '' );
		}
	};

	const handleLongTailBlur = () => {
		const next = addTagToList( longTailList, longTailPending );
		persistLongTailTags( next );
		setLongTailPending( '' );
	};

	const keyphraseStacked =
		!! keyphrase && ( titleFocusCount > 1 || descriptionFocusCount > 1 );

	const buildCheckClass = ( status: 'good' | 'warn' | 'bad' ) => {
		let cls = 'airygen-preview-check';
		if ( status === 'good' ) {
			cls += ' airygen-preview-check--good';
		} else if ( status === 'warn' ) {
			cls += ' airygen-preview-check--warn';
		} else {
			cls += ' airygen-preview-check--bad';
		}
		return cls;
	};

	const titleFocusMessage = () => {
		return getTitleFocusMessage( Boolean( keyphrase ), Boolean( titleHasFocus ) );
	};

	const descriptionFocusMessage = () => {
		return getDescriptionFocusMessage(
			Boolean( keyphrase ),
			Boolean( descriptionHasFocus ),
		);
	};

	const checklistTitleBarStatus = ( () => {
		const pixels = checklistTitleAnalysis.pixels;
		if ( pixels < 250 ) {
			return 'bad';
		}
		if ( pixels <= 350 ) {
			return 'warn';
		}
		if ( pixels <= 580 ) {
			return 'good';
		}
		return 'bad';
	} )();

	const checklistDescriptionBarStatus = ( () => {
		const pixels = checklistDescriptionAnalysis.pixels;
		if ( pixels < 400 ) {
			return 'bad';
		}
		if ( pixels <= 600 ) {
			return 'warn';
		}
		if ( pixels <= 920 ) {
			return 'good';
		}
		return 'bad';
	} )();

	const allChecklistPass =
		checklistTitleBarStatus === 'good' &&
		checklistDescriptionBarStatus === 'good' &&
		Boolean( keyphrase ) &&
		titleHasFocus &&
		descriptionHasFocus &&
		! keyphraseStacked;

	const focusDensityStatus = useMemo( () => {
		if ( ! keyphrase?.trim() ) {
			return {
				status: 'warn' as const,
				message: __( 'Set a focus keyphrase to check density.', 'airygen-seo' ),
			};
		}

		const density = focusStats.density;
		if ( density >= 0.5 && density <= 2 ) {
			return {
				status: 'good' as const,
				message: __(
					/* translators: percentage range for focus keyphrase density. */
					'Focus keyphrase density is in range (0.5%–2%).',
					'airygen-seo',
				),
			};
		}

		if ( density === 0 ) {
			return {
				status: 'bad' as const,
				message: __( 'Focus keyphrase density is 0%. Add it naturally to the content.', 'airygen-seo' ),
			};
		}

		return {
			status: 'bad' as const,
			message: __(
				/* translators: percentage range for focus keyphrase density. */
				'Adjust focus keyphrase density to 0.5%–2%.',
				'airygen-seo',
			),
		};
	}, [ keyphrase, focusStats.density ] );

	const longTailDensityStatus = useMemo( () => {
		if ( longTailList.length === 0 ) {
			return {
				status: 'warn' as const,
				message: __( 'Add long-tail keyphrases to check density.', 'airygen-seo' ),
			};
		}

		const totalDensity = longTailStats.reduce( ( sum, stat ) => sum + stat.density, 0 );
		const perRangeOk = longTailStats.every(
			( stat ) => stat.density >= 0.1 && stat.density <= 0.5,
		);
		const totalOk = totalDensity <= 2;

		if ( perRangeOk && totalOk ) {
			return {
				status: 'good' as const,
				message: __(
					'Long-tail density is in range (0.1%%–0.5%% each, total ≤ 2%).',
					'airygen-seo',
				),
			};
		}

		return {
			status: 'bad' as const,
			message: __(
				'Adjust long-tail density (0.1%%–0.5%% each, total ≤ 2%).',
				'airygen-seo',
			),
		};
	}, [ longTailList.length, longTailStats ] );

	const renderKeyphraseChecks = () => {
		if ( ! keyphrase ) {
			return (
				<p className={ buildCheckClass( 'warn' ) }>
					<span className="airygen-preview-check__icon">
						<span className="dashicons dashicons-warning" aria-hidden="true" />
					</span>
					<span>{ __( 'Focus keyphrase is not set.', 'airygen-seo' ) }</span>
				</p>
			);
		}

		return (
			<>
				<p className={ buildCheckClass( titleHasFocus ? 'good' : 'bad' ) }>
					<span className="airygen-preview-check__icon">
						<span
							className={ `dashicons ${ titleHasFocus ? 'dashicons-yes' : 'dashicons-no-alt' }` }
							aria-hidden="true"
						/>
					</span>
					<span>{ titleFocusMessage() }</span>
				</p>
				<p className={ buildCheckClass( descriptionHasFocus ? 'good' : 'bad' ) }>
					<span className="airygen-preview-check__icon">
						<span
							className={ `dashicons ${ descriptionHasFocus ? 'dashicons-yes' : 'dashicons-no-alt' }` }
							aria-hidden="true"
						/>
					</span>
					<span>{ descriptionFocusMessage() }</span>
				</p>
				<p className={ buildCheckClass( focusDensityStatus.status ) }>
					<span className="airygen-preview-check__icon">
						<span
							className={ `dashicons ${ focusDensityStatus.status === 'good' ? 'dashicons-yes' : 'dashicons-no-alt' }` }
							aria-hidden="true"
						/>
					</span>
					<span>{ focusDensityStatus.message }</span>
				</p>
				<p className={ buildCheckClass( longTailDensityStatus.status ) }>
					<span className="airygen-preview-check__icon">
						<span
							className={ `dashicons ${ longTailDensityStatus.status === 'good' ? 'dashicons-yes' : 'dashicons-no-alt' }` }
							aria-hidden="true"
						/>
					</span>
					<span>{ longTailDensityStatus.message }</span>
				</p>
				{ keyphraseStacked && (
					<p className={ buildCheckClass( 'bad' ) }>
						<span className="airygen-preview-check__icon">
							<span className="dashicons dashicons-no-alt" aria-hidden="true" />
						</span>
						<span>{ __( 'Focus keyphrase appears too often.', 'airygen-seo' ) }</span>
					</p>
				) }
			</>
		);
	};

	const renderBar = (
		pixels: number,
		max: number,
		status: 'good' | 'warn' | 'bad',
	) => {
		const clamped = Math.min( pixels, max );
		const widthPercent = max > 0 ? ( clamped / max ) * 100 : 0;

		return (
			<div className={ `airygen-progress airygen-progress--${ status }` }>
				<div
					className="airygen-progress__bar"
					style={ { width: `${ widthPercent }%` } }
				/>
			</div>
		);
	};

	const totalScoreValue = scoreState.data ? Number( scoreState.data.total.score ) : Number.NaN;
	const totalScore = scoreState.data ? truncateScore( scoreState.data.total.score ) : 0;
	const totalMaxValue = scoreState.data ? Number( scoreState.data.total.max ) : Number.NaN;
	const rules = useMemo( () => {
		if ( ! scoreState.data ) {
			return [];
		}

		const baseRules = normalizeRules( scoreState.data.base?.rules ).map( ( rule, index ) => ( {
			...rule,
			_sortIndex: index,
		} ) );

		const statusOrder: Record< string, number > = {
			fail: 0,
			warn: 1,
			pass: 2,
		};

		return baseRules
			.slice()
			.sort( ( a, b ) => {
				const orderA = statusOrder[ a.status ] ?? 1;
				const orderB = statusOrder[ b.status ] ?? 1;
				if ( orderA !== orderB ) {
					return orderA - orderB;
				}
				return a._sortIndex - b._sortIndex;
			} )
			.map( ( rule ) => {
				const { _sortIndex, ...rest } = rule;
				return rest;
			} );
	}, [ scoreState.data ] );

	const failingCount = useMemo(
		() => rules.filter( ( rule ) => rule.status && rule.status !== 'pass' ).length,
		[ rules ],
	);
	const passedCount = useMemo(
		() => rules.filter( ( rule ) => rule.status === 'pass' ).length,
		[ rules ],
	);
	const visibleRules = useMemo( () => {
		if ( showPassedRules ) {
			return rules;
		}
		return rules.filter( ( rule ) => rule.status && rule.status !== 'pass' );
	}, [ rules, showPassedRules ] );

	const renderSuggestions = () => (
		<div className="airygen-score-panel__suggestions">
			<div className="airygen-score-panel__summary">
				<div>
					<p className="airygen-score-panel__label">
						{ `${ failingCount } ${ __( 'things to improve', 'airygen-seo' ) }` }
						<span
							role="button"
							tabIndex={ 0 }
							data-airygen-e2e="score-show-tips"
							onClick={ () => setShowTips( ( value ) => ! value ) }
							onKeyDown={ ( event ) => {
								if ( event.key === 'Enter' || event.key === ' ' ) {
									event.preventDefault();
									setShowTips( ( value ) => ! value );
								}
							} }
							className="airygen-score-panel__tips-toggle"
						>
							{ showTips
								? __( 'Hide tips', 'airygen-seo' )
								: __( 'Show tips', 'airygen-seo' ) }
						</span>
					</p>
				</div>
				<div
					role="button"
					tabIndex={ 0 }
					onClick={ () => setSuggestionsExpanded( ( value ) => ! value ) }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' || event.key === ' ' ) {
							event.preventDefault();
							setSuggestionsExpanded( ( value ) => ! value );
						}
					} }
					aria-expanded={ suggestionsExpanded }
					aria-label={
						suggestionsExpanded
							? __( 'Collapse suggestions', 'airygen-seo' )
							: __( 'Expand suggestions', 'airygen-seo' )
					}
					className="airygen-score-panel__toggle"
				>
					<span
						className={
							suggestionsExpanded
								? 'dashicons dashicons-arrow-down-alt2'
								: 'dashicons dashicons-arrow-right-alt2'
						}
						aria-hidden="true"
					/>
				</div>
			</div>
			{ suggestionsExpanded ? (
				<>
					{ 'loading' === scoreState.status && (
						<div className="inline-flex items-center">
							<span className="spinner is-active" aria-hidden="true" />
						</div>
					) }
					{ 'loading' !== scoreState.status && failingCount === 0 && (
						<p>{ __( 'Great job! No suggestions right now.', 'airygen-seo' ) }</p>
					) }
					{ visibleRules.length > 0 && (
						<div className="airygen-preview-checklist airygen-score-panel__list">
							{ visibleRules.map( ( rule ) => {
								const isPass = rule.status === 'pass';
								const isWarn = rule.status === 'warn';
								let className = 'airygen-preview-check';
								if ( isPass ) {
									className += ' airygen-preview-check--good';
								} else if ( isWarn ) {
									className += ' airygen-preview-check--warn';
								} else {
									className += ' airygen-preview-check--bad';
								}
								const iconClass = isPass
									? 'dashicons dashicons-yes'
									: 'dashicons dashicons-no-alt';
								const hintText = isWarn ? __( 'Consider improving', 'airygen-seo' ) : '';

								const guide = buildScoreRuleGuide( rule.id, rule.label );
								const showPopover = openRuleId === rule.id;

								return (
									<div className={ className } key={ rule.id }>
										<span className="airygen-preview-check__icon">
											<span className={ iconClass } aria-hidden="true" />
										</span>
										<span className="airygen-score-panel__rule">
											<span className="airygen-score-panel__rule-label">
												{ rule.label }
												{ showTips ? (
													<span className="airygen-score-panel__tip-wrap">
														<span
															role="button"
															tabIndex={ 0 }
															data-airygen-e2e={ `score-tip-button-${ rule.id }` }
															onClick={ () =>
																setOpenRuleId( showPopover ? null : rule.id )
															}
															onKeyDown={ ( event ) => {
																if ( event.key === 'Enter' || event.key === ' ' ) {
																	event.preventDefault();
																	setOpenRuleId( showPopover ? null : rule.id );
																}
															} }
															className="airygen-score-panel__tip-button"
														>
															?
														</span>
														{ showPopover && (
															<div className="airygen-panel-popover">
																<button
																	type="button"
																	onClick={ () => setOpenRuleId( null ) }
																	className="airygen-score-panel__popover-close"
																	aria-label={ __( 'Close', 'airygen-seo' ) }
																	data-airygen-e2e="score-tip-popover-close"
																>
																	×
																</button>
																<div className="airygen-score-panel__popover-section">
																	<p className="airygen-score-panel__popover-label">
																		{ __( 'Meaning', 'airygen-seo' ) }
																	</p>
																	<p className="airygen-score-panel__popover-text">
																		{ guide.meaning }
																	</p>
																</div>
																<div className="airygen-score-panel__popover-section">
																	<p className="airygen-score-panel__popover-label">
																		{ __( 'How to improve', 'airygen-seo' ) }
																	</p>
																	<p className="airygen-score-panel__popover-text">{ guide.how }</p>
																</div>
																<div className="airygen-score-panel__popover-section">
																	<p className="airygen-score-panel__popover-label">
																		{ __( 'SEO impact', 'airygen-seo' ) }
																	</p>
																	<p className="airygen-score-panel__popover-text">
																		{ guide.impact }
																	</p>
																</div>
															</div>
														) }
													</span>
												) : null }
											</span>
										</span>
										{ hintText && (
											<span className="airygen-score-panel__hint">{ hintText }</span>
										) }
									</div>
								);
							} ) }
						</div>
					) }
				</>
			) : null }
		</div>
	);

	const saveChanges = async () => {
		if ( ! postId || ! editorConfig?.restNonce ) {
			return;
		}
		const restNonce = editorConfig.restNonce;
		if ( ! validateCanonicalUrl( canonical ) ) {
			setSaveError( canonicalError || __( 'Enter a valid URL (including http/https).', 'airygen-seo' ) );
			return;
		}
		const postTypeField = document.getElementById( 'post_type' ) as HTMLInputElement | null;
		const postType = postTypeField?.value ?? 'post';
		const excerptValue = getEditorExcerpt() || excerpt;
		setSaveLoading( true );
		setSaveError( null );
		try {
			const resolveRestBase = async ( type: string ) => {
				if ( restBaseCache.current[ type ] ) {
					return restBaseCache.current[ type ];
				}
				if ( type === 'post' ) {
					restBaseCache.current[ type ] = 'posts';
					return 'posts';
				}
				if ( type === 'page' ) {
					restBaseCache.current[ type ] = 'pages';
					return 'pages';
				}
				try {
					const response = await apiFetch( {
						path: `/wp/v2/types/${ type }`,
						method: 'GET',
						headers: {
							'X-WP-Nonce': restNonce,
						},
					} );
					const restBase = ( response as { rest_base?: string } )?.rest_base;
					if ( restBase ) {
						restBaseCache.current[ type ] = restBase;
						return restBase;
					}
				} catch {
					// fall through to use type directly
				}
				restBaseCache.current[ type ] = type;
				return type;
			};

			const restBase = await resolveRestBase( postType );
			await apiFetch( {
				path: `/wp/v2/${ restBase }/${ postId }`,
				method: 'POST',
				data: {
					excerpt: excerptValue,
					meta: {
						_airygen_post_data: buildPostDataValue( {
							title,
							description,
							keyphrase,
							longTail,
							agentPrompt,
							canonical,
							robots,
							schemaType,
						} ),
						_airygen_output_modes: buildOutputModesValue( tocMode, faqMode, topicMode ),
					},
				},
				headers: {
					'X-WP-Nonce': restNonce,
				},
			} );
			initialRef.current = {
				title,
				description,
				excerpt: excerptValue,
				keyphrase,
				longTail,
				agentPrompt,
				canonical,
				robots,
				tocMode,
				faqMode,
				topicMode,
				schemaType,
			};
			setExcerpt( excerptValue );
			setCtrApplyNotice( null );
			setCtrApplyLocked( false );
			setAiApplyDirty( false );
			setAiApplyNotice( {} );
			setExtensionDirty( false );
		} catch {
			setSaveError( __( 'Failed to save changes.', 'airygen-seo' ) );
		} finally {
			setSaveLoading( false );
		}
	};

	const classicExtensionProps = useMemo<ClassicEditorExtensionProps>(
		() => ( {
			editorConfig: editorConfig as Record<string, unknown>,
			postId,
			getMetaValue,
			setMetaValue,
			getPostTitle: getEditorTitle,
			setPostTitle,
			getExcerpt: getEditorExcerpt,
			setExcerpt: setClassicExcerpt,
			insertShortcode,
			getEditorContent,
			setEditorContent,
			markDirty: markExtensionDirty,
			notifySuccess,
		} ),
		[
			editorConfig,
			getMetaValue,
			insertShortcode,
			markExtensionDirty,
			notifySuccess,
			postId,
			setClassicExcerpt,
			setMetaValue,
			setPostTitle,
		],
	);

	const emptyPanelState = (
		<div className="airygen-classic-panel">
			<div className="airygen-panel-container">
				<p className="airygen-classic-label-helper">{ __( 'No panels available.', 'airygen-seo' ) }</p>
			</div>
		</div>
	);

	if ( mode === 'score' ) {
		if ( ! isScoreCalculatorVisible ) {
			return emptyPanelState;
		}
		const scoreProgress = clampScore(
			Number.isFinite( totalScoreValue ) && Number.isFinite( totalMaxValue ) && totalMaxValue > 0
				? Math.round( ( totalScoreValue / totalMaxValue ) * 100 )
				: 0,
		);
		const scoreToneValue = scoreTone( totalScoreValue );
		return (
			<div className="airygen-classic-panel">
				<ScorePanel
					scoreState={ scoreState }
					editorConfig={ editorConfig }
					requestScore={ requestScore }
					progress={ scoreProgress }
					tone={ scoreToneValue }
					totalScore={ totalScore }
					rules={ rules }
					renderSuggestions={ renderSuggestions }
					passedCount={ passedCount }
					showPassedRules={ showPassedRules }
					setShowPassedRules={ setShowPassedRules }
				/>
			</div>
		);
	}

	if ( tabs.length === 0 ) {
		return emptyPanelState;
	}

	const titlePixelsRounded = Math.round( titlePixels );
	const descriptionPixelsRounded = Math.round( descriptionPixels );
	const robotsPreviewValue = formatRobotsValue(
		robotsIndexChoice,
		robotsFollowChoice,
		robotsExtras,
		robotsCustomDirectives,
		robotsMaxImagePreview,
		robotsMaxVideoPreview,
	);
	const checklistTitleStatus = ( () => {
		return getTitleLengthStatusMessage( checklistTitleAnalysis.pixels, true );
	} )();
	const checklistDescriptionStatus = ( () => {
		return getDescriptionLengthStatusMessage(
			checklistDescriptionAnalysis.pixels,
			true,
		);
	} )();
	const titleStatusText = ( () => {
		if ( ! title.trim() ) {
			return '';
		}
		return getTitleLengthStatusMessage( titlePixels );
	} )();
	const descriptionStatusText = ( () => {
		if ( ! description.trim() ) {
			return '';
		}
		return getDescriptionLengthStatusMessage( descriptionPixels );
	} )();
	const titleBarStatus = ( () => {
		if ( titlePixels < 250 ) {
			return 'bad';
		}
		if ( titlePixels <= 350 ) {
			return 'warn';
		}
		if ( titlePixels <= 580 ) {
			return 'good';
		}
		return 'bad';
	} )();
	const descriptionBarStatus = ( () => {
		if ( descriptionPixels < 400 ) {
			return 'bad';
		}
		if ( descriptionPixels <= 600 ) {
			return 'warn';
		}
		if ( descriptionPixels <= 920 ) {
			return 'good';
		}
		return 'bad';
	} )();
	const progress = clampScore(
		Number.isFinite( totalScoreValue ) && Number.isFinite( totalMaxValue ) && totalMaxValue > 0
			? Math.round( ( totalScoreValue / totalMaxValue ) * 100 )
			: 0,
	);
	const tone = scoreTone( totalScoreValue );
	const isDirty = ( () => {
		const initial = initialRef.current;
		return (
			initial.title !== title ||
			initial.description !== description ||
			initial.excerpt !== excerpt ||
			initial.keyphrase !== keyphrase ||
			initial.longTail !== longTail ||
			initial.canonical !== canonical ||
			initial.robots !== robots ||
			initial.tocMode !== tocMode ||
			initial.faqMode !== faqMode ||
			initial.topicMode !== topicMode ||
			initial.schemaType !== schemaType ||
			aiApplyDirty ||
			extensionDirty
		);
	} )();

	const ActiveExtensionTab = activeExtensionTab?.component;

	return (
		<div className="airygen-classic-panel">
			<HiddenInputs
				title={ title }
				description={ description }
				keyphrase={ keyphrase }
				longTail={ longTail }
				agentPrompt={ agentPrompt }
				canonical={ canonical }
				robots={ robots }
				tocMode={ tocMode }
				faqMode={ faqMode }
				topicMode={ topicMode }
				schemaType={ schemaType }
			/>
			<div className="airygen-classic-layout">
				<div className="airygen-classic-tabs" data-airygen-e2e="classic-tabs">
					{ tabs.map( ( tab ) => (
						<button
							key={ tab.id }
							type="button"
							data-airygen-e2e={ `classic-tab-${ tab.id }` }
							className={
								activeTab === tab.id
									? 'airygen-classic-tab is-active'
									: 'airygen-classic-tab'
							}
							onClick={ () => setActiveTab( tab.id ) }
						>
							{ tab.label }
						</button>
					) ) }
				</div>
				<div className="airygen-classic-tab-panel">
					{ activeTab === 'snippet' &&
						isClassicPanelModuleEnabled( 'onPageSeo' ) &&
						editorConfig?.panelVisibility?.serpSnippet !== false && (
						<SerpSnippetPanel
							snippetSubTab={ snippetSubTab }
							setSnippetSubTab={ setSnippetSubTab }
							previewUrl={ previewUrl }
							previewTitle={ previewTitle }
							previewDescription={ previewDescription }
							previewChoice={ previewChoice }
							setPreviewChoice={ setPreviewChoice }
							allChecklistPass={ allChecklistPass }
							buildCheckClass={ buildCheckClass }
							checklistTitleBarStatus={ checklistTitleBarStatus }
							checklistDescriptionBarStatus={ checklistDescriptionBarStatus }
							checklistTitleStatus={ checklistTitleStatus }
							checklistDescriptionStatus={ checklistDescriptionStatus }
							titleHasFocus={ titleHasFocus }
							descriptionHasFocus={ descriptionHasFocus }
							titleFocusMessage={ titleFocusMessage }
							descriptionFocusMessage={ descriptionFocusMessage }
							keyphraseStacked={ keyphraseStacked }
							FieldGroup={ FieldGroup }
							title={ title }
							setTitle={ setTitle }
							titlePixels={ titlePixels }
							titlePixelsRounded={ titlePixelsRounded }
							titleBarStatus={ titleBarStatus }
							titleStatusText={ titleStatusText }
							description={ description }
							setDescription={ setDescription }
							descriptionPixels={ descriptionPixels }
							descriptionPixelsRounded={ descriptionPixelsRounded }
							descriptionBarStatus={ descriptionBarStatus }
							descriptionStatusText={ descriptionStatusText }
							renderBar={ renderBar }
						/>
					) }
					{ isScoreCalculatorVisible &&
						isClassicPanelModuleEnabled( 'scoreCalculator' ) &&
						activeTab === 'score' &&
						editorConfig?.panelVisibility?.scoreCalculator !== false && (
						<ScorePanel
							scoreState={ scoreState }
							editorConfig={ editorConfig }
							requestScore={ requestScore }
							progress={ progress }
							tone={ tone }
							totalScore={ totalScore }
							rules={ rules }
							renderSuggestions={ renderSuggestions }
							passedCount={ passedCount }
							showPassedRules={ showPassedRules }
							setShowPassedRules={ setShowPassedRules }
						/>
					) }
					{ activeTab === 'keyphrases' &&
						isClassicPanelModuleEnabled( 'onPageSeo' ) &&
						editorConfig?.panelVisibility?.keyphrases !== false && (
						<KeyphrasesPanel
							keyphraseSubTab={ keyphraseSubTab }
							setKeyphraseSubTab={ setKeyphraseSubTab }
							keyphrase={ keyphrase }
							focusStats={ focusStats }
							longTailStats={ longTailStats }
							renderKeyphraseChecks={ renderKeyphraseChecks }
							FieldGroup={ FieldGroup }
							setKeyphrase={ setKeyphrase }
							longTailPending={ longTailPending }
							handleLongTailInputChange={ handleLongTailInputChange }
							handleLongTailKeyDown={ handleLongTailKeyDown }
							handleLongTailBlur={ handleLongTailBlur }
							longTailList={ longTailList }
							removeLongTailTag={ removeLongTailTag }
						/>
					) }
					{ activeTab === 'canonical' &&
						isClassicPanelModuleEnabled( 'onPageSeo' ) &&
						editorConfig?.panelVisibility?.canonical !== false && (
						<CanonicalPanel
							canonicalSubTab={ canonicalSubTab }
							setCanonicalSubTab={ setCanonicalSubTab }
							effectiveCanonical={ effectiveCanonical }
							canonicalChoice={ canonicalChoice }
							setCanonicalChoice={ setCanonicalChoice }
							canonical={ canonical }
							handleCanonicalChange={ handleCanonicalChange }
							hasCustomCanonical={ hasCustomCanonical }
							setCanonical={ setCanonical }
							canonicalError={ canonicalError }
							FieldGroup={ FieldGroup }
						/>
					) }
					{ activeTab === 'schema' &&
						isClassicPanelModuleEnabled( 'schema' ) &&
						editorConfig?.panelVisibility?.schemaMarkup !== false && (
						<SchemaPanel
							schemaSubTab={ schemaSubTab }
							setSchemaSubTab={ setSchemaSubTab }
							schemaType={ schemaType }
							setSchemaType={ setSchemaType }
							postId={ postId }
							schemaConfig={ editorConfig?.schemaMarkup }
						/>
					) }
					{ activeTab === 'robots' &&
						isClassicPanelModuleEnabled( 'robots' ) &&
						editorConfig?.panelVisibility?.robots !== false && (
						<RobotsPanel
							robotsSubTab={ robotsSubTab }
							setRobotsSubTab={ setRobotsSubTab }
							robotsSource={ robotsSource }
							setRobotsSource={ setRobotsSource }
							clearRobotsOverride={ clearRobotsOverride }
							robotsPreviewValue={ robotsPreviewValue }
							defaultRobotsDirective={ defaultRobotsDirective }
							FieldGroup={ FieldGroup }
							robotsIndexChoice={ robotsIndexChoice }
							setRobotsIndexChoice={ setRobotsIndexChoice }
							robotsFollowChoice={ robotsFollowChoice }
							setRobotsFollowChoice={ setRobotsFollowChoice }
							robotsMaxImagePreview={ robotsMaxImagePreview }
							setRobotsMaxImagePreview={ setRobotsMaxImagePreview }
							robotsMaxVideoPreview={ robotsMaxVideoPreview }
							setRobotsMaxVideoPreview={ setRobotsMaxVideoPreview }
							updateRobotsFromState={ updateRobotsFromState }
							robotsExtras={ robotsExtras }
							toggleRobotsExtra={ toggleRobotsExtra }
							robotsCustomDirectives={ robotsCustomDirectives }
							setRobotsCustomDirectives={ setRobotsCustomDirectives }
							ROBOTS_EXTRA_OPTIONS={ ROBOTS_EXTRA_OPTIONS }
						/>
					) }
					{ activeTab === 'toc' &&
						isClassicPanelModuleEnabled( 'toc' ) &&
						editorConfig?.panelVisibility?.toc !== false && (
						<TocPanel tocMode={ tocMode } setTocMode={ setTocMode } insertShortcode={ insertShortcode } />
					) }
					{ activeTab === 'topic' &&
						isClassicPanelModuleEnabled( 'topicCluster' ) &&
						editorConfig?.panelVisibility?.topicCluster !== false && (
						<TopicClusterPanel
							topicSubTab={ topicSubTab }
							setTopicSubTab={ setTopicSubTab }
							postId={ postId }
							config={ editorConfig?.topicCluster }
						/>
					) }
					{ activeTab === 'links' &&
						isClassicPanelModuleEnabled( 'linkSuggestions' ) &&
						editorConfig?.panelVisibility?.linkSuggestions !== false && (
						<LinkSuggestionsPanel
							linksSubTab={ linksSubTab }
							setLinksSubTab={ setLinksSubTab }
							linkConfig={ linkConfig }
							linkState={ linkState }
							insertLinkSuggestion={ insertLinkSuggestion }
						/>
					) }
					{ activeTab === 'prompts' &&
						isClassicPanelModuleEnabled( 'markdownForAgents' ) &&
						editorConfig?.panelVisibility?.promptsForAgents !== false &&
						editorConfig?.markdownForAgents?.promptsForAgents !== false && (
						<PromptsForAgentsPanel value={ agentPrompt } onChange={ setAgentPrompt } />
					) }
					{ ActiveExtensionTab ? (
						<ActiveExtensionTab { ...classicExtensionProps } />
					) : null }
				</div>
			</div>
			<div className="airygen-classic-footer">
				<div className="airygen-classic-footer__message">
					<span>
						{ isDirty
							? __( 'Save changes to apply your updates.', 'airygen-seo' )
							: __( 'Saved.', 'airygen-seo' ) }
					</span>
					{ saveError && (
						<span className="airygen-classic-footer__error">{ saveError }</span>
					) }
				</div>
				<button
					type="button"
					className="button button-primary"
					onClick={ () => void saveChanges() }
					disabled={ ! isDirty || saveLoading }
				>
					{ saveLoading
						? __( 'Saving…', 'airygen-seo' )
						: __( 'Save Changes', 'airygen-seo' ) }
				</button>
			</div>
		</div>
	);
};

export default ClassicApp;

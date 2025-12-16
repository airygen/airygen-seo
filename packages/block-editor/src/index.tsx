import '../style.scss';

import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { registerPlugin } from '@wordpress/plugins';
import { registerBlockType } from '@wordpress/blocks';
import { PluginSidebar } from '@wordpress/edit-post';
import { Notice, PanelBody } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import { Fragment, useEffect, useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import SeoBasicsPanel from '../components/SeoBasicsPanel';
import Keyphrases from '../components/Keyphrases';
import Canonical from '../components/Canonical';
import RobotsPanel from '../components/RobotsPanel';
import ScorePanel from '../components/ScorePanel';
import SchemaPanel from '../components/SchemaPanel';
import LinkSuggestionsPanel from '../components/LinkSuggestionsPanel';
import TocPanel from '../components/TocPanel';
import TopicClusterPanel from '../components/TopicClusterPanel';
import PromptsForAgentsPanel from '../components/PromptsForAgentsPanel';
import { getEditorConfig } from '../config';
import type { EditorConfig } from '../types';
import {
	isSessionExpiredRestError,
	lockSessionExpired,
} from '../../shared/rest/session';
import {
	EDITOR_EXTENSIONS_UPDATED_EVENT,
	getRegisteredBlockEditorPanels,
	type BlockEditorPanelRegistration,
} from '../../shared/extensions/editorPanels';

const AirygenIcon = () => (
	<svg
		width="16"
		height="16"
		viewBox="0 0 48 48"
		xmlns="http://www.w3.org/2000/svg"
		preserveAspectRatio="xMidYMid meet"
	>
		<g transform="matrix(0.603828602702188,0,0,0.603828602702188,3.53115385058755,46.0)">
			<path
				className="airygen-plugin-icon-primary"
				fill="currentColor"
				fillRule="evenodd"
				d="M 33.898438 -72.868360 L 68.566895 0.000000 L -0.770020 0.000000 Z M 28.698169 -43.721016 L 49.499243 0.000000 L 7.897094 0.000000 Z"
			/>
			<path
				fill="#00a0e9"
				d="M 21.764478 -29.147344 L 35.631860 0.000000 L 7.897094 0.000000 Z"
			/>
		</g>
	</svg>
);

const TocBlockEdit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps } className="airygen-block airygen-toc-block">
			<strong>{ __( 'Airygen TOC', 'airygen-seo' ) }</strong>
			<p>{ __( 'Renders the table of contents on the frontend.', 'airygen-seo' ) }</p>
		</div>
	);
};

registerBlockType( 'airygen/toc', {
	title: __( 'Airygen Table of Contents', 'airygen-seo' ),
	description: __( 'Insert the Airygen TOC block.', 'airygen-seo' ),
	icon: 'list-view',
	category: 'widgets',
	attributes: {},
	edit: TocBlockEdit,
	save: () => null,
	supports: {
		html: false,
	},
} );

const BreadcrumbBlockEdit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps } className="airygen-block airygen-breadcrumb-block">
			<strong>{ __( 'Airygen Breadcrumbs', 'airygen-seo' ) }</strong>
			<p>{ __( 'Renders breadcrumbs on the frontend.', 'airygen-seo' ) }</p>
		</div>
	);
};

registerBlockType( 'airygen/breadcrumb', {
	title: __( 'Airygen Breadcrumbs', 'airygen-seo' ),
	description: __( 'Insert Airygen breadcrumb output.', 'airygen-seo' ),
	icon: 'editor-ol',
	category: 'widgets',
	attributes: {},
	edit: BreadcrumbBlockEdit,
	save: () => null,
	supports: {
		html: false,
	},
} );

type CorePanelKey =
	| 'serpSnippet'
	| 'keyphrases'
	| 'canonical'
	| 'robots'
	| 'scoreCalculator'
	| 'schemaMarkup'
	| 'linkSuggestions'
	| 'promptsForAgents'
	| 'toc'
	| 'topicCluster';

type PanelKey = CorePanelKey | string;

type PanelEntry = {
	key: string;
	title: string;
	render: JSX.Element;
	order: number;
};

const PANEL_ORDER_DEFAULT: CorePanelKey[] = [
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

const PANEL_ORDER_INDEX = new Map<string, number>(
	PANEL_ORDER_DEFAULT.map( ( key, index ) => [ key, ( index + 1 ) * 10 ] ),
);

const isPanelVisible = ( config: EditorConfig, key: string ): boolean => {
	if ( ! config.panelVisibility || typeof config.panelVisibility !== 'object' ) {
		return true;
	}
	if ( ! ( key in config.panelVisibility ) ) {
		return true;
	}

	return Boolean( config.panelVisibility[ key ] );
};

const isRelatedModuleEnabled = (
	config: EditorConfig,
	key: string,
	moduleKey?: string,
): boolean => {
	const modules = config.modules ?? {};
	const relatedModuleByPanel: Record<CorePanelKey, string> = {
		serpSnippet: 'onPageSeo',
		keyphrases: 'onPageSeo',
		canonical: 'onPageSeo',
		robots: 'robots',
		scoreCalculator: 'scoreCalculator',
		schemaMarkup: 'schema',
		linkSuggestions: 'linkSuggestions',
		promptsForAgents: 'markdownForAgents',
		toc: 'toc',
		topicCluster: 'topicCluster',
	};

	const resolvedModuleKey = moduleKey ?? relatedModuleByPanel[ key as CorePanelKey ];
	if ( ! resolvedModuleKey ) {
		return true;
	}

	if ( ! ( resolvedModuleKey in modules ) ) {
		return true;
	}

	return Boolean( modules[ resolvedModuleKey ] );
};

const buildPanels = ( config: EditorConfig, currentPostType: string ) => {
	const supportedPostTypes = Array.isArray( config.scoreCalculator?.postTypes )
		? config.scoreCalculator.postTypes
			.map( ( slug ) => String( slug ).trim() )
			.filter( Boolean )
		: [];
	const scoreCalculatorVisible =
		supportedPostTypes.length === 0 || supportedPostTypes.includes( currentPostType );
	const corePanels: PanelEntry[] = [
		{
			key: 'serpSnippet',
			title: __( 'SERP Snippet', 'airygen-seo' ),
			render: <SeoBasicsPanel />,
			order: PANEL_ORDER_INDEX.get( 'serpSnippet' ) ?? 20,
		},
		{
			key: 'keyphrases',
			title: __( 'Keyphrases', 'airygen-seo' ),
			render: <Keyphrases />,
			order: PANEL_ORDER_INDEX.get( 'keyphrases' ) ?? 30,
		},
		{
			key: 'canonical',
			title: __( 'Canonical URL', 'airygen-seo' ),
			render: <Canonical />,
			order: PANEL_ORDER_INDEX.get( 'canonical' ) ?? 40,
		},
		{
			key: 'robots',
			title: __( 'Rebots Meta', 'airygen-seo' ),
			render: <RobotsPanel />,
			order: PANEL_ORDER_INDEX.get( 'robots' ) ?? 60,
		},
		{
			key: 'scoreCalculator',
			title: __( 'Content Score', 'airygen-seo' ),
			render: <ScorePanel />,
			order: PANEL_ORDER_INDEX.get( 'scoreCalculator' ) ?? 10,
		},
		{
			key: 'linkSuggestions',
			title: __( 'Link Suggestions', 'airygen-seo' ),
			render: <LinkSuggestionsPanel />,
			order: PANEL_ORDER_INDEX.get( 'linkSuggestions' ) ?? 80,
		},
		{
			key: 'promptsForAgents',
			title: __( 'Prompts for Agents', 'airygen-seo' ),
			render: <PromptsForAgentsPanel />,
			order: PANEL_ORDER_INDEX.get( 'promptsForAgents' ) ?? 90,
		},
		{
			key: 'toc',
			title: __( 'Table of Contents', 'airygen-seo' ),
			render: <TocPanel />,
			order: PANEL_ORDER_INDEX.get( 'toc' ) ?? 70,
		},
		{
			key: 'topicCluster',
			title: __( 'Topic Cluster', 'airygen-seo' ),
			render: <TopicClusterPanel />,
			order: PANEL_ORDER_INDEX.get( 'topicCluster' ) ?? 100,
		},
		{
			key: 'schemaMarkup',
			title: __( 'Schema', 'airygen-seo' ),
			render: <SchemaPanel />,
			order: PANEL_ORDER_INDEX.get( 'schemaMarkup' ) ?? 50,
		},
	];

	if ( ! scoreCalculatorVisible ) {
		const scoreIndex = corePanels.findIndex( ( panel ) => panel.key === 'scoreCalculator' );
		if ( scoreIndex >= 0 ) {
			corePanels.splice( scoreIndex, 1 );
		}
	}

	const promptsEnabled = Boolean( config.markdownForAgents?.promptsForAgents );

	const visibleCorePanels = corePanels.filter( ( panel ) => {
		if ( ! isRelatedModuleEnabled( config, panel.key ) ) {
			return false;
		}

		if ( panel.key === 'promptsForAgents' && ! promptsEnabled ) {
			return false;
		}

		return isPanelVisible( config, panel.key );
	} );

	const extensionPanels = getRegisteredBlockEditorPanels()
		.filter( ( panel ) => {
			if ( ! isRelatedModuleEnabled( config, panel.key, panel.moduleKey ) ) {
				return false;
			}

			return isPanelVisible( config, panel.visibilityKey ?? panel.key );
		} )
		.map( ( panel, index ): PanelEntry => {
			const PanelComponent = panel.render as BlockEditorPanelRegistration['render'];

			return {
				key: panel.key,
				title: panel.title,
				render: <PanelComponent />,
				order:
					typeof panel.order === 'number' && Number.isFinite( panel.order )
						? panel.order
						: 1000 + index,
			};
		} );

	return [ ...visibleCorePanels, ...extensionPanels ];
};

const BlockEditorApp = () => {
	const config = getEditorConfig();
	const panelOrder = Array.isArray( config.panelOrder ) ? config.panelOrder.map( String ) : undefined;
	// eslint-disable-next-line @typescript-eslint/no-unused-vars -- bumped by event listener to trigger useMemo rebuild
	const [ registryVersion, setRegistryVersion ] = useState( 0 );
	const currentPostType = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as { getCurrentPostType?: () => string | undefined };
			return editor.getCurrentPostType ? editor.getCurrentPostType() : undefined;
		},
		[],
	);
	const resolvedPostType = currentPostType ?? 'post';

	useEffect( () => {
		if ( typeof window === 'undefined' ) {
			return () => {};
		}

		const handleRegistryUpdate = () => {
			setRegistryVersion( ( current ) => current + 1 );
		};

		window.addEventListener( EDITOR_EXTENSIONS_UPDATED_EVENT, handleRegistryUpdate );

		return () => {
			window.removeEventListener( EDITOR_EXTENSIONS_UPDATED_EVENT, handleRegistryUpdate );
		};
	}, [] );

	const basePanels = useMemo( () => {
		return buildPanels( config, resolvedPostType );
	// eslint-disable-next-line react-hooks/exhaustive-deps -- registryVersion triggers rebuild when extensions register
	}, [ config, resolvedPostType, registryVersion ] );

	const orderedPanels = useMemo( () => {
		const configuredOrder = Array.isArray( panelOrder ) ? panelOrder : [];
		const configuredIndex = new Map(
			configuredOrder.map( ( key, index ) => [ key, index ] ),
		);

		return [ ...basePanels ].sort( ( left, right ) => {
			const leftConfigured = configuredIndex.get( left.key );
			const rightConfigured = configuredIndex.get( right.key );

			if ( typeof leftConfigured === 'number' || typeof rightConfigured === 'number' ) {
				if ( typeof leftConfigured !== 'number' ) {
					return 1;
				}
				if ( typeof rightConfigured !== 'number' ) {
					return -1;
				}

				return leftConfigured - rightConfigured;
			}

			if ( left.order === right.order ) {
				return left.key.localeCompare( right.key );
			}

			return left.order - right.order;
		} );
	}, [ basePanels, panelOrder ] );

	const storageKey = 'airygen_seo_open_panel';
	const [ openPanel, setOpenPanel ] = useState< PanelKey | null >( () => {
		if ( typeof window === 'undefined' || ! window.localStorage ) {
			return null;
		}
		const stored = window.localStorage.getItem( storageKey );
		if ( ! stored ) {
			return null;
		}
		const match = orderedPanels.find( ( panel ) => panel.key === stored );
		return match ? match.key : null;
	} );

	const handleToggle = useMemo(
		() => ( key: PanelKey ) => {
			setOpenPanel( ( current ) => {
				const next = current === key ? null : key;
				if ( typeof window !== 'undefined' && window.localStorage ) {
					if ( next ) {
						window.localStorage.setItem( storageKey, next );
					} else {
						window.localStorage.removeItem( storageKey );
					}
				}
				return next;
			} );
		},
		[],
	);
	useEffect( () => {
		if ( openPanel && ! orderedPanels.find( ( panel ) => panel.key === openPanel ) ) {
			setOpenPanel( null );
		}
	}, [ openPanel, orderedPanels ] );

	return (
		<Fragment>
			<PluginSidebar
				name="airygen-seo-sidebar"
				title={ __( 'Airygen SEO', 'airygen-seo' ) }
				icon={ <AirygenIcon /> }
			>
				{ orderedPanels.length === 0 ? (
					<div className="components-panel__body is-opened">
						<p style={ { margin: 0, padding: '16px', color: '#50575e' } }>
							{ __( 'No panels available.', 'airygen-seo' ) }
						</p>
					</div>
				) : (
					orderedPanels.map( ( panel ) => (
						<div
							key={ panel.key }
							className={ `airygen-editor-panel airygen-editor-panel--${ panel.key }` }
							data-airygen-panel={ panel.key }
							data-airygen-e2e={ `editor-panel-${ panel.key }` }
						>
							<PanelBody
								title={ panel.title }
								opened={ openPanel === panel.key }
								onToggle={ () => handleToggle( panel.key ) }
							>
								{ panel.render }
							</PanelBody>
						</div>
					) )
				) }
			</PluginSidebar>
		</Fragment>
	);
};

const SessionExpiredSidebar = () => (
	<PluginSidebar
		name="airygen-seo-sidebar"
		title={ __( 'Airygen SEO', 'airygen-seo' ) }
		icon={ <AirygenIcon /> }
	>
		<div style={ { padding: '12px' } }>
			<Notice status="error" isDismissible={ false }>
				{ __( 'Permission expired. Please log in again.', 'airygen-seo' ) }
			</Notice>
		</div>
	</PluginSidebar>
);

const preflightSessionCheck = async (): Promise<boolean> => {
	const config = getEditorConfig();
	const endpoint = config.sessionCheckUrl ?? '/wp-json/airygen/v1/session-check';
	const nonce = config.restNonce ?? '';

	try {
		const response = await window.fetch( endpoint, {
			method: 'GET',
			credentials: 'same-origin',
			headers: nonce
				? {
					'X-WP-Nonce': nonce,
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

		if ( isSessionExpiredRestError( { status: response.status, ...payload } ) ) {
			lockSessionExpired( 'block' );
			return false;
		}
	} catch {
		return true;
	}

	return true;
};

domReady( () => {
	void ( async () => {
		const validSession = await preflightSessionCheck();
		registerPlugin( 'airygen-seo-sidebar', {
			render: validSession ? BlockEditorApp : SessionExpiredSidebar,
		} );
	} )();
} );

import { useCallback, useMemo, useState } from '@wordpress/element';
import type { DragEvent } from 'react';
import { __ } from '@wordpress/i18n';

import Checkbox from '../../../components/Checkbox';
import HeadingIcon from '../../../components/HeadingIcon';
import Input from '../../../components/Input';
import { LinkCounterIcon } from '../../../components/Icons';
import Select from '../../../components/Select';
import Toggle from '../../../components/Toggle';
import Button from '../../../components/Button';
import PreviewDeviceSwitcher from '../../../components/PreviewDeviceSwitcher';
import PreviewDeviceFrame, {
	type PreviewDeviceKind,
} from '../../../components/PreviewDeviceFrame';
import TransparentColorPicker from '../../../components/TransparentColorPicker';
import PreviewCodeSamples from '../../../components/PreviewCodeSamples';
import SectionHeaderSettingsCard from '../../../components/SectionHeaderSettingsCard';
import SectionHeaderStyleCards from '../../../components/SectionHeaderStyleCards';
import SectionBodyContainerStyleCard from '../../../components/SectionBodyContainerStyleCard';
import {
	getAutomaticInjectionLabel,
	getBlockSnippetLabel,
	getManualInjectionLabel,
	getSnippetCopiedLabel,
	getShortcodeLabel,
	getTemplateFunctionLabel,
	getUnableToCopySnippetLabel,
} from '../../../utils/i18n';
import type { MetaPayload } from '../../../types/api';
import type {
	RelatedPostsBlockId,
	RelatedPostsLayoutRegion,
	RelatedPostsSettings,
} from '../../../types/settings';

type RelatedPostsTabProps = {
	settings: RelatedPostsSettings;
	meta: MetaPayload;
	onChange: ( next: RelatedPostsSettings ) => void;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
};

type BlockDefinition = {
	id: RelatedPostsBlockId;
	label: string;
	description: string;
};

type RelatedPostsPreviewViewport = PreviewDeviceKind;

const BLOCK_DEFINITIONS: BlockDefinition[] = [
	{ id: 'featured_image', label: __( 'Featured image', 'airygen-seo' ), description: __( 'Post thumbnail image.', 'airygen-seo' ) },
	{ id: 'title', label: __( 'Title', 'airygen-seo' ), description: __( 'Post title with permalink.', 'airygen-seo' ) },
	{ id: 'excerpt', label: __( 'Excerpt', 'airygen-seo' ), description: __( 'Post excerpt with truncation rules.', 'airygen-seo' ) },
	{ id: 'author', label: __( 'Author', 'airygen-seo' ), description: __( 'Display author name.', 'airygen-seo' ) },
	{ id: 'date', label: __( 'Date', 'airygen-seo' ), description: __( 'Published or modified date.', 'airygen-seo' ) },
];

const TEMPLATE_OPTIONS = [
	{ value: 'single_column', label: __( 'Template 1', 'airygen-seo' ) },
	{ value: 'sidebar_left', label: __( 'Template 2', 'airygen-seo' ) },
] as const;

const DISPLAY_PRESET_OPTIONS = [
	{ value: '2x2', label: '2 x 2' },
	{ value: '3x2', label: '3 x 2' },
	{ value: '4x2', label: '4 x 2' },
	{ value: '4x1', label: '4 x 1' },
	{ value: '1x4', label: '1 x 4' },
] as const;

const getDefaultRegion = ( blockId: RelatedPostsBlockId ): RelatedPostsLayoutRegion => {
	if ( blockId === 'featured_image' ) {
		return 'header';
	}
	if ( blockId === 'author' ) {
		return 'footer_left';
	}
	if ( blockId === 'date' ) {
		return 'footer_right';
	}
	return 'body';
};

const RelatedPostsColorPicker = TransparentColorPicker;

const escapeHtml = ( value: string ): string =>
	value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );

const PreviewLaptopIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M1.11068 1.66569H5.55341V4.4424H1.11068V1.66569ZM5.55341 4.99774C5.7007 4.99774 5.84195 4.93923 5.9461 4.83508C6.05025 4.73094 6.10875 4.58968 6.10875 4.4424V1.66569C6.10875 1.35748 5.85885 1.11035 5.55341 1.11035H1.11068C0.802468 1.11035 0.555341 1.35748 0.555341 1.66569V4.4424C0.555341 4.58968 0.61385 4.73094 0.717997 4.83508C0.822144 4.93923 0.963397 4.99774 1.11068 4.99774H0V5.55308H6.6641V4.99774H5.55341Z" fill="currentColor" />
	</svg>
);

const PreviewTabletIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M5.27566 4.99774H1.38827V1.66569H5.27566V4.99774ZM5.831 1.11035H0.832929C0.524715 1.11035 0.277588 1.35748 0.277588 1.66569V4.99774C0.277588 5.14503 0.336097 5.28628 0.440244 5.39043C0.54439 5.49457 0.685644 5.55308 0.832929 5.55308H5.831C5.97829 5.55308 6.11954 5.49457 6.22369 5.39043C6.32783 5.28628 6.38634 5.14503 6.38634 4.99774V1.66569C6.38634 1.35748 6.13644 1.11035 5.831 1.11035Z" fill="currentColor" />
	</svg>
);

const PreviewCellphoneIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M4.72048 5.27542H1.94377V1.38803H4.72048V5.27542ZM4.72048 0.277344H1.94377C1.63555 0.277344 1.38843 0.524471 1.38843 0.832685V5.83076C1.38843 5.97804 1.44694 6.1193 1.55108 6.22344C1.65523 6.32759 1.79648 6.3861 1.94377 6.3861H4.72048C4.86776 6.3861 5.00901 6.32759 5.11316 6.22344C5.21731 6.1193 5.27582 5.97804 5.27582 5.83076V0.832685C5.27582 0.524471 5.02591 0.277344 4.72048 0.277344Z" fill="currentColor" />
	</svg>
);

const RelatedPostsTab = ( { settings, meta, onChange, onCopyToClipboard }: RelatedPostsTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'layout' | 'preview'>( 'settings' );
	const [ draggingBlockId, setDraggingBlockId ] = useState<RelatedPostsBlockId | null>( null );
	const [ previewViewport, setPreviewViewport ] = useState<RelatedPostsPreviewViewport>( 'laptop' );
	const adminConfig = window as Window & {
		airygenAdmin?: {
			assets?: {
				relatedPostsDemoImage?: string;
			};
		};
	};
	const relatedPostsDemoImage =
		typeof adminConfig.airygenAdmin?.assets?.relatedPostsDemoImage === 'string'
			? adminConfig.airygenAdmin.assets.relatedPostsDemoImage.trim()
			: '';
	const templateTagSnippet =
		"<?php if ( function_exists( 'airygen_the_related_posts' ) ) { airygen_the_related_posts(); } ?>";
	const shortcodeSnippet = '[airygen_related_posts]';
	const blockSnippet = '<!-- wp:shortcode -->\n[airygen_related_posts]\n<!-- /wp:shortcode -->';
	const templateTagId = 'airygen-related-posts-php-snippet';
	const shortcodeId = 'airygen-related-posts-shortcode-snippet';
	const blockSnippetId = 'airygen-related-posts-block-snippet';
	const relatedPostsModuleLabel = __( 'related posts', 'airygen-seo' );
	const manualRelatedPostsLabel = getManualInjectionLabel( relatedPostsModuleLabel );
	const automaticRelatedPostsLabel = getAutomaticInjectionLabel( relatedPostsModuleLabel );
	const injectedCssId = 'airygen-related-posts-injected-css';
	const previewHtmlId = 'airygen-related-posts-html-sample';
	const imageSizeOptions = useMemo(
		() =>
			( meta.mediaImageSizes ?? [] ).map( ( item ) => ( {
				value: item.slug,
				label: item.label,
			} ) ),
		[ meta.mediaImageSizes ],
	);
	const imageSizeMetaMap = useMemo(
		() => new Map( ( meta.mediaImageSizes ?? [] ).map( ( item ) => [ item.slug, item ] ) ),
		[ meta.mediaImageSizes ],
	);
	const blockDefinitions = useMemo(
		() => new Map( BLOCK_DEFINITIONS.map( ( item ) => [ item.id, item ] ) ),
		[],
	);
	const visibleBlocks = settings.blockOrder.filter( ( id ) => blockDefinitions.has( id ) );
	const hiddenBlocks = BLOCK_DEFINITIONS.filter( ( item ) => ! visibleBlocks.includes( item.id ) );
	const blockRegions = useMemo( () => {
		const next: Partial<Record<RelatedPostsBlockId, RelatedPostsLayoutRegion>> = {};
		visibleBlocks.forEach( ( blockId ) => {
			const raw = settings.blockRegions[ blockId ];
			const normalized =
				raw === 'header' ||
				raw === 'body' ||
				raw === 'left_sidebar' ||
				raw === 'footer_left' ||
				raw === 'footer_center' ||
				raw === 'footer_right'
					? raw
					: getDefaultRegion( blockId );
			next[ blockId ] =
				settings.template === 'single_column' && normalized === 'left_sidebar'
					? 'body'
					: normalized;
		} );
		return next;
	}, [ visibleBlocks, settings.blockRegions, settings.template ] );
	const visibleBlocksByRegion = useMemo( () => {
		const grouped: Record<RelatedPostsLayoutRegion, RelatedPostsBlockId[]> = {
			header: [],
			body: [],
			left_sidebar: [],
			footer_left: [],
			footer_center: [],
			footer_right: [],
		};
		visibleBlocks.forEach( ( blockId ) => {
			const region = blockRegions[ blockId ] ?? getDefaultRegion( blockId );
			grouped[ region ].push( blockId );
		} );
		return grouped;
	}, [ visibleBlocks, blockRegions ] );

	const updateSettings = ( patch: Partial<RelatedPostsSettings> ) => {
		onChange( { ...settings, ...patch } );
	};
	const updateGridContainer = ( patch: Partial<RelatedPostsSettings['gridContainer']> ) => {
		updateSettings( {
			gridContainer: {
				...settings.gridContainer,
				...patch,
			},
		} );
	};
	const updatePostContainer = ( patch: Partial<RelatedPostsSettings['postContainer']> ) => {
		updateSettings( {
			postContainer: {
				...settings.postContainer,
				...patch,
			},
		} );
	};
	const relatedHeaderContainer = useMemo(
		() =>
			settings.headerContainer ?? {
				borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
				borderRadius: 0,
				borderStyle: 'solid' as const,
				borderColor: '#a3a3a3',
				paddings: { top: 0, right: 0, bottom: 0, left: 15 },
				bgColor: 'transparent',
				margins: { top: 0, right: 0, bottom: 12, left: 0 },
			},
		[ settings.headerContainer ],
	);
	const relatedHeaderTitle = useMemo(
		() =>
			settings.headerTitle ?? {
				fontStyle: { bold: false, italic: false, underline: false },
				color: '#0f172a',
				fontSize: 18,
			},
		[ settings.headerTitle ],
	);
	const updateHeaderContainer = (
		patch: Partial<NonNullable<RelatedPostsSettings['headerContainer']>>,
	) =>
		updateSettings( {
			headerContainer: {
				...relatedHeaderContainer,
				...patch,
			},
		} );
	const updateHeaderTitle = (
		patch: Partial<NonNullable<RelatedPostsSettings['headerTitle']>>,
	) =>
		updateSettings( {
			headerTitle: {
				...relatedHeaderTitle,
				...patch,
			},
		} );
	const styleGridThemes = useMemo(
		() => [
			{
				id: 'snow-slate',
				label: __( 'Snow Slate', 'airygen-seo' ),
				description: __( 'Neutral white + light gray with crisp slate text.', 'airygen-seo' ),
				gridContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 6,
					borderStyle: 'dashed' as const,
					borderColor: '#dddddd',
					bgColor: 'transparent',
					paddings: { top: 9, right: 16, bottom: 16, left: 16 },
					gap: 16,
				},
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#a3a3a3',
					bgColor: 'transparent',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
				postContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#e2e8f0',
					bgColor: '#ffffff',
					paddings: { top: 12, right: 12, bottom: 12, left: 12 },
					gap: 10,
				},
			},
			{
				id: 'honey-paper',
				label: __( 'Honey Paper', 'airygen-seo' ),
				description: __( 'Soft parchment yellow for warm, readable TOCs.', 'airygen-seo' ),
				gridContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 6,
					borderStyle: 'solid' as const,
					borderColor: '#e4d8aa',
					bgColor: '#fefbf1',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 18,
				},
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#a3a3a3',
					bgColor: 'transparent',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
				postContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 12,
					borderStyle: 'solid' as const,
					borderColor: '#eadfc0',
					bgColor: '#fffdf5',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 12,
				},
			},
			{
				id: 'sky-breeze',
				label: __( 'Sky Breeze', 'airygen-seo' ),
				description: __( 'Airy light blues with calm contrast.', 'airygen-seo' ),
				gridContainer: {
					borderWidths: { top: 2, right: 2, bottom: 2, left: 2 },
					borderRadius: 6,
					borderStyle: 'dotted' as const,
					borderColor: '#93c5fd',
					bgColor: 'transparent',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 18,
				},
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#4b7db9',
					bgColor: 'transparent',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
				postContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 14,
					borderStyle: 'solid' as const,
					borderColor: '#bfdbfe',
					bgColor: '#f8fbff',
					paddings: { top: 14, right: 14, bottom: 14, left: 14 },
					gap: 12,
				},
			},
			{
				id: 'mint-calm',
				label: __( 'Mint Calm', 'airygen-seo' ),
				description: __( 'Mint green tone that feels supportive and stable.', 'airygen-seo' ),
				gridContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 6,
					borderStyle: 'dashed' as const,
					borderColor: '#7dcaab',
					bgColor: '#fafffd',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 18,
				},
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#7dcaab',
					bgColor: 'transparent',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
				postContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 12,
					borderStyle: 'dashed' as const,
					borderColor: '#b7e4d1',
					bgColor: '#ffffff',
					paddings: { top: 14, right: 14, bottom: 14, left: 14 },
					gap: 12,
				},
			},
			{
				id: 'rose-blush',
				label: __( 'Rose Blush', 'airygen-seo' ),
				description: __( 'Soft rose palette with friendly, approachable presence.', 'airygen-seo' ),
				gridContainer: {
					borderWidths: { top: 5, right: 5, bottom: 5, left: 5 },
					borderRadius: 6,
					borderStyle: 'dotted' as const,
					borderColor: '#ebc6ca',
					bgColor: '#fffafa',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 20,
				},
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#881337',
					bgColor: 'transparent',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
				postContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 16,
					borderStyle: 'solid' as const,
					borderColor: '#f2d3d7',
					bgColor: '#ffffff',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 12,
				},
			},
			{
				id: 'lavender-mist',
				label: __( 'Lavender Mist', 'airygen-seo' ),
				description: __( 'Light violet with a modern AI/creative product vibe.', 'airygen-seo' ),
				gridContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 0 },
					borderRadius: 6,
					borderStyle: 'solid' as const,
					borderColor: '#c4b5fd',
					bgColor: '#f5f3ff',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 18,
				},
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#472975',
					bgColor: 'transparent',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
				postContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					borderRadius: 16,
					borderStyle: 'solid' as const,
					borderColor: '#ddd6fe',
					bgColor: '#ffffff',
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					gap: 12,
				},
			},
		],
		[],
	);
	const isSameStyleGridTheme = useCallback(
		(
			candidate: {
				gridContainer: RelatedPostsSettings['gridContainer'];
				headerContainer: NonNullable<RelatedPostsSettings['headerContainer']>;
				headerTitle: NonNullable<RelatedPostsSettings['headerTitle']>;
				postContainer: RelatedPostsSettings['postContainer'];
			},
		) =>
			JSON.stringify( candidate.gridContainer.borderWidths ) === JSON.stringify( settings.gridContainer.borderWidths ) &&
			candidate.gridContainer.borderRadius === settings.gridContainer.borderRadius &&
			candidate.gridContainer.borderStyle === settings.gridContainer.borderStyle &&
			candidate.gridContainer.borderColor === settings.gridContainer.borderColor &&
			candidate.gridContainer.bgColor === settings.gridContainer.bgColor &&
			JSON.stringify( candidate.gridContainer.paddings ) === JSON.stringify( settings.gridContainer.paddings ) &&
			candidate.gridContainer.gap === settings.gridContainer.gap &&
			JSON.stringify( candidate.headerContainer ) === JSON.stringify( relatedHeaderContainer ) &&
			JSON.stringify( candidate.headerTitle ) === JSON.stringify( relatedHeaderTitle ) &&
			JSON.stringify( candidate.postContainer.borderWidths ) === JSON.stringify( settings.postContainer.borderWidths ) &&
			candidate.postContainer.borderRadius === settings.postContainer.borderRadius &&
			candidate.postContainer.borderStyle === settings.postContainer.borderStyle &&
			candidate.postContainer.borderColor === settings.postContainer.borderColor &&
			candidate.postContainer.bgColor === settings.postContainer.bgColor &&
			JSON.stringify( candidate.postContainer.paddings ) === JSON.stringify( settings.postContainer.paddings ) &&
			candidate.postContainer.gap === settings.postContainer.gap,
		[ relatedHeaderContainer, relatedHeaderTitle, settings.gridContainer, settings.postContainer ],
	);

	const moveBlockToRegion = (
		sourceId: RelatedPostsBlockId,
		targetRegion: RelatedPostsLayoutRegion,
		targetId?: RelatedPostsBlockId,
	) => {
		const normalizedRegion =
			settings.template === 'single_column' && targetRegion === 'left_sidebar'
				? 'body'
				: targetRegion;
		const order = [ ...visibleBlocks ];
		const sourceIndex = order.indexOf( sourceId );
		if ( sourceIndex >= 0 ) {
			order.splice( sourceIndex, 1 );
		}
		if ( targetId ) {
			const targetIndex = order.indexOf( targetId );
			if ( targetIndex >= 0 ) {
				order.splice( targetIndex, 0, sourceId );
			} else {
				order.push( sourceId );
			}
		} else {
			order.push( sourceId );
		}

		updateSettings( {
			blockOrder: order,
			blockRegions: {
				...settings.blockRegions,
				[ sourceId ]: normalizedRegion,
			},
		} );
	};

	const removeFromVisibleBlocks = ( blockId: RelatedPostsBlockId ) => {
		const nextRegions = { ...settings.blockRegions };
		delete nextRegions[ blockId ];
		updateSettings( {
			blockOrder: visibleBlocks.filter( ( id ) => id !== blockId ),
			blockRegions: nextRegions,
		} );
	};

	const handleDragStart = (
		event: DragEvent<HTMLDivElement>,
		blockId: RelatedPostsBlockId,
	) => {
		setDraggingBlockId( blockId );
		event.dataTransfer.setData( 'text/plain', blockId );
		event.dataTransfer.effectAllowed = 'move';
	};

	const handleDragEnd = () => {
		setDraggingBlockId( null );
	};

	const extractDragId = ( event: DragEvent<HTMLElement> ): RelatedPostsBlockId | null => {
		const id = ( event.dataTransfer.getData( 'text/plain' ) || draggingBlockId || '' ) as RelatedPostsBlockId;
		return blockDefinitions.has( id ) ? id : null;
	};

	const handleVisibleDrop = (
		event: DragEvent<HTMLElement>,
		targetRegion: RelatedPostsLayoutRegion,
		targetId?: RelatedPostsBlockId,
	) => {
		event.preventDefault();
		const sourceId = extractDragId( event );
		if ( sourceId ) {
			moveBlockToRegion( sourceId, targetRegion, targetId );
		}
		setDraggingBlockId( null );
	};

	const handleHiddenDrop = ( event: DragEvent<HTMLElement> ) => {
		event.preventDefault();
		const sourceId = extractDragId( event );
		if ( sourceId ) {
			removeFromVisibleBlocks( sourceId );
		}
		setDraggingBlockId( null );
	};

	const regionLabels: Record<RelatedPostsLayoutRegion, string> = {
		header: __( 'Header', 'airygen-seo' ),
		body: __( 'Body', 'airygen-seo' ),
		left_sidebar: __( 'Left sidebar', 'airygen-seo' ),
		footer_left: __( 'Left', 'airygen-seo' ),
		footer_center: __( 'Center', 'airygen-seo' ),
		footer_right: __( 'Right', 'airygen-seo' ),
	};
	const footerRegions = useMemo( (): RelatedPostsLayoutRegion[] => {
		if ( settings.footerColumns === 1 ) {
			return [ 'footer_left' ];
		}
		if ( settings.footerColumns === 2 ) {
			return [ 'footer_left', 'footer_right' ];
		}
		return [ 'footer_left', 'footer_center', 'footer_right' ];
	}, [ settings.footerColumns ] );
	const handleFooterColumnsChange = ( value: string ) => {
		let nextColumns: RelatedPostsSettings['footerColumns'] = 3;
		if ( value === '1' ) {
			nextColumns = 1;
		} else if ( value === '2' ) {
			nextColumns = 2;
		}
		const nextRegions = { ...settings.blockRegions };
		Object.keys( nextRegions ).forEach( ( blockId ) => {
			const region = nextRegions[ blockId as RelatedPostsBlockId ];
			if ( ! region ) {
				return;
			}
			if ( nextColumns === 1 && ( region === 'footer_center' || region === 'footer_right' ) ) {
				nextRegions[ blockId as RelatedPostsBlockId ] = 'footer_left';
				return;
			}
			if ( nextColumns === 2 && region === 'footer_center' ) {
				nextRegions[ blockId as RelatedPostsBlockId ] = 'footer_left';
			}
		} );
		updateSettings( {
			footerColumns: nextColumns,
			blockRegions: nextRegions,
		} );
	};
	const renderRegionCards = ( region: RelatedPostsLayoutRegion ) => (
		<div className="space-y-2">
			{ visibleBlocksByRegion[ region ].map( ( blockId ) => {
				const def = blockDefinitions.get( blockId );
				if ( ! def ) {
					return null;
				}
				return (
					<div
						key={ `visible-block-${ region }-${ blockId }` }
						role="button"
						tabIndex={ 0 }
						draggable
						onDragStart={ ( event ) => handleDragStart( event, blockId ) }
						onDragEnd={ handleDragEnd }
						onDragOver={ ( event ) => {
							event.preventDefault();
							event.dataTransfer.dropEffect = 'move';
						} }
						onDrop={ ( event ) => handleVisibleDrop( event, region, blockId ) }
						className="relative cursor-grab rounded-md border border-slate-200 bg-white p-2 shadow-sm active:cursor-grabbing"
					>
						<button
							type="button"
							aria-label={ __( 'Hide block', 'airygen-seo' ) }
							className="absolute right-2 top-2 z-10 flex h-4 w-4 items-center justify-center rounded-sm text-slate-400 hover:bg-slate-100 hover:text-slate-700"
							onClick={ () => removeFromVisibleBlocks( blockId ) }
						>
							<span className="dashicons dashicons-no-alt m-0 block h-[14px] w-[14px] text-[14px] leading-[14px]" />
						</button>
						<div className="mb-1 flex items-start justify-between gap-2 pr-5">
							<p className="text-sm font-medium text-slate-900">{ def.label }</p>
						</div>
						<p className="mt-1 text-xs text-slate-500">{ def.description }</p>
					</div>
				);
			} ) }
			{ visibleBlocksByRegion[ region ].length === 0 ? (
				<p className="text-xs text-slate-400">{ __( 'Drop blocks here.', 'airygen-seo' ) }</p>
			) : null }
		</div>
	);

	const previewCss = `.airygen-auto-related-posts__section-title{margin:${ relatedHeaderContainer.margins.top }px ${ relatedHeaderContainer.margins.right }px ${ relatedHeaderContainer.margins.bottom }px ${ relatedHeaderContainer.margins.left }px;padding:${ relatedHeaderContainer.paddings.top }px ${ relatedHeaderContainer.paddings.right }px ${ relatedHeaderContainer.paddings.bottom }px ${ relatedHeaderContainer.paddings.left }px;border-width:${ relatedHeaderContainer.borderWidths.top }px ${ relatedHeaderContainer.borderWidths.right }px ${ relatedHeaderContainer.borderWidths.bottom }px ${ relatedHeaderContainer.borderWidths.left }px;border-style:${ relatedHeaderContainer.borderStyle };border-color:${ relatedHeaderContainer.borderColor };border-radius:${ relatedHeaderContainer.borderRadius }px;background:${ relatedHeaderContainer.bgColor };color:${ relatedHeaderTitle.color };font-size:${ relatedHeaderTitle.fontSize }px;font-weight:${ relatedHeaderTitle.fontStyle.bold ? '700' : '400' };font-style:${ relatedHeaderTitle.fontStyle.italic ? 'italic' : 'normal' };text-decoration:${ relatedHeaderTitle.fontStyle.underline ? 'underline' : 'none' };}.airygen-auto-related-posts__grid{display:grid;gap:${ settings.gridContainer.gap }px;padding:${ settings.gridContainer.paddings.top }px ${ settings.gridContainer.paddings.right }px ${ settings.gridContainer.paddings.bottom }px ${ settings.gridContainer.paddings.left }px;border-style:${ settings.gridContainer.borderStyle };border-color:${ settings.gridContainer.borderColor };border-width:${ settings.gridContainer.borderWidths.top }px ${ settings.gridContainer.borderWidths.right }px ${ settings.gridContainer.borderWidths.bottom }px ${ settings.gridContainer.borderWidths.left }px;border-radius:${ settings.gridContainer.borderRadius }px;background:${ settings.gridContainer.bgColor };box-sizing:border-box;}.airygen-auto-related-posts__card{padding:${ settings.postContainer.paddings.top }px ${ settings.postContainer.paddings.right }px ${ settings.postContainer.paddings.bottom }px ${ settings.postContainer.paddings.left }px;border-style:${ settings.postContainer.borderStyle };border-color:${ settings.postContainer.borderColor };border-width:${ settings.postContainer.borderWidths.top }px ${ settings.postContainer.borderWidths.right }px ${ settings.postContainer.borderWidths.bottom }px ${ settings.postContainer.borderWidths.left }px;border-radius:${ settings.postContainer.borderRadius }px;background:${ settings.postContainer.bgColor };display:flex;flex-direction:column;gap:${ settings.postContainer.gap }px;box-sizing:border-box;}.airygen-auto-related-posts__layout{display:grid;grid-template-columns:minmax(0,1fr) 3fr;gap:${ settings.postContainer.gap }px;align-items:start;height:100%;}.airygen-auto-related-posts__main{display:flex;flex-direction:column;gap:${ settings.postContainer.gap }px;}.airygen-auto-related-posts__card--sidebar_left .airygen-auto-related-posts__main{height:100%;}.airygen-auto-related-posts__card--sidebar_left .airygen-auto-related-posts__footer-grid{margin-top:auto;}.airygen-auto-related-posts__region{display:flex;flex-direction:column;gap:${ settings.postContainer.gap }px;}.airygen-auto-related-posts__footer-grid{display:grid;grid-template-columns:repeat(${ settings.footerColumns },minmax(0,1fr));gap:${ settings.postContainer.gap }px;align-items:start;}.airygen-auto-related-posts__region--footer-left{text-align:left;}.airygen-auto-related-posts__region--footer-center{text-align:center;}.airygen-auto-related-posts__region--footer-right{text-align:right;}.airygen-auto-related-posts__thumb-link{display:block;overflow:hidden;border-radius:${ settings.featuredImageRadius }px;}.airygen-auto-related-posts__thumb{display:block;max-width:100%;height:auto;border-radius:${ settings.featuredImageRadius }px;}.airygen-auto-related-posts__title{margin:0;font-size:${ settings.titleFontSize }px;color:${ settings.titleColor };font-weight:${ settings.titleBold ? 700 : 400 };font-style:${ settings.titleItalic ? 'italic' : 'normal' };}.airygen-auto-related-posts__title a{color:inherit;text-decoration:none;}.airygen-auto-related-posts__excerpt{margin:0;font-size:${ settings.excerptFontSize }px;color:${ settings.excerptColor };line-height:1.5;}.airygen-auto-related-posts__excerpt--fade::after{content:"";position:absolute;left:0;right:0;bottom:0;height:${ settings.excerptMaskHeight }px;background:linear-gradient(to bottom,rgba(255,255,255,0),${ settings.excerptFadeColor });}.airygen-auto-related-posts__excerpt--fade{position:relative;max-height:4.5em;overflow:hidden;}.airygen-auto-related-posts__author{margin:0;font-size:${ settings.authorFontSize }px;color:${ settings.authorColor };font-weight:${ settings.authorBold ? 700 : 400 };font-style:${ settings.authorItalic ? 'italic' : 'normal' };}.airygen-auto-related-posts__date{margin:0;font-size:${ settings.authorFontSize }px;color:${ settings.authorColor };font-weight:${ settings.authorBold ? 700 : 400 };font-style:${ settings.authorItalic ? 'italic' : 'normal' };}.airygen-auto-related-posts--cellphone .airygen-auto-related-posts__grid{grid-template-columns:repeat(1,minmax(0,1fr))!important;}.airygen-auto-related-posts--cellphone .airygen-auto-related-posts__layout{grid-template-columns:1fr;}`;

	let previewCardCount = 4;
	if ( settings.displayPreset === '4x2' ) {
		previewCardCount = 8;
	} else if ( settings.displayPreset === '3x2' ) {
		previewCardCount = 6;
	}
	let previewGridTemplateColumns = 'repeat(2,minmax(0,1fr))';
	if ( settings.displayPreset === '4x2' || settings.displayPreset === '4x1' ) {
		previewGridTemplateColumns = 'repeat(4,minmax(0,1fr))';
	} else if ( settings.displayPreset === '3x2' ) {
		previewGridTemplateColumns = 'repeat(3,minmax(0,1fr))';
	} else if ( settings.displayPreset === '1x4' ) {
		previewGridTemplateColumns = 'repeat(1,minmax(0,1fr))';
	}
	const previewGridStyle = `grid-template-columns:${ previewGridTemplateColumns };`;
	const previewViewportClass =
		previewViewport === 'cellphone' ? 'airygen-auto-related-posts--cellphone' : '';
	const selectedImageSize = imageSizeMetaMap.get( settings.featuredImageSize );
	const previewImageWidth =
		typeof selectedImageSize?.width === 'number' && selectedImageSize.width > 0
			? Math.floor( selectedImageSize.width )
			: 0;
	const previewImageHeight =
		typeof selectedImageSize?.height === 'number' && selectedImageSize.height > 0
			? Math.floor( selectedImageSize.height )
			: 0;

	const previewBlockMarkup = ( blockId: RelatedPostsBlockId, sampleIndex = 1 ): string => {
		if ( blockId === 'featured_image' ) {
			const imageSrc = relatedPostsDemoImage !== '' ? relatedPostsDemoImage : '';
			const maxWidthStyle = previewImageWidth > 0 ? ` style="max-width:${ previewImageWidth }px;"` : '';
			const widthAttr = previewImageWidth > 0 ? ` width="${ previewImageWidth }"` : '';
			const heightAttr = previewImageHeight > 0 ? ` height="${ previewImageHeight }"` : '';
			return `<a class="airygen-auto-related-posts__thumb-link" href="#"${ maxWidthStyle }><img class="airygen-auto-related-posts__thumb" src="${ imageSrc }" alt="Featured image"${ widthAttr }${ heightAttr } /></a>`;
		}
		if ( blockId === 'title' ) {
			return `<h3 class="airygen-auto-related-posts__title"><a href="#">Sample related post title ${ sampleIndex }</a></h3>`;
		}
		if ( blockId === 'excerpt' ) {
			const baseText =
				'This sample excerpt shows how Related Posts content appears in your selected card style, spacing, and typography settings. Adjust the style controls to fine-tune readability, hierarchy, and visual rhythm across each card in this preview area.';
			const text = baseText.length >= 300 ? baseText.slice( 0, 300 ) : baseText.padEnd( 300, '.' );
			const limited = text.length > settings.excerptMaxChars ? `${ text.slice( 0, settings.excerptMaxChars ) }...` : text;
			const fadeClass = settings.excerptFadeMask ? ' airygen-auto-related-posts__excerpt--fade' : '';
			return `<p class="airygen-auto-related-posts__excerpt${ fadeClass }">${ limited }</p>`;
		}
		if ( blockId === 'author' ) {
			return '<p class="airygen-auto-related-posts__author">By Airygen Team</p>';
		}
		return '<p class="airygen-auto-related-posts__date"><time datetime="2026-02-28T00:00:00+00:00">February 28, 2026</time></p>';
	};

	const previewRegionHtml = ( region: RelatedPostsLayoutRegion, sampleIndex: number ): string =>
		visibleBlocksByRegion[ region ].map( ( blockId ) => previewBlockMarkup( blockId, sampleIndex ) ).join( '\n' );
	const previewFooterHtml = ( sampleIndex: number ): string =>
		footerRegions
			.map(
				( region ) =>
					`<div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--${ region.replace( '_', '-' ) }">\n          ${ previewRegionHtml( region, sampleIndex ) }\n        </div>`,
			)
			.join( '\n' );

	const previewCardHtml = ( sampleIndex: number ): string =>
		settings.template === 'sidebar_left'
			? `<article class="airygen-auto-related-posts__card airygen-auto-related-posts__card--sidebar_left">\n  <div class="airygen-auto-related-posts__layout">\n    <div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--left-sidebar">\n      ${ previewRegionHtml( 'left_sidebar', sampleIndex ) }\n    </div>\n    <div class="airygen-auto-related-posts__main">\n      <div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--header">\n        ${ previewRegionHtml( 'header', sampleIndex ) }\n      </div>\n      <div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--body">\n        ${ previewRegionHtml( 'body', sampleIndex ) }\n      </div>\n      <div class="airygen-auto-related-posts__footer-grid">\n        ${ previewFooterHtml( sampleIndex ) }\n      </div>\n    </div>\n  </div>\n</article>`
			: `<article class="airygen-auto-related-posts__card airygen-auto-related-posts__card--single_column">\n  <div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--header">\n    ${ previewRegionHtml( 'header', sampleIndex ) }\n  </div>\n  <div class="airygen-auto-related-posts__region airygen-auto-related-posts__region--body">\n    ${ previewRegionHtml( 'body', sampleIndex ) }\n  </div>\n  <div class="airygen-auto-related-posts__footer-grid">\n    ${ previewFooterHtml( sampleIndex ) }\n  </div>\n</article>`;

	const previewSectionTitleHtml =
		settings.titleEnabled && settings.titleText.trim() !== ''
			? `<${ settings.titleLevel } class="airygen-auto-related-posts__section-title">${ escapeHtml( settings.titleText.trim() ) }</${ settings.titleLevel }>\n`
			: '';
	const SectionTitleTag = settings.titleLevel as keyof JSX.IntrinsicElements;

	const previewHtml = `${ previewSectionTitleHtml }<div class="airygen-auto-related-posts__grid" style="${ previewGridStyle }">\n${ Array.from(
		{ length: previewCardCount },
		( _, index ) => previewCardHtml( index + 1 ),
	).join( '\n' ) }\n</div>`;

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<LinkCounterIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Related Posts', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __( 'Based on indexed content and taxonomies, automatically displays a related posts list inside articles.', 'airygen-seo' ) }
					</div>
				</div>
			</div>

			<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
				<button
					type="button"
					data-airygen-e2e="tab-settings"
					className={
						activeTab === 'settings'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'settings' ) }
				>
					{ __( 'Settings', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-layout"
					className={
						activeTab === 'layout'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'layout' ) }
				>
					{ __( 'Layout', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-preview"
					className={
						activeTab === 'preview'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
			</div>

			{ activeTab === 'layout' ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2 capitalize">
								{ __( 'Layout', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'Grid', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Configure related posts display grid layout.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="grid grid-cols-5 gap-3">
							{ DISPLAY_PRESET_OPTIONS.map( ( option ) => {
								const active = settings.displayPreset === option.value;
								return (
									<button
										key={ option.value }
										type="button"
										aria-label={ option.label }
										onClick={ () =>
											updateSettings( {
												displayPreset:
													option.value === '2x2' ||
													option.value === '4x2' ||
													option.value === '4x1' ||
													option.value === '1x4'
														? option.value
														: '3x2',
											} )
										}
										className={ `rounded-lg border p-3 transition ${
											active
												? 'border-sky-500 bg-sky-50'
												: 'border-slate-200 bg-white hover:border-sky-300'
										}` }
									>
										<span className="sr-only">{ option.label }</span>
										<div className="mx-auto aspect-square w-full max-w-[130px] rounded-md border border-dashed border-slate-300 bg-white p-2">
											{ option.value === '2x2' ? (
												<div className="grid h-full grid-cols-2 gap-1">
													{ Array.from( { length: 4 } ).map( ( _, index ) => (
														<span key={ `preset-2x2-${ index }` } className="rounded-sm bg-slate-300" />
													) ) }
												</div>
											) : null }
											{ option.value === '3x2' ? (
												<div className="grid h-full grid-cols-3 gap-1">
													{ Array.from( { length: 6 } ).map( ( _, index ) => (
														<span key={ `preset-3x2-${ index }` } className="rounded-sm bg-slate-300" />
													) ) }
												</div>
											) : null }
											{ option.value === '4x2' ? (
												<div className="grid h-full grid-cols-4 gap-1">
													{ Array.from( { length: 8 } ).map( ( _, index ) => (
														<span key={ `preset-4x2-${ index }` } className="rounded-sm bg-slate-300" />
													) ) }
												</div>
											) : null }
											{ option.value === '4x1' ? (
												<div className="grid h-full grid-cols-4 gap-1">
													{ Array.from( { length: 4 } ).map( ( _, index ) => (
														<span key={ `preset-4x1-${ index }` } className="rounded-sm bg-slate-300" />
													) ) }
												</div>
											) : null }
											{ option.value === '1x4' ? (
												<div className="grid h-full grid-cols-1 gap-1">
													{ Array.from( { length: 4 } ).map( ( _, index ) => (
														<span key={ `preset-1x4-${ index }` } className="rounded-sm bg-slate-300" />
													) ) }
												</div>
											) : null }
										</div>
										<p className="mt-2 text-xs font-medium text-slate-600">{ option.label }</p>
									</button>
								);
							} ) }
						</div>
					</section>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex items-start justify-between gap-3">
							<div className="space-y-1">
								<div className="airygen_h2_title flex items-center gap-2 capitalize">
									{ __( 'Layout', 'airygen-seo' ) }
									<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
										{ __( 'Post Card', 'airygen-seo' ) }
									</span>
								</div>
								<p className="text-sm text-slate-500">
									{ __( 'Choose a post card template and arrange visible blocks.', 'airygen-seo' ) }
								</p>
							</div>
						</div>

						<div className="space-y-3">
							<p className="text-sm font-medium text-slate-800">
								{ __( 'Post card template', 'airygen-seo' ) }
							</p>
							<div className="grid gap-4 md:grid-cols-2">
								{ TEMPLATE_OPTIONS.map( ( option ) => {
									const active = settings.template === option.value;
									return (
										<button
											type="button"
											key={ option.value }
											onClick={ () => {
												const nextTemplate = option.value;
												if ( nextTemplate === 'single_column' ) {
													const nextRegions = { ...settings.blockRegions };
													Object.entries( nextRegions ).forEach( ( [ blockId, region ] ) => {
														if ( region === 'left_sidebar' ) {
															nextRegions[ blockId as RelatedPostsBlockId ] = 'body';
														}
													} );
													updateSettings( { template: nextTemplate, blockRegions: nextRegions } );
													return;
												}
												updateSettings( { template: nextTemplate } );
											} }
											className={ `rounded-lg border p-3 transition ${
												active
													? 'border-sky-500 bg-sky-50'
													: 'border-slate-200 bg-white hover:border-sky-300'
											}` }
											aria-label={ option.label }
										>
											<span className="sr-only">{ option.label }</span>
											<div className="mx-auto aspect-square w-full max-w-[180px] rounded-md border border-dashed border-slate-300 bg-white p-3">
												{ option.value === 'single_column' ? (
													<div className="flex h-full flex-col gap-2">
														<div className="h-[22%] rounded bg-slate-300" />
														<div className="h-[56%] rounded bg-slate-100" />
														<div className="h-[22%] rounded bg-slate-300" />
													</div>
												) : (
													<div className="grid h-full grid-cols-[1fr_3fr] gap-2">
														<div className="rounded bg-slate-200" />
														<div className="flex h-full flex-col gap-2">
															<div className="h-[22%] rounded bg-slate-300" />
															<div className="h-[56%] rounded bg-slate-100" />
															<div className="h-[22%] rounded bg-slate-300" />
														</div>
													</div>
												) }
											</div>
										</button>
									);
								} ) }
							</div>
						</div>

						<div className="space-y-3">
							<div
								className="rounded-lg border border-slate-200 bg-slate-50 p-3"
								onDragOver={ ( event ) => {
									event.preventDefault();
									event.dataTransfer.dropEffect = 'move';
								} }
							>
								{ settings.template === 'sidebar_left' ? (
									<div className="grid gap-3 md:grid-cols-4">
										<div
											className="rounded-lg border border-dashed border-slate-300 bg-white p-3 md:col-span-1"
											onDragOver={ ( event ) => {
												event.preventDefault();
												event.dataTransfer.dropEffect = 'move';
											} }
											onDrop={ ( event ) => handleVisibleDrop( event, 'left_sidebar' ) }
										>
											<p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
												{ regionLabels.left_sidebar }
											</p>
											{ renderRegionCards( 'left_sidebar' ) }
										</div>
										<div className="space-y-3 md:col-span-3">
											<div
												className="rounded-lg border border-dashed border-slate-300 bg-white p-3"
												onDragOver={ ( event ) => {
													event.preventDefault();
													event.dataTransfer.dropEffect = 'move';
												} }
												onDrop={ ( event ) => handleVisibleDrop( event, 'header' ) }
											>
												<p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
													{ regionLabels.header }
												</p>
												{ renderRegionCards( 'header' ) }
											</div>
											<div
												className="rounded-lg border border-dashed border-slate-300 bg-white p-3"
												onDragOver={ ( event ) => {
													event.preventDefault();
													event.dataTransfer.dropEffect = 'move';
												} }
												onDrop={ ( event ) => handleVisibleDrop( event, 'body' ) }
											>
												<p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
													{ regionLabels.body }
												</p>
												{ renderRegionCards( 'body' ) }
											</div>
											<div className="rounded-lg border border-dashed border-slate-300 bg-white p-3">
												<div className="mb-2 flex items-center justify-between gap-2">
													<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
														{ __( 'Footer', 'airygen-seo' ) }
													</p>
													<label className="sr-only" htmlFor="airygen-related-footer-columns-sidebar">
														{ __( 'Footer columns', 'airygen-seo' ) }
													</label>
													<select
														id="airygen-related-footer-columns-sidebar"
														className="airygen-field w-[110px] text-xs"
														value={ String( settings.footerColumns ) }
														onChange={ ( event ) => handleFooterColumnsChange( event.target.value ) }
													>
														<option value="1">{ `1 ${ __( 'column', 'airygen-seo' ) }` }</option>
														<option value="2">{ `2 ${ __( 'columns', 'airygen-seo' ) }` }</option>
														<option value="3">{ `3 ${ __( 'columns', 'airygen-seo' ) }` }</option>
													</select>
												</div>
												<div
													className="grid gap-2"
													style={ {
														gridTemplateColumns: `repeat(${ settings.footerColumns }, minmax(0, 1fr))`,
													} }
												>
													{ footerRegions.map( ( region ) => (
														<div
															key={ `footer-region-${ region }` }
															className="rounded-md border border-dashed border-slate-300 bg-slate-50 p-2"
															onDragOver={ ( event ) => {
																event.preventDefault();
																event.dataTransfer.dropEffect = 'move';
															} }
															onDrop={ ( event ) => handleVisibleDrop( event, region ) }
														>
															<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
																{ regionLabels[ region ] }
															</p>
															{ renderRegionCards( region ) }
														</div>
													) ) }
												</div>
											</div>
										</div>
									</div>
								) : (
									<div className="space-y-3">
										<div
											className="rounded-lg border border-dashed border-slate-300 bg-white p-3"
											onDragOver={ ( event ) => {
												event.preventDefault();
												event.dataTransfer.dropEffect = 'move';
											} }
											onDrop={ ( event ) => handleVisibleDrop( event, 'header' ) }
										>
											<p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
												{ regionLabels.header }
											</p>
											{ renderRegionCards( 'header' ) }
										</div>
										<div
											className="rounded-lg border border-dashed border-slate-300 bg-white p-3"
											onDragOver={ ( event ) => {
												event.preventDefault();
												event.dataTransfer.dropEffect = 'move';
											} }
											onDrop={ ( event ) => handleVisibleDrop( event, 'body' ) }
										>
											<p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
												{ regionLabels.body }
											</p>
											{ renderRegionCards( 'body' ) }
										</div>
										<div className="rounded-lg border border-dashed border-slate-300 bg-white p-3">
											<div className="mb-2 flex items-center justify-between gap-2">
												<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
													{ __( 'Footer', 'airygen-seo' ) }
												</p>
												<label className="sr-only" htmlFor="airygen-related-footer-columns-single">
													{ __( 'Footer columns', 'airygen-seo' ) }
												</label>
												<select
													id="airygen-related-footer-columns-single"
													className="airygen-field w-[110px] text-xs"
													value={ String( settings.footerColumns ) }
													onChange={ ( event ) => handleFooterColumnsChange( event.target.value ) }
												>
													<option value="1">{ `1 ${ __( 'column', 'airygen-seo' ) }` }</option>
													<option value="2">{ `2 ${ __( 'columns', 'airygen-seo' ) }` }</option>
													<option value="3">{ `3 ${ __( 'columns', 'airygen-seo' ) }` }</option>
												</select>
											</div>
											<div
												className="grid gap-2"
												style={ {
													gridTemplateColumns: `repeat(${ settings.footerColumns }, minmax(0, 1fr))`,
												} }
											>
												{ footerRegions.map( ( region ) => (
													<div
														key={ `footer-region-${ region }` }
														className="rounded-md border border-dashed border-slate-300 bg-slate-50 p-2"
														onDragOver={ ( event ) => {
															event.preventDefault();
															event.dataTransfer.dropEffect = 'move';
														} }
														onDrop={ ( event ) => handleVisibleDrop( event, region ) }
													>
														<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
															{ regionLabels[ region ] }
														</p>
														{ renderRegionCards( region ) }
													</div>
												) ) }
											</div>
										</div>
									</div>
								) }
							</div>
						</div>

						<div className="space-y-3">
							<p className="text-sm font-medium text-slate-800">
								{ __( 'Hidden blocks', 'airygen-seo' ) }
							</p>
							<div
								className="rounded-lg border border-dashed border-slate-300 bg-white p-3"
								data-airygen-e2e="hidden-blocks-related-posts"
								onDragOver={ ( event ) => {
									event.preventDefault();
									event.dataTransfer.dropEffect = 'move';
								} }
								onDrop={ handleHiddenDrop }
							>
								<div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
									{ hiddenBlocks.map( ( block ) => (
										<div
											key={ block.id }
											draggable
											onDragStart={ ( event ) => handleDragStart( event, block.id ) }
											onDragEnd={ handleDragEnd }
											className="flex cursor-grab items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm active:cursor-grabbing"
										>
											<div className="min-w-0 flex-1">
												<p className="text-sm font-medium text-slate-900">{ block.label }</p>
												<p className="mt-1 text-xs text-slate-500">{ block.description }</p>
											</div>
											<button
												type="button"
												className="inline-flex items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white whitespace-nowrap align-middle border-slate-400 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-500 focus:ring-sky-500 text-xs"
												onClick={ () => moveBlockToRegion( block.id, 'body' ) }
											>
												{ __( 'Add to display', 'airygen-seo' ) }
											</button>
										</div>
									) ) }
									{ hiddenBlocks.length === 0 ? (
										<p className="text-xs text-slate-500">{ __( 'No hidden blocks.', 'airygen-seo' ) }</p>
									) : null }
								</div>
							</div>
						</div>
					</section>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Themes', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Choose a preset theme as the base style for the related posts grid and post containers.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="grid gap-3 md:grid-cols-4">
							{ styleGridThemes.map( ( option ) => {
								const isActive = isSameStyleGridTheme( option );
								return (
									<div
										key={ option.id }
										role="button"
										tabIndex={ 0 }
										onClick={ () =>
											updateSettings( {
												gridContainer: option.gridContainer,
												headerContainer: option.headerContainer,
												headerTitle: option.headerTitle,
												postContainer: option.postContainer,
											} )
										}
										onKeyDown={ ( event ) => {
											if ( event.key === 'Enter' || event.key === ' ' ) {
												event.preventDefault();
												updateSettings( {
													gridContainer: option.gridContainer,
													headerContainer: option.headerContainer,
													headerTitle: option.headerTitle,
													postContainer: option.postContainer,
												} );
											}
										} }
										className={ [
											'flex flex-col items-start gap-1 rounded-lg border px-4 py-3 text-sm font-medium',
											isActive
												? 'border-slate-900 text-slate-900'
												: 'border-slate-200 text-slate-600 hover:border-slate-400',
										].join( ' ' ) }
									>
										<span className="text-sm font-semibold">{ option.label }</span>
										<span className="text-xs text-slate-500">{ option.description }</span>
										{ isActive ? (
											<span className="text-xs text-slate-500">
												{ __( 'Selected', 'airygen-seo' ) }
											</span>
										) : null }
									</div>
								);
							} ) }
						</div>
					</section>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2 capitalize">
								{ __( 'Style', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'Grid', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Configure the outer grid and each post container before styling individual post card blocks.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="space-y-3">
							<SectionHeaderStyleCards
								container={ relatedHeaderContainer }
								title={ relatedHeaderTitle }
								onContainerChange={ updateHeaderContainer }
								onTitleChange={ updateHeaderTitle }
								idPrefix="airygen-related-header"
								containerMaxBorderWidth={ 20 }
								titleMinFontSize={ 10 }
								titleMaxFontSize={ 40 }
							/>
							<SectionBodyContainerStyleCard
								borderWidths={ settings.gridContainer.borderWidths }
								onBorderWidthsChange={ ( values ) => updateGridContainer( { borderWidths: values } ) }
								borderRadius={ settings.gridContainer.borderRadius }
								onBorderRadiusChange={ ( value ) => updateGridContainer( { borderRadius: value } ) }
								borderStyle={ settings.gridContainer.borderStyle }
								onBorderStyleChange={ ( value ) =>
									updateGridContainer( {
										borderStyle: value as RelatedPostsSettings['gridContainer']['borderStyle'],
									} )
								}
								borderColor={ settings.gridContainer.borderColor }
								onBorderColorChange={ ( value ) => updateGridContainer( { borderColor: value } ) }
								paddings={ settings.gridContainer.paddings }
								onPaddingsChange={ ( values ) => updateGridContainer( { paddings: values } ) }
								bgColor={ settings.gridContainer.bgColor }
								onBgColorChange={ ( value ) => updateGridContainer( { bgColor: value } ) }
								gap={ settings.gridContainer.gap }
								onGapChange={ ( value ) => updateGridContainer( { gap: value } ) }
								idPrefix="airygen-related-grid"
								maxBorderWidth={ 20 }
								maxSpacing={ 50 }
								maxRadius={ 50 }
								maxGap={ 50 }
								showMargins={ false }
							/>
							<SectionBodyContainerStyleCard
								title={ __( 'Post container', 'airygen-seo' ) }
								borderWidths={ settings.postContainer.borderWidths }
								onBorderWidthsChange={ ( values ) => updatePostContainer( { borderWidths: values } ) }
								borderRadius={ settings.postContainer.borderRadius }
								onBorderRadiusChange={ ( value ) => updatePostContainer( { borderRadius: value } ) }
								borderStyle={ settings.postContainer.borderStyle }
								onBorderStyleChange={ ( value ) =>
									updatePostContainer( {
										borderStyle: value as RelatedPostsSettings['postContainer']['borderStyle'],
									} )
								}
								borderColor={ settings.postContainer.borderColor }
								onBorderColorChange={ ( value ) => updatePostContainer( { borderColor: value } ) }
								paddings={ settings.postContainer.paddings }
								onPaddingsChange={ ( values ) => updatePostContainer( { paddings: values } ) }
								bgColor={ settings.postContainer.bgColor }
								onBgColorChange={ ( value ) => updatePostContainer( { bgColor: value } ) }
								gap={ settings.postContainer.gap }
								onGapChange={ ( value ) => updatePostContainer( { gap: value } ) }
								idPrefix="airygen-related-post"
								maxBorderWidth={ 20 }
								maxSpacing={ 50 }
								maxRadius={ 50 }
								maxGap={ 50 }
								showMargins={ false }
							/>
						</div>
					</section>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2 capitalize">
								{ __( 'Style', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'Post Card', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Configure style settings for each block inside the Post Card layout.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="space-y-3">
							<div className="space-y-3 rounded-lg border border-slate-200 p-4">
								<h3 className="text-sm font-semibold text-slate-900">{ __( 'Featured image', 'airygen-seo' ) }</h3>
								<div className="grid gap-3 md:grid-cols-4">
									<div className="rounded-lg border border-slate-200 p-3">
										<Select
											label={ __( 'Image size', 'airygen-seo' ) }
											value={ settings.featuredImageSize }
											options={ imageSizeOptions }
											onChange={ ( value ) => updateSettings( { featuredImageSize: value } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Choose which registered WordPress image size to output in each card.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<Input
											label={ __( 'Image border radius', 'airygen-seo' ) + ' (px)' }
											type="number"
											min={ 0 }
											max={ 64 }
											value={ String( settings.featuredImageRadius ) }
											onChange={ ( value ) =>
												updateSettings( {
													featuredImageRadius: Math.max( 0, Math.min( 64, Number( value ) || 0 ) ),
												} )
											}
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Rounds the four image corners in each Related Posts card.', 'airygen-seo' ) }
										</p>
									</div>
								</div>
							</div>
							<div className="space-y-3 rounded-lg border border-slate-200 p-4">
								<h3 className="text-sm font-semibold text-slate-900">{ __( 'Title', 'airygen-seo' ) }</h3>
								<div className="grid gap-3 md:grid-cols-4">
									<div className="rounded-lg border border-slate-200 p-3">
										<Input
											label={ __( 'Font size', 'airygen-seo' ) + ' (px)' }
											type="number"
											min={ 10 }
											max={ 64 }
											value={ String( settings.titleFontSize ) }
											onChange={ ( value ) => updateSettings( { titleFontSize: Number( value ) || 18 } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Sets the title text size in each Related Posts card.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<RelatedPostsColorPicker
											label={ __( 'Font color', 'airygen-seo' ) }
											value={ settings.titleColor }
											onChange={ ( value ) => updateSettings( { titleColor: value } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Sets the title text color in each Related Posts card.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<p className="text-sm font-medium text-slate-900">{ __( 'Font style', 'airygen-seo' ) }</p>
										<div className="mt-3 grid grid-cols-3 gap-2">
											<Checkbox
												label={ __( 'Bold', 'airygen-seo' ) }
												checked={ settings.titleBold }
												onChange={ ( value ) => updateSettings( { titleBold: value } ) }
											/>
											<Checkbox
												label={ __( 'Italic', 'airygen-seo' ) }
												checked={ settings.titleItalic }
												onChange={ ( value ) => updateSettings( { titleItalic: value } ) }
											/>
										</div>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Choose whether post titles are bold or italic in each card.', 'airygen-seo' ) }
										</p>
									</div>
								</div>
							</div>
							<div className="space-y-3 rounded-lg border border-slate-200 p-4">
								<h3 className="text-sm font-semibold text-slate-900">{ __( 'Excerpt', 'airygen-seo' ) }</h3>
								<div className="grid gap-3 md:grid-cols-4">
									<div className="rounded-lg border border-slate-200 p-3">
										<Input
											label={ __( 'Font size', 'airygen-seo' ) + ' (px)' }
											type="number"
											min={ 10 }
											max={ 48 }
											value={ String( settings.excerptFontSize ) }
											onChange={ ( value ) => updateSettings( { excerptFontSize: Number( value ) || 14 } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Sets the text size for excerpt content in each related post card.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<RelatedPostsColorPicker
											label={ __( 'Font color', 'airygen-seo' ) }
											value={ settings.excerptColor }
											onChange={ ( value ) => updateSettings( { excerptColor: value } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Sets the text color for excerpt content.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<Input
											label={ __( 'Max chars', 'airygen-seo' ) }
											type="number"
											min={ 30 }
											max={ 1000 }
											value={ String( settings.excerptMaxChars ) }
											onChange={ ( value ) => updateSettings( { excerptMaxChars: Number( value ) || 140 } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Limits how many characters are shown before excerpt text is truncated.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-3">
										<div className="flex items-center justify-between gap-3">
											<p className="text-sm font-medium text-slate-900">{ __( 'Fade mask', 'airygen-seo' ) }</p>
											<Toggle
												label={ __( 'Fade mask', 'airygen-seo' ) }
												hideLabelText
												checked={ settings.excerptFadeMask }
												onChange={ ( value ) => updateSettings( { excerptFadeMask: value } ) }
											/>
										</div>
										<p className="text-xs text-slate-500">
											{ __( 'Adds a fade effect at the end of excerpt text to make truncated content look smoother.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<RelatedPostsColorPicker
											label={ __( 'Fade color', 'airygen-seo' ) }
											value={ settings.excerptFadeColor }
											onChange={ ( value ) => updateSettings( { excerptFadeColor: value } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Choose the ending color used by the fade mask effect.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<Input
											label={ __( 'Mask height', 'airygen-seo' ) + ' (px)' }
											type="number"
											min={ 8 }
											max={ 200 }
											value={ String( settings.excerptMaskHeight ) }
											onChange={ ( value ) =>
												updateSettings( {
													excerptMaskHeight: Math.max( 8, Math.min( 200, Number( value ) || 40 ) ),
												} )
											}
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Controls how tall the fade mask overlay appears at the bottom of excerpt text.', 'airygen-seo' ) }
										</p>
									</div>
								</div>
							</div>
							<div className="space-y-3 rounded-lg border border-slate-200 p-4">
								<h3 className="text-sm font-semibold text-slate-900">{ __( 'Author & Date', 'airygen-seo' ) }</h3>
								<div className="grid gap-3 md:grid-cols-4">
									<div className="rounded-lg border border-slate-200 p-3">
										<Input
											label={ __( 'Font size', 'airygen-seo' ) + ' (px)' }
											type="number"
											min={ 10 }
											max={ 48 }
											value={ String( settings.authorFontSize ) }
											onChange={ ( value ) => updateSettings( { authorFontSize: Number( value ) || 13 } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Sets the text size for both author and date blocks.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<RelatedPostsColorPicker
											label={ __( 'Font color', 'airygen-seo' ) }
											value={ settings.authorColor }
											onChange={ ( value ) => updateSettings( { authorColor: value } ) }
										/>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Sets the text color for both author and date blocks.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<p className="text-sm font-medium text-slate-900">{ __( 'Font style', 'airygen-seo' ) }</p>
										<div className="mt-3 grid grid-cols-3 gap-2">
											<Checkbox
												label={ __( 'Bold', 'airygen-seo' ) }
												checked={ settings.authorBold }
												onChange={ ( value ) => updateSettings( { authorBold: value } ) }
											/>
											<Checkbox
												label={ __( 'Italic', 'airygen-seo' ) }
												checked={ settings.authorItalic }
												onChange={ ( value ) => updateSettings( { authorItalic: value } ) }
											/>
										</div>
										<p className="mt-2 text-xs text-slate-500">
											{ __( 'Choose whether author and date text are bold or italic.', 'airygen-seo' ) }
										</p>
									</div>
								</div>
							</div>
						</div>
					</section>
				</>
			) : null }

			{ activeTab === 'settings' ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title capitalize">
								{ __( 'Settings', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Configure enable state and integration methods for related posts output.', 'airygen-seo' ) }
							</p>
						</div>

						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ manualRelatedPostsLabel }
								</p>
								<Toggle
									label={ manualRelatedPostsLabel }
									hideLabelText
									checked={ settings.enabled }
									onChange={ ( value ) => updateSettings( { enabled: value } ) }
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Requires Link Suggestions and outputs related post cards via shortcode or automatic insertion.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-3">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
									<label className="text-sm font-semibold text-gray-800" htmlFor={ templateTagId }>
										{ __( 'Template function', 'airygen-seo' ) }
									</label>
									<textarea
										id={ templateTagId }
										value={ templateTagSnippet }
										readOnly
										rows={ 3 }
										className="mt-2 airygen-field w-full font-mono text-xs"
									/>
									<Button
										variant="secondary"
										className="mt-3 text-xs"
										onClick={ () =>
											onCopyToClipboard(
												templateTagSnippet,
												getSnippetCopiedLabel( getTemplateFunctionLabel() ),
												getUnableToCopySnippetLabel(),
											)
										}
									>
										{ __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
									<label className="text-sm font-semibold text-gray-800" htmlFor={ shortcodeId }>
										{ __( 'Shortcode', 'airygen-seo' ) }
									</label>
									<textarea
										id={ shortcodeId }
										value={ shortcodeSnippet }
										readOnly
										rows={ 3 }
										className="mt-2 airygen-field w-full font-mono text-xs"
									/>
									<Button
										variant="secondary"
										className="mt-3 text-xs"
										onClick={ () =>
											onCopyToClipboard(
												shortcodeSnippet,
												getSnippetCopiedLabel( getShortcodeLabel() ),
												getUnableToCopySnippetLabel(),
											)
										}
									>
										{ __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
									<label className="text-sm font-semibold text-gray-800" htmlFor={ blockSnippetId }>
										{ __( 'Block', 'airygen-seo' ) }
									</label>
									<textarea
										id={ blockSnippetId }
										value={ blockSnippet }
										readOnly
										rows={ 3 }
										className="mt-2 airygen-field w-full font-mono text-xs"
									/>
									<Button
										variant="secondary"
										className="mt-3 text-xs"
										onClick={ () =>
											onCopyToClipboard(
												blockSnippet,
												getSnippetCopiedLabel( getBlockSnippetLabel() ),
												getUnableToCopySnippetLabel(),
											)
										}
									>
										{ __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ automaticRelatedPostsLabel }
								</p>
								<Toggle
									label={ automaticRelatedPostsLabel }
									hideLabelText
									checked={ settings.autoInjectEnabled }
									onChange={ ( value ) => updateSettings( { autoInjectEnabled: value } ) }
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Automatically inject related posts into content based on the insertion settings below.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-3">
								<div className="rounded-lg border border-slate-200 p-3">
									<Select
										label={ __( 'Insert position', 'airygen-seo' ) }
										value={ settings.insertPosition }
										options={ [
											{ value: 'before_content', label: __( 'Before content', 'airygen-seo' ) },
											{ value: 'after_content', label: __( 'After content', 'airygen-seo' ) },
										] }
										onChange={ ( value ) =>
											updateSettings( {
												insertPosition: value === 'before_content' ? 'before_content' : 'after_content',
											} )
										}
									/>
									<p className="mt-2 text-xs text-slate-500">
										{ __( 'Choose whether automatic related posts appear before or after the article content.', 'airygen-seo' ) }
									</p>
								</div>
							</div>
						</div>
						<SectionHeaderSettingsCard
							moduleLabel={ relatedPostsModuleLabel }
							enabled={ settings.titleEnabled }
							onEnabledChange={ ( value ) => updateSettings( { titleEnabled: value } ) }
							text={ settings.titleText }
							onTextChange={ ( value ) => updateSettings( { titleText: value } ) }
							level={ settings.titleLevel }
							onLevelChange={ ( value ) => updateSettings( { titleLevel: value } ) }
						/>
					</section>

					<section className="rounded-xl border border-slate-200 bg-white p-4">
						<div className="airygen_h2_title">
							{ __( 'Scope', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __( 'Post type scope is synced from Link Suggestions and cannot be edited here.', 'airygen-seo' ) }
						</p>
						<div className="mt-4 space-y-3">
							<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
								{ __( 'Post types to include', 'airygen-seo' ) }
							</p>
							<div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
								{ ( meta.postTypes ?? [] ).map( ( postType ) => (
									<div
										key={ postType.slug }
										className="rounded-lg border border-slate-200 p-3"
									>
										<Checkbox
											label={ postType.label }
											checked={ settings.enabledPostTypes.includes( postType.slug ) }
											onChange={ () => undefined }
											disabled
										/>
									</div>
								) ) }
							</div>
						</div>
					</section>
				</>
			) : null }

			{ activeTab === 'preview' ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="flex items-center justify-between gap-3">
							<div className="airygen_h2_title">
								{ __( 'Preview', 'airygen-seo' ) }
							</div>
							<PreviewDeviceSwitcher
								options={ [
									{ key: 'laptop', label: __( 'Laptop', 'airygen-seo' ), Icon: PreviewLaptopIcon },
									{ key: 'tablet', label: __( 'Tablet', 'airygen-seo' ), Icon: PreviewTabletIcon },
									{ key: 'cellphone', label: __( 'Cellphone', 'airygen-seo' ), Icon: PreviewCellphoneIcon },
								] }
								value={ previewViewport }
								onChange={ ( next ) => setPreviewViewport( next as RelatedPostsPreviewViewport ) }
							/>
						</div>
						<p className="text-sm text-slate-500">
							{ __( 'Preview related posts output based on current layout and style settings.', 'airygen-seo' ) }
						</p>
					</div>
					<div className="overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
						<div data-airygen-e2e="preview-stage-related-posts">
							<style>{ previewCss }</style>
							<PreviewDeviceFrame device={ previewViewport }>
								<div
									className={ `airygen-auto-related-posts ${ previewViewportClass }` }
									style={ { width: '100%', margin: '0 auto' } }
								>
									{ settings.titleEnabled && settings.titleText.trim() !== '' ? (
										<SectionTitleTag className="airygen-auto-related-posts__section-title">
											{ settings.titleText.trim() }
										</SectionTitleTag>
									) : null }
									<div
										className="airygen-auto-related-posts__grid"
										style={ { gridTemplateColumns: previewGridTemplateColumns } }
									>
										{ Array.from( { length: previewCardCount } ).map( ( _, index ) => {
											const sampleIndex = index + 1;
											return settings.template === 'sidebar_left' ? (
												<div
													key={ `preview-card-sidebar-${ sampleIndex }` }
													className="airygen-auto-related-posts__card airygen-auto-related-posts__card--sidebar_left"
												>
													<div className="airygen-auto-related-posts__layout">
														<div className="airygen-auto-related-posts__region airygen-auto-related-posts__region--left-sidebar">
															{ visibleBlocksByRegion.left_sidebar.map( ( blockId ) => (
																<div
																	key={ `preview-left-${ sampleIndex }-${ blockId }` }
																	dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
																/>
															) ) }
														</div>
														<div className="airygen-auto-related-posts__main">
															<div className="airygen-auto-related-posts__region airygen-auto-related-posts__region--header">
																{ visibleBlocksByRegion.header.map( ( blockId ) => (
																	<div
																		key={ `preview-header-${ sampleIndex }-${ blockId }` }
																		dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
																	/>
																) ) }
															</div>
															<div className="airygen-auto-related-posts__region airygen-auto-related-posts__region--body">
																{ visibleBlocksByRegion.body.map( ( blockId ) => (
																	<div
																		key={ `preview-body-${ sampleIndex }-${ blockId }` }
																		dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
																	/>
																) ) }
															</div>
															<div className="airygen-auto-related-posts__footer-grid">
																{ footerRegions.map( ( region ) => (
																	<div
																		key={ `preview-footer-${ sampleIndex }-${ region }` }
																		className={ `airygen-auto-related-posts__region airygen-auto-related-posts__region--${ region.replace( '_', '-' ) }` }
																	>
																		{ visibleBlocksByRegion[ region ].map( ( blockId ) => (
																			<div
																				key={ `preview-footer-${ region }-${ sampleIndex }-${ blockId }` }
																				dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
																			/>
																		) ) }
																	</div>
																) ) }
															</div>
														</div>
													</div>
												</div>
											) : (
												<div
													key={ `preview-card-single-${ sampleIndex }` }
													className="airygen-auto-related-posts__card airygen-auto-related-posts__card--single_column"
												>
													<div className="airygen-auto-related-posts__region airygen-auto-related-posts__region--header">
														{ visibleBlocksByRegion.header.map( ( blockId ) => (
															<div
																key={ `preview-single-header-${ sampleIndex }-${ blockId }` }
																dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
															/>
														) ) }
													</div>
													<div className="airygen-auto-related-posts__region airygen-auto-related-posts__region--body">
														{ visibleBlocksByRegion.body.map( ( blockId ) => (
															<div
																key={ `preview-single-body-${ sampleIndex }-${ blockId }` }
																dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
															/>
														) ) }
													</div>
													<div className="airygen-auto-related-posts__footer-grid">
														{ footerRegions.map( ( region ) => (
															<div
																key={ `preview-single-footer-${ sampleIndex }-${ region }` }
																className={ `airygen-auto-related-posts__region airygen-auto-related-posts__region--${ region.replace( '_', '-' ) }` }
															>
																{ visibleBlocksByRegion[ region ].map( ( blockId ) => (
																	<div
																		key={ `preview-single-footer-${ region }-${ sampleIndex }-${ blockId }` }
																		dangerouslySetInnerHTML={ { __html: previewBlockMarkup( blockId, sampleIndex ) } }
																	/>
																) ) }
															</div>
														) ) }
													</div>
												</div>
											);
										} ) }
									</div>
								</div>
							</PreviewDeviceFrame>
						</div>
					</div>
					<PreviewCodeSamples
						injectedCss={ previewCss }
						htmlSample={ previewHtml }
						injectedCssId={ injectedCssId }
						htmlSampleId={ previewHtmlId }
					/>
				</section>
			) : null }
		</div>
	);
};

export default RelatedPostsTab;

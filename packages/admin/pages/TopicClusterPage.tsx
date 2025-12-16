import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	Fragment,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import Toggle from '../components/Toggle';
import Checkbox from '../components/Checkbox';
import Button from '../components/Button';
import Modal from '../components/Modal';
import { TopicClusterIcon } from '../components/Icons';
import PreviewDeviceSwitcher from '../components/PreviewDeviceSwitcher';
import PreviewDeviceFrame, {
	type PreviewDeviceKind,
} from '../components/PreviewDeviceFrame';
import PreviewCodeSamples from '../components/PreviewCodeSamples';
import SectionHeaderSettingsCard from '../components/SectionHeaderSettingsCard';
import SectionHeaderStyleCards from '../components/SectionHeaderStyleCards';
import SectionBodyContainerStyleCard from '../components/SectionBodyContainerStyleCard';
import LinkStyleCard from '../components/LinkStyleCard';
import ListStyleCard from '../components/ListStyleCard';
import MindMap from './topicCluster/MindMap';
import {
	getAllChangesSavedLabel,
	getAutomaticInjectionLabel,
	getBlockSnippetLabel,
	getManualInjectionLabel,
	getResetApplySaveLabel,
	getSaveChangesLabel,
	getSnippetCopiedLabel,
	getShortcodeLabel,
	getTemplateFunctionLabel,
	getUnableToCopySnippetLabel,
	getUnsavedChangesLabel,
} from '../utils/i18n';
import {
	getLoadingItemLabel,
	getNoItemsYetLabel,
} from '../../shared/i18nPhrases';
import type { MetaPayload, NoticeState } from '../types/api';
import type { TopicClusterSettings } from '../types/settings';

type TopicClusterPageProps = {
	settings: TopicClusterSettings;
	meta: MetaPayload;
	isDirty: boolean;
	isSaving: boolean;
	onSave: () => void;
	onChange: ( next: TopicClusterSettings ) => void;
	onNotice: ( notice: NoticeState ) => void;
	restBase: string;
};

type TopicClusterGroup = {
	group_id: number;
	group_name?: string;
	description?: string;
	l1_count?: number;
	pillar_title?: string;
	pillar_edit?: string;
	l2_count: number;
	l3_count: number;
	candidates_count?: number;
	total_members: number;
	updated_at: string;
};

type TopicClusterGroupsResponse = {
	groups?: TopicClusterGroup[];
	pagination?: {
		page?: number;
		totalPages?: number;
	};
};

type TopicClusterCandidate = {
	id: number;
	post_id: number;
	title: string;
};

type TopicClusterCandidateResponse = {
	candidates?: TopicClusterCandidate[];
};

type TopicClusterCandidateSearchItem = {
	post_id: number;
	title: string;
};

type TopicClusterCandidateSearchResponse = {
	items?: TopicClusterCandidateSearchItem[];
};

type TopicClusterPreviewItem = {
	id: string;
	title: string;
};

type TopicClusterPreviewSample = {
	level: 'L1' | 'L2' | 'L3';
	title: string;
	currentId: string;
	l1: TopicClusterPreviewItem;
	l2: TopicClusterPreviewItem[];
	l3: Record<string, TopicClusterPreviewItem[]>;
};

const TOPIC_CLUSTER_SETTINGS_DEFAULTS: TopicClusterSettings = {
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
		borderStyle: 'dashed',
		borderColor: '#dddddd',
		borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
		borderRadius: 6,
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

const escapePreviewHtml = ( value: string ): string =>
	value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );

const applyRelationPreviewTemplate = ( template: string, linkHtml?: string ): string => {
	const escapedTemplate = escapePreviewHtml( template );
	if ( ! linkHtml || ! escapedTemplate.includes( '%s' ) ) {
		return escapedTemplate;
	}

	return escapedTemplate.replace( /%s/g, linkHtml );
};

const countRelationLinkTokens = ( value: string ): number => ( value.match( /%s/g ) ?? [] ).length;

const hasValidRelationTemplate = ( value: string, requiredTokens: number ): boolean =>
	countRelationLinkTokens( value.trim() ) === requiredTokens;

const buildRelationLinkTokenLabel = (): string =>
	sprintf(
		/* translators: %s is the required link placeholder token. */
		__( '%s is the link placeholder and must appear exactly once.', 'airygen-seo' ),
		'%s',
	);

const buildTopicClusterPreviewHtml = (
	sample: TopicClusterPreviewSample,
	titleEnabled: boolean,
	titleLevel: 'h2' | 'h3' | 'h4',
	titleText: string,
	relationTextL1: string,
	relationTextL2: string,
	relationTextL3: string,
	itemListStyle: TopicClusterSettings['style']['itemListStyle'],
): string => {
	const renderPostItem = ( item: TopicClusterPreviewItem, currentId: string ): string => {
		if ( item.id === currentId ) {
			return `<div class="airygen-topic-cluster__item airygen-topic-cluster__item--${ itemListStyle }">
	<span class="airygen-topic-cluster__item-marker" aria-hidden="true"></span>
	<span class="airygen-topic-cluster__link" aria-current="page">${ item.title }</span>
</div>`;
		}

		return `<div class="airygen-topic-cluster__item airygen-topic-cluster__item--${ itemListStyle }">
	<span class="airygen-topic-cluster__item-marker" aria-hidden="true"></span>
	<a class="airygen-topic-cluster__link" href="#">${ item.title }</a>
</div>`;
	};

	const renderParentItem = ( item: TopicClusterPreviewItem, currentId: string ): string => {
		if ( item.id === currentId ) {
			return `<div class="airygen-topic-cluster__item airygen-topic-cluster__item--parent">
	<span class="airygen-topic-cluster__parent-label">${ __( 'Continue with the main article', 'airygen-seo' ) }</span>
	<span class="airygen-topic-cluster__link" aria-current="page">${ item.title }</span>
</div>`;
		}

		return `<div class="airygen-topic-cluster__item airygen-topic-cluster__item--parent">
	<span class="airygen-topic-cluster__parent-label">${ __( 'Continue with the main article', 'airygen-seo' ) }</span>
	<a class="airygen-topic-cluster__link" href="#">${ item.title }</a>
</div>`;
	};

	const titleHtml = titleEnabled
		? `<div class="airygen-topic-cluster__header"><${ titleLevel } class="airygen-topic-cluster__title">${ titleText }</${ titleLevel }></div>`
		: '';

	let intro = '';
	let items: TopicClusterPreviewItem[] = [];

	if ( sample.level === 'L1' ) {
		intro = applyRelationPreviewTemplate( relationTextL1 );
		items = sample.l2;
	} else if ( sample.level === 'L2' ) {
		intro = applyRelationPreviewTemplate(
			relationTextL2,
			`<a class="airygen-topic-cluster__link" href="#">${ escapePreviewHtml( sample.l1.title ) }</a>`,
		);
		items = sample.l3[ sample.currentId ] ?? [];
	} else {
		const parent = sample.l2[ 0 ];
		intro = applyRelationPreviewTemplate(
			relationTextL3,
			parent
				? `<a class="airygen-topic-cluster__link" href="#">${ escapePreviewHtml( parent.title ) }</a>`
				: undefined,
		);
		items = parent ? [ parent ] : [];
	}

	return `${ titleHtml }
	<nav class="airygen-topic-cluster" aria-label="Topic cluster">
		<p class="airygen-topic-cluster__intro">${ intro }</p>
		<div class="airygen-topic-cluster__links airygen-topic-cluster__links--${ itemListStyle }">
			${ items
		.map( ( item ) =>
			sample.level === 'L3'
				? renderParentItem( item, sample.currentId )
				: renderPostItem( item, sample.currentId ),
		)
		.join( '\n\t\t' ) }
		</div>
	</nav>`;
};

const PreviewLaptopIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg width="7" height="7" viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" className={ className } aria-hidden="true">
		<path d="M1.11068 1.66569H5.55341V4.4424H1.11068V1.66569ZM5.55341 4.99774C5.7007 4.99774 5.84195 4.93923 5.9461 4.83508C6.05025 4.73094 6.10875 4.58968 6.10875 4.4424V1.66569C6.10875 1.35748 5.85885 1.11035 5.55341 1.11035H1.11068C0.802468 1.11035 0.555341 1.35748 0.555341 1.66569V4.4424C0.555341 4.58968 0.61385 4.73094 0.717997 4.83508C0.822144 4.93923 0.963397 4.99774 1.11068 4.99774H0V5.55308H6.6641V4.99774H5.55341Z" fill="currentColor" />
	</svg>
);

const PreviewTabletIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg width="7" height="7" viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" className={ className } aria-hidden="true">
		<path d="M5.27566 4.99774H1.38827V1.66569H5.27566V4.99774ZM5.831 1.11035H0.832929C0.524715 1.11035 0.277588 1.35748 0.277588 1.66569V4.99774C0.277588 5.14503 0.336097 5.28628 0.440244 5.39043C0.54439 5.49457 0.685644 5.55308 0.832929 5.55308H5.831C5.97829 5.55308 6.11954 5.49457 6.22369 5.39043C6.32783 5.28628 6.38634 5.14503 6.38634 4.99774V1.66569C6.38634 1.35748 6.13644 1.11035 5.831 1.11035Z" fill="currentColor" />
	</svg>
);

const PreviewCellphoneIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg width="7" height="7" viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" className={ className } aria-hidden="true">
		<path d="M4.72048 5.27542H1.94377V1.38803H4.72048V5.27542ZM4.72048 0.277344H1.94377C1.63555 0.277344 1.38843 0.524471 1.38843 0.832685V5.83076C1.38843 5.97804 1.44694 6.1193 1.55108 6.22344C1.65523 6.32759 1.79648 6.3861 1.94377 6.3861H4.72048C4.86776 6.3861 5.00901 6.32759 5.11316 6.22344C5.21731 6.1193 5.27582 5.97804 5.27582 5.83076V0.832685C5.27582 0.524471 5.02591 0.277344 4.72048 0.277344Z" fill="currentColor" />
	</svg>
);

const TopicClusterPage = ( {
	settings,
	meta,
	isDirty,
	isSaving,
	onSave,
	onChange,
	onNotice,
	restBase,
}: TopicClusterPageProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'layout' | 'groups' | 'mindmap' | 'preview'>( 'settings' );
	const [ isResetModalOpen, setIsResetModalOpen ] = useState( false );
	const [ previewViewport, setPreviewViewport ] = useState<PreviewDeviceKind>( 'laptop' );
	const [ groups, setGroups ] = useState<TopicClusterGroup[]>( [] );
	const [ groupsLoading, setGroupsLoading ] = useState( false );
	const [ groupPage, setGroupPage ] = useState( 1 );
	const [ groupTotalPages, setGroupTotalPages ] = useState( 1 );
	const [ activeMindmapGroupId, setActiveMindmapGroupId ] = useState<number | null>( null );
	const [ mindMapDirty, setMindMapDirty ] = useState( false );
	const [ mindMapSaving, setMindMapSaving ] = useState( false );
	const [ mindMapSaveSignal, setMindMapSaveSignal ] = useState( 0 );
	const [ mindMapResetSignal, setMindMapResetSignal ] = useState( 0 );
	const [ isCreateGroupModalOpen, setIsCreateGroupModalOpen ] = useState( false );
	const [ isCreatingGroup, setIsCreatingGroup ] = useState( false );
	const [ groupName, setGroupName ] = useState( '' );
	const [ groupDescription, setGroupDescription ] = useState( '' );
	const [ isEditGroupModalOpen, setIsEditGroupModalOpen ] = useState( false );
	const [ isUpdatingGroup, setIsUpdatingGroup ] = useState( false );
	const [ removingGroupId, setRemovingGroupId ] = useState( 0 );
	const [ editingGroupId, setEditingGroupId ] = useState( 0 );
	const [ editingGroupName, setEditingGroupName ] = useState( '' );
	const [ editingGroupDescription, setEditingGroupDescription ] = useState( '' );
	const [ expandedGroupId, setExpandedGroupId ] = useState<number | null>( null );
	const [ candidatesByGroup, setCandidatesByGroup ] = useState<Record<number, TopicClusterCandidate[]>>(
		{},
	);
	const [ candidatesLoadingGroupId, setCandidatesLoadingGroupId ] = useState( 0 );
	const [ candidateSearchTermByGroup, setCandidateSearchTermByGroup ] = useState<Record<number, string>>(
		{},
	);
	const [ candidateSearchResultsByGroup, setCandidateSearchResultsByGroup ] = useState<
		Record<number, TopicClusterCandidateSearchItem[]>
	>( {} );
	const [ candidateSearchLoadingGroupId, setCandidateSearchLoadingGroupId ] = useState( 0 );
	const [ addingCandidateKey, setAddingCandidateKey ] = useState( '' );
	const [ removingCandidateId, setRemovingCandidateId ] = useState( 0 );
	const groupsPerPage = 20;
	const groupsTabActive = activeTab === 'groups';
	const mindMapTabActive = activeTab === 'mindmap';
	const relationLinkTokenLabel = buildRelationLinkTokenLabel();
	const relationLinkTokenForbiddenLabel = sprintf(
		/* translators: %s is the link placeholder token. */
		__( 'Shown above the main series links. Do not include %s in this description.', 'airygen-seo' ),
		'%s',
	);
	const groupsPath = `${ restBase }/topic-cluster/groups`;
	const templateTagSnippet =
		"<?php if ( function_exists( 'airygen_the_topic_cluster' ) ) { airygen_the_topic_cluster(); } ?>";
	const shortcodeSnippet = '[airygen_topic_cluster]';
	const blockSnippet = '<!-- wp:airygen/topic-cluster /-->';
	const topicClusterModuleLabel = __( 'Topic Cluster', 'airygen-seo' );
	const manualTopicClusterLabel = getManualInjectionLabel( topicClusterModuleLabel );
	const automaticTopicClusterLabel = getAutomaticInjectionLabel( topicClusterModuleLabel );
	const updateStyle = ( patch: Partial<TopicClusterSettings['style']> ) => {
		onChange( {
			...settings,
			style: {
				...settings.style,
				...patch,
			},
		} );
	};
	const topicHeaderContainer = useMemo(
		() =>
			settings.style.headerContainer ?? {
				borderWidths: { top: 0, right: 0, bottom: 0, left: 0 },
				borderRadius: 0,
				borderStyle: 'solid' as const,
				borderColor: '#cbd5e1',
				paddings: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: 'transparent',
				margins: { top: 0, right: 0, bottom: 12, left: 0 },
			},
		[ settings.style.headerContainer ],
	);
	const topicHeaderTitle = useMemo(
		() =>
			settings.style.headerTitle ?? {
				fontStyle: { bold: true, italic: false, underline: false },
				color: '#0f172a',
				fontSize: 18,
			},
		[ settings.style.headerTitle ],
	);
	const updateHeaderContainer = (
		patch: Partial<NonNullable<TopicClusterSettings['style']['headerContainer']>>,
	) =>
		updateStyle( {
			headerContainer: {
				...topicHeaderContainer,
				...patch,
			},
		} );
	const updateHeaderTitle = (
		patch: Partial<NonNullable<TopicClusterSettings['style']['headerTitle']>>,
	) =>
		updateStyle( {
			headerTitle: {
				...topicHeaderTitle,
				...patch,
			},
		} );
	const isSameStyle = (
		candidate: TopicClusterSettings['style'],
		current: TopicClusterSettings['style'],
	) =>
		candidate.showBorder === current.showBorder &&
		candidate.borderStyle === current.borderStyle &&
		candidate.borderColor === current.borderColor &&
		JSON.stringify( candidate.borderWidths ) === JSON.stringify( current.borderWidths ) &&
		candidate.borderRadius === current.borderRadius &&
		JSON.stringify( candidate.paddings ) === JSON.stringify( current.paddings ) &&
		JSON.stringify( candidate.margins ) === JSON.stringify( current.margins ) &&
		candidate.bgColor === current.bgColor &&
		candidate.itemTextColor === current.itemTextColor &&
		candidate.itemFontSize === current.itemFontSize &&
		candidate.itemBold === current.itemBold &&
		candidate.itemItalic === current.itemItalic &&
		candidate.itemUnderline === current.itemUnderline &&
		candidate.itemListStyle === current.itemListStyle &&
		candidate.itemGap === current.itemGap &&
		JSON.stringify( candidate.headerContainer ?? {} ) === JSON.stringify( current.headerContainer ?? {} ) &&
		JSON.stringify( candidate.headerTitle ?? {} ) === JSON.stringify( current.headerTitle ?? {} );
	const topicClusterThemes = [
		{
			key: 'snow-slate',
			label: __( 'Snow Slate', 'airygen-seo' ),
			description: __( 'Neutral white + light gray with crisp slate text.', 'airygen-seo' ),
			style: {
				...settings.style,
				preset: 'snow-slate',
				showBorder: true,
				borderStyle: 'dashed',
				borderColor: '#dddddd',
				borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
				borderRadius: 6,
				paddings: { top: 9, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: 'transparent',
				itemTextColor: '#0f172a',
				itemFontSize: 14,
				itemBold: false,
				itemItalic: false,
				itemUnderline: false,
				itemListStyle: 'disc' as const,
				itemGap: 0,
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
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
		},
		{
			key: 'honey-paper',
			label: __( 'Honey Paper', 'airygen-seo' ),
			description: __( 'Soft parchment yellow for a warm hierarchy card.', 'airygen-seo' ),
			style: {
				...settings.style,
				preset: 'honey-paper',
				showBorder: true,
				borderStyle: 'solid',
				borderColor: '#e4d8aa',
				borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
				paddings: { top: 16, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: '#fefbf1',
				itemTextColor: '#5a3926',
				itemFontSize: 13,
				itemBold: false,
				itemItalic: false,
				itemUnderline: false,
				itemListStyle: 'disc' as const,
				itemGap: 0,
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
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
		},
		{
			key: 'sky-breeze',
			label: __( 'Sky Breeze', 'airygen-seo' ),
			description: __( 'Airy light blues for a softer navigation block.', 'airygen-seo' ),
			style: {
				...settings.style,
				preset: 'sky-breeze',
				showBorder: true,
				borderStyle: 'dotted',
				borderColor: '#93c5fd',
				borderWidths: { top: 2, right: 2, bottom: 2, left: 2 },
				paddings: { top: 16, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: 'transparent',
				itemTextColor: '#4b7db9',
				itemFontSize: 13,
				itemBold: false,
				itemItalic: false,
				itemUnderline: false,
				itemListStyle: 'disc' as const,
				itemGap: 0,
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#4b7db9',
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
		},
		{
			key: 'mint-calm',
			label: __( 'Mint Calm', 'airygen-seo' ),
			description: __( 'Mint green tone that feels supportive and stable.', 'airygen-seo' ),
			style: {
				...settings.style,
				preset: 'mint-calm',
				showBorder: true,
				borderStyle: 'dashed',
				borderColor: '#7dcaab',
				borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
				paddings: { top: 16, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: '#fafffd',
				itemTextColor: '#292929',
				itemFontSize: 13,
				itemBold: false,
				itemItalic: false,
				itemUnderline: false,
				itemListStyle: 'disc' as const,
				itemGap: 0,
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#7dcaab',
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
		},
		{
			key: 'rose-blush',
			label: __( 'Rose Blush', 'airygen-seo' ),
			description: __( 'Soft rose palette with friendly, approachable presence.', 'airygen-seo' ),
			style: {
				...settings.style,
				preset: 'rose-blush',
				showBorder: true,
				borderStyle: 'dotted',
				borderColor: '#ebc6ca',
				borderWidths: { top: 5, right: 5, bottom: 5, left: 5 },
				paddings: { top: 16, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: '#fffafa',
				itemTextColor: '#881337',
				itemFontSize: 13,
				itemBold: false,
				itemItalic: false,
				itemUnderline: false,
				itemListStyle: 'disc' as const,
				itemGap: 0,
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#881337',
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
		},
		{
			key: 'lavender-mist',
			label: __( 'Lavender Mist', 'airygen-seo' ),
			description: __( 'Light violet with a modern AI/creative product vibe.', 'airygen-seo' ),
			style: {
				...settings.style,
				preset: 'lavender-mist',
				showBorder: true,
				borderStyle: 'solid',
				borderColor: '#c4b5fd',
				borderWidths: { top: 0, right: 0, bottom: 0, left: 0 },
				paddings: { top: 16, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
				bgColor: '#f5f3ff',
				itemTextColor: '#472975',
				itemFontSize: 13,
				itemBold: false,
				itemItalic: false,
				itemUnderline: false,
				itemListStyle: 'disc' as const,
				itemGap: 0,
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid' as const,
					borderColor: '#472975',
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
		},
	];
	const previewSamples = useMemo<TopicClusterPreviewSample[]>(
		() => [
			{
				level: 'L1',
				title: __( 'L1 article output', 'airygen-seo' ),
				currentId: 'l1',
				l1: { id: 'l1', title: __( 'Japan Travel Guide', 'airygen-seo' ) },
				l2: [
					{ id: 'l2a', title: __( 'Tokyo Itinerary', 'airygen-seo' ) },
					{ id: 'l2b', title: __( 'Osaka Itinerary', 'airygen-seo' ) },
				],
				l3: {} as Record<string, Array<{ id: string; title: string }>>,
			},
			{
				level: 'L2',
				title: __( 'L2 article output', 'airygen-seo' ),
				currentId: 'l2a',
				l1: { id: 'l1', title: __( 'Japan Travel Guide', 'airygen-seo' ) },
				l2: [ { id: 'l2a', title: __( 'Tokyo Itinerary', 'airygen-seo' ) } ],
				l3: {
					l2a: [
						{ id: 'l3a', title: __( 'Shibuya Hotel Picks', 'airygen-seo' ) },
						{ id: 'l3b', title: __( 'Asakusa One-day Walk', 'airygen-seo' ) },
					],
				},
			},
			{
				level: 'L3',
				title: __( 'L3 article output', 'airygen-seo' ),
				currentId: 'l3b',
				l1: { id: 'l1', title: __( 'Japan Travel Guide', 'airygen-seo' ) },
				l2: [ { id: 'l2a', title: __( 'Tokyo Itinerary', 'airygen-seo' ) } ],
				l3: {
					l2a: [
						{ id: 'l3a', title: __( 'Shibuya Hotel Picks', 'airygen-seo' ) },
						{ id: 'l3b', title: __( 'Asakusa One-day Walk', 'airygen-seo' ) },
					],
				},
			},
		],
		[],
	);
	let markerCss = '.airygen-topic-cluster__item-marker{display:none;}';
	if ( settings.style.itemListStyle === 'decimal' ) {
		markerCss =
			'.airygen-topic-cluster__links--decimal{counter-reset:topic-cluster-item;}.airygen-topic-cluster__links--decimal .airygen-topic-cluster__item{counter-increment:topic-cluster-item;}.airygen-topic-cluster__links--decimal .airygen-topic-cluster__item-marker::before{content:counter(topic-cluster-item) \'.\';}';
	} else if ( settings.style.itemListStyle === 'disc' ) {
		markerCss = ".airygen-topic-cluster__links--disc .airygen-topic-cluster__item-marker::before{content:'•';}";
	}
	const previewCss = useMemo(
		() =>
			`.airygen-topic-cluster__header{margin:${ topicHeaderContainer.margins.top }px ${ topicHeaderContainer.margins.right }px ${ topicHeaderContainer.margins.bottom }px ${ topicHeaderContainer.margins.left }px;padding:${ topicHeaderContainer.paddings.top }px ${ topicHeaderContainer.paddings.right }px ${ topicHeaderContainer.paddings.bottom }px ${ topicHeaderContainer.paddings.left }px;border-style:${ topicHeaderContainer.borderStyle };border-color:${ topicHeaderContainer.borderColor };border-width:${ topicHeaderContainer.borderWidths.top }px ${ topicHeaderContainer.borderWidths.right }px ${ topicHeaderContainer.borderWidths.bottom }px ${ topicHeaderContainer.borderWidths.left }px;background:${ topicHeaderContainer.bgColor };border-radius:${ topicHeaderContainer.borderRadius }px;}.airygen-topic-cluster__title{margin:0;font-size:${ topicHeaderTitle.fontSize }px;font-weight:${ topicHeaderTitle.fontStyle.bold ? 700 : 400 };font-style:${ topicHeaderTitle.fontStyle.italic ? 'italic' : 'normal' };text-decoration:${ topicHeaderTitle.fontStyle.underline ? 'underline' : 'none' };color:${ topicHeaderTitle.color };}.airygen-topic-cluster{margin:${ settings.style.margins.top }px ${ settings.style.margins.right }px ${ settings.style.margins.bottom }px ${ settings.style.margins.left }px;padding:${ settings.style.paddings.top }px ${ settings.style.paddings.right }px ${ settings.style.paddings.bottom }px ${ settings.style.paddings.left }px;border-style:${ settings.style.borderStyle };border-color:${ settings.style.borderColor };border-width:${ settings.style.borderWidths.top }px ${ settings.style.borderWidths.right }px ${ settings.style.borderWidths.bottom }px ${ settings.style.borderWidths.left }px;background:${ settings.style.bgColor };border-radius:${ settings.style.borderRadius }px;}.airygen-topic-cluster__intro{margin:0 0 12px;font-size:.925rem;line-height:1.6;color:#475569;}.airygen-topic-cluster__links{display:flex;flex-direction:column;gap:${ settings.style.itemGap }px;}${ markerCss }.airygen-topic-cluster__item{display:flex;align-items:flex-start;gap:8px;}.airygen-topic-cluster__item--parent{flex-direction:column;gap:6px;border:1px solid rgba(148,163,184,.35);border-radius:10px;background:rgba(255,255,255,.7);padding:12px;}.airygen-topic-cluster__parent-label{font-size:.75rem;font-weight:600;letter-spacing:.02em;text-transform:uppercase;color:#64748b;}.airygen-topic-cluster__item-marker{flex:0 0 auto;display:inline-flex;min-width:1rem;justify-content:center;color:${ settings.style.itemTextColor };font-size:${ settings.style.itemFontSize }px;line-height:1.5;}.airygen-topic-cluster__link{color:${ settings.style.itemTextColor };font-size:${ settings.style.itemFontSize }px;font-weight:${ settings.style.itemBold ? 700 : 400 };font-style:${ settings.style.itemItalic ? 'italic' : 'normal' };text-decoration:${ settings.style.itemUnderline ? 'underline' : 'none' };text-underline-offset:2px;line-height:1.6;}.airygen-topic-cluster__link[aria-current="page"]{color:${ settings.style.itemTextColor };font-weight:600;}`,
		[ markerCss, settings.style, topicHeaderContainer, topicHeaderTitle ],
	);
	const previewCodeSamples = useMemo(
		() =>
			previewSamples.map( ( sample ) => ( {
				level: sample.level,
				html: buildTopicClusterPreviewHtml(
					sample,
					settings.titleEnabled,
					settings.titleLevel,
					settings.titleText,
					settings.relationTextL1,
					settings.relationTextL2,
					settings.relationTextL3,
					settings.style.itemListStyle,
				),
				css: previewCss,
			} ) ),
		[
			previewCss,
			previewSamples,
			settings.style.itemListStyle,
			settings.titleEnabled,
			settings.titleLevel,
			settings.titleText,
			settings.relationTextL1,
			settings.relationTextL2,
			settings.relationTextL3,
		],
	);

	useEffect( () => {
		if ( 'undefined' === typeof window ) {
			return;
		}

		const params = new URLSearchParams( window.location.search );
		const tab = params.get( 'tab' );
		const groupId = Number( params.get( 'group_id' ) || 0 );
		const hasMindmapQuery = 'mindmap' === tab && Number.isFinite( groupId ) && groupId > 0;

		if ( hasMindmapQuery ) {
			setActiveTab( 'mindmap' );
		}

		if ( Number.isFinite( groupId ) && groupId > 0 ) {
			setActiveMindmapGroupId( groupId );
		}
	}, [] );

	const canOpenMindmapTab = Number.isFinite( activeMindmapGroupId ) && null !== activeMindmapGroupId && activeMindmapGroupId > 0;

	const fetchGroups = useCallback( async () => {
		setGroupsLoading( true );
		try {
			const response = await apiFetch<TopicClusterGroupsResponse>( {
				path: `${ groupsPath }?page=${ groupPage }&per_page=${ groupsPerPage }`,
			} );
			const nextGroups = Array.isArray( response.groups ) ? response.groups : [];
			setGroups( nextGroups );
			const total = Number( response.pagination?.totalPages );
			setGroupTotalPages( Number.isFinite( total ) && total > 0 ? total : 1 );
		} catch ( error ) {
			const message =
				error instanceof Error
					? error.message
					: __( 'Unable to load topic cluster groups.', 'airygen-seo' );
			onNotice( { status: 'error', message } );
		} finally {
			setGroupsLoading( false );
		}
	}, [ groupPage, groupsPath, onNotice ] );

	useEffect( () => {
		if ( activeTab === 'groups' ) {
			void fetchGroups();
		}
	}, [ activeTab, fetchGroups ] );

	useEffect( () => {
		if ( groupPage > groupTotalPages ) {
			setGroupPage( groupTotalPages );
		}
	}, [ groupPage, groupTotalPages ] );

	const copySnippet = useCallback(
		( value: string, success: string ) => {
			void navigator.clipboard.writeText( value ).then(
				() => onNotice( { status: 'success', message: success } ),
				() =>
					onNotice( {
						status: 'error',
						message: getUnableToCopySnippetLabel(),
					} ),
			);
		},
		[ onNotice ],
	);

	const handleCloseCreateGroupModal = useCallback( () => {
		if ( isCreatingGroup ) {
			return;
		}

		setIsCreateGroupModalOpen( false );
		setGroupName( '' );
		setGroupDescription( '' );
	}, [ isCreatingGroup ] );

	const handleCreateGroup = useCallback( async () => {
		const nextName = groupName.trim();
		if ( ! nextName ) {
			onNotice( {
				status: 'error',
				message: __( 'Group name is required.', 'airygen-seo' ),
			} );
			return;
		}

		setIsCreatingGroup( true );
		try {
			await apiFetch( {
				path: `${ restBase }/topic-cluster/groups`,
				method: 'POST',
				data: {
					name: nextName,
					description: groupDescription.trim(),
				},
			} );
			onNotice( {
				status: 'success',
				message: __( 'Group created.', 'airygen-seo' ),
			} );
			handleCloseCreateGroupModal();
			setGroupPage( 1 );
			void fetchGroups();
		} catch ( error ) {
			const message =
				error instanceof Error
					? error.message
					: __( 'Unable to create group.', 'airygen-seo' );
			onNotice( { status: 'error', message } );
		} finally {
			setIsCreatingGroup( false );
		}
	}, [
		fetchGroups,
		groupDescription,
		groupName,
		handleCloseCreateGroupModal,
		onNotice,
		restBase,
	] );

	const handleOpenEditGroupModal = useCallback( ( group: TopicClusterGroup ) => {
		setEditingGroupId( group.group_id );
		setEditingGroupName( group.group_name || '' );
		setEditingGroupDescription( group.description || '' );
		setIsEditGroupModalOpen( true );
	}, [] );

	const handleCloseEditGroupModal = useCallback( () => {
		if ( isUpdatingGroup ) {
			return;
		}
		setIsEditGroupModalOpen( false );
		setEditingGroupId( 0 );
		setEditingGroupName( '' );
		setEditingGroupDescription( '' );
	}, [ isUpdatingGroup ] );

	const handleUpdateGroup = useCallback( async () => {
		if ( editingGroupId <= 0 ) {
			onNotice( {
				status: 'error',
				message: __( 'A valid group is required.', 'airygen-seo' ),
			} );
			return;
		}

		const nextName = editingGroupName.trim();
		if ( ! nextName ) {
			onNotice( {
				status: 'error',
				message: __( 'Group name is required.', 'airygen-seo' ),
			} );
			return;
		}

		setIsUpdatingGroup( true );
		try {
			await apiFetch( {
				path: `${ restBase }/topic-cluster/groups/${ editingGroupId }`,
				method: 'POST',
				data: {
					name: nextName,
					description: editingGroupDescription.trim(),
				},
			} );
			onNotice( {
				status: 'success',
				message: __( 'Group updated.', 'airygen-seo' ),
			} );
			handleCloseEditGroupModal();
			void fetchGroups();
		} catch ( error ) {
			const message =
				error instanceof Error
					? error.message
					: __( 'Unable to update group.', 'airygen-seo' );
			onNotice( { status: 'error', message } );
		} finally {
			setIsUpdatingGroup( false );
		}
	}, [
		editingGroupDescription,
		editingGroupId,
		editingGroupName,
		fetchGroups,
		handleCloseEditGroupModal,
		onNotice,
		restBase,
	] );

	const handleRemoveGroup = useCallback( async ( group: TopicClusterGroup ) => {
		const l1Count = Number( group.l1_count || 0 );
		const l2Count = Number( group.l2_count || 0 );
		const l3Count = Number( group.l3_count || 0 );
		if ( l1Count > 0 || l2Count > 0 || l3Count > 0 ) {
			return;
		}

		setRemovingGroupId( group.group_id );
		try {
			await apiFetch( {
				path: `${ restBase }/topic-cluster/groups/${ group.group_id }`,
				method: 'DELETE',
			} );
			onNotice( {
				status: 'success',
				message: __( 'Group removed.', 'airygen-seo' ),
			} );
			void fetchGroups();
		} catch ( error ) {
			const message =
				error instanceof Error
					? error.message
					: __( 'Unable to remove group.', 'airygen-seo' );
			onNotice( { status: 'error', message } );
		} finally {
			setRemovingGroupId( 0 );
		}
	}, [ fetchGroups, onNotice, restBase ] );

	const fetchCandidates = useCallback(
		async ( groupId: number ) => {
			setCandidatesLoadingGroupId( groupId );
			try {
				const response = await apiFetch<TopicClusterCandidateResponse>( {
					path: `${ restBase }/topic-cluster/groups/${ groupId }/candidates`,
				} );
				const items = Array.isArray( response.candidates ) ? response.candidates : [];
				setCandidatesByGroup( ( current ) => ( {
					...current,
					[ groupId ]: items,
				} ) );
			} catch ( error ) {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to load candidates.', 'airygen-seo' );
				onNotice( { status: 'error', message } );
			} finally {
				setCandidatesLoadingGroupId( 0 );
			}
		},
		[ onNotice, restBase ],
	);

	const handleToggleManage = useCallback(
		( groupId: number ) => {
			if ( expandedGroupId === groupId ) {
				setExpandedGroupId( null );
				return;
			}
			setExpandedGroupId( groupId );
			if ( ! candidatesByGroup[ groupId ] ) {
				void fetchCandidates( groupId );
			}
		},
		[ candidatesByGroup, expandedGroupId, fetchCandidates ],
	);

	const handleSearchCandidates = useCallback(
		async ( groupId: number ) => {
			const query = ( candidateSearchTermByGroup[ groupId ] || '' ).trim();
			setCandidateSearchLoadingGroupId( groupId );
			try {
				const response = await apiFetch<TopicClusterCandidateSearchResponse>( {
					path: `${ restBase }/topic-cluster/groups/${ groupId }/candidates/search?q=${ encodeURIComponent(
						query,
					) }`,
				} );
				const items = Array.isArray( response.items ) ? response.items : [];
				setCandidateSearchResultsByGroup( ( current ) => ( {
					...current,
					[ groupId ]: items,
				} ) );
			} catch ( error ) {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to search posts.', 'airygen-seo' );
				onNotice( { status: 'error', message } );
			} finally {
				setCandidateSearchLoadingGroupId( 0 );
			}
		},
		[ candidateSearchTermByGroup, onNotice, restBase ],
	);

	const handleAddCandidate = useCallback(
		async ( groupId: number, postId: number ) => {
			const key = `${ groupId }:${ postId }`;
			setAddingCandidateKey( key );
			try {
				await apiFetch( {
					path: `${ restBase }/topic-cluster/groups/${ groupId }/candidates`,
					method: 'POST',
					data: { post_id: postId },
				} );
				await fetchCandidates( groupId );
				setGroups( ( current ) =>
					current.map( ( group ) =>
						group.group_id === groupId
							? {
								...group,
								candidates_count: Number( group.candidates_count || 0 ) + 1,
							}
							: group,
					),
				);
				setCandidateSearchResultsByGroup( ( current ) => ( {
					...current,
					[ groupId ]: ( current[ groupId ] || [] ).filter( ( item ) => item.post_id !== postId ),
				} ) );
			} catch ( error ) {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to add candidate.', 'airygen-seo' );
				onNotice( { status: 'error', message } );
			} finally {
				setAddingCandidateKey( '' );
			}
		},
		[ fetchCandidates, onNotice, restBase ],
	);

	const handleRemoveCandidate = useCallback(
		async ( groupId: number, candidateId: number ) => {
			setRemovingCandidateId( candidateId );
			try {
				await apiFetch( {
					path: `${ restBase }/topic-cluster/groups/${ groupId }/candidates/${ candidateId }`,
					method: 'DELETE',
				} );
				setCandidatesByGroup( ( current ) => ( {
					...current,
					[ groupId ]: ( current[ groupId ] || [] ).filter(
						( candidate ) => candidate.id !== candidateId,
					),
				} ) );
				setGroups( ( current ) =>
					current.map( ( group ) =>
						group.group_id === groupId
							? {
								...group,
								candidates_count: Math.max( 0, Number( group.candidates_count || 0 ) - 1 ),
							}
							: group,
					),
				);
			} catch ( error ) {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to remove candidate.', 'airygen-seo' );
				onNotice( { status: 'error', message } );
			} finally {
				setRemovingCandidateId( 0 );
			}
		},
		[ onNotice, restBase ],
	);

	const renderCandidateRows = ( group: TopicClusterGroup ) => {
		const items = candidatesByGroup[ group.group_id ] || [];
		if ( candidatesLoadingGroupId === group.group_id ) {
			return (
				<tr>
					<td colSpan={ 3 } className="px-3 py-3 text-slate-500">
						{ getLoadingItemLabel( __( 'candidates', 'airygen-seo' ) ) }
					</td>
				</tr>
			);
		}

		if ( 0 === items.length ) {
			return (
				<tr>
					<td colSpan={ 3 } className="px-3 py-3 text-slate-500">
						{ getNoItemsYetLabel( __( 'candidates', 'airygen-seo' ) ) }
					</td>
				</tr>
			);
		}

		return items.map( ( candidate ) => (
			<tr key={ candidate.id }>
				<td className="px-3 py-2">{ candidate.post_id }</td>
				<td className="px-3 py-2">{ candidate.title }</td>
				<td className="px-3 py-2 text-right">
					<Button
						variant="danger"
						className="text-xs inline-flex items-center justify-center gap-1 !px-1 !py-1 leading-none"
						onClick={ () => {
							void handleRemoveCandidate( group.group_id, candidate.id );
						} }
						loading={ removingCandidateId === candidate.id }
					>
						<span className="dashicons dashicons-trash" aria-hidden="true" />
						<span className="sr-only">{ __( 'Remove', 'airygen-seo' ) }</span>
					</Button>
				</td>
			</tr>
		) );
	};

	let mindMapTabClass = 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500';
	if ( activeTab === 'mindmap' ) {
		mindMapTabClass = 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900';
	} else if ( ! canOpenMindmapTab ) {
		mindMapTabClass = 'cursor-not-allowed rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-300';
	}

	const footerIsDirty = mindMapTabActive ? mindMapDirty : isDirty;
	const footerIsSaving = isSaving || mindMapSaving;
	const handleSaveSettings = useCallback( () => {
		if ( ! hasValidRelationTemplate( settings.relationTextL1, 0 ) ) {
			onNotice( {
				status: 'error',
				message: sprintf(
					/* translators: %s is the link placeholder token. */
					__( 'L1 relation link description must not contain %s.', 'airygen-seo' ),
					'%s',
				),
			} );
			return;
		}

		if ( ! hasValidRelationTemplate( settings.relationTextL2, 1 ) ) {
			onNotice( {
				status: 'error',
				message: sprintf(
					/* translators: %s is the link placeholder token. */
					__( 'L2 relation link description must contain exactly one %s.', 'airygen-seo' ),
					'%s',
				),
			} );
			return;
		}

		if ( ! hasValidRelationTemplate( settings.relationTextL3, 1 ) ) {
			onNotice( {
				status: 'error',
				message: sprintf(
					/* translators: %s is the link placeholder token. */
					__( 'L3 relation link description must contain exactly one %s.', 'airygen-seo' ),
					'%s',
				),
			} );
			return;
		}

		onSave();
	}, [
		onNotice,
		onSave,
		settings.relationTextL1,
		settings.relationTextL2,
		settings.relationTextL3,
	] );

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-4 sm:flex-row sm:items-start">
				<span className="flex h-12 w-12 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600">
					<TopicClusterIcon className="h-8 w-8" aria-hidden="true" />
				</span>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Topic Cluster', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __( 'Manage pillar, cluster, and support relationships for your content.', 'airygen-seo' ) }
					</div>
				</div>
			</div>

			<div className="flex gap-2" data-airygen-e2e="tabs-topic-cluster-page">
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
				<button
					type="button"
					data-airygen-e2e="tab-groups"
					className={
						activeTab === 'groups'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'groups' ) }
				>
					{ __( 'Groups', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-mindmap"
					className={ mindMapTabClass }
					disabled={ ! canOpenMindmapTab }
					onClick={ () => {
						if ( ! canOpenMindmapTab ) {
							return;
						}
						setActiveTab( 'mindmap' );
					} }
				>
					{ __( 'Mind map', 'airygen-seo' ) }
				</button>
			</div>

			{ activeTab === 'settings' ? (
				<div className="space-y-4">
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex flex-wrap items-start justify-between gap-3">
							<div className="space-y-1">
								<div className="airygen_h2_title">
									{ __( 'Settings', 'airygen-seo' ) }
								</div>
								<p className="text-sm text-slate-500">
									{ sprintf(
										/* translators: %s is the module name. */
										__( 'Configure manual output and automatic %s injection.', 'airygen-seo' ),
										topicClusterModuleLabel,
									) }
								</p>
							</div>
						</div>

						<div className="space-y-4">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-800">
										{ manualTopicClusterLabel }
									</p>
									<Toggle
										label={ manualTopicClusterLabel }
										checked={ settings.manualOutputEnabled }
										onChange={ ( next ) =>
											onChange( { ...settings, manualOutputEnabled: next } )
										}
										hideLabelText
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Allow template function, shortcode, and block output.', 'airygen-seo' ) }
								</p>
								<div className="mt-1 grid gap-3 md:grid-cols-3">
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
										<label className="text-sm font-semibold text-gray-800" htmlFor="airygen-topic-cluster-template-tag">
											{ __( 'Template function', 'airygen-seo' ) }
										</label>
										<textarea
											id="airygen-topic-cluster-template-tag"
											className="mt-2 airygen-field w-full font-mono text-xs"
											rows={ 3 }
											readOnly
											value={ templateTagSnippet }
										/>
										<Button
											variant="secondary"
											className="mt-3 text-xs"
											onClick={ () =>
												copySnippet(
													templateTagSnippet,
													getSnippetCopiedLabel( getTemplateFunctionLabel() ),
												)
											}
										>
											{ __( 'Copy', 'airygen-seo' ) }
										</Button>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
										<label className="text-sm font-semibold text-gray-800" htmlFor="airygen-topic-cluster-shortcode">
											{ __( 'Shortcode', 'airygen-seo' ) }
										</label>
										<textarea
											id="airygen-topic-cluster-shortcode"
											className="mt-2 airygen-field w-full font-mono text-xs"
											rows={ 3 }
											readOnly
											value={ shortcodeSnippet }
										/>
										<Button
											variant="secondary"
											className="mt-3 text-xs"
											onClick={ () =>
												copySnippet(
													shortcodeSnippet,
													getSnippetCopiedLabel( getShortcodeLabel() ),
												)
											}
										>
											{ __( 'Copy', 'airygen-seo' ) }
										</Button>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
										<label className="text-sm font-semibold text-gray-800" htmlFor="airygen-topic-cluster-block">
											{ __( 'Block', 'airygen-seo' ) }
										</label>
										<textarea
											id="airygen-topic-cluster-block"
											className="mt-2 airygen-field w-full font-mono text-xs"
											rows={ 3 }
											readOnly
											value={ blockSnippet }
										/>
										<Button
											variant="secondary"
											className="mt-3 text-xs"
											onClick={ () =>
												copySnippet(
													blockSnippet,
													getSnippetCopiedLabel( getBlockSnippetLabel() ),
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
									<p className="text-sm font-medium text-slate-800">
										{ automaticTopicClusterLabel }
									</p>
									<Toggle
										label={ automaticTopicClusterLabel }
										checked={ settings.autoInjectionEnabled }
										onChange={ ( next ) =>
											onChange( { ...settings, autoInjectionEnabled: next } )
										}
										hideLabelText
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ sprintf(
										/* translators: %s is the module name. */
										__( 'Automatically inject %s navigation into post content.', 'airygen-seo' ),
										topicClusterModuleLabel,
									) }
								</p>
								<div className="mt-1 grid gap-3 md:grid-cols-3">
									<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-3">
										<label className="text-sm font-medium text-gray-800" htmlFor="airygen-topic-cluster-insert">
											{ __( 'Insert position', 'airygen-seo' ) }
										</label>
										<select
											id="airygen-topic-cluster-insert"
											className="airygen-field-select mt-2 w-full"
											value={ settings.insertPosition }
											disabled={ ! settings.autoInjectionEnabled }
											onChange={ ( event ) =>
												onChange( { ...settings, insertPosition: event.target.value as TopicClusterSettings['insertPosition'] } )
											}
										>
											<option value="before-content">
												{ __( 'Before content', 'airygen-seo' ) }
											</option>
											<option value="after-content">
												{ __( 'After content', 'airygen-seo' ) }
											</option>
										</select>
										<p className="mt-2 text-xs text-slate-500">
											{ sprintf(
												/* translators: %s is the module name. */
												__( 'Choose where automatic %s output appears in post content.', 'airygen-seo' ),
												topicClusterModuleLabel,
											) }
										</p>
									</div>
								</div>
							</div>
							<SectionHeaderSettingsCard
								moduleLabel={ topicClusterModuleLabel }
								enabled={ settings.titleEnabled }
								onEnabledChange={ ( value ) => onChange( { ...settings, titleEnabled: value } ) }
								text={ settings.titleText }
								onTextChange={ ( value ) => onChange( { ...settings, titleText: value } ) }
								level={ settings.titleLevel }
								onLevelChange={ ( value ) => onChange( { ...settings, titleLevel: value } ) }
							/>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Relation link descriptions', 'airygen-seo' ) }
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ __(
									'Customize the descriptions shown before related Topic Cluster links in L1, L2, and L3 articles.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="rounded-lg border border-slate-200 p-4">
								<label
									className="text-sm font-medium text-slate-900"
									htmlFor="airygen-topic-cluster-relation-text-l1"
								>
									L1
								</label>
								<textarea
									id="airygen-topic-cluster-relation-text-l1"
									className="airygen-field mt-2 w-full"
									rows={ 2 }
									value={ settings.relationTextL1 }
									onChange={ ( event ) =>
										onChange( { ...settings, relationTextL1: event.target.value } )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ relationLinkTokenForbiddenLabel }
								</p>
							</div>
							<div className="rounded-lg border border-slate-200 p-4">
								<label
									className="text-sm font-medium text-slate-900"
									htmlFor="airygen-topic-cluster-relation-text-l2"
								>
									L2
								</label>
								<textarea
									id="airygen-topic-cluster-relation-text-l2"
									className="airygen-field mt-2 w-full"
									rows={ 2 }
									value={ settings.relationTextL2 }
									onChange={ ( event ) =>
										onChange( { ...settings, relationTextL2: event.target.value } )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ relationLinkTokenLabel }
								</p>
							</div>
							<div className="rounded-lg border border-slate-200 p-4">
								<label
									className="text-sm font-medium text-slate-900"
									htmlFor="airygen-topic-cluster-relation-text-l3"
								>
									L3
								</label>
								<textarea
									id="airygen-topic-cluster-relation-text-l3"
									className="airygen-field mt-2 w-full"
									rows={ 2 }
									value={ settings.relationTextL3 }
									onChange={ ( event ) =>
										onChange( { ...settings, relationTextL3: event.target.value } )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ relationLinkTokenLabel }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Behavior', 'airygen-seo' ) }
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ sprintf(
									/* translators: %s is the module name. */
									__( 'Define how %s influences breadcrumbs and previous and next navigation.', 'airygen-seo' ),
									topicClusterModuleLabel,
								) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Override breadcrumbs', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Override breadcrumbs', 'airygen-seo' ) }
										checked={ settings.overrideBreadcrumbs }
										onChange={ ( next ) =>
											onChange( { ...settings, overrideBreadcrumbs: next } )
										}
										hideLabelText
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ sprintf(
										/* translators: %s is the module name. */
										__( 'Use %s hierarchy instead of the default breadcrumb trail.', 'airygen-seo' ),
										topicClusterModuleLabel,
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Override WordPress previous and next links', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Override WordPress previous and next links', 'airygen-seo' ) }
										checked={ settings.overrideWpAdjacent }
										onChange={ ( next ) =>
											onChange( { ...settings, overrideWpAdjacent: next } )
										}
										hideLabelText
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ sprintf(
										/* translators: %s is the module name. */
										__( 'Take over previous and next navigation with %s relationships.', 'airygen-seo' ),
										topicClusterModuleLabel,
									) }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Scope', 'airygen-seo' ) }
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ sprintf(
									/* translators: %s is the module name. */
									__( 'Choose which post types can participate in %s groups.', 'airygen-seo' ),
									topicClusterModuleLabel,
								) }
							</p>
						</div>
						<div>
							<h3 className="mb-3 text-sm font-medium text-gray-800">
								{ __( 'Post types', 'airygen-seo' ) }
							</h3>
							<div className="grid gap-2 md:grid-cols-8">
								{ meta.postTypes.map( ( postType ) => {
									const checked = settings.postTypes.includes( postType.slug );
									return (
										<div
											key={ postType.slug }
											className="rounded-lg border border-slate-200 p-2"
										>
											<Checkbox
												label={ postType.label }
												checked={ checked }
												onChange={ ( value ) => {
													const enabled = new Set( settings.postTypes );
													if ( value ) {
														enabled.add( postType.slug );
													} else {
														enabled.delete( postType.slug );
													}
													onChange( {
														...settings,
														postTypes: Array.from( enabled ),
													} );
												} }
											/>
										</div>
									);
								} ) }
							</div>
						</div>
					</section>

				</div>
			) : null }

			{ activeTab === 'layout' ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex flex-wrap items-start justify-between gap-3">
							<div className="space-y-1">
								<div className="airygen_h2_title">
									{ __( 'Templates', 'airygen-seo' ) }
								</div>
								<p className="text-sm text-slate-500">
									{ __( 'Pick a preset theme for Topic Cluster output.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
						<div className="grid gap-3 md:grid-cols-3">
							{ topicClusterThemes.map( ( option ) => {
								const isPresetActive = isSameStyle( option.style, settings.style );
								return (
									<div
										key={ option.key }
										role="button"
										tabIndex={ 0 }
										onClick={ () => {
											onChange( {
												...settings,
												styleType: option.key,
												style: option.style,
											} );
										} }
										onKeyDown={ ( event ) => {
											if ( event.key === 'Enter' || event.key === ' ' ) {
												event.preventDefault();
												onChange( {
													...settings,
													styleType: option.key,
													style: option.style,
												} );
											}
										} }
										className={ [
											'flex flex-col items-start gap-1 rounded-lg border px-4 py-3 text-sm font-medium',
											isPresetActive
												? 'border-slate-900 text-slate-900'
												: 'border-slate-200 text-slate-600 hover:border-slate-400',
										].join( ' ' ) }
									>
										<span className="text-sm font-semibold">{ option.label }</span>
										<span className="text-xs text-slate-500">{ option.description }</span>
									</div>
								);
							} ) }
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Style', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Adjust how Topic Cluster output appears on the page.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="space-y-4">
							<SectionHeaderStyleCards
								container={ topicHeaderContainer }
								title={ topicHeaderTitle }
								onContainerChange={ updateHeaderContainer }
								onTitleChange={ updateHeaderTitle }
								idPrefix="airygen-topic-cluster-header"
								containerMaxBorderWidth={ 20 }
								titleMinFontSize={ 10 }
								titleMaxFontSize={ 40 }
							/>
							<SectionBodyContainerStyleCard
								borderWidths={ settings.style.borderWidths }
								onBorderWidthsChange={ ( values ) => updateStyle( { borderWidths: values } ) }
								borderRadius={ settings.style.borderRadius }
								onBorderRadiusChange={ ( value ) => updateStyle( { borderRadius: value } ) }
								borderStyle={ settings.style.borderStyle as 'solid' | 'dashed' | 'dotted' }
								onBorderStyleChange={ ( value ) =>
									updateStyle( {
										borderStyle: value as TopicClusterSettings['style']['borderStyle'],
									} )
								}
								borderColor={ settings.style.borderColor }
								onBorderColorChange={ ( value ) => updateStyle( { borderColor: value } ) }
								paddings={ settings.style.paddings }
								onPaddingsChange={ ( values ) => updateStyle( { paddings: values } ) }
								margins={ settings.style.margins }
								onMarginsChange={ ( values ) => updateStyle( { margins: values } ) }
								bgColor={ settings.style.bgColor }
								onBgColorChange={ ( value ) => updateStyle( { bgColor: value } ) }
								idPrefix="airygen-topic-cluster"
								maxBorderWidth={ 20 }
								maxSpacing={ 50 }
								maxRadius={ 50 }
							/>
							<LinkStyleCard
								fontStyle={ {
									bold: Boolean( settings.style.itemBold ),
									italic: Boolean( settings.style.itemItalic ),
									underline: Boolean( settings.style.itemUnderline ),
								} }
								onFontStyleChange={ ( value ) =>
									updateStyle( {
										itemBold: value.bold,
										itemItalic: value.italic,
										itemUnderline: value.underline,
									} )
								}
								color={ settings.style.itemTextColor }
								onColorChange={ ( value ) => updateStyle( { itemTextColor: value } ) }
								fontSize={ settings.style.itemFontSize }
								onFontSizeChange={ ( value ) => updateStyle( { itemFontSize: value } ) }
								fontSizeMin={ 10 }
								fontSizeDescription={ __( 'Font size used for each Topic Cluster link.', 'airygen-seo' ) }
								fontStyleDescription={ __(
									'Set whether Topic Cluster links are bold, italic, or underlined.',
									'airygen-seo',
								) }
								colorDescription={ __( 'Color for each Topic Cluster link.', 'airygen-seo' ) }
							/>
							<ListStyleCard
								idPrefix="airygen-topic-cluster-list"
								listStyle={ settings.style.itemListStyle as 'none' | 'disc' | 'decimal' }
								onListStyleChange={ ( value ) =>
									updateStyle( {
										itemListStyle: value as TopicClusterSettings['style']['itemListStyle'],
									} )
								}
								listStyleDescription={ __(
									'Choose whether each line uses bullets, numbers, or plain text.',
									'airygen-seo',
								) }
								gap={ settings.style.itemGap }
								onGapChange={ ( value ) => updateStyle( { itemGap: value } ) }
								gapMax={ 20 }
								gapDescription={ __(
									'Set vertical spacing between each Topic Cluster item.',
									'airygen-seo',
								) }
							/>
						</div>
					</section>

				</>
			) : null }

			{ activeTab === 'preview' ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="flex flex-wrap items-start justify-between gap-3">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Preview', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Preview simulated Topic Cluster output for L1, L2, and L3 articles.', 'airygen-seo' ) }
							</p>
						</div>
						<PreviewDeviceSwitcher
							options={ [
								{ key: 'laptop', label: __( 'Laptop', 'airygen-seo' ), Icon: PreviewLaptopIcon },
								{ key: 'tablet', label: __( 'Tablet', 'airygen-seo' ), Icon: PreviewTabletIcon },
								{ key: 'cellphone', label: __( 'Cellphone', 'airygen-seo' ), Icon: PreviewCellphoneIcon },
							] }
							value={ previewViewport }
							onChange={ ( next ) => setPreviewViewport( next as PreviewDeviceKind ) }
						/>
					</div>

					<div className="overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
						<PreviewDeviceFrame device={ previewViewport }>
							<>
								<style>{ previewCss }</style>
								<div className="space-y-4">
									{ previewSamples.map( ( sample ) => (
										<div key={ sample.level } className="space-y-2">
											<div className="text-center text-xs font-medium uppercase tracking-wide text-slate-500">
												{ sample.title }
											</div>
											<div
												dangerouslySetInnerHTML={ {
													__html: buildTopicClusterPreviewHtml(
														sample,
														settings.titleEnabled,
														settings.titleLevel,
														settings.titleText,
														settings.relationTextL1,
														settings.relationTextL2,
														settings.relationTextL3,
														settings.style.itemListStyle,
													),
												} }
											/>
										</div>
									) ) }
								</div>
							</>
						</PreviewDeviceFrame>
					</div>

					<div className="space-y-5">
						{ previewCodeSamples.map( ( sample ) => (
							<section
								key={ `code-${ sample.level }` }
								className="space-y-4 rounded-lg border border-slate-200 bg-white p-4"
							>
								<div className="space-y-1">
									<h3 className="text-sm font-medium text-slate-900">
										{ sample.level }
									</h3>
								</div>
								<PreviewCodeSamples
									injectedCss={ sample.css }
									htmlSample={ sample.html }
									injectedCssId={ `airygen-topic-cluster-preview-css-${ sample.level.toLowerCase() }` }
									htmlSampleId={ `airygen-topic-cluster-preview-html-${ sample.level.toLowerCase() }` }
									rows={ 18 }
								/>
							</section>
						) ) }
					</div>
				</section>
			) : null }

			{ activeTab === 'mindmap' ? (
				<MindMap
					restBase={ restBase }
					onNotice={ onNotice }
					groupId={ activeMindmapGroupId }
					enableOrdering={ settings.overrideWpAdjacent }
					saveSignal={ mindMapSaveSignal }
					resetSignal={ mindMapResetSignal }
					onDirtyChange={ setMindMapDirty }
					onSavingChange={ setMindMapSaving }
				/>
			) : null }

			{ activeTab === 'groups' ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="flex flex-wrap items-start justify-between gap-3">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Groups', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Review Topic Cluster groups and their pillar/cluster/support counts.', 'airygen-seo' ) }
							</p>
						</div>
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ () => setIsCreateGroupModalOpen( true ) }
						>
							{ __( 'Create group', 'airygen-seo' ) }
						</Button>
					</div>

					{ groupsLoading ? (
						<p className="text-sm text-slate-500">{ getLoadingItemLabel( __( 'groups', 'airygen-seo' ) ) }</p>
					) : null }

					{ ! groupsLoading && groups.length === 0 ? (
						<div className="rounded-lg border border-slate-200 bg-white p-4">
							<p className="text-sm text-slate-500">{ getNoItemsYetLabel( __( 'topic cluster groups', 'airygen-seo' ) ) }</p>
						</div>
					) : null }

					{ groups.length > 0 ? (
						<div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
							<table className="min-w-full table-fixed divide-y divide-slate-200 text-sm">
								<thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
									<tr>
										<th className="w-[24%] px-3 py-2 text-left">{ __( 'Name', 'airygen-seo' ) }</th>
										<th className="w-[22%] px-3 py-2 text-left">{ __( 'Pillar', 'airygen-seo' ) }</th>
										<th className="w-[80px] px-3 py-2 text-center">{ __( 'L2', 'airygen-seo' ) }</th>
										<th className="w-[80px] px-3 py-2 text-center">{ __( 'L3', 'airygen-seo' ) }</th>
										<th className="w-[80px] px-3 py-2 text-center">{ __( 'Members', 'airygen-seo' ) }</th>
										<th className="w-[80px] px-3 py-2 text-center">{ __( 'Candidates', 'airygen-seo' ) }</th>
										<th className="w-[90px] px-3 py-2 text-center">{ __( 'Mind map', 'airygen-seo' ) }</th>
										<th className="w-[8%] px-3 py-2 text-right">{ __( 'Action', 'airygen-seo' ) }</th>
									</tr>
								</thead>
								<tbody className="divide-y divide-slate-200 align-top">
									{ groups.map( ( group ) => (
										<Fragment key={ group.group_id }>
											<tr className="border-t border-slate-100">
												<td className="px-3 py-3 text-xs text-slate-700">
													<p className="font-medium text-slate-900">
														{ group.group_name || '' }
													</p>
													<p className="mt-1 text-slate-500">
														{ group.description || '' }
													</p>
												</td>
												<td className="px-3 py-3 align-middle text-xs text-slate-700">
													{ group.pillar_edit ? (
														<a
															href={ group.pillar_edit }
															className="text-slate-900 underline-offset-2 hover:underline"
														>
															{ group.pillar_title || __( 'Not set', 'airygen-seo' ) }
														</a>
													) : (
														<span>{ group.pillar_title || __( 'Not set', 'airygen-seo' ) }</span>
													) }
												</td>
												<td className="px-3 py-3 align-middle text-center text-xs text-slate-700">{ group.l2_count }</td>
												<td className="px-3 py-3 align-middle text-center text-xs text-slate-700">{ group.l3_count }</td>
												<td className="px-3 py-3 align-middle text-center text-xs text-slate-700">{ group.total_members }</td>
												<td className="px-3 py-3 align-middle text-center text-xs text-slate-700">
													<div className="flex items-center justify-center gap-2">
														<span>{ Number( group.candidates_count || 0 ) }</span>
														<button
															type="button"
															className="inline-flex h-[30px] items-center justify-center rounded-md border border-slate-300 px-3 text-xs text-sky-700 transition-colors hover:border-slate-400 hover:bg-slate-50"
															data-airygen-e2e={ `topic-cluster-manage-group-${ group.group_id }` }
															onClick={ () => handleToggleManage( group.group_id ) }
														>
															{ __( 'Manage', 'airygen-seo' ) }
														</button>
													</div>
												</td>
												<td className="px-3 py-3 align-middle text-center text-xs text-slate-700">
													<button
														type="button"
														className="inline-flex h-[30px] items-center justify-center rounded-md border border-slate-300 px-3 text-xs text-sky-700 transition-colors hover:border-slate-400 hover:bg-slate-50"
														data-airygen-e2e={ `topic-cluster-launch-group-${ group.group_id }` }
														onClick={ () => {
															setActiveMindmapGroupId( group.group_id );
															setActiveTab( 'mindmap' );
														} }
													>
														{ __( 'Launch', 'airygen-seo' ) }
													</button>
												</td>
												<td className="px-3 py-3 align-middle text-xs text-slate-700">
													<div className="flex justify-end gap-2">
														<Button
															variant="secondary"
															className="text-xs inline-flex items-center justify-center gap-1 !px-1 !py-1 leading-none"
															onClick={ () => handleOpenEditGroupModal( group ) }
														>
															<span className="dashicons dashicons-edit" aria-hidden="true" />
															<span className="sr-only">{ __( 'Edit', 'airygen-seo' ) }</span>
														</Button>
														<Button
															variant="danger"
															className="text-xs inline-flex items-center justify-center gap-1 !px-1 !py-1 leading-none"
															disabled={
																removingGroupId === group.group_id ||
																	Number( group.l1_count || 0 ) > 0 ||
																	Number( group.l2_count || 0 ) > 0 ||
																	Number( group.l3_count || 0 ) > 0
															}
															loading={ removingGroupId === group.group_id }
															onClick={ () => {
																void handleRemoveGroup( group );
															} }
														>
															<span className="dashicons dashicons-trash" aria-hidden="true" />
															<span className="sr-only">{ __( 'Remove', 'airygen-seo' ) }</span>
														</Button>
													</div>
												</td>
											</tr>
											{ expandedGroupId === group.group_id ? (
												<tr className="bg-slate-50">
													<td colSpan={ 8 } className="px-3 py-3">
														<div className="rounded-lg border border-slate-200 bg-white p-3">
															<div className="flex items-center gap-2">
																<input
																	type="text"
																	className="airygen-field w-full"
																	placeholder={ __( 'Search post title…', 'airygen-seo' ) }
																	value={ candidateSearchTermByGroup[ group.group_id ] || '' }
																	onChange={ ( event ) =>
																		setCandidateSearchTermByGroup( ( current ) => ( {
																			...current,
																			[ group.group_id ]: event.target.value,
																		} ) )
																	}
																/>
																<Button
																	variant="secondary"
																	className="text-xs !h-[34px] !px-2 !py-0 leading-none"
																	onClick={ () => {
																		void handleSearchCandidates( group.group_id );
																	} }
																	loading={ candidateSearchLoadingGroupId === group.group_id }
																>
																	{ __( 'Search', 'airygen-seo' ) }
																</Button>
															</div>
															{ ( candidateSearchResultsByGroup[ group.group_id ] || [] ).length > 0 ? (
																<div className="mt-3 rounded border border-slate-200">
																	<table className="min-w-full table-fixed text-xs">
																		<thead className="bg-slate-50 text-slate-500">
																			<tr>
																				<th className="px-3 py-2 text-left">post_id</th>
																				<th className="px-3 py-2 text-left">{ __( 'Title', 'airygen-seo' ) }</th>
																				<th className="px-3 py-2 text-right">{ __( 'Action', 'airygen-seo' ) }</th>
																			</tr>
																		</thead>
																		<tbody>
																			{ ( candidateSearchResultsByGroup[ group.group_id ] || [] ).map(
																				( item ) => (
																					<tr key={ `${ group.group_id }-${ item.post_id }` }>
																						<td className="px-3 py-2">{ item.post_id }</td>
																						<td className="px-3 py-2">{ item.title }</td>
																						<td className="px-3 py-2 text-right">
																							<Button
																								variant="secondary"
																								className="text-xs !px-1 !py-1 leading-none"
																								onClick={ () => {
																									void handleAddCandidate(
																										group.group_id,
																										item.post_id,
																									);
																								} }
																								loading={
																									addingCandidateKey ===
																										`${ group.group_id }:${ item.post_id }`
																								}
																							>
																								<span className="dashicons dashicons-plus-alt2" aria-hidden="true" />
																								<span className="sr-only">{ __( 'Add', 'airygen-seo' ) }</span>
																							</Button>
																						</td>
																					</tr>
																				),
																			) }
																		</tbody>
																	</table>
																</div>
															) : null }
															<div className="mt-3 rounded border border-slate-200">
																<table className="min-w-full table-fixed text-xs">
																	<thead className="bg-slate-50 text-slate-500">
																		<tr>
																			<th className="w-[120px] px-3 py-2 text-left">{ __( 'Post ID', 'airygen-seo' ) }</th>
																			<th className="px-3 py-2 text-left">{ __( 'Post title', 'airygen-seo' ) }</th>
																			<th className="w-[80px] px-3 py-2 text-right">{ __( 'Action', 'airygen-seo' ) }</th>
																		</tr>
																	</thead>
																	<tbody>
																		{ renderCandidateRows( group ) }
																	</tbody>
																</table>
															</div>
														</div>
													</td>
												</tr>
											) : null }
										</Fragment>
									) ) }
								</tbody>
							</table>
						</div>
					) : null }

					<div className="flex items-center justify-between text-xs text-slate-500">
						<span>
							{ __( 'Page', 'airygen-seo' ) } { groupPage } / { groupTotalPages }
						</span>
						<div className="flex items-center gap-2">
							<Button
								variant="secondary"
								className="px-2 py-1 text-xs"
								disabled={ groupsLoading || groupPage <= 1 }
								onClick={ () => setGroupPage( ( current ) => Math.max( 1, current - 1 ) ) }
							>
								{ __( 'Previous', 'airygen-seo' ) }
							</Button>
							<Button
								variant="secondary"
								className="px-2 py-1 text-xs"
								disabled={ groupsLoading || groupPage >= groupTotalPages }
								onClick={ () =>
									setGroupPage( ( current ) => Math.min( groupTotalPages, current + 1 ) )
								}
							>
								{ __( 'Next', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				</section>
			) : null }

			<footer className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div>
					{ footerIsDirty ? (
						<span className="text-sm font-medium text-amber-600">
							{ getUnsavedChangesLabel() }
						</span>
					) : (
						<span className="text-sm text-slate-400">
							{ getAllChangesSavedLabel() }
						</span>
					) }
				</div>
				<div className="flex items-center gap-3">
					<Button
						variant="secondary"
						onClick={ () => setIsResetModalOpen( true ) }
						disabled={ footerIsSaving }
					>
						{ __( 'Reset', 'airygen-seo' ) }
					</Button>
					<Button
						variant="outline"
						onClick={
							mindMapTabActive
								? () => setMindMapSaveSignal( ( current ) => current + 1 )
								: handleSaveSettings
						}
						loading={ footerIsSaving }
						disabled={ ! footerIsDirty || footerIsSaving || groupsTabActive }
					>
						{ getSaveChangesLabel() }
					</Button>
				</div>
			</footer>

			<Modal
				isOpen={ isResetModalOpen }
				onClose={ () => setIsResetModalOpen( false ) }
				title={
					mindMapTabActive
						? __( 'Reset mind map changes', 'airygen-seo' )
						: __( 'Reset settings', 'airygen-seo' )
				}
				maxWidth="max-w-lg"
				footer={
					<div className="flex items-center justify-end gap-3">
						<Button variant="secondary" onClick={ () => setIsResetModalOpen( false ) }>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ () => {
								if ( mindMapTabActive ) {
									setMindMapResetSignal( ( current ) => current + 1 );
								} else {
									onChange( TOPIC_CLUSTER_SETTINGS_DEFAULTS );
								}
								setIsResetModalOpen( false );
							} }
						>
							{ __( 'Reset', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<p className="text-sm text-slate-700">
					{ mindMapTabActive
						? __(
							'Reset the current mind map to the last saved state?',
							'airygen-seo',
						)
						: __(
							'Reset all settings in this module to default values?',
							'airygen-seo',
						) }
				</p>
				<p className="mt-2 text-sm text-slate-500">
					{ mindMapTabActive
						? __(
							'Unsaved node positions and relationships in this mind map will be discarded.',
							'airygen-seo',
						)
						: getResetApplySaveLabel() }
				</p>
			</Modal>

			<Modal
				isOpen={ isCreateGroupModalOpen }
				onClose={ handleCloseCreateGroupModal }
				title={ __( 'Create Group', 'airygen-seo' ) }
				maxWidth="max-w-xl"
				footer={
					<div className="flex items-center justify-end gap-2">
						<Button
							variant="secondary"
							onClick={ handleCloseCreateGroupModal }
							disabled={ isCreatingGroup }
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ () => {
								void handleCreateGroup();
							} }
							loading={ isCreatingGroup }
							disabled={ isCreatingGroup }
						>
							{ __( 'Create', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4">
					<div className="space-y-2">
						<label className="text-sm font-medium text-slate-800" htmlFor="airygen-topic-cluster-group-name">
							{ __( 'Name', 'airygen-seo' ) }
						</label>
						<input
							id="airygen-topic-cluster-group-name"
							className="airygen-field w-full"
							type="text"
							value={ groupName }
							onChange={ ( event ) => setGroupName( event.target.value ) }
							placeholder={ __( 'Enter group name', 'airygen-seo' ) }
						/>
					</div>
					<div className="space-y-2">
						<label className="text-sm font-medium text-slate-800" htmlFor="airygen-topic-cluster-group-description">
							{ __( 'Description', 'airygen-seo' ) }
						</label>
						<textarea
							id="airygen-topic-cluster-group-description"
							className="airygen-field w-full"
							rows={ 4 }
							value={ groupDescription }
							onChange={ ( event ) => setGroupDescription( event.target.value ) }
							placeholder={ __( 'Optional description', 'airygen-seo' ) }
						/>
					</div>
				</div>
			</Modal>

			<Modal
				isOpen={ isEditGroupModalOpen }
				onClose={ handleCloseEditGroupModal }
				title={ __( 'Edit Group', 'airygen-seo' ) }
				maxWidth="max-w-xl"
				footer={
					<div className="flex items-center justify-end gap-2">
						<Button
							variant="secondary"
							onClick={ handleCloseEditGroupModal }
							disabled={ isUpdatingGroup }
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ () => {
								void handleUpdateGroup();
							} }
							loading={ isUpdatingGroup }
							disabled={ isUpdatingGroup }
						>
							{ __( 'Save', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4">
					<div className="space-y-2">
						<label className="text-sm font-medium text-slate-800" htmlFor="airygen-topic-cluster-edit-group-name">
							{ __( 'Name', 'airygen-seo' ) }
						</label>
						<input
							id="airygen-topic-cluster-edit-group-name"
							className="airygen-field w-full"
							type="text"
							value={ editingGroupName }
							onChange={ ( event ) => setEditingGroupName( event.target.value ) }
							placeholder={ __( 'Enter group name', 'airygen-seo' ) }
						/>
					</div>
					<div className="space-y-2">
						<label className="text-sm font-medium text-slate-800" htmlFor="airygen-topic-cluster-edit-group-description">
							{ __( 'Description', 'airygen-seo' ) }
						</label>
						<textarea
							id="airygen-topic-cluster-edit-group-description"
							className="airygen-field w-full"
							rows={ 4 }
							value={ editingGroupDescription }
							onChange={ ( event ) => setEditingGroupDescription( event.target.value ) }
							placeholder={ __( 'Optional description', 'airygen-seo' ) }
						/>
					</div>
				</div>
			</Modal>
		</div>
	);
};

export default TopicClusterPage;

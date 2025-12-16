import HeadingIcon from '../../../components/HeadingIcon';
import { LlmsTxtIcon } from '../../../components/Icons';
import Input from '../../../components/Input';
import Toggle from '../../../components/Toggle';
import Checkbox from '../../../components/Checkbox';
import Button from '../../../components/Button';
import Select from '../../../components/Select';
import Modal from '../../../components/Modal';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { DragEvent } from 'react';

import type { MetaPayload } from '../../../types/api';
import type { LlmsTxtSettings } from '../../../types/settings';
import {
	getNoItemsSelectedLabel,
	getNoItemsYetAddToStartLabel,
	getNoItemsYetAddOneToConfigureLabel,
	getPreviewLoadedLabel,
} from '../../../../shared/i18nPhrases';

type LlmsTxtTabProps = {
	settings: LlmsTxtSettings;
	meta: MetaPayload;
	restBase: string;
	topicClusterEnabled: boolean;
	markdownForAgentsEnabled: boolean;
	onChange: ( next: LlmsTxtSettings ) => void;
};
type LlmsSelection = LlmsTxtSettings['sections'][number];
type LlmsExtension = LlmsTxtSettings['extensions'][number];
type LlmsSelectionOwner =
	| { type: 'base' }
	| { type: 'extension'; extensionId: string };

type LlmsSelectionDraft = {
	title: string;
	description: string;
	postIdsInput: string;
	hidden: boolean;
};

type LlmsLookupPost = {
	id: number;
	title: string;
};

type LlmsLookupResponse = {
	items?: LlmsLookupPost[];
};

const buildSelectionId = ( index: number ): string => `selection_${ index }`;
const TITLE_SUGGESTIONS = [
	'Start Here',
	'Overview',
	'Product / Services',
	'Documentation',
	'Pricing',
	'FAQ',
	'Policies',
	'Important Articles',
];

const buildSelectionDraft = (
	selection: LlmsSelection,
): LlmsSelectionDraft => ( {
	title: selection.title,
	description: selection.description,
	postIdsInput: selection.postIds.join( '\n' ),
	hidden: Boolean( selection.hidden ),
} );
const buildExtensionId = ( index: number ): string => `extension_${ index }`;
const buildExtensionLabel = ( index: number ): string => `Extension ${ index }`;
const buildDefaultExtension = ( index: number ): LlmsExtension => ( {
	id: buildExtensionId( Date.now() + index ),
	title: buildExtensionLabel( index ),
	description: '',
	path: `placeholder${ index }`,
	customDeclaration: '',
	filename: 'llms.txt',
	enabled: true,
	sections: [],
} );
const LLMS_EXTENSION_PATH_PATTERN = /^(?:[A-Za-z0-9]+(?:\/[A-Za-z0-9]+)*)?$/;
const isValidExtensionPath = ( value: string ): boolean =>
	LLMS_EXTENSION_PATH_PATTERN.test( value );
const buildExtensionOutputPath = ( extension: LlmsExtension ): string =>
	extension.path.trim() !== ''
		? `/${ extension.path.trim() }/${ extension.filename }`
		: `/${ extension.filename }`;
const buildDisplayPath = ( path: string, basePath?: string ): string => {
	const normalizedBasePath =
		typeof basePath === 'string' && basePath.trim() !== '' && basePath.trim() !== '/'
			? `/${ basePath.trim().replace( /^\/+|\/+$/g, '' ) }`
			: '';

	return normalizedBasePath !== '' ? `${ normalizedBasePath }${ path }` : path;
};
const buildDisplayPathPrefix = ( basePath?: string ): string =>
	typeof basePath === 'string' && basePath.trim() !== '' && basePath.trim() !== '/'
		? `/${ basePath.trim().replace( /^\/+|\/+$/g, '' ) }/`
		: '/';
const RESERVED_LLMS_PATHS = [ '/llms.txt' ];
const canUseRootLlmsFilename = ( path: string ): boolean => path.trim() !== '';

const LlmsTxtTab = ( {
	settings,
	meta,
	restBase,
	topicClusterEnabled,
	markdownForAgentsEnabled,
	onChange,
}: LlmsTxtTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'extensions' | 'preview'>( 'settings' );
	const [ previewOutput, setPreviewOutput ] = useState( '' );
	const [ previewLoading, setPreviewLoading ] = useState( false );
	const [ previewTarget, setPreviewTarget ] = useState( 'base' );
	const [ statusMessage, setStatusMessage ] = useState( '' );
	const [ statusType, setStatusType ] = useState<'success' | 'error'>( 'success' );
	const [ isSelectionModalOpen, setIsSelectionModalOpen ] = useState( false );
	const [ selectionModalMode, setSelectionModalMode ] = useState<'add' | 'edit'>( 'add' );
	const [ selectionOwner, setSelectionOwner ] = useState<LlmsSelectionOwner>( { type: 'base' } );
	const [ selectionModalTab, setSelectionModalTab ] = useState<'settings' | 'posts' | 'search'>( 'settings' );
	const [ editingSelectionId, setEditingSelectionId ] = useState<string | null>( null );
	const [ editingExtensionId, setEditingExtensionId ] = useState<string | null>( null );
	const [ draggingSelectionId, setDraggingSelectionId ] = useState<string | null>( null );
	const [ postSearchKeyword, setPostSearchKeyword ] = useState( '' );
	const [ postSearchResults, setPostSearchResults ] = useState<LlmsLookupPost[]>( [] );
	const [ selectedPostRows, setSelectedPostRows ] = useState<LlmsLookupPost[]>( [] );
	const [ postSearchLoading, setPostSearchLoading ] = useState( false );
	const [ isTitleSuggestionOpen, setIsTitleSuggestionOpen ] = useState( false );
	const titleSuggestionRef = useRef<HTMLDivElement | null>( null );
	const [ clearCacheLoading, setClearCacheLoading ] = useState( false );
	const [ selectionDraft, setSelectionDraft ] = useState<LlmsSelectionDraft>( {
		title: '',
		description: '',
		postIdsInput: '',
		hidden: false,
	} );

	const updateSettings = ( patch: Partial<LlmsTxtSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const postTypeOptions = useMemo(
		() => meta.postTypes.filter( ( item ) => item.slug !== 'attachment' ),
		[ meta.postTypes ],
	);

	const togglePostType = ( slug: string, checked: boolean ) => {
		const next = new Set( settings.postTypes );
		if ( checked ) {
			next.add( slug );
		} else {
			next.delete( slug );
		}
		updateSettings( { postTypes: Array.from( next ) } );
	};

	const setStatus = ( type: 'success' | 'error', message: string ) => {
		setStatusType( type );
		setStatusMessage( message );
	};

	const handlePreview = async () => {
		try {
			setPreviewLoading( true );
			const response = await apiFetch<{ content?: string }>( {
				path: `${ restBase }/llms-txt/preview`,
				method: 'POST',
				data: {
					settings: {
						enabled: settings.enabled,
						custom_declaration: settings.customDeclaration,
						auto_section_title: settings.autoSectionTitle,
						index_strategy: settings.indexStrategy,
						auto_topic_cluster_groups: settings.autoTopicClusterGroups,
						use_markdown_links: settings.useMarkdownLinks,
						add_to_sitemap: settings.addToSitemap,
						exclude_noindex: settings.excludeNoindex,
						exclude_password_protected: settings.excludePasswordProtected,
						min_word_count: settings.minWordCount,
						post_types: settings.postTypes,
						sections: settings.sections.map( ( section ) => ( {
							id: section.id,
							title: section.title,
							description: section.description,
							post_ids: section.postIds,
							max_items: section.maxItems,
							hidden: section.hidden,
						} ) ),
						extensions: settings.extensions.map( ( extension ) => ( {
							id: extension.id,
							title: extension.title,
							description: extension.description,
							path: extension.path,
							custom_declaration: extension.customDeclaration,
							filename: extension.filename,
							enabled: extension.enabled,
							sections: extension.sections.map( ( section ) => ( {
								id: section.id,
								title: section.title,
								description: section.description,
								post_ids: section.postIds,
								max_items: section.maxItems,
								hidden: section.hidden,
							} ) ),
						} ) ),
					},
					target: previewTarget,
				},
			} );
			setPreviewOutput( typeof response?.content === 'string' ? response.content : '' );
			setStatus( 'success', getPreviewLoadedLabel() );
		} catch ( error ) {
			const message = error instanceof Error
				? error.message
				: __( 'Failed to load preview.', 'airygen-seo' );
			setStatus( 'error', message );
		} finally {
			setPreviewLoading( false );
		}
	};

	const handleClearCache = async () => {
		try {
			setClearCacheLoading( true );
			await apiFetch( {
				path: `${ restBase }/llms-txt/clear-cache`,
				method: 'POST',
			} );
			setStatus( 'success', __( 'LLMs.txt cache cleared.', 'airygen-seo' ) );
		} catch ( error ) {
			setStatus( 'error', error instanceof Error ? error.message : __( 'Unable to clear llms.txt cache.', 'airygen-seo' ) );
		} finally {
			setClearCacheLoading( false );
		}
	};

	const editingExtension = useMemo(
		() => settings.extensions.find( ( extension ) => extension.id === editingExtensionId ) ?? null,
		[ editingExtensionId, settings.extensions ],
	);
	const extensionPathInvalid = useMemo(
		() => ( editingExtension ? ! isValidExtensionPath( editingExtension.path ) : false ),
		[ editingExtension ],
	);
	const extensionPathDuplicate = useMemo( () => {
		if ( ! editingExtension ) {
			return false;
		}

		const currentPath = buildExtensionOutputPath( editingExtension );
		if ( RESERVED_LLMS_PATHS.includes( currentPath ) ) {
			return true;
		}

		return settings.extensions.some(
			( extension ) =>
				extension.id !== editingExtension.id &&
				buildExtensionOutputPath( extension ) === currentPath,
		);
	}, [ editingExtension, settings.extensions ] );
	const previewTargetOptions = useMemo(
		() => [
			{ value: 'base', label: buildDisplayPath( '/llms.txt', meta.llmsBasePath ) },
			...settings.extensions.map( ( extension ) => ( {
				value: extension.id,
				label: buildDisplayPath( buildExtensionOutputPath( extension ), meta.llmsBasePath ),
			} ) ),
		],
		[ meta.llmsBasePath, settings.extensions ],
	);
	let extensionPathHelpMessage = '';
	if ( extensionPathInvalid ) {
		extensionPathHelpMessage = __( 'Use only letters, numbers, and / between folders.', 'airygen-seo' );
	} else if ( extensionPathDuplicate ) {
		extensionPathHelpMessage = __( 'Already duplicated.', 'airygen-seo' );
	}

	const getOwnerSections = ( owner: LlmsSelectionOwner ): LlmsSelection[] =>
		owner.type === 'base'
			? settings.sections
			: settings.extensions.find( ( extension ) => extension.id === owner.extensionId )?.sections ?? [];

	const updateOwnerSections = ( owner: LlmsSelectionOwner, sections: LlmsSelection[] ) => {
		if ( owner.type === 'base' ) {
			updateSettings( { sections } );
			return;
		}

		updateSettings( {
			extensions: settings.extensions.map( ( extension ) =>
				extension.id === owner.extensionId
					? {
						...extension,
						sections,
					}
					: extension,
			),
		} );
	};

	const openAddSelectionModal = ( owner: LlmsSelectionOwner = { type: 'base' } ) => {
		setSelectionModalMode( 'add' );
		setSelectionOwner( owner );
		setEditingSelectionId( null );
		setSelectionModalTab( 'settings' );
		setIsTitleSuggestionOpen( false );
		setPostSearchKeyword( '' );
		setPostSearchResults( [] );
		setSelectionDraft( {
			title: '',
			description: '',
			postIdsInput: '',
			hidden: false,
		} );
		setIsSelectionModalOpen( true );
	};

	const openEditSelectionModal = (
		owner: LlmsSelectionOwner,
		selection: LlmsSelection,
	) => {
		setSelectionModalMode( 'edit' );
		setSelectionOwner( owner );
		setEditingSelectionId( selection.id );
		setSelectionModalTab( 'settings' );
		setIsTitleSuggestionOpen( false );
		setPostSearchKeyword( '' );
		setPostSearchResults( [] );
		setSelectionDraft( buildSelectionDraft( selection ) );
		setIsSelectionModalOpen( true );
	};

	const closeSelectionModal = () => {
		setIsSelectionModalOpen( false );
		setEditingSelectionId( null );
		setSelectionOwner( { type: 'base' } );
		setIsTitleSuggestionOpen( false );
	};

	const saveSelection = () => {
		const sectionTitle = selectionDraft.title.trim();
		if ( sectionTitle === '' ) {
			return;
		}
		const postIds = selectionDraft.postIdsInput
			.split( /\r?\n/ )
			.map( ( value ) => Number.parseInt( value.trim(), 10 ) )
			.filter( ( value ) => Number.isFinite( value ) && value > 0 );
		const nextSection = {
			id:
				selectionModalMode === 'edit' && editingSelectionId
					? editingSelectionId
					: buildSelectionId( getOwnerSections( selectionOwner ).length + 1 ),
			title: sectionTitle,
			description: selectionDraft.description.trim(),
			postIds: Array.from( new Set( postIds ) ),
			maxItems: 100,
			hidden: selectionModalMode === 'edit' ? selectionDraft.hidden : false,
		};

		if ( selectionModalMode === 'add' ) {
			updateOwnerSections( selectionOwner, [ ...getOwnerSections( selectionOwner ), nextSection ] );
			closeSelectionModal();
			return;
		}

		const targetId = editingSelectionId;
		if ( ! targetId ) {
			return;
		}

		updateOwnerSections(
			selectionOwner,
			getOwnerSections( selectionOwner ).map( ( section ) =>
				section.id === targetId ? nextSection : section,
			),
		);
		closeSelectionModal();
	};

	const removeSelection = ( owner: LlmsSelectionOwner, selectionId: string ) => {
		updateOwnerSections(
			owner,
			getOwnerSections( owner ).filter( ( section ) => section.id !== selectionId ),
		);
	};

	const parseDraftPostIds = ( value: string ): number[] =>
		Array.from(
			new Set(
				value
					.split( /\r?\n/ )
					.map( ( item ) => Number.parseInt( item.trim(), 10 ) )
					.filter( ( item ) => Number.isFinite( item ) && item > 0 ),
			),
		);

	const replaceDraftPostIds = ( postIds: number[] ) => {
		setSelectionDraft( ( prev ) => ( {
			...prev,
			postIdsInput: postIds.join( '\n' ),
		} ) );
	};

	const lookupPosts = async ( query: string, ids: number[] ): Promise<LlmsLookupPost[]> => {
		const params = new URLSearchParams();
		if ( query.trim() !== '' ) {
			params.set( 'q', query.trim() );
		}
		if ( ids.length > 0 ) {
			params.set( 'ids', ids.join( ',' ) );
		}
		const queryString = params.toString();
		const path = `${ restBase }/llms-txt/posts${ queryString !== '' ? `?${ queryString }` : '' }`;
		const response = await apiFetch<LlmsLookupResponse>( { path, method: 'GET' } );
		return Array.isArray( response.items ) ? response.items : [];
	};

	const selectedPostIds = useMemo(
		() => parseDraftPostIds( selectionDraft.postIdsInput ),
		[ selectionDraft.postIdsInput ],
	);

	useEffect( () => {
		if ( ! isSelectionModalOpen ) {
			return;
		}

		const run = async () => {
			if ( selectedPostIds.length === 0 ) {
				setSelectedPostRows( [] );
				return;
			}
			try {
				const params = new URLSearchParams();
				params.set( 'ids', selectedPostIds.join( ',' ) );
				const response = await apiFetch<LlmsLookupResponse>( {
					path: `${ restBase }/llms-txt/posts?${ params.toString() }`,
					method: 'GET',
				} );
				const rows = Array.isArray( response.items ) ? response.items : [];
				setSelectedPostRows( rows );
			} catch {
				setSelectedPostRows(
					selectedPostIds.map( ( postId ) => ( {
						id: postId,
						title: '',
					} ) ),
				);
			}
		};

		void run();
	}, [ isSelectionModalOpen, restBase, selectedPostIds ] );

	useEffect( () => {
		if ( ! isTitleSuggestionOpen ) {
			return;
		}

		const handleOutside = ( event: MouseEvent ) => {
			if ( titleSuggestionRef.current?.contains( event.target as Node ) ) {
				return;
			}
			setIsTitleSuggestionOpen( false );
		};

		document.addEventListener( 'mousedown', handleOutside );
		return () => document.removeEventListener( 'mousedown', handleOutside );
	}, [ isTitleSuggestionOpen ] );

	const runPostSearch = async () => {
		setPostSearchLoading( true );
		try {
			const rows = await lookupPosts( postSearchKeyword, [] );
			setPostSearchResults( rows );
		} catch {
			setPostSearchResults( [] );
		} finally {
			setPostSearchLoading( false );
		}
	};

	const addPostToSelection = ( postId: number ) => {
		const next = Array.from( new Set( [ ...selectedPostIds, postId ] ) );
		replaceDraftPostIds( next );
	};

	const removePostFromSelection = ( postId: number ) => {
		const next = selectedPostIds.filter( ( id ) => id !== postId );
		replaceDraftPostIds( next );
	};

	const addExtension = () => {
		const nextExtension = buildDefaultExtension( settings.extensions.length + 1 );
		updateSettings( {
			extensions: [ ...settings.extensions, nextExtension ],
		} );
		setEditingExtensionId( nextExtension.id );
	};

	const updateExtension = ( extensionId: string, patch: Partial<LlmsExtension> ) => {
		updateSettings( {
			extensions: settings.extensions.map( ( extension ) =>
				extension.id === extensionId
					? {
						...extension,
						...patch,
					}
					: extension,
			),
		} );
	};

	const removeExtension = ( extensionId: string ) => {
		updateSettings( {
			extensions: settings.extensions.filter( ( extension ) => extension.id !== extensionId ),
		} );
		if ( editingExtensionId === extensionId ) {
			setEditingExtensionId( null );
		}
	};

	const getVisibleSelections = ( sections: LlmsSelection[] ) =>
		sections.filter( ( selection ) => ! selection.hidden );
	const getHiddenSelections = ( sections: LlmsSelection[] ) =>
		sections.filter( ( selection ) => Boolean( selection.hidden ) );

	const moveSelection = (
		owner: LlmsSelectionOwner,
		selectionId: string,
		targetZone: 'visible' | 'hidden',
		targetSelectionId?: string,
	) => {
		const ownerSections = getOwnerSections( owner );
		const source = ownerSections.find( ( section ) => section.id === selectionId );
		if ( ! source ) {
			return;
		}

		const visibleOwnerSelections = getVisibleSelections( ownerSections );
		const hiddenOwnerSelections = getHiddenSelections( ownerSections );
		const sourceVisible = visibleOwnerSelections.filter( ( section ) => section.id !== selectionId );
		const sourceHidden = hiddenOwnerSelections.filter( ( section ) => section.id !== selectionId );
		const sectionToMove = { ...source, hidden: targetZone === 'hidden' };
		const targetList = targetZone === 'visible' ? sourceVisible : sourceHidden;

		if ( ! targetSelectionId ) {
			targetList.push( sectionToMove );
		} else {
			const targetIndex = targetList.findIndex( ( section ) => section.id === targetSelectionId );
			if ( targetIndex === -1 ) {
				targetList.push( sectionToMove );
			} else {
				targetList.splice( targetIndex, 0, sectionToMove );
			}
		}

		const nextSections =
			targetZone === 'visible'
				? [ ...targetList, ...sourceHidden ]
				: [ ...sourceVisible, ...targetList ];

		updateOwnerSections( owner, nextSections );
	};

	const resolveDraggingSelectionId = ( event: DragEvent<HTMLElement> ): string => {
		const payload = event.dataTransfer.getData( 'text/plain' );
		return draggingSelectionId ?? payload;
	};

	const handleSelectionZoneDrop = (
		event: DragEvent<HTMLDivElement>,
		owner: LlmsSelectionOwner,
		targetZone: 'visible' | 'hidden',
	) => {
		event.preventDefault();
		const selectionId = resolveDraggingSelectionId( event );
		if ( selectionId ) {
			moveSelection( owner, selectionId, targetZone );
		}
		setDraggingSelectionId( null );
	};

	const handleSelectionCardDrop = (
		event: DragEvent<HTMLDivElement>,
		owner: LlmsSelectionOwner,
		targetZone: 'visible' | 'hidden',
		targetSelectionId: string,
	) => {
		event.preventDefault();
		event.stopPropagation();
		const selectionId = resolveDraggingSelectionId( event );
		if ( selectionId && selectionId !== targetSelectionId ) {
			moveSelection( owner, selectionId, targetZone, targetSelectionId );
		}
		setDraggingSelectionId( null );
	};

	const renderSelectionCard = (
		owner: LlmsSelectionOwner,
		selection: LlmsSelection,
		zone: 'visible' | 'hidden',
	) => (
		<div
			key={ `${ zone }-${ selection.id }` }
			className="cursor-grab rounded-md border border-slate-300 bg-white p-3 shadow-sm active:cursor-grabbing"
			draggable
			onDragStart={ ( event ) => {
				setDraggingSelectionId( selection.id );
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData( 'text/plain', selection.id );
			} }
			onDragEnd={ () => setDraggingSelectionId( null ) }
			onDragOver={ ( event ) => {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';
			} }
			onDrop={ ( event ) => handleSelectionCardDrop( event, owner, zone, selection.id ) }
		>
			<div className="flex items-center justify-between gap-2">
				<div className="min-w-0">
					<p className="truncate text-sm font-medium text-slate-900">{ selection.title }</p>
					<p className="mt-1 line-clamp-2 text-xs text-slate-500">
						{ selection.description.trim() !== ''
							? selection.description
							: __( 'No description', 'airygen-seo' ) }
					</p>
				</div>
				<div className="flex items-center gap-2">
					<span className="text-xs text-slate-500">
						{ selection.postIds.length > 0
							? `${ selection.postIds.length } ${ __( 'posts', 'airygen-seo' ) }`
							: getNoItemsSelectedLabel( __( 'posts', 'airygen-seo' ) ) }
					</span>
					<Button
						variant="secondary"
						className="text-xs"
						onClick={ () => openEditSelectionModal( owner, selection ) }
					>
						{ __( 'Edit', 'airygen-seo' ) }
					</Button>
					{ zone === 'hidden' ? (
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ () => removeSelection( owner, selection.id ) }
						>
							{ __( 'Delete', 'airygen-seo' ) }
						</Button>
					) : null }
				</div>
			</div>
		</div>
	);

	const renderSelectionsManager = (
		owner: LlmsSelectionOwner,
		sections: LlmsSelection[],
		description: string,
	) => {
		const visibleOwnerSelections = getVisibleSelections( sections );
		const hiddenOwnerSelections = getHiddenSelections( sections );

		return (
			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex items-start justify-between gap-4">
					<div className="space-y-1">
						<h3 className="text-lg font-semibold text-gray-800">{ __( 'Selections', 'airygen-seo' ) }</h3>
						<p className="text-sm text-slate-500">{ description }</p>
					</div>
					<Button
						variant="secondary"
						className="text-xs"
						onClick={ () => openAddSelectionModal( owner ) }
						data-airygen-e2e={ `button-add-selection-${ owner }` }
					>
						{ __( 'Add selection', 'airygen-seo' ) }
					</Button>
				</div>
				<div className="grid gap-4 lg:grid-cols-3">
					<div className="lg:col-span-2">
						<div className="rounded-lg border border-dashed border-slate-300 p-4">
							<div data-airygen-e2e="selection-zone-visible">
								<div className="mb-3">
									<h4 className="text-sm font-medium text-slate-900">{ __( 'Selections', 'airygen-seo' ) }</h4>
									<p className="mt-1 text-xs text-slate-500">{ __( 'Cards in this area are included in llms.txt output.', 'airygen-seo' ) }</p>
								</div>
								<div
									data-airygen-e2e="selection-list-visible"
									className="space-y-3"
									onDragOver={ ( event ) => {
										event.preventDefault();
										event.dataTransfer.dropEffect = 'move';
									} }
									onDrop={ ( event ) => handleSelectionZoneDrop( event, owner, 'visible' ) }
								>
									{ visibleOwnerSelections.length > 0
										? visibleOwnerSelections.map( ( selection ) => renderSelectionCard( owner, selection, 'visible' ) )
										: (
											<p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-6 text-center text-xs text-slate-500">
												{ getNoItemsYetAddToStartLabel(
													__( 'selections', 'airygen-seo' ),
													__( 'selection', 'airygen-seo' ),
												) }
											</p>
										) }
								</div>
							</div>
						</div>
					</div>
					<div>
						<div className="rounded-lg border border-dashed border-slate-300 p-4">
							<div data-airygen-e2e="selection-zone-hidden">
								<div className="mb-3">
									<h4 className="text-sm font-medium text-slate-900">{ __( 'Hidden selections staging area', 'airygen-seo' ) }</h4>
									<p className="mt-1 text-xs text-slate-500">{ __( 'Store selections here when they should not appear in output.', 'airygen-seo' ) }</p>
								</div>
								<div
									data-airygen-e2e="selection-list-hidden"
									className="space-y-3"
									onDragOver={ ( event ) => {
										event.preventDefault();
										event.dataTransfer.dropEffect = 'move';
									} }
									onDrop={ ( event ) => handleSelectionZoneDrop( event, owner, 'hidden' ) }
								>
									{ hiddenOwnerSelections.length > 0
										? hiddenOwnerSelections.map( ( selection ) => renderSelectionCard( owner, selection, 'hidden' ) )
										: (
											<p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-6 text-center text-xs text-slate-500">
												{ __( 'No hidden selections.', 'airygen-seo' ) }
											</p>
										) }
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		);
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<LlmsTxtIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">{ __( 'LLMs.txt', 'airygen-seo' ) }</div>
					<div className="airygen_h1_description">{ __( 'An important GEO factor. Configure it carefully to improve the chance of being recommended by LLMs.', 'airygen-seo' ) }</div>
				</div>
			</div>

			<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
				<button
					type="button"
					data-airygen-e2e="tab-settings"
					className={
						'settings' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'settings' ) }
				>
					{ __( 'Settings', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-extensions"
					className={
						'extensions' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'extensions' ) }
				>
					{ __( 'Extensions', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-preview"
					className={
						'preview' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
			</div>

			{ activeTab === 'settings' ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex items-start justify-between gap-3">
							<div className="space-y-1">
								<div className="airygen_h2_title">{ __( 'Settings', 'airygen-seo' ) }</div>
								<p className="text-sm text-slate-500">{ __( 'Configure curated LLM index output and content selection rules.', 'airygen-seo' ) }</p>
							</div>
							<Button
								variant="secondary"
								className="text-xs"
								onClick={ handleClearCache }
								loading={ clearCacheLoading }
								data-airygen-e2e="button-clear-cache-llms-txt"
							>
								{ __( 'Clear cache', 'airygen-seo' ) }
							</Button>
						</div>
						<div className="grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Enable llms.txt output', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Enable llms.txt output', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.enabled }
										onChange={ ( checked ) => updateSettings( { enabled: checked } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">{ __( 'Publish /llms.txt so AI crawlers can discover your content.', 'airygen-seo' ) }</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Select
									label={ __( 'Index strategy', 'airygen-seo' ) }
									labelTip={
										<div className="space-y-2 text-xs text-slate-700">
											<p>{ __( 'llms.txt is a precise instruction file for AI, not a sitemap.', 'airygen-seo' ) }</p>
											<p>
												{ __( 'If your site has many articles,', 'airygen-seo' ) }{ ' ' }
												<strong>{ __( 'Topic Cluster', 'airygen-seo' ) }</strong>
												{ ' + ' }
												<strong>{ __( 'Selections only', 'airygen-seo' ) }</strong>
												{ ' ' }
												{ __( 'is recommended.', 'airygen-seo' ) }
											</p>
											<p>{ __( 'Using this module as a sitemap replacement has no value. Disable it instead.', 'airygen-seo' ) }</p>
										</div>
									}
									value={ settings.indexStrategy }
									options={ [
										{ value: 'curated_only', label: __( 'Selections only', 'airygen-seo' ) },
										{ value: 'curated_plus_auto', label: __( 'Selections + auto', 'airygen-seo' ) },
										{ value: 'auto_only', label: __( 'Auto only', 'airygen-seo' ) },
									] }
									onChange={ ( value ) => {
										const strategy =
											value === 'curated_only' || value === 'auto_only' || value === 'curated_plus_auto'
												? value
												: 'curated_plus_auto';
										updateSettings( { indexStrategy: strategy } );
									} }
									help={ __( 'Choose how selected items and auto-selected items are combined.', 'airygen-seo' ) }
								/>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Use markdown post links', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Use markdown post links', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.useMarkdownLinks }
										disabled={ ! markdownForAgentsEnabled }
										onChange={ ( checked ) => updateSettings( { useMarkdownLinks: checked } ) }
									/>
								</div>
								<p
									className="text-xs text-slate-500"
									// Help content is controlled by plugin authors.
									// eslint-disable-next-line react/no-danger
									dangerouslySetInnerHTML={ {
										__html: markdownForAgentsEnabled
											? sprintf(
												/* translators: %s: module name. */
												__( 'Use markdown URLs in llms.txt when %s supports that post type.', 'airygen-seo' ),
												`<strong>${ __( 'Markdown for Agents', 'airygen-seo' ) }</strong>`,
											)
											: sprintf(
												/* translators: %s: module name. */
												__( 'This option depends on %s. Enable it to use markdown post links.', 'airygen-seo' ),
												`<strong>${ __( 'Markdown for Agents', 'airygen-seo' ) }</strong>`,
											),
									} }
								/>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Add to sitemap', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Add to sitemap', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.addToSitemap }
										onChange={ ( checked ) => updateSettings( { addToSitemap: checked } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">{ __( 'Include /llms.txt and enabled extensions in the XML sitemap index.', 'airygen-seo' ) }</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Auto-add Topic Cluster groups', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Auto-add Topic Cluster groups', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.autoTopicClusterGroups }
										disabled={ ! topicClusterEnabled }
										onChange={ ( checked ) => updateSettings( { autoTopicClusterGroups: checked } ) }
									/>
								</div>
								<p
									className="text-xs text-slate-500"
									// Help content is controlled by plugin authors.
									// eslint-disable-next-line react/no-danger
									dangerouslySetInnerHTML={ {
										__html: topicClusterEnabled
											? sprintf(
												/* translators: %s: module name. */
												__( 'Automatically include %s groups as highlighted sections.', 'airygen-seo' ),
												`<strong>${ __( 'Topic Cluster', 'airygen-seo' ) }</strong>`,
											)
											: sprintf(
												/* translators: %s: module name. */
												__( 'This option depends on %s. Enable it to use it.', 'airygen-seo' ),
												`<strong>${ __( 'Topic Cluster', 'airygen-seo' ) }</strong>`,
											),
									} }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Auto-loaded section title', 'airygen-seo' ) }
									value={ settings.autoSectionTitle }
									onChange={ ( value ) => updateSettings( { autoSectionTitle: value } ) }
									help={ __( 'This title is used for the automatically loaded items section.', 'airygen-seo' ) }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 md:col-span-2">
								<Input
									label={ __( 'Custom declaration', 'airygen-seo' ) }
									labelTip={
										<div className="space-y-2 text-xs text-slate-700">
											<p>{ __( 'If your site welcomes automated AI agents, such as auto-purchase flows, describe the process here.', 'airygen-seo' ) }</p>
										</div>
									}
									value={ settings.customDeclaration }
									onChange={ ( value ) => updateSettings( { customDeclaration: value } ) }
									help={ __( 'Optional text added under the llms.txt heading.', 'airygen-seo' ) }
								/>
							</div>
						</div>
					</section>

					<section className="rounded-xl border border-slate-200 bg-white p-4">
						<h3 className="text-lg font-semibold text-gray-800">{ __( 'Scope', 'airygen-seo' ) }</h3>
						<p className="mt-1 text-sm text-slate-500">{ __( 'Select post types used for auto-selection.', 'airygen-seo' ) }</p>
						<div className="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
							{ postTypeOptions.map( ( postType ) => (
								<div key={ postType.slug } className="rounded-lg border border-slate-200 p-3">
									<Checkbox
										label={ postType.label }
										checked={ settings.postTypes.includes( postType.slug ) }
										onChange={ ( checked ) => togglePostType( postType.slug, checked ) }
									/>
								</div>
							) ) }
						</div>
					</section>

					<section className="rounded-xl border border-slate-200 bg-white p-4">
						<h3 className="text-lg font-semibold text-gray-800">{ __( 'Exclusions', 'airygen-seo' ) }</h3>
						<p className="mt-1 text-sm text-slate-500">{ __( 'Control what content should be excluded from LLMs.txt output.', 'airygen-seo' ) }</p>
						<div className="mt-4 grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Exclude noindex', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Exclude noindex', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.excludeNoindex }
										onChange={ ( checked ) => updateSettings( { excludeNoindex: checked } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">{ __( 'Skip posts that contain a noindex robots directive.', 'airygen-seo' ) }</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Exclude password-protected', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Exclude password-protected', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.excludePasswordProtected }
										onChange={ ( checked ) => updateSettings( { excludePasswordProtected: checked } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">{ __( 'Skip posts that require a password.', 'airygen-seo' ) }</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Exclude posts below word count', 'airygen-seo' ) }
									type="number"
									min={ 0 }
									max={ 5000 }
									value={ String( settings.minWordCount ) }
									onChange={ ( value ) => {
										const parsed = Number.parseInt( value, 10 );
										if ( Number.isFinite( parsed ) ) {
											updateSettings( { minWordCount: Math.max( 0, Math.min( 5000, parsed ) ) } );
										}
									} }
									help={ __( 'Exclude auto-selected posts with fewer words than this value.', 'airygen-seo' ) }
								/>
							</div>
						</div>
					</section>

					{ renderSelectionsManager(
						{ type: 'base' },
						settings.sections,
						__( 'Arrange curated selection cards for llms.txt output.', 'airygen-seo' ),
					) }
				</>
			) : null }

			{ activeTab === 'extensions' ? (
				<div className="space-y-5">
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex items-start justify-between gap-4">
							<div className="space-y-1">
								<div className="airygen_h2_title">{ __( 'Extensions', 'airygen-seo' ) }</div>
								<p className="text-sm text-slate-500">{ __( 'Add extension-specific selection overrides for llms.txt output.', 'airygen-seo' ) }</p>
							</div>
							<Button
								variant="secondary"
								className="text-xs"
								onClick={ addExtension }
								data-airygen-e2e="button-add-extension"
							>
								{ __( 'Add extension', 'airygen-seo' ) }
							</Button>
						</div>
						{ settings.extensions.length > 0 ? (
							<div className="space-y-3">
								{ settings.extensions.map( ( extension, index ) => (
									<div
										key={ extension.id }
										className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 p-4"
									>
										<div className="min-w-0 flex-1">
											<div className="flex min-w-0 flex-wrap items-center gap-2">
												<p className="truncate text-sm font-medium text-slate-900">
													{ extension.title || buildExtensionLabel( index + 1 ) }
												</p>
												{ canUseRootLlmsFilename( extension.path ) || extension.filename !== 'llms.txt' ? (
													<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
														{ buildDisplayPath( buildExtensionOutputPath( extension ), meta.llmsBasePath ) }
													</span>
												) : null }
											</div>
											<p className="mt-1 text-xs text-slate-500">
												{ extension.enabled
													? __( 'Enabled override set.', 'airygen-seo' )
													: __( 'Disabled override set.', 'airygen-seo' ) }
											</p>
										</div>
										<div className="flex items-center gap-2">
											<Toggle
												label={ __( 'Enable extension', 'airygen-seo' ) }
												hideLabelText
												checked={ extension.enabled }
												onChange={ ( value ) => updateExtension( extension.id, { enabled: value } ) }
											/>
											<Button variant="secondary" className="text-xs" onClick={ () => setEditingExtensionId( extension.id ) }>
												{ __( 'Edit', 'airygen-seo' ) }
											</Button>
											<Button variant="secondary" className="text-xs" onClick={ () => removeExtension( extension.id ) }>
												{ __( 'Delete', 'airygen-seo' ) }
											</Button>
										</div>
									</div>
								) ) }
							</div>
						) : (
							<p className="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
								{ getNoItemsYetAddOneToConfigureLabel(
									__( 'extensions', 'airygen-seo' ),
									__( 'an extension', 'airygen-seo' ),
								) }
							</p>
						) }
					</section>

					{ editingExtension ? (
						<>
							<div className="relative my-6">
								<div className="border-b-4 border-dashed border-slate-300" />
								<span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-slate-100 px-3 text-base font-semibold text-slate-800">
									{ __( 'Extension', 'airygen-seo' ) }: { editingExtension.title }
								</span>
								<div className="absolute right-0 top-1/2 -translate-y-1/2 bg-slate-100 pl-3">
									<Button
										variant="secondary"
										className="text-xs"
										onClick={ () => setEditingExtensionId( null ) }
									>
										{ __( 'Close', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
							<p className="mb-4 text-center text-sm text-slate-500">
								{ __( 'Extension selections override the base llms.txt selections when used.', 'airygen-seo' ) }
							</p>
							<section className="rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">
										{ __( 'Settings', 'airygen-seo' ) }
									</div>
									<p className="text-sm text-slate-500">{ __( 'Configure extension basics before arranging selection overrides.', 'airygen-seo' ) }</p>
								</div>
								<div className="mt-4 grid gap-4 md:grid-cols-2">
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Title', 'airygen-seo' ) }
											value={ editingExtension.title }
											onChange={ ( value ) => updateExtension( editingExtension.id, { title: value } ) }
											help={ __( 'Used as the H1 title for this extension output.', 'airygen-seo' ) }
										/>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Description', 'airygen-seo' ) }
											value={ editingExtension.description }
											onChange={ ( value ) => updateExtension( editingExtension.id, { description: value } ) }
											help={ __( 'Used as the quoted description line under the H1 title.', 'airygen-seo' ) }
										/>
									</div>
								</div>
								<div className="mt-4 grid gap-4 md:grid-cols-2">
									<div className="rounded-lg border border-slate-200 p-4">
										<label className="block text-sm font-medium text-gray-800" htmlFor={ `llms-extension-path-${ editingExtension.id }` }>
											{ __( 'File path', 'airygen-seo' ) }
										</label>
										<div className="mt-2 flex items-center gap-2">
											<span className="text-sm font-medium text-slate-700">
												{ buildDisplayPathPrefix( meta.llmsBasePath ) }
											</span>
											<input
												id={ `llms-extension-path-${ editingExtension.id }` }
												className={
													extensionPathInvalid || extensionPathDuplicate
														? 'airygen-field border-red-500 focus:border-red-500 focus:ring-red-500'
														: 'airygen-field'
												}
												value={ editingExtension.path }
												onChange={ ( event ) =>
													updateExtension( editingExtension.id, {
														path: event.target.value.replace( /^\/+|\/+$/g, '' ),
													} )
												}
											/>
											<span className="text-sm font-medium text-slate-700">/</span>
											<Select
												value={ editingExtension.filename }
												options={ [
													{ value: 'llms-small.txt', label: 'llms-small.txt' },
													{ value: 'llms-full.txt', label: 'llms-full.txt' },
													{
														value: 'llms.txt',
														label: 'llms.txt',
														disabled: ! canUseRootLlmsFilename( editingExtension.path ),
													},
												] }
												onChange={ ( value ) =>
													updateExtension( editingExtension.id, {
														filename:
															value === 'llms-small.txt' || value === 'llms-full.txt'
																? value
																: 'llms.txt',
													} )
												}
												className="min-w-[180px]"
											/>
										</div>
										{ extensionPathHelpMessage !== '' ? (
											<p className="mt-2 text-xs text-red-500">{ extensionPathHelpMessage }</p>
										) : null }
										<p className={ extensionPathInvalid || extensionPathDuplicate ? 'hidden' : 'mt-2 text-xs text-slate-500' }>
											{ __( 'Set the output path using a folder path and file name.', 'airygen-seo' ) }
										</p>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Custom declaration', 'airygen-seo' ) }
											value={ editingExtension.customDeclaration }
											onChange={ ( value ) => updateExtension( editingExtension.id, { customDeclaration: value } ) }
											help={ __( 'Optional instructions included only in this extension output.', 'airygen-seo' ) }
										/>
									</div>
								</div>
							</section>
							{ renderSelectionsManager(
								{ type: 'extension', extensionId: editingExtension.id },
								editingExtension.sections,
								__( 'Arrange curated selection cards for this extension override.', 'airygen-seo' ),
							) }
						</>
					) : null }
				</div>
			) : null }

			{ activeTab === 'preview' ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">{ __( 'Preview', 'airygen-seo' ) }</div>
						<p className="text-sm text-slate-500">{ __( 'Preview generated llms.txt output from current curated and scope settings.', 'airygen-seo' ) }</p>
					</div>
					<div className="rounded-lg border border-slate-200 p-4">
						<div className="flex flex-wrap items-end gap-2">
							<div className="min-w-[220px] flex-1">
								<Select
									value={ previewTarget }
									options={ previewTargetOptions }
									onChange={ ( value ) => setPreviewTarget( value ) }
								/>
							</div>
							<Button
								variant="secondary"
								className="text-xs"
								onClick={ () => handlePreview() }
								loading={ previewLoading }
								data-airygen-e2e="button-preview-llms-txt"
							>
								{ __( 'Preview llms.txt', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
					{ statusMessage ? (
						<p className={ statusType === 'error' ? 'text-sm text-red-600' : 'text-sm text-emerald-600' }>
							{ statusMessage }
						</p>
					) : null }
					<div className="rounded-lg border border-slate-200 p-4">
						<label htmlFor="airygen-llms-output" className="block text-sm font-medium text-gray-800">
							{ __( 'Output', 'airygen-seo' ) }
						</label>
						<textarea
							id="airygen-llms-output"
							className="mt-2 airygen-field h-80 w-full font-mono text-xs"
							readOnly
							value={ previewOutput }
						/>
					</div>
				</section>
			) : null }

			<Modal
				isOpen={ isSelectionModalOpen }
				onClose={ closeSelectionModal }
				title={
					selectionModalMode === 'add'
						? __( 'Add selection', 'airygen-seo' )
						: __( 'Edit selection', 'airygen-seo' )
				}
				maxWidth="max-w-3xl"
				bodyClassName="min-h-[360px] pt-3"
				footer={
					<div className="flex justify-end gap-2 bg-slate-50" data-airygen-e2e="modal-selection-actions">
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ closeSelectionModal }
							data-airygen-e2e="button-cancel-selection"
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="primary"
							className="text-xs"
							onClick={ saveSelection }
							data-airygen-e2e="button-save-selection"
						>
							{ selectionModalMode === 'add'
								? __( 'Add selection', 'airygen-seo' )
								: __( 'Save selection', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4" data-airygen-e2e="modal-selection">
					<div className="border-b border-slate-200">
						<div className="flex flex-wrap gap-4" data-airygen-e2e="modal-selection-tabs">
							<button
								type="button"
								data-airygen-e2e="modal-selection-tab-settings"
								className={
									selectionModalTab === 'settings'
										? 'border-b-2 border-sky-500 px-0 pb-2 text-xs font-medium text-slate-900'
										: 'border-b-2 border-transparent px-0 pb-2 text-xs font-medium text-slate-500 hover:text-slate-700'
								}
								onClick={ () => setSelectionModalTab( 'settings' ) }
							>
								{ __( 'Settings', 'airygen-seo' ) }
							</button>
							<button
								type="button"
								data-airygen-e2e="modal-selection-tab-posts"
								className={
									selectionModalTab === 'posts'
										? 'border-b-2 border-sky-500 px-0 pb-2 text-xs font-medium text-slate-900'
										: 'border-b-2 border-transparent px-0 pb-2 text-xs font-medium text-slate-500 hover:text-slate-700'
								}
								onClick={ () => setSelectionModalTab( 'posts' ) }
							>
								{ __( 'Selected Posts', 'airygen-seo' ) }
							</button>
							<button
								type="button"
								data-airygen-e2e="modal-selection-tab-search"
								className={
									selectionModalTab === 'search'
										? 'border-b-2 border-sky-500 px-0 pb-2 text-xs font-medium text-slate-900'
										: 'border-b-2 border-transparent px-0 pb-2 text-xs font-medium text-slate-500 hover:text-slate-700'
								}
								onClick={ () => setSelectionModalTab( 'search' ) }
							>
								{ __( 'Search posts', 'airygen-seo' ) }
							</button>
						</div>
					</div>
					{ selectionModalTab === 'settings' ? (
						<div className="space-y-4">
							<div className="space-y-2">
								<div className="flex items-center justify-between gap-3">
									<label className="text-sm font-medium text-gray-800" htmlFor="airygen-llms-selection-title">
										{ __( 'Title', 'airygen-seo' ) }
									</label>
									<div className="relative" ref={ titleSuggestionRef }>
										<button
											type="button"
											className="text-xs font-medium text-sky-600 hover:text-sky-700"
											onClick={ () => setIsTitleSuggestionOpen( ( prev ) => ! prev ) }
										>
											{ __( 'Suggestion', 'airygen-seo' ) }
										</button>
										{ isTitleSuggestionOpen ? (
											<div className="absolute right-0 top-full z-20 mt-1 w-52 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
												{ TITLE_SUGGESTIONS.map( ( suggestion ) => (
													<button
														key={ suggestion }
														type="button"
														className="block w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50"
														onClick={ () => {
															setSelectionDraft( ( prev ) => ( { ...prev, title: suggestion } ) );
															setIsTitleSuggestionOpen( false );
														} }
													>
														{ suggestion }
													</button>
												) ) }
											</div>
										) : null }
									</div>
								</div>
								<input
									id="airygen-llms-selection-title"
									className="airygen-field w-full"
									value={ selectionDraft.title }
									onChange={ ( event ) =>
										setSelectionDraft( ( prev ) => ( { ...prev, title: event.target.value } ) )
									}
								/>
								<p className="text-xs text-slate-500">
									{ __( 'This title is the section label that LLMs will read in llms.txt.', 'airygen-seo' ) }
								</p>
							</div>
							<div>
								<label
									className="block text-sm font-medium text-gray-800"
									htmlFor="airygen-llms-selection-description"
								>
									{ __( 'Description', 'airygen-seo' ) }
								</label>
								<textarea
									id="airygen-llms-selection-description"
									className="mt-2 airygen-field h-20 w-full"
									value={ selectionDraft.description }
									onChange={ ( event ) =>
										setSelectionDraft( ( prev ) => ( {
											...prev,
											description: event.target.value,
										} ) )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'This description is included in the generated llms.txt file.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					) : null }
					{ selectionModalTab === 'posts' ? (
						<div className="space-y-3">
							<div className="max-h-[320px] space-y-4 overflow-y-auto pr-1">
								<div className="mt-2 flex flex-col gap-2">
									{ selectedPostRows.length > 0 ? (
										selectedPostRows.map( ( post ) => (
											<div
												key={ `selected-${ post.id }` }
												className="flex items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2"
											>
												<p className="min-w-0 truncate text-sm text-slate-700">
													{ `${ post.title || '—' } (${ post.id })` }
												</p>
												<button
													type="button"
													className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-slate-200 text-xs text-slate-500 hover:border-slate-300 hover:text-slate-700"
													aria-label={ __( 'Remove', 'airygen-seo' ) }
													onClick={ () => removePostFromSelection( post.id ) }
												>
													<span
														className="dashicons dashicons-no-alt m-0 block h-[14px] w-[14px] text-[14px] leading-[14px]"
														aria-hidden="true"
													/>
												</button>
											</div>
										) )
									) : (
										<p className="text-sm text-slate-500">{ getNoItemsSelectedLabel( __( 'posts', 'airygen-seo' ) ) }</p>
									) }
								</div>
							</div>
						</div>
					) : null }
					{ selectionModalTab === 'search' ? (
						<div className="space-y-3">
							<div className="grid gap-3 md:grid-cols-[1fr_auto]">
								<Input
									value={ postSearchKeyword }
									onChange={ setPostSearchKeyword }
									placeholder={ __( 'Search by post title', 'airygen-seo' ) }
								/>
								<div className="flex items-end">
									<Button
										variant="secondary"
										className="text-xs"
										onClick={ runPostSearch }
										loading={ postSearchLoading }
									>
										{ __( 'Search', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
							<div className="max-h-[320px] overflow-y-auto pr-1">
								<div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
									<table className="min-w-full table-fixed divide-y divide-slate-200 text-sm">
										<thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
											<tr>
												<th className="w-24 px-3 py-2 text-left">{ __( 'Post ID', 'airygen-seo' ) }</th>
												<th className="px-3 py-2 text-left">{ __( 'Post title', 'airygen-seo' ) }</th>
												<th className="w-28 px-3 py-2 text-right">{ __( 'Action', 'airygen-seo' ) }</th>
											</tr>
										</thead>
										<tbody className="divide-y divide-slate-200 text-slate-700">
											{ postSearchResults.length > 0
												? postSearchResults.map( ( post ) => {
													const alreadyAdded = selectedPostIds.includes( post.id );
													return (
														<tr key={ `search-${ post.id }` }>
															<td className="px-3 py-2 text-slate-700">{ post.id }</td>
															<td className="px-3 py-2 text-slate-700">{ post.title || '—' }</td>
															<td className="px-3 py-2 text-right">
																<Button
																	variant="secondary"
																	className="text-xs"
																	disabled={ alreadyAdded }
																	onClick={ () => addPostToSelection( post.id ) }
																>
																	{ alreadyAdded
																		? __( 'Added', 'airygen-seo' )
																		: __( 'Add', 'airygen-seo' ) }
																</Button>
															</td>
														</tr>
													);
												} )
												: (
													<tr>
														<td colSpan={ 3 } className="px-3 py-3 text-slate-500">
															{ __( 'No results.', 'airygen-seo' ) }
														</td>
													</tr>
												) }
										</tbody>
									</table>
								</div>
							</div>
						</div>
					) : null }
				</div>
			</Modal>
		</div>
	);
};

export default LlmsTxtTab;

/* eslint-disable no-nested-ternary */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getNoTopicClusterAssignedYetLabel } from '../../shared/i18nPhrases';
import { useSelect } from '@wordpress/data';
import { getEditorConfig } from '../config';

type TopicClusterItem = {
	id: number;
	title: string;
	level: 'L1' | 'L2';
	parent_post_id?: number | null;
	cluster_root_id?: number | null;
};

type TopicClusterResponse = {
	items?: TopicClusterItem[];
	current?: {
		post_id: number;
		level: 'L1' | 'L2' | 'L3';
		parent_post_id?: number | null;
		cluster_root_id?: number | null;
	} | null;
};

type TopicClusterSummary = {
	current?: {
		post_id: number;
		level: 'L1' | 'L2' | 'L3';
	} | null;
	l1?: {
		id: number;
		title: string;
		edit?: string | null;
		l2?: number;
		l3?: number;
	} | null;
	l2?: {
		id: number;
		title: string;
		edit?: string | null;
		l3?: number;
	} | null;
	group?: {
		id?: number;
		name?: string;
		mind_map_url?: string;
	} | null;
};

const DEFAULT_LEVEL = '' as const;

const SettingsTabIcon = () => (
	<svg width="16" height="16" viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg">
		<g clipPath="url(#clip0_topic_cluster_settings)">
			<path
				d="M5.47015 3.58227L3.88743 5.165H3.24879V4.52635L4.83151 2.94363L5.47015 3.58227ZM6.41423 3.36014C6.41423 3.44344 6.33093 3.52674 6.24763 3.61004L5.55345 4.30422L5.30355 4.05431L6.02549 3.33237L5.85889 3.16577L5.66452 3.36014L5.02588 2.7215L5.63675 2.13839C5.69229 2.08285 5.80336 2.08285 5.88666 2.13839L6.2754 2.52713C6.33093 2.58266 6.33093 2.69373 6.2754 2.77703C6.21986 2.83256 6.16433 2.8881 6.16433 2.94363C6.16433 2.99917 6.21986 3.0547 6.2754 3.11023C6.3587 3.19354 6.442 3.27684 6.41423 3.36014ZM0.833051 5.55374V1.11101H2.77675V2.49936H4.1651V2.91586L4.72044 2.36052V2.22169L3.05442 0.555664H0.833051C0.527614 0.555664 0.27771 0.805568 0.27771 1.11101V5.55374C0.27771 5.85917 0.527614 6.10908 0.833051 6.10908H4.1651C4.47054 6.10908 4.72044 5.85917 4.72044 5.55374H0.833051ZM3.05442 4.74849C2.99888 4.74849 2.94335 4.77626 2.91558 4.77626L2.77675 4.16538H2.36024L1.77713 4.63742L1.94373 3.88771H1.52723L1.24956 5.27607H1.66606L2.47131 4.55412L2.63791 5.19276H2.91558L3.05442 5.165V4.74849Z"
				fill="currentColor"
			/>
		</g>
		<defs>
			<clipPath id="clip0_topic_cluster_settings">
				<rect width="6.6641" height="6.6641" fill="white" />
			</clipPath>
		</defs>
	</svg>
);

const MindMapTabIcon = () => (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
		<circle cx="3" cy="3" r="2" fill="currentColor" />
		<circle cx="13" cy="3" r="2" fill="currentColor" />
		<circle cx="8" cy="13" r="2" fill="currentColor" />
		<path
			d="M4.7 4.1 7.1 10M11.3 4.1 8.9 10M5 3h6"
			stroke="currentColor"
			strokeWidth="1.2"
			fill="none"
			strokeLinecap="round"
		/>
	</svg>
);

const TopicClusterPanel = () => {
	const config = getEditorConfig().topicCluster;
	const postId = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as { getCurrentPostId?: () => number | undefined };
			return editor.getCurrentPostId ? editor.getCurrentPostId() : undefined;
		},
		[],
	);

	const [ isLoading, setIsLoading ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ items, setItems ] = useState< TopicClusterItem[] >( [] );
	const [ level, setLevel ] = useState< '' | 'L1' | 'L2' | 'L3' >( DEFAULT_LEVEL );
	const [ parentId, setParentId ] = useState< number | null >( null );
	const [ parentSearch, setParentSearch ] = useState( '' );
	const [ currentLevel, setCurrentLevel ] = useState< '' | 'L1' | 'L2' | 'L3' >( DEFAULT_LEVEL );
	const [ activeTab, setActiveTab ] = useState< 'settings' | 'mindmap' >( 'settings' );
	const [ summary, setSummary ] = useState< TopicClusterSummary | null >( null );

	const l1Items = useMemo(
		() => items.filter( ( item ) => item.level === 'L1' ),
		[ items ],
	);
	const l2Items = useMemo(
		() => items.filter( ( item ) => item.level === 'L2' ),
		[ items ],
	);

	const loadData = useCallback( async () => {
		if ( ! config?.list || ! postId ) {
			return;
		}

		setIsLoading( true );
		setError( null );

		try {
			const query = new URLSearchParams( { post: String( postId ) } );
			const data = await apiFetch< TopicClusterResponse >( {
				url: `${ config.list }?${ query.toString() }`,
				method: 'GET',
				headers: { 'X-WP-Nonce': config.nonce ?? '' },
			} );

			const nextItems = Array.isArray( data.items ) ? data.items : [];
			setItems( nextItems );

			if ( data.current && data.current.level ) {
				setLevel( data.current.level );
				setCurrentLevel( data.current.level );
				setParentId(
					data.current.parent_post_id ? Number( data.current.parent_post_id ) : null,
				);
			} else {
				setLevel( DEFAULT_LEVEL );
				setCurrentLevel( DEFAULT_LEVEL );
				setParentId( null );
			}
		} catch ( err ) {
			const message =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as Error ).message )
					: __( 'Unable to load Topic Cluster settings.', 'airygen-seo' );
			setError( message );
		} finally {
			setIsLoading( false );
		}
	}, [ config?.list, config?.nonce, postId ] );

	useEffect( () => {
		if ( postId && config?.list ) {
			void loadData();
		}
	}, [ postId, config?.list, loadData ] );

	const saveSettings = useCallback( async () => {
		if ( ! config?.save || ! postId ) {
			return;
		}

		setIsSaving( true );
		setError( null );

		try {
			await apiFetch( {
				url: config.save,
				method: 'POST',
				headers: { 'X-WP-Nonce': config.nonce ?? '' },
				data: {
					post: postId,
					level,
					parent_post_id: parentId ?? 0,
				},
			} );
		} catch ( err ) {
			const message =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as Error ).message )
					: __( 'Unable to save Topic Cluster settings.', 'airygen-seo' );
			setError( message );
		} finally {
			setIsSaving( false );
		}
	}, [ config?.save, config?.nonce, level, parentId, postId ] );

	const loadSummary = useCallback( async () => {
		if ( ! config?.summary || ! postId ) {
			return;
		}

		try {
			const query = new URLSearchParams( { post: String( postId ) } );
			const data = await apiFetch< TopicClusterSummary >( {
				url: `${ config.summary }?${ query.toString() }`,
				method: 'GET',
				headers: { 'X-WP-Nonce': config.nonce ?? '' },
			} );
			setSummary( data );
		} catch ( err ) {
			const message =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as Error ).message )
					: __( 'Unable to load Topic Cluster summary.', 'airygen-seo' );
			setError( message );
		}
	}, [ config?.summary, config?.nonce, postId ] );

	useEffect( () => {
		if ( activeTab === 'mindmap' ) {
			void loadSummary();
		}
	}, [ activeTab, loadSummary ] );

	const parentOptions = useMemo( () => {
		const base =
			level === 'L2'
				? l1Items
				: level === 'L3'
					? l2Items
					: [];

		return [
			{ value: '', label: __( 'Select parent…', 'airygen-seo' ) },
			...base.map( ( item ) => ( {
				value: String( item.id ),
				label: item.title,
			} ) ),
		];
	}, [ level, l1Items, l2Items ] );
	const filteredParentOptions = useMemo( () => {
		if ( level !== 'L2' && level !== 'L3' ) {
			return parentOptions;
		}

		const query = parentSearch.trim().toLowerCase();
		if ( '' === query ) {
			return parentOptions;
		}

		const defaultOption = parentOptions[ 0 ] ?? {
			value: '',
			label: __( 'Select parent…', 'airygen-seo' ),
		};
		const matched = parentOptions
			.slice( 1 )
			.filter( ( option ) => option.label.toLowerCase().includes( query ) );

		const selectedValue = parentId ? String( parentId ) : '';
		if ( selectedValue && ! matched.some( ( option ) => option.value === selectedValue ) ) {
			const selectedOption = parentOptions.find( ( option ) => option.value === selectedValue );
			if ( selectedOption ) {
				matched.unshift( selectedOption );
			}
		}

		return [ defaultOption, ...matched ];
	}, [ level, parentOptions, parentSearch, parentId ] );
	const hasChildren = useMemo( () => {
		if ( ! postId ) {
			return false;
		}
		return items.some( ( item ) => item.parent_post_id === postId );
	}, [ items, postId ] );
	const isLevelChangeBlocked =
		Boolean( currentLevel ) && currentLevel !== level && hasChildren;

	return (
		<div>
			<div className="airygen-panel-tabs" style={ { marginBottom: '8px' } }>
				<Button
					variant={ activeTab === 'settings' ? 'primary' : 'secondary' }
					className="airygen-component-button"
					onClick={ () => setActiveTab( 'settings' ) }
					aria-label={ __( 'Topic cluster settings', 'airygen-seo' ) }
					title={ __( 'Topic cluster settings', 'airygen-seo' ) }
				>
					<SettingsTabIcon />
				</Button>
				<Button
					variant={ activeTab === 'mindmap' ? 'primary' : 'secondary' }
					className="airygen-component-button"
					onClick={ () => setActiveTab( 'mindmap' ) }
					aria-label={ __( 'Open mind map', 'airygen-seo' ) }
					title={ __( 'Open mind map', 'airygen-seo' ) }
				>
					<MindMapTabIcon />
				</Button>
			</div>
			{ error ? (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) : null }

			{ activeTab === 'mindmap' ? (
				<>
					<div className="airygen-topic-cluster__summary">
						{ ! summary?.current?.level ? (
							<div>
								{ getNoTopicClusterAssignedYetLabel() }
							</div>
						) : (
							<table className="airygen-topic-cluster__table">
								<thead>
									<tr>
										<th>{ __( 'Level', 'airygen-seo' ) }</th>
										<th>{ __( 'Details', 'airygen-seo' ) }</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>{ __( 'Group', 'airygen-seo' ) }</td>
										<td>
											<span className="airygen-topic-cluster__meta">
												{ summary?.group?.name || '—' }
											</span>
										</td>
									</tr>
									<tr>
										<td>L1</td>
										<td>
											{ summary?.current?.level === 'L1' ? (
												<span className="airygen-topic-cluster__meta">
													{ __( 'This post is a pillar.', 'airygen-seo' ) }
												</span>
											) : summary?.l1?.title ? (
												<a
													className="airygen-topic-cluster__link"
													href={ summary?.l1?.edit ?? '#' }
												>
													{ summary?.l1?.title }
												</a>
											) : (
												<span className="airygen-topic-cluster__meta">—</span>
											) }
										</td>
									</tr>
									<tr>
										<td>L2</td>
										<td>
											{ summary?.current?.level === 'L1' ? (
												<span className="airygen-topic-cluster__meta">
													{ __( 'Total:', 'airygen-seo' ) } { summary?.l1?.l2 ?? 0 }
												</span>
											) : summary?.current?.level === 'L2' ? (
												<span className="airygen-topic-cluster__meta">
													{ __( 'This post is a cluster.', 'airygen-seo' ) }
												</span>
											) : summary?.current?.level === 'L3' && summary?.l2?.title ? (
												<a
													className="airygen-topic-cluster__link"
													href={ summary?.l2?.edit ?? '#' }
												>
													{ summary?.l2?.title }
												</a>
											) : (
												<span className="airygen-topic-cluster__meta">—</span>
											) }
										</td>
									</tr>
									<tr>
										<td>L3</td>
										<td>
											{ summary?.current?.level === 'L3' ? (
												<span className="airygen-topic-cluster__meta">
													{ __( 'This post is a support article.', 'airygen-seo' ) }
												</span>
											) : summary?.current?.level === 'L2' ? (
												<span className="airygen-topic-cluster__meta">
													{ __( 'Total:', 'airygen-seo' ) } { summary?.l2?.l3 ?? 0 }
												</span>
											) : summary?.current?.level === 'L1' ? (
												<span className="airygen-topic-cluster__meta">
													{ __( 'Total:', 'airygen-seo' ) } { summary?.l1?.l3 ?? 0 }
												</span>
											) : (
												<span className="airygen-topic-cluster__meta">—</span>
											) }
										</td>
									</tr>
								</tbody>
							</table>
						) }
					</div>
					<div className="airygen-topic-cluster__actions">
						<div>
							<Button
								variant="secondary"
								className="airygen-component-button"
								onClick={ () => {
									const targetUrl =
										summary?.group?.mind_map_url && summary.group.mind_map_url.length > 0
											? summary.group.mind_map_url
											: config?.mindMapUrl;
									if ( targetUrl ) {
										window.open( targetUrl, '_blank', 'noopener' );
									}
								} }
							>
								{ __( 'Open mind map', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				</>
			) : null }

			{ activeTab === 'settings' ? (
				<>
					<SelectControl
						label={ __( 'Topic level', 'airygen-seo' ) }
						value={ level }
						options={ [
							{ value: '', label: __( 'Not set', 'airygen-seo' ) },
							{ value: 'L1', label: __( 'L1 — Pillar', 'airygen-seo' ) },
							{ value: 'L2', label: __( 'L2 — Cluster', 'airygen-seo' ) },
							{ value: 'L3', label: __( 'L3 — Support', 'airygen-seo' ) },
						] }
						onChange={ ( value ) => {
							const next = value as '' | 'L1' | 'L2' | 'L3';
							setLevel( next );
							setParentSearch( '' );
							if ( next === 'L1' ) {
								setParentId( null );
							}
						} }
					/>

					{ level === 'L1' ? (
						<p className="components-base-control__help">
							{ __( 'This post becomes the root of a new topic cluster.', 'airygen-seo' ) }
						</p>
					) : null }

					{ ( level === 'L2' || level === 'L3' ) ? (
						<>
							<TextControl
								label={ __( 'Search parent', 'airygen-seo' ) }
								value={ parentSearch }
								onChange={ ( value ) => setParentSearch( value ) }
								placeholder={ __( 'Type to filter parent posts…', 'airygen-seo' ) }
							/>
							<SelectControl
								label={
									level === 'L2'
										? __( 'Choose an L1 parent', 'airygen-seo' )
										: __( 'Choose an L2 parent', 'airygen-seo' )
								}
								value={ parentId ? String( parentId ) : '' }
								options={ filteredParentOptions }
								onChange={ ( value ) => {
									const nextId = value ? Number( value ) : null;
									setParentId( nextId );
								} }
							/>
							<span className="components-base-control__help">
								{ `${ Math.max( 0, filteredParentOptions.length - 1 ) } ${ __( 'matching parent posts.', 'airygen-seo' ) }` }
							</span>
						</>
					) : null }

					<div className="airygen-topic-cluster__actions">
						<div>
							<Button
								variant="secondary"
								className="airygen-component-button"
								onClick={ saveSettings }
								disabled={ isSaving || ! postId || isLevelChangeBlocked }
							>
								{ isSaving
									? __( 'Saving…', 'airygen-seo' )
									: __( 'Save', 'airygen-seo' ) }
							</Button>
						</div>
						{ isLevelChangeBlocked ? (
							<span className="components-base-control__help">
								{ __(
									'This post already has child items. Remove them before changing the level.',
									'airygen-seo',
								) }
							</span>
						) : null }
						{ isLoading ? <Spinner /> : null }
					</div>
				</>
			) : null }
		</div>
	);
};

export default TopicClusterPanel;

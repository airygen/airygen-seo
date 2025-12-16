import { __ } from '@wordpress/i18n';
import {
	getLoadingLabel,
	getNoTopicClusterAssignedYetLabel,
} from '../../../shared/i18nPhrases';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

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

type TopicClusterConfig = {
	list?: string;
	save?: string;
	summary?: string;
	mindMapUrl?: string;
	nonce?: string;
};

const DEFAULT_LEVEL = '' as const;

type TopicClusterPanelProps = {
	topicSubTab: 'settings' | 'summary';
	setTopicSubTab: ( tab: 'settings' | 'summary' ) => void;
	postId: number;
	config?: TopicClusterConfig;
};

export const TopicClusterPanel = ( {
	topicSubTab,
	setTopicSubTab,
	postId,
	config,
}: TopicClusterPanelProps ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ items, setItems ] = useState< TopicClusterItem[] >( [] );
	const [ level, setLevel ] = useState< '' | 'L1' | 'L2' | 'L3' >( DEFAULT_LEVEL );
	const [ parentId, setParentId ] = useState< number | null >( null );
	const [ parentSearch, setParentSearch ] = useState( '' );
	const [ currentLevel, setCurrentLevel ] = useState< '' | 'L1' | 'L2' | 'L3' >( DEFAULT_LEVEL );
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
				setParentId( data.current.parent_post_id ? Number( data.current.parent_post_id ) : null );
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
		if ( topicSubTab === 'summary' ) {
			void loadSummary();
		}
	}, [ topicSubTab, loadSummary ] );

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
			setCurrentLevel( level );
		} catch ( err ) {
			const message =
				err && typeof err === 'object' && 'message' in err
					? String( ( err as Error ).message )
					: __( 'Unable to save Topic Cluster settings.', 'airygen-seo' );
			setError( message );
		} finally {
			setIsSaving( false );
		}
	}, [ config?.save, config?.nonce, postId, level, parentId ] );

	const parentOptions = useMemo( () => {
		let base: TopicClusterItem[] = [];
		if ( level === 'L2' ) {
			base = l1Items;
		} else if ( level === 'L3' ) {
			base = l2Items;
		}

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
	const currentSummaryLevel = summary?.current?.level;

	const renderL1SummaryCell = () => {
		if ( currentSummaryLevel === 'L1' ) {
			return (
				<span className="airygen-topic-cluster__meta">
					{ __( 'This post is a pillar.', 'airygen-seo' ) }
				</span>
			);
		}

		if ( summary?.l1?.title ) {
			return (
				<a className="airygen-topic-cluster__link" href={ summary?.l1?.edit ?? '#' }>
					{ summary?.l1?.title }
				</a>
			);
		}

		return <span className="airygen-topic-cluster__meta">—</span>;
	};

	const renderL2SummaryCell = () => {
		if ( currentSummaryLevel === 'L1' ) {
			return (
				<span className="airygen-topic-cluster__meta">
					{ __( 'Total:', 'airygen-seo' ) } { summary?.l1?.l2 ?? 0 }
				</span>
			);
		}

		if ( currentSummaryLevel === 'L2' ) {
			return (
				<span className="airygen-topic-cluster__meta">
					{ __( 'This post is a cluster.', 'airygen-seo' ) }
				</span>
			);
		}

		if ( currentSummaryLevel === 'L3' && summary?.l2?.title ) {
			return (
				<a className="airygen-topic-cluster__link" href={ summary?.l2?.edit ?? '#' }>
					{ summary?.l2?.title }
				</a>
			);
		}

		return <span className="airygen-topic-cluster__meta">—</span>;
	};

	const renderL3SummaryCell = () => {
		if ( currentSummaryLevel === 'L3' ) {
			return (
				<span className="airygen-topic-cluster__meta">
					{ __( 'This post is a support article.', 'airygen-seo' ) }
				</span>
			);
		}

		if ( currentSummaryLevel === 'L2' ) {
			return (
				<span className="airygen-topic-cluster__meta">
					{ __( 'Total:', 'airygen-seo' ) } { summary?.l2?.l3 ?? 0 }
				</span>
			);
		}

		if ( currentSummaryLevel === 'L1' ) {
			return (
				<span className="airygen-topic-cluster__meta">
					{ __( 'Total:', 'airygen-seo' ) } { summary?.l1?.l3 ?? 0 }
				</span>
			);
		}

		return <span className="airygen-topic-cluster__meta">—</span>;
	};

	return (
		<>
			<div className="airygen-panel-tabs">
				<button
					type="button"
					className={
						topicSubTab === 'settings'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setTopicSubTab( 'settings' ) }
				>
					{ __( 'Settings', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					className={
						topicSubTab === 'summary'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setTopicSubTab( 'summary' ) }
				>
					{ __( 'Summary', 'airygen-seo' ) }
				</button>
			</div>
			<div className="airygen-panel-container">
				{ error ? (
					<p className="airygen-classic-label-helper airygen-field-helper--bad">{ error }</p>
				) : null }
				{ topicSubTab === 'settings' ? (
					<div className="airygen-panel-container">
						<div className="airygen-classic-field">
							<label className="airygen-classic-label" htmlFor="airygen-topic-cluster-level">
								<span className="airygen-classic-label-text">{ __( 'Topic level', 'airygen-seo' ) }</span>
							</label>
							<select
								id="airygen-topic-cluster-level"
								className="airygen-classic-input"
								value={ level }
								onChange={ ( event ) => {
									const next = event.target.value as '' | 'L1' | 'L2' | 'L3';
									setLevel( next );
									setParentSearch( '' );
									if ( next === 'L1' ) {
										setParentId( null );
									}
								} }
							>
								<option value="">{ __( 'Not set', 'airygen-seo' ) }</option>
								<option value="L1">{ __( 'L1 — Pillar', 'airygen-seo' ) }</option>
								<option value="L2">{ __( 'L2 — Cluster', 'airygen-seo' ) }</option>
								<option value="L3">{ __( 'L3 — Support', 'airygen-seo' ) }</option>
							</select>
						</div>
						{ level === 'L1' ? (
							<p className="airygen-classic-label-helper">
								{ __( 'This post becomes the root of a new topic cluster.', 'airygen-seo' ) }
							</p>
						) : null }
						{ ( level === 'L2' || level === 'L3' ) ? (
							<>
								<div className="airygen-classic-field">
									<label className="airygen-classic-label" htmlFor="airygen-topic-cluster-parent-search">
										<span className="airygen-classic-label-text">
											{ __( 'Search parent', 'airygen-seo' ) }
										</span>
									</label>
									<input
										id="airygen-topic-cluster-parent-search"
										type="text"
										className="airygen-classic-input"
										value={ parentSearch }
										onChange={ ( event ) => setParentSearch( event.target.value ) }
										placeholder={ __( 'Type to filter parent posts…', 'airygen-seo' ) }
									/>
								</div>
								<div className="airygen-classic-field">
									<label className="airygen-classic-label" htmlFor="airygen-topic-cluster-parent">
										<span className="airygen-classic-label-text">
											{ level === 'L2'
												? __( 'Choose an L1 parent', 'airygen-seo' )
												: __( 'Choose an L2 parent', 'airygen-seo' ) }
										</span>
									</label>
									<select
										id="airygen-topic-cluster-parent"
										className="airygen-classic-input"
										value={ parentId ? String( parentId ) : '' }
										onChange={ ( event ) => {
											const nextId = event.target.value ? Number( event.target.value ) : null;
											setParentId( nextId );
										} }
									>
										{ filteredParentOptions.map( ( option ) => (
											<option key={ option.value || 'empty' } value={ option.value }>
												{ option.label }
											</option>
										) ) }
									</select>
									<span className="airygen-field-helper">
										{ `${ Math.max( 0, filteredParentOptions.length - 1 ) } ${ __( 'matching parent posts.', 'airygen-seo' ) }` }
									</span>
								</div>
							</>
						) : null }
						<div className="airygen-topic-cluster__actions">
							<button
								type="button"
								className="airygen-component-button is-secondary"
								onClick={ () => {
									void saveSettings();
								} }
								disabled={ isSaving || ! postId || isLevelChangeBlocked }
							>
								{ isSaving ? __( 'Saving…', 'airygen-seo' ) : __( 'Save', 'airygen-seo' ) }
							</button>
							{ isLevelChangeBlocked ? (
								<span className="airygen-classic-label-helper airygen-field-helper--warn">
									{ __(
										'This post already has child items. Remove them before changing the level.',
										'airygen-seo',
									) }
								</span>
							) : null }
							{ isLoading ? (
								<span className="airygen-classic-label-helper">{ getLoadingLabel() }</span>
							) : null }
						</div>
					</div>
				) : null }
				{ topicSubTab === 'summary' ? (
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
												<span className="airygen-topic-cluster__meta">{ summary?.group?.name || '—' }</span>
											</td>
										</tr>
										<tr>
											<td>L1</td>
											<td>{ renderL1SummaryCell() }</td>
										</tr>
										<tr>
											<td>L2</td>
											<td>{ renderL2SummaryCell() }</td>
										</tr>
										<tr>
											<td>L3</td>
											<td>{ renderL3SummaryCell() }</td>
										</tr>
									</tbody>
								</table>
							) }
						</div>
						<div className="airygen-topic-cluster__actions">
							<button
								type="button"
								className="airygen-component-button is-secondary"
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
							</button>
						</div>
					</>
				) : null }
			</div>
		</>
	);
};

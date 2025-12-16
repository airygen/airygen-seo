import apiFetch from '@wordpress/api-fetch';
import {
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';

import Button from '../../../components/Button';
import Checkbox from '../../../components/Checkbox';
import Input from '../../../components/Input';
import Notice from '../../../components/Notice';
import Select from '../../../components/Select';
import Spinner from '../../../components/Spinner';
import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import ActionSchedulerPill from '../../../components/ActionSchedulerPill';
import { InstantIndexingIcon } from '../../../components/Icons';
import { getNoLogsYetLabel } from '../../../../shared/i18nPhrases';
import formatDateTime from '../../../utils/formatDateTime';

import { __, sprintf } from '@wordpress/i18n';
import type { MetaPayload } from '../../../types/api';
import type { InstantIndexingSettings } from '../../../types/settings';

const DEFAULT_ENGINE_FALLBACK = [
	{ slug: 'bing', label: 'Microsoft Bing', endpoint: 'https://www.bing.com/indexnow' },
];

type ManualAction = 'add' | 'update' | 'delete';

type InstantIndexingStatus = {
	summary: {
		pending: number;
		processing: number;
		failed: number;
		completed: number;
	};
	recent: Array<{
		id: number;
		url: string;
		status: string;
		source: string;
		last_error?: string;
		updated_at?: string;
	}>;
	logs: Array<{
		engine: string;
		status_code: number | null;
		success: boolean;
		message: string;
		timestamp: string;
	}>;
	key: {
		present: boolean;
		reachable: boolean;
		message: string;
		location: string;
	};
};

type InstantIndexingTabProps = {
	restBase: string;
	settings: InstantIndexingSettings;
	meta: MetaPayload;
	actionSchedulerAvailable?: boolean;
	onChange: ( value: InstantIndexingSettings ) => void;
};

const formatNumber = ( value: number | null | undefined ): string => {
	if ( typeof value !== 'number' || Number.isNaN( value ) ) {
		return '—';
	}
	return new Intl.NumberFormat().format( value );
};

const formatTimestamp = ( value?: string | null ): string =>
	formatDateTime( value, '—' );

const InstantIndexingTab = ( {
	restBase,
	settings,
	meta,
	actionSchedulerAvailable,
	onChange,
}: InstantIndexingTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'records'>( 'settings' );
	const [ status, setStatus ] = useState<InstantIndexingStatus | null>( null );
	const [ statusError, setStatusError ] = useState<string | null >( null );
	const [ isLoadingStatus, setIsLoadingStatus ] = useState( false );
	const [ manualUrls, setManualUrls ] = useState( '' );
	const [ manualAction, setManualAction ] = useState<ManualAction>( 'update' );
	const [ isSubmittingManual, setIsSubmittingManual ] = useState( false );
	const [ manualNotice, setManualNotice ] = useState<{ status: 'success' | 'error'; message: string } | null >( null );
	const [ selectedPostTypes, setSelectedPostTypes ] = useState<string[]>( settings.backfill.postTypes ?? [] );
	const [ isQueuingBackfill, setIsQueuingBackfill ] = useState( false );
	const [ backfillNotice, setBackfillNotice ] = useState<{ status: 'success' | 'error'; message: string } | null >( null );
	const [ rotatePending, setRotatePending ] = useState( false );
	const [ copyPending, setCopyPending ] = useState( false );
	const showSchedulerPill = typeof actionSchedulerAvailable === 'boolean';

	const normalizedBase = useMemo( () => restBase.replace( /\/$/, '' ), [ restBase ] );
	const statusPath = `${ normalizedBase }/indexnow/status`;
	const manualPath = `${ normalizedBase }/indexnow/manual`;
	const backfillPath = `${ normalizedBase }/indexnow/backfill`;
	const rotatePath = `${ normalizedBase }/indexnow/rotate-key`;

	useEffect( () => {
		setSelectedPostTypes( settings.backfill.postTypes ?? [] );
	}, [ settings.backfill.postTypes ] );

	const fetchStatus = useCallback( () => {
		setIsLoadingStatus( true );
		return apiFetch<InstantIndexingStatus>( { path: statusPath } )
			.then( ( response ) => {
				setStatus( response );
				setStatusError( null );
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to load status.', 'airygen-seo' );
				setStatusError( message );
			} )
			.finally( () => {
				setIsLoadingStatus( false );
			} );
	}, [ statusPath ] );

	useEffect( () => {
		fetchStatus();
	}, [ fetchStatus ] );

	const handleSettingChange = ( patch: Partial<InstantIndexingSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const engineCatalog = useMemo( () => {
		if ( meta.instantIndexing?.engines?.length ) {
			return meta.instantIndexing.engines;
		}

		const defined = Object.entries( settings.engines ).map( ( [ slug, config ] ) => ( {
			slug,
			label: slug,
			endpoint: config.endpoint,
		} ) );

		return defined.length ? defined : DEFAULT_ENGINE_FALLBACK;
	}, [ meta.instantIndexing?.engines, settings.engines ] );

	const engineCards = engineCatalog.map( ( engine ) => ( {
		...engine,
		config: settings.engines[ engine.slug ] ?? {
			enabled: engine.slug === 'bing',
			endpoint: engine.endpoint,
		},
	} ) );

	const enabledEngineCount = engineCards.reduce( ( total, engine ) =>
		engine.config.enabled ? total + 1 : total,
	0,
	);

	const updateEngine = ( slug: string, patch: Partial<{ enabled: boolean; endpoint: string }> ) => {
		const current = settings.engines[ slug ] ?? {
			enabled: slug === 'bing',
			endpoint: engineCatalog.find( ( engine ) => engine.slug === slug )?.endpoint ?? '',
		};

		handleSettingChange( {
			engines: {
				...settings.engines,
				[ slug ]: {
					...current,
					...patch,
				},
			},
		} );
	};

	const togglePostType = ( slug: string ) => {
		const next = selectedPostTypes.includes( slug )
			? selectedPostTypes.filter( ( value ) => value !== slug )
			: [ ...selectedPostTypes, slug ];

		setSelectedPostTypes( next );
		handleSettingChange( {
			backfill: {
				...settings.backfill,
				postTypes: next,
			},
		} );
	};

	const handlePostTypeToggle =
		( slug: string ) =>
			( _checked: boolean ) => {
				togglePostType( slug );
			};

	const handleManualSubmit = () => {
		const urls = manualUrls
			.split( /\r?\n/ )
			.map( ( url ) => url.trim() )
			.filter( Boolean );

		if ( urls.length === 0 ) {
			setManualNotice( {
				status: 'error',
				message: __( 'Enter at least one URL.', 'airygen-seo' ),
			} );
			return;
		}

		setIsSubmittingManual( true );
		setManualNotice( null );

		apiFetch<{ queued: number }>( {
			path: manualPath,
			method: 'POST',
			data: {
				urls,
				action: manualAction,
			},
		} )
			.then( () => {
				setManualNotice( {
					status: 'success',
					message: __( 'URLs queued successfully.', 'airygen-seo' ),
				} );
				setManualUrls( '' );
				fetchStatus();
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Could not queue URLs. Try again.', 'airygen-seo' );
				setManualNotice( {
					status: 'error',
					message,
				} );
			} )
			.finally( () => {
				setIsSubmittingManual( false );
			} );
	};

	const handleBackfill = () => {
		if ( selectedPostTypes.length === 0 ) {
			setBackfillNotice( {
				status: 'error',
				message: __( 'Select at least one post type to backfill.', 'airygen-seo' ),
			} );
			return;
		}

		setIsQueuingBackfill( true );
		setBackfillNotice( null );

		apiFetch( {
			path: backfillPath,
			method: 'POST',
			data: {
				post_types: selectedPostTypes,
			},
		} )
			.then( () => {
				setBackfillNotice( {
					status: 'success',
					message: __( 'Backfill queued successfully.', 'airygen-seo' ),
				} );
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Backfill request failed.', 'airygen-seo' );
				setBackfillNotice( {
					status: 'error',
					message,
				} );
			} )
			.finally( () => {
				setIsQueuingBackfill( false );
			} );
	};

	const handleRotateKey = () => {
		setRotatePending( true );

		apiFetch<{ key: string }>( {
			path: rotatePath,
			method: 'POST',
		} )
			.then( ( response ) => {
				const nextKey = response?.key ?? '';
				handleSettingChange( { key: nextKey } );
			} )
			.finally( () => {
				setRotatePending( false );
			} );
	};

	const handleCopyKey = () => {
		if ( ! settings.key ) {
			return;
		}

		setCopyPending( true );

		const onError = () => {
			setCopyPending( false );
		};

		if ( navigator?.clipboard?.writeText ) {
			navigator.clipboard
				.writeText( settings.key )
				.then( () => setCopyPending( false ) )
				.catch( onError );
			return;
		}

		try {
			const temp = document.createElement( 'textarea' );
			temp.value = settings.key;
			temp.style.position = 'absolute';
			temp.style.left = '-9999px';
			document.body.appendChild( temp );
			temp.select();
			document.execCommand( 'copy' );
			document.body.removeChild( temp );
			setCopyPending( false );
		} catch {
			onError();
		}
	};

	const moduleDisabled = ! settings.enabled;

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<InstantIndexingIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'Instant Indexing', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Submit URLs to supported search engines via IndexNow and monitor delivery.',
								'airygen-seo',
							) }
						</p>
					</div>
				</div>
				{ showSchedulerPill ? (
					<ActionSchedulerPill available={ Boolean( actionSchedulerAvailable ) } />
				) : null }
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
					data-airygen-e2e="tab-records"
					className={
						activeTab === 'records'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'records' ) }
				>
					{ __( 'Records', 'airygen-seo' ) }
				</button>
			</div>

			{ activeTab === 'settings' ? (
				<>
					<section className="space-y-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
							<div className="space-y-1">
								<div className="airygen_h2_title">
									{ __( 'Settings', 'airygen-seo' ) }
								</div>
								<p className="text-sm text-slate-500">
									{ __(
										'Set your IndexNow key and submission limits so search engines can receive updates faster.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="flex items-center gap-3">
								<p className="text-sm font-medium text-slate-900">
									{ __( 'Enable Instant Indexing', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Instant Indexing', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.enabled }
									onChange={ ( enabled ) =>
										handleSettingChange( { enabled } )
									}
								/>
							</div>
						</div>

						<div className="grid gap-4 md:grid-cols-2">
							<Input
								label={ __( 'API key', 'airygen-seo' ) }
								help={ __(
									'IndexNow requires a key file. Paste an existing key or generate a new one.',
									'airygen-seo',
								) }
								value={ settings.key }
								onChange={ ( value ) => handleSettingChange( { key: value } ) }
								disabled={ moduleDisabled }
							/>
							<div className="flex flex-wrap gap-3">
								<Button
									variant="secondary"
									onClick={ handleCopyKey }
									disabled={ moduleDisabled || ! settings.key }
									loading={ copyPending }
								>
									{ __( 'Copy key', 'airygen-seo' ) }
								</Button>
								<Button
									variant="outline"
									onClick={ handleRotateKey }
									disabled={ moduleDisabled }
									loading={ rotatePending }
								>
									{ __( 'Rotate key', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
						<div className="grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__toggle--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Key location URL', 'airygen-seo' ) }
									help={ __(
										'Public URL to your key file. Leave blank to use the default location.',
										'airygen-seo',
									) }
									value={ settings.keyLocation }
									onChange={ ( value ) =>
										handleSettingChange( { keyLocation: value } )
									}
									disabled={ moduleDisabled }
									isUrl
								/>
							</div>
							<div className="airygen-setting-card__toggle--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Retry cooldown (days)', 'airygen-seo' ) }
									type="number"
									value={ String( settings.retryCooldownDays ) }
									onChange={ ( value ) => {
										const parsed = Number( value );
										handleSettingChange( {
											retryCooldownDays: Number.isFinite( parsed )
												? Math.max( 1, Math.round( parsed ) )
												: 1,
										} );
									} }
									min={ 1 }
									help={ __(
										'Minimum days to wait before retrying failed submissions.',
										'airygen-seo',
									) }
									disabled={ moduleDisabled }
								/>
							</div>
							<div className="airygen-setting-card__toggle--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Daily event quota', 'airygen-seo' ) }
									help={ __(
										'Max number of submission events per day. Set to 0 for no limit.',
										'airygen-seo',
									) }
									type="number"
									value={ String( settings.maxEventsPerDay ) }
									onChange={ ( value ) => {
										const parsed = Number( value );
										handleSettingChange( {
											maxEventsPerDay: Number.isFinite( parsed )
												? Math.max( 0, Math.round( parsed ) )
												: 0,
										} );
									} }
									disabled={ moduleDisabled }
								/>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Auto-submit new/updated posts', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Auto-submit new/updated posts', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.autoSubmit }
										onChange={ ( value ) =>
											handleSettingChange( { autoSubmit: value } )
										}
										disabled={ moduleDisabled }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Automatically queue URLs when content is created or updated.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Engines', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Enable which engines receive submissions and review their endpoints. Bing can notify other search engines, so enabling only Bing can reduce extra requests.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="space-y-4">
							{ ( () => {
								const featuredSlugs = [ 'yandex', 'seznam', 'naver', 'yep' ];
								const bingEngine = engineCards.find(
									( engine ) => engine.slug === 'bing',
								);
								const featuredEngines = engineCards.filter( ( engine ) =>
									featuredSlugs.includes( engine.slug ),
								);
								const otherEngines = engineCards.filter(
									( engine ) =>
										! featuredSlugs.includes( engine.slug ) &&
								engine.slug !== 'bing',
								);

								return (
									<>
										{ bingEngine ? (
											<div className="space-y-3 rounded-lg border border-slate-200 p-4">
												<div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
													<div>
														<p className="text-sm font-medium text-slate-900">
															{ bingEngine.label }
														</p>
														<p className="text-xs text-slate-500">
															{ bingEngine.endpoint }
														</p>
													</div>
													<Toggle
														hideLabelText
														label={ sprintf(
															/* translators: %s is the search engine label. */
															__( 'Enable %s submissions', 'airygen-seo' ),
															bingEngine.label,
														) }
														checked={ bingEngine.config.enabled }
														onChange={ ( value ) =>
															updateEngine( bingEngine.slug, { enabled: value } )
														}
														disabled={
															moduleDisabled ||
													( enabledEngineCount <= 1 &&
														bingEngine.config.enabled )
														}
													/>
												</div>
											</div>
										) : null }
										{ featuredEngines.length > 0 ? (
											<div className="grid gap-4 md:grid-cols-4">
												{ featuredEngines.map( ( engine ) => {
													const disableToggle =
												enabledEngineCount <= 1 && engine.config.enabled;

													return (
														<div
															key={ engine.slug }
															className="airygen-setting-card__toggle--normal flex flex-col gap-2 rounded-lg border border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:gap-3"
														>
															<div className="min-w-0 flex-1">
																<p className="text-sm font-medium text-slate-900">
																	{ engine.label }
																</p>
																<p className="text-xs text-slate-500 break-all">
																	{ engine.endpoint }
																</p>
															</div>
															<Toggle
																className="shrink-0"
																hideLabelText
																label={ sprintf(
																	/* translators: %s is the search engine label. */
																	__( 'Enable %s submissions', 'airygen-seo' ),
																	engine.label,
																) }
																checked={ engine.config.enabled }
																onChange={ ( value ) =>
																	updateEngine( engine.slug, { enabled: value } )
																}
																disabled={ moduleDisabled || disableToggle }
															/>
														</div>
													);
												} ) }
											</div>
										) : null }
										{ otherEngines.map( ( engine ) => {
											const disableToggle =
										enabledEngineCount <= 1 && engine.config.enabled;
											return (
												<div
													key={ engine.slug }
													className="space-y-3 rounded-lg border border-slate-200 p-4"
												>
													<div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
														<div>
															<p className="text-sm font-medium text-slate-900">
																{ engine.label }
															</p>
															<p className="text-xs text-slate-500">
																{ engine.endpoint }
															</p>
														</div>
														<Toggle
															hideLabelText
															label={ sprintf(
																/* translators: %s is the search engine label. */
																__( 'Enable %s submissions', 'airygen-seo' ),
																engine.label,
															) }
															checked={ engine.config.enabled }
															onChange={ ( value ) =>
																updateEngine( engine.slug, { enabled: value } )
															}
															disabled={ moduleDisabled || disableToggle }
														/>
													</div>
												</div>
											);
										} ) }
									</>
								);
							} )() }
						</div>
					</section>

					<section className="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
							<div>
								<div className="airygen_h2_title">
									{ __( 'Backfill', 'airygen-seo' ) }
								</div>
								<p className="text-sm text-slate-500">
									{ __(
										'Queue existing posts for submission. Useful after enabling a new engine.',
										'airygen-seo',
									) }
								</p>
							</div>
							<Button
								variant="secondary"
								onClick={ handleBackfill }
								loading={ isQueuingBackfill }
								disabled={ moduleDisabled }
								className="text-xs"
							>
								{ isQueuingBackfill
									? __( 'Queueing…', 'airygen-seo' )
									: __( 'Queue backfill', 'airygen-seo' ) }
							</Button>
						</div>
						{ backfillNotice ? (
							<Notice status={ backfillNotice.status }>
								{ backfillNotice.message }
							</Notice>
						) : null }
						<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
							{ __( 'Included post types', 'airygen-seo' ) }
						</p>
						<div className="grid max-h-64 gap-3 overflow-y-auto pr-1 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
							{ ( meta.postTypes ?? [] ).map( ( postType ) => (
								<div
									key={ postType.slug }
									className="rounded-lg border border-slate-200 p-3"
								>
									<Checkbox
										label={ postType.label }
										checked={ selectedPostTypes.includes( postType.slug ) }
										onChange={ handlePostTypeToggle( postType.slug ) }
										disabled={ moduleDisabled }
									/>
								</div>
							) ) }
						</div>
					</section>

					<section className="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Manual submit', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Paste URLs (one per line) to manually queue add/update/delete events.',
									'airygen',
								) }
							</p>
						</div>
						{ manualNotice ? (
							<Notice status={ manualNotice.status }>
								{ manualNotice.message }
							</Notice>
						) : null }
						<div className="grid gap-4 md:grid-cols-2">
							<Textarea
								label={ __( 'URLs', 'airygen-seo' ) }
								value={ manualUrls }
								onChange={ setManualUrls }
								rows={ 6 }
								placeholder={ __(
									'https://example.com/post-1\\nhttps://example.com/post-2',
									'airygen-seo',
								) }
								disabled={ moduleDisabled }
							/>
							<div className="space-y-4">
								<Select
									label={ __( 'Action', 'airygen-seo' ) }
									value={ manualAction }
									onChange={ ( value ) =>
										setManualAction( value as ManualAction )
									}
									options={ [
										{ value: 'add', label: __( 'Add', 'airygen-seo' ) },
										{ value: 'update', label: __( 'Update', 'airygen-seo' ) },
										{ value: 'delete', label: __( 'Delete', 'airygen-seo' ) },
									] }
									disabled={ moduleDisabled }
								/>
								<Button
									variant="secondary"
									onClick={ handleManualSubmit }
									loading={ isSubmittingManual }
									disabled={ moduleDisabled }
									className="text-xs"
								>
									{ isSubmittingManual
										? __( 'Submitting…', 'airygen-seo' )
										: __( 'Submit URLs', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
					</section>
				</>
			) : null }

			{ activeTab === 'records' ? (
				<section className="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
					<div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Status', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'View recent submissions, logs, and key health.',
									'airygen-seo',
								) }
							</p>
						</div>
						<Button
							variant="outline"
							onClick={ fetchStatus }
							loading={ isLoadingStatus }
							className="text-xs"
						>
							{ __( 'Refresh status', 'airygen-seo' ) }
						</Button>
					</div>
					{ statusError ? (
						<Notice status="error">{ statusError }</Notice>
					) : null }
					{ ! status ? (
						<div className="flex items-center gap-3 text-sm text-slate-500">
							<Spinner />
							<span>{ __( 'Loading…', 'airygen-seo' ) }</span>
						</div>
					) : (
						<div className="space-y-5">
							<div className="grid gap-4 md:grid-cols-4">
								<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
									<p className="text-xs uppercase tracking-wide text-slate-500">
										{ __( 'Pending', 'airygen-seo' ) }
									</p>
									<p className="pb-4 text-center text-2xl font-semibold leading-4 text-slate-900">
										{ formatNumber( status.summary.pending ) }
									</p>
								</div>
								<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
									<p className="text-xs uppercase tracking-wide text-slate-500">
										{ __( 'Processing', 'airygen-seo' ) }
									</p>
									<p className="pb-4 text-center text-2xl font-semibold leading-4 text-slate-900">
										{ formatNumber( status.summary.processing ) }
									</p>
								</div>
								<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
									<p className="text-xs uppercase tracking-wide text-slate-500">
										{ __( 'Failed', 'airygen-seo' ) }
									</p>
									<p className="pb-4 text-center text-2xl font-semibold leading-4 text-slate-900">
										{ formatNumber( status.summary.failed ) }
									</p>
								</div>
								<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
									<p className="text-xs uppercase tracking-wide text-slate-500">
										{ __( 'Completed', 'airygen-seo' ) }
									</p>
									<p className="pb-4 text-center text-2xl font-semibold leading-4 text-slate-900">
										{ formatNumber( status.summary.completed ) }
									</p>
								</div>
							</div>

							<div className="rounded-lg border border-slate-200 p-4">
								<p className="text-sm font-medium text-slate-900">
									{ status.key.present
										? __( 'Key detected', 'airygen-seo' )
										: __( 'Key missing', 'airygen-seo' ) }
								</p>
								<p className="text-xs text-slate-500">
									{ status.key.reachable
										? __( 'Reachable', 'airygen-seo' )
										: __( 'Unreachable', 'airygen-seo' ) }
								</p>
								<p className="text-xs text-slate-500">
									{ __( 'Message', 'airygen-seo' ) }: { status.key.message }
								</p>
								<p className="text-xs text-slate-500">
									{ __( 'Location', 'airygen-seo' ) }: { status.key.location || '—' }
								</p>
							</div>

							<div className="grid gap-6 md:grid-cols-2">
								<div>
									<h3 className="text-sm font-semibold text-slate-900">
										{ __( 'Submission logs', 'airygen-seo' ) }
									</h3>
									<div className="mt-2 max-h-64 overflow-y-auto rounded-lg border border-slate-200">
										<table className="min-w-full divide-y divide-slate-200 text-sm">
											<thead className="bg-slate-50">
												<tr>
													<th className="px-3 py-2 text-left font-medium text-slate-600">
														{ __( 'Engine', 'airygen-seo' ) }
													</th>
													<th className="px-3 py-2 text-left font-medium text-slate-600">
														{ __( 'Status', 'airygen-seo' ) }
													</th>
													<th className="px-3 py-2 text-left font-medium text-slate-600">
														{ __( 'Message', 'airygen-seo' ) }
													</th>
													<th className="px-3 py-2 text-left font-medium text-slate-600">
														{ __( 'Time', 'airygen-seo' ) }
													</th>
												</tr>
											</thead>
											<tbody className="divide-y divide-slate-200">
												{ status.logs.length === 0 ? (
													<tr>
														<td className="px-3 py-3 text-sm text-slate-500" colSpan={ 4 }>
															{ getNoLogsYetLabel() }
														</td>
													</tr>
												) : (
													status.logs.map( ( log, index ) => (
														<tr key={ `${ log.engine }-${ index }` }>
															<td className="px-3 py-2 text-slate-700">{ log.engine }</td>
															<td className="px-3 py-2 text-slate-700">{ log.status_code ?? '—' }</td>
															<td className="px-3 py-2 text-slate-700">{ log.message || '—' }</td>
															<td className="px-3 py-2 text-slate-700">{ formatTimestamp( log.timestamp ) }</td>
														</tr>
													) )
												) }
											</tbody>
										</table>
									</div>
								</div>
								<div>
									<h3 className="text-sm font-semibold text-slate-900">
										{ __( 'Recent URLs', 'airygen-seo' ) }
									</h3>
									<div className="mt-2 space-y-3 max-h-64 overflow-y-auto">
										{ status.recent.length === 0 ? (
											<p className="text-sm text-slate-500">
												{ getNoLogsYetLabel() }
											</p>
										) : (
											status.recent.map( ( event ) => (
												<div key={ event.id } className="rounded-lg border border-slate-200 p-3">
													<p className="text-sm font-medium text-slate-900 break-all">
														{ event.url }
													</p>
													<p className="text-xs text-slate-500">
														{ event.status } · { formatTimestamp( event.updated_at ) }
													</p>
													{ event.last_error ? (
														<p className="text-xs text-rose-600 mt-1">
															{ event.last_error }
														</p>
													) : null }
												</div>
											) )
										) }
									</div>
								</div>
							</div>
						</div>
					) }
				</section>
			) : null }
		</div>
	);
};

export default InstantIndexingTab;

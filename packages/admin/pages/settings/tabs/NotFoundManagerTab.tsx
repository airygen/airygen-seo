import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

import Button from '../../../components/Button';
import HeadingIcon from '../../../components/HeadingIcon';
import Input from '../../../components/Input';
import Modal from '../../../components/Modal';
import Select from '../../../components/Select';
import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import Spinner from '../../../components/Spinner';
import { RedirectsIcon } from '../../../components/Icons';
import type { NoticeState } from '../../../types/api';
import type { NotFoundManagerSettings } from '../../../types/settings';

type NotFoundManagerTabProps = {
	settings: NotFoundManagerSettings;
	onChange: ( next: NotFoundManagerSettings ) => void;
	restBase: string;
	onNotice: ( notice: NoticeState ) => void;
};

type NotFoundStats = {
	total_urls: number;
	total_hits: number;
	open_urls: number;
};

type NotFoundLogItem = {
	id: number;
	url_path: string;
	query_hash: string | null;
	hits: number;
	first_seen_at: string;
	last_seen_at: string;
	last_referer: string | null;
	last_user_agent: string | null;
	status: 'open' | 'ignored' | 'resolved';
	matched_redirect_id: number | null;
};

type NotFoundLogsResponse = {
	items: NotFoundLogItem[];
	total: number;
	page: number;
	per_page: number;
};

type LogStatusFilter = 'all' | 'open' | 'ignored' | 'resolved';
type BatchAction = 'resolve' | 'ignore' | 'delete';

const NotFoundManagerTab = ( {
	settings,
	onChange,
	restBase,
	onNotice,
}: NotFoundManagerTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'records'>( 'settings' );
	const [ stats, setStats ] = useState<NotFoundStats | null>( null );
	const [ logs, setLogs ] = useState<NotFoundLogItem[]>( [] );
	const [ logsTotal, setLogsTotal ] = useState( 0 );
	const [ logsPage, setLogsPage ] = useState( 1 );
	const [ statusFilter, setStatusFilter ] = useState<LogStatusFilter>( 'all' );
	const [ query, setQuery ] = useState( '' );
	const [ isLoadingRecords, setIsLoadingRecords ] = useState( false );
	const [ rowBusyId, setRowBusyId ] = useState<number | null>( null );
	const [ selectedIds, setSelectedIds ] = useState<number[]>( [] );
	const [ isBatchBusy, setIsBatchBusy ] = useState( false );
	const [ pendingBatchAction, setPendingBatchAction ] = useState<BatchAction | null>( null );
	const perPage = 20;
	const showAdvancedColumns = settings.monitorMode === 'advanced';
	const tableColumnCount = showAdvancedColumns ? 6 : 4;

	const updateSettings = ( patch: Partial<NotFoundManagerSettings> ) => {
		onChange( { ...settings, ...patch } );
	};

	const logsTotalPages = useMemo( () => Math.max( 1, Math.ceil( logsTotal / perPage ) ), [ logsTotal ] );
	const visibleIds = useMemo( () => logs.map( ( item ) => item.id ), [ logs ] );
	const allVisibleSelected = useMemo(
		() => visibleIds.length > 0 && visibleIds.every( ( id ) => selectedIds.includes( id ) ),
		[ selectedIds, visibleIds ],
	);

	const loadStats = useCallback( async () => {
		try {
			const response = await apiFetch<NotFoundStats>( {
				path: `${ restBase }/404-manager/stats`,
				method: 'GET',
			} );
			setStats( response );
		} catch {
			onNotice( {
				status: 'error',
				message: __( 'Failed to load 404 stats.', 'airygen-seo' ),
			} );
		}
	}, [ onNotice, restBase ] );

	const loadLogs = useCallback( async () => {
		setIsLoadingRecords( true );
		try {
			const params = new URLSearchParams();
			params.set( 'page', String( logsPage ) );
			params.set( 'per_page', String( perPage ) );
			if ( statusFilter !== 'all' ) {
				params.set( 'status', statusFilter );
			}
			if ( query.trim() !== '' ) {
				params.set( 'q', query.trim() );
			}
			const response = await apiFetch<NotFoundLogsResponse>( {
				path: `${ restBase }/404-manager/logs?${ params.toString() }`,
				method: 'GET',
			} );
			setLogs( Array.isArray( response.items ) ? response.items : [] );
			setLogsTotal( Number.isFinite( response.total ) ? response.total : 0 );
			setSelectedIds( [] );
		} catch {
			onNotice( {
				status: 'error',
				message: sprintf(
					/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
					__( 'Failed to load %s records.', 'airygen-seo' ),
					'404',
				),
			} );
		} finally {
			setIsLoadingRecords( false );
		}
	}, [ logsPage, onNotice, query, restBase, statusFilter ] );

	useEffect( () => {
		void loadStats();
	}, [ loadStats ] );

	useEffect( () => {
		if ( activeTab !== 'records' ) {
			return;
		}
		void loadLogs();
	}, [ activeTab, loadLogs ] );

	const executeRowAction = async ( id: number, action: 'resolve' | 'ignore' | 'delete' ) => {
		let path = `${ restBase }/404-manager/logs/${ id }`;
		if ( action === 'resolve' ) {
			path = `${ restBase }/404-manager/logs/${ id }/resolve`;
		} else if ( action === 'ignore' ) {
			path = `${ restBase }/404-manager/logs/${ id }/ignore`;
		}

		try {
			await apiFetch( {
				path,
				method: action === 'delete' ? 'DELETE' : 'POST',
			} );
			return true;
		} catch {
			return false;
		}
	};

	const runRowAction = async ( id: number, action: 'resolve' | 'ignore' | 'delete' ) => {
		setRowBusyId( id );
		const ok = await executeRowAction( id, action );
		if ( ! ok ) {
			onNotice( {
				status: 'error',
				message: __( 'Failed to update 404 record.', 'airygen-seo' ),
			} );
			setRowBusyId( null );
			return;
		}

		const message =
			action === 'delete'
				? __( 'Record deleted.', 'airygen-seo' )
				: __( 'Record updated.', 'airygen-seo' );
		onNotice( { status: 'success', message } );
		await loadStats();
		await loadLogs();
		setRowBusyId( null );
	};

	const runBatchAction = async ( action: BatchAction ) => {
		if ( selectedIds.length === 0 || isBatchBusy ) {
			return;
		}

		setIsBatchBusy( true );
		let success = 0;
		for ( const id of selectedIds ) {
			// eslint-disable-next-line no-await-in-loop
			const ok = await executeRowAction( id, action );
			if ( ok ) {
				success += 1;
			}
		}

		if ( success > 0 ) {
			onNotice( {
				status: 'success',
				message: __( 'Batch action completed.', 'airygen-seo' ),
			} );
		} else {
			onNotice( {
				status: 'error',
				message: __( 'Failed to update selected records.', 'airygen-seo' ),
			} );
		}

		await loadStats();
		await loadLogs();
		setIsBatchBusy( false );
	};

	const getBatchActionLabel = ( action: BatchAction | null ) => {
		if ( action === 'resolve' ) {
			return __( 'Resolve selected', 'airygen-seo' );
		}
		if ( action === 'ignore' ) {
			return __( 'Ignore selected', 'airygen-seo' );
		}
		return __( 'Delete selected', 'airygen-seo' );
	};

	const handleSearch = () => {
		setLogsPage( 1 );
		void loadLogs();
	};

	const renderTableRows = () => {
		if ( isLoadingRecords ) {
			return (
				<tr>
					<td colSpan={ tableColumnCount } className="px-3 py-6 text-center text-slate-500">
						<div className="inline-flex items-center gap-2"><Spinner size="sm" />{ __( 'Loading records…', 'airygen-seo' ) }</div>
					</td>
				</tr>
			);
		}

		if ( logs.length === 0 ) {
			return (
				<tr>
					<td colSpan={ tableColumnCount } className="px-3 py-6 text-center text-slate-500">
						{ __( 'No records found.', 'airygen-seo' ) }
					</td>
				</tr>
			);
		}

		return logs.map( ( item ) => (
			<tr key={ item.id }>
				<td className="px-3 py-2">
					<input
						type="checkbox"
						className="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-300"
						checked={ selectedIds.includes( item.id ) }
						onChange={ ( event ) => {
							const checked = event.target.checked;
							setSelectedIds( ( prev ) => {
								if ( checked ) {
									if ( prev.includes( item.id ) ) {
										return prev;
									}
									return [ ...prev, item.id ];
								}
								return prev.filter( ( id ) => id !== item.id );
							} );
						} }
					/>
				</td>
				<td className="px-3 py-2">
					<p className="font-medium text-slate-800">{ item.url_path }</p>
					{ item.last_referer ? (
						<p className="mt-1 text-xs text-slate-500">{ item.last_referer }</p>
					) : null }
				</td>
				<td className="px-3 py-2 text-slate-700">{ item.status }</td>
				{ showAdvancedColumns ? <td className="px-3 py-2 text-slate-700">{ item.hits }</td> : null }
				{ showAdvancedColumns ? <td className="px-3 py-2 text-slate-700">{ item.last_seen_at }</td> : null }
				<td className="px-3 py-2">
					<div className="flex items-center justify-end gap-2">
						<Button
							variant="secondary"
							onClick={ () => void runRowAction( item.id, 'resolve' ) }
							disabled={ rowBusyId === item.id || isBatchBusy }
							className="text-xs"
						>
							{ __( 'Resolve', 'airygen-seo' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => void runRowAction( item.id, 'ignore' ) }
							disabled={ rowBusyId === item.id || isBatchBusy }
							className="text-xs"
						>
							{ __( 'Ignore', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ () => void runRowAction( item.id, 'delete' ) }
							disabled={ rowBusyId === item.id || isBatchBusy }
							className="text-xs"
						>
							{ __( 'Delete', 'airygen-seo' ) }
						</Button>
					</div>
				</td>
			</tr>
		) );
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<RedirectsIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ sprintf(
							/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
							__( '%s Manager', 'airygen-seo' ),
							'404',
						) }
					</div>
					<div className="airygen_h1_description">
						{ __( 'Track 404 traffic, keep only meaningful logs, and define fallback behavior when no specific redirect rule matches.', 'airygen-seo' ) }
					</div>
				</div>
			</div>

			<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
				<button
					type="button"
					data-airygen-e2e="tab-settings"
					onClick={ () => setActiveTab( 'settings' ) }
					className={
						activeTab === 'settings'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
				>
					{ __( 'Settings', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-records"
					onClick={ () => setActiveTab( 'records' ) }
					className={
						activeTab === 'records'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
				>
					{ __( 'Records', 'airygen-seo' ) }
				</button>
			</div>

			{ activeTab === 'settings' ? (
				<>
					<section className="rounded-lg border border-slate-200 bg-white p-4">
						<div className="mb-3 space-y-1">
							<div className="airygen_h2_title capitalize">
								{ __( 'Monitoring', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Control how 404 requests are tracked, stored, and filtered.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="grid gap-3 md:grid-cols-4">
							<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
								<Select
									label={ __( 'Monitor mode', 'airygen-seo' ) }
									value={ settings.monitorMode }
									onChange={ ( value ) =>
										updateSettings( {
											monitorMode: value === 'advanced' ? 'advanced' : 'simple',
										} )
									}
									options={ [
										{ value: 'simple', label: __( 'Simple', 'airygen-seo' ) },
										{ value: 'advanced', label: __( 'Advanced', 'airygen-seo' ) },
									] }
									help={ __( 'Simple logs the first hit only. Advanced keeps hits, last seen time, referer, and user agent updated.', 'airygen-seo' ) }
								/>
							</div>
							<div className="airygen-setting-card__input--column rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Log limit', 'airygen-seo' ) }
									type="number"
									value={ String( settings.logLimit ) }
									onChange={ ( value ) =>
										updateSettings( {
											logLimit: Number.isFinite( Number( value ) )
												? Math.max( 100, Math.min( 100000, Number( value ) ) )
												: settings.logLimit,
										} )
									}
									help={ sprintf(
										/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
										__( 'Maximum number of %s records kept in the database.', 'airygen-seo' ),
										'404',
									) }
								/>
							</div>
							<div className="airygen-setting-card__input--column rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Retention days', 'airygen-seo' ) }
									type="number"
									value={ String( settings.retentionDays ) }
									onChange={ ( value ) =>
										updateSettings( {
											retentionDays: Number.isFinite( Number( value ) )
												? Math.max( 1, Math.min( 3650, Number( value ) ) )
												: settings.retentionDays,
										} )
									}
									help={ __( 'Old logs are cleaned up automatically after this period.', 'airygen-seo' ) }
								/>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Ignore query params', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Ignore query params', 'airygen-seo' ) }
										checked={ settings.ignoreQueryParams }
										onChange={ ( checked ) => updateSettings( { ignoreQueryParams: checked } ) }
										hideLabelText
									/>
								</div>
								<p className="text-xs text-slate-500">{ __( 'Treat /page?a=1 and /page?a=2 as the same path.', 'airygen-seo' ) }</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 md:col-span-2">
								<Textarea
									label={ __( 'Exclude patterns', 'airygen-seo' ) }
									value={ settings.excludePatterns.join( '\n' ) }
									onChange={ ( value ) =>
										updateSettings( {
											excludePatterns: value
												.split( /\r?\n/ )
												.map( ( item ) => item.trim() )
												.filter( ( item ) => item !== '' ),
										} )
									}
									help={ __( 'One pattern per line. Requests matching these patterns are ignored.', 'airygen-seo' ) }
									rows={ 4 }
								/>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4 md:col-span-2">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Daily alerts', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Daily alerts', 'airygen-seo' ) }
										checked={ settings.enableDailyAlert }
										onChange={ ( checked ) => updateSettings( { enableDailyAlert: checked } ) }
										hideLabelText
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Receive daily alerts for newly detected records.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					</section>

					<section className="rounded-lg border border-slate-200 bg-white p-4">
						<div className="mb-3 space-y-1">
							<div className="airygen_h2_title capitalize">{ __( 'Fallback redirect', 'airygen-seo' ) }</div>
							<p className="text-sm text-slate-500">{ __( 'Define what should happen when a URL hits 404 and no redirect rule is matched.', 'airygen-seo' ) }</p>
						</div>
						<div className="mt-3 grid gap-3 md:grid-cols-4">
							<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4 md:col-span-1">
								<Select
									label={ __( 'Mode', 'airygen-seo' ) }
									value={ settings.fallbackRedirectMode }
									onChange={ ( value ) =>
										updateSettings( {
											fallbackRedirectMode:
												value === 'home' || value === 'custom' ? value : 'off',
										} )
									}
									options={ [
										{ value: 'off', label: __( 'Off', 'airygen-seo' ) },
										{ value: 'home', label: __( 'Redirect to homepage', 'airygen-seo' ) },
										{ value: 'custom', label: __( 'Redirect to custom URL', 'airygen-seo' ) },
									] }
								/>
							</div>
							<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4 md:col-span-1">
								<Select
									label={ __( 'Status code', 'airygen-seo' ) }
									value={ String( settings.fallbackRedirectCode ) }
									onChange={ ( value ) => {
										const parsed = Number( value );
										if ( parsed === 301 || parsed === 302 || parsed === 307 || parsed === 410 || parsed === 451 ) {
											updateSettings( {
												fallbackRedirectCode: parsed,
											} );
										}
									} }
									options={ [
										{ value: '301', label: '301' },
										{ value: '302', label: '302' },
										{ value: '307', label: '307' },
										{ value: '410', label: '410' },
										{ value: '451', label: '451' },
									] }
								/>
							</div>
							{ settings.fallbackRedirectMode === 'custom' ? (
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 md:col-span-2">
									<Input
										label={ __( 'Custom target URL', 'airygen-seo' ) }
										value={ settings.fallbackRedirectTarget }
										onChange={ ( value ) => updateSettings( { fallbackRedirectTarget: value } ) }
										help={ __( 'Used only when mode is set to custom URL.', 'airygen-seo' ) }
										isUrl
									/>
								</div>
							) : null }
						</div>
					</section>

				</>
			) : (
				<div className="space-y-4">
					<div className="grid gap-3 md:grid-cols-3">
						<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 bg-white p-3">
							<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Total URLs', 'airygen-seo' ) }</p>
							<p className="pt-2 text-2xl font-semibold text-slate-900">{ stats ? stats.total_urls : '—' }</p>
						</div>
						<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 bg-white p-3">
							<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Total hits', 'airygen-seo' ) }</p>
							<p className="pt-2 text-2xl font-semibold text-slate-900">{ stats ? stats.total_hits : '—' }</p>
						</div>
						<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 bg-white p-3">
							<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Open URLs', 'airygen-seo' ) }</p>
							<p className="pt-2 text-2xl font-semibold text-slate-900">{ stats ? stats.open_urls : '—' }</p>
						</div>
					</div>

					<div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="grid gap-3 md:grid-cols-4">
							<Select
								label={ __( 'Status', 'airygen-seo' ) }
								value={ statusFilter }
								onChange={ ( value ) => {
									setLogsPage( 1 );
									if ( value === 'open' || value === 'ignored' || value === 'resolved' ) {
										setStatusFilter( value );
										return;
									}
									setStatusFilter( 'all' );
								} }
								options={ [
									{ value: 'all', label: __( 'All', 'airygen-seo' ) },
									{ value: 'open', label: __( 'Open', 'airygen-seo' ) },
									{ value: 'ignored', label: __( 'Ignored', 'airygen-seo' ) },
									{ value: 'resolved', label: __( 'Resolved', 'airygen-seo' ) },
								] }
							/>
							<Input
								className="md:col-span-2"
								label={ __( 'Search path', 'airygen-seo' ) }
								value={ query }
								onChange={ setQuery }
								placeholder={ __( 'e.g. /old-page', 'airygen-seo' ) }
							/>
							<div className="flex items-end">
								<Button variant="secondary" onClick={ handleSearch } className="w-full text-xs">
									{ __( 'Search', 'airygen-seo' ) }
								</Button>
							</div>
						</div>

						<div className="mt-4 overflow-x-auto">
							<div className="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
								<p className="text-xs text-slate-600">
									{ __( 'Selected', 'airygen-seo' ) }: { selectedIds.length }
								</p>
								<div className="flex items-center gap-2">
									<Button
										variant="secondary"
										className="text-xs"
										onClick={ () => setPendingBatchAction( 'resolve' ) }
										disabled={ selectedIds.length === 0 || isBatchBusy }
										loading={ isBatchBusy }
									>
										{ __( 'Resolve selected', 'airygen-seo' ) }
									</Button>
									<Button
										variant="secondary"
										className="text-xs"
										onClick={ () => setPendingBatchAction( 'ignore' ) }
										disabled={ selectedIds.length === 0 || isBatchBusy }
										loading={ isBatchBusy }
									>
										{ __( 'Ignore selected', 'airygen-seo' ) }
									</Button>
									<Button
										variant="outline"
										className="text-xs"
										onClick={ () => setPendingBatchAction( 'delete' ) }
										disabled={ selectedIds.length === 0 || isBatchBusy }
										loading={ isBatchBusy }
									>
										{ __( 'Delete selected', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
							<table className="min-w-full divide-y divide-slate-200 text-sm">
								<thead className="bg-slate-50">
									<tr>
										<th className="px-3 py-2 text-left font-medium text-slate-600">
											<input
												type="checkbox"
												className="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-300"
												checked={ allVisibleSelected }
												onChange={ ( event ) => {
													const checked = event.target.checked;
													if ( checked ) {
														setSelectedIds( [ ...new Set( [ ...selectedIds, ...visibleIds ] ) ] );
														return;
													}
													setSelectedIds( selectedIds.filter( ( id ) => ! visibleIds.includes( id ) ) );
												} }
											/>
										</th>
										<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Path', 'airygen-seo' ) }</th>
										<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Status', 'airygen-seo' ) }</th>
										{ showAdvancedColumns ? (
											<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Hits', 'airygen-seo' ) }</th>
										) : null }
										{ showAdvancedColumns ? (
											<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Last seen', 'airygen-seo' ) }</th>
										) : null }
										<th className="px-3 py-2 text-right font-medium text-slate-600">{ __( 'Actions', 'airygen-seo' ) }</th>
									</tr>
								</thead>
								<tbody className="divide-y divide-slate-200 bg-white">{ renderTableRows() }</tbody>
							</table>
						</div>

						<div className="mt-4 flex items-center justify-between">
							<p className="text-xs text-slate-500">
								{ __( 'Total records', 'airygen-seo' ) }: { logsTotal }
							</p>
							<div className="flex items-center gap-2">
								<Button
									variant="secondary"
									onClick={ () => setLogsPage( ( prev ) => Math.max( 1, prev - 1 ) ) }
									disabled={ logsPage <= 1 || isLoadingRecords }
									className="text-xs"
								>
									{ __( 'Prev', 'airygen-seo' ) }
								</Button>
								<span className="text-xs text-slate-600">{ logsPage } / { logsTotalPages }</span>
								<Button
									variant="secondary"
									onClick={ () => setLogsPage( ( prev ) => Math.min( logsTotalPages, prev + 1 ) ) }
									disabled={ logsPage >= logsTotalPages || isLoadingRecords }
									className="text-xs"
								>
									{ __( 'Next', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
					</div>
				</div>
			) }
			<Modal
				isOpen={ pendingBatchAction !== null }
				onClose={ () => setPendingBatchAction( null ) }
				title={ __( 'Confirm batch action', 'airygen-seo' ) }
				maxWidth="max-w-md"
				footer={
					<div className="flex items-center justify-end gap-2">
						<Button variant="secondary" className="text-xs" onClick={ () => setPendingBatchAction( null ) }>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant={ pendingBatchAction === 'delete' ? 'outline' : 'secondary' }
							className="text-xs"
							onClick={ () => {
								if ( ! pendingBatchAction ) {
									return;
								}
								void runBatchAction( pendingBatchAction );
								setPendingBatchAction( null );
							} }
						>
							{ getBatchActionLabel( pendingBatchAction ) }
						</Button>
					</div>
				}
			>
				<p className="text-sm text-slate-700">
					{ __( 'Apply this action to all selected records?', 'airygen-seo' ) }
				</p>
				<p className="mt-2 text-xs text-slate-500">
					{ __( 'Selected records', 'airygen-seo' ) }: { selectedIds.length }
				</p>
			</Modal>
		</div>
	);
};

export default NotFoundManagerTab;

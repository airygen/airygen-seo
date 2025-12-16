import apiFetch from '@wordpress/api-fetch';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import type { ChangeEvent, ReactNode } from 'react';
import Button from '../../../components/Button';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import ActionSchedulerPill from '../../../components/ActionSchedulerPill';
import { BrokenLinkCheckerIcon } from '../../../components/Icons';
import { __, sprintf } from '@wordpress/i18n';
import {
	getNoItemsMatchCurrentFiltersLabel,
	getNoLogsYetLabel,
} from '../../../../shared/i18nPhrases';
import type {
	BrokenLinkCheckerSettings,
	BrokenLinkCheckerStatusMeta,
} from '../../../types/settings';
import formatDateTime from '../../../utils/formatDateTime';

type Props = {
	restBase: string;
	settings: BrokenLinkCheckerSettings;
	linkCounterEnabled: boolean;
	actionSchedulerAvailable?: boolean;
	status?: BrokenLinkCheckerStatusMeta;
	defaults: BrokenLinkCheckerSettings;
	onChange: ( value: BrokenLinkCheckerSettings ) => void;
};

type BrokenLinkLogEntry = {
	id: number;
	postId: number;
	postTitle: string;
	postEditLink: string | null;
	postViewLink: string | null;
	url: string;
	statusCode: number | null;
	statusLabel: string | null;
	errorMessage: string | null;
	checkedAt: string | null;
	createdAt: string | null;
};

type BrokenLinkLogResponse = {
	logs: BrokenLinkLogEntry[];
	pagination: {
		page: number;
		perPage: number;
		totalPages: number;
		totalItems: number;
	};
};

type StatusFilterKey = 'ok' | 'redirect' | 'error';
type StatusFilters = Record<StatusFilterKey, boolean>;

const STATUS_FILTER_KEYS: StatusFilterKey[] = [ 'ok', 'redirect', 'error' ];

type LogActionLinkProps = {
	href: string;
	label: string;
	children: ReactNode;
};

const LogActionLink = ( { href, label, children }: LogActionLinkProps ) => (
	<a
		className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-1"
		href={ href }
		target="_blank"
		rel="noreferrer"
		aria-label={ label }
		title={ label }
	>
		<span className="sr-only">{ label }</span>
		{ children }
	</a>
);

const EditPostIcon = () => (
	<svg
		width="16"
		height="16"
		viewBox="0 0 7 7"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
	>
		<path
			d="M2.2214 3.33237H4.44277V3.88771H2.2214V3.33237ZM2.77674 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V3.36014L5.55345 2.8048V2.22169L3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H2.77674V5.55374ZM2.2214 4.99839H3.35985L3.60975 4.74849V4.44305H2.2214V4.99839ZM5.60898 3.61004C5.63675 3.61004 5.69228 3.63781 5.72005 3.66558L6.08102 4.02655C6.13656 4.08208 6.13656 4.19315 6.08102 4.24868L5.80335 4.52635L5.22024 3.94325L5.49791 3.66558C5.52568 3.63781 5.55345 3.61004 5.60898 3.61004ZM5.60898 4.69296L3.91519 6.38675H3.33208V5.80364L5.02587 4.10985L5.60898 4.69296Z"
			fill="currentColor"
		/>
	</svg>
);

const ViewPostIcon = () => (
	<svg
		width="16"
		height="16"
		viewBox="0 0 7 7"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
	>
		<path
			d="M3.33196 2.49952C3.55289 2.49952 3.76477 2.58728 3.92099 2.7435C4.07721 2.89972 4.16498 3.1116 4.16498 3.33253C4.16498 3.55346 4.07721 3.76534 3.92099 3.92156C3.76477 4.07778 3.55289 4.16554 3.33196 4.16554C3.11104 4.16554 2.89916 4.07778 2.74294 3.92156C2.58672 3.76534 2.49895 3.55346 2.49895 3.33253C2.49895 3.1116 2.58672 2.89972 2.74294 2.7435C2.89916 2.58728 3.11104 2.49952 3.33196 2.49952ZM3.33196 1.25C4.72032 1.25 5.90597 2.11356 6.38634 3.33253C5.90597 4.5515 4.72032 5.41506 3.33196 5.41506C1.94361 5.41506 0.757958 4.5515 0.277588 3.33253C0.757958 2.11356 1.94361 1.25 3.33196 1.25ZM0.88291 3.33253C1.34107 4.2655 2.28792 4.85972 3.33196 4.85972C4.37601 4.85972 5.32286 4.2655 5.78102 3.33253C5.32286 2.39956 4.37601 1.80534 3.33196 1.80534C2.28792 1.80534 1.34107 2.39956 0.88291 3.33253Z"
			fill="currentColor"
		/>
	</svg>
);

const OpenUrlIcon = () => (
	<svg
		width="16"
		height="16"
		viewBox="0 0 7 7"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
	>
		<path
			d="M3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H3.60975C3.49591 6.03966 3.38762 5.95358 3.29321 5.85917C3.20158 5.76754 3.12383 5.6648 3.05441 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V2.82701C5.19525 2.87144 5.38407 2.94641 5.55345 3.0547V2.22169L3.88742 0.555664ZM5.63953 5.2483C6.00883 4.66241 5.83112 3.88771 5.25079 3.52119C4.6649 3.15188 3.88742 3.33237 3.52368 3.90993C3.1516 4.49581 3.33208 5.26774 3.91241 5.63704C4.31781 5.89527 4.83428 5.89527 5.24246 5.64259L6.10879 6.49504L6.49475 6.10908L5.63953 5.2483ZM4.5816 5.27607C4.39749 5.27607 4.22093 5.20293 4.09074 5.07275C3.96056 4.94256 3.88742 4.766 3.88742 4.58189C3.88742 4.39778 3.96056 4.22121 4.09074 4.09103C4.22093 3.96085 4.39749 3.88771 4.5816 3.88771C4.76571 3.88771 4.94227 3.96085 5.07246 4.09103C5.20264 4.22121 5.27578 4.39778 5.27578 4.58189C5.27578 4.766 5.20264 4.94256 5.07246 5.07275C4.94227 5.20293 4.76571 5.27607 4.5816 5.27607Z"
			fill="currentColor"
		/>
	</svg>
);

const formatLogDate = ( value: string | null | undefined ) =>
	formatDateTime( value, '—' );

const BrokenLinkCheckerTab = ( {
	restBase,
	settings,
	linkCounterEnabled,
	actionSchedulerAvailable,
	status,
	defaults: _defaultSettings,
	onChange,
}: Props ) => {
	const normalizedBase = useMemo(
		() => restBase.replace( /\/$/, '' ),
		[ restBase ],
	);
	const logsPath = `${ normalizedBase }/broken-links/logs`;

	const [ entries, setEntries ] = useState<BrokenLinkLogEntry[]>( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ errorMessage, setErrorMessage ] = useState<string | null>( null );
	const [ pagination, setPagination ] = useState<{
		page: number;
		perPage: number;
		totalPages: number;
		totalItems: number;
	}>( {
		page: 1,
		perPage: 20,
		totalPages: 1,
		totalItems: 0,
	} );
	const [ statusFilters, setStatusFilters ] = useState<StatusFilters>( {
		ok: true,
		redirect: true,
		error: true,
	} );
	const [ activeTab, setActiveTab ] = useState<'settings' | 'logs'>( 'settings' );
	const selectedStatuses = useMemo(
		() => STATUS_FILTER_KEYS.filter( ( key ) => statusFilters[ key ] ),
		[ statusFilters ],
	);
	const hasAllStatuses = selectedStatuses.length === STATUS_FILTER_KEYS.length;
	const emptyStateLabel = hasAllStatuses
		? getNoLogsYetLabel()
		: getNoItemsMatchCurrentFiltersLabel( __( 'log entries', 'airygen-seo' ) );
	const showSchedulerPill = typeof actionSchedulerAvailable === 'boolean';

	const fetchLogs = useCallback(
		( page = 1 ) => {
			setIsLoading( true );
			const targetPage = Math.max( 1, page );
			const params = new URLSearchParams( {
				page: String( targetPage ),
			} );

			if ( selectedStatuses.length > 0 ) {
				params.set( 'statuses', selectedStatuses.join( ',' ) );
			}

			return apiFetch<BrokenLinkLogResponse>( {
				path: `${ logsPath }?${ params.toString() }`,
			} )
				.then( ( response ) => {
					setEntries( response?.logs ?? [] );
					const data = response?.pagination ?? {
						page: targetPage,
						perPage: 20,
						totalPages: 1,
						totalItems: 0,
					};

					setPagination( {
						page: data.page ?? targetPage,
						perPage: data.perPage ?? 20,
						totalPages: data.totalPages ?? 1,
						totalItems: data.totalItems ?? 0,
					} );
					setErrorMessage( null );
				} )
				.catch( ( error: unknown ) => {
					const message =
						error instanceof Error
							? error.message
							: __( 'Unable to load logs.', 'airygen-seo' );
					setErrorMessage( message );
				} )
				.finally( () => {
					setIsLoading( false );
				} );
		},
		[ logsPath, selectedStatuses ],
	);

	useEffect( () => {
		fetchLogs( 1 );
	}, [ fetchLogs ] );

	const logPageLabel = sprintf(
		/* translators: 1: current page, 2: total pages. */
		__( 'Page %1$s of %2$s', 'airygen-seo' ),
		String( pagination.page ),
		String( Math.max( 1, pagination.totalPages ) ),
	);
	const queueStatus = status?.queue;
	const queueMetrics = [
		{
			label: __( 'Pending jobs', 'airygen-seo' ),
			value: queueStatus?.pending ?? null,
		},
		{
			label: __( 'Failed jobs', 'airygen-seo' ),
			value: queueStatus?.failed ?? null,
		},
		{
			label: __( 'Completed jobs', 'airygen-seo' ),
			value: queueStatus?.completed ?? null,
		},
	];
	const lastRunLabel = formatDateTime(
		status?.lastRunGmt,
		__( 'Not scheduled', 'airygen-seo' ),
	);
	const queueActionLink = `/wp-admin/tools.php?page=action-scheduler&s=${ encodeURIComponent(
		'airygen_broken_link_checker_run',
	) }&orderby=schedule&order=desc`;

	const updateSettings = useCallback(
		( patch: Partial<BrokenLinkCheckerSettings> ) => {
			onChange( { ...settings, ...patch } );
		},
		[ onChange, settings ],
	);

	const handleStatusFilterToggle = useCallback( ( key: StatusFilterKey ) => {
		setStatusFilters( ( current ) => {
			const next = { ...current, [ key ]: ! current[ key ] };
			if ( ! next.ok && ! next.redirect && ! next.error ) {
				return current;
			}
			return next;
		} );
	}, [] );

	const handleNumberChange = (
		field:
			| 'checkIntervalHours'
			| 'maxRequestsPerRun'
			| 'batchDelayMinutes'
			| 'logRetentionDays'
			| 'connectionTimeoutSeconds'
			| 'operationTimeoutSeconds',
		min: number,
		max: number,
	) => ( event: ChangeEvent<HTMLInputElement> ) => {
		const value = Number( event.target.value );
		const sanitized = Number.isFinite( value )
			? Math.min( max, Math.max( min, Math.round( value ) ) )
			: min;

		updateSettings( { [ field ]: sanitized } as Partial<BrokenLinkCheckerSettings> );
	};

	const handleLinkTypeToggle = (
		type: 'external' | 'internal',
	) => ( event: ChangeEvent<HTMLInputElement> ) => {
		const next = {
			...settings.linkTypes,
			[ type ]: event.target.checked,
		};

		if ( ! next.external && ! next.internal ) {
			next[ type ] = true;
		}

		updateSettings( { linkTypes: next } as Partial<BrokenLinkCheckerSettings> );
	};

	const renderStatusMeta = ( entry: BrokenLinkLogEntry ) => {
		if ( entry.statusLabel ) {
			return (
				<p className="flex items-center text-xs text-slate-500">
					<span>{ entry.statusLabel }</span>
					{ entry.errorMessage ? (
						<ErrorTooltip message={ entry.errorMessage } />
					) : null }
				</p>
			);
		}

		if ( entry.errorMessage ) {
			return (
				<p className="flex items-center text-xs text-slate-500">
					<span>{ __( 'Error', 'airygen-seo' ) }</span>
					<ErrorTooltip message={ entry.errorMessage } />
				</p>
			);
		}

		return null;
	};

	const renderTableRows = () => {
		if ( isLoading ) {
			return (
				<tr>
					<td className="px-3 py-4 text-center text-slate-500" colSpan={ 5 }>
						{ __( 'Loading…', 'airygen-seo' ) }
					</td>
				</tr>
			);
		}

		if ( errorMessage ) {
			return (
				<tr>
					<td className="px-3 py-4 text-center text-rose-600" colSpan={ 5 }>
						{ errorMessage }
					</td>
				</tr>
			);
		}

		if ( entries.length === 0 ) {
			return (
				<tr>
					<td className="px-3 py-4 text-center text-slate-500" colSpan={ 5 }>
						{ emptyStateLabel }
					</td>
				</tr>
			);
		}

		return entries.map( ( entry ) => (
			<tr key={ entry.id }>
				<td className="w-1/3 px-3 py-3 align-top">
					<div className="break-words font-medium text-slate-900">
						{ entry.postTitle }
					</div>
				</td>
				<td className="w-1/3 px-3 py-3 align-top">
					<div className="break-all text-slate-900">{ entry.url }</div>
				</td>
				<td className="px-3 py-3 align-top">
					<div className="font-medium text-slate-900">
						{ entry.statusCode ?? '—' }
					</div>
					{ renderStatusMeta( entry ) }
				</td>
				<td className="px-3 py-3 align-top text-xs text-slate-600">
					{ formatLogDate( entry.checkedAt ) }
				</td>
				<td className="px-3 py-3 align-top text-right">
					<div className="flex items-center justify-end gap-2">
						{ entry.postEditLink ? (
							<LogActionLink
								href={ entry.postEditLink }
								label={ __( 'Edit post', 'airygen-seo' ) }
							>
								<EditPostIcon />
							</LogActionLink>
						) : null }
						{ entry.postViewLink ? (
							<LogActionLink
								href={ entry.postViewLink }
								label={ __( 'View post', 'airygen-seo' ) }
							>
								<ViewPostIcon />
							</LogActionLink>
						) : null }
						{ entry.url ? (
							<LogActionLink
								href={ entry.url }
								label={ __( 'Open link', 'airygen-seo' ) }
							>
								<OpenUrlIcon />
							</LogActionLink>
						) : null }
					</div>
				</td>
			</tr>
		) );
	};

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<BrokenLinkCheckerIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'Broken Link Checker', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Scan content for broken and redirected links, and monitor crawl health.',
								'airygen-seo',
							) }
						</p>
					</div>
				</div>
				<div className="flex flex-col items-start gap-2 sm:items-end">
					{ showSchedulerPill ? (
						<ActionSchedulerPill available={ Boolean( actionSchedulerAvailable ) } />
					) : null }
					{ ! linkCounterEnabled ? (
						<div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
							{ __(
								'Link Counter must be enabled to gather internal link data.',
								'airygen-seo',
							) }
						</div>
					) : null }
				</div>
			</div>

			<div className="space-y-4">
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
						data-airygen-e2e="tab-logs"
						className={
							'logs' === activeTab
								? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
								: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
						}
						onClick={ () => setActiveTab( 'logs' ) }
					>
						{ __( 'Logs', 'airygen-seo' ) }
					</button>
				</div>

				{ 'settings' === activeTab ? (
					<>
						<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
							<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
								<div>
									<div className="airygen_h2_title">
										{ __( 'Settings', 'airygen-seo' ) }
									</div>
									<p className="mt-1 text-sm text-slate-500">
										{ __(
											'Tune scan frequency and limits. Lower numbers are safer on shared hosts.',
											'airygen-seo',
										) }
									</p>
								</div>
							</div>
							<div className="grid gap-4 md:grid-cols-4">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="text-sm font-medium text-slate-700" htmlFor="blc-interval">
										{ __( 'Check interval (hours)', 'airygen-seo' ) }
									</label>
									<input
										id="blc-interval"
										type="number"
										min={ 1 }
										max={ 168 }
										className="airygen-field mt-1 w-full"
										value={ settings.checkIntervalHours }
										onChange={ handleNumberChange( 'checkIntervalHours', 1, 168 ) }
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'How often the scanner runs. Set higher on large sites.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="text-sm font-medium text-slate-700" htmlFor="blc-concurrency">
										{ __( 'Max requests per run', 'airygen-seo' ) }
									</label>
									<input
										id="blc-concurrency"
										type="number"
										min={ 1 }
										max={ 50 }
										className="airygen-field mt-1 w-full"
										value={ settings.maxRequestsPerRun }
										onChange={ handleNumberChange( 'maxRequestsPerRun', 1, 50 ) }
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'Limits simultaneous HTTP requests during a scan.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="text-sm font-medium text-slate-700" htmlFor="blc-delay">
										{ __( 'Delay between batches (minutes)', 'airygen-seo' ) }
									</label>
									<input
										id="blc-delay"
										type="number"
										min={ 1 }
										max={ 60 }
										className="airygen-field mt-1 w-full"
										value={ settings.batchDelayMinutes }
										onChange={ handleNumberChange( 'batchDelayMinutes', 1, 60 ) }
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'Pause between request batches to reduce load.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="text-sm font-medium text-slate-700" htmlFor="blc-retention">
										{ __( 'Log retention (days)', 'airygen-seo' ) }
									</label>
									<input
										id="blc-retention"
										type="number"
										min={ 1 }
										max={ 365 }
										className="airygen-field mt-1 w-full"
										value={ settings.logRetentionDays }
										onChange={ handleNumberChange( 'logRetentionDays', 1, 365 ) }
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'Older log entries are deleted automatically.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="text-sm font-medium text-slate-700" htmlFor="blc-conn-timeout">
										{ __( 'Connection timeout (seconds)', 'airygen-seo' ) }
									</label>
									<input
										id="blc-conn-timeout"
										type="number"
										min={ 1 }
										max={ 30 }
										className="airygen-field mt-1 w-full"
										value={ settings.connectionTimeoutSeconds }
										onChange={ handleNumberChange( 'connectionTimeoutSeconds', 1, 30 ) }
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'Max time to open a connection before aborting.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="text-sm font-medium text-slate-700" htmlFor="blc-op-timeout">
										{ __( 'Operation timeout (seconds)', 'airygen-seo' ) }
									</label>
									<input
										id="blc-op-timeout"
										type="number"
										min={ 1 }
										max={ 120 }
										className="airygen-field mt-1 w-full"
										value={ settings.operationTimeoutSeconds }
										onChange={ handleNumberChange( 'operationTimeoutSeconds', 1, 120 ) }
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'Max time to complete a request before aborting.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
									<div className="flex items-center justify-between gap-3">
										<p className="text-sm font-medium text-slate-900">
											{ __( 'Treat redirects as warnings', 'airygen-seo' ) }
										</p>
										<Toggle
											label={ __( 'Treat redirects as warnings', 'airygen-seo' ) }
											hideLabelText
											checked={ settings.treatRedirectsAsWarning }
											onChange={ ( value ) =>
												updateSettings( { treatRedirectsAsWarning: value } )
											}
										/>
									</div>
									<p className="text-xs text-slate-500">
										{ __(
											'When on, redirected links are flagged but not counted as errors.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
									<div className="flex items-center justify-between gap-3">
										<p className="text-sm font-medium text-slate-900">
											{ __( 'Daily alerts', 'airygen-seo' ) }
										</p>
										<Toggle
											label={ __( 'Daily alerts', 'airygen-seo' ) }
											checked={ settings.enableDailyAlert }
											onChange={ ( value ) =>
												updateSettings( { enableDailyAlert: value } )
											}
											hideLabelText
										/>
									</div>
									<p className="text-xs text-slate-500">
										{ __( 'Receive daily alerts for newly detected records.', 'airygen-seo' ) }
									</p>
								</div>
							</div>
						</section>
						<section className="rounded-xl border border-slate-200 bg-white p-4">
							<h3 className="text-lg font-semibold text-gray-800">
								{ __( 'Scope', 'airygen-seo' ) }
							</h3>
							<p className="mt-1 text-sm text-slate-500">
								{ __( 'Choose which link types are included in broken-link scans.', 'airygen-seo' ) }
							</p>
							<div className="mt-4 space-y-3">
								<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
									{ __( 'Link types to scan', 'airygen-seo' ) }
								</p>
								<div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
									<div className="rounded-lg border border-slate-200 p-3">
										<label htmlFor="blc-link-external" className="inline-flex items-center gap-2">
											<input
												id="blc-link-external"
												type="checkbox"
												className="h-8 w-8 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
												checked={ settings.linkTypes.external }
												onChange={ handleLinkTypeToggle( 'external' ) }
											/>
											<span>{ __( 'External', 'airygen-seo' ) }</span>
										</label>
									</div>
									<div className="rounded-lg border border-slate-200 p-3">
										<label htmlFor="blc-link-internal" className="inline-flex items-center gap-2">
											<input
												id="blc-link-internal"
												type="checkbox"
												className="h-8 w-8 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
												checked={ settings.linkTypes.internal }
												onChange={ handleLinkTypeToggle( 'internal' ) }
											/>
											<span>{ __( 'Internal', 'airygen-seo' ) }</span>
										</label>
									</div>
								</div>
							</div>
						</section>
					</>
				) : null }

				{ 'logs' === activeTab ? (
					<>
						<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
							<div className="flex items-center justify-between gap-3">
								<div>
									<div className="airygen_h2_title">
										{ __( 'Queue', 'airygen-seo' ) }
									</div>
									<p className="text-xs text-slate-500">
										{ __(
											'Shows current background job status for Broken Link Checker scans.',
											'airygen-seo',
										) }
									</p>
								</div>
								<span className="text-xs text-slate-500">
									{ __( 'Last run', 'airygen-seo' ) }: { lastRunLabel }{ ' ' }
									<a
										href={ queueActionLink }
										target="_blank"
										rel="noopener noreferrer"
										className="text-sky-600 hover:text-sky-700 hover:underline"
									>
										[{ __( 'View', 'airygen-seo' ) }]
									</a>
								</span>
							</div>
							<dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
								{ queueMetrics.map( ( metric ) => (
									<div
										key={ metric.label }
										className="airygen-setting-card__status--normal rounded-lg border border-slate-200 bg-white p-3"
									>
										<dt className="text-xs uppercase tracking-wide text-slate-500">
											{ metric.label }
										</dt>
										<dd className="pb-4 text-center text-2xl font-semibold leading-4 text-slate-900">
											{ metric.value ?? '—' }
										</dd>
									</div>
								) ) }
							</dl>
						</section>
						<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
							<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
								<div>
									<div className="airygen_h2_title">
										{ __( 'Link log', 'airygen-seo' ) }
									</div>
									<p className="mt-1 text-sm text-slate-500">
										{ __(
											'Review recent link checks and jump to affected posts.',
											'airygen-seo',
										) }
									</p>
								</div>
								<Button
									variant="secondary"
									onClick={ () => fetchLogs( pagination.page ) }
									loading={ isLoading }
									disabled={ isLoading }
									className="text-xs"
								>
									{ __( 'Retry', 'airygen-seo' ) }
								</Button>
							</div>

							<div className="grid gap-4 md:grid-cols-2">
								<div>
									<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
										{ __( 'Filters', 'airygen-seo' ) }
									</p>
									<p className="mt-1 text-xs text-slate-500">
										{ __(
											'Toggle which statuses appear in the log.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="flex flex-wrap justify-end gap-4 text-sm text-slate-700">
									<label htmlFor="blc-filter-ok" className="inline-flex items-center gap-2">
										<input
											id="blc-filter-ok"
											type="checkbox"
											className="h-8 w-8 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
											checked={ statusFilters.ok }
											onChange={ () => handleStatusFilterToggle( 'ok' ) }
										/>
										<span>{ __( 'OK', 'airygen-seo' ) }</span>
									</label>
									<label htmlFor="blc-filter-redirect" className="inline-flex items-center gap-2">
										<input
											id="blc-filter-redirect"
											type="checkbox"
											className="h-8 w-8 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
											checked={ statusFilters.redirect }
											onChange={ () => handleStatusFilterToggle( 'redirect' ) }
										/>
										<span>{ __( 'Redirect', 'airygen-seo' ) }</span>
									</label>
									<label htmlFor="blc-filter-error" className="inline-flex items-center gap-2">
										<input
											id="blc-filter-error"
											type="checkbox"
											className="h-8 w-8 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
											checked={ statusFilters.error }
											onChange={ () => handleStatusFilterToggle( 'error' ) }
										/>
										<span>{ __( 'Error', 'airygen-seo' ) }</span>
									</label>
								</div>
							</div>

							<div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
								<table className="min-w-full table-fixed divide-y divide-slate-200 text-sm">
									<thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
										<tr>
											<th className="w-1/3 px-3 py-2 text-left">
												{ __( 'Post', 'airygen-seo' ) }
											</th>
											<th className="w-1/3 px-3 py-2 text-left">
												{ __( 'URL', 'airygen-seo' ) }
											</th>
											<th className="px-3 py-2 text-left">
												{ __( 'Status', 'airygen-seo' ) }
											</th>
											<th className="px-3 py-2 text-left">
												{ __( 'Checked at', 'airygen-seo' ) }
											</th>
											<th className="px-3 py-2 text-right">
												{ __( 'Actions', 'airygen-seo' ) }
											</th>
										</tr>
									</thead>
									<tbody className="divide-y divide-slate-200 text-slate-700">
										{ renderTableRows() }
									</tbody>
								</table>
							</div>

							<div className="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
								<span>{ logPageLabel }</span>
								<div className="flex gap-2">
									<Button
										variant="secondary"
										onClick={ () => fetchLogs( pagination.page - 1 ) }
										disabled={ isLoading || pagination.page <= 1 }
										className="text-xs"
									>
										{ __( 'Previous', 'airygen-seo' ) }
									</Button>
									<Button
										variant="secondary"
										onClick={ () => fetchLogs( pagination.page + 1 ) }
										disabled={
											isLoading || pagination.page >= pagination.totalPages
										}
										className="text-xs"
									>
										{ __( 'Next', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</section>
					</>
				) : null }
			</div>
		</div>
	);
};

export default BrokenLinkCheckerTab;
const ErrorTooltip = ( { message }: { message: string } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ position, setPosition ] = useState( { top: 0, left: 0 } );
	const triggerRef = useRef<HTMLButtonElement | null>( null );

	if ( ! message ) {
		return null;
	}

	const show = () => {
		if ( triggerRef.current ) {
			const rect = triggerRef.current.getBoundingClientRect();
			setPosition( {
				top: rect.bottom + 6,
				left: rect.left,
			} );
		}
		setIsOpen( true );
	};
	const hide = () => setIsOpen( false );

	return (
		<span
			className="relative inline-flex text-xs"
			onMouseEnter={ show }
			onMouseLeave={ hide }
		>
			<button
				type="button"
				ref={ triggerRef }
				className="ml-1 text-rose-600 outline-none focus-visible:ring-2 focus-visible:ring-rose-300 focus-visible:ring-offset-1"
				onFocus={ show }
				onBlur={ hide }
				aria-label={ message }
			>
				[?]
			</button>
			{ isOpen ? (
				<span
					className="pointer-events-none fixed z-[9999] w-56 rounded-lg border border-rose-200 bg-white p-3 text-left text-[11px] text-rose-600 shadow-lg"
					style={ { top: `${ position.top }px`, left: `${ position.left }px` } }
				>
					{ message }
				</span>
			) : null }
		</span>
	);
};

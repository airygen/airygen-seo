import apiFetch from '@wordpress/api-fetch';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

import Button from '../../../components/Button';
import HeadingIcon from '../../../components/HeadingIcon';
import { LinkCounterIcon } from '../../../components/Icons';
import ActionSchedulerPill from '../../../components/ActionSchedulerPill';
import type { LinkCounterStatusMeta } from '../../../types/settings';
import formatDateTime from '../../../utils/formatDateTime';

type LinkCounterTabProps = {
	restBase: string;
	initialStatus?: LinkCounterStatusMeta | null;
};

const numberFormatter = new Intl.NumberFormat();

const formatNumber = ( value: number | null | undefined ) => {
	if ( typeof value !== 'number' || Number.isNaN( value ) ) {
		return '—';
	}

	return numberFormatter.format( value );
};

const formatDate = ( value: string | null | undefined, fallback: string ) =>
	formatDateTime( value, fallback );

const isStatusSettled = ( data: LinkCounterStatusMeta | null ): boolean => {
	if ( ! data ) {
		return false;
	}

	const queuePending = data.queue?.pending ?? 0;
	const queueRunning = data.queue?.inProgress ?? 0;

	return (
		data.pendingPosts === 0 &&
		data.processingPosts === 0 &&
		queuePending === 0 &&
		queueRunning === 0
	);
};

const LinkCounterTab = ( { restBase, initialStatus = null }: LinkCounterTabProps ) => {
	const [ status, setStatus ] =
		useState<LinkCounterStatusMeta | null>( initialStatus );
	const [ isLoading, setIsLoading ] = useState( ! initialStatus );
	const [ isRechecking, setIsRechecking ] = useState( false );
	const [ isPolling, setIsPolling ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState<string | null>( null );
	const normalizedBase = useMemo(
		() => restBase.replace( /\/$/, '' ),
		[ restBase ],
	);
	const statusPath = `${ normalizedBase }/link-counter/status`;
	const recheckPath = `${ normalizedBase }/link-counter/recheck`;
	const pollingRef = useRef( isPolling );

	useEffect( () => {
		pollingRef.current = isPolling;
	}, [ isPolling ] );

	const fetchStatus = useCallback(
		( showLoader = false ) => {
			if ( showLoader ) {
				setIsLoading( true );
			}

			return apiFetch<{ status: LinkCounterStatusMeta }>( {
				path: statusPath,
			} )
				.then( ( response ) => {
					const nextStatus = response?.status ?? null;
					setStatus( nextStatus );
					setErrorMessage( null );

					if ( pollingRef.current && isStatusSettled( nextStatus ) ) {
						pollingRef.current = false;
						setIsPolling( false );
						setIsRechecking( false );
					}
				} )
				.catch( ( error: unknown ) => {
					const message =
						error instanceof Error
							? error.message
							: __( 'Unable to fetch status. Try again.', 'airygen-seo' );
					setErrorMessage( message );

					if ( pollingRef.current ) {
						pollingRef.current = false;
						setIsPolling( false );
						setIsRechecking( false );
					}
				} )
				.finally( () => {
					if ( showLoader ) {
						setIsLoading( false );
					}
				} );
		},
		[ statusPath ],
	);

	useEffect( () => {
		fetchStatus( ! initialStatus );
	}, [ fetchStatus, initialStatus ] );

	useEffect( () => {
		if ( ! isPolling ) {
			return undefined;
		}

		const timer = window.setInterval( () => {
			fetchStatus();
		}, 3000 );

		return () => window.clearInterval( timer );
	}, [ isPolling, fetchStatus ] );

	const handleRecheck = () => {
		setIsRechecking( true );
		setErrorMessage( null );

		apiFetch( {
			path: recheckPath,
			method: 'POST',
		} )
			.then( () => {
				setIsPolling( true );
				pollingRef.current = true;
				fetchStatus();
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to fetch status. Try again.', 'airygen-seo' );
				setErrorMessage( message );
				setIsRechecking( false );
				setIsPolling( false );
				pollingRef.current = false;
			} );
	};

	const hasPendingJobs =
		Number( status?.queue?.pending ?? 0 ) > 0 ||
		Number( status?.queue?.inProgress ?? 0 ) > 0;
	const recheckBusy = isRechecking || isPolling || hasPendingJobs;
	const recheckLabel = recheckBusy
		? __( 'Rechecking…', 'airygen-seo' )
		: __( 'Recheck', 'airygen-seo' );

	const renderHeading = (
		description: string,
		rightSlot: ReactNode = null,
	) => (
		<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<LinkCounterIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Link Counter', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">{ description }</div>
				</div>
			</div>
			{ rightSlot }
		</div>
	);

	if ( isLoading && ! status ) {
		return (
			<div className="space-y-4">
				{ renderHeading(
					__(
						'See how your content connects and keep link metrics up to date across your site.',
						'airygen-seo',
					),
					<span className="text-sm text-slate-500">{ __( 'Loading…', 'airygen-seo' ) }</span>,
				) }
			</div>
		);
	}

	if ( ! status ) {
		return (
			<div className="space-y-4">
				{ renderHeading(
					__(
						'Status unavailable. Try again to fetch the latest link counter state.',
						'airygen-seo',
					),
					<Button variant="outline" onClick={ () => fetchStatus( true ) }>
						{ __( 'Retry', 'airygen-seo' ) }
					</Button>,
				) }
				<Button
					variant="secondary"
					onClick={ handleRecheck }
					loading={ recheckBusy }
					disabled={ recheckBusy }
				>
					{ recheckLabel }
				</Button>
				{ errorMessage ? (
					<p className="text-sm text-rose-600">{ errorMessage }</p>
				) : null }
			</div>
		);
	}

	const queueMetrics = [
		{
			label: __( 'Pending jobs', 'airygen-seo' ),
			value: status.queue.pending,
		},
		{
			label: __( 'Failed jobs', 'airygen-seo' ),
			value: status.queue.failed,
		},
		{
			label: __( 'Completed jobs', 'airygen-seo' ),
			value: status.queue.completed,
		},
	];

	const failedBacklogCount = Number( status.failedPosts ?? 0 );
	const backlogMetrics = [
		{
			key: 'pending',
			label: __( 'Pending posts', 'airygen-seo' ),
			value: status.pendingPosts,
		},
		{
			key: 'processing',
			label: __( 'Processing posts', 'airygen-seo' ),
			value: status.processingPosts + failedBacklogCount,
			hasWarning: failedBacklogCount > 0,
		},
		{
			key: 'processed',
			label: __( 'Processed posts', 'airygen-seo' ),
			value: status.processedPosts,
		},
	];

	const lastRunLabel = formatDate(
		status.lastRunGmt,
		__( 'Not scheduled', 'airygen-seo' ),
	);
	const actionSchedulerLink = `/wp-admin/tools.php?page=action-scheduler&s=${ encodeURIComponent(
		'airygen_link_counter_process_backlog_async',
	) }`;

	const schedulerPill = (
		<ActionSchedulerPill available={ status.actionSchedulerAvailable } />
	);

	return (
		<div className="space-y-5">
			{ renderHeading(
				__(
					'See how your content connects and keep link metrics up to date across your site.',
					'airygen-seo',
				),
				schedulerPill,
			) }

			<section className="rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex items-center justify-between gap-3">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Queue', 'airygen-seo' ) }
						</div>
						<p className="text-xs text-slate-500">
							{ __(
								'Shows how many posts are waiting or being processed for link counts.',
								'airygen-seo',
							) }
						</p>
					</div>
					<span className="text-xs text-slate-500">
						{ __( 'Last run', 'airygen-seo' ) }: { lastRunLabel }{ ' ' }
						[<a
							href={ actionSchedulerLink }
							target="_blank"
							rel="noopener noreferrer"
							className="text-sky-600 hover:text-sky-700 hover:underline"
						>
							{ __( 'View', 'airygen-seo' ) }
						</a>]
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
								{ formatNumber( metric.value ) }
							</dd>
						</div>
					) ) }
				</dl>
			</section>

			<section className="rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Backlog', 'airygen-seo' ) }
						</div>
						<p className="text-xs text-slate-500">
							{ __(
								'Saving a post recalculates link counts in the background.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:gap-3">
						{ isPolling ? (
							<span>{ __( 'Monitoring…', 'airygen-seo' ) }</span>
						) : null }
						<Button
							variant="secondary"
							onClick={ handleRecheck }
							loading={ recheckBusy }
							disabled={ isLoading || recheckBusy }
							className="text-xs"
						>
							{ recheckLabel }
						</Button>
					</div>
				</div>
				{ errorMessage ? (
					<div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
						{ errorMessage }
					</div>
				) : null }
				<dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
					{ backlogMetrics.map( ( metric ) => (
						<div
							key={ metric.key }
							className="airygen-setting-card__status--normal relative rounded-lg border border-slate-200 bg-white p-3"
						>
							{ metric.hasWarning ? (
								<span
									className="dashicons dashicons-warning absolute right-2 top-2 m-0 block h-[16px] w-[16px] text-[16px] leading-[16px] text-slate-400"
									aria-hidden="true"
								/>
							) : null }
							<dt className="text-xs uppercase tracking-wide text-slate-500">
								{ metric.label }
							</dt>
							<dd className="pb-4 text-center text-xl font-bold leading-4 text-slate-900">
								{ formatNumber( metric.value ) }
							</dd>
						</div>
					) ) }
				</dl>
			</section>
		</div>
	);
};

export default LinkCounterTab;

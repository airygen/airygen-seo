import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import type { DragEvent } from 'react';

import Button from '../../../components/Button';
import HeadingIcon from '../../../components/HeadingIcon';
import Input from '../../../components/Input';
import Select from '../../../components/Select';
import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import Spinner from '../../../components/Spinner';
import ModuleCard from '../../../components/ModuleCard';
import {
	NotifyModuleIcon,
	NotifyDigestIcon,
	NotifyEmailIcon,
	NotifyTelegramIcon,
	NotifyDiscordIcon,
	NotifyTeamsIcon,
} from '../../../components/Icons';
import type { NoticeState } from '../../../types/api';
import type { NotifySettings, NotifyStatusMeta } from '../../../types/settings';
import formatDateTime from '../../../utils/formatDateTime';
import { getNoLogsYetLabel } from '../../../../shared/i18nPhrases';

type NotifyLogEntry = {
	timestamp: string;
	results: Array<{
		channel: string;
		ok: boolean;
		message: string;
	}>;
};
type NotifyLogsResponse = {
	items?: NotifyLogEntry[];
	pagination?: {
		page?: number;
		perPage?: number;
		total?: number;
		totalPages?: number;
	};
};

export type NotifyView = 'home' | 'digest' | 'email' | 'telegram' | 'discord' | 'teams';

type NotifyTabProps = {
	settings: NotifySettings;
	onChange: ( next: NotifySettings ) => void;
	restBase: string;
	onNotice: ( notice: NoticeState ) => void;
	timezoneOptions?: Array<{ value: string; label: string }>;
	status?: NotifyStatusMeta;
	view?: NotifyView;
	onViewChange?: ( next: NotifyView ) => void;
};

type SmtpProviderKey =
	| 'custom'
	| 'gmail'
	| 'office365'
	| 'mailgun'
	| 'sendgrid'
	| 'amazonses'
	| 'zoho'
	| 'brevo'
	| 'postmark'
	| 'mailpit';

type DigestCustomBlockId = 'not_found_logs' | 'broken_link_logs';

const DIGEST_CUSTOM_BLOCKS: Array<{
	id: DigestCustomBlockId;
	title: string;
	description: string;
}> = [
	{
		id: 'not_found_logs',
		title: sprintf(
			/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
			__( '%s records', 'airygen-seo' ),
			'404',
		),
		description: __( "Yesterday's newly added not found page events.", 'airygen-seo' ),
	},
	{
		id: 'broken_link_logs',
		title: __( 'Broken link records', 'airygen-seo' ),
		description: __( "Yesterday's newly added broken link events.", 'airygen-seo' ),
	},
];

const SMTP_PROVIDER_PRESETS: Record<
	Exclude<SmtpProviderKey, 'custom'>,
	{
		host: string;
		port: number;
		auth: boolean;
		secure: '' | 'tls' | 'ssl';
	}
> = {
	gmail: { host: 'smtp.gmail.com', port: 587, auth: true, secure: 'tls' },
	office365: { host: 'smtp.office365.com', port: 587, auth: true, secure: 'tls' },
	mailgun: { host: 'smtp.mailgun.org', port: 587, auth: true, secure: 'tls' },
	sendgrid: { host: 'smtp.sendgrid.net', port: 587, auth: true, secure: 'tls' },
	amazonses: {
		host: 'email-smtp.us-east-1.amazonaws.com',
		port: 587,
		auth: true,
		secure: 'tls',
	},
	zoho: { host: 'smtp.zoho.com', port: 587, auth: true, secure: 'tls' },
	brevo: { host: 'smtp-relay.brevo.com', port: 587, auth: true, secure: 'tls' },
	postmark: { host: 'smtp.postmarkapp.com', port: 587, auth: true, secure: 'tls' },
	mailpit: { host: '127.0.0.1', port: 1025, auth: false, secure: '' },
};

const NotifyTab = ( {
	settings,
	onChange,
	restBase,
	onNotice,
	timezoneOptions = [],
	status,
	view,
	onViewChange,
}: NotifyTabProps ) => {
	const [ internalView, setInternalView ] = useState<NotifyView>( 'home' );
	const [ digestTab, setDigestTab ] = useState<'settings' | 'logs'>( 'settings' );
	const currentView = view ?? internalView;
	const setView = onViewChange ?? setInternalView;
	const [ logs, setLogs ] = useState<NotifyLogEntry[]>( [] );
	const [ draggingCustomBlockId, setDraggingCustomBlockId ] = useState<string | null>( null );
	const [ isLogsLoading, setIsLogsLoading ] = useState( false );
	const [ logsPage, setLogsPage ] = useState( 1 );
	const [ logsPagination, setLogsPagination ] = useState( {
		page: 1,
		perPage: 20,
		total: 0,
		totalPages: 1,
	} );
	const [ busyAction, setBusyAction ] = useState<string | null>( null );
	const [ isProviderMenuOpen, setIsProviderMenuOpen ] = useState( false );
	const providerMenuRef = useRef<HTMLDivElement | null>( null );
	const normalizedTimezoneOptions = useMemo(
		() =>
			timezoneOptions.length > 0
				? timezoneOptions
				: [ { value: 'UTC', label: 'UTC (UTC +0)' } ],
		[ timezoneOptions ],
	);
	useEffect( () => {
		if ( ! isProviderMenuOpen ) {
			return () => {};
		}

		const handleClickOutside = ( event: MouseEvent ) => {
			if ( providerMenuRef.current?.contains( event.target as Node ) ) {
				return;
			}
			setIsProviderMenuOpen( false );
		};

		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [ isProviderMenuOpen ] );

	const updateSettings = ( patch: Partial<NotifySettings> ) => {
		onChange( { ...settings, ...patch } );
	};

	const latestLog = useMemo( () => {
		if ( logs.length === 0 ) {
			return null;
		}
		return logs.reduce<NotifyLogEntry>( ( newest, entry ) => {
			const newestTime = Date.parse( newest.timestamp );
			const entryTime = Date.parse( entry.timestamp );
			return entryTime > newestTime ? entry : newest;
		}, logs[ 0 ] );
	}, [ logs ] );

	const summary = useMemo( () => {
		const now = Date.now();
		const buildWindowStats = ( rangeMs: number ) => {
			const inRange = logs.filter( ( entry ) => {
				const ts = Date.parse( entry.timestamp );
				return Number.isFinite( ts ) && now - ts <= rangeMs;
			} );

			const results = inRange.flatMap( ( entry ) =>
				Array.isArray( entry.results ) ? entry.results : [],
			);
			return {
				runs: inRange.length,
				success: results.filter( ( item ) => item.ok ).length,
				failed: results.filter( ( item ) => ! item.ok ).length,
			};
		};

		return {
			latestAt: latestLog?.timestamp ?? '—',
			last24h: buildWindowStats( 24 * 60 * 60 * 1000 ),
			last7d: buildWindowStats( 7 * 24 * 60 * 60 * 1000 ),
		};
	}, [ latestLog, logs ] );

	const digestCustomMap = useMemo(
		() => new Map( DIGEST_CUSTOM_BLOCKS.map( ( block ) => [ block.id, block ] ) ),
		[],
	);

	const visibleDigestBlocks = useMemo(
		() =>
			settings.custom.visibleBlocks.filter(
				( id ): id is DigestCustomBlockId => digestCustomMap.has( id as DigestCustomBlockId ),
			),
		[ digestCustomMap, settings.custom.visibleBlocks ],
	);

	const hiddenDigestBlocks = useMemo(
		() =>
			settings.custom.hiddenBlocks.filter(
				( id ): id is DigestCustomBlockId => digestCustomMap.has( id as DigestCustomBlockId ),
			),
		[ digestCustomMap, settings.custom.hiddenBlocks ],
	);

	const updateDigestCustomBlocks = (
		nextVisible: DigestCustomBlockId[],
		nextHidden: DigestCustomBlockId[],
	) => {
		onChange( {
			...settings,
			custom: {
				visibleBlocks: nextVisible,
				hiddenBlocks: nextHidden,
			},
		} );
	};

	const resolveDraggingCustomBlockId = ( event: DragEvent<HTMLElement> ): string => {
		const payload = event.dataTransfer.getData( 'text/plain' );
		return draggingCustomBlockId ?? payload;
	};

	const moveDigestBlock = (
		blockId: string,
		targetZone: 'visible' | 'hidden',
		targetBlockId?: string,
	) => {
		if ( ! digestCustomMap.has( blockId as DigestCustomBlockId ) ) {
			return;
		}

		const id = blockId as DigestCustomBlockId;
		const sourceVisible = visibleDigestBlocks.filter( ( item ) => item !== id );
		const sourceHidden = hiddenDigestBlocks.filter( ( item ) => item !== id );
		const targetList = targetZone === 'visible' ? sourceVisible : sourceHidden;
		const otherList = targetZone === 'visible' ? sourceHidden : sourceVisible;

		if ( ! targetBlockId ) {
			targetList.push( id );
		} else {
			const insertAt = targetList.indexOf( targetBlockId as DigestCustomBlockId );
			if ( insertAt < 0 ) {
				targetList.push( id );
			} else {
				targetList.splice( insertAt, 0, id );
			}
		}

		if ( targetZone === 'visible' ) {
			updateDigestCustomBlocks( targetList, otherList );
			return;
		}
		updateDigestCustomBlocks( otherList, targetList );
	};

	const handleDigestZoneDrop = (
		event: DragEvent<HTMLDivElement>,
		targetZone: 'visible' | 'hidden',
	) => {
		event.preventDefault();
		const blockId = resolveDraggingCustomBlockId( event );
		if ( blockId ) {
			moveDigestBlock( blockId, targetZone );
		}
		setDraggingCustomBlockId( null );
	};

	const handleDigestCardDrop = (
		event: DragEvent<HTMLDivElement>,
		targetZone: 'visible' | 'hidden',
		targetBlockId: string,
	) => {
		event.preventDefault();
		event.stopPropagation();
		const blockId = resolveDraggingCustomBlockId( event );
		if ( blockId && blockId !== targetBlockId ) {
			moveDigestBlock( blockId, targetZone, targetBlockId );
		}
		setDraggingCustomBlockId( null );
	};

	const renderDigestBlockCard = (
		blockId: DigestCustomBlockId,
		zone: 'visible' | 'hidden',
	) => {
		const block = digestCustomMap.get( blockId );
		if ( ! block ) {
			return null;
		}

		return (
			<div
				key={ `${ zone }-${ block.id }` }
				className="cursor-grab rounded-md border border-slate-300 bg-white p-3 shadow-sm active:cursor-grabbing"
				draggable
				onDragStart={ ( event ) => {
					setDraggingCustomBlockId( block.id );
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData( 'text/plain', block.id );
				} }
				onDragEnd={ () => setDraggingCustomBlockId( null ) }
				onDragOver={ ( event ) => {
					event.preventDefault();
					event.dataTransfer.dropEffect = 'move';
				} }
				onDrop={ ( event ) => handleDigestCardDrop( event, zone, block.id ) }
			>
				<p className="text-sm font-medium text-slate-900">{ block.title }</p>
				<p className="mt-1 text-xs text-slate-500">{ block.description }</p>
			</div>
		);
	};

	const loadLogs = useCallback( async ( page = 1 ) => {
		setIsLogsLoading( true );
		try {
			const response = await apiFetch<NotifyLogsResponse>( {
				path: `${ restBase }/notify/logs?page=${ page }&per_page=20`,
				method: 'GET',
			} );
			setLogs( Array.isArray( response.items ) ? response.items : [] );
			const pagination = response.pagination ?? {};
			setLogsPagination( {
				page: Number.isFinite( Number( pagination.page ) )
					? Math.max( 1, Number( pagination.page ) )
					: 1,
				perPage: Number.isFinite( Number( pagination.perPage ) )
					? Math.max( 1, Number( pagination.perPage ) )
					: 20,
				total: Number.isFinite( Number( pagination.total ) )
					? Math.max( 0, Number( pagination.total ) )
					: 0,
				totalPages: Number.isFinite( Number( pagination.totalPages ) )
					? Math.max( 1, Number( pagination.totalPages ) )
					: 1,
			} );
		} catch {
			onNotice( {
				status: 'error',
				message: __( 'Failed to load notify logs.', 'airygen-seo' ),
			} );
		} finally {
			setIsLogsLoading( false );
		}
	}, [ onNotice, restBase ] );

	useEffect( () => {
		void loadLogs( logsPage );
	}, [ loadLogs, logsPage ] );

	const runSendNow = async () => {
		setBusyAction( 'send-now' );
		try {
			const response = await apiFetch<{
				ok?: boolean;
				results?: Array<{ channel: string; ok: boolean; message: string }>;
			}>( {
				path: `${ restBase }/notify/send-now`,
				method: 'POST',
			} );
			const hasResults =
				Array.isArray( response.results ) && response.results.length > 0;
			onNotice(
				hasResults
					? {
						status: 'success',
						message: __(
							'Digest sent. Check channel results in logs below.',
							'airygen-seo',
						),
					}
					: {
						status: 'error',
						message: __(
							'No enabled channels. Enable at least one channel to send a digest.',
							'airygen-seo',
						),
					},
			);
			await loadLogs( logsPage );
		} catch {
			onNotice( {
				status: 'error',
				message: __( 'Failed to send digest.', 'airygen-seo' ),
			} );
		} finally {
			setBusyAction( null );
		}
	};

	const runTestChannel = async ( channel: 'email' | 'telegram' | 'discord' | 'teams' ) => {
		setBusyAction( `test-${ channel }` );
		try {
			const response = await apiFetch<{ result?: { ok?: boolean; message?: string } }>( {
				path: `${ restBase }/notify/test/${ channel }`,
				method: 'POST',
			} );
			onNotice( {
				status: response?.result?.ok ? 'success' : 'error',
				message: response?.result?.message
					? `${ channel }: ${ response.result.message }`
					: __( 'Channel test completed.', 'airygen-seo' ),
			} );
			await loadLogs( logsPage );
		} catch {
			onNotice( {
				status: 'error',
				message: __( 'Failed to test channel.', 'airygen-seo' ),
			} );
		} finally {
			setBusyAction( null );
		}
	};

	const renderLogRows = () => {
		if ( isLogsLoading ) {
			return (
				<tr>
					<td colSpan={ 3 } className="px-3 py-6 text-center text-slate-500">
						<div className="inline-flex items-center gap-2"><Spinner size="sm" />{ __( 'Loading logs…', 'airygen-seo' ) }</div>
					</td>
				</tr>
			);
		}

		if ( logs.length === 0 ) {
			return (
				<tr>
					<td colSpan={ 3 } className="px-3 py-6 text-center text-slate-500">
						{ getNoLogsYetLabel() }
					</td>
				</tr>
			);
		}

		return logs.flatMap( ( entry, entryIndex ) => {
			const rowResults =
				Array.isArray( entry.results ) && entry.results.length > 0
					? entry.results
					: [ { channel: 'system', ok: false, message: __( 'No channel results.', 'airygen-seo' ) } ];

			return rowResults.map( ( result, resultIndex ) => (
				<tr key={ `${ entry.timestamp }-${ entryIndex }-${ result.channel }-${ resultIndex }` }>
					{ resultIndex === 0 ? (
						<td
							rowSpan={ rowResults.length }
							className="px-3 py-2 align-middle text-center text-slate-700"
						>
							{ entry.timestamp }
						</td>
					) : null }
					<td className="px-3 py-2 text-slate-700">
						<span
							className={ [
								'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
								result.ok
									? 'bg-emerald-100 text-emerald-700'
									: 'bg-rose-100 text-rose-700',
							].join( ' ' ) }
						>
							{ result.ok
								? __( 'Success', 'airygen-seo' )
								: __( 'Failed', 'airygen-seo' ) }
						</span>
					</td>
					<td className="px-3 py-2 text-slate-700">{ result.message }</td>
				</tr>
			) );
		} );
	};
	const logPageLabel = sprintf(
		/* translators: 1: current page, 2: total pages */
		__( 'Page %1$s / %2$s', 'airygen-seo' ),
		String( logsPagination.page ),
		String( logsPagination.totalPages ),
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
	const queueLastRunLabel = formatDateTime(
		status?.lastRunGmt ?? null,
		__( 'Not scheduled', 'airygen-seo' ),
	);
	const queueActionLink = '/wp-admin/tools.php?page=action-scheduler&s=airygen_notify_daily_digest&orderby=schedule&order=desc';

	const notifyCards = [
		{
			key: 'digest' as const,
			title: __( 'Daily Digest', 'airygen-seo' ),
			description: __( 'Schedule and dispatch one daily digest for 404s, broken links, and related site alerts.', 'airygen-seo' ),
			enabled: settings.enabled,
			onToggle: ( next: boolean ) => updateSettings( { enabled: next } ),
			Icon: NotifyDigestIcon,
			tier: 'starter' as const,
		},
		{
			key: 'email' as const,
			title: __( 'Email', 'airygen-seo' ),
			description: __( 'Deliver digest notifications through wp_mail recipients.', 'airygen-seo' ),
			enabled: settings.channels.email.enabled,
			onToggle: ( next: boolean ) =>
				updateSettings( {
					channels: {
						...settings.channels,
						email: {
							...settings.channels.email,
							enabled: next,
						},
					},
				} ),
			Icon: NotifyEmailIcon,
			tier: 'starter' as const,
		},
		{
			key: 'telegram' as const,
			title: __( 'Telegram', 'airygen-seo' ),
			description: __( 'Send digest notifications to Telegram Bot chats.', 'airygen-seo' ),
			enabled: settings.channels.telegram.enabled,
			onToggle: ( next: boolean ) =>
				updateSettings( {
					channels: {
						...settings.channels,
						telegram: {
							...settings.channels.telegram,
							enabled: next,
						},
					},
				} ),
			Icon: NotifyTelegramIcon,
			tier: 'expert' as const,
		},
		{
			key: 'discord' as const,
			title: __( 'Discord', 'airygen-seo' ),
			description: __( 'Push digest messages to a Discord webhook endpoint.', 'airygen-seo' ),
			enabled: settings.channels.discord.enabled,
			onToggle: ( next: boolean ) =>
				updateSettings( {
					channels: {
						...settings.channels,
						discord: {
							...settings.channels.discord,
							enabled: next,
						},
					},
				} ),
			Icon: NotifyDiscordIcon,
			tier: 'expert' as const,
		},
		{
			key: 'teams' as const,
			title: __( 'Microsoft Teams', 'airygen-seo' ),
			description: __( 'Post digest messages to a Teams incoming webhook URL.', 'airygen-seo' ),
			enabled: settings.channels.teams.enabled,
			onToggle: ( next: boolean ) =>
				updateSettings( {
					channels: {
						...settings.channels,
						teams: {
							...settings.channels.teams,
							enabled: next,
						},
					},
				} ),
			Icon: NotifyTeamsIcon,
			tier: 'expert' as const,
		},
	];
	const toolOnlyTraits = {
		tool: true,
	} as const;
	const digestCard = notifyCards.find( ( card ) => card.key === 'digest' ) ?? notifyCards[ 0 ];
	const channelCards = notifyCards.filter( ( card ) => card.key !== 'digest' );

	return (
		<div className="space-y-5">
			{ currentView === 'home' ? (
				<>
					<div className="flex items-start gap-3">
						<HeadingIcon>
							<NotifyModuleIcon className="h-8 w-8" aria-hidden="true" />
						</HeadingIcon>
						<div>
							<div className="airygen_h1_title">
								{ __( 'Alerts', 'airygen-seo' ) }
							</div>
							<div className="airygen_h1_description">
								{ __( 'Configure delivery channels for daily 404 digest alerts.', 'airygen-seo' ) }
							</div>
						</div>
					</div>
					<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
						<div className="airygen_h2_title">{ __( 'Reports', 'airygen-seo' ) }</div>
						<p className="mt-2 text-sm text-slate-500">{ __( 'Manage daily digest scheduling and delivery controls.', 'airygen-seo' ) }</p>
						<div className="mt-6 grid gap-4 lg:grid-cols-3">
							<ModuleCard
								key={ digestCard.key }
								title={ digestCard.title }
								description={ digestCard.description }
								Icon={ digestCard.Icon }
								enabled={ digestCard.enabled }
								onToggle={ digestCard.onToggle }
								onOpenSettings={ () => setView( digestCard.key ) }
								showSettingsButton
								tier={ digestCard.tier }
							/>
						</div>
					</div>
					<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
						<div className="airygen_h2_title">{ __( 'Channels', 'airygen-seo' ) }</div>
						<p className="mt-2 text-sm text-slate-500">{ __( 'Enable channels and open each card to configure delivery details.', 'airygen-seo' ) }</p>
						<div className="mt-6 grid gap-4 lg:grid-cols-3">
							{ channelCards.map( ( card ) => (
								<ModuleCard
									key={ card.key }
									title={ card.title }
									description={ card.description }
									Icon={ card.Icon }
									enabled={ card.enabled }
									onToggle={ card.onToggle }
									onOpenSettings={ () => setView( card.key ) }
									showSettingsButton
									tier={ card.tier }
									traits={ toolOnlyTraits }
								/>
							) ) }
						</div>
					</div>
				</>
			) : null }

			{ currentView === 'digest' ? (
				<div className="space-y-5">
					<div className="flex items-start gap-3">
						<HeadingIcon>
							<NotifyDigestIcon className="h-8 w-8" aria-hidden="true" />
						</HeadingIcon>
						<div>
							<div className="airygen_h1_title">{ __( 'Daily Digest', 'airygen-seo' ) }</div>
							<div className="airygen_h1_description">{ __( 'Configure digest schedule, trigger delivery, and review send logs.', 'airygen-seo' ) }</div>
						</div>
					</div>
					<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
						<button
							type="button"
							data-airygen-e2e="tab-settings"
							className={
								digestTab === 'settings'
									? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
									: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
							}
							onClick={ () => setDigestTab( 'settings' ) }
						>
							{ __( 'Settings', 'airygen-seo' ) }
						</button>
						<button
							type="button"
							data-airygen-e2e="tab-logs"
							className={
								digestTab === 'logs'
									? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
									: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
							}
							onClick={ () => setDigestTab( 'logs' ) }
						>
							{ __( 'Logs', 'airygen-seo' ) }
						</button>
					</div>

					{ digestTab === 'settings' ? (
						<>
							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">{ __( 'Settings', 'airygen-seo' ) }</div>
									<p className="text-sm text-slate-500">{ __( 'Configure daily digest schedule and send behavior.', 'airygen-seo' ) }</p>
								</div>
								<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
									<div className="flex items-center justify-between gap-3">
										<p className="text-sm font-medium text-slate-900">
											{ __( 'Enable daily digest', 'airygen-seo' ) }
										</p>
										<Toggle label={ __( 'Enable daily digest', 'airygen-seo' ) } checked={ settings.enabled } onChange={ ( checked ) => updateSettings( { enabled: checked } ) } hideLabelText />
									</div>
									<p className="text-xs text-slate-500">
										{ __( 'When enabled, Airygen sends one digest daily at the configured time.', 'airygen-seo' ) }
									</p>
									<div className="mt-3 grid gap-3 md:grid-cols-3">
										<div className="airygen-setting-card__input--column rounded-lg border border-slate-200 p-4">
											<Select
												label={ __( 'Timezone', 'airygen-seo' ) }
												value={ settings.schedule.timezone }
												options={ normalizedTimezoneOptions }
												onChange={ ( value ) =>
													updateSettings( {
														schedule: { ...settings.schedule, timezone: value },
													} )
												}
												help={ __( 'Choose a timezone for daily digest scheduling.', 'airygen-seo' ) }
											/>
										</div>
										<div className="airygen-setting-card__input--column rounded-lg border border-slate-200 p-4">
											<Input label={ __( 'Daily send time', 'airygen-seo' ) } type="time" value={ settings.schedule.time } onChange={ ( value ) => updateSettings( { schedule: { ...settings.schedule, time: value } } ) } help={ __( '24-hour format, such as 09:00.', 'airygen-seo' ) } />
										</div>
										<div className="airygen-setting-card__input--column rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Log retention (days)', 'airygen-seo' ) }
												type="number"
												value={ String( settings.logs.retentionDays ) }
												onChange={ ( value ) =>
													updateSettings( {
														logs: {
															...settings.logs,
															retentionDays: Number.isFinite( Number( value ) )
																? Number( value )
																: settings.logs.retentionDays,
														},
													} )
												}
												min={ 1 }
												max={ 3650 }
												help={ __( 'Keep digest logs for this many days before automatic cleanup.', 'airygen-seo' ) }
											/>
										</div>
									</div>
									<div className="mt-3 grid gap-3 md:grid-cols-3">
										<div className="rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Message subject', 'airygen-seo' ) }
												value={ settings.message.subject }
												onChange={ ( value ) =>
													updateSettings( {
														message: {
															...settings.message,
															subject: value,
														},
													} )
												}
												help={ __( 'Email and message title used for daily digest delivery.', 'airygen-seo' ) }
											/>
										</div>
										<div className="rounded-lg border border-slate-200 p-4">
											<Textarea
												label={ __( 'Intro', 'airygen-seo' ) }
												rows={ 3 }
												value={ settings.message.intro }
												onChange={ ( value ) =>
													updateSettings( {
														message: {
															...settings.message,
															intro: value,
														},
													} )
												}
												help={ __( 'Shown before digest sections.', 'airygen-seo' ) }
											/>
										</div>
										<div className="rounded-lg border border-slate-200 p-4">
											<Textarea
												label={ __( 'Footer', 'airygen-seo' ) }
												rows={ 3 }
												value={ settings.message.footer }
												onChange={ ( value ) =>
													updateSettings( {
														message: {
															...settings.message,
															footer: value,
														},
													} )
												}
												help={ __( 'Shown after digest sections.', 'airygen-seo' ) }
											/>
										</div>
									</div>
									<div className="mt-3 grid gap-3 md:grid-cols-3">
										<div className="rounded-lg border border-slate-200 p-4">
											<p className="text-sm font-medium text-slate-900">
												{ __( 'Send now', 'airygen-seo' ) }
											</p>
											<p className="mt-1 text-xs text-slate-500">
												{ __( 'Daily digest is sent by background processing, but you can also send one immediately now.', 'airygen-seo' ) }
											</p>
											<Button variant="outline" onClick={ () => void runSendNow() } disabled={ busyAction !== null } loading={ busyAction === 'send-now' } className="mt-3 w-full text-xs">
												{ __( 'Send now', 'airygen-seo' ) }
											</Button>
										</div>
									</div>
								</div>
							</section>
							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">{ __( 'Custom', 'airygen-seo' ) }</div>
									<p className="text-sm text-slate-500">
										{ __( 'Arrange cards to control which sections are shown in digest content.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="grid gap-4 lg:grid-cols-3">
									<div
										className="space-y-3 rounded-lg border border-dashed border-slate-300 p-4 lg:col-span-2"
										data-airygen-e2e="digest-blocks-visible"
										onDragOver={ ( event ) => {
											event.preventDefault();
											event.dataTransfer.dropEffect = 'move';
										} }
										onDrop={ ( event ) => handleDigestZoneDrop( event, 'visible' ) }
									>
										<h3 className="text-sm font-semibold text-slate-800">{ __( 'Message content', 'airygen-seo' ) }</h3>
										<p className="text-xs text-slate-500">{ __( 'Blocks here are included in the digest message.', 'airygen-seo' ) }</p>
										<div className="space-y-3">
											{ visibleDigestBlocks.map( ( blockId ) => renderDigestBlockCard( blockId, 'visible' ) ) }
										</div>
									</div>
									<div
										className="space-y-3 rounded-lg border border-dashed border-slate-300 p-4"
										data-airygen-e2e="digest-blocks-hidden"
										onDragOver={ ( event ) => {
											event.preventDefault();
											event.dataTransfer.dropEffect = 'move';
										} }
										onDrop={ ( event ) => handleDigestZoneDrop( event, 'hidden' ) }
									>
										<h3 className="text-sm font-semibold text-slate-800">{ __( 'Hidden blocks', 'airygen-seo' ) }</h3>
										<p className="text-xs text-slate-500">{ __( 'Blocks here are not included in the digest message.', 'airygen-seo' ) }</p>
										<div className="space-y-3">
											{ hiddenDigestBlocks.map( ( blockId ) => renderDigestBlockCard( blockId, 'hidden' ) ) }
										</div>
									</div>
								</div>
							</section>
						</>
					) : null }

					{ digestTab === 'logs' ? (
						<>
							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="flex items-center justify-between gap-3">
									<div className="space-y-1">
										<div className="airygen_h2_title">{ __( 'Queue', 'airygen-seo' ) }</div>
										<p className="text-sm text-slate-500">{ __( 'Shows current background job status for Daily Digest delivery.', 'airygen-seo' ) }</p>
									</div>
									<span className="text-xs text-slate-500">
										{ __( 'Last run', 'airygen-seo' ) }: { queueLastRunLabel }{ ' ' }
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
								<div className="flex items-center justify-between gap-3">
									<div className="space-y-1">
										<div className="airygen_h2_title">{ __( 'Logs', 'airygen-seo' ) }</div>
										<p className="text-sm text-slate-500">{ __( 'Review daily digest delivery history and channel outcomes.', 'airygen-seo' ) }</p>
									</div>
									<Button variant="secondary" onClick={ () => void loadLogs( logsPage ) } className="text-xs" disabled={ isLogsLoading }>
										{ __( 'Refresh', 'airygen-seo' ) }
									</Button>
								</div>
								<div className="mt-1 grid gap-3 md:grid-cols-4">
									<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
										<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Latest run', 'airygen-seo' ) }</p>
										<p className="pt-2 text-sm font-semibold text-slate-900">{ summary.latestAt }</p>
									</div>
									<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
										<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Runs (24h)', 'airygen-seo' ) }</p>
										<p className="pt-2 text-xl font-semibold text-slate-900">{ summary.last24h.runs }</p>
									</div>
									<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
										<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Success / failed (24h)', 'airygen-seo' ) }</p>
										<p className="pt-2 text-sm font-semibold text-slate-900">
											<span className="text-emerald-700">{ summary.last24h.success }</span> / <span className="text-rose-700">{ summary.last24h.failed }</span>
										</p>
									</div>
									<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
										<p className="text-xs uppercase tracking-wide text-slate-500">{ __( 'Runs (7d)', 'airygen-seo' ) }</p>
										<p className="pt-2 text-sm font-semibold text-slate-900">{ summary.last7d.runs } ({ summary.last7d.success } / { summary.last7d.failed })</p>
									</div>
								</div>
								<div className="mt-3 overflow-x-auto rounded-lg border border-slate-200">
									<table className="min-w-full divide-y divide-slate-200 text-sm">
										<thead className="bg-slate-50">
											<tr>
												<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Delivery date', 'airygen-seo' ) }</th>
												<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Status', 'airygen-seo' ) }</th>
												<th className="px-3 py-2 text-left font-medium text-slate-600">{ __( 'Results', 'airygen-seo' ) }</th>
											</tr>
										</thead>
										<tbody className="divide-y divide-slate-200 bg-white">{ renderLogRows() }</tbody>
									</table>
								</div>
								<div className="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
									<span>{ logPageLabel }</span>
									<div className="flex gap-2">
										<Button
											variant="secondary"
											onClick={ () => setLogsPage( ( prev ) => Math.max( 1, prev - 1 ) ) }
											disabled={ isLogsLoading || logsPagination.page <= 1 }
											className="text-xs"
										>
											{ __( 'Previous', 'airygen-seo' ) }
										</Button>
										<Button
											variant="secondary"
											onClick={ () =>
												setLogsPage( ( prev ) =>
													Math.min( logsPagination.totalPages, prev + 1 ),
												)
											}
											disabled={
												isLogsLoading ||
											logsPagination.page >= logsPagination.totalPages
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
			) : null }

			{ currentView === 'email' ? (
				<div className="space-y-5">
					<div className="flex items-start gap-3">
						<HeadingIcon>
							<NotifyEmailIcon className="h-8 w-8" aria-hidden="true" />
						</HeadingIcon>
						<div>
							<div className="airygen_h1_title">{ __( 'Email', 'airygen-seo' ) }</div>
							<div className="airygen_h1_description">{ __( 'Configure recipients and test digest delivery using wp_mail.', 'airygen-seo' ) }</div>
						</div>
					</div>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">{ __( 'Settings', 'airygen-seo' ) }</div>
							<p className="text-sm text-slate-500">{ __( 'Set up SMTP delivery, recipients, and test sending for Email notifications.', 'airygen-seo' ) }</p>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Enable Email', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Email', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.channels.email.enabled }
									onChange={ ( value ) =>
										updateSettings( {
											channels: {
												...settings.channels,
												email: { ...settings.channels.email, enabled: value },
											},
										} )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Send digest messages through built-in SMTP settings.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-4">
								<div className="rounded-lg border border-slate-200 p-3 md:col-span-3">
									<Textarea
										label={ __( 'Recipients', 'airygen-seo' ) }
										value={ settings.channels.email.recipients.join( '\n' ) }
										onChange={ ( value ) =>
											updateSettings( {
												channels: {
													...settings.channels,
													email: {
														...settings.channels.email,
														recipients: value
															.split( /[\n,]/ )
															.map( ( item ) => item.trim() )
															.filter( ( item ) => item !== '' ),
													},
												},
											} )
										}
										help={ __( 'One email per line or separated by commas.', 'airygen-seo' ) }
										rows={ 4 }
									/>
									<p className="mt-2 text-xs text-slate-500">
										{ __( 'Add all destination email addresses for daily reports.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="rounded-lg border border-slate-200 p-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Test delivery', 'airygen-seo' ) }
									</p>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Send a sample digest to confirm email delivery works.', 'airygen-seo' ) }
									</p>
									<Button
										variant="secondary"
										onClick={ () => void runTestChannel( 'email' ) }
										disabled={ busyAction !== null }
										loading={ busyAction === 'test-email' }
										className="mt-3 w-full text-xs"
									>
										{ __( 'Test Email', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</div>
					</section>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex items-start justify-between gap-3">
							<div className="space-y-1">
								<div className="airygen_h2_title">{ __( 'SMTP', 'airygen-seo' ) }</div>
								<p className="text-sm text-slate-500">{ __( 'Configure SMTP server connection and sender identity for Email delivery.', 'airygen-seo' ) }</p>
							</div>
							<div className="space-y-1">
								<div
									ref={ providerMenuRef }
									className="relative"
								>
									<Button
										variant="secondary"
										className="text-xs"
										onClick={ () => setIsProviderMenuOpen( ( open ) => ! open ) }
									>
										{ __( 'Choose a provider', 'airygen-seo' ) }
									</Button>
									{ isProviderMenuOpen ? (
										<div className="absolute right-0 z-20 mt-2 w-[480px] max-w-[80vw] rounded-md border border-slate-200 bg-white p-2 shadow-lg">
											<div className="grid grid-cols-3 gap-1">
												{ [
													{ value: 'custom', label: __( 'Custom', 'airygen-seo' ) },
													{ value: 'gmail', label: 'Gmail' },
													{ value: 'office365', label: 'Office 365 / Outlook' },
													{ value: 'mailgun', label: 'Mailgun' },
													{ value: 'sendgrid', label: 'SendGrid' },
													{ value: 'amazonses', label: 'Amazon SES' },
													{ value: 'zoho', label: 'Zoho Mail' },
													{ value: 'brevo', label: 'Brevo' },
													{ value: 'postmark', label: 'Postmark' },
												].map( ( option ) => (
													<button
														key={ option.value }
														type="button"
														className="block w-full rounded border border-transparent px-2 py-2 text-left text-xs text-slate-700 hover:border-slate-200 hover:bg-slate-50"
														onClick={ () => {
															setIsProviderMenuOpen( false );
															if ( option.value === 'custom' ) {
																return;
															}
															const provider = SMTP_PROVIDER_PRESETS[ option.value as Exclude<SmtpProviderKey, 'custom'> ];
															if ( ! provider ) {
																return;
															}
															updateSettings( {
																channels: {
																	...settings.channels,
																	email: {
																		...settings.channels.email,
																		smtp: {
																			...settings.channels.email.smtp,
																			host: provider.host,
																			port: provider.port,
																			auth: provider.auth,
																			secure: provider.secure,
																		},
																	},
																},
															} );
														} }
													>
														{ option.label }
													</button>
												) ) }
											</div>
										</div>
									) : null }
								</div>
							</div>
						</div>
						<div className="grid gap-3 md:grid-cols-4">
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'SMTP host', 'airygen-seo' ) }
								value={ settings.channels.email.smtp.host }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													host: value,
												},
											},
										},
									} )
								}
								help={ __( 'SMTP server address, for example smtp.gmail.com.', 'airygen-seo' ) }
							/>
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'SMTP port', 'airygen-seo' ) }
								type="number"
								min={ 1 }
								max={ 65535 }
								value={ String( settings.channels.email.smtp.port ) }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													port: Number( value ) || 587,
												},
											},
										},
									} )
								}
								help={ __( 'SMTP service port, for example 587 for Gmail.', 'airygen-seo' ) }
							/>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'SMTP auth', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'SMTP auth', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.channels.email.smtp.auth }
										onChange={ ( value ) =>
											updateSettings( {
												channels: {
													...settings.channels,
													email: {
														...settings.channels.email,
														smtp: {
															...settings.channels.email.smtp,
															auth: value,
														},
													},
												},
											} )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Enable this when your SMTP server requires username and password.', 'airygen-seo' ) }
								</p>
							</div>
							<Select
								label={ __( 'Security', 'airygen-seo' ) }
								className="rounded-lg border border-slate-200 p-3"
								value={ settings.channels.email.smtp.secure }
								options={ [
									{ value: '', label: __( 'None', 'airygen-seo' ) },
									{ value: 'tls', label: 'TLS' },
									{ value: 'ssl', label: 'SSL' },
								] }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													secure: value === 'tls' || value === 'ssl' ? value : '',
												},
											},
										},
									} )
								}
								help={ __( 'Choose TLS or SSL if your SMTP server requires encryption.', 'airygen-seo' ) }
							/>
						</div>
						<div className="grid gap-3 md:grid-cols-4">
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'Username', 'airygen-seo' ) }
								value={ settings.channels.email.smtp.username }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													username: value,
												},
											},
										},
									} )
								}
								help={ __( 'SMTP account username used for authentication.', 'airygen-seo' ) }
							/>
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'Password', 'airygen-seo' ) }
								type="password"
								value={ settings.channels.email.smtp.password }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													password: value,
												},
											},
										},
									} )
								}
								help={ __( 'SMTP account password used for authentication.', 'airygen-seo' ) }
							/>
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'Timeout (seconds)', 'airygen-seo' ) }
								type="number"
								min={ 1 }
								max={ 120 }
								value={ String( settings.channels.email.smtp.timeout ) }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													timeout: Number( value ) || 10,
												},
											},
										},
									} )
								}
								help={ __( 'How long to wait before SMTP connection is considered failed.', 'airygen-seo' ) }
							/>
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'From Email', 'airygen-seo' ) }
								value={ settings.channels.email.smtp.fromEmail }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													fromEmail: value,
												},
											},
										},
									} )
								}
								help={ __( 'Sender email address shown in recipients inbox.', 'airygen-seo' ) }
							/>
						</div>
						<div className="grid gap-3 md:grid-cols-4">
							<Input
								className="rounded-lg border border-slate-200 p-3"
								label={ __( 'From Name', 'airygen-seo' ) }
								value={ settings.channels.email.smtp.fromName }
								onChange={ ( value ) =>
									updateSettings( {
										channels: {
											...settings.channels,
											email: {
												...settings.channels.email,
												smtp: {
													...settings.channels.email.smtp,
													fromName: value,
												},
											},
										},
									} )
								}
								help={ __( 'Sender name shown in recipients inbox.', 'airygen-seo' ) }
							/>
						</div>
					</section>
				</div>
			) : null }

			{ currentView === 'telegram' ? (
				<div className="space-y-5">
					<div className="flex items-start gap-3">
						<HeadingIcon>
							<NotifyTelegramIcon className="h-8 w-8" aria-hidden="true" />
						</HeadingIcon>
						<div>
							<div className="airygen_h1_title">{ __( 'Telegram', 'airygen-seo' ) }</div>
							<div className="airygen_h1_description">{ __( 'Connect Telegram Bot credentials to send digest messages.', 'airygen-seo' ) }</div>
						</div>
					</div>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">{ __( 'Settings', 'airygen-seo' ) }</div>
							<p className="text-sm text-slate-500">{ __( 'Set bot token, target chat, and optional topic ID for delivery.', 'airygen-seo' ) }</p>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Enable Telegram', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Telegram', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.channels.telegram.enabled }
									onChange={ ( value ) =>
										updateSettings( {
											channels: {
												...settings.channels,
												telegram: {
													...settings.channels.telegram,
													enabled: value,
												},
											},
										} )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Deliver daily digest messages through Telegram Bot API.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-4">
								<Input className="rounded-lg border border-slate-200 p-3" label={ __( 'Bot token', 'airygen-seo' ) } value={ settings.channels.telegram.botToken } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, telegram: { ...settings.channels.telegram, botToken: value } } } ) } help={ __( 'Paste the token from BotFather for your Telegram bot.', 'airygen-seo' ) } />
								<Input className="rounded-lg border border-slate-200 p-3" label={ __( 'Chat ID', 'airygen-seo' ) } value={ settings.channels.telegram.chatId } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, telegram: { ...settings.channels.telegram, chatId: value } } } ) } help={ __( 'Enter the target chat ID where daily reports should be sent.', 'airygen-seo' ) } />
								<Input className="rounded-lg border border-slate-200 p-3" label={ __( 'Topic ID (optional)', 'airygen-seo' ) } value={ settings.channels.telegram.topicId } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, telegram: { ...settings.channels.telegram, topicId: value } } } ) } help={ __( 'Use this only for forum topics inside Telegram groups.', 'airygen-seo' ) } />
								<div className="rounded-lg border border-slate-200 p-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Test delivery', 'airygen-seo' ) }
									</p>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Send a sample message to verify bot and chat settings.', 'airygen-seo' ) }
									</p>
									<Button
										variant="secondary"
										onClick={ () => void runTestChannel( 'telegram' ) }
										disabled={ busyAction !== null }
										loading={ busyAction === 'test-telegram' }
										className="mt-3 w-full text-xs"
									>
										{ __( 'Test Telegram', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</div>
					</section>
				</div>
			) : null }

			{ currentView === 'discord' ? (
				<div className="space-y-5">
					<div className="flex items-start gap-3">
						<HeadingIcon>
							<NotifyDiscordIcon className="h-8 w-8" aria-hidden="true" />
						</HeadingIcon>
						<div>
							<div className="airygen_h1_title">{ __( 'Discord', 'airygen-seo' ) }</div>
							<div className="airygen_h1_description">{ __( 'Configure webhook, username, and avatar for Discord delivery.', 'airygen-seo' ) }</div>
						</div>
					</div>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">{ __( 'Settings', 'airygen-seo' ) }</div>
							<p className="text-sm text-slate-500">{ __( 'Set Discord webhook endpoint and optional display identity.', 'airygen-seo' ) }</p>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Enable Discord', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Discord', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.channels.discord.enabled }
									onChange={ ( value ) =>
										updateSettings( {
											channels: {
												...settings.channels,
												discord: {
													...settings.channels.discord,
													enabled: value,
												},
											},
										} )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Send digest reports to Discord using incoming webhooks.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-4">
								<Input className="rounded-lg border border-slate-200 p-3" label={ __( 'Webhook URL', 'airygen-seo' ) } value={ settings.channels.discord.webhook } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, discord: { ...settings.channels.discord, webhook: value } } } ) } isUrl help={ __( 'Paste the Discord incoming webhook URL for your channel.', 'airygen-seo' ) } />
								<Input className="rounded-lg border border-slate-200 p-3" label={ __( 'Username (optional)', 'airygen-seo' ) } value={ settings.channels.discord.username } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, discord: { ...settings.channels.discord, username: value } } } ) } help={ __( 'Override the sender name shown in Discord messages.', 'airygen-seo' ) } />
								<Input className="rounded-lg border border-slate-200 p-3" label={ __( 'Avatar URL (optional)', 'airygen-seo' ) } value={ settings.channels.discord.avatar } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, discord: { ...settings.channels.discord, avatar: value } } } ) } isUrl help={ __( 'Optional avatar image URL for the webhook sender.', 'airygen-seo' ) } />
								<div className="rounded-lg border border-slate-200 p-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Test delivery', 'airygen-seo' ) }
									</p>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Send a sample message to verify Discord webhook setup.', 'airygen-seo' ) }
									</p>
									<Button
										variant="secondary"
										onClick={ () => void runTestChannel( 'discord' ) }
										disabled={ busyAction !== null }
										loading={ busyAction === 'test-discord' }
										className="mt-3 w-full text-xs"
									>
										{ __( 'Test Discord', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</div>
					</section>
				</div>
			) : null }

			{ currentView === 'teams' ? (
				<div className="space-y-5">
					<div className="flex items-start gap-3">
						<HeadingIcon>
							<NotifyTeamsIcon className="h-8 w-8" aria-hidden="true" />
						</HeadingIcon>
						<div>
							<div className="airygen_h1_title">{ __( 'Microsoft Teams', 'airygen-seo' ) }</div>
							<div className="airygen_h1_description">{ __( 'Configure Teams webhook delivery for daily digest reports.', 'airygen-seo' ) }</div>
						</div>
					</div>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">{ __( 'Settings', 'airygen-seo' ) }</div>
							<p className="text-sm text-slate-500">{ __( 'Set the Teams incoming webhook and verify channel delivery.', 'airygen-seo' ) }</p>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Enable Teams', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Teams', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.channels.teams.enabled }
									onChange={ ( value ) =>
										updateSettings( {
											channels: {
												...settings.channels,
												teams: { ...settings.channels.teams, enabled: value },
											},
										} )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Send digest reports to Microsoft Teams using incoming webhooks.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-4">
								<Input className="rounded-lg border border-slate-200 p-3 md:col-span-3" label={ __( 'Webhook URL', 'airygen-seo' ) } value={ settings.channels.teams.webhook } onChange={ ( value ) => updateSettings( { channels: { ...settings.channels, teams: { ...settings.channels.teams, webhook: value } } } ) } isUrl help={ __( 'Paste the Microsoft Teams incoming webhook URL.', 'airygen-seo' ) } />
								<div className="rounded-lg border border-slate-200 p-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Test delivery', 'airygen-seo' ) }
									</p>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Send a sample message to confirm Teams delivery works.', 'airygen-seo' ) }
									</p>
									<Button
										variant="secondary"
										onClick={ () => void runTestChannel( 'teams' ) }
										disabled={ busyAction !== null }
										loading={ busyAction === 'test-teams' }
										className="mt-3 w-full text-xs"
									>
										{ __( 'Test Teams', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</div>
					</section>
				</div>
			) : null }
		</div>
	);
};

export default NotifyTab;

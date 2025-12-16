import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import Button from '../../../components/Button';
import HeadingIcon from '../../../components/HeadingIcon';
import Notice from '../../../components/Notice';
import Spinner from '../../../components/Spinner';
import { SiteHealthIcon } from '../../../components/Icons';
import formatDateTime from '../../../utils/formatDateTime';
import { __, sprintf } from '@wordpress/i18n';

type SiteHealthAction = {
	label: string;
	url: string;
};

type SiteHealthResult = {
	status: 'good' | 'recommended' | 'critical';
	label: string;
	description: string;
	slug?: string;
	code?: string;
	actions?: SiteHealthAction[];
	details?: Record<string, unknown>;
	group?: 'score' | 'feature' | 'advice' | string;
	scoreEligible?: boolean;
};

type SiteHealthResponse = {
	tests: Record<string, SiteHealthResult>;
	meta?: {
		site_url?: string;
		timestamp?: string;
	};
};

type SiteHealthTabProps = {
	restBase: string;
};

type SampleItem = {
	id?: number;
	title?: string;
	link?: string;
};

const CACHE_KEY = 'airygen-sitehealth-cache';
const CACHE_TTL_MS = 24 * 60 * 60 * 1000;

const statusClasses: Record<
	SiteHealthResult['status'],
	string
> = {
	good: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
	recommended: 'bg-amber-50 text-amber-700 border border-amber-200',
	critical: 'bg-rose-50 text-rose-700 border border-rose-200',
};

const groupLabels: Record<string, string> = {
	score: __( 'Score', 'airygen-seo' ),
	feature: __( 'Feature checks', 'airygen-seo' ),
};

const groupOrder = [ 'score', 'feature' ];
const groupBySlug: Record<string, string> = {
	core_sitemap: 'score',
	robots_visibility: 'score',
	permalink_structure: 'score',
	ssl_status: 'score',
	score_rest: 'feature',
};

const excludedFromScore = new Set( [ 'score_rest', 'search_console' ] );

const SiteHealthTab = ( { restBase }: SiteHealthTabProps ) => {
	const [ data, setData ] = useState<SiteHealthResponse | null >( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState<string | null>( null );

	const normalizedBase = useMemo(
		() => restBase.replace( /\/$/, '' ),
		[ restBase ],
	);

	const endpoint = useMemo(
		() => `${ normalizedBase }/site-health`,
		[ normalizedBase ],
	);

	const persistCache = useCallback(
		( payload: SiteHealthResponse ) => {
			if ( typeof window === 'undefined' ) {
				return;
			}

			try {
				window.localStorage.setItem(
					CACHE_KEY,
					JSON.stringify( {
						base: normalizedBase,
						cachedAt: Date.now(),
						data: payload,
					} ),
				);
			} catch {
				// Silently ignore cache failures.
			}
		},
		[ normalizedBase ],
	);

	const hydrateFromCache = useCallback( () => {
		if ( typeof window === 'undefined' ) {
			return false;
		}

		try {
			const raw = window.localStorage.getItem( CACHE_KEY );
			if ( ! raw ) {
				return false;
			}

			const parsed = JSON.parse( raw ) as {
				base?: string;
				cachedAt?: number;
				data?: SiteHealthResponse;
			};

			if (
				parsed.base !== normalizedBase ||
				! parsed.cachedAt ||
				! parsed.data
			) {
				return false;
			}

			const isFresh = Date.now() - parsed.cachedAt < CACHE_TTL_MS;
			if ( ! isFresh ) {
				return false;
			}

			setData( parsed.data );
			setIsLoading( false );
			setError( null );

			return true;
		} catch {
			return false;
		}
	}, [ normalizedBase ] );

	const fetchResults = useCallback( () => {
		setIsLoading( true );
		setError( null );
		apiFetch<SiteHealthResponse>( { path: endpoint } )
			.then( ( response ) => {
				setData( response );
				persistCache( response );
			} )
			.catch( ( err: unknown ) => {
				setError(
					err instanceof Error
						? err.message
						: __( 'Unable to load site health data.', 'airygen-seo' ),
				);
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ endpoint, persistCache ] );

	useEffect( () => {
		const servedFromCache = hydrateFromCache();
		if ( ! servedFromCache ) {
			fetchResults();
		}
	}, [ fetchResults, hydrateFromCache ] );

	const tests = useMemo( () => {
		if ( ! data?.tests ) {
			return [];
		}
		return Object.entries( data.tests );
	}, [ data ] );

	const lastChecked = data?.meta?.timestamp
		? formatTimestamp( data.meta.timestamp )
		: null;

	const scoringTests = useMemo(
		() =>
			tests.filter(
				( [ slug, result ] ) =>
					result.scoreEligible !== false &&
					! excludedFromScore.has( slug ),
			),
		[ tests ],
	);

	const groupedTests = useMemo( () => {
		const buckets: Record<string, Array<[ string, SiteHealthResult ]>> = {};

		tests.forEach( ( [ slug, result ] ) => {
			if ( 'search_console' === slug || 'advice' === result.group ) {
				return;
			}
			const group = result.group ?? groupBySlug[ slug ] ?? 'score';
			if ( ! buckets[ group ] ) {
				buckets[ group ] = [];
			}
			buckets[ group ].push( [ slug, result ] );
		} );

		const ordered: Array<{
			key: string;
			items: Array<[ string, SiteHealthResult ]>;
		}> = [];

		groupOrder.forEach( ( key ) => {
			if ( buckets[ key ]?.length ) {
				ordered.push( {
					key,
					items: buckets[ key ],
				} );
				delete buckets[ key ];
			}
		} );

		Object.entries( buckets ).forEach( ( [ key, items ] ) => {
			ordered.push( { key, items } );
		} );

		return ordered;
	}, [ tests ] );

	const totalTests = scoringTests.length;
	const totalGood = useMemo(
		() =>
			scoringTests.filter(
				( [ , result ] ) => result.status === 'good',
			).length,
		[ scoringTests ],
	);

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<SiteHealthIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'Sitewide SEO', 'airygen-seo' ) }
						</div>
						<div className="airygen_h1_description">
							{ __(
								'Run health checks across core SEO features and integrations.',
								'airygen-seo',
							) }
						</div>
					</div>
				</div>
				<div className="flex flex-col items-start gap-2 text-left md:items-end md:text-right">
					<div className="flex items-center gap-3">
						<span className="text-xs font-medium text-slate-700 md:text-right">
							{ totalTests > 0
								? sprintf(
									/* translators: 1: passed tests, 2: total tests. */
									__( 'Score: %1$s / %2$s', 'airygen-seo' ),
									String( totalGood ),
									String( totalTests ),
								)
								: __( 'Score: —', 'airygen-seo' ) }
						</span>
						<Button
							variant="secondary"
							onClick={ fetchResults }
							loading={ isLoading }
							className="px-3 py-1.5 text-xs"
						>
							{ __( 'Refresh', 'airygen-seo' ) }
						</Button>
					</div>
					{ lastChecked ? (
						<span className="block text-xs text-slate-500 sm:text-right">
							{ sprintf(
								/* translators: %s is the last checked date/time. */
								__( 'Last checked: %s', 'airygen-seo' ),
								lastChecked,
							) }
						</span>
					) : null }
				</div>
			</div>

			<div className="space-y-4">
				{ error ? (
					<Notice status="error" dismissible={ false }>
						<div className="space-y-1">
							<p className="font-medium">
								{ __( 'An error occurred', 'airygen-seo' ) }
							</p>
							<p className="text-xs">{ error }</p>
						</div>
					</Notice>
				) : null }

				{ isLoading ? (
					<div className="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
						<Spinner />
						{ __( 'Running checks…', 'airygen-seo' ) }
					</div>
				) : null }

				{ ! isLoading && ! error && tests.length === 0 ? (
					<p className="text-sm text-slate-600">
						{ __( 'No results available.', 'airygen-seo' ) }
					</p>
				) : null }

				{ tests.length > 0 ? (
					<div className="space-y-5">
						{ groupedTests.map( ( group ) => (
							<div
								key={ group.key }
								className="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
							>
								<div className="airygen_h2_title">
									{ groupLabels[ group.key ] ?? group.key }
								</div>
								<div className="space-y-3">
									{ group.items.map( ( [ slug, result ] ) => (
										<div
											key={ slug }
											className="space-y-2 rounded-lg border border-slate-200 p-4"
										>
											<div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
												<div>
													<div className="flex flex-wrap items-center gap-2">
														<p className="text-sm font-medium text-slate-800">
															{ result.label }
														</p>
													</div>
													<p className="mt-1 text-xs text-slate-600">
														{ result.description }
													</p>
												</div>
												<span
													className={ [
														'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
														statusClasses[ result.status ],
													].join( ' ' ) }
												>
													{ getStatusLabel(
														result.status,
													) }
												</span>
											</div>

											{ renderDetails( result ) }

											{ renderActions( slug, result.actions ) }
										</div>
									) ) }
								</div>
							</div>
						) ) }
					</div>
				) : null }
			</div>
		</div>
	);
};

const getStatusLabel = ( status: SiteHealthResult['status'] ) => {
	switch ( status ) {
		case 'good':
			return __( 'Good', 'airygen-seo' );
		case 'critical':
			return __( 'Critical', 'airygen-seo' );
		default:
			return __( 'Recommended', 'airygen-seo' );
	}
};

const renderDetails = ( result: SiteHealthResult ) => {
	if ( ! result.details ) {
		return null;
	}

	const detailBlocks = [];

	const grouped = result.details.grouped;
	if (
		grouped &&
		typeof grouped === 'object' &&
		! Array.isArray( grouped )
	) {
		const entries = Object.entries( grouped as Record<string, number> );
		if ( entries.length > 0 ) {
			detailBlocks.push(
				<div key="grouped">
					<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
						{ __( 'Grouped', 'airygen-seo' ) }
					</p>
					<div className="mt-1 flex flex-wrap gap-2">
						{ entries.map( ( [ type, total ] ) => (
							<span
								key={ `${ result.slug }-${ type }` }
								className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
							>
								{ type }: { total }
							</span>
						) ) }
					</div>
				</div>,
			);
		}
	}

	const sample = result.details.sample;
	if ( isSampleList( sample ) && sample.length > 0 ) {
		detailBlocks.push(
			<div key="sample" className="space-y-1">
				<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
					{ __( 'Samples', 'airygen-seo' ) }
				</p>
				<ul className="list-disc space-y-1 pl-4 text-xs text-slate-700">
					{ sample.map( ( item, index ) => {
						const label = item.title || item.link || __( 'Untitled', 'airygen-seo' );
						return (
							<li key={ `${ result.slug }-sample-${ index }` }>
								{ item.link ? (
									<a
										href={ item.link }
										target="_blank"
										rel="noreferrer"
										className="text-xs text-sky-600 hover:underline"
									>
										{ label }
									</a>
								) : (
									<span className="text-xs text-slate-700">
										{ label }
									</span>
								) }
							</li>
						);
					} ) }
				</ul>
			</div>,
		);
	}

	const message = result.details.message;
	if ( message && typeof message === 'string' ) {
		detailBlocks.push(
			<p key="message" className="text-xs text-slate-600">
				{ message }
			</p>,
		);
	}

	if ( detailBlocks.length === 0 ) {
		return null;
	}

	return <div className="space-y-3">{ detailBlocks }</div>;
};

const renderActions = (
	slug: string,
	actions?: SiteHealthAction[],
) => {
	if ( ! Array.isArray( actions ) || actions.length === 0 ) {
		return null;
	}

	return (
		<div className="mt-4 flex flex-wrap gap-2">
			{ actions.map( ( action, index ) => (
				<a
					key={ `${ slug }-action-${ index }` }
					href={ action.url }
					className="inline-flex items-center rounded-md border border-slate-200 px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
					target="_blank"
					rel="noreferrer"
				>
					{ action.label }
				</a>
			) ) }
		</div>
	);
};

const isSampleList = ( value: unknown ): value is SampleItem[] => {
	if ( ! Array.isArray( value ) ) {
		return false;
	}

	return value.every( ( item ) => {
		if ( typeof item !== 'object' || ! item ) {
			return false;
		}

		const candidate = item as Record<string, unknown>;

		return (
			typeof candidate.title === 'string' ||
			typeof candidate.link === 'string'
		);
	} );
};

const formatTimestamp = ( timestamp: string ): string => {
	const normalized = timestamp.replace( ' ', 'T' );
	return formatDateTime( normalized, timestamp );
};

export default SiteHealthTab;

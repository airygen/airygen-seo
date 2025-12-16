import apiFetch from '@wordpress/api-fetch';
import { useMemo, useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import HeadingIcon from '../../../components/HeadingIcon';
import { ScoreCalculatorIcon } from '../../../components/Icons';
import Popover from '../../../components/Popover';
import Button from '../../../components/Button';
import Checkbox from '../../../components/Checkbox';
import Input from '../../../components/Input';
import { buildScoreRuleGuide } from '../../../../shared/scoreRuleGuide';
import { clearAllScoreCaches } from '../../../../shared/scoreCache';
import type { MetaPayload } from '../../../types/api';
import type { ScoreCalculatorSettings } from '../../../types/settings';

type ScoreCalculatorTabProps = {
	settings: ScoreCalculatorSettings;
	meta: MetaPayload;
	restBase: string;
	onChange: ( value: ScoreCalculatorSettings ) => void;
};

type RecalculateProgressItem = {
	slug: string;
	label: string;
	total: number;
	processed: number;
	failed: number;
	lastProcessedAt?: string | null;
	current?: {
		id: number;
		title: string;
		score: number | null;
	} | null;
};

type RecalculateStatus = {
	running: boolean;
	processed: number;
	total: number;
	failed: number;
	current?: {
		postType: string;
		postTypeLabel: string;
		id: number;
		title: string;
		score: number | null;
	} | null;
	postTypes: RecalculateProgressItem[];
	startedAt?: string | null;
	finishedAt?: string | null;
	updatedAt?: string | null;
};

const formatPoints = ( value: number ): string =>
	Number.isInteger( value ) ? `${ value }` : value.toFixed( 1 );

const formatScore = ( value: number | null | undefined ): string => {
	if ( typeof value !== 'number' || Number.isNaN( value ) ) {
		return '—';
	}

	return Number.isInteger( value ) ? `${ value }` : value.toFixed( 1 );
};

const formatLastProcessedAt = ( value?: string | null ): string => {
	if ( ! value ) {
		return '';
	}

	const date = new Date( value );
	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}

	return date.toLocaleString();
};

const ScoreCalculatorTab = ( {
	settings,
	meta,
	restBase,
	onChange,
}: ScoreCalculatorTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'rules' | 'settings' | 'custom'>( 'settings' );
	const [ recalculateStatus, setRecalculateStatus ] = useState<RecalculateStatus | null>( null );
	const [ isRecalculating, setIsRecalculating ] = useState( false );
	const [ isPolling, setIsPolling ] = useState( false );
	const [ recalculateError, setRecalculateError ] = useState<string | null>( null );
	const spec = meta.scoreCalculator ?? {
		rules: [],
		minWeight: 0,
		maxWeight: 20,
	};
	const { minWeight, maxWeight, rules } = spec;
	const customRules = Array.isArray( spec.customRules ) ? spec.customRules : [];
	const sliderStep = 0.5;
	const normalizedBase = useMemo(
		() => restBase.replace( /\/$/, '' ),
		[ restBase ],
	);
	const scopePostTypes = useMemo(
		() => meta.postTypes.filter(
			( postType ) => ! [ 'wp_block', 'wp_navigation' ].includes( postType.slug ),
		),
		[ meta.postTypes ],
	);
	const recalculatePath = `${ normalizedBase }/score/recalculate`;
	const recalculateStepPath = `${ normalizedBase }/score/recalculate-step`;
	const recalculateStatusPath = `${ normalizedBase }/score/recalculate-status`;

	const groupedRules = useMemo( () => {
		const base: typeof rules = [];
		const bonus: typeof rules = [];
		rules.forEach( ( rule ) => {
			if ( rule.group === 'bonus' ) {
				bonus.push( rule );
			} else {
				base.push( rule );
			}
		} );
		return { base, bonus };
	}, [ rules ] );

	const applyRuleWeight = useCallback(
		( ruleId: string, defaultWeight: number, nextValue: number ) => {
			const clamped = Math.max( minWeight, Math.min( maxWeight, nextValue ) );
			const rounded = Math.round( clamped * 10 ) / 10;
			const nextRules = { ...settings.rules };

			if ( Math.abs( rounded - defaultWeight ) < 0.001 ) {
				delete nextRules[ ruleId ];
			} else {
				nextRules[ ruleId ] = rounded;
			}

			onChange( { ...settings, rules: nextRules } );
		},
		[ maxWeight, minWeight, onChange, settings ],
	);

	const renderRuleCard = useCallback(
		( rule: ( typeof rules )[ number ] ): JSX.Element => {
			const override = settings.rules?.[ rule.id ];
			const currentWeight =
				typeof override === 'number' ? override : rule.defaultWeight;
			const weightLabel = `${ formatPoints( currentWeight ) } ${ __( 'pts', 'airygen-seo' ) }`;
			const defaultLabel = sprintf(
				/* translators: %s is the default weight label with points unit, e.g. "1.5 pts". */
				__( 'Default: %s', 'airygen-seo' ),
				`${ formatPoints( rule.defaultWeight ) } ${ __( 'pts', 'airygen-seo' ) }`,
			);
			const guide = buildScoreRuleGuide( rule.id, rule.label );

			return (
				<div
					key={ rule.id }
					className="space-y-4 rounded-lg border border-slate-200 bg-white p-4"
				>
					<div className="flex flex-wrap items-start justify-between gap-3">
						<div className="space-y-2">
							<div className="flex items-center gap-2">
								<span className="text-sm font-medium text-slate-900">
									{ rule.label }
								</span>
								<Popover
									position="top-left"
									triggerAs="div"
									showClose={ true }
									trigger={
										<span className="_airygen_tips_popover inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-semibold text-slate-600 hover:border-sky-300 hover:text-sky-700">
											?
										</span>
									}
								>
									<div className="space-y-3 text-sm text-slate-700">
										<div>
											<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
												{ __( 'Meaning', 'airygen-seo' ) }
											</p>
											<p className="whitespace-pre-line break-words">{ guide.meaning }</p>
										</div>
										<div>
											<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
												{ __( 'How to improve', 'airygen-seo' ) }
											</p>
											<p className="whitespace-pre-line break-words">{ guide.how }</p>
										</div>
										<div>
											<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
												{ __( 'SEO impact', 'airygen-seo' ) }
											</p>
											<p className="whitespace-pre-line break-words">{ guide.impact }</p>
										</div>
									</div>
								</Popover>
							</div>
							{ rule.requiresFocus ? (
								<div>
									<span className="inline-flex items-center rounded-full border border-slate-300 px-2 py-0.5 text-xs font-medium text-slate-600">
										{ __( 'Requires focus keyphrase', 'airygen-seo' ) }
									</span>
								</div>
							) : null }
						</div>
						<div className="text-right whitespace-nowrap">
							<p className="text-sm font-semibold text-slate-900">
								{ weightLabel }
							</p>
							<p className="text-xs text-slate-500">{ defaultLabel }</p>
						</div>
					</div>

					<div className="flex flex-col gap-3 md:flex-row md:items-center">
						<input
							type="range"
							min={ minWeight }
							max={ maxWeight }
							step={ sliderStep }
							value={ currentWeight }
							onChange={ ( event ) =>
								applyRuleWeight(
									rule.id,
									rule.defaultWeight,
									Number( event.target.value ),
								)
							}
							className="h-2 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-sky-600"
							aria-label={ rule.label }
						/>
					</div>
				</div>
			);
		},
		[
			applyRuleWeight,
			settings.rules,
			sliderStep,
			maxWeight,
			minWeight,
		],
	);

	const setCustomRuleField = useCallback(
		( ruleId: string, fieldKey: string, nextRaw: string, defaultValue: number ) => {
			const nextNumber = Number( nextRaw );
			const nextSettings = {
				...settings,
				customRules: {
					...settings.customRules,
				},
			};

			const ruleValues = { ...( nextSettings.customRules[ ruleId ] ?? {} ) };

			if ( Number.isNaN( nextNumber ) || Math.abs( nextNumber - defaultValue ) < 0.0001 ) {
				delete ruleValues[ fieldKey ];
			} else {
				ruleValues[ fieldKey ] = nextNumber;
			}

			if ( Object.keys( ruleValues ).length === 0 ) {
				delete nextSettings.customRules[ ruleId ];
			} else {
				nextSettings.customRules[ ruleId ] = ruleValues;
			}

			onChange( nextSettings );
		},
		[ onChange, settings ],
	);

	const getCustomFieldValue = useCallback(
		(
			ruleId: string,
			fieldKey: string,
			fallbackValue: number,
		): number => {
			const ruleOverrides = settings.customRules?.[ ruleId ];
			if ( ruleOverrides && typeof ruleOverrides[ fieldKey ] === 'number' ) {
				return ruleOverrides[ fieldKey ] as number;
			}

			return fallbackValue;
		},
		[ settings.customRules ],
	);

	const fetchRecalculateStatus = useCallback( () => {
		return apiFetch<{ status: RecalculateStatus }>( {
			path: recalculateStatusPath,
		} )
			.then( ( response ) => {
				const nextStatus = response?.status ?? null;
				setRecalculateStatus( nextStatus );
				setRecalculateError( null );

				if ( nextStatus && ! nextStatus.running ) {
					setIsPolling( false );
					setIsRecalculating( false );
				}
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to fetch recalculation status.', 'airygen-seo' );
				setRecalculateError( message );
				setIsPolling( false );
				setIsRecalculating( false );
			} );
	}, [ recalculateStatusPath ] );

	const handleRecalculate = useCallback( () => {
		clearAllScoreCaches();
		setIsRecalculating( true );
		setRecalculateError( null );
		const scopedPostTypes = settings.postTypes.filter(
			( slug ) => ! [ 'wp_block', 'wp_navigation' ].includes( slug ),
		);

		apiFetch<{ status: RecalculateStatus }>( {
			path: recalculatePath,
			method: 'POST',
			data: {
				postTypes: scopedPostTypes,
			},
		} )
			.then( ( response ) => {
				const nextStatus = response?.status ?? null;
				setRecalculateStatus( nextStatus );
				setRecalculateError( null );
				setIsPolling( Boolean( nextStatus?.running ) );
				setIsRecalculating( false );
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to start recalculation.', 'airygen-seo' );
				setRecalculateError( message );
				setIsRecalculating( false );
				setIsPolling( false );
			} );
	}, [ recalculatePath, settings.postTypes ] );

	const processRecalculateStep = useCallback( () => {
		return apiFetch<{ status: RecalculateStatus }>( {
			path: recalculateStepPath,
			method: 'POST',
		} )
			.then( ( response ) => {
				const nextStatus = response?.status ?? null;
				setRecalculateStatus( nextStatus );
				setRecalculateError( null );

				if ( nextStatus && ! nextStatus.running ) {
					setIsPolling( false );
					setIsRecalculating( false );
				}
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to process recalculation step.', 'airygen-seo' );
				setRecalculateError( message );
				setIsPolling( false );
				setIsRecalculating( false );
			} );
	}, [ recalculateStepPath ] );

	useEffect( () => {
		fetchRecalculateStatus();
	}, [ fetchRecalculateStatus ] );

	useEffect( () => {
		if ( ! isPolling ) {
			return undefined;
		}

		const timer = window.setInterval( () => {
			processRecalculateStep();
		}, 1500 );

		return () => window.clearInterval( timer );
	}, [ isPolling, processRecalculateStep ] );

	if ( rules.length === 0 ) {
		return (
			<div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
				{ __(
					'No scoring rules found. Make sure the score engine metadata is available.',
					'airygen-seo',
				) }
			</div>
		);
	}

	let tabContent: JSX.Element;

	if ( activeTab === 'rules' ) {
		tabContent = (
			<div className="space-y-5 rounded-xl border border-slate-200 bg-white p-4">
				<section className="space-y-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Base rules', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'These factors make up the core content score.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
						{ groupedRules.base.map( renderRuleCard ) }
					</div>
				</section>

				{ groupedRules.bonus.length > 0 ? (
					<section className="space-y-4 border-t border-slate-200 pt-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Sitewide', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Optional boosts that can raise the total score when applicable.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
							{ groupedRules.bonus.map( renderRuleCard ) }
						</div>
					</section>
				) : null }
			</div>
		);
	} else if ( activeTab === 'settings' ) {
		const progressItems = Array.isArray( recalculateStatus?.postTypes )
			? recalculateStatus.postTypes
			: [];
		const recalculateBusy = isRecalculating;
		const recalculateLabel = __( 'Recalculate', 'airygen-seo' );

		tabContent = (
			<div className="space-y-5">
				<section className="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Settings', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Manage score calculation actions for existing posts.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="space-y-2 rounded-lg border border-slate-200 p-4">
						<Button
							variant="secondary"
							onClick={ handleRecalculate }
							loading={ recalculateBusy }
							disabled={ recalculateBusy }
						>
							{ recalculateLabel }
						</Button>
						<div className="flex items-start gap-2">
							<p className="text-xs text-slate-500">
								{ __(
									'After adjusting rule weights, recalculate scores for currently recorded posts.',
									'airygen-seo',
								) }
							</p>
							<Popover
								position="top-left"
								triggerAs="div"
								showClose={ true }
								trigger={
									<span className="_airygen_tips_popover inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-semibold text-slate-600 hover:border-sky-300 hover:text-sky-700">
										?
									</span>
								}
							>
								<div className="space-y-2 text-sm text-slate-700">
									<p>
										{ __(
											'This bulk recalculation uses saved post content in the database.',
											'airygen-seo',
										) }
									</p>
									<p>
										{ __(
											'The editor panel can include unsaved text and browser-based pixel measurement, so the score may look slightly different.',
											'airygen-seo',
										) }
									</p>
									<p>
										{ __(
											'For best consistency, save the post before comparing scores.',
											'airygen-seo',
										) }
									</p>
								</div>
							</Popover>
						</div>
						{ recalculateError ? (
							<p className="text-xs text-rose-600">{ recalculateError }</p>
						) : null }
						{ progressItems.length > 0 ? (
							<div className="space-y-3 rounded-lg border border-slate-200 p-3">
								{ progressItems.map( ( item ) => {
									const safeTotal = Math.max( 0, item.total ?? 0 );
									const safeProcessed = Math.max( 0, item.processed ?? 0 );
									const percent = safeTotal > 0
										? Math.min( 100, Math.round( ( safeProcessed / safeTotal ) * 100 ) )
										: 0;
									let itemStatusLabel = String( __( 'Waiting…', 'airygen-seo' ) );

									if ( safeTotal > 0 && safeProcessed >= safeTotal ) {
										const lastProcessedAt = formatLastProcessedAt( item.lastProcessedAt );
										if ( lastProcessedAt ) {
											itemStatusLabel = sprintf(
												/* translators: %s is the last processed datetime. */
												__( 'All completed, last processed: %s', 'airygen-seo' ),
												lastProcessedAt,
											);
										} else {
											itemStatusLabel = String( __( 'All completed', 'airygen-seo' ) );
										}
									} else if ( item.current && item.current.id > 0 ) {
										itemStatusLabel = `${ item.current.title || __( '(Untitled)', 'airygen-seo' ) } (${ __( 'ID', 'airygen-seo' ) }: ${ item.current.id }) - ${ formatScore( item.current.score ) }`;
									}

									return (
										<div key={ item.slug } className="space-y-1">
											<div className="flex items-center justify-between gap-2 text-xs">
												<span className="font-medium text-slate-700">{ item.label }</span>
												<span className="text-slate-500">{ percent }%</span>
											</div>
											<div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
												<div
													className="h-full rounded-full bg-sky-500 transition-all"
													style={ { width: `${ percent }%` } }
												/>
											</div>
											<div className="flex items-center justify-between gap-2 text-xs text-slate-600">
												<span>{ `${ safeProcessed } / ${ safeTotal }` }</span>
												<span className="text-right">{ itemStatusLabel }</span>
											</div>
										</div>
									);
								} ) }
							</div>
						) : null }
					</div>
				</section>

				<section className="rounded-xl border border-slate-200 bg-white p-4">
					<h3 className="text-lg font-semibold text-gray-800">
						{ __( 'Scope', 'airygen-seo' ) }
					</h3>
					<p className="mt-1 text-sm text-slate-500">
						{ __( 'Choose which post types can use Score Calculator.', 'airygen-seo' ) }
					</p>
					<div className="mt-4 space-y-3">
						<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
							{ __( 'Post types to include', 'airygen-seo' ) }
						</p>
						<div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
							{ scopePostTypes.map( ( postType ) => {
								const checked = settings.postTypes.includes( postType.slug );
								return (
									<div
										key={ postType.slug }
										className="rounded-lg border border-slate-200 p-3"
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
		);
	} else {
		tabContent = (
			<div className="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
				<div className="space-y-1">
					<div className="airygen_h2_title">
						{ __( 'Custom', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'SEO experts can adjust rule parameters based on their own experience.',
							'airygen-seo',
						) }
					</div>
				</div>
				{ customRules.length === 0 ? (
					<div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
						{ __( 'No custom rule fields are available.', 'airygen-seo' ) }
					</div>
				) : (
					<div className="grid grid-cols-1 gap-4">
						{ customRules.map( ( rule ) => (
							<section
								key={ rule.id }
								className="space-y-3 rounded-lg border border-slate-200 p-4"
							>
								<p className="text-sm font-semibold text-slate-900">{ rule.label }</p>
								<div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
									{ rule.fields.map( ( field ) => (
										<div
											key={ `${ rule.id }-${ field.key }` }
											className="rounded-lg border border-slate-200 p-3"
										>
											<Input
												label={ field.label }
												type="number"
												value={ String(
													getCustomFieldValue(
														rule.id,
														field.key,
														field.defaultValue,
													),
												) }
												min={ field.min }
												max={ field.max }
												step={ field.step ?? 1 }
												onChange={ ( value ) =>
													setCustomRuleField( rule.id, field.key, value, field.defaultValue )
												}
											/>
											<p className="mt-2 text-xs text-slate-500">
												{ field.help }
											</p>
											<p className="mt-1 text-[11px] text-slate-500">
												{ sprintf(
													/* translators: %s is numeric default value for this custom field. */
													__( 'Default: %s', 'airygen-seo' ),
													String( field.defaultValue ),
												) }
											</p>
										</div>
									) ) }
								</div>
							</section>
						) ) }
					</div>
				) }
			</div>
		);
	}

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<ScoreCalculatorIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'Score Calculator', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Adjust per-rule weights for the content score engine. Values reset to defaults when matching the baseline.',
								'airygen-seo',
							) }
						</p>
					</div>
				</div>
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
					data-airygen-e2e="tab-rules"
					className={
						activeTab === 'rules'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'rules' ) }
				>
					{ __( 'Rule Weights', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-custom"
					className={
						activeTab === 'custom'
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'custom' ) }
				>
					{ __( 'Custom Rules', 'airygen-seo' ) }
				</button>
			</div>

			{ tabContent }
		</div>
	);
};

export default ScoreCalculatorTab;

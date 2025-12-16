import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Popover, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { getEditorConfig } from '../config';
import { measureSerpDescription, measureSerpTitle } from '../utils/textMetrics';
import usePostDataField from '../hooks/usePostDataField';
import type { ScoreApiConfig, ScoreResponse } from '../types';
import { buildScoreRuleGuide } from '../../shared/scoreRuleGuide';
import { clearScoreCache, loadScoreCache, saveScoreCache } from '../../shared/scoreCache';

type ScoreState =
	| { status: 'idle' | 'loading'; data: null; error: null }
	| { status: 'ready'; data: ScoreResponse; error: null }
	| { status: 'error'; data: null; error: string };

type RuleResult = {
	id: string;
	label: string;
	score: number;
	weight: number;
	status: string;
	value?: unknown;
};

const fetchScore = async (
	api: ScoreApiConfig,
	postId: number,
	metaTitlePx: number,
	metaDescriptionPx: number,
): Promise< ScoreResponse > => {
	const query = new URLSearchParams( {
		post: String( postId ),
		meta_title_length_px: String( Math.max( 0, Math.round( metaTitlePx ) ) ),
		meta_description_length_px: String( Math.max( 0, Math.round( metaDescriptionPx ) ) ),
	} );
	return apiFetch< ScoreResponse >( {
		url: `${ api.root }?${ query.toString() }`,
		method: api.method ?? 'GET',
		headers: {
			'X-WP-Nonce': api.nonce,
		},
	} );
};

const truncate = ( value: number | string ): number => {
	const numeric = typeof value === 'string' ? parseFloat( value ) : value;
	return Math.floor( numeric );
};

const clampScore = ( score: number ): number => {
	if ( ! Number.isFinite( score ) ) {
		return 0;
	}
	return Math.max( 0, Math.min( 100, score ) );
};

const scoreTone = ( score: number ): { background: string; border: string; text: string } => {
	if ( ! Number.isFinite( score ) || score < 60 ) {
		return { background: '#feefed', border: '#f35d4a', text: '#f35d4a' };
	}
	if ( score < 80 ) {
		return { background: '#fef6eb', border: '#f8a738', text: '#f8a738' };
	}
	return { background: '#eefaf1', border: '#51c975', text: '#51c975' };
};

const normalizeRules = ( rules: unknown ): RuleResult[] => {
	if ( ! Array.isArray( rules ) ) {
		return [];
	}

	return rules
		.map( ( rule ) => ( rule && typeof rule === 'object' ? ( rule as Record< string, unknown > ) : null ) )
		.filter( ( rule ): rule is Record< string, unknown > => !! rule && 'id' in rule && 'label' in rule )
		.map( ( rule ) => ( {
			id: String( rule.id ?? '' ),
			label: String( rule.label ?? '' ),
			score: Number( rule.score ?? 0 ),
			weight: Number( rule.weight ?? 0 ),
			status: String( rule.status ?? '' ),
			value: rule.value,
		} ) )
		.filter( ( rule ) => rule.status !== 'na' );
};

const ScorePanel = () => {
	const apiConfig = getEditorConfig().scoreApi;
	const [ metaTitle ] = usePostDataField( 'title' );
	const [ metaDescription ] = usePostDataField( 'description' );
	const postId = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as { getCurrentPostId?: () => number | undefined };
			return editor.getCurrentPostId ? editor.getCurrentPostId() : undefined;
		},
		[],
	);
	const postExcerpt = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as {
				getEditedPostAttribute?: ( key: string ) => unknown;
			};
			return ( editor.getEditedPostAttribute?.( 'excerpt' ) as string ) || '';
		},
		[],
	);
	const postTitle = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as {
				getEditedPostAttribute?: ( key: string ) => unknown;
			};
			return ( editor.getEditedPostAttribute?.( 'title' ) as string ) || '';
		},
		[],
	);
	const saveState = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as {
				isSavingPost?: () => boolean;
				isAutosavingPost?: () => boolean;
				didPostSaveRequestSucceed?: () => boolean;
			};
			return {
				isSaving: editor.isSavingPost ? editor.isSavingPost() : false,
				isAutosaving: editor.isAutosavingPost ? editor.isAutosavingPost() : false,
				didSaveSucceed: editor.didPostSaveRequestSucceed
					? editor.didPostSaveRequestSucceed()
					: false,
			};
		},
		[],
	);
	const [ state, setState ] = useState< ScoreState >( {
		status: 'idle',
		data: null,
		error: null,
	} );
	const [ openRuleId, setOpenRuleId ] = useState< string | null >( null );
	const [ suggestionsExpanded, setSuggestionsExpanded ] = useState( true );
	const [ showTips, setShowTips ] = useState( false );
	const prevSaveRef = useRef( false );
	const prevDidSaveRef = useRef( false );
	const hasInitializedSaveRefs = useRef( false );
	const currentBlogId = getEditorConfig().currentBlogId ?? 1;

	const requestScore = useCallback( async ( options?: { forceRefresh?: boolean } ) => {
		if ( ! apiConfig?.root || ! postId ) {
			return;
		}

		if ( options?.forceRefresh ) {
			clearScoreCache( postId, currentBlogId );
		}

		const descriptionText = metaDescription?.trim() ? metaDescription : postExcerpt;
		const titleText = metaTitle?.trim() ? metaTitle : postTitle;
		const titlePx = measureSerpTitle( titleText ?? '' );
		const descriptionPx = measureSerpDescription( descriptionText ?? '' );

		setState( { status: 'loading', data: null, error: null } );

		try {
			const data = await fetchScore( apiConfig, postId, titlePx, descriptionPx );
			saveScoreCache( postId, data, currentBlogId );
			setState( { status: 'ready', data, error: null } );
		} catch ( error ) {
			const message =
				error && typeof error === 'object' && 'message' in error
					? String( ( error as Error ).message )
					: __( 'Unable to fetch SEO score.', 'airygen-seo' );
			setState( { status: 'error', data: null, error: message } );
		}
	}, [ apiConfig, postId, metaTitle, postTitle, metaDescription, postExcerpt, currentBlogId ] );

	useEffect( () => {
		if ( ! postId || ! apiConfig?.root ) {
			return;
		}

		const cached = loadScoreCache< ScoreResponse >( postId, currentBlogId );
		if ( cached ) {
			setState( { status: 'ready', data: cached, error: null } );
			return;
		}

		const persisted = getEditorConfig().scoreCalculator?.scoreCache;
		if ( persisted && persisted.post_id === postId ) {
			setState( { status: 'ready', data: persisted, error: null } );
			return;
		}

		void requestScore();
	}, [ postId, apiConfig, currentBlogId, requestScore ] );

	useEffect( () => {
		if ( ! postId || ! apiConfig?.root ) {
			return;
		}

		if ( ! hasInitializedSaveRefs.current ) {
			prevSaveRef.current = saveState.isSaving && ! saveState.isAutosaving;
			prevDidSaveRef.current = saveState.didSaveSucceed;
			hasInitializedSaveRefs.current = true;
			return;
		}

		const wasSaving = prevSaveRef.current;
		const isSavingNow = saveState.isSaving && ! saveState.isAutosaving;

		if ( wasSaving && ! isSavingNow ) {
			void requestScore();
		}

		prevSaveRef.current = isSavingNow;
	}, [ saveState.isSaving, saveState.isAutosaving, saveState.didSaveSucceed, postId, apiConfig, requestScore ] );

	useEffect( () => {
		if ( ! postId || ! apiConfig?.root ) {
			return;
		}

		if ( ! hasInitializedSaveRefs.current ) {
			prevDidSaveRef.current = saveState.didSaveSucceed;
			return;
		}

		if ( saveState.didSaveSucceed && ! prevDidSaveRef.current ) {
			void requestScore();
		}

		prevDidSaveRef.current = saveState.didSaveSucceed;
	}, [ saveState.didSaveSucceed, postId, apiConfig, requestScore ] );

	const totalScoreValue = state.data ? Number( state.data.total.score ) : Number.NaN;
	const totalScore = state.data ? truncate( state.data.total.score ) : '--';
	const totalMaxValue = state.data ? Number( state.data.total.max ) : Number.NaN;
	const tone = scoreTone( totalScoreValue );
	const viewWidth = 233;
	const viewHeight = 64;
	const strokeWidth = 4;
	const rectWidth = viewWidth - strokeWidth;
	const rectHeight = viewHeight - strokeWidth;
	const perimeter = 2 * ( rectWidth + rectHeight );
	const startOffset = ( rectWidth / 2 / perimeter ) * 100;
	const progress = clampScore(
		Number.isFinite( totalScoreValue ) && Number.isFinite( totalMaxValue ) && totalMaxValue > 0
			? Math.round( ( totalScoreValue / totalMaxValue ) * 100 )
			: 0,
	);
	const rules = useMemo( () => {
		if ( ! state.data ) {
			return [];
		}

		const baseRules = normalizeRules( state.data.base?.rules ).map( ( rule, index ) => ( {
			...rule,
			_sortIndex: index,
		} ) );

		const statusOrder: Record<string, number> = {
			fail: 0,
			warn: 1,
			pass: 2,
		};

		return baseRules
			.slice()
			.sort( ( a, b ) => {
				const orderA = statusOrder[ a.status ] ?? 1;
				const orderB = statusOrder[ b.status ] ?? 1;
				if ( orderA !== orderB ) {
					return orderA - orderB;
				}
				return a._sortIndex - b._sortIndex;
			} )
			.map( ( rule ) => {
				const { _sortIndex, ...rest } = rule;
				return rest;
			} );
	}, [ state.data ] );

	const failingCount = useMemo(
		() => rules.filter( ( rule ) => rule.status && rule.status !== 'pass' ).length,
		[ rules ],
	);
	const OverallIcon = () => (
		<svg width="20" height="20" viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M5.27574 1.38835V1.94369H4.16506V1.38835H5.27574ZM2.49903 1.38835V3.05437H1.38835V1.38835H2.49903ZM5.27574 3.60971V5.27574H4.16506V3.60971H5.27574ZM2.49903 4.7204V5.27574H1.38835V4.7204H2.49903ZM5.83108 0.833008H3.60971V2.49903H5.83108V0.833008ZM3.05437 0.833008H0.833008V3.60971H3.05437V0.833008ZM5.83108 3.05437H3.60971V5.83108H5.83108V3.05437ZM3.05437 4.16506H0.833008V5.83108H3.05437V4.16506Z" fill="black" />
		</svg>
	);

	const RecalculateIcon = () => (
		<span
			className="dashicons dashicons-update"
			aria-hidden="true"
			style={ { fontSize: '20px' } }
		/>
	);

	const renderSuggestions = () => (
		<div className="airygen-score-panel__suggestions">
			<div className="airygen-score-panel__summary">
				<div>
					<p className="airygen-score-panel__label">
						{ `${ failingCount } ${ __( 'things to improve', 'airygen-seo' ) }` }
						<span
							role="button"
							tabIndex={ 0 }
							data-airygen-e2e="score-show-tips"
							onClick={ () => setShowTips( ( value ) => ! value ) }
							onKeyDown={ ( event ) => {
								if ( event.key === 'Enter' || event.key === ' ' ) {
									event.preventDefault();
									setShowTips( ( value ) => ! value );
								}
							} }
							style={ {
								marginLeft: '8px',
								fontSize: '12px',
								color: '#3b82f6',
								cursor: 'pointer',
								fontWeight: 500,
							} }
						>
							{ showTips
								? __( 'Hide tips', 'airygen-seo' )
								: __( 'Show tips', 'airygen-seo' ) }
						</span>
					</p>
				</div>
				<div
					role="button"
					tabIndex={ 0 }
					onClick={ () => setSuggestionsExpanded( ( value ) => ! value ) }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' || event.key === ' ' ) {
							event.preventDefault();
							setSuggestionsExpanded( ( value ) => ! value );
						}
					} }
					aria-expanded={ suggestionsExpanded }
					aria-label={
						suggestionsExpanded
							? __( 'Collapse suggestions', 'airygen-seo' )
							: __( 'Expand suggestions', 'airygen-seo' )
					}
					className="airygen-score-panel__toggle"
					style={ {
						width: '24px',
						height: '24px',
						borderRadius: '9999px',
						border: '1px solid #cbd5f5',
						display: 'inline-flex',
						alignItems: 'center',
						justifyContent: 'center',
					} }
				>
					<span
						className={
							suggestionsExpanded
								? 'dashicons dashicons-arrow-down-alt2'
								: 'dashicons dashicons-arrow-right-alt2'
						}
						aria-hidden="true"
						style={ { fontSize: '14px', lineHeight: 1.6, display: 'block' } }
					/>
				</div>
			</div>
			{ suggestionsExpanded ? (
				<>
					{ 'loading' === state.status && (
						<div className="inline-flex items-center">
							<Spinner />
						</div>
					) }
					{ 'loading' !== state.status && failingCount === 0 && (
						<p>{ __( 'Great job! No suggestions right now.', 'airygen-seo' ) }</p>
					) }
					{ rules.length > 0 && (
						<div className="airygen-preview-checklist airygen-score-panel__list">
							{ rules.map( ( rule ) => {
								const isPass = rule.status === 'pass';
								const isWarn = rule.status === 'warn';
								let className = 'airygen-preview-check';
								if ( isPass ) {
									className += ' airygen-preview-check--good';
								} else if ( isWarn ) {
									className += ' airygen-preview-check--warn';
								} else {
									className += ' airygen-preview-check--bad';
								}
								const iconClass = isPass ? 'dashicons dashicons-yes' : 'dashicons dashicons-no-alt';
								const hintText = isWarn ? __( 'Consider improving', 'airygen-seo' ) : '';

								const guide = buildScoreRuleGuide( rule.id, rule.label );
								const showPopover = openRuleId === rule.id;

								return (
									<div
										className={ className }
										key={ rule.id }
										style={ { display: 'flex', alignItems: 'flex-start' } }
									>
										<span
											className={ iconClass }
											aria-hidden="true"
											style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
										/>
										<span style={ { flex: '1 1 auto' } }>
											<span style={ { display: 'inline-flex', alignItems: 'center', gap: '6px' } }>
												{ rule.label }
												{ showTips ? (
													<div
														role="button"
														tabIndex={ 0 }
														data-airygen-e2e={ `score-tip-button-${ rule.id }` }
														onClick={ () =>
															setOpenRuleId( showPopover ? null : rule.id )
														}
														onKeyDown={ ( event ) => {
															if ( event.key === 'Enter' || event.key === ' ' ) {
																event.preventDefault();
																setOpenRuleId( showPopover ? null : rule.id );
															}
														} }
														style={ {
															display: 'inline-flex',
															alignItems: 'center',
															justifyContent: 'center',
															width: '18px',
															height: '18px',
															borderRadius: '9999px',
															border: '1px solid #cbd5e1',
															fontSize: '11px',
															fontWeight: 600,
															color: '#475569',
															cursor: 'pointer',
														} }
													>
														?
													</div>
												) : null }
											</span>
											{ showPopover && (
												<Popover
													noArrow
													position="bottom left"
													onClose={ () => setOpenRuleId( null ) }
												>
													<div
														className="airygen-panel-popover"
														style={ { maxWidth: '260px', position: 'relative' } }
													>
														<button
															type="button"
															onClick={ () => setOpenRuleId( null ) }
															style={ {
																position: 'absolute',
																top: '8px',
																right: '8px',
																width: '22px',
																height: '22px',
																borderRadius: '9999px',
																border: '1px solid #e2e8f0',
																fontSize: '12px',
																color: '#64748b',
																background: '#fff',
																cursor: 'pointer',
															} }
															aria-label={ __( 'Close', 'airygen-seo' ) }
															data-airygen-e2e="score-tip-popover-close"
														>
															×
														</button>
														<div style={ { marginBottom: '8px' } }>
															<p style={ { fontSize: '11px', fontWeight: 600, textTransform: 'uppercase', color: '#94a3b8' } }>
																{ __( 'Meaning', 'airygen-seo' ) }
															</p>
															<p style={ { whiteSpace: 'pre-line', wordBreak: 'break-word' } }>{ guide.meaning }</p>
														</div>
														<div style={ { marginBottom: '8px' } }>
															<p style={ { fontSize: '11px', fontWeight: 600, textTransform: 'uppercase', color: '#94a3b8' } }>
																{ __( 'How to improve', 'airygen-seo' ) }
															</p>
															<p style={ { whiteSpace: 'pre-line', wordBreak: 'break-word' } }>{ guide.how }</p>
														</div>
														<div>
															<p style={ { fontSize: '11px', fontWeight: 600, textTransform: 'uppercase', color: '#94a3b8' } }>
																{ __( 'SEO impact', 'airygen-seo' ) }
															</p>
															<p style={ { whiteSpace: 'pre-line', wordBreak: 'break-word' } }>{ guide.impact }</p>
														</div>
													</div>
												</Popover>
											) }
											{ hintText && (
												<span
													className="airygen-score-panel__hint"
													style={ { display: 'block', marginTop: '4px' } }
												>
													{ hintText }
												</span>
											) }
										</span>
									</div>
								);
							} ) }
						</div>
					) }
				</>
			) : null }
		</div>
	);

	return (
		<div className="airygen-score-panel airygen-panel-layout">
			{ ! apiConfig ? (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Score Calculator is not configured yet. Configure it under Airygen → Modules.',
						'airygen-seo',
					) }
				</Notice>
			) : null }
			<div className="airygen-panel-tabs" style={ { marginBottom: 0 } }>
				<Button
					variant="primary"
					aria-label={ __( 'Overall', 'airygen-seo' ) }
					title={ __( 'Overall', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<OverallIcon />
				</Button>
				<Button
					variant="secondary"
					onClick={ () => void requestScore( { forceRefresh: true } ) }
					disabled={ 'loading' === state.status }
					aria-label={ __( 'Recalculate', 'airygen-seo' ) }
					title={ __( 'Recalculate', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<RecalculateIcon />
				</Button>
			</div>

			{ state.error && (
				<Notice status="error" isDismissible={ false }>
					{ state.error }
				</Notice>
			) }

			<>
				<div
					className="airygen-score-panel__summary"
					style={ {
						backgroundColor: tone.background,
						color: tone.text,
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						width: '233px',
						height: '64px',
						borderRadius: '8px',
						fontSize: '32px',
						fontWeight: 700,
						lineHeight: 1,
						position: 'relative',
						overflow: 'hidden',
						margin: 0,
					} }
				>
					<svg
						aria-hidden="true"
						viewBox={ `0 0 ${ viewWidth } ${ viewHeight }` }
						preserveAspectRatio="none"
						shapeRendering="geometricPrecision"
						style={ {
							position: 'absolute',
							inset: 0,
							width: '233px',
							height: '64px',
							pointerEvents: 'none',
						} }
					>
						<rect
							x={ strokeWidth / 2 }
							y={ strokeWidth / 2 }
							width={ rectWidth }
							height={ rectHeight }
							rx="8"
							ry="8"
							fill="none"
							stroke={ tone.border }
							strokeWidth={ strokeWidth }
							vectorEffect="non-scaling-stroke"
							pathLength="100"
							opacity="0.1"
							strokeLinecap="round"
						/>
						<rect
							x={ strokeWidth / 2 }
							y={ strokeWidth / 2 }
							width={ rectWidth }
							height={ rectHeight }
							rx="8"
							ry="8"
							fill="none"
							stroke={ tone.border }
							strokeWidth={ strokeWidth }
							vectorEffect="non-scaling-stroke"
							pathLength="100"
							strokeDasharray={ `${ progress } ${ 100 - progress }` }
							strokeDashoffset={ `-${ startOffset }` }
							strokeLinecap="round"
						/>
					</svg>
					<span style={ { position: 'relative', zIndex: 1 } }>
						{ 'loading' === state.status && ! state.data ? <Spinner /> : totalScore }
					</span>
				</div>
				{ renderSuggestions() }
			</>
		</div>
	);
};

export default ScorePanel;

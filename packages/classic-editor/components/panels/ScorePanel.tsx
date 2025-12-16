import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

type ScorePanelProps = {
	scoreState: {
		status: 'idle' | 'loading' | 'ready' | 'error';
		data?: unknown;
		error?: string | null;
	};
	editorConfig?: { scoreApi?: { root?: string } } | null;
	requestScore: ( options?: { forceRefresh?: boolean } ) => void;
	progress: number;
	tone: { background: string; text: string; border: string };
	totalScore: number;
	rules: Array<{ status: 'pass' | 'warn' | 'fail' | 'na' }>;
	renderSuggestions: () => JSX.Element | null;
	passedCount: number;
	showPassedRules: boolean;
	setShowPassedRules: ( updater: ( value: boolean ) => boolean ) => void;
};

export const ScorePanel = ( {
	scoreState,
	editorConfig,
	requestScore,
	progress,
	tone,
	totalScore,
	rules,
	renderSuggestions,
	passedCount,
	showPassedRules,
	setShowPassedRules,
}: ScorePanelProps ) => {
	return (
		<>
			<div className="airygen-panel-tabs">
				<button
					type="button"
					className="airygen-tab-panel-button is-primary"
				>
					{ __( 'Overall', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					className="airygen-tab-panel-button is-secondary"
					disabled={ 'loading' === scoreState.status }
					onClick={ () => void requestScore( { forceRefresh: true } ) }
				>
					{ __( 'Recalculate', 'airygen-seo' ) }
				</button>
			</div>
			<div className="airygen-panel-container">
				{ ! editorConfig?.scoreApi?.root && (
					<p className="airygen-classic-label-helper">
						{ __(
							'Score Calculator is not configured yet. Configure it under Airygen → Modules.',
							'airygen-seo',
						) }
					</p>
				) }
				{ scoreState.error && (
					<p className="airygen-classic-label-helper airygen-field-helper--bad">
						{ scoreState.error }
					</p>
				) }
				{ ( () => {
					const circleSize = 120;
					const circleStroke = 6;
					const radius = ( circleSize - circleStroke ) / 2;
					const circumference = 2 * Math.PI * radius;
					const filled = ( progress / 100 ) * circumference;

					const totalRules = rules.length;
					const failedRules = rules.filter( ( rule ) => rule.status !== 'pass' ).length;
					return (
						<div className="airygen-score-panel__score">
							<div
								className="airygen-score-panel__summary"
								style={ {
									backgroundColor: tone.background,
									color: tone.text,
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									width: `${ circleSize }px`,
									height: `${ circleSize }px`,
									borderRadius: '9999px',
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
									viewBox={ `0 0 ${ circleSize } ${ circleSize }` }
									shapeRendering="geometricPrecision"
									style={ {
										position: 'absolute',
										inset: 0,
										width: `${ circleSize }px`,
										height: `${ circleSize }px`,
										pointerEvents: 'none',
										transform: 'rotate(-90deg)',
									} }
								>
									<circle
										cx={ circleSize / 2 }
										cy={ circleSize / 2 }
										r={ radius }
										fill="none"
										stroke={ tone.border }
										strokeWidth={ circleStroke }
										opacity="0.1"
										strokeLinecap="round"
									/>
									<circle
										cx={ circleSize / 2 }
										cy={ circleSize / 2 }
										r={ radius }
										fill="none"
										stroke={ tone.border }
										strokeWidth={ circleStroke }
										strokeDasharray={ `${ filled } ${ circumference - filled }` }
										strokeLinecap="round"
									/>
								</svg>
								<span style={ { position: 'relative', zIndex: 1 } }>
									{ 'loading' === scoreState.status && ! scoreState.data ? <Spinner /> : totalScore }
								</span>
							</div>
							<div className="airygen-score-panel__summary-meta">
								<div className="airygen-score-panel__summary-row">
									<span className="airygen-score-panel__summary-label">
										{ __( 'Total rules', 'airygen-seo' ) }
									</span>
									<span className="airygen-score-panel__summary-value">{ totalRules }</span>
								</div>
								<div className="airygen-score-panel__summary-row">
									<span className="airygen-score-panel__summary-label">
										{ __( 'Passed', 'airygen-seo' ) }
									</span>
									<span className="airygen-score-panel__summary-value">{ passedCount }</span>
									<span
										role="button"
										tabIndex={ 0 }
										onClick={ () => setShowPassedRules( ( value ) => ! value ) }
										onKeyDown={ ( event ) => {
											if ( event.key === 'Enter' || event.key === ' ' ) {
												event.preventDefault();
												setShowPassedRules( ( value ) => ! value );
											}
										} }
										className="airygen-score-panel__summary-action"
									>
										{ showPassedRules ? __( 'Hide', 'airygen-seo' ) : __( 'Show', 'airygen-seo' ) }
									</span>
								</div>
								<div className="airygen-score-panel__summary-row">
									<span className="airygen-score-panel__summary-label">
										{ __( 'Failed', 'airygen-seo' ) }
									</span>
									<span className="airygen-score-panel__summary-value">{ failedRules }</span>
								</div>
							</div>
						</div>
					);
				} )() }
				{ renderSuggestions() }
			</div>
		</>
	);
};

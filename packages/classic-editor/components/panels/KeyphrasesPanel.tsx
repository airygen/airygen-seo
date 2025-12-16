import { __ } from '@wordpress/i18n';
import { getNoLongTailKeyphrasesAddedYetLabel } from '../../../shared/i18nPhrases';
import type { ChangeEvent, KeyboardEvent, ReactNode } from 'react';

type FieldGroupComponent = ( props: {
	label: string;
	description?: string;
	id: string;
	children: ReactNode;
} ) => JSX.Element;

type KeyphrasesPanelProps = {
	keyphraseSubTab: 'preview' | 'focus' | 'longtail';
	setKeyphraseSubTab: ( tab: 'preview' | 'focus' | 'longtail' ) => void;
	keyphrase: string;
	focusStats: { occurrences: number; density: number };
	longTailStats: Array<{ tag: string; occurrences: number; density: number }>;
	renderKeyphraseChecks: () => JSX.Element | null;
	FieldGroup: FieldGroupComponent;
	setKeyphrase: ( value: string ) => void;
	longTailPending: string;
	handleLongTailInputChange: ( value: string ) => void;
	handleLongTailKeyDown: ( event: KeyboardEvent<HTMLInputElement> ) => void;
	handleLongTailBlur: () => void;
	longTailList: string[];
	removeLongTailTag: ( index: number ) => void;
};

export const KeyphrasesPanel = ( {
	keyphraseSubTab,
	setKeyphraseSubTab,
	keyphrase,
	focusStats,
	longTailStats,
	renderKeyphraseChecks,
	FieldGroup,
	setKeyphrase,
	longTailPending,
	handleLongTailInputChange,
	handleLongTailKeyDown,
	handleLongTailBlur,
	longTailList,
	removeLongTailTag,
}: KeyphrasesPanelProps ) => {
	return (
		<>
			<div className="airygen-panel-tabs">
				<button
					type="button"
					className={
						keyphraseSubTab === 'preview'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setKeyphraseSubTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					className={
						keyphraseSubTab === 'focus'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setKeyphraseSubTab( 'focus' ) }
				>
					{ __( 'Focus Keyphrase', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					className={
						keyphraseSubTab === 'longtail'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setKeyphraseSubTab( 'longtail' ) }
				>
					{ __( 'Long-tail keyphrases', 'airygen-seo' ) }
				</button>
			</div>
			<div className="airygen-panel-container">
				{ keyphraseSubTab === 'preview' && (
					<>
						<div className="airygen-keyphrase-list">
							<div className="airygen-keyphrase-list__row">
								<div className="airygen-keyphrase-list__item">
									<span className="airygen-keyphrase-list__badge" aria-hidden="true"></span>
									<div className="airygen-keyphrase-list__keyword">
										<strong>{ keyphrase || __( '(Not set)', 'airygen-seo' ) }</strong>
									</div>
								</div>
								<div className="airygen-keyphrase-list__meta">
									<div
										className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--count"
										title={ __( 'This is the keyword count.', 'airygen-seo' ) }
									>
										<span className="airygen-keyphrase-list__dot" aria-hidden="true" />
										<span className="airygen-keyphrase-list__value">
											{ focusStats.occurrences }
										</span>
									</div>
									<div
										className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--density"
										title={ __( 'This is the keyword density.', 'airygen-seo' ) }
									>
										<span className="airygen-keyphrase-list__dot" aria-hidden="true" />
										<span className="airygen-keyphrase-list__value">
											{ focusStats.density.toFixed( 2 ) }%
										</span>
									</div>
								</div>
							</div>
							{ longTailStats.map( ( stat ) => (
								<div className="airygen-keyphrase-list__row" key={ stat.tag }>
									<div className="airygen-keyphrase-list__item">
										<div className="airygen-keyphrase-list__keyword">{ stat.tag }</div>
									</div>
									<div className="airygen-keyphrase-list__meta">
										<div
											className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--count"
											title={ __( 'This is the keyword count.', 'airygen-seo' ) }
										>
											<span className="airygen-keyphrase-list__dot" aria-hidden="true" />
											<span className="airygen-keyphrase-list__value">
												{ stat.occurrences }
											</span>
										</div>
										<div
											className="airygen-keyphrase-list__stat airygen-keyphrase-list__stat--density"
											title={ __( 'This is the keyword density.', 'airygen-seo' ) }
										>
											<span className="airygen-keyphrase-list__dot" aria-hidden="true" />
											<span className="airygen-keyphrase-list__value">
												{ stat.density.toFixed( 2 ) }%
											</span>
										</div>
									</div>
								</div>
							) ) }
							{ longTailStats.length === 0 && (
								<span className="airygen-preview-check airygen-preview-check--warn">
									{ getNoLongTailKeyphrasesAddedYetLabel() }
								</span>
							) }
						</div>
						<div className="airygen-preview-checklist">
							<span className="airygen-classic-label-text">{ __( 'Checks', 'airygen-seo' ) }</span>
							{ renderKeyphraseChecks() }
						</div>
					</>
				) }
				{ keyphraseSubTab === 'focus' && (
					<FieldGroup label={ __( 'Focus Keyphrase', 'airygen-seo' ) } id="airygen-focus-keyphrase">
						<input
							type="text"
							className="airygen-classic-input"
							value={ keyphrase }
							id="airygen-focus-keyphrase"
							onChange={ ( event: ChangeEvent<HTMLInputElement> ) =>
								setKeyphrase( event.target.value )
							}
						/>
						<span className="airygen-field-helper">
							{ __( 'Track your primary keyword for this document.', 'airygen-seo' ) }
						</span>
					</FieldGroup>
				) }
				{ keyphraseSubTab === 'longtail' && (
					<FieldGroup label={ __( 'Long-tail keyphrases', 'airygen-seo' ) } id="airygen-long-tail">
						<input
							type="text"
							className="airygen-classic-input"
							value={ longTailPending }
							id="airygen-long-tail"
							onChange={ ( event: ChangeEvent<HTMLInputElement> ) =>
								handleLongTailInputChange( event.target.value )
							}
							onKeyDown={ handleLongTailKeyDown }
							onBlur={ handleLongTailBlur }
						/>
						<span className="airygen-field-helper">
							{ __( 'Press Enter or comma to save each keyphrase (max 5).', 'airygen-seo' ) }
						</span>
						<div className="airygen-tag-list">
							{ longTailList.map( ( tag, index ) => (
								<span className="airygen-tag" key={ `${ tag }-${ index }` }>
									{ tag }
									<button
										type="button"
										onClick={ () => removeLongTailTag( index ) }
										aria-label={ __( 'Remove keyphrase', 'airygen-seo' ) }
									>
										×
									</button>
								</span>
							) ) }
							{ longTailList.length === 0 && (
								<span className="airygen-preview-check airygen-preview-check--warn">
									{ getNoLongTailKeyphrasesAddedYetLabel() }
								</span>
							) }
						</div>
					</FieldGroup>
				) }
			</div>
		</>
	);
};

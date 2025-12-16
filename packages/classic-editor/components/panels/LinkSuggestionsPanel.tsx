import { __ } from '@wordpress/i18n';
import { getNoSuggestionsYetLabel } from '../../../shared/i18nPhrases';
import type { LinkSuggestionsConfig } from '../../../block-editor/types';

type LinkSuggestion = {
	id: number;
	title: string;
	permalink: string;
	post_type: string;
	score: number;
};

type LinkSuggestionState = {
	status: 'idle' | 'loading' | 'ready' | 'error';
	error?: string | null;
	data?: {
		suggestions?: LinkSuggestion[];
	} | null;
};

type LinkSuggestionsPanelProps = {
	linksSubTab: 'suggestions';
	setLinksSubTab: ( tab: 'suggestions' ) => void;
	linkConfig?: LinkSuggestionsConfig | null;
	linkState: LinkSuggestionState;
	insertLinkSuggestion: ( item: LinkSuggestion ) => void;
};

export const LinkSuggestionsPanel = ( {
	linksSubTab,
	setLinksSubTab,
	linkConfig,
	linkState,
	insertLinkSuggestion,
}: LinkSuggestionsPanelProps ) => (
	<>
		<div className="airygen-panel-tabs">
			<button
				type="button"
				className={
					linksSubTab === 'suggestions'
						? 'airygen-tab-panel-button is-primary'
						: 'airygen-tab-panel-button is-secondary'
				}
				onClick={ () => setLinksSubTab( 'suggestions' ) }
			>
				{ __( 'Suggestions', 'airygen-seo' ) }
			</button>
		</div>
		<div className="airygen-panel-container">
			{ ! linkConfig?.enabled && (
				<p className="airygen-classic-label-helper airygen-field-helper--warn">
					{ __( 'Link Suggestions is disabled. Enable it in Airygen → Modules.', 'airygen-seo' ) }
				</p>
			) }
			{ linkConfig?.enabled && ! linkConfig?.api?.root && (
				<p className="airygen-classic-label-helper airygen-field-helper--bad">
					{ __( 'Link Suggestions API is not configured.', 'airygen-seo' ) }
				</p>
			) }
			{ linkConfig?.enabled && linkConfig?.api?.root && linkState.status === 'loading' && (
				<p className="airygen-classic-label-helper">
					{ __( 'Finding suggestions…', 'airygen-seo' ) }
				</p>
			) }
			{ linkConfig?.enabled && linkConfig?.api?.root && linkState.error && (
				<p className="airygen-classic-label-helper airygen-field-helper--bad">{ linkState.error }</p>
			) }
			{ linkConfig?.enabled &&
				linkConfig?.api?.root &&
				linkState.status === 'ready' &&
				( linkState.data?.suggestions?.length ?? 0 ) === 0 && (
				<p className="airygen-classic-label-helper">
					{ getNoSuggestionsYetLabel() }
				</p>
			) }
			{ linkConfig?.enabled &&
				linkConfig?.api?.root &&
				linkState.status === 'ready' &&
				( linkState.data?.suggestions?.length ?? 0 ) > 0 && (
				<div className="airygen-preview-checklist">
					<div className="airygen-keyphrase-list">
						{ ( linkState.data?.suggestions ?? [] ).map( ( item ) => (
							<div className="airygen-keyphrase-list__row" key={ item.id }>
								<div className="airygen-keyphrase-list__item airygen-link-suggestions__item">
									<div className="airygen-link-suggestions__title">
										{ item.title || __( '(No title)', 'airygen-seo' ) }
									</div>
								</div>
								<div className="airygen-link-suggestions__item-actions">
									<a
										className="airygen-component-button is-secondary airygen-link-suggestions__icon-btn"
										href={ item.permalink }
										target="_blank"
										rel="noreferrer"
									>
										{ __( 'View', 'airygen-seo' ) }
									</a>
									<button
										type="button"
										className="airygen-component-button is-secondary airygen-link-suggestions__icon-btn"
										onClick={ () => insertLinkSuggestion( item ) }
									>
										{ __( 'Insert', 'airygen-seo' ) }
									</button>
								</div>
							</div>
						) ) }
					</div>
				</div>
			) }
		</div>
	</>
);

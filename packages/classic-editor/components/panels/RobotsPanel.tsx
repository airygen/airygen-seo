import { __ } from '@wordpress/i18n';
import { getNoGlobalRobotsDefaultsConfiguredYetLabel } from '../../../shared/i18nPhrases';
import type { ChangeEvent, ReactNode } from 'react';

type FieldGroupComponent = ( props: {
	label: string;
	description?: string;
	id: string;
	children: ReactNode;
} ) => JSX.Element;

type IndexChoice = '' | 'index' | 'noindex';
type FollowChoice = '' | 'follow' | 'nofollow';
type MaxImagePreviewChoice = '' | 'none' | 'standard' | 'large';
type MaxVideoPreviewChoice = '' | '-1' | '0' | '30' | '60';

type RobotsPanelProps = {
	robotsSubTab: 'preview' | 'custom';
	setRobotsSubTab: ( tab: 'preview' | 'custom' ) => void;
	robotsSource: 'default' | 'custom';
	setRobotsSource: ( value: 'default' | 'custom' ) => void;
	clearRobotsOverride: () => void;
	robotsPreviewValue: string;
	defaultRobotsDirective: string;
	FieldGroup: FieldGroupComponent;
	robotsIndexChoice: IndexChoice;
	setRobotsIndexChoice: ( value: IndexChoice ) => void;
	robotsFollowChoice: FollowChoice;
	setRobotsFollowChoice: ( value: FollowChoice ) => void;
	robotsMaxImagePreview: MaxImagePreviewChoice;
	setRobotsMaxImagePreview: ( value: MaxImagePreviewChoice ) => void;
	robotsMaxVideoPreview: MaxVideoPreviewChoice;
	setRobotsMaxVideoPreview: ( value: MaxVideoPreviewChoice ) => void;
	updateRobotsFromState: (
		index: IndexChoice,
		follow: FollowChoice,
		extra: Set<string>,
		custom: string,
		maxImage: MaxImagePreviewChoice,
		maxVideo: MaxVideoPreviewChoice,
	) => void;
	robotsExtras: Set<string>;
	toggleRobotsExtra: ( key: string, checked: boolean ) => void;
	robotsCustomDirectives: string;
	setRobotsCustomDirectives: ( value: string ) => void;
	ROBOTS_EXTRA_OPTIONS: ReadonlyArray<{ key: string; label: ReactNode }>;
};

export const RobotsPanel = ( {
	robotsSubTab,
	setRobotsSubTab,
	robotsSource,
	setRobotsSource,
	clearRobotsOverride,
	robotsPreviewValue,
	defaultRobotsDirective,
	FieldGroup,
	robotsIndexChoice,
	setRobotsIndexChoice,
	robotsFollowChoice,
	setRobotsFollowChoice,
	robotsMaxImagePreview,
	setRobotsMaxImagePreview,
	robotsMaxVideoPreview,
	setRobotsMaxVideoPreview,
	updateRobotsFromState,
	robotsExtras,
	toggleRobotsExtra,
	robotsCustomDirectives,
	setRobotsCustomDirectives,
	ROBOTS_EXTRA_OPTIONS,
}: RobotsPanelProps ) => (
	<>
		<div className="airygen-panel-tabs">
			<button
				type="button"
				className={
					robotsSubTab === 'preview'
						? 'airygen-tab-panel-button is-primary'
						: 'airygen-tab-panel-button is-secondary'
				}
				onClick={ () => setRobotsSubTab( 'preview' ) }
			>
				{ __( 'Preview', 'airygen-seo' ) }
			</button>
			<button
				type="button"
				className={
					robotsSubTab === 'custom'
						? 'airygen-tab-panel-button is-primary'
						: 'airygen-tab-panel-button is-secondary'
				}
				onClick={ () => setRobotsSubTab( 'custom' ) }
			>
				{ __( 'Custom', 'airygen-seo' ) }
			</button>
		</div>
		<div className="airygen-panel-container">
			{ robotsSubTab === 'preview' && (
				<>
					<div className="airygen-robots-panel__preview">
						<div className="airygen-robots-panel__preview-value">
							{ robotsSource === 'custom'
								? ( robotsPreviewValue || __( 'No directives yet', 'airygen-seo' ) )
								: ( defaultRobotsDirective ||
										getNoGlobalRobotsDefaultsConfiguredYetLabel() ) }
						</div>
					</div>
					<div className="airygen-classic-field">
						<span className="airygen-classic-label-text">
							{ __( 'Source', 'airygen-seo' ) }
						</span>
						<div className="airygen-classic-options">
							<span className="airygen-classic-option">
								<input
									type="radio"
									name="airygen_robots_source"
									value="default"
									checked={ robotsSource === 'default' }
									onChange={ () => {
										setRobotsSource( 'default' );
										clearRobotsOverride();
									} }
								/>
								<span>{ __( 'Use defaults', 'airygen-seo' ) }</span>
							</span>
							<span className="airygen-classic-option">
								<input
									type="radio"
									name="airygen_robots_source"
									value="custom"
									checked={ robotsSource === 'custom' }
									onChange={ () => setRobotsSource( 'custom' ) }
								/>
								<span>{ __( 'Use custom data', 'airygen-seo' ) }</span>
							</span>
						</div>
					</div>
				</>
			) }
			{ robotsSubTab === 'custom' && (
				<div className="airygen-robots-panel">
					<div className="airygen-classic-grid">
						<FieldGroup label={ __( 'Indexing directive', 'airygen-seo' ) } id="airygen-robots-index">
							<select
								id="airygen-robots-index"
								className="airygen-classic-input"
								value={ robotsIndexChoice }
								onChange={ ( event: ChangeEvent<HTMLSelectElement> ) => {
									const next = ( event.target.value || '' ) as IndexChoice;
									setRobotsIndexChoice( next );
									updateRobotsFromState(
										next,
										robotsFollowChoice,
										robotsExtras,
										robotsCustomDirectives,
										robotsMaxImagePreview,
										robotsMaxVideoPreview,
									);
								} }
							>
								<option value="">{ __( 'Inherit default', 'airygen-seo' ) }</option>
								<option value="index">{ __( 'Index', 'airygen-seo' ) }</option>
								<option value="noindex">{ __( 'Noindex', 'airygen-seo' ) }</option>
							</select>
							<span className="airygen-field-helper">
								{ __( 'Controls whether search engines should index this page.', 'airygen-seo' ) }
							</span>
						</FieldGroup>
						<FieldGroup label={ __( 'Link following directive', 'airygen-seo' ) } id="airygen-robots-follow">
							<select
								id="airygen-robots-follow"
								className="airygen-classic-input"
								value={ robotsFollowChoice }
								onChange={ ( event: ChangeEvent<HTMLSelectElement> ) => {
									const next = ( event.target.value || '' ) as FollowChoice;
									setRobotsFollowChoice( next );
									updateRobotsFromState(
										robotsIndexChoice,
										next,
										robotsExtras,
										robotsCustomDirectives,
										robotsMaxImagePreview,
										robotsMaxVideoPreview,
									);
								} }
							>
								<option value="">{ __( 'Inherit default', 'airygen-seo' ) }</option>
								<option value="follow">{ __( 'Follow', 'airygen-seo' ) }</option>
								<option value="nofollow">{ __( 'Nofollow', 'airygen-seo' ) }</option>
							</select>
							<span className="airygen-field-helper">
								{ __( 'Controls whether search engines follow links on this page.', 'airygen-seo' ) }
							</span>
						</FieldGroup>
						<FieldGroup label={ __( 'Max image preview', 'airygen-seo' ) } id="airygen-robots-image-preview">
							<select
								id="airygen-robots-image-preview"
								className="airygen-classic-input"
								value={ robotsMaxImagePreview }
								onChange={ ( event: ChangeEvent<HTMLSelectElement> ) => {
									const value = ( event.target.value || '' ) as MaxImagePreviewChoice;
									setRobotsMaxImagePreview( value );
									updateRobotsFromState(
										robotsIndexChoice,
										robotsFollowChoice,
										robotsExtras,
										robotsCustomDirectives,
										value,
										robotsMaxVideoPreview,
									);
								} }
							>
								<option value="">{ __( 'Inherit default', 'airygen-seo' ) }</option>
								<option value="none">{ __( 'None', 'airygen-seo' ) }</option>
								<option value="standard">{ __( 'Standard', 'airygen-seo' ) }</option>
								<option value="large">{ __( 'Large', 'airygen-seo' ) }</option>
							</select>
							<span className="airygen-field-helper">
								{ __( 'Limits how large image previews can appear in search results.', 'airygen-seo' ) }
							</span>
						</FieldGroup>
						<FieldGroup label={ __( 'Max video preview', 'airygen-seo' ) } id="airygen-robots-video-preview">
							<select
								id="airygen-robots-video-preview"
								className="airygen-classic-input"
								value={ robotsMaxVideoPreview }
								onChange={ ( event: ChangeEvent<HTMLSelectElement> ) => {
									const value = ( event.target.value || '' ) as MaxVideoPreviewChoice;
									setRobotsMaxVideoPreview( value );
									updateRobotsFromState(
										robotsIndexChoice,
										robotsFollowChoice,
										robotsExtras,
										robotsCustomDirectives,
										robotsMaxImagePreview,
										value,
									);
								} }
							>
								<option value="">{ __( 'Inherit default', 'airygen-seo' ) }</option>
								<option value="0">{ __( 'Disable previews (0 seconds)', 'airygen-seo' ) }</option>
								<option value="30">{ `30 ${ __( 'seconds', 'airygen-seo' ) }` }</option>
								<option value="60">{ `60 ${ __( 'seconds', 'airygen-seo' ) }` }</option>
								<option value="-1">{ __( 'No limit (-1)', 'airygen-seo' ) }</option>
							</select>
							<span className="airygen-field-helper">
								{ __( 'Sets the maximum video preview length shown by search engines.', 'airygen-seo' ) }
							</span>
						</FieldGroup>
					</div>
					<div className="airygen-robots-panel__extras">
						{ ROBOTS_EXTRA_OPTIONS.map( ( option ) => (
							<span key={ option.key } className="airygen-classic-option">
								<input
									type="checkbox"
									checked={ robotsExtras.has( option.key ) }
									onChange={ ( event: ChangeEvent<HTMLInputElement> ) =>
										toggleRobotsExtra( option.key, event.target.checked )
									}
								/>
								<span>{ option.label }</span>
							</span>
						) ) }
					</div>
					<FieldGroup
						label={ __( 'Custom directives (comma-separated)', 'airygen-seo' ) }
						id="airygen-robots-custom"
					>
						<input
							type="text"
							id="airygen-robots-custom"
							className="airygen-classic-input"
							value={ robotsCustomDirectives }
							onChange={ ( event: ChangeEvent<HTMLInputElement> ) => {
								const next = event.target.value;
								setRobotsCustomDirectives( next );
								updateRobotsFromState(
									robotsIndexChoice,
									robotsFollowChoice,
									robotsExtras,
									next,
									robotsMaxImagePreview,
									robotsMaxVideoPreview,
								);
							} }
						/>
						<span className="airygen-field-helper">
							{ __( 'Add any extra robots directives not covered above, separated by commas.', 'airygen-seo' ) }
						</span>
					</FieldGroup>
					<div className="airygen-robots-panel__preview">
						<span className="airygen-classic-label-text">
							{ __( 'Preview', 'airygen-seo' ) }
						</span>
						<div className="airygen-robots-panel__preview-value">
							{ robotsPreviewValue || __( 'No directives yet', 'airygen-seo' ) }
						</div>
					</div>
					<button
						type="button"
						className="airygen-component-button is-secondary airygen-robots-panel__reset"
						onClick={ clearRobotsOverride }
					>
						{ __( 'Use site-wide default', 'airygen-seo' ) }
					</button>
				</div>
			) }
		</div>
	</>
);

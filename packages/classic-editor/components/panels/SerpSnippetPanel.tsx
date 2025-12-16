import { __ } from '@wordpress/i18n';
import type { ChangeEvent, ReactNode } from 'react';
import { decodeUrlPreview } from '../../../shared/urlPreview';

type FieldGroupComponent = ( props: {
	label: string;
	description?: string;
	id: string;
	children: ReactNode;
} ) => JSX.Element;

type CheckStatus = 'good' | 'warn' | 'bad';

type SerpSnippetPanelProps = {
	snippetSubTab: 'preview' | 'custom';
	setSnippetSubTab: ( tab: 'preview' | 'custom' ) => void;
	previewUrl: string;
	previewTitle: string;
	previewDescription: string;
	previewChoice: 'default' | 'custom';
	setPreviewChoice: ( value: 'default' | 'custom' ) => void;
	allChecklistPass: boolean;
	buildCheckClass: ( status: CheckStatus ) => string;
	checklistTitleBarStatus: CheckStatus;
	checklistDescriptionBarStatus: CheckStatus;
	checklistTitleStatus: string;
	checklistDescriptionStatus: string;
	titleHasFocus: boolean;
	descriptionHasFocus: boolean;
	titleFocusMessage: () => string;
	descriptionFocusMessage: () => string;
	keyphraseStacked: boolean;
	FieldGroup: FieldGroupComponent;
	title: string;
	setTitle: ( value: string ) => void;
	titlePixels: number;
	titlePixelsRounded: number;
	titleBarStatus: CheckStatus;
	titleStatusText: string | null;
	description: string;
	setDescription: ( value: string ) => void;
	descriptionPixels: number;
	descriptionPixelsRounded: number;
	descriptionBarStatus: CheckStatus;
	descriptionStatusText: string | null;
	renderBar: ( value: number, max: number, status: CheckStatus ) => JSX.Element | null;
};

export const SerpSnippetPanel = ( {
	snippetSubTab,
	setSnippetSubTab,
	previewUrl,
	previewTitle,
	previewDescription,
	previewChoice,
	setPreviewChoice,
	allChecklistPass,
	buildCheckClass,
	checklistTitleBarStatus,
	checklistDescriptionBarStatus,
	checklistTitleStatus,
	checklistDescriptionStatus,
	titleHasFocus,
	descriptionHasFocus,
	titleFocusMessage,
	descriptionFocusMessage,
	keyphraseStacked,
	FieldGroup,
	title,
	setTitle,
	titlePixels,
	titlePixelsRounded,
	titleBarStatus,
	titleStatusText,
	description,
	setDescription,
	descriptionPixels,
	descriptionPixelsRounded,
	descriptionBarStatus,
	descriptionStatusText,
	renderBar,
}: SerpSnippetPanelProps ) => {
	return (
		<>
			<div className="airygen-panel-tabs">
				<button
					type="button"
					className={
						snippetSubTab === 'preview'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setSnippetSubTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					className={
						snippetSubTab === 'custom'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setSnippetSubTab( 'custom' ) }
				>
					{ __( 'Custom', 'airygen-seo' ) }
				</button>
			</div>
			<div className="airygen-panel-container">
				{ snippetSubTab === 'preview' && (
					<>
						<div className="airygen-snippet-preview">
							<p className="airygen-snippet-preview__url">
								{ previewUrl
									? decodeUrlPreview( previewUrl )
									: __( 'Permalink not available yet', 'airygen-seo' ) }
							</p>
							<span className="airygen-snippet-preview__title">
								{ previewTitle || __( 'Add a title to see preview', 'airygen-seo' ) }
							</span>
							<p className="airygen-snippet-preview__description">
								{ previewDescription || __( 'Add a description to see preview', 'airygen-seo' ) }
							</p>
						</div>
						<div className="airygen-classic-field">
							<span className="airygen-classic-label-text">
								{ __( 'Source', 'airygen-seo' ) }
							</span>
							<div className="airygen-classic-options">
								<span className="airygen-classic-option">
									<input
										type="radio"
										name="airygen_snippet_source"
										value="default"
										checked={ previewChoice === 'default' }
										onChange={ () => setPreviewChoice( 'default' ) }
									/>
									<span>{ __( 'Use defaults', 'airygen-seo' ) }</span>
								</span>
								<span className="airygen-classic-option">
									<input
										type="radio"
										name="airygen_snippet_source"
										value="custom"
										checked={ previewChoice === 'custom' }
										onChange={ () => setPreviewChoice( 'custom' ) }
									/>
									<span>{ __( 'Use custom data', 'airygen-seo' ) }</span>
								</span>
							</div>
						</div>
						<div className="airygen-preview-checklist">
							<span className="airygen-classic-label-text">
								{ __( 'Checks', 'airygen-seo' ) }
							</span>
							{ allChecklistPass ? (
								<p className={ buildCheckClass( 'good' ) }>
									<span className="airygen-preview-check__icon">
										<span className="dashicons dashicons-yes" aria-hidden="true" />
									</span>
									<span>{ __( 'All snippet checks look good.', 'airygen-seo' ) }</span>
								</p>
							) : (
								<>
									<p className={ buildCheckClass( checklistTitleBarStatus ) }>
										<span className="airygen-preview-check__icon">
											<span
												className={ `dashicons ${ checklistTitleBarStatus === 'bad' ? 'dashicons-no-alt' : 'dashicons-yes' }` }
												aria-hidden="true"
											/>
										</span>
										<span>{ checklistTitleStatus }</span>
									</p>
									<p className={ buildCheckClass( checklistDescriptionBarStatus ) }>
										<span className="airygen-preview-check__icon">
											<span
												className={ `dashicons ${ checklistDescriptionBarStatus === 'bad' ? 'dashicons-no-alt' : 'dashicons-yes' }` }
												aria-hidden="true"
											/>
										</span>
										<span>{ checklistDescriptionStatus }</span>
									</p>
									<p className={ buildCheckClass( titleHasFocus ? 'good' : 'bad' ) }>
										<span className="airygen-preview-check__icon">
											<span
												className={ `dashicons ${ titleHasFocus ? 'dashicons-yes' : 'dashicons-no-alt' }` }
												aria-hidden="true"
											/>
										</span>
										<span>{ titleFocusMessage() }</span>
									</p>
									<p className={ buildCheckClass( descriptionHasFocus ? 'good' : 'bad' ) }>
										<span className="airygen-preview-check__icon">
											<span
												className={ `dashicons ${ descriptionHasFocus ? 'dashicons-yes' : 'dashicons-no-alt' }` }
												aria-hidden="true"
											/>
										</span>
										<span>{ descriptionFocusMessage() }</span>
									</p>
									{ keyphraseStacked && (
										<p className={ buildCheckClass( 'bad' ) }>
											<span className="airygen-preview-check__icon">
												<span className="dashicons dashicons-no-alt" aria-hidden="true" />
											</span>
											<span>{ __( 'Focus keyphrase appears too often.', 'airygen-seo' ) }</span>
										</p>
									) }
								</>
							) }
						</div>
					</>
				) }
				{ snippetSubTab === 'custom' && (
					<>
						<FieldGroup
							label={ __( 'Meta Title', 'airygen-seo' ) }
							id="airygen-meta-title"
						>
							<input
								type="text"
								className="airygen-classic-input"
								value={ title }
								id="airygen-meta-title"
								onChange={ ( event: ChangeEvent<HTMLInputElement> ) =>
									setTitle( event.target.value )
								}
							/>
							<span className="airygen-field-helper">
								{ __( 'Customize how your page title appears in search.', 'airygen-seo' ) }
							</span>
							{ renderBar( titlePixels, 580, titleBarStatus ) }
							<span className="airygen-field-helper">
								{ __( 'Current length:', 'airygen-seo' ) } { titlePixelsRounded }px
							</span>
							{ titleStatusText && (
								<span className={ `airygen-field-helper airygen-field-helper--${ titleBarStatus }` }>
									{ titleStatusText }
								</span>
							) }
						</FieldGroup>
						<FieldGroup
							label={ __( 'Meta Description', 'airygen-seo' ) }
							id="airygen-meta-description"
						>
							<textarea
								className="airygen-classic-textarea"
								value={ description }
								id="airygen-meta-description"
								onChange={ ( event: ChangeEvent<HTMLTextAreaElement> ) =>
									setDescription( event.target.value )
								}
								rows={ 3 }
							/>
							<span className="airygen-field-helper">
								{ __( 'Summarize the page in 160 characters or less (~920px).', 'airygen-seo' ) }
							</span>
							{ renderBar( descriptionPixels, 920, descriptionBarStatus ) }
							<span className="airygen-field-helper">
								{ __( 'Current length:', 'airygen-seo' ) } { descriptionPixelsRounded }px
							</span>
							{ descriptionStatusText && (
								<span className={ `airygen-field-helper airygen-field-helper--${ descriptionBarStatus }` }>
									{ descriptionStatusText }
								</span>
							) }
						</FieldGroup>
					</>
				) }
			</div>
		</>
	);
};

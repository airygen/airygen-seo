import { __ } from '@wordpress/i18n';
import type { ChangeEvent, ReactNode } from 'react';
import { decodeUrlPreview } from '../../../shared/urlPreview';

type FieldGroupComponent = ( props: {
	label: string;
	description?: string;
	id: string;
	children: ReactNode;
} ) => JSX.Element;

type CanonicalPanelProps = {
	canonicalSubTab: 'preview' | 'custom';
	setCanonicalSubTab: ( tab: 'preview' | 'custom' ) => void;
	effectiveCanonical: string;
	canonicalChoice: 'default' | 'custom';
	setCanonicalChoice: ( value: 'default' | 'custom' ) => void;
	canonical: string;
	handleCanonicalChange: ( value: string ) => void;
	hasCustomCanonical: boolean;
	setCanonical: ( value: string ) => void;
	canonicalError: string;
	FieldGroup: FieldGroupComponent;
};

export const CanonicalPanel = ( {
	canonicalSubTab,
	setCanonicalSubTab,
	effectiveCanonical,
	canonicalChoice,
	setCanonicalChoice,
	canonical,
	handleCanonicalChange,
	hasCustomCanonical,
	setCanonical,
	canonicalError,
	FieldGroup,
}: CanonicalPanelProps ) => (
	<>
		<div className="airygen-panel-tabs">
			<button
				type="button"
				className={
					canonicalSubTab === 'preview'
						? 'airygen-tab-panel-button is-primary'
						: 'airygen-tab-panel-button is-secondary'
				}
				onClick={ () => setCanonicalSubTab( 'preview' ) }
			>
				{ __( 'Preview', 'airygen-seo' ) }
			</button>
			<button
				type="button"
				className={
					canonicalSubTab === 'custom'
						? 'airygen-tab-panel-button is-primary'
						: 'airygen-tab-panel-button is-secondary'
				}
				onClick={ () => setCanonicalSubTab( 'custom' ) }
			>
				{ __( 'Custom', 'airygen-seo' ) }
			</button>
		</div>
		<div className="airygen-panel-container">
			{ canonicalSubTab === 'preview' && (
				<>
					<div className="airygen-snippet-preview">
						<p className="airygen-snippet-preview__url" style={ { marginBottom: 0 } }>
							{ effectiveCanonical
								? decodeUrlPreview( effectiveCanonical )
								: __( 'Permalink not available yet', 'airygen-seo' ) }
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
									name="airygen_canonical_source"
									value="default"
									checked={ canonicalChoice === 'default' }
									onChange={ () => setCanonicalChoice( 'default' ) }
								/>
								<span>{ __( 'Use defaults', 'airygen-seo' ) }</span>
							</span>
							<span className="airygen-classic-option">
								<input
									type="radio"
									name="airygen_canonical_source"
									value="custom"
									checked={ canonicalChoice === 'custom' }
									onChange={ () => setCanonicalChoice( 'custom' ) }
								/>
								<span>{ __( 'Use custom data', 'airygen-seo' ) }</span>
							</span>
						</div>
					</div>
					<p className="airygen-field-helper">
						{ canonicalChoice === 'custom'
							? __( 'Using a custom canonical.', 'airygen-seo' )
							: __( 'Using the default permalink as canonical.', 'airygen-seo' ) }
					</p>
				</>
			) }
			{ canonicalSubTab === 'custom' && (
				<FieldGroup label={ __( 'Canonical URL', 'airygen-seo' ) } id="airygen-canonical">
					<div className="airygen-classic-inline-row">
						<input
							type="url"
							className="airygen-classic-input"
							value={ canonical }
							id="airygen-canonical"
							onChange={ ( event: ChangeEvent<HTMLInputElement> ) =>
								handleCanonicalChange( event.target.value )
							}
						/>
						<button
							type="button"
							className="airygen-component-button is-secondary"
							onClick={ () => setCanonical( '' ) }
							disabled={ ! hasCustomCanonical }
						>
							{ __( 'Clear', 'airygen-seo' ) }
						</button>
					</div>
					<span className="airygen-field-helper">
						{ canonicalError ||
							__( 'Leave blank to use the permalink WordPress generates.', 'airygen-seo' ) }
					</span>
				</FieldGroup>
			) }
		</div>
	</>
);

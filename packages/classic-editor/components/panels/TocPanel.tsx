import { __ } from '@wordpress/i18n';

type TocPanelProps = {
	tocMode: string;
	setTocMode: ( value: string ) => void;
	insertShortcode: ( value: string ) => void;
};

export const TocPanel = ( { tocMode, setTocMode, insertShortcode }: TocPanelProps ) => (
	<>
		<div className="airygen-panel-tabs">
			<button type="button" className="airygen-tab-panel-button is-primary">
				{ __( 'Settings', 'airygen-seo' ) }
			</button>
		</div>
		<div className="airygen-panel-container">
			<div className="airygen-classic-field">
				<label className="airygen-classic-label-text" htmlFor="airygen-toc-mode">
					{ __( 'Display mode', 'airygen-seo' ) }
				</label>
				<select
					className="airygen-classic-input"
					id="airygen-toc-mode"
					value={ tocMode }
					onChange={ ( event ) => setTocMode( event.target.value ) }
				>
					<option value="auto">{ __( 'Auto', 'airygen-seo' ) }</option>
					<option value="manual">{ __( 'Manual', 'airygen-seo' ) }</option>
					<option value="disabled">{ __( 'Disabled', 'airygen-seo' ) }</option>
				</select>
				<span className="airygen-field-helper">
					{ __( 'Choose how the Table of Contents appears for this post.', 'airygen-seo' ) }
				</span>
			</div>
			{ tocMode === 'manual' ? (
				<div className="airygen-ai-card__actions-row">
					<button
						type="button"
						className="airygen-component-button is-secondary airygen-ai-card__shortcode"
						onClick={ () => insertShortcode( '[airygen_toc]' ) }
					>
						{ __( 'Shortcode', 'airygen-seo' ) }
					</button>
				</div>
			) : null }
		</div>
	</>
);

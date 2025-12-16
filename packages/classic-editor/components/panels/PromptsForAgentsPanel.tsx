import { __ } from '@wordpress/i18n';

type PromptsForAgentsPanelProps = {
	value: string;
	onChange: ( next: string ) => void;
};

const PromptsForAgentsPanel = ( { value, onChange }: PromptsForAgentsPanelProps ) => (
	<div className="airygen-classic-subpanel">
		<div className="airygen-classic-field">
			<label className="airygen-classic-label" htmlFor="airygen-agent-prompt">
				<span className="airygen-classic-label-text">{ __( 'Prompts for Agents', 'airygen-seo' ) }</span>
			</label>
			<textarea
				id="airygen-agent-prompt"
				className="airygen-classic-textarea"
				rows={ 8 }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
			<p className="airygen-classic-label-helper">{ __( 'Add descriptions, instructions, or prompts for LLM agents.', 'airygen-seo' ) }</p>
		</div>
	</div>
);

export default PromptsForAgentsPanel;

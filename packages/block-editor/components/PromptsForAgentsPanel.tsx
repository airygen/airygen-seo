import { __ } from '@wordpress/i18n';
import { TextareaControl } from '@wordpress/components';
import usePostDataField from '../hooks/usePostDataField';

const PromptsForAgentsPanel = () => {
	const [ prompt, setPrompt ] = usePostDataField( 'agentPrompt' );

	return (
		<div className="airygen-panel-group">
			<TextareaControl
				label={ __( 'Prompts for Agents', 'airygen-seo' ) }
				help={ __( 'Add descriptions, instructions, or prompts for LLM agents.', 'airygen-seo' ) }
				value={ prompt ?? '' }
				onChange={ ( value ) => setPrompt( value ) }
				rows={ 8 }
			/>
		</div>
	);
};

export default PromptsForAgentsPanel;

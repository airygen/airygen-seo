import CodeSnippetManagerTab from '../tabs/CodeSnippetManagerTab';
import type { SettingsState } from '../../../types/settings';

type CodeSnippetManagerTabPanelProps = {
	settings: SettingsState['codeSnippetManager'];
	onChange: ( value: SettingsState['codeSnippetManager'] ) => void;
};

const CodeSnippetManagerTabPanel = ( {
	settings,
	onChange,
}: CodeSnippetManagerTabPanelProps ) => (
	<CodeSnippetManagerTab settings={ settings } onChange={ onChange } />
);

export default CodeSnippetManagerTabPanel;

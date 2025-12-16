import MarkdownForAgentsTab from '../tabs/MarkdownForAgentsTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type MarkdownForAgentsTabPanelProps = {
	settings: SettingsState['markdownForAgents'];
	meta: MetaPayload;
	restBase: string;
	onChange: ( value: SettingsState['markdownForAgents'] ) => void;
};

const MarkdownForAgentsTabPanel = ( {
	settings,
	meta,
	restBase,
	onChange,
}: MarkdownForAgentsTabPanelProps ) => (
	<MarkdownForAgentsTab
		settings={ settings }
		meta={ meta }
		restBase={ restBase }
		onChange={ onChange }
	/>
);

export default MarkdownForAgentsTabPanel;

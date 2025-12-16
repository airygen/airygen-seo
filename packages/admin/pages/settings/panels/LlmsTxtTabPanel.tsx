import LlmsTxtTab from '../tabs/LlmsTxtTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type LlmsTxtTabPanelProps = {
	settings: SettingsState['llmsTxt'];
	meta: MetaPayload;
	restBase: string;
	topicClusterEnabled: boolean;
	markdownForAgentsEnabled: boolean;
	onChange: ( value: SettingsState['llmsTxt'] ) => void;
};

const LlmsTxtTabPanel = ( {
	settings,
	meta,
	restBase,
	topicClusterEnabled,
	markdownForAgentsEnabled,
	onChange,
}: LlmsTxtTabPanelProps ) => (
	<LlmsTxtTab
		settings={ settings }
		meta={ meta }
		restBase={ restBase }
		topicClusterEnabled={ topicClusterEnabled }
		markdownForAgentsEnabled={ markdownForAgentsEnabled }
		onChange={ onChange }
	/>
);

export default LlmsTxtTabPanel;

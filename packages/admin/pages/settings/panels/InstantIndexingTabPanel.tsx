import InstantIndexingTab from '../tabs/InstantIndexingTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type InstantIndexingTabPanelProps = {
	settings: SettingsState['instantIndexing'];
	meta: MetaPayload;
	restBase: string;
	actionSchedulerAvailable?: boolean;
	onChange: ( value: SettingsState['instantIndexing'] ) => void;
};

const InstantIndexingTabPanel = ( {
	settings,
	meta,
	restBase,
	actionSchedulerAvailable,
	onChange,
}: InstantIndexingTabPanelProps ) => (
	<InstantIndexingTab
		settings={ settings }
		meta={ meta }
		restBase={ restBase }
		actionSchedulerAvailable={ actionSchedulerAvailable }
		onChange={ onChange }
	/>
);

export default InstantIndexingTabPanel;

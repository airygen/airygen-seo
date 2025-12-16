import BrokenLinkCheckerTab from '../tabs/BrokenLinkCheckerTab';
import type {
	BrokenLinkCheckerStatusMeta,
	BrokenLinkCheckerSettings,
	SettingsState,
} from '../../../types/settings';

type BrokenLinkCheckerTabPanelProps = {
	settings: SettingsState['brokenLinkChecker'];
	restBase: string;
	linkCounterEnabled: boolean;
	actionSchedulerAvailable?: boolean;
	status?: BrokenLinkCheckerStatusMeta;
	defaults: BrokenLinkCheckerSettings;
	onChange: ( value: SettingsState['brokenLinkChecker'] ) => void;
};

const BrokenLinkCheckerTabPanel = ( {
	settings,
	restBase,
	linkCounterEnabled,
	actionSchedulerAvailable,
	status,
	defaults,
	onChange,
}: BrokenLinkCheckerTabPanelProps ) => (
	<BrokenLinkCheckerTab
		settings={ settings }
		restBase={ restBase }
		linkCounterEnabled={ linkCounterEnabled }
		actionSchedulerAvailable={ actionSchedulerAvailable }
		status={ status }
		defaults={ defaults }
		onChange={ onChange }
	/>
);

export default BrokenLinkCheckerTabPanel;

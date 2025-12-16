import RobotsTab from '../tabs/RobotsTab';
import type { SettingsState } from '../../../types/settings';

type RobotsTabPanelProps = {
	settings: SettingsState['robots'];
	robotsPreviewUrl: string;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
	onChange: ( value: SettingsState['robots'] ) => void;
};

const RobotsTabPanel = ( {
	settings,
	robotsPreviewUrl,
	onCopyToClipboard,
	onChange,
}: RobotsTabPanelProps ) => (
	<RobotsTab
		settings={ settings }
		robotsPreviewUrl={ robotsPreviewUrl }
		onCopyToClipboard={ onCopyToClipboard }
		onChange={ onChange }
	/>
);

export default RobotsTabPanel;

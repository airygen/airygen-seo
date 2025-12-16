import NotifyTab from '../tabs/NotifyTab';
import type { NoticeState } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type NotifyTabPanelProps = {
	settings: SettingsState['notify'];
	onChange: ( value: SettingsState['notify'] ) => void;
	restBase: string;
	onNotice: ( notice: NoticeState ) => void;
};

const NotifyTabPanel = ( {
	settings,
	onChange,
	restBase,
	onNotice,
}: NotifyTabPanelProps ) => (
	<NotifyTab
		settings={ settings }
		onChange={ onChange }
		restBase={ restBase }
		onNotice={ onNotice }
	/>
);

export default NotifyTabPanel;

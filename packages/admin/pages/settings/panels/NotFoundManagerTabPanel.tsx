import NotFoundManagerTab from '../tabs/NotFoundManagerTab';
import type { NoticeState } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type NotFoundManagerTabPanelProps = {
	settings: SettingsState['notFoundManager'];
	onChange: ( value: SettingsState['notFoundManager'] ) => void;
	restBase: string;
	onNotice: ( notice: NoticeState ) => void;
};

const NotFoundManagerTabPanel = ( {
	settings,
	onChange,
	restBase,
	onNotice,
}: NotFoundManagerTabPanelProps ) => (
	<NotFoundManagerTab
		settings={ settings }
		onChange={ onChange }
		restBase={ restBase }
		onNotice={ onNotice }
	/>
);

export default NotFoundManagerTabPanel;

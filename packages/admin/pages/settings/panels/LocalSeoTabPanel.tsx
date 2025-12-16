import LocalSeoTab from '../tabs/LocalSeoTab';
import type { SettingsState } from '../../../types/settings';

type LocalSeoTabPanelProps = {
	settings: SettingsState['localSeo'];
	onChange: ( value: SettingsState['localSeo'] ) => void;
};

const LocalSeoTabPanel = ( { settings, onChange }: LocalSeoTabPanelProps ) => (
	<LocalSeoTab settings={ settings } onChange={ onChange } />
);

export default LocalSeoTabPanel;


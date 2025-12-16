import SocialTab from '../tabs/SocialTab';
import type { SettingsState } from '../../../types/settings';

type SocialTabPanelProps = {
	settings: SettingsState['socialCards'];
	onChange: ( value: SettingsState['socialCards'] ) => void;
};

const SocialTabPanel = ( {
	settings,
	onChange,
}: SocialTabPanelProps ) => (
	<SocialTab settings={ settings } onChange={ onChange } />
);

export default SocialTabPanel;

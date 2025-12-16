import RssFeedSignatureTab from '../tabs/RssFeedSignatureTab';
import type { SettingsState } from '../../../types/settings';

type RssFeedSignatureTabPanelProps = {
	settings: SettingsState['rssFeedSignature'];
	onChange: ( value: SettingsState['rssFeedSignature'] ) => void;
};

const RssFeedSignatureTabPanel = ( {
	settings,
	onChange,
}: RssFeedSignatureTabPanelProps ) => (
	<RssFeedSignatureTab settings={ settings } onChange={ onChange } />
);

export default RssFeedSignatureTabPanel;

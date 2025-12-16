import SiteVerificationTab from '../tabs/SiteVerificationTab';
import type { SettingsState } from '../../../types/settings';

type SiteVerificationTabPanelProps = {
	settings: SettingsState['siteVerification'];
	onChange: ( value: SettingsState['siteVerification'] ) => void;
};

const SiteVerificationTabPanel = ( {
	settings,
	onChange,
}: SiteVerificationTabPanelProps ) => (
	<SiteVerificationTab settings={ settings } onChange={ onChange } />
);

export default SiteVerificationTabPanel;

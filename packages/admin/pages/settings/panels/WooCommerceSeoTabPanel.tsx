import WooCommerceSeoTab from '../tabs/WooCommerceSeoTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type WooCommerceSeoTabPanelProps = {
	settings: SettingsState['wooCommerceSeo'];
	meta: MetaPayload;
	onChange: ( value: SettingsState['wooCommerceSeo'] ) => void;
};

const WooCommerceSeoTabPanel = ( {
	settings,
	meta,
	onChange,
}: WooCommerceSeoTabPanelProps ) => (
	<WooCommerceSeoTab settings={ settings } meta={ meta } onChange={ onChange } />
);

export default WooCommerceSeoTabPanel;

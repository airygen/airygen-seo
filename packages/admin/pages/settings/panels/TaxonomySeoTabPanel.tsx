import TaxonomySeoTab from '../tabs/TaxonomySeoTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type TaxonomySeoTabPanelProps = {
	settings: SettingsState['taxonomySeo'];
	meta: MetaPayload;
	onChange: ( value: SettingsState['taxonomySeo'] ) => void;
};

const TaxonomySeoTabPanel = ( {
	settings,
	meta,
	onChange,
}: TaxonomySeoTabPanelProps ) => (
	<TaxonomySeoTab settings={ settings } meta={ meta } onChange={ onChange } />
);

export default TaxonomySeoTabPanel;


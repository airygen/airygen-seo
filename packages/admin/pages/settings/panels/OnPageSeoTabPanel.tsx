import OnPageSeoTab from '../tabs/OnPageSeoTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type OnPageSeoTabPanelProps = {
	settings: SettingsState['onPageSeo'];
	meta: MetaPayload;
	onChange: ( value: SettingsState['onPageSeo'] ) => void;
};

const OnPageSeoTabPanel = ( {
	settings,
	meta,
	onChange,
}: OnPageSeoTabPanelProps ) => (
	<OnPageSeoTab
		settings={ settings }
		postTypes={ meta.postTypes }
		onChange={ onChange }
	/>
);

export default OnPageSeoTabPanel;

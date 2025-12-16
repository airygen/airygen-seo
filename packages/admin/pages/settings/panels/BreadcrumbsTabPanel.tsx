import BreadcrumbsTab from '../tabs/BreadcrumbsTab';
import type { SettingsState } from '../../../types/settings';

type BreadcrumbsTabPanelProps = {
	settings: SettingsState['breadcrumbs'];
	onChange: ( value: SettingsState['breadcrumbs'] ) => void;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
};

const BreadcrumbsTabPanel = ( {
	settings,
	onChange,
	onCopyToClipboard,
}: BreadcrumbsTabPanelProps ) => (
	<BreadcrumbsTab
		settings={ settings }
		onChange={ onChange }
		onCopyToClipboard={ onCopyToClipboard }
	/>
);

export default BreadcrumbsTabPanel;

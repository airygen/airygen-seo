import RedirectsTab from '../tabs/RedirectsTab';
import type { MetaPayload } from '../../../types/api';
import type { RedirectRule, SettingsState } from '../../../types/settings';

type RedirectsTabPanelProps = {
	settings: SettingsState['redirects'];
	meta: MetaPayload;
	onRemoveRule: ( id: string ) => Promise<void>;
	onUpdateRule: ( id: string, patch: Partial<RedirectRule> ) => Promise<void>;
	onCreateRule: ( rule: Omit<RedirectRule, 'id'> ) => Promise<void>;
};

const RedirectsTabPanel = ( {
	settings,
	meta,
	onRemoveRule,
	onUpdateRule,
	onCreateRule,
}: RedirectsTabPanelProps ) => (
	<RedirectsTab
		settings={ settings }
		meta={ meta }
		onRemoveRule={ onRemoveRule }
		onUpdateRule={ onUpdateRule }
		onCreateRule={ onCreateRule }
	/>
);

export default RedirectsTabPanel;

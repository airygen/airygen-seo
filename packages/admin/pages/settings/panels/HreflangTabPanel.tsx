import HreflangTab from '../tabs/HreflangTab';
import type {
	HreflangEntry,
	SettingsState,
} from '../../../types/settings';

type HreflangTabPanelProps = {
	settings: SettingsState['hreflang'];
	onUpdateEntry: ( index: number, patch: Partial<HreflangEntry> ) => void;
	onRemoveEntry: ( index: number ) => void;
	onAddEntry: () => void;
	onIncludeDefaultChange: ( include: boolean ) => void;
};

const HreflangTabPanel = ( {
	settings,
	onUpdateEntry,
	onRemoveEntry,
	onAddEntry,
	onIncludeDefaultChange,
}: HreflangTabPanelProps ) => (
	<HreflangTab
		settings={ settings }
		onUpdateEntry={ onUpdateEntry }
		onRemoveEntry={ onRemoveEntry }
		onAddEntry={ onAddEntry }
		onIncludeDefaultChange={ onIncludeDefaultChange }
	/>
);

export default HreflangTabPanel;

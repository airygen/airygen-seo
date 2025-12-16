import ScoreCalculatorTab from '../tabs/ScoreCalculatorTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type ScoreCalculatorTabPanelProps = {
	settings: SettingsState['scoreCalculator'];
	meta: MetaPayload;
	restBase: string;
	onChange: ( value: SettingsState['scoreCalculator'] ) => void;
};

const ScoreCalculatorTabPanel = ( {
	settings,
	meta,
	restBase,
	onChange,
}: ScoreCalculatorTabPanelProps ) => (
	<ScoreCalculatorTab
		settings={ settings }
		meta={ meta }
		restBase={ restBase }
		onChange={ onChange }
	/>
);

export default ScoreCalculatorTabPanel;

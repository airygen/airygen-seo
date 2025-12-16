import TocTab from '../tabs/TocTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type TocTabPanelProps = {
	settings: SettingsState['toc'];
	meta: MetaPayload;
	onChange: ( value: SettingsState['toc'] ) => void;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
};

const TocTabPanel = ( { settings, meta, onChange, onCopyToClipboard }: TocTabPanelProps ) => (
	<TocTab settings={ settings } meta={ meta } onChange={ onChange } onCopyToClipboard={ onCopyToClipboard } />
);

export default TocTabPanel;

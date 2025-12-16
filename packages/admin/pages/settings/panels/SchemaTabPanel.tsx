import SchemaTab from '../tabs/SchemaTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type SchemaTabPanelProps = {
	settings: SettingsState['schemaMarkup'];
	meta: MetaPayload;
	onChange: ( value: SettingsState['schemaMarkup'] ) => void;
};

const SchemaTabPanel = ( {
	settings,
	meta,
	onChange,
}: SchemaTabPanelProps ) => (
	<SchemaTab settings={ settings } meta={ meta } onChange={ onChange } />
);

export default SchemaTabPanel;

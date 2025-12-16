import AuthorSeoTab from '../tabs/AuthorSeoTab';
import type { SettingsState } from '../../../types/settings';

type AuthorSeoTabPanelProps = {
	settings: SettingsState['authorSeo'];
	onChange: ( value: SettingsState['authorSeo'] ) => void;
};

const AuthorSeoTabPanel = ( { settings, onChange }: AuthorSeoTabPanelProps ) => (
	<AuthorSeoTab settings={ settings } onChange={ onChange } />
);

export default AuthorSeoTabPanel;


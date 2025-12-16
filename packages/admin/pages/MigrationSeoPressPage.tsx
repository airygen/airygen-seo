import { __ } from '@wordpress/i18n';
import SettingsPage from './SettingsPage';
import SeoPressMigrationPanel from './migration/SeoPressMigrationPanel';
import { getNoMigrationToolsAvailableYetLabel } from '../../shared/i18nPhrases';

type MigrationSeoPressPageProps = {
	restBase: string;
	isActive: boolean;
};

const MigrationSeoPressPage = ( { restBase, isActive }: MigrationSeoPressPageProps ) => (
	<SettingsPage
		panel={ <SeoPressMigrationPanel restBase={ restBase } isActive={ isActive } /> }
		isDirty={ false }
		isSaving={ false }
		onRetry={ () => {} }
		onSave={ () => {} }
		showFooter={ false }
		emptyMessage={ getNoMigrationToolsAvailableYetLabel() }
	/>
);

export default MigrationSeoPressPage;

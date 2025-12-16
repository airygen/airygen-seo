import { __ } from '@wordpress/i18n';
import SettingsPage from './SettingsPage';
import AioseoMigrationPanel from './migration/AioseoMigrationPanel';
import { getNoMigrationToolsAvailableYetLabel } from '../../shared/i18nPhrases';

type MigrationAioseoPageProps = {
	restBase: string;
	isActive: boolean;
};

const MigrationAioseoPage = ( { restBase, isActive }: MigrationAioseoPageProps ) => (
	<SettingsPage
		panel={ <AioseoMigrationPanel restBase={ restBase } isActive={ isActive } /> }
		isDirty={ false }
		isSaving={ false }
		onRetry={ () => {} }
		onSave={ () => {} }
		showFooter={ false }
		emptyMessage={ getNoMigrationToolsAvailableYetLabel() }
	/>
);

export default MigrationAioseoPage;

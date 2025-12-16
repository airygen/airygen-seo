import { __ } from '@wordpress/i18n';
import SettingsPage from './SettingsPage';
import YoastMigrationPanel from './migration/YoastMigrationPanel';
import { getNoMigrationToolsAvailableYetLabel } from '../../shared/i18nPhrases';

type MigrationYoastPageProps = {
	restBase: string;
	isActive: boolean;
};

const MigrationYoastPage = ( { restBase, isActive }: MigrationYoastPageProps ) => (
	<SettingsPage
		panel={ <YoastMigrationPanel restBase={ restBase } isActive={ isActive } /> }
		isDirty={ false }
		isSaving={ false }
		onRetry={ () => {} }
		onSave={ () => {} }
		showFooter={ false }
		emptyMessage={ getNoMigrationToolsAvailableYetLabel() }
	/>
);

export default MigrationYoastPage;

import { __ } from '@wordpress/i18n';
import SettingsPage from './SettingsPage';
import RankMathMigrationPanel from './migration/RankMathMigrationPanel';
import { getNoMigrationToolsAvailableYetLabel } from '../../shared/i18nPhrases';

type MigrationRankMathPageProps = {
	restBase: string;
	isActive: boolean;
};

const MigrationRankMathPage = ( { restBase, isActive }: MigrationRankMathPageProps ) => (
	<SettingsPage
		panel={ <RankMathMigrationPanel restBase={ restBase } isActive={ isActive } /> }
		isDirty={ false }
		isSaving={ false }
		onRetry={ () => {} }
		onSave={ () => {} }
		showFooter={ false }
		emptyMessage={ getNoMigrationToolsAvailableYetLabel() }
	/>
);

export default MigrationRankMathPage;

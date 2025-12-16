import LinkSuggestionsTab from '../tabs/LinkSuggestionsTab';
import type { MetaPayload, NoticeState } from '../../../types/api';

type LinkSuggestionsTabPanelProps = {
	restBase: string;
	meta: MetaPayload;
	actionSchedulerAvailable?: boolean;
	onNotice?: ( notice: NoticeState ) => void;
	onDirtyChange: ( dirty: boolean ) => void;
	registerSubmit: ( submit: () => Promise<void> ) => void;
	isSaving: boolean;
};

const LinkSuggestionsTabPanel = ( {
	restBase,
	meta,
	actionSchedulerAvailable,
	onNotice,
	onDirtyChange,
	registerSubmit,
	isSaving,
}: LinkSuggestionsTabPanelProps ) => (
	<LinkSuggestionsTab
		restBase={ restBase }
		meta={ meta }
		actionSchedulerAvailable={ actionSchedulerAvailable }
		onNotice={ onNotice }
		onDirtyChange={ onDirtyChange }
		registerSubmit={ registerSubmit }
		isSaving={ isSaving }
	/>
);

export default LinkSuggestionsTabPanel;

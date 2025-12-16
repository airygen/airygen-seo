import RelatedPostsTab from '../tabs/RelatedPostsTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type RelatedPostsTabPanelProps = {
	settings: SettingsState['relatedPosts'];
	meta: MetaPayload;
	onChange: ( value: SettingsState['relatedPosts'] ) => void;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
};

const RelatedPostsTabPanel = ( {
	settings,
	meta,
	onChange,
	onCopyToClipboard,
}: RelatedPostsTabPanelProps ) => (
	<RelatedPostsTab
		settings={ settings }
		meta={ meta }
		onChange={ onChange }
		onCopyToClipboard={ onCopyToClipboard }
	/>
);

export default RelatedPostsTabPanel;

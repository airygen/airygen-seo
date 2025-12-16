import SitemapTab from '../tabs/SitemapTab';
import type { MetaPayload } from '../../../types/api';
import type { SettingsState } from '../../../types/settings';

type SitemapTabPanelProps = {
	settings: SettingsState['sitemap'];
	meta: MetaPayload;
	sitemapPreviewUrl: string;
	onCopyPreviewLink: () => void;
	onChange: ( value: SettingsState['sitemap'] ) => void;
};

const SitemapTabPanel = ( {
	settings,
	meta,
	sitemapPreviewUrl,
	onCopyPreviewLink,
	onChange,
}: SitemapTabPanelProps ) => (
	<SitemapTab
		settings={ settings }
		meta={ meta }
		sitemapPreviewUrl={ sitemapPreviewUrl }
		onCopyPreviewLink={ onCopyPreviewLink }
		onChange={ onChange }
	/>
);

export default SitemapTabPanel;

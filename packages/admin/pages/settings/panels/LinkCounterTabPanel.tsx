import LinkCounterTab from '../tabs/LinkCounterTab';
import type { MetaPayload } from '../../../types/api';

type LinkCounterTabPanelProps = {
	meta: MetaPayload;
	restBase: string;
};

const LinkCounterTabPanel = ( {
	meta,
	restBase,
}: LinkCounterTabPanelProps ) => (
	<LinkCounterTab
		restBase={ restBase }
		initialStatus={ meta.linkCounter?.status ?? null }
	/>
);

export default LinkCounterTabPanel;

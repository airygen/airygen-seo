import SiteHealthTab from '../tabs/SiteHealthTab';

type SiteHealthTabPanelProps = {
	restBase: string;
};

const SiteHealthTabPanel = ( { restBase }: SiteHealthTabPanelProps ) => (
	<SiteHealthTab restBase={ restBase } />
);

export default SiteHealthTabPanel;

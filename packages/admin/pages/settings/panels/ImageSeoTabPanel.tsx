import ImageSeoTab from '../tabs/ImageSeoTab';
import type { SettingsState } from '../../../types/settings';

type ImageSeoTabPanelProps = {
	settings: SettingsState['imageSeo'];
	onChange: ( value: SettingsState['imageSeo'] ) => void;
};

const ImageSeoTabPanel = ( {
	settings,
	onChange,
}: ImageSeoTabPanelProps ) => (
	<ImageSeoTab settings={ settings } onChange={ onChange } />
);

export default ImageSeoTabPanel;

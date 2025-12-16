import type { EditorConfig, MetaKeys } from './types';

const DEFAULT_META_KEYS: MetaKeys = {
	postData: '_airygen_post_data',
	outputModes: '_airygen_output_modes',
};

declare global {
	interface Window {
		AirygenEditor?: EditorConfig;
	}
}

export const getEditorConfig = (): EditorConfig =>
	window.AirygenEditor ?? {};

export const getMetaKeys = (): MetaKeys => {
	const config = getEditorConfig();
	if ( config.metaKeys ) {
		return {
			postData: config.metaKeys.postData ?? DEFAULT_META_KEYS.postData,
			outputModes:
				config.metaKeys.outputModes ?? DEFAULT_META_KEYS.outputModes,
		};
	}

	return DEFAULT_META_KEYS;
};

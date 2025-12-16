import type {
	BrokenLinkCheckerStatusMeta,
	LinkCounterStatusMeta,
	NotifyStatusMeta,
	ScoreCalculatorMeta,
} from './settings';

// Supplementary data returned alongside the settings payload so the UI can
// populate dropdowns, radio groups, and status widgets across each tab.
export type MetaPayload = {
	postTypes: Array<{ slug: string; label: string }>;
	taxonomies: Array<{ slug: string; label: string }>;
	organizationTypes: string[];
	articleTypes: string[];
	schemaPostTypes: Array<{
		slug: string;
		key: string;
		label: string;
		options: Array<{ value: string; label: string }>;
		selected: string;
	}>;
	redirectStatuses: Array<{ value: number; label: string }>;
	redirectTypes: Array<{ value: string; label: string }>;
	linkCounter?: {
		status: LinkCounterStatusMeta;
	};
	brokenLinkChecker?: {
		status: BrokenLinkCheckerStatusMeta;
	};
	notify?: {
		status: NotifyStatusMeta;
	};
	instantIndexing?: {
		engines: Array<{ slug: string; label: string; endpoint: string }>;
	};
	scoreCalculator: ScoreCalculatorMeta;
	tocPreviewUrl?: string;
	faqPreviewUrl?: string;
	topicPreviewUrl?: string;
	llmsBasePath?: string;
	wooCommerce?: {
		active: boolean;
	};
	mediaImageSizes?: Array<{ slug: string; label: string; width?: number; height?: number }>;
};

export type NoticeState = {
	status: 'success' | 'error';
	message: string;
};

export type ApiResponse = {
	settings: Record<string, unknown>;
	meta: MetaPayload;
	wizardDismissed: boolean;
};

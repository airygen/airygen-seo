export type RelatedSettings = {
	enabled: boolean;
	allowed_post_types: string[];
	max_suggestions: number;
};

export type RelatedIndexStats = {
	post_type: string;
	label: string;
	indexed: number;
	not_indexed: number;
	total: number;
};

export type RelatedSettingsResponse = RelatedSettings & {
	stats?: RelatedIndexStats[];
};

export type RelatedSuggestionsResponse = {
	suggestions: Array<{
		id: number;
		title: string;
		permalink: string;
		post_type: string;
		score: number;
	}>;
	meta: Record<string, unknown>;
};

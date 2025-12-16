export type MetaKeys = {
	postData: string;
	outputModes?: string;
};

export type ScoreApiConfig = {
	root: string;
	nonce: string;
	method?: string;
	version?: string;
	pack?: string;
	language?: string;
};

export type RobotsSettings = {
	default_directive?: string;
};

export type SchemaVisibility = {
	organization?: boolean;
	website?: boolean;
	breadcrumb?: boolean;
	article?: boolean;
};

export type SchemaMarkupConfig = {
	organization_name?: string;
	organization_type?: string;
	organization_logo_url?: string;
	article_type?: string;
	article_show_author?: boolean;
	post_type_defaults?: Record< string, string >;
	visibility?: SchemaVisibility;
};

export type OnPageTemplates = {
	global?: {
		title?: string;
		description?: string;
	};
	separator?: string;
	post_types?: Record<
		string,
		{
			title?: string;
			description?: string;
		}
	>;
};

export type OnPageConfig = {
	templates?: OnPageTemplates;
	site_name?: string;
	site_description?: string;
};

export type LinkSuggestionsApi = {
	root: string;
	nonce: string;
	method?: string;
};

export type LinkSuggestionsConfig = {
	enabled?: boolean;
	max?: number;
	api?: LinkSuggestionsApi;
};

export type EditorConfig = {
	mode?: 'block' | 'classic';
	currentBlogId?: number;
	modules?: Record<string, boolean>;
	restNonce?: string;
	sessionCheckUrl?: string;
	metaKeys?: Partial< MetaKeys >;
	panelOrder?: string[];
	panelVisibility?: Record<string, boolean>;
	scoreApi?: ScoreApiConfig;
	scoreCalculator?: {
		postTypes?: string[];
		scoreCache?: ScoreResponse | null;
	};
	linkSuggestions?: LinkSuggestionsConfig;
	topicCluster?: {
		list?: string;
		save?: string;
		summary?: string;
		mindMapUrl?: string;
		nonce?: string;
	};
	markdownForAgents?: {
		promptsForAgents?: boolean;
	};
	robots?: RobotsSettings;
	schemaMarkup?: SchemaMarkupConfig;
	onpage?: OnPageConfig;
};

export type ScoreBreakdown = {
	score: number;
	max: number;
	percentage?: number;
	rules?: unknown;
};

export type ScoreResponse = {
	post_id: number;
	total: ScoreBreakdown;
	base: ScoreBreakdown;
	bonus: ScoreBreakdown;
	version: string;
	pack: string;
	language: string;
};

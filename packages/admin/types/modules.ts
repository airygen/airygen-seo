import type { ComponentType } from 'react';
import type { IconProps } from '../components/Icons';

export type ModuleKey =
	| 'onPageSeo'
	| 'social'
	| 'schema'
	| 'breadcrumbs'
	| 'robots'
	| 'toc'
	| 'imageSeo'
	| 'hreflang'
	| 'sitemap'
	| 'codeSnippetManager'
	| 'siteVerification'
	| 'rssFeedSignature'
	| 'siteHealth'
	| 'linkCounter'
	| 'scoreCalculator'
	| 'brokenLinkChecker'
	| 'redirects'
	| 'linkSuggestions'
	| 'instantIndexing'
	| 'topicCluster'
	| 'authorSeo'
	| 'taxonomySeo'
	| 'wooCommerceSeo'
	| 'localSeo'
	| 'relatedPosts'
	| 'notFoundManager'
	| 'notify'
	| 'markdownForAgents'
	| 'llmsTxt';

export type ModuleSettings = Record<ModuleKey, boolean>;

export type PanelKey =
	| 'serpSnippet'
	| 'keyphrases'
	| 'canonical'
	| 'robots'
	| 'scoreCalculator'
	| 'schemaMarkup'
	| 'linkSuggestions'
	| 'toc'
	| 'topicCluster'
	| 'promptsForAgents';

export type ModuleTraits = {
	background?: boolean;
	markup?: boolean;
	sidebar?: boolean;
	tool?: boolean;
};

export type ModuleMetadata = {
	key: ModuleKey;
	title: string;
	description: string;
	icon: ComponentType<IconProps>;
	hasSettings?: boolean;
	traits?: ModuleTraits;
};

export type PanelMetadata = {
	key: PanelKey;
	title: string;
	description: string;
	relatedModule: ModuleKey;
};

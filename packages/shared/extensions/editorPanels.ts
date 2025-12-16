import type { ComponentType } from 'react';

export type ClassicEditorMetaField =
	| 'title'
	| 'description'
	| 'keyphrase'
	| 'longTail'
	| 'agentPrompt'
	| 'canonical'
	| 'robots'
	| 'tocMode'
	| 'faqMode'
	| 'topicMode'
	| 'schemaType';

export type ClassicEditorExtensionProps = {
	editorConfig: Record<string, unknown>;
	postId: number;
	getMetaValue: ( field: ClassicEditorMetaField ) => string;
	setMetaValue: ( field: ClassicEditorMetaField, value: string ) => void;
	getPostTitle: () => string;
	setPostTitle: ( value: string ) => void;
	getExcerpt: () => string;
	setExcerpt: ( value: string ) => void;
	insertShortcode: ( value: string ) => void;
	getEditorContent: () => string;
	setEditorContent: ( value: string ) => void;
	markDirty: () => void;
	notifySuccess: ( message: string ) => void;
};

export type BlockEditorPanelRegistration = {
	key: string;
	title: string;
	render: ComponentType;
	order?: number;
	moduleKey?: string;
	visibilityKey?: string;
};

export type ClassicEditorTabRegistration = {
	key: string;
	title: string;
	render: ComponentType<ClassicEditorExtensionProps>;
	order?: number;
	moduleKey?: string;
	visibilityKey?: string;
};

type AirygenEditorExtensionRegistry = {
	version: string;
	registerBlockEditorPanel: ( panel: BlockEditorPanelRegistration ) => void;
	getBlockEditorPanels: () => BlockEditorPanelRegistration[];
	registerClassicEditorTab: ( tab: ClassicEditorTabRegistration ) => void;
	getClassicEditorTabs: () => ClassicEditorTabRegistration[];
};

declare global {
	interface Window {
		airygenEditorExtensions?: AirygenEditorExtensionRegistry;
	}
}

const API_VERSION = '1';
export const EDITOR_EXTENSIONS_UPDATED_EVENT = 'airygen:editor-extensions-updated';

const isBlockPanelRegistration = (
	panel: BlockEditorPanelRegistration | undefined,
): panel is BlockEditorPanelRegistration =>
	Boolean( panel?.key ) && typeof panel?.render === 'function';

const isClassicTabRegistration = (
	tab: ClassicEditorTabRegistration | undefined,
): tab is ClassicEditorTabRegistration =>
	Boolean( tab?.key ) && typeof tab?.render === 'function';

export const ensureAirygenEditorExtensions = (): AirygenEditorExtensionRegistry => {
	if ( typeof window === 'undefined' ) {
		return {
			version: API_VERSION,
			registerBlockEditorPanel: () => {},
			getBlockEditorPanels: () => [],
			registerClassicEditorTab: () => {},
			getClassicEditorTabs: () => [],
		};
	}

	if ( window.airygenEditorExtensions?.version === API_VERSION ) {
		return window.airygenEditorExtensions;
	}

	const blockPanels = new Map<string, BlockEditorPanelRegistration>();
	const classicTabs = new Map<string, ClassicEditorTabRegistration>();
	const registry: AirygenEditorExtensionRegistry = {
		version: API_VERSION,
		registerBlockEditorPanel: ( panel ) => {
			if ( ! isBlockPanelRegistration( panel ) ) {
				return;
			}

			blockPanels.set( panel.key, panel );
			window.dispatchEvent( new CustomEvent( EDITOR_EXTENSIONS_UPDATED_EVENT ) );
		},
		getBlockEditorPanels: () => Array.from( blockPanels.values() ),
		registerClassicEditorTab: ( tab ) => {
			if ( ! isClassicTabRegistration( tab ) ) {
				return;
			}

			classicTabs.set( tab.key, tab );
			window.dispatchEvent( new CustomEvent( EDITOR_EXTENSIONS_UPDATED_EVENT ) );
		},
		getClassicEditorTabs: () => Array.from( classicTabs.values() ),
	};

	window.airygenEditorExtensions = registry;

	return registry;
};

export const getRegisteredBlockEditorPanels = (): BlockEditorPanelRegistration[] =>
	ensureAirygenEditorExtensions().getBlockEditorPanels();

export const getRegisteredClassicEditorTabs = (): ClassicEditorTabRegistration[] =>
	ensureAirygenEditorExtensions().getClassicEditorTabs();

import type { ComponentType } from 'react';

export type AdminPageNotice = {
	status: 'success' | 'error';
	message: string;
};

export type AdminPageBootConfig = {
	restPath?: string;
	restRoot?: string;
	nonce?: string;
	adminUrl?: string;
	logoutUrl?: string;
	locale?: string;
	extensionApiVersion?: string;
	pageRegistry?: Array<{
		key?: string;
		slug?: string;
		title?: string;
		order?: number;
	}>;
	[ key: string ]: unknown;
};

export type AdminPageRenderProps = {
	adminConfig: AdminPageBootConfig;
	pageKey: string;
	navigate: ( pageKey: string ) => void;
	notify: ( notice: AdminPageNotice ) => void;
};

export type AdminPageRegistration = {
	key: string;
	render: ComponentType<AdminPageRenderProps>;
};

type AirygenExtensionRegistry = {
	version: string;
	registerAdminPage: ( page: AdminPageRegistration ) => void;
	getAdminPages: () => AdminPageRegistration[];
};

declare global {
	interface Window {
		airygenExtensions?: AirygenExtensionRegistry;
	}
}

const API_VERSION = '1';
export const ADMIN_PAGES_UPDATED_EVENT = 'airygen:admin-pages-updated';

export const ensureAirygenExtensions = (): AirygenExtensionRegistry => {
	if ( typeof window === 'undefined' ) {
		return {
			version: API_VERSION,
			registerAdminPage: () => {},
			getAdminPages: () => [],
		};
	}

	if ( window.airygenExtensions?.version === API_VERSION ) {
		return window.airygenExtensions;
	}

	const adminPages = new Map<string, AdminPageRegistration>();
	const registry: AirygenExtensionRegistry = {
		version: API_VERSION,
		registerAdminPage: ( page ) => {
			if ( ! page.key || typeof page.render !== 'function' ) {
				return;
			}

			adminPages.set( page.key, page );
			window.dispatchEvent( new CustomEvent( ADMIN_PAGES_UPDATED_EVENT ) );
		},
		getAdminPages: () => Array.from( adminPages.values() ),
	};

	window.airygenExtensions = registry;

	return registry;
};

export const getRegisteredAdminPages = (): AdminPageRegistration[] =>
	ensureAirygenExtensions().getAdminPages();

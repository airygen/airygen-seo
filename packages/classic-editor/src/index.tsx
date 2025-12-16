import '../style.scss';

import domReady from '@wordpress/dom-ready';
import { render, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ClassicApp, { type ClassicEditorConfig } from '../components/App';
import {
	isSessionExpiredRestError,
	lockSessionExpired,
} from '../../shared/rest/session';

type ClassicDataset = {
	metaTitle: string;
	metaDescription: string;
	focusKeyphrase: string;
	focusLongTail: string;
	agentPrompt: string;
	canonical: string;
	robots: string;
	tocMode: string;
	faqMode: string;
	topicMode: string;
	schemaType: string;
	postId: number;
	mode: string;
};

const parseDataset = ( element: HTMLElement ): ClassicDataset => ( {
	metaTitle: element.dataset.metaTitle ?? '',
	metaDescription: element.dataset.metaDescription ?? '',
	focusKeyphrase: element.dataset.focusKeyphrase ?? '',
	focusLongTail: element.dataset.focusLongTail ?? '',
	agentPrompt: element.dataset.agentPrompt ?? '',
	canonical: element.dataset.canonical ?? '',
	robots: element.dataset.robots ?? '',
	tocMode: element.dataset.tocMode ?? '',
	faqMode: element.dataset.faqMode ?? '',
	topicMode: element.dataset.topicMode ?? '',
	schemaType: element.dataset.schemaType ?? '',
	postId: element.dataset.postId ? Number( element.dataset.postId ) : 0,
	mode: element.dataset.mode ?? 'main',
} );

const mountClassicApp = ( element: HTMLElement ) => {
	const dataset = parseDataset( element );

	render(
		createElement( ClassicApp, {
			initialTitle: dataset.metaTitle,
			initialDescription: dataset.metaDescription,
			initialKeyphrase: dataset.focusKeyphrase,
			initialLongTail: dataset.focusLongTail,
			initialAgentPrompt: dataset.agentPrompt,
			initialCanonical: dataset.canonical,
			initialRobots: dataset.robots,
			initialTocMode: dataset.tocMode,
			initialFaqMode: dataset.faqMode,
			initialTopicMode: dataset.topicMode,
			initialSchemaType: dataset.schemaType,
			postId: dataset.postId,
			mode: dataset.mode,
			editorConfig: ( window.AirygenEditor ?? {} ) as ClassicEditorConfig,
		} ),
		element,
	);
};

const SESSION_EXPIRED_MESSAGE = __( 'Permission expired. Please log in again.', 'airygen-seo' );

const renderSessionExpiredMessage = ( element: HTMLElement ) => {
	element.innerHTML = `<div class="airygen-classic-panel"><div class="airygen-panel-container"><p class="airygen-classic-label-helper airygen-field-helper--bad">${ SESSION_EXPIRED_MESSAGE }</p></div></div>`;
};

const preflightSessionCheck = async (): Promise<boolean> => {
	const config = ( window.AirygenEditor ?? {} ) as ClassicEditorConfig;
	const endpoint = config.sessionCheckUrl ?? '/wp-json/airygen/v1/session-check';
	const nonce = config.restNonce ?? '';

	try {
		const response = await window.fetch( endpoint, {
			method: 'GET',
			credentials: 'same-origin',
			headers: nonce
				? {
					'X-WP-Nonce': nonce,
					Accept: 'application/json',
				}
				: {
					Accept: 'application/json',
				},
		} );

		if ( response.ok ) {
			return true;
		}

		const payload = ( await response.json().catch( () => ( {} ) ) ) as {
			code?: string;
			message?: string;
		};

		if ( isSessionExpiredRestError( { status: response.status, ...payload } ) ) {
			lockSessionExpired( 'classic' );
			return false;
		}
	} catch {
		return true;
	}

	return true;
};

domReady( () => {
	void ( async () => {
		const validSession = await preflightSessionCheck();
		const roots = document.querySelectorAll<HTMLElement>(
			'#airygen-classic-root, #airygen-classic-score-root',
		);
		roots.forEach( ( element ) => {
			if ( 'true' === element.dataset.initialized ) {
				return;
			}

			element.dataset.initialized = 'true';
			if ( validSession ) {
				mountClassicApp( element );
				return;
			}

			renderSessionExpiredMessage( element );
		} );
	} )();
} );

import { __ } from '@wordpress/i18n';
import { getNoSuggestionsYetLabel } from '../../shared/i18nPhrases';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Popover, Spinner } from '@wordpress/components';
import { useDispatch, useSelect, select as dataSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { getEditorConfig } from '../config';
import type { LinkSuggestionsConfig } from '../types';

type InsertBlocks = ( blocks: unknown | unknown[], index?: number, rootClientId?: string ) => void;

type Suggestion = {
	id: number;
	title: string;
	permalink: string;
	post_type: string;
	score: number;
};

type SuggestionsResponse = {
	suggestions: Suggestion[];
	meta?: Record< string, unknown >;
};

type State =
	| { status: 'idle' | 'loading'; data: null; error: null }
	| { status: 'ready'; data: SuggestionsResponse; error: null }
	| { status: 'error'; data: null; error: string };

const escapeHtml = ( value: string ) =>
	value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );

const fetchSuggestions = async (
	api: LinkSuggestionsConfig['api'],
	postId: number,
): Promise< SuggestionsResponse > => {
	if ( ! api?.root ) {
		throw new Error( 'Missing API configuration.' );
	}

	const query = new URLSearchParams( { post: String( postId ) } );

	return apiFetch< SuggestionsResponse >( {
		url: `${ api.root }?${ query.toString() }`,
		method: api.method ?? 'GET',
		headers: { 'X-WP-Nonce': api.nonce },
	} );
};

const LinkSuggestionsPanel = () => {
	const config = getEditorConfig().linkSuggestions;
	const postId = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as { getCurrentPostId?: () => number | undefined };
			return editor.getCurrentPostId ? editor.getCurrentPostId() : undefined;
		},
		[],
	);
	const { insertBlocks, updateBlockAttributes } = useDispatch( 'core/block-editor' ) as {
		insertBlocks?: InsertBlocks;
		updateBlockAttributes?: ( clientId: string, attributes: Record< string, unknown > ) => void;
	};

	const [ state, setState ] = useState< State >( {
		status: 'idle',
		data: null,
		error: null,
	} );
	const [ showTip, setShowTip ] = useState( false );

	const request = useCallback( async () => {
		if ( ! config?.api || ! postId ) {
			return;
		}

		setState( { status: 'loading', data: null, error: null } );

		try {
			const data = await fetchSuggestions( config.api, postId );
			setState( { status: 'ready', data, error: null } );
		} catch ( error ) {
			const message =
				error && typeof error === 'object' && 'message' in error
					? String( ( error as Error ).message )
					: __( 'Unable to fetch link suggestions.', 'airygen-seo' );
			setState( { status: 'error', data: null, error: message } );
		}
	}, [ config?.api, postId ] );

	useEffect( () => {
		if ( 'idle' !== state.status ) {
			return;
		}

		if ( postId && config?.api ) {
			void request();
		}
	}, [ state.status, postId, config?.api, request ] );

	if ( ! config?.enabled ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Link Suggestions is disabled. Enable it in Airygen → Modules.',
					'airygen-seo',
				) }
			</Notice>
		);
	}

	if ( ! config.api ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __(
					'Link Suggestions API is not configured.',
					'airygen-seo',
				) }
			</Notice>
		);
	}

	const handleInsert = ( item: Suggestion ) => {
		const title = item.title || __( '(No title)', 'airygen-seo' );
		const safeTitle = escapeHtml( title );
		const safeHref = escapeHtml( item.permalink );
		const anchor = `<a href="${ safeHref }">${ safeTitle }</a>`;

		const blockEditor = dataSelect( 'core/block-editor' ) as {
			getSelectionStart?: () => {
				clientId?: string;
				attributeKey?: string;
				offset?: number | null;
			} | null;
			getBlock?: (
				clientId: string,
			) => { name?: string; attributes?: Record< string, unknown > } | undefined;
			getBlockInsertionPoint?: () => { rootClientId?: string; index?: number };
		};

		const selection = blockEditor?.getSelectionStart?.();
		const attrKey = selection?.attributeKey || 'content';
		if (
			selection?.clientId &&
			updateBlockAttributes &&
			selection.offset !== undefined &&
			selection.offset !== null
		) {
			const target = blockEditor.getBlock?.( selection.clientId );
			const attrValue = target?.attributes?.[ attrKey ];
			if ( typeof attrValue === 'string' ) {
				const offset = Math.min( Math.max( selection.offset, 0 ), attrValue.length );
				const nextContent =
					attrValue.slice( 0, offset ) + anchor + attrValue.slice( offset );
				updateBlockAttributes( selection.clientId, { [ attrKey ]: nextContent } );
				return;
			}
		}

		if ( insertBlocks ) {
			const block = createBlock( 'core/paragraph', {
				content: anchor,
			} );
			const insertionPoint = blockEditor?.getBlockInsertionPoint?.() ?? {};
			insertBlocks( [ block ], insertionPoint.index, insertionPoint.rootClientId );
		}
	};

	const renderSuggestions = () => {
		if ( 'loading' === state.status ) {
			return (
				<div className="airygen-link-suggestions__loading">
					<Spinner />
					<span>{ __( 'Finding suggestions…', 'airygen-seo' ) }</span>
				</div>
			);
		}

		if ( state.error ) {
			return (
				<Notice status="error" isDismissible={ false }>
					{ state.error }
				</Notice>
			);
		}

		const suggestionItems = state.data?.suggestions ?? [];

		if ( suggestionItems.length === 0 ) {
			return (
				<p className="airygen-link-suggestions__empty">
					{ getNoSuggestionsYetLabel() }
				</p>
			);
		}

		return (
			<div className="airygen-preview-checklist">
				<div className="airygen-keyphrase-list">
					{ suggestionItems.map( ( item ) => (
						<div className="airygen-keyphrase-list__row" key={ item.id }>
							<div className="airygen-keyphrase-list__item airygen-link-suggestions__item">
								<div
									className="airygen-link-suggestions__title"
									title={ `${ __( 'Score', 'airygen-seo' ) }: ${ item.score }` }
								>
									{ item.title || __( '(No title)', 'airygen-seo' ) }
								</div>
							</div>
							<div className="airygen-link-suggestions__item-actions">
								<Button
									href={ item.permalink }
									target="_blank"
									rel="noreferrer"
									variant="link"
									label={ __( 'View', 'airygen-seo' ) }
									icon="visibility"
									className="airygen-link-suggestions__icon-btn"
								/>
								<Button
									variant="link"
									onClick={ () => handleInsert( item ) }
									label={ __( 'Insert', 'airygen-seo' ) }
									icon="exit"
									className="airygen-link-suggestions__icon-btn"
								/>
							</div>
						</div>
					) ) }
				</div>
			</div>
		);
	};

	const SuggestionsTabIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_link_suggestions_tab)">
				<path
					d="M2.94333 3.72154C2.98344 3.77501 3.00291 3.84116 2.99817 3.90783C2.99343 3.9745 2.9648 4.03723 2.91754 4.08449C2.87028 4.13175 2.80755 4.16038 2.74088 4.16512C2.67421 4.16986 2.60807 4.15039 2.55459 4.11028C2.42178 3.98564 2.31593 3.83509 2.24357 3.66794C2.1712 3.50079 2.13387 3.32058 2.13387 3.13843C2.13387 2.95629 2.1712 2.77608 2.24357 2.60893C2.31593 2.44178 2.42178 2.29123 2.55459 2.16659L3.52644 1.16697C3.79064 0.910823 4.14418 0.767578 4.51217 0.767578C4.88016 0.767578 5.2337 0.910823 5.4979 1.16697C5.75405 1.43118 5.8973 1.78471 5.8973 2.1527C5.8973 2.52069 5.75405 2.87423 5.4979 3.13843L5.0814 3.55494C5.08733 3.32775 5.04962 3.10152 4.97033 2.88853L5.10916 2.7497C5.25745 2.58622 5.33959 2.37341 5.33959 2.1527C5.33959 1.93199 5.25745 1.71918 5.10916 1.55571C4.94569 1.40742 4.73288 1.32528 4.51217 1.32528C4.29146 1.32528 4.07865 1.40742 3.91518 1.55571L2.94333 2.55533C2.86436 2.63053 2.80149 2.721 2.75854 2.82123C2.71558 2.92147 2.69343 3.02938 2.69343 3.13843C2.69343 3.24749 2.71558 3.3554 2.75854 3.45564C2.80149 3.55587 2.86436 3.64633 2.94333 3.72154ZM6.38645 4.99883V5.55417H5.55344V6.38718H4.9981V5.55417H4.16508V4.99883H4.9981V4.16582H5.55344V4.99883H6.38645ZM4.49829 3.80484C4.54641 3.58072 4.53592 3.34798 4.46782 3.12909C4.39973 2.91021 4.27633 2.71259 4.10955 2.55533C4.05608 2.51522 3.98993 2.49575 3.92326 2.50049C3.85659 2.50523 3.79387 2.53386 3.7466 2.58112C3.69934 2.62838 3.67071 2.69111 3.66597 2.75778C3.66124 2.82445 3.68071 2.89059 3.72081 2.94406C3.79978 3.01927 3.86265 3.10974 3.90561 3.20997C3.94856 3.31021 3.97071 3.41812 3.97071 3.52717C3.97071 3.63622 3.94856 3.74414 3.90561 3.84437C3.86265 3.94461 3.79978 4.03507 3.72081 4.11028L2.74896 5.1099C2.58549 5.25818 2.37268 5.34033 2.15197 5.34033C1.93126 5.34033 1.71845 5.25818 1.55498 5.1099C1.40669 4.94642 1.32455 4.73361 1.32455 4.5129C1.32455 4.29219 1.40669 4.07938 1.55498 3.91591L1.69381 3.80484C1.61664 3.58169 1.57905 3.34676 1.58275 3.11067L1.16624 3.52717C0.910091 3.79138 0.766846 4.14491 0.766846 4.5129C0.766846 4.88089 0.910091 5.23443 1.16624 5.49863C1.43044 5.75478 1.78398 5.89803 2.15197 5.89803C2.51996 5.89803 2.8735 5.75478 3.1377 5.49863L3.63751 4.99883C3.67995 4.74718 3.77964 4.50864 3.92889 4.30162C4.07814 4.0946 4.27295 3.92464 4.49829 3.80484Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_link_suggestions_tab">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	return (
		<div className="airygen-link-suggestions-panel">
			<div className="airygen-panel-tabs" style={ { marginBottom: '8px' } }>
				<Button
					variant="primary"
					aria-pressed="true"
					className="airygen-component-button"
					aria-label={ __( 'Suggestions', 'airygen-seo' ) }
					title={ __( 'Suggestions', 'airygen-seo' ) }
				>
					<SuggestionsTabIcon />
				</Button>
			</div>
			<div className="airygen-link-suggestions__header">
				<div className="airygen-tip-positioner" style={ { display: 'flex', gap: '8px', alignItems: 'center' } }>
					<Button
						className="airygen-tip-button"
						onClick={ () => setShowTip( ( prev ) => ! prev ) }
						aria-expanded={ showTip }
					>
						?
					</Button>
					{ showTip && (
						<Popover
							noArrow
							position="bottom left"
							onClose={ () => setShowTip( false ) }
						>
							<div className="airygen-panel-popover">
								{ __(
									'Refresh after editing to see the latest five link ideas and this post’s top terms.',
									'airygen-seo',
								) }
								<div style={ { marginTop: '8px', textAlign: 'center', display: 'flex', gap: '8px', justifyContent: 'center' } }>
									<Button
										variant="secondary"
										onClick={ request }
										disabled={ 'loading' === state.status }
										className="airygen-component-button"
									>
										{ 'loading' === state.status
											? __( 'Refreshing…', 'airygen-seo' )
											: __( 'Refresh', 'airygen-seo' ) }
									</Button>
									<Button
										variant="primary"
										size="small"
										onClick={ () => setShowTip( false ) }
										className="airygen-component-button"
									>
										{ __( 'Got it', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</Popover>
					) }
				</div>
				{ config?.max ? (
					<span className="airygen-link-suggestions__hint">
						{ __( 'Showing up to', 'airygen-seo' ) } { config.max }{ ' ' }
						{ __( 'Suggestions', 'airygen-seo' ) }
					</span>
				) : null }
			</div>
			{ renderSuggestions() }
		</div>
	);
};

export default LinkSuggestionsPanel;

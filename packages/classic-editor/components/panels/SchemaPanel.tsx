import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { getLoadingItemLabel } from '../../../shared/i18nPhrases';

const INHERIT_VALUE = '__airygen_schema_inherit__';

const DEFAULT_OPTIONS = [
	{ value: 'Article', label: __( 'Article', 'airygen-seo' ) },
	{ value: 'NewsArticle', label: __( 'News Article', 'airygen-seo' ) },
	{ value: 'BlogPosting', label: __( 'Blog Posting', 'airygen-seo' ) },
	{ value: 'TechArticle', label: __( 'Tech Article', 'airygen-seo' ) },
];

type SchemaMarkupConfig = {
	article_type?: string;
	post_type_defaults?: Record<string, string>;
};

type SchemaPanelProps = {
	schemaSubTab: 'preview' | 'custom';
	setSchemaSubTab: ( tab: 'preview' | 'custom' ) => void;
	schemaType: string;
	setSchemaType: ( value: string ) => void;
	postId: number;
	schemaConfig?: SchemaMarkupConfig;
};

export const SchemaPanel = ( {
	schemaSubTab,
	setSchemaSubTab,
	schemaType,
	setSchemaType,
	postId,
	schemaConfig,
}: SchemaPanelProps ) => {
	const [ previewLoading, setPreviewLoading ] = useState( false );
	const [ previewError, setPreviewError ] = useState( '' );
	const [ previewWarning, setPreviewWarning ] = useState( '' );
	const [ previewJson, setPreviewJson ] = useState( '' );
	const [ copyMessage, setCopyMessage ] = useState( '' );
	const [ copyStatus, setCopyStatus ] = useState< '' | 'success' | 'error' | 'warn' >( '' );
	const [ isPreviewOpen, setIsPreviewOpen ] = useState( false );

	const postType = useMemo( () => {
		const input = document.getElementById( 'post_type' ) as HTMLInputElement | null;
		return input?.value ?? '';
	}, [] );

	const typeOptions = useMemo( () => {
		const combined = new Set<string>();
		DEFAULT_OPTIONS.forEach( ( option ) => combined.add( option.value ) );
		if ( schemaConfig?.post_type_defaults ) {
			Object.values( schemaConfig.post_type_defaults ).forEach( ( value ) => {
				if ( typeof value === 'string' && value !== '' ) {
					combined.add( value );
				}
			} );
		}
		if ( schemaConfig?.article_type ) {
			combined.add( schemaConfig.article_type );
		}

		return Array.from( combined ).map( ( value ) => ( {
			value,
			label: value,
		} ) );
	}, [ schemaConfig ] );

	const articleDefault =
		( postType === 'product'
			? 'Product'
			: ( postType && schemaConfig?.post_type_defaults?.[ postType ] ) ) ||
		schemaConfig?.article_type ||
		'Article';

	const isProductPostType = postType === 'product';
	const currentValue = schemaType && schemaType !== '' ? schemaType : INHERIT_VALUE;
	const selectValue = isProductPostType ? INHERIT_VALUE : currentValue;
	let previewType = currentValue;
	if ( isProductPostType ) {
		previewType = 'Product';
	} else if ( currentValue === INHERIT_VALUE ) {
		previewType = articleDefault;
	}

	const fetchPreview = async () => {
		if ( ! postId ) {
			setPreviewError( __( 'Save the post first to preview JSON-LD.', 'airygen-seo' ) );
			return;
		}

		setPreviewLoading( true );
		setPreviewError( '' );
		setPreviewWarning( '' );
		setCopyMessage( '' );
		setCopyStatus( '' );

		try {
			const response = await apiFetch< { jsonld?: unknown } >( {
				path: `/airygen/v1/schema/preview?post=${ postId }`,
				method: 'GET',
			} );
			const payload = ( response as { jsonld?: unknown } ).jsonld ?? response;
			const json = JSON.stringify( payload, null, 2 );
			setPreviewJson( json );

			const isEmpty =
				payload === null ||
				( Array.isArray( payload ) && payload.length === 0 ) ||
				( typeof payload === 'object' && payload !== null && Object.keys( payload ).length === 0 );

			if ( isEmpty ) {
				setCopyStatus( 'warn' );
				setPreviewWarning(
					__( 'JSON-LD is empty. Check Schema settings and enable the graphs you need.', 'airygen-seo' ),
				);
			}
		} catch ( error ) {
			const message =
				( error as { message?: string } )?.message ??
				__( 'Unable to load JSON-LD preview.', 'airygen-seo' );
			setPreviewError( message );
		} finally {
			setPreviewLoading( false );
		}
	};

	const copyToClipboard = async () => {
		if ( ! previewJson ) {
			setCopyStatus( 'error' );
			setCopyMessage( __( 'Nothing to copy.', 'airygen-seo' ) );
			return;
		}

		try {
			if ( navigator?.clipboard?.writeText ) {
				await navigator.clipboard.writeText( previewJson );
			} else {
				const textarea = document.createElement( 'textarea' );
				textarea.value = previewJson;
				document.body.appendChild( textarea );
				textarea.select();
				document.execCommand( 'copy' );
				document.body.removeChild( textarea );
			}
			setCopyStatus( 'success' );
			setCopyMessage( __( 'Copied to clipboard.', 'airygen-seo' ) );
		} catch ( error ) {
			const message =
				( error as { message?: string } )?.message ??
				__( 'Failed to copy.', 'airygen-seo' );
			setCopyStatus( 'error' );
			setCopyMessage( message );
		}
	};

	let copyStatusClass = 'airygen-json-preview__status';
	if ( copyStatus === 'success' ) {
		copyStatusClass += ' airygen-json-preview__status--success';
	} else if ( copyStatus === 'error' ) {
		copyStatusClass += ' airygen-json-preview__status--error';
	} else if ( copyStatus === 'warn' ) {
		copyStatusClass += ' airygen-json-preview__status--warn';
	}

	const closePreviewModal = () => {
		setIsPreviewOpen( false );
		setCopyMessage( '' );
		setCopyStatus( '' );
		setPreviewWarning( '' );
	};

	return (
		<div className="airygen-schema-panel">
			<div className="airygen-panel-tabs">
				<button
					type="button"
					className={
						schemaSubTab === 'preview'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setSchemaSubTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					className={
						schemaSubTab === 'custom'
							? 'airygen-tab-panel-button is-primary'
							: 'airygen-tab-panel-button is-secondary'
					}
					onClick={ () => setSchemaSubTab( 'custom' ) }
				>
					{ __( 'Custom', 'airygen-seo' ) }
				</button>
			</div>
			<div className="airygen-panel-container">
				{ schemaSubTab === 'preview' ? (
					<div className="airygen-panel-container">
						<div className="airygen-snippet-preview">
							<p className="airygen-snippet-preview__url" style={ { marginBottom: 0 } }>
								{ sprintf(
									/* translators: %s is the resolved schema type. */
									__( 'Article schema: %s', 'airygen-seo' ),
									previewType,
								) }
							</p>
						</div>
						<button
							type="button"
							className="airygen-component-button is-secondary"
							onClick={ () => {
								setCopyMessage( '' );
								setCopyStatus( '' );
								setPreviewWarning( '' );
								setIsPreviewOpen( true );
								void fetchPreview();
							} }
							disabled={ ! postId }
						>
							{ __( 'Preview JSON-LD', 'airygen-seo' ) }
						</button>
						{ isPreviewOpen && (
							<div className="airygen-ai-modal" role="dialog" aria-modal="true">
								<div className="airygen-ai-modal__backdrop" onClick={ closePreviewModal } role="presentation" />
								<div className="airygen-ai-modal__container">
									<div className="airygen-ai-modal__header">
										<h2 className="airygen-ai-modal__title">{ __( 'JSON-LD Preview', 'airygen-seo' ) }</h2>
										<button type="button" className="airygen-ai-modal__close" onClick={ closePreviewModal }>
											<svg viewBox="0 0 24 24" aria-hidden="true">
												<path d="M18.3 5.71 12 12l6.3 6.29-1.42 1.42L10.59 13.4 4.29 19.7 2.88 18.3 9.17 12 2.88 5.71 4.29 4.29l6.3 6.3 6.29-6.3z" />
											</svg>
										</button>
									</div>
									<div className="airygen-ai-modal__body">
										{ previewLoading && (
											<div className="flex items-center gap-2">
												<Spinner />
												<span>{ getLoadingItemLabel( __( 'JSON-LD', 'airygen-seo' ) ) }</span>
											</div>
										) }
										{ ! previewLoading && previewError && (
											<Notice status="error" isDismissible={ false }>
												{ previewError }
											</Notice>
										) }
										{ ! previewLoading && ! previewError && (
											<>
												{ previewWarning && (
													<Notice status="warning" isDismissible={ false }>
														{ previewWarning }
													</Notice>
												) }
												<div className="airygen-json-preview__container">
													<pre className="airygen-json-preview__body">
														{ previewJson || __( 'No JSON-LD available for this post.', 'airygen-seo' ) }
													</pre>
												</div>
											</>
										) }
									</div>
									<div className="airygen-ai-modal__footer">
										<span className={ copyStatusClass }>{ copyMessage }</span>
										<div className="airygen-json-preview__actions">
											<button
												type="button"
												className="airygen-component-button is-secondary"
												onClick={ () => {
													void copyToClipboard();
												} }
												disabled={ previewLoading || ! previewJson }
											>
												{ __( 'Copy', 'airygen-seo' ) }
											</button>
										</div>
									</div>
								</div>
							</div>
						) }
					</div>
				) : (
					<div className="airygen-classic-field">
						<label className="airygen-classic-label" htmlFor="airygen-schema-type-select">
							<span className="airygen-classic-label-text">{ __( 'Article schema for this post', 'airygen-seo' ) }</span>
						</label>
						<select
							id="airygen-schema-type-select"
							className="airygen-classic-input"
							value={ selectValue }
							disabled={ isProductPostType }
							onChange={ ( event ) => {
								const next = event.target.value;
								if ( next === INHERIT_VALUE ) {
									setSchemaType( '' );
									return;
								}
								setSchemaType( next );
							} }
						>
							<option value={ INHERIT_VALUE }>
								{ sprintf(
									/* translators: %s is the inherited schema type. */
									__( 'Inherit default (%s)', 'airygen-seo' ),
									articleDefault,
								) }
							</option>
							{ ( isProductPostType ? [] : typeOptions ).map( ( option ) => (
								<option key={ option.value } value={ option.value }>
									{ option.label }
								</option>
							) ) }
						</select>
						<p className="airygen-field-helper">
							{ __(
								'Choose a different Article schema for this post or leave it on the inherited default.',
								'airygen-seo',
							) }
						</p>
					</div>
				) }
			</div>
		</div>
	);
};

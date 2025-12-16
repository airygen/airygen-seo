import { __, sprintf } from '@wordpress/i18n';
import { Notice, SelectControl, Button, Modal, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';
import apiFetch from '@wordpress/api-fetch';
import { getLoadingItemLabel } from '../../shared/i18nPhrases';
import { getEditorConfig } from '../config';
import usePostDataField from '../hooks/usePostDataField';

const INHERIT_VALUE = '__airygen_schema_inherit__';

const DEFAULT_OPTIONS = [
	{ value: 'Article', label: __( 'Article', 'airygen-seo' ) },
	{ value: 'NewsArticle', label: __( 'News Article', 'airygen-seo' ) },
	{ value: 'BlogPosting', label: __( 'Blog Posting', 'airygen-seo' ) },
	{ value: 'TechArticle', label: __( 'Tech Article', 'airygen-seo' ) },
];

type EditorSelectors = {
	getCurrentPostType?: () => string | undefined;
};

const SchemaPanel = () => {
	const config = getEditorConfig();
	const schema = config.schemaMarkup;
	const postType = useSelect(
		( select ): string | undefined => {
			const selectors = select( editorStore ) as EditorSelectors;
			return selectors.getCurrentPostType?.();
		},
		[],
	);
	const [ metaValue, setMetaValue ] = usePostDataField( 'schemaArticleType' );
	const [ mode, setMode ] = useState< 'preview' | 'custom' >( 'preview' );
	const [ isPreviewOpen, setIsPreviewOpen ] = useState( false );
	const [ previewLoading, setPreviewLoading ] = useState( false );
	const [ previewError, setPreviewError ] = useState( '' );
	const [ previewJson, setPreviewJson ] = useState<string>( '' );
	const [ copyMessage, setCopyMessage ] = useState( '' );
	const [ copyStatus, setCopyStatus ] = useState< '' | 'success' | 'error' | 'warn' >( '' );
	const [ previewWarning, setPreviewWarning ] = useState( '' );
	const postId = useSelect(
		( select ) => {
			const selectors = select( editorStore ) as EditorSelectors & {
				getCurrentPostId?: () => number | undefined;
			};
			return selectors.getCurrentPostId?.() || 0;
		},
		[],
	);
	const typeOptions = useMemo( () => {
		const combined = new Set<string>();
		DEFAULT_OPTIONS.forEach( ( option ) => combined.add( option.value ) );
		if ( schema?.post_type_defaults ) {
			Object.values( schema.post_type_defaults ).forEach( ( value ) => {
				if ( typeof value === 'string' && value !== '' ) {
					combined.add( value );
				}
			} );
		}
		if ( schema?.article_type ) {
			combined.add( schema.article_type );
		}
		return Array.from( combined ).map( ( value ) => ( {
			value,
			label: value,
		} ) );
	}, [ schema ] );

	let copyStatusClass = 'airygen-json-preview__status';
	if ( copyStatus === 'success' ) {
		copyStatusClass += ' airygen-json-preview__status--success';
	} else if ( copyStatus === 'error' ) {
		copyStatusClass += ' airygen-json-preview__status--error';
	}

	if ( ! schema ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Schema Markup defaults are not set yet. Configure them in the Airygen dashboard.',
					'airygen-seo',
				) }
			</Notice>
		);
	}

	const articleDefault =
		( postType === 'product'
			? 'Product'
			: ( postType && schema.post_type_defaults?.[ postType ] ) ) ||
		schema.article_type ||
		'Article';

	const isProductPostType = postType === 'product';
	const currentValue = metaValue && metaValue !== '' ? metaValue : INHERIT_VALUE;
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

		try {
			const response = await apiFetch< { jsonld?: unknown } >( {
				path: `/airygen/v1/schema/preview?post=${ postId }`,
			} );
			const json = JSON.stringify( response?.jsonld ?? response, null, 2 );
			setPreviewJson( json );

			const payload = ( response as { jsonld?: unknown } ).jsonld ?? response;
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

	return (
		<div className="airygen-schema-panel">
			<div className="airygen-panel-tabs">
				<Button
					variant={ mode === 'preview' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'preview' ) }
					aria-label={ __( 'Preview schema', 'airygen-seo' ) }
					title={ __( 'Preview schema', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<svg
						width="20"
						height="20"
						viewBox="0 0 7 7"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
						aria-hidden="true"
						focusable="false"
					>
						<g clipPath="url(#clip0_schema_preview)">
							<path
								d="M3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H3.60975C3.49591 6.03966 3.38762 5.95358 3.29321 5.85917C3.20158 5.76754 3.12383 5.6648 3.05441 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V2.82701C5.19525 2.87144 5.38407 2.94641 5.55345 3.0547V2.22169L3.88742 0.555664ZM5.63953 5.2483C6.00883 4.66241 5.83112 3.88771 5.25079 3.52119C4.6649 3.15188 3.88742 3.33237 3.52368 3.90993C3.1516 4.49581 3.33208 5.26774 3.91241 5.63704C4.31781 5.89527 4.83428 5.89527 5.24246 5.64259L6.10879 6.49504L6.49475 6.10908L5.63953 5.2483ZM4.5816 5.27607C4.39749 5.27607 4.22093 5.20293 4.09074 5.07275C3.96056 4.94256 3.88742 4.766 3.88742 4.58189C3.88742 4.39778 3.96056 4.22121 4.09074 4.09103C4.22093 3.96085 4.39749 3.88771 4.5816 3.88771C4.76571 3.88771 4.94227 3.96085 5.07246 4.09103C5.20264 4.22121 5.27578 4.39778 5.27578 4.58189C5.27578 4.766 5.20264 4.94256 5.07246 5.07275C4.94227 5.20293 4.76571 5.27607 4.5816 5.27607Z"
								fill="black"
							/>
						</g>
						<defs>
							<clipPath id="clip0_schema_preview">
								<rect width="6.6641" height="6.6641" fill="white" />
							</clipPath>
						</defs>
					</svg>
				</Button>
				<Button
					variant={ mode === 'custom' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'custom' ) }
					aria-label={ __( 'Custom schema', 'airygen-seo' ) }
					title={ __( 'Custom schema', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<svg
						width="20"
						height="20"
						viewBox="0 0 7 7"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
						aria-hidden="true"
						focusable="false"
					>
						<g clipPath="url(#clip0_schema_custom)">
							<path d="M5.47015 3.58227L3.88743 5.165H3.24879V4.52635L4.83151 2.94363L5.47015 3.58227ZM6.41423 3.36014C6.41423 3.44344 6.33093 3.52674 6.24763 3.61004L5.55345 4.30422L5.30355 4.05431L6.02549 3.33237L5.85889 3.16577L5.66452 3.36014L5.02588 2.7215L5.63675 2.13839C5.69229 2.08285 5.80336 2.08285 5.88666 2.13839L6.2754 2.52713C6.33093 2.58266 6.33093 2.69373 6.2754 2.77703C6.21986 2.83256 6.16433 2.8881 6.16433 2.94363C6.16433 2.99917 6.21986 3.0547 6.2754 3.11023C6.3587 3.19354 6.442 3.27684 6.41423 3.36014ZM0.833051 5.55374V1.11101H2.77675V2.49936H4.1651V2.91586L4.72044 2.36052V2.22169L3.05442 0.555664H0.833051C0.527614 0.555664 0.27771 0.805568 0.27771 1.11101V5.55374C0.27771 5.85917 0.527614 6.10908 0.833051 6.10908H4.1651C4.47054 6.10908 4.72044 5.85917 4.72044 5.55374H0.833051ZM3.05442 4.74849C2.99888 4.74849 2.94335 4.77626 2.91558 4.77626L2.77675 4.16538H2.36024L1.77713 4.63742L1.94373 3.88771H1.52723L1.24956 5.27607H1.66606L2.47131 4.55412L2.63791 5.19276H2.91558L3.05442 5.165V4.74849Z" fill="black" />
						</g>
						<defs>
							<clipPath id="clip0_schema_custom">
								<rect width="6.6641" height="6.6641" fill="white" />
							</clipPath>
						</defs>
					</svg>
				</Button>
			</div>

			{ mode === 'preview' ? (
				<div className="space-y-3">
					<div className="airygen-snippet-preview">
						<p className="airygen-snippet-preview__url" style={ { marginBottom: 0 } }>
							{ sprintf(
								/* translators: %s is the resolved schema type. */
								__( 'Article schema: %s', 'airygen-seo' ),
								previewType,
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						onClick={ () => {
							setCopyMessage( '' );
							setCopyStatus( '' );
							setIsPreviewOpen( true );
							fetchPreview();
						} }
						disabled={ ! postId }
						className="airygen-component-button"
					>
						{ __( 'Preview JSON-LD', 'airygen-seo' ) }
					</Button>
					{ isPreviewOpen && (
						<Modal
							title={ __( 'JSON-LD Preview', 'airygen-seo' ) }
							onRequestClose={ () => {
								setIsPreviewOpen( false );
								setCopyMessage( '' );
								setCopyStatus( '' );
								setPreviewWarning( '' );
							} }
							className="airygen-json-preview-modal"
						>
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
									<div className="airygen-json-preview__footer">
										<span className={ copyStatusClass }>
											{ copyMessage }
										</span>
										<div className="airygen-json-preview__actions">
											<Button
												variant="secondary"
												onClick={ () => {
													setIsPreviewOpen( false );
													setCopyMessage( '' );
													setCopyStatus( '' );
												} }
												className="airygen-component-button"
											>
												{ __( 'Close', 'airygen-seo' ) }
											</Button>
											<Button
												variant="primary"
												onClick={ copyToClipboard }
												disabled={ previewLoading || ! previewJson }
												className="airygen-component-button"
											>
												{ __( 'Copy', 'airygen-seo' ) }
											</Button>
										</div>
									</div>
								</>
							) }
						</Modal>
					) }
				</div>
			) : (
				<SelectControl
					label={ __( 'Article schema for this post', 'airygen-seo' ) }
					value={ selectValue }
					disabled={ isProductPostType }
					options={ [
						{
							label: sprintf(
								/* translators: %s is the inherited schema type. */
								__( 'Inherit default (%s)', 'airygen-seo' ),
								articleDefault,
							),
							value: INHERIT_VALUE,
						},
						...( isProductPostType ? [] : typeOptions ),
					] }
					onChange={ ( next ) => {
						if ( next === INHERIT_VALUE ) {
							setMetaValue( '' );
							return;
						}
						setMetaValue( next );
					} }
					help={ __(
						'Choose a different Article schema for this post or leave it on the inherited default.',
						'airygen-seo',
					) }
				/>
			) }
		</div>
	);
};

export default SchemaPanel;

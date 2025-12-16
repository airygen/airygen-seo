import apiFetch from '@wordpress/api-fetch';
import HeadingIcon from '../../../components/HeadingIcon';
import Input from '../../../components/Input';
import TemplateTokenEditor, { type TemplateToken } from '../../../components/TemplateTokenEditor';
import Toggle from '../../../components/Toggle';
import { SchemaMarkupIcon } from '../../../components/Icons';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { getLoadingItemLabel } from '../../../../shared/i18nPhrases';
import type { MetaPayload } from '../../../types/api';
import type { WooCommerceSeoSettings } from '../../../types/settings';

type WooCommerceSeoTabProps = {
	settings: WooCommerceSeoSettings;
	meta: MetaPayload;
	onChange: ( next: WooCommerceSeoSettings ) => void;
};

type PreviewProductOption = {
	id: number;
	title: string;
};

type WooCommerceSchemaPreviewResponse = {
	products: PreviewProductOption[];
	selectedProductId: number;
	head: {
		title: string;
		description: string;
		canonical: string;
	};
	schema: Record<string, unknown>;
};

const WooCommerceSeoTab = ( { settings, meta, onChange }: WooCommerceSeoTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'preview'>( 'settings' );
	const [ previewProducts, setPreviewProducts ] = useState<PreviewProductOption[]>( [] );
	const [ selectedPreviewProductId, setSelectedPreviewProductId ] = useState<number>( 0 );
	const [ headSample, setHeadSample ] = useState<string>( '' );
	const [ schemaSample, setSchemaSample ] = useState<string>( '' );
	const [ previewLoading, setPreviewLoading ] = useState<boolean>( false );
	const [ previewError, setPreviewError ] = useState<string>( '' );

	const updateSettings = ( patch: Partial<WooCommerceSeoSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const templateTokens = useMemo<TemplateToken[]>(
		() => [
			{ value: '%product_name%', label: 'product_name', description: __( 'Uses the product title.', 'airygen-seo' ) },
			{ value: '%sku%', label: 'sku', description: __( 'Uses the SKU value.', 'airygen-seo' ) },
			{ value: '%price%', label: 'price', description: __( 'Uses the current product price.', 'airygen-seo' ) },
			{ value: '%min_price%', label: 'min_price', description: __( 'Uses the minimum variation price.', 'airygen-seo' ) },
			{ value: '%max_price%', label: 'max_price', description: __( 'Uses the maximum variation price.', 'airygen-seo' ) },
			{ value: '%currency%', label: 'currency', description: __( 'Uses the WooCommerce store currency.', 'airygen-seo' ) },
			{ value: '%stock_status%', label: 'stock_status', description: __( 'Uses stock status (in stock, backorder, out of stock).', 'airygen-seo' ) },
			{ value: '%brand%', label: 'brand', description: __( 'Uses the configured brand attribute term.', 'airygen-seo' ) },
			{ value: '%category_name%', label: 'category_name', description: __( 'Uses the first product category name.', 'airygen-seo' ) },
			{ value: '%site_name%', label: 'site_name', description: __( 'Uses your site name.', 'airygen-seo' ) },
			{
				value: '%separator%',
				label: 'separator',
				description: __(
					'Uses the separator value below. One space is added on both sides automatically.',
					'airygen-seo',
				),
			},
			{
				value: '%custom_1%',
				label: 'custom_1',
				description: sprintf(
					/* translators: %s is the custom token number. */
					__( 'Uses custom token %s.', 'airygen-seo' ),
					'1',
				),
			},
			{
				value: '%custom_2%',
				label: 'custom_2',
				description: sprintf(
					/* translators: %s is the custom token number. */
					__( 'Uses custom token %s.', 'airygen-seo' ),
					'2',
				),
			},
			{
				value: '%custom_3%',
				label: 'custom_3',
				description: sprintf(
					/* translators: %s is the custom token number. */
					__( 'Uses custom token %s.', 'airygen-seo' ),
					'3',
				),
			},
		],
		[],
	);

	const detectedBrandTaxonomies = useMemo(
		() =>
			meta.taxonomies.filter( ( taxonomy ) => {
				const slug = String( taxonomy.slug || '' ).toLowerCase();
				const label = String( taxonomy.label || '' ).toLowerCase();
				return slug.includes( 'brand' ) || label.includes( 'brand' );
			} ),
		[ meta.taxonomies ],
	);

	/* translators: %s is the template token placeholder. */
	const customToken1Help = sprintf( __( 'Value for %s.', 'airygen-seo' ), '%custom_1%' );
	/* translators: %s is the template token placeholder. */
	const customToken2Help = sprintf( __( 'Value for %s.', 'airygen-seo' ), '%custom_2%' );
	/* translators: %s is the template token placeholder. */
	const customToken3Help = sprintf( __( 'Value for %s.', 'airygen-seo' ), '%custom_3%' );

	useEffect( () => {
		if ( 'preview' !== activeTab ) {
			return;
		}

		let cancelled = false;
		const timer = window.setTimeout( () => {
			setPreviewLoading( true );
			setPreviewError( '' );

			const params = new URLSearchParams();
			if ( selectedPreviewProductId > 0 ) {
				params.set( 'product', String( selectedPreviewProductId ) );
			}

			const path = `/airygen/v1/woocommerce/schema-preview?${ params.toString() }`;

			apiFetch<WooCommerceSchemaPreviewResponse>( { path } )
				.then( ( response ) => {
					if ( cancelled ) {
						return;
					}

					setPreviewProducts( response.products || [] );
					if ( response.selectedProductId > 0 ) {
						setSelectedPreviewProductId( response.selectedProductId );
					}

					const headLines = [
						`<title>${ response.head?.title || '' }</title>`,
						`<meta name="description" content="${ response.head?.description || '' }" />`,
						`<link rel="canonical" href="${ response.head?.canonical || '' }" />`,
					];
					setHeadSample( headLines.join( '\n' ) );
					setSchemaSample( JSON.stringify( response.schema || {}, null, 2 ) );
				} )
				.catch( ( error: unknown ) => {
					if ( cancelled ) {
						return;
					}

					const message = error instanceof Error ? error.message : __( 'Unable to load preview.', 'airygen-seo' );
					setPreviewError( message );
					setHeadSample( '' );
					setSchemaSample( '' );
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setPreviewLoading( false );
					}
				} );
		}, 250 );

		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ activeTab, selectedPreviewProductId ] );

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<SchemaMarkupIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'WooCommerce SEO', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __( 'Configure product-specific SEO templates while using WooCommerce native Product schema.', 'airygen-seo' ) }
						</p>
					</div>
				</div>
				<span
					className={
						meta.wooCommerce?.active
							? 'inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100'
							: 'inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200'
					}
				>
					<span className="inline-flex h-8 w-8 items-center justify-center">
						<svg
							className="block h-8 w-8"
							style={ { transform: 'translateY(1px)' } }
							viewBox="0 0 7 7"
							fill="none"
							xmlns="http://www.w3.org/2000/svg"
							aria-hidden="true"
							focusable="false"
						>
							{ meta.wooCommerce?.active ? (
								<>
									<g clipPath="url(#wc-status-enabled-clip)">
										<path
											d="M5.20616 6.15253L4.44256 5.31952L4.76466 4.99742L5.20616 5.43891L6.203 4.44208L6.52509 4.83359L5.20616 6.15253ZM3.05421 4.16441H3.60955V4.71975H3.05421V4.16441ZM3.05421 1.94304H3.60955V3.60906H3.05421V1.94304ZM3.33188 0.554688C4.85907 0.554688 6.10859 1.80421 6.10859 3.33139L6.08638 3.69514C5.917 3.63961 5.73929 3.60906 5.53659 3.60906L5.55325 3.33139C5.55325 2.10409 4.55919 1.11003 3.33188 1.11003C2.10458 1.11003 1.11052 2.10409 1.11052 3.33139C1.11052 4.5587 2.10458 5.55276 3.33188 5.55276C3.52903 5.55276 3.71784 5.52777 3.90111 5.48056C3.92332 5.66938 3.97885 5.84709 4.05938 6.01092C3.82614 6.07478 3.58179 6.1081 3.33188 6.1081C1.79636 6.1081 0.555176 4.85858 0.555176 3.33139C0.555176 1.80421 1.79636 0.554688 3.33188 0.554688Z"
											fill="currentColor"
										/>
									</g>
									<defs>
										<clipPath id="wc-status-enabled-clip">
											<rect width="6.6641" height="6.6641" fill="white" />
										</clipPath>
									</defs>
								</>
							) : (
								<>
									<g clipPath="url(#wc-status-disabled-clip)">
										<path
											d="M3.05446 2.49936H3.6098V1.94402H3.05446V2.49936ZM3.33213 5.55374C2.1076 5.55374 1.11076 4.5569 1.11076 3.33237C1.11076 2.10784 2.1076 1.11101 3.33213 1.11101C4.55665 1.11101 5.55349 2.10784 5.55349 3.33237C5.55349 4.5569 4.55665 5.55374 3.33213 5.55374ZM3.33213 0.555664C2.96748 0.555664 2.60641 0.627486 2.26953 0.767028C1.93264 0.906571 1.62654 1.1111 1.3687 1.36894C0.847965 1.88968 0.55542 2.59594 0.55542 3.33237C0.55542 4.0688 0.847965 4.77506 1.3687 5.2958C1.62654 5.55364 1.93264 5.75817 2.26953 5.89771C2.60641 6.03726 2.96748 6.10908 3.33213 6.10908C4.06855 6.10908 4.77482 5.81653 5.29555 5.2958C5.81629 4.77506 6.10883 4.0688 6.10883 3.33237C6.10883 2.96773 6.03701 2.60666 5.89747 2.26977C5.75793 1.93289 5.5534 1.62678 5.29555 1.36894C5.03771 1.1111 4.73161 0.906571 4.39473 0.767028C4.05784 0.627486 3.69677 0.555664 3.33213 0.555664ZM3.05446 4.72072H3.6098V3.0547H3.05446V4.72072Z"
											fill="currentColor"
										/>
									</g>
									<defs>
										<clipPath id="wc-status-disabled-clip">
											<rect width="6.6641" height="6.6641" fill="white" />
										</clipPath>
									</defs>
								</>
							) }
						</svg>
					</span>
					{ meta.wooCommerce?.active
						? __( 'WooCommerce detected', 'airygen-seo' )
						: __( 'WooCommerce not enabled', 'airygen-seo' ) }
				</span>
			</div>

			<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
				<button
					type="button"
					data-airygen-e2e="tab-settings"
					className={
						'settings' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'settings' ) }
				>
					{ __( 'Settings', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-preview"
					className={
						'preview' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
			</div>

			{ 'settings' === activeTab ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Settings', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Enable WooCommerce SEO output for product metadata. Product schema is provided by WooCommerce native output.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-1">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Enable WooCommerce SEO', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Enable WooCommerce SEO', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.enabled }
										onChange={ ( value ) => updateSettings( { enabled: value } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Global output switch. When disabled, WooCommerce SEO metadata is not rendered.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Templates', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'These templates are used when product-level SEO fields are empty.', 'airygen-seo' ) }
							</p>
						</div>
						<TemplateTokenEditor
							label={ __( 'Default product title template', 'airygen-seo' ) }
							description={ __( 'Fallback title for products without custom SEO title.', 'airygen-seo' ) }
							value={ settings.templates.product.title }
							availableTokens={ templateTokens }
							onChange={ ( value ) =>
								updateSettings( {
									templates: {
										...settings.templates,
										product: {
											...settings.templates.product,
											title: value,
										},
									},
								} )
							}
						/>
						<TemplateTokenEditor
							label={ __( 'Default product description template', 'airygen-seo' ) }
							description={ __( 'Fallback description for products without custom SEO description.', 'airygen-seo' ) }
							value={ settings.templates.product.description }
							availableTokens={ templateTokens }
							onChange={ ( value ) =>
								updateSettings( {
									templates: {
										...settings.templates,
										product: {
											...settings.templates.product,
											description: value,
										},
									},
								} )
							}
						/>
						<div className="grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Separator', 'airygen-seo' ) }
									help={ __( 'One space is added before and after when rendered.', 'airygen-seo' ) }
									value={ settings.templates.separator }
									inputClassName="!w-[100px] px-2"
									inputStyle={ { width: '100px' } }
									onChange={ ( value ) =>
										updateSettings( {
											templates: {
												...settings.templates,
												separator: value,
											},
										} )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Custom token 1', 'airygen-seo' ) }
									help={ customToken1Help }
									value={ settings.templates.customTokens.custom1 }
									onChange={ ( value ) =>
										updateSettings( {
											templates: {
												...settings.templates,
												customTokens: {
													...settings.templates.customTokens,
													custom1: value,
												},
											},
										} )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Custom token 2', 'airygen-seo' ) }
									help={ customToken2Help }
									value={ settings.templates.customTokens.custom2 }
									onChange={ ( value ) =>
										updateSettings( {
											templates: {
												...settings.templates,
												customTokens: {
													...settings.templates.customTokens,
													custom2: value,
												},
											},
										} )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Custom token 3', 'airygen-seo' ) }
									help={ customToken3Help }
									value={ settings.templates.customTokens.custom3 }
									onChange={ ( value ) =>
										updateSettings( {
											templates: {
												...settings.templates,
												customTokens: {
													...settings.templates.customTokens,
													custom3: value,
												},
											},
										} )
									}
								/>
							</div>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Brand attribute taxonomy', 'airygen-seo' ) }
								help={ __(
									'Set which product attribute is your brand (for example: product_brand). This value is used for the %brand% token and Product schema brand field. Leave it as default unless you use a different brand attribute slug.',
									'airygen-seo',
								) }
								value={ settings.brandAttribute }
								onChange={ ( value ) => updateSettings( { brandAttribute: value } ) }
							/>
							<div className="mt-3">
								<p className="text-xs font-medium text-slate-700">
									{ __( 'Detected brand-like taxonomies', 'airygen-seo' ) }
								</p>
								{ detectedBrandTaxonomies.length > 0 ? (
									<div className="mt-2 flex flex-wrap gap-2">
										{ detectedBrandTaxonomies.map( ( taxonomy ) => (
											<button
												key={ taxonomy.slug }
												type="button"
												className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
												onClick={ () =>
													updateSettings( { brandAttribute: taxonomy.slug } )
												}
											>
												{ taxonomy.slug }
											</button>
										) ) }
									</div>
								) : (
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'No taxonomy containing "brand" was detected.', 'airygen-seo' ) }
									</p>
								) }
							</div>
						</div>
					</section>
				</>
			) : null }

			{ 'preview' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Preview', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __( 'Preview fallback metadata for product pages. Product schema comes from WooCommerce native output.', 'airygen-seo' ) }
						</p>
					</div>
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="woocommerce-seo-preview-product">
								{ __( 'Preview product', 'airygen-seo' ) }
							</label>
							<select
								id="woocommerce-seo-preview-product"
								className="airygen-field-select mt-2 w-full"
								value={ selectedPreviewProductId > 0 ? String( selectedPreviewProductId ) : '' }
								onChange={ ( event ) => setSelectedPreviewProductId( Number( event.target.value ) || 0 ) }
							>
								<option value="">
									{ __( 'Select a product', 'airygen-seo' ) }
								</option>
								{ previewProducts.map( ( product ) => (
									<option key={ product.id } value={ String( product.id ) }>
										{ product.title }
									</option>
								) ) }
							</select>
							<p className="mt-2 text-xs text-slate-500">
								{ __( 'Pick a product to load Head Sample and WooCommerce native schema preview.', 'airygen-seo' ) }
							</p>
						</div>
					</div>
					{ previewLoading ? (
						<p className="text-xs text-slate-500">
							{ getLoadingItemLabel( __( 'preview', 'airygen-seo' ) ) }
						</p>
					) : null }
					{ '' !== previewError ? (
						<p className="text-xs text-rose-600">{ previewError }</p>
					) : null }
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="woocommerce-seo-preview-head">
								{ __( 'Head Sample', 'airygen-seo' ) }
							</label>
							<textarea
								id="woocommerce-seo-preview-head"
								rows={ 10 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={ headSample }
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="woocommerce-seo-preview-schema">
								{ __( 'Schema Source', 'airygen-seo' ) }
							</label>
							<textarea
								id="woocommerce-seo-preview-schema"
								rows={ 10 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={ schemaSample }
							/>
						</div>
					</div>
				</section>
			) : null }
		</div>
	);
};

export default WooCommerceSeoTab;

import apiFetch from '@wordpress/api-fetch';
import HeadingIcon from '../../../components/HeadingIcon';
import Toggle from '../../../components/Toggle';
import Input from '../../../components/Input';
import Checkbox from '../../../components/Checkbox';
import TemplateTokenEditor, { type TemplateToken } from '../../../components/TemplateTokenEditor';
import { SitemapIcon } from '../../../components/Icons';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { getLoadingItemLabel } from '../../../../shared/i18nPhrases';
import type { MetaPayload } from '../../../types/api';
import type { TaxonomySeoSettings } from '../../../types/settings';

type TaxonomySeoTabProps = {
	settings: TaxonomySeoSettings;
	meta: MetaPayload;
	onChange: ( next: TaxonomySeoSettings ) => void;
};

type PreviewTermOption = {
	id: number;
	title: string;
};

type TaxonomyPreviewResponse = {
	categories: PreviewTermOption[];
	tags: PreviewTermOption[];
	selectedCategoryId: number;
	selectedTagId: number;
	head: {
		title: string;
		description: string;
		canonical: string;
	};
};

const TaxonomySeoTab = ( { settings, meta, onChange }: TaxonomySeoTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'preview'>( 'settings' );
	const [ previewCategories, setPreviewCategories ] = useState<PreviewTermOption[]>( [] );
	const [ previewTags, setPreviewTags ] = useState<PreviewTermOption[]>( [] );
	const [ selectedCategoryId, setSelectedCategoryId ] = useState<number>( 0 );
	const [ selectedTagId, setSelectedTagId ] = useState<number>( 0 );
	const [ headSample, setHeadSample ] = useState<string>( '' );
	const [ enabledTaxonomiesSample, setEnabledTaxonomiesSample ] = useState<string>( '' );
	const [ previewLoading, setPreviewLoading ] = useState<boolean>( false );
	const [ previewError, setPreviewError ] = useState<string>( '' );

	const updateSettings = ( patch: Partial<TaxonomySeoSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const availableTaxonomies = useMemo(
		() =>
			meta.taxonomies.filter(
				( taxonomy ) =>
					'category' === taxonomy.slug ||
					'post_tag' === taxonomy.slug ||
					( ! taxonomy.slug.startsWith( 'pa_' ) &&
						'product_cat' !== taxonomy.slug &&
						'product_tag' !== taxonomy.slug ),
			),
		[ meta.taxonomies ],
	);

	const templateTokens = useMemo<TemplateToken[]>(
		() => [
			{
				value: '%term_name%',
				label: 'term_name',
				description: __( 'Uses the taxonomy term name.', 'airygen-seo' ),
			},
			{
				value: '%term_description%',
				label: 'term_description',
				description: __( 'Uses the taxonomy term description.', 'airygen-seo' ),
			},
			{
				value: '%site_name%',
				label: 'site_name',
				description: __( 'Uses your site name.', 'airygen-seo' ),
			},
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
			if ( selectedCategoryId > 0 ) {
				params.set( 'category', String( selectedCategoryId ) );
			}
			if ( selectedTagId > 0 ) {
				params.set( 'tag', String( selectedTagId ) );
			}

			const query = params.toString();
			const path = query.length > 0 ? `/airygen/v1/taxonomy/preview?${ query }` : '/airygen/v1/taxonomy/preview';

			apiFetch<TaxonomyPreviewResponse>( { path } )
				.then( ( response ) => {
					if ( cancelled ) {
						return;
					}

					setPreviewCategories( response.categories || [] );
					setPreviewTags( response.tags || [] );
					setSelectedCategoryId( response.selectedCategoryId || 0 );
					setSelectedTagId( response.selectedTagId || 0 );

					const headLines = [
						`<title>${ response.head?.title || '' }</title>`,
						`<meta name="description" content="${ response.head?.description || '' }" />`,
						`<link rel="canonical" href="${ response.head?.canonical || '' }" />`,
					];
					setHeadSample( headLines.join( '\n' ) );
					setEnabledTaxonomiesSample( JSON.stringify( settings.enabledTaxonomies, null, 2 ) );
				} )
				.catch( ( error: unknown ) => {
					if ( cancelled ) {
						return;
					}

					const message =
						error instanceof Error
							? error.message
							: __( 'Unable to load preview.', 'airygen-seo' );
					setPreviewError( message );
					setHeadSample( '' );
					setEnabledTaxonomiesSample( '' );
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
	}, [ activeTab, selectedCategoryId, selectedTagId, settings.enabledTaxonomies ] );

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<SitemapIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Taxonomy SEO', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Configure SEO metadata for category, tag, and custom taxonomy archive pages.',
							'airygen-seo',
						) }
					</div>
				</div>
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
								{ __( 'Enable term-level SEO overrides and choose which taxonomies are managed.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-slate-900">
									{ __( 'Enable Taxonomy SEO', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Taxonomy SEO', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.enabled }
									onChange={ ( value ) => updateSettings( { enabled: value } ) }
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Master switch for taxonomy archive metadata output.', 'airygen-seo' ) }
							</p>
						</div>
						<div>
							<h3 className="mb-3 text-sm font-medium text-gray-800">
								{ __( 'Taxonomies', 'airygen-seo' ) }
							</h3>
							<p className="mt-1 text-sm text-slate-500">
								{ __( 'Select which taxonomy archives should expose custom SEO fields.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-2 md:grid-cols-8">
								{ availableTaxonomies.map( ( taxonomy ) => {
									const checked = settings.enabledTaxonomies.includes( taxonomy.slug );
									return (
										<div
											key={ taxonomy.slug }
											className="rounded-lg border border-slate-200 p-2"
										>
											<Checkbox
												label={ taxonomy.label }
												checked={ checked }
												onChange={ ( value ) => {
													const enabled = new Set( settings.enabledTaxonomies );
													if ( value ) {
														enabled.add( taxonomy.slug );
													} else {
														enabled.delete( taxonomy.slug );
													}
													updateSettings( {
														enabledTaxonomies: Array.from( enabled ),
													} );
												} }
											/>
										</div>
									);
								} ) }
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Templates', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Define fallback title and description templates for taxonomy archives.', 'airygen-seo' ) }
							</p>
						</div>
						<TemplateTokenEditor
							label={ __( 'Default title template', 'airygen-seo' ) }
							description={ __( 'Used when a term has no custom title override.', 'airygen-seo' ) }
							value={ settings.templates.global.title }
							availableTokens={ templateTokens }
							onChange={ ( value ) =>
								updateSettings( {
									templates: {
										...settings.templates,
										global: {
											...settings.templates.global,
											title: value,
										},
									},
								} )
							}
						/>
						<TemplateTokenEditor
							label={ __( 'Default description template', 'airygen-seo' ) }
							description={ __( 'Used when a term has no custom description override.', 'airygen-seo' ) }
							value={ settings.templates.global.description }
							availableTokens={ templateTokens }
							onChange={ ( value ) =>
								updateSettings( {
									templates: {
										...settings.templates,
										global: {
											...settings.templates.global,
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
							{ __( 'Preview fallback metadata output for taxonomy archive pages.', 'airygen-seo' ) }
						</p>
					</div>
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="taxonomy-seo-preview-category">
								{ __( 'Preview category', 'airygen-seo' ) }
							</label>
							<select
								id="taxonomy-seo-preview-category"
								className="airygen-field-select mt-2 w-full"
								value={ selectedCategoryId }
								onChange={ ( event ) => {
									const next = Number( event.currentTarget.value );
									setSelectedCategoryId( Number.isNaN( next ) ? 0 : next );
									setSelectedTagId( 0 );
								} }
							>
								<option value={ 0 }>{ __( 'Select a category', 'airygen-seo' ) }</option>
								{ previewCategories.map( ( term ) => (
									<option key={ term.id } value={ term.id }>
										{ term.title }
									</option>
								) ) }
							</select>
						</div>
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="taxonomy-seo-preview-tag">
								{ __( 'Preview tag', 'airygen-seo' ) }
							</label>
							<select
								id="taxonomy-seo-preview-tag"
								className="airygen-field-select mt-2 w-full"
								value={ selectedTagId }
								onChange={ ( event ) => {
									const next = Number( event.currentTarget.value );
									setSelectedTagId( Number.isNaN( next ) ? 0 : next );
									setSelectedCategoryId( 0 );
								} }
							>
								<option value={ 0 }>{ __( 'Select a tag', 'airygen-seo' ) }</option>
								{ previewTags.map( ( term ) => (
									<option key={ term.id } value={ term.id }>
										{ term.title }
									</option>
								) ) }
							</select>
						</div>
					</div>
					{ previewError ? (
						<p className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
							{ previewError }
						</p>
					) : null }
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="taxonomy-seo-preview-head">
								{ __( 'Head Sample', 'airygen-seo' ) }
							</label>
							<textarea
								id="taxonomy-seo-preview-head"
								rows={ 10 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={ previewLoading ? getLoadingItemLabel( __( 'preview', 'airygen-seo' ) ) : headSample }
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="taxonomy-seo-preview-enabled">
								{ __( 'Enabled Taxonomies', 'airygen-seo' ) }
							</label>
							<textarea
								id="taxonomy-seo-preview-enabled"
								rows={ 10 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={ enabledTaxonomiesSample }
							/>
						</div>
					</div>
				</section>
			) : null }
		</div>
	);
};

export default TaxonomySeoTab;

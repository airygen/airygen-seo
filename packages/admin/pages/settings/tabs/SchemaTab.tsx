/* eslint-disable camelcase */
import Input from '../../../components/Input';
import Select from '../../../components/Select';
import HeadingIcon from '../../../components/HeadingIcon';
import { SchemaMarkupIcon } from '../../../components/Icons';
import Toggle from '../../../components/Toggle';

import { __ } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import type { MetaPayload } from '../../../types/api';
import type { SchemaMarkupSettings } from '../../../types/settings';

type SchemaTabProps = {
	settings: SchemaMarkupSettings;
	meta: MetaPayload;
	onChange: ( next: SchemaMarkupSettings ) => void;
};

const SchemaTab = ( { settings, meta, onChange }: SchemaTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'preview'>( 'settings' );
	const handleChange = ( patch: Partial<SchemaMarkupSettings> ) => {
		onChange( { ...settings, ...patch } );
	};
	const previewJson = useMemo( () => {
		const home = window.location.origin;
		const siteUrl = `${ home }/`;
		const sampleUrl = `${ home }/sample-post/`;
		const graph: Array<Record<string, unknown>> = [];

		if ( settings.visibility.organization && settings.organization_name ) {
			const organization: Record<string, unknown> = {
				'@type': settings.organization_type || 'Organization',
				name: settings.organization_name,
				url: siteUrl,
			};
			if ( settings.organization_logo_url ) {
				organization.logo = {
					'@type': 'ImageObject',
					url: settings.organization_logo_url,
				};
			}
			graph.push( organization );
		}

		if ( settings.visibility.website ) {
			graph.push( {
				'@type': 'WebSite',
				name: settings.organization_name || __( 'Site Name', 'airygen-seo' ),
				url: siteUrl,
				potentialAction: {
					'@type': 'SearchAction',
					target: `${ siteUrl }?s={search_term_string}`,
					'query-input': 'required name=search_term_string',
				},
			} );
		}

		if ( settings.visibility.article ) {
			const article: Record<string, unknown> = {
				'@type': settings.article_type || 'Article',
				headline: __( 'Sample post title', 'airygen-seo' ),
				url: sampleUrl,
				mainEntityOfPage: {
					'@type': 'WebPage',
					'@id': sampleUrl,
				},
				datePublished: '2026-01-01T00:00:00+00:00',
				dateModified: '2026-01-01T00:00:00+00:00',
			};
			if ( settings.article_show_author ) {
				article.author = {
					'@type': 'Person',
					name: __( 'Author Name', 'airygen-seo' ),
				};
			}
			if ( settings.organization_name ) {
				article.publisher = {
					'@type': settings.organization_type || 'Organization',
					name: settings.organization_name,
					...( settings.organization_logo_url
						? {
							logo: {
								'@type': 'ImageObject',
								url: settings.organization_logo_url,
							},
						}
						: {} ),
				};
			}
			graph.push( article );
		}

		if ( settings.visibility.breadcrumb ) {
			graph.push( {
				'@type': 'BreadcrumbList',
				itemListElement: [
					{
						'@type': 'ListItem',
						position: 1,
						name: __( 'Home', 'airygen-seo' ),
						item: siteUrl,
					},
					{
						'@type': 'ListItem',
						position: 2,
						name: __( 'Sample post title', 'airygen-seo' ),
						item: sampleUrl,
					},
				],
			} );
		}

		return JSON.stringify(
			{
				'@context': 'https://schema.org',
				'@graph': graph,
			},
			null,
			2,
		);
	}, [ settings ] );

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<SchemaMarkupIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Schema Markup', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Set global organization details and article defaults used by the Schema markup emitter.',
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
				<div className="space-y-5">
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Organization', 'airygen-seo' ) }
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ __(
									'Set your organization details to help search engines display your brand in rich results.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Organization name', 'airygen-seo' ) }
									help={ __(
										'The name of your business or site shown in structured data.',
										'airygen-seo',
									) }
									value={ settings.organization_name }
									onChange={ ( value ) =>
										handleChange( { organization_name: value } )
									}
								/>
							</div>
							<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
								<Select
									label={ __( 'Organization type', 'airygen-seo' ) }
									value={ settings.organization_type }
									options={ meta.organizationTypes.map( ( option ) => ( {
										label: option,
										value: option,
									} ) ) }
									onChange={ ( value ) =>
										handleChange( { organization_type: value } )
									}
									help={ __(
										'Pick the type that best matches your organization (e.g., Organization, LocalBusiness).',
										'airygen-seo',
									) }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Logo URL', 'airygen-seo' ) }
									help={ __(
										'Used as your brand logo in search results and knowledge panels.',
										'airygen-seo',
									) }
									value={ settings.organization_logo_url }
									onChange={ ( value ) =>
										handleChange( { organization_logo_url: value } )
									}
									placeholder="https://example.com/logo.svg"
								/>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Article', 'airygen-seo' ) }
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ __(
									'Set fallback schema values used when individual posts do not define their own.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Default image ID', 'airygen-seo' ) }
									help={ __(
										'Fallback image used when a post has no featured image. Set to 0 to disable.',
										'airygen-seo',
									) }
									type="number"
									value={
										Number.isFinite( settings.organization_logo_id )
											? String( settings.organization_logo_id )
											: ''
									}
									onChange={ ( value ) =>
										handleChange( {
											organization_logo_id: Number( value ) || 0,
										} )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Select
									label={ __( 'Default article type', 'airygen-seo' ) }
									help={ __(
										'Select the schema type used for most posts (you can override per post type).',
										'airygen-seo',
									) }
									value={ settings.article_type }
									options={ meta.articleTypes.map( ( option ) => ( {
										label: option,
										value: option,
									} ) ) }
									onChange={ ( value ) =>
										handleChange( { article_type: value } )
									}
								/>
							</div>
						</div>
					</section>

					{ meta.schemaPostTypes && meta.schemaPostTypes.length > 0 && (
						<>
							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div>
									<div className="airygen_h2_title">
										{ __( 'Per post type defaults', 'airygen-seo' ) }
									</div>
									<p className="mt-1 text-sm text-slate-500">
										{ __(
											'Choose a schema type for each content type (Post, Page, Media). Leave empty to use the Default article type.',
											'airygen-seo',
										) }
									</p>
								</div>
								<div className="grid gap-4 md:grid-cols-3">
									{ meta.schemaPostTypes.map( ( postType ) => {
										const storageKey = postType.key ?? postType.slug;
										const currentValue =
									settings.post_type_defaults[ storageKey ] ??
									postType.selected ??
									'';

										return (
											<div
												key={ storageKey }
												className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4"
											>
												<Select
													label={ postType.label }
													help={ __(
														'Used when generating schema for this content type.',
														'airygen-seo',
													) }
													value={ currentValue }
													options={ postType.options.map( ( option ) => ( {
														...option,
														value: option.value,
													} ) ) }
													onChange={ ( value ) => {
														const next = {
															...settings.post_type_defaults,
														};

														if ( value === '' ) {
															delete next[ storageKey ];
														} else {
															next[ storageKey ] = value;
														}

														handleChange( {
															post_type_defaults: next,
														} );
													} }
												/>
											</div>
										);
									} ) }
								</div>
							</section>

						</>
					) }

					<div className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="airygen_h2_title">
								{ __( 'Visibility', 'airygen-seo' ) }
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ __(
									'Select which schema graphs Airygen SEO should output for visitors.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Output Organization graph', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Output Organization graph', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.visibility.organization }
										onChange={ ( organization ) =>
											handleChange( {
												visibility: {
													...settings.visibility,
													organization,
												},
											} )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Includes Organization/LocalBusiness JSON-LD based on the details above.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Output Website graph', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Output Website graph', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.visibility.website }
										onChange={ ( website ) =>
											handleChange( {
												visibility: { ...settings.visibility, website },
											} )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Generates a WebSite graph with search action data pulled from your site.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Output Article graph', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Output Article graph', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.visibility.article }
										onChange={ ( article ) =>
											handleChange( {
												visibility: { ...settings.visibility, article },
											} )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Outputs Article/NewsArticle schema using the defaults configured below.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Include author details in Article graph', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __(
											'Include author details in Article graph',
											'airygen-seo',
										) }
										hideLabelText
										checked={ settings.article_show_author }
										onChange={ ( article_show_author ) =>
											handleChange( {
												article_show_author,
											} )
										}
										disabled={ ! settings.visibility.article }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Only applies when the Article graph is enabled.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Only output Article graph for Posts', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __(
											'Only output Article graph for Posts',
											'airygen-seo',
										) }
										hideLabelText
										checked={ settings.article_only_post }
										onChange={ ( article_only_post ) =>
											handleChange( { article_only_post } )
										}
										disabled={ ! settings.visibility.article }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'When enabled, Article/author schema is emitted only for the Post post type. Other post types are excluded.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Output BreadcrumbList graph', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Output BreadcrumbList graph', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.visibility.breadcrumb }
										onChange={ ( breadcrumb ) =>
											handleChange( {
												visibility: {
													...settings.visibility,
													breadcrumb,
												},
											} )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Outputs a BreadcrumbList JSON-LD graph. If Breadcrumbs is enabled, it uses that trail; otherwise it falls back to a simple trail on single posts. Disable to stop schema output.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
					</div>
				</div>
			) : null }
			{ 'preview' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Preview', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'JSON-LD preview generated from current settings. This is the structured data format printed in page source.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="rounded-lg border border-slate-200 p-4">
						<textarea
							readOnly
							rows={ 20 }
							value={ previewJson }
							className="airygen-field w-full font-mono text-xs"
						/>
					</div>
				</section>
			) : null }
		</div>
	);
};

export default SchemaTab;

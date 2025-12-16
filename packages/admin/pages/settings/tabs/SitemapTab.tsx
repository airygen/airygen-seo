import Button from '../../../components/Button';
import Checkbox from '../../../components/Checkbox';
import Select from '../../../components/Select';
import HeadingIcon from '../../../components/HeadingIcon';
import { SitemapIcon } from '../../../components/Icons';

import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { MetaPayload } from '../../../types/api';
import type { SitemapSettings } from '../../../types/settings';

type SitemapTabProps = {
	settings: SitemapSettings;
	meta: MetaPayload;
	onChange: ( next: SitemapSettings ) => void;
	sitemapPreviewUrl: string;
	onCopyPreviewLink: () => void;
};

const SitemapTab = ( {
	settings,
	meta,
	onChange,
	sitemapPreviewUrl,
	onCopyPreviewLink,
}: SitemapTabProps ) => {
	const handleChange = ( patch: Partial<SitemapSettings> ) => {
		onChange( { ...settings, ...patch } );
	};

	const previewInputId = useMemo(
		() => `sitemap-preview-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<SitemapIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Sitemap', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Choose which content types appear in your XML sitemaps and tune pagination.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>
			<div className="space-y-5">
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Scope', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Choose which post types and taxonomies are included in your XML sitemaps.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="space-y-4">
						<div>
							<h3 className="mb-3 text-sm font-medium text-gray-800">
								{ __( 'Post types', 'airygen-seo' ) }
							</h3>
							<div className="grid gap-2 md:grid-cols-8">
								{ meta.postTypes.map( ( postType ) => {
									const checked = settings.enabled_post_types.includes(
										postType.slug,
									);
									return (
										<div
											key={ postType.slug }
											className="rounded-lg border border-slate-200 p-2"
										>
											<Checkbox
												label={ postType.label }
												checked={ checked }
												onChange={ ( value ) => {
													const enabled = new Set(
														settings.enabled_post_types,
													);
													if ( value ) {
														enabled.add( postType.slug );
													} else {
														enabled.delete( postType.slug );
													}
													handleChange( {
														enabled_post_types: Array.from( enabled ),
													} );
												} }
											/>
										</div>
									);
								} ) }
							</div>
						</div>
						<div>
							<h3 className="mb-3 text-sm font-medium text-gray-800">
								{ __( 'Taxonomies', 'airygen-seo' ) }
							</h3>
							<div className="grid gap-2 md:grid-cols-8">
								{ meta.taxonomies.map( ( taxonomy ) => {
									const checked = settings.enabled_taxonomies.includes(
										taxonomy.slug,
									);
									return (
										<div
											key={ taxonomy.slug }
											className="rounded-lg border border-slate-200 p-2"
										>
											<Checkbox
												label={ taxonomy.label }
												checked={ checked }
												onChange={ ( value ) => {
													const enabled = new Set(
														settings.enabled_taxonomies,
													);
													if ( value ) {
														enabled.add( taxonomy.slug );
													} else {
														enabled.delete( taxonomy.slug );
													}
													handleChange( {
														enabled_taxonomies: Array.from( enabled ),
													} );
												} }
											/>
										</div>
									);
								} ) }
							</div>
							<div className="mt-4">
								<Checkbox
									label={ __( 'Exclude empty taxonomies', 'airygen-seo' ) }
									checked={ settings.exclude_empty_taxonomies }
									onChange={ ( value ) =>
										handleChange( { exclude_empty_taxonomies: value } )
									}
								/>
							</div>
						</div>
					</div>
				</section>
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Configuration', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Control sitemap pagination and preview the generated index.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<div className="max-w-xs">
								<Select
									label={ __( 'Items per sitemap page', 'airygen-seo' ) }
									value={ String( settings.items_per_page ) }
									options={ Array.from( { length: 10 }, ( _, index ) => {
										const value = ( index + 1 ) * 500;
										return {
											value: String( value ),
											label: `${ value.toLocaleString() }`,
										};
									} ) }
									onChange={ ( value ) =>
										handleChange( { items_per_page: Number( value ) } )
									}
								/>
								<p className="mt-3 text-xs text-slate-500">
									{ __(
										'Large sites should keep this lower to avoid memory issues on crawl.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<div className="w-full">
								<label
									htmlFor={ previewInputId }
									className="block text-sm font-medium text-gray-800"
								>
									{ __( 'Preview link', 'airygen-seo' ) }
								</label>
								<div className="mt-2 flex gap-2">
									<input
										id={ previewInputId }
										type="text"
										value={ sitemapPreviewUrl }
										readOnly
										className="airygen-field flex-1"
									/>
									<Button
										variant="secondary"
										onClick={ onCopyPreviewLink }
										className="inline-flex items-center gap-1 text-xs"
									>
										{ __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
								<p className="mt-2 text-xs text-slate-500">
									{ __(
										'Open in a new tab to verify the generated sitemap index.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>
	);
};

export default SitemapTab;

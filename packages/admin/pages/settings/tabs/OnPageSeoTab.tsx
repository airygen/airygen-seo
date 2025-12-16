import HeadingIcon from '../../../components/HeadingIcon';
import Toggle from '../../../components/Toggle';
import Input from '../../../components/Input';
import TemplateTokenEditor, { type TemplateToken } from '../../../components/TemplateTokenEditor';
import { OnPageSeoIcon } from '../../../components/Icons';

import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import type {
	OnPageSeoSettings,
	OnPageSeoTemplateGroup,
} from '../../../types/settings';

type PostTypeOption = {
	slug: string;
	label: string;
};

type OnPageSeoTabProps = {
	settings: OnPageSeoSettings;
	postTypes: PostTypeOption[];
	onChange: ( next: OnPageSeoSettings ) => void;
};

const OnPageSeoTab = ( {
	settings,
	postTypes,
	onChange,
}: OnPageSeoTabProps ) => {
	const { output, templates } = settings;
	const templateTokens = useMemo<TemplateToken[]>(
		() => [
			{
				value: '%post_title%',
				label: 'post_title',
				description: __( 'Inserts the post title.', 'airygen-seo' ),
			},
			{
				value: '%post_excerpt%',
				label: 'post_excerpt',
				description: __( 'Inserts the post excerpt.', 'airygen-seo' ),
			},
			{
				value: '%site_name%',
				label: 'site_name',
				description: __( 'Inserts your site name.', 'airygen-seo' ),
			},
			{
				value: '%site_description%',
				label: 'site_description',
				description: __( 'Inserts your site tagline.', 'airygen-seo' ),
			},
			{
				value: '%separator%',
				label: 'separator',
				description: __(
					'Inserts the title separator from the template settings.',
					'airygen-seo',
				),
			},
			{
				value: '%custom_1%',
				label: 'custom_1',
				description: sprintf(
					/* translators: %s is the custom token number. */
					__( 'Uses the value from Custom token %s.', 'airygen-seo' ),
					'1',
				),
			},
			{
				value: '%custom_2%',
				label: 'custom_2',
				description: sprintf(
					/* translators: %s is the custom token number. */
					__( 'Uses the value from Custom token %s.', 'airygen-seo' ),
					'2',
				),
			},
			{
				value: '%custom_3%',
				label: 'custom_3',
				description: sprintf(
					/* translators: %s is the custom token number. */
					__( 'Uses the value from Custom token %s.', 'airygen-seo' ),
					'3',
				),
			},
		],
		[],
	);

	const updateOutput = ( key: keyof typeof output, value: boolean ) => {
		onChange( {
			...settings,
			output: {
				...output,
				[ key ]: value,
			},
		} );
	};

	const updateTemplates = (
		next: OnPageSeoSettings['templates'],
	) => {
		onChange( {
			...settings,
			templates: next,
		} );
	};

	const updateGlobalTemplate = (
		field: keyof OnPageSeoTemplateGroup,
		value: string,
	) => {
		updateTemplates( {
			...templates,
			global: {
				...templates.global,
				[ field ]: value,
			},
		} );
	};

	const updatePostTypeTemplate = (
		slug: string,
		field: keyof OnPageSeoTemplateGroup,
		value: string,
	) => {
		const existing = templates.postTypes[ slug ] ?? {
			title: '',
			description: '',
		};
		const nextValue = {
			...existing,
			[ field ]: value,
		};

		const nextPostTypes = { ...templates.postTypes };
		if (
			nextValue.title.trim() === '' &&
			nextValue.description.trim() === ''
		) {
			delete nextPostTypes[ slug ];
		} else {
			nextPostTypes[ slug ] = nextValue;
		}

		updateTemplates( {
			...templates,
			postTypes: nextPostTypes,
		} );
	};

	const updateCustomToken = (
		key: 'custom1' | 'custom2' | 'custom3',
		value: string,
	) => {
		updateTemplates( {
			...templates,
			customTokens: {
				...templates.customTokens,
				[ key ]: value,
			},
		} );
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<OnPageSeoIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'On-Page SEO', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Control which metadata tags Airygen emits and how template-based defaults are generated.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="space-y-1">
					<div className="airygen_h2_title">
						{ __( 'Head tag output', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __( 'Choose which tags Airygen prints when metadata is available.', 'airygen-seo' ) }
					</div>
				</div>

				<div className="grid gap-4 md:grid-cols-4">
					{ [
						{
							key: 'title' as const,
							label: __( 'Title tag', 'airygen-seo' ),
							help: __( 'Outputs a title tag when a title is available.', 'airygen-seo' ),
						},
						{
							key: 'description' as const,
							label: __( 'Meta description', 'airygen-seo' ),
							help: __( 'Outputs a description tag when a description exists.', 'airygen-seo' ),
						},
						{
							key: 'canonical' as const,
							label: __( 'Canonical URL', 'airygen-seo' ),
							help: __( 'Outputs a canonical URL tag.', 'airygen-seo' ),
						},
						{
							key: 'robots' as const,
							label: __( 'Robots meta', 'airygen-seo' ),
							help: __( 'Outputs a robots tag when custom directives are set.', 'airygen-seo' ),
						},
					].map( ( toggle ) => (
						<div
							key={ toggle.key }
							className="airygen-setting-card__input--normal flex flex-col gap-3 rounded-lg border border-slate-200 p-4"
						>
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-slate-900">
									{ toggle.label }
								</p>
								<Toggle
									label={ toggle.label }
									hideLabelText
									checked={ output[ toggle.key ] }
									onChange={ ( value ) =>
										updateOutput( toggle.key, value )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ toggle.help }
							</p>
						</div>
					) ) }
				</div>
			</section>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="space-y-1">
					<div className="airygen_h2_title">
						{ __( 'Templates', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Set the default title and description templates used when posts are missing custom SEO fields.',
							'airygen-seo',
						) }
					</div>
				</div>

				<TemplateTokenEditor
					label={ __( 'Default title template', 'airygen-seo' ) }
					description={ __(
						'Controls the fallback title when a post has no custom SEO title.',
						'airygen-seo',
					) }
					value={ templates.global.title }
					availableTokens={ templateTokens }
					onChange={ ( value ) => updateGlobalTemplate( 'title', value ) }
				/>
				<TemplateTokenEditor
					label={ __( 'Default description template', 'airygen-seo' ) }
					description={ __(
						'Controls the fallback description when a post has no custom SEO description.',
						'airygen-seo',
					) }
					value={ templates.global.description }
					availableTokens={ templateTokens }
					onChange={ ( value ) =>
						updateGlobalTemplate( 'description', value )
					}
				/>
				<div className="grid gap-4 md:grid-cols-4">
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Separator', 'airygen-seo' ) }
							help={ sprintf(
								/* translators: %s is the template token placeholder wrapped in a code tag. */
								__(
									'Inserted between token values when you add %s. One space is added on both sides automatically.',
									'airygen-seo',
								),
								'<code>%separator%</code>',
							) }
							value={ templates.separator }
							inputClassName="!w-[100px] px-2"
							inputStyle={ { width: '100px' } }
							onChange={ ( value ) =>
								updateTemplates( {
									...templates,
									separator: value,
								} )
							}
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Custom token 1', 'airygen-seo' ) }
							help={ sprintf(
								/* translators: %s is the template token placeholder wrapped in a code tag. */
								__( 'Sets the value used by %s. Leave blank to omit it.', 'airygen-seo' ),
								'<code>%custom_1%</code>',
							) }
							value={ templates.customTokens.custom1 }
							onChange={ ( value ) =>
								updateCustomToken( 'custom1', value )
							}
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Custom token 2', 'airygen-seo' ) }
							help={ sprintf(
								/* translators: %s is the template token placeholder wrapped in a code tag. */
								__( 'Sets the value used by %s. Leave blank to omit it.', 'airygen-seo' ),
								'<code>%custom_2%</code>',
							) }
							value={ templates.customTokens.custom2 }
							onChange={ ( value ) =>
								updateCustomToken( 'custom2', value )
							}
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Custom token 3', 'airygen-seo' ) }
							help={ sprintf(
								/* translators: %s is the template token placeholder wrapped in a code tag. */
								__( 'Sets the value used by %s. Leave blank to omit it.', 'airygen-seo' ),
								'<code>%custom_3%</code>',
							) }
							value={ templates.customTokens.custom3 }
							onChange={ ( value ) =>
								updateCustomToken( 'custom3', value )
							}
						/>
					</div>
				</div>
			</section>

			{ postTypes.length > 0 ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Per post type overrides', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Override the global template for specific post types. Separator and custom tokens reuse the values set above.',
								'airygen-seo',
							) }
						</p>
					</div>

					<div className="grid gap-4">
						{ postTypes.map( ( postType ) => {
							if ( 'product' === postType.slug ) {
								return (
									<div
										key={ postType.slug }
										className="rounded-lg border border-slate-200 p-4"
									>
										<div>
											<p className="text-sm font-medium text-slate-900">
												{ postType.label }
											</p>
											<p className="text-xs text-slate-500">
												{ postType.slug }
											</p>
										</div>
										<p className="mt-4 text-sm text-slate-600">
											{ __( 'Please configure this in WooCommerce SEO settings.', 'airygen-seo' ) }
										</p>
									</div>
								);
							}

							const current =
								templates.postTypes[ postType.slug ] ?? {
									title: '',
									description: '',
								};
							return (
								<div
									key={ postType.slug }
									className="rounded-lg border border-slate-200 p-4"
								>
									<div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
										<div>
											<p className="text-sm font-medium text-slate-900">
												{ postType.label }
											</p>
											<p className="text-xs text-slate-500">
												{ postType.slug }
											</p>
										</div>
									</div>
									<div className="mt-4 grid gap-3">
										<TemplateTokenEditor
											label={ __( 'Title template', 'airygen-seo' ) }
											description={ __(
												'Override the default title template for this post type.',
												'airygen-seo',
											) }
											value={ current.title }
											availableTokens={ templateTokens }
											onChange={ ( value ) =>
												updatePostTypeTemplate(
													postType.slug,
													'title',
													value,
												)
											}
										/>
										<TemplateTokenEditor
											label={ __( 'Description template', 'airygen-seo' ) }
											description={ __(
												'Override the default description template for this post type.',
												'airygen-seo',
											) }
											value={ current.description }
											availableTokens={ templateTokens }
											onChange={ ( value ) =>
												updatePostTypeTemplate(
													postType.slug,
													'description',
													value,
												)
											}
										/>
									</div>
								</div>
							);
						} ) }
					</div>
				</section>
			) : null }
		</div>
	);
};

export default OnPageSeoTab;

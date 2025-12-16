import HeadingIcon from '../../../components/HeadingIcon';
import Input from '../../../components/Input';
import TemplateTokenEditor, { type TemplateToken } from '../../../components/TemplateTokenEditor';
import Toggle from '../../../components/Toggle';
import { AuthorSeoIcon } from '../../../components/Icons';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import type { AuthorSeoSettings } from '../../../types/settings';

type AuthorSeoTabProps = {
	settings: AuthorSeoSettings;
	onChange: ( next: AuthorSeoSettings ) => void;
};

const TEMPLATE_LIMIT = 200;
const SEPARATOR_LIMIT = 10;

const clampTemplate = ( value: string ): string => value.slice( 0, TEMPLATE_LIMIT );
const clampSeparator = ( value: string ): string => value.trim().slice( 0, SEPARATOR_LIMIT );

const AuthorSeoTab = ( { settings, onChange }: AuthorSeoTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'preview'>( 'settings' );

	const updateSettings = ( patch: Partial<AuthorSeoSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const socialProfilesValue = useMemo(
		() => settings.socialProfiles.join( '\n' ),
		[ settings.socialProfiles ],
	);

	const socialProfilesPreview = useMemo( () => {
		if ( 0 === settings.socialProfiles.length ) {
			return '[]';
		}

		return JSON.stringify( settings.socialProfiles, null, 2 );
	}, [ settings.socialProfiles ] );

	const templateTokens = useMemo<TemplateToken[]>(
		() => [
			{
				value: '%author_name%',
				label: 'author_name',
				description: __( 'Uses the author display name.', 'airygen-seo' ),
			},
			{
				value: '%site_name%',
				label: 'site_name',
				description: __( 'Uses the site name.', 'airygen-seo' ),
			},
			{
				value: '%author_bio%',
				label: 'author_bio',
				description: __( 'Uses the author bio.', 'airygen-seo' ),
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

	const customToken1Help = sprintf(
		/* translators: %s is the template token placeholder. */
		__( 'Sets the value used by %s. Leave blank to omit it.', 'airygen-seo' ),
		'%custom_1%',
	);
	const customToken2Help = sprintf(
		/* translators: %s is the template token placeholder. */
		__( 'Sets the value used by %s. Leave blank to omit it.', 'airygen-seo' ),
		'%custom_2%',
	);
	const customToken3Help = sprintf(
		/* translators: %s is the template token placeholder. */
		__( 'Sets the value used by %s. Leave blank to omit it.', 'airygen-seo' ),
		'%custom_3%',
	);
	const separatorHelp = sprintf(
		/* translators: %s is the template token placeholder wrapped in a code tag. */
		__(
			'Inserted between token values when you add %s. One space is added on both sides automatically.',
			'airygen-seo',
		),
		'<code>%separator%</code>',
	);

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<AuthorSeoIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Author SEO', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Configure author archive metadata and schema so search engines better understand content ownership.',
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
								{ __(
									'Set default metadata and schema behavior for author archive pages.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 lg:grid-cols-2">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Enable Author SEO', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Enable Author SEO', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.enabled }
										onChange={ ( value ) => updateSettings( { enabled: value } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Master switch for author archive metadata and schema output.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Noindex author archives', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Noindex author archives', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.noindexAuthorArchives }
										onChange={ ( value ) =>
											updateSettings( { noindexAuthorArchives: value } )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Prevent author archive pages from being indexed.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
						<div className="grid gap-4 lg:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 lg:col-span-3">
								<label className="block text-sm font-medium text-gray-800" htmlFor="author-seo-social-profiles">
									{ __( 'Social profiles', 'airygen-seo' ) }
								</label>
								<textarea
									id="author-seo-social-profiles"
									rows={ 4 }
									className="airygen-field mt-2 w-full"
									value={ socialProfilesValue }
									onChange={ ( event ) =>
										updateSettings( {
											socialProfiles: event.target.value
												.split( '\n' )
												.map( ( value ) => value.trim() )
												.filter( ( value ) => '' !== value ),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'One URL per line.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Template', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Build title and description templates using draggable tokens.',
									'airygen-seo',
								) }
							</p>
						</div>
						<TemplateTokenEditor
							label={ __( 'Title template', 'airygen-seo' ) }
							description={ __(
								'Choose the tokens used to generate author archive titles.',
								'airygen-seo',
							) }
							value={ settings.titleTemplate }
							availableTokens={ templateTokens }
							onChange={ ( value ) =>
								updateSettings( {
									titleTemplate: clampTemplate( value ),
								} )
							}
						/>
						<TemplateTokenEditor
							label={ __( 'Description template', 'airygen-seo' ) }
							description={ __(
								'Choose the tokens used to generate author archive descriptions.',
								'airygen-seo',
							) }
							value={ settings.descriptionTemplate }
							availableTokens={ templateTokens }
							onChange={ ( value ) =>
								updateSettings( {
									descriptionTemplate: clampTemplate( value ),
								} )
							}
						/>
						<div className="grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Separator', 'airygen-seo' ) }
									help={ separatorHelp }
									value={ settings.separator }
									inputClassName="!w-[100px] px-2"
									inputStyle={ { width: '100px' } }
									onChange={ ( value ) =>
										updateSettings( { separator: clampSeparator( value ) } )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Custom token 1', 'airygen-seo' ) }
									help={ customToken1Help }
									value={ settings.customTokens.custom1 }
									onChange={ ( value ) =>
										updateSettings( {
											customTokens: {
												...settings.customTokens,
												custom1: clampTemplate( value ),
											},
										} )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Custom token 2', 'airygen-seo' ) }
									help={ customToken2Help }
									value={ settings.customTokens.custom2 }
									onChange={ ( value ) =>
										updateSettings( {
											customTokens: {
												...settings.customTokens,
												custom2: clampTemplate( value ),
											},
										} )
									}
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Custom token 3', 'airygen-seo' ) }
									help={ customToken3Help }
									value={ settings.customTokens.custom3 }
									onChange={ ( value ) =>
										updateSettings( {
											customTokens: {
												...settings.customTokens,
												custom3: clampTemplate( value ),
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
							{ __( 'Preview the metadata values this module will output.', 'airygen-seo' ) }
						</p>
					</div>
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="author-seo-preview-head">
								{ __( 'Head Sample', 'airygen-seo' ) }
							</label>
							<textarea
								id="author-seo-preview-head"
								rows={ 10 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={ `<title>${ settings.titleTemplate }</title>\n<meta name="description" content="${ settings.descriptionTemplate }" />\n<meta name="robots" content="${ settings.noindexAuthorArchives ? 'noindex,follow' : 'index,follow' }" />` }
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label className="block text-sm font-medium text-gray-800" htmlFor="author-seo-preview-schema">
								{ __( 'Schema Sample', 'airygen-seo' ) }
							</label>
							<textarea
								id="author-seo-preview-schema"
								rows={ 10 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={ JSON.stringify(
									{
										'@context': 'https://schema.org',
										'@type': 'Person',
										sameAs: settings.socialProfiles,
									},
									null,
									2,
								) }
							/>
							<p className="mt-2 text-xs text-slate-500">
								{ socialProfilesPreview }
							</p>
						</div>
					</div>
				</section>
			) : null }
		</div>
	);
};

export default AuthorSeoTab;

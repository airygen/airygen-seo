import Input from '../../../components/Input';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import TemplateTokenEditor, { type TemplateToken } from '../../../components/TemplateTokenEditor';
import { ImageSeoIcon } from '../../../components/Icons';

import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import type { ImageSeoAttributeSettings, ImageSeoSettings } from '../../../types/settings';

type ImageSeoTabProps = {
	settings: ImageSeoSettings;
	onChange: ( value: ImageSeoSettings ) => void;
};

const TEMPLATE_LIMIT = 160;

const clampTemplate = ( value: string ): string =>
	value.slice( 0, TEMPLATE_LIMIT );

const clampSeparator = ( value: string ): string =>
	value.trim().slice( 0, 10 );

const ImageSeoTab = ( { settings, onChange }: ImageSeoTabProps ) => {
	const updateAttribute = (
		key: 'alt' | 'title',
		patch: Partial<ImageSeoAttributeSettings>,
	) => {
		onChange( {
			...settings,
			[ key ]: {
				...settings[ key ],
				...patch,
			},
		} );
	};

	const updateSetting = ( patch: Partial<ImageSeoSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const updateCustomToken = (
		key: 'custom1' | 'custom2' | 'custom3',
		value: string,
	) => {
		updateSetting( {
			customTokens: {
				...settings.customTokens,
				[ key ]: clampTemplate( value ),
			},
		} );
	};

	const templateTokens = useMemo<TemplateToken[]>(
		() => [
			{
				value: '%title%',
				label: 'title',
				description: __( 'Uses the post title.', 'airygen-seo' ),
			},
			{
				value: '%filename%',
				label: 'filename',
				description: __( 'Uses the image file name.', 'airygen-seo' ),
			},
			{
				value: '%image_title%',
				label: 'image_title',
				description: __(
					'Uses the attachment title from the Media Library.',
					'airygen-seo',
				),
			},
			{
				value: '%counter%',
				label: 'counter',
				description: __(
					'Adds a running number for each image.',
					'airygen-seo',
				),
			},
			{
				value: '%focus_keyphase%',
				label: 'focus_keyphase',
				description: __(
					'Uses the focus keyphrase (blank if none is set).',
					'airygen-seo',
				),
			},
			{
				value: '%longtail_keyphase_*%',
				label: 'longtail_keyphase_*',
				description: __(
					'Uses a random long-tail keyphrase (blank if none are set).',
					'airygen-seo',
				),
			},
			{
				value: '%separator%',
				label: 'separator',
				description: __(
					'Uses the separator value configured below.',
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

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<ImageSeoIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Image SEO', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Automatically add missing ALT and TITLE attributes with templated values.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>
			<div className="space-y-5">
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="flex items-center justify-between">
							<div className="airygen_h2_title">
								{ __( 'Add missing alt attributes', 'airygen-seo' ) }
							</div>
							<Toggle
								label={ __( 'Add missing alt attributes', 'airygen-seo' ) }
								hideLabelText
								checked={ settings.alt.enabled }
								onChange={ ( enabled ) =>
									updateAttribute( 'alt', { enabled } )
								}
							/>
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Runtime-only change; stored post content never updates.',
								'airygen-seo',
							) }
						</p>
					</div>
					<TemplateTokenEditor
						label={ __( 'ALT attribute template', 'airygen-seo' ) }
						description={ __(
							'Build the ALT template using tokens only.',
							'airygen-seo',
						) }
						e2eName="image-seo-alt"
						value={ settings.alt.format }
						availableTokens={ templateTokens }
						onChange={ ( format ) =>
							updateAttribute( 'alt', {
								format: clampTemplate( format ),
							} )
						}
					/>
				</section>
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="flex items-center justify-between">
							<div className="airygen_h2_title">
								{ __( 'Add missing title attributes', 'airygen-seo' ) }
							</div>
							<Toggle
								label={ __( 'Add missing title attributes', 'airygen-seo' ) }
								hideLabelText
								checked={ settings.title.enabled }
								onChange={ ( enabled ) =>
									updateAttribute( 'title', { enabled } )
								}
							/>
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Runtime-only change; stored post content never updates.',
								'airygen-seo',
							) }
						</p>
					</div>
					<TemplateTokenEditor
						label={ __( 'TITLE attribute template', 'airygen-seo' ) }
						description={ __(
							'Build the TITLE template using tokens only.',
							'airygen-seo',
						) }
						e2eName="image-seo-title"
						value={ settings.title.format }
						availableTokens={ templateTokens }
						onChange={ ( format ) =>
							updateAttribute( 'title', {
								format: clampTemplate( format ),
							} )
						}
					/>
				</section>
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Template settings', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Define shared tokens used by your image templates.',
								'airygen-seo',
							) }
						</p>
					</div>
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
								value={ settings.separator }
								inputClassName="!w-[100px] px-2"
								inputStyle={ { width: '100px' } }
								onChange={ ( value ) =>
									updateSetting( {
										separator: clampSeparator( value ),
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
								value={ settings.customTokens.custom1 }
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
								value={ settings.customTokens.custom2 }
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
								value={ settings.customTokens.custom3 }
								onChange={ ( value ) =>
									updateCustomToken( 'custom3', value )
								}
							/>
						</div>
					</div>
				</section>
			</div>
		</div>
	);
};

export default ImageSeoTab;

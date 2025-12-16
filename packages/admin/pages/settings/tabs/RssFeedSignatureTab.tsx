import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import { SitemapIcon } from '../../../components/Icons';

import { __ } from '@wordpress/i18n';
import type { RssFeedSignatureSettings } from '../../../types/settings';

type RssFeedSignatureTabProps = {
	settings: RssFeedSignatureSettings;
	onChange: ( next: RssFeedSignatureSettings ) => void;
};

const RssFeedSignatureTab = ( {
	settings,
	onChange,
}: RssFeedSignatureTabProps ) => {
	const handleChange = ( patch: Partial<RssFeedSignatureSettings> ) => {
		onChange( { ...settings, ...patch } );
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<SitemapIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'RSS Feed Signature', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Add branding or attribution snippets to your RSS feed entries.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="space-y-1">
					<div className="airygen_h2_title">
						{ __( 'Settings', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Control whether feed signatures are injected and customize before/after message blocks.',
							'airygen-seo',
						) }
					</div>
				</div>

				<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
					<div className="flex items-center justify-between gap-3">
						<div className="space-y-1 pr-4">
							<p className="text-sm font-medium text-gray-800">
								{ __( 'Enable RSS signature', 'airygen-seo' ) }
							</p>
							<p className="text-xs text-slate-500">
								{ __(
									'When enabled, the signature blocks below are injected into feed content and excerpts.',
									'airygen-seo',
								) }
							</p>
						</div>
						<Toggle
							label={ __( 'Enable RSS signature', 'airygen-seo' ) }
							hideLabelText
							checked={ settings.enabled }
							onChange={ ( value ) => handleChange( { enabled: value } ) }
						/>
					</div>
				</div>

				<div className="grid gap-4 lg:grid-cols-2">
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Textarea
							label={ __( 'Before content signature', 'airygen-seo' ) }
							value={ settings.before_content }
							rows={ 5 }
							onChange={ ( value ) =>
								handleChange( { before_content: value } )
							}
							help={ __(
								'Shown before each feed item. Basic HTML like links, strong, and line breaks is allowed.',
								'airygen-seo',
							) }
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Textarea
							label={ __( 'After content signature', 'airygen-seo' ) }
							value={ settings.after_content }
							rows={ 5 }
							onChange={ ( value ) =>
								handleChange( { after_content: value } )
							}
							help={ __(
								'Shown after each feed item. Use this for attribution, CTA links, or copyright notices.',
								'airygen-seo',
							) }
						/>
					</div>
				</div>
			</section>
		</div>
	);
};

export default RssFeedSignatureTab;

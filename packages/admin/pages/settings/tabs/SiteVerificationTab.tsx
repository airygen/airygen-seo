import Input from '../../../components/Input';
import HeadingIcon from '../../../components/HeadingIcon';
import { SiteVerificationIcon } from '../../../components/Icons';

import { __ } from '@wordpress/i18n';
import type { SiteVerificationSettings } from '../../../types/settings';

type SiteVerificationTabProps = {
	settings: SiteVerificationSettings;
	onChange: ( next: SiteVerificationSettings ) => void;
};

const SiteVerificationTab = ( {
	settings,
	onChange,
}: SiteVerificationTabProps ) => {
	const handleChange = ( patch: Partial<SiteVerificationSettings> ) => {
		onChange( { ...settings, ...patch } );
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<SiteVerificationIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Site Verification', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Add verification tokens so search engines can verify your site ownership.',
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
							'Paste verification strings from each platform. Airygen SEO outputs the corresponding meta tags automatically.',
							'airygen-seo',
						) }
					</div>
				</div>

				<div className="grid gap-4 lg:grid-cols-2">
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Google verification', 'airygen-seo' ) }
							value={ settings.google }
							onChange={ ( value ) => handleChange( { google: value } ) }
							placeholder={ __( 'google-site-verification token', 'airygen-seo' ) }
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Bing verification', 'airygen-seo' ) }
							value={ settings.bing }
							onChange={ ( value ) => handleChange( { bing: value } ) }
							placeholder={ __( 'msvalidate.01 token', 'airygen-seo' ) }
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Yandex verification', 'airygen-seo' ) }
							value={ settings.yandex }
							onChange={ ( value ) => handleChange( { yandex: value } ) }
							placeholder={ __( 'yandex-verification token', 'airygen-seo' ) }
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Baidu verification', 'airygen-seo' ) }
							value={ settings.baidu }
							onChange={ ( value ) => handleChange( { baidu: value } ) }
							placeholder={ __( 'baidu-site-verification token', 'airygen-seo' ) }
						/>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 lg:col-span-2">
						<div className="max-w-xl">
							<Input
								label={ __( 'Pinterest verification', 'airygen-seo' ) }
								value={ settings.pinterest }
								onChange={ ( value ) => handleChange( { pinterest: value } ) }
								placeholder={ __( 'p:domain_verify token', 'airygen-seo' ) }
							/>
						</div>
					</div>
				</div>
			</section>
		</div>
	);
};

export default SiteVerificationTab;

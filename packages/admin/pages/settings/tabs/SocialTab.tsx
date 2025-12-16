import Input from '../../../components/Input';
import Select from '../../../components/Select';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import { SocialCardsIcon } from '../../../components/Icons';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import type { SocialCardsOgSettings, SocialCardsSettings, SocialCardsTwitterSettings } from '../../../types/settings';

type SocialTabProps = {
	settings: SocialCardsSettings;
	onChange: ( next: SocialCardsSettings ) => void;
};

const SocialTab = ( { settings, onChange }: SocialTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'preview'>( 'settings' );
	const { og, twitter } = settings;

	const updateOg = ( patch: Partial<SocialCardsOgSettings> ) => {
		onChange( {
			...settings,
			og: { ...og, ...patch },
		} );
	};

	const updateTwitter = ( patch: Partial<SocialCardsTwitterSettings> ) => {
		onChange( {
			...settings,
			twitter: { ...twitter, ...patch },
		} );
	};

	const showTwitterInheritToggle = og.enabled;
	const showTwitterImageFields =
		! showTwitterInheritToggle || ! twitter.inheritOgImage;
	const previewTags = useMemo( () => {
		const tags: string[] = [];
		const sampleUrl = `${ window.location.origin }/sample-post/`;
		const resolvedOgImage =
			og.defaultImageUrl || ( og.defaultImageId > 0 ? `attachment:${ og.defaultImageId }` : '' );
		const resolvedTwitterImage =
			twitter.inheritOgImage && og.enabled
				? resolvedOgImage
				: twitter.defaultImageUrl || ( twitter.defaultImageId > 0 ? `attachment:${ twitter.defaultImageId }` : '' );

		if ( og.enabled ) {
			tags.push( '<meta property="og:type" content="article" />' );
			tags.push( `<meta property="og:url" content="${ sampleUrl }" />` );
			tags.push( '<meta property="og:title" content="Sample post title" />' );
			tags.push( '<meta property="og:description" content="Sample meta description for social preview." />' );
			if ( resolvedOgImage ) {
				tags.push( `<meta property="og:image" content="${ resolvedOgImage }" />` );
			}
			if ( og.imageWidth > 0 ) {
				tags.push( `<meta property="og:image:width" content="${ og.imageWidth }" />` );
			}
			if ( og.imageHeight > 0 ) {
				tags.push( `<meta property="og:image:height" content="${ og.imageHeight }" />` );
			}
			if ( og.publisherUrl ) {
				tags.push( `<meta property="article:publisher" content="${ og.publisherUrl }" />` );
			}
			if ( og.fbAppId ) {
				tags.push( `<meta property="fb:app_id" content="${ og.fbAppId }" />` );
			}
			if ( og.fbAdmins ) {
				tags.push( `<meta property="fb:admins" content="${ og.fbAdmins }" />` );
			}
			if ( og.domainVerification ) {
				tags.push(
					`<meta property="facebook-domain-verification" content="${ og.domainVerification }" />`,
				);
			}
		}

		if ( twitter.enabled ) {
			tags.push( `<meta name="twitter:card" content="${ twitter.cardType }" />` );
			tags.push( '<meta name="twitter:title" content="Sample post title" />' );
			tags.push( '<meta name="twitter:description" content="Sample meta description for social preview." />' );
			if ( twitter.siteHandle ) {
				tags.push( `<meta name="twitter:site" content="${ twitter.siteHandle }" />` );
			}
			if ( twitter.creatorHandle ) {
				tags.push( `<meta name="twitter:creator" content="${ twitter.creatorHandle }" />` );
			}
			if ( resolvedTwitterImage ) {
				tags.push( `<meta name="twitter:image" content="${ resolvedTwitterImage }" />` );
			}
		}

		return tags.join( '\n' );
	}, [ og, twitter ] );

	return (
		<>
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<SocialCardsIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Social Media Tags', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Provide defaults for social share images and Twitter handles when per-post values are missing.',
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
							<div className="flex items-center justify-between">
								<div className="airygen_h2_title">
									{ __( 'Open Graph', 'airygen-seo' ) }
								</div>
								<Toggle
									label={ __( 'Enable Open Graph', 'airygen-seo' ) }
									hideLabelText
									checked={ og.enabled }
									onChange={ ( enabled ) => {
										updateOg( { enabled } );
										if ( ! enabled && twitter.inheritOgImage ) {
											updateTwitter( { inheritOgImage: false } );
										}
									} }
								/>
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ __(
									'When off, the site outputs no og:* tags. Threads and LinkedIn also rely on Open Graph, so their link previews will be affected.',
									'airygen-seo',
								) }
							</p>
						</div>

						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Default image — URL', 'airygen-seo' ) }
									help={ __(
										'Recommended 1200x630 (1.91:1). Leave empty to fall back to the next available image source.',
										'airygen',
									) }
									value={ og.defaultImageUrl }
									onChange={ ( value ) =>
										updateOg( { defaultImageUrl: value } )
									}
									isUrl
									disabled={ ! og.enabled }
								/>
							</div>
							<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Default image — Attachment ID', 'airygen-seo' ) }
									help={ __(
										'Select an image from the Media Library. If both URL and ID are set, the Attachment ID takes precedence. Set to 0 to disable.',
										'airygen-seo',
									) }
									type="number"
									value={ og.defaultImageId ? String( og.defaultImageId ) : '' }
									onChange={ ( value ) => {
										const parsed = parseInt( value, 10 );
										updateOg( {
											defaultImageId: Number.isFinite( parsed ) && parsed > 0 ? parsed : 0,
										} );
									} }
									disabled={ ! og.enabled }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Publisher URL', 'airygen-seo' ) }
									help={ __(
										'Optional canonical publisher page for social networks.',
										'airygen-seo',
									) }
									value={ og.publisherUrl }
									onChange={ ( value ) => updateOg( { publisherUrl: value } ) }
									disabled={ ! og.enabled }
								/>
							</div>
						</div>

						<div className="space-y-4 md:grid md:grid-cols-3 md:gap-4 md:space-y-0">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label="og:image:width"
									help={ __( 'Optional width to help caching. Suggested value: 1200.', 'airygen-seo' ) }
									type="number"
									value={ og.imageWidth ? String( og.imageWidth ) : '' }
									onChange={ ( value ) => {
										const parsed = parseInt( value, 10 );
										updateOg( {
											imageWidth: Number.isFinite( parsed ) && parsed > 0 ? parsed : 0,
										} );
									} }
									disabled={ ! og.enabled }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label="og:image:height"
									help={ __( 'Optional height to help caching. Suggested value: 630.', 'airygen-seo' ) }
									type="number"
									value={ og.imageHeight ? String( og.imageHeight ) : '' }
									onChange={ ( value ) => {
										const parsed = parseInt( value, 10 );
										updateOg( {
											imageHeight: Number.isFinite( parsed ) && parsed > 0 ? parsed : 0,
										} );
									} }
									disabled={ ! og.enabled }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Domain verification', 'airygen-seo' ) }
									help={ __(
										'Paste the Meta (Facebook/Instagram) Business Manager verification token to prove domain ownership. This helps Meta trust your og: tags and unlocks domain insights.',
										'airygen-seo',
									) }
									value={ og.domainVerification }
									onChange={ ( value ) =>
										updateOg( { domainVerification: value } )
									}
									disabled={ ! og.enabled }
								/>
							</div>
						</div>

						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label="fb:app_id"
									help={ __(
										'Connect your Facebook App for advanced analytics and verification.',
										'airygen-seo',
									) }
									value={ og.fbAppId }
									onChange={ ( value ) => updateOg( { fbAppId: value } ) }
									disabled={ ! og.enabled }
								/>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label="fb:admins"
									help={ __(
										'Comma-separated Facebook admin IDs so those accounts can access Page Insights for your domain. IDs are visible in page source, so only add them if you are fine with that exposure.',
										'airygen-seo',
									) }
									value={ og.fbAdmins }
									onChange={ ( value ) => updateOg( { fbAdmins: value } ) }
									disabled={ ! og.enabled }
								/>
							</div>
						</div>

					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div>
							<div className="flex items-center justify-between">
								<div className="airygen_h2_title">
									{ __( 'Twitter', 'airygen-seo' ) }
								</div>
								<Toggle
									label={ __( 'Enable Twitter Cards', 'airygen-seo' ) }
									hideLabelText
									checked={ twitter.enabled }
									onChange={ ( enabled ) => updateTwitter( { enabled } ) }
								/>
							</div>
							<p className="mt-1 text-sm text-slate-500">
								{ __(
									'When off, the site outputs no twitter:* tags.',
									'airygen-seo',
								) }
							</p>
						</div>
						{ twitter.enabled ? (
							<div className="space-y-4 pt-4">
								<div className="grid gap-4 md:grid-cols-3">
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Select
											label={ __( 'Default card type', 'airygen-seo' ) }
											help={ sprintf(
												/* translators: 1: HTML code tag for summary_large_image, 2: HTML code tag for summary. */
												__(
													'%1$s is recommended (2:1). Use %2$s for compact cards.',
													'airygen',
												),
												'<code>summary_large_image</code>',
												'<code>summary</code>',
											) }
											value={ twitter.cardType }
											onChange={ ( value ) =>
												updateTwitter( {
													cardType:
														value === 'summary' ? 'summary' : 'summary_large_image',
												} )
											}
											disabled={ ! twitter.enabled }
											options={ [
												{ value: 'summary_large_image', label: 'summary_large_image' },
												{ value: 'summary', label: 'summary' },
											] }
										/>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Twitter site handle', 'airygen-seo' ) }
											help={ __(
												'Brand account. Enter with or without @. Will be normalized on save.',
												'airygen-seo',
											) }
											value={ twitter.siteHandle }
											onChange={ ( value ) =>
												updateTwitter( { siteHandle: value } )
											}
											disabled={ ! twitter.enabled }
										/>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Twitter creator handle', 'airygen-seo' ) }
											help={ __(
												'Author/creator account. Enter with or without @. Will be normalized on save.',
												'airygen-seo',
											) }
											value={ twitter.creatorHandle }
											onChange={ ( value ) =>
												updateTwitter( { creatorHandle: value } )
											}
											disabled={ ! twitter.enabled }
										/>
									</div>
								</div>
								{ showTwitterInheritToggle || showTwitterImageFields ? (
									<div className="grid gap-4 md:grid-cols-3">
										{ showTwitterInheritToggle ? (
											<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
												<div className="flex items-center justify-between gap-3">
													<p className="text-sm font-medium text-slate-900">
														{ __( 'Inherit Open Graph image', 'airygen-seo' ) }
													</p>
													<Toggle
														label={ __( 'Inherit Open Graph image', 'airygen-seo' ) }
														hideLabelText
														checked={ twitter.inheritOgImage }
														onChange={ ( inheritOgImage ) =>
															updateTwitter( { inheritOgImage } )
														}
													/>
												</div>
												<p className="text-xs text-slate-500">
													{ __(
														'Always use the Open Graph image for Twitter cards when available. When it is missing, Twitter falls back to no image.',
														'airygen-seo',
													) }
												</p>
											</div>
										) : null }
										{ showTwitterImageFields ? (
											<>
												<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
													<Input
														label={ __( 'Default image — URL', 'airygen-seo' ) }
														value={ twitter.defaultImageUrl }
														onChange={ ( value ) =>
															updateTwitter( {
																defaultImageUrl: value,
															} )
														}
														isUrl
														disabled={ twitter.inheritOgImage && og.enabled }
													/>
												</div>
												<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
													<Input
														label={ __( 'Default image — Attachment ID', 'airygen-seo' ) }
														type="number"
														value={
															twitter.defaultImageId
																? String( twitter.defaultImageId )
																: ''
														}
														onChange={ ( value ) => {
															const parsed = parseInt( value, 10 );
															updateTwitter( {
																defaultImageId:
																Number.isFinite( parsed ) && parsed > 0
																	? parsed
																	: 0,
															} );
														} }
														disabled={ twitter.inheritOgImage && og.enabled }
													/>
												</div>
											</>
										) : null }
									</div>
								) : null }
							</div>
						) : null }
					</section>
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
								'Meta tag preview generated from current settings. This is the format printed in the page source.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="rounded-lg border border-slate-200 p-4">
						<textarea
							readOnly
							rows={ 20 }
							value={ previewTags || '<!-- No social tags enabled -->' }
							className="airygen-field w-full font-mono text-xs"
						/>
					</div>
				</section>
			) : null }
		</>
	);
};

export default SocialTab;

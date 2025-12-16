import Input from '../../../components/Input';
import Select from '../../../components/Select';
import HeadingIcon from '../../../components/HeadingIcon';
import { BreadcrumbsIcon } from '../../../components/Icons';
import Toggle from '../../../components/Toggle';
import Button from '../../../components/Button';
import PreviewDeviceSwitcher from '../../../components/PreviewDeviceSwitcher';
import PreviewDeviceFrame, {
	type PreviewDeviceKind,
} from '../../../components/PreviewDeviceFrame';
import TransparentColorPicker from '../../../components/TransparentColorPicker';
import PreviewCodeSamples from '../../../components/PreviewCodeSamples';
import {
	getBlockSnippetLabel,
	getSnippetCopiedLabel,
	getShortcodeLabel,
	getTemplateFunctionLabel,
	getUnableToCopySnippetLabel,
} from '../../../utils/i18n';

import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import type { BreadcrumbsSettings } from '../../../types/settings';

type BreadcrumbsTabProps = {
	settings: BreadcrumbsSettings;
	onChange: ( next: BreadcrumbsSettings ) => void;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
};

const BreadcrumbsTab = ( {
	settings,
	onChange,
	onCopyToClipboard,
}: BreadcrumbsTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'layout' | 'preview'>( 'settings' );
	const [ previewViewport, setPreviewViewport ] = useState<PreviewDeviceKind>( 'laptop' );

	const PreviewLaptopIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
		<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M1.11068 1.66569H5.55341V4.4424H1.11068V1.66569ZM5.55341 4.99774C5.7007 4.99774 5.84195 4.93923 5.9461 4.83508C6.05025 4.73094 6.10875 4.58968 6.10875 4.4424V1.66569C6.10875 1.35748 5.85885 1.11035 5.55341 1.11035H1.11068C0.802468 1.11035 0.555341 1.35748 0.555341 1.66569V4.4424C0.555341 4.58968 0.61385 4.73094 0.717997 4.83508C0.822144 4.93923 0.963397 4.99774 1.11068 4.99774H0V5.55308H6.6641V4.99774H5.55341Z" fill="currentColor" />
		</svg>
	);

	const PreviewTabletIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
		<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M5.27566 4.99774H1.38827V1.66569H5.27566V4.99774ZM5.831 1.11035H0.832929C0.524715 1.11035 0.277588 1.35748 0.277588 1.66569V4.99774C0.277588 5.14503 0.336097 5.28628 0.440244 5.39043C0.54439 5.49457 0.685644 5.55308 0.832929 5.55308H5.831C5.97829 5.55308 6.11954 5.49457 6.22369 5.39043C6.32783 5.28628 6.38634 5.14503 6.38634 4.99774V1.66569C6.38634 1.35748 6.13644 1.11035 5.831 1.11035Z" fill="currentColor" />
		</svg>
	);

	const PreviewCellphoneIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
		<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
			<path d="M4.72048 5.27542H1.94377V1.38803H4.72048V5.27542ZM4.72048 0.277344H1.94377C1.63555 0.277344 1.38843 0.524471 1.38843 0.832685V5.83076C1.38843 5.97804 1.44694 6.1193 1.55108 6.22344C1.65523 6.32759 1.79648 6.3861 1.94377 6.3861H4.72048C4.86776 6.3861 5.00901 6.32759 5.11316 6.22344C5.21731 6.1193 5.27582 5.97804 5.27582 5.83076V0.832685C5.27582 0.524471 5.02591 0.277344 4.72048 0.277344Z" fill="currentColor" />
		</svg>
	);

	const updateSettings = ( patch: Partial<BreadcrumbsSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const updateHome = ( patch: Partial<BreadcrumbsSettings['home']> ) => {
		updateSettings( {
			home: {
				...settings.home,
				...patch,
			},
		} );
	};

	const updateLabels = (
		patch: Partial<BreadcrumbsSettings['labels']>,
	) => {
		updateSettings( {
			labels: {
				...settings.labels,
				...patch,
			},
		} );
	};

	const updateDisplay = (
		patch: Partial<BreadcrumbsSettings['display']>,
	) => {
		updateSettings( {
			display: {
				...settings.display,
				...patch,
			},
		} );
	};

	const updateStyle = ( patch: Partial<BreadcrumbsSettings['style']> ) => {
		updateSettings( {
			style: {
				...settings.style,
				...patch,
			},
		} );
	};

	const templateTagSnippet =
		"<?php if ( function_exists( 'airygen_the_breadcrumbs' ) ) { airygen_the_breadcrumbs(); } ?>";
	const shortcodeSnippet = '[airygen_breadcrumbs]';
	const blockSnippet = '<!-- wp:airygen/breadcrumb /-->';
	const templateTagId = 'airygen-breadcrumbs-php-snippet';
	const shortcodeId = 'airygen-breadcrumbs-shortcode-snippet';
	const blockId = 'airygen-breadcrumbs-block-snippet';
	const injectedCssId = 'airygen-breadcrumbs-injected-css';
	const previewHtmlId = 'airygen-breadcrumbs-html-sample';
	const styleCss = `.airygen-breadcrumbs__list{display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;}.airygen-breadcrumbs__item{display:inline-flex;align-items:center;}.airygen-breadcrumbs__separator{display:inline-flex;align-items:center;}.airygen-breadcrumbs__link,.airygen-breadcrumbs__text{display:inline-flex;align-items:center;}.airygen-breadcrumbs{font-size:${ settings.style.fontSize }px;color:${ settings.style.textColor };}${
		settings.style.textColor
			? `.airygen-breadcrumbs__separator{color:${ settings.style.textColor };}`
			: ''
	}${
		settings.style.borderWidth > 0
			? `.airygen-breadcrumbs{border:${ settings.style.borderWidth }px solid ${ settings.style.borderColor };}`
			: ''
	}${
		settings.style.padding > 0
			? `.airygen-breadcrumbs{padding:${ settings.style.padding }px;}`
			: ''
	}${
		settings.style.bgColor
			? `.airygen-breadcrumbs{background:${ settings.style.bgColor };}`
			: ''
	}.airygen-breadcrumbs a{color:${ settings.style.linkColor };text-decoration:${
		settings.style.underlineLinks ? 'underline' : 'none'
	};}`;
	const previewItems = [
		...( settings.home.display
			? [ { label: settings.home.label, url: 'https://example.com' } ]
			: [] ),
		{
			label: __( 'Blog', 'airygen-seo' ),
			url: 'https://example.com/blog',
		},
		{
			label: __( 'Example post', 'airygen-seo' ),
			url: '',
		},
	];
	const previewItemsHtml = previewItems
		.map(
			( item, index ) =>
				`<div class="airygen-breadcrumbs__item">${
					item.url
						? `<a href="${ item.url }" class="airygen-breadcrumbs__link">${ item.label }</a>`
						: `<span class="airygen-breadcrumbs__text">${ item.label }</span>`
				}</div>${
					index < previewItems.length - 1
						? `\n    <span class="airygen-breadcrumbs__separator">${ settings.separator }</span>`
						: ''
				}`,
		)
		.join( '\n    ' );
	const previewHtml = `<nav aria-label="${ __( 'Breadcrumbs', 'airygen-seo' ) }" class="airygen-breadcrumbs">\n  <div class="airygen-breadcrumbs__list">\n    ${ settings.prefix ? `<span class="airygen-breadcrumbs__prefix">${ settings.prefix }</span>\n    ` : '' }${ previewItemsHtml }\n  </div>\n</nav>`;

	return (
		<>
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<BreadcrumbsIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Breadcrumbs', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Configure the on-page breadcrumb trail and labels used in Schema.',
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
					data-airygen-e2e="tab-layout"
					className={
						'layout' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'layout' ) }
				>
					{ __( 'Layout', 'airygen-seo' ) }
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
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Settings', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Control which parts of the trail are shown and how separators render.',
								'airygen-seo',
							) }
						</p>
					</div>

					<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
						<div className="flex items-center justify-between gap-3">
							<p className="text-sm font-medium text-gray-800">
								{ __( 'Enable manual breadcrumbs output', 'airygen-seo' ) }
							</p>
							<Toggle
								label={ __( 'Enable manual breadcrumbs output', 'airygen-seo' ) }
								checked={ settings.manualOutputEnabled }
								onChange={ ( value ) =>
									updateSettings( { manualOutputEnabled: value } )
								}
								hideLabelText
							/>
						</div>
						<p className="text-xs text-slate-500">
							{ __(
								'Enable template function, shortcode, and block output for breadcrumb trail rendering.',
								'airygen-seo',
							) }
						</p>
						<div className="mt-3 grid gap-3 md:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
								<label className="text-sm font-semibold text-gray-800" htmlFor={ templateTagId }>
									{ __( 'Template function', 'airygen-seo' ) }
								</label>
								<textarea
									id={ templateTagId }
									value={ templateTagSnippet }
									readOnly
									rows={ 3 }
									className="mt-2 airygen-field w-full font-mono text-xs"
								/>
								<Button
									variant="secondary"
									className="mt-3 text-xs"
									onClick={ () =>
										onCopyToClipboard(
											templateTagSnippet,
											getSnippetCopiedLabel( getTemplateFunctionLabel() ),
											getUnableToCopySnippetLabel(),
										)
									}
								>
									{ __( 'Copy', 'airygen-seo' ) }
								</Button>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
								<label className="text-sm font-semibold text-gray-800" htmlFor={ shortcodeId }>
									{ __( 'Shortcode', 'airygen-seo' ) }
								</label>
								<textarea
									id={ shortcodeId }
									value={ shortcodeSnippet }
									readOnly
									rows={ 3 }
									className="mt-2 airygen-field w-full font-mono text-xs"
								/>
								<Button
									variant="secondary"
									className="mt-3 text-xs"
									onClick={ () =>
										onCopyToClipboard(
											shortcodeSnippet,
											getSnippetCopiedLabel( getShortcodeLabel() ),
											getUnableToCopySnippetLabel(),
										)
									}
								>
									{ __( 'Copy', 'airygen-seo' ) }
								</Button>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
								<label className="text-sm font-semibold text-gray-800" htmlFor={ blockId }>
									{ __( 'Block', 'airygen-seo' ) }
								</label>
								<textarea
									id={ blockId }
									value={ blockSnippet }
									readOnly
									rows={ 3 }
									className="mt-2 airygen-field w-full font-mono text-xs"
								/>
								<Button
									variant="secondary"
									className="mt-3 text-xs"
									onClick={ () =>
										onCopyToClipboard(
											blockSnippet,
											getSnippetCopiedLabel( getBlockSnippetLabel() ),
											getUnableToCopySnippetLabel(),
										)
									}
								>
									{ __( 'Copy', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
					</div>
					<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
						<div className="flex items-center justify-between gap-3">
							<p className="text-sm font-medium text-gray-800">
								{ __( 'Enable automatic breadcrumbs injection', 'airygen-seo' ) }
							</p>
							<Toggle
								label={ __( 'Enable automatic breadcrumbs injection', 'airygen-seo' ) }
								checked={ settings.autoInjectionEnabled }
								onChange={ ( value ) =>
									updateSettings( { autoInjectionEnabled: value } )
								}
								hideLabelText
							/>
						</div>
						<p className="text-xs text-slate-500">
							{ __(
								'Automatically insert breadcrumbs into post content based on the selected position.',
								'airygen-seo',
							) }
						</p>
						<div className="mt-3 grid gap-3 md:grid-cols-3">
							<div className="rounded-lg border border-slate-200 p-3">
								<Select
									label={ __( 'Insert position', 'airygen-seo' ) }
									value={ settings.injectionPosition }
									options={ [
										{ label: __( 'Before content', 'airygen-seo' ), value: 'before_content' },
										{ label: __( 'After content', 'airygen-seo' ), value: 'after_content' },
									] }
									onChange={ ( value ) =>
										updateSettings( {
											injectionPosition:
												value === 'after_content'
													? 'after_content'
													: 'before_content',
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __(
										'Choose where automatic breadcrumb output appears in the main content area.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
					</div>

					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Show current page', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Show current page', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.display.showCurrent }
									onChange={ ( value ) =>
										updateDisplay( { showCurrent: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Include the current page as the final breadcrumb (also affects the JSON-LD breadcrumb list).',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Show pagination', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Show pagination', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.display.showPagination }
									onChange={ ( value ) =>
										updateDisplay( { showPagination: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Append the current page number when viewing paged archives (hidden from schema by default).',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Separator', 'airygen-seo' ) }
								</p>
								<input
									type="text"
									aria-label={ __( 'Separator', 'airygen-seo' ) }
									className="airygen-field airygen-input--compact text-center"
									value={ settings.separator }
									onChange={ ( event ) =>
										updateSettings( {
											separator: event.target.value,
										} )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Shown between each breadcrumb item.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Prefix', 'airygen-seo' ) }
								</p>
								<input
									type="text"
									aria-label={ __( 'Prefix', 'airygen-seo' ) }
									className="airygen-field airygen-input--compact"
									value={ settings.prefix }
									onChange={ ( event ) =>
										updateSettings( {
											prefix: event.target.value,
										} )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Text shown before the breadcrumb trail (e.g., "You are here").',
									'airygen-seo',
								) }
							</p>
						</div>
					</div>

				</section>
			) : null }

			{ 'layout' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="flex items-center gap-2">
							<div className="airygen_h2_title">
								{ __( 'Style', 'airygen-seo' ) }
							</div>
							<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
								{ __( 'Container', 'airygen-seo' ) }
							</span>
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Adjust the breadcrumb container box style.',
								'airygen-seo',
							) }
						</p>
					</div>

					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Border width', 'airygen-seo' ) + ' (px)' }
								type="number"
								min={ 0 }
								max={ 10 }
								value={ String( settings.style.borderWidth ) }
								onChange={ ( value ) =>
									updateStyle( {
										borderWidth: Number.parseInt( value, 10 ) || 0,
									} )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<TransparentColorPicker
								label={ __( 'Border color', 'airygen-seo' ) }
								value={ settings.style.borderColor }
								onChange={ ( value ) =>
									updateStyle( { borderColor: value } )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Padding', 'airygen-seo' ) + ' (px)' }
								type="number"
								min={ 0 }
								max={ 64 }
								value={ String( settings.style.padding ) }
								onChange={ ( value ) =>
									updateStyle( {
										padding: Number.parseInt( value, 10 ) || 0,
									} )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<TransparentColorPicker
								label={ __( 'Container background color', 'airygen-seo' ) }
								value={ settings.style.bgColor }
								onChange={ ( value ) =>
									updateStyle( { bgColor: value } )
								}
							/>
						</div>
					</div>
				</section>
			) : null }

			{ 'layout' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="flex items-center gap-2">
							<div className="airygen_h2_title">
								{ __( 'Style', 'airygen-seo' ) }
							</div>
							<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
								{ __( 'Trail', 'airygen-seo' ) }
							</span>
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Adjust text and link styling for the breadcrumb trail.',
								'airygen-seo',
							) }
						</p>
					</div>

					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Font size', 'airygen-seo' ) + ' (px)' }
								type="number"
								min={ 10 }
								max={ 24 }
								value={ String( settings.style.fontSize ) }
								onChange={ ( value ) =>
									updateStyle( {
										fontSize: Number.parseInt( value, 10 ) || 14,
									} )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<TransparentColorPicker
								label={ __( 'Text color', 'airygen-seo' ) }
								value={ settings.style.textColor }
								onChange={ ( value ) =>
									updateStyle( { textColor: value } )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<TransparentColorPicker
								label={ __( 'Link color', 'airygen-seo' ) }
								value={ settings.style.linkColor }
								onChange={ ( value ) =>
									updateStyle( { linkColor: value } )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Underline links', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Underline links', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.style.underlineLinks }
									onChange={ ( value ) =>
										updateStyle( { underlineLinks: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Enable underline styling for breadcrumb links.',
									'airygen-seo',
								) }
							</p>
						</div>
					</div>
				</section>
			) : null }

			{ 'preview' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="flex items-center justify-between gap-3">
							<div className="airygen_h2_title">
								{ __( 'Preview', 'airygen-seo' ) }
							</div>
							<PreviewDeviceSwitcher
								options={ [
									{ key: 'laptop', label: __( 'Laptop', 'airygen-seo' ), Icon: PreviewLaptopIcon },
									{ key: 'tablet', label: __( 'Tablet', 'airygen-seo' ), Icon: PreviewTabletIcon },
									{ key: 'cellphone', label: __( 'Cellphone', 'airygen-seo' ), Icon: PreviewCellphoneIcon },
								] }
								value={ previewViewport }
								onChange={ ( next ) => setPreviewViewport( next as PreviewDeviceKind ) }
							/>
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'This is how the breadcrumb trail will appear with the current style settings.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
						<style>{ styleCss }</style>
						<PreviewDeviceFrame device={ previewViewport }>
							<div className="w-full min-w-max">
								<nav
									aria-label={ __( 'Breadcrumbs', 'airygen-seo' ) }
									className="airygen-breadcrumbs"
								>
									<div className="airygen-breadcrumbs__list flex flex-wrap items-center gap-2">
										{ settings.prefix ? (
											<span className="airygen-breadcrumbs__prefix">
												{ settings.prefix }
											</span>
										) : null }
										{ previewItems.map( ( item, index ) => (
											<div key={ `crumb-${ item.label }-${ index }` } className="inline-flex items-center gap-2">
												<div className="airygen-breadcrumbs__item">
													{ item.url ? (
														<a href={ item.url } className="airygen-breadcrumbs__link">
															{ item.label }
														</a>
													) : (
														<span className="airygen-breadcrumbs__text">
															{ item.label }
														</span>
													) }
												</div>
												{ index < previewItems.length - 1 ? (
													<span className="airygen-breadcrumbs__separator">
														{ settings.separator }
													</span>
												) : null }
											</div>
										) ) }
									</div>
								</nav>
							</div>
						</PreviewDeviceFrame>
					</div>
					<PreviewCodeSamples
						injectedCss={ styleCss }
						htmlSample={ previewHtml }
						injectedCssId={ injectedCssId }
						htmlSampleId={ previewHtmlId }
					/>
				</section>
			) : null }

			{ 'settings' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Hierarchy', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Fine-tune which links appear in the chain.',
								'airygen-seo',
							) }
						</p>
					</div>

					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Show home link', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Show home link', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.home.display }
									onChange={ ( value ) =>
										updateHome( { display: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Start the trail with your homepage label and URL.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Show blog link on archives', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Show blog link on archives', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.display.showBlog }
									onChange={ ( value ) =>
										updateDisplay( { showBlog: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Include the Posts page before archive items when a static blog page is set.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Show ancestor pages', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Show ancestor pages', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.display.showAncestors }
									onChange={ ( value ) =>
										updateDisplay( { showAncestors: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Add parent pages for hierarchical content types (pages, hierarchical CPTs).',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-gray-800">
									{ __( 'Hide taxonomy parents', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Hide taxonomy parents', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.display.hideTaxonomy }
									onChange={ ( value ) =>
										updateDisplay( { hideTaxonomy: value } )
									}
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Skip parent terms in category/tag hierarchies to keep the trail compact.',
									'airygen-seo',
								) }
							</p>
						</div>
					</div>

				</section>
			) : null }

			{ 'settings' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Labels', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Customize how archives, search results, and 404 pages appear in the trail.',
								'airygen-seo',
							) }
						</p>
					</div>

					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Home label', 'airygen-seo' ) }
								help={ __(
									'Label used for the homepage breadcrumb.',
									'airygen-seo',
								) }
								value={ settings.home.label }
								onChange={ ( value ) =>
									updateHome( { label: value } )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Archive label', 'airygen-seo' ) }
								help={ __(
									'Used for archive pages (category, tag, taxonomy, post type archives).',
									'airygen-seo',
								) }
								value={ settings.labels.archive }
								onChange={ ( value ) =>
									updateLabels( { archive: value } )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Search results label', 'airygen-seo' ) }
								help={ __(
									'Shown when viewing the search results page.',
									'airygen-seo',
								) }
								value={ settings.labels.search }
								onChange={ ( value ) =>
									updateLabels( { search: value } )
								}
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ sprintf(
									/* translators: %s is an HTTP status code and should remain numeric, e.g. 404. */
									__( '%s label', 'airygen-seo' ),
									'404',
								) }
								help={ __(
									'Shown for not found pages (404 responses).',
									'airygen-seo',
								) }
								value={ settings.labels.error }
								onChange={ ( value ) =>
									updateLabels( { error: value } )
								}
							/>
						</div>
					</div>
				</section>
			) : null }

		</>
	);
};

export default BreadcrumbsTab;

import Input from '../../../components/Input';
import Select from '../../../components/Select';
import Toggle from '../../../components/Toggle';
import Checkbox from '../../../components/Checkbox';
import HeadingIcon from '../../../components/HeadingIcon';
import { TocIcon } from '../../../components/Icons';
import Button from '../../../components/Button';
import PreviewDeviceSwitcher from '../../../components/PreviewDeviceSwitcher';
import PreviewDeviceFrame, {
	type PreviewDeviceKind,
} from '../../../components/PreviewDeviceFrame';
import PreviewCodeSamples from '../../../components/PreviewCodeSamples';
import SectionHeaderSettingsCard from '../../../components/SectionHeaderSettingsCard';
import SectionHeaderStyleCards from '../../../components/SectionHeaderStyleCards';
import SectionBodyContainerStyleCard from '../../../components/SectionBodyContainerStyleCard';
import LinkStyleCard from '../../../components/LinkStyleCard';
import ListStyleCard from '../../../components/ListStyleCard';
import {
	getAutomaticInjectionLabel,
	getBlockSnippetLabel,
	getManualInjectionLabel,
	getSnippetCopiedLabel,
	getShortcodeLabel,
	getTemplateFunctionLabel,
	getUnableToCopySnippetLabel,
} from '../../../utils/i18n';

import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { MetaPayload } from '../../../types/api';
import type { TocSettings } from '../../../types/settings';

type TocTabProps = {
	settings: TocSettings;
	meta: MetaPayload;
	onChange: ( next: TocSettings ) => void;
	onCopyToClipboard: ( text: string, success: string, failure: string ) => void;
};

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

const TocTab = ( { settings, meta, onChange, onCopyToClipboard }: TocTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'layout' | 'preview'>( 'settings' );
	const [ previewViewport, setPreviewViewport ] = useState<PreviewDeviceKind>( 'laptop' );
	const updateSettings = ( patch: Partial<TocSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const updateStyle = ( patch: Partial<TocSettings['style']> ) => {
		updateSettings( {
			style: {
				...settings.style,
				...patch,
			},
		} );
	};
	const tocHeaderContainer = useMemo(
		() =>
			settings.style.headerContainer ?? {
				borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
				borderRadius: 0,
				borderStyle: 'solid' as const,
				borderColor: '#a3a3a3',
				paddings: { top: 0, right: 0, bottom: 0, left: 15 },
				bgColor: 'transparent',
				margins: { top: 0, right: 0, bottom: 12, left: 0 },
			},
		[ settings.style.headerContainer ],
	);
	const tocHeaderTitle = useMemo(
		() =>
			settings.style.headerTitle ?? {
				fontStyle: { bold: false, italic: false, underline: false },
				color: '#0f172a',
				fontSize: 18,
			},
		[ settings.style.headerTitle ],
	);
	const tocBodyContainer = useMemo(
		() =>
			settings.style.bodyContainer ?? {
				borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
				paddings: { top: 9, right: 16, bottom: 16, left: 16 },
				margins: { top: 0, right: 0, bottom: 0, left: 0 },
			},
		[ settings.style.bodyContainer ],
	);
	const updateHeaderContainer = (
		patch: Partial<NonNullable<TocSettings['style']['headerContainer']>>,
	) => updateStyle( { headerContainer: { ...tocHeaderContainer, ...patch } } );
	const updateHeaderTitle = (
		patch: Partial<NonNullable<TocSettings['style']['headerTitle']>>,
	) => updateStyle( { headerTitle: { ...tocHeaderTitle, ...patch } } );
	const updateBodyContainer = (
		patch: Partial<NonNullable<TocSettings['style']['bodyContainer']>>,
	) => updateStyle( { bodyContainer: { ...tocBodyContainer, ...patch } } );
	const previewUrl = meta.tocPreviewUrl ?? '';
	const previewId = useMemo(
		() => `toc-preview-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);
	const previewRef = useRef<HTMLIFrameElement | null>( null );
	const previewOrigin = useMemo( () => {
		if ( ! previewUrl ) {
			return window.location.origin;
		}
		try {
			return new URL( previewUrl, window.location.href ).origin;
		} catch {
			return window.location.origin;
		}
	}, [ previewUrl ] );
	const sendPreviewSettings = useCallback( () => {
		if ( ! previewRef.current?.contentWindow || ! previewUrl ) {
			return;
		}
		previewRef.current.contentWindow.postMessage(
			{
				type: 'airygenTocPreview',
				settings: {
					levels: settings.levels,
					position: settings.position,
					title_enabled: settings.titleEnabled,
					title: settings.title,
					min_headings: settings.minHeadings,
					smooth_scroll: settings.smoothScroll,
					anchor_prefix: settings.anchorPrefix,
					add_numbers: settings.addNumbers,
					exclude_headings: settings.excludeHeadings,
					collapse_on_load: settings.collapseOnLoad,
					style: {
						preset: settings.style.preset,
						border_style: settings.style.borderStyle,
						border_color: settings.style.borderColor,
						border_radius: settings.style.borderRadius,
						body_container: {
							border_width_top: tocBodyContainer.borderWidths.top,
							border_width_right: tocBodyContainer.borderWidths.right,
							border_width_bottom: tocBodyContainer.borderWidths.bottom,
							border_width_left: tocBodyContainer.borderWidths.left,
							padding_top: tocBodyContainer.paddings.top,
							padding_right: tocBodyContainer.paddings.right,
							padding_bottom: tocBodyContainer.paddings.bottom,
							padding_left: tocBodyContainer.paddings.left,
							margin_top: tocBodyContainer.margins.top,
							margin_right: tocBodyContainer.margins.right,
							margin_bottom: tocBodyContainer.margins.bottom,
							margin_left: tocBodyContainer.margins.left,
						},
						toc_padding: settings.style.tocPadding,
						link_color: settings.style.linkColor,
						link_size: settings.style.linkSize,
						font_style: {
							bold: settings.style.fontStyle.bold,
							italic: settings.style.fontStyle.italic,
							underline: settings.style.fontStyle.underline,
						},
						bg_color: settings.style.bgColor,
						header_container: {
							border_width_top: tocHeaderContainer.borderWidths.top,
							border_width_right: tocHeaderContainer.borderWidths.right,
							border_width_bottom: tocHeaderContainer.borderWidths.bottom,
							border_width_left: tocHeaderContainer.borderWidths.left,
							border_radius: tocHeaderContainer.borderRadius,
							border_style: tocHeaderContainer.borderStyle,
							border_color: tocHeaderContainer.borderColor,
							padding_top: tocHeaderContainer.paddings.top,
							padding_right: tocHeaderContainer.paddings.right,
							padding_bottom: tocHeaderContainer.paddings.bottom,
							padding_left: tocHeaderContainer.paddings.left,
							bg_color: tocHeaderContainer.bgColor,
							margin_top: tocHeaderContainer.margins.top,
							margin_right: tocHeaderContainer.margins.right,
							margin_bottom: tocHeaderContainer.margins.bottom,
							margin_left: tocHeaderContainer.margins.left,
						},
						header_title: {
							font_style: tocHeaderTitle.fontStyle,
							color: tocHeaderTitle.color,
							font_size: tocHeaderTitle.fontSize,
						},
					},
				},
			},
			previewOrigin,
		);
	}, [
		previewOrigin,
		previewUrl,
		settings.addNumbers,
		settings.anchorPrefix,
		settings.collapseOnLoad,
		settings.excludeHeadings,
		settings.levels,
		settings.minHeadings,
		settings.position,
		settings.smoothScroll,
		settings.style,
		tocBodyContainer,
		tocHeaderContainer,
		tocHeaderTitle,
		settings.title,
		settings.titleEnabled,
	] );
	useEffect( () => {
		sendPreviewSettings();
	}, [ sendPreviewSettings ] );

	const isSameStyle = ( candidate: TocSettings['style'] ) =>
		candidate.preset === settings.style.preset &&
		candidate.borderStyle === settings.style.borderStyle &&
		candidate.borderColor === settings.style.borderColor &&
		candidate.borderRadius === settings.style.borderRadius &&
		candidate.tocPadding === settings.style.tocPadding &&
		candidate.linkColor === settings.style.linkColor &&
		candidate.linkSize === settings.style.linkSize &&
		candidate.fontStyle.bold === settings.style.fontStyle.bold &&
		candidate.fontStyle.italic === settings.style.fontStyle.italic &&
		candidate.fontStyle.underline === settings.style.fontStyle.underline &&
		candidate.bgColor === settings.style.bgColor &&
		( candidate.bodyContainer?.borderWidths.top ?? 1 ) === tocBodyContainer.borderWidths.top &&
		( candidate.bodyContainer?.borderWidths.right ?? 1 ) === tocBodyContainer.borderWidths.right &&
		( candidate.bodyContainer?.borderWidths.bottom ?? 1 ) === tocBodyContainer.borderWidths.bottom &&
		( candidate.bodyContainer?.borderWidths.left ?? 1 ) === tocBodyContainer.borderWidths.left &&
		( candidate.bodyContainer?.paddings.top ?? 16 ) === tocBodyContainer.paddings.top &&
		( candidate.bodyContainer?.paddings.right ?? 16 ) === tocBodyContainer.paddings.right &&
		( candidate.bodyContainer?.paddings.bottom ?? 16 ) === tocBodyContainer.paddings.bottom &&
		( candidate.bodyContainer?.paddings.left ?? 16 ) === tocBodyContainer.paddings.left &&
		( candidate.bodyContainer?.margins.top ?? 0 ) === tocBodyContainer.margins.top &&
		( candidate.bodyContainer?.margins.right ?? 0 ) === tocBodyContainer.margins.right &&
		( candidate.bodyContainer?.margins.bottom ?? 0 ) === tocBodyContainer.margins.bottom &&
		( candidate.bodyContainer?.margins.left ?? 0 ) === tocBodyContainer.margins.left &&
		JSON.stringify( candidate.headerContainer ?? {} ) === JSON.stringify( tocHeaderContainer ) &&
		JSON.stringify( candidate.headerTitle ?? {} ) === JSON.stringify( tocHeaderTitle );

	const styleThemes: Array<{
		id: TocSettings['style']['preset'];
		label: string;
		description: string;
		style: TocSettings['style'];
	}> = [
		{
			id: 'snow-slate',
			label: __( 'Snow Slate', 'airygen-seo' ),
			description: __( 'Neutral white + light gray with crisp slate text.', 'airygen-seo' ),
			style: {
				preset: 'snow-slate',
				borderStyle: 'solid',
				borderColor: '#dddddd',
				borderRadius: 6,
				bodyContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					paddings: { top: 9, right: 16, bottom: 16, left: 16 },
					margins: { top: 0, right: 0, bottom: 0, left: 0 },
				},
				tocPadding: 20,
				linkColor: '#0f172a',
				linkSize: 14,
				fontStyle: {
					bold: false,
					italic: false,
					underline: false,
				},
				bgColor: 'transparent',
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid',
					borderColor: '#a3a3a3',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					bgColor: 'transparent',
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
			},
		},
		{
			id: 'honey-paper',
			label: __( 'Honey Paper', 'airygen-seo' ),
			description: __( 'Soft parchment yellow for warm, readable TOCs.', 'airygen-seo' ),
			style: {
				preset: 'honey-paper',
				borderStyle: 'solid',
				borderColor: '#e4d8aa',
				borderRadius: 6,
				bodyContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					paddings: { top: 18, right: 18, bottom: 18, left: 18 },
					margins: { top: 0, right: 0, bottom: 0, left: 0 },
				},
				tocPadding: 20,
				linkColor: '#5a3926',
				linkSize: 14,
				fontStyle: {
					bold: false,
					italic: false,
					underline: false,
				},
				bgColor: '#fefbf1',
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid',
					borderColor: '#a3a3a3',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					bgColor: 'transparent',
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
			},
		},
		{
			id: 'sky-breeze',
			label: __( 'Sky Breeze', 'airygen-seo' ),
			description: __( 'Airy light blues with calm contrast.', 'airygen-seo' ),
			style: {
				preset: 'sky-breeze',
				borderStyle: 'dotted',
				borderColor: '#93c5fd',
				borderRadius: 6,
				bodyContainer: {
					borderWidths: { top: 2, right: 2, bottom: 2, left: 2 },
					paddings: { top: 18, right: 18, bottom: 18, left: 18 },
					margins: { top: 0, right: 0, bottom: 0, left: 0 },
				},
				tocPadding: 20,
				linkColor: '#4b7db9',
				linkSize: 14,
				fontStyle: {
					bold: false,
					italic: false,
					underline: false,
				},
				bgColor: 'transparent',
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid',
					borderColor: '#4b7db9',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					bgColor: 'transparent',
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
			},
		},
		{
			id: 'mint-calm',
			label: __( 'Mint Calm', 'airygen-seo' ),
			description: __( 'Mint green tone that feels supportive and stable.', 'airygen-seo' ),
			style: {
				preset: 'mint-calm',
				borderStyle: 'dashed',
				borderColor: '#7dcaab',
				borderRadius: 6,
				bodyContainer: {
					borderWidths: { top: 1, right: 1, bottom: 1, left: 1 },
					paddings: { top: 18, right: 18, bottom: 18, left: 18 },
					margins: { top: 0, right: 0, bottom: 0, left: 0 },
				},
				tocPadding: 20,
				linkColor: '#292929',
				linkSize: 14,
				fontStyle: {
					bold: false,
					italic: false,
					underline: false,
				},
				bgColor: '#fafffd',
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid',
					borderColor: '#7dcaab',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					bgColor: 'transparent',
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
			},
		},
		{
			id: 'rose-blush',
			label: __( 'Rose Blush', 'airygen-seo' ),
			description: __( 'Soft rose palette with friendly, approachable presence.', 'airygen-seo' ),
			style: {
				preset: 'rose-blush',
				borderStyle: 'dotted',
				borderColor: '#ebc6ca',
				borderRadius: 6,
				bodyContainer: {
					borderWidths: { top: 5, right: 5, bottom: 5, left: 5 },
					paddings: { top: 20, right: 20, bottom: 20, left: 20 },
					margins: { top: 0, right: 0, bottom: 0, left: 0 },
				},
				tocPadding: 30,
				linkColor: '#881337',
				linkSize: 14,
				fontStyle: {
					bold: false,
					italic: false,
					underline: false,
				},
				bgColor: '#fffafa',
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid',
					borderColor: '#881337',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					bgColor: 'transparent',
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
			},
		},
		{
			id: 'lavender-mist',
			label: __( 'Lavender Mist', 'airygen-seo' ),
			description: __( 'Light violet with a modern AI/creative product vibe.', 'airygen-seo' ),
			style: {
				preset: 'lavender-mist',
				borderStyle: 'solid',
				borderColor: '#c4b5fd',
				borderRadius: 6,
				bodyContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 0 },
					paddings: { top: 16, right: 16, bottom: 16, left: 16 },
					margins: { top: 0, right: 0, bottom: 0, left: 0 },
				},
				tocPadding: 20,
				linkColor: '#472975',
				linkSize: 14,
				fontStyle: {
					bold: false,
					italic: false,
					underline: false,
				},
				bgColor: '#f5f3ff',
				headerContainer: {
					borderWidths: { top: 0, right: 0, bottom: 0, left: 7 },
					borderRadius: 0,
					borderStyle: 'solid',
					borderColor: '#472975',
					paddings: { top: 0, right: 0, bottom: 0, left: 15 },
					bgColor: 'transparent',
					margins: { top: 0, right: 0, bottom: 12, left: 0 },
				},
				headerTitle: {
					fontStyle: { bold: false, italic: false, underline: false },
					color: '#0f172a',
					fontSize: 18,
				},
			},
		},
	];

	const injectedCss = `.airygen-toc-header{display:block;margin:${ tocHeaderContainer.margins.top }px ${ tocHeaderContainer.margins.right }px ${ tocHeaderContainer.margins.bottom }px ${ tocHeaderContainer.margins.left }px;padding:${ tocHeaderContainer.paddings.top }px ${ tocHeaderContainer.paddings.right }px ${ tocHeaderContainer.paddings.bottom }px ${ tocHeaderContainer.paddings.left }px;border-width:${ tocHeaderContainer.borderWidths.top }px ${ tocHeaderContainer.borderWidths.right }px ${ tocHeaderContainer.borderWidths.bottom }px ${ tocHeaderContainer.borderWidths.left }px;border-style:${ tocHeaderContainer.borderStyle };border-color:${ tocHeaderContainer.borderColor };border-radius:${ tocHeaderContainer.borderRadius }px;background:${ tocHeaderContainer.bgColor };color:${ tocHeaderTitle.color };font-size:${ tocHeaderTitle.fontSize }px;font-weight:${ tocHeaderTitle.fontStyle.bold ? '700' : '400' };font-style:${ tocHeaderTitle.fontStyle.italic ? 'italic' : 'normal' };text-decoration:${ tocHeaderTitle.fontStyle.underline ? 'underline' : 'none' };}\n.airygen-toc{background:${ settings.style.bgColor };margin:${ tocBodyContainer.margins.top }px ${ tocBodyContainer.margins.right }px ${ tocBodyContainer.margins.bottom }px ${ tocBodyContainer.margins.left }px;padding:${ tocBodyContainer.paddings.top }px ${ tocBodyContainer.paddings.right }px ${ tocBodyContainer.paddings.bottom }px ${ tocBodyContainer.paddings.left }px;border-width:${ tocBodyContainer.borderWidths.top }px ${ tocBodyContainer.borderWidths.right }px ${ tocBodyContainer.borderWidths.bottom }px ${ tocBodyContainer.borderWidths.left }px;border-style:${ settings.style.borderStyle };border-color:${ settings.style.borderColor };border-radius:${ settings.style.borderRadius }px;}\n.airygen-toc a{color:${ settings.style.linkColor };font-size:${ settings.style.linkSize }px;font-weight:${ settings.style.fontStyle.bold ? '700' : '400' };font-style:${ settings.style.fontStyle.italic ? 'italic' : 'normal' };text-decoration:${ settings.style.fontStyle.underline ? 'underline' : 'none' };}\n.airygen-toc__list{padding:${ settings.style.tocPadding }px;}`;
	const htmlSample = `${
		settings.titleEnabled
			? `<strong class="airygen-toc-header">${
				settings.title || __( 'Table of Contents', 'airygen-seo' )
			}</strong>\n`
			: ''
	}<div class="airygen-toc">\n  <nav class="airygen-toc__nav">\n    <ul class="airygen-toc__list">\n      <li><a href="#section-1">${ __(
		'Section 1',
		'airygen-seo',
	) }</a></li>\n      <li><a href="#section-2">${ __( 'Section 2', 'airygen-seo' ) }</a></li>\n    </ul>\n  </nav>\n</div>`;
	const tocShortcodeSnippet = '[airygen_toc]';
	const tocBlockSnippet = '<!-- wp:airygen/toc /-->';
	const templateFunctionSnippet =
		"<?php if ( function_exists( 'airygen_the_toc' ) ) { airygen_the_toc(); } ?>";
	const tocModuleLabel = __( 'TOC', 'airygen-seo' );
	const manualTocLabel = getManualInjectionLabel( tocModuleLabel );
	const automaticTocLabel = getAutomaticInjectionLabel( tocModuleLabel );

	return (
		<>
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<TocIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Table of Contents', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Generate a navigable outline of headings and control where it appears in your content.',
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
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Settings', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Configure manual output and automatic injection for table of contents.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-slate-900">
									{ manualTocLabel }
								</p>
								<Toggle
									label={ manualTocLabel }
									checked={ settings.manualOutputEnabled }
									onChange={ ( value ) => updateSettings( { manualOutputEnabled: value } ) }
									hideLabelText
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Allows TOC output via template function, shortcode, or block.',
									'airygen-seo',
								) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-3">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
									<label className="text-sm font-semibold text-gray-800" htmlFor="airygen-toc-template-function">
										{ __( 'Template function', 'airygen-seo' ) }
									</label>
									<textarea
										id="airygen-toc-template-function"
										value={ templateFunctionSnippet }
										readOnly
										rows={ 3 }
										className="mt-2 airygen-field w-full font-mono text-xs"
									/>
									<Button
										variant="secondary"
										className="mt-3 text-xs"
										onClick={ () =>
											onCopyToClipboard(
												templateFunctionSnippet,
												getSnippetCopiedLabel( getTemplateFunctionLabel() ),
												getUnableToCopySnippetLabel(),
											)
										}
									>
										{ __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
									<label className="text-sm font-semibold text-gray-800" htmlFor="airygen-toc-shortcode">
										{ __( 'Shortcode', 'airygen-seo' ) }
									</label>
									<textarea
										id="airygen-toc-shortcode"
										value={ tocShortcodeSnippet }
										readOnly
										rows={ 3 }
										className="mt-2 airygen-field w-full font-mono text-xs"
									/>
									<Button
										variant="secondary"
										className="mt-3 text-xs"
										onClick={ () =>
											onCopyToClipboard(
												tocShortcodeSnippet,
												getSnippetCopiedLabel( getShortcodeLabel() ),
												getUnableToCopySnippetLabel(),
											)
										}
									>
										{ __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-3">
									<label className="text-sm font-semibold text-gray-800" htmlFor="airygen-toc-block">
										{ __( 'Block', 'airygen-seo' ) }
									</label>
									<textarea
										id="airygen-toc-block"
										value={ tocBlockSnippet }
										readOnly
										rows={ 3 }
										className="mt-2 airygen-field w-full font-mono text-xs"
									/>
									<Button
										variant="secondary"
										className="mt-3 text-xs"
										onClick={ () =>
											onCopyToClipboard(
												tocBlockSnippet,
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
								<p className="text-sm font-medium text-slate-900">
									{ automaticTocLabel }
								</p>
								<Toggle
									label={ automaticTocLabel }
									checked={ settings.autoInjectionEnabled }
									onChange={ ( value ) => updateSettings( { autoInjectionEnabled: value } ) }
									hideLabelText
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __(
									'Automatically injects TOC into eligible post content.',
									'airygen-seo',
								) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-3">
								<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-3">
									<Select
										label={ __( 'Insert position', 'airygen-seo' ) }
										value={ settings.position }
										options={ [
											{ value: 'before-content', label: __( 'Before content', 'airygen-seo' ) },
											{ value: 'after-first-paragraph', label: __( 'After first paragraph', 'airygen-seo' ) },
										] }
										onChange={ ( value ) =>
											updateSettings( {
												position: value as TocSettings['position'],
											} )
										}
									/>
									<p className="mt-2 text-xs text-slate-500">
										{ __( 'Choose where TOC is inserted when automatic injection is enabled.', 'airygen-seo' ) }
									</p>
								</div>
							</div>
						</div>

						<SectionHeaderSettingsCard
							moduleLabel={ tocModuleLabel }
							enabled={ settings.titleEnabled }
							onEnabledChange={ ( value ) => updateSettings( { titleEnabled: value } ) }
							text={ settings.title }
							onTextChange={ ( value ) => updateSettings( { title: value } ) }
							level={ settings.titleLevel }
							onLevelChange={ ( value ) => updateSettings( { titleLevel: value } ) }
							gridClassName="lg:grid-cols-3"
						/>

					</section>

					<section className="rounded-xl border border-slate-200 bg-white p-4">
						<h3 className="text-lg font-semibold text-gray-800">
							{ __( 'Scope', 'airygen-seo' ) }
						</h3>
						<p className="mt-1 text-sm text-slate-500">
							{ __( 'Choose which post types are eligible for TOC output.', 'airygen-seo' ) }
						</p>
						<div className="mt-4 space-y-3">
							<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
								{ __( 'Post types to include', 'airygen-seo' ) }
							</p>
							<div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
								{ meta.postTypes.map( ( postType ) => {
									const checked = settings.postTypes.includes( postType.slug );
									return (
										<div
											key={ postType.slug }
											className="rounded-lg border border-slate-200 p-3"
										>
											<Checkbox
												label={ postType.label }
												checked={ checked }
												onChange={ ( value ) => {
													const enabled = new Set( settings.postTypes );
													if ( value ) {
														enabled.add( postType.slug );
													} else {
														enabled.delete( postType.slug );
													}
													updateSettings( {
														postTypes: Array.from( enabled ),
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
								{ __( 'Structure', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Choose heading levels and placement.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 lg:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Minimum headings', 'airygen-seo' ) }
									type="number"
									min={ 1 }
									max={ 20 }
									value={ String( settings.minHeadings ) }
									onChange={ ( value ) =>
										updateSettings( { minHeadings: Number( value ) } )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __(
										'Only show the TOC when this many headings are found.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<div className="airygen_h3_title">
									{ __( 'Heading levels', 'airygen-seo' ) }
								</div>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Choose which heading levels are included in the TOC list.', 'airygen-seo' ) }
								</p>
								<div className="mt-3 grid gap-2 md:grid-cols-3">
									{ [ 2, 3, 4 ].map( ( level ) => {
										const checked = settings.levels.includes( level );
										return (
											<div
												key={ level }
												className="rounded-lg border border-slate-200 p-2"
											>
												<Checkbox
													label={ `H${ level }` }
													checked={ checked }
													onChange={ ( value ) => {
														const next = new Set( settings.levels );
														if ( value ) {
															next.add( level );
														} else {
															next.delete( level );
														}
														updateSettings( { levels: Array.from( next ) } );
													} }
												/>
											</div>
										);
									} ) }
								</div>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Behavior', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Control how visitors interact with the TOC links.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Smooth scroll', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Smooth scroll', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.smoothScroll }
										onChange={ ( value ) =>
											updateSettings( { smoothScroll: value } )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Animate scrolling when visitors click TOC links.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Add numbering', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Add numbering', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.addNumbers }
										onChange={ ( value ) =>
											updateSettings( { addNumbers: value } )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Show ordered numbers for each heading item.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Collapse on load', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Collapse on load', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.collapseOnLoad }
										onChange={ ( value ) =>
											updateSettings( { collapseOnLoad: value } )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __(
										'Start with the TOC collapsed for a cleaner page.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Anchors', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Control anchor IDs and exclusions when building the TOC.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="grid gap-4 lg:grid-cols-3">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Anchor prefix', 'airygen-seo' ) }
									value={ settings.anchorPrefix }
									onChange={ ( value ) => updateSettings( { anchorPrefix: value } ) }
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __(
										'Prefix applied to generated heading IDs to avoid clashes.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<label
									className="block text-sm font-medium text-gray-800"
									htmlFor="airygen-toc-exclude-headings"
								>
									{ __( 'Exclude headings', 'airygen-seo' ) }
								</label>
								<textarea
									id="airygen-toc-exclude-headings"
									value={ settings.excludeHeadings }
									onChange={ ( event ) =>
										updateSettings( { excludeHeadings: event.target.value } )
									}
									rows={ 3 }
									className="airygen-field mt-2 w-full"
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __(
										'Separate phrases with commas to skip matching headings.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
					</section>

				</>
			) : null }

			{ 'layout' === activeTab ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
							<div className="space-y-1">
								<div className="airygen_h2_title">
									{ __( 'Themes', 'airygen-seo' ) }
								</div>
								<p className="text-sm text-slate-500">
									{ __(
										'Choose a preset theme as the base style for TOC preview and output.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="grid gap-3 md:grid-cols-3">
							{ styleThemes.map( ( option, index ) => {
								const isActive = isSameStyle( option.style );
								return (
									<div
										key={ `${ option.id }-${ index }` }
										role="button"
										tabIndex={ 0 }
										onClick={ () => {
											updateSettings( { style: option.style } );
										} }
										onKeyDown={ ( event ) => {
											if ( 'Enter' === event.key || ' ' === event.key ) {
												event.preventDefault();
												updateSettings( { style: option.style } );
											}
										} }
										className={ [
											'flex flex-col items-start gap-1 rounded-lg border px-4 py-3 text-sm font-medium',
											isActive
												? 'border-slate-900 text-slate-900'
												: 'border-slate-200 text-slate-600 hover:border-slate-400',
										].join( ' ' ) }
									>
										<span className="text-sm font-semibold">{ option.label }</span>
										<span className="text-xs text-slate-500">{ option.description }</span>
										{ isActive && (
											<span className="text-xs text-slate-500">
												{ __( 'Selected', 'airygen-seo' ) }
											</span>
										) }
									</div>
								);
							} ) }
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Style', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __(
									'Adjust spacing and borders to match your theme.',
									'airygen-seo',
								) }
							</p>
						</div>
						<div className="space-y-4">
							<SectionHeaderStyleCards
								container={ tocHeaderContainer }
								title={ tocHeaderTitle }
								onContainerChange={ updateHeaderContainer }
								onTitleChange={ updateHeaderTitle }
								idPrefix="airygen-toc-header"
								containerMaxBorderWidth={ 20 }
								titleMinFontSize={ 10 }
								titleMaxFontSize={ 40 }
							/>
							<SectionBodyContainerStyleCard
								borderWidths={ tocBodyContainer.borderWidths }
								onBorderWidthsChange={ ( values ) => updateBodyContainer( { borderWidths: values } ) }
								borderRadius={ settings.style.borderRadius }
								onBorderRadiusChange={ ( value ) => updateStyle( { borderRadius: value } ) }
								borderStyle={ settings.style.borderStyle }
								onBorderStyleChange={ ( value ) =>
									updateStyle( {
										borderStyle: value as TocSettings['style']['borderStyle'],
									} )
								}
								borderColor={ settings.style.borderColor }
								onBorderColorChange={ ( value ) => updateStyle( { borderColor: value } ) }
								paddings={ tocBodyContainer.paddings }
								onPaddingsChange={ ( values ) => updateBodyContainer( { paddings: values } ) }
								margins={ tocBodyContainer.margins }
								onMarginsChange={ ( values ) => updateBodyContainer( { margins: values } ) }
								bgColor={ settings.style.bgColor }
								onBgColorChange={ ( value ) => updateStyle( { bgColor: value } ) }
								idPrefix="airygen-toc-body"
								maxBorderWidth={ 20 }
								maxSpacing={ 50 }
								maxRadius={ 50 }
							/>

							<LinkStyleCard
								fontStyle={ settings.style.fontStyle }
								onFontStyleChange={ ( value ) => updateStyle( { fontStyle: value } ) }
								color={ settings.style.linkColor }
								onColorChange={ ( value ) => updateStyle( { linkColor: value } ) }
								fontSize={ settings.style.linkSize }
								onFontSizeChange={ ( value ) => updateStyle( { linkSize: value } ) }
								fontSizeMin={ 10 }
								fontSizeMax={ 22 }
								fontStyleDescription={ __(
									'Choose whether TOC links are bold, italic, or underlined.',
									'airygen-seo',
								) }
								colorDescription={ __( 'Sets the color of TOC links.', 'airygen-seo' ) }
								fontSizeDescription={ __( 'Adjust the font size of TOC links.', 'airygen-seo' ) }
							/>

							<ListStyleCard
								idPrefix="airygen-toc-list"
								indent={ settings.style.tocPadding }
								onIndentChange={ ( value ) => updateStyle( { tocPadding: value } ) }
								indentMax={ 48 }
								indentDescription={ __( 'Padding inside the list of TOC links.', 'airygen-seo' ) }
							/>
						</div>
					</section>
				</>
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
								'This is a simple preview. Actual output is affected by your theme styles. For accurate results, check the real page output.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="space-y-3">
						<div className="overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
							<PreviewDeviceFrame device={ previewViewport }>
								<div className="h-full w-full">
									{ previewUrl ? (
										<iframe
											id={ previewId }
											ref={ previewRef }
											title={ __( 'TOC preview', 'airygen-seo' ) }
											src={ previewUrl }
											onLoad={ () => sendPreviewSettings() }
											className="h-full w-full border-0"
										/>
									) : (
										<p className="p-3 text-sm text-slate-500">
											{ __( 'Preview is unavailable.', 'airygen-seo' ) }
										</p>
									) }
								</div>
							</PreviewDeviceFrame>
						</div>
						<p className="text-xs text-slate-500">
							{ __(
								'This is a simple preview. Actual output is affected by your theme styles. For accurate results, check the real page output.',
								'airygen-seo',
							) }
						</p>
						<PreviewCodeSamples
							injectedCss={ injectedCss }
							htmlSample={ htmlSample }
							injectedCssId="airygen-toc-injected-css"
							htmlSampleId="airygen-toc-html-sample"
						/>
					</div>
				</section>
			) : null }
		</>
	);
};

export default TocTab;

import { __ } from '@wordpress/i18n';
import { Button, RadioControl, TextareaControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import usePostDataField from '../hooks/usePostDataField';
import {
	analyseDescriptionPixels,
	analyseTitlePixels,
} from '../utils/textMetrics';
import {
	getDescriptionFocusMessage,
	getDescriptionLengthStatusMessage,
	getTitleFocusMessage,
	getTitleLengthStatusMessage,
} from '../../shared/seoAnalysisPhrases';
import { decodeUrlPreview } from '../../shared/urlPreview';

const SeoBasicsPanel = () => {
	const [ metaTitle, setMetaTitle ] = usePostDataField( 'title' );
	const [ metaDescription, setMetaDescription ] = usePostDataField( 'description' );
	const [ keyphrase ] = usePostDataField( 'focusKeyphrase' );
	const { permalink, postTitle, postExcerpt } = useSelect( ( select ) => {
		const editor = select( 'core/editor' ) as {
			getPermalink?: () => string;
			getEditedPostAttribute?: ( key: string ) => unknown;
		};
		return {
			permalink: editor.getPermalink ? editor.getPermalink() : '',
			postTitle: ( editor.getEditedPostAttribute?.( 'title' ) as string ) || '',
			postExcerpt: ( editor.getEditedPostAttribute?.( 'excerpt' ) as string ) || '',
		};
	}, [] );

	const [ previewChoice, setPreviewChoice ] = useState<
		'default' | 'custom'
	>( metaTitle?.trim() ? 'custom' : 'default' );

	const defaultTitle = postTitle?.trim() ?? '';
	const defaultDescription = postExcerpt?.trim() ?? '';
	const previewTitle =
		previewChoice === 'custom'
			? metaTitle || ''
			: defaultTitle || __( 'Add a title to see preview', 'airygen-seo' );
	const previewDescription =
		previewChoice === 'custom'
			? metaDescription || ''
			: defaultDescription || __( 'Add a description to see preview', 'airygen-seo' );
	const [ mode, setMode ] = useState< 'preview' | 'custom' >( 'preview' );

	const finalTitle = metaTitle?.trim() ?? '';
	const finalDescription = metaDescription?.trim() ?? '';
	const hasTitle = finalTitle.length > 0;
	const hasDescription = finalDescription.length > 0;

	const selectedTitle = ( previewChoice === 'custom' ? metaTitle : defaultTitle )?.trim() ?? '';
	const selectedDescription =
		( previewChoice === 'custom' ? metaDescription : defaultDescription )?.trim() ?? '';

	const titleAnalysis = useMemo(
		() => analyseTitlePixels( finalTitle ),
		[ finalTitle ],
	);
	const descriptionAnalysis = useMemo(
		() => analyseDescriptionPixels( finalDescription ),
		[ finalDescription ],
	);

	const buildCheckClass = ( status: 'good' | 'warn' | 'bad' ) => {
		let cls = 'airygen-preview-check';
		if ( status === 'good' ) {
			cls += ' airygen-preview-check--good';
		} else if ( status === 'warn' ) {
			cls += ' airygen-preview-check--warn';
		} else {
			cls += ' airygen-preview-check--bad';
		}
		return cls;
	};

	const titleFocusMessage = () => {
		return getTitleFocusMessage( Boolean( keyphrase ), Boolean( titleHasFocus ) );
	};

	const descriptionFocusMessage = () => {
		return getDescriptionFocusMessage(
			Boolean( keyphrase ),
			Boolean( descriptionHasFocus ),
		);
	};
	const checklistTitleAnalysis = useMemo(
		() => analyseTitlePixels( selectedTitle ),
		[ selectedTitle ],
	);
	const checklistDescriptionAnalysis = useMemo(
		() => analyseDescriptionPixels( selectedDescription ),
		[ selectedDescription ],
	);

	const titlePixels = titleAnalysis.pixels;
	const descriptionPixels = descriptionAnalysis.pixels;

	const titleBarStatus = ( () => {
		if ( titlePixels < 250 ) {
			return 'bad';
		}
		if ( titlePixels <= 350 ) {
			return 'warn';
		}
		if ( titlePixels <= 580 ) {
			return 'good';
		}
		return 'bad';
	} )();

	const descriptionBarStatus = ( () => {
		if ( descriptionPixels < 400 ) {
			return 'bad';
		}
		if ( descriptionPixels <= 600 ) {
			return 'warn';
		}
		if ( descriptionPixels <= 920 ) {
			return 'good';
		}
		return 'bad';
	} )();

	const checklistTitleBarStatus = ( () => {
		const pixels = checklistTitleAnalysis.pixels;
		if ( pixels < 250 ) {
			return 'bad';
		}
		if ( pixels <= 350 ) {
			return 'warn';
		}
		if ( pixels <= 580 ) {
			return 'good';
		}
		return 'bad';
	} )();

	const checklistDescriptionBarStatus = ( () => {
		const pixels = checklistDescriptionAnalysis.pixels;
		if ( pixels < 400 ) {
			return 'bad';
		}
		if ( pixels <= 600 ) {
			return 'warn';
		}
		if ( pixels <= 920 ) {
			return 'good';
		}
		return 'bad';
	} )();

	const TITLE_MAX = 580;
	const DESC_MAX = 920;

	const descriptionStatusText = ( () => {
		if ( ! hasDescription ) {
			return '';
		}
		return getDescriptionLengthStatusMessage( descriptionPixels );
	} )();

	const titleStatusText = ( () => {
		if ( ! hasTitle ) {
			return '';
		}
		return getTitleLengthStatusMessage( titlePixels );
	} )();

	const checklistTitleStatusText = ( () => {
		return getTitleLengthStatusMessage( checklistTitleAnalysis.pixels, true );
	} )();

	const checklistDescriptionStatusText = ( () => {
		return getDescriptionLengthStatusMessage(
			checklistDescriptionAnalysis.pixels,
			true,
		);
	} )();

	const titleHasFocus =
		keyphrase &&
		selectedTitle.toLowerCase().includes( keyphrase.toLowerCase() );
	const descriptionHasFocus =
		keyphrase &&
		selectedDescription.toLowerCase().includes( keyphrase.toLowerCase() );

	const countOccurrences = ( text: string, phrase: string ): number => {
		if ( ! phrase ) {
			return 0;
		}

		const escaped = phrase.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		const match = text.match( new RegExp( escaped, 'gi' ) );

		return match ? match.length : 0;
	};

	const titleFocusCount = useMemo(
		() => countOccurrences( selectedTitle, keyphrase ?? '' ),
		[ selectedTitle, keyphrase ],
	);
	const descriptionFocusCount = useMemo(
		() => countOccurrences( selectedDescription, keyphrase ?? '' ),
		[ selectedDescription, keyphrase ],
	);

	const keyphraseStacked =
		!! keyphrase && ( titleFocusCount > 1 || descriptionFocusCount > 1 );

	const allChecklistPass =
		checklistTitleBarStatus === 'good' &&
		checklistDescriptionBarStatus === 'good' &&
		Boolean( keyphrase ) &&
		titleHasFocus &&
		descriptionHasFocus &&
		! keyphraseStacked;

	const PreviewTabIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_preview_tab)">
				<path
					d="M3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H3.60975C3.49591 6.03966 3.38762 5.95358 3.29321 5.85917C3.20158 5.76754 3.12383 5.6648 3.05441 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V2.82701C5.19525 2.87144 5.38407 2.94641 5.55345 3.0547V2.22169L3.88742 0.555664ZM5.63953 5.2483C6.00883 4.66241 5.83112 3.88771 5.25079 3.52119C4.6649 3.15188 3.88742 3.33237 3.52368 3.90993C3.1516 4.49581 3.33208 5.26774 3.91241 5.63704C4.31781 5.89527 4.83428 5.89527 5.24246 5.64259L6.10879 6.49504L6.49475 6.10908L5.63953 5.2483ZM4.5816 5.27607C4.39749 5.27607 4.22093 5.20293 4.09074 5.07275C3.96056 4.94256 3.88742 4.766 3.88742 4.58189C3.88742 4.39778 3.96056 4.22121 4.09074 4.09103C4.22093 3.96085 4.39749 3.88771 4.5816 3.88771C4.76571 3.88771 4.94227 3.96085 5.07246 4.09103C5.20264 4.22121 5.27578 4.39778 5.27578 4.58189C5.27578 4.766 5.20264 4.94256 5.07246 5.07275C4.94227 5.20293 4.76571 5.27607 4.5816 5.27607Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_preview_tab">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	const DefaultTabIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_default_tab)">
				<path d="M5.47015 3.58227L3.88743 5.165H3.24879V4.52635L4.83151 2.94363L5.47015 3.58227ZM6.41423 3.36014C6.41423 3.44344 6.33093 3.52674 6.24763 3.61004L5.55345 4.30422L5.30355 4.05431L6.02549 3.33237L5.85889 3.16577L5.66452 3.36014L5.02588 2.7215L5.63675 2.13839C5.69229 2.08285 5.80336 2.08285 5.88666 2.13839L6.2754 2.52713C6.33093 2.58266 6.33093 2.69373 6.2754 2.77703C6.21986 2.83256 6.16433 2.8881 6.16433 2.94363C6.16433 2.99917 6.21986 3.0547 6.2754 3.11023C6.3587 3.19354 6.442 3.27684 6.41423 3.36014ZM0.833051 5.55374V1.11101H2.77675V2.49936H4.1651V2.91586L4.72044 2.36052V2.22169L3.05442 0.555664H0.833051C0.527614 0.555664 0.27771 0.805568 0.27771 1.11101V5.55374C0.27771 5.85917 0.527614 6.10908 0.833051 6.10908H4.1651C4.47054 6.10908 4.72044 5.85917 4.72044 5.55374H0.833051ZM3.05442 4.74849C2.99888 4.74849 2.94335 4.77626 2.91558 4.77626L2.77675 4.16538H2.36024L1.77713 4.63742L1.94373 3.88771H1.52723L1.24956 5.27607H1.66606L2.47131 4.55412L2.63791 5.19276H2.91558L3.05442 5.165V4.74849Z" fill="black" />
			</g>
			<defs>
				<clipPath id="clip0_default_tab">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	const renderBar = (
		pixels: number,
		max: number,
		status: 'good' | 'warn' | 'bad',
	) => {
		const clamped = Math.min( pixels, max );
		const widthPercent = max > 0 ? ( clamped / max ) * 100 : 0;

		return (
			<div className={ `airygen-progress airygen-progress--${ status }` }>
				<div
					className="airygen-progress__bar"
					style={ { width: `${ widthPercent }%` } }
				/>
			</div>
		);
	};

	return (
		<>
			<div className="airygen-panel-tabs">
				<Button
					variant={ mode === 'preview' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'preview' ) }
					aria-label={ __( 'Preview', 'airygen-seo' ) }
					title={ __( 'Preview', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<PreviewTabIcon />
				</Button>
				<Button
					variant={ mode === 'custom' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'custom' ) }
					aria-label={ __( 'Custom', 'airygen-seo' ) }
					title={ __( 'Custom', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<DefaultTabIcon />
				</Button>
			</div>

			{ mode === 'preview' ? (
				<>
					<div className="airygen-snippet-preview">
						<p className="airygen-snippet-preview__url">
							{ permalink
								? decodeUrlPreview( permalink )
								: __( 'Permalink not available yet', 'airygen-seo' ) }
						</p>
						<span className="airygen-snippet-preview__title">
							{ previewTitle || __( 'Add a title to see preview', 'airygen-seo' ) }
						</span>
						<p className="airygen-snippet-preview__description">
							{ previewDescription ||
								__( 'Add a description to see preview', 'airygen-seo' ) }
						</p>
					</div>
					<RadioControl
						label={ __( 'Source', 'airygen-seo' ) }
						selected={ previewChoice }
						options={ [
							{ label: __( 'Use defaults', 'airygen-seo' ), value: 'default' },
							{ label: __( 'Use custom data', 'airygen-seo' ), value: 'custom' },
						] }
						onChange={ ( value ) =>
							setPreviewChoice( ( value as 'default' | 'custom' ) ?? 'default' )
						}
					/>
					<hr aria-hidden="true" className="airygen-preview-divider" />
					<div className="airygen-preview-checklist">
						<legend className="components-base-control__label airygen-preview-check__legend">
							{ __( 'Checks', 'airygen-seo' ) }
						</legend>
						{ allChecklistPass ? (
							<div
								className="airygen-preview-check airygen-preview-check--good"
								style={ { display: 'flex', alignItems: 'flex-start' } }
							>
								<span
									className="dashicons dashicons-yes"
									aria-hidden="true"
									style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
								/>
								<span style={ { flex: '1 1 auto' } }>
									{ __( 'All snippet checks look good.', 'airygen-seo' ) }
								</span>
							</div>
						) : (
							<>
								<div
									className={ buildCheckClass(
											checklistTitleBarStatus as 'good' | 'warn' | 'bad',
									) }
									style={ { display: 'flex', alignItems: 'flex-start' } }
								>
									<span
										className={
											checklistTitleBarStatus === 'good'
												? 'dashicons dashicons-yes'
												: 'dashicons dashicons-no-alt'
										}
										aria-hidden="true"
										style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
									/>
									<span style={ { flex: '1 1 auto' } }>
										{ checklistTitleStatusText }
									</span>
								</div>
								<div
									className={ buildCheckClass(
											checklistDescriptionBarStatus as 'good' | 'warn' | 'bad',
									) }
									style={ { display: 'flex', alignItems: 'flex-start' } }
								>
									<span
										className={
											checklistDescriptionBarStatus === 'good'
												? 'dashicons dashicons-yes'
												: 'dashicons dashicons-no-alt'
										}
										aria-hidden="true"
										style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
									/>
									<span style={ { flex: '1 1 auto' } }>
										{ checklistDescriptionStatusText }
									</span>
								</div>
								<div
									className={ buildCheckClass(
										titleHasFocus ? 'good' : 'bad',
									) }
									style={ { display: 'flex', alignItems: 'flex-start' } }
								>
									<span
										className={
											titleHasFocus
												? 'dashicons dashicons-yes'
												: 'dashicons dashicons-no-alt'
										}
										aria-hidden="true"
										style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
									/>
									<span style={ { flex: '1 1 auto' } }>
										{ titleFocusMessage() }
									</span>
								</div>
								<div
									className={ buildCheckClass(
										descriptionHasFocus ? 'good' : 'bad',
									) }
									style={ { display: 'flex', alignItems: 'flex-start' } }
								>
									<span
										className={
											descriptionHasFocus
												? 'dashicons dashicons-yes'
												: 'dashicons dashicons-no-alt'
										}
										aria-hidden="true"
										style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
									/>
									<span style={ { flex: '1 1 auto' } }>
										{ descriptionFocusMessage() }
									</span>
								</div>
								{ keyphraseStacked && (
									<div
										className="airygen-preview-check airygen-preview-check--warn"
										style={ { display: 'flex', alignItems: 'flex-start' } }
									>
										<span
											className="dashicons dashicons-no-alt"
											aria-hidden="true"
											style={ { flex: '0 0 18px', marginRight: '8px', marginTop: '2px' } }
										/>
										<span style={ { flex: '1 1 auto' } }>
											{ __(
												'Avoid repeating the focus keyphrase multiple times in meta title or description.',
												'airygen-seo',
											) }
										</span>
									</div>
								) }
							</>
						) }
					</div>
				</>
			) : (
				<>
					<TextControl
						label={ __( 'Meta Title', 'airygen-seo' ) }
						value={ metaTitle }
						onChange={ setMetaTitle }
					/>
					{ renderBar( titlePixels, TITLE_MAX, titleBarStatus ) }
					<p className="airygen-field-helper">
						{ titleStatusText && <>{ titleStatusText } </> }
						{ __( 'Current length:', 'airygen-seo' ) } { Math.round( titlePixels ) }px
					</p>
					<TextareaControl
						label={ __( 'Meta Description', 'airygen-seo' ) }
						value={ metaDescription }
						onChange={ setMetaDescription }
					/>
					{ renderBar( descriptionPixels, DESC_MAX, descriptionBarStatus ) }
					<p className="airygen-field-helper">
						{ descriptionStatusText && <>{ descriptionStatusText } </> }
						{ __( 'Current length:', 'airygen-seo' ) } { Math.round( descriptionPixels ) }px
					</p>
				</>
			) }
		</>
	);
};

export default SeoBasicsPanel;

import { __, sprintf } from '@wordpress/i18n';
import { getNoGlobalRobotsDefaultsConfiguredYetLabel } from '../../shared/i18nPhrases';
import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
	RadioControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { getEditorConfig } from '../config';
import usePostDataField from '../hooks/usePostDataField';

const ROBOTS_EXTRA_OPTIONS = [
	{ key: 'noarchive', label: __( 'Prevent caching', 'airygen-seo' ) },
	{ key: 'nosnippet', label: __( 'Hide snippets', 'airygen-seo' ) },
	{
		key: 'noimageindex',
		label: __( 'Block image indexing', 'airygen-seo' ),
	},
	{
		key: 'notranslate',
		label: __( 'Disable translation prompts', 'airygen-seo' ),
	},
] as const;

const KNOWN_ROBOTS_TOKENS = new Set(
	[ 'index', 'noindex', 'follow', 'nofollow', ...ROBOTS_EXTRA_OPTIONS.map( ( opt ) => opt.key ) ].map(
		( token ) => token.toLowerCase(),
	),
);

const parseRobotsTokens = ( value: string ): string[] =>
	value
		.split( ',' )
		.map( ( token ) => token.trim() )
		.filter( Boolean );

type IndexChoice = '' | 'index' | 'noindex';
type FollowChoice = '' | 'follow' | 'nofollow';
type MaxImagePreviewChoice = '' | 'none' | 'standard' | 'large';
type MaxVideoPreviewChoice = '' | '-1' | '0' | '30' | '60';

const formatRobotsValue = (
	index: IndexChoice,
	follow: FollowChoice,
	extra: Set< string >,
	custom: string,
	maxImagePreview: MaxImagePreviewChoice,
	maxVideoPreview: MaxVideoPreviewChoice,
): string => {
	const tokens: string[] = [];

	if ( index ) {
		tokens.push( index );
	}

	if ( follow ) {
		tokens.push( follow );
	}

	tokens.push( ...Array.from( extra ) );

	if ( maxImagePreview ) {
		tokens.push( `max-image-preview:${ maxImagePreview }` );
	}

	if ( maxVideoPreview ) {
		tokens.push( `max-video-preview:${ maxVideoPreview }` );
	}

	const customTokens = parseRobotsTokens( custom ).filter(
		( token ) => ! KNOWN_ROBOTS_TOKENS.has( token.toLowerCase() ),
	);

	return [ ...tokens, ...customTokens ].join( ', ' );
};

const RobotsPanel = () => {
	const defaultDirective =
		getEditorConfig().robots?.default_directive?.trim() ?? '';
	const [ robotsValue, setRobotsValue, hasLoaded ] = usePostDataField( 'robots' );
	const [ indexChoice, setIndexChoice ] = useState< IndexChoice >( '' );
	const [ followChoice, setFollowChoice ] = useState< FollowChoice >( '' );
	const [ extras, setExtras ] = useState< Set< string > >( new Set() );
	const [ custom, setCustom ] = useState( '' );
	const [ mode, setMode ] = useState< 'global' | 'custom' >( 'global' );
	const [ sourceChoice, setSourceChoice ] = useState< 'default' | 'custom' >( 'default' );
	const [ maxImagePreview, setMaxImagePreview ] =
		useState< MaxImagePreviewChoice >( '' );
	const [ maxVideoPreview, setMaxVideoPreview ] =
		useState< MaxVideoPreviewChoice >( '' );
	const previewValue = formatRobotsValue(
		indexChoice,
		followChoice,
		extras,
		custom,
		maxImagePreview,
		maxVideoPreview,
	);

	useEffect( () => {
		setSourceChoice( robotsValue.trim() ? 'custom' : 'default' );
		if ( ! hasLoaded ) {
			return;
		}

		const source = robotsValue.trim() || defaultDirective;
		const tokens = parseRobotsTokens( source );
		let nextIndex: IndexChoice = '';
		let nextFollow: FollowChoice = '';
		const nextExtras = new Set< string >();
		let nextImagePreview: MaxImagePreviewChoice = '';
		let nextVideoPreview: MaxVideoPreviewChoice = '';
		const customTokens: string[] = [];

		tokens.forEach( ( rawToken ) => {
			const token = rawToken.toLowerCase();
			if ( token === 'index' || token === 'noindex' ) {
				nextIndex = token;
				return;
			}

			if ( token === 'follow' || token === 'nofollow' ) {
				nextFollow = token;
				return;
			}

			if ( token.startsWith( 'max-image-preview:' ) ) {
				const value = token.split( ':' )[ 1 ] ?? '';
				if ( value === 'none' || value === 'standard' || value === 'large' ) {
					nextImagePreview = value as MaxImagePreviewChoice;
					return;
				}
			}

			if ( token.startsWith( 'max-video-preview:' ) ) {
				const value = token.split( ':' )[ 1 ] ?? '';
				if ( value === '-1' || value === '0' || value === '30' || value === '60' ) {
					nextVideoPreview = value as MaxVideoPreviewChoice;
					return;
				}
			}

			if ( KNOWN_ROBOTS_TOKENS.has( token ) ) {
				nextExtras.add( token );
				return;
			}

			customTokens.push( rawToken );
		} );

		setIndexChoice( nextIndex );
		setFollowChoice( nextFollow );
		setExtras( nextExtras );
		setCustom( customTokens.join( ', ' ) );
		setMaxImagePreview( nextImagePreview );
		setMaxVideoPreview( nextVideoPreview );
	}, [ robotsValue, defaultDirective, hasLoaded ] );

	const updateMetaFromState = (
		nextIndex: IndexChoice = indexChoice,
		nextFollow: FollowChoice = followChoice,
		nextExtras: Set< string > = extras,
		nextCustom: string = custom,
		nextImagePreview: MaxImagePreviewChoice = maxImagePreview,
		nextVideoPreview: MaxVideoPreviewChoice = maxVideoPreview,
	) => {
		const value = formatRobotsValue(
			nextIndex,
			nextFollow,
			nextExtras,
			nextCustom,
			nextImagePreview,
			nextVideoPreview,
		);
		setRobotsValue( value.trim() );
	};

	const toggleExtra = ( key: string, checked: boolean ) => {
		const next = new Set( extras );
		if ( checked ) {
			next.add( key );
		} else {
			next.delete( key );
		}
		setExtras( next );
		updateMetaFromState(
			indexChoice,
			followChoice,
			next,
			custom,
			maxImagePreview,
			maxVideoPreview,
		);
	};

	const clearOverride = () => {
		setIndexChoice( '' );
		setFollowChoice( '' );
		setExtras( new Set() );
		setCustom( '' );
		setMaxImagePreview( '' );
		setMaxVideoPreview( '' );
		setRobotsValue( '' );
		setSourceChoice( 'default' );
	};

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
			<g clipPath="url(#clip0_preview_robots)">
				<path
					d="M3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H3.60975C3.49591 6.03966 3.38762 5.95358 3.29321 5.85917C3.20158 5.76754 3.12383 5.6648 3.05441 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V2.82701C5.19525 2.87144 5.38407 2.94641 5.55345 3.0547V2.22169L3.88742 0.555664ZM5.63953 5.2483C6.00883 4.66241 5.83112 3.88771 5.25079 3.52119C4.6649 3.15188 3.88742 3.33237 3.52368 3.90993C3.1516 4.49581 3.33208 5.26774 3.91241 5.63704C4.31781 5.89527 4.83428 5.89527 5.24246 5.64259L6.10879 6.49504L6.49475 6.10908L5.63953 5.2483ZM4.5816 5.27607C4.39749 5.27607 4.22093 5.20293 4.09074 5.07275C3.96056 4.94256 3.88742 4.766 3.88742 4.58189C3.88742 4.39778 3.96056 4.22121 4.09074 4.09103C4.22093 3.96085 4.39749 3.88771 4.5816 3.88771C4.76571 3.88771 4.94227 3.96085 5.07246 4.09103C5.20264 4.22121 5.27578 4.39778 5.27578 4.58189C5.27578 4.766 5.20264 4.94256 5.07246 5.07275C4.94227 5.20293 4.76571 5.27607 4.5816 5.27607Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_preview_robots">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	const CustomTabIcon = () => (
		<svg
			width="20"
			height="20"
			viewBox="0 0 7 7"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<g clipPath="url(#clip0_custom_robots)">
				<path
					d="M5.47015 3.58227L3.88743 5.165H3.24879V4.52635L4.83151 2.94363L5.47015 3.58227ZM6.41423 3.36014C6.41423 3.44344 6.33093 3.52674 6.24763 3.61004L5.55345 4.30422L5.30355 4.05431L6.02549 3.33237L5.85889 3.16577L5.66452 3.36014L5.02588 2.7215L5.63675 2.13839C5.69229 2.08285 5.80336 2.08285 5.88666 2.13839L6.2754 2.52713C6.33093 2.58266 6.33093 2.69373 6.2754 2.77703C6.21986 2.83256 6.16433 2.8881 6.16433 2.94363C6.16433 2.99917 6.21986 3.0547 6.2754 3.11023C6.3587 3.19354 6.442 3.27684 6.41423 3.36014ZM0.833051 5.55374V1.11101H2.77675V2.49936H4.1651V2.91586L4.72044 2.36052V2.22169L3.05442 0.555664H0.833051C0.527614 0.555664 0.27771 0.805568 0.27771 1.11101V5.55374C0.27771 5.85917 0.527614 6.10908 0.833051 6.10908H4.1651C4.47054 6.10908 4.72044 5.85917 4.72044 5.55374H0.833051ZM3.05442 4.74849C2.99888 4.74849 2.94335 4.77626 2.91558 4.77626L2.77675 4.16538H2.36024L1.77713 4.63742L1.94373 3.88771H1.52723L1.24956 5.27607H1.66606L2.47131 4.55412L2.63791 5.19276H2.91558L3.05442 5.165V4.74849Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_custom_robots">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	return (
		<div className="airygen-robots-panel">
			<div className="airygen-panel-tabs">
				<Button
					variant={ mode === 'global' ? 'primary' : 'secondary' }
					onClick={ () => {
						clearOverride();
					} }
					aria-label={ __( 'Preview robots directive', 'airygen-seo' ) }
					title={ __( 'Preview robots directive', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<PreviewTabIcon />
				</Button>
				<Button
					variant={ mode === 'custom' ? 'primary' : 'secondary' }
					onClick={ () => setMode( 'custom' ) }
					aria-label={ __( 'Custom robots directive', 'airygen-seo' ) }
					title={ __( 'Custom robots directive', 'airygen-seo' ) }
					className="airygen-component-button"
				>
					<CustomTabIcon />
				</Button>
			</div>
			{ 'global' === mode && (
				<>
					<div className="airygen-robots-panel__preview">
						<div className="airygen-robots-panel__preview-value">
							{ defaultDirective
								? sprintf(
									/* translators: %s: Global robots directive applied by default. */
									__( 'Current default: %s', 'airygen-seo' ),
									defaultDirective,
								)
								: getNoGlobalRobotsDefaultsConfiguredYetLabel() }
						</div>
					</div>
					<RadioControl
						label={ __( 'Source', 'airygen-seo' ) }
						selected={ sourceChoice }
						options={ [
							{ label: __( 'Use defaults', 'airygen-seo' ), value: 'default' },
							{ label: __( 'Use custom data', 'airygen-seo' ), value: 'custom' },
						] }
						onChange={ ( value ) => {
							const next = ( value as 'default' | 'custom' ) ?? 'default';
							setSourceChoice( next );
							if ( next === 'default' ) {
								clearOverride();
							}
						} }
					/>
				</>
			) }
			{ 'custom' === mode && (
				<>
					<SelectControl
						label={ __( 'Indexing directive', 'airygen-seo' ) }
						value={ indexChoice }
						options={ [
							{ label: __( 'Inherit default', 'airygen-seo' ), value: '' },
							{ label: __( 'Index', 'airygen-seo' ), value: 'index' },
							{ label: __( 'Noindex', 'airygen-seo' ), value: 'noindex' },
						] }
						onChange={ ( next ) => {
							const coercion = ( next as IndexChoice ) || '';
							setIndexChoice( coercion );
							updateMetaFromState(
								coercion,
								followChoice,
								extras,
								custom,
								maxImagePreview,
								maxVideoPreview,
							);
						} }
					/>
					<SelectControl
						label={ __( 'Link following directive', 'airygen-seo' ) }
						value={ followChoice }
						options={ [
							{ label: __( 'Inherit default', 'airygen-seo' ), value: '' },
							{ label: __( 'Follow', 'airygen-seo' ), value: 'follow' },
							{ label: __( 'Nofollow', 'airygen-seo' ), value: 'nofollow' },
						] }
						onChange={ ( next ) => {
							const coercion = ( next as FollowChoice ) || '';
							setFollowChoice( coercion );
							updateMetaFromState(
								indexChoice,
								coercion,
								extras,
								custom,
								maxImagePreview,
								maxVideoPreview,
							);
						} }
					/>
					<SelectControl
						label={ __( 'Max image preview', 'airygen-seo' ) }
						value={ maxImagePreview }
						options={ [
							{
								label: __( 'Inherit default', 'airygen-seo' ),
								value: '',
							},
							{
								label: __( 'None', 'airygen-seo' ),
								value: 'none',
							},
							{
								label: __( 'Standard', 'airygen-seo' ),
								value: 'standard',
							},
							{
								label: __( 'Large', 'airygen-seo' ),
								value: 'large',
							},
						] }
						onChange={ ( next ) => {
							const value = ( next as MaxImagePreviewChoice ) || '';
							setMaxImagePreview( value );
							updateMetaFromState(
								indexChoice,
								followChoice,
								extras,
								custom,
								value,
								maxVideoPreview,
							);
						} }
					/>
					<SelectControl
						label={ __( 'Max video preview', 'airygen-seo' ) }
						value={ maxVideoPreview }
						options={ [
							{
								label: __( 'Inherit default', 'airygen-seo' ),
								value: '',
							},
							{
								label: __( 'Disable previews (0 seconds)', 'airygen-seo' ),
								value: '0',
							},
							{
								label: `30 ${ __( 'seconds', 'airygen-seo' ) }`,
								value: '30',
							},
							{
								label: `60 ${ __( 'seconds', 'airygen-seo' ) }`,
								value: '60',
							},
							{
								label: __( 'No limit (-1)', 'airygen-seo' ),
								value: '-1',
							},
						] }
						onChange={ ( next ) => {
							const value = ( next as MaxVideoPreviewChoice ) || '';
							setMaxVideoPreview( value );
							updateMetaFromState(
								indexChoice,
								followChoice,
								extras,
								custom,
								maxImagePreview,
								value,
							);
						} }
					/>
					<div className="airygen-robots-panel__extras">
						{ ROBOTS_EXTRA_OPTIONS.map( ( option ) => (
							<CheckboxControl
								key={ option.key }
								label={ option.label }
								checked={ extras.has( option.key ) }
								onChange={ ( checked ) => toggleExtra( option.key, checked ) }
							/>
						) ) }
					</div>
					<TextControl
						label={ __( 'Custom directives (comma-separated)', 'airygen-seo' ) }
						value={ custom }
						onChange={ ( next ) => {
							setCustom( next );
							updateMetaFromState(
								indexChoice,
								followChoice,
								extras,
								next,
								maxImagePreview,
								maxVideoPreview,
							);
						} }
					/>
					<div className="airygen-robots-panel__preview">
						<span className="airygen-robots-panel__preview-label">
							{ __( 'Preview', 'airygen-seo' ) }
						</span>
						<div className="airygen-robots-panel__preview-value">
							{ previewValue || __( 'No directives yet', 'airygen-seo' ) }
						</div>
					</div>
					<Button
						variant="secondary"
						onClick={ clearOverride }
						className="airygen-component-button"
					>
						{ __( 'Use site-wide default', 'airygen-seo' ) }
					</Button>
				</>
			) }
		</div>
	);
};

export default RobotsPanel;

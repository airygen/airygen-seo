/* eslint-disable camelcase */
import Button from '../../../components/Button';
import Input from '../../../components/Input';
import Select from '../../../components/Select';
import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import { RobotsIcon } from '../../../components/Icons';

import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';

import { __ } from '@wordpress/i18n';
import type { RobotsSettings } from '../../../types/settings';

type IndexChoice = '' | 'index' | 'noindex';
type FollowChoice = '' | 'follow' | 'nofollow';
type MaxImagePreviewChoice = '' | 'none' | 'standard' | 'large';
type MaxVideoPreviewChoice = '' | '0' | '30' | '60' | '-1';

const EXTRA_DIRECTIVE_KEYS = [ 'noarchive', 'nosnippet', 'noimageindex', 'notranslate' ] as const;

const KNOWN_DIRECTIVES = new Set(
	[ 'index', 'noindex', 'follow', 'nofollow', ...EXTRA_DIRECTIVE_KEYS ].map(
		( key ) => key.toLowerCase(),
	),
);

const parseTokens = ( value: string ): string[] =>
	value
		.split( ',' )
		.map( ( token ) => token.trim() )
		.filter( Boolean );

const formatRobotsValue = (
	index: IndexChoice,
	follow: FollowChoice,
	extras: Set<string>,
	custom: string,
	maxImagePreview: MaxImagePreviewChoice,
	maxVideoPreview: MaxVideoPreviewChoice,
): string => {
	const pieces: string[] = [];

	if ( index ) {
		pieces.push( index );
	}

	if ( follow ) {
		pieces.push( follow );
	}

	pieces.push( ...Array.from( extras ) );

	if ( maxImagePreview ) {
		pieces.push( `max-image-preview:${ maxImagePreview }` );
	}

	if ( maxVideoPreview ) {
		pieces.push( `max-video-preview:${ maxVideoPreview }` );
	}

	const customTokens = parseTokens( custom ).filter(
		( token ) => ! KNOWN_DIRECTIVES.has( token.toLowerCase() ),
	);

	pieces.push( ...customTokens );

	return pieces.join( ', ' );
};

type RobotsTabProps = {
	settings: RobotsSettings;
	onChange: ( next: RobotsSettings ) => void;
	robotsPreviewUrl: string;
	onCopyToClipboard: (
		value: string,
		successMessage: string,
		failureMessage: string,
	) => void;
};

const RobotsTab = ( {
	settings,
	onChange,
	robotsPreviewUrl,
	onCopyToClipboard,
}: RobotsTabProps ) => {
	const [ indexChoice, setIndexChoice ] = useState<IndexChoice>( 'index' );
	const [ followChoice, setFollowChoice ] = useState<FollowChoice>( 'follow' );
	const hasAppliedDefaults = useRef( false );
	const [ extraDirectives, setExtraDirectives ] = useState<Set<string>>(
		new Set(),
	);
	const [ customDirectives, setCustomDirectives ] = useState( '' );
	const [ maxImagePreview, setMaxImagePreview ] =
		useState<MaxImagePreviewChoice>( '' );
	const [ maxVideoPreview, setMaxVideoPreview ] =
		useState<MaxVideoPreviewChoice>( '' );
	const extraDirectiveOptions = useMemo(
		() => [
			{
				key: 'noarchive',
				label: 'noarchive',
				description: __(
					'Stops search engines from showing cached copies of your pages.',
					'airygen-seo',
				),
			},
			{
				key: 'nosnippet',
				label: 'nosnippet',
				description: __(
					'Prevents search results from showing text snippets under your title.',
					'airygen-seo',
				),
			},
			{
				key: 'noimageindex',
				label: 'noimageindex',
				description: __(
					'Stops images on this site from appearing in image search results.',
					'airygen-seo',
				),
			},
			{
				key: 'notranslate',
				label: 'notranslate',
				description: __(
					'Prevents search engines from offering automatic translation for this page.',
					'airygen-seo',
				),
			},
		],
		[],
	);
	const maxImagePreviewOptions = useMemo(
		() => [
			{ value: '', label: __( 'Use default', 'airygen-seo' ) },
			{ value: 'large', label: __( 'Large (best quality)', 'airygen-seo' ) },
			{ value: 'standard', label: __( 'Standard', 'airygen-seo' ) },
			{ value: 'none', label: __( 'None', 'airygen-seo' ) },
		],
		[],
	);
	const maxVideoPreviewOptions = useMemo(
		() => [
			{ value: '', label: __( 'Use default', 'airygen-seo' ) },
			{ value: '-1', label: __( 'Unlimited (no cap)', 'airygen-seo' ) },
			{ value: '30', label: `30 ${ __( 'seconds', 'airygen-seo' ) }` },
			{ value: '60', label: `60 ${ __( 'seconds', 'airygen-seo' ) }` },
			{ value: '0', label: __( 'Disable video preview', 'airygen-seo' ) },
		],
		[],
	);

	const handleChange = useCallback(
		( patch: Partial<RobotsSettings> ) => {
			onChange( { ...settings, ...patch } );
		},
		[ onChange, settings ],
	);

	useEffect( () => {
		const currentValue = settings.default_directive ?? '';
		const trimmedValue = currentValue.trim();
		const tokens = parseTokens( currentValue );
		const shouldUseFallback = trimmedValue === '' && ! hasAppliedDefaults.current;

		let nextIndex: IndexChoice = shouldUseFallback ? 'index' : '';
		let nextFollow: FollowChoice = shouldUseFallback ? 'follow' : '';
		const nextExtras = new Set<string>();
		let nextMaxImagePreview: MaxImagePreviewChoice = '';
		let nextMaxVideoPreview: MaxVideoPreviewChoice = '';
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
					nextMaxImagePreview = value as MaxImagePreviewChoice;
					return;
				}
			}

			if ( token.startsWith( 'max-video-preview:' ) ) {
				const value = token.split( ':' )[ 1 ] ?? '';
				if ( value === '0' || value === '30' || value === '60' || value === '-1' ) {
					nextMaxVideoPreview = value as MaxVideoPreviewChoice;
					return;
				}
			}

			if ( KNOWN_DIRECTIVES.has( token ) ) {
				nextExtras.add( token );
				return;
			}

			customTokens.push( rawToken );
		} );

		const customValue = customTokens.join( ', ' );

		setIndexChoice( nextIndex );
		setFollowChoice( nextFollow );
		setExtraDirectives( nextExtras );
		setCustomDirectives( customValue );
		setMaxImagePreview( nextMaxImagePreview );
		setMaxVideoPreview( nextMaxVideoPreview );

		if ( trimmedValue !== '' ) {
			hasAppliedDefaults.current = true;
		}

		if ( shouldUseFallback ) {
			const fallbackValue = formatRobotsValue(
				nextIndex,
				nextFollow,
				nextExtras,
				customValue,
				nextMaxImagePreview,
				nextMaxVideoPreview,
			);

			if ( fallbackValue !== currentValue ) {
				hasAppliedDefaults.current = true;
				handleChange( { default_directive: fallbackValue } );
			}
		}
	}, [ settings.default_directive, handleChange ] );

	const updateDirectiveValue = (
		nextIndex: IndexChoice = indexChoice,
		nextFollow: FollowChoice = followChoice,
		nextExtras: Set<string> = extraDirectives,
		nextCustom: string = customDirectives,
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
		handleChange( { default_directive: value } );
	};

	const handleIndexChange = ( value: string ) => {
		const coerced = ( value as IndexChoice ) || '';
		setIndexChoice( coerced );
		updateDirectiveValue(
			coerced,
			followChoice,
			extraDirectives,
			customDirectives,
			maxImagePreview,
			maxVideoPreview,
		);
	};

	const handleFollowChange = ( value: string ) => {
		const coerced = ( value as FollowChoice ) || '';
		setFollowChoice( coerced );
		updateDirectiveValue(
			indexChoice,
			coerced,
			extraDirectives,
			customDirectives,
			maxImagePreview,
			maxVideoPreview,
		);
	};

	const handleExtraToggle = ( key: string, checked: boolean ) => {
		const next = new Set( extraDirectives );
		if ( checked ) {
			next.add( key );
		} else {
			next.delete( key );
		}
		setExtraDirectives( next );
		updateDirectiveValue(
			indexChoice,
			followChoice,
			next,
			customDirectives,
			maxImagePreview,
			maxVideoPreview,
		);
	};

	const handleCustomChange = ( value: string ) => {
		setCustomDirectives( value );
		updateDirectiveValue(
			indexChoice,
			followChoice,
			extraDirectives,
			value,
			maxImagePreview,
			maxVideoPreview,
		);
	};

	const directivesPreview = useMemo(
		() =>
			formatRobotsValue(
				indexChoice,
				followChoice,
				extraDirectives,
				customDirectives,
				maxImagePreview,
				maxVideoPreview,
			),
		[
			indexChoice,
			followChoice,
			extraDirectives,
			customDirectives,
			maxImagePreview,
			maxVideoPreview,
		],
	);
	const directivesPreviewInputId = useMemo(
		() => `robots-directives-preview-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);
	const previewInputId = useMemo(
		() => `robots-preview-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<RobotsIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Robots Control', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Set default robots meta directives and manage robots.txt additions.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>
			<div className="space-y-5">
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="flex items-center justify-between">
							<div className="flex items-center gap-2">
								<div className="airygen_h2_title">
									{ __( 'Settings', 'airygen-seo' ) }
								</div>
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'Defaults', 'airygen-seo' ) }
								</span>
							</div>
							<Toggle
								label={ __( 'Enable default robots meta', 'airygen-seo' ) }
								hideLabelText
								checked={ settings.enable_default_meta }
								onChange={ ( enable_default_meta ) =>
									handleChange( { enable_default_meta } )
								}
							/>
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'When enabled, Airygen will output a robots meta tag sitewide.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<Select
								label={ __( 'Indexing', 'airygen-seo' ) }
								help={ __(
									'Choose whether pages should be indexed by default.',
									'airygen-seo',
								) }
								value={ indexChoice }
								onChange={ ( value ) => handleIndexChange( value ) }
								options={ [
									{ value: '', label: __( 'Use default', 'airygen-seo' ) },
									{ value: 'index', label: __( 'Index', 'airygen-seo' ) },
									{ value: 'noindex', label: __( 'Noindex', 'airygen-seo' ) },
								] }
							/>
						</div>
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<Select
								label={ __( 'Following', 'airygen-seo' ) }
								help={ __(
									'Choose whether links should be followed by default.',
									'airygen-seo',
								) }
								value={ followChoice }
								onChange={ ( value ) => handleFollowChange( value ) }
								options={ [
									{ value: '', label: __( 'Use default', 'airygen-seo' ) },
									{ value: 'follow', label: __( 'Follow', 'airygen-seo' ) },
									{ value: 'nofollow', label: __( 'Nofollow', 'airygen-seo' ) },
								] }
							/>
						</div>
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<Select
								label={ __( 'Max image preview', 'airygen-seo' ) }
								help={ __(
									'Set a limit for image previews (e.g., Google Images).',
									'airygen',
								) }
								value={ maxImagePreview }
								onChange={ ( value ) => {
									const coerced = ( value as MaxImagePreviewChoice ) || '';
									setMaxImagePreview( coerced );
									updateDirectiveValue(
										indexChoice,
										followChoice,
										extraDirectives,
										customDirectives,
										coerced,
										maxVideoPreview,
									);
								} }
								options={ maxImagePreviewOptions }
							/>
						</div>
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
							<Select
								label={ __( 'Max video preview', 'airygen-seo' ) }
								help={ __(
									'Set a limit for video preview duration.',
									'airygen-seo',
								) }
								value={ maxVideoPreview }
								onChange={ ( value ) => {
									const coerced = ( value as MaxVideoPreviewChoice ) || '';
									setMaxVideoPreview( coerced );
									updateDirectiveValue(
										indexChoice,
										followChoice,
										extraDirectives,
										customDirectives,
										maxImagePreview,
										coerced,
									);
								} }
								options={ maxVideoPreviewOptions }
							/>
						</div>
					</div>
					<div className="mt-4">
						<div className="airygen_h3_title">
							{ __( 'Extra directives', 'airygen-seo' ) }
						</div>
						<p className="mt-2 text-xs text-slate-500">
							{ __(
								'Add additional robots directives like nosnippet or noarchive.',
								'airygen-seo',
							) }
						</p>
						<div className="mt-3 grid gap-4 md:grid-cols-4">
							{ extraDirectiveOptions.map( ( directive ) => (
								<div
									key={ directive.key }
									className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4"
								>
									<div className="flex items-center justify-between gap-3">
										<p className="text-sm font-medium text-slate-900">
											{ directive.label }
										</p>
										<Toggle
											label={ directive.label }
											hideLabelText
											checked={ extraDirectives.has( directive.key ) }
											onChange={ ( value ) =>
												handleExtraToggle( directive.key, value )
											}
										/>
									</div>
									<p className="text-xs text-slate-500">
										{ directive.description }
									</p>
								</div>
							) ) }
						</div>
					</div>
					<div className="grid gap-4 md:grid-cols-2">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<Input
								label={ __( 'Custom directives', 'airygen-seo' ) }
								help={ __(
									'Comma-separated directives. Known directives are deduped; unknown ones are passed through.',
									'airygen-seo',
								) }
								value={ customDirectives }
								onChange={ ( value ) => handleCustomChange( value ) }
								placeholder="max-snippet:-1, max-video-preview:0"
							/>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label
								htmlFor={ directivesPreviewInputId }
								className="block text-sm font-medium text-gray-800"
							>
								{ __( 'Preview robots meta value', 'airygen-seo' ) }
							</label>
							<div className="mt-2 flex gap-2">
								<input
									id={ directivesPreviewInputId }
									type="text"
									value={ directivesPreview }
									readOnly
									className="airygen-field flex-1"
								/>
								<Button
									variant="secondary"
									onClick={ () =>
										onCopyToClipboard(
											directivesPreview,
											__( 'Robots meta copied', 'airygen-seo' ),
											__( 'Unable to copy robots meta', 'airygen-seo' ),
										)
									}
									className="text-xs"
								>
									{ __( 'Copy', 'airygen-seo' ) }
								</Button>
							</div>
							<p className="mt-2 text-xs text-slate-500">
								{ __(
									'This is the exact content that will be output in the <meta name="robots"> tag.',
									'airygen-seo',
								) }
							</p>
						</div>
					</div>
				</section>
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Robots.txt', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Add rules for crawlers such as disallow paths or custom user-agent directives.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="grid gap-4 md:grid-cols-2">
						<Textarea
							label={ __( 'Additional rules', 'airygen-seo' ) }
							className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4"
							value={ settings.additional_rules.join( '\n' ) }
							onChange={ ( value ) =>
								handleChange( {
									additional_rules: value
										.split( /\r?\n/ )
										.map( ( line ) => line.trim() )
										.filter( ( line ) => line !== '' ),
								} )
							}
							rows={ 6 }
							help={ __(
								'These lines will be appended to the robots.txt output.',
								'airygen-seo',
							) }
						/>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label
								htmlFor={ previewInputId }
								className="block text-sm font-medium text-gray-800"
							>
								{ __( 'Preview URL', 'airygen-seo' ) }
							</label>
							<div className="mt-1 flex gap-2">
								<input
									id={ previewInputId }
									type="text"
									value={ robotsPreviewUrl }
									readOnly
									className="airygen-field flex-1"
								/>
								<Button
									variant="secondary"
									onClick={ () =>
										onCopyToClipboard(
											robotsPreviewUrl,
											__( 'Preview link copied', 'airygen-seo' ),
											__( 'Unable to copy preview link', 'airygen-seo' ),
										)
									}
									className="text-xs"
								>
									{ __( 'Copy', 'airygen-seo' ) }
								</Button>
							</div>
							<p className="mt-2 text-xs text-slate-500">
								{ __(
									'Open robots.txt in a new tab to verify output.',
									'airygen-seo',
								) }
							</p>
						</div>
					</div>
				</section>
			</div>
		</div>
	);
};

export default RobotsTab;

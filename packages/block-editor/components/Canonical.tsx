import { __ } from '@wordpress/i18n';
import { BaseControl, Button, PanelRow, TextControl, RadioControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import usePostDataField from '../hooks/usePostDataField';
import { decodeUrlPreview } from '../../shared/urlPreview';

const Canonical = () => {
	const [ canonical, setCanonical ] = usePostDataField( 'canonical' );
	const { permalink } = useSelect( ( select ) => {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const editor = select( 'core/editor' ) as any;
		return {
			permalink: editor.getPermalink() as string,
		};
	}, [] );

	const [ mode, setMode ] = useState< 'preview' | 'custom' >(
		canonical.trim() ? 'custom' : 'preview',
	);
	const [ source, setSource ] = useState< 'default' | 'custom' >(
		canonical.trim() ? 'custom' : 'default',
	);
	const hasCustom = canonical.trim() !== '';
	const effectiveCanonical =
		source === 'custom' && hasCustom ? canonical.trim() : permalink || '';

	const [ error, setError ] = useState( '' );

	const validateUrl = ( value: string ) => {
		if ( value.trim() === '' ) {
			setError( '' );
			return true;
		}

		try {
			// eslint-disable-next-line no-new
			new URL( value );
			setError( '' );
			return true;
		} catch {
			setError( __( 'Enter a valid URL (including http/https).', 'airygen-seo' ) );
			return false;
		}
	};

	const handleCanonicalChange = ( value: string ) => {
		if ( source === 'custom' ) {
			validateUrl( value );
		}
		setCanonical( value );
	};

	useEffect( () => {
		if ( source === 'default' ) {
			setError( '' );
		}
	}, [ source ] );

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
			<g clipPath="url(#clip0_canonical_preview)">
				<path
					d="M3.88742 0.555664H1.66606C1.51877 0.555664 1.37752 0.614173 1.27337 0.71832C1.16923 0.822466 1.11072 0.96372 1.11072 1.11101V5.55374C1.11072 5.70102 1.16923 5.84227 1.27337 5.94642C1.37752 6.05057 1.51877 6.10908 1.66606 6.10908H3.60975C3.49591 6.03966 3.38762 5.95358 3.29321 5.85917C3.20158 5.76754 3.12383 5.6648 3.05441 5.55374H1.66606V1.11101H3.60975V2.49936H4.99811V2.82701C5.19525 2.87144 5.38407 2.94641 5.55345 3.0547V2.22169L3.88742 0.555664ZM5.63953 5.2483C6.00883 4.66241 5.83112 3.88771 5.25079 3.52119C4.6649 3.15188 3.88742 3.33237 3.52368 3.90993C3.1516 4.49581 3.33208 5.26774 3.91241 5.63704C4.31781 5.89527 4.83428 5.89527 5.24246 5.64259L6.10879 6.49504L6.49475 6.10908L5.63953 5.2483ZM4.5816 5.27607C4.39749 5.27607 4.22093 5.20293 4.09074 5.07275C3.96056 4.94256 3.88742 4.766 3.88742 4.58189C3.88742 4.39778 3.96056 4.22121 4.09074 4.09103C4.22093 3.96085 4.39749 3.88771 4.5816 3.88771C4.76571 3.88771 4.94227 3.96085 5.07246 4.09103C5.20264 4.22121 5.27578 4.39778 5.27578 4.58189C5.27578 4.766 5.20264 4.94256 5.07246 5.07275C4.94227 5.20293 4.76571 5.27607 4.5816 5.27607Z"
					fill="black"
				/>
			</g>
			<defs>
				<clipPath id="clip0_canonical_preview">
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
			<g clipPath="url(#clip0_canonical_custom)">
				<path d="M5.47015 3.58227L3.88743 5.165H3.24879V4.52635L4.83151 2.94363L5.47015 3.58227ZM6.41423 3.36014C6.41423 3.44344 6.33093 3.52674 6.24763 3.61004L5.55345 4.30422L5.30355 4.05431L6.02549 3.33237L5.85889 3.16577L5.66452 3.36014L5.02588 2.7215L5.63675 2.13839C5.69229 2.08285 5.80336 2.08285 5.88666 2.13839L6.2754 2.52713C6.33093 2.58266 6.33093 2.69373 6.2754 2.77703C6.21986 2.83256 6.16433 2.8881 6.16433 2.94363C6.16433 2.99917 6.21986 3.0547 6.2754 3.11023C6.3587 3.19354 6.442 3.27684 6.41423 3.36014ZM0.833051 5.55374V1.11101H2.77675V2.49936H4.1651V2.91586L4.72044 2.36052V2.22169L3.05442 0.555664H0.833051C0.527614 0.555664 0.27771 0.805568 0.27771 1.11101V5.55374C0.27771 5.85917 0.527614 6.10908 0.833051 6.10908H4.1651C4.47054 6.10908 4.72044 5.85917 4.72044 5.55374H0.833051ZM3.05442 4.74849C2.99888 4.74849 2.94335 4.77626 2.91558 4.77626L2.77675 4.16538H2.36024L1.77713 4.63742L1.94373 3.88771H1.52723L1.24956 5.27607H1.66606L2.47131 4.55412L2.63791 5.19276H2.91558L3.05442 5.165V4.74849Z" fill="black" />
			</g>
			<defs>
				<clipPath id="clip0_canonical_custom">
					<rect width="6.6641" height="6.6641" fill="white" />
				</clipPath>
			</defs>
		</svg>
	);

	return (
		<PanelRow className="airygen-canonical-row">
			<BaseControl id="canonical-url">
				<div className="airygen-panel-tabs">
					<Button
						variant={ mode === 'preview' ? 'primary' : 'secondary' }
						onClick={ () => setMode( 'preview' ) }
						aria-label={ __( 'Preview canonical', 'airygen-seo' ) }
						title={ __( 'Preview canonical', 'airygen-seo' ) }
						className="airygen-component-button"
					>
						<PreviewTabIcon />
					</Button>
					<Button
						variant={ mode === 'custom' ? 'primary' : 'secondary' }
						onClick={ () => setMode( 'custom' ) }
						aria-label={ __( 'Custom canonical', 'airygen-seo' ) }
						title={ __( 'Custom canonical', 'airygen-seo' ) }
						className="airygen-component-button"
					>
						<CustomTabIcon />
					</Button>
				</div>

				{ mode === 'preview' ? (
					<>
						<div className="airygen-snippet-preview">
							<p className="airygen-snippet-preview__url" style={ { marginBottom: 0 } }>
								{ effectiveCanonical
									? decodeUrlPreview( effectiveCanonical )
									: __( 'Permalink not available yet', 'airygen-seo' ) }
							</p>
						</div>
						<RadioControl
							label={ __( 'Source', 'airygen-seo' ) }
							selected={ source }
							options={ [
								{ label: __( 'Use defaults', 'airygen-seo' ), value: 'default' },
								{ label: __( 'Use custom data', 'airygen-seo' ), value: 'custom' },
							] }
							onChange={ ( value ) =>
								setSource( ( value as 'default' | 'custom' ) ?? 'default' )
							}
						/>
						<p className="airygen-robots-panel__helper">
							{ source === 'custom'
								? __( 'Using a custom canonical.', 'airygen-seo' )
								: __( 'Using the default permalink as canonical.', 'airygen-seo' ) }
						</p>
					</>
				) : (
					<div className="flex flex-col gap-2">
						<div className="flex items-center gap-2">
							<div className="flex-1">
								<TextControl
									value={ canonical }
									onChange={ handleCanonicalChange }
									className="airygen-canonical-input"
									help={
										error ||
										__( 'Leave blank to use the permalink WordPress generates.', 'airygen-seo' )
									}
									type="url"
								/>
							</div>
							<Button
								variant="secondary"
								onClick={ () => setCanonical( '' ) }
								disabled={ ! hasCustom }
								className="airygen-component-button"
							>
								{ __( 'Clear', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				) }
			</BaseControl>
		</PanelRow>
	);
};

export default Canonical;

import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';

type TransparentColorPickerProps = {
	label: string;
	value: string;
	onChange: ( nextValue: string ) => void;
};

const HEX_COLOR_PATTERN = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;

const isValidCssColor = ( value: string ): boolean => {
	const normalized = value.trim();
	if ( normalized === '' ) {
		return false;
	}
	if ( HEX_COLOR_PATTERN.test( normalized ) ) {
		return true;
	}
	if (
		typeof window !== 'undefined' &&
		typeof window.CSS !== 'undefined' &&
		typeof window.CSS.supports === 'function'
	) {
		return window.CSS.supports( 'color', normalized );
	}
	return false;
};

const toNativeColorInputValue = ( value: string ): string => {
	const normalized = value.trim();
	if ( /^#[0-9a-fA-F]{6}$/.test( normalized ) ) {
		return normalized;
	}
	if ( /^#[0-9a-fA-F]{3}$/.test( normalized ) ) {
		const [ r, g, b ] = normalized.slice( 1 ).split( '' );
		return `#${ r }${ r }${ g }${ g }${ b }${ b }`;
	}
	if ( /^#[0-9a-fA-F]{8}$/.test( normalized ) ) {
		return `#${ normalized.slice( 1, 7 ) }`;
	}
	if ( /^#[0-9a-fA-F]{4}$/.test( normalized ) ) {
		const [ r, g, b ] = normalized.slice( 1, 4 ).split( '' );
		return `#${ r }${ r }${ g }${ g }${ b }${ b }`;
	}
	return '#000000';
};

const TransparentColorPicker = ( { label, value, onChange }: TransparentColorPickerProps ) => {
	const [ draft, setDraft ] = useState( value );

	useEffect( () => {
		setDraft( value );
	}, [ value ] );

	const nativeColorValue = useMemo( () => toNativeColorInputValue( value ), [ value ] );
	const isTransparent = value.trim().toLowerCase() === 'transparent';

	const handleTextChange = ( nextValue: string ) => {
		setDraft( nextValue );
		if ( isValidCssColor( nextValue ) ) {
			onChange( nextValue.trim() );
		}
	};

	const handleTextBlur = () => {
		if ( ! isValidCssColor( draft ) ) {
			setDraft( value );
		}
	};

	return (
		<div className="flex flex-col gap-2 text-sm font-medium text-gray-800">
			<div className="flex items-center justify-between gap-2">
				<span>{ label }</span>
				<button
					type="button"
					className={
						'inline-flex items-center gap-1 px-0 py-0 text-[11px] font-medium transition-colors ' +
						( value.trim().toLowerCase() === 'transparent'
							? 'text-sky-700'
							: 'text-slate-600 hover:text-slate-800' )
					}
					onClick={ () => {
						setDraft( 'transparent' );
						onChange( 'transparent' );
					} }
					aria-label={ __( 'Set transparent color', 'airygen-seo' ) }
					title={ __( 'Transparent', 'airygen-seo' ) }
				>
					<span
						className="h-3 w-3 rounded border border-slate-300"
						style={ {
							backgroundImage:
								'linear-gradient(45deg,#e2e8f0 25%,transparent 25%),linear-gradient(-45deg,#e2e8f0 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e2e8f0 75%),linear-gradient(-45deg,transparent 75%,#e2e8f0 75%)',
							backgroundSize: '6px 6px',
							backgroundPosition: '0 0,0 3px,3px -3px,-3px 0',
						} }
						aria-hidden="true"
					/>
					<span>{ __( 'Transparent', 'airygen-seo' ) }</span>
				</button>
			</div>
			<div className="flex items-center gap-3">
				<div className="relative h-6 w-6">
					<span
						className="pointer-events-none h-6 w-6 absolute inset-0 rounded border border-slate-300"
						style={
							isTransparent
								? {
									backgroundImage:
										'linear-gradient(45deg,#e2e8f0 25%,transparent 25%),linear-gradient(-45deg,#e2e8f0 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e2e8f0 75%),linear-gradient(-45deg,transparent 75%,#e2e8f0 75%)',
									backgroundSize: '6px 6px',
									backgroundPosition: '0 0,0 3px,3px -3px,-3px 0',
								}
								: { backgroundColor: isValidCssColor( value ) ? value : nativeColorValue }
						}
						aria-hidden="true"
					/>
					<input
						type="color"
						value={ nativeColorValue }
						onChange={ ( event ) => onChange( event.target.value ) }
						className="airygen-color-palette absolute inset-0 h-6 w-6 cursor-pointer rounded opacity-0"
					/>
				</div>
				<input
					type="text"
					value={ draft }
					onChange={ ( event ) => handleTextChange( event.target.value ) }
					onBlur={ handleTextBlur }
					className="airygen-field w-full"
					placeholder="#RRGGBB / #RRGGBBAA / rgba(...)"
				/>
			</div>
		</div>
	);
};

export default TransparentColorPicker;

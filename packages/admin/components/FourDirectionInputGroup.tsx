import { useEffect, useRef, useState } from '@wordpress/element';

type BoxSide = 'top' | 'right' | 'bottom' | 'left';

type FourDirectionInputGroupProps = {
	idPrefix: string;
	values: Record<BoxSide, number>;
	max: number;
	onChange: ( values: Record<BoxSide, number> ) => void;
};

const CONTROLS: Array<{ key: BoxSide; icon: string }> = [
	{ key: 'top', icon: '↑' },
	{ key: 'right', icon: '→' },
	{ key: 'bottom', icon: '↓' },
	{ key: 'left', icon: '←' },
];

const clampValue = ( value: number, max: number ): number =>
	Math.max( 0, Math.min( max, Number.isFinite( value ) ? value : 0 ) );

const FourDirectionInputGroup = ( {
	idPrefix,
	values,
	max,
	onChange,
}: FourDirectionInputGroupProps ) => {
	const [ activeSide, setActiveSide ] = useState<BoxSide | null>( null );
	const rootRef = useRef<HTMLDivElement | null>( null );

	useEffect( () => {
		const handlePointerDown = ( event: MouseEvent ) => {
			if ( ! rootRef.current?.contains( event.target as Node ) ) {
				setActiveSide( null );
			}
		};

		const handleEscape = ( event: KeyboardEvent ) => {
			if ( 'Escape' === event.key ) {
				setActiveSide( null );
			}
		};

		document.addEventListener( 'mousedown', handlePointerDown );
		document.addEventListener( 'keydown', handleEscape );

		return () => {
			document.removeEventListener( 'mousedown', handlePointerDown );
			document.removeEventListener( 'keydown', handleEscape );
		};
	}, [] );

	return (
		<div
			ref={ rootRef }
			className="_airygen_four_inputs_group mt-2 flex w-full max-w-[261px] overflow-visible rounded-md border border-slate-300"
		>
			{ CONTROLS.map( ( control ) => {
				const id = `${ idPrefix }-${ control.key }`;
				const dropdownId = `${ id }-slider`;
				const currentValue = clampValue( values[ control.key ], max );
				const isActive = activeSide === control.key;

				return (
					<div
						key={ control.key }
						className="relative flex items-center border-r border-slate-300 last:border-r-0"
					>
						<label
							htmlFor={ id }
							className="inline-flex h-8 w-8 items-center justify-center border-r border-slate-300 text-sm font-semibold text-slate-500"
						>
							{ control.icon }
						</label>
						<input
							id={ id }
							className="airygen-input-group-field h-8 w-8 focus:outline-none focus:ring-0"
							type="text"
							inputMode="numeric"
							pattern="[0-9]*"
							value={ currentValue }
							onFocus={ () => setActiveSide( control.key ) }
							onClick={ () => setActiveSide( control.key ) }
							onChange={ ( event ) =>
								onChange( {
									...values,
									[ control.key ]: clampValue( Number( event.target.value ), max ),
								} )
							}
						/>
						{ isActive ? (
							<div
								id={ dropdownId }
								className="absolute left-0 top-[calc(100%+8px)] z-30 w-[180px] rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
							>
								<div className="flex items-center justify-between text-[11px] font-medium text-slate-500">
									<span>{ `${ control.icon } 0-${ max }` }</span>
									<span className="text-slate-700">{ currentValue }</span>
								</div>
								<input
									className="mt-3 h-2 w-full cursor-pointer appearance-none rounded-full bg-slate-200 accent-sky-500"
									type="range"
									min={ 0 }
									max={ max }
									step={ 1 }
									value={ currentValue }
									onChange={ ( event ) =>
										onChange( {
											...values,
											[ control.key ]: clampValue( Number( event.target.value ), max ),
										} )
									}
								/>
								<div className="mt-2 flex items-center justify-between text-[13px] text-slate-400">
									<span>0</span>
									<span>{ max }</span>
								</div>
							</div>
						) : null }
					</div>
				);
			} ) }
		</div>
	);
};

export default FourDirectionInputGroup;

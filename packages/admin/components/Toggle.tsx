import { useMemo } from '@wordpress/element';

type ToggleProps = {
	label: string;
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	description?: string;
	disabled?: boolean;
	className?: string;
	hideLabelText?: boolean;
};

/**
 * Tailwind-based toggle switch.
 *
 * @param {ToggleProps} props Component props.
 * @return {JSX.Element} Rendered toggle component.
 */
const Toggle = ( props: ToggleProps ) => {
	const {
		label,
		checked,
		onChange,
		description,
		disabled = false,
		className,
		hideLabelText = false,
	} = props;

	const controlId = useMemo(
		() => `airygen-toggle-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);

	return (
		<label
			htmlFor={ controlId }
			className={ [
				'inline-flex items-start gap-3 cursor-pointer',
				disabled ? 'opacity-60 cursor-not-allowed pointer-events-none' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<input
				id={ controlId }
				type="checkbox"
				className="peer sr-only"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
				disabled={ disabled }
			/>
			<div className="relative h-6 w-11 rounded-full bg-gray-200 transition peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-sky-300 peer-checked:bg-sky-500 rtl:peer-checked:after:-translate-x-full peer-checked:after:translate-x-full peer-checked:after:border-white after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all">
				<span className="sr-only">{ label }</span>
			</div>
			{ ! hideLabelText ? (
				<div className="flex flex-col text-sm text-gray-800">
					{ label }
					{ description ? (
						<p
							className="mt-1 text-xs text-gray-600"
							// Description text is authored by trusted code (plugin developers).
							// eslint-disable-next-line react/no-danger
							dangerouslySetInnerHTML={ { __html: description } }
						/>
					) : null }
				</div>
			) : null }
		</label>
	);
};

export default Toggle;

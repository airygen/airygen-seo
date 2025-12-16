import { useMemo } from '@wordpress/element';

type CheckboxProps = {
	label: string;
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	description?: string;
	disabled?: boolean;
	className?: string;
};

/**
 * Tailwind-styled checkbox control.
 *
 * @param {CheckboxProps} props Component props.
 * @return {JSX.Element} Rendered checkbox element.
 */
const Checkbox = ( props: CheckboxProps ) => {
	const {
		label,
		checked,
		onChange,
		description,
		disabled = false,
		className,
	} = props;

	const inputId = useMemo(
		() => `airygen-checkbox-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);

	const handleChange = ( event: { target: HTMLInputElement } ) => {
		onChange( event.target.checked );
	};

	return (
		<label
			htmlFor={ inputId }
			className={ [
				'flex cursor-pointer items-center gap-3 rounded-lg border border-transparent transition-colors',
				disabled ? 'cursor-not-allowed opacity-60' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<input
				id={ inputId }
				type="checkbox"
				className="h-4 w-4 rounded-sm border border-slate-300 bg-slate-50 text-sky-600 focus:ring-3 focus:ring-sky-300 focus:ring-offset-0"
				checked={ checked }
				onChange={ handleChange }
				disabled={ disabled }
			/>
			<span className="flex flex-col">
				<span className="flex h-5 items-center">
					<span className="text-sm font-medium text-gray-800">
						{ label }
					</span>
				</span>
				{ description && (
					<span className="text-xs text-slate-500">
						{ description }
					</span>
				) }
			</span>
		</label>
	);
};

export default Checkbox;

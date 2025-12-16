import { useMemo } from '@wordpress/element';

type TextareaProps = {
	label?: string;
	help?: string;
	value: string;
	onChange: ( value: string ) => void;
	rows?: number;
	placeholder?: string;
	className?: string;
	disabled?: boolean;
};

/**
 * Tailwind-styled textarea control.
 */

const Textarea = ( props: TextareaProps ) => {
	const {
		label,
		help,
		value,
		onChange,
		rows = 4,
		placeholder,
		className,
		disabled = false,
	} = props;

	const textareaId = useMemo(
		() => `airygen-textarea-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);

	const handleChange = ( event: { target: HTMLTextAreaElement } ) => {
		onChange( event.target.value );
	};

	const wrapperClass = [ 'flex flex-col gap-2', className ]
		.filter( Boolean )
		.join( ' ' );

	const textareaClass = [ 'airygen-field', disabled ? 'cursor-not-allowed' : '' ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ wrapperClass }>
			{ label ? (
				<label
					htmlFor={ textareaId }
					className="block text-sm font-medium text-gray-800"
				>
					{ label }
				</label>
			) : null }
			<textarea
				id={ textareaId }
				value={ value }
				onChange={ handleChange }
				rows={ rows }
				placeholder={ placeholder }
				disabled={ disabled }
				className={ textareaClass }
			/>
			{ help ? (
				<p
					className="text-xs text-gray-500"
					// Help content is controlled by plugin authors.
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: help } }
				/>
			) : null }
		</div>
	);
};

export default Textarea;

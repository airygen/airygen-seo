import { useMemo } from '@wordpress/element';
import type { CSSProperties } from 'react';
import Popover from './Popover';

type InputProps = {
	label?: string;
	labelTip?: React.ReactNode;
	help?: string;
	value: string;
	onChange: ( value: string ) => void;
	type?: string;
	placeholder?: string;
	id?: string;
	name?: string;
	required?: boolean;
	className?: string;
	disabled?: boolean;
	isUrl?: boolean;
	inputClassName?: string;
	inputStyle?: CSSProperties;
	min?: number | string;
	max?: number | string;
	step?: number | string;
};

/**
 * Text input control styled with Tailwind.
 *
 * @param {InputProps} props Component props.
 * @return {JSX.Element} Rendered input control.
 */
const Input = ( props: InputProps ) => {
	const {
		label,
		labelTip,
		help,
		value,
		onChange,
		type = 'text',
		placeholder,
		id,
		name,
		required = false,
		className,
		disabled = false,
		isUrl = false,
		inputClassName,
		inputStyle,
		min,
		max,
		step,
	} = props;

	const generatedId = useMemo(
		() => `airygen-input-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);
	const inputId = id ?? generatedId;

	const handleChange = ( event: { target: HTMLInputElement } ) => {
		onChange( event.target.value );
	};

	const trimmedValue = value.trim();
	const urlInvalid = useMemo( () => {
		if ( ! isUrl || '' === trimmedValue ) {
			return false;
		}

		try {
			const parsed = new URL( trimmedValue );
			return ! [ 'http:', 'https:' ].includes( parsed.protocol );
		} catch {
			return true;
		}
	}, [ isUrl, trimmedValue ] );

	const wrapperClass = [ 'flex flex-col gap-2', className ]
		.filter( Boolean )
		.join( ' ' );

	const inputClass = [
		'airygen-field',
		disabled ? 'cursor-not-allowed' : '',
		urlInvalid ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
		inputClassName,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ wrapperClass }>
			{ label ? (
				<div className="flex items-center justify-between gap-2">
					<label
						className="block text-sm font-medium text-gray-800"
						htmlFor={ inputId }
					>
						{ label }
						{ required && <span className="text-red-500"> *</span> }
					</label>
					{ labelTip ? (
						<Popover
							position="top-right"
							triggerAs="div"
							showClose={ true }
							trigger={
								<span className="_airygen_tips_popover inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-semibold text-slate-600 hover:border-sky-300 hover:text-sky-700">
									?
								</span>
							}
						>
							{ labelTip }
						</Popover>
					) : null }
				</div>
			) : null }
			<input
				id={ inputId }
				type={ type }
				name={ name }
				value={ value }
				onChange={ handleChange }
				placeholder={ placeholder }
				required={ required }
				disabled={ disabled }
				className={ inputClass }
				style={ inputStyle }
				min={ min }
				max={ max }
				step={ step }
			/>
			{ help ? (
				<p
					className="text-xs text-gray-500"
					// Help content is controlled by plugin authors.
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: help } }
				/>
			) : null }
			{ urlInvalid ? (
				<p className="text-xs text-red-500">
					Please enter a valid URL starting with http:// or https://.
				</p>
			) : null }
		</div>
	);
};

export default Input;

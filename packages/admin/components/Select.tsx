import { useMemo } from '@wordpress/element';
import Popover from './Popover';

type SelectOption = {
	value: string;
	label: string;
	disabled?: boolean;
};

type SelectProps = {
	label?: string;
	labelTip?: React.ReactNode;
	value: string;
	options: SelectOption[];
	onChange: ( value: string ) => void;
	placeholder?: string;
	help?: string;
	className?: string;
	disabled?: boolean;
};

/**
 * Tailwind-styled select control.
 *
 * @param {SelectProps} props Component props.
 * @return {JSX.Element} Rendered select control.
 */

const Select = ( props: SelectProps ) => {
	const {
		label,
		labelTip,
		value,
		options,
		onChange,
		placeholder,
		help,
		className,
		disabled = false,
	} = props;

	const selectId = useMemo(
		() => `airygen-select-${ Math.random().toString( 36 ).slice( 2 ) }`,
		[],
	);

	const handleChange = ( event: { target: HTMLSelectElement } ) => {
		onChange( event.target.value );
	};

	const wrapperClass = [ 'flex flex-col gap-2', className ]
		.filter( Boolean )
		.join( ' ' );

	const selectClass = [ 'airygen-field-select', disabled ? 'cursor-not-allowed' : '' ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ wrapperClass }>
			{ label ? (
				<div className="flex items-center justify-between gap-2">
					<label
						htmlFor={ selectId }
						className="block text-sm font-medium text-gray-800"
					>
						{ label }
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
			<select
				id={ selectId }
				value={ value }
				onChange={ handleChange }
				disabled={ disabled }
				className={ selectClass }
			>
				{ placeholder && (
					<option value="" disabled>
						{ placeholder }
					</option>
				) }
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value } disabled={ option.disabled }>
						{ option.label }
					</option>
				) ) }
			</select>
			{ help ? (
				<p
					className="text-xs text-gray-500"
					// Help content is managed by trusted plugin code.
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: help } }
				/>
			) : null }
		</div>
	);
};

export type { SelectOption };
export default Select;

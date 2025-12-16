type ButtonVariant =
	| 'primary'
	| 'secondary'
	| 'ghost'
	| 'danger'
	| 'gradient'
	| 'outline';

type ButtonProps = Omit<JSX.IntrinsicElements['button'], 'ref'> & {
	variant?: ButtonVariant;
	loading?: boolean;
};

/**
 * Tailwind-styled button component independent from wp-admin styles.
 *
 * @param {ButtonProps} props Component props.
 * @return {JSX.Element} Rendered button element.
 */
const Button = ( props: ButtonProps ) => {
	const {
		variant = 'primary',
		loading = false,
		disabled,
		className,
		children,
		type = 'button',
		...rest
	} = props;

	const isDisabled = disabled || loading;

	const baseClasses =
		'inline-flex items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white whitespace-nowrap align-middle';

	let variantClasses = '';
	if ( 'primary' === variant ) {
		variantClasses =
			'border-transparent bg-sky-600 text-white hover:bg-sky-500 focus:ring-sky-500';
	} else if ( 'secondary' === variant ) {
		variantClasses =
			'border-slate-400 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-500 focus:ring-sky-500';
	} else if ( 'outline' === variant ) {
		variantClasses =
			'border-sky-600 bg-transparent text-sky-600 hover:bg-sky-50 focus:ring-sky-500';
	} else if ( 'danger' === variant ) {
		variantClasses =
			'border-red-200 bg-white text-red-600 hover:bg-red-50 hover:border-red-300 focus:ring-red-500';
	} else {
		variantClasses =
			'border-transparent bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-sky-500';
	}

	if ( 'gradient' === variant ) {
		return (
			<button
				type={ type }
				{ ...rest }
				disabled={ isDisabled }
				className={ [
					'relative inline-flex items-center justify-center p-0.5 overflow-hidden text-sm font-medium text-gray-900 rounded-lg group bg-gradient-to-br from-sky-400 to-sky-600 group-hover:from-sky-400 group-hover:to-sky-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-sky-200',
					isDisabled ? 'opacity-60 cursor-not-allowed pointer-events-none' : '',
					className,
				]
					.filter( Boolean )
					.join( ' ' ) }
			>
				<span className="relative inline-flex items-center justify-center gap-2 rounded-md bg-white px-5 py-2.5 transition-all duration-75 ease-in group-hover:bg-transparent">
					{ loading && (
						<span
							className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
							aria-hidden="true"
						/>
					) }
					{ children }
				</span>
			</button>
		);
	}

	const disabledClasses = isDisabled ? 'cursor-not-allowed opacity-60' : '';

	return (
		<button
			type={ type }
			{ ...rest }
			disabled={ isDisabled }
			className={ [ baseClasses, variantClasses, disabledClasses, className ]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ loading && (
				<span
					className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
					aria-hidden="true"
				/>
			) }
			{ children }
		</button>
	);
};

export default Button;

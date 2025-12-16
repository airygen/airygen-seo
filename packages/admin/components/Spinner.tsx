type SpinnerProps = {
	className?: string;
	size?: 'sm' | 'md' | 'lg';
};

/**
 * Simple Tailwind-based spinner.
 *
 * @param {SpinnerProps} props Component props.
 * @return {JSX.Element} Spinner element.
 */
const Spinner = ( props: SpinnerProps ) => {
	const { className, size = 'md' } = props;

	let dimension = 'h-6 w-6';
	if ( 'sm' === size ) {
		dimension = 'h-4 w-4';
	} else if ( 'lg' === size ) {
		dimension = 'h-10 w-10';
	}

	return (
		<span
			className={ [
				'inline-block animate-spin rounded-full border-2 border-slate-300 border-t-sky-500',
				dimension,
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-hidden="true"
		/>
	);
};

export default Spinner;

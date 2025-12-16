type PreviewDeviceOption = {
	key: string;
	label: string;
	Icon: ( props: { className?: string } ) => JSX.Element;
};

type PreviewDeviceSwitcherProps = {
	options: PreviewDeviceOption[];
	value: string;
	onChange: ( next: string ) => void;
	className?: string;
};

const getEdgeBorderClass = ( index: number, total: number ): string => {
	if ( index === 0 ) {
		return 'border border-slate-200 border-r-0';
	}

	if ( index === total - 1 ) {
		return 'border border-slate-200 border-l-0';
	}

	return 'border border-slate-200';
};

const PreviewDeviceSwitcher = ( {
	options,
	value,
	onChange,
	className = '',
}: PreviewDeviceSwitcherProps ) => (
	<div
		className={ `inline-flex items-center rounded-md border border-slate-300 bg-white p-1.5 _airygen-preview-device-container ${ className }`.trim() }
	>
		{ options.map( ( option, index ) => {
			const active = value === option.key;
			const Icon = option.Icon;
			const edgeBorderClass = getEdgeBorderClass( index, options.length );

			return (
				<button
					key={ option.key }
					type="button"
					aria-label={ option.label }
					title={ option.label }
					onClick={ () => onChange( option.key ) }
					className={ `_airygen-preview-device flex h-9 w-9 items-center justify-center rounded-sm ${ edgeBorderClass } transition ${
						active
							? 'bg-sky-50 text-sky-600'
							: 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'
					}` }
				>
					<Icon className="h-5 w-5" />
				</button>
			);
		} ) }
	</div>
);

export type { PreviewDeviceOption, PreviewDeviceSwitcherProps };
export default PreviewDeviceSwitcher;

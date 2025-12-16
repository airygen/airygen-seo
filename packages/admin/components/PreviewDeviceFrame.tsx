type PreviewDeviceKind = 'laptop' | 'tablet' | 'cellphone';

type PreviewDeviceSpec = {
	width: number;
	height: number;
	frameClassName: string;
	screenClassName: string;
};

type PreviewDeviceFrameProps = {
	device: PreviewDeviceKind;
	children: JSX.Element;
	className?: string;
};

const DEVICE_SPECS: Record<PreviewDeviceKind, PreviewDeviceSpec> = {
	laptop: {
		width: 1280,
		height: 720,
		frameClassName: 'relative rounded-xl border-[10px] border-slate-700 bg-slate-800 px-2 pb-2 pt-4 shadow',
		screenClassName: 'overflow-hidden rounded-md border border-slate-700 bg-white',
	},
	tablet: {
		width: 800,
		height: 920,
		frameClassName: 'relative rounded-[28px] border-[10px] border-slate-700 bg-slate-800 px-2 pb-2 pt-4 shadow',
		screenClassName: 'overflow-hidden rounded-[18px] border border-slate-700 bg-white',
	},
	cellphone: {
		width: 375,
		height: 667,
		frameClassName: 'relative rounded-[30px] border-[10px] border-slate-700 bg-slate-800 px-2 pb-2 pt-4 shadow',
		screenClassName: 'overflow-hidden rounded-[20px] border border-slate-700 bg-white',
	},
};

const renderTopChrome = ( device: PreviewDeviceKind ) => {
	if ( device === 'laptop' ) {
		return (
			<div className="pointer-events-none absolute left-1/2 top-1 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-slate-500/80 shadow-inner" />
		);
	}

	if ( device === 'tablet' ) {
		return (
			<div className="pointer-events-none absolute left-1/2 top-1.5 flex -translate-x-1/2 items-center gap-1">
				<div className="h-1.5 w-1.5 rounded-full bg-slate-500/90" />
				<div className="h-1 w-8 rounded-full bg-slate-500/70" />
			</div>
		);
	}

	return (
		<div className="pointer-events-none absolute left-1/2 top-1.5 flex -translate-x-1/2 items-center gap-1">
			<div className="h-1.5 w-1.5 rounded-full bg-slate-500/90" />
			<div className="h-1 w-10 rounded-full bg-slate-500/70" />
		</div>
	);
};

const PreviewDeviceFrame = ( { device, children, className = '' }: PreviewDeviceFrameProps ) => {
	const spec = DEVICE_SPECS[ device ];

	return (
		<div style={ { width: `${ spec.width + 16 }px`, margin: '0 auto' } }>
			<div className={ `${ spec.frameClassName } ${ className }`.trim() }>
				{ renderTopChrome( device ) }
				<div className={ spec.screenClassName }>
					<div
						style={ { height: `${ spec.height }px` } }
						className="box-border overflow-y-auto overflow-x-hidden p-3"
					>
						{ children }
					</div>
				</div>
			</div>
		</div>
	);
};

export type { PreviewDeviceKind, PreviewDeviceFrameProps };
export default PreviewDeviceFrame;

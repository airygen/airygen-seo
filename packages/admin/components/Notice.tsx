import type { ReactNode } from 'react';

type NoticeStatus = 'success' | 'error' | 'warning' | 'info';

type NoticeProps = {
	status?: NoticeStatus;
	children: ReactNode;
	onClose?: () => void;
	dismissible?: boolean;
	className?: string;
};

const colorMap: Record<NoticeStatus, { border: string; background: string; text: string }> = {
	success: {
		border: 'border-green-200',
		background: 'bg-green-50',
		text: 'text-green-800',
	},
	error: {
		border: 'border-red-200',
		background: 'bg-red-50',
		text: 'text-red-800',
	},
	warning: {
		border: 'border-amber-200',
		background: 'bg-amber-50',
		text: 'text-amber-800',
	},
	info: {
		border: 'border-sky-200',
		background: 'bg-sky-50',
		text: 'text-sky-800',
	},
};

/**
 * Lightweight alert component.
 *
 * @param {NoticeProps} props Component props.
 * @return {JSX.Element} Rendered notice element.
 */
const Notice = ( props: NoticeProps ) => {
	const {
		status = 'info',
		children,
		onClose,
		dismissible = true,
		className,
	} = props;
	const palette = colorMap[ status ];

	return (
		<div
			className={ [
				'flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm',
				palette.border,
				palette.background,
				palette.text,
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<div className="flex-1">{ children }</div>
			{ dismissible && onClose && (
				<button
					type="button"
					onClick={ onClose }
					className="ml-3 text-xs font-medium uppercase tracking-wide opacity-75 transition hover:opacity-100"
				>
					Close
				</button>
			) }
		</div>
	);
};

export default Notice;

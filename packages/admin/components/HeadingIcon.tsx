import type { ReactNode } from 'react';

type HeadingIconProps = {
	children: ReactNode;
	className?: string;
};

const HeadingIcon = ( { children, className }: HeadingIconProps ) => (
	<span
		className={
			`flex h-12 w-12 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 ${ className ?? '' }`
		}
	>
		{ children }
	</span>
);

export default HeadingIcon;

import classNames from '../utils/classNames';
import {
	ModuleTraitBackgroundIcon,
	ModuleTraitMarkupIcon,
	ModuleTraitSidebarIcon,
	ModuleTraitToolIcon,
} from './Icons';
import type { ModuleTraits } from '../types/modules';

type ModuleTraitLabels = {
	background: string;
	markup: string;
	sidebar: string;
	tool: string;
};

type ModuleTraitIconsProps = {
	traits?: ModuleTraits;
	labels: ModuleTraitLabels;
};

const ModuleTraitIcons = ( { traits, labels }: ModuleTraitIconsProps ) => {
	const items = [
		{
			key: 'background' as const,
			label: labels.background,
			Icon: ModuleTraitBackgroundIcon,
		},
		{
			key: 'markup' as const,
			label: labels.markup,
			Icon: ModuleTraitMarkupIcon,
		},
		{
			key: 'sidebar' as const,
			label: labels.sidebar,
			Icon: ModuleTraitSidebarIcon,
		},
		{
			key: 'tool' as const,
			label: labels.tool,
			Icon: ModuleTraitToolIcon,
		},
	];

	return (
		<div className="absolute right-4 top-4 flex gap-1">
			{ items.map( ( trait ) => {
				const active = Boolean( traits?.[ trait.key ] );

				return (
					<span
						key={ trait.key }
						className="group relative flex h-5 w-5 items-center justify-center"
						aria-label={ trait.label }
					>
						<trait.Icon
							className={ classNames(
								'block h-3.5 w-3.5 transition-colors',
								active ? 'text-sky-600' : 'text-[#aaaaaa]',
							) }
							aria-hidden="true"
						/>
						<span className="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-0.5 text-[10px] font-medium text-white opacity-0 shadow-sm transition group-hover:opacity-100 z-[999]">
							{ trait.label }
						</span>
					</span>
				);
			} ) }
		</div>
	);
};

export default ModuleTraitIcons;

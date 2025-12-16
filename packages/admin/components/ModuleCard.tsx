import { __ } from '@wordpress/i18n';
import type { ComponentType, DragEventHandler } from 'react';
import Button from './Button';
import ModuleTraitIcons from './ModuleTraitIcons';
import { type IconProps } from './Icons';
import Toggle from './Toggle';
import type { ModuleTraits } from '../types/modules';

type Tier = 'starter' | 'expert' | null;

type ModuleCardProps = {
	title: string;
	description: string;
	Icon: ComponentType<IconProps>;
	enabled: boolean;
	onToggle: ( next: boolean ) => void;
	onOpenSettings?: () => void;
	showSettingsButton?: boolean;
	settingsLabel?: string;
	disabled?: boolean;
	traits?: ModuleTraits;
	traitLabels?: {
		background: string;
		markup: string;
		sidebar: string;
		tool: string;
	};
	tier?: Tier;
	id?: string;
	draggable?: boolean;
	onDragStart?: DragEventHandler<HTMLDivElement>;
	onDragOver?: DragEventHandler<HTMLDivElement>;
	onDrop?: DragEventHandler<HTMLDivElement>;
	onDragEnd?: DragEventHandler<HTMLDivElement>;
	className?: string;
};

const ModuleCard = ( {
	title,
	description,
	Icon,
	enabled,
	onToggle,
	onOpenSettings,
	showSettingsButton = true,
	settingsLabel,
	disabled = false,
	traits,
	traitLabels,
	id,
	draggable = false,
	onDragStart,
	onDragOver,
	onDrop,
	onDragEnd,
	className = '',
}: ModuleCardProps ) => {
	const resolvedTraitLabels = traitLabels ?? {
		background: __( 'Background Process', 'airygen-seo' ),
		markup: __( 'Front-end output', 'airygen-seo' ),
		sidebar: __( 'Editor Sidebar', 'airygen-seo' ),
		tool: __( 'Tool', 'airygen-seo' ),
	};

	return (
		<div
			id={ id }
			draggable={ draggable }
			onDragStart={ onDragStart }
			onDragOver={ onDragOver }
			onDrop={ onDrop }
			onDragEnd={ onDragEnd }
			className={ [
				'relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5',
				draggable ? 'cursor-move transition-shadow' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ traits ? (
				<ModuleTraitIcons
					traits={ traits }
					labels={ resolvedTraitLabels }
				/>
			) : null }
			<div className="flex flex-1 flex-col gap-3">
				<div className="flex items-start gap-3">
					<span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600">
						<Icon className="h-6 w-6" aria-hidden="true" />
					</span>
					<div>
						<div className="airygen_h3_title">
							<span>{ title }</span>
						</div>
						<p className="mt-1 text-sm text-slate-500">{ description }</p>
					</div>
				</div>
			</div>
			<div className="mt-6 flex min-h-[30px] flex-wrap items-center justify-between gap-3">
				<Toggle
					label={ __( 'Enabled', 'airygen-seo' ) }
					checked={ enabled }
					hideLabelText
					disabled={ disabled }
					onChange={ onToggle }
				/>
				{ showSettingsButton && onOpenSettings ? (
					<Button
						variant="secondary"
						className="px-3 py-1.5 text-xs"
						onClick={ onOpenSettings }
					>
						{ settingsLabel ?? __( 'Settings', 'airygen-seo' ) }
					</Button>
				) : null }
			</div>
		</div>
	);
};

export default ModuleCard;

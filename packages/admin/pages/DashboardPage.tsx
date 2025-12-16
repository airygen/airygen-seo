import type { DragEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	getAllChangesSavedLabel,
	getSaveChangesLabel,
	getSavingLabel,
	getUnsavedChangesLabel,
} from '../utils/i18n';
import Button from '../components/Button';
import ContentBlocksOrder from '../components/ContentBlocksOrder';
import DashboardTour from '../components/DashboardTour';
import ModuleCard from '../components/ModuleCard';
import Toggle from '../components/Toggle';
import type { ContentBlockKey, SettingsState } from '../types/settings';
import type { ModuleKey, ModuleMetadata, PanelMetadata, PanelKey } from '../types/modules';

type DashboardPageProps = {
	settings: SettingsState;
	orderedModules: ModuleMetadata[];
	orderedPanels: PanelMetadata[];
	onModuleToggle: ( key: ModuleKey, value: boolean ) => void;
	onOpenSettings: ( key: ModuleKey ) => void;
	onDragStart: ( event: DragEvent<HTMLDivElement>, key: ModuleKey ) => void;
	onDragOver: ( event: DragEvent<HTMLDivElement> ) => void;
	onDrop: ( event: DragEvent<HTMLDivElement>, key: ModuleKey ) => void;
	onDragEnd: () => void;
	onPanelDragStart: ( event: DragEvent<HTMLDivElement>, key: PanelKey ) => void;
	onPanelDragOver: ( event: DragEvent<HTMLDivElement> ) => void;
	onPanelDrop: ( event: DragEvent<HTMLDivElement>, key: PanelKey ) => void;
	onPanelDragEnd: () => void;
	onPanelToggle: ( key: PanelKey, value: boolean ) => void;
	onSave: () => void;
	isDirty: boolean;
	isSaving: boolean;
	wizardDismissed: boolean;
	contentBlockOrder: ContentBlockKey[];
	contentBlockGap: number;
	contentBlockMarginTop: number;
	onContentBlockOrderChange: ( next: ContentBlockKey[] ) => void;
	onContentBlockGapChange: ( next: number ) => void;
	onContentBlockMarginTopChange: ( next: number ) => void;
	onContentBlockZoneChange: ( key: ContentBlockKey, zone: 'before' | 'after' ) => void;
};

const DashboardPage = ( {
	settings,
	orderedModules,
	orderedPanels,
	onModuleToggle,
	onOpenSettings,
	onDragStart,
	onDragOver,
	onDrop,
	onDragEnd,
	onPanelDragStart,
	onPanelDragOver,
	onPanelDrop,
	onPanelDragEnd,
	onPanelToggle,
	onSave,
	isDirty,
	isSaving,
	wizardDismissed,
	contentBlockOrder,
	contentBlockGap,
	contentBlockMarginTop,
	onContentBlockOrderChange,
	onContentBlockGapChange,
	onContentBlockMarginTopChange,
	onContentBlockZoneChange,
}: DashboardPageProps ) => {
	const enabledCount = orderedModules.filter( ( module ) => settings.modules[ module.key ] ).length;
	const isPanelAvailable = ( panel: PanelMetadata ): boolean => {
		const moduleEnabled = settings.modules[ panel.relatedModule ] !== false;
		if ( ! moduleEnabled ) {
			return false;
		}

		if ( panel.key === 'promptsForAgents' ) {
			return Boolean( settings.markdownForAgents.promptsForAgents );
		}

		return true;
	};

	type ContentBlockZone = 'before' | 'after';

	const getContentBlockZone = ( key: ContentBlockKey ): ContentBlockZone | null => {
		switch ( key ) {
			case 'toc':
				return settings.toc.position === 'before-content' ? 'before' : null;
			case 'breadcrumbs':
				return settings.breadcrumbs.injectionPosition === 'before_content' ? 'before' : 'after';
			case 'relatedPosts':
				return settings.relatedPosts.insertPosition === 'before_content' ? 'before' : 'after';
			case 'topicCluster':
				return settings.topicCluster.insertPosition === 'before-content' ? 'before' : 'after';
		}
	};

	const contentBlockLabel = ( key: ContentBlockKey ): string => {
		switch ( key ) {
			case 'toc': return __( 'Table of Contents', 'airygen-seo' );
			case 'breadcrumbs': return __( 'Breadcrumbs', 'airygen-seo' );
			case 'relatedPosts': return __( 'Related Posts', 'airygen-seo' );
			case 'topicCluster': return __( 'Topic Cluster', 'airygen-seo' );
		}
	};

	const visibleContentBlockKeys: ContentBlockKey[] = [ 'toc', 'breadcrumbs', 'relatedPosts', 'topicCluster' ];

	const getContentBlockEnabled = ( key: ContentBlockKey ): boolean => {
		switch ( key ) {
			case 'toc':
				return Boolean( settings.modules.toc ) && Boolean( settings.toc.autoInjectionEnabled );
			case 'breadcrumbs':
				return Boolean( settings.modules.breadcrumbs ) && Boolean( settings.breadcrumbs.autoInjectionEnabled );
			case 'relatedPosts':
				return Boolean( settings.modules.relatedPosts ) && Boolean( settings.relatedPosts.autoInjectEnabled );
			case 'topicCluster':
				return Boolean( settings.modules.topicCluster ) && Boolean( settings.topicCluster.autoInjectionEnabled );
		}
	};

	const contentBlocks = visibleContentBlockKeys.map( ( key ) => ( {
		key,
		label: contentBlockLabel( key ),
		zone: getContentBlockZone( key ),
		moduleEnabled: getContentBlockEnabled( key ),
	} ) );

	const moduleLabel = ( key: ModuleKey ): string => {
		switch ( key ) {
			case 'onPageSeo':
				return __( 'On-Page SEO', 'airygen-seo' );
			case 'robots':
				return __( 'Robots Control', 'airygen-seo' );
			case 'scoreCalculator':
				return __( 'Score Calculator', 'airygen-seo' );
			case 'linkSuggestions':
				return __( 'Link Suggestions', 'airygen-seo' );
			case 'schema':
				return __( 'Schema Markup', 'airygen-seo' );
			case 'topicCluster':
				return __( 'Topic Cluster', 'airygen-seo' );
			case 'markdownForAgents':
				return __( 'Markdown for Agents', 'airygen-seo' );
			default:
				return __( 'Settings', 'airygen-seo' );
		}
	};

	return (
		<div className="space-y-6">
			<DashboardTour wizardDismissed={ wizardDismissed } />
			<p className="text-base text-slate-600">
				{ __(
					'Manage feature defaults, global metadata, and redirects from one dashboard.',
					'airygen-seo',
				) }
			</p>
			<div id="airygen-tour-step-1" className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
				<div className="airygen_h2_title">
					{ __( 'Quick start', 'airygen-seo' ) }
				</div>
				<p className="mt-2 text-sm text-slate-500">
					{ __(
						"Go to the sections you use most, or check your site's overall SEO status.",
						'airygen-seo',
					) }
				</p>
				<div className="mt-4 flex flex-wrap items-center gap-3">
					<Button variant="gradient" onClick={ () => onOpenSettings( 'social' ) }>
						{ __( 'Open global settings', 'airygen-seo' ) }
					</Button>
					{ isDirty ? (
						<span className="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">
							{ __( 'Unsaved changes pending', 'airygen-seo' ) }
						</span>
					) : null }
				</div>
			</div>

			<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Modules', 'airygen-seo' ) }
						</div>
						<p className="mt-2 text-sm text-slate-500">
							{ __(
								'Enable or disable features to customize the Settings page.',
								'airygen-seo',
							) }
						</p>
					</div>
				</div>

				<div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
					{ orderedModules.map( ( module, moduleIndex ) => {
						const Icon = module.icon;
						const checked = settings.modules[ module.key ];
						const showSettingsButton = module.hasSettings !== false;

						return (
							<ModuleCard
								key={ module.key }
								id={ moduleIndex === 1 ? 'airygen-tour-step-2' : undefined }
								title={ module.title }
								description={ module.description }
								Icon={ Icon }
								enabled={ checked }
								onToggle={ ( value ) => onModuleToggle( module.key, value ) }
								onOpenSettings={ () => onOpenSettings( module.key ) }
								showSettingsButton={ checked && showSettingsButton }
								traits={ module.traits }
								draggable
								onDragStart={ ( event ) => onDragStart( event, module.key ) }
								onDragOver={ onDragOver }
								onDrop={ ( event ) => onDrop( event, module.key ) }
								onDragEnd={ onDragEnd }
							/>
						);
					} ) }
				</div>

				{ 0 === enabledCount ? (
					<p className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
						{ __(
							'Enable at least one module to configure its settings.',
							'airygen-seo',
						) }
					</p>
				) : null }

			</div>

			<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Panels', 'airygen-seo' ) }
						</div>
						<p className="mt-2 text-sm text-slate-500">
							{ __(
								'Reorder the sidebar panels shown in the editor. Drag to arrange and keep related modules together.',
								'airygen-seo',
							) }
						</p>
					</div>
				</div>

				<div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
					{ orderedPanels.map( ( panel, panelIndex ) => (
						<div
							key={ panel.key }
							id={ panelIndex === 1 ? 'airygen-tour-step-3' : undefined }
							draggable
							onDragStart={ ( event ) => onPanelDragStart( event, panel.key ) }
							onDragOver={ onPanelDragOver }
							onDrop={ ( event ) => onPanelDrop( event, panel.key ) }
							onDragEnd={ onPanelDragEnd }
							className="relative flex h-full cursor-move flex-col rounded-xl border border-slate-200 bg-slate-50 p-5 transition-shadow"
						>
							<div className="flex flex-1 flex-col gap-3">
								<div>
									<div className="airygen_h3_title">
										{ panel.title }
									</div>
									<p className="mt-1 text-sm text-slate-500">
										{ panel.description }
									</p>
								</div>
								<div className="mt-auto flex items-center justify-between text-xs font-medium text-slate-500">
									<div className="flex items-center">
										<Toggle
											label={ __( 'Active', 'airygen-seo' ) }
											hideLabelText
											checked={
												isPanelAvailable( panel ) &&
													settings.panelVisibility[ panel.key ] !== false
											}
											disabled={ ! isPanelAvailable( panel ) }
											onChange={ ( value ) => onPanelToggle( panel.key, value ) }
										/>
									</div>
									<span>
										{ sprintf(
											/* translators: %s is the module name. */
											__( 'Enabled by %s', 'airygen-seo' ),
											moduleLabel( panel.relatedModule ),
										) }
									</span>
								</div>
							</div>
						</div>
					) ) }
				</div>

				{ orderedPanels.length === 0 ? (
					<p className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
						{ __( 'No panels available.', 'airygen-seo' ) }
					</p>
				) : null }
			</div>

			<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'In-Article Content Order', 'airygen-seo' ) }
						</div>
						<p className="mt-2 text-sm text-slate-500">
							{ __(
								'Drag content blocks between zones to control where they appear, and reorder within each zone to set their stacking sequence.',
								'airygen-seo',
							) }
						</p>
					</div>
				</div>
				<div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
					<label htmlFor="airygen-cb-gap" className="flex flex-col gap-1">
						<span className="text-xs font-medium text-slate-600">
							{ __( 'Gap between blocks (px)', 'airygen-seo' ) }
						</span>
						<input
							id="airygen-cb-gap"
							type="number"
							min={ 0 }
							value={ contentBlockGap }
							onChange={ ( e ) => onContentBlockGapChange( Math.max( 0, parseInt( e.target.value, 10 ) || 0 ) ) }
							className="airygen-field"
						/>
					</label>
					<label htmlFor="airygen-cb-margin-top" className="flex flex-col gap-1">
						<span className="text-xs font-medium text-slate-600">
							{ __( 'Content top margin (px)', 'airygen-seo' ) }
						</span>
						<input
							id="airygen-cb-margin-top"
							type="number"
							min={ 0 }
							value={ contentBlockMarginTop }
							onChange={ ( e ) => onContentBlockMarginTopChange( Math.max( 0, parseInt( e.target.value, 10 ) || 0 ) ) }
							className="airygen-field"
						/>
					</label>
				</div>
				<div className="mt-6">
					<ContentBlocksOrder
						blocks={ contentBlocks }
						order={ contentBlockOrder }
						onOrderChange={ onContentBlockOrderChange }
						onZoneChange={ onContentBlockZoneChange }
					/>
				</div>
			</div>

			<footer className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div>
					{ isDirty ? (
						<span className="text-sm font-medium text-slate-700">
							{ getUnsavedChangesLabel() }
						</span>
					) : (
						<span className="text-sm text-slate-400">
							{ getAllChangesSavedLabel() }
						</span>
					) }
				</div>
				<div>
					<Button
						variant="outline"
						onClick={ onSave }
						loading={ isSaving }
						disabled={ ! isDirty || isSaving }
					>
						{ isSaving
							? getSavingLabel()
							: getSaveChangesLabel() }
					</Button>
				</div>
			</footer>
		</div>
	);
};

export default DashboardPage;

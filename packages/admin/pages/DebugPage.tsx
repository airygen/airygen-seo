import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import Button from '../components/Button';
import Notice from '../components/Notice';
import Spinner from '../components/Spinner';
import Select from '../components/Select';
import Toggle from '../components/Toggle';
import { getLoadingItemLabel } from '../../shared/i18nPhrases';
import type { DebugState } from '../types/debug';
import classNames from '../utils/classNames';

type DebugPageProps = {
	debugState: DebugState | null;
	debugTab: 'config' | 'logs';
	onSelectTab: ( tab: 'config' | 'logs' ) => void;
	isDebugLoading: boolean;
	isDebugEnabling: boolean;
	onEnableDebug: () => void;
	onDisableDebug: () => void;
	onRefresh: () => void;
	onClearLogs: () => void;
	onToggleClassicEditor: ( value: boolean ) => void;
	onChangeDebugLevel: ( level: 'error' | 'warning' | 'info' ) => void;
	selectedLogDate: string | null;
	onSelectLogDate: ( value: string | null ) => void;
	onLoadLog: ( value: string | null ) => void;
	isLogLoading: boolean;
	logViewerError: string | null;
	logViewerContent: string;
	debugError: string | null;
	onDismissError: () => void;
};

const DebugPage = ( {
	debugState,
	debugTab,
	onSelectTab,
	isDebugLoading,
	isDebugEnabling,
	onEnableDebug,
	onDisableDebug,
	onRefresh,
	onClearLogs,
	onToggleClassicEditor,
	onChangeDebugLevel,
	selectedLogDate,
	onSelectLogDate,
	onLoadLog,
	isLogLoading,
	logViewerError,
	logViewerContent,
	debugError,
	onDismissError,
}: DebugPageProps ) => {
	const enabled = Boolean( debugState?.config.enabled );
	const directoryExists = Boolean( debugState?.config.directory );
	const directory = directoryExists
		? debugState?.config.directory ?? ''
		: __( 'Directory not found', 'airygen-seo' );
	const logs = debugState?.logs ?? [];

	const handleToggleChange = ( value: boolean ) => {
		if ( value && ! enabled ) {
			onEnableDebug();
		} else if ( ! value && enabled ) {
			onDisableDebug();
		}
	};

	const forceClassicEnabled = Boolean( debugState?.config.forceClassic );
	const debugLevel = debugState?.config.level ?? 'info';

	const handleClassicToggle = ( value: boolean ) => {
		if ( value !== forceClassicEnabled ) {
			onToggleClassicEditor( value );
		}
	};

	const handleLevelChange = ( value: string ) => {
		if ( value === 'error' || value === 'warning' || value === 'info' ) {
			onChangeDebugLevel( value );
		}
	};

	const handleRefreshLogs = () => {
		onRefresh();
		if ( selectedLogDate ) {
			onLoadLog( selectedLogDate );
		}
	};

	const renderSetup = () => (
		<div className="space-y-4">
			<div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
				<div className="space-y-2">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Debug Status', 'airygen-seo' ) }
					</p>
					<p className="text-sm text-slate-600">
						{ enabled
							? __( 'Enabled', 'airygen-seo' )
							: __( 'Disabled', 'airygen-seo' ) }
					</p>
				</div>
				<div className="mt-4 flex flex-col gap-3">
					<div className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
						<div>
							<p className="text-sm font-medium text-gray-800">
								{ __( 'Enable Debug Mode', 'airygen-seo' ) }
							</p>
							<p className="text-xs text-slate-500">
								{ __( 'Enable debug logging to file.', 'airygen-seo' ) }
							</p>
						</div>
						<Toggle
							label={ __( 'Enable Debug Mode', 'airygen-seo' ) }
							hideLabelText
							checked={ enabled }
							disabled={ isDebugEnabling }
							onChange={ handleToggleChange }
						/>
					</div>
					<div className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
						<div>
							<p className="text-sm font-medium text-gray-800">
								{ __( 'Force Classic Editor', 'airygen-seo' ) }
							</p>
							<p className="text-xs text-slate-500">
								{ __( 'Disable the block editor and load Classic Editor for testing.', 'airygen-seo' ) }
							</p>
						</div>
						<Toggle
							label={ __( 'Force Classic Editor', 'airygen-seo' ) }
							hideLabelText
							checked={ forceClassicEnabled }
							disabled={ isDebugEnabling }
							onChange={ handleClassicToggle }
						/>
					</div>
					<div className="rounded-lg border border-slate-200 px-4 py-3">
						<p className="text-sm font-medium text-gray-800">
							{ __( 'Debug level', 'airygen-seo' ) }
						</p>
						<p className="text-xs text-slate-500">
							{ __( 'Choose how much debug information is recorded.', 'airygen-seo' ) }
						</p>
						<Select
							className="mt-3"
							value={ debugLevel }
							options={ [
								{ value: 'error', label: __( 'Error', 'airygen-seo' ) },
								{ value: 'warning', label: __( 'Warning', 'airygen-seo' ) },
								{ value: 'info', label: __( 'Info', 'airygen-seo' ) },
							] }
							onChange={ handleLevelChange }
							disabled={ isDebugEnabling }
						/>
					</div>
					<Button
						variant="secondary"
						onClick={ onRefresh }
						loading={ isDebugLoading }
					>
						{ __( 'Refresh', 'airygen-seo' ) }
					</Button>
				</div>
			</div>

			<div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
				<div className="space-y-2">
					<div>
						<p className="text-sm font-medium text-gray-800">
							{ __( 'Log Directory', 'airygen-seo' ) }
						</p>
						<p className="text-sm text-slate-500">
							{ __( 'Location where logs are stored.', 'airygen-seo' ) }
						</p>
					</div>
					<code className="block break-all rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-700">
						{ directory || __( 'No directory configured', 'airygen-seo' ) }
					</code>
					<p className="text-xs text-slate-500">
						<span className="font-medium">
							{ __( 'Slug', 'airygen-seo' ) }:
						</span>{ ' ' }
						<span className="font-mono">
							{ enabled && debugState?.config.slug
								? debugState.config.slug
								: __( 'N/A', 'airygen-seo' ) }
						</span>
					</p>
				</div>
			</div>
		</div>
	);

	const renderLogs = () => {
		if ( ! enabled ) {
			return (
				<div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
					<p className="text-sm text-slate-600">
						{ __( 'Debug mode must be enabled to view logs.', 'airygen-seo' ) }
					</p>
					<div className="mt-4 flex flex-wrap gap-3">
						<Button
							variant="secondary"
							onClick={ onRefresh }
							loading={ isDebugLoading }
						>
							{ __( 'Refresh', 'airygen-seo' ) }
						</Button>
					</div>
				</div>
			);
		}

		let logPanel: ReactNode;
		if ( isLogLoading ) {
			logPanel = (
				<div className="flex items-center gap-3 text-slate-200">
					<Spinner size="sm" className="border-slate-400 border-t-white" />
					<span>{ getLoadingItemLabel( __( 'log content', 'airygen-seo' ) ) }</span>
				</div>
			);
		} else if ( logViewerError ) {
			logPanel = <p className="text-rose-300">{ logViewerError }</p>;
		} else if ( logViewerContent ) {
			logPanel = (
				<pre className="max-h-[420px] overflow-y-auto whitespace-pre-wrap font-mono text-xs">
					{ logViewerContent }
				</pre>
			);
		} else {
			logPanel = (
				<p className="text-slate-400">
					{ __( 'Select a log file to view content.', 'airygen-seo' ) }
				</p>
			);
		}

		return (
			<div className="space-y-4">
				<div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
					<div className="space-y-2">
						<div>
							<p className="text-sm font-medium text-gray-800">
								{ __( 'Log Files', 'airygen-seo' ) }
							</p>
							<p className="text-sm text-slate-500">
								{ __( 'Select a log file to view.', 'airygen-seo' ) }
							</p>
						</div>
						<Select
							className="mt-3"
							value={ selectedLogDate ?? '' }
							options={
								logs.length === 0
									? [ { value: '', label: __( 'No logs found', 'airygen-seo' ) } ]
									: logs.map( ( entry ) => ( {
										value: entry.date,
										label: `${ entry.date } - ${ entry.human_size }`,
									} ) )
							}
							onChange={ ( value ) => onSelectLogDate( value || null ) }
						/>
					</div>

					<div className="mt-4 flex flex-wrap gap-3">
						<Button
							onClick={ () => onLoadLog( selectedLogDate ) }
							loading={ isLogLoading }
							disabled={ ! selectedLogDate }
							variant="secondary"
						>
							{ __( 'Load Log', 'airygen-seo' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ handleRefreshLogs }
							loading={ isDebugLoading }
						>
							{ __( 'Refresh Logs', 'airygen-seo' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ onClearLogs }
							loading={ isDebugLoading }
							disabled={ logs.length === 0 }
						>
							{ __( 'Clear Logs', 'airygen-seo' ) }
						</Button>
					</div>
				</div>

				<div className="rounded-lg border border-slate-900/40 bg-slate-950 p-4 text-xs text-slate-50 shadow-sm">
					{ logPanel }
				</div>

				<div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
					<div className="airygen_h3_title">
						{ __( 'Log Files', 'airygen-seo' ) }
					</div>
					{ logs.length > 0 ? (
						<ul className="mt-3 divide-y divide-slate-100 text-sm">
							{ logs.map( ( entry ) => (
								<li
									key={ entry.date }
									className="flex items-center justify-between py-2"
								>
									<div>
										<p className="font-medium text-slate-900">
											{ entry.date }
										</p>
										<p className="text-xs text-slate-500">
											{ entry.filename }
										</p>
									</div>
									<span className="text-xs text-slate-500">
										{ entry.human_size }
									</span>
								</li>
							) ) }
						</ul>
					) : (
						<p className="mt-3 text-sm text-slate-500">
							{ __( 'No logs found', 'airygen-seo' ) }
						</p>
					) }
				</div>
			</div>
		);
	};

	let debugContent: ReactNode;
	if ( isDebugLoading && ! debugState ) {
		debugContent = (
			<div className="flex items-center gap-3 text-sm text-slate-600">
				<Spinner size="sm" />
				<span>{ __( 'Loading…', 'airygen-seo' ) }</span>
			</div>
		);
	} else if ( debugTab === 'config' ) {
		debugContent = renderSetup();
	} else {
		debugContent = renderLogs();
	}

	const tabs = [
		{ id: 'config', label: __( 'Configuration', 'airygen-seo' ) },
		{ id: 'logs', label: __( 'Logs', 'airygen-seo' ) },
	] as const;

	return (
		<div className="space-y-6">
			<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
				<div className="space-y-2">
					<div className="airygen_h2_title">
						{ __( 'Debug Information', 'airygen-seo' ) }
					</div>
					<p className="text-sm text-slate-500">
						{ __( 'View debug information and logs.', 'airygen-seo' ) }
					</p>
				</div>
				<div className="mt-4 flex flex-wrap gap-2">
					{ tabs.map( ( tab ) => {
						const active = tab.id === debugTab;
						return (
							<button
								key={ tab.id }
								type="button"
								onClick={ () => onSelectTab( tab.id ) }
								className={ classNames(
									'rounded-full px-4 py-2 text-sm font-medium transition-colors',
									active
										? 'bg-sky-500 text-white shadow-sm'
										: 'border border-slate-200 bg-slate-50 text-slate-600 hover:border-sky-200 hover:text-slate-900',
								) }
								aria-current={ active ? 'page' : undefined }
							>
								{ tab.label }
							</button>
						);
					} ) }
				</div>
				{ debugError ? (
					<div className="mt-4">
						<Notice status="error" onClose={ onDismissError }>
							{ debugError }
						</Notice>
					</div>
				) : null }
				<div className="mt-6">{ debugContent }</div>
			</div>
		</div>
	);
};

export default DebugPage;

import { __ } from '@wordpress/i18n';
import Button from '../components/Button';
import Notice from '../components/Notice';
import Spinner from '../components/Spinner';
import Select from '../components/Select';
import Toggle from '../components/Toggle';
import type { DebugState } from '../types/debug';

type DebugPageProps = {
	debugState: DebugState | null;
	isDebugLoading: boolean;
	isDebugEnabling: boolean;
	onEnableDebug: () => void;
	onDisableDebug: () => void;
	onRefresh: () => void;
	onToggleClassicEditor: ( value: boolean ) => void;
	onChangeDebugLevel: ( level: 'error' | 'warning' | 'info' ) => void;
	debugError: string | null;
	onDismissError: () => void;
};

const DebugPage = ( {
	debugState,
	isDebugLoading,
	isDebugEnabling,
	onEnableDebug,
	onDisableDebug,
	onRefresh,
	onToggleClassicEditor,
	onChangeDebugLevel,
	debugError,
	onDismissError,
}: DebugPageProps ) => {
	const enabled = Boolean( debugState?.config.enabled );
	const forceClassicEnabled = Boolean( debugState?.config.forceClassic );
	const debugLevel = debugState?.config.level ?? 'info';

	const handleToggleChange = ( value: boolean ) => {
		if ( value && ! enabled ) {
			onEnableDebug();
		} else if ( ! value && enabled ) {
			onDisableDebug();
		}
	};

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

	if ( isDebugLoading && ! debugState ) {
		return (
			<div className="flex items-center gap-3 text-sm text-slate-600">
				<Spinner size="sm" />
				<span>{ __( 'Loading…', 'airygen-seo' ) }</span>
			</div>
		);
	}

	return (
		<div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
			<div className="airygen_h2_title">{ __( 'Debug', 'airygen-seo' ) }</div>
			{ debugError ? (
				<div className="mt-4">
					<Notice status="error" onClose={ onDismissError }>
						{ debugError }
					</Notice>
				</div>
			) : null }
			<div className="mt-6 flex flex-col gap-3">
				<div className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Enable Debug Mode', 'airygen-seo' ) }
					</p>
					<Toggle
						label={ __( 'Enable Debug Mode', 'airygen-seo' ) }
						hideLabelText
						checked={ enabled }
						disabled={ isDebugEnabling }
						onChange={ handleToggleChange }
					/>
				</div>
				<div className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Force Classic Editor', 'airygen-seo' ) }
					</p>
					<Toggle
						label={ __( 'Force Classic Editor', 'airygen-seo' ) }
						hideLabelText
						checked={ forceClassicEnabled }
						disabled={ isDebugEnabling }
						onChange={ handleClassicToggle }
					/>
				</div>
				<div className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Debug level', 'airygen-seo' ) }
					</p>
					<Select
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
				<div>
					<Button
						variant="secondary"
						onClick={ onRefresh }
						loading={ isDebugLoading }
					>
						{ __( 'Refresh', 'airygen-seo' ) }
					</Button>
				</div>
			</div>
		</div>
	);
};

export default DebugPage;

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import Button from '../components/Button';
import { MigrationIcon } from '../components/Icons';
import classNames from '../utils/classNames';

type UninstallPrefs = {
	clearTables: boolean;
	clearOptions: boolean;
	clearMeta: boolean;
};

type MigrationPageProps = {
	restBase: string;
	yoastActive: boolean;
	onOpenYoast: () => void;
	rankMathActive: boolean;
	onOpenRankMath: () => void;
	aioseoActive: boolean;
	onOpenAioseo: () => void;
	seoPressActive: boolean;
	onOpenSeoPress: () => void;
	onOpenDebug?: () => void;
};

const MigrationPage = ( {
	restBase,
	yoastActive,
	onOpenYoast,
	rankMathActive,
	onOpenRankMath,
	aioseoActive,
	onOpenAioseo,
	seoPressActive,
	onOpenSeoPress,
	onOpenDebug,
}: MigrationPageProps ) => {
	// ── Export ────────────────────────────────────────────────────────────────
	const [ isExporting, setIsExporting ] = useState( false );

	const handleExport = async () => {
		setIsExporting( true );
		try {
			const data = await apiFetch< Record< string, unknown > >( {
				path: `${ restBase }/transfer/export`,
			} );
			const json = JSON.stringify( data, null, 2 );
			const blob = new Blob( [ json ], { type: 'application/json' } );
			const url = URL.createObjectURL( blob );
			const anchor = document.createElement( 'a' );
			const date = new Date().toISOString().slice( 0, 10 );
			anchor.href = url;
			anchor.download = `airygen-seo-settings-${ date }.json`;
			anchor.click();
			URL.revokeObjectURL( url );
		} finally {
			setIsExporting( false );
		}
	};

	// ── Import ────────────────────────────────────────────────────────────────
	const [ isImporting, setIsImporting ] = useState( false );
	const [ importFile, setImportFile ] = useState< File | null >( null );
	const [ importStatus, setImportStatus ] = useState< 'idle' | 'success' | 'error' >( 'idle' );
	const [ importError, setImportError ] = useState( '' );
	const fileInputRef = useRef< HTMLInputElement >( null );

	const handleFileChange = ( e: React.ChangeEvent< HTMLInputElement > ) => {
		const file = e.target.files?.[ 0 ] ?? null;
		setImportFile( file );
		setImportStatus( 'idle' );
		setImportError( '' );
	};

	const handleImport = async () => {
		if ( ! importFile ) {
			return;
		}
		setIsImporting( true );
		setImportStatus( 'idle' );
		setImportError( '' );
		try {
			const text = await importFile.text();
			const data = JSON.parse( text ) as Record< string, unknown >;
			await apiFetch( {
				path: `${ restBase }/transfer/import`,
				method: 'POST',
				data,
			} );
			setImportStatus( 'success' );
			setImportFile( null );
			if ( fileInputRef.current ) {
				fileInputRef.current.value = '';
			}
		} catch ( err ) {
			setImportStatus( 'error' );
			const message =
				err instanceof Error
					? err.message
					: __( 'An unexpected error occurred.', 'airygen-seo' );
			setImportError( message );
		} finally {
			setIsImporting( false );
		}
	};

	// ── Uninstall preferences ─────────────────────────────────────────────────
	const [ uninstallPrefs, setUninstallPrefs ] = useState< UninstallPrefs >( {
		clearTables: false,
		clearOptions: false,
		clearMeta: false,
	} );
	const [ uninstallLoading, setUninstallLoading ] = useState( true );
	const [ uninstallSaving, setUninstallSaving ] = useState( false );
	const [ uninstallSaveStatus, setUninstallSaveStatus ] = useState< 'idle' | 'success' | 'error' >( 'idle' );

	useEffect( () => {
		apiFetch< UninstallPrefs >( { path: `${ restBase }/transfer/uninstall` } )
			.then( ( data ) => setUninstallPrefs( data ) )
			.catch( () => {} )
			.finally( () => setUninstallLoading( false ) );
	}, [ restBase ] );

	const handleUninstallPrefChange = ( key: keyof UninstallPrefs ) => {
		setUninstallPrefs( ( prev ) => ( { ...prev, [ key ]: ! prev[ key ] } ) );
		setUninstallSaveStatus( 'idle' );
	};

	const handleSaveUninstall = async () => {
		setUninstallSaving( true );
		setUninstallSaveStatus( 'idle' );
		try {
			await apiFetch( {
				path: `${ restBase }/transfer/uninstall`,
				method: 'POST',
				data: uninstallPrefs,
			} );
			setUninstallSaveStatus( 'success' );
		} catch {
			setUninstallSaveStatus( 'error' );
		} finally {
			setUninstallSaving( false );
		}
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<span className="flex h-12 w-12 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600">
					<MigrationIcon className="h-8 w-8" aria-hidden="true" />
				</span>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Migration', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Bring SEO data from other plugins into Airygen SEO. Start with the tools you already use and migrate only what you need.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>
			<div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Migrations', 'airygen-seo' ) }
						</div>
						<div className="airygen_h1_description">
							{ __(
								'Choose the plugin you want to migrate from and review the available steps.',
								'airygen-seo',
							) }
						</div>
					</div>
				</div>

				<div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									Yoast
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Import post titles, descriptions, social overrides, and schema settings.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="mt-6 flex flex-wrap items-center justify-between gap-3 min-h-[30px]">
							<span
								className={ classNames(
									'text-xs font-medium',
									yoastActive ? 'text-emerald-600' : 'text-slate-400',
								) }
							>
								{ yoastActive
									? __( 'Yoast detected', 'airygen-seo' )
									: __( 'Yoast not detected', 'airygen-seo' ) }
							</span>
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ onOpenYoast }
							>
								{ __( 'Details', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									AIOSEO
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Import post metadata, social defaults, schema settings, breadcrumbs, and redirects.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="mt-6 flex flex-wrap items-center justify-between gap-3 min-h-[30px]">
							<span
								className={ classNames(
									'text-xs font-medium',
									aioseoActive ? 'text-emerald-600' : 'text-slate-400',
								) }
							>
								{ aioseoActive
									? __( 'Plugin detected', 'airygen-seo' )
									: __( 'Plugin not detected', 'airygen-seo' ) }
							</span>
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ onOpenAioseo }
							>
								{ __( 'Details', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									Rank Math
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Import titles, descriptions, social overrides, breadcrumbs, and redirect rules.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="mt-6 flex flex-wrap items-center justify-between gap-3 min-h-[30px]">
							<span
								className={ classNames(
									'text-xs font-medium',
									rankMathActive ? 'text-emerald-600' : 'text-slate-400',
								) }
							>
								{ rankMathActive
									? __( 'Plugin detected', 'airygen-seo' )
									: __( 'Plugin not detected', 'airygen-seo' ) }
							</span>
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ onOpenRankMath }
							>
								{ __( 'Details', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									SEOPress
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Review mapped title templates, robots directives, social metadata, and redirects before import.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="mt-6 flex flex-wrap items-center justify-between gap-3 min-h-[30px]">
							<span
								className={ classNames(
									'text-xs font-medium',
									seoPressActive ? 'text-emerald-600' : 'text-slate-400',
								) }
							>
								{ seoPressActive
									? __( 'Plugin detected', 'airygen-seo' )
									: __( 'Plugin not detected', 'airygen-seo' ) }
							</span>
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ onOpenSeoPress }
							>
								{ __( 'Details', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				</div>
			</div>

			{ /* ── Airygen SEO section ────────────────────────────────────────────── */ }
			<div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
				<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Airygen SEO', 'airygen-seo' ) }
						</div>
						<div className="airygen_h1_description">
							{ __(
								'Export or import all plugin settings, and configure what data is removed when the plugin is uninstalled.',
								'airygen-seo',
							) }
						</div>
					</div>
					{ onOpenDebug ? (
						<Button
							variant="secondary"
							className="px-3 py-1.5 text-xs"
							onClick={ onOpenDebug }
						>
							{ __( 'Debug', 'airygen-seo' ) }
						</Button>
					) : null }
				</div>

				<div className="mt-6 grid gap-4 md:grid-cols-3">
					{ /* Export */ }
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									{ __( 'Export settings', 'airygen-seo' ) }
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Download a JSON snapshot of all your current settings. Use this to back up your configuration or transfer it to another site.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="mt-6 flex items-center justify-end">
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ handleExport }
								disabled={ isExporting }
							>
								{ isExporting
									? __( 'Exporting…', 'airygen-seo' )
									: __( 'Export', 'airygen-seo' ) }
							</Button>
						</div>
					</div>

					{ /* Import */ }
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									{ __( 'Import settings', 'airygen-seo' ) }
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Upload a JSON file exported from Airygen SEO to restore or copy your settings. Existing settings will be overwritten.',
										'airygen-seo',
									) }
								</p>
							</div>
							<div className="mt-2">
								<input
									ref={ fileInputRef }
									type="file"
									accept="application/json,.json"
									onChange={ handleFileChange }
									className="block w-full text-sm text-slate-500 file:mr-3 file:rounded file:border-0 file:bg-slate-200 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-300"
								/>
							</div>
							{ importStatus === 'success' && (
								<p className="text-xs font-medium text-emerald-600">
									{ __( 'Settings imported successfully.', 'airygen-seo' ) }
								</p>
							) }
							{ importStatus === 'error' && (
								<p className="text-xs font-medium text-red-600">
									{ importError || __( 'Import failed. Please check the file and try again.', 'airygen-seo' ) }
								</p>
							) }
						</div>
						<div className="mt-6 flex items-center justify-end">
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ handleImport }
								disabled={ ! importFile || isImporting }
							>
								{ isImporting
									? __( 'Importing…', 'airygen-seo' )
									: __( 'Import', 'airygen-seo' ) }
							</Button>
						</div>
					</div>

					{ /* Uninstall */ }
					<div className="relative flex h-full flex-col rounded-xl border border-slate-200 bg-slate-50 p-5">
						<div className="flex flex-1 flex-col gap-3">
							<div>
								<div className="airygen_h3_title">
									{ __( 'Uninstall', 'airygen-seo' ) }
								</div>
								<p className="mt-1 text-sm text-slate-500">
									{ __(
										'Choose what data is permanently removed when the plugin is deleted. These actions only run on full uninstall, not on deactivation.',
										'airygen-seo',
									) }
								</p>
							</div>
							{ uninstallLoading ? (
								<p className="text-xs text-slate-400">
									{ __( 'Loading…', 'airygen-seo' ) }
								</p>
							) : (
								<div className="mt-1 flex flex-col gap-3">
									{ /* eslint-disable jsx-a11y/label-has-associated-control -- input is nested inside label */ }
									<label className="flex cursor-pointer items-start gap-2.5">
										<input
											type="checkbox"
											className="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-700 focus:ring-slate-500"
											checked={ uninstallPrefs.clearTables }
											onChange={ () => handleUninstallPrefChange( 'clearTables' ) }
										/>
										<span className="text-sm text-slate-700">
											{ __( 'Remove all custom database tables', 'airygen-seo' ) }
										</span>
									</label>
									<label className="flex cursor-pointer items-start gap-2.5">
										<input
											type="checkbox"
											className="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-700 focus:ring-slate-500"
											checked={ uninstallPrefs.clearOptions }
											onChange={ () => handleUninstallPrefChange( 'clearOptions' ) }
										/>
										<span className="text-sm text-slate-700">
											{ __( 'Remove all plugin settings (wp_options)', 'airygen-seo' ) }
										</span>
									</label>
									<label className="flex cursor-pointer items-start gap-2.5">
										<input
											type="checkbox"
											className="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-700 focus:ring-slate-500"
											checked={ uninstallPrefs.clearMeta }
											onChange={ () => handleUninstallPrefChange( 'clearMeta' ) }
										/>
										<span className="text-sm text-slate-700">
											{ __( 'Remove all post, term, and user metadata', 'airygen-seo' ) }
										</span>
									</label>
									{ /* eslint-enable jsx-a11y/label-has-associated-control */ }
								</div>
							) }
							{ uninstallSaveStatus === 'success' && (
								<p className="text-xs font-medium text-emerald-600">
									{ __( 'Preferences saved.', 'airygen-seo' ) }
								</p>
							) }
							{ uninstallSaveStatus === 'error' && (
								<p className="text-xs font-medium text-red-600">
									{ __( 'Failed to save preferences.', 'airygen-seo' ) }
								</p>
							) }
						</div>
						<div className="mt-6 flex items-center justify-end">
							<Button
								variant="secondary"
								className="px-3 py-1.5 text-xs"
								onClick={ handleSaveUninstall }
								disabled={ uninstallLoading || uninstallSaving }
							>
								{ uninstallSaving
									? __( 'Saving…', 'airygen-seo' )
									: __( 'Save', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
};

export default MigrationPage;

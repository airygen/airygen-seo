import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Button from '../../components/Button';

type AioseoProgress = {
	total: number;
	migrated: number;
	remaining: number;
	percent: number;
	completed: boolean;
	batchSize: number;
};

type MigrationResponse = {
	progress?: AioseoProgress;
	processed?: number;
	settings?: {
		available?: boolean;
	};
};

type AioseoMigrationPanelProps = {
	restBase: string;
	isActive: boolean;
};

const DEFAULT_PROGRESS: AioseoProgress = {
	total: 0,
	migrated: 0,
	remaining: 0,
	percent: 0,
	completed: false,
	batchSize: 10,
};

const normalizeBase = ( base: string ): string => base.replace( /\/+$/, '' );

const AioseoMigrationPanel = ( { restBase, isActive }: AioseoMigrationPanelProps ) => {
	const [ progress, setProgress ] = useState<AioseoProgress>( DEFAULT_PROGRESS );
	const [ redirectsProgress, setRedirectsProgress ] =
		useState<AioseoProgress>( DEFAULT_PROGRESS );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isImporting, setIsImporting ] = useState( false );
	const [ isRedirectImporting, setIsRedirectImporting ] = useState( false );
	const [ settingsStatus, setSettingsStatus ] = useState<string | null>( null );
	const [ redirectStatus, setRedirectStatus ] = useState<string | null>( null );
	const [ error, setError ] = useState<string | null>( null );
	const [ settingsAvailable, setSettingsAvailable ] = useState( false );

	const statusPath = `${ normalizeBase( restBase ) }/migration/aioseo`;
	const importPath = `${ normalizeBase( restBase ) }/migration/aioseo/import`;
	const settingsPath = `${ normalizeBase( restBase ) }/migration/aioseo/settings`;
	const redirectsPath = `${ normalizeBase( restBase ) }/migration/aioseo/redirects`;

	const percent = useMemo( () => {
		const value = Number.isFinite( progress.percent )
			? progress.percent
			: 0;
		return Math.min( 100, Math.max( 0, value ) );
	}, [ progress.percent ] );

	const progressLabel = useMemo(
		() =>
			`${ percent }% — ${ progress.migrated } ${ __( 'posts imported', 'airygen-seo' ) } / ${ progress.total } ${ __( 'total', 'airygen-seo' ) }`,
		[ percent, progress.migrated, progress.total ],
	);

	const redirectPercent = useMemo( () => {
		const value = Number.isFinite( redirectsProgress.percent )
			? redirectsProgress.percent
			: 0;
		return Math.min( 100, Math.max( 0, value ) );
	}, [ redirectsProgress.percent ] );

	const redirectsLabel = useMemo(
		() =>
			`${ redirectPercent }% — ${ redirectsProgress.migrated } ${ __( 'rules imported', 'airygen-seo' ) } / ${ redirectsProgress.total } ${ __( 'total', 'airygen-seo' ) }`,
		[ redirectPercent, redirectsProgress.migrated, redirectsProgress.total ],
	);

	const loadStatus = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const response = ( await apiFetch( {
				path: statusPath,
			} ) ) as MigrationResponse;

			if ( response.progress ) {
				setProgress( { ...DEFAULT_PROGRESS, ...response.progress } );
			}

			setSettingsAvailable( Boolean( response.settings?.available ) );

			const redirectsResponse = ( await apiFetch( {
				path: redirectsPath,
			} ) ) as MigrationResponse;

			if ( redirectsResponse.progress ) {
				setRedirectsProgress( {
					...DEFAULT_PROGRESS,
					...redirectsResponse.progress,
				} );
			}
		} catch ( fetchError ) {
			setError(
				fetchError instanceof Error
					? fetchError.message
					: __( 'Unable to load migration status.', 'airygen-seo' ),
			);
		} finally {
			setIsLoading( false );
		}
	}, [ statusPath, redirectsPath ] );

	const runBatch = useCallback( async () => {
		const response = ( await apiFetch( {
			path: importPath,
			method: 'POST',
		} ) ) as MigrationResponse;

		if ( response.progress ) {
			const nextProgress = { ...DEFAULT_PROGRESS, ...response.progress };
			setProgress( nextProgress );
			return nextProgress;
		}

		return progress;
	}, [ importPath, progress ] );

	const runRedirectBatch = useCallback( async () => {
		const response = ( await apiFetch( {
			path: redirectsPath,
			method: 'POST',
		} ) ) as MigrationResponse;

		const processed = Number( response.processed ?? 0 );

		if ( response.progress ) {
			const nextProgress = { ...DEFAULT_PROGRESS, ...response.progress };
			setRedirectsProgress( nextProgress );
			return { progress: nextProgress, processed };
		}

		return { progress: redirectsProgress, processed };
	}, [ redirectsPath, redirectsProgress ] );

	const startImport = useCallback( async () => {
		if ( isImporting ) {
			return;
		}

		setIsImporting( true );
		setError( null );

		try {
			let nextProgress = await runBatch();
			while (
				nextProgress &&
				! nextProgress.completed &&
				nextProgress.remaining > 0
			) {
				await new Promise( ( resolve ) => setTimeout( resolve, 150 ) );
				nextProgress = await runBatch();
			}
		} catch ( fetchError ) {
			setError(
				fetchError instanceof Error
					? fetchError.message
					: __( 'Migration failed. Please try again.', 'airygen-seo' ),
			);
		} finally {
			setIsImporting( false );
		}
	}, [ isImporting, runBatch ] );

	const startSettingsImport = useCallback( async () => {
		setSettingsStatus( null );
		setError( null );

		try {
			await apiFetch( {
				path: settingsPath,
				method: 'POST',
			} );
			setSettingsStatus( __( 'Settings imported.', 'airygen-seo' ) );
		} catch ( fetchError ) {
			setError(
				fetchError instanceof Error
					? fetchError.message
					: __( 'Settings migration failed.', 'airygen-seo' ),
			);
		}
	}, [ settingsPath ] );

	const startRedirectImport = useCallback( async () => {
		if ( isRedirectImporting ) {
			return;
		}

		setRedirectStatus( null );
		setError( null );
		setIsRedirectImporting( true );

		try {
			let batch = await runRedirectBatch();
			while (
				batch.progress &&
				batch.processed > 0 &&
				! batch.progress.completed &&
				batch.progress.remaining > 0
			) {
				await new Promise( ( resolve ) => setTimeout( resolve, 150 ) );
				batch = await runRedirectBatch();
			}
			setRedirectStatus( __( 'Redirects imported.', 'airygen-seo' ) );
		} catch ( fetchError ) {
			setError(
				fetchError instanceof Error
					? fetchError.message
					: __( 'Redirect migration failed.', 'airygen-seo' ),
			);
		} finally {
			setIsRedirectImporting( false );
		}
	}, [ isRedirectImporting, runRedirectBatch ] );

	useEffect( () => {
		void loadStatus();
	}, [ loadStatus ] );

	return (
		<div className="space-y-6">
			<div className="space-y-2">
				<div className="flex flex-wrap items-center justify-between gap-3">
					<div className="airygen_h1_title">
						AIOSEO
					</div>
					<span
						className={ `inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium ${
							isActive
								? 'border-emerald-200 bg-emerald-50 text-emerald-700'
								: 'border-slate-200 bg-slate-50 text-slate-500'
						}` }
					>
						{ isActive
							? __( 'Plugin active', 'airygen-seo' )
							: __( 'Plugin inactive', 'airygen-seo' ) }
					</span>
				</div>
				<div className="airygen_h1_description">
					{ __(
						'Prepare to migrate All-in-One SEO Pack metadata, social defaults, schema settings, and redirects into Airygen SEO.',
						'airygen-seo',
					) }
				</div>
			</div>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Post meta migration', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Import AIOSEO per-post metadata (titles, descriptions, canonicals, social overrides, and robots).',
								'airygen-seo',
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						className="px-3 py-1.5 text-xs"
						onClick={ startImport }
						disabled={
							isLoading ||
							! isActive ||
							isImporting ||
							progress.completed ||
							progress.total === 0
						}
						loading={ isImporting }
					>
						{ isImporting
							? __( 'Importing…', 'airygen-seo' )
							: __( 'Import', 'airygen-seo' ) }
					</Button>
				</div>

				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<div className="space-y-2">
						<div className="flex items-center justify-between text-xs text-slate-500">
							<span>{ __( 'Progress', 'airygen-seo' ) }</span>
							<span>
								{ `${ String( percent ) }%` }
							</span>
						</div>
						<div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
							<div
								className="h-full rounded-full bg-sky-500 transition-all"
								style={ { width: `${ percent }%` } }
							/>
						</div>
						<div className="text-xs text-slate-500">{ progressLabel }</div>
					</div>
				</div>

				{ error ? (
					<p className="text-xs text-rose-600">{ error }</p>
				) : null }
			</section>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Global settings migration', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Import AIOSEO global templates, organization schema, social defaults, and breadcrumbs.',
								'airygen-seo',
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						className="px-3 py-1.5 text-xs"
						onClick={ startSettingsImport }
						disabled={ isLoading || ! settingsAvailable || ! isActive }
					>
						{ __( 'Import', 'airygen-seo' ) }
					</Button>
				</div>
				{ settingsStatus ? (
					<p className="text-xs text-emerald-600">{ settingsStatus }</p>
				) : null }
			</section>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
					<div>
						<div className="airygen_h2_title">
							{ __( 'Redirect rules migration', 'airygen-seo' ) }
						</div>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Import redirects from All-in-One SEO Pack and add them to Airygen Redirects.',
								'airygen-seo',
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						className="px-3 py-1.5 text-xs"
						onClick={ startRedirectImport }
						disabled={
							isLoading ||
							! isActive ||
							isRedirectImporting ||
							redirectsProgress.completed ||
							redirectsProgress.total === 0
						}
						loading={ isRedirectImporting }
					>
						{ isRedirectImporting
							? __( 'Importing…', 'airygen-seo' )
							: __( 'Import', 'airygen-seo' ) }
					</Button>
				</div>
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<div className="space-y-2">
						<div className="flex items-center justify-between text-xs text-slate-500">
							<span>{ __( 'Progress', 'airygen-seo' ) }</span>
							<span>
								{ `${ String( redirectPercent ) }%` }
							</span>
						</div>
						<div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
							<div
								className="h-full rounded-full bg-sky-500 transition-all"
								style={ { width: `${ redirectPercent }%` } }
							/>
						</div>
						<div className="text-xs text-slate-500">{ redirectsLabel }</div>
					</div>
				</div>
				{ redirectStatus ? (
					<p className="text-xs text-emerald-600">{ redirectStatus }</p>
				) : null }
			</section>
		</div>
	);
};

export default AioseoMigrationPanel;

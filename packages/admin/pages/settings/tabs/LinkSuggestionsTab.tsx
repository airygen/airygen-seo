import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getLoadingItemLabel } from '../../../../shared/i18nPhrases';

import Button from '../../../components/Button';
import Checkbox from '../../../components/Checkbox';
import HeadingIcon from '../../../components/HeadingIcon';
import ActionSchedulerPill from '../../../components/ActionSchedulerPill';
import Spinner from '../../../components/Spinner';
import Notice from '../../../components/Notice';
import Toggle from '../../../components/Toggle';
import Modal from '../../../components/Modal';
import { LinkCounterIcon } from '../../../components/Icons';
import type { MetaPayload, NoticeState } from '../../../types/api';
import type { RelatedIndexStats, RelatedSettings, RelatedSettingsResponse } from '../../../types/related';

type LinkSuggestionsTabProps = {
	restBase: string;
	meta: MetaPayload;
	actionSchedulerAvailable?: boolean;
	onNotice?: ( notice: NoticeState ) => void;
	onDirtyChange: ( dirty: boolean ) => void;
	registerSubmit: ( submit: () => Promise<void> ) => void;
	isSaving: boolean;
};

const DEFAULT_SETTINGS: RelatedSettings = {
	enabled: false,
	allowed_post_types: [],
	max_suggestions: 5,
};

const LinkSuggestionsTab = ( {
	restBase,
	meta,
	actionSchedulerAvailable,
	onNotice,
	onDirtyChange,
	registerSubmit,
	isSaving,
}: LinkSuggestionsTabProps ) => {
	const [ settings, setSettings ] = useState<RelatedSettings>( DEFAULT_SETTINGS );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ reindexing, setReindexing ] = useState( false );
	const [ reindexNotice, setReindexNotice ] = useState<{ status: 'success' | 'error'; message: string } | null>( null );
	const [ stats, setStats ] = useState<RelatedIndexStats[]>( [] );
	const showSchedulerPill = typeof actionSchedulerAvailable === 'boolean';
	const [ isReindexModalOpen, setIsReindexModalOpen ] = useState( false );

	const normalizedBase = useMemo( () => restBase.replace( /\/$/, '' ), [ restBase ] );
	const settingsPath = `${ normalizedBase }/link-suggestions/settings`;
	const reindexPath = `${ normalizedBase }/link-suggestions/reindex`;

	const loadSettings = useCallback( () => {
		setIsLoading( true );
		return apiFetch<RelatedSettingsResponse>( { path: settingsPath } )
			.then( ( response ) => {
				const nextStats = response.stats ?? [];
				setSettings( {
					...DEFAULT_SETTINGS,
					...response,
				} );
				setStats( nextStats );
				onDirtyChange( false );
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to load related posts settings.', 'airygen-seo' );
				setReindexNotice( { status: 'error', message } );
			} )
			.finally( () => setIsLoading( false ) );
	}, [ onDirtyChange, settingsPath ] );

	useEffect( () => {
		loadSettings();
	}, [ loadSettings ] );

	const handleSettingChange = ( patch: Partial<RelatedSettings> ) => {
		setSettings( ( current ) => {
			const next = { ...current, ...patch };
			onDirtyChange( true );
			return next;
		} );
	};

	const submitSettings = useCallback( async () => {
		const payload = settings ?? DEFAULT_SETTINGS;

		const response = await apiFetch<RelatedSettingsResponse>( {
			path: settingsPath,
			method: 'POST',
			data: payload,
		} );

		const nextStats = response.stats ?? [];
		setSettings( {
			...DEFAULT_SETTINGS,
			...response,
		} );
		setStats( nextStats );
		onDirtyChange( false );
	}, [ onDirtyChange, settings, settingsPath ] );

	useEffect( () => {
		registerSubmit( submitSettings );
	}, [ registerSubmit, submitSettings ] );

	const togglePostType = ( slug: string ) => {
		const next = settings.allowed_post_types.includes( slug )
			? settings.allowed_post_types.filter( ( value ) => value !== slug )
			: [ ...settings.allowed_post_types, slug ];
		handleSettingChange( { allowed_post_types: next } );
	};

	const handleReindex = () => {
		setReindexing( true );
		setReindexNotice( null );
		apiFetch<{ queued: boolean }>( {
			path: reindexPath,
			method: 'POST',
		} )
			.then( () => {
				if ( onNotice ) {
					onNotice( {
						status: 'success',
						message: __( 'Full reindex queued. We will refresh suggestions in the background.', 'airygen-seo' ),
					} );
				}
			} )
			.catch( ( error: unknown ) => {
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to queue reindex. Try again later.', 'airygen-seo' );
				setReindexNotice( { status: 'error', message } );
			} )
			.finally( () => setReindexing( false ) );
	};

	const handleConfirmReindex = () => {
		setIsReindexModalOpen( false );
		handleReindex();
	};

	const postTypes = meta.postTypes ?? [];

	if ( isLoading ) {
		return (
			<div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
				<Spinner />
				<span className="text-sm text-slate-600">
					{ getLoadingItemLabel( __( 'related posts settings', 'airygen-seo' ) ) }
				</span>
			</div>
		);
	}

	return (
		<>
			<div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<LinkCounterIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'Link Suggestions', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __( 'Find related posts while you edit.', 'airygen-seo' ) }
						</p>
						{ isSaving ? (
							<p className="mt-1 text-xs text-slate-500">
								{ __( 'Saving…', 'airygen-seo' ) }
							</p>
						) : null }
					</div>
				</div>
				{ showSchedulerPill ? (
					<ActionSchedulerPill available={ Boolean( actionSchedulerAvailable ) } />
				) : null }
			</div>

			<div className="rounded-xl border border-slate-200 bg-white p-4">
				<div className="flex items-center justify-between gap-3">
					<div className="airygen_h2_title">
						{ __( 'Enable', 'airygen-seo' ) }
					</div>
					<Toggle
						label={ __( 'Enable related post suggestions', 'airygen-seo' ) }
						hideLabelText
						checked={ settings.enabled }
						onChange={ ( value ) => handleSettingChange( { enabled: value } ) }
					/>
				</div>
				<p className="mt-1 text-sm text-slate-500">
					{ __(
						'When on, we index content in the background and surface suggestions in the editor.',
						'airygen-seo',
					) }
				</p>
			</div>

			<div className="rounded-xl border border-slate-200 bg-white p-4">
				<h3 className="text-lg font-semibold text-gray-800">
					{ __( 'Scope', 'airygen-seo' ) }
				</h3>
				<p className="mt-1 text-sm text-slate-500">
					{ __( 'Choose which post types are indexed for suggestions.', 'airygen-seo' ) }
				</p>

				<div className="mt-4 space-y-3">
					<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
						{ __( 'Post types to include', 'airygen-seo' ) }
					</p>
					<div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
						{ postTypes.map( ( type ) => (
							<div
								key={ type.slug }
								className="rounded-lg border border-slate-200 p-3"
							>
								<Checkbox
									label={ type.label }
									checked={ settings.allowed_post_types.includes( type.slug ) }
									onChange={ () => togglePostType( type.slug ) }
								/>
							</div>
						) ) }
					</div>
				</div>
			</div>

			<div className="rounded-xl border border-slate-200 bg-white p-4">
				<div className="flex items-start justify-between gap-3">
					<div>
						<h3 className="text-lg font-semibold text-gray-800">
							{ __( 'Index status', 'airygen-seo' ) }
						</h3>
						<p className="mt-1 text-sm text-slate-500">
							{ __(
								'Queue a background job to refresh keyphrases for all allowed post types.',
								'airygen-seo',
							) }
						</p>
						{ stats.length ? (
							<div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
								{ stats.map( ( item ) => (
									<div
										key={ item.post_type }
										className="rounded-lg border border-slate-200 p-3"
									>
										<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
											{ item.label }
										</p>
										<div className="mt-2 flex items-center justify-between text-sm text-slate-600">
											<span>
												{ __( 'Indexed', 'airygen-seo' ) }
											</span>
											<span className="font-medium text-slate-800">
												{ item.indexed }
											</span>
										</div>
										<div className="mt-1 flex items-center justify-between text-sm text-slate-600">
											<span>
												{ __( 'Not indexed', 'airygen-seo' ) }
											</span>
											<span className="font-medium text-slate-800">
												{ item.not_indexed }
											</span>
										</div>
									</div>
								) ) }
							</div>
						) : null }
					</div>
					<Button
						variant="secondary"
						className="px-3 py-1.5 text-xs"
						disabled={ reindexing || isSaving }
						onClick={ () => setIsReindexModalOpen( true ) }
					>
						{ reindexing
							? __( 'Queuing…', 'airygen-seo' )
							: __( 'Reindex', 'airygen-seo' ) }
					</Button>
				</div>
				{ reindexNotice ? (
					<div className="mt-3">
						<Notice
							status={ reindexNotice.status }
							onClose={ () => setReindexNotice( null ) }
						>
							{ reindexNotice.message }
						</Notice>
					</div>
				) : null }
			</div>

			<Modal
				isOpen={ isReindexModalOpen }
				onClose={ () => setIsReindexModalOpen( false ) }
				title={ __( 'Reindex suggestions', 'airygen-seo' ) }
				footer={
					<div className="flex justify-end gap-2 bg-slate-50">
						<Button
							variant="secondary"
							onClick={ () => setIsReindexModalOpen( false ) }
							disabled={ reindexing }
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleConfirmReindex }
							loading={ reindexing }
							disabled={ reindexing }
						>
							{ __( 'Confirm reindex', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<p className="text-sm text-slate-600">
					{ __(
						'Indexing updates automatically when you save content. Reindex only when you enable Link Suggestions for the first time or add new post types.',
						'airygen-seo',
					) }
				</p>
			</Modal>
		</>
	);
};

export default LinkSuggestionsTab;

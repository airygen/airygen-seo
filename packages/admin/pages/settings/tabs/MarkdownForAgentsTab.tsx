import HeadingIcon from '../../../components/HeadingIcon';
import { MarkdownForAgentsIcon } from '../../../components/Icons';
import Toggle from '../../../components/Toggle';
import Checkbox from '../../../components/Checkbox';
import Button from '../../../components/Button';
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	getLoadingItemLabel,
	getNoItemsFoundLabel,
} from '../../../../shared/i18nPhrases';

import type { MetaPayload } from '../../../types/api';
import type { MarkdownForAgentsSettings } from '../../../types/settings';

type MarkdownForAgentsTabProps = {
	settings: MarkdownForAgentsSettings;
	meta: MetaPayload;
	restBase: string;
	onChange: ( next: MarkdownForAgentsSettings ) => void;
};

type SnapshotRecord = {
	postId: number;
	postType: string;
	title: string;
	url: string;
	lastSynced: string;
	contentHash: string;
};

type SnapshotResponse = {
	records?: SnapshotRecord[];
	pagination?: {
		page?: number;
		perPage?: number;
		total?: number;
		totalPages?: number;
	};
};

const MarkdownForAgentsTab = ( {
	settings,
	meta,
	restBase,
	onChange,
}: MarkdownForAgentsTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'settings' | 'snapshots' | 'preview'>( 'settings' );
	const [ previewPostId, setPreviewPostId ] = useState( '' );
	const [ previewOutput, setPreviewOutput ] = useState( '' );
	const [ previewLoading, setPreviewLoading ] = useState( false );
	const [ statusMessage, setStatusMessage ] = useState( '' );
	const [ statusType, setStatusType ] = useState<'success' | 'error'>( 'success' );
	const [ snapshotRecords, setSnapshotRecords ] = useState<SnapshotRecord[]>( [] );
	const [ snapshotPostType, setSnapshotPostType ] = useState( 'all' );
	const [ snapshotPage, setSnapshotPage ] = useState( 1 );
	const [ snapshotTotalPages, setSnapshotTotalPages ] = useState( 1 );
	const [ snapshotLoading, setSnapshotLoading ] = useState( false );

	const updateSettings = ( patch: Partial<MarkdownForAgentsSettings> ) => {
		onChange( {
			...settings,
			...patch,
		} );
	};

	const postTypeOptions = meta.postTypes.filter( ( item ) => item.slug !== 'attachment' );
	const scopePostTypeOptions = useMemo(
		() => postTypeOptions.filter( ( item ) => settings.postTypes.includes( item.slug ) ),
		[ postTypeOptions, settings.postTypes ],
	);

	const togglePostType = ( slug: string, checked: boolean ) => {
		const set = new Set( settings.postTypes );
		if ( checked ) {
			set.add( slug );
		} else {
			set.delete( slug );
		}
		updateSettings( { postTypes: Array.from( set ) } );
	};

	const parsePostId = (): number => {
		const postId = Number.parseInt( previewPostId, 10 );
		if ( ! Number.isFinite( postId ) || postId <= 0 ) {
			throw new Error( __( 'Please enter a valid post ID.', 'airygen-seo' ) );
		}
		return postId;
	};

	const setStatus = ( type: 'success' | 'error', message: string ) => {
		setStatusType( type );
		setStatusMessage( message );
	};
	const serveMarkdownHelp = sprintf(
		/* translators: %s: code tag containing MIME type text/markdown. */
		__( 'Serve %s for AI clients via headers or format query.', 'airygen-seo' ),
		'<code>text/markdown</code>',
	);

	const handlePreview = async () => {
		try {
			const postId = parsePostId();
			setPreviewLoading( true );
			const response = await apiFetch<{ markdown?: string }>( {
				path: `${ restBase }/markdown-for-agents/preview?post_id=${ postId }`,
				method: 'GET',
			} );
			const markdown = typeof response?.markdown === 'string' ? response.markdown : '';
			setPreviewOutput( markdown );
			setStatus( 'success', __( 'Markdown preview loaded.', 'airygen-seo' ) );
		} catch ( error ) {
			const message = error instanceof Error
				? error.message
				: __( 'Failed to load markdown preview.', 'airygen-seo' );
			setStatus( 'error', message );
		} finally {
			setPreviewLoading( false );
		}
	};

	const handleRebuild = async () => {
		try {
			setSnapshotLoading( true );
			const response = await apiFetch<{ total?: number; synced?: number; failed?: number }>( {
				path: `${ restBase }/markdown-for-agents/rebuild`,
				method: 'POST',
			} );
			setStatus(
				'success',
				`${ __( 'Rebuild finished:', 'airygen-seo' ) } ${ response.synced ?? 0 }/${ response.total ?? 0 }`,
			);
			if ( 'snapshots' === activeTab ) {
				await fetchSnapshots();
			}
		} catch ( error ) {
			const message = error instanceof Error
				? error.message
				: __( 'Failed to rebuild markdown snapshots.', 'airygen-seo' );
			setStatus( 'error', message );
		} finally {
			setSnapshotLoading( false );
		}
	};

	const fetchSnapshots = useCallback( async () => {
		setSnapshotLoading( true );
		try {
			const postTypeQuery =
				snapshotPostType === 'all' || snapshotPostType === ''
					? ''
					: `&post_type=${ encodeURIComponent( snapshotPostType ) }`;
			const response = await apiFetch<SnapshotResponse>( {
				path: `${ restBase }/markdown-for-agents/records?page=${ snapshotPage }&per_page=20${ postTypeQuery }`,
				method: 'GET',
			} );
			setSnapshotRecords( Array.isArray( response.records ) ? response.records : [] );
			const totalPages = Number( response.pagination?.totalPages );
			setSnapshotTotalPages( Number.isFinite( totalPages ) && totalPages > 0 ? totalPages : 1 );
		} catch ( error ) {
			const message = error instanceof Error
				? error.message
				: __( 'Failed to load snapshots.', 'airygen-seo' );
			setStatus( 'error', message );
			setSnapshotRecords( [] );
			setSnapshotTotalPages( 1 );
		} finally {
			setSnapshotLoading( false );
		}
	}, [ restBase, snapshotPage, snapshotPostType ] );

	useEffect( () => {
		if ( activeTab === 'snapshots' ) {
			void fetchSnapshots();
		}
	}, [ activeTab, fetchSnapshots ] );

	const snapshotTableRows = useMemo( () => {
		if ( snapshotLoading ) {
			return (
				<tr>
					<td className="px-3 py-4 text-slate-500" colSpan={ 5 }>
						{ getLoadingItemLabel( __( 'snapshots', 'airygen-seo' ) ) }
					</td>
				</tr>
			);
		}

		if ( snapshotRecords.length === 0 ) {
			return (
				<tr>
					<td className="px-3 py-4 text-slate-500" colSpan={ 5 }>
						{ getNoItemsFoundLabel( __( 'snapshots', 'airygen-seo' ) ) }
					</td>
				</tr>
			);
		}

		return snapshotRecords.map( ( record ) => (
			<tr key={ `${ record.postId }-${ record.contentHash }` }>
				<td className="px-3 py-3 font-mono text-xs text-slate-700">{ record.postId }</td>
				<td className="px-3 py-3 text-slate-800">{ record.title || '—' }</td>
				<td className="px-3 py-3 text-slate-700">{ record.postType || '—' }</td>
				<td className="px-3 py-3 text-slate-700">{ record.lastSynced || '—' }</td>
				<td className="px-3 py-3 text-right">
					{ record.url ? (
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ () => {
								const separator = record.url.includes( '?' ) ? '&' : '?';
								window.open( `${ record.url }${ separator }format=md`, '_blank', 'noopener,noreferrer' );
							} }
						>
							{ __( 'View', 'airygen-seo' ) }
						</Button>
					) : (
						<span className="text-slate-400">—</span>
					) }
				</td>
			</tr>
		) );
	}, [ snapshotLoading, snapshotRecords ] );

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<MarkdownForAgentsIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Markdown for Agents', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __( 'Generate AI-friendly markdown from published content.', 'airygen-seo' ) }
					</div>
				</div>
			</div>

			<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
				<button
					type="button"
					data-airygen-e2e="tab-settings"
					className={
						'settings' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'settings' ) }
				>
					{ __( 'Settings', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-snapshots"
					className={
						'snapshots' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'snapshots' ) }
				>
					{ __( 'Snapshots', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-preview"
					className={
						'preview' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
			</div>

			{ 'settings' === activeTab ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Settings', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Control markdown output and metadata behavior for AI crawlers.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="grid gap-4 md:grid-cols-3">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Enable Markdown output', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Enable Markdown output', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.enabled }
										onChange={ ( checked ) => updateSettings( { enabled: checked } ) }
									/>
								</div>
								<p
									className="text-xs text-slate-500"
									// Injecting trusted inline code tags through sprintf placeholders.
									// eslint-disable-next-line react/no-danger
									dangerouslySetInnerHTML={ { __html: serveMarkdownHelp } }
								/>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Prompts for Agents', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Prompts for Agents', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.promptsForAgents }
										onChange={ ( checked ) => updateSettings( { promptsForAgents: checked } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Enable this to show a panel in the editor where you can add descriptions, instructions, or prompts for LLM agents.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">{ __( 'Include YAML front matter', 'airygen-seo' ) }</p>
									<Toggle
										label={ __( 'Include YAML front matter', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.includeFrontmatter }
										onChange={ ( checked ) => updateSettings( { includeFrontmatter: checked } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">{ __( 'Add title, author, publish date, canonical URL, and keyphrase metadata at the top of markdown.', 'airygen-seo' ) }</p>
							</div>
						</div>
					</section>

					<section className="rounded-xl border border-slate-200 bg-white p-4">
						<h3 className="text-lg font-semibold text-gray-800">
							{ __( 'Scope', 'airygen-seo' ) }
						</h3>
						<p className="mt-1 text-sm text-slate-500">
							{ __( 'Select post types that will be exported to markdown.', 'airygen-seo' ) }
						</p>
						<div className="mt-4 space-y-3">
							<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
								{ __( 'Post types to include', 'airygen-seo' ) }
							</p>
							<div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
								{ postTypeOptions.map( ( postType ) => (
									<div
										key={ postType.slug }
										className="rounded-lg border border-slate-200 p-3"
									>
										<Checkbox
											label={ postType.label }
											checked={ settings.postTypes.includes( postType.slug ) }
											onChange={ ( checked ) => togglePostType( postType.slug, checked ) }
										/>
									</div>
								) ) }
							</div>
						</div>
					</section>
				</>
			) : null }

			{ 'snapshots' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="flex items-start justify-between gap-3">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Snapshots', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Browse posts that already have generated markdown snapshots.', 'airygen-seo' ) }
							</p>
						</div>
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ handleRebuild }
							loading={ snapshotLoading }
						>
							{ __( 'Rebuild', 'airygen-seo' ) }
						</Button>
					</div>

					<div className="grid gap-4 md:grid-cols-4">
						<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4 md:col-span-4">
							<label className="text-sm font-medium text-gray-800" htmlFor="airygen-m4a-snapshots-filter">
								{ __( 'Post type filter', 'airygen-seo' ) }
							</label>
							<select
								id="airygen-m4a-snapshots-filter"
								className="airygen-field-select mt-2 w-full"
								value={ snapshotPostType }
								onChange={ ( event ) => {
									setSnapshotPostType( event.target.value );
									setSnapshotPage( 1 );
								} }
							>
								<option value="all">{ __( 'All scope post types', 'airygen-seo' ) }</option>
								{ scopePostTypeOptions.map( ( option ) => (
									<option key={ option.slug } value={ option.slug }>
										{ option.label }
									</option>
								) ) }
							</select>
						</div>
					</div>

					<div className="overflow-x-auto rounded-lg border border-slate-200">
						<table className="min-w-full divide-y divide-slate-200 text-sm">
							<thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
								<tr>
									<th className="px-3 py-2">{ __( 'Post ID', 'airygen-seo' ) }</th>
									<th className="px-3 py-2">{ __( 'Title', 'airygen-seo' ) }</th>
									<th className="px-3 py-2">{ __( 'Post type', 'airygen-seo' ) }</th>
									<th className="px-3 py-2">{ __( 'Last synced', 'airygen-seo' ) }</th>
									<th className="px-3 py-2 text-right">{ __( 'View', 'airygen-seo' ) }</th>
								</tr>
							</thead>
							<tbody className="divide-y divide-slate-100">{ snapshotTableRows }</tbody>
						</table>
					</div>

					<div className="flex items-center justify-between gap-3">
						<p className="text-sm text-slate-500">
							{ sprintf(
								/* translators: 1: current page number, 2: total pages number. */
								__( 'Page %1$d of %2$d', 'airygen-seo' ),
								snapshotPage,
								snapshotTotalPages,
							) }
						</p>
						<div className="flex items-center gap-2">
							<Button
								variant="secondary"
								className="text-xs"
								disabled={ snapshotLoading || snapshotPage <= 1 }
								onClick={ () => setSnapshotPage( ( page ) => Math.max( 1, page - 1 ) ) }
							>
								{ __( 'Previous', 'airygen-seo' ) }
							</Button>
							<Button
								variant="secondary"
								className="text-xs"
								disabled={ snapshotLoading || snapshotPage >= snapshotTotalPages }
								onClick={ () =>
									setSnapshotPage( ( page ) =>
										Math.min( snapshotTotalPages, page + 1 ),
									)
								}
							>
								{ __( 'Next', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				</section>
			) : null }

			{ 'preview' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Preview', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __( 'Preview markdown output using current settings.', 'airygen-seo' ) }
						</p>
					</div>
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<label htmlFor="airygen-markdown-preview-post-id" className="block text-sm font-medium text-gray-800">
							{ __( 'Post ID', 'airygen-seo' ) }
						</label>
						<div className="mt-2 flex items-end gap-3">
							<input
								id="airygen-markdown-preview-post-id"
								type="number"
								min={ 1 }
								value={ previewPostId }
								onChange={ ( event ) => setPreviewPostId( event.target.value ) }
								className="airygen-field"
							/>
							<Button variant="secondary" className="text-xs" onClick={ handlePreview } loading={ previewLoading }>
								{ __( 'Preview', 'airygen-seo' ) }
							</Button>
						</div>
						<p className="mt-2 text-xs text-gray-500">
							{ __( 'Enter a published post ID for markdown preview/export.', 'airygen-seo' ) }
						</p>
					</div>
					{ statusMessage ? (
						<p className={ statusType === 'error' ? 'text-sm text-red-600' : 'text-sm text-emerald-600' }>
							{ statusMessage }
						</p>
					) : null }
					<div className="rounded-lg border border-slate-200 p-4">
						<label htmlFor="airygen-markdown-output" className="block text-sm font-medium text-gray-800">
							{ __( 'Output', 'airygen-seo' ) }
						</label>
						<textarea
							id="airygen-markdown-output"
							className="mt-2 airygen-field h-80 w-full font-mono text-xs"
							readOnly
							value={ previewOutput }
						/>
					</div>
				</section>
			) : null }
		</div>
	);
};

export default MarkdownForAgentsTab;

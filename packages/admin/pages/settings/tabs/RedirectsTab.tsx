import Button from '../../../components/Button';
import Input from '../../../components/Input';
import Select from '../../../components/Select';
import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import Popover from '../../../components/Popover';
import Modal from '../../../components/Modal';
import HeadingIcon from '../../../components/HeadingIcon';
import { RedirectsIcon } from '../../../components/Icons';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getNoItemsAddedYetLabel,
	getNoItemsMatchCurrentFiltersLabel,
} from '../../../../shared/i18nPhrases';

import type { MetaPayload } from '../../../types/api';
import type { RedirectRule, RedirectsSettings } from '../../../types/settings';

type RedirectsTabProps = {
	settings: RedirectsSettings;
	meta: MetaPayload;
	onRemoveRule: ( id: string ) => Promise<void>;
	onCreateRule: ( rule: Omit<RedirectRule, 'id'> ) => Promise<void>;
	onUpdateRule: ( id: string, patch: Partial<RedirectRule> ) => Promise<void>;
};

const PAGE_SIZE = 12;
type RedirectFormState = Omit<RedirectRule, 'id'>;

const emptyDraft: RedirectFormState = {
	source: '',
	target: '',
	type: 'exact',
	status: 301,
	enabled: true,
	note: '',
};

const RedirectsTab = ( {
	settings,
	meta,
	onRemoveRule,
	onCreateRule,
	onUpdateRule,
}: RedirectsTabProps ) => {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ modalMode, setModalMode ] = useState<'add' | 'edit'>( 'add' );
	const [ draftRule, setDraftRule ] = useState( emptyDraft );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ editingRuleId, setEditingRuleId ] = useState<string | null>( null );
	const [ isSubmitting, setIsSubmitting ] = useState( false );
	const [ searchQuery, setSearchQuery ] = useState( '' );

	const filteredRules = useMemo( () => {
		const query = searchQuery.trim().toLowerCase();

		if ( '' === query ) {
			return settings.rules;
		}

		return settings.rules.filter( ( rule ) => {
			const haystack = [
				rule.source,
				rule.target,
				rule.note ?? '',
			]
				.join( ' ' )
				.toLowerCase();

			return haystack.includes( query );
		} );
	}, [ searchQuery, settings.rules ] );

	const totalPages = Math.max(
		1,
		Math.ceil( filteredRules.length / PAGE_SIZE ),
	);

	useEffect( () => {
		if ( currentPage > totalPages ) {
			setCurrentPage( totalPages );
		}
	}, [ currentPage, totalPages ] );

	const paginatedRules = useMemo( () => {
		const start = ( currentPage - 1 ) * PAGE_SIZE;
		return filteredRules.slice( start, start + PAGE_SIZE );
	}, [ filteredRules, currentPage ] );

	useEffect( () => {
		setCurrentPage( 1 );
	}, [ searchQuery ] );

	const handleDraftChange = ( patch: Partial<RedirectFormState> ) => {
		setDraftRule( ( prev ) => ( { ...prev, ...patch } ) );
	};

	const handleOpenAdd = () => {
		setModalMode( 'add' );
		setDraftRule( emptyDraft );
		setEditingRuleId( null );
		setIsModalOpen( true );
	};

	const handleOpenEdit = ( rule: RedirectRule ) => {
		setModalMode( 'edit' );
		setDraftRule( {
			source: rule.source,
			target: rule.target,
			type: rule.type,
			status: rule.status,
			enabled: rule.enabled,
			note: rule.note,
		} );
		setEditingRuleId( rule.id );
		setIsModalOpen( true );
	};

	const handleCloseModal = () => {
		if ( isSubmitting ) {
			return;
		}
		setIsModalOpen( false );
		setDraftRule( emptyDraft );
		setEditingRuleId( null );
	};

	const handleSave = async () => {
		if ( isSubmitting || ! canSave ) {
			return;
		}

		const payload = {
			...draftRule,
			source: draftRule.source.trim(),
			target: draftRule.target.trim(),
			note: draftRule.note.trim(),
		};

		setIsSubmitting( true );
		try {
			if ( modalMode === 'add' ) {
				await onCreateRule( payload );
				setCurrentPage( 1 );
			} else if ( modalMode === 'edit' && editingRuleId ) {
				await onUpdateRule( editingRuleId, payload );
			}
			handleCloseModal();
		} catch {
			// errors are surfaced via global notice
		} finally {
			setIsSubmitting( false );
		}
	};

	const canSave =
		draftRule.source.trim() !== '' && draftRule.target.trim() !== '';

	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div className="flex items-start gap-3">
					<HeadingIcon>
						<RedirectsIcon className="h-8 w-8" aria-hidden="true" />
					</HeadingIcon>
					<div>
						<div className="airygen_h1_title">
							{ __( 'Redirects', 'airygen-seo' ) }
						</div>
						<div className="text-sm text-slate-500">
							<p>
								{ __(
									'Manage URL redirects in priority order: exact → wildcard → regex.',
									'airygen-seo',
								) }
							</p>
							<p>
								{ __(
									'Use notes to document why a rule exists and keep the list tidy.',
									'airygen-seo',
								) }
							</p>
						</div>
					</div>
				</div>
				<Button
					variant="secondary"
					onClick={ handleOpenAdd }
					className="w-full text-xs md:w-auto"
				>
					{ __( 'Add redirect', 'airygen-seo' ) }
				</Button>
			</div>

			<Modal
				isOpen={ isModalOpen }
				onClose={ handleCloseModal }
				title={
					modalMode === 'add'
						? __( 'Add redirect', 'airygen-seo' )
						: __( 'Edit redirect', 'airygen-seo' )
				}
				footer={
					<div className="flex justify-end gap-2 bg-slate-50">
						<Button
							variant="secondary"
							onClick={ handleCloseModal }
							disabled={ isSubmitting }
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleSave }
							loading={ isSubmitting }
							disabled={ ! canSave || isSubmitting }
						>
							{ modalMode === 'add'
								? __( 'Add redirect', 'airygen-seo' )
								: __( 'Save changes', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4">
					<div className="flex items-center justify-between">
						<span className="text-sm font-medium text-slate-800">
							{ __( 'Status', 'airygen-seo' ) }
						</span>
						<Toggle
							label={ __( 'Enabled', 'airygen-seo' ) }
							checked={ draftRule.enabled }
							onChange={ ( enabled ) =>
								handleDraftChange( { enabled } )
							}
						/>
					</div>
					<div className="grid gap-4 md:grid-cols-2">
						<Input
							label={ __( 'Source path or pattern', 'airygen-seo' ) }
							value={ draftRule.source }
							onChange={ ( value ) =>
								handleDraftChange( { source: value } )
							}
							placeholder="/old-page"
						/>
						<Input
							label={ __( 'Target URL', 'airygen-seo' ) }
							value={ draftRule.target }
							onChange={ ( value ) =>
								handleDraftChange( { target: value } )
							}
							placeholder="/new-page"
						/>
						<Select
							label={ __( 'HTTP status', 'airygen-seo' ) }
							value={ String( draftRule.status ) }
							options={ meta.redirectStatuses.map( ( option ) => ( {
								label: option.label,
								value: String( option.value ),
							} ) ) }
							onChange={ ( value ) =>
								handleDraftChange( {
									status: Number( value ) || 301,
								} )
							}
						/>
						<Select
							label={ __( 'Match type', 'airygen-seo' ) }
							value={ draftRule.type }
							options={ meta.redirectTypes.map( ( option ) => ( {
								label: option.label,
								value: option.value,
							} ) ) }
							onChange={ ( value ) =>
								handleDraftChange( {
									type:
										value === 'exact' ||
										value === 'wildcard' ||
										value === 'regex'
											? value
											: 'exact',
								} )
							}
						/>
					</div>
					<Textarea
						label={ __( 'Note', 'airygen-seo' ) }
						value={ draftRule.note }
						onChange={ ( value ) =>
							handleDraftChange( { note: value } )
						}
						help={ __(
							'Optional. Summarize why this redirect exists for future context.',
							'airygen-seo',
						) }
					/>
				</div>
			</Modal>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Settings', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Set redirects for incoming URLs so visitors land on the right destination.',
								'airygen-seo',
							) }
						</p>
					</div>
					<div className="flex items-center gap-2">
						<label
							className="text-xs font-medium text-slate-600"
							htmlFor="airygen-redirects-search"
						>
							{ __( 'Search', 'airygen-seo' ) }
						</label>
						<Input
							id="airygen-redirects-search"
							value={ searchQuery }
							onChange={ setSearchQuery }
							placeholder={ __( 'Search rules', 'airygen-seo' ) }
							inputClassName="!w-[120px]"
							inputStyle={ { width: '120px' } }
						/>
					</div>
				</div>
				{ settings.rules.length === 0 && (
					<div className="rounded-lg border border-slate-200 bg-white p-4">
						<p className="text-sm italic text-slate-500">
							{ getNoItemsAddedYetLabel( __( 'redirects', 'airygen-seo' ) ) }
						</p>
					</div>
				) }
				{ settings.rules.length > 0 && filteredRules.length === 0 && (
					<div className="rounded-lg border border-slate-200 bg-white p-4">
						<p className="text-sm italic text-slate-500">
							{ getNoItemsMatchCurrentFiltersLabel( __( 'redirects', 'airygen-seo' ) ) }
						</p>
					</div>
				) }
				{ filteredRules.length > 0 && (
					<div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
						<table className="min-w-full table-fixed divide-y divide-slate-200 text-sm">
							<thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
								<tr>
									<th className="px-3 py-2 text-left w-[30%]">{ __( 'Source', 'airygen-seo' ) }</th>
									<th className="px-3 py-2 text-left w-[30%]">{ __( 'Target', 'airygen-seo' ) }</th>
									<th className="px-3 py-2 text-center w-[10%]">{ __( 'Match type', 'airygen-seo' ) }</th>
									<th className="px-3 py-2 text-center w-[10%]">{ __( 'HTTP status', 'airygen-seo' ) }</th>
									<th className="px-3 py-2 text-right w-[20%]">
										{ __( 'Action', 'airygen-seo' ) }
									</th>
								</tr>
							</thead>
							<tbody className="divide-y divide-slate-200">
								{ paginatedRules.map( ( rule ) => {
									const showNote = rule.note && rule.note.trim() !== '';
									return (
										<tr key={ rule.id } className="align-middle">
											<td className="px-3 py-3 text-slate-900 font-mono text-xs">{ rule.source }</td>
											<td className="px-3 py-3 text-slate-900 break-all font-mono text-xs">{ rule.target }</td>
											<td className="px-3 py-3 text-center text-slate-900">{ rule.type }</td>
											<td className="px-3 py-3 text-center text-slate-900">{ rule.status }</td>
											<td className="px-3 py-3 text-right">
												<div className="flex gap-2 justify-end">
													{ showNote ? (
														<Popover
															trigger={
																<Button
																	variant="secondary"
																	className="text-xs inline-flex items-center justify-center gap-1"
																>
																	<span
																		className="dashicons dashicons-testimonial"
																		aria-hidden="true"
																	/>
																	<span className="sr-only">
																		{ __( 'Note', 'airygen-seo' ) }
																	</span>
																</Button>
															}
															position="bottom-right"
															className="w-64"
														>
															<p className="text-xs text-slate-600 whitespace-normal">
																{ rule.note }
															</p>
														</Popover>
													) : (
														''
													) }
													<Button
														variant="secondary"
														className="text-xs inline-flex items-center justify-center gap-1"
														onClick={ () => handleOpenEdit( rule ) }
														disabled={ isSubmitting }
													>
														<span className="dashicons dashicons-edit" aria-hidden="true" />
														<span className="sr-only">{ __( 'Edit', 'airygen-seo' ) }</span>
													</Button>
													<Button
														variant="danger"
														className="text-xs flex items-center justify-center gap-1"
														onClick={ () => onRemoveRule( rule.id ) }
														disabled={ isSubmitting }
													>
														<span className="dashicons dashicons-trash" aria-hidden="true" />
														<span className="sr-only">{ __( 'Remove', 'airygen-seo' ) }</span>
													</Button>
												</div>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</div>
				) }
				{ settings.rules.length > 0 ? (
					<div className="flex items-center justify-end gap-3 text-xs text-slate-600">
						<span>
							{ currentPage } / { totalPages }
						</span>
						<Button
							variant="secondary"
							disabled={ currentPage === 1 }
							onClick={ () =>
								setCurrentPage( ( page ) => Math.max( 1, page - 1 ) )
							}
							className="text-xs"
						>
							{ __( 'Prev', 'airygen-seo' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ currentPage === totalPages }
							onClick={ () =>
								setCurrentPage( ( page ) =>
									Math.min( totalPages, page + 1 ),
								)
							}
							className="text-xs"
						>
							{ __( 'Next', 'airygen-seo' ) }
						</Button>
					</div>
				) : null }
			</section>
		</div>
	);
};

export default RedirectsTab;

import Button from '../../../components/Button';
import Input from '../../../components/Input';
import Modal from '../../../components/Modal';
import Select from '../../../components/Select';
import Textarea from '../../../components/Textarea';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import { CodeSnippetsIcon } from '../../../components/Icons';

import { __ } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import type { DragEvent } from 'react';
import type {
	CodeSnippetManagerSettings,
	CodeSnippet,
	CodeSnippetPlacement,
} from '../../../types/settings';

type CodeSnippetManagerTabProps = {
	settings: CodeSnippetManagerSettings;
	onChange: ( next: CodeSnippetManagerSettings ) => void;
};

type SnippetDraft = {
	enabled: boolean;
	description: string;
	code: string;
	placement: CodeSnippetPlacement;
};

const emptySnippetDraft = (): SnippetDraft => ( {
	enabled: true,
	description: '',
	code: '',
	placement: 'head',
} );

const createSnippetId = (): string =>
	`snippet-${ Date.now().toString( 36 ) }-${ Math.random().toString( 36 ).slice( 2, 8 ) }`;

const ZONE_LABELS: Array<{
	placement: CodeSnippetPlacement;
	label: string;
	description: string;
}> = [
	{
		placement: 'head',
		label: __( 'Head', 'airygen-seo' ),
		description: __( 'Snippets in this area are injected into the <head> section.', 'airygen-seo' ),
	},
	{
		placement: 'body',
		label: __( 'Body', 'airygen-seo' ),
		description: __( 'Snippets in this area are injected right after <body>.', 'airygen-seo' ),
	},
	{
		placement: 'footer',
		label: __( 'Footer', 'airygen-seo' ),
		description: __( 'Snippets in this area are injected before </body>.', 'airygen-seo' ),
	},
	{
		placement: 'inactive',
		label: __( 'Disabled snippets staging area', 'airygen-seo' ),
		description: __( 'Keep snippets here when you do not want them rendered.', 'airygen-seo' ),
	},
];

const CodeSnippetManagerTab = ( {
	settings,
	onChange,
}: CodeSnippetManagerTabProps ) => {
	const [ isSnippetModalOpen, setIsSnippetModalOpen ] = useState( false );
	const [ snippetModalMode, setSnippetModalMode ] = useState<'add' | 'edit'>( 'add' );
	const [ editingSnippetId, setEditingSnippetId ] = useState<string | null>( null );
	const [ snippetDraft, setSnippetDraft ] = useState<SnippetDraft>( emptySnippetDraft );
	const [ draggingSnippetId, setDraggingSnippetId ] = useState<string | null>( null );

	const snippets = settings.snippets;
	const snippetsByZone = useMemo( () => {
		return {
			head: snippets.filter( ( item ) => item.placement === 'head' ),
			body: snippets.filter( ( item ) => item.placement === 'body' ),
			footer: snippets.filter( ( item ) => item.placement === 'footer' ),
			inactive: snippets.filter( ( item ) => item.placement === 'inactive' ),
		};
	}, [ snippets ] );

	const handleChange = ( patch: Partial<CodeSnippetManagerSettings> ) => {
		onChange( { ...settings, ...patch } );
	};

	const updateSnippets = ( nextSnippets: CodeSnippet[] ) => {
		handleChange( { snippets: nextSnippets } );
	};

	const openAddSnippetModal = () => {
		setSnippetModalMode( 'add' );
		setEditingSnippetId( null );
		setSnippetDraft( emptySnippetDraft() );
		setIsSnippetModalOpen( true );
	};

	const openEditSnippetModal = ( snippet: CodeSnippet ) => {
		setSnippetModalMode( 'edit' );
		setEditingSnippetId( snippet.id );
		setSnippetDraft( {
			enabled: snippet.enabled,
			description: snippet.description,
			code: snippet.code,
			placement: snippet.placement,
		} );
		setIsSnippetModalOpen( true );
	};

	const closeSnippetModal = () => {
		setIsSnippetModalOpen( false );
		setEditingSnippetId( null );
		setSnippetDraft( emptySnippetDraft() );
	};

	const saveSnippet = () => {
		if ( snippetDraft.code.trim() === '' ) {
			return;
		}

		if ( snippetModalMode === 'add' ) {
			updateSnippets( [
				...snippets,
				{
					id: createSnippetId(),
					enabled: snippetDraft.enabled,
					description: snippetDraft.description.trim(),
					code: snippetDraft.code,
					placement: snippetDraft.placement,
				},
			] );
			closeSnippetModal();
			return;
		}

		if ( ! editingSnippetId ) {
			return;
		}

		updateSnippets(
			snippets.map( ( item ) =>
				item.id === editingSnippetId
					? {
						...item,
						enabled: snippetDraft.enabled,
						description: snippetDraft.description.trim(),
						code: snippetDraft.code,
						placement: snippetDraft.placement,
					}
					: item,
			),
		);
		closeSnippetModal();
	};

	const deleteSnippet = ( snippetId: string ) => {
		updateSnippets( snippets.filter( ( item ) => item.id !== snippetId ) );
	};

	const moveSnippet = (
		sourceSnippetId: string,
		targetPlacement: CodeSnippetPlacement,
		targetSnippetId?: string,
	) => {
		const sourceIndex = snippets.findIndex( ( item ) => item.id === sourceSnippetId );
		if ( sourceIndex < 0 ) {
			return;
		}

		const source = snippets[ sourceIndex ];
		const withoutSource = snippets.filter( ( item ) => item.id !== sourceSnippetId );
		let nextEnabled = source.enabled;
		if ( targetPlacement === 'inactive' ) {
			nextEnabled = false;
		} else if ( source.placement === 'inactive' && ! source.enabled ) {
			nextEnabled = true;
		}
		const moved: CodeSnippet = {
			...source,
			enabled: nextEnabled,
			placement: targetPlacement,
		};

		if ( ! targetSnippetId ) {
			updateSnippets( [ ...withoutSource, moved ] );
			return;
		}

		const targetIndex = withoutSource.findIndex( ( item ) => item.id === targetSnippetId );
		if ( targetIndex < 0 ) {
			updateSnippets( [ ...withoutSource, moved ] );
			return;
		}

		const next = [ ...withoutSource ];
		next.splice( targetIndex, 0, moved );
		updateSnippets( next );
	};

	const resolveDraggingSnippetId = ( event: DragEvent<HTMLElement> ): string => {
		const payload = event.dataTransfer.getData( 'text/plain' );
		return draggingSnippetId ?? payload;
	};

	const handleZoneDrop = ( event: DragEvent<HTMLDivElement>, placement: CodeSnippetPlacement ) => {
		event.preventDefault();
		const snippetId = resolveDraggingSnippetId( event );
		if ( snippetId ) {
			moveSnippet( snippetId, placement );
		}
		setDraggingSnippetId( null );
	};

	const handleCardDrop = (
		event: DragEvent<HTMLDivElement>,
		placement: CodeSnippetPlacement,
		targetSnippetId: string,
	) => {
		event.preventDefault();
		event.stopPropagation();
		const snippetId = resolveDraggingSnippetId( event );
		if ( snippetId && snippetId !== targetSnippetId ) {
			moveSnippet( snippetId, placement, targetSnippetId );
		}
		setDraggingSnippetId( null );
	};

	const renderSnippetCard = ( snippet: CodeSnippet ) => {
		const isInactiveZone = snippet.placement === 'inactive';
		return (
			<div
				key={ snippet.id }
				className="cursor-grab rounded-md border border-slate-300 bg-white p-3 shadow-sm active:cursor-grabbing"
				draggable
				onDragStart={ ( event ) => {
					setDraggingSnippetId( snippet.id );
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData( 'text/plain', snippet.id );
				} }
				onDragEnd={ () => setDraggingSnippetId( null ) }
				onDragOver={ ( event ) => {
					event.preventDefault();
					event.dataTransfer.dropEffect = 'move';
				} }
				onDrop={ ( event ) =>
					handleCardDrop( event, snippet.placement, snippet.id )
				}
			>
				<div className="flex items-center justify-between gap-2">
					<div>
						<p className="text-sm font-medium text-slate-900">
							{ snippet.description.trim() !== ''
								? snippet.description
								: __( 'No description', 'airygen-seo' ) }
						</p>
						<p className="mt-1 text-xs text-slate-500">
							{ snippet.enabled
								? __( 'Enabled', 'airygen-seo' )
								: __( 'Disabled', 'airygen-seo' ) }
						</p>
					</div>
					<div className="flex items-center gap-2">
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ () => openEditSnippetModal( snippet ) }
						>
							{ __( 'Edit', 'airygen-seo' ) }
						</Button>
						{ isInactiveZone ? (
							<Button
								variant="secondary"
								className="text-xs"
								onClick={ () => deleteSnippet( snippet.id ) }
							>
								{ __( 'Delete', 'airygen-seo' ) }
							</Button>
						) : null }
					</div>
				</div>
			</div>
		);
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<CodeSnippetsIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Code Snippets', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Manage custom inline JavaScript snippets for your site.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>

			<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
				<div className="flex items-start justify-between gap-3">
					<div className="space-y-1">
						<div className="airygen_h2_title">
							{ __( 'Custom', 'airygen-seo' ) }
						</div>
						<p className="text-sm text-slate-500">
							{ __(
								'Create reusable script snippets and drag them between output locations.',
								'airygen-seo',
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						className="text-xs"
						onClick={ openAddSnippetModal }
						data-airygen-e2e="button-add-snippet"
					>
						{ __( 'Add Snippet', 'airygen-seo' ) }
					</Button>
				</div>

				<div className="grid gap-4 lg:grid-cols-3">
					<div className="space-y-4 lg:col-span-2">
						{ ZONE_LABELS.filter( ( zone ) => zone.placement !== 'inactive' ).map( ( zone ) => {
							const items = snippetsByZone[ zone.placement ];
							return (
								<div
									key={ zone.placement }
									className="rounded-lg border border-dashed border-slate-300 p-4"
									data-airygen-e2e={ `snippet-zone-${ zone.placement }` }
									onDragOver={ ( event ) => {
										event.preventDefault();
										event.dataTransfer.dropEffect = 'move';
									} }
									onDrop={ ( event ) => handleZoneDrop( event, zone.placement ) }
								>
									<h3 className="text-sm font-semibold text-slate-800">{ zone.label }</h3>
									<p className="mt-1 text-xs text-slate-500">{ zone.description }</p>
									<div className="mt-3 space-y-3">
										{ items.map( renderSnippetCard ) }
									</div>
								</div>
							);
						} ) }
					</div>
					{ ZONE_LABELS.filter( ( zone ) => zone.placement === 'inactive' ).map( ( zone ) => {
						const items = snippetsByZone[ zone.placement ];
						return (
							<div
								key={ zone.placement }
								className="rounded-lg border border-dashed border-slate-300 p-4"
								data-airygen-e2e={ `snippet-zone-${ zone.placement }` }
								onDragOver={ ( event ) => {
									event.preventDefault();
									event.dataTransfer.dropEffect = 'move';
								} }
								onDrop={ ( event ) => handleZoneDrop( event, zone.placement ) }
							>
								<h3 className="text-sm font-semibold text-slate-800">{ zone.label }</h3>
								<p className="mt-1 text-xs text-slate-500">{ zone.description }</p>
								<div className="mt-3 space-y-3">
									{ items.map( renderSnippetCard ) }
								</div>
							</div>
						);
					} ) }
				</div>
			</section>

			<Modal
				isOpen={ isSnippetModalOpen }
				onClose={ closeSnippetModal }
				title={ __( 'Add snippet', 'airygen-seo' ) }
				footer={
					<div className="flex justify-end gap-2 bg-slate-50" data-airygen-e2e="modal-snippet-actions">
						<Button
							variant="secondary"
							onClick={ closeSnippetModal }
							data-airygen-e2e="button-cancel-snippet"
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ saveSnippet }
							disabled={ snippetDraft.code.trim() === '' }
							data-airygen-e2e="button-save-snippet"
						>
							{ snippetModalMode === 'add'
								? __( 'Add snippet', 'airygen-seo' )
								: __( 'Save changes', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4" data-airygen-e2e="modal-snippet">
					<div className="flex items-center justify-between">
						<span className="text-sm font-medium text-slate-800">
							{ __( 'Status', 'airygen-seo' ) }
						</span>
						<Toggle
							label={ __( 'Enabled', 'airygen-seo' ) }
							checked={ snippetDraft.enabled }
							onChange={ ( enabled ) => setSnippetDraft( ( prev ) => ( { ...prev, enabled } ) ) }
						/>
					</div>
					<Input
						label={ __( 'Description', 'airygen-seo' ) }
						value={ snippetDraft.description }
						onChange={ ( value ) =>
							setSnippetDraft( ( prev ) => ( { ...prev, description: value } ) )
						}
						help={ __( 'Internal reference only. This is not output on the frontend.', 'airygen-seo' ) }
					/>
					<Textarea
						label={ __( 'Code', 'airygen-seo' ) }
						help={ __(
							'Paste a script tag or inline JavaScript. Non-script HTML tags are not accepted.',
							'airygen-seo',
						) }
						value={ snippetDraft.code }
						rows={ 8 }
						onChange={ ( value ) =>
							setSnippetDraft( ( prev ) => ( { ...prev, code: value } ) )
						}
					/>
					<Select
						label={ __( 'Injection position', 'airygen-seo' ) }
						value={ snippetDraft.placement }
						options={ [
							{ label: __( 'Head', 'airygen-seo' ), value: 'head' },
							{ label: __( 'Body', 'airygen-seo' ), value: 'body' },
							{ label: __( 'Footer', 'airygen-seo' ), value: 'footer' },
							{ label: __( 'Inactive', 'airygen-seo' ), value: 'inactive' },
						] }
						onChange={ ( value ) => {
							const placement: CodeSnippetPlacement =
								value === 'head' || value === 'body' || value === 'footer' || value === 'inactive'
									? value
									: 'inactive';
							setSnippetDraft( ( prev ) => ( { ...prev, placement } ) );
						} }
					/>
				</div>
			</Modal>
		</div>
	);
};

export default CodeSnippetManagerTab;

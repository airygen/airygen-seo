import type { DragEvent } from 'react';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ContentBlockKey } from '../types/settings';

type Zone = 'before' | 'after';

type BlockMeta = {
	key: ContentBlockKey;
	label: string;
	zone: Zone | null; // null = not participating (e.g. TOC with after-first-paragraph)
	moduleEnabled: boolean;
};

type ContentBlocksOrderProps = {
	blocks: BlockMeta[];
	order: ContentBlockKey[];
	onOrderChange: ( next: ContentBlockKey[] ) => void;
	onZoneChange: ( key: ContentBlockKey, zone: Zone ) => void;
};

const ContentBlocksOrder = ( {
	blocks,
	order,
	onOrderChange,
	onZoneChange,
}: ContentBlocksOrderProps ) => {
	const dragKey = useRef<ContentBlockKey | null>( null );
	const dragSourceZone = useRef<Zone | null>( null );

	// Sort blocks by their position in order array
	const sortedBlocks = ( zone: Zone ): BlockMeta[] => {
		const inZone = blocks.filter( ( b ) => b.zone === zone );
		inZone.sort( ( a, b ) => {
			const ai = order.indexOf( a.key );
			const bi = order.indexOf( b.key );
			return ( ai === -1 ? 999 : ai ) - ( bi === -1 ? 999 : bi );
		} );
		return inZone;
	};

	const handleDragStart = (
		key: ContentBlockKey,
		zone: Zone,
	) => ( event: DragEvent<HTMLDivElement> ) => {
		dragKey.current = key;
		dragSourceZone.current = zone;
		event.dataTransfer.effectAllowed = 'move';
	};

	const handleDragOver = ( event: DragEvent ) => {
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
	};

	const handleDropOnCard = (
		targetKey: ContentBlockKey,
		targetZone: Zone,
	) => ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		const sourceKey = dragKey.current;
		const sourceZone = dragSourceZone.current;
		if ( ! sourceKey ) {
			return;
		}

		// If moving to a different zone, update zone first
		if ( sourceZone !== targetZone ) {
			onZoneChange( sourceKey, targetZone );
		}

		// Reorder: move sourceKey before targetKey in the order array
		if ( sourceKey === targetKey ) {
			return;
		}
		const next = order.filter( ( k ) => k !== sourceKey );
		const targetIndex = next.indexOf( targetKey );
		if ( targetIndex === -1 ) {
			next.push( sourceKey );
		} else {
			next.splice( targetIndex, 0, sourceKey );
		}
		onOrderChange( next );

		dragKey.current = null;
		dragSourceZone.current = null;
	};

	const handleDropOnZone = ( targetZone: Zone ) => ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		const sourceKey = dragKey.current;
		const sourceZone = dragSourceZone.current;
		if ( ! sourceKey || sourceZone === targetZone ) {
			return;
		}
		onZoneChange( sourceKey, targetZone );
		dragKey.current = null;
		dragSourceZone.current = null;
	};

	const handleDragEnd = () => {
		dragKey.current = null;
		dragSourceZone.current = null;
	};

	const renderCard = ( block: BlockMeta, zone: Zone ) => (
		<div
			key={ block.key }
			draggable
			onDragStart={ handleDragStart( block.key, zone ) }
			onDragOver={ handleDragOver }
			onDrop={ handleDropOnCard( block.key, zone ) }
			onDragEnd={ handleDragEnd }
			className="flex cursor-move items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 transition-shadow hover:shadow-sm"
		>
			<div className="flex items-center gap-3">
				<span className="text-slate-400" aria-hidden="true">
					<svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 } d="M4 8h16M4 16h16" />
					</svg>
				</span>
				<span className="text-sm font-medium text-slate-800">{ block.label }</span>
			</div>
			<span
				className={
					block.moduleEnabled
						? 'rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-600'
						: 'rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-400'
				}
			>
				{ block.moduleEnabled ? __( 'Enabled', 'airygen-seo' ) : __( 'Disabled', 'airygen-seo' ) }
			</span>
		</div>
	);

	const renderZone = ( zone: Zone, title: string ) => {
		const zoneBlocks = sortedBlocks( zone );
		return (
			<div className="flex flex-1 flex-col">
				<div className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
					{ title }
				</div>
				<div
					onDragOver={ handleDragOver }
					onDrop={ handleDropOnZone( zone ) }
					className="flex min-h-[120px] flex-col gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-3 transition-colors"
				>
					{ zoneBlocks.length === 0 ? (
						<p className="m-auto text-xs text-slate-400">
							{ __( 'Drop blocks here', 'airygen-seo' ) }
						</p>
					) : (
						zoneBlocks.map( ( block ) => renderCard( block, zone ) )
					) }
				</div>
			</div>
		);
	};

	return (
		<div className="flex flex-col gap-4 sm:flex-row">
			{ renderZone( 'before', __( 'Before content', 'airygen-seo' ) ) }
			{ renderZone( 'after', __( 'After content', 'airygen-seo' ) ) }
		</div>
	);
};

export default ContentBlocksOrder;

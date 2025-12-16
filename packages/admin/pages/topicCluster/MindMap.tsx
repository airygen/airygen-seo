import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import Modal from '../../components/Modal';
import Button from '../../components/Button';
// eslint-disable-next-line import/no-extraneous-dependencies
import {
	ReactFlow,
	useNodesState,
	useEdgesState,
	Background,
	BackgroundVariant,
	MiniMap,
	Controls,
	MarkerType,
	type Node,
	type Edge,
	type Connection,
	type EdgeTypes,
} from '@xyflow/react';

// eslint-disable-next-line import/no-extraneous-dependencies
import '@xyflow/react/dist/base.css';

import TopicClusterNode from './TopicClusterNode';
import RelationEdge from './RelationEdge';
import type { NoticeState } from '../../types/api';
import {
	getLoadingItemLabel,
	getNoItemsYetLabel,
} from '../../../shared/i18nPhrases';

type MindMapProps = {
	restBase: string;
	onNotice: ( notice: NoticeState ) => void;
	groupId?: number | null;
	enableOrdering: boolean;
	saveSignal: number;
	resetSignal: number;
	onDirtyChange: ( isDirty: boolean ) => void;
	onSavingChange: ( isSaving: boolean ) => void;
};

type MindmapItem = {
	id: number;
	title: string;
	level: 'L1' | 'L2' | 'L3';
	parent_post_id: number | null;
	prev_post_id?: number | null;
	next_post_id?: number | null;
	cluster_root_id: number | null;
	edit_url?: string;
};

type MindmapCandidate = {
	id: number;
	post_id: number;
	group_id: number;
	title: string;
	edit_url?: string;
};

type MindmapPosition = {
	x: number;
	y: number;
};

type MindmapLayout = {
	nodes?: Record<string, MindmapPosition>;
};

type ConfigLevel = 'L1' | 'L2' | 'L3';

type MindMapSnapshot = {
	items: MindmapItem[];
	candidates: MindmapCandidate[];
	map: MindmapLayout;
};

const nodeTypes = {
	topicCluster: TopicClusterNode,
};

const edgeTypes = {
	relationEdge: RelationEdge,
};

const cloneMapLayout = ( map: MindmapLayout ): MindmapLayout => ( {
	nodes: map.nodes ? { ...map.nodes } : {},
} );

const TOPIC_CLUSTER_CANVAS_SELECTOR = '.react-flow__pane';
const TOPIC_CLUSTER_ZOOM_OUT_SELECTORS = [
	'.react-flow__controls-zoomout',
	'[aria-label="Zoom Out"]',
	'[title="Zoom Out"]',
].join( ', ' );

const serializeMindMapSnapshot = ( snapshot: MindMapSnapshot ): string => {
	const sortedItems = [ ...snapshot.items ]
		.map( ( item ) => ( {
			id: item.id,
			level: item.level,
			parent_post_id: item.parent_post_id ?? 0,
			prev_post_id: item.prev_post_id ?? 0,
			next_post_id: item.next_post_id ?? 0,
			cluster_root_id: item.cluster_root_id ?? 0,
		} ) )
		.sort( ( left, right ) => left.id - right.id );
	const sortedCandidates = [ ...snapshot.candidates ]
		.map( ( candidate ) => ( {
			id: candidate.id,
			post_id: candidate.post_id,
			group_id: candidate.group_id,
		} ) )
		.sort( ( left, right ) => left.post_id - right.post_id );
	const sortedNodes = Object.entries( snapshot.map.nodes ?? {} )
		.sort( ( [ left ], [ right ] ) => left.localeCompare( right ) )
		.reduce<Record<string, MindmapPosition>>( ( carry, [ key, position ] ) => {
			carry[ key ] = {
				x: Math.round( position.x ),
				y: Math.round( position.y ),
			};
			return carry;
		}, {} );

	return JSON.stringify( {
		items: sortedItems,
		candidates: sortedCandidates,
		map: {
			nodes: sortedNodes,
		},
	} );
};

const buildMindMapGraph = (
	items: MindmapItem[],
	candidates: MindmapCandidate[],
	savedNodes: Record<string, MindmapPosition>,
	enableOrdering: boolean,
	onConfigureCandidate: ( candidate: MindmapCandidate ) => void,
): { nodes: Node[]; edges: Edge[] } => {
	const hasSavedLayout = Object.keys( savedNodes ).length > 0;
	const useAutoOrdering = ! hasSavedLayout;
	const groups = new Map<number, MindmapItem[]>();
	items.forEach( ( item ) => {
		const rootId = item.cluster_root_id ?? item.id;
		const list = groups.get( rootId ) ?? [];
		list.push( item );
		groups.set( rootId, list );
	} );
	candidates.forEach( ( candidate ) => {
		if ( ! groups.has( candidate.group_id ) ) {
			groups.set( candidate.group_id, [] );
		}
	} );

	const newNodes: Node[] = [];
	const newEdges: Edge[] = [];
	const rowY = {
		L1: 320,
		L2Top: 170,
		L2Bottom: 470,
	};
	const l3BaseGap = 140;
	const l3DensityStep = 80;
	const nodeWidth = 260;
	const laneGap = nodeWidth + 50;
	const minGroupWidth = 520;
	let xOffset = 0;

	const sortedGroups = Array.from( groups.entries() ).sort( ( a, b ) => a[ 0 ] - b[ 0 ] );

	const spreadX = ( centerX: number, count: number ): number[] => {
		if ( count <= 1 ) {
			return [ centerX ];
		}
		const total = ( count - 1 ) * laneGap;
		const start = centerX - ( total / 2 );
		return Array.from( { length: count }, ( _unused, index ) => start + ( index * laneGap ) );
	};

	const orderByPrevNext = ( list: MindmapItem[] ): MindmapItem[] => {
		if ( list.length <= 1 ) {
			return list;
		}

		const itemMap = new Map<number, MindmapItem>();
		list.forEach( ( item ) => itemMap.set( item.id, item ) );

		const starts = list
			.filter( ( item ) => {
				const prev = item.prev_post_id ? Number( item.prev_post_id ) : 0;
				return prev <= 0 || ! itemMap.has( prev );
			} )
			.sort( ( a, b ) => a.id - b.id );

		const ordered: MindmapItem[] = [];
		const visited = new Set<number>();

		const appendChain = ( start: MindmapItem ) => {
			let cursor: MindmapItem | undefined = start;
			while ( cursor && ! visited.has( cursor.id ) ) {
				ordered.push( cursor );
				visited.add( cursor.id );
				const next: number = cursor.next_post_id ? Number( cursor.next_post_id ) : 0;
				cursor = next > 0 ? itemMap.get( next ) : undefined;
			}
		};

		starts.forEach( appendChain );

		list
			.filter( ( item ) => ! visited.has( item.id ) )
			.sort( ( a, b ) => a.id - b.id )
			.forEach( appendChain );

		return ordered;
	};

	sortedGroups.forEach( ( [ groupRootId, group ] ) => {
		const l1 = group.find( ( item ) => item.level === 'L1' );
		const l2Raw = group.filter( ( item ) => item.level === 'L2' );
		const l2s = useAutoOrdering ? orderByPrevNext( l2Raw ) : l2Raw;
		const l3s = group.filter( ( item ) => item.level === 'L3' );
		const rootId =
			l1?.cluster_root_id ??
			l1?.id ??
			( group[ 0 ]?.cluster_root_id ?? group[ 0 ]?.id ?? groupRootId );
		const groupCandidates = candidates.filter( ( item ) => item.group_id === rootId );
		const maxLaneItems = Math.max( l2s.length, l3s.length, groupCandidates.length, 1 );
		const dynamicLaneGap = laneGap + Math.min( 320, Math.max( 0, l2s.length - 3 ) * 22 );
		const groupWidth = Math.max( minGroupWidth, ( maxLaneItems * dynamicLaneGap ) + 120 );
		const centerX = xOffset + ( groupWidth / 2 );

		if ( l1 ) {
			const saved = savedNodes[ String( l1.id ) ];
			newNodes.push( {
				id: String( l1.id ),
				position: {
					x: saved && Number.isFinite( saved.x ) ? saved.x : centerX,
					y: saved && Number.isFinite( saved.y ) ? saved.y : rowY.L1,
				},
				type: 'topicCluster',
				data: {
					nodeId: String( l1.id ),
					label: l1.title,
					level: l1.level,
					editUrl: l1.edit_url,
					orderEnabled: false,
				},
			} );
		}

		const laneScale = dynamicLaneGap / laneGap;
		const l2XList = spreadX( centerX, l2s.length ).map(
			( x ) => centerX + ( ( x - centerX ) * laneScale ),
		);
		const l2XById = new Map<number, number>();
		const l2SideById = new Map<number, 'top' | 'bottom'>();
		const l2OrderHandlesById = new Map<number, { prevIn: string; nextOut: string }>();

		l2s.forEach( ( l2, index ) => {
			const l2X = l2XList[ index ];
			const saved = savedNodes[ String( l2.id ) ];
			const side: 'top' | 'bottom' = 0 === index % 2 ? 'top' : 'bottom';
			const orderHandles =
				'bottom' === side
					? { prevIn: 'prev-in-right', nextOut: 'next-out-left' }
					: { prevIn: 'prev-in', nextOut: 'next-out' };
			const defaultL2Y = 'top' === side ? rowY.L2Top : rowY.L2Bottom;
			l2SideById.set( l2.id, side );
			l2OrderHandlesById.set( l2.id, orderHandles );
			l2XById.set( l2.id, l2X );
			newNodes.push( {
				id: String( l2.id ),
				position: {
					x: saved && Number.isFinite( saved.x ) ? saved.x : l2X,
					y: saved && Number.isFinite( saved.y ) ? saved.y : defaultL2Y,
				},
				type: 'topicCluster',
				data: {
					nodeId: String( l2.id ),
					label: l2.title,
					level: l2.level,
					editUrl: l2.edit_url,
					orderMode: 'bottom' === side ? 'reverse' : 'normal',
					orderEnabled: enableOrdering,
				},
			} );

			if ( l1 ) {
				newEdges.push( {
					id: `e${ l1.id }-${ l2.id }`,
					source: String( l1.id ),
					target: String( l2.id ),
					type: 'relationEdge',
					sourceHandle: 'top' === side ? 'parent-out' : 'child-out',
					targetHandle: 'top' === side ? 'child-in' : 'parent-in',
					data: {
						armed: false,
						edgeKind: 'hierarchy',
						relation: 'hierarchy',
						childPostId: l2.id,
					},
				} );
			}
		} );

		if ( enableOrdering ) {
			l2s.forEach( ( item ) => {
				const next = item.next_post_id ? Number( item.next_post_id ) : 0;
				if ( next <= 0 || ! l2s.some( ( sibling ) => sibling.id === next ) ) {
					return;
				}
				const sourceHandles = l2OrderHandlesById.get( item.id ) ?? {
					prevIn: 'prev-in',
					nextOut: 'next-out',
				};
				const targetHandles = l2OrderHandlesById.get( next ) ?? {
					prevIn: 'prev-in',
					nextOut: 'next-out',
				};
				newEdges.push( {
					id: `n${ item.id }-${ next }`,
					source: String( item.id ),
					target: String( next ),
					type: 'relationEdge',
					sourceHandle: sourceHandles.nextOut,
					targetHandle: targetHandles.prevIn,
					markerEnd: {
						type: MarkerType.ArrowClosed,
						color: '#64748b',
						width: 18,
						height: 18,
					},
					style: { stroke: '#64748b', strokeDasharray: '4 3' },
					data: {
						armed: false,
						edgeKind: 'order',
						relation: 'order',
						orderLane: 'L2',
						leftPostId: item.id,
						rightPostId: next,
					},
				} );
			} );
		}

		const l3ChildrenByParent = new Map<number, MindmapItem[]>();
		l3s.forEach( ( l3 ) => {
			const parentId = l3.parent_post_id ?? 0;
			const list = l3ChildrenByParent.get( parentId ) ?? [];
			list.push( l3 );
			l3ChildrenByParent.set( parentId, list );
		} );

		const l3CountByL2 = new Map<number, number>();
		l2s.forEach( ( l2 ) => {
			const count = ( l3ChildrenByParent.get( l2.id ) ?? [] ).length;
			l3CountByL2.set( l2.id, count );
		} );
		const uniqueL3Counts = Array.from( new Set( Array.from( l3CountByL2.values() ) ) ).sort(
			( a, b ) => a - b,
		);
		const l3OffsetByCount = new Map<number, number>();
		uniqueL3Counts.forEach( ( count, index ) => {
			l3OffsetByCount.set( count, index * l3DensityStep );
		} );
		const maxL3Offset =
			uniqueL3Counts.length > 0 ? ( uniqueL3Counts.length - 1 ) * l3DensityStep : 0;
		const candidateY = rowY.L2Bottom + l3BaseGap + maxL3Offset + 180;

		const l3YBySide = ( side: 'top' | 'bottom', childCount: number ): number => {
			const offset = l3OffsetByCount.get( childCount ) ?? 0;
			if ( 'top' === side ) {
				return rowY.L2Top - l3BaseGap - offset;
			}
			return rowY.L2Bottom + l3BaseGap + offset;
		};

		l2s.forEach( ( l2 ) => {
			const parentX = l2XById.get( l2.id ) ?? centerX;
			const childRaw = l3ChildrenByParent.get( l2.id ) ?? [];
			const children = useAutoOrdering ? orderByPrevNext( childRaw ) : childRaw;
			const l3XList = spreadX( parentX, children.length );
			const childCount = l3CountByL2.get( l2.id ) ?? 0;
			const side = l2SideById.get( l2.id ) ?? 'bottom';
			const l3Y = l3YBySide( side, childCount );

			children.forEach( ( l3, index ) => {
				const saved = savedNodes[ String( l3.id ) ];
				newNodes.push( {
					id: String( l3.id ),
					position: {
						x: saved && Number.isFinite( saved.x ) ? saved.x : l3XList[ index ],
						y: saved && Number.isFinite( saved.y ) ? saved.y : l3Y,
					},
					type: 'topicCluster',
					data: {
						nodeId: String( l3.id ),
						label: l3.title,
						level: l3.level,
						editUrl: l3.edit_url,
						orderMode: 'normal',
						l3Side: side,
						orderEnabled: enableOrdering,
					},
				} );
				newEdges.push( {
					id: `e${ l2.id }-${ l3.id }`,
					source: String( l2.id ),
					target: String( l3.id ),
					type: 'relationEdge',
					sourceHandle: 'top' === side ? 'parent-out' : 'child-out',
					targetHandle: 'top' === side ? 'child-in' : 'parent-in',
					data: {
						armed: false,
						edgeKind: 'hierarchy',
						relation: 'hierarchy',
						childPostId: l3.id,
					},
				} );
			} );
			l3ChildrenByParent.delete( l2.id );
		} );

		const orphanL3s = Array.from( l3ChildrenByParent.values() ).flat();
		const orderedOrphanL3s = orderByPrevNext( orphanL3s );
		const orphanL3XList = spreadX( centerX, orderedOrphanL3s.length );
		orderedOrphanL3s.forEach( ( l3, index ) => {
			const saved = savedNodes[ String( l3.id ) ];
			newNodes.push( {
				id: String( l3.id ),
				position: {
					x: saved && Number.isFinite( saved.x ) ? saved.x : orphanL3XList[ index ],
					y:
						saved && Number.isFinite( saved.y )
							? saved.y
							: rowY.L2Bottom + l3BaseGap + maxL3Offset,
				},
				type: 'topicCluster',
				data: {
					nodeId: String( l3.id ),
					label: l3.title,
					level: l3.level,
					editUrl: l3.edit_url,
					orderMode: 'normal',
					l3Side: 'bottom',
					orderEnabled: enableOrdering,
				},
			} );
		} );

		if ( enableOrdering ) {
			const allL3Raw = group.filter( ( item ) => item.level === 'L3' );
			const allL3sInGroup = useAutoOrdering ? orderByPrevNext( allL3Raw ) : allL3Raw;
			allL3sInGroup.forEach( ( item ) => {
				const next = item.next_post_id ? Number( item.next_post_id ) : 0;
				if ( next <= 0 || ! allL3sInGroup.some( ( sibling ) => sibling.id === next ) ) {
					return;
				}
				newEdges.push( {
					id: `n${ item.id }-${ next }`,
					source: String( item.id ),
					target: String( next ),
					type: 'relationEdge',
					sourceHandle: 'next-out',
					targetHandle: 'prev-in',
					markerEnd: {
						type: MarkerType.ArrowClosed,
						color: '#64748b',
						width: 18,
						height: 18,
					},
					style: { stroke: '#64748b', strokeDasharray: '4 3' },
					data: {
						armed: false,
						edgeKind: 'order',
						relation: 'order',
						orderLane: 'L3',
						leftPostId: item.id,
						rightPostId: next,
					},
				} );
			} );
		}

		const candidateXList = spreadX( centerX, groupCandidates.length );
		groupCandidates.forEach( ( candidate, index ) => {
			const nodeId = `c-${ candidate.id }`;
			const saved = savedNodes[ nodeId ];
			newNodes.push( {
				id: nodeId,
				position: {
					x: saved && Number.isFinite( saved.x ) ? saved.x : candidateXList[ index ],
					y: saved && Number.isFinite( saved.y ) ? saved.y : candidateY,
				},
				type: 'topicCluster',
				data: {
					nodeId: `candidate-${ candidate.id }`,
					label: candidate.title,
					level: 'NOT_SET',
					editUrl: candidate.edit_url,
					isCandidate: true,
					orderEnabled: false,
					onConfigure: () => onConfigureCandidate( candidate ),
				},
			} );
		} );

		xOffset += groupWidth + 80;
	} );

	if ( hasSavedLayout ) {
		return {
			nodes: newNodes,
			edges: newEdges,
		};
	}

	const minNodeSpacing = nodeWidth + 40;
	const adjustedNodes = [ ...newNodes ];
	const rowBuckets = new Map<number, number[]>();
	adjustedNodes.forEach( ( node, index ) => {
		const y = Math.round( node.position.y );
		const bucket = rowBuckets.get( y ) ?? [];
		bucket.push( index );
		rowBuckets.set( y, bucket );
	} );

	rowBuckets.forEach( ( rowIndexes ) => {
		rowIndexes
			.sort( ( a, b ) => adjustedNodes[ a ].position.x - adjustedNodes[ b ].position.x )
			.forEach( ( currentIndex, position ) => {
				if ( 0 === position ) {
					return;
				}
				const previousIndex = rowIndexes[ position - 1 ];
				const previousX = adjustedNodes[ previousIndex ].position.x;
				const currentX = adjustedNodes[ currentIndex ].position.x;
				const requiredX = previousX + minNodeSpacing;
				if ( currentX < requiredX ) {
					adjustedNodes[ currentIndex ] = {
						...adjustedNodes[ currentIndex ],
						position: {
							...adjustedNodes[ currentIndex ].position,
							x: requiredX,
						},
					};
				}
			} );
	} );

	return {
		nodes: adjustedNodes,
		edges: newEdges,
	};
};

const MindMap = ( {
	restBase,
	onNotice,
	groupId,
	enableOrdering,
	saveSignal,
	resetSignal,
	onDirtyChange,
	onSavingChange,
}: MindMapProps ) => {
	const [ nodes, setNodes, onNodesChange ] = useNodesState<Node>( [] );
	const [ edges, setEdges, onEdgesChange ] = useEdgesState<Edge>( [] );
	const [ mindmapItems, setMindmapItems ] = useState<MindmapItem[]>( [] );
	const [ mindmapCandidates, setMindmapCandidates ] = useState<MindmapCandidate[]>( [] );
	const [ draftMap, setDraftMap ] = useState<MindmapLayout>( { nodes: {} } );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ configuringCandidate, setConfiguringCandidate ] = useState<MindmapCandidate | null>( null );
	const [ configLevel, setConfigLevel ] = useState<ConfigLevel>( 'L1' );
	const [ configParentId, setConfigParentId ] = useState( 0 );
	const [ isFullscreen, setIsFullscreen ] = useState( false );
	const containerRef = useRef<HTMLDivElement | null>( null );
	const savedSnapshotRef = useRef<MindMapSnapshot | null>( null );
	const lastSaveSignalRef = useRef( 0 );
	const lastResetSignalRef = useRef( 0 );

	const mindmapPath = `${ restBase }/topic-cluster/mindmap`;
	const syncPath = groupId && groupId > 0 ? `${ restBase }/topic-cluster/groups/${ groupId }/mindmap-sync` : '';

	const buildSnapshot = useCallback(
		(
			items: MindmapItem[],
			candidates: MindmapCandidate[],
			map: MindmapLayout,
		): MindMapSnapshot => ( {
			items: items.map( ( item ) => ( { ...item } ) ),
			candidates: candidates.map( ( candidate ) => ( { ...candidate } ) ),
			map: cloneMapLayout( map ),
		} ),
		[],
	);

	const detachOrderLinks = useCallback( ( items: MindmapItem[], postId: number ): MindmapItem[] => {
		return items.map( ( item ) => {
			if ( item.id === postId ) {
				return {
					...item,
					prev_post_id: null,
					next_post_id: null,
				};
			}

			return {
				...item,
				prev_post_id: item.prev_post_id === postId ? null : item.prev_post_id ?? null,
				next_post_id: item.next_post_id === postId ? null : item.next_post_id ?? null,
			};
		} );
	}, [] );

	const detachOrderSide = useCallback(
		(
			items: MindmapItem[],
			postId: number,
			side: 'prev' | 'next',
		): MindmapItem[] =>
			items.map( ( item ) => {
				if ( item.id === postId ) {
					return {
						...item,
						prev_post_id: 'prev' === side ? null : item.prev_post_id ?? null,
						next_post_id: 'next' === side ? null : item.next_post_id ?? null,
					};
				}

				if ( 'prev' === side && item.next_post_id === postId ) {
					return {
						...item,
						next_post_id: null,
					};
				}

				if ( 'next' === side && item.prev_post_id === postId ) {
					return {
						...item,
						prev_post_id: null,
					};
				}

				return item;
			} ),
		[],
	);

	const replaceItem = useCallback(
		(
			items: MindmapItem[],
			postId: number,
			patch: Partial<MindmapItem>,
		): MindmapItem[] =>
			items.map( ( item ) => ( item.id === postId ? { ...item, ...patch } : item ) ),
		[],
	);

	const updateBranchClusterRoot = useCallback(
		( items: MindmapItem[], rootPostId: number, branchRootId: number ): MindmapItem[] => {
			const nextItems = items.map( ( item ) => ( { ...item } ) );
			const childMap = new Map<number, number[]>();

			nextItems.forEach( ( item ) => {
				const parentId = item.parent_post_id ?? 0;
				if ( parentId <= 0 ) {
					return;
				}
				const list = childMap.get( parentId ) ?? [];
				list.push( item.id );
				childMap.set( parentId, list );
			} );

			const walk = ( currentPostId: number, nextRootId: number ) => {
				nextItems.forEach( ( item, index ) => {
					if ( item.id === currentPostId ) {
						nextItems[ index ] = {
							...item,
							cluster_root_id: nextRootId,
						};
					}
				} );

				( childMap.get( currentPostId ) ?? [] ).forEach( ( childPostId ) => {
					walk( childPostId, nextRootId );
				} );
			};

			walk( branchRootId, rootPostId );

			return nextItems;
		},
		[],
	);

	const applyGraphState = useCallback(
		(
			nextItems: MindmapItem[],
			nextCandidates: MindmapCandidate[],
			nextMap: MindmapLayout,
		) => {
			const { nodes: nextNodes, edges: nextEdges } = buildMindMapGraph(
				nextItems,
				nextCandidates,
				nextMap.nodes ?? {},
				enableOrdering,
				( candidate ) => {
					setConfiguringCandidate( candidate );
					setConfigLevel( 'L1' );
					setConfigParentId( 0 );
					const nextGroupItems = nextItems.filter(
						( item ) => ( item.cluster_root_id ?? item.id ) === candidate.group_id,
					);
					const nextGroupHasPillar = nextGroupItems.some( ( item ) => 'L1' === item.level );
					if ( nextGroupHasPillar ) {
						setConfigLevel( 'L2' );
					}
				},
			);
			setNodes( nextNodes );
			setEdges( nextEdges );
		},
		[ enableOrdering, setEdges, setNodes ],
	);

	const loadMindmap = useCallback( async () => {
		setIsLoading( true );
		try {
			const query = groupId && groupId > 0 ? `?group_id=${ groupId }` : '';
			const response = await apiFetch<{
				items: MindmapItem[];
				candidates?: MindmapCandidate[];
				map?: MindmapLayout;
			}>( {
				path: `${ mindmapPath }${ query }`,
			} );
			const items = Array.isArray( response.items ) ? response.items : [];
			const candidates = Array.isArray( response.candidates ) ? response.candidates : [];
			const map =
				response.map && 'object' === typeof response.map
					? cloneMapLayout( response.map )
					: { nodes: {} };
			const snapshot = buildSnapshot( items, candidates, map );

			savedSnapshotRef.current = snapshot;
			setMindmapItems( snapshot.items );
			setMindmapCandidates( snapshot.candidates );
			setDraftMap( snapshot.map );
			applyGraphState( snapshot.items, snapshot.candidates, snapshot.map );
			onDirtyChange( false );
		} catch ( error ) {
			const message =
				error instanceof Error
					? error.message
					: __( 'Unable to load the mind map.', 'airygen-seo' );
			onNotice( { status: 'error', message } );
		} finally {
			setIsLoading( false );
		}
	}, [ applyGraphState, buildSnapshot, groupId, mindmapPath, onDirtyChange, onNotice ] );

	useEffect( () => {
		void loadMindmap();
	}, [ loadMindmap ] );

	useEffect( () => {
		const savedSnapshot = savedSnapshotRef.current;
		if ( ! savedSnapshot ) {
			onDirtyChange( false );
			return;
		}
		const currentSnapshot = buildSnapshot( mindmapItems, mindmapCandidates, draftMap );
		onDirtyChange(
			serializeMindMapSnapshot( currentSnapshot ) !== serializeMindMapSnapshot( savedSnapshot ),
		);
	}, [ buildSnapshot, draftMap, mindmapCandidates, mindmapItems, onDirtyChange ] );

	const selectedCandidateGroupItems = useMemo( () => {
		if ( ! configuringCandidate ) {
			return [] as MindmapItem[];
		}
		return mindmapItems.filter(
			( item ) => ( item.cluster_root_id ?? item.id ) === configuringCandidate.group_id,
		);
	}, [ configuringCandidate, mindmapItems ] );

	const groupHasPillar = useMemo(
		() => selectedCandidateGroupItems.some( ( item ) => 'L1' === item.level ),
		[ selectedCandidateGroupItems ],
	);

	const l1Parents = useMemo(
		() => {
			const groupParents = selectedCandidateGroupItems.filter( ( item ) => 'L1' === item.level );
			if ( groupParents.length > 0 ) {
				return groupParents;
			}
			return mindmapItems.filter( ( item ) => 'L1' === item.level );
		},
		[ selectedCandidateGroupItems, mindmapItems ],
	);

	const l2Parents = useMemo(
		() => {
			const groupParents = selectedCandidateGroupItems.filter( ( item ) => 'L2' === item.level );
			if ( groupParents.length > 0 ) {
				return groupParents;
			}
			return mindmapItems.filter( ( item ) => 'L2' === item.level );
		},
		[ selectedCandidateGroupItems, mindmapItems ],
	);

	useEffect( () => {
		if ( ! configuringCandidate ) {
			return;
		}
		if ( 'L1' === configLevel ) {
			setConfigParentId( 0 );
			return;
		}
		if ( 'L2' === configLevel ) {
			const defaultParent = l1Parents[ 0 ]?.id ?? 0;
			setConfigParentId( defaultParent );
			return;
		}
		const defaultParent = l2Parents[ 0 ]?.id ?? 0;
		setConfigParentId( defaultParent );
	}, [ configLevel, configuringCandidate, l1Parents, l2Parents ] );

	const handleSaveCandidateConfig = useCallback( () => {
		if ( ! configuringCandidate ) {
			return;
		}

		if ( 'L2' === configLevel && configParentId <= 0 ) {
			onNotice( {
				status: 'error',
				message: __( 'Select an L1 parent before saving.', 'airygen-seo' ),
			} );
			return;
		}

		if ( 'L3' === configLevel && configParentId <= 0 ) {
			onNotice( {
				status: 'error',
				message: __( 'Select an L2 parent before saving.', 'airygen-seo' ),
			} );
			return;
		}

		const parentRootId =
			configParentId > 0
				? mindmapItems.find( ( item ) => item.id === configParentId )?.cluster_root_id ??
					configParentId
				: configuringCandidate.group_id;

		const nextItem: MindmapItem = {
			id: configuringCandidate.post_id,
			title: configuringCandidate.title,
			level: configLevel,
			parent_post_id: configParentId > 0 ? configParentId : null,
			prev_post_id: null,
			next_post_id: null,
			cluster_root_id: 'L1' === configLevel ? configuringCandidate.post_id : parentRootId,
			edit_url: configuringCandidate.edit_url,
		};

		const nextItems = [ ...mindmapItems, nextItem ];
		const nextCandidates = mindmapCandidates.filter(
			( candidate ) => candidate.id !== configuringCandidate.id,
		);

		setMindmapItems( nextItems );
		setMindmapCandidates( nextCandidates );
		applyGraphState( nextItems, nextCandidates, draftMap );
		setConfiguringCandidate( null );
		onNotice( {
			status: 'success',
			message: __( 'Topic Cluster level updated in draft.', 'airygen-seo' ),
		} );
	}, [
		applyGraphState,
		configLevel,
		configParentId,
		configuringCandidate,
		draftMap,
		mindmapCandidates,
		mindmapItems,
		onNotice,
	] );

	const onConnect = useCallback(
		( connection: Connection ) => {
			if ( ! connection.source || ! connection.target ) {
				return;
			}

			const sourceHandle = connection.sourceHandle ?? '';
			const targetHandle = connection.targetHandle ?? '';
			const sourceIsNextOut = sourceHandle.startsWith( 'next-out' );
			const targetIsPrevIn = targetHandle.startsWith( 'prev-in' );
			const isOrderConnection =
				sourceIsNextOut &&
				targetIsPrevIn;

			const sourceId = Number( connection.source );
			const targetId = Number( connection.target );
			if ( Number.isNaN( sourceId ) || Number.isNaN( targetId ) ) {
				return;
			}

			const source = mindmapItems.find( ( item ) => item.id === sourceId );
			const target = mindmapItems.find( ( item ) => item.id === targetId );

			if ( ! source || ! target ) {
				return;
			}

			if ( isOrderConnection ) {
				if ( ! enableOrdering ) {
					onNotice( {
						status: 'error',
						message: __( 'Enable WordPress previous and next override in Settings to use ordering links.', 'airygen-seo' ),
					} );
					return;
				}
				if ( source.level !== target.level ) {
					onNotice( {
						status: 'error',
						message: __( 'Ordering only works between the same level.', 'airygen-seo' ),
					} );
					return;
				}
				if (
					'L3' === source.level &&
					( source.parent_post_id ?? 0 ) !== ( target.parent_post_id ?? 0 )
				) {
					onNotice( {
						status: 'error',
						message: __( 'L3 ordering only works within the same L2 parent.', 'airygen-seo' ),
					} );
					return;
				}

				let nextItems = detachOrderSide( mindmapItems, sourceId, 'next' );
				nextItems = detachOrderSide( nextItems, targetId, 'prev' );
				nextItems = replaceItem( nextItems, sourceId, { next_post_id: targetId } );
				nextItems = replaceItem( nextItems, targetId, { prev_post_id: sourceId } );
				setMindmapItems( nextItems );
				applyGraphState( nextItems, mindmapCandidates, draftMap );
				onNotice( {
					status: 'success',
					message: __( 'Left/right ordering updated in draft.', 'airygen-seo' ),
				} );
				return;
			}

			if (
				sourceHandle.startsWith( 'prev-in' ) ||
				targetHandle.startsWith( 'next-out' )
			) {
				onNotice( {
					status: 'error',
					message: __( 'Connect from next to prev to set ordering.', 'airygen-seo' ),
				} );
				return;
			}

			// Validate level hierarchy
			if ( target.level === 'L2' && source.level !== 'L1' ) {
				onNotice( {
					status: 'error',
					message: __(
						'L2 topics must be linked to an L1 parent.',
						'airygen-seo',
					),
				} );
				return;
			}

			if ( target.level === 'L3' && source.level !== 'L2' ) {
				onNotice( {
					status: 'error',
					message: __(
						'L3 topics must be linked to an L2 parent.',
						'airygen-seo',
					),
				} );
				return;
			}

			if ( target.level === 'L1' ) {
				onNotice( {
					status: 'error',
					message: __(
						'Pillar topics cannot be re-parented.',
						'airygen-seo',
					),
				} );
				return;
			}

			let nextItems = detachOrderLinks( mindmapItems, targetId );
			nextItems = nextItems.map( ( item ) => {
				if ( item.id !== targetId ) {
					return item;
				}

				return {
					...item,
					parent_post_id: sourceId,
					cluster_root_id:
						'L1' === source.level
							? source.id
							: source.cluster_root_id ?? source.id,
				};
			} );
			nextItems = updateBranchClusterRoot(
				nextItems,
				'L1' === source.level ? source.id : source.cluster_root_id ?? source.id,
				targetId,
			);
			setMindmapItems( nextItems );
			applyGraphState( nextItems, mindmapCandidates, draftMap );
			onNotice( {
				status: 'success',
				message: __( 'Cluster relationship updated in draft.', 'airygen-seo' ),
			} );
		},
		[
			applyGraphState,
			detachOrderSide,
			detachOrderLinks,
			draftMap,
			enableOrdering,
			mindmapCandidates,
			mindmapItems,
			onNotice,
			replaceItem,
			updateBranchClusterRoot,
		],
	);

	const onEdgeClick = useCallback(
		async ( event: React.MouseEvent, edge: Edge ) => {
			event.preventDefault();
			setEdges( ( currentEdges ) =>
				currentEdges.map( ( currentEdge ) => ( {
					...currentEdge,
					data: {
						...( currentEdge.data || {} ),
						armed: currentEdge.id === edge.id ? ! Boolean( currentEdge.data?.armed ) : false,
					},
				} ) ),
			);
		},
		[ setEdges ],
	);

	const onPaneClick = useCallback( () => {
		setEdges( ( currentEdges ) =>
			currentEdges.map( ( currentEdge ) => ( {
				...currentEdge,
				data: {
					...( currentEdge.data || {} ),
					armed: false,
				},
			} ) ),
		);
	}, [ setEdges ] );

	useEffect( () => {
		const handleDeleteEdge = ( event: Event ) => {
			const detail = ( event as CustomEvent<{
				edgeId: string;
				relation: string;
				childPostId: number;
				leftPostId: number;
				rightPostId: number;
			}> ).detail;
			if ( ! detail || ! detail.relation ) {
				return;
			}

			if ( 'hierarchy' === detail.relation ) {
				let nextItems = detachOrderLinks( mindmapItems, detail.childPostId );
				nextItems = nextItems.map( ( item ) => {
					if ( item.id !== detail.childPostId ) {
						return item;
					}

					return {
						...item,
						parent_post_id: null,
						cluster_root_id: item.id,
					};
				} );
				nextItems = updateBranchClusterRoot(
					nextItems,
					detail.childPostId,
					detail.childPostId,
				);
				setMindmapItems( nextItems );
				applyGraphState( nextItems, mindmapCandidates, draftMap );
			} else if ( 'order' === detail.relation ) {
				let nextItems = replaceItem( mindmapItems, detail.leftPostId, { next_post_id: null } );
				nextItems = replaceItem( nextItems, detail.rightPostId, { prev_post_id: null } );
				setMindmapItems( nextItems );
				applyGraphState( nextItems, mindmapCandidates, draftMap );
			}

			onNotice( {
				status: 'success',
				message: __( 'Relation removed in draft.', 'airygen-seo' ),
			} );
		};

		window.addEventListener( 'airygen-topic-cluster-delete-edge', handleDeleteEdge as EventListener );
		return () => {
			window.removeEventListener( 'airygen-topic-cluster-delete-edge', handleDeleteEdge as EventListener );
		};
	}, [
		applyGraphState,
		detachOrderLinks,
		draftMap,
		mindmapCandidates,
		mindmapItems,
		onNotice,
		replaceItem,
		updateBranchClusterRoot,
	] );

	useEffect( () => {
		if ( saveSignal <= 0 || saveSignal === lastSaveSignalRef.current ) {
			return;
		}
		lastSaveSignalRef.current = saveSignal;

		if ( ! syncPath ) {
			onNotice( {
				status: 'error',
				message: __( 'Select a group before saving the mind map.', 'airygen-seo' ),
			} );
			return;
		}

		const currentSnapshot = buildSnapshot( mindmapItems, mindmapCandidates, draftMap );
		if (
			savedSnapshotRef.current &&
			serializeMindMapSnapshot( currentSnapshot ) ===
				serializeMindMapSnapshot( savedSnapshotRef.current )
		) {
			return;
		}

		let cancelled = false;
		const saveDraft = async () => {
			onSavingChange( true );
			try {
				await apiFetch( {
					path: syncPath,
					method: 'POST',
					data: {
						items: currentSnapshot.items,
						candidates: currentSnapshot.candidates,
						map: currentSnapshot.map,
					},
				} );
				if ( cancelled ) {
					return;
				}
				onNotice( {
					status: 'success',
					message: __( 'Mind map saved.', 'airygen-seo' ),
				} );
				await loadMindmap();
			} catch ( error ) {
				if ( cancelled ) {
					return;
				}
				const message =
					error instanceof Error
						? error.message
						: __( 'Unable to save the mind map.', 'airygen-seo' );
				onNotice( { status: 'error', message } );
			} finally {
				if ( ! cancelled ) {
					onSavingChange( false );
				}
			}
		};

		void saveDraft();
		return () => {
			cancelled = true;
		};
	}, [
		buildSnapshot,
		draftMap,
		loadMindmap,
		mindmapCandidates,
		mindmapItems,
		onNotice,
		onSavingChange,
		saveSignal,
		syncPath,
	] );

	useEffect( () => {
		if ( resetSignal <= 0 || resetSignal === lastResetSignalRef.current ) {
			return;
		}
		lastResetSignalRef.current = resetSignal;

		const snapshot = savedSnapshotRef.current;
		if ( ! snapshot ) {
			return;
		}

		const resetSnapshot = buildSnapshot( snapshot.items, snapshot.candidates, snapshot.map );
		setMindmapItems( resetSnapshot.items );
		setMindmapCandidates( resetSnapshot.candidates );
		setDraftMap( resetSnapshot.map );
		applyGraphState( resetSnapshot.items, resetSnapshot.candidates, resetSnapshot.map );
		onDirtyChange( false );
		onNotice( {
			status: 'success',
			message: __( 'Mind map reset to last saved state.', 'airygen-seo' ),
		} );
	}, [ applyGraphState, buildSnapshot, onDirtyChange, onNotice, resetSignal ] );

	useEffect( () => {
		const handleFullscreenChange = () => {
			const node = containerRef.current;
			setIsFullscreen( Boolean( node ) && document.fullscreenElement === node );
		};

		document.addEventListener( 'fullscreenchange', handleFullscreenChange );
		return () => {
			document.removeEventListener( 'fullscreenchange', handleFullscreenChange );
		};
	}, [] );

	useEffect( () => {
		const containerNode = containerRef.current;
		if ( ! containerNode ) {
			return undefined;
		}

		const applyE2ELocators = () => {
			const canvasNode = containerNode.querySelector<HTMLElement>( TOPIC_CLUSTER_CANVAS_SELECTOR );
			if ( canvasNode ) {
				canvasNode.dataset.airygenE2e = 'topic-cluster-canvas';
			}

			const zoomOutButton = containerNode.querySelector<HTMLElement>( TOPIC_CLUSTER_ZOOM_OUT_SELECTORS );
			if ( zoomOutButton ) {
				zoomOutButton.dataset.airygenE2e = 'topic-cluster-zoom-out';
			}
		};

		applyE2ELocators();

		const observer = new MutationObserver( () => {
			applyE2ELocators();
		} );

		observer.observe( containerNode, {
			childList: true,
			subtree: true,
		} );

		return () => {
			observer.disconnect();
		};
	}, [ isLoading, nodes.length ] );

	const handleToggleFullscreen = useCallback( async () => {
		const node = containerRef.current;
		if ( ! node ) {
			return;
		}

		try {
			if ( document.fullscreenElement === node ) {
				await document.exitFullscreen();
				return;
			}
			await node.requestFullscreen();
		} catch ( error ) {
			const message =
				error instanceof Error
					? error.message
					: __( 'Unable to toggle full screen mode.', 'airygen-seo' );
			onNotice( { status: 'error', message } );
		}
	}, [ onNotice ] );

	return (
		<>
			<div
				ref={ containerRef }
				className="relative mt-4 h-[600px] w-full rounded-lg border border-slate-200 bg-slate-50"
				data-airygen-e2e="topic-cluster-mindmap"
			>
				<button
					type="button"
					className="absolute right-3 top-3 z-20 inline-flex h-8 w-8 items-center justify-center rounded border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
					data-airygen-e2e="topic-cluster-mindmap-fullscreen"
					onClick={ () => {
						void handleToggleFullscreen();
					} }
					aria-label={
						isFullscreen
							? __( 'Exit full screen', 'airygen-seo' )
							: __( 'Enter full screen', 'airygen-seo' )
					}
					title={
						isFullscreen
							? __( 'Exit full screen', 'airygen-seo' )
							: __( 'Enter full screen', 'airygen-seo' )
					}
				>
					<span
						className={
							isFullscreen
								? 'dashicons dashicons-editor-contract'
								: 'dashicons dashicons-editor-expand'
						}
						aria-hidden="true"
					/>
				</button>
				{ isLoading && (
					<div className="flex h-full items-center justify-center text-sm text-slate-500">
						{ getLoadingItemLabel( __( 'mind map', 'airygen-seo' ) ) }
					</div>
				) }
				{ ! isLoading && nodes.length === 0 && (
					<div className="flex h-full items-center justify-center text-sm text-slate-500">
						{ getNoItemsYetLabel( __( 'Topic Cluster data', 'airygen-seo' ) ) }
					</div>
				) }
				{ ! isLoading && nodes.length > 0 && (
					<ReactFlow
						nodes={ nodes }
						edges={ edges }
						onNodesChange={ onNodesChange }
						onEdgesChange={ onEdgesChange }
						onEdgeClick={ onEdgeClick }
						onPaneClick={ onPaneClick }
						onConnect={ onConnect }
						onNodeDragStop={ ( _event, movedNode ) => {
							const mergedNodes = nodes.map( ( currentNode ) => {
								if ( currentNode.id !== movedNode.id ) {
									return currentNode;
								}
								return {
									...currentNode,
									position: {
										x: movedNode.position.x,
										y: movedNode.position.y,
									},
								};
							} );
							const nodeMap: Record<string, MindmapPosition> = {};
							mergedNodes.forEach( ( node ) => {
								nodeMap[ node.id ] = {
									x: Math.round( node.position.x ),
									y: Math.round( node.position.y ),
								};
							} );
							setNodes( mergedNodes );
							setDraftMap( { nodes: nodeMap } );
						} }
						nodeTypes={ nodeTypes }
						edgeTypes={ edgeTypes as EdgeTypes }
						proOptions={ { hideAttribution: true } }
						fitView
						fitViewOptions={ {
							padding: 0.2,
							minZoom: 0.75,
							maxZoom: 0.75,
						} }
						className="bg-slate-50 airygen-topic-cluster-mindmap__flow"
					>
						<Background
							id="airygen-topic-cluster-grid"
							variant={ BackgroundVariant.Lines }
							gap={ 24 }
							size={ 1 }
							color="#e2e8f0"
						/>
						<MiniMap />
						<Controls />
					</ReactFlow>
				) }
			</div>
			<Modal
				isOpen={ null !== configuringCandidate }
				onClose={ () => setConfiguringCandidate( null ) }
				title={ __( 'Configure Topic Level', 'airygen-seo' ) }
				maxWidth="max-w-lg"
				footer={
					<div className="flex justify-end gap-2">
						<Button
							variant="secondary"
							onClick={ () => setConfiguringCandidate( null ) }
						>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ () => {
								handleSaveCandidateConfig();
							} }
						>
							{ __( 'Save', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4">
					{ configuringCandidate ? (
						<p className="text-sm text-slate-600">
							{ configuringCandidate.title }
						</p>
					) : null }
					<div className="space-y-2">
						<p className="text-sm font-medium text-slate-900">{ __( 'Level', 'airygen-seo' ) }</p>
						<div className="flex items-center gap-2 text-sm text-slate-700">
							<input
								type="radio"
								name="airygen-topic-level"
								value="L1"
								aria-label={ __( 'L1 (Pillar)', 'airygen-seo' ) }
								checked={ 'L1' === configLevel }
								disabled={ groupHasPillar }
								onChange={ () => setConfigLevel( 'L1' ) }
							/>
							<span>{ __( 'L1 (Pillar)', 'airygen-seo' ) }</span>
						</div>
						{ groupHasPillar ? (
							<p className="text-xs text-slate-500">
								{ __( 'Pillar already exists in this group, so L1 is unavailable.', 'airygen-seo' ) }
							</p>
						) : null }
						<div className="flex items-center gap-2 text-sm text-slate-700">
							<input
								type="radio"
								name="airygen-topic-level"
								value="L2"
								aria-label={ __( 'L2 (Cluster)', 'airygen-seo' ) }
								checked={ 'L2' === configLevel }
								onChange={ () => setConfigLevel( 'L2' ) }
							/>
							<span>{ __( 'L2 (Cluster)', 'airygen-seo' ) }</span>
						</div>
						<div className="flex items-center gap-2 text-sm text-slate-700">
							<input
								type="radio"
								name="airygen-topic-level"
								value="L3"
								aria-label={ __( 'L3 (Support)', 'airygen-seo' ) }
								checked={ 'L3' === configLevel }
								onChange={ () => setConfigLevel( 'L3' ) }
							/>
							<span>{ __( 'L3 (Support)', 'airygen-seo' ) }</span>
						</div>
					</div>
					{ 'L2' === configLevel ? (
						<div className="space-y-2">
							<p className="block text-sm font-medium text-slate-900">{ __( 'Parent L1', 'airygen-seo' ) }</p>
							<select
								className="airygen-field w-full"
								value={ configParentId }
								onChange={ ( event ) => setConfigParentId( Number( event.target.value ) ) }
							>
								<option value={ 0 }>{ __( 'Select a parent', 'airygen-seo' ) }</option>
								{ l1Parents.map( ( item ) => (
									<option key={ item.id } value={ item.id }>
										{ item.title }
									</option>
								) ) }
							</select>
						</div>
					) : null }
					{ 'L3' === configLevel ? (
						<div className="space-y-2">
							<p className="block text-sm font-medium text-slate-900">{ __( 'Parent L2', 'airygen-seo' ) }</p>
							<select
								className="airygen-field w-full"
								value={ configParentId }
								onChange={ ( event ) => setConfigParentId( Number( event.target.value ) ) }
							>
								<option value={ 0 }>{ __( 'Select a parent', 'airygen-seo' ) }</option>
								{ l2Parents.map( ( item ) => (
									<option key={ item.id } value={ item.id }>
										{ item.title }
									</option>
								) ) }
							</select>
						</div>
					) : null }
				</div>
			</Modal>
		</>
	);
};

export default MindMap;

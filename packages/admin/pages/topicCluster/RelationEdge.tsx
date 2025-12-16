import { __ } from '@wordpress/i18n';
// eslint-disable-next-line import/no-extraneous-dependencies
import {
	BaseEdge,
	EdgeLabelRenderer,
	getBezierPath,
	type Edge,
	type EdgeProps,
} from '@xyflow/react';

type RelationEdgeData = Record<string, unknown> & {
	armed?: boolean;
	edgeKind?: 'hierarchy' | 'order';
	relation?: 'hierarchy' | 'order';
	orderLane?: 'L2' | 'L3';
	childPostId?: number;
	leftPostId?: number;
	rightPostId?: number;
};

const RelationEdge = ( props: EdgeProps<Edge<RelationEdgeData>> ) => {
	const {
		id,
		sourceX,
		sourceY,
		targetX,
		targetY,
		sourcePosition,
		targetPosition,
		markerEnd,
		style,
		data,
	} = props;

	const edgeKind = data?.edgeKind || 'hierarchy';
	const orderLane = data?.orderLane || 'L3';
	const armed = Boolean( data?.armed );

	let path = '';
	let labelX = 0;
	let labelY = 0;

	if ( 'order' === edgeKind ) {
		const sourceSide = String( sourcePosition ).toLowerCase();
		const targetSide = String( targetPosition ).toLowerCase();
		const isSameSide =
			sourcePosition === targetPosition &&
			( 'right' === sourceSide || 'left' === sourceSide );

		if ( isSameSide ) {
			const isRightSide = 'right' === sourceSide;
			const direction = isRightSide ? 1 : -1;
			const sameSideFactor = 'L2' === orderLane ? 0.48 : 0.35;
			const outward = Math.max( 56, Math.min( 'L2' === orderLane ? 150 : 120, Math.round( Math.abs( sourceY - targetY ) * sameSideFactor ) ) );
			const xOuter =
				( isRightSide ? Math.max( sourceX, targetX ) : Math.min( sourceX, targetX ) ) +
				( direction * outward );
			const near = Math.max( 12, Math.min( 30, Math.round( outward * ( 'L2' === orderLane ? 0.20 : 0.24 ) ) ) );
			const isUpperL3Lane =
				'L3' === orderLane &&
				sourceY < 320 &&
				targetY < 320;

			if ( 'L2' === orderLane ) {
				const smoothHandle = Math.max( 90, Math.min( 210, Math.round( outward * 1.15 ) ) );
				path = `M ${ sourceX },${ sourceY } C ${ sourceX + ( direction * smoothHandle ) },${ sourceY } ${ targetX + ( direction * smoothHandle ) },${ targetY } ${ targetX },${ targetY }`;
				labelX = ( ( sourceX + targetX ) / 2 ) + ( direction * Math.round( smoothHandle * 0.78 ) );
				labelY = ( sourceY + targetY ) / 2;
			} else if ( isUpperL3Lane ) {
				const topDepth = Math.max(
					56,
					Math.min(
						180,
						Math.round(
							( Math.abs( sourceX - targetX ) * 0.18 ) + ( Math.abs( sourceY - targetY ) * 0.30 ),
						),
					),
				);
				const yOuter = Math.min( sourceY, targetY ) - topDepth;
				path = `M ${ sourceX },${ sourceY } C ${ sourceX + ( direction * near ) },${ sourceY } ${ xOuter },${ sourceY - ( topDepth * 0.55 ) } ${ xOuter },${ yOuter } C ${ xOuter },${ targetY - ( topDepth * 0.55 ) } ${ targetX + ( direction * near ) },${ targetY } ${ targetX },${ targetY }`;
				labelX = xOuter;
				labelY = yOuter - 8;
			} else {
				const yMid = ( sourceY + targetY ) / 2;
				const deltaY = targetY - sourceY;
				const curveRatio = 0.22;
				const cY1 = sourceY + ( deltaY * curveRatio );
				const cY2 = targetY - ( deltaY * curveRatio );

				path = `M ${ sourceX },${ sourceY } C ${ sourceX + ( direction * near ) },${ sourceY } ${ xOuter },${ cY1 } ${ xOuter },${ yMid } C ${ xOuter },${ cY2 } ${ targetX + ( direction * near ) },${ targetY } ${ targetX },${ targetY }`;
				labelX = xOuter;
				labelY = yMid;
			}
		} else {
			const isWrapAroundRightToLeft =
				sourceX > targetX &&
				'right' === sourceSide &&
				'left' === targetSide;
			const isWrapAroundLeftToRight =
				sourceX > targetX &&
				'left' === sourceSide &&
				'right' === targetSide;
			const isLowerL2InnerLink =
				'L2' === orderLane &&
				sourceY > 320 &&
				targetY > 320 &&
				'left' === sourceSide &&
				'right' === targetSide;
			if ( ( isWrapAroundRightToLeft || isWrapAroundLeftToRight ) && ! isLowerL2InnerLink ) {
				const distanceX = Math.abs( targetX - sourceX );
				const wrapFactor = 'L2' === orderLane ? 0.46 : 0.35;
				const arcDepth = Math.max( 110, Math.min( 'L2' === orderLane ? 320 : 260, Math.round( distanceX * wrapFactor ) ) );
				const isUpperL3Lane =
					'L3' === orderLane &&
					sourceY < 320 &&
					targetY < 320;
				const isLowerL2Lane =
					'L2' === orderLane &&
					sourceY > 320 &&
					targetY > 320;
				let controlY = Math.max( sourceY, targetY ) + arcDepth;
				if ( isUpperL3Lane || isLowerL2Lane ) {
					controlY = Math.min( sourceY, targetY ) - arcDepth;
				}
				const stub = Math.max( 28, Math.min( 64, Math.round( distanceX * 0.08 ) ) );
				const stubDirection = isWrapAroundRightToLeft ? 1 : -1;
				const near = Math.max( 12, Math.min( 26, Math.round( stub * 0.5 ) ) );
				const sourceOuterX = sourceX + ( stub * stubDirection );
				const targetOuterX = targetX - ( stub * stubDirection );
				path = `M ${ sourceX },${ sourceY } C ${ sourceX + ( near * stubDirection ) },${ sourceY } ${ sourceOuterX },${ controlY } ${ ( sourceOuterX + targetOuterX ) / 2 },${ controlY } C ${ targetOuterX },${ controlY } ${ targetX - ( near * stubDirection ) },${ targetY } ${ targetX },${ targetY }`;
				labelX = ( sourceOuterX + targetOuterX ) / 2;
				labelY = ( isUpperL3Lane || isLowerL2Lane ) ? controlY + 10 : controlY - 10;
			} else {
				[ path, labelX, labelY ] = getBezierPath( {
					sourceX,
					sourceY,
					targetX,
					targetY,
					sourcePosition,
					targetPosition,
				} );
			}
		}
	} else {
		[ path, labelX, labelY ] = getBezierPath( {
			sourceX,
			sourceY,
			targetX,
			targetY,
			sourcePosition,
			targetPosition,
		} );
	}

	return (
		<>
			<BaseEdge
				id={ id }
				path={ path }
				markerEnd={ markerEnd }
				style={ {
					stroke: 'order' === edgeKind ? '#64748b' : '#94a3b8',
					strokeWidth: 'order' === edgeKind ? 1.5 : 1.2,
					strokeDasharray: 'order' === edgeKind ? '4 3' : undefined,
					...style,
				} }
			/>
			{ armed ? (
				<EdgeLabelRenderer>
					<div
						style={ {
							position: 'absolute',
							transform: `translate(-50%, -50%) translate(${ labelX }px, ${ labelY }px)`,
							pointerEvents: 'all',
						} }
					>
						<button
							type="button"
							className="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-bold text-slate-700 shadow-sm hover:bg-slate-50"
							aria-label={ __( 'Remove relation', 'airygen-seo' ) }
							onClick={ ( event ) => {
								event.preventDefault();
								event.stopPropagation();
								window.dispatchEvent(
									new CustomEvent( 'airygen-topic-cluster-delete-edge', {
										detail: {
											edgeId: id,
											relation: data?.relation || 'hierarchy',
											childPostId: data?.childPostId || 0,
											leftPostId: data?.leftPostId || 0,
											rightPostId: data?.rightPostId || 0,
										},
									} ),
								);
							} }
						>
							x
						</button>
					</div>
				</EdgeLabelRenderer>
			) : null }
		</>
	);
};

export default RelationEdge;

import { memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
// eslint-disable-next-line import/no-extraneous-dependencies
import { Handle, Position } from '@xyflow/react';

type NodeData = {
	nodeId?: string;
	label: string;
	level: 'L1' | 'L2' | 'L3' | 'NOT_SET';
	editUrl?: string;
	isCandidate?: boolean;
	orderMode?: 'normal' | 'reverse';
	orderEnabled?: boolean;
	l3Side?: 'top' | 'bottom';
	onConfigure?: () => void;
};

function TopicClusterNode( { data }: { data: NodeData } ) {
	const hierarchyHandleClass =
		'!h-3.5 !w-3.5 !border-2 !border-white !bg-black !shadow-[0_0_0_1px_rgba(15,23,42,0.45)]';
	const prevHandleClass =
		'!h-3.5 !w-3.5 !border-2 !border-white !bg-green-500 !shadow-[0_0_0_1px_rgba(15,23,42,0.45)]';
	const nextHandleClass =
		'!h-3.5 !w-3.5 !border-2 !border-white !bg-blue-500 !shadow-[0_0_0_1px_rgba(15,23,42,0.45)]';

	const levelColors = {
		L1: 'bg-blue-100 border-blue-400',
		L2: 'bg-green-100 border-green-400',
		L3: 'bg-purple-100 border-purple-400',
		NOT_SET: 'bg-amber-50 border-amber-300',
	};

	const levelLabels = {
		L1: '🎯 Pillar',
		L2: '📦 Cluster',
		L3: '📄 Support',
		NOT_SET: '⚪ Not set',
	};

	const hideTopHandles = 'L3' === data.level && 'top' === data.l3Side;
	const hideBottomHandles = 'L3' === data.level && 'bottom' === data.l3Side;
	let renderOrderHandles = null;
	if ( 'L1' !== data.level && data.orderEnabled ) {
		if ( 'reverse' === data.orderMode ) {
			renderOrderHandles = (
				<>
					<Handle id="next-out-left" type="source" position={ Position.Left } className={ nextHandleClass } />
					<Handle id="prev-in-right" type="target" position={ Position.Right } className={ prevHandleClass } />
				</>
			);
		} else {
			renderOrderHandles = (
				<>
					<Handle id="prev-in" type="target" position={ Position.Left } className={ prevHandleClass } />
					<Handle id="next-out" type="source" position={ Position.Right } className={ nextHandleClass } />
				</>
			);
		}
	}

	return (
		<div
			className={ `w-[260px] min-h-[86px] rounded-lg border-2 bg-white px-4 py-2 shadow-md ${ levelColors[ data.level ] }` }
			data-airygen-e2e={
				data.nodeId
					? `topic-cluster-node-${ data.nodeId }`
					: 'topic-cluster-node'
			}
		>
			<div className="flex items-center gap-2">
				<div className="text-lg">{ levelLabels[ data.level ].split( ' ' )[ 0 ] }</div>
				<div className="min-w-0 flex-1">
					<div
						className="text-sm font-bold leading-tight text-slate-900"
						style={ {
							display: '-webkit-box',
							WebkitLineClamp: 2,
							WebkitBoxOrient: 'vertical',
							overflow: 'hidden',
							wordBreak: 'break-word',
						} }
					>
						{ data.label }
					</div>
					<div className="text-xs text-gray-500">
						{ levelLabels[ data.level ].split( ' ' )[ 1 ] }
					</div>
				</div>
				{ data.isCandidate && data.onConfigure ? (
					<button
						type="button"
						className="ml-auto rounded border border-slate-300 bg-white px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50"
						onClick={ data.onConfigure }
					>
						{ __( 'Configure', 'airygen-seo' ) }
					</button>
				) : null }
			</div>

			{ ! hideTopHandles ? (
				<>
					<Handle id="parent-in" type="target" position={ Position.Top } className={ hierarchyHandleClass } />
					<Handle id="parent-out" type="source" position={ Position.Top } className={ hierarchyHandleClass } />
				</>
			) : null }
			{ ! hideBottomHandles ? (
				<>
					<Handle id="child-out" type="source" position={ Position.Bottom } className={ hierarchyHandleClass } />
					<Handle id="child-in" type="target" position={ Position.Bottom } className={ hierarchyHandleClass } />
				</>
			) : null }
			{ renderOrderHandles }
		</div>
	);
}

export default memo( TopicClusterNode );

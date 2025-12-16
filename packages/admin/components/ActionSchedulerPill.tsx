import { __ } from '@wordpress/i18n';

import { ActionSchedulerIcon } from './Icons';

type ActionSchedulerPillProps = {
	available: boolean;
};

const ActionSchedulerPill = ( { available }: ActionSchedulerPillProps ) => {
	const classes = available
		? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
		: 'bg-amber-50 text-amber-700 ring-amber-100';

	return (
		<span
			className={ `inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 ${ classes }` }
		>
			<ActionSchedulerIcon
				className="h-8 w-8 flex-shrink-0"
				style={ { transform: 'translateY(1px)' } }
				aria-hidden="true"
			/>
			{ available
				? __( 'background execution', 'airygen-seo' )
				: __( 'Action Scheduler missing', 'airygen-seo' ) }
		</span>
	);
};

export default ActionSchedulerPill;

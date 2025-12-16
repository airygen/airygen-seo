import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

type ToastProps = {
	status: 'success' | 'error';
	message: string;
	onDismiss: () => void;
	autoHideAfter?: number;
};

const baseClasses =
	'pointer-events-auto relative flex min-w-[360px] items-center gap-4 rounded-xl border border-slate-200 bg-white px-7 py-5 shadow-xl ring-1 ring-black/5 transform transition-all duration-300 ease-out overflow-hidden';

const statusVisuals: Record<
	ToastProps['status'],
	{ accent: string; iconColor: string }
> = {
	success: {
		accent: 'bg-emerald-500',
		iconColor: 'text-emerald-500',
	},
	error: {
		accent: 'bg-rose-500',
		iconColor: 'text-rose-500',
	},
};

const closeClasses =
	'absolute top-2 right-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-500 text-xs transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300';

const Toast = ( {
	status,
	message,
	onDismiss,
	autoHideAfter = 5000,
}: ToastProps ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const dismissTimer = useRef<number | null>( null );
	const hideTimer = useRef<number | null>( null );
	const enterTimer = useRef<number | null>( null );

	const clearTimers = useCallback( () => {
		if ( dismissTimer.current ) {
			window.clearTimeout( dismissTimer.current );
			dismissTimer.current = null;
		}

		if ( hideTimer.current ) {
			window.clearTimeout( hideTimer.current );
			hideTimer.current = null;
		}
		if ( enterTimer.current ) {
			window.clearTimeout( enterTimer.current );
			enterTimer.current = null;
		}
	}, [] );

	const startHideSequence = useCallback( () => {
		if ( dismissTimer.current ) {
			window.clearTimeout( dismissTimer.current );
			dismissTimer.current = null;
		}

		setIsVisible( false );

		if ( hideTimer.current ) {
			window.clearTimeout( hideTimer.current );
		}

		hideTimer.current = window.setTimeout( () => {
			clearTimers();
			onDismiss();
		}, 220 );
	}, [ clearTimers, onDismiss ] );

	const scheduleDismissal = useCallback(
		( delay: number ) => {
			if ( delay <= 0 ) {
				return;
			}

			if ( dismissTimer.current ) {
				window.clearTimeout( dismissTimer.current );
			}

			dismissTimer.current = window.setTimeout( () => {
				startHideSequence();
			}, delay );
		},
		[ startHideSequence ],
	);

	const handleManualDismiss = () => {
		startHideSequence();
	};

	useEffect( () => {
		clearTimers();
		setIsVisible( false );

		enterTimer.current = window.setTimeout( () => {
			setIsVisible( true );
			scheduleDismissal( autoHideAfter );
		}, 20 );

		return () => {
			setIsVisible( false );
			clearTimers();
		};
	}, [ message, status, autoHideAfter, scheduleDismissal, clearTimers ] );

	const animationClasses = isVisible
		? 'translate-x-0 opacity-100'
		: 'translate-x-12 opacity-0';

	return (
		<div
			className={ `${ baseClasses } ${ animationClasses } text-slate-900` }
			role="status"
			aria-live="polite"
		>
			<span
				className={ `absolute inset-y-0 left-0 w-1 rounded-bl-xl rounded-tl-xl ${ statusVisuals[ status ].accent }` }
				aria-hidden="true"
			/>
			<div
				className={ `flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 ${ statusVisuals[ status ].iconColor }` }
			>
				<svg
					viewBox="0 0 16 16"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					className="h-5 w-5"
					aria-hidden="true"
				>
					{ 'success' === status ? (
						<path
							d="M6.714 11.286 4.143 8.714l1.143-1.143 1.428 1.428 4-4 1.143 1.143-5.143 5.143Z"
							fill="currentColor"
						/>
					) : (
						<path
							d="M8 6.586 4.757 3.343 3.343 4.757 6.586 8l-3.243 3.243 1.414 1.414L8 9.414l3.243 3.243 1.414-1.414L9.414 8l3.243-3.243-1.414-1.414L8 6.586Z"
							fill="currentColor"
						/>
					) }
				</svg>
			</div>
			<div className="flex-1 text-base leading-relaxed">{ message }</div>
			<button
				type="button"
				className={ closeClasses }
				onClick={ handleManualDismiss }
			>
				<span className="sr-only">{ __( 'Dismiss notification', 'airygen-seo' ) }</span>
				<svg
					viewBox="0 0 16 16"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					className="h-3 w-3"
					aria-hidden="true"
				>
					<path
						d="M11.333 4.667 8 8l3.333 3.333-1.333 1.333L6.667 9.333 3.333 12.667 2 11.333 5.333 8 2 4.667 3.333 3.333 6.667 6.667 10 3.333 11.333 4.667Z"
						fill="currentColor"
					/>
				</svg>
			</button>
		</div>
	);
};

export default Toast;

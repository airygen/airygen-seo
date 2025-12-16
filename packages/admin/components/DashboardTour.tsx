import { useCallback, useEffect, useRef, useState, createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Button from './Button';

// ─── Constants ────────────────────────────────────────────────────────────────

const STORAGE_KEY = 'airygen_dashboard_tour_v1';
const SPOT_PADDING = 10;
const TOOLTIP_WIDTH = 340;
const TOOLTIP_GAP = 14;

// ─── Types ────────────────────────────────────────────────────────────────────

interface TourStep {
	targetId: string;
	/** Optional secondary element to pulse-highlight (CSS selector). */
	secondarySelector?: string;
	title: string;
	body: string;
	placement: 'top' | 'bottom';
}

interface ElementRect {
	top: number;
	left: number;
	width: number;
	height: number;
}

// ─── Step definitions ─────────────────────────────────────────────────────────

const STEPS: TourStep[] = [
	{
		targetId: 'airygen-tour-step-1',
		secondarySelector: '[data-airygen-e2e="primary-menu-button-settings"]',
		title: __( 'Two ways to open settings', 'airygen-seo' ),
		body: __(
			'Click "Open global settings" here, or use Settings in the top navigation bar — both take you to the same settings page.',
			'airygen-seo',
		),
		placement: 'bottom',
	},
	{
		targetId: 'airygen-tour-step-2',
		title: __( 'Drag modules to reorder', 'airygen-seo' ),
		body: __(
			'Drag any module card to rearrange them. The Settings menu bar reflects this exact order — put your most-used modules up front.',
			'airygen-seo',
		),
		placement: 'bottom',
	},
	{
		targetId: 'airygen-tour-step-3',
		title: __( 'Control editor panel order', 'airygen-seo' ),
		body: __(
			'Drag these panel cards to set the display order of sidebar panels shown in the post editor. The order here is the order there.',
			'airygen-seo',
		),
		placement: 'top',
	},
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getRect( el: Element ): ElementRect {
	const r = el.getBoundingClientRect();
	return { top: r.top, left: r.left, width: r.width, height: r.height };
}

// ─── Component ────────────────────────────────────────────────────────────────

type DashboardTourProps = {
	/** Tour will not start until the install wizard has been dismissed. */
	wizardDismissed: boolean;
};

const DashboardTour = ( { wizardDismissed }: DashboardTourProps ) => {
	const [ active, setActive ] = useState( false );
	const [ stepIndex, setStepIndex ] = useState( 0 );
	const [ primaryRect, setPrimaryRect ] = useState<ElementRect | null>( null );
	const [ secondaryRect, setSecondaryRect ] = useState<ElementRect | null>( null );
	const scrollTimerRef = useRef<ReturnType<typeof setTimeout> | null>( null );

	// ── Show on first visit (only after install wizard is done) ─────────────

	useEffect( () => {
		if ( ! wizardDismissed ) {
			return;
		}
		try {
			if ( ! localStorage.getItem( STORAGE_KEY ) ) {
				setActive( true );
			}
		} catch {
			// localStorage unavailable — skip tour
		}
	}, [ wizardDismissed ] );

	const currentStep = STEPS[ stepIndex ];

	// ── Measure targets ──────────────────────────────────────────────────────

	const measureTargets = useCallback( () => {
		if ( ! currentStep ) {
			return;
		}

		const primaryEl = document.getElementById( currentStep.targetId );
		if ( primaryEl ) {
			setPrimaryRect( getRect( primaryEl ) );
		}

		if ( currentStep.secondarySelector ) {
			const secEl = document.querySelector( currentStep.secondarySelector );
			setSecondaryRect( secEl ? getRect( secEl ) : null );
		} else {
			setSecondaryRect( null );
		}
	}, [ currentStep ] );

	// ── Scroll + measure when step changes ───────────────────────────────────

	useEffect( () => {
		if ( ! active || ! currentStep ) {
			return;
		}

		setPrimaryRect( null );
		setSecondaryRect( null );

		let retryCount = 0;
		const MAX_RETRIES = 8;
		const RETRY_MS = 250;

		const attemptScrollAndMeasure = () => {
			const primaryEl = document.getElementById( currentStep.targetId );
			if ( primaryEl ) {
				primaryEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				// Measure immediately (element is visible) and again after scroll settles
				measureTargets();
				scrollTimerRef.current = setTimeout( measureTargets, 450 );
			} else if ( retryCount < MAX_RETRIES ) {
				// Element not yet in DOM — retry
				retryCount++;
				scrollTimerRef.current = setTimeout( attemptScrollAndMeasure, RETRY_MS );
			}
		};

		// Small initial delay to let React finish painting the sibling elements
		scrollTimerRef.current = setTimeout( attemptScrollAndMeasure, 100 );

		window.addEventListener( 'resize', measureTargets );
		window.addEventListener( 'scroll', measureTargets, true );

		return () => {
			if ( scrollTimerRef.current ) {
				clearTimeout( scrollTimerRef.current );
			}
			window.removeEventListener( 'resize', measureTargets );
			window.removeEventListener( 'scroll', measureTargets, true );
		};
	}, [ active, currentStep, measureTargets ] );

	// ── Navigation ───────────────────────────────────────────────────────────

	const dismiss = useCallback( () => {
		try {
			localStorage.setItem( STORAGE_KEY, '1' );
		} catch {}
		setActive( false );
	}, [] );

	const handleNext = useCallback( () => {
		if ( stepIndex < STEPS.length - 1 ) {
			setStepIndex( ( i ) => i + 1 );
		} else {
			dismiss();
		}
	}, [ stepIndex, dismiss ] );

	const handlePrev = useCallback( () => {
		if ( stepIndex > 0 ) {
			setStepIndex( ( i ) => i - 1 );
		}
	}, [ stepIndex ] );

	// ── Render ───────────────────────────────────────────────────────────────

	if ( ! active || ! primaryRect || ! currentStep ) {
		return null;
	}

	const spotTop = primaryRect.top - SPOT_PADDING;
	const spotLeft = primaryRect.left - SPOT_PADDING;
	const spotWidth = ( primaryRect.width ) + ( SPOT_PADDING * 2 );
	const spotHeight = ( primaryRect.height ) + ( SPOT_PADDING * 2 );

	// Tooltip vertical position
	const tooltipTop =
		currentStep.placement === 'bottom'
			? spotTop + spotHeight + TOOLTIP_GAP
			: undefined;
	const tooltipBottom =
		currentStep.placement === 'top'
			? ( window.innerHeight - spotTop ) + TOOLTIP_GAP
			: undefined;

	// Tooltip horizontal position — centred on spotlight, clamped to viewport
	const tooltipLeft = Math.max(
		16,
		Math.min(
			window.innerWidth - TOOLTIP_WIDTH - 16,
			( spotLeft + ( spotWidth / 2 ) ) - ( TOOLTIP_WIDTH / 2 ),
		),
	);

	const isLast = stepIndex === STEPS.length - 1;

	const overlay = (
		<>
			{ /* ── Click-away backdrop ─────────────────────────────────────── */ }
			<div
				aria-hidden="true"
				className="fixed inset-0"
				style={ { zIndex: 9996 } }
				onClick={ dismiss }
			/>

			{ /* ── Primary spotlight ────────────────────────────────────────── */ }
			<div
				aria-hidden="true"
				style={ {
					position: 'fixed',
					top: spotTop,
					left: spotLeft,
					width: spotWidth,
					height: spotHeight,
					borderRadius: 12,
					boxShadow: '0 0 0 9999px rgba(0,0,0,0.52)',
					zIndex: 9997,
					pointerEvents: 'none',
					transition: 'top 0.25s ease, left 0.25s ease, width 0.25s ease, height 0.25s ease',
				} }
			/>

			{ /* ── Secondary pulse ring (e.g. nav Settings button) ─────────── */ }
			{ secondaryRect && (
				<div
					aria-hidden="true"
					style={ {
						position: 'fixed',
						top: secondaryRect.top - 4,
						left: secondaryRect.left - 4,
						width: secondaryRect.width + 8,
						height: secondaryRect.height + 8,
						borderRadius: 999,
						zIndex: 9998,
						pointerEvents: 'none',
					} }
					className="animate-ping border-2 border-sky-400"
				/>
			) }

			{ /* ── Tooltip card ─────────────────────────────────────────────── */ }
			<div
				role="dialog"
				aria-modal="false"
				aria-label={ currentStep.title }
				style={ {
					position: 'fixed',
					top: tooltipTop !== undefined ? tooltipTop : 'auto',
					bottom: tooltipBottom !== undefined ? tooltipBottom : 'auto',
					left: tooltipLeft,
					width: TOOLTIP_WIDTH,
					zIndex: 9999,
				} }
				className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xl"
			>
				{ /* Step counter + skip */ }
				<div className="mb-2.5 flex items-center justify-between">
					<div className="flex items-center gap-1.5">
						{ STEPS.map( ( _, i ) => (
							<span
								key={ i }
								className={ `block h-1.5 w-1.5 rounded-full transition-colors ${
									i === stepIndex ? 'bg-sky-500' : 'bg-slate-200'
								}` }
							/>
						) ) }
					</div>
					<button
						type="button"
						onClick={ dismiss }
						className="text-xs text-slate-400 hover:text-slate-600"
					>
						{ __( 'Skip tour', 'airygen-seo' ) }
					</button>
				</div>

				<h3 className="text-sm font-semibold text-slate-900">
					{ currentStep.title }
				</h3>
				<p className="mt-1.5 text-sm leading-relaxed text-slate-500">
					{ currentStep.body }
				</p>

				{ /* Navigation */ }
				<div className="mt-4 flex items-center justify-between gap-2">
					{ stepIndex > 0 ? (
						<Button
							variant="secondary"
							className="px-3 py-1.5 text-xs"
							onClick={ handlePrev }
						>
							{ __( '← Back', 'airygen-seo' ) }
						</Button>
					) : (
						<span />
					) }
					<Button
						variant="primary"
						className="px-3 py-1.5 text-xs"
						onClick={ handleNext }
					>
						{ isLast ? __( 'Done', 'airygen-seo' ) : __( 'Next →', 'airygen-seo' ) }
					</Button>
				</div>
			</div>
		</>
	);

	return createPortal( overlay, document.body );
};

export default DashboardTour;

import { useState, useRef, useEffect, createPortal, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

type PopoverProps = {
	trigger: React.ReactNode;
	children: React.ReactNode;
	className?: string;
	position?: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';
	triggerAs?: 'button' | 'div' | 'span';
	showClose?: boolean;
	onClose?: () => void;
};

const Popover = ( {
	trigger,
	children,
	className = '',
	position = 'bottom-right',
	triggerAs = 'button',
	showClose = false,
	onClose,
}: PopoverProps ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ coords, setCoords ] = useState<React.CSSProperties>( {} );
	const containerRef = useRef<HTMLDivElement>( null );
	const popoverRef = useRef<HTMLDivElement>( null );

	const updatePosition = useCallback( () => {
		if ( ! containerRef.current ) {
			return;
		}

		const rect = containerRef.current.getBoundingClientRect();
		const style: React.CSSProperties = {
			position: 'fixed',
			zIndex: 9999,
		};

		// Add a small gap
		const gap = 8;

		switch ( position ) {
			case 'bottom-right':
				style.top = rect.bottom + gap;
				style.right = window.innerWidth - rect.right;
				break;
			case 'bottom-left':
				style.top = rect.bottom + gap;
				style.left = rect.left;
				break;
			case 'top-right':
				style.bottom = window.innerHeight - rect.top + gap;
				style.right = window.innerWidth - rect.right;
				break;
			case 'top-left':
				style.bottom = window.innerHeight - rect.top + gap;
				style.left = rect.left;
				break;
		}

		setCoords( style );
	}, [ position ] );

	useEffect( () => {
		const handleClickOutside = ( event: MouseEvent ) => {
			const target = event.target as Node;
			if (
				containerRef.current &&
				! containerRef.current.contains( target ) &&
				popoverRef.current &&
				! popoverRef.current.contains( target )
			) {
				setIsOpen( false );
			}
		};

		const handleScroll = () => {
			if ( isOpen ) {
				updatePosition();
			}
		};

		const handleResize = () => {
			if ( isOpen ) {
				updatePosition();
			}
		};

		if ( isOpen ) {
			updatePosition();
			document.addEventListener( 'mousedown', handleClickOutside );
			window.addEventListener( 'scroll', handleScroll, true );
			window.addEventListener( 'resize', handleResize );
		}

		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
			window.removeEventListener( 'scroll', handleScroll, true );
			window.removeEventListener( 'resize', handleResize );
		};
	}, [ isOpen, position, updatePosition ] );

	const toggle = () => setIsOpen( ( prev ) => ! prev );
	const close = () => {
		setIsOpen( false );
		if ( onClose ) {
			onClose();
		}
	};

	return (
		<div className="inline-block text-left" ref={ containerRef }>
			{ triggerAs === 'button' ? (
				<button
					type="button"
					onClick={ toggle }
					className="cursor-pointer inline-flex bg-transparent border-0 p-0"
				>
					{ trigger }
				</button>
			) : (
				<div
					role="button"
					tabIndex={ 0 }
					onClick={ toggle }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' || event.key === ' ' ) {
							event.preventDefault();
							toggle();
						}
					} }
					className="cursor-pointer inline-flex"
				>
					{ trigger }
				</div>
			) }

			{ isOpen &&
				createPortal(
					<div
						ref={ popoverRef }
						style={ coords }
						className={ `w-64 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none ${ className }` }
					>
						{ showClose && (
							<button
								type="button"
								onClick={ close }
								className="absolute right-2 top-2 inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 text-xs text-slate-500 hover:border-slate-300 hover:text-slate-700"
								aria-label={ __( 'Close', 'airygen-seo' ) }
							>
								<span
									className="dashicons dashicons-no-alt m-0 block h-[14px] w-[14px] text-[14px] leading-[14px]"
									aria-hidden="true"
								/>
							</button>
						) }
						<div className="p-6 text-sm text-slate-600">
							{ children }
						</div>
					</div>,
					document.body,
				) }
		</div>
	);
};

export default Popover;

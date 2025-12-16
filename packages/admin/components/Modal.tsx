import { useEffect, useRef, createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

type ModalProps = {
	isOpen: boolean;
	onClose: () => void;
	title: string;
	children: React.ReactNode;
	footer?: React.ReactNode;
	className?: string;
	bodyClassName?: string;
	maxWidth?: string;
};

const Modal = ( {
	isOpen,
	onClose,
	title,
	children,
	footer,
	className = '',
	bodyClassName = '',
	maxWidth = 'max-w-2xl',
}: ModalProps ) => {
	const modalRef = useRef<HTMLDivElement>( null );

	useEffect( () => {
		const handleEscape = ( event: KeyboardEvent ) => {
			if ( event.key === 'Escape' ) {
				onClose();
			}
		};

		if ( isOpen ) {
			document.addEventListener( 'keydown', handleEscape );
			document.body.style.overflow = 'hidden';
		}

		return () => {
			document.removeEventListener( 'keydown', handleEscape );
			document.body.style.overflow = 'unset';
		};
	}, [ isOpen, onClose ] );

	if ( ! isOpen ) {
		return null;
	}

	return createPortal(
		<div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6">
			<div
				className="fixed inset-0 bg-slate-900/50 transition-opacity backdrop-blur-sm"
				onClick={ onClose }
				aria-hidden="true"
			/>

			<div
				ref={ modalRef }
				className={ `airygen-admin-root relative flex max-h-[90vh] w-full flex-col rounded-xl bg-white shadow-2xl ring-1 ring-slate-900/5 ${ maxWidth } ${ className }` }
				data-airygen-e2e="modal"
				role="dialog"
				aria-modal="true"
				aria-labelledby="modal-title"
			>
				<div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
					<h3
						id="modal-title"
						className="text-lg font-semibold text-slate-900"
					>
						{ title }
					</h3>
					<button
						onClick={ onClose }
						className="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500"
						data-airygen-e2e="button-close-modal"
						aria-label={ __( 'Close modal', 'airygen-seo' ) }
					>
						<svg
							className="h-6 w-6"
							fill="none"
							viewBox="0 0 24 24"
							strokeWidth="1.5"
							stroke="currentColor"
						>
							<path
								strokeLinecap="round"
								strokeLinejoin="round"
								d="M6 18L18 6M6 6l12 12"
							/>
						</svg>
					</button>
				</div>

				<div className={ `flex-1 overflow-y-auto px-6 py-6 ${ bodyClassName }` }>
					{ children }
				</div>

				{ footer && (
					<div className="border-slate-100 bg-slate-50 px-6 py-4 rounded-b-xl border-t">
						{ footer }
					</div>
				) }
			</div>
		</div>,
		document.body,
	);
};

export default Modal;

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

type InfoPopoverProps = {
	content: string;
};

const InfoPopover = ( { content }: InfoPopoverProps ) => {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<div className="relative">
			<button
				type="button"
				className="_airygen_tips_popover ml-2 flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-[10px] text-slate-500"
				onClick={ () => setIsOpen( ( value ) => ! value ) }
				onBlur={ () => setIsOpen( false ) }
				aria-label={ __( 'More info', 'airygen-seo' ) }
			>
				?
			</button>
			{ isOpen ? (
				<div className="absolute right-0 z-10 mt-2 w-56 rounded-md border border-black bg-black p-3 text-xs text-white shadow-lg">
					{ content }
				</div>
			) : null }
		</div>
	);
};

export default InfoPopover;

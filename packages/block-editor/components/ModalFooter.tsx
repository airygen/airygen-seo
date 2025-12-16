import type { ReactNode } from 'react';

type ModalFooterProps = {
	children: ReactNode;
};

const ModalFooter = ( { children }: ModalFooterProps ) => (
	<div className="airygen-modal-footer">
		{ children }
	</div>
);

export default ModalFooter;

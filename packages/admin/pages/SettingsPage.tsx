import type { ReactNode } from 'react';
import { useState } from '@wordpress/element';
import Button from '../components/Button';
import { __ } from '@wordpress/i18n';
import Modal from '../components/Modal';
import {
	getAllChangesSavedLabel,
	getResetApplySaveLabel,
	getSaveChangesLabel,
	getSavingLabel,
	getUnsavedChangesLabel,
} from '../utils/i18n';

type SettingsPageProps = {
	panel: ReactNode | null;
	isDirty: boolean;
	isSaving: boolean;
	onReset?: () => void;
	onRetry?: () => void;
	onSave: () => void;
	showFooter: boolean;
	emptyMessage: string;
};

const SettingsPage = ( {
	panel,
	isDirty,
	isSaving,
	onReset,
	onRetry,
	onSave,
	showFooter,
	emptyMessage,
}: SettingsPageProps ) => {
	const [ isResetModalOpen, setIsResetModalOpen ] = useState( false );
	const handleReset = onReset ?? onRetry ?? ( () => {} );

	return (
		<div className="space-y-5">
			{ panel ?? (
				<div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
					{ emptyMessage }
				</div>
			) }
			{ showFooter ? (
				<footer className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between" data-airygen-e2e="settings-footer">
					<div>
						{ isDirty ? (
							<span className="text-sm font-medium text-amber-600">
								{ getUnsavedChangesLabel() }
							</span>
						) : (
							<span className="text-sm text-slate-400">
								{ getAllChangesSavedLabel() }
							</span>
						) }
					</div>
					<div className="flex items-center gap-3">
						<Button
							variant="secondary"
							onClick={ () => setIsResetModalOpen( true ) }
							disabled={ isSaving }
							data-airygen-e2e="button-reset-settings"
						>
							{ __( 'Reset', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ onSave }
							loading={ isSaving }
							disabled={ ! isDirty || isSaving }
							data-airygen-e2e="button-save-settings"
						>
							{ isSaving
								? getSavingLabel()
								: getSaveChangesLabel() }
						</Button>
					</div>
				</footer>
			) : null }
			<Modal
				isOpen={ isResetModalOpen }
				onClose={ () => setIsResetModalOpen( false ) }
				title={ __( 'Reset settings', 'airygen-seo' ) }
				maxWidth="max-w-lg"
				bodyClassName="space-y-2"
				footer={
					<div className="flex items-center justify-end gap-3" data-airygen-e2e="modal-reset-settings-actions">
						<Button variant="secondary" onClick={ () => setIsResetModalOpen( false ) } data-airygen-e2e="button-cancel-reset-settings">
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button
							variant="outline"
							onClick={ () => {
								handleReset();
								setIsResetModalOpen( false );
							} }
							data-airygen-e2e="button-confirm-reset-settings"
						>
							{ __( 'Reset', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div data-airygen-e2e="modal-reset-settings">
					<p className="text-sm text-slate-700">
						{ __( 'Reset all settings in this module to default values?', 'airygen-seo' ) }
					</p>
					<p className="mt-2 text-sm text-slate-500">
						{ getResetApplySaveLabel() }
					</p>
				</div>
			</Modal>
		</div>
	);
};

export default SettingsPage;

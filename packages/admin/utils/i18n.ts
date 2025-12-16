import { __, sprintf } from '@wordpress/i18n';

export const getSaveChangesLabel = (): string =>
	__( 'Save Changes', 'airygen-seo' );

export const getSavingLabel = (): string =>
	__( 'Saving…', 'airygen-seo' );

export const getUnsavedChangesLabel = (): string =>
	__( 'Unsaved changes', 'airygen-seo' );

export const getAllChangesSavedLabel = (): string =>
	__( 'All changes saved', 'airygen-seo' );

export const getSavedLabel = (): string =>
	__( 'Saved.', 'airygen-seo' );

export const getUnableToCopySnippetLabel = (): string =>
	__( 'Unable to copy snippet.', 'airygen-seo' );

export const getResetApplySaveLabel = (): string =>
	__( 'After reset, click Save Changes to apply.', 'airygen-seo' );

export const getTemplateFunctionLabel = (): string =>
	__( 'Template function', 'airygen-seo' );

export const getShortcodeLabel = (): string =>
	__( 'Shortcode', 'airygen-seo' );

export const getBlockSnippetLabel = (): string =>
	__( 'Block snippet', 'airygen-seo' );

export const getSnippetCopiedLabel = ( snippetTypeLabel: string ): string =>
	sprintf(
		/* translators: %s is the snippet type, such as Template function, Shortcode, or Block snippet. */
		__( '%s copied.', 'airygen-seo' ),
		snippetTypeLabel,
	);

export const getManualInjectionLabel = ( moduleLabel: string ): string =>
	sprintf(
		/* translators: %s is the module name. */
		__( 'Enable manual %s injection', 'airygen-seo' ),
		moduleLabel,
	);

export const getAutomaticInjectionLabel = ( moduleLabel: string ): string =>
	sprintf(
		/* translators: %s is the module name. */
		__( 'Enable automatic %s injection', 'airygen-seo' ),
		moduleLabel,
	);

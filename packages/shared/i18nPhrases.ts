import { __, sprintf } from '@wordpress/i18n';

export const getLoadingLabel = (): string =>
	__( 'Loading…', 'airygen-seo' );

export const getLoadingAppLabel = ( appLabel: string ): string =>
	sprintf(
		/* translators: %s is the application name. */
		__( 'Loading %s…', 'airygen-seo' ),
		appLabel,
	);

export const getLoadingItemLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the item label being loaded, such as groups or preview. */
		__( 'Loading %s…', 'airygen-seo' ),
		itemLabel,
	);

export const getLoadingRecordsLabel = (): string =>
	__( 'Loading records…', 'airygen-seo' );

export const getUnableToLoadRecordsLabel = (): string =>
	__( 'Unable to load records.', 'airygen-seo' );

export const getAppliedSaveChangesLabel = (): string =>
	__( 'Applied. Save Changes to persist updates.', 'airygen-seo' );

export const getPreviewLoadedLabel = (): string =>
	__( 'Preview loaded.', 'airygen-seo' );

export const getNoMigrationToolsAvailableYetLabel = (): string =>
	__( 'No migration tools available yet.', 'airygen-seo' );

export const getNoSuggestionsYetLabel = (): string =>
	__(
		'No suggestions yet. Save the post so we can index its keywords.',
		'airygen-seo',
	);

export const getNoLongTailKeyphrasesAddedYetLabel = (): string =>
	__( 'No long-tail keyphrases added yet.', 'airygen-seo' );

export const getNoGlobalRobotsDefaultsConfiguredYetLabel = (): string =>
	__( 'No global robots defaults configured yet.', 'airygen-seo' );

export const getNoTopicClusterAssignedYetLabel = (): string =>
	__(
		'No topic cluster assigned yet. Save a level to view the relationships.',
		'airygen-seo',
	);

export const getNoRecordsYetLabel = ( recordTypeLabel: string ): string =>
	sprintf(
		/* translators: %s is the record type label, such as CTR booster or FAQ. */
		__( 'No %s records yet.', 'airygen-seo' ),
		recordTypeLabel,
	);

export const getFocusKeyphraseAiHelpLabel = (): string =>
	__(
		'The focus keyphrase is the main keyword or phrase this post is optimized for. AI modules use it to guide content generation — keeping the output relevant and keyword-focused.',
		'airygen-seo',
	);

export const getNoRecordsGenerateToGetStartedLabel = ( targetLabel: string ): string =>
	sprintf(
		/* translators: %s is the thing to generate first, such as an outline or a draft. */
		__( 'No records yet. Generate %s to get started.', 'airygen-seo' ),
		targetLabel,
	);

export const getNoLogsYetLabel = (): string =>
	__( 'No logs yet.', 'airygen-seo' );

export const getNoModuleSelectedLabel = ( moduleLabel: string ): string =>
	sprintf(
		/* translators: %s is the module label. Example: No notify module selected. */
		__( 'No %s selected.', 'airygen-seo' ),
		moduleLabel,
	);

export const getNoItemsFoundLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the item label. Example: No snapshots found. */
		__( 'No %s found.', 'airygen-seo' ),
		itemLabel,
	);

export const getNoItemsYetLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the item label. Example: No candidates yet. */
		__( 'No %s yet.', 'airygen-seo' ),
		itemLabel,
	);

export const getNoItemsSelectedLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the item label. Example: No posts selected. */
		__( 'No %s selected.', 'airygen-seo' ),
		itemLabel,
	);

export const getNoItemsMatchCurrentFiltersLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the item label. Example: No redirects match the current filters. */
		__( 'No %s match the current filters.', 'airygen-seo' ),
		itemLabel,
	);

export const getGenerateLabel = (): string =>
	__( 'Generate', 'airygen-seo' );

export const getGeneratingLabel = (): string =>
	__( 'Generating…', 'airygen-seo' );

export const getNoItemsAddedYetLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the item label. Example: No redirects added yet. */
		__( 'No %s added yet.', 'airygen-seo' ),
		itemLabel,
	);

export const getNoItemsYetAddToStartLabel = (
	itemLabel: string,
	actionLabel: string,
): string =>
	sprintf(
		/* translators: 1: item label, 2: action label. Example: No selections yet. Add selection to start. */
		__( 'No %1$s yet. Add %2$s to start.', 'airygen-seo' ),
		itemLabel,
		actionLabel,
	);

export const getNoItemsYetAddOneToConfigureLabel = (
	itemLabel: string,
	targetLabel: string,
): string =>
	sprintf(
		/* translators: 1: item label, 2: configurable target label. Example: No branches yet. Add one to configure branch overrides. */
		__( 'No %1$s yet. Add one to configure %2$s.', 'airygen-seo' ),
		itemLabel,
		targetLabel,
	);

export const getNoItemsYetAddBelowLabel = (
	itemLabel: string,
	actionLabel: string,
): string =>
	sprintf(
		/* translators: 1: item label, 2: action label. Example: No special hours rules yet. Add a rule below. */
		__( 'No %1$s yet. Add %2$s below.', 'airygen-seo' ),
		itemLabel,
		actionLabel,
	);

export const getNoItemsYetAddItemsToOverrideLabel = (
	itemLabel: string,
	actionLabel: string,
	targetLabel: string,
): string =>
	sprintf(
		/* translators: 1: item label, 2: action label, 3: override target label. Example: No manual alternates yet. Add languages to override automatic mappings. */
		__( 'No %1$s yet. Add %2$s to override %3$s.', 'airygen-seo' ),
		itemLabel,
		actionLabel,
		targetLabel,
	);

export const getTimedGenerationHintLabel = (
	durationLabel: string,
	driverLabel: string,
): string =>
	sprintf(
		/* translators: 1: estimated duration label, e.g. "~15 seconds". 2: what affects the duration, e.g. "length" or "count". */
		__(
			'This can take up to %1$s depending on %2$s. Please keep this window open.',
			'airygen-seo',
		),
		durationLabel,
		driverLabel,
	);

export const getTimedDraftingHintLabel = (
	durationLabel: string,
	driverLabel: string,
): string =>
	sprintf(
		/* translators: 1: estimated duration label, e.g. "~90 seconds". 2: what affects the duration, e.g. "length". */
		__(
			'Drafting can take up to %1$s depending on %2$s. Please keep this window open.',
			'airygen-seo',
		),
		durationLabel,
		driverLabel,
	);

export const getLongerGenerationHintLabel = ( itemLabel: string ): string =>
	sprintf(
		/* translators: %s is the generated item type in plural form, e.g. "blueprints", "articles", or "drafts". */
		__(
			'Longer %s take more time. Please keep this window open.',
			'airygen-seo',
		),
		itemLabel,
	);

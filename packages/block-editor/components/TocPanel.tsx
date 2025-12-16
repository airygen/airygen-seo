import { __ } from '@wordpress/i18n';
import { Button, SelectControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { getMetaKeys } from '../config';

type OutputModesValue = {
	toc?: string;
	faq?: string;
	topicExpansion?: string;
};

const readOutputModes = ( value: unknown ): OutputModesValue => {
	if ( typeof value !== 'string' || value.trim() === '' ) {
		return {};
	}

	try {
		const parsed = JSON.parse( value ) as OutputModesValue;
		return typeof parsed === 'object' && parsed !== null ? parsed : {};
	} catch {
		return {};
	}
};

const TocPanel = () => {
	const metaKeys = getMetaKeys();
	const outputModesKey = metaKeys.outputModes ?? '_airygen_output_modes';
	const meta = useSelect(
		( select ) => {
			const editor = select( 'core/editor' ) as {
				getEditedPostAttribute?: ( attribute: string ) => unknown;
			};
			return editor.getEditedPostAttribute
				? ( editor.getEditedPostAttribute( 'meta' ) as Record<string, unknown> )
				: {};
		},
		[],
	);
	const { editPost } = useDispatch( 'core/editor' ) as {
		editPost?: ( edits: Record<string, unknown> ) => void;
	};
	const { insertBlocks } = useDispatch( 'core/block-editor' ) as {
		insertBlocks?: ( blocks: unknown[] ) => void;
	};

	const outputModes = readOutputModes( meta?.[ outputModesKey ] );
	const modeValue = outputModes.toc;
	let resolvedMode = 'auto';
	if ( typeof modeValue === 'string' && modeValue !== '' ) {
		resolvedMode = modeValue;
	}

	const handleModeChange = ( value: string ) => {
		if ( ! editPost ) {
			return;
		}

		editPost( {
			meta: {
				...meta,
				[ outputModesKey ]: JSON.stringify( {
					...outputModes,
					toc: value,
				} ),
			},
		} );
	};

	const insertShortcode = () => {
		if ( ! insertBlocks ) {
			return;
		}
		insertBlocks( [ createBlock( 'core/shortcode', { text: '[airygen_toc]' } ) ] );
	};

	const insertTocBlock = () => {
		if ( ! insertBlocks ) {
			return;
		}
		insertBlocks( [ createBlock( 'airygen/toc' ) ] );
	};

	const selectedMode = resolvedMode === 'manual' || resolvedMode === 'disabled'
		? resolvedMode
		: 'auto';

	return (
		<div className="space-y-4">
			<SelectControl
				label={ __( 'TOC display mode', 'airygen-seo' ) }
				value={ selectedMode }
				options={ [
					{ value: 'auto', label: __( 'Auto', 'airygen-seo' ) },
					{ value: 'manual', label: __( 'Manual', 'airygen-seo' ) },
					{ value: 'disabled', label: __( 'Disabled', 'airygen-seo' ) },
				] }
				onChange={ handleModeChange }
				help={ __(
					'Select how TOC should appear for this post.',
					'airygen-seo',
				) }
			/>
			{ selectedMode === 'manual' ? (
				<div className="space-y-2">
					<label
						className="airygen-base-control__label"
						htmlFor="airygen-toc-insert-anchor"
					>
						{ __( 'Insert TOC', 'airygen-seo' ) }
					</label>
					<input
						id="airygen-toc-insert-anchor"
						className="screen-reader-text"
						type="text"
						readOnly
						value=""
						tabIndex={ -1 }
					/>
					<div className="airygen-toc-actions-row">
						<div className="airygen-toc-actions-row__item">
							<Button
								className="airygen-toc-action"
								variant="secondary"
								onClick={ insertTocBlock }
							>
								{ __( 'Block', 'airygen-seo' ) }
							</Button>
						</div>
						<div className="airygen-toc-actions-row__item">
							<Button
								className="airygen-toc-action"
								variant="secondary"
								onClick={ insertShortcode }
							>
								{ __( 'Shortcode', 'airygen-seo' ) }
							</Button>
						</div>
					</div>
				</div>
			) : null }
		</div>
	);
};

export default TocPanel;

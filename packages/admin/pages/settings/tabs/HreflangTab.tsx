import Button from '../../../components/Button';
import Input from '../../../components/Input';
import Toggle from '../../../components/Toggle';
import HeadingIcon from '../../../components/HeadingIcon';
import { HreflangIcon } from '../../../components/Icons';
import { getNoItemsYetAddItemsToOverrideLabel } from '../../../../shared/i18nPhrases';

import { __ } from '@wordpress/i18n';
import type { HreflangEntry, HreflangSettings } from '../../../types/settings';

type HreflangTabProps = {
	settings: HreflangSettings;
	onUpdateEntry: ( index: number, patch: Partial<HreflangEntry> ) => void;
	onRemoveEntry: ( index: number ) => void;
	onAddEntry: () => void;
	onIncludeDefaultChange: ( value: boolean ) => void;
};

const HreflangTab = ( {
	settings,
	onUpdateEntry,
	onRemoveEntry,
	onAddEntry,
	onIncludeDefaultChange,
}: HreflangTabProps ) => (
	<div className="space-y-5">
		<div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<HreflangIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Language Versions', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __(
							'Tell search engines which URL is the right version for each language.',
							'airygen-seo',
						) }
					</div>
				</div>
			</div>
			<Button
				variant="secondary"
				onClick={ onAddEntry }
				className="w-full text-xs md:w-auto"
			>
				{ __( 'Add language', 'airygen-seo' ) }
			</Button>
		</div>
		<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
			<div className="space-y-1">
				<div className="airygen_h2_title">
					{ __( 'Mappings', 'airygen-seo' ) }
				</div>
				<div className="airygen_h1_description">
					{ __(
						'Airygen can use WPML/Polylang automatically, and you can add manual mappings here.',
						'airygen-seo',
					) }
				</div>
			</div>
			<div className="flex flex-col gap-4">
				{ settings.manual_map.length === 0 && (
					<div className="rounded-lg border border-slate-200 bg-white p-4">
						<p className="text-sm italic text-slate-500">
							{ getNoItemsYetAddItemsToOverrideLabel(
								__( 'manual alternates', 'airygen-seo' ),
								__( 'languages', 'airygen-seo' ),
								__( 'automatic mappings', 'airygen-seo' ),
							) }
						</p>
					</div>
				) }
				{ settings.manual_map.map( ( entry, index ) => {
					const isPersisted =
						Boolean( entry.persisted ) &&
						entry.code.trim() !== '' &&
						entry.url.trim() !== '';

					const codeField = isPersisted ? (
						<div>
							<p className="text-xs uppercase tracking-wide text-slate-500">
								{ __( 'Language code', 'airygen-seo' ) }
							</p>
							<p className="mt-1 font-medium text-gray-800">
								{ entry.code }
							</p>
						</div>
					) : (
						<Input
							label={ __( 'Language code', 'airygen-seo' ) }
							value={ entry.code }
							onChange={ ( value ) =>
								onUpdateEntry( index, { code: value } )
							}
							placeholder="en-US"
						/>
					);

					const urlField = isPersisted ? (
						<div>
							<p className="text-xs uppercase tracking-wide text-slate-500">
								{ __( 'Language URL', 'airygen-seo' ) }
							</p>
							<p className="mt-1 break-all font-medium text-gray-800">
								{ entry.url }
							</p>
						</div>
					) : (
						<Input
							label={ __( 'Language URL', 'airygen-seo' ) }
							value={ entry.url }
							onChange={ ( value ) =>
								onUpdateEntry( index, { url: value } )
							}
							placeholder="https://example.com/en/"
							isUrl
						/>
					);

					return (
						<div
							key={ `hreflang-entry-${ index }` }
							className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
						>
							<div className="flex flex-col gap-3 md:flex-row md:items-end md:gap-4">
								<div className="flex-1">{ codeField }</div>
								<div className="flex-1">{ urlField }</div>
								<Button
									variant="danger"
									onClick={ () => onRemoveEntry( index ) }
									className="text-xs"
								>
									{ __( 'Remove', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
					);
				} ) }
			</div>
		</section>
		<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
			<div className="space-y-1">
				<div className="airygen_h2_title">
					{ __( 'Default', 'airygen-seo' ) }
				</div>
				<div className="airygen_h1_description">
					{ __(
						'Set a fallback URL when a visitor’s language isn’t listed above.',
						'airygen-seo',
					) }
				</div>
			</div>
			<div className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3">
				<div className="space-y-1">
					<p className="text-sm font-medium text-slate-800">
						{ __( 'Include x-default', 'airygen-seo' ) }
					</p>
					<p className="text-xs text-slate-500">
						{ __(
							'Use your main language page as the fallback.',
							'airygen-seo',
						) }
					</p>
				</div>
				<Toggle
					label={ __( 'Include x-default', 'airygen-seo' ) }
					hideLabelText
					checked={ settings.include_x_default }
					onChange={ onIncludeDefaultChange }
				/>
			</div>
		</section>
	</div>
);

export default HreflangTab;

import { __, sprintf } from '@wordpress/i18n';
import Input from './Input';
import Select from './Select';
import Toggle from './Toggle';

type SectionHeaderSettingsCardProps = {
	moduleLabel: string;
	enabled: boolean;
	onEnabledChange: ( value: boolean ) => void;
	text: string;
	onTextChange: ( value: string ) => void;
	level: 'h2' | 'h3' | 'h4';
	onLevelChange: ( value: 'h2' | 'h3' | 'h4' ) => void;
	textPlaceholder?: string;
	gridClassName?: string;
};

const SectionHeaderSettingsCard = ( {
	moduleLabel,
	enabled,
	onEnabledChange,
	text,
	onTextChange,
	level,
	onLevelChange,
	textPlaceholder,
	gridClassName = 'md:grid-cols-3',
}: SectionHeaderSettingsCardProps ) => (
	<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
		<div className="flex items-center justify-between gap-3">
			<p className="text-sm font-medium text-slate-900">
				{ __( 'Section header', 'airygen-seo' ) }
			</p>
			<Toggle
				label={ __( 'Section header', 'airygen-seo' ) }
				hideLabelText
				checked={ enabled }
				onChange={ onEnabledChange }
			/>
		</div>
		<p className="text-xs text-slate-500">
			{ sprintf(
				/* translators: %s is the module name. */
				__( 'Control the heading shown above %s output.', 'airygen-seo' ),
				moduleLabel,
			) }
		</p>
		<div className={ `mt-3 grid gap-3 ${ gridClassName }` }>
			<div className="rounded-lg border border-slate-200 p-3">
				<Input
					label={ __( 'Text', 'airygen-seo' ) }
					value={ text }
					onChange={ onTextChange }
					placeholder={ textPlaceholder }
				/>
				<p className="mt-2 text-xs text-slate-500">
					{ sprintf(
						/* translators: %s is the module name. */
						__( 'Set the heading text shown above %s output.', 'airygen-seo' ),
						moduleLabel,
					) }
				</p>
			</div>
			<div className="rounded-lg border border-slate-200 p-3">
				<Select
					label={ __( 'Heading level', 'airygen-seo' ) }
					value={ level }
					options={ [
						{ value: 'h2', label: 'H2' },
						{ value: 'h3', label: 'H3' },
						{ value: 'h4', label: 'H4' },
					] }
					onChange={ ( value ) => onLevelChange( value as 'h2' | 'h3' | 'h4' ) }
				/>
				<p className="mt-2 text-xs text-slate-500">
					{ sprintf(
						/* translators: %s is the module name. */
						__( 'Choose the heading level for the %s title.', 'airygen-seo' ),
						moduleLabel,
					) }
				</p>
			</div>
		</div>
	</div>
);

export default SectionHeaderSettingsCard;

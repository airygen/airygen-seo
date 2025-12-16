import { __ } from '@wordpress/i18n';
import Checkbox from './Checkbox';
import FourDirectionInputGroup from './FourDirectionInputGroup';
import Input from './Input';
import Select from './Select';
import TransparentColorPicker from './TransparentColorPicker';

type DirectionValues = {
	top: number;
	right: number;
	bottom: number;
	left: number;
};

type HeaderContainerValues = {
	borderWidths: DirectionValues;
	borderRadius: number;
	borderStyle: 'solid' | 'dashed' | 'dotted';
	borderColor: string;
	paddings: DirectionValues;
	bgColor: string;
	margins: DirectionValues;
};

type HeaderTitleValues = {
	fontStyle: {
		bold: boolean;
		italic: boolean;
		underline: boolean;
	};
	color: string;
	fontSize: number;
};

type SectionHeaderStyleCardsProps = {
	container: HeaderContainerValues;
	title: HeaderTitleValues;
	onContainerChange: ( patch: Partial<HeaderContainerValues> ) => void;
	onTitleChange: ( patch: Partial<HeaderTitleValues> ) => void;
	idPrefix: string;
	containerMaxBorderWidth?: number;
	containerMaxSpacing?: number;
	containerMaxRadius?: number;
	titleMinFontSize?: number;
	titleMaxFontSize?: number;
};

const SectionHeaderStyleCards = ( {
	container,
	title,
	onContainerChange,
	onTitleChange,
	idPrefix,
	containerMaxBorderWidth = 20,
	containerMaxSpacing = 50,
	containerMaxRadius = 50,
	titleMinFontSize = 10,
	titleMaxFontSize = 40,
}: SectionHeaderStyleCardsProps ) => (
	<>
		<div className="space-y-3 rounded-lg border border-slate-200 p-4">
			<h3 className="text-sm font-semibold text-slate-900">{ __( 'Section header container', 'airygen-seo' ) }</h3>
			<div className="grid gap-3 md:grid-cols-4">
				<div className="rounded-lg border border-slate-200 p-3">
					<TransparentColorPicker
						label={ __( 'Background color', 'airygen-seo' ) }
						value={ container.bgColor }
						onChange={ ( value ) => onContainerChange( { bgColor: value } ) }
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<TransparentColorPicker
						label={ __( 'Border color', 'airygen-seo' ) }
						value={ container.borderColor }
						onChange={ ( value ) => onContainerChange( { borderColor: value } ) }
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<Select
						label={ __( 'Border style', 'airygen-seo' ) }
						value={ container.borderStyle }
						options={ [
							{ value: 'solid', label: __( 'Solid', 'airygen-seo' ) },
							{ value: 'dashed', label: __( 'Dashed', 'airygen-seo' ) },
							{ value: 'dotted', label: __( 'Dotted', 'airygen-seo' ) },
						] }
						onChange={ ( value ) =>
							onContainerChange( { borderStyle: value as HeaderContainerValues['borderStyle'] } )
						}
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<Input
						label={ __( 'Border radius', 'airygen-seo' ) + ' (px)' }
						type="number"
						min={ 0 }
						max={ containerMaxRadius }
						value={ String( container.borderRadius ) }
						onChange={ ( value ) =>
							onContainerChange( {
								borderRadius: Math.max( 0, Math.min( containerMaxRadius, Number( value ) || 0 ) ),
							} )
						}
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Padding', 'airygen-seo' ) + ' (px)' }
					</p>
					<FourDirectionInputGroup
						idPrefix={ `${ idPrefix }-padding` }
						values={ container.paddings }
						max={ containerMaxSpacing }
						onChange={ ( values ) => onContainerChange( { paddings: values } ) }
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Margin', 'airygen-seo' ) + ' (px)' }
					</p>
					<FourDirectionInputGroup
						idPrefix={ `${ idPrefix }-margin` }
						values={ container.margins }
						max={ containerMaxSpacing }
						onChange={ ( values ) => onContainerChange( { margins: values } ) }
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<p className="text-sm font-medium text-gray-800">
						{ __( 'Border width', 'airygen-seo' ) + ' (px)' }
					</p>
					<FourDirectionInputGroup
						idPrefix={ `${ idPrefix }-border-width` }
						values={ container.borderWidths }
						max={ containerMaxBorderWidth }
						onChange={ ( values ) => onContainerChange( { borderWidths: values } ) }
					/>
				</div>
			</div>
		</div>
		<div className="space-y-3 rounded-lg border border-slate-200 p-4">
			<h3 className="text-sm font-semibold text-slate-900">{ __( 'Section title', 'airygen-seo' ) }</h3>
			<div className="grid gap-3 md:grid-cols-4">
				<div className="rounded-lg border border-slate-200 p-3">
					<p className="text-sm font-medium text-slate-900">{ __( 'Font style', 'airygen-seo' ) }</p>
					<div className="mt-3 grid grid-cols-3 gap-2">
						<Checkbox
							label={ __( 'Bold', 'airygen-seo' ) }
							checked={ title.fontStyle.bold }
							onChange={ ( value ) =>
								onTitleChange( { fontStyle: { ...title.fontStyle, bold: value } } )
							}
						/>
						<Checkbox
							label={ __( 'Italic', 'airygen-seo' ) }
							checked={ title.fontStyle.italic }
							onChange={ ( value ) =>
								onTitleChange( { fontStyle: { ...title.fontStyle, italic: value } } )
							}
						/>
						<Checkbox
							label={ __( 'Underline', 'airygen-seo' ) }
							checked={ title.fontStyle.underline }
							onChange={ ( value ) =>
								onTitleChange( { fontStyle: { ...title.fontStyle, underline: value } } )
							}
						/>
					</div>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<TransparentColorPicker
						label={ __( 'Color', 'airygen-seo' ) }
						value={ title.color }
						onChange={ ( value ) => onTitleChange( { color: value } ) }
					/>
				</div>
				<div className="rounded-lg border border-slate-200 p-3">
					<Input
						label={ __( 'Font size', 'airygen-seo' ) + ' (px)' }
						type="number"
						min={ titleMinFontSize }
						max={ titleMaxFontSize }
						value={ String( title.fontSize ) }
						onChange={ ( value ) =>
							onTitleChange( {
								fontSize: Math.max( titleMinFontSize, Math.min( titleMaxFontSize, Number( value ) || 0 ) ),
							} )
						}
					/>
				</div>
			</div>
		</div>
	</>
);

export type { HeaderContainerValues, HeaderTitleValues };
export default SectionHeaderStyleCards;

import { __ } from '@wordpress/i18n';
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

type BorderStyle = 'solid' | 'dashed' | 'dotted';

type SectionBodyContainerStyleCardProps = {
	title?: string;
	borderWidths: DirectionValues;
	onBorderWidthsChange: ( value: DirectionValues ) => void;
	borderRadius: number;
	onBorderRadiusChange: ( value: number ) => void;
	borderStyle: BorderStyle;
	onBorderStyleChange: ( value: BorderStyle ) => void;
	borderColor: string;
	onBorderColorChange: ( value: string ) => void;
	paddings: DirectionValues;
	onPaddingsChange: ( value: DirectionValues ) => void;
	margins?: DirectionValues;
	onMarginsChange?: ( value: DirectionValues ) => void;
	bgColor: string;
	onBgColorChange: ( value: string ) => void;
	gap?: number;
	onGapChange?: ( value: number ) => void;
	idPrefix: string;
	maxBorderWidth?: number;
	maxSpacing?: number;
	maxRadius?: number;
	maxGap?: number;
	showMargins?: boolean;
	gapLabel?: string;
	gapDescription?: string;
};

const SectionBodyContainerStyleCard = ( {
	title,
	borderWidths,
	onBorderWidthsChange,
	borderRadius,
	onBorderRadiusChange,
	borderStyle,
	onBorderStyleChange,
	borderColor,
	onBorderColorChange,
	paddings,
	onPaddingsChange,
	margins,
	onMarginsChange,
	bgColor,
	onBgColorChange,
	gap,
	onGapChange,
	idPrefix,
	maxBorderWidth = 20,
	maxSpacing = 50,
	maxRadius = 50,
	maxGap = 64,
	showMargins = true,
	gapLabel,
	gapDescription,
}: SectionBodyContainerStyleCardProps ) => {
	return (
		<div className="rounded-lg border border-slate-200 p-4">
			<h3 className="text-sm font-medium text-slate-900">
				{ title ?? __( 'Section body container', 'airygen-seo' ) }
			</h3>
			<div className="mt-3 grid gap-4 md:grid-cols-4">
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<TransparentColorPicker
						label={ __( 'Background color', 'airygen-seo' ) }
						value={ bgColor }
						onChange={ onBgColorChange }
					/>
					<p className="mt-2 text-xs text-slate-500">
						{ __( 'Background color for the container area.', 'airygen-seo' ) }
					</p>
				</div>
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<TransparentColorPicker
						label={ __( 'Border color', 'airygen-seo' ) }
						value={ borderColor }
						onChange={ onBorderColorChange }
					/>
					<p className="mt-2 text-xs text-slate-500">
						{ __( 'Sets the border color for the container.', 'airygen-seo' ) }
					</p>
				</div>
				<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
					<Select
						label={ __( 'Border style', 'airygen-seo' ) }
						value={ borderStyle }
						options={ [
							{ value: 'solid', label: __( 'Solid', 'airygen-seo' ) },
							{ value: 'dashed', label: __( 'Dashed', 'airygen-seo' ) },
							{ value: 'dotted', label: __( 'Dotted', 'airygen-seo' ) },
						] }
						onChange={ ( value ) => onBorderStyleChange( value as BorderStyle ) }
					/>
					<p className="mt-2 text-xs text-slate-500">
						{ __( 'Choose the line style for the container border.', 'airygen-seo' ) }
					</p>
				</div>
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<Input
						label={ `${ __( 'Border radius', 'airygen-seo' ) } (px)` }
						type="number"
						min={ 0 }
						max={ maxRadius }
						value={ String( borderRadius ) }
						onChange={ ( value ) =>
							onBorderRadiusChange(
								Math.max( 0, Math.min( maxRadius, Number( value ) || 0 ) ),
							)
						}
					/>
					<p className="mt-2 text-xs text-slate-500">
						{ __( 'Controls how rounded the container corners appear.', 'airygen-seo' ) }
					</p>
				</div>
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<p className="text-sm font-medium text-gray-800">
						{ `${ __( 'Padding', 'airygen-seo' ) } (px)` }
					</p>
					<FourDirectionInputGroup
						idPrefix={ `${ idPrefix }-padding` }
						values={ paddings }
						max={ maxSpacing }
						onChange={ onPaddingsChange }
					/>
					<p className="mt-2 text-xs text-slate-500">
						{ __( 'Padding inside the container.', 'airygen-seo' ) }
					</p>
				</div>
				{ showMargins && margins && onMarginsChange ? (
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<p className="text-sm font-medium text-gray-800">
							{ `${ __( 'Margin', 'airygen-seo' ) } (px)` }
						</p>
						<FourDirectionInputGroup
							idPrefix={ `${ idPrefix }-margin` }
							values={ margins }
							max={ maxSpacing }
							onChange={ onMarginsChange }
						/>
						<p className="mt-2 text-xs text-slate-500">
							{ __( 'Space outside the container.', 'airygen-seo' ) }
						</p>
					</div>
				) : null }
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<p className="text-sm font-medium text-gray-800">
						{ `${ __( 'Border width', 'airygen-seo' ) } (px)` }
					</p>
					<FourDirectionInputGroup
						idPrefix={ `${ idPrefix }-border-width` }
						values={ borderWidths }
						max={ maxBorderWidth }
						onChange={ onBorderWidthsChange }
					/>
					<p className="mt-2 text-xs text-slate-500">
						{ __( 'Adjust the thickness of the container border.', 'airygen-seo' ) }
					</p>
				</div>
				{ typeof gap !== 'undefined' && onGapChange ? (
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ gapLabel ?? `${ __( 'Gap', 'airygen-seo' ) } (px)` }
							type="number"
							min={ 0 }
							max={ maxGap }
							value={ String( gap ) }
							onChange={ ( value ) =>
								onGapChange( Math.max( 0, Math.min( maxGap, Number( value ) || 0 ) ) )
							}
						/>
						{ gapDescription ? <p className="mt-2 text-xs text-slate-500">{ gapDescription }</p> : null }
					</div>
				) : null }
			</div>
		</div>
	);
};

export default SectionBodyContainerStyleCard;

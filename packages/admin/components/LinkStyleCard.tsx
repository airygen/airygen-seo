import { __ } from '@wordpress/i18n';
import Checkbox from './Checkbox';
import Input from './Input';
import TransparentColorPicker from './TransparentColorPicker';

type FontStyleValue = {
	bold: boolean;
	italic: boolean;
	underline: boolean;
};

type LinkStyleCardProps = {
	title?: string;
	fontStyle: FontStyleValue;
	onFontStyleChange: ( value: FontStyleValue ) => void;
	color: string;
	onColorChange: ( value: string ) => void;
	fontSize: number;
	onFontSizeChange: ( value: number ) => void;
	fontSizeMin?: number;
	fontSizeMax?: number;
	fontStyleDescription?: string;
	colorDescription?: string;
	fontSizeDescription?: string;
	fontStyleCardClassName?: string;
	fontSizeLabel?: string;
};

const LinkStyleCard = ( {
	title,
	fontStyle,
	onFontStyleChange,
	color,
	onColorChange,
	fontSize,
	onFontSizeChange,
	fontSizeMin = 10,
	fontSizeMax = 40,
	fontStyleDescription,
	colorDescription,
	fontSizeDescription,
	fontStyleCardClassName = '',
	fontSizeLabel,
}: LinkStyleCardProps ) => (
	<div className="rounded-lg border border-slate-200 p-4">
		<h3 className="text-sm font-medium text-slate-900">{ title ?? __( 'Link', 'airygen-seo' ) }</h3>
		<div className="mt-3 grid gap-4 md:grid-cols-4">
			<div className={ `airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 ${ fontStyleCardClassName }`.trim() }>
				<h4 className="text-sm font-medium text-slate-900">{ __( 'Font style', 'airygen-seo' ) }</h4>
				{ fontStyleDescription ? <p className="mt-2 text-xs text-slate-500">{ fontStyleDescription }</p> : null }
				<div className="mt-3 grid gap-3 md:grid-cols-3">
					<Checkbox
						label={ __( 'Bold', 'airygen-seo' ) }
						checked={ fontStyle.bold }
						onChange={ ( value ) => onFontStyleChange( { ...fontStyle, bold: value } ) }
					/>
					<Checkbox
						label={ __( 'Italic', 'airygen-seo' ) }
						checked={ fontStyle.italic }
						onChange={ ( value ) => onFontStyleChange( { ...fontStyle, italic: value } ) }
					/>
					<Checkbox
						label={ __( 'Underline', 'airygen-seo' ) }
						checked={ fontStyle.underline }
						onChange={ ( value ) => onFontStyleChange( { ...fontStyle, underline: value } ) }
					/>
				</div>
			</div>
			<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
				<TransparentColorPicker
					label={ __( 'Color', 'airygen-seo' ) }
					value={ color }
					onChange={ onColorChange }
				/>
				{ colorDescription ? <p className="mt-2 text-xs text-slate-500">{ colorDescription }</p> : null }
			</div>
			<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
				<Input
					label={ fontSizeLabel ?? `${ __( 'Font size', 'airygen-seo' ) } (px)` }
					type="number"
					min={ fontSizeMin }
					max={ fontSizeMax }
					value={ String( fontSize ) }
					onChange={ ( value ) =>
						onFontSizeChange( Math.max( fontSizeMin, Math.min( fontSizeMax, Number( value ) || 0 ) ) )
					}
				/>
				{ fontSizeDescription ? <p className="mt-2 text-xs text-slate-500">{ fontSizeDescription }</p> : null }
			</div>
		</div>
	</div>
);

export type { FontStyleValue };
export default LinkStyleCard;

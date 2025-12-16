import { __ } from '@wordpress/i18n';
import Input from './Input';
import Select from './Select';

type ListStyleValue = 'none' | 'disc' | 'decimal';

type ListStyleCardProps = {
	title?: string;
	listStyle?: ListStyleValue;
	onListStyleChange?: ( value: ListStyleValue ) => void;
	listStyleDescription?: string;
	gap?: number;
	onGapChange?: ( value: number ) => void;
	gapDescription?: string;
	gapMin?: number;
	gapMax?: number;
	indent?: number;
	onIndentChange?: ( value: number ) => void;
	indentDescription?: string;
	indentMin?: number;
	indentMax?: number;
	indentLabel?: string;
	idPrefix: string;
};

const ListStyleCard = ( {
	title,
	listStyle,
	onListStyleChange,
	listStyleDescription,
	gap,
	onGapChange,
	gapDescription,
	gapMin = 0,
	gapMax = 20,
	indent,
	onIndentChange,
	indentDescription,
	indentMin = 0,
	indentMax = 48,
	indentLabel,
	idPrefix,
}: ListStyleCardProps ) => (
	<div className="rounded-lg border border-slate-200 p-4">
		<h3 className="text-sm font-medium text-slate-900">{ title ?? __( 'List', 'airygen-seo' ) }</h3>
		<div className="mt-3 grid gap-4 md:grid-cols-4">
			{ typeof listStyle !== 'undefined' && onListStyleChange ? (
				<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
					<Select
						label={ __( 'Style', 'airygen-seo' ) }
						value={ listStyle }
						options={ [
							{ value: 'none', label: __( 'Plain text', 'airygen-seo' ) },
							{ value: 'disc', label: __( 'Bullet list', 'airygen-seo' ) },
							{ value: 'decimal', label: __( 'Number list', 'airygen-seo' ) },
						] }
						onChange={ ( value ) => onListStyleChange( value as ListStyleValue ) }
					/>
					{ listStyleDescription ? <p className="mt-2 text-xs text-slate-500">{ listStyleDescription }</p> : null }
				</div>
			) : null }
			{ typeof gap !== 'undefined' && onGapChange ? (
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<Input
						label={ `${ __( 'Gap', 'airygen-seo' ) } (px)` }
						type="number"
						min={ gapMin }
						max={ gapMax }
						value={ String( gap ) }
						onChange={ ( value ) =>
							onGapChange( Math.max( gapMin, Math.min( gapMax, Number( value ) || 0 ) ) )
						}
						id={ `${ idPrefix }-gap` }
					/>
					{ gapDescription ? <p className="mt-2 text-xs text-slate-500">{ gapDescription }</p> : null }
				</div>
			) : null }
			{ typeof indent !== 'undefined' && onIndentChange ? (
				<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
					<Input
						label={ indentLabel ?? `${ __( 'Indent', 'airygen-seo' ) } (px)` }
						type="number"
						min={ indentMin }
						max={ indentMax }
						value={ String( indent ) }
						onChange={ ( value ) =>
							onIndentChange( Math.max( indentMin, Math.min( indentMax, Number( value ) || 0 ) ) )
						}
						id={ `${ idPrefix }-indent` }
					/>
					{ indentDescription ? <p className="mt-2 text-xs text-slate-500">{ indentDescription }</p> : null }
				</div>
			) : null }
		</div>
	</div>
);

export default ListStyleCard;

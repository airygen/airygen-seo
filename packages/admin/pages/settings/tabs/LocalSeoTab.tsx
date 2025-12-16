import HeadingIcon from '../../../components/HeadingIcon';
import Input from '../../../components/Input';
import Toggle from '../../../components/Toggle';
import Button from '../../../components/Button';
import Modal from '../../../components/Modal';
import PreviewDeviceSwitcher from '../../../components/PreviewDeviceSwitcher';
import PreviewDeviceFrame, {
	type PreviewDeviceKind,
} from '../../../components/PreviewDeviceFrame';
import PreviewCodeSamples from '../../../components/PreviewCodeSamples';
import { LocalSeoIcon } from '../../../components/Icons';
import TransparentColorPicker from '../../../components/TransparentColorPicker';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import type { LocalSeoSettings } from '../../../types/settings';
import type { DragEvent } from 'react';
import {
	getNoItemsYetAddBelowLabel,
	getNoItemsYetAddOneToConfigureLabel,
} from '../../../../shared/i18nPhrases';

type LocalSeoTabProps = {
	settings: LocalSeoSettings;
	onChange: ( next: LocalSeoSettings ) => void;
};
type LocalSeoBranch = LocalSeoSettings['branches'][ number ];
type LocalSeoLayoutTemplate = LocalSeoSettings['layoutTemplate'];
type LocalSeoLayoutDefinition = ( typeof LOCAL_SEO_LAYOUT_BLOCKS )[ number ];
type ContactPreviewBlockKind = 'text' | 'list' | 'image' | 'map';
type ContactPreviewBlock = {
	blockId: string;
	label: string;
	kind: ContactPreviewBlockKind;
	row: number;
	col: number;
	span: number;
	displayRow: number;
	displayRowSpan: number;
	displayCol: number;
	displayColSpan: number;
	text?: string;
	lines?: string[];
	url?: string;
	alt?: string;
	html: string;
};

const LOCAL_SEO_LAYOUT_BLOCKS = [
	{
		id: 'business_name',
		label: __( 'Business name', 'airygen-seo' ),
		description: __( 'Business title text in the Local SEO output card.', 'airygen-seo' ),
	},
	{
		id: 'legal_name',
		label: __( 'Legal name', 'airygen-seo' ),
		description: __( 'Registered legal entity name line.', 'airygen-seo' ),
	},
	{
		id: 'address',
		label: __( 'Address', 'airygen-seo' ),
		description: __( 'Combined street/city/region/postal/country line.', 'airygen-seo' ),
	},
	{
		id: 'phone',
		label: __( 'Phone', 'airygen-seo' ),
		description: __( 'Primary phone number line in the output card.', 'airygen-seo' ),
	},
	{
		id: 'map',
		label: __( 'Map', 'airygen-seo' ),
		description: __( 'Embedded map block controlled by map settings.', 'airygen-seo' ),
	},
	{
		id: 'image_url',
		label: __( 'Image', 'airygen-seo' ),
		description: __( 'Business image URL rendered in content.', 'airygen-seo' ),
	},
	{
		id: 'logo_url',
		label: __( 'Logo', 'airygen-seo' ),
		description: __( 'Business logo image rendered in content.', 'airygen-seo' ),
	},
	{
		id: 'vat_id',
		label: __( 'Tax ID', 'airygen-seo' ),
		description: __( 'Displays configured Tax ID.', 'airygen-seo' ),
	},
	{
		id: 'pricing',
		label: __( 'Pricing', 'airygen-seo' ),
		description: __( 'Displays resolved price range text.', 'airygen-seo' ),
	},
	{
		id: 'service_areas',
		label: __( 'Service Areas', 'airygen-seo' ),
		description: __( 'Displays served cities, postal codes, and radius.', 'airygen-seo' ),
	},
	{
		id: 'service_catalog',
		label: __( 'Service Catalog', 'airygen-seo' ),
		description: __( 'Displays configured service catalog items.', 'airygen-seo' ),
	},
	{
		id: 'opening_hours',
		label: __( 'Opening Hours', 'airygen-seo' ),
		description: __( 'Displays opening hours lines.', 'airygen-seo' ),
	},
	{
		id: 'special_hours',
		label: __( 'Special Hours', 'airygen-seo' ),
		description: __( 'Displays special-hours lines.', 'airygen-seo' ),
	},
] as const;
const FOOTER_NAP_LAYOUT_BLOCKS = [
	{
		id: 'business_name',
		label: __( 'Business name', 'airygen-seo' ),
		description: __( 'Display the business name line.', 'airygen-seo' ),
	},
	{
		id: 'legal_name',
		label: __( 'Legal name', 'airygen-seo' ),
		description: __( 'Display the legal entity name line.', 'airygen-seo' ),
	},
	{
		id: 'phone',
		label: __( 'Tel', 'airygen-seo' ),
		description: __( 'Display the telephone line.', 'airygen-seo' ),
	},
	{
		id: 'address',
		label: __( 'Address', 'airygen-seo' ),
		description: __( 'Display the full address line.', 'airygen-seo' ),
	},
	{
		id: 'tax_id',
		label: __( 'Tax ID', 'airygen-seo' ),
		description: __( 'Display the Tax ID line.', 'airygen-seo' ),
	},
] as const;
type FooterNapLayoutBlockId = ( typeof FOOTER_NAP_LAYOUT_BLOCKS )[ number ]['id'];
const FOOTER_NAP_DEFAULT_LAYOUT_ORDER: FooterNapLayoutBlockId[] = [
	'business_name',
	'phone',
	'address',
];
const LOCAL_SEO_LAYOUT_ROWS = 15;
const LOCAL_SEO_LAYOUT_COLS = 5;
const LOCAL_SEO_LAYOUT_TEMPLATE_OPTIONS: Array<{
	value: LocalSeoLayoutTemplate;
	label: string;
}> = [
	{
		value: 'sidebar_left',
		label: __( 'Sidebar Left (2) + Main Content Right (3)', 'airygen-seo' ),
	},
	{
		value: 'sidebar_right',
		label: __( 'Main Content Left (3) + Sidebar Right (2)', 'airygen-seo' ),
	},
	{
		value: 'sidebar_left_header',
		label: __( 'Header (5) + Sidebar Left (2) + Main Content Right (3)', 'airygen-seo' ),
	},
	{
		value: 'sidebar_right_header',
		label: __( 'Header (5) + Main Content Left (3) + Sidebar Right (2)', 'airygen-seo' ),
	},
];
const templateHasHeader = ( template: LocalSeoLayoutTemplate ) =>
	template === 'sidebar_left_header' || template === 'sidebar_right_header';
const isSidebarLeftTemplate = ( template: LocalSeoLayoutTemplate ) =>
	template === 'sidebar_left' || template === 'sidebar_left_header';
const resolveLayoutColumns = ( template: LocalSeoLayoutTemplate ) =>
	isSidebarLeftTemplate( template )
		? {
			sidebarStart: 1,
			sidebarSpan: 2,
			mainStart: 3,
			mainSpan: 3,
		}
		: {
			mainStart: 1,
			mainSpan: 3,
			sidebarStart: 4,
			sidebarSpan: 2,
		};
const getPreferredLayoutRows = ( maxOccupiedRow: number ) =>
	Math.max( 7, Math.min( LOCAL_SEO_LAYOUT_ROWS, maxOccupiedRow + 1 ) );

const LOCAL_BUSINESS_TYPES = [
	'LocalBusiness',
	'Organization',
	'Store',
	'ProfessionalService',
	'HomeAndConstructionBusiness',
	'Electrician',
	'Plumber',
	'GeneralContractor',
	'RoofingContractor',
	'HVACBusiness',
	'Locksmith',
	'HousePainter',
	'Carpenter',
	'AutoRepair',
	'HealthAndBeautyBusiness',
	'BeautySalon',
	'NailSalon',
	'Barbershop',
	'MedicalBusiness',
	'Restaurant',
	'FastFoodRestaurant',
	'CafeOrCoffeeShop',
	'Bakery',
	'Dentist',
	'MedicalClinic',
	'Physician',
	'Pediatric',
	'CommunityHealth',
	'Hospital',
	'Pharmacy',
	'LegalService',
	'RealEstateAgent',
] as const;

const OPENING_HOURS_STEP_MINUTES = 10;
const OPENING_HOURS_MAX_MINUTES = ( 24 * 60 ) - OPENING_HOURS_STEP_MINUTES;
const DAY_CODES = [ 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ] as const;
type DayCode = ( typeof DAY_CODES )[ number ];

type DaySchedule = {
	enabled: boolean;
	openMinutes: number;
	closeMinutes: number;
	is24Hours: boolean;
};
type SpecialHoursRule = {
	startDate: string;
	endDate: string;
	isClosed: boolean;
	opens: string;
	closes: string;
};

const createDefaultOpeningHoursSchedule = (): Record<DayCode, DaySchedule> =>
	DAY_CODES.reduce(
		( acc, dayCode ) => {
			acc[ dayCode ] = {
				enabled: false,
				openMinutes: 9 * 60,
				closeMinutes: 18 * 60,
				is24Hours: false,
			};
			return acc;
		},
		{} as Record<DayCode, DaySchedule>,
	);

const clampOpeningHourMinutes = ( minutes: number ): number => {
	const snapped = Math.round( minutes / OPENING_HOURS_STEP_MINUTES ) * OPENING_HOURS_STEP_MINUTES;
	return Math.min( OPENING_HOURS_MAX_MINUTES, Math.max( 0, snapped ) );
};

const toOpeningHourMinutes = ( value: string ): number | null => {
	const match = value.match( /^([0-9]{2}):([0-9]{2})$/ );
	if ( ! match ) {
		return null;
	}

	const hours = Number( match[ 1 ] );
	const mins = Number( match[ 2 ] );
	if (
		! Number.isFinite( hours ) ||
		! Number.isFinite( mins ) ||
		hours < 0 ||
		hours > 23 ||
		mins < 0 ||
		mins > 59
	) {
		return null;
	}

	return clampOpeningHourMinutes( ( hours * 60 ) + mins );
};

const toOpeningHourString = ( totalMinutes: number ): string => {
	const minutes = clampOpeningHourMinutes( totalMinutes );
	const hoursPart = String( Math.floor( minutes / 60 ) ).padStart( 2, '0' );
	const minsPart = String( minutes % 60 ).padStart( 2, '0' );
	return `${ hoursPart }:${ minsPart }`;
};

const parseOpeningHoursByDay = ( raw: string ): Record<DayCode, DaySchedule> => {
	const schedule = createDefaultOpeningHoursSchedule();
	const lines = raw
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => '' !== line );

	lines.forEach( ( line ) => {
		const normalizedLine = line.replace( /[\u2012\u2013\u2014\u2212]/g, '-' );
		const match = normalizedLine.match(
			/^([A-Za-z]{2})(?:-([A-Za-z]{2}))?\s+([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})$/,
		);
		if ( ! match ) {
			return;
		}

		const from = match[ 1 ] as DayCode;
		const to = ( match[ 2 ] ?? '' ) as DayCode | '';
		const opens = toOpeningHourMinutes( match[ 3 ] ?? '' );
		const closes = toOpeningHourMinutes( match[ 4 ] ?? '' );
		if ( null === opens || null === closes ) {
			return;
		}
		const is24Hours = '00:00' === ( match[ 3 ] ?? '' ) && '23:59' === ( match[ 4 ] ?? '' );

		let openMinutes = opens;
		let closeMinutes = closes;
		if ( is24Hours ) {
			openMinutes = 0;
			closeMinutes = OPENING_HOURS_MAX_MINUTES;
		} else if ( closeMinutes <= openMinutes ) {
			closeMinutes = Math.min( OPENING_HOURS_MAX_MINUTES, openMinutes + OPENING_HOURS_STEP_MINUTES );
		}

		const fromIndex = DAY_CODES.indexOf( from );
		if ( -1 === fromIndex ) {
			return;
		}

		const toIndex = '' !== to ? DAY_CODES.indexOf( to ) : fromIndex;
		if ( -1 === toIndex || toIndex < fromIndex ) {
			return;
		}

		for ( let index = fromIndex; index <= toIndex; index += 1 ) {
			const dayCode = DAY_CODES[ index ];
			schedule[ dayCode ] = {
				enabled: true,
				openMinutes,
				closeMinutes,
				is24Hours,
			};
		}
	} );

	return schedule;
};

const serializeOpeningHoursByDay = ( schedule: Record<DayCode, DaySchedule> ): string =>
	DAY_CODES.filter( ( dayCode ) => schedule[ dayCode ].enabled )
		.map( ( dayCode ) => {
			if ( schedule[ dayCode ].is24Hours ) {
				return `${ dayCode } 00:00-23:59`;
			}
			return `${ dayCode } ${ toOpeningHourString( schedule[ dayCode ].openMinutes ) }-${ toOpeningHourString( schedule[ dayCode ].closeMinutes ) }`;
		} )
		.join( '\n' );

const parseLineItems = ( value: string ): string[] =>
	value
		.split( /\r\n|\r|\n/ )
		.map( ( item ) => item.trim() )
		.filter( ( item ) => item !== '' );
const parseTextareaLineItems = ( value: string ): string[] => {
	const lines = value.split( /\r\n|\r|\n/ ).map( ( item ) => item.trim() );
	return lines.filter( ( item, index ) => item !== '' || index === lines.length - 1 );
};
const formatOpeningHoursLineForDisplay = ( line: string ): string => {
	const trimmed = line.trim();
	const rangeMatch = trimmed.match(
		/^([A-Za-z]{2}(?:-[A-Za-z]{2})?)\s+00:00-23:59$/,
	);
	if ( rangeMatch && rangeMatch[ 1 ] ) {
		return `${ rangeMatch[ 1 ] } Open 24 hours`;
	}
	if ( trimmed === '00:00-23:59' ) {
		return 'Open 24 hours';
	}
	return trimmed;
};
const formatSpecialHoursLineForDisplay = ( line: string ): string => {
	const normalized = normalizeSpecialHoursLine( line );
	const parts = normalized.split( '|', 2 );
	if ( parts.length < 2 || ! parts[ 0 ] || ! parts[ 1 ] ) {
		return normalized;
	}
	return `${ parts[ 0 ].trim() } | ${ parts[ 1 ].trim() }`;
};
const sanitizeCountryCodeInput = ( value: string ): string =>
	value
		.toUpperCase()
		.replace( /[^A-Z]/g, '' )
		.slice( 0, 2 );
const COUNTRY_CODE_LABEL_MAP: Record<string, string> = {
	US: 'United States',
	GB: 'United Kingdom',
	KR: 'South Korea',
	TW: 'Taiwan',
	HK: 'Hong Kong',
	AE: 'United Arab Emirates',
	VE: 'Venezuela',
	TZ: 'Tanzania',
	LA: 'Laos',
	PS: 'Palestine',
	BO: 'Bolivia',
	MD: 'Moldova',
	SY: 'Syria',
	CI: "Cote d'Ivoire",
};

const serializeLineItems = ( values: string[] ): string => values.join( '\n' );
const escapeHtml = ( value: string ): string =>
	value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' );

const normalizeSpecialHoursLine = ( line: string ): string =>
	line
		.replace( /[｜∣︱│]/g, '|' )
		.replace( /[\u2012\u2013\u2014\u2212]/g, '-' )
		.trim();

const parseSpecialHoursRules = ( value: string ): SpecialHoursRule[] =>
	value
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => normalizeSpecialHoursLine( line ) )
		.filter( ( line ) => line !== '' )
		.map( ( line ) => {
			const [ rawDate, rawTime ] = line.split( '|', 2 );
			if ( ! rawDate || ! rawTime ) {
				return null;
			}

			const dateMatch = rawDate
				.trim()
				.match(
					/^([0-9]{4}-[0-9]{2}-[0-9]{2})(?:\s+to\s+([0-9]{4}-[0-9]{2}-[0-9]{2}))?$/i,
				);
			if ( ! dateMatch ) {
				return null;
			}

			const startDate = dateMatch[ 1 ] ?? '';
			const endDate = dateMatch[ 2 ] ?? startDate;
			const time = rawTime.trim().toLowerCase();

			if ( time === 'closed' ) {
				return {
					startDate,
					endDate,
					isClosed: true,
					opens: '00:00',
					closes: '00:00',
				};
			}

			const timeMatch = time.match( /^([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})$/ );
			if ( ! timeMatch ) {
				return null;
			}

			return {
				startDate,
				endDate,
				isClosed: false,
				opens: timeMatch[ 1 ] ?? '09:00',
				closes: timeMatch[ 2 ] ?? '18:00',
			};
		} )
		.filter( ( item ): item is SpecialHoursRule => null !== item );

const serializeSpecialHoursRules = ( rules: SpecialHoursRule[] ): string =>
	rules
		.map( ( rule ) => {
			const startDate = rule.startDate.trim();
			if ( '' === startDate ) {
				return '';
			}
			const endDate = rule.endDate.trim();
			const datePart =
				'' !== endDate && endDate !== startDate
					? `${ startDate } to ${ endDate }`
					: startDate;
			if ( rule.isClosed ) {
				return `${ datePart } | closed`;
			}
			return `${ datePart } | ${ rule.opens }-${ rule.closes }`;
		} )
		.filter( ( line ) => line !== '' )
		.join( '\n' );

const createDefaultSpecialHoursRule = (): SpecialHoursRule => {
	const today = new Date().toISOString().slice( 0, 10 );
	return {
		startDate: today,
		endDate: today,
		isClosed: false,
		opens: '09:00',
		closes: '18:00',
	};
};

const parseSpecialHoursForPreview = (
	value: string,
): Array<Record<string, string>> => {
	return parseSpecialHoursRules( value ).map( ( rule ) => ( {
		'@type': 'OpeningHoursSpecification',
		validFrom: rule.startDate,
		validThrough: rule.endDate || rule.startDate,
		opens: rule.isClosed ? '00:00' : rule.opens,
		closes: rule.isClosed ? '00:00' : rule.closes,
	} ) );
};

type SpecialHoursRulesEditorProps = {
	value: string;
	onChange: ( next: string ) => void;
	inputIdPrefix: string;
};

const SpecialHoursRulesEditor = ( {
	value,
	onChange,
	inputIdPrefix,
}: SpecialHoursRulesEditorProps ) => {
	const rules = useMemo( () => parseSpecialHoursRules( value ), [ value ] );
	const setRules = ( nextRules: SpecialHoursRule[] ) => {
		onChange( serializeSpecialHoursRules( nextRules ) );
	};
	const addRule = () => {
		setRules( [ ...rules, createDefaultSpecialHoursRule() ] );
	};
	const updateRule = ( index: number, patch: Partial<SpecialHoursRule> ) => {
		setRules(
			rules.map( ( rule, ruleIndex ) =>
				ruleIndex === index
					? {
						...rule,
						...patch,
					}
					: rule,
			),
		);
	};
	const removeRule = ( index: number ) => {
		setRules( rules.filter( ( _, ruleIndex ) => ruleIndex !== index ) );
	};

	return (
		<div className="space-y-3">
			{ rules.length < 1 ? (
				<p className="rounded-md border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500">
					{ getNoItemsYetAddBelowLabel(
						__( 'special hours rules', 'airygen-seo' ),
						__( 'a rule', 'airygen-seo' ),
					) }
				</p>
			) : null }
			{ rules.map( ( rule, index ) => (
				<div
					key={ `${ inputIdPrefix }-rule-${ index }` }
					className="airygen-setting-card__input--normal space-y-3 rounded-lg border border-slate-200 p-4"
				>
					<div className="flex items-center justify-between gap-2">
						<p className="text-sm font-medium text-slate-900">
							{ __( 'Rule', 'airygen-seo' ) } { index + 1 }
						</p>
						<Button
							variant="secondary"
							className="text-xs"
							onClick={ () => removeRule( index ) }
						>
							{ __( 'Remove', 'airygen-seo' ) }
						</Button>
					</div>
					<div className="grid gap-3 md:grid-cols-4">
						<div>
							<label
								className="block text-xs font-medium text-slate-700"
								htmlFor={ `${ inputIdPrefix }-start-${ index }` }
							>
								{ __( 'Start date', 'airygen-seo' ) }
							</label>
							<input
								id={ `${ inputIdPrefix }-start-${ index }` }
								type="date"
								className="airygen-field mt-1 w-full"
								style={ { height: '34px' } }
								value={ rule.startDate }
								onChange={ ( event ) =>
									updateRule( index, {
										startDate: event.currentTarget.value || rule.startDate,
									} )
								}
							/>
						</div>
						<div>
							<label
								className="block text-xs font-medium text-slate-700"
								htmlFor={ `${ inputIdPrefix }-end-${ index }` }
							>
								{ __( 'End date', 'airygen-seo' ) }
							</label>
							<input
								id={ `${ inputIdPrefix }-end-${ index }` }
								type="date"
								className="airygen-field mt-1 w-full"
								style={ { height: '34px' } }
								value={ rule.endDate }
								onChange={ ( event ) =>
									updateRule( index, {
										endDate: event.currentTarget.value || rule.startDate,
									} )
								}
							/>
						</div>
						<div className="airygen-setting-card__select--normal">
							<label
								className="block text-xs font-medium text-slate-700"
								htmlFor={ `${ inputIdPrefix }-status-${ index }` }
							>
								{ __( 'Status', 'airygen-seo' ) }
							</label>
							<select
								id={ `${ inputIdPrefix }-status-${ index }` }
								className="airygen-field-select mt-1 w-full"
								value={ rule.isClosed ? 'closed' : 'open' }
								onChange={ ( event ) =>
									updateRule( index, {
										isClosed: event.currentTarget.value === 'closed',
									} )
								}
							>
								<option value="open">{ __( 'Open', 'airygen-seo' ) }</option>
								<option value="closed">{ __( 'Closed', 'airygen-seo' ) }</option>
							</select>
						</div>
						<div className="grid grid-cols-2 gap-2">
							<div>
								<label
									className="block text-xs font-medium text-slate-700"
									htmlFor={ `${ inputIdPrefix }-opens-${ index }` }
								>
									{ __( 'Opens', 'airygen-seo' ) }
								</label>
								<input
									id={ `${ inputIdPrefix }-opens-${ index }` }
									type="time"
									step={ 600 }
									className="airygen-field mt-1 w-full"
									style={ { height: '34px' } }
									value={ rule.opens }
									disabled={ rule.isClosed }
									onChange={ ( event ) =>
										updateRule( index, {
											opens: event.currentTarget.value || rule.opens,
										} )
									}
								/>
							</div>
							<div>
								<label
									className="block text-xs font-medium text-slate-700"
									htmlFor={ `${ inputIdPrefix }-closes-${ index }` }
								>
									{ __( 'Closes', 'airygen-seo' ) }
								</label>
								<input
									id={ `${ inputIdPrefix }-closes-${ index }` }
									type="time"
									step={ 600 }
									className="airygen-field mt-1 w-full"
									style={ { height: '34px' } }
									value={ rule.closes }
									disabled={ rule.isClosed }
									onChange={ ( event ) =>
										updateRule( index, {
											closes: event.currentTarget.value || rule.closes,
										} )
									}
								/>
							</div>
						</div>
					</div>
				</div>
			) ) }
			<Button variant="secondary" className="text-xs" onClick={ addRule }>
				{ __( 'Add special hours rule', 'airygen-seo' ) }
			</Button>
		</div>
	);
};
const LocalSeoColorPicker = TransparentColorPicker;
const buildOpeningHoursSchemaPreview = (
	raw: string,
): Array<Record<string, string | string[]>> => {
	const lines = raw
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line !== '' );
	const dayMap: Record<DayCode, string> = {
		Mo: 'Monday',
		Tu: 'Tuesday',
		We: 'Wednesday',
		Th: 'Thursday',
		Fr: 'Friday',
		Sa: 'Saturday',
		Su: 'Sunday',
	};

	return lines.flatMap( ( line ) => {
		const normalizedLine = line.replace( /[\u2012\u2013\u2014\u2212]/g, '-' );
		const match = normalizedLine.match( /^([A-Za-z]{2})(?:-([A-Za-z]{2}))?\s+(.+)$/ );
		if ( ! match ) {
			return [];
		}

		const fromCode = match[ 1 ] as DayCode;
		const toCode = ( match[ 2 ] ?? '' ) as DayCode | '';
		const fromIndex = DAY_CODES.indexOf( fromCode );
		const toIndex = '' === toCode ? fromIndex : DAY_CODES.indexOf( toCode );
		if ( -1 === fromIndex || -1 === toIndex || toIndex < fromIndex ) {
			return [];
		}

		const dayOfWeek = DAY_CODES.slice( fromIndex, toIndex + 1 )
			.map( ( dayCode ) => dayMap[ dayCode ] )
			.filter( ( value ): value is string => Boolean( value ) );
		if ( dayOfWeek.length < 1 ) {
			return [];
		}

		return match[ 3 ]
			.split( /\s*,\s*/ )
			.map( ( segment ) => segment.trim() )
			.filter( ( segment ) => segment !== '' )
			.flatMap( ( segment ): Array<Record<string, string | string[]>> => {
				const timeMatch = segment.match( /^([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})$/ );
				if ( ! timeMatch ) {
					return [];
				}
				return [
					{
						'@type': 'OpeningHoursSpecification',
						dayOfWeek,
						opens: timeMatch[ 1 ] ?? '',
						closes: timeMatch[ 2 ] ?? '',
					},
				];
			} );
	} );
};

const resolvePriceRangePreview = ( settings: LocalSeoSettings ): string => {
	const custom = settings.priceRangeCustom.trim();
	if ( custom !== '' ) {
		return custom;
	}
	return settings.priceRangeLevel;
};
const toReadablePriceLevel = ( value: string ): string => {
	if ( value === '$' ) {
		return __( 'Budget', 'airygen-seo' );
	}
	if ( value === '$$' ) {
		return __( 'Moderate', 'airygen-seo' );
	}
	if ( value === '$$$' ) {
		return __( 'Premium', 'airygen-seo' );
	}
	if ( value === '$$$$' ) {
		return __( 'Luxury', 'airygen-seo' );
	}
	return value;
};

const parseCoordinatesFromGoogleMapsUrl = (
	urlValue: string,
): { latitude: number; longitude: number } | null => {
	const trimmed = urlValue.trim();
	if ( '' === trimmed ) {
		return null;
	}

	let parsedUrl: URL;
	try {
		parsedUrl = new URL( trimmed );
	} catch {
		return null;
	}

	const host = parsedUrl.hostname.toLowerCase();
	const isGoogleMapsHost =
		host.includes( 'google.' ) || host.includes( 'maps.app.goo.gl' );
	if ( ! isGoogleMapsHost ) {
		return null;
	}

	const full = decodeURIComponent( parsedUrl.href );
	const candidates: Array<[ string, string ]> = [];

	const atMatch = full.match( /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/ );
	if ( atMatch && atMatch[ 1 ] && atMatch[ 2 ] ) {
		candidates.push( [ atMatch[ 1 ], atMatch[ 2 ] ] );
	}

	const queryPairs = [ 'q', 'query', 'll', 'sll' ] as const;
	queryPairs.forEach( ( key ) => {
		const value = parsedUrl.searchParams.get( key );
		if ( ! value ) {
			return;
		}
		const queryMatch = value.match( /(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/ );
		if ( queryMatch && queryMatch[ 1 ] && queryMatch[ 2 ] ) {
			candidates.push( [ queryMatch[ 1 ], queryMatch[ 2 ] ] );
		}
	} );

	const bangMatch = full.match( /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/ );
	if ( bangMatch && bangMatch[ 1 ] && bangMatch[ 2 ] ) {
		candidates.push( [ bangMatch[ 1 ], bangMatch[ 2 ] ] );
	}

	for ( const [ latRaw, lngRaw ] of candidates ) {
		const latitude = Number( latRaw );
		const longitude = Number( lngRaw );
		if ( ! Number.isFinite( latitude ) || ! Number.isFinite( longitude ) ) {
			continue;
		}
		if ( latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180 ) {
			continue;
		}

		return { latitude, longitude };
	}

	return null;
};

const hasValidMapCoordinates = ( latitude: number, longitude: number ): boolean => {
	if ( ! Number.isFinite( latitude ) || ! Number.isFinite( longitude ) ) {
		return false;
	}
	if ( latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180 ) {
		return false;
	}
	if ( latitude === 0 || longitude === 0 ) {
		return false;
	}

	return true;
};

const toBranchSlug = ( value: string ): string =>
	value
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );

const getUniqueBranchSlug = (
	preferred: string,
	branches: LocalSeoSettings['branches'],
	currentBranchId: string | null = null,
): string => {
	const normalizedPreferred = toBranchSlug( preferred );
	const baseSlug = '' !== normalizedPreferred ? normalizedPreferred : 'branch';
	const usedSlugs = new Set(
		branches
			.filter( ( branch ) => null === currentBranchId || branch.id !== currentBranchId )
			.map( ( branch ) => toBranchSlug( branch.slug ) )
			.filter( ( slug ) => '' !== slug ),
	);

	let slug = baseSlug;
	let suffix = 2;
	while ( usedSlugs.has( slug ) ) {
		slug = `${ baseSlug }-${ suffix }`;
		suffix += 1;
	}

	return slug;
};

const createDefaultBranch = ( index: number, branches: LocalSeoSettings['branches'] ): LocalSeoBranch => ( {
	id: `branch-${ Date.now() }-${ index }`,
	label: `Branch ${ index }`,
	slug: getUniqueBranchSlug( `branch-${ index }`, branches ),
	enabled: true,
	businessName: '',
	phone: '',
	imageUrl: '',
	streetAddress: '',
	city: '',
	region: '',
	postalCode: '',
	country: '',
	latitude: 0,
	longitude: 0,
	openingHours: '',
	specialHours: '',
	serviceAreaCities: [],
	serviceAreaPostalCodes: [],
	serviceAreaRadiusKm: 0,
	contactAutoMapEmbed: false,
	kmlInSitemap: false,
	geoRegionCode: '',
	geoPlacename: '',
} );

const PreviewLaptopIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M1.11068 1.66569H5.55341V4.4424H1.11068V1.66569ZM5.55341 4.99774C5.7007 4.99774 5.84195 4.93923 5.9461 4.83508C6.05025 4.73094 6.10875 4.58968 6.10875 4.4424V1.66569C6.10875 1.35748 5.85885 1.11035 5.55341 1.11035H1.11068C0.802468 1.11035 0.555341 1.35748 0.555341 1.66569V4.4424C0.555341 4.58968 0.61385 4.73094 0.717997 4.83508C0.822144 4.93923 0.963397 4.99774 1.11068 4.99774H0V5.55308H6.6641V4.99774H5.55341Z" fill="currentColor" />
	</svg>
);

const PreviewTabletIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M5.27566 4.99774H1.38827V1.66569H5.27566V4.99774ZM5.831 1.11035H0.832929C0.524715 1.11035 0.277588 1.35748 0.277588 1.66569V4.99774C0.277588 5.14503 0.336097 5.28628 0.440244 5.39043C0.54439 5.49457 0.685644 5.55308 0.832929 5.55308H5.831C5.97829 5.55308 6.11954 5.49457 6.22369 5.39043C6.32783 5.28628 6.38634 5.14503 6.38634 4.99774V1.66569C6.38634 1.35748 6.13644 1.11035 5.831 1.11035Z" fill="currentColor" />
	</svg>
);

const PreviewCellphoneIcon = ( { className = 'h-4 w-4' }: { className?: string } ) => (
	<svg className={ className } viewBox="0 0 7 7" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M4.72048 5.27542H1.94377V1.38803H4.72048V5.27542ZM4.72048 0.277344H1.94377C1.63555 0.277344 1.38843 0.524471 1.38843 0.832685V5.83076C1.38843 5.97804 1.44694 6.1193 1.55108 6.22344C1.65523 6.32759 1.79648 6.3861 1.94377 6.3861H4.72048C4.86776 6.3861 5.00901 6.32759 5.11316 6.22344C5.21731 6.1193 5.27582 5.97804 5.27582 5.83076V0.832685C5.27582 0.524471 5.02591 0.277344 4.72048 0.277344Z" fill="currentColor" />
	</svg>
);

const LocalSeoTab = ( { settings, onChange }: LocalSeoTabProps ) => {
	const [ activeTab, setActiveTab ] = useState<'mainStore' | 'branches' | 'layout' | 'preview'>( 'mainStore' );
	const [ previewViewport, setPreviewViewport ] = useState<PreviewDeviceKind>( 'laptop' );
	const [ editingBranchId, setEditingBranchId ] = useState<string | null>( null );
	const [ isMapUrlModalOpen, setIsMapUrlModalOpen ] = useState( false );
	const [ mapUrlTarget, setMapUrlTarget ] = useState<'mainStore' | 'branch'>( 'mainStore' );
	const [ mapUrlInput, setMapUrlInput ] = useState( '' );
	const [ mapUrlError, setMapUrlError ] = useState( '' );
	const [ unifiedShortcodeCopied, setUnifiedShortcodeCopied ] = useState( false );
	const [ branchShortcodeCopied, setBranchShortcodeCopied ] = useState( false );
	const [ localSchemaCopied, setLocalSchemaCopied ] = useState( false );
	const [ draggingLayoutBlockId, setDraggingLayoutBlockId ] = useState<string | null>( null );
	const [ draggingFooterNapBlockId, setDraggingFooterNapBlockId ] = useState<FooterNapLayoutBlockId | null>( null );
	const [ layoutCanvasRows, setLayoutCanvasRows ] = useState( () => {
		const maxOccupied = settings.layoutGrid.reduce( ( max, item ) => {
			const row = Number.isFinite( item.row ) ? Math.floor( item.row ) : 1;
			const rowSpan = Number.isFinite( item.rowSpan ) ? Math.floor( item.rowSpan ) : 1;
			const endRow = row + rowSpan - 1;
			return endRow > max ? endRow : max;
		}, 1 );
		return getPreferredLayoutRows( maxOccupied );
	} );
	const [ openingHoursByDay, setOpeningHoursByDay ] = useState<Record<DayCode, DaySchedule>>(
		parseOpeningHoursByDay( settings.openingHours ),
	);
	const [ branchOpeningHoursByDay, setBranchOpeningHoursByDay ] = useState<
		Record<DayCode, DaySchedule>
	>( createDefaultOpeningHoursSchedule() );
	const countryOptions = useMemo( () => {
		if ( 'undefined' === typeof Intl || typeof Intl.DisplayNames !== 'function' ) {
			return Object.entries( COUNTRY_CODE_LABEL_MAP )
				.map( ( [ code, label ] ) => ( { code, label } ) )
				.sort( ( left, right ) => left.code.localeCompare( right.code, 'en' ) );
		}

		const displayNames = new Intl.DisplayNames( [ 'en' ], { type: 'region' } );
		const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		const options: Array<{ code: string; label: string }> = [];

		for ( let first = 0; first < letters.length; first += 1 ) {
			for ( let second = 0; second < letters.length; second += 1 ) {
				const code = `${ letters[ first ] ?? '' }${ letters[ second ] ?? '' }`;
				const resolvedLabel = COUNTRY_CODE_LABEL_MAP[ code ] ?? displayNames.of( code ) ?? '';
				if (
					'' === resolvedLabel ||
					resolvedLabel === code ||
					resolvedLabel.toLowerCase().includes( 'unknown region' )
				) {
					continue;
				}
				options.push( {
					code,
					label: resolvedLabel,
				} );
			}
		}

		return options.sort( ( left, right ) => left.code.localeCompare( right.code, 'en' ) );
	}, [] );
	const normalizedLayoutGrid = useMemo( () => {
		const allowedIds = new Set<string>(
			LOCAL_SEO_LAYOUT_BLOCKS.map( ( block ) => block.id ),
		);
		const occupied = new Set<string>();
		const placedIds = new Set<string>();
		const next: LocalSeoSettings['layoutGrid'] = [];

		settings.layoutGrid.forEach( ( item ) => {
			if (
				! item ||
					! allowedIds.has( item.blockId ) ||
					placedIds.has( item.blockId ) ||
					! Number.isInteger( item.row ) ||
				! Number.isInteger( item.col ) ||
				! Number.isInteger( item.span )
			) {
				return;
			}

			const row = Math.floor( item.row );
			const col = Math.floor( item.col );
			const span = Math.floor( item.span );
			const rowSpan = Math.floor(
				Number.isInteger( item.rowSpan ) ? item.rowSpan : 1,
			);
			const maxSpan = LOCAL_SEO_LAYOUT_COLS - col + 1;
			const maxRowSpan = Math.min( 5, LOCAL_SEO_LAYOUT_ROWS - row + 1 );
			if (
				row < 1 ||
				row > LOCAL_SEO_LAYOUT_ROWS ||
				col < 1 ||
				col > LOCAL_SEO_LAYOUT_COLS ||
				span < 1 ||
				span > maxSpan ||
				rowSpan < 1 ||
				rowSpan > maxRowSpan
			) {
				return;
			}

			for ( let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1 ) {
				for ( let colOffset = 0; colOffset < span; colOffset += 1 ) {
					const key = `${ row + rowOffset }-${ col + colOffset }`;
					if ( occupied.has( key ) ) {
						return;
					}
				}
			}
			for ( let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1 ) {
				for ( let colOffset = 0; colOffset < span; colOffset += 1 ) {
					occupied.add( `${ row + rowOffset }-${ col + colOffset }` );
				}
			}
			next.push( { blockId: item.blockId, row, col, span, rowSpan } );
			placedIds.add( item.blockId );
		} );

		return next.slice().sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
	}, [ settings.layoutGrid ] );
	const layoutGridById = useMemo(
		() => new Map( normalizedLayoutGrid.map( ( item ) => [ item.blockId, item ] ) ),
		[ normalizedLayoutGrid ],
	);
	const layoutOccupiedMaxRow = useMemo( () => {
		if ( normalizedLayoutGrid.length < 1 ) {
			return 1;
		}
		return normalizedLayoutGrid.reduce( ( max, item ) => {
			const endRow = item.row + item.rowSpan - 1;
			return endRow > max ? endRow : max;
		}, 1 );
	}, [ normalizedLayoutGrid ] );
	const minSelectableLayoutRows = Math.max( 7, layoutOccupiedMaxRow );
	useEffect( () => {
		if ( layoutCanvasRows < minSelectableLayoutRows ) {
			setLayoutCanvasRows( minSelectableLayoutRows );
		}
	}, [ layoutCanvasRows, minSelectableLayoutRows ] );
	const layoutVisibleIds = useMemo(
		() => new Set( normalizedLayoutGrid.map( ( item ) => item.blockId ) ),
		[ normalizedLayoutGrid ],
	);
	const hiddenLayoutBlocks = useMemo(
		() => LOCAL_SEO_LAYOUT_BLOCKS.filter( ( block ) => ! layoutVisibleIds.has( block.id ) ),
		[ layoutVisibleIds ],
	);
	const footerNapVisibleOrder = useMemo( () => {
		const allowed = new Set( FOOTER_NAP_LAYOUT_BLOCKS.map( ( block ) => block.id ) );
		const selected = settings.footerNapLayoutOrder.filter(
			( item ): item is FooterNapLayoutBlockId => allowed.has( item as FooterNapLayoutBlockId ),
		);
		const unique = [ ...new Set( selected ) ] as FooterNapLayoutBlockId[];
		return unique.length > 0 ? unique : [ ...FOOTER_NAP_DEFAULT_LAYOUT_ORDER ];
	}, [ settings.footerNapLayoutOrder ] );
	const footerNapHiddenBlocks = useMemo(
		() =>
			FOOTER_NAP_LAYOUT_BLOCKS.filter(
				( block ) => ! footerNapVisibleOrder.includes( block.id ),
			),
		[ footerNapVisibleOrder ],
	);
	const footerNapBlockConfiguredMap = useMemo( () => {
		const hasAddress = [
			settings.streetAddress,
			settings.city,
			settings.region,
			settings.postalCode,
			settings.country,
		].some( ( value ) => value.trim() !== '' );
		return {
			business_name: settings.businessName.trim() !== '',
			legal_name: settings.legalName.trim() !== '',
			phone: settings.phone.trim() !== '',
			address: hasAddress,
			tax_id: settings.vatId.trim() !== '',
		} as Record<FooterNapLayoutBlockId, boolean>;
	}, [ settings ] );
	const layoutBlockConfiguredMap = useMemo( () => {
		const hasAddress = [
			settings.streetAddress,
			settings.city,
			settings.region,
			settings.postalCode,
			settings.country,
		].some( ( value ) => value.trim() !== '' );
		const hasServiceAreas =
			settings.serviceAreaCities.some( ( city ) => city.trim() !== '' ) ||
			settings.serviceAreaPostalCodes.some( ( postalCode ) => postalCode.trim() !== '' ) ||
			settings.serviceAreaRadiusKm > 0;
		const hasServiceCatalog = settings.serviceCatalogItems.some(
			( item ) => item.name.trim() !== '' || item.description.trim() !== '',
		);

		return {
			business_name: settings.businessName.trim() !== '',
			legal_name: settings.legalName.trim() !== '',
			address: hasAddress,
			phone: settings.phone.trim() !== '',
			map: hasValidMapCoordinates( settings.latitude, settings.longitude ),
			image_url: settings.imageUrl.trim() !== '',
			logo_url: settings.logoUrl.trim() !== '',
			vat_id: settings.vatId.trim() !== '',
			pricing: resolvePriceRangePreview( settings ) !== '',
			service_areas: hasServiceAreas,
			service_catalog: hasServiceCatalog,
			opening_hours: settings.openingHours.trim() !== '',
			special_hours: settings.specialHours.trim() !== '',
		} as Record<string, boolean>;
	}, [ settings ] );
	const layoutOccupiedMap = useMemo( () => {
		const occupied = new Map<string, string>();
		normalizedLayoutGrid.forEach( ( item ) => {
			for ( let rowOffset = 0; rowOffset < item.rowSpan; rowOffset += 1 ) {
				for ( let colOffset = 0; colOffset < item.span; colOffset += 1 ) {
					occupied.set(
						`${ item.row + rowOffset }-${ item.col + colOffset }`,
						item.blockId,
					);
				}
			}
		} );
		return occupied;
	}, [ normalizedLayoutGrid ] );
	const getLayoutMaxSpan = ( col: number ) => Math.max( 1, LOCAL_SEO_LAYOUT_COLS - col + 1 );
	const getLayoutMaxRowSpan = ( row: number ) =>
		Math.max( 1, Math.min( 5, LOCAL_SEO_LAYOUT_ROWS - row + 1 ) );
	const resolveLayoutLane = (
		item: Pick<LocalSeoSettings['layoutGrid'][ number ], 'row' | 'rowSpan' | 'col' | 'span'>,
		template: LocalSeoLayoutTemplate,
	): 'header' | 'sidebar' | 'main' => {
		if ( templateHasHeader( template ) && item.row === 1 ) {
			return 'header';
		}
		const center = item.col + ( ( item.span - 1 ) / 2 );
		if ( isSidebarLeftTemplate( template ) ) {
			return center <= 2.5 ? 'sidebar' : 'main';
		}
		return center >= 3.5 ? 'sidebar' : 'main';
	};
	const getLaneColumns = (
		lane: 'header' | 'sidebar' | 'main',
		template: LocalSeoLayoutTemplate,
	): { start: number; span: number } => {
		if ( lane === 'header' ) {
			return { start: 1, span: 5 };
		}
		const templateColumns = resolveLayoutColumns( template );
		return lane === 'sidebar'
			? { start: templateColumns.sidebarStart, span: templateColumns.sidebarSpan }
			: { start: templateColumns.mainStart, span: templateColumns.mainSpan };
	};
	const normalizeLanePlacement = (
		item: LocalSeoSettings['layoutGrid'][ number ],
		lane: 'header' | 'sidebar' | 'main',
		template: LocalSeoLayoutTemplate,
	): { col: number; span: number } => {
		if ( lane === 'header' ) {
			const col = Math.max( 1, Math.min( LOCAL_SEO_LAYOUT_COLS, item.col ) );
			const maxSpan = getLayoutMaxSpan( col );
			const span = Math.max( 1, Math.min( maxSpan, item.span ) );
			return { col, span };
		}
		const laneColumns = getLaneColumns( lane, template );
		const laneStart = laneColumns.start;
		const laneSpan = laneColumns.span;
		const span = Math.max( 1, Math.min( laneSpan, item.span ) );
		const maxCol = laneStart + laneSpan - span;
		const col = Math.max( laneStart, Math.min( maxCol, item.col ) );
		return { col, span };
	};
	const resolvePlacementZone = (
		row: number,
		col: number,
		span: number,
		rowSpan: number,
		template: LocalSeoLayoutTemplate,
	): 'header' | 'sidebar' | 'main' | 'mixed' => {
		if ( row < 1 || col < 1 || span < 1 || rowSpan < 1 ) {
			return 'mixed';
		}
		const endCol = col + span - 1;
		const endRow = row + rowSpan - 1;
		if ( endCol > LOCAL_SEO_LAYOUT_COLS || endRow > LOCAL_SEO_LAYOUT_ROWS ) {
			return 'mixed';
		}
		if ( templateHasHeader( template ) ) {
			if ( row === 1 ) {
				if ( rowSpan !== 1 ) {
					return 'mixed';
				}
				return 'header';
			}
			if ( row < 2 ) {
				return 'mixed';
			}
		}
		const splitCol = isSidebarLeftTemplate( template ) ? 2 : 3;
		if ( endCol <= splitCol ) {
			return isSidebarLeftTemplate( template ) ? 'sidebar' : 'main';
		}
		if ( col > splitCol ) {
			return isSidebarLeftTemplate( template ) ? 'main' : 'sidebar';
		}
		return 'mixed';
	};
	const applyLayoutTemplate = (
		template: LocalSeoLayoutTemplate,
		sourceGrid: LocalSeoSettings['layoutGrid'],
	): LocalSeoSettings['layoutGrid'] => {
		const headerItems = sourceGrid
			.filter( ( item ) => resolveLayoutLane( item, template ) === 'header' )
			.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
		const sidebarItems = sourceGrid
			.filter( ( item ) => resolveLayoutLane( item, template ) === 'sidebar' )
			.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
		const mainItems = sourceGrid
			.filter( ( item ) => resolveLayoutLane( item, template ) === 'main' )
			.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
		const nextGrid: LocalSeoSettings['layoutGrid'] = [];
		let headerRow = 1;
		const startContentRow = templateHasHeader( template ) ? 2 : 1;
		let sidebarRow = startContentRow;
		let mainRow = startContentRow;

		headerItems.forEach( ( item ) => {
			if ( headerRow > LOCAL_SEO_LAYOUT_ROWS ) {
				return;
			}
			const rowSpan = Math.max( 1, Math.min( 5, item.rowSpan ) );
			const placement = normalizeLanePlacement( item, 'header', template );
			nextGrid.push( {
				blockId: item.blockId,
				row: headerRow,
				col: placement.col,
				span: placement.span,
				rowSpan: Math.min( rowSpan, LOCAL_SEO_LAYOUT_ROWS - headerRow + 1 ),
			} );
			headerRow += rowSpan;
		} );
		sidebarItems.forEach( ( item ) => {
			if ( sidebarRow > LOCAL_SEO_LAYOUT_ROWS ) {
				return;
			}
			const rowSpan = Math.max( 1, Math.min( 5, item.rowSpan ) );
			const placement = normalizeLanePlacement( item, 'sidebar', template );
			nextGrid.push( {
				blockId: item.blockId,
				row: sidebarRow,
				col: placement.col,
				span: placement.span,
				rowSpan: Math.min( rowSpan, LOCAL_SEO_LAYOUT_ROWS - sidebarRow + 1 ),
			} );
			sidebarRow += rowSpan;
		} );
		mainItems.forEach( ( item ) => {
			if ( mainRow > LOCAL_SEO_LAYOUT_ROWS ) {
				return;
			}
			const rowSpan = Math.max( 1, Math.min( 5, item.rowSpan ) );
			const placement = normalizeLanePlacement( item, 'main', template );
			nextGrid.push( {
				blockId: item.blockId,
				row: mainRow,
				col: placement.col,
				span: placement.span,
				rowSpan: Math.min( rowSpan, LOCAL_SEO_LAYOUT_ROWS - mainRow + 1 ),
			} );
			mainRow += rowSpan;
		} );

		return nextGrid.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
	};
	const updateSettings = ( patch: Partial<LocalSeoSettings> ) => {
		const nextSettings: LocalSeoSettings = {
			...settings,
			...patch,
		};
		if ( ! hasValidMapCoordinates( nextSettings.latitude, nextSettings.longitude ) ) {
			nextSettings.kmlInSitemap = false;
			nextSettings.contactAutoMapEmbed = false;
		}
		onChange( nextSettings );
	};
	const canEnableMapFeatures = hasValidMapCoordinates( settings.latitude, settings.longitude );
	const editingBranch =
		null === editingBranchId
			? null
			: settings.branches.find( ( branch ) => branch.id === editingBranchId ) ?? null;
	const canEnableBranchMapFeatures =
		null !== editingBranch &&
		hasValidMapCoordinates( editingBranch.latitude, editingBranch.longitude );

	const localSchemaPreview = useMemo( () => {
		const name = settings.businessName.trim();
		if ( '' === name ) {
			return '';
		}

		const schema: Record<string, unknown> = {
			'@context': 'https://schema.org',
			'@type': settings.businessType || 'LocalBusiness',
			name,
		};

		if ( settings.imageUrl.trim() ) {
			schema.image = settings.imageUrl.trim();
		}
		if ( settings.logoUrl.trim() ) {
			schema.logo = {
				'@type': 'ImageObject',
				url: settings.logoUrl.trim(),
			};
		}
		if ( settings.phone.trim() ) {
			schema.telephone = settings.phone.trim();
		}
		if ( settings.legalName.trim() ) {
			schema.legalName = settings.legalName.trim();
		}
		const priceRangeValue = resolvePriceRangePreview( settings );
		if ( priceRangeValue !== '' ) {
			schema.priceRange = priceRangeValue;
		}
		if ( settings.ratingValue > 0 && settings.reviewCount > 0 ) {
			schema.aggregateRating = {
				'@type': 'AggregateRating',
				ratingValue: Number( settings.ratingValue.toFixed( 1 ) ),
				reviewCount: Math.max( 0, Math.floor( settings.reviewCount ) ),
			};
		}
		if ( settings.sameAsUrls.length > 0 ) {
			schema.sameAs = settings.sameAsUrls
				.map( ( item ) => item.trim() )
				.filter( ( item ) => item !== '' );
		}
		if ( settings.vatId.trim() ) {
			schema.taxID = settings.vatId.trim();
		}

		if (
			settings.streetAddress.trim() ||
			settings.city.trim() ||
			settings.region.trim() ||
			settings.postalCode.trim() ||
			settings.country.trim()
		) {
			schema.address = {
				'@type': 'PostalAddress',
				streetAddress: settings.streetAddress.trim(),
				addressLocality: settings.city.trim(),
				addressRegion: settings.region.trim(),
				postalCode: settings.postalCode.trim(),
				addressCountry: settings.country.trim(),
			};
		}

		if ( settings.latitude || settings.longitude ) {
			schema.geo = {
				'@type': 'GeoCoordinates',
				latitude: settings.latitude,
				longitude: settings.longitude,
			};
		}

		const areaServed: Array<Record<string, unknown>> = [];
		settings.serviceAreaCities
			.map( ( city ) => city.trim() )
			.filter( ( city ) => city !== '' )
			.forEach( ( city ) =>
				areaServed.push( {
					'@type': 'City',
					name: city,
				} ),
			);
		settings.serviceAreaPostalCodes
			.map( ( code ) => code.trim() )
			.filter( ( code ) => code !== '' )
			.forEach( ( code ) =>
				areaServed.push( {
					'@type': 'PostalCode',
					postalCode: code,
					...( settings.country.trim() !== ''
						? { addressCountry: settings.country.trim() }
						: {} ),
				} ),
			);
		if ( settings.serviceAreaRadiusKm > 0 && ( settings.latitude || settings.longitude ) ) {
			areaServed.push( {
				'@type': 'GeoCircle',
				geoMidpoint: {
					'@type': 'GeoCoordinates',
					latitude: settings.latitude,
					longitude: settings.longitude,
				},
				geoRadius: String( Math.round( settings.serviceAreaRadiusKm * 1000 ) ),
			} );
		}
		if ( areaServed.length > 0 ) {
			schema.areaServed = areaServed;
		}

		const catalogItems = settings.serviceCatalogItems
			.map( ( item ) => ( {
				name: item.name.trim(),
				description: item.description.trim(),
			} ) )
			.filter( ( item ) => item.name !== '' );
		if ( catalogItems.length > 0 ) {
			schema.hasOfferCatalog = {
				'@type': 'OfferCatalog',
				name:
					settings.serviceCatalogName.trim() !== ''
						? settings.serviceCatalogName.trim()
						: 'Services',
				itemListElement: catalogItems.map( ( item ) => ( {
					'@type': 'Offer',
					itemOffered: {
						'@type': 'Service',
						name: item.name,
						...( item.description !== '' ? { description: item.description } : {} ),
					},
				} ) ),
			};
		}

		const openingHours = buildOpeningHoursSchemaPreview( settings.openingHours );
		if ( openingHours.length > 0 ) {
			schema.openingHoursSpecification = openingHours;
		}

		const specialHours = parseSpecialHoursForPreview( settings.specialHours );
		if ( specialHours.length > 0 ) {
			schema.specialOpeningHoursSpecification = specialHours;
		}

		return JSON.stringify( schema, null, 2 );
	}, [ settings ] );
	const previewDemoAssets = useMemo( () => {
		if ( 'undefined' === typeof window ) {
			return {
				imageUrl: '',
				logoUrl: '',
			};
		}
		const adminConfig = window as Window & {
			airygenAdmin?: {
				assets?: {
					localSeoDemoImage?: string;
					localSeoDemoLogoImage?: string;
				};
			};
		};
		const imageUrl =
			'string' === typeof adminConfig.airygenAdmin?.assets?.localSeoDemoImage
				? adminConfig.airygenAdmin.assets.localSeoDemoImage.trim()
				: '';
		const logoUrl =
			'string' === typeof adminConfig.airygenAdmin?.assets?.localSeoDemoLogoImage
				? adminConfig.airygenAdmin.assets.localSeoDemoLogoImage.trim()
				: '';

		return {
			imageUrl,
			logoUrl,
		};
	}, [] );
	const contactLayoutPreview = useMemo( () => {
		const blockDefinitions = new Map<string, LocalSeoLayoutDefinition>(
			LOCAL_SEO_LAYOUT_BLOCKS.map(
				( block ): [ string, LocalSeoLayoutDefinition ] => [ block.id, block ],
			),
		);
		const previewImageUrl = settings.imageUrl.trim();
		const previewLogoUrl =
			settings.logoUrl.trim() !== ''
				? settings.logoUrl.trim()
				: previewDemoAssets.logoUrl;
		const addressText = [
			settings.streetAddress.trim(),
			settings.city.trim(),
			settings.region.trim(),
			settings.postalCode.trim(),
			settings.country.trim(),
		]
			.filter( ( item ) => item !== '' )
			.join( ' ' );
		const serviceAreaParts: string[] = [];
		const serviceAreaCities = settings.serviceAreaCities
			.map( ( value ) => value.trim() )
			.filter( ( value ) => value !== '' );
		if ( serviceAreaCities.length > 0 ) {
			serviceAreaParts.push( serviceAreaCities.join( ', ' ) );
		}
		const serviceAreaPostalCodes = settings.serviceAreaPostalCodes
			.map( ( value ) => value.trim() )
			.filter( ( value ) => value !== '' );
		if ( serviceAreaPostalCodes.length > 0 ) {
			serviceAreaParts.push( `Postal: ${ serviceAreaPostalCodes.join( ', ' ) }` );
		}
		if ( settings.serviceAreaRadiusKm > 0 ) {
			serviceAreaParts.push( `Radius: ${ Math.round( settings.serviceAreaRadiusKm ) } km` );
		}
		const serviceCatalogLines = settings.serviceCatalogItems
			.map( ( item ) => {
				const name = item.name.trim();
				if ( '' === name ) {
					return '';
				}
				const description = item.description.trim();
				if ( '' === description ) {
					return name;
				}
				return `${ name }: ${ description }`;
			} )
			.filter( ( item ) => item !== '' );
		const openingHoursLines = parseLineItems( settings.openingHours ).map(
			formatOpeningHoursLineForDisplay,
		);
		const specialHoursLines = parseLineItems( settings.specialHours ).map(
			formatSpecialHoursLineForDisplay,
		);
		const pricingText = toReadablePriceLevel( resolvePriceRangePreview( settings ).trim() );
		const hasMap = hasValidMapCoordinates( settings.latitude, settings.longitude );
		const mapEmbedUrl = hasMap
			? `https://www.google.com/maps?q=${ encodeURIComponent(
				`${ settings.latitude },${ settings.longitude }`,
			) }&z=14&output=embed`
			: '';

		const blockPayload = {
			business_name:
				settings.businessName.trim() !== ''
					? {
						kind: 'text',
						text: settings.businessName.trim(),
						html: `<p class="airygen-local-business__business-name">${ escapeHtml( settings.businessName.trim() ) }</p>`,
					}
					: null,
			legal_name:
				settings.legalName.trim() !== ''
					? {
						kind: 'text',
						text: settings.legalName.trim(),
						html: `<p>${ escapeHtml( settings.legalName.trim() ) }</p>`,
					}
					: null,
			address:
				addressText !== ''
					? {
						kind: 'text',
						text: addressText,
						html: `<p>${ escapeHtml( addressText ) }</p>`,
					}
					: null,
			phone:
				settings.phone.trim() !== ''
					? {
						kind: 'text',
						text: settings.phone.trim(),
						html: `<p>${ escapeHtml( settings.phone.trim() ) }</p>`,
					}
					: null,
			map:
				hasMap && mapEmbedUrl !== ''
					? {
						kind: 'map',
						text: `${ settings.latitude.toFixed( 6 ) }, ${ settings.longitude.toFixed( 6 ) }`,
						url: mapEmbedUrl,
						html: `<iframe src="${ escapeHtml( mapEmbedUrl ) }" title="${ escapeHtml( __( 'Map preview', 'airygen-seo' ) ) }" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>`,
					}
					: null,
			image_url:
				previewImageUrl !== ''
					? {
						kind: 'image',
						url: previewImageUrl,
						alt: __( 'Business image', 'airygen-seo' ),
						html: `<img src="${ escapeHtml( previewImageUrl ) }" alt="${ escapeHtml( __( 'Business image', 'airygen-seo' ) ) }" loading="lazy" />`,
					}
					: null,
			logo_url:
				previewLogoUrl !== ''
					? {
						kind: 'image',
						url: previewLogoUrl,
						alt: __( 'Business logo', 'airygen-seo' ),
						html: `<img src="${ escapeHtml( previewLogoUrl ) }" alt="${ escapeHtml( __( 'Business logo', 'airygen-seo' ) ) }" loading="lazy" />`,
					}
					: null,
			vat_id:
				settings.vatId.trim() !== ''
					? {
						kind: 'text',
						text: settings.vatId.trim(),
						html: `<p>${ escapeHtml( settings.vatId.trim() ) }</p>`,
					}
					: null,
			pricing:
				pricingText !== ''
					? {
						kind: 'text',
						text: pricingText,
						html: `<p>${ escapeHtml( pricingText ) }</p>`,
					}
					: null,
			service_areas:
				serviceAreaParts.length > 0
					? {
						kind: 'list',
						text: serviceAreaParts.join( ' | ' ),
						lines: serviceAreaParts,
						html: `<ul>${ serviceAreaParts.map( ( item ) => `<li>${ escapeHtml( item ) }</li>` ).join( '' ) }</ul>`,
					}
					: null,
			service_catalog:
				serviceCatalogLines.length > 0
					? {
						kind: 'list',
						lines: serviceCatalogLines,
						html: `<ul>${ serviceCatalogLines.map( ( item ) => `<li>${ escapeHtml( item ) }</li>` ).join( '' ) }</ul>`,
					}
					: null,
			opening_hours:
				openingHoursLines.length > 0
					? {
						kind: 'list',
						lines: openingHoursLines,
						html: `<ul>${ openingHoursLines.map( ( item ) => `<li>${ escapeHtml( item ) }</li>` ).join( '' ) }</ul>`,
					}
					: null,
			special_hours:
				specialHoursLines.length > 0
					? {
						kind: 'list',
						lines: specialHoursLines,
						html: `<ul>${ specialHoursLines.map( ( item ) => `<li>${ escapeHtml( item ) }</li>` ).join( '' ) }</ul>`,
					}
					: null,
		} as Record<
			string,
			| {
					kind: ContactPreviewBlockKind;
					text?: string;
					lines?: string[];
					url?: string;
					alt?: string;
					html: string;
			}
			| null
		>;

		const visibleBlocks = normalizedLayoutGrid
			.map( ( item ) => {
				const payload = blockPayload[ item.blockId ] ?? null;
				const block = blockDefinitions.get( item.blockId );
				if ( ! payload || ! block ) {
					return null;
				}
				return {
					...item,
					label: block.label,
					...payload,
				};
			} )
			.filter( ( item ): item is NonNullable<typeof item> => null !== item );

		if ( visibleBlocks.length < 1 ) {
			const emptyHtml = `<section class="airygen-local-business">\n  <p>${ escapeHtml( __( 'No configured layout blocks are available for contact page preview.', 'airygen-seo' ) ) }</p>\n</section>`;
			return {
				rows: 0,
				blocks: [] as ContactPreviewBlock[],
				html: emptyHtml,
				css: `.airygen-local-business {\n  background: #ffffff;\n  color: #1e293b;\n  font-size: 14px;\n  line-height: 1.55;\n}\n.airygen-local-business p {\n  margin: 0;\n}`,
			};
		}

		const buildLaneBlocks = ( lane: 'header' | 'sidebar' | 'main' ) => {
			const laneColumns = getLaneColumns( lane, settings.layoutTemplate );
			const laneBlocks = visibleBlocks.filter(
				( block ) => resolveLayoutLane( block, settings.layoutTemplate ) === lane,
			);
			const occupiedRows = new Set<number>();
			laneBlocks.forEach( ( block ) => {
				for ( let row = block.row; row < block.row + block.rowSpan; row += 1 ) {
					occupiedRows.add( row );
				}
			} );
			const sortedOccupiedRows = Array.from( occupiedRows ).sort( ( a, b ) => a - b );
			const rowMap = new Map<number, number>();
			sortedOccupiedRows.forEach( ( row, index ) => {
				rowMap.set( row, index + 1 );
			} );
			const blocks: ContactPreviewBlock[] = laneBlocks
				.map( ( block ) => {
					const displayRow = rowMap.get( block.row ) ?? 1;
					const endRow = block.row + block.rowSpan - 1;
					const displayEndRow = rowMap.get( endRow ) ?? displayRow;
					const laneCol = Math.max( 1, block.col - laneColumns.start + 1 );
					const laneMaxSpan = Math.max( 1, laneColumns.span - laneCol + 1 );
					const laneSpan = Math.max( 1, Math.min( laneMaxSpan, block.span ) );
					return {
						...block,
						displayRow,
						displayRowSpan: Math.max( 1, displayEndRow - displayRow + 1 ),
						displayCol: laneCol,
						displayColSpan: laneSpan,
					};
				} )
				.sort( ( a, b ) =>
					a.displayRow === b.displayRow ? a.displayCol - b.displayCol : a.displayRow - b.displayRow,
				);

			return {
				blocks,
				rows: sortedOccupiedRows.length,
				columns: laneColumns.span,
			};
		};
		const headerLane = buildLaneBlocks( 'header' );
		const sidebarLane = buildLaneBlocks( 'sidebar' );
		const mainLane = buildLaneBlocks( 'main' );
		const templateColumns = resolveLayoutColumns( settings.layoutTemplate );
		const isSidebarLeft = isSidebarLeftTemplate( settings.layoutTemplate );
		const hasHeaderLane = templateHasHeader( settings.layoutTemplate ) && headerLane.blocks.length > 0;
		const leftLane = isSidebarLeft ? sidebarLane : mainLane;
		const rightLane = isSidebarLeft ? mainLane : sidebarLane;
		const leftLaneClass = isSidebarLeft ? 'sidebar' : 'main';
		const rightLaneClass = isSidebarLeft ? 'main' : 'sidebar';
		const compactBlocks = [ ...headerLane.blocks, ...sidebarLane.blocks, ...mainLane.blocks ];
		const renderBlock = ( block: ContactPreviewBlock ) => {
			let labelHtml = '';
			if ( block.blockId === 'image_url' || block.blockId === 'logo_url' ) {
				labelHtml = `<p class="airygen-local-business__label airygen-local-business__label--sr-only">${ escapeHtml( block.label ) }</p>`;
			} else if ( block.blockId !== 'map' && block.blockId !== 'business_name' ) {
				labelHtml = `<p class="airygen-local-business__label">${ escapeHtml( block.label ) }</p>`;
			}
			return `<div class="airygen-local-business__item airygen-local-business__item--${ block.blockId }" style="grid-column:${ block.displayCol } / span ${ block.displayColSpan };grid-row:${ block.displayRow } / span ${ block.displayRowSpan };">${ labelHtml }${ block.html }</div>`;
		};
		const headerHtml = hasHeaderLane
			? `  <div class="airygen-local-business__lane airygen-local-business__lane--header" style="grid-template-columns:repeat(${ headerLane.columns }, minmax(0, 1fr));grid-template-rows:repeat(${ Math.max( 1, headerLane.rows ) }, minmax(0, auto));gap:12px;">\n${ headerLane.blocks
				.map( ( block ) => `    ${ renderBlock( block ) }` )
				.join( '\n' ) }\n  </div>\n`
			: '';
		const html = `<section class="airygen-local-business">\n${ headerHtml }  <div class="airygen-local-business__layout airygen-local-business__layout--${ settings.layoutTemplate }" style="grid-template-columns:${ isSidebarLeft ? `${ templateColumns.sidebarSpan }fr ${ templateColumns.mainSpan }fr` : `${ templateColumns.mainSpan }fr ${ templateColumns.sidebarSpan }fr` };gap:12px;">\n    <div class="airygen-local-business__column airygen-local-business__column--${ leftLaneClass }">\n      <div class="airygen-local-business__lane" style="grid-template-columns:repeat(${ leftLane.columns }, minmax(0, 1fr));grid-template-rows:repeat(${ Math.max( 1, leftLane.rows ) }, minmax(0, auto));gap:12px;">\n${ leftLane.blocks
			.map( ( block ) => `        ${ renderBlock( block ) }` )
			.join( '\n' ) }\n      </div>\n    </div>\n    <div class="airygen-local-business__column airygen-local-business__column--${ rightLaneClass }">\n      <div class="airygen-local-business__lane" style="grid-template-columns:repeat(${ rightLane.columns }, minmax(0, 1fr));grid-template-rows:repeat(${ Math.max( 1, rightLane.rows ) }, minmax(0, auto));gap:12px;">\n${ rightLane.blocks
			.map( ( block ) => `        ${ renderBlock( block ) }` )
			.join( '\n' ) }\n      </div>\n    </div>\n  </div>\n</section>`;

		return {
			rows: headerLane.rows + Math.max( sidebarLane.rows, mainLane.rows ),
			blocks: compactBlocks,
			html,
			css: `.airygen-local-business {\n  background: #ffffff;\n  color: ${ settings.layoutValueColor };\n  font-size: ${ settings.layoutValueFontSize }px;\n  line-height: 1.55;\n  border-radius: 8px;\n  ${ settings.layoutShowCardBorder ? 'border: 1px solid #e2e8f0;' : 'border: 0;' }\n  padding: 16px;\n  box-sizing: border-box;\n}\n.airygen-local-business__layout {\n  display: grid;\n  gap: 12px;\n  align-items: start;\n}\n.airygen-local-business__column {\n  min-width: 0;\n}\n.airygen-local-business__lane {\n  display: grid;\n  gap: 12px;\n  align-items: start;\n}\n.airygen-local-business__lane--header {\n  margin-bottom: 12px;\n}\n.airygen-local-business__item {\n  border-radius: 8px;\n  background: ${ settings.layoutCardBackgroundColor };\n  border: 0;\n  padding: ${ settings.layoutCardPadding }px;\n  box-sizing: border-box;\n}\n.airygen-local-business__label {\n  margin: 0 0 8px;\n  font-size: ${ settings.layoutLabelFontSize }px;\n  font-weight: ${ settings.layoutLabelBold ? 700 : 500 };\n  font-style: ${ settings.layoutLabelItalic ? 'italic' : 'normal' };\n  letter-spacing: 0.04em;\n  text-transform: ${ settings.layoutLabelUppercase ? 'uppercase' : 'none' };\n  color: ${ settings.layoutLabelColor };\n}\n.airygen-local-business__label--sr-only {\n  position: absolute;\n  width: 1px;\n  height: 1px;\n  padding: 0;\n  margin: -1px;\n  overflow: hidden;\n  clip: rect(0, 0, 0, 0);\n  white-space: nowrap;\n  border: 0;\n}\n.airygen-local-business__business-name {\n  margin: 0;\n  font-size: ${ settings.layoutTitleFontSize }px;\n  font-weight: 700;\n  line-height: 1.1;\n}\n.airygen-local-business__item p:not(.airygen-local-business__business-name),\n.airygen-local-business__item li {\n  color: ${ settings.layoutValueColor };\n  font-size: ${ settings.layoutValueFontSize }px;\n}\n.airygen-local-business__item p {\n  margin: 0;\n}\n.airygen-local-business__item ul {\n  margin: 8px 0 0;\n  padding-left: 18px;\n}\n.airygen-local-business__item img,\n.airygen-local-business__item iframe {\n  width: 100%;\n  max-width: 100%;\n  border: 1px solid #e2e8f0;\n  border-radius: 8px;\n}\n.airygen-local-business__item iframe {\n  min-height: 220px;\n  border: 0;\n}\n.airygen-local-business__item--logo_url img {\n  max-height: 72px;\n  width: auto;\n}\n@media (max-width: 960px) {\n  .airygen-local-business__layout {\n    grid-template-columns: 1fr !important;\n  }\n  .airygen-local-business__lane {\n    grid-template-columns: 1fr !important;\n    grid-template-rows: auto !important;\n  }\n  .airygen-local-business__item {\n    grid-column: auto !important;\n    grid-row: auto !important;\n  }\n}`,
		};
	}, [ normalizedLayoutGrid, previewDemoAssets.logoUrl, settings ] );
	const previewThemeStylesheets = useMemo( () => {
		if ( 'undefined' === typeof window ) {
			return [] as string[];
		}
		const adminConfig = window as Window & {
			airygenAdmin?: {
				themeStylesheets?: string[];
			};
		};
		const stylesheets = adminConfig.airygenAdmin?.themeStylesheets;
		if ( ! Array.isArray( stylesheets ) ) {
			return [] as string[];
		}
		return stylesheets.filter(
			( url ): url is string =>
				'string' === typeof url &&
				'' !== url.trim() &&
				( url.startsWith( 'http://' ) || url.startsWith( 'https://' ) || url.startsWith( '/' ) ),
		);
	}, [] );
	const contactPreviewSrcDoc = useMemo( () => {
		const stylesheetLinks = previewThemeStylesheets
			.map(
				( url ) =>
					`<link rel="stylesheet" href="${ escapeHtml( url ) }" />`,
			)
			.join( '\n' );
		return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
${ stylesheetLinks }
<style>
${ contactLayoutPreview.css }
</style>
</head>
<body>
${ contactLayoutPreview.html }
</body>
</html>`;
	}, [ contactLayoutPreview.css, contactLayoutPreview.html, previewThemeStylesheets ] );
	const footerNapPreview = useMemo( () => {
		const napTextAlign =
			settings.footerNapTextAlign === 'left' ||
			settings.footerNapTextAlign === 'right' ||
			settings.footerNapTextAlign === 'center'
				? settings.footerNapTextAlign
				: 'center';
		let napJustifyContent = 'center';
		if ( napTextAlign === 'left' ) {
			napJustifyContent = 'flex-start';
		} else if ( napTextAlign === 'right' ) {
			napJustifyContent = 'flex-end';
		}
		const napFontSize = Math.max( 10, Math.min( 48, settings.footerNapFontSize || 12 ) );
		const napContainerWidth = Math.max(
			280,
			Math.min( 1920, settings.footerNapContainerWidth || 960 ),
		);
		const napMarginY = Math.max( 0, Math.min( 200, settings.footerNapMarginY || 0 ) );
		const napGap = Math.max( 0, Math.min( 48, settings.footerNapGap || 0 ) );
		const napFirstItemBold = Boolean( settings.footerNapFirstItemBold );
		const name = settings.businessName.trim();
		const legalName = settings.legalName.trim();
		const phone = settings.phone.trim();
		const street = settings.streetAddress.trim();
		const city = settings.city.trim();
		const region = settings.region.trim();
		const postalCode = settings.postalCode.trim();
		const country = settings.country.trim().toUpperCase();
		const taxIdLine =
			settings.vatId.trim() !== ''
				? `Tax ID: ${ settings.vatId.trim() }`
				: '';
		const linesByBlock: Record<FooterNapLayoutBlockId, string> = {
			business_name: name,
			legal_name: legalName,
			phone,
			address:
				'' !== street || '' !== city || '' !== region || '' !== postalCode || '' !== country
					? 'address'
					: '',
			tax_id: taxIdLine,
		};
		const lines = footerNapVisibleOrder.filter( ( blockId ) => ( linesByBlock[ blockId ] ?? '' ) !== '' );
		const streetLine = `${ street } ${ city }`.trim();
		const locality = '' !== region ? region : city;
		const renderNapLine = ( blockId: FooterNapLayoutBlockId, isFirst: boolean ): string => {
			const lineClass = `airygen-local-nap__line${ napFirstItemBold && isFirst ? ' airygen-local-nap__line--first' : '' }`;
			if ( 'business_name' === blockId ) {
				return '' !== name
					? `<div class="${ lineClass }" itemprop="name">${ escapeHtml( name ) }</div>`
					: '';
			}
			if ( 'legal_name' === blockId ) {
				return '' !== legalName
					? `<div class="${ lineClass }" itemprop="legalName">${ escapeHtml( legalName ) }</div>`
					: '';
			}
			if ( 'phone' === blockId ) {
				return '' !== phone
					? `<div class="${ lineClass }"><span>${ escapeHtml( __( 'TEL:', 'airygen-seo' ) ) }</span><span itemprop="telephone">${ escapeHtml( phone ) }</span></div>`
					: '';
			}
			if ( 'address' === blockId ) {
				return '' !== linesByBlock.address
					? `<div class="${ lineClass }" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">${
						'' !== streetLine
							? `<span itemprop="streetAddress">${ escapeHtml( streetLine ) }</span>`
							: ''
					}${
						'' !== locality
							? `<span itemprop="addressLocality">${ escapeHtml( locality ) }</span>`
							: ''
					}${
						'' !== postalCode
							? `<span itemprop="postalCode">${ escapeHtml( postalCode ) }</span>`
							: ''
					}${
						'' !== country
							? `<meta itemprop="addressCountry" content="${ escapeHtml( country ) }">`
							: ''
					}</div>`
					: '';
			}
			if ( 'tax_id' === blockId ) {
				return '' !== taxIdLine
					? `<div class="${ lineClass }"><span>${ escapeHtml( __( 'Tax ID:', 'airygen-seo' ) ) }</span><span itemprop="taxID">${ escapeHtml( settings.vatId.trim() ) }</span></div>`
					: '';
			}
			return '';
		};
		const html =
			lines.length > 0
				? `<div class="airygen-local-nap-wrap">\n<div class="airygen-local-nap" itemscope itemtype="https://schema.org/LocalBusiness">\n  <address class="airygen-local-nap__address">\n${ lines
					.map( ( blockId, index ) => `    ${ renderNapLine( blockId, 0 === index ) }` )
					.join( '\n' ) }\n  </address>\n</div>\n</div>`
				: `<p>${ escapeHtml( __( 'Footer NAP is empty with current settings.', 'airygen-seo' ) ) }</p>`;
		const css = `.airygen-local-nap-wrap {\n  width: 100%;\n}\n.airygen-local-nap {\n  margin: ${ napMarginY }px auto;\n  width: 100%;\n  max-width: ${ napContainerWidth }px;\n  font-size: ${ napFontSize }px;\n  line-height: 1.6;\n  text-align: ${ napTextAlign };\n  color: ${ settings.footerNapTextColor };\n}\n.airygen-local-nap__address {\n  margin: 0;\n  font-style: normal;\n  display: flex;\n  flex-direction: row;\n  gap: ${ napGap }px;\n  flex-wrap: wrap;\n  justify-content: ${ napJustifyContent };\n}\n.airygen-local-nap__line {\n  margin: 0;\n  display: flex;\n  gap: 4px;\n}\n.airygen-local-nap__line--first {\n  font-weight: 700;\n}`;
		return { html, css };
	}, [ footerNapVisibleOrder, settings ] );
	const footerNapPreviewSrcDoc = useMemo( () => {
		const stylesheetLinks = previewThemeStylesheets
			.map(
				( url ) =>
					`<link rel="stylesheet" href="${ escapeHtml( url ) }" />`,
			)
			.join( '\n' );
		return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
${ stylesheetLinks }
<style>
${ footerNapPreview.css }
</style>
</head>
<body>
${ footerNapPreview.html }
</body>
</html>`;
	}, [ footerNapPreview.css, footerNapPreview.html, previewThemeStylesheets ] );
	const footerNapPreviewFrameHeight = 40;
	useEffect( () => {
		setOpeningHoursByDay( parseOpeningHoursByDay( settings.openingHours ) );
	}, [ settings.openingHours ] );
	useEffect( () => {
		setBranchOpeningHoursByDay(
			parseOpeningHoursByDay( null !== editingBranch ? editingBranch.openingHours : '' ),
		);
	}, [ editingBranch ] );

	const dayLabels: Record<DayCode, string> = {
		Mo: __( 'Monday', 'airygen-seo' ),
		Tu: __( 'Tuesday', 'airygen-seo' ),
		We: __( 'Wednesday', 'airygen-seo' ),
		Th: __( 'Thursday', 'airygen-seo' ),
		Fr: __( 'Friday', 'airygen-seo' ),
		Sa: __( 'Saturday', 'airygen-seo' ),
		Su: __( 'Sunday', 'airygen-seo' ),
	};

	const updateDaySchedule = ( dayCode: DayCode, patch: Partial<DaySchedule> ) => {
		setOpeningHoursByDay( ( previous ) => {
			const current = previous[ dayCode ];
			let openMinutes =
				undefined !== patch.openMinutes ? patch.openMinutes : current.openMinutes;
			let closeMinutes =
				undefined !== patch.closeMinutes ? patch.closeMinutes : current.closeMinutes;
			const is24Hours =
				undefined !== patch.is24Hours ? patch.is24Hours : current.is24Hours;

			openMinutes = clampOpeningHourMinutes( openMinutes );
			closeMinutes = clampOpeningHourMinutes( closeMinutes );

			if ( is24Hours ) {
				openMinutes = 0;
				closeMinutes = OPENING_HOURS_MAX_MINUTES;
			} else if ( closeMinutes <= openMinutes ) {
				if ( undefined !== patch.openMinutes ) {
					closeMinutes = Math.min(
						OPENING_HOURS_MAX_MINUTES,
						openMinutes + OPENING_HOURS_STEP_MINUTES,
					);
				} else {
					openMinutes = Math.max( 0, closeMinutes - OPENING_HOURS_STEP_MINUTES );
				}
			}

			const next = {
				...previous,
				[ dayCode ]: {
					...current,
					...patch,
					is24Hours,
					openMinutes,
					closeMinutes,
				},
			};

			updateSettings( { openingHours: serializeOpeningHoursByDay( next ) } );
			return next;
		} );
	};
	const updateBranchDaySchedule = (
		branchId: string,
		dayCode: DayCode,
		patch: Partial<DaySchedule>,
	) => {
		setBranchOpeningHoursByDay( ( previous ) => {
			const current = previous[ dayCode ];
			let openMinutes =
				undefined !== patch.openMinutes ? patch.openMinutes : current.openMinutes;
			let closeMinutes =
				undefined !== patch.closeMinutes ? patch.closeMinutes : current.closeMinutes;
			const is24Hours =
				undefined !== patch.is24Hours ? patch.is24Hours : current.is24Hours;

			openMinutes = clampOpeningHourMinutes( openMinutes );
			closeMinutes = clampOpeningHourMinutes( closeMinutes );

			if ( is24Hours ) {
				openMinutes = 0;
				closeMinutes = OPENING_HOURS_MAX_MINUTES;
			} else if ( closeMinutes <= openMinutes ) {
				if ( undefined !== patch.openMinutes ) {
					closeMinutes = Math.min(
						OPENING_HOURS_MAX_MINUTES,
						openMinutes + OPENING_HOURS_STEP_MINUTES,
					);
				} else {
					openMinutes = Math.max( 0, closeMinutes - OPENING_HOURS_STEP_MINUTES );
				}
			}

			const next = {
				...previous,
				[ dayCode ]: {
					...current,
					...patch,
					is24Hours,
					openMinutes,
					closeMinutes,
				},
			};

			updateBranch( branchId, { openingHours: serializeOpeningHoursByDay( next ) } );
			return next;
		} );
	};

	const addServiceCatalogItem = () => {
		updateSettings( {
			serviceCatalogItems: [
				...settings.serviceCatalogItems,
				{
					name: '',
					description: '',
				},
			],
		} );
	};

	const updateServiceCatalogItem = (
		index: number,
		patch: Partial<LocalSeoSettings['serviceCatalogItems'][ number ]>,
	) => {
		updateSettings( {
			serviceCatalogItems: settings.serviceCatalogItems.map( ( item, itemIndex ) =>
				itemIndex === index ? { ...item, ...patch } : item,
			),
		} );
	};

	const removeServiceCatalogItem = ( index: number ) => {
		updateSettings( {
			serviceCatalogItems: settings.serviceCatalogItems.filter(
				( _, itemIndex ) => itemIndex !== index,
			),
		} );
	};

	const addBranch = () => {
		const branch = createDefaultBranch( settings.branches.length + 1, settings.branches );
		updateSettings( {
			branches: [ ...settings.branches, branch ],
		} );
		setEditingBranchId( branch.id );
	};

	const updateBranch = ( branchId: string, patch: Partial<LocalSeoBranch> ) => {
		updateSettings( {
			branches: settings.branches.map( ( branch ) => {
				if ( branch.id !== branchId ) {
					return branch;
				}

				const nextBranch: LocalSeoBranch = {
					...branch,
					...patch,
				};
				if ( Object.prototype.hasOwnProperty.call( patch, 'slug' ) || '' === nextBranch.slug.trim() ) {
					nextBranch.slug = getUniqueBranchSlug(
						'' !== nextBranch.slug.trim() ? nextBranch.slug : nextBranch.label,
						settings.branches,
						branch.id,
					);
				}
				if ( ! hasValidMapCoordinates( nextBranch.latitude, nextBranch.longitude ) ) {
					nextBranch.contactAutoMapEmbed = false;
					nextBranch.kmlInSitemap = false;
				}

				return nextBranch;
			} ),
		} );
	};

	const removeBranch = ( branchId: string ) => {
		updateSettings( {
			branches: settings.branches.filter( ( branch ) => branch.id !== branchId ),
		} );
		if ( editingBranchId === branchId ) {
			setEditingBranchId( null );
		}
	};

	const updateLayoutGrid = ( nextGrid: LocalSeoSettings['layoutGrid'] ) => {
		const sorted = nextGrid
			.slice()
			.map( ( item ) => {
				const lane = resolveLayoutLane( item, settings.layoutTemplate );
				const placement = normalizeLanePlacement( item, lane, settings.layoutTemplate );
				return {
					...item,
					row: Math.max( 1, Math.min( LOCAL_SEO_LAYOUT_ROWS, item.row ) ),
					col: placement.col,
					span: placement.span,
					rowSpan: Math.max(
						1,
						Math.min( getLayoutMaxRowSpan( item.row ), item.rowSpan ),
					),
				};
			} )
			.sort( ( a, b ) => ( a.row === b.row ? a.col - b.col : a.row - b.row ) );
		updateSettings( {
			layoutGrid: sorted,
			layoutOrder: sorted.map( ( item ) => item.blockId ),
		} );
	};
	const canPlaceLayoutBlock = (
		blockId: string,
		row: number,
		col: number,
		span: number,
		rowSpan: number,
	): boolean => {
		if (
			row < 1 ||
			row > LOCAL_SEO_LAYOUT_ROWS ||
			col < 1 ||
			col > LOCAL_SEO_LAYOUT_COLS ||
			span < 1 ||
			span > getLayoutMaxSpan( col ) ||
			rowSpan < 1 ||
			rowSpan > getLayoutMaxRowSpan( row )
		) {
			return false;
		}
		if ( 'mixed' === resolvePlacementZone( row, col, span, rowSpan, settings.layoutTemplate ) ) {
			return false;
		}

		for ( let rowOffset = 0; rowOffset < rowSpan; rowOffset += 1 ) {
			for ( let colOffset = 0; colOffset < span; colOffset += 1 ) {
				const key = `${ row + rowOffset }-${ col + colOffset }`;
				const occupiedBy = layoutOccupiedMap.get( key );
				if ( occupiedBy && occupiedBy !== blockId ) {
					return false;
				}
			}
		}

		return true;
	};
	const moveLayoutBlock = (
		blockId: string,
		row: number,
		col: number,
		overrideSpan?: number,
		overrideRowSpan?: number,
	) => {
		const current = layoutGridById.get( blockId );
		if ( ! current ) {
			return;
		}
		const span = Math.min(
			getLayoutMaxSpan( col ),
			Math.max( 1, undefined !== overrideSpan ? overrideSpan : current.span ),
		);
		const rowSpan = Math.min(
			getLayoutMaxRowSpan( row ),
			Math.max( 1, undefined !== overrideRowSpan ? overrideRowSpan : current.rowSpan ),
		);
		if ( ! canPlaceLayoutBlock( blockId, row, col, span, rowSpan ) ) {
			return;
		}

		updateLayoutGrid(
			normalizedLayoutGrid.map( ( item ) =>
				item.blockId === blockId ? { ...item, row, col, span, rowSpan } : item,
			),
		);
	};
	const reorderLayoutBlocks = ( sourceBlockId: string, targetBlockId: string ) => {
		if ( sourceBlockId === targetBlockId ) {
			return;
		}
		const source = layoutGridById.get( sourceBlockId );
		const target = layoutGridById.get( targetBlockId );
		if ( ! source || ! target ) {
			return;
		}
		updateLayoutGrid(
			normalizedLayoutGrid.map( ( item ) => {
				if ( item.blockId === sourceBlockId ) {
					return {
						...item,
						row: target.row,
						col: target.col,
						span: target.span,
						rowSpan: target.rowSpan,
					};
				}
				if ( item.blockId === targetBlockId ) {
					return {
						...item,
						row: source.row,
						col: source.col,
						span: source.span,
						rowSpan: source.rowSpan,
					};
				}
				return item;
			} ),
		);
	};
	const handleLayoutDragStart = (
		event: DragEvent<HTMLDivElement>,
		blockId: string,
	) => {
		setDraggingLayoutBlockId( blockId );
		event.dataTransfer.setData( 'text/plain', `layout_block:${ blockId }` );
		event.dataTransfer.effectAllowed = 'move';
	};
	const handleLayoutDragEnd = () => {
		setDraggingLayoutBlockId( null );
	};
	const handleLayoutDragOver = ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
	};
	const handleLayoutDrop = (
		event: DragEvent<HTMLDivElement>,
		targetRow: number,
		targetCol: number,
	) => {
		event.preventDefault();
		event.stopPropagation();
		const payload = event.dataTransfer.getData( 'text/plain' );
		const sourceBlockId = payload.startsWith( 'layout_block:' )
			? payload.slice( 'layout_block:'.length )
			: draggingLayoutBlockId ?? '';
		if ( '' === sourceBlockId ) {
			return;
		}
		const occupiedBy = layoutOccupiedMap.get( `${ targetRow }-${ targetCol }` );
		if ( occupiedBy && occupiedBy !== sourceBlockId ) {
			reorderLayoutBlocks( sourceBlockId, occupiedBy );
			setDraggingLayoutBlockId( null );
			return;
		}
		moveLayoutBlock( sourceBlockId, targetRow, targetCol );
		setDraggingLayoutBlockId( null );
	};
	const handleLayoutDropOnCard = (
		event: DragEvent<HTMLDivElement>,
		targetBlockId: string,
	) => {
		event.preventDefault();
		event.stopPropagation();
		const payload = event.dataTransfer.getData( 'text/plain' );
		const sourceBlockId = payload.startsWith( 'layout_block:' )
			? payload.slice( 'layout_block:'.length )
			: draggingLayoutBlockId ?? '';
		if ( '' === sourceBlockId ) {
			return;
		}
		reorderLayoutBlocks( sourceBlockId, targetBlockId );
		setDraggingLayoutBlockId( null );
	};
	const handleLayoutDropOnHidden = ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		const payload = event.dataTransfer.getData( 'text/plain' );
		const sourceBlockId = payload.startsWith( 'layout_block:' )
			? payload.slice( 'layout_block:'.length )
			: draggingLayoutBlockId ?? '';
		if ( '' === sourceBlockId ) {
			return;
		}
		hideLayoutBlock( sourceBlockId );
		setDraggingLayoutBlockId( null );
	};
	const updateLayoutBlockSpan = ( blockId: string, span: number ) => {
		const current = layoutGridById.get( blockId );
		if ( ! current ) {
			return;
		}
		moveLayoutBlock( blockId, current.row, current.col, span );
	};
	const updateLayoutBlockRowSpan = ( blockId: string, rowSpan: number ) => {
		const current = layoutGridById.get( blockId );
		if ( ! current ) {
			return;
		}
		moveLayoutBlock( blockId, current.row, current.col, undefined, rowSpan );
	};
	const hideLayoutBlock = ( blockId: string ) => {
		updateLayoutGrid( normalizedLayoutGrid.filter( ( item ) => item.blockId !== blockId ) );
	};
	const findFirstAvailableLayoutSlot = () => {
		for ( let row = 1; row <= LOCAL_SEO_LAYOUT_ROWS; row += 1 ) {
			for ( let col = 1; col <= LOCAL_SEO_LAYOUT_COLS; col += 1 ) {
				if ( canPlaceLayoutBlock( '__new__', row, col, 1, 1 ) ) {
					return { row, col };
				}
			}
		}
		return null;
	};
	const showLayoutBlock = ( blockId: string ) => {
		if ( layoutVisibleIds.has( blockId ) ) {
			return;
		}
		const slot = findFirstAvailableLayoutSlot();
		if ( ! slot ) {
			return;
		}
		updateLayoutGrid( [
			...normalizedLayoutGrid,
			{ blockId, row: slot.row, col: slot.col, span: 1, rowSpan: 1 },
		] );
	};
	const updateFooterNapLayoutOrder = ( nextOrder: FooterNapLayoutBlockId[] ) => {
		const unique = [ ...new Set( nextOrder ) ];
		updateSettings( { footerNapLayoutOrder: unique } );
	};
	const handleFooterNapDragStart = (
		event: DragEvent<HTMLDivElement>,
		blockId: FooterNapLayoutBlockId,
	) => {
		setDraggingFooterNapBlockId( blockId );
		event.dataTransfer.setData( 'text/plain', `footer_nap_block:${ blockId }` );
		event.dataTransfer.effectAllowed = 'move';
	};
	const handleFooterNapDragEnd = () => {
		setDraggingFooterNapBlockId( null );
	};
	const handleFooterNapDragOver = ( event: DragEvent<HTMLDivElement> ) => {
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
	};
	const resolveFooterNapDraggedBlockId = ( event: DragEvent<HTMLElement> ) => {
		const payload = event.dataTransfer.getData( 'text/plain' );
		const source = payload.startsWith( 'footer_nap_block:' )
			? payload.slice( 'footer_nap_block:'.length )
			: draggingFooterNapBlockId ?? '';
		if (
			source === '' ||
			! FOOTER_NAP_LAYOUT_BLOCKS.some( ( block ) => block.id === source )
		) {
			return null;
		}
		return source as FooterNapLayoutBlockId;
	};
	const handleFooterNapDropOnVisible = (
		event: DragEvent<HTMLElement>,
		targetId: FooterNapLayoutBlockId | null = null,
	) => {
		event.preventDefault();
		const sourceId = resolveFooterNapDraggedBlockId( event );
		if ( null === sourceId ) {
			return;
		}
		const nextVisible = footerNapVisibleOrder.filter( ( id ) => id !== sourceId );
		if ( null === targetId ) {
			nextVisible.push( sourceId );
		} else {
			const targetIndex = nextVisible.indexOf( targetId );
			if ( targetIndex < 0 ) {
				nextVisible.push( sourceId );
			} else {
				nextVisible.splice( targetIndex, 0, sourceId );
			}
		}
		updateFooterNapLayoutOrder( nextVisible );
		setDraggingFooterNapBlockId( null );
	};
	const handleFooterNapDropOnHidden = ( event: DragEvent<HTMLElement> ) => {
		event.preventDefault();
		const sourceId = resolveFooterNapDraggedBlockId( event );
		if ( null === sourceId ) {
			return;
		}
		updateFooterNapLayoutOrder( footerNapVisibleOrder.filter( ( id ) => id !== sourceId ) );
		setDraggingFooterNapBlockId( null );
	};
	const showFooterNapBlock = ( blockId: FooterNapLayoutBlockId ) => {
		if ( footerNapVisibleOrder.includes( blockId ) ) {
			return;
		}
		updateFooterNapLayoutOrder( [ ...footerNapVisibleOrder, blockId ] );
	};
	const hideFooterNapBlock = ( blockId: FooterNapLayoutBlockId ) => {
		updateFooterNapLayoutOrder( footerNapVisibleOrder.filter( ( id ) => id !== blockId ) );
	};
	const updateLayoutTemplate = ( template: LocalSeoLayoutTemplate ) => {
		const visibleForTemplate = normalizedLayoutGrid.filter(
			( item ) =>
				'mixed' !==
				resolvePlacementZone( item.row, item.col, item.span, item.rowSpan, template ),
		);
		const nextGrid = applyLayoutTemplate( template, visibleForTemplate );
		const nextMaxOccupied = nextGrid.reduce( ( max, item ) => {
			const endRow = item.row + item.rowSpan - 1;
			return endRow > max ? endRow : max;
		}, 1 );
		updateSettings( {
			layoutTemplate: template,
			layoutGrid: nextGrid,
			layoutOrder: nextGrid.map( ( item ) => item.blockId ),
		} );
		setLayoutCanvasRows( getPreferredLayoutRows( nextMaxOccupied ) );
		setDraggingLayoutBlockId( null );
	};

	const openMapUrlModal = ( target: 'mainStore' | 'branch' = 'mainStore' ) => {
		setMapUrlTarget( target );
		setMapUrlInput( '' );
		setMapUrlError( '' );
		setIsMapUrlModalOpen( true );
	};

	const closeMapUrlModal = () => {
		setIsMapUrlModalOpen( false );
	};

	const copyShortcode = async (
		shortcode: string,
		setCopied: ( value: boolean ) => void,
	) => {
		try {
			if ( globalThis.navigator?.clipboard?.writeText ) {
				await globalThis.navigator.clipboard.writeText( shortcode );
			} else if ( globalThis.document ) {
				const textarea = globalThis.document.createElement( 'textarea' );
				textarea.value = shortcode;
				textarea.setAttribute( 'readonly', 'readonly' );
				textarea.style.position = 'absolute';
				textarea.style.left = '-9999px';
				globalThis.document.body.appendChild( textarea );
				textarea.select();
				globalThis.document.execCommand( 'copy' );
				globalThis.document.body.removeChild( textarea );
			}
			setCopied( true );
			globalThis.setTimeout( () => setCopied( false ), 1500 );
		} catch {
			setCopied( false );
		}
	};

	const copyUnifiedShortcode = async () => {
		await copyShortcode( '[airygen_localseo]', setUnifiedShortcodeCopied );
	};

	const copyBranchShortcode = async ( shortcode: string ) => {
		await copyShortcode( shortcode, setBranchShortcodeCopied );
	};
	const copyLocalSchemaPreview = async () => {
		const preview = localSchemaPreview.trim();
		if ( '' === preview ) {
			return;
		}
		await copyShortcode( preview, setLocalSchemaCopied );
	};

	const applyCoordinatesFromMapUrl = () => {
		const coordinates = parseCoordinatesFromGoogleMapsUrl( mapUrlInput );
		if ( ! coordinates ) {
			setMapUrlError(
				__(
					'Unable to parse coordinates from this Google Maps URL. Please use a full Google Maps link.',
					'airygen-seo',
				),
			);
			return;
		}

		if ( 'branch' === mapUrlTarget && null !== editingBranch ) {
			updateBranch( editingBranch.id, {
				latitude: coordinates.latitude,
				longitude: coordinates.longitude,
			} );
		} else {
			updateSettings( {
				latitude: coordinates.latitude,
				longitude: coordinates.longitude,
			} );
		}
		setMapUrlError( '' );
		setIsMapUrlModalOpen( false );
	};

	return (
		<div className="space-y-5">
			<div className="flex items-start gap-3">
				<HeadingIcon>
					<LocalSeoIcon className="h-8 w-8" aria-hidden="true" />
				</HeadingIcon>
				<div>
					<div className="airygen_h1_title">
						{ __( 'Local SEO', 'airygen-seo' ) }
					</div>
					<div className="airygen_h1_description">
						{ __( 'Configure your business details for LocalBusiness schema, geo meta tags, and reusable shortcodes.', 'airygen-seo' ) }
					</div>
				</div>
			</div>

			<div className="airygen-module-page__tab flex flex-wrap gap-2" data-airygen-e2e="tabs-module-page">
				<button
					type="button"
					data-airygen-e2e="tab-main-store"
					className={
						'mainStore' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'mainStore' ) }
				>
					{ __( 'Main store', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-branches"
					className={
						'branches' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'branches' ) }
				>
					{ __( 'Branches', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-layout"
					className={
						'layout' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'layout' ) }
				>
					{ __( 'Layout', 'airygen-seo' ) }
				</button>
				<button
					type="button"
					data-airygen-e2e="tab-preview"
					className={
						'preview' === activeTab
							? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
							: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
					}
					onClick={ () => setActiveTab( 'preview' ) }
				>
					{ __( 'Preview', 'airygen-seo' ) }
				</button>
			</div>

			{ 'mainStore' === activeTab ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Main store', 'airygen-seo' ) }
							</div>
						</div>
						<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<p className="text-sm font-medium text-slate-900">
									{ __( 'Enable Local SEO output', 'airygen-seo' ) }
								</p>
								<Toggle
									label={ __( 'Enable Local SEO output', 'airygen-seo' ) }
									hideLabelText
									checked={ settings.enabled }
									onChange={ ( value ) => updateSettings( { enabled: value } ) }
								/>
							</div>
							<p className="text-xs text-slate-500">
								{ __( 'Master switch for LocalBusiness schema, geo tags, and Local SEO shortcodes.', 'airygen-seo' ) }
							</p>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 md:w-1/2">
								<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-unified-shortcode">
									{ __( 'Unified shortcode', 'airygen-seo' ) }
								</label>
								<div className="mt-2 flex items-center gap-2">
									<code
										id="local-seo-unified-shortcode"
										className="block w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
									>
										[airygen_localseo]
									</code>
									<Button variant="secondary" className="text-xs" onClick={ copyUnifiedShortcode }>
										{ unifiedShortcodeCopied
											? __( 'Copied', 'airygen-seo' )
											: __( 'Copy', 'airygen-seo' ) }
									</Button>
								</div>
								<p className="mt-2 text-xs text-slate-500">
									{ __(
										'Place this shortcode in a page to automatically generate Contact content and Local Business Schema.',
										'airygen-seo',
									) }
								</p>
							</div>
						</div>
						<div className="grid gap-4 md:grid-cols-2">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4 order-2">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Enable geo tags', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Enable geo tags', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.enableGeoTags }
										onChange={ ( value ) => updateSettings( { enableGeoTags: value } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Output optional geo meta tags for region, place name, and coordinates.', 'airygen-seo' ) }
								</p>
								<div className="mt-2 grid gap-4 lg:grid-cols-2">
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Geo region code', 'airygen-seo' ) }
											help={ __( 'Example: TW-TPE', 'airygen-seo' ) }
											value={ settings.geoRegionCode }
											onChange={ ( value ) => updateSettings( { geoRegionCode: value } ) }
											disabled={ ! settings.enableGeoTags }
										/>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Geo place name', 'airygen-seo' ) }
											help={ __( 'Example: Taipei', 'airygen-seo' ) }
											value={ settings.geoPlacename }
											onChange={ ( value ) => updateSettings( { geoPlacename: value } ) }
											disabled={ ! settings.enableGeoTags }
										/>
									</div>
								</div>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4 order-3">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Footer NAP', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Footer NAP', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.footerNapEnabled }
										onChange={ ( value ) =>
											updateSettings( { footerNapEnabled: value } )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Output Name, Address, Phone in the site footer.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Business Info', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Make sure every value exactly matches your Google Business Profile details, character for character.', 'airygen-seo' ) }
							</p>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Profile', 'airygen-seo' ) }</div>
							<div className="mt-3 grid gap-4 lg:grid-cols-4">
								<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
									<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-type">
										{ __( 'Business type', 'airygen-seo' ) }
									</label>
									<select
										id="local-seo-type"
										className="airygen-field-select mt-2 w-full"
										value={ settings.businessType }
										onChange={ ( event ) =>
											updateSettings( {
												businessType: event.currentTarget.value,
											} )
										}
									>
										{ LOCAL_BUSINESS_TYPES.map( ( type ) => (
											<option key={ type } value={ type }>
												{ type }
											</option>
										) ) }
									</select>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Choose the schema type that best matches your business category.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Business name', 'airygen-seo' ) }
										help={ __( 'Use your public business name shown on storefronts and listings.', 'airygen-seo' ) }
										value={ settings.businessName }
										onChange={ ( value ) => updateSettings( { businessName: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Legal name', 'airygen-seo' ) }
										help={ __( 'Registered legal entity name (for example, company registration name).', 'airygen-seo' ) }
										value={ settings.legalName }
										onChange={ ( value ) => updateSettings( { legalName: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Phone', 'airygen-seo' ) }
										help={ __( 'Primary contact number for customers. Include country/area code when possible.', 'airygen-seo' ) }
										value={ settings.phone }
										onChange={ ( value ) => updateSettings( { phone: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Image URL', 'airygen-seo' ) }
										help={ __( 'Representative photo used in LocalBusiness schema output.', 'airygen-seo' ) }
										value={ settings.imageUrl }
										isUrl
										onChange={ ( value ) => updateSettings( { imageUrl: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Logo URL', 'airygen-seo' ) }
										help={ __( 'Logo image used for schema logo ImageObject output.', 'airygen-seo' ) }
										value={ settings.logoUrl }
										isUrl
										onChange={ ( value ) => updateSettings( { logoUrl: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Tax ID', 'airygen-seo' ) }
										help={ __( 'If available, enter your official tax ID to improve search engine trust signals.', 'airygen-seo' ) }
										value={ settings.vatId }
										onChange={ ( value ) => updateSettings( { vatId: value } ) }
									/>
								</div>
							</div>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Address', 'airygen-seo' ) }</div>
							<div className="mt-3 grid gap-4 lg:grid-cols-4">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-country">
										{ __( 'Country code', 'airygen-seo' ) }
									</label>
									<select
										id="local-seo-country"
										className="airygen-field-select mt-2 w-full"
										value={ settings.country }
										onChange={ ( event ) =>
											updateSettings( {
												country: sanitizeCountryCodeInput( event.currentTarget.value ),
											} )
										}
									>
										<option value="">{ __( 'Select a country', 'airygen-seo' ) }</option>
										{ countryOptions.map( ( option ) => (
											<option key={ `country-option-${ option.code }` } value={ option.code }>
												{ `${ option.label } (${ option.code })` }
											</option>
										) ) }
									</select>
									<p className="mt-1 text-xs text-slate-500">
										{ sprintf(
											/* translators: %s is the schema property name. */
											__( 'Used for schema %s.', 'airygen-seo' ),
											'addressCountry (ISO-3166 Alpha-2)',
										) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'City', 'airygen-seo' ) }
										help={ __( 'City (locality) for your business address.', 'airygen-seo' ) }
										value={ settings.city }
										onChange={ ( value ) => updateSettings( { city: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Region / State', 'airygen-seo' ) }
										help={ __( 'State, province, or administrative region.', 'airygen-seo' ) }
										value={ settings.region }
										onChange={ ( value ) => updateSettings( { region: value } ) }
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Postal code', 'airygen-seo' ) }
										help={ __( 'ZIP or postal code for the street address.', 'airygen-seo' ) }
										value={ settings.postalCode }
										onChange={ ( value ) => updateSettings( { postalCode: value } ) }
									/>
								</div>
							</div>
							<div className="mt-4">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Street address', 'airygen-seo' ) }
										help={ __( 'Street and number for your primary location.', 'airygen-seo' ) }
										value={ settings.streetAddress }
										onChange={ ( value ) => updateSettings( { streetAddress: value } ) }
									/>
								</div>
							</div>
						</div>

					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Map', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Set map coordinates and contact-page map behavior for Local SEO map output.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="rounded-lg border border-slate-200 p-4">
							<div className="flex items-center justify-between gap-3">
								<div className="airygen_h3_title">{ __( 'Coordinates', 'airygen-seo' ) }</div>
								<Button variant="secondary" className="text-xs" onClick={ () => openMapUrlModal( 'mainStore' ) }>
									{ __( 'Use Google Maps URL', 'airygen-seo' ) }
								</Button>
							</div>
							<div className="mt-3 grid gap-4 lg:grid-cols-3">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Latitude', 'airygen-seo' ) }
										help={ __( 'Decimal latitude coordinate used for geo metadata and map output.', 'airygen-seo' ) }
										type="number"
										step="0.000001"
										value={ String( settings.latitude ) }
										onChange={ ( value ) =>
											updateSettings( { latitude: Number( value ) || 0 } )
										}
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Longitude', 'airygen-seo' ) }
										help={ __( 'Decimal longitude coordinate used for geo metadata and map output.', 'airygen-seo' ) }
										type="number"
										step="0.000001"
										value={ String( settings.longitude ) }
										onChange={ ( value ) =>
											updateSettings( { longitude: Number( value ) || 0 } )
										}
									/>
								</div>
								<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
									<div className="flex items-center justify-between gap-3">
										<p className="text-sm font-medium text-slate-900">
											{ __( 'Include KML in sitemap', 'airygen-seo' ) }
										</p>
										<Toggle
											label={ __( 'Include KML in sitemap', 'airygen-seo' ) }
											hideLabelText
											checked={ settings.kmlInSitemap }
											disabled={ ! canEnableMapFeatures }
											onChange={ ( value ) =>
												updateSettings( { kmlInSitemap: value } )
											}
										/>
									</div>
									<p className="text-xs text-slate-500">
										{ canEnableMapFeatures
											? __( 'Controls whether /local.kml is listed in sitemap.xml. This KML file helps search engines understand your business location data.', 'airygen-seo' )
											: __( 'Set valid latitude and longitude (both cannot be 0) to enable KML sitemap listing.', 'airygen-seo' ) }
									</p>
								</div>
							</div>
						</div>
					</section>

					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title">
								{ __( 'Service Info', 'airygen-seo' ) }
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Manage service catalog, service areas, hours, trust signals, and service visibility settings.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Pricing', 'airygen-seo' ) }</div>
							<div className="mt-3 grid gap-4 lg:grid-cols-2">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-price-level">
										{ __( 'Price level', 'airygen-seo' ) }
									</label>
									<select
										id="local-seo-price-level"
										className="airygen-field-select mt-2 w-full"
										value={ settings.priceRangeLevel }
										onChange={ ( event ) =>
											updateSettings( {
												priceRangeLevel:
													event.currentTarget.value === '$' ||
													event.currentTarget.value === '$$' ||
													event.currentTarget.value === '$$$' ||
													event.currentTarget.value === '$$$$'
														? event.currentTarget.value
														: '$$',
											} )
										}
									>
										<option value="$">{ __( 'Budget ($)', 'airygen-seo' ) }</option>
										<option value="$$">{ __( 'Moderate ($$)', 'airygen-seo' ) }</option>
										<option value="$$$">{ __( 'Premium ($$$)', 'airygen-seo' ) }</option>
										<option value="$$$$">{ __( 'Luxury ($$$$)', 'airygen-seo' ) }</option>
									</select>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Recommended format for Google priceRange output.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Custom price text', 'airygen-seo' ) }
										help={ __( 'Optional. If filled, this overrides price level (example: TWD 200 – 500).', 'airygen-seo' ) }
										value={ settings.priceRangeCustom }
										onChange={ ( value ) => updateSettings( { priceRangeCustom: value } ) }
									/>
								</div>
							</div>
							<div className="mt-4">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-same-as-urls">
										{ __( 'SameAs URLs', 'airygen-seo' ) }
									</label>
									<textarea
										id="local-seo-same-as-urls"
										rows={ 5 }
										className="airygen-field mt-2 w-full"
										value={ serializeLineItems( settings.sameAsUrls ) }
										onChange={ ( event ) =>
											updateSettings( {
												sameAsUrls: parseTextareaLineItems(
													event.currentTarget.value,
												),
											} )
										}
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'One social/profile URL per line (Facebook, Instagram, Yelp, etc.).', 'airygen-seo' ) }
									</p>
								</div>
							</div>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Reviews', 'airygen-seo' ) }</div>
							<div className="mt-3 grid gap-4 lg:grid-cols-2">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Rating value', 'airygen-seo' ) }
										help={ __( 'Aggregate rating value (0–5). Requires review count to output schema.', 'airygen-seo' ) }
										type="number"
										step="0.1"
										value={ String( settings.ratingValue ) }
										onChange={ ( value ) =>
											updateSettings( {
												ratingValue: Math.max( 0, Math.min( 5, Number( value ) || 0 ) ),
											} )
										}
									/>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Review count', 'airygen-seo' ) }
										help={ __( 'Total number of reviews for aggregateRating schema output.', 'airygen-seo' ) }
										type="number"
										step="1"
										value={ String( settings.reviewCount ) }
										onChange={ ( value ) =>
											updateSettings( {
												reviewCount: Math.max( 0, Math.floor( Number( value ) || 0 ) ),
											} )
										}
									/>
								</div>
							</div>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Service Areas', 'airygen-seo' ) }</div>
							<p className="mt-1 text-xs text-slate-500">
								{ __( 'Define served cities, postal codes, and optional radius for areaServed schema.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-4 lg:grid-cols-3">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-service-area-cities">
										{ __( 'Served cities', 'airygen-seo' ) }
									</label>
									<textarea
										id="local-seo-service-area-cities"
										rows={ 6 }
										className="airygen-field mt-2 w-full"
										value={ serializeLineItems( settings.serviceAreaCities ) }
										onChange={ ( event ) =>
											updateSettings( {
												serviceAreaCities: parseTextareaLineItems(
													event.currentTarget.value,
												),
											} )
										}
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'One city per line. Output as @type: City.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<label className="block text-sm font-medium text-gray-800" htmlFor="local-seo-service-area-postal-codes">
										{ __( 'Served postal codes', 'airygen-seo' ) }
									</label>
									<textarea
										id="local-seo-service-area-postal-codes"
										rows={ 6 }
										className="airygen-field mt-2 w-full"
										value={ serializeLineItems( settings.serviceAreaPostalCodes ) }
										onChange={ ( event ) =>
											updateSettings( {
												serviceAreaPostalCodes: parseTextareaLineItems(
													event.currentTarget.value,
												),
											} )
										}
									/>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'One postal code per line. Output as @type: PostalCode.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Service radius (km)', 'airygen-seo' ) }
										help={ __( 'GeoCircle radius. Uses the Map latitude/longitude as center point.', 'airygen-seo' ) }
										type="number"
										step="0.1"
										value={ String( settings.serviceAreaRadiusKm ) }
										onChange={ ( value ) =>
											updateSettings( {
												serviceAreaRadiusKm: Math.max(
													0,
													Number( value ) || 0,
												),
											} )
										}
									/>
								</div>
							</div>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Service Catalog', 'airygen-seo' ) }</div>
							<p className="mt-1 text-xs text-slate-500">
								{ __( 'Define the services list for hasOfferCatalog in LocalBusiness schema.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 space-y-4">
								<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
									<Input
										label={ __( 'Catalog name', 'airygen-seo' ) }
										help={ __( 'Example: Plumbing and Home Repair Services', 'airygen-seo' ) }
										value={ settings.serviceCatalogName }
										onChange={ ( value ) =>
											updateSettings( { serviceCatalogName: value } )
										}
									/>
								</div>
								<div className="space-y-3">
									{ settings.serviceCatalogItems.map( ( item, index ) => (
										<div
											key={ `service-catalog-item-${ index }` }
											className="rounded-lg border border-slate-200 p-4"
										>
											<div className="mb-3 flex items-center justify-between gap-2">
												<p className="text-sm font-medium text-slate-900">
													{ __( 'Service item', 'airygen-seo' ) } { index + 1 }
												</p>
												<Button
													variant="secondary"
													className="text-xs"
													onClick={ () => removeServiceCatalogItem( index ) }
												>
													{ __( 'Remove', 'airygen-seo' ) }
												</Button>
											</div>
											<div className="grid gap-4 md:grid-cols-2">
												<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
													<Input
														label={ __( 'Service name', 'airygen-seo' ) }
														help={ __( 'Example: Emergency plumbing', 'airygen-seo' ) }
														value={ item.name }
														onChange={ ( value ) =>
															updateServiceCatalogItem( index, { name: value } )
														}
													/>
												</div>
												<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
													<Input
														label={ __( 'Service description', 'airygen-seo' ) }
														help={ __( 'Short service summary for schema output.', 'airygen-seo' ) }
														value={ item.description }
														onChange={ ( value ) =>
															updateServiceCatalogItem( index, {
																description: value,
															} )
														}
													/>
												</div>
											</div>
										</div>
									) ) }
									<Button variant="secondary" className="text-xs" onClick={ addServiceCatalogItem }>
										{ __( 'Add service item', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Opening Hours', 'airygen-seo' ) }</div>
							<p className="mt-1 text-xs text-slate-500">
								{ __( 'Configure daily opening intervals for schema output.', 'airygen-seo' ) }
							</p>
							<div className="mt-3 grid gap-3 md:grid-cols-2">
								{ DAY_CODES.map( ( dayCode ) => {
									const daySchedule = openingHoursByDay[ dayCode ];
									const openPercent =
										( daySchedule.openMinutes / OPENING_HOURS_MAX_MINUTES ) * 100;
									const closePercent =
										( daySchedule.closeMinutes / OPENING_HOURS_MAX_MINUTES ) * 100;

									return (
										<div
											key={ dayCode }
											className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4"
										>
											<div className="flex items-center justify-between gap-3">
												<p className="text-sm font-medium text-slate-900">
													{ dayLabels[ dayCode ] }
												</p>
												<Toggle
													label={ dayLabels[ dayCode ] }
													hideLabelText
													checked={ daySchedule.enabled }
													onChange={ ( value ) =>
														updateDaySchedule( dayCode, { enabled: value } )
													}
												/>
											</div>
											<p className="text-xs text-slate-500">
												{ __( 'Enable this day and adjust open/close time.', 'airygen-seo' ) }
											</p>
											{ daySchedule.enabled ? (
												<div className="space-y-3">
													<div className="flex items-center justify-between text-xs text-slate-600">
														<span>
															{ __( 'Open', 'airygen-seo' ) }: { daySchedule.is24Hours ? '00:00' : toOpeningHourString( daySchedule.openMinutes ) }
														</span>
														<span>
															{ __( 'Close', 'airygen-seo' ) }: { daySchedule.is24Hours ? '23:59' : toOpeningHourString( daySchedule.closeMinutes ) }
														</span>
													</div>
													<div className="flex items-center gap-3">
														<div className="relative h-8 flex-1">
															<div className="absolute top-1/2 h-1.5 w-full -translate-y-1/2 rounded-full bg-slate-200" />
															{ ! daySchedule.is24Hours ? (
																<>
																	<div
																		className="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-sky-500"
																		style={ {
																			left: `${ openPercent }%`,
																			right: `${ 100 - closePercent }%`,
																		} }
																	/>
																	<input
																		type="range"
																		min={ 0 }
																		max={ OPENING_HOURS_MAX_MINUTES }
																		step={ OPENING_HOURS_STEP_MINUTES }
																		value={ daySchedule.openMinutes }
																		onChange={ ( event ) =>
																			updateDaySchedule( dayCode, {
																				openMinutes: Number( event.currentTarget.value ),
																			} )
																		}
																		className="pointer-events-none absolute inset-0 m-0 h-8 w-full cursor-pointer appearance-none rounded-full bg-transparent accent-sky-500 [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:mt-[-3px] [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border [&::-webkit-slider-thumb]:border-sky-500 [&::-webkit-slider-thumb]:bg-sky-500 [&::-moz-range-track]:h-1.5 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-3 [&::-moz-range-thumb]:w-3 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border [&::-moz-range-thumb]:border-sky-500 [&::-moz-range-thumb]:bg-sky-500"
																	/>
																	<input
																		type="range"
																		min={ 0 }
																		max={ OPENING_HOURS_MAX_MINUTES }
																		step={ OPENING_HOURS_STEP_MINUTES }
																		value={ daySchedule.closeMinutes }
																		onChange={ ( event ) =>
																			updateDaySchedule( dayCode, {
																				closeMinutes: Number( event.currentTarget.value ),
																			} )
																		}
																		className="pointer-events-none absolute inset-0 m-0 h-8 w-full cursor-pointer appearance-none rounded-full bg-transparent accent-sky-500 [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:mt-[-3px] [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border [&::-webkit-slider-thumb]:border-sky-500 [&::-webkit-slider-thumb]:bg-sky-500 [&::-moz-range-track]:h-1.5 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-3 [&::-moz-range-thumb]:w-3 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border [&::-moz-range-thumb]:border-sky-500 [&::-moz-range-thumb]:bg-sky-500"
																	/>
																</>
															) : null }
														</div>
														<label
															htmlFor={ `main-store-opening-hours-${ dayCode }-24h` }
															className="flex items-center gap-2 whitespace-nowrap text-xs text-slate-700"
														>
															<input
																id={ `main-store-opening-hours-${ dayCode }-24h` }
																type="checkbox"
																className="h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500"
																checked={ daySchedule.is24Hours }
																onChange={ ( event ) =>
																	updateDaySchedule( dayCode, {
																		is24Hours: event.currentTarget.checked,
																	} )
																}
															/>
															{ `24 ${ __( 'hours', 'airygen-seo' ) }` }
														</label>
													</div>
												</div>
											) : null }
										</div>
									);
								} ) }
							</div>
						</div>

						<div className="rounded-lg border border-slate-200 p-4">
							<div className="airygen_h3_title">{ __( 'Special Hours', 'airygen-seo' ) }</div>
							<p className="mt-1 text-xs text-slate-500">
								{ __( 'Configure one rule per line for special opening hours.', 'airygen-seo' ) }
							</p>
							<div className="mt-3">
								<SpecialHoursRulesEditor
									value={ settings.specialHours }
									onChange={ ( next ) => updateSettings( { specialHours: next } ) }
									inputIdPrefix="main-special-hours"
								/>
							</div>
						</div>
					</section>

				</>
			) : null }

			{ 'layout' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="flex items-start justify-between gap-3">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2">
								{ __( 'Layout', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'Contact page', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Choose sidebar/content layout, then arrange blocks in the 5x15 canvas.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="flex items-center gap-2">
							<label className="sr-only" htmlFor="local-seo-layout-rows">
								{ __( 'Canvas rows', 'airygen-seo' ) }
							</label>
							<select
								id="local-seo-layout-rows"
								className="airygen-field-select"
								style={ { width: '100px' } }
								value={ String( layoutCanvasRows ) }
								onChange={ ( event ) => {
									const nextRows = Number( event.currentTarget.value );
									if ( Number.isNaN( nextRows ) ) {
										return;
									}
									setLayoutCanvasRows(
										Math.max( minSelectableLayoutRows, Math.min( LOCAL_SEO_LAYOUT_ROWS, nextRows ) ),
									);
								} }
							>
								{ Array.from( { length: LOCAL_SEO_LAYOUT_ROWS - 6 }, ( _, index ) => {
									const rows = index + 7;
									return (
										<option
											key={ `layout-rows-${ rows }` }
											value={ String( rows ) }
											disabled={ rows < minSelectableLayoutRows }
										>
											{ `5 x ${ rows }` }
										</option>
									);
								} ) }
							</select>
						</div>
					</div>
					<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
						<p className="block text-sm font-medium text-gray-800">
							{ __( 'Layout template', 'airygen-seo' ) }
						</p>
						<div className="mt-3 grid grid-cols-4 gap-3">
							{ LOCAL_SEO_LAYOUT_TEMPLATE_OPTIONS.map( ( option ) => {
								const isActive = settings.layoutTemplate === option.value;
								const hasHeader = templateHasHeader( option.value );
								const isLeftSidebarTemplate = isSidebarLeftTemplate( option.value );
								const showLeftSidebarDiagram = isLeftSidebarTemplate;
								return (
									<button
										key={ option.value }
										type="button"
										className={
											`rounded-lg border p-3 text-left transition ${
												isActive
													? 'border-sky-500 bg-sky-50'
													: 'border-slate-200 bg-white hover:border-slate-300'
											}`
										}
										onClick={ () => updateLayoutTemplate( option.value ) }
										aria-pressed={ isActive }
										aria-label={ option.label }
									>
										<span className="sr-only">{ option.label }</span>
										<div className="mt-2 rounded-md border border-slate-200 bg-slate-50 p-2">
											{ hasHeader ? (
												<div
													className="mx-auto mb-2 flex h-6 items-center justify-center rounded bg-slate-300 text-[11px] font-medium uppercase tracking-wide text-slate-700"
													style={ { width: '120px' } }
												>
													5
												</div>
											) : null }
											<div
												className="mx-auto flex gap-2"
												style={ {
													width: '120px',
													height: hasHeader ? '56px' : '80px',
												} }
											>
												<div
													className="flex items-center justify-center rounded bg-slate-200 text-[11px] font-medium uppercase tracking-wide text-slate-600"
													style={ { width: showLeftSidebarDiagram ? '45px' : '67px' } }
												>
													{ showLeftSidebarDiagram ? 2 : 3 }
												</div>
												<div
													className="flex items-center justify-center rounded bg-slate-300 text-[11px] font-medium uppercase tracking-wide text-slate-700"
													style={ { width: showLeftSidebarDiagram ? '67px' : '45px' } }
												>
													{ showLeftSidebarDiagram ? 3 : 2 }
												</div>
											</div>
										</div>
									</button>
								);
							} ) }
						</div>
						<p className="mt-1 text-xs text-slate-500">
							{ __( 'Sidebar and main content use independent top-to-bottom stacks to avoid cross-column height impact.', 'airygen-seo' ) }
						</p>
					</div>
					<div
						className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3"
						onDragOver={ handleLayoutDragOver }
						onDrop={ handleLayoutDropOnHidden }
					>
						<div className="relative">
							<div
								className="pointer-events-none absolute inset-0 z-0 grid grid-cols-5 gap-3"
								style={ {
									gridTemplateRows: `repeat(${ layoutCanvasRows }, minmax(0, 128px))`,
								} }
							>
								{ templateHasHeader( settings.layoutTemplate ) ? (
									<div
										className="border-b-2 border-dashed border-emerald-400"
										style={ {
											gridColumn: '1 / span 5',
											gridRow: '1 / span 1',
											width: 'calc(100% + 12px)',
											height: 'calc(100% + 13px)',
											marginLeft: '-6px',
											marginTop: '-6px',
										} }
									/>
								) : null }
								<div
									className={
										`border-dashed border-sky-400 ${
											isSidebarLeftTemplate( settings.layoutTemplate )
												? 'border-r-2'
												: 'border-l-2'
										}`
									}
									style={ {
										gridColumn: `${
											isSidebarLeftTemplate( settings.layoutTemplate ) ? 1 : 4
										} / span 2`,
										gridRow: `${ templateHasHeader( settings.layoutTemplate ) ? 2 : 1 } / span ${
											layoutCanvasRows -
											( templateHasHeader( settings.layoutTemplate ) ? 1 : 0 )
										}`,
										width: 'calc(100% + 13px)',
										height: 'calc(100% + 12px)',
										marginLeft: '-6px',
										marginTop: '-6px',
									} }
								/>
								<div
									className=""
									style={ {
										gridColumn: `${
											isSidebarLeftTemplate( settings.layoutTemplate ) ? 3 : 1
										} / span 3`,
										gridRow: `${ templateHasHeader( settings.layoutTemplate ) ? 2 : 1 } / span ${
											layoutCanvasRows -
											( templateHasHeader( settings.layoutTemplate ) ? 1 : 0 )
										}`,
										width: 'calc(100% + 12px)',
										height: 'calc(100% + 12px)',
										marginLeft: '-6px',
										marginTop: '-6px',
									} }
								/>
							</div>
							<div
								className="relative z-10 grid grid-cols-5 gap-3"
								style={ {
									gridTemplateRows: `repeat(${ layoutCanvasRows }, minmax(0, 128px))`,
								} }
							>
								{ Array.from( { length: layoutCanvasRows * LOCAL_SEO_LAYOUT_COLS }, ( _, index ) => {
									const row = Math.floor( index / LOCAL_SEO_LAYOUT_COLS ) + 1;
									const col = ( index % LOCAL_SEO_LAYOUT_COLS ) + 1;
									const occupied = layoutOccupiedMap.has( `${ row }-${ col }` );
									return (
										<div
											key={ `layout-cell-${ row }-${ col }` }
											className={
												`h-[128px] rounded-md border border-dashed ${
													occupied ? 'border-slate-300 bg-slate-100' : 'border-slate-300 bg-white'
												}`
											}
											onDragOver={ handleLayoutDragOver }
											onDrop={ ( event ) => handleLayoutDrop( event, row, col ) }
										/>
									);
								} ) }
							</div>
							<div
								className="pointer-events-none absolute inset-0 z-20 grid grid-cols-5 gap-3"
								style={ {
									gridTemplateRows: `repeat(${ layoutCanvasRows }, minmax(0, 128px))`,
								} }
							>
								{ normalizedLayoutGrid.map( ( item, index ) => {
									const block = LOCAL_SEO_LAYOUT_BLOCKS.find(
										( blockItem ) => blockItem.id === item.blockId,
									);
									if ( ! block ) {
										return null;
									}
									const maxSpan = getLayoutMaxSpan( item.col );
									const maxRowSpan = getLayoutMaxRowSpan( item.row );

									return (
										<div
											key={ `layout-card-${ block.id }` }
											className="pointer-events-auto relative box-border flex min-h-[128px] cursor-grab flex-col justify-between overflow-hidden rounded-md border border-slate-300 bg-white p-3 shadow-sm active:cursor-grabbing"
											style={ {
												gridColumn: `${ item.col } / span ${ item.span }`,
												gridRow: `${ item.row } / span ${ item.rowSpan }`,
											} }
											draggable
											onDragStart={ ( event ) => handleLayoutDragStart( event, block.id ) }
											onDragEnd={ handleLayoutDragEnd }
											onDragOver={ handleLayoutDragOver }
											onDrop={ ( event ) => handleLayoutDropOnCard( event, block.id ) }
										>
											<button
												type="button"
												className="absolute right-2 top-2 z-10 flex h-4 w-4 items-center justify-center rounded-sm text-slate-400 hover:bg-slate-100 hover:text-slate-700"
												onClick={ ( event ) => {
													event.preventDefault();
													event.stopPropagation();
													hideLayoutBlock( block.id );
												} }
												aria-label={ `${ __( 'Hide', 'airygen-seo' ) } ${ block.label }` }
											>
												<span className="dashicons dashicons-no-alt m-0 block h-[14px] w-[14px] text-[14px] leading-[14px]" />
											</button>
											<div className="flex items-start gap-3">
												<div className="flex flex-col gap-1 pt-0.5">
													{ Array.from( { length: 5 }, ( _, rowSpanIndex ) => {
														const rowSpanValue = rowSpanIndex + 1;
														const selectable =
															rowSpanValue <= maxRowSpan &&
															canPlaceLayoutBlock(
																block.id,
																item.row,
																item.col,
																item.span,
																rowSpanValue,
															);
														const active = rowSpanValue <= item.rowSpan;
														let spanClassName =
																'border-slate-300 bg-white hover:border-emerald-300';
														if ( ! selectable ) {
															spanClassName =
																	'cursor-not-allowed border-slate-200 bg-slate-200';
														} else if ( active ) {
															spanClassName = 'border-emerald-400 bg-emerald-400';
														}
														return (
															<button
																key={ `row-span-${ block.id }-${ rowSpanValue }` }
																type="button"
																className={ `h-3 w-3 rounded-sm border ${ spanClassName }` }
																onClick={ () =>
																	updateLayoutBlockRowSpan( block.id, rowSpanValue )
																}
																disabled={ ! selectable }
																aria-label={ `${ block.label } row span ${ rowSpanValue }` }
															/>
														);
													} ) }
												</div>
												<div className="min-w-0 flex-1">
													<p className="text-sm font-medium text-slate-900">{ block.label }</p>
													<p className="mt-1 text-[11px] text-slate-500">{ block.description }</p>
													{ ! layoutBlockConfiguredMap[ block.id ] ? (
														<p className="mt-1 text-[11px] font-medium text-amber-600">
															{ __( 'Not configured', 'airygen-seo' ) }
														</p>
													) : null }
												</div>
											</div>
											<div className="flex items-end justify-between gap-2">
												<p className="text-[11px] font-medium uppercase tracking-wide text-slate-400">
													{ `${ __( 'Order', 'airygen-seo' ) } ${ index + 1 }` }
												</p>
												<div className="flex items-center gap-1">
													{ Array.from( { length: LOCAL_SEO_LAYOUT_COLS }, ( _, spanIndex ) => {
														const spanValue = spanIndex + 1;
														const selectable =
															spanValue <= maxSpan &&
															canPlaceLayoutBlock(
																block.id,
																item.row,
																item.col,
																spanValue,
																item.rowSpan,
															);
														const active = spanValue <= item.span;
														let spanClassName =
																'border-slate-300 bg-white hover:border-sky-300';
														if ( ! selectable ) {
															spanClassName =
																	'cursor-not-allowed border-slate-200 bg-slate-200';
														} else if ( active ) {
															spanClassName = 'border-sky-400 bg-sky-400';
														}
														return (
															<button
																key={ `span-${ block.id }-${ spanValue }` }
																type="button"
																className={ `h-3 w-3 rounded-sm border ${ spanClassName }` }
																onClick={ () =>
																	updateLayoutBlockSpan(
																		block.id,
																		spanValue === item.span
																			? Math.max( 1, spanValue - 1 )
																			: spanValue,
																	)
																}
																disabled={ ! selectable }
																aria-label={ `${ block.label } span ${ spanValue }` }
															/>
														);
													} ) }
												</div>
											</div>
										</div>
									);
								} ) }
							</div>
						</div>
					</div>
					<div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
						<div data-airygen-e2e="hidden-blocks-local-seo-contact-page">
							<div className="mb-2">
								<p className="text-xs font-medium uppercase tracking-wide text-slate-500">
									{ __( 'Hidden blocks', 'airygen-seo' ) }
								</p>
							</div>
							{ hiddenLayoutBlocks.length > 0 ? (
								<div
									className="grid gap-2 md:grid-cols-4"
									onDragOver={ handleLayoutDragOver }
									onDrop={ handleLayoutDropOnHidden }
								>
									{ hiddenLayoutBlocks.map( ( block ) => {
										const canShow = null !== findFirstAvailableLayoutSlot();
										return (
											<div
												key={ `layout-hidden-${ block.id }` }
												className="rounded-md border border-slate-200 bg-white p-3"
												onDragOver={ handleLayoutDragOver }
												onDrop={ handleLayoutDropOnHidden }
											>
												<p className="text-sm font-medium text-slate-900">{ block.label }</p>
												<p className="mt-1 text-xs text-slate-500">{ block.description }</p>
												{ ! layoutBlockConfiguredMap[ block.id ] ? (
													<p className="mt-1 text-xs font-medium text-amber-600">
														{ __( 'Not configured', 'airygen-seo' ) }
													</p>
												) : null }
												<div className="mt-3">
													<Button
														variant="secondary"
														className="text-xs"
														onClick={ () => showLayoutBlock( block.id ) }
														disabled={ ! canShow }
													>
														{ __( 'Add to display', 'airygen-seo' ) }
													</Button>
												</div>
											</div>
										);
									} ) }
								</div>
							) : (
								<p className="text-xs text-slate-500">
									{ __( 'No hidden blocks.', 'airygen-seo' ) }
								</p>
							) }
						</div>
					</div>
					<p className="text-xs text-slate-500">
						{ __( 'Drag cards to target cells. Bottom squares control width span. Left vertical squares control row span from top to bottom.', 'airygen-seo' ) }
					</p>
					<div className="rounded-lg border border-slate-200 p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2">
								{ __( 'Layout', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'NAP', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Drag cards to reorder Footer NAP output. Drop into hidden blocks to hide a field.', 'airygen-seo' ) }
							</p>
						</div>
						<div
							className="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3"
							data-airygen-e2e="layout-canvas-local-seo-footer-nap"
							onDragOver={ handleFooterNapDragOver }
							onDrop={ ( event ) => handleFooterNapDropOnVisible( event ) }
						>
							<p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
								{ __( 'Layout area', 'airygen-seo' ) }
							</p>
							<div className="grid gap-2 md:grid-cols-3">
								{ footerNapVisibleOrder.map( ( blockId ) => {
									const block = FOOTER_NAP_LAYOUT_BLOCKS.find( ( item ) => item.id === blockId );
									if ( ! block ) {
										return null;
									}
									return (
										<div
											key={ `footer-nap-visible-${ block.id }` }
											draggable
											onDragStart={ ( event ) => handleFooterNapDragStart( event, block.id ) }
											onDragEnd={ handleFooterNapDragEnd }
											onDragOver={ handleFooterNapDragOver }
											onDrop={ ( event ) => handleFooterNapDropOnVisible( event, block.id ) }
											className="relative cursor-grab rounded-md border border-slate-300 bg-white p-3 active:cursor-grabbing"
										>
											<button
												type="button"
												className="absolute right-2 top-2 z-10 flex h-4 w-4 items-center justify-center rounded-sm text-slate-400 hover:bg-slate-100 hover:text-slate-700"
												onClick={ ( event ) => {
													event.preventDefault();
													event.stopPropagation();
													hideFooterNapBlock( block.id );
												} }
												aria-label={ `${ __( 'Hide', 'airygen-seo' ) } ${ block.label }` }
											>
												<span className="dashicons dashicons-no-alt m-0 block h-[14px] w-[14px] text-[14px] leading-[14px]" />
											</button>
											<p className="text-sm font-medium text-slate-900">{ block.label }</p>
											<p className="mt-1 text-xs text-slate-500">{ block.description }</p>
											{ ! footerNapBlockConfiguredMap[ block.id ] ? (
												<p className="mt-1 text-xs font-medium text-amber-600">
													{ __( 'Not configured', 'airygen-seo' ) }
												</p>
											) : null }
										</div>
									);
								} ) }
							</div>
						</div>
						<div
							className="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3"
							data-airygen-e2e="hidden-blocks-local-seo-footer-nap"
							onDragOver={ handleFooterNapDragOver }
							onDrop={ handleFooterNapDropOnHidden }
						>
							<p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
								{ __( 'Hidden blocks', 'airygen-seo' ) }
							</p>
							{ footerNapHiddenBlocks.length > 0 ? (
								<div className="grid gap-2 md:grid-cols-4">
									{ footerNapHiddenBlocks.map( ( block ) => (
										<div
											key={ `footer-nap-hidden-${ block.id }` }
											draggable
											onDragStart={ ( event ) => handleFooterNapDragStart( event, block.id ) }
											onDragEnd={ handleFooterNapDragEnd }
											className="rounded-md border border-slate-200 bg-white p-3"
										>
											<p className="text-sm font-medium text-slate-900">{ block.label }</p>
											<p className="mt-1 text-xs text-slate-500">{ block.description }</p>
											{ ! footerNapBlockConfiguredMap[ block.id ] ? (
												<p className="mt-1 text-xs font-medium text-amber-600">
													{ __( 'Not configured', 'airygen-seo' ) }
												</p>
											) : null }
											<div className="mt-2">
												<Button
													variant="secondary"
													className="text-xs"
													onClick={ () => showFooterNapBlock( block.id ) }
												>
													{ __( 'Add to display', 'airygen-seo' ) }
												</Button>
											</div>
										</div>
									) ) }
								</div>
							) : (
								<p className="text-xs text-slate-500">
									{ __( 'No hidden blocks.', 'airygen-seo' ) }
								</p>
							) }
						</div>
					</div>
					<div className="rounded-lg border border-slate-200 p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2">
								{ __( 'Style', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'Contact page', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Customize contact card appearance for preview and shortcode output.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="mt-3 grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4 order-3">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Outline border', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Outline border', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.layoutShowCardBorder }
										onChange={ ( value ) => updateSettings( { layoutShowCardBorder: value } ) }
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Show or hide the outer border around the contact layout container.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 order-5">
								<Input
									label={ __( 'Card padding', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.layoutCardPadding ) }
									onChange={ ( value ) =>
										updateSettings( {
											layoutCardPadding: Math.max( 0, Math.min( 64, Number( value ) || 0 ) ),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Controls inner spacing of each content card.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 order-6">
								<Input
									label={ __( 'Label font size', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.layoutLabelFontSize ) }
									onChange={ ( value ) =>
										updateSettings( {
											layoutLabelFontSize: Math.max( 10, Math.min( 32, Number( value ) || 10 ) ),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Controls font size of field labels like Address and Phone.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<LocalSeoColorPicker
									label={ __( 'Label color', 'airygen-seo' ) }
									value={ settings.layoutLabelColor }
									onChange={ ( value ) => updateSettings( { layoutLabelColor: value } ) }
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets text color for field labels.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<p className="text-sm font-medium text-slate-900">
									{ __( 'Label typography', 'airygen-seo' ) }
								</p>
								<div className="flex flex-wrap gap-3 text-sm text-slate-700">
									<label htmlFor="local-seo-label-uppercase" className="flex items-center gap-2">
										<input
											id="local-seo-label-uppercase"
											type="checkbox"
											checked={ settings.layoutLabelUppercase }
											onChange={ ( event ) =>
												updateSettings( { layoutLabelUppercase: event.currentTarget.checked } )
											}
										/>
										{ __( 'Uppercase', 'airygen-seo' ) }
									</label>
									<label htmlFor="local-seo-label-bold" className="flex items-center gap-2">
										<input
											id="local-seo-label-bold"
											type="checkbox"
											checked={ settings.layoutLabelBold }
											onChange={ ( event ) =>
												updateSettings( { layoutLabelBold: event.currentTarget.checked } )
											}
										/>
										{ __( 'Bold', 'airygen-seo' ) }
									</label>
									<label htmlFor="local-seo-label-italic" className="flex items-center gap-2">
										<input
											id="local-seo-label-italic"
											type="checkbox"
											checked={ settings.layoutLabelItalic }
											onChange={ ( event ) =>
												updateSettings( { layoutLabelItalic: event.currentTarget.checked } )
											}
										/>
										{ __( 'Italic', 'airygen-seo' ) }
									</label>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Adjust label text transform and emphasis.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 order-1">
								<Input
									label={ __( 'Field font size', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.layoutValueFontSize ) }
									onChange={ ( value ) =>
										updateSettings( {
											layoutValueFontSize: Math.max( 10, Math.min( 40, Number( value ) || 10 ) ),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Controls font size of field values.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4 order-3">
								<Input
									label={ __( 'Title font size', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.layoutTitleFontSize ) }
									onChange={ ( value ) =>
										updateSettings( {
											layoutTitleFontSize: Math.max( 16, Math.min( 80, Number( value ) || 16 ) ),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Controls Business name title size.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<LocalSeoColorPicker
									label={ __( 'Field text color', 'airygen-seo' ) }
									value={ settings.layoutValueColor }
									onChange={ ( value ) => updateSettings( { layoutValueColor: value } ) }
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets text color for field values.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<LocalSeoColorPicker
									label={ __( 'Card background color', 'airygen-seo' ) }
									value={ settings.layoutCardBackgroundColor }
									onChange={ ( value ) =>
										updateSettings( { layoutCardBackgroundColor: value } )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets background color of each content card.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					</div>
					<div className="rounded-lg border border-slate-200 p-4">
						<div className="space-y-1">
							<div className="airygen_h2_title flex items-center gap-2">
								{ __( 'Style', 'airygen-seo' ) }
								<span className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
									{ __( 'NAP', 'airygen-seo' ) }
								</span>
							</div>
							<p className="text-sm text-slate-500">
								{ __( 'Customize Footer NAP typography, spacing, and container width.', 'airygen-seo' ) }
							</p>
						</div>
						<div className="mt-3 grid gap-4 md:grid-cols-4">
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Container width', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.footerNapContainerWidth ) }
									onChange={ ( value ) =>
										updateSettings( {
											footerNapContainerWidth: Math.max(
												280,
												Math.min( 1920, Number( value ) || 280 ),
											),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets centered container width inside a full-width wrapper.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Margin Y', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.footerNapMarginY ) }
									onChange={ ( value ) =>
										updateSettings( {
											footerNapMarginY: Math.max(
												0,
												Math.min( 200, Number( value ) || 0 ),
											),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets the space above and below the Footer NAP block.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<p className="text-sm font-medium text-slate-900">
									{ __( 'Align', 'airygen-seo' ) }
								</p>
								<div className="flex flex-wrap gap-3 text-sm text-slate-700">
									<label htmlFor="footer-nap-align-left" className="flex items-center gap-2">
										<input
											id="footer-nap-align-left"
											type="radio"
											name="footer-nap-text-align"
											checked={ settings.footerNapTextAlign === 'left' }
											onChange={ () => updateSettings( { footerNapTextAlign: 'left' } ) }
										/>
										{ __( 'Left', 'airygen-seo' ) }
									</label>
									<label htmlFor="footer-nap-align-center" className="flex items-center gap-2">
										<input
											id="footer-nap-align-center"
											type="radio"
											name="footer-nap-text-align"
											checked={ settings.footerNapTextAlign === 'center' }
											onChange={ () => updateSettings( { footerNapTextAlign: 'center' } ) }
										/>
										{ __( 'Center', 'airygen-seo' ) }
									</label>
									<label htmlFor="footer-nap-align-right" className="flex items-center gap-2">
										<input
											id="footer-nap-align-right"
											type="radio"
											name="footer-nap-text-align"
											checked={ settings.footerNapTextAlign === 'right' }
											onChange={ () => updateSettings( { footerNapTextAlign: 'right' } ) }
										/>
										{ __( 'Right', 'airygen-seo' ) }
									</label>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Controls horizontal alignment of Footer NAP content.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
								<div className="flex items-center justify-between gap-3">
									<p className="text-sm font-medium text-slate-900">
										{ __( 'Bold first item', 'airygen-seo' ) }
									</p>
									<Toggle
										label={ __( 'Bold first item', 'airygen-seo' ) }
										hideLabelText
										checked={ settings.footerNapFirstItemBold }
										onChange={ ( value ) =>
											updateSettings( { footerNapFirstItemBold: value } )
										}
									/>
								</div>
								<p className="text-xs text-slate-500">
									{ __( 'Makes the first visible contact item bold.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Gap', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.footerNapGap ) }
									onChange={ ( value ) =>
										updateSettings( {
											footerNapGap: Math.max(
												0,
												Math.min( 48, Number( value ) || 0 ),
											),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets the spacing between each contact item.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<Input
									label={ __( 'Font size', 'airygen-seo' ) + ' (px)' }
									type="number"
									step="1"
									value={ String( settings.footerNapFontSize ) }
									onChange={ ( value ) =>
										updateSettings( {
											footerNapFontSize: Math.max(
												10,
												Math.min( 48, Number( value ) || 10 ),
											),
										} )
									}
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Controls Footer NAP text size.', 'airygen-seo' ) }
								</p>
							</div>
							<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
								<LocalSeoColorPicker
									label={ __( 'Font color', 'airygen-seo' ) }
									value={ settings.footerNapTextColor }
									onChange={ ( value ) => updateSettings( { footerNapTextColor: value } ) }
								/>
								<p className="mt-2 text-xs text-slate-500">
									{ __( 'Sets text color for Footer NAP values.', 'airygen-seo' ) }
								</p>
							</div>
						</div>
					</div>
				</section>
			) : null }

			{ 'branches' === activeTab ? (
				<>
					<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
						<div className="flex items-start justify-between gap-3">
							<div className="space-y-1">
								<div className="airygen_h2_title">
									{ __( 'Branches', 'airygen-seo' ) }
								</div>
								<p className="text-sm text-slate-500">
									{ __( 'Manage branch list and override branch-specific settings.', 'airygen-seo' ) }
								</p>
							</div>
							<Button variant="secondary" className="text-xs" onClick={ addBranch }>
								{ __( 'Add branch', 'airygen-seo' ) }
							</Button>
						</div>
						<div className="space-y-3">
							{ settings.branches.length > 0 ? (
								settings.branches.map( ( branch, index ) => (
									<div
										key={ branch.id }
										className="flex items-center justify-between rounded-lg border border-slate-200 p-4"
									>
										<div>
											<p className="text-sm font-medium text-slate-900">
												{ branch.label || `${ __( 'Branch', 'airygen-seo' ) } ${ index + 1 }` }
											</p>
											<p className="mt-1 text-xs text-slate-500">
												{ branch.enabled
													? __( 'Enabled', 'airygen-seo' )
													: __( 'Disabled', 'airygen-seo' ) }
											</p>
										</div>
										<div className="flex items-center gap-2">
											<div className="mr-3">
												<Toggle
													label={ __( 'Enable branch', 'airygen-seo' ) }
													hideLabelText
													checked={ branch.enabled }
													onChange={ ( value ) =>
														updateBranch( branch.id, { enabled: value } )
													}
												/>
											</div>
											<Button
												variant="secondary"
												className="text-xs"
												onClick={ () => setEditingBranchId( branch.id ) }
											>
												{ __( 'Edit', 'airygen-seo' ) }
											</Button>
											<Button
												variant="secondary"
												className="text-xs"
												onClick={ () => removeBranch( branch.id ) }
											>
												{ __( 'Delete', 'airygen-seo' ) }
											</Button>
										</div>
									</div>
								) )
							) : (
								<div className="rounded-lg border border-dashed border-slate-300 p-6 text-sm text-slate-500">
									{ getNoItemsYetAddOneToConfigureLabel(
										__( 'branches', 'airygen-seo' ),
										__( 'branch overrides', 'airygen-seo' ),
									) }
								</div>
							) }
						</div>
					</section>

					{ editingBranch ? (
						<>
							<div className="relative my-6">
								<div className="border-b-4 border-dashed border-slate-300" />
								<span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-slate-100 px-3 text-base font-semibold text-slate-800">
									{ __( 'Branch overrides', 'airygen-seo' ) }: { editingBranch.label }
								</span>
								<div className="absolute right-0 top-1/2 -translate-y-1/2 bg-slate-100 pl-3">
									<Button
										variant="secondary"
										className="text-xs"
										onClick={ () => setEditingBranchId( null ) }
									>
										{ __( 'Close', 'airygen-seo' ) }
									</Button>
								</div>
							</div>
							<p className="mb-4 text-center text-sm text-slate-500">
								{ __( 'Branch fields override Main store values when provided.', 'airygen-seo' ) }
							</p>
							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">
										{ __( 'Settings', 'airygen-seo' ) }
									</div>
									<p className="text-sm text-slate-500">
										{ __( 'Configure basic branch controls before applying field overrides.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="rounded-lg border border-slate-200 p-4">
									<div className="airygen_h3_title">{ __( 'Branch Controls', 'airygen-seo' ) }</div>
									<div className="mt-3 grid gap-4 lg:grid-cols-3">
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Branch label', 'airygen-seo' ) }
												help={ __( 'Used in list and internal branch identification.', 'airygen-seo' ) }
												value={ editingBranch.label }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, { label: value } )
												}
											/>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Geo region code', 'airygen-seo' ) }
												help={ __( 'Example: TW-TPE', 'airygen-seo' ) }
												value={ editingBranch.geoRegionCode }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, {
														geoRegionCode: value,
													} )
												}
											/>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Geo place name', 'airygen-seo' ) }
												help={ __( 'Example: Taipei', 'airygen-seo' ) }
												value={ editingBranch.geoPlacename }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, {
														geoPlacename: value,
													} )
												}
											/>
										</div>
									</div>
									<div className="mt-4">
										<h4 className="text-sm font-medium text-gray-800">{ __( 'Shortcode', 'airygen-seo' ) }</h4>
										<div className="mt-3 grid gap-4 lg:grid-cols-2">
											<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
												<Input
													label={ __( 'Branch slug', 'airygen-seo' ) }
													help={ __( 'Used in shortcode branch parameter. Letters, numbers, and hyphens only.', 'airygen-seo' ) }
													value={ editingBranch.slug }
													onChange={ ( value ) =>
														updateBranch( editingBranch.id, { slug: value } )
													}
												/>
											</div>
											<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
												<label className="block text-sm font-medium text-gray-800" htmlFor="branch-shortcode">
													{ __( 'Shortcode', 'airygen-seo' ) }
												</label>
												<div className="mt-2 flex items-center gap-2">
													<code
														id="branch-shortcode"
														className="block w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
													>
														{ `[airygen_localseo branch="${ editingBranch.slug }"]` }
													</code>
													<Button
														variant="secondary"
														className="text-xs"
														onClick={ () =>
															copyBranchShortcode(
																`[airygen_localseo branch="${ editingBranch.slug }"]`,
															)
														}
													>
														{ branchShortcodeCopied
															? __( 'Copied', 'airygen-seo' )
															: __( 'Copy', 'airygen-seo' ) }
													</Button>
												</div>
												<p className="mt-1 text-xs text-slate-500">
													{ __( 'Outputs this branch contact card and map. Falls back to Main store for empty fields.', 'airygen-seo' ) }
												</p>
											</div>
										</div>
									</div>
								</div>
							</section>

							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">
										{ __( 'Business Info', 'airygen-seo' ) }
									</div>
									<p className="text-sm text-slate-500">
										{ __( 'Make sure every value exactly matches your Google Business Profile details, character for character.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="mt-3 grid gap-4 lg:grid-cols-4">
									<div className="lg:col-span-4">
										<div className="airygen_h3_title">{ __( 'Profile', 'airygen-seo' ) }</div>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Business name', 'airygen-seo' ) }
											help={ __( 'Use your public business name shown on storefronts and listings.', 'airygen-seo' ) }
											value={ editingBranch.businessName }
											onChange={ ( value ) =>
												updateBranch( editingBranch.id, { businessName: value } )
											}
										/>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Phone', 'airygen-seo' ) }
											help={ __( 'Primary contact number for customers. Include country/area code when possible.', 'airygen-seo' ) }
											value={ editingBranch.phone }
											onChange={ ( value ) =>
												updateBranch( editingBranch.id, { phone: value } )
											}
										/>
									</div>
									<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
										<Input
											label={ __( 'Image URL', 'airygen-seo' ) }
											help={ __( 'Representative photo used in LocalBusiness schema output.', 'airygen-seo' ) }
											value={ editingBranch.imageUrl }
											isUrl
											onChange={ ( value ) =>
												updateBranch( editingBranch.id, { imageUrl: value } )
											}
										/>
									</div>
								</div>
								<div className="mt-4 rounded-lg border border-slate-200 p-4">
									<div className="airygen_h3_title">{ __( 'Address', 'airygen-seo' ) }</div>
									<div className="mt-3 grid gap-4 lg:grid-cols-4">
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<label
												className="block text-sm font-medium text-gray-800"
												htmlFor={ `branch-country-${ editingBranch.id }` }
											>
												{ __( 'Country code', 'airygen-seo' ) }
											</label>
											<select
												id={ `branch-country-${ editingBranch.id }` }
												className="airygen-field-select mt-2 w-full"
												value={ editingBranch.country }
												onChange={ ( event ) =>
													updateBranch( editingBranch.id, {
														country: sanitizeCountryCodeInput( event.currentTarget.value ),
													} )
												}
											>
												<option value="">{ __( 'Select a country', 'airygen-seo' ) }</option>
												{ countryOptions.map( ( option ) => (
													<option
														key={ `branch-country-option-${ option.code }` }
														value={ option.code }
													>
														{ `${ option.label } (${ option.code })` }
													</option>
												) ) }
											</select>
											<p className="mt-1 text-xs text-slate-500">
												{ sprintf(
													/* translators: %s is the schema property name. */
													__( 'Used for schema %s.', 'airygen-seo' ),
													'addressCountry (ISO-3166 Alpha-2)',
												) }
											</p>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'City', 'airygen-seo' ) }
												help={ __( 'City (locality) for your business address.', 'airygen-seo' ) }
												value={ editingBranch.city }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, { city: value } )
												}
											/>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Region / State', 'airygen-seo' ) }
												help={ __( 'State, province, or administrative region.', 'airygen-seo' ) }
												value={ editingBranch.region }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, { region: value } )
												}
											/>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Postal code', 'airygen-seo' ) }
												help={ __( 'ZIP or postal code for the street address.', 'airygen-seo' ) }
												value={ editingBranch.postalCode }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, { postalCode: value } )
												}
											/>
										</div>
									</div>
									<div className="mt-4">
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Street address', 'airygen-seo' ) }
												help={ __( 'Street and number for your primary location.', 'airygen-seo' ) }
												value={ editingBranch.streetAddress }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, { streetAddress: value } )
												}
											/>
										</div>
									</div>
								</div>
							</section>

							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">
										{ __( 'Map', 'airygen-seo' ) }
									</div>
									<p className="text-sm text-slate-500">
										{ __( 'Set map coordinates and contact-page map behavior for Local SEO map output.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="rounded-lg border border-slate-200 p-4">
									<div className="flex items-center justify-between gap-3">
										<div className="airygen_h3_title">{ __( 'Coordinates', 'airygen-seo' ) }</div>
										<Button variant="secondary" className="text-xs" onClick={ () => openMapUrlModal( 'branch' ) }>
											{ __( 'Use Google Maps URL', 'airygen-seo' ) }
										</Button>
									</div>
									<div className="mt-3 grid gap-4 lg:grid-cols-3">
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Latitude', 'airygen-seo' ) }
												help={ __( 'Decimal latitude coordinate used for geo metadata and map output.', 'airygen-seo' ) }
												type="number"
												step="0.000001"
												value={ 0 === editingBranch.latitude ? '' : String( editingBranch.latitude ) }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, {
														latitude: Number( value ) || 0,
													} )
												}
											/>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Longitude', 'airygen-seo' ) }
												help={ __( 'Decimal longitude coordinate used for geo metadata and map output.', 'airygen-seo' ) }
												type="number"
												step="0.000001"
												value={ 0 === editingBranch.longitude ? '' : String( editingBranch.longitude ) }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, {
														longitude: Number( value ) || 0,
													} )
												}
											/>
										</div>
										<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
											<div className="flex items-center justify-between gap-3">
												<p className="text-sm font-medium text-slate-900">
													{ __( 'Include KML in sitemap', 'airygen-seo' ) }
												</p>
												<Toggle
													label={ __( 'Include KML in sitemap', 'airygen-seo' ) }
													hideLabelText
													checked={ editingBranch.kmlInSitemap }
													disabled={ ! canEnableBranchMapFeatures }
													onChange={ ( value ) =>
														updateBranch( editingBranch.id, { kmlInSitemap: value } )
													}
												/>
											</div>
											<p className="text-xs text-slate-500">
												{ canEnableBranchMapFeatures
													? __( 'Controls whether /local.kml is listed in sitemap.xml. This KML file helps search engines understand your business location data.', 'airygen-seo' )
													: __( 'Set valid latitude and longitude (both cannot be 0) to enable KML sitemap listing.', 'airygen-seo' ) }
											</p>
										</div>
									</div>
								</div>
							</section>

							<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
								<div className="space-y-1">
									<div className="airygen_h2_title">
										{ __( 'Service Info', 'airygen-seo' ) }
									</div>
									<p className="text-sm text-slate-500">
										{ __( 'Manage service catalog, service areas, hours, trust signals, and service visibility settings.', 'airygen-seo' ) }
									</p>
								</div>
								<div className="rounded-lg border border-slate-200 p-4">
									<div className="airygen_h3_title">{ __( 'Service Areas', 'airygen-seo' ) }</div>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Define served cities, postal codes, and optional radius for areaServed schema.', 'airygen-seo' ) }
									</p>
									<div className="mt-3 grid gap-4 lg:grid-cols-3">
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<label
												className="block text-sm font-medium text-gray-800"
												htmlFor={ `branch-service-area-cities-${ editingBranch.id }` }
											>
												{ __( 'Served cities', 'airygen-seo' ) }
											</label>
											<textarea
												id={ `branch-service-area-cities-${ editingBranch.id }` }
												rows={ 6 }
												className="airygen-field mt-2 w-full"
												value={ serializeLineItems( editingBranch.serviceAreaCities ) }
												onChange={ ( event ) =>
													updateBranch( editingBranch.id, {
														serviceAreaCities: parseTextareaLineItems(
															event.currentTarget.value,
														),
													} )
												}
											/>
											<p className="mt-1 text-xs text-slate-500">
												{ __( 'One city per line. Output as @type: City.', 'airygen-seo' ) }
											</p>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<label
												className="block text-sm font-medium text-gray-800"
												htmlFor={ `branch-service-area-postal-codes-${ editingBranch.id }` }
											>
												{ __( 'Served postal codes', 'airygen-seo' ) }
											</label>
											<textarea
												id={ `branch-service-area-postal-codes-${ editingBranch.id }` }
												rows={ 6 }
												className="airygen-field mt-2 w-full"
												value={ serializeLineItems( editingBranch.serviceAreaPostalCodes ) }
												onChange={ ( event ) =>
													updateBranch( editingBranch.id, {
														serviceAreaPostalCodes: parseTextareaLineItems(
															event.currentTarget.value,
														),
													} )
												}
											/>
											<p className="mt-1 text-xs text-slate-500">
												{ __( 'One postal code per line. Output as @type: PostalCode.', 'airygen-seo' ) }
											</p>
										</div>
										<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
											<Input
												label={ __( 'Service radius (km)', 'airygen-seo' ) }
												help={ __( 'GeoCircle radius. Uses the Map latitude/longitude as center point.', 'airygen-seo' ) }
												type="number"
												step="0.1"
												value={ String( editingBranch.serviceAreaRadiusKm ) }
												onChange={ ( value ) =>
													updateBranch( editingBranch.id, {
														serviceAreaRadiusKm: Math.max(
															0,
															Number( value ) || 0,
														),
													} )
												}
											/>
										</div>
									</div>
								</div>
								<div className="rounded-lg border border-slate-200 p-4">
									<div className="airygen_h3_title">{ __( 'Opening Hours', 'airygen-seo' ) }</div>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Configure daily opening intervals for schema output.', 'airygen-seo' ) }
									</p>
									<div className="mt-3 grid gap-3 md:grid-cols-2">
										{ DAY_CODES.map( ( dayCode ) => {
											const daySchedule = branchOpeningHoursByDay[ dayCode ];
											const openPercent =
														( daySchedule.openMinutes / OPENING_HOURS_MAX_MINUTES ) * 100;
											const closePercent =
														( daySchedule.closeMinutes / OPENING_HOURS_MAX_MINUTES ) * 100;

											return (
												<div
													key={ `branch-${ dayCode }` }
													className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4"
												>
													<div className="flex items-center justify-between gap-3">
														<p className="text-sm font-medium text-slate-900">
															{ dayLabels[ dayCode ] }
														</p>
														<Toggle
															label={ dayLabels[ dayCode ] }
															hideLabelText
															checked={ daySchedule.enabled }
															onChange={ ( value ) =>
																updateBranchDaySchedule(
																	editingBranch.id,
																	dayCode,
																	{ enabled: value },
																)
															}
														/>
													</div>
													<p className="text-xs text-slate-500">
														{ __( 'Enable this day and adjust open/close time.', 'airygen-seo' ) }
													</p>
													{ daySchedule.enabled ? (
														<div className="space-y-3">
															<div className="flex items-center justify-between text-xs text-slate-600">
																<span>
																	{ __( 'Open', 'airygen-seo' ) }: { daySchedule.is24Hours ? '00:00' : toOpeningHourString( daySchedule.openMinutes ) }
																</span>
																<span>
																	{ __( 'Close', 'airygen-seo' ) }: { daySchedule.is24Hours ? '23:59' : toOpeningHourString( daySchedule.closeMinutes ) }
																</span>
															</div>
															<div className="flex items-center gap-3">
																<div className="relative h-8 flex-1">
																	<div className="absolute top-1/2 h-1.5 w-full -translate-y-1/2 rounded-full bg-slate-200" />
																	{ ! daySchedule.is24Hours ? (
																		<>
																			<div
																				className="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-sky-500"
																				style={ {
																					left: `${ openPercent }%`,
																					right: `${ 100 - closePercent }%`,
																				} }
																			/>
																			<input
																				type="range"
																				min={ 0 }
																				max={ OPENING_HOURS_MAX_MINUTES }
																				step={ OPENING_HOURS_STEP_MINUTES }
																				value={ daySchedule.openMinutes }
																				onChange={ ( event ) =>
																					updateBranchDaySchedule(
																						editingBranch.id,
																						dayCode,
																						{
																							openMinutes: Number(
																								event.currentTarget.value,
																							),
																						},
																					)
																				}
																				className="pointer-events-none absolute inset-0 m-0 h-8 w-full cursor-pointer appearance-none rounded-full bg-transparent accent-sky-500 [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:mt-[-3px] [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border [&::-webkit-slider-thumb]:border-sky-500 [&::-webkit-slider-thumb]:bg-sky-500 [&::-moz-range-track]:h-1.5 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-3 [&::-moz-range-thumb]:w-3 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border [&::-moz-range-thumb]:border-sky-500 [&::-moz-range-thumb]:bg-sky-500"
																			/>
																			<input
																				type="range"
																				min={ 0 }
																				max={ OPENING_HOURS_MAX_MINUTES }
																				step={ OPENING_HOURS_STEP_MINUTES }
																				value={ daySchedule.closeMinutes }
																				onChange={ ( event ) =>
																					updateBranchDaySchedule(
																						editingBranch.id,
																						dayCode,
																						{
																							closeMinutes: Number(
																								event.currentTarget.value,
																							),
																						},
																					)
																				}
																				className="pointer-events-none absolute inset-0 m-0 h-8 w-full cursor-pointer appearance-none rounded-full bg-transparent accent-sky-500 [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:mt-[-3px] [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border [&::-webkit-slider-thumb]:border-sky-500 [&::-webkit-slider-thumb]:bg-sky-500 [&::-moz-range-track]:h-1.5 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-3 [&::-moz-range-thumb]:w-3 [&::-moz-range-thumb]:cursor-pointer [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border [&::-moz-range-thumb]:border-sky-500 [&::-moz-range-thumb]:bg-sky-500"
																			/>
																		</>
																	) : null }
																</div>
																<label
																	htmlFor={ `branch-${ editingBranch.id }-opening-hours-${ dayCode }-24h` }
																	className="flex items-center gap-2 whitespace-nowrap text-xs text-slate-700"
																>
																	<input
																		id={ `branch-${ editingBranch.id }-opening-hours-${ dayCode }-24h` }
																		type="checkbox"
																		className="h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500"
																		checked={ daySchedule.is24Hours }
																		onChange={ ( event ) =>
																			updateBranchDaySchedule(
																				editingBranch.id,
																				dayCode,
																				{
																					is24Hours: event.currentTarget.checked,
																				},
																			)
																		}
																	/>
																	{ `24 ${ __( 'hours', 'airygen-seo' ) }` }
																</label>
															</div>
														</div>
													) : null }
												</div>
											);
										} ) }
									</div>
								</div>
								<div className="rounded-lg border border-slate-200 p-4">
									<div className="airygen_h3_title">{ __( 'Special Hours', 'airygen-seo' ) }</div>
									<p className="mt-1 text-xs text-slate-500">
										{ __( 'Configure one rule per line for special opening hours.', 'airygen-seo' ) }
									</p>
									<div className="mt-3">
										<SpecialHoursRulesEditor
											value={ editingBranch.specialHours }
											onChange={ ( next ) =>
												updateBranch( editingBranch.id, {
													specialHours: next,
												} )
											}
											inputIdPrefix={ `branch-special-hours-${ editingBranch.id }` }
										/>
									</div>
								</div>
							</section>
						</>
					) : null }
				</>
			) : null }

			{ 'preview' === activeTab ? (
				<section className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
					<div className="space-y-1">
						<div className="flex items-center justify-between gap-3">
							<div className="airygen_h2_title">
								{ __( 'Preview', 'airygen-seo' ) }
							</div>
							<PreviewDeviceSwitcher
								options={ [
									{ key: 'laptop', label: __( 'Laptop', 'airygen-seo' ), Icon: PreviewLaptopIcon },
									{ key: 'tablet', label: __( 'Tablet', 'airygen-seo' ), Icon: PreviewTabletIcon },
									{ key: 'cellphone', label: __( 'Cellphone', 'airygen-seo' ), Icon: PreviewCellphoneIcon },
								] }
								value={ previewViewport }
								onChange={ ( next ) => setPreviewViewport( next as PreviewDeviceKind ) }
							/>
						</div>
						<p className="text-sm text-slate-500">
							{ __( 'This is a simple preview. Actual output is affected by your theme styles. For accurate results, check the real page output.', 'airygen-seo' ) }
						</p>
					</div>
					<div className="overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
						<div data-airygen-e2e="preview-stage-local-seo">
							<PreviewDeviceFrame device={ previewViewport }>
								<div className="h-full w-full">
									<iframe
										title={ __( 'Contact page preview', 'airygen-seo' ) }
										srcDoc={ contactPreviewSrcDoc }
										data-airygen-e2e="preview-iframe-contact-page"
										className="h-full w-full border-0 bg-white"
									/>
								</div>
							</PreviewDeviceFrame>
						</div>
					</div>
					<div>
						<div className="space-y-1">
							<div className="airygen_h3_title">
								{ __( 'Footer NAP', 'airygen-seo' ) }
							</div>
						</div>
						<div className="mt-3">
							<iframe
								title={ __( 'Footer NAP preview', 'airygen-seo' ) }
								srcDoc={ footerNapPreviewSrcDoc }
								data-airygen-e2e="preview-iframe-footer-nap"
								className="w-full rounded-lg border border-slate-200 bg-white"
								style={ { height: `${ footerNapPreviewFrameHeight }px`, overflowY: 'auto' } }
							/>
						</div>
					</div>
					<PreviewCodeSamples
						injectedCss={ contactLayoutPreview.css }
						htmlSample={ contactLayoutPreview.html }
						injectedCssId="local-seo-contact-page-css-preview"
						htmlSampleId="local-seo-contact-page-html-preview"
						injectedCssLabel={ __( 'Contact page CSS', 'airygen-seo' ) }
						htmlSampleLabel={ __( 'Contact page HTML', 'airygen-seo' ) }
						rows={ 14 }
					/>
					<div className="grid gap-4 lg:grid-cols-2">
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label htmlFor="local-seo-jsonld-preview" className="block text-sm font-medium text-gray-800">
								{ __( 'LocalBusiness JSON-LD', 'airygen-seo' ) }
							</label>
							<textarea
								id="local-seo-jsonld-preview"
								rows={ 14 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={
									localSchemaPreview ||
									__( 'Add Business name to generate preview JSON-LD.', 'airygen-seo' )
								}
							/>
							<div className="mt-2 flex items-center gap-2">
								<Button
									variant="secondary"
									className="text-xs"
									onClick={ copyLocalSchemaPreview }
									disabled={ '' === localSchemaPreview.trim() }
								>
									{ localSchemaCopied
										? __( 'Copied', 'airygen-seo' )
										: __( 'Copy', 'airygen-seo' ) }
								</Button>
								<Button
									variant="secondary"
									className="text-xs"
									onClick={ () =>
										window.open(
											'https://search.google.com/test/rich-results',
											'_blank',
											'noopener,noreferrer',
										)
									}
								>
									{ __( 'Check Schema', 'airygen-seo' ) }
								</Button>
							</div>
						</div>
						<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
							<label htmlFor="local-seo-geo-meta-preview" className="block text-sm font-medium text-gray-800">
								{ __( 'Geo meta tags', 'airygen-seo' ) }
							</label>
							<textarea
								id="local-seo-geo-meta-preview"
								rows={ 14 }
								readOnly
								className="airygen-field mt-2 w-full font-mono text-xs"
								value={
									settings.enableGeoTags
										? `<meta name="geo.region" content="${ settings.geoRegionCode }" />\n<meta name="geo.placename" content="${ settings.geoPlacename }" />\n<meta name="geo.position" content="${ settings.latitude };${ settings.longitude }" />`
										: __( 'Geo tags are disabled.', 'airygen-seo' )
								}
							/>
						</div>
					</div>
					<div className="mt-4">
						<PreviewCodeSamples
							injectedCss={ footerNapPreview.css }
							htmlSample={ footerNapPreview.html }
							injectedCssId="local-seo-footer-nap-css-preview"
							htmlSampleId="local-seo-footer-nap-html-preview"
							injectedCssLabel={ __( 'Footer NAP CSS', 'airygen-seo' ) }
							htmlSampleLabel={ __( 'Footer NAP HTML', 'airygen-seo' ) }
							rows={ 8 }
						/>
					</div>
				</section>
			) : null }

			<Modal
				isOpen={ isMapUrlModalOpen }
				onClose={ closeMapUrlModal }
				title={ __( 'Import Coordinates from Google Maps URL', 'airygen-seo' ) }
				maxWidth="max-w-xl"
				footer={
					<div className="flex items-center justify-end gap-2">
						<Button variant="secondary" onClick={ closeMapUrlModal }>
							{ __( 'Cancel', 'airygen-seo' ) }
						</Button>
						<Button variant="primary" onClick={ applyCoordinatesFromMapUrl }>
							{ __( 'Apply coordinates', 'airygen-seo' ) }
						</Button>
					</div>
				}
			>
				<div className="space-y-4">
					<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
						<Input
							label={ __( 'Google Maps URL', 'airygen-seo' ) }
							help={ __( 'Paste a full Google Maps URL. Supported patterns include @lat,lng, q=lat,lng, and !3d…!4d….', 'airygen-seo' ) }
							value={ mapUrlInput }
							onChange={ ( value ) => {
								setMapUrlInput( value );
								if ( mapUrlError !== '' ) {
									setMapUrlError( '' );
								}
							} }
							isUrl
						/>
					</div>
					{ mapUrlError !== '' ? (
						<p className="text-sm text-red-600">{ mapUrlError }</p>
					) : null }
				</div>
			</Modal>
		</div>
	);
};

export default LocalSeoTab;

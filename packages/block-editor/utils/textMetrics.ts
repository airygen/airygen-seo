/**
 * Text measurement helpers for SERP-like pixel analysis.
 *
 * Uses browser canvas metrics instead of per-character guess tables so CJK,
 * Latin, and mixed strings are handled by the actual font rendering.
 */

const FONT_STACK = 'Arial, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

export type TextMeasurerOptions = {
	fontSize: number;
	fontWeight: string;
	fontFamily?: string;
};

/**
 * Create a memoized text measurer using a shared in-memory canvas.
 * @param {TextMeasurerOptions} options Measurement configuration.
 */
export function createTextMeasurer( options: TextMeasurerOptions ): ( text: string ) => number {
	const { fontSize, fontWeight, fontFamily = FONT_STACK } = options;

	if ( typeof document === 'undefined' ) {
		return () => 0;
	}

	const canvas = document.createElement( 'canvas' );
	const ctx = canvas.getContext( '2d' );

	if ( ! ctx ) {
		return () => 0;
	}

	ctx.font = `${ fontWeight } ${ fontSize }px ${ fontFamily }`;

	return ( text: string ): number => {
		if ( ! text || ! text.trim() ) {
			return 0;
		}

		return ctx.measureText( text ).width;
	};
}

const measureTitle = createTextMeasurer( {
	fontWeight: 'bold',
	fontSize: 20,
	fontFamily: FONT_STACK,
} );

const measureDescription = createTextMeasurer( {
	fontWeight: 'normal',
	fontSize: 14,
	fontFamily: FONT_STACK,
} );

export function measureSerpTitle( text: string ): number {
	return measureTitle( text );
}

export function measureSerpDescription( text: string ): number {
	return measureDescription( text );
}

const TITLE_GOOD_MAX = 580;
const TITLE_HARD_MAX = 600;
const DESC_GOOD_MAX = 920;

export type TitleStatus = 'empty' | 'too_short' | 'good' | 'borderline' | 'too_long';
export type DescriptionStatus = 'empty' | 'too_short' | 'good' | 'too_long';

export function analyseTitlePixels( text: string ): { text: string; pixels: number; status: TitleStatus } {
	const pixels = measureSerpTitle( text );
	let status: TitleStatus = 'empty';

	if ( pixels === 0 ) {
		status = 'empty';
	} else if ( pixels < 200 ) {
		status = 'too_short';
	} else if ( pixels <= TITLE_GOOD_MAX ) {
		status = 'good';
	} else if ( pixels <= TITLE_HARD_MAX ) {
		status = 'borderline';
	} else {
		status = 'too_long';
	}

	return { text, pixels, status };
}

export function analyseDescriptionPixels(
	text: string,
): { text: string; pixels: number; status: DescriptionStatus } {
	const pixels = measureSerpDescription( text );
	let status: DescriptionStatus = 'empty';

	if ( pixels === 0 ) {
		status = 'empty';
	} else if ( pixels < 300 ) {
		status = 'too_short';
	} else if ( pixels <= DESC_GOOD_MAX ) {
		status = 'good';
	} else {
		status = 'too_long';
	}

	return { text, pixels, status };
}

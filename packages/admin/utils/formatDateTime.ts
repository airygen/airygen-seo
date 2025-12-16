const pad = ( value: number ) => String( value ).padStart( 2, '0' );

const formatDateTime = (
	value: string | Date | null | undefined,
	fallback = '—',
): string => {
	if ( ! value ) {
		return fallback;
	}

	const parsed = value instanceof Date ? value : new Date( value );
	if ( Number.isNaN( parsed.getTime() ) ) {
		return fallback;
	}

	return [
		parsed.getFullYear(),
		'-',
		pad( parsed.getMonth() + 1 ),
		'-',
		pad( parsed.getDate() ),
		' ',
		pad( parsed.getHours() ),
		':',
		pad( parsed.getMinutes() ),
		':',
		pad( parsed.getSeconds() ),
	].join( '' );
};

export default formatDateTime;

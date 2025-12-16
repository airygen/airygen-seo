export const decodeUrlPreview = ( value: string ): string => {
	if ( ! value ) {
		return value;
	}

	try {
		return decodeURI( value );
	} catch {
		return value;
	}
};

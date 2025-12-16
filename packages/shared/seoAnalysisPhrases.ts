import { __ } from '@wordpress/i18n';

export const getTitleFocusMessage = (
	hasKeyphrase: boolean,
	hasFocus: boolean,
): string => {
	if ( ! hasKeyphrase ) {
		return __( 'Focus keyphrase not set for title.', 'airygen-seo' );
	}
	if ( hasFocus ) {
		return __( 'Title includes the focus keyphrase.', 'airygen-seo' );
	}
	return __( 'Title is missing the focus keyphrase.', 'airygen-seo' );
};

export const getDescriptionFocusMessage = (
	hasKeyphrase: boolean,
	hasFocus: boolean,
): string => {
	if ( ! hasKeyphrase ) {
		return __( 'Focus keyphrase not set for description.', 'airygen-seo' );
	}
	if ( hasFocus ) {
		return __( 'Description includes the focus keyphrase.', 'airygen-seo' );
	}
	return __( 'Description is missing the focus keyphrase.', 'airygen-seo' );
};

export const getTitleLengthStatusMessage = (
	pixels: number,
	includeEmptyState = false,
): string => {
	if ( includeEmptyState && pixels === 0 ) {
		return __( 'Title is empty.', 'airygen-seo' );
	}
	if ( pixels < 250 ) {
		return __( 'Title is too short.', 'airygen-seo' );
	}
	if ( pixels <= 350 ) {
		return __( 'Title length could be improved.', 'airygen-seo' );
	}
	if ( pixels <= 580 ) {
		return __( 'Title length is in a good range.', 'airygen-seo' );
	}
	return __( 'Title is too long.', 'airygen-seo' );
};

export const getDescriptionLengthStatusMessage = (
	pixels: number,
	includeEmptyState = false,
): string => {
	if ( includeEmptyState && pixels === 0 ) {
		return __( 'Description is empty.', 'airygen-seo' );
	}
	if ( pixels < 400 ) {
		return __( 'Description is too short.', 'airygen-seo' );
	}
	if ( pixels <= 600 ) {
		return __( 'Description length could be improved.', 'airygen-seo' );
	}
	if ( pixels <= 920 ) {
		return __( 'Description length is in a good range.', 'airygen-seo' );
	}
	return __( 'Description is too long.', 'airygen-seo' );
};

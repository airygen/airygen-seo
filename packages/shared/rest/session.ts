export const SESSION_EXPIRED_REST_CODES = new Set( [
	'rest_cookie_invalid_nonce',
	'rest_not_logged_in',
	'rest_nonce_invalid',
] );

type RestErrorShape = {
	code?: string;
	data?: { status?: number };
	status?: number;
};

export const isSessionExpiredRestError = ( error: unknown ): boolean => {
	if ( ! error || typeof error !== 'object' ) {
		return false;
	}

	const maybeError = error as RestErrorShape;
	const code = typeof maybeError.code === 'string' ? maybeError.code : '';
	const status = maybeError.data?.status ?? maybeError.status;

	return status === 403 && SESSION_EXPIRED_REST_CODES.has( code );
};

export type SessionExpiredScope = 'classic' | 'block' | 'admin';

const LOCK_KEY_BY_SCOPE: Record<SessionExpiredScope, string> = {
	classic: '__airygenClassicSessionExpired',
	block: '__airygenBlockSessionExpired',
	admin: '__airygenAdminSessionExpired',
};

type SessionWindow = Window & {
	__airygenClassicSessionExpired?: boolean;
	__airygenBlockSessionExpired?: boolean;
	__airygenAdminSessionExpired?: boolean;
};

export const isSessionExpiredLocked = ( scope: SessionExpiredScope ): boolean => {
	if ( typeof window === 'undefined' ) {
		return false;
	}

	const key = LOCK_KEY_BY_SCOPE[ scope ];
	const sessionWindow = window as SessionWindow;
	if ( key === '__airygenClassicSessionExpired' ) {
		return Boolean( sessionWindow.__airygenClassicSessionExpired );
	}
	if ( key === '__airygenBlockSessionExpired' ) {
		return Boolean( sessionWindow.__airygenBlockSessionExpired );
	}
	return Boolean( sessionWindow.__airygenAdminSessionExpired );
};

export const lockSessionExpired = ( scope: SessionExpiredScope ): void => {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const key = LOCK_KEY_BY_SCOPE[ scope ];
	const sessionWindow = window as SessionWindow;
	if ( key === '__airygenClassicSessionExpired' ) {
		sessionWindow.__airygenClassicSessionExpired = true;
		return;
	}
	if ( key === '__airygenBlockSessionExpired' ) {
		sessionWindow.__airygenBlockSessionExpired = true;
		return;
	}
	sessionWindow.__airygenAdminSessionExpired = true;
};

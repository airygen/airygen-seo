const SCORE_CACHE_PREFIX = 'airygen_score_cache_';
const SCORE_CACHE_TTL_MS = 30 * 24 * 60 * 60 * 1000;

type ScoreCacheEntry<T> = {
	cachedAt: number;
	payload: T;
};

export const getScoreCacheKey = ( postId?: number, blogId?: number ): string | null => {
	if ( ! postId ) {
		return null;
	}

	const normalizedBlogId = blogId && blogId > 0 ? blogId : 1;

	return `${ SCORE_CACHE_PREFIX }${ normalizedBlogId }:${ postId }`;
};

export const loadScoreCache = <T>( postId?: number, blogId?: number ): T | null => {
	if ( typeof window === 'undefined' ) {
		return null;
	}

	const key = getScoreCacheKey( postId, blogId );
	if ( ! key ) {
		return null;
	}

	try {
		const raw = window.localStorage.getItem( key );
		if ( ! raw ) {
			return null;
		}

		const parsed = JSON.parse( raw ) as Partial< ScoreCacheEntry<T> > | null;
		if (
			! parsed ||
			typeof parsed !== 'object' ||
			typeof parsed.cachedAt !== 'number' ||
			! ( 'payload' in parsed )
		) {
			window.localStorage.removeItem( key );
			return null;
		}

		if ( Date.now() - parsed.cachedAt > SCORE_CACHE_TTL_MS ) {
			window.localStorage.removeItem( key );
			return null;
		}

		return parsed.payload as T;
	} catch {
		return null;
	}
};

export const saveScoreCache = <T>( postId: number, payload: T, blogId?: number ): void => {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const key = getScoreCacheKey( postId, blogId );
	if ( ! key ) {
		return;
	}

	try {
		const cacheEntry: ScoreCacheEntry<T> = {
			cachedAt: Date.now(),
			payload,
		};
		window.localStorage.setItem( key, JSON.stringify( cacheEntry ) );
	} catch {
		// Ignore storage errors.
	}
};

export const clearScoreCache = ( postId?: number, blogId?: number ): void => {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const key = getScoreCacheKey( postId, blogId );
	if ( ! key ) {
		return;
	}

	try {
		window.localStorage.removeItem( key );
	} catch {
		// Ignore storage errors.
	}
};

export const clearAllScoreCaches = (): void => {
	if ( typeof window === 'undefined' ) {
		return;
	}

	try {
		const keysToRemove: string[] = [];
		for ( let index = 0; index < window.localStorage.length; index += 1 ) {
			const key = window.localStorage.key( index );
			if ( key && key.startsWith( SCORE_CACHE_PREFIX ) ) {
				keysToRemove.push( key );
			}
		}

		keysToRemove.forEach( ( key ) => window.localStorage.removeItem( key ) );
	} catch {
		// Ignore storage errors.
	}
};

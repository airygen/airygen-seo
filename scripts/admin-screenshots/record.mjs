#!/usr/bin/env node
/* eslint-disable no-console */
import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const DEFAULT_BASE_URL = 'http://localhost:9000';
const DEFAULT_LOCALES_BASE = 'http://localhost:9000';
const DEFAULT_USER = 'admin';
const DEFAULT_PASS = 'admin';
const DEFAULT_ROUTES_PATH = 'scripts/admin-screenshots/routes/admin.json';
const DEFAULT_HIDE_SELECTORS = [
	'.notice',
	'.update-nag',
	'.updated',
	'.error',
];

const VIEWPORTS = {
	desktop: { width: 1920, height: 1080 },
	tablet: { width: 1024, height: 1366 },
	mobile: { width: 480, height: 900 },
};

const baseUrl = ( process.env.AIRYGEN_CAPTURE_BASE_URL || DEFAULT_BASE_URL ).replace( /\/$/, '' );
const localesBase = ( process.env.AIRYGEN_CAPTURE_LOCALES_BASE || DEFAULT_LOCALES_BASE ).replace( /\/$/, '' );
const requestedLocales = process.env.AIRYGEN_CAPTURE_LOCALES
	? process.env.AIRYGEN_CAPTURE_LOCALES.split( ',' ).map( ( value ) => value.trim() ).filter( Boolean )
	: null;
const username = process.env.AIRYGEN_CAPTURE_USER || DEFAULT_USER;
const password = process.env.AIRYGEN_CAPTURE_PASSWORD || DEFAULT_PASS;
const routesPath = process.env.AIRYGEN_CAPTURE_ROUTES || DEFAULT_ROUTES_PATH;
const outputDir = process.env.AIRYGEN_CAPTURE_OUTPUT || path.join( 'artifacts', 'admin-screencasts' );
const now = new Date();
const dateStamp = `${ now.getFullYear() }${ String( now.getMonth() + 1 ).padStart( 2, '0' ) }${ String( now.getDate() ).padStart( 2, '0' ) }`;
const headless = process.env.AIRYGEN_CAPTURE_HEADLESS !== '0';
const postId = process.env.AIRYGEN_CAPTURE_POST_ID || '50';
const navTimeoutMs = Number.parseInt( process.env.AIRYGEN_CAPTURE_NAV_TIMEOUT_MS || '60000', 10 );
const pauseMs = Number.parseInt( process.env.AIRYGEN_RECORD_PAUSE_MS || '5000', 10 );
const scrollDurationMs = Number.parseInt( process.env.AIRYGEN_RECORD_SCROLL_MS || '1800', 10 );
const tabPauseMs = Number.parseInt( process.env.AIRYGEN_RECORD_TAB_PAUSE_MS || '3000', 10 );
const forceRerun = process.env.AIRYGEN_CAPTURE_FORCE === '1';

const requestedViewports = ( process.env.AIRYGEN_CAPTURE_VIEWPORTS || 'desktop' )
	.split( ',' )
	.map( ( value ) => value.trim() )
	.filter( Boolean );

const viewportEntries = requestedViewports
	.map( ( key ) => [ key, VIEWPORTS[ key ] ] )
	.filter( ( [ , value ] ) => Boolean( value ) );

if ( viewportEntries.length === 0 ) {
	console.error( '[record] No valid viewport selected. Use AIRYGEN_CAPTURE_VIEWPORTS=desktop,tablet,mobile' );
	process.exit( 1 );
}

const importPlaywright = async () => {
	try {
		return await import( 'playwright' );
	} catch ( error ) {
		console.error( '[record] Missing dependency: playwright' );
		console.error( '[record] Install once with: pnpm add -D playwright' );
		throw error;
	}
};

const sanitizeFileName = ( value ) => value.replace( /[^a-zA-Z0-9-_]+/g, '-' );

const getLocaleSlug = ( siteLabel ) => {
	if ( siteLabel && siteLabel.trim() ) {
		return siteLabel.trim();
	}
	return 'zh';
};

const getManifestPath = ( dateOutputDir, routeName, locale ) =>
	path.join( dateOutputDir, routeName, 'manifest', locale, 'index.json' );

const getManifestItemKey = ( item ) => `${ item.type }:${ item.pageGroup }:${ item.viewport }`;

const normalizeViews = ( views ) =>
	views.map( ( view, index ) => ( {
		name: String( view?.name || `view-${ index + 1 }` ).trim(),
		enabledIfPresent: view?.enabledIfPresent ? String( view.enabledIfPresent ).trim() : null,
		waitFor: view?.waitFor ? String( view.waitFor ).trim() : null,
		click: view?.click ? String( view.click ).trim() : null,
		waitBeforeActions: Number.parseInt( view?.waitBeforeActions ?? 0, 10 ),
		tabIndex: ( () => {
			if ( Number.isInteger( view?.tabIndex ) && Number( view.tabIndex ) > 0 ) {
				return Number( view.tabIndex );
			}

			if (
				typeof view?.click === 'string' &&
				view.click.includes( '.airygen-module-page__tab button:has-text(' )
			) {
				return index + 1;
			}

			return null;
		} )(),
		waitAfterClick: Number.parseInt( view?.waitAfterClick ?? 300, 10 ),
		clicks: Array.isArray( view?.clicks )
			? view.clicks.map( ( item ) => ( {
				selector: String( item?.selector || '' ).trim(),
				wait: Number.parseInt( item?.wait ?? 300, 10 ),
			} ) ).filter( ( item ) => item.selector )
			: [],
		presses: Array.isArray( view?.presses )
			? view.presses.map( ( item ) => ( {
				key: String( item?.key || '' ).trim(),
				wait: Number.parseInt( item?.wait ?? 300, 10 ),
			} ) ).filter( ( item ) => item.key )
			: [],
		actions: Array.isArray( view?.actions )
			? view.actions.map( ( item ) => ( {
				type: String( item?.type || '' ).trim(),
				selector: String( item?.selector || '' ).trim(),
				key: String( item?.key || '' ).trim(),
				target: String( item?.target || '' ).trim(),
				deltaX: Number.isFinite( Number( item?.deltaX ) ) ? Number( item.deltaX ) : null,
				deltaY: Number.isFinite( Number( item?.deltaY ) ) ? Number( item.deltaY ) : null,
				wait: Number.parseInt( item?.wait ?? 300, 10 ),
			} ) ).filter( ( item ) => {
				if ( 'click' === item.type ) {
					return item.selector;
				}
				if ( 'press' === item.type ) {
					return item.key;
				}
				if ( 'drag' === item.type ) {
					return item.selector && ( item.target || ( Number.isFinite( item.deltaX ) && Number.isFinite( item.deltaY ) ) );
				}
				return false;
			} )
			: [],
		drags: Array.isArray( view?.drags )
			? view.drags.map( ( item ) => ( {
				source: String( item?.source || '' ).trim(),
				target: String( item?.target || '' ).trim(),
				deltaX: Number.isFinite( Number( item?.deltaX ) ) ? Number( item.deltaX ) : null,
				deltaY: Number.isFinite( Number( item?.deltaY ) ) ? Number( item.deltaY ) : null,
				wait: Number.parseInt( item?.wait ?? 300, 10 ),
			} ) ).filter(
				( item ) =>
					item.source &&
					( item.target || ( Number.isFinite( item.deltaX ) && Number.isFinite( item.deltaY ) ) )
			)
			: [],
	} ) );

const shouldRecordView = async ( page, view ) => {
	if ( ! view.enabledIfPresent ) {
		return true;
	}

	const target = await getFirstVisibleLocator( page, view.enabledIfPresent );
	if ( target ) {
		return true;
	}

	console.warn( `[record] skip view ${ view.name } - missing prerequisite: ${ view.enabledIfPresent }` );
	return false;
};

const performPrimaryViewNavigation = async ( page, view ) => {
	if ( view.tabIndex ) {
		await clickIfPresent(
			page,
			`.airygen-module-page__tab button:nth-of-type(${ view.tabIndex })`,
			view.waitAfterClick
		);
		return;
	}

	if ( view.click ) {
		await clickIfPresent( page, view.click, view.waitAfterClick );
	}
};

const normalizeGroupType = ( rawType, routePath ) => {
	const type = typeof rawType === 'string' ? rawType.trim().toLowerCase() : '';
	if ( 'admin' === type || 'editor' === type ) {
		return type;
	}

	return routePath.includes( 'post.php?' ) ? 'editor' : 'admin';
};

const parseRouteGroups = ( parsed, sourceName, routeName ) => {
	if ( ! Array.isArray( parsed ) || parsed.length === 0 ) {
		throw new Error( `Routes file is empty or invalid: ${ sourceName }` );
	}

	if ( parsed[ 0 ]?.views ) {
		return parsed.map( ( group, index ) => {
			const name = String( group?.name || `group-${ index + 1 }` ).trim();
			const routePath = String( group?.path || '' ).trim().replace( /\{POST_ID\}/g, postId );
			const waitFor = String( group?.waitFor || '#airygen-root' ).trim();

			if ( ! routePath ) {
				throw new Error( `Group at index ${ index } is missing "path" in ${ sourceName }` );
			}

			return {
				routeName,
				type: normalizeGroupType( group?.type, routePath ),
				name,
				path: routePath,
				waitFor,
				views: normalizeViews( Array.isArray( group.views ) && group.views.length ? group.views : [ {} ] ),
			};
		} );
	}

	return parsed.map( ( route, index ) => {
		const name = String( route?.name || `route-${ index + 1 }` ).trim();
		const routePath = String( route?.path || '' ).trim().replace( /\{POST_ID\}/g, postId );
		const waitFor = String( route?.waitFor || '#airygen-root' ).trim();

		if ( ! routePath ) {
			throw new Error( `Route at index ${ index } is missing "path" in ${ sourceName }` );
		}

		return {
			routeName,
			type: normalizeGroupType( route?.type, routePath ),
			name,
			path: routePath,
			waitFor,
			views: normalizeViews( [ route ] ),
		};
	} );
};

const loadRoutesFile = async ( filePath ) => {
	const raw = await fs.readFile( filePath, 'utf8' );
	const stripped = raw.replace( /\/\/[^\n]*/g, '' );
	return parseRouteGroups(
		JSON.parse( stripped ),
		filePath,
		path.basename( filePath, '.json' )
	);
};

const loadRouteGroups = async () => {
	const stats = await fs.stat( routesPath );

	if ( stats.isDirectory() ) {
		const entries = ( await fs.readdir( routesPath ) )
			.filter( ( entry ) => entry.endsWith( '.json' ) )
			.sort();

		if ( entries.length === 0 ) {
			throw new Error( `No route JSON files found in directory: ${ routesPath }` );
		}

		const groupedRoutes = await Promise.all(
			entries.map( ( entry ) => loadRoutesFile( path.join( routesPath, entry ) ) )
		);

		return groupedRoutes.flat();
	}

	return loadRoutesFile( routesPath );
};

const groupRouteGroupsByRoute = ( routeGroups ) => {
	const grouped = new Map();
	for ( const group of routeGroups ) {
		const items = grouped.get( group.routeName ) || [];
		items.push( group );
		grouped.set( group.routeName, items );
	}
	return grouped;
};

const login = async ( page, siteBaseUrl ) => {
	await page.goto( `${ siteBaseUrl }/wp-login.php`, {
		waitUntil: 'commit',
		timeout: navTimeoutMs,
	} );

	const alreadyLoggedIn = await page.locator( '#wpadminbar' ).count();
	if ( alreadyLoggedIn > 0 ) {
		return;
	}

	await page.fill( '#user_login', username );
	await page.fill( '#user_pass', password );
	await page.click( '#wp-submit', { noWaitAfter: true } );

	await Promise.race( [
		page.waitForSelector( '#wpadminbar', { timeout: navTimeoutMs } ),
		page.waitForFunction(
			() => ! window.location.pathname.endsWith( '/wp-login.php' ),
			{ timeout: navTimeoutMs }
		),
	] );
};

const hideUiNoise = async ( page ) => {
	await page.addStyleTag( {
		content: `${ DEFAULT_HIDE_SELECTORS.join( ',' ) } { display:none !important; }`,
	} );
};

const ensureCursorOverlay = async ( page ) => {
	await page.evaluate( () => {
		if ( document.getElementById( 'airygen-e2e-cursor-style' ) ) {
			return;
		}

		const style = document.createElement( 'style' );
		style.id = 'airygen-e2e-cursor-style';
		style.textContent = `
			#airygen-e2e-cursor {
				position: fixed;
				left: 0;
				top: 0;
				width: 18px;
				height: 18px;
				border: 2px solid rgba(15, 23, 42, 0.9);
				border-radius: 9999px;
				background: rgba(255, 255, 255, 0.85);
				box-shadow: 0 2px 10px rgba(15, 23, 42, 0.18);
				pointer-events: none;
				transform: translate(-50%, -50%);
				z-index: 2147483647;
				transition: left 120ms ease, top 120ms ease, transform 120ms ease;
			}
			.airygen-e2e-cursor-click {
				position: fixed;
				left: 0;
				top: 0;
				width: 14px;
				height: 14px;
				border: 2px solid rgba(14, 165, 233, 0.75);
				border-radius: 9999px;
				pointer-events: none;
				transform: translate(-50%, -50%) scale(0.6);
				opacity: 0.9;
				z-index: 2147483646;
				animation: airygen-e2e-ripple 480ms ease-out forwards;
			}
			@keyframes airygen-e2e-ripple {
				to {
					transform: translate(-50%, -50%) scale(3.2);
					opacity: 0;
				}
			}
		`;
		document.head.appendChild( style );

		const cursor = document.createElement( 'div' );
		cursor.id = 'airygen-e2e-cursor';
		document.body.appendChild( cursor );

		window.__airygenMoveCursor = ( x, y ) => {
			cursor.style.left = `${ x }px`;
			cursor.style.top = `${ y }px`;
		};

		window.__airygenClickCursor = ( x, y ) => {
			const pulse = document.createElement( 'div' );
			pulse.className = 'airygen-e2e-cursor-click';
			pulse.style.left = `${ x }px`;
			pulse.style.top = `${ y }px`;
			document.body.appendChild( pulse );
			window.setTimeout( () => pulse.remove(), 520 );
		};
	} );
};

const isEditorRoute = ( group ) => 'editor' === group.type;

const detectEditorMode = async ( page, group ) => {
	if ( ! isEditorRoute( group ) ) {
		return null;
	}

	const blockSelectors = [
		'.interface-interface-skeleton',
		'.edit-post-layout',
		'.block-editor-page',
	];

	for ( const selector of blockSelectors ) {
		if ( await page.locator( selector ).count() > 0 ) {
			return 'block';
		}
	}

	if ( await page.locator( '#poststuff' ).count() > 0 ) {
		return 'classic';
	}

	return 'block';
};

const getCapturePrefix = ( group, editorMode = null ) => {
	if ( ! isEditorRoute( group ) ) {
		return 'admin';
	}

	if ( 'classic' === editorMode ) {
		return 'classic';
	}

	return 'editor';
};

const getManifestType = ( group, editorMode = null ) => {
	if ( ! isEditorRoute( group ) ) {
		return 'admin';
	}

	if ( 'classic' === editorMode ) {
		return 'classic';
	}

	return 'editor';
};

const waitForReady = async ( page, selector, group ) => {
	if ( selector ) {
		try {
			await page.waitForSelector( selector, { timeout: 15000 } );
			return;
		} catch ( error ) {
			if ( ! isEditorRoute( group ) ) {
				throw error;
			}
		}
	}

	if ( isEditorRoute( group ) ) {
		const fallbackSelectors = [
			'#poststuff',
			'.interface-interface-skeleton',
			'.edit-post-layout',
			'.block-editor-page',
		];

		for ( const fallback of fallbackSelectors ) {
			if ( await page.locator( fallback ).count() > 0 ) {
				return;
			}
		}
	}

	if ( selector ) {
		await page.waitForSelector( selector, { timeout: 15000 } );
	}
};

const getFirstVisibleLocator = async ( page, selector ) => {
	const locator = page.locator( selector );
	const count = await locator.count();

	for ( let index = 0; index < count; index += 1 ) {
		const candidate = locator.nth( index );
		const box = await candidate.boundingBox();
		if ( box ) {
			return { locator: candidate, box };
		}
	}

	return null;
};

const dismissEditorOverlays = async ( page, group ) => {
	if ( ! isEditorRoute( group ) ) {
		return;
	}

	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );
};

const clickIfPresent = async ( page, selector, waitMs ) => {
	const visibleTarget = await getFirstVisibleLocator( page, selector );
	if ( ! visibleTarget ) {
		console.warn( `[record] skip missing selector: ${ selector }` );
		return false;
	}
	const target = visibleTarget.locator;
	await target.scrollIntoViewIfNeeded();

	const box = ( await target.boundingBox() ) || visibleTarget.box;
	if ( box ) {
		const clickX = box.x + ( box.width / 2 );
		const clickY = box.y + ( box.height / 2 );
		await page.mouse.move( clickX, clickY, { steps: 18 } );
		await page.evaluate(
			( { x, y } ) => {
				window.__airygenMoveCursor?.( x, y );
			},
			{ x: clickX, y: clickY }
		);
		await page.waitForTimeout( 180 );
		await target.click( { timeout: 10000 } );
		await page.evaluate(
			( { x, y } ) => {
				window.__airygenMoveCursor?.( x, y );
				window.__airygenClickCursor?.( x, y );
			},
			{ x: clickX, y: clickY }
		);
	} else {
		await target.click( { timeout: 10000 } );
	}
	await page.waitForTimeout( waitMs );
	return true;
};

const dragIfPresent = async ( page, sourceSelector, targetSelector, deltaX, deltaY, waitMs ) => {
	const visibleSource = await getFirstVisibleLocator( page, sourceSelector );
	if ( ! visibleSource ) {
		console.warn( `[record] skip missing drag source: ${ sourceSelector }` );
		return false;
	}
	const source = visibleSource.locator;

	await source.scrollIntoViewIfNeeded();

	const sourceBox = ( await source.boundingBox() ) || visibleSource.box;
	let targetBox = null;
	let target = null;

	if ( targetSelector ) {
		const visibleTarget = await getFirstVisibleLocator( page, targetSelector );
		if ( ! visibleTarget ) {
			console.warn( `[record] skip missing drag target: ${ targetSelector }` );
			return false;
		}
		target = visibleTarget.locator;
		await target.scrollIntoViewIfNeeded();
		targetBox = ( await target.boundingBox() ) || visibleTarget.box;
	}

	if ( sourceBox && targetBox ) {
		const startX = sourceBox.x + ( sourceBox.width / 2 );
		const startY = sourceBox.y + ( sourceBox.height / 2 );

		await page.mouse.move( startX, startY, { steps: 18 } );
		await page.evaluate(
			( { x, y } ) => {
				window.__airygenMoveCursor?.( x, y );
			},
			{ x: startX, y: startY }
		);
		await page.waitForTimeout( 160 );
	}

	if ( target ) {
		await source.dragTo( target, { timeout: 10000 } );
	} else if ( sourceBox && Number.isFinite( deltaX ) && Number.isFinite( deltaY ) ) {
		const startX = sourceBox.x + ( sourceBox.width / 2 );
		const startY = sourceBox.y + ( sourceBox.height / 2 );
		const endX = startX + deltaX;
		const endY = startY + deltaY;
		await page.mouse.move( startX, startY, { steps: 18 } );
		await page.evaluate(
			( { x, y } ) => {
				window.__airygenMoveCursor?.( x, y );
			},
			{ x: startX, y: startY }
		);
		await page.waitForTimeout( 120 );
		await page.mouse.down();
		await page.mouse.move( endX, endY, { steps: 24 } );
		await page.evaluate(
			( { x, y } ) => {
				window.__airygenMoveCursor?.( x, y );
			},
			{ x: endX, y: endY }
		);
		await page.mouse.up();
		targetBox = { x: endX, y: endY, width: 0, height: 0 };
	} else {
		return false;
	}

	if ( targetBox ) {
		const endX = targetBox.x + ( targetBox.width / 2 );
		const endY = targetBox.y + ( targetBox.height / 2 );
		await page.evaluate(
			( { x, y } ) => {
				window.__airygenMoveCursor?.( x, y );
				window.__airygenClickCursor?.( x, y );
			},
			{ x: endX, y: endY }
		);
	}

	await page.waitForTimeout( waitMs );
	return true;
};

const scrollToPosition = async ( page, targetY, durationMs ) => {
	await page.evaluate(
		async ( { target, duration } ) => {
			const start = window.scrollY;
			const distance = target - start;
			const startTime = performance.now();
			await new Promise( ( resolve ) => {
				const step = ( nowValue ) => {
					const progress = Math.min( ( nowValue - startTime ) / duration, 1 );
					window.scrollTo( 0, start + ( distance * progress ) );
					if ( progress < 1 ) {
						window.requestAnimationFrame( step );
						return;
					}
					resolve();
				};
				window.requestAnimationFrame( step );
			} );
		},
		{ target: targetY, duration: durationMs }
	);
};

const performViewActions = async ( page, view ) => {
	if ( view.tabIndex ) {
		await clickIfPresent(
			page,
			`.airygen-module-page__tab button:nth-of-type(${ view.tabIndex })`,
			view.waitAfterClick
		);
	}

	if ( view.click && ! view.tabIndex ) {
		await clickIfPresent( page, view.click, view.waitAfterClick );
	}

	if ( view.actions.length > 0 ) {
		for ( const step of view.actions ) {
			if ( 'click' === step.type ) {
				await clickIfPresent( page, step.selector, step.wait );
				continue;
			}

			if ( 'press' === step.type ) {
				await page.keyboard.press( step.key );
				await page.waitForTimeout( step.wait );
				continue;
			}

			if ( 'drag' === step.type ) {
				await dragIfPresent( page, step.selector, step.target, step.deltaX, step.deltaY, step.wait );
			}
		}
		return;
	}

	for ( const step of view.clicks ) {
		await clickIfPresent( page, step.selector, step.wait );
	}

	for ( const step of view.presses ) {
		await page.keyboard.press( step.key );
		await page.waitForTimeout( step.wait );
	}

	for ( const step of view.drags ) {
		await dragIfPresent( page, step.source, step.target, step.deltaX, step.deltaY, step.wait );
	}
};

const recordView = async ( page, group, view, viewportKey, screenshotsDir, editorMode ) => {
	if ( view.waitBeforeActions > 0 ) {
		await page.waitForTimeout( view.waitBeforeActions );
	}

	await performPrimaryViewNavigation( page, view );

	if ( ! ( await shouldRecordView( page, view ) ) ) {
		return null;
	}

	await performViewActions( page, {
		...view,
		tabIndex: null,
		click: null,
	} );

	await waitForReady( page, view.waitFor, group );

	await hideUiNoise( page );
	await ensureCursorOverlay( page );
	await page.waitForTimeout( tabPauseMs );

	const screenshotPath = path.join(
		screenshotsDir,
		`${ getCapturePrefix( group, editorMode ) }-${ sanitizeFileName( group.name ) }-${ sanitizeFileName( view.name ) }-${ viewportKey }.png`
	);
	await page.screenshot( {
		path: screenshotPath,
		fullPage: false,
	} );
	console.log( `[record] screenshot ${ group.routeName }/${ path.basename( screenshotPath ) }` );

	const scrollMetrics = await page.evaluate( () => ( {
		height: document.documentElement.scrollHeight,
		viewport: window.innerHeight,
	} ) );

	if ( scrollMetrics.height > scrollMetrics.viewport + 80 ) {
		await scrollToPosition( page, scrollMetrics.height - scrollMetrics.viewport, scrollDurationMs );
		await page.waitForTimeout( pauseMs );
		await scrollToPosition( page, 0, scrollDurationMs );
		await page.waitForTimeout( 800 );
	} else {
		await page.waitForTimeout( pauseMs );
	}
	return screenshotPath;
};

const renameVideoFile = async ( videoPath, targetPath ) => {
	await fs.mkdir( path.dirname( targetPath ), { recursive: true } );
	try {
		await fs.rm( targetPath, { force: true } );
	} catch {}
	await fs.rename( videoPath, targetPath );
};

const gotoWithRetry = async ( page, url, options, attempts = 3 ) => {
	let lastError = null;

	for ( let index = 0; index < attempts; index += 1 ) {
		try {
			await page.goto( url, options );
			return;
		} catch ( error ) {
			lastError = error;
			if ( index === attempts - 1 ) {
				break;
			}
			await page.waitForTimeout( 1500 * ( index + 1 ) );
		}
	}

	throw lastError;
};

const recordGroup = async ( browser, storageState, siteBaseUrl, dateOutputDir, locale, group, viewportKey, viewport ) => {
	const routeDir = path.join( dateOutputDir, group.routeName );
	const tempVideoDir = path.join( routeDir, '.tmp-video', viewportKey, group.name );
	const screenshotsDir = path.join( routeDir, 'screenshots', locale );
	const videosDir = path.join( routeDir, 'videos', locale );

	await fs.mkdir( tempVideoDir, { recursive: true } );
	await fs.mkdir( screenshotsDir, { recursive: true } );
	await fs.mkdir( videosDir, { recursive: true } );

	const context = await browser.newContext( {
		viewport,
		storageState,
		deviceScaleFactor: 1,
		recordVideo: {
			dir: tempVideoDir,
			size: viewport,
		},
	} );

	const page = await context.newPage();

	const url = `${ siteBaseUrl }/wp-admin/${ group.path.replace( /^\//, '' ) }`;
	await gotoWithRetry( page, url, {
		waitUntil: 'commit',
		timeout: navTimeoutMs,
	} );
	await page.waitForTimeout( 600 );

	await waitForReady( page, group.waitFor, group );
	await dismissEditorOverlays( page, group );
	const editorMode = await detectEditorMode( page, group );

	const screenshotPaths = [];
	for ( const view of group.views ) {
		const screenshotPath = await recordView( page, group, view, viewportKey, screenshotsDir, editorMode );
		if ( screenshotPath ) {
			screenshotPaths.push( screenshotPath );
		}
	}

	const video = page.video();
	await context.close();

	let targetPath = null;
	if ( video ) {
		const tempPath = await video.path();
		targetPath = path.join(
			videosDir,
			`${ getCapturePrefix( group, editorMode ) }-${ sanitizeFileName( group.name ) }-${ viewportKey }.webm`
		);
		await renameVideoFile( tempPath, targetPath );
		console.log( `[record] video ${ targetPath }` );
	}

	return {
		route: group.routeName,
		type: getManifestType( group, editorMode ),
		locale,
		pageGroup: group.name,
		viewport: viewportKey,
		videoPath: targetPath,
		screenshots: screenshotPaths,
		status: 'success',
		lastUpdated: new Date().toISOString(),
	};
};

const writeRouteManifest = async ( dateOutputDir, routeName, locale, items ) => {
	const manifestDir = path.join( dateOutputDir, routeName, 'manifest', locale );
	await fs.mkdir( manifestDir, { recursive: true } );
	const manifestPath = getManifestPath( dateOutputDir, routeName, locale );
	let existingItems = [];
	try {
		const raw = await fs.readFile( manifestPath, 'utf8' );
		const parsed = JSON.parse( raw );
		if ( Array.isArray( parsed ) ) {
			existingItems = parsed;
		}
	} catch {}

	const merged = new Map();
	for ( const item of existingItems ) {
		if ( item && item.type && item.pageGroup && item.viewport ) {
			merged.set( getManifestItemKey( item ), item );
		}
	}

	for ( const item of items ) {
		merged.set( getManifestItemKey( item ), {
			...item,
			videoPath: item.videoPath ? path.relative( dateOutputDir, item.videoPath ) : null,
			screenshots: item.screenshots.map( ( screenshotPath ) => path.relative( dateOutputDir, screenshotPath ) ),
		} );
	}

	const normalizedItems = Array.from( merged.values() ).map( ( item ) => ( {
		...item,
	} ) );
	await fs.writeFile( manifestPath, `${ JSON.stringify( normalizedItems, null, 2 ) }\n`, 'utf8' );
	console.log( `[record] manifest ${ manifestPath }` );
};

const routeManifestExists = async ( dateOutputDir, routeName, locale ) => {
	try {
		await fs.access( getManifestPath( dateOutputDir, routeName, locale ) );
		return true;
	} catch {
		return false;
	}
};

const recordRoute = async (
	browser,
	storageState,
	siteBaseUrl,
	dateOutputDir,
	locale,
	routeName,
	groups
) => {
	const items = [];
	for ( const [ viewportKey, viewport ] of viewportEntries ) {
		console.log( `[record] ${ siteBaseUrl } route=${ routeName } viewport=${ viewportKey } ${ viewport.width }x${ viewport.height }` );
		for ( const group of groups ) {
			items.push(
				await recordGroup(
					browser,
					storageState,
					siteBaseUrl,
					dateOutputDir,
					locale,
					group,
					viewportKey,
					viewport
				)
			);
		}
	}

	await writeRouteManifest( dateOutputDir, routeName, locale, items );
};

const recordSite = async ( browser, routeGroups, storageState, siteBaseUrl, dateOutputDir, locale ) => {
	await fs.mkdir( dateOutputDir, { recursive: true } );

	const groupedRoutes = groupRouteGroupsByRoute( routeGroups );
	const failures = [];

	for ( const [ routeName, groups ] of groupedRoutes.entries() ) {
		if ( ! forceRerun && await routeManifestExists( dateOutputDir, routeName, locale ) ) {
			console.log( `[record] skip ${ routeName } (${ locale }) - manifest exists` );
			continue;
		}

		try {
			await recordRoute(
				browser,
				storageState,
				siteBaseUrl,
				dateOutputDir,
				locale,
				routeName,
				groups
			);
		} catch ( error ) {
			failures.push( {
				routeName,
				error: error instanceof Error ? error.message : String( error ),
			} );
			console.error( `[record] route failed ${ routeName } (${ locale })` );
			console.error( error instanceof Error ? error.message : String( error ) );
		}
	}

	if ( failures.length > 0 ) {
		const lines = failures.map( ( failure ) => `${ failure.routeName }: ${ failure.error }` );
		throw new Error( `Recording failed for ${ locale }: ${ lines.join( '; ' ) }` );
	}
};

const main = async () => {
	const routeGroups = await loadRouteGroups();
	const sites = requestedLocales
		? requestedLocales.map( ( locale ) => ( {
			label: locale,
			baseUrl: `${ localesBase }/${ locale }`,
		} ) )
		: [ { label: null, baseUrl } ];

	const { chromium } = await importPlaywright();
	const browser = await chromium.launch( { headless } );

	try {
		const authContext = await browser.newContext( { viewport: VIEWPORTS.desktop } );
		const authPage = await authContext.newPage();
		await login( authPage, baseUrl );
		const storageState = await authContext.storageState();
		await authContext.close();

		for ( const site of sites ) {
			if ( site.label ) {
				console.log( `[record] === locale: ${ site.label } ===` );
			}
			await recordSite(
				browser,
				routeGroups,
				storageState,
				site.baseUrl,
				path.join( outputDir, dateStamp ),
				getLocaleSlug( site.label )
			);
		}
	} finally {
		await browser.close();
	}

	console.log( `[record] done -> ${ path.join( outputDir, dateStamp ) }` );
};

main().catch( ( error ) => {
	console.error( '[record] failed' );
	console.error( error instanceof Error ? error.message : String( error ) );
	process.exit( 1 );
} );

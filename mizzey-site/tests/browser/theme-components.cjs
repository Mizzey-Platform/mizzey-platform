#!/usr/bin/env node
/**
 * The overlays and tabs of spec 066, slice 1, in a real browser (AC-14, NFR-04, NFR-11).
 *
 *     node mizzey-site/tests/browser/theme-components.cjs
 *
 * On the component preview (mizzey-theme/tests/preview.php), in English (ltr) and Arabic (rtl), at 1440 and 390
 * wide, for the mini cart drawer, the phone menu drawer, the bottom sheet and the dialog: opening moves focus inside;
 * Tab and Shift+Tab stay inside; Escape closes and focus returns to the control that opened it; a press on the scrim
 * closes and focus returns; in Arabic the end drawer comes from the left and the start drawer from the right. With
 * reduced motion the drawer does not slide (its transform does not change), fades in 120 ms or less, and fades out
 * the same way instead of vanishing. Tabs move with the arrow keys, mirrored in Arabic. The board values a computed
 * style shows: the notice words (C1-Notices: 13/20, Arabic 14/24), the tab indicator's 14 px inset, and a steps
 * line that fits a 358 column in both languages with every label whole.
 *
 * Prints one JSON object; exits 0 when every check passes, 1 when one fails, 2 when it cannot run.
 */

const { serve, loadPlaywright } = require( './_theme-preview.cjs' );

const OVERLAYS = [ 'pv-cart', 'pv-menu', 'pv-sheet', 'pv-dialog' ];
const SIZES = [ { width: 1440, height: 900 }, { width: 390, height: 844 } ];

async function overlayChecks( page, id, dir, size ) {
	const out = [];
	const check = ( ok, what ) => out.push( { ok: !! ok, what: `${ dir } ${ size.width } ${ id }: ${ what }` } );
	const trigger = page.locator( `[aria-controls="${ id }"]` ).first();
	const isOpen = () => page.evaluate( ( i ) => document.getElementById( i ).open, id );
	const focusInside = () => page.evaluate( ( i ) => document.getElementById( i ).contains( document.activeElement ), id );
	const focusOnTrigger = () => page.evaluate( ( i ) => document.activeElement === document.querySelector( `[aria-controls="${ i }"]` ), id );

	await trigger.scrollIntoViewIfNeeded();
	await trigger.click();
	await page.waitForFunction( ( i ) => document.getElementById( i ).open, id, { timeout: 3000 } ).catch( () => {} );
	check( await isOpen(), 'opens' );
	check( await focusInside(), 'focus moves inside on open' );

	let trapped = true;
	for ( let i = 0; i < 8; i++ ) {
		await page.keyboard.press( 'Tab' );
		trapped = trapped && ( await focusInside() );
	}
	for ( let i = 0; i < 8; i++ ) {
		await page.keyboard.press( 'Shift+Tab' );
		trapped = trapped && ( await focusInside() );
	}
	check( trapped, 'Tab and Shift+Tab stay inside' );

	await page.keyboard.press( 'Escape' );
	await page.waitForFunction( ( i ) => ! document.getElementById( i ).open, id, { timeout: 3000 } ).catch( () => {} );
	check( ! ( await isOpen() ), 'Escape closes' );
	check( await focusOnTrigger(), 'focus returns after Escape' );

	await trigger.click();
	await page.waitForFunction( ( i ) => document.getElementById( i ).open, id, { timeout: 3000 } ).catch( () => {} );
	await page.waitForTimeout( 700 );
	const geometry = await page.evaluate( ( i ) => {
		const r = document.getElementById( i ).querySelector( '.mizzey-overlay__panel' ).getBoundingClientRect();
		return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, vw: innerWidth, vh: innerHeight };
	}, id );
	if ( id === 'pv-cart' || id === 'pv-menu' ) {
		const fromEnd = id === 'pv-cart';
		const onRight = Math.abs( geometry.right - geometry.vw ) < 1;
		const onLeft = Math.abs( geometry.left ) < 1;
		const expectRight = ( dir === 'ltr' ) === fromEnd;
		check( expectRight ? onRight : onLeft, `comes from the ${ expectRight ? 'right' : 'left' } edge` );
	}
	const points = [ [ 4, 4 ], [ geometry.vw - 4, 4 ], [ 4, geometry.vh - 4 ], [ geometry.vw - 4, geometry.vh - 4 ], [ geometry.vw / 2, 4 ] ];
	const outside = points.find( ( [ x, y ] ) => x < geometry.left || x > geometry.right || y < geometry.top || y > geometry.bottom );
	if ( outside ) {
		await page.mouse.click( outside[ 0 ], outside[ 1 ] );
		await page.waitForFunction( ( i ) => ! document.getElementById( i ).open, id, { timeout: 3000 } ).catch( () => {} );
		check( ! ( await isOpen() ), 'a press on the scrim closes' );
		check( await focusOnTrigger(), 'focus returns after the scrim' );
	} else {
		check( false, 'no part of the scrim was visible to press' );
	}
	return out;
}

async function tabChecks( page, dir ) {
	const forward = dir === 'rtl' ? 'ArrowLeft' : 'ArrowRight';
	await page.locator( '#pv-tabs-tab-0' ).focus();
	await page.keyboard.press( forward );
	await page.waitForFunction( () => document.activeElement.id === 'pv-tabs-tab-1', null, { timeout: 2000 } ).catch( () => {} );
	const state = await page.evaluate( () => ( {
		selected: document.getElementById( 'pv-tabs-tab-1' ).getAttribute( 'aria-selected' ),
		focused: document.activeElement.id,
		panel: ! document.getElementById( 'pv-tabs-panel-1' ).hidden && document.getElementById( 'pv-tabs-panel-0' ).hidden,
	} ) );
	await page.keyboard.press( 'End' );
	await page.waitForFunction( () => document.activeElement.id === 'pv-tabs-tab-3', null, { timeout: 2000 } ).catch( () => {} );
	const end = await page.evaluate( () => document.activeElement.id );
	return [
		{ ok: state.selected === 'true' && state.focused === 'pv-tabs-tab-1' && state.panel, what: `${ dir }: ${ forward } selects the next tab and shows its panel` },
		{ ok: end === 'pv-tabs-tab-3', what: `${ dir }: End moves to the last tab` },
	];
}

/* What the boards fix that a computed style can show: C1-Notices' notice words (13/20, Arabic 14/24), the tab
   indicator's 14 px inset, and the steps line that fits a 358 column in both languages with every label whole. */
async function boardValueChecks( page, dir, size ) {
	const got = await page.evaluate( () => {
		const notice = getComputedStyle( document.querySelector( '.mizzey-notice' ) );
		const indicator = getComputedStyle( document.querySelector( '.mizzey-tabs__indicator' ) );
		const host = document.querySelector( '#pv-steps-358' );
		const list = host.querySelector( '.mizzey-steps' );
		const box = host.getBoundingClientRect();
		const steps = [ ...host.querySelectorAll( '.mizzey-steps__step' ) ].map( ( step ) => {
			const r = step.getBoundingClientRect();
			return { inside: r.left >= box.left - 0.5 && r.right <= box.right + 0.5, whole: step.scrollWidth <= step.clientWidth + 0.5, text: step.textContent.trim() };
		} );
		return {
			fontSize: parseFloat( notice.fontSize ),
			lineHeight: parseFloat( notice.lineHeight ),
			inset: [ indicator.paddingInlineStart, indicator.paddingInlineEnd ],
			hostWidth: Math.round( box.width ),
			overflow: list.scrollWidth - list.clientWidth,
			steps,
		};
	} );
	const want = dir === 'rtl' ? [ 14, 24 ] : [ 13, 20 ];
	const where = `${ dir } ${ size.width }`;
	return [
		{ ok: Math.round( got.fontSize ) === want[ 0 ] && Math.round( got.lineHeight ) === want[ 1 ], what: `${ where }: notice words are ${ want.join( '/' ) } (C1-Notices), got ${ got.fontSize }/${ got.lineHeight }` },
		{ ok: got.inset[ 0 ] === '14px' && got.inset[ 1 ] === '14px', what: `${ where }: the tab indicator is inset 14 px, got ${ got.inset.join( ' ' ) }` },
		{ ok: got.hostWidth <= 358 && got.overflow <= 0 && got.steps.length === 4 && got.steps.every( ( s ) => s.inside && s.whole ), what: `${ where }: the steps line fits ${ got.hostWidth } px with every label whole (${ JSON.stringify( got.steps ) }, overflow ${ got.overflow })` },
	];
}

/* Under reduced motion an overlay still fades out in 120 ms: just after it closes it is still shown, fading. */
async function reducedMotionCloseCheck( browser, base ) {
	const context = await browser.newContext( { reducedMotion: 'reduce', viewport: SIZES[ 0 ] } );
	const page = await context.newPage();
	await page.goto( `${ base }/tests/preview-ltr.html`, { waitUntil: 'networkidle' } );
	await page.locator( '[aria-controls="pv-cart"]' ).click();
	await page.waitForTimeout( 300 );
	const during = await page.evaluate( () => new Promise( ( resolve ) => {
		const dialog = document.getElementById( 'pv-cart' );
		dialog.close();
		setTimeout( () => {
			const panel = getComputedStyle( dialog.querySelector( '.mizzey-overlay__panel' ) );
			resolve( { display: getComputedStyle( dialog ).display, opacity: parseFloat( panel.opacity ) } );
		}, 40 );
	} ) );
	await page.waitForTimeout( 300 );
	const after = await page.evaluate( () => getComputedStyle( document.getElementById( 'pv-cart' ) ).display );
	await context.close();
	return [
		{ ok: during.display !== 'none' && during.opacity > 0 && during.opacity < 1, what: `reduced motion: closing fades instead of vanishing (40 ms in: display ${ during.display }, opacity ${ during.opacity })` },
		{ ok: after === 'none', what: `reduced motion: the overlay is gone once the fade ends (${ after })` },
	];
}

async function reducedMotionCheck( browser, base ) {
	const context = await browser.newContext( { reducedMotion: 'reduce', viewport: SIZES[ 0 ] } );
	const page = await context.newPage();
	await page.goto( `${ base }/tests/preview-ltr.html`, { waitUntil: 'networkidle' } );
	await page.waitForFunction( () => document.querySelector( '[data-wp-interactive]' ) && window.getComputedStyle );
	const before = await page.evaluate( () => getComputedStyle( document.querySelector( '#pv-cart .mizzey-overlay__panel' ) ).transform );
	await page.locator( '[aria-controls="pv-cart"]' ).click();
	const after = await page.evaluate( () => {
		const panel = document.querySelector( '#pv-cart .mizzey-overlay__panel' );
		const style = getComputedStyle( panel );
		return { transform: style.transform, durations: style.transitionDuration.split( ',' ).map( ( d ) => parseFloat( d ) ) };
	} );
	await context.close();
	return [
		{ ok: before === 'none' && after.transform === 'none', what: 'reduced motion: the drawer does not slide' },
		{ ok: Math.max( ...after.durations ) <= 0.12, what: `reduced motion: it fades in 120 ms or less (${ after.durations.join( ', ' ) } s)` },
	];
}

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' } ) );
		process.exit( 2 );
	}
	let server;
	try {
		server = await serve();
	} catch ( error ) {
		console.log( JSON.stringify( { error: error.message } ) );
		process.exit( 2 );
	}
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	const results = [];
	const errors = [];
	try {
		for ( const dir of [ 'ltr', 'rtl' ] ) {
			for ( const size of SIZES ) {
				const context = await browser.newContext( { viewport: size } );
				const page = await context.newPage();
				page.on( 'pageerror', ( e ) => errors.push( `${ dir } ${ size.width }: ${ e.message }` ) );
				page.on( 'console', ( m ) => m.type() === 'error' && errors.push( `${ dir } ${ size.width }: ${ m.text() }` ) );
				await page.goto( `${ server.url }/tests/preview-${ dir }.html`, { waitUntil: 'networkidle' } );
				await page.waitForTimeout( 300 );
				for ( const id of OVERLAYS ) {
					results.push( ...( await overlayChecks( page, id, dir, size ) ) );
				}
				if ( size.width === 1440 ) {
					results.push( ...( await tabChecks( page, dir ) ) );
				}
				results.push( ...( await boardValueChecks( page, dir, size ) ) );
				await context.close();
			}
		}
		results.push( ...( await reducedMotionCheck( browser, server.url ) ) );
		results.push( ...( await reducedMotionCloseCheck( browser, server.url ) ) );
	} finally {
		await browser.close();
		server.close();
	}
	results.push( { ok: errors.length === 0, what: `no script error in the page${ errors.length ? ': ' + errors.slice( 0, 3 ).join( ' | ' ) : '' }` } );
	const failed = results.filter( ( r ) => ! r.ok );
	console.log( JSON.stringify( { passed: results.length - failed.length, failed: failed.map( ( r ) => r.what ), total: results.length } ) );
	process.exit( failed.length ? 1 : 0 );
} )();

#!/usr/bin/env node
/**
 * Drag to close, by touch, for the bottom sheets and the side drawers (C1-Motion-Playground, "Overlays"; the owner's
 * iPhone, 25 November 2026), on the theme's component preview (no WordPress needed; see _theme-preview.cjs).
 *
 * At 390 with touch, in Chromium and WebKit, English and Arabic:
 * - a sheet dragged down by its handle 40% of its height closes; 15% springs back (passing through frames between)
 *   and stays open; a flick (20% in two quick moves) closes; dragged from its body while the body is scrolled down,
 *   it does not move; the scrim fades with the drag;
 * - the end drawer dragged toward its edge 45% closes, 15% springs back;
 * - closed by a drag, focus is back on the opener and no element shows a focus ring;
 * - reduced motion: the sheet does not follow the finger; released past 30% it still closes.
 * Prints { checks, numbers } and exits 1 when a check fails.
 */

const { serve, loadPlaywright } = require( './_theme-preview.cjs' );

const ENGINES = ( process.env.MIZZEY_ENGINES || 'chromium,webkit' ).split( ',' );
const checks = [];
const numbers = {};
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );

/** A finger: pointer events as a touch makes them, spaced in real time, from a point of the element. */
function drag( page, selector, dx, dy, ms, steps ) {
	return page.evaluate( async ( [ sel, ddx, ddy, ms2, n ] ) => {
		const el = document.querySelector( sel );
		const panel = el.closest( '.mizzey-overlay__panel' );
		const dialog = el.closest( 'dialog' );
		const box = el.getBoundingClientRect();
		const p0 = panel.getBoundingClientRect();
		const x0 = box.left + Math.min( box.width / 2, 20 );
		const y0 = box.top + Math.min( box.height / 2, 10 );
		const fire = ( type, x, y ) => el.dispatchEvent( new PointerEvent( type, { bubbles: true, cancelable: true, composed: true, pointerId: 9, pointerType: 'touch', isPrimary: true, clientX: x, clientY: y, buttons: type === 'pointerup' ? 0 : 1 } ) );
		const veil = () => parseFloat( getComputedStyle( dialog, '::backdrop' ).opacity );
		const seen = [];
		fire( 'pointerdown', x0, y0 );
		for ( let i = 1; i <= n; i++ ) {
			await new Promise( ( r ) => setTimeout( r, ms2 / n ) );
			fire( 'pointermove', x0 + ( ddx * i ) / n, y0 + ( ddy * i ) / n );
			await new Promise( ( r ) => requestAnimationFrame( r ) );
			const p = panel.getBoundingClientRect();
			seen.push( [ Math.round( p.left - p0.left ), Math.round( p.top - p0.top ), Math.round( veil() * 100 ) / 100 ] );
		}
		fire( 'pointerup', x0 + ddx, y0 + ddy );
		const after = [];
		const t0 = performance.now();
		while ( performance.now() - t0 < 500 ) {
			await new Promise( ( r ) => requestAnimationFrame( r ) );
			const p = panel.getBoundingClientRect();
			after.push( [ Math.round( p.left - p0.left ), Math.round( p.top - p0.top ) ] );
		}
		return { seen, after, w: p0.width, h: p0.height };
	}, [ selector, dx, dy, ms, steps ] );
}

const isOpen = ( page, id ) => page.evaluate( ( i ) => document.getElementById( i ).open, id );
const settle = ( page, id ) => page.waitForFunction( ( i ) => ! document.getElementById( i ).open, id, { timeout: 3000 } ).catch( () => {} ).then( () => page.waitForTimeout( 400 ) );

async function open( page, id ) {
	await page.locator( `[aria-controls="${ id }"]` ).first().tap();
	await page.waitForFunction( ( i ) => document.getElementById( i ).open, id );
	await page.waitForTimeout( 700 );
}

async function focusAfter( page, where, id, what ) {
	const r = await page.evaluate( ( i ) => ( {
		rings: [ ...document.querySelectorAll( '*' ) ].filter( ( el ) => el.matches( ':focus-visible' ) ).length,
		back: !! document.activeElement && document.activeElement.getAttribute( 'aria-controls' ) === i,
	} ), id );
	check( r.rings === 0 && r.back, `K-5 ${ where } ${ what }: focus is back on the opener with no ring (${ JSON.stringify( r ) })` );
}

async function sheet( page, where, reduced ) {
	const id = 'pv-sheet';
	const handle = `#${ id } .mizzey-overlay__handle`;
	await open( page, id );
	if ( reduced ) {
		const d = await drag( page, handle, 0, 200, 400, 8 );
		check( d.seen.every( ( [ , y ] ) => Math.abs( y ) <= 1 ), `K-5 ${ where } reduced motion: the sheet does not follow the finger (${ JSON.stringify( d.seen.map( ( s ) => s[ 1 ] ) ) })` );
		await settle( page, id );
		check( ! await isOpen( page, id ), `K-5 ${ where } reduced motion: released past 30%, the sheet closes` );
		return;
	}
	const h = await page.evaluate( ( s ) => document.querySelector( s ).closest( '.mizzey-overlay__panel' ).getBoundingClientRect().height, handle );
	numbers[ `${ where } sheet height` ] = Math.round( h );

	// Steps larger than the 6 px a touch must move before it counts as a drag.
	const small = await drag( page, handle, 0, h * 0.15, 400, 4 );
	const follows = small.seen.every( ( [ , y ], i ) => Math.abs( y - ( h * 0.15 * ( i + 1 ) ) / 4 ) <= 3 );
	const fades = small.seen[ small.seen.length - 1 ][ 2 ] < 0.95;
	const between = small.after.filter( ( [ , y ] ) => y > 1 && y < h * 0.15 - 1 ).length;
	check( follows, `M-5 ${ where } the sheet follows the finger down 1:1: ${ JSON.stringify( small.seen.map( ( s ) => s[ 1 ] ) ) }` );
	check( fades, `M-5 ${ where } the scrim fades with the drag (${ small.seen[ small.seen.length - 1 ][ 2 ] })` );
	check( await isOpen( page, id ) && between >= 3 && Math.abs( small.after[ small.after.length - 1 ][ 1 ] ) <= 1, `M-5 ${ where } released at 15%, the sheet springs back (${ between } frames between) and stays open` );

	const up = await drag( page, handle, 0, -80, 300, 6 );
	const resisted = up.seen[ up.seen.length - 1 ][ 1 ];
	check( resisted < 0 && resisted >= -21, `M-5 ${ where } dragged up, the sheet resists (moves ${ resisted } for 80)` );
	await page.waitForTimeout( 500 );

	await drag( page, handle, 0, h * 0.4, 500, 10 );
	await settle( page, id );
	check( ! await isOpen( page, id ), `M-5 ${ where } dragged down 40% of its height, the sheet closes` );
	await focusAfter( page, where, id, 'the sheet closed by a drag' );

	await open( page, id );
	await drag( page, handle, 0, h * 0.2, 16, 2 );
	await settle( page, id );
	check( ! await isOpen( page, id ), `M-5 ${ where } a downward flick of 20% closes the sheet` );

	// The body scrolled down: a downward drag there is the body's scroll, not the sheet's.
	await open( page, id );
	const scrolled = await page.evaluate( ( i ) => {
		const body = document.querySelector( `#${ i } .mizzey-overlay__body` );
		const filler = document.createElement( 'div' );
		filler.className = 'mz-test-filler';
		filler.style.blockSize = '2000px';
		body.appendChild( filler );
		body.scrollTop = 120;
		return body.scrollTop;
	}, id );
	const inBody = await drag( page, `#${ id } .mizzey-overlay__body .mz-test-filler`, 0, h * 0.4, 400, 8 );
	check( scrolled > 0 && inBody.seen.every( ( [ , y ] ) => Math.abs( y ) <= 1 ) && await isOpen( page, id ), `M-5 ${ where } with the body scrolled down, a drag in the body does not move the sheet (${ JSON.stringify( inBody.seen.map( ( s ) => s[ 1 ] ) ) })` );
	await page.evaluate( ( i ) => {
		const body = document.querySelector( `#${ i } .mizzey-overlay__body` );
		body.querySelector( '.mz-test-filler' ).remove();
		body.scrollTop = 0;
	}, id );
	const fromBody = await drag( page, `#${ id } .mizzey-overlay__body`, 0, h * 0.4, 500, 10 );
	await settle( page, id );
	check( fromBody.seen[ fromBody.seen.length - 1 ][ 1 ] > h * 0.3 && ! await isOpen( page, id ), `M-5 ${ where } with the body at its top, a drag in the body moves and closes the sheet` );
}

async function drawer( page, where, rtl ) {
	const id = 'pv-cart';
	const sign = rtl ? -1 : 1; // the end edge
	await open( page, id );
	const w = await page.evaluate( ( i ) => document.querySelector( `#${ i } .mizzey-overlay__panel` ).getBoundingClientRect().width, id );
	const small = await drag( page, `#${ id } .mizzey-overlay__header`, sign * w * 0.15, 0, 400, 8 );
	check( Math.abs( small.seen[ small.seen.length - 1 ][ 0 ] - sign * w * 0.15 ) <= 3 && await isOpen( page, id ) && Math.abs( small.after[ small.after.length - 1 ][ 0 ] ) <= 1, `M-4 ${ where } the end drawer follows the finger to its edge and springs back from 15% (${ JSON.stringify( small.seen.map( ( s ) => s[ 0 ] ) ) })` );
	await drag( page, `#${ id } .mizzey-overlay__header`, sign * w * 0.45, 0, 500, 10 );
	await settle( page, id );
	check( ! await isOpen( page, id ), `M-4 ${ where } the end drawer dragged 45% toward its edge closes` );
	await focusAfter( page, where, id, 'the end drawer closed by a drag' );
}

( async () => {
	const { pw, launch } = loadPlaywright();
	const server = await serve();
	try {
		for ( const engine of ENGINES ) {
			const browser = await pw[ engine ].launch( 'chromium' === engine ? launch : { headless: true } );
			for ( const dir of [ 'ltr', 'rtl' ] ) {
				for ( const reduced of [ false, true ] ) {
					const where = `[${ engine }, ${ dir }, 390${ reduced ? ', reduced' : '' }]`;
					const context = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: reduced ? 'reduce' : 'no-preference' } );
					const page = await context.newPage();
					const errors = [];
					page.on( 'pageerror', ( e ) => errors.push( e.message ) );
					await page.goto( `${ server.url }/tests/preview-${ dir }.html`, { waitUntil: 'networkidle' } );
					try {
						await sheet( page, where, reduced );
						if ( ! reduced ) {
							await drawer( page, where, 'rtl' === dir );
						}
					} catch ( e ) {
						check( false, `${ where } ran to the end: ${ e.message.split( '\n' )[ 0 ] }` );
					}
					check( errors.length === 0, `${ where } no script errors: ${ JSON.stringify( errors ) }` );
					await context.close();
				}
			}
			await browser.close();
		}
	} finally {
		server.close();
	}
	const failed = checks.filter( ( c ) => ! c.ok );
	console.log( JSON.stringify( { checks, numbers, failed: failed.length } ) );
	process.exit( failed.length ? 1 : 0 );
} )();

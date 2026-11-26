#!/usr/bin/env node
/**
 * Focus rings on the overlays (spec 066, K-5; the owner's iPhone, 25 November 2026): a ring shows only for keyboard
 * use. Opened by touch, an overlay takes focus without a ring (on the dialog itself), and closed by touch it gives
 * focus back to the control that opened it, again without a ring. Opened by the keyboard, the ring shows as before.
 *
 * Usage: node overlay-focus.cjs [base-url] (default http://127.0.0.1:8124). At 390 with touch, in Chromium and
 * WebKit, English and Arabic: on the site, the phone menu and the cookie preferences (opened by their own script),
 * and a phone menu row tapped keeps no hover colour; on the theme's component preview (_theme-preview.cjs), every
 * kind of overlay: the start drawer, the end drawer, the bottom sheet and the dialog.
 * Prints { checks: [{ ok, what }] } and exits 1 when a check fails.
 */

const { loadPlaywright, serve } = require( './_theme-preview.cjs' );

const BASE = ( process.argv[ 2 ] || 'http://127.0.0.1:8124' ).replace( /\/$/, '' );
const ENGINES = ( process.env.MIZZEY_ENGINES || 'chromium,webkit' ).split( ',' );
const checks = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );

/** Which elements match :focus-visible now, and where focus is. */
const rings = ( page ) => page.evaluate( () => {
	const name = ( el ) => ( el ? `${ el.tagName.toLowerCase() }${ el.id ? '#' + el.id : '' }${ el.classList.length ? '.' + [ ...el.classList ].slice( 0, 2 ).join( '.' ) : '' }` : 'none' );
	const visible = [ ...document.querySelectorAll( '*' ) ].filter( ( el ) => {
		try {
			return el.matches( ':focus-visible' );
		} catch ( e ) {
			return false;
		}
	} );
	return { visible: visible.map( name ), active: name( document.activeElement ) };
} );

const closed = ( page, id ) => page.waitForFunction( ( i ) => ! document.getElementById( i ).open, id, { timeout: 4000 } ).then( () => page.waitForTimeout( 400 ) );
const opened = ( page, id ) => page.waitForFunction( ( i ) => document.getElementById( i ).open, id, { timeout: 4000 } ).then( () => page.waitForTimeout( 700 ) );

async function overlay( page, where, o ) {
	// By touch: open, close with the overlay's own close control.
	await page.locator( o.opener ).first().tap();
	await opened( page, o.id );
	let r = await rings( page );
	const inside = await page.evaluate( ( i ) => document.getElementById( i ).contains( document.activeElement ), o.id );
	check( r.visible.length === 0 && inside, `K-5 ${ where } ${ o.name } opened by touch: focus is inside (${ r.active }) and no element shows a ring (${ JSON.stringify( r.visible ) })` );
	await page.locator( `#${ o.id } ${ o.close }` ).first().tap();
	await closed( page, o.id );
	r = await rings( page );
	const back = await page.evaluate( ( s ) => !! document.activeElement && document.activeElement.matches( s ), o.opener );
	check( r.visible.length === 0 && back, `K-5 ${ where } ${ o.name } closed by touch: focus is back on its opener (${ r.active }) with no ring (${ JSON.stringify( r.visible ) })` );

	// By the keyboard: the ring shows inside, and on the opener after Escape.
	await page.locator( o.opener ).first().focus();
	await page.keyboard.press( 'Enter' );
	await opened( page, o.id );
	r = await rings( page );
	const keyInside = await page.evaluate( ( i ) => {
		const a = document.activeElement;
		return document.getElementById( i ).contains( a ) && a.matches( ':focus-visible' );
	}, o.id );
	check( keyInside, `K-5 ${ where } ${ o.name } opened by the keyboard: the focused element inside shows its ring (${ r.active }, ${ JSON.stringify( r.visible ) })` );
	await page.keyboard.press( 'Escape' );
	await closed( page, o.id );
	r = await rings( page );
	const keyBack = await page.evaluate( ( s ) => !! document.activeElement && document.activeElement.matches( s ) && document.activeElement.matches( ':focus-visible' ), o.opener );
	check( keyBack, `K-5 ${ where } ${ o.name } closed by Escape: focus is back on its opener with its ring (${ r.active })` );
}

/** A row tapped keeps no hover colour: after a push and Back, the row has the background of a row never touched. */
async function stuckHover( page, where ) {
	await page.locator( '.mizzey-header__menu' ).tap();
	await page.waitForTimeout( 900 );
	const level = '#mizzey-menu .mizzey-menu__level:first-child';
	if ( await page.locator( `${ level } .mizzey-menu__row` ).count() < 2 ) {
		check( false, `K-5 ${ where } the phone menu has two rows to compare` );
		return;
	}
	const opener = page.locator( `${ level } .mizzey-menu__row[aria-controls]` ).first();
	await ( await opener.count() ? opener : page.locator( `${ level } .mizzey-menu__row` ).first() ).tap();
	await page.waitForTimeout( 600 );
	const back = page.locator( '#mizzey-menu .mizzey-menu__level:not([hidden]) .mizzey-menu__back' );
	if ( await back.count() ) {
		await back.first().tap();
		await page.waitForTimeout( 600 );
	}
	const r = await page.evaluate( ( l ) => {
		const all = [ ...document.querySelectorAll( `${ l } .mizzey-menu__row` ) ];
		const touched = all.find( ( el ) => el.hasAttribute( 'aria-controls' ) ) || all[ 0 ];
		const other = all.find( ( el ) => el !== touched );
		return { touched: getComputedStyle( touched ).backgroundColor, other: getComputedStyle( other ).backgroundColor, rings: [ ...document.querySelectorAll( '*' ) ].filter( ( el ) => el.matches( ':focus-visible' ) ).length };
	}, level );
	check( r.touched === r.other && r.rings === 0, `K-5 ${ where } a phone menu row tapped keeps no hover colour and no ring (${ JSON.stringify( r ) })` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 500 );
}

( async () => {
	const { pw, launch } = loadPlaywright();
	const preview = await serve();
	for ( const engine of ENGINES ) {
		const browser = await pw[ engine ].launch( 'chromium' === engine ? launch : { headless: true } );
		for ( const lang of [ 'en', 'ar' ] ) {
			const where = `[${ engine }, ${ lang }, 390]`;
			const context = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
			const page = await context.newPage();
			const errors = [];
			page.on( 'pageerror', ( e ) => errors.push( e.message ) );
			await page.goto( BASE + ( 'ar' === lang ? '/ar/' : '/' ), { waitUntil: 'load', timeout: 90000 } );
			await page.waitForTimeout( 600 );
			try {
				await overlay( page, where, { name: 'the phone menu', id: 'mizzey-menu', opener: '.mizzey-header__menu', close: '.mizzey-menu__level:not([hidden]) .mizzey-menu__close' } );
				const prefs = await page.locator( '[data-mizzey-consent-open]' ).count();
				if ( prefs ) {
					await overlay( page, where, { name: 'the cookie preferences', id: 'mizzey-consent-preferences', opener: '[data-mizzey-consent-open]', close: '.mizzey-overlay__header .mizzey-button--icon' } );
				}
				await stuckHover( page, where );
			} catch ( e ) {
				check( false, `${ where } ran to the end: ${ e.message.split( '\n' )[ 0 ] }` );
			}
			check( errors.length === 0, `${ where } no script errors: ${ JSON.stringify( errors ) }` );
			await context.close();

			// Every kind of overlay, on the component preview.
			const pv = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
			const pp = await pv.newPage();
			const dir = 'ar' === lang ? 'rtl' : 'ltr';
			await pp.goto( `${ preview.url }/tests/preview-${ dir }.html`, { waitUntil: 'networkidle' } );
			try {
				for ( const [ id, name ] of [ [ 'pv-menu', 'the start drawer' ], [ 'pv-cart', 'the end drawer' ], [ 'pv-sheet', 'the bottom sheet' ], [ 'pv-dialog', 'the dialog' ] ] ) {
					await overlay( pp, `[${ engine }, ${ dir }, 390, preview]`, { name, id, opener: `[aria-controls="${ id }"]`, close: '[data-wp-on--click="actions.close"]' } );
				}
			} catch ( e ) {
				check( false, `[${ engine }, ${ dir }, preview] ran to the end: ${ e.message.split( '\n' )[ 0 ] }` );
			}
			await pv.close();
		}
		await browser.close();
	}
	preview.close();
	const failed = checks.filter( ( c ) => ! c.ok );
	console.log( JSON.stringify( { checks, failed: failed.length } ) );
	process.exit( failed.length ? 1 : 0 );
} )();

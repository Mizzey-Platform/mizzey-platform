#!/usr/bin/env node
/**
 * Category pictures in every place they are shown, as the closed design draws them (spec 066, the owner's comments 7
 * and 8 of 26 November 2026; AC-1, AC-3, AC-7, AC-15). Driven by t123, which makes the categories and passes a JSON
 * plan as the only argument; prints one JSON object: { checks: [{ ok, what }], shots: [...] }.
 *
 * Every place prints its picture through one helper (MizzeySite\Storefront\CategoryImage): an <img> or, for a category
 * with no picture, the placeholder, each `.mizzey-catimg` with `data-place`. Per place the board gives the box and the
 * fit (C1-Menus-Desktop, C1-Menus-Phone, C1-Home-Desktop, C1-Home-Phone, C1-Category-Desktop, C1-Category-Phone):
 *
 * | place     | where                                | 1440      | 390        | fit     |
 * |-----------|--------------------------------------|-----------|------------|---------|
 * | row       | mega menu rows, phone menu rows      | 32 x 32   | 32 x 32    | contain |
 * | mega      | mega menu panel photo                | 408 x 300 |            | cover   |
 * | mega-wide | mega menu panel of a category with no sub-categories | 640 x 200 |  | cover   |
 * | banner    | phone menu level-2 photo banner      |           | 310 x 120  | cover   |
 * | tile      | the Categories tab                   |           | 76 x 76    | cover   |
 * | chip      | the phone header's category chips    |           | 24 x 24    | contain |
 * | shortcut  | the home page's category shortcuts   | 138 x 138 | 80.5 x 80.5| cover   |
 * | listing   | the category page's banner tile      | 128 x 128 | 88 x 88    | cover   |
 *
 * For each: the box as drawn; a picture is never upscaled (its natural size is at least the size it is painted at,
 * times the device pixel ratio, at 1x, 2x and 3x) and uses the drawn fit; a category with no picture shows the image
 * glyph on the tint (C1-Components 10: 32, smaller in the 32 and 24 boxes) in the same box. In Chromium and WebKit, in
 * English and Arabic; the phone with touch.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const shots = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const TIMEOUT = 90000;
const TINT = 'rgb(238, 241, 250)';

const BOX = {
	row: { 1440: [ 32, 32 ], 390: [ 32, 32 ], fit: 'contain', glyph: 20 },
	mega: { 1440: [ 408, 300 ], fit: 'cover', glyph: 32 },
	'mega-wide': { 1440: [ 640, 200 ], fit: 'cover', glyph: 32 },
	banner: { 390: [ 310, 120 ], fit: 'cover', glyph: 32 },
	tile: { 390: [ 76, 76 ], fit: 'cover', glyph: 32 },
	chip: { 390: [ 24, 24 ], fit: 'contain', glyph: 16 },
	shortcut: { 1440: [ 138, 138 ], 390: [ 80.5, 80.5 ], fit: 'cover', glyph: 32 },
	listing: { 1440: [ 128, 128 ], 390: [ 88, 88 ], fit: 'cover', glyph: 32 },
};

/** Every visible category picture or placeholder inside `scope`, measured. */
const measure = ( page, scope ) => page.evaluate( async ( s ) => {
	const root = document.querySelector( s );
	if ( ! root ) {
		return null;
	}
	const items = [ ...root.querySelectorAll( '.mizzey-catimg' ) ].filter( ( el ) => el.getClientRects().length && el.getBoundingClientRect().width > 0 );
	const out = [];
	for ( const el of items ) {
		const r = el.getBoundingClientRect();
		const cs = getComputedStyle( el );
		const row = { place: el.dataset.place || '', w: r.width, h: r.height, tag: el.tagName.toLowerCase(), empty: el.classList.contains( 'is-empty' ) };
		if ( 'img' === row.tag ) {
			if ( ! el.complete || ! el.naturalWidth ) {
				el.loading = 'eager';
				await new Promise( ( done ) => {
					const t = setTimeout( done, 8000 );
					el.addEventListener( 'load', () => { clearTimeout( t ); done(); }, { once: true } );
				} );
			}
			// The file's own pixels: with a w-descriptor srcset, naturalWidth is corrected by the chosen density.
			const probe = new Image();
			probe.src = el.currentSrc || el.src;
			try {
				await probe.decode();
			} catch ( e ) {
				// Measured as 0 below, which fails.
			}
			row.nw = probe.naturalWidth;
			row.nh = probe.naturalHeight;
			row.fit = cs.objectFit;
			row.src = ( el.currentSrc || el.src ).split( '/' ).pop();
			row.alt = el.getAttribute( 'alt' );
		} else {
			const svg = el.querySelector( 'svg' );
			const g = svg ? svg.getBoundingClientRect() : { width: 0, height: 0, left: 0, top: 0 };
			row.glyph = [ g.width, g.height ];
			// How far the glyph's centre is from the box's centre.
			row.off = Math.max( Math.abs( g.left + g.width / 2 - ( r.left + r.width / 2 ) ), Math.abs( g.top + g.height / 2 - ( r.top + r.height / 2 ) ) );
			row.bg = cs.backgroundColor;
			row.hidden = el.getAttribute( 'aria-hidden' );
		}
		out.push( row );
	}
	return out;
}, scope );

/** The checks on one place's measured items. */
function judge( items, place, width, dpr, where, want ) {
	const spec = BOX[ place ];
	const box = spec[ width ];
	const mine = ( items || [] ).filter( ( i ) => i.place === place );
	check( mine.length > 0 && ( ! want.count || mine.length >= want.count ), `${ where } ${ place}: the place prints its picture through the shared helper (${ mine.length } found)` );
	let pictures = 0;
	let empties = 0;
	for ( const i of mine ) {
		const sized = Math.abs( i.w - box[ 0 ] ) <= 1 && Math.abs( i.h - box[ 1 ] ) <= 1;
		if ( 'img' === i.tag ) {
			pictures++;
			const ratio = i.nw / i.nh;
			// The size the picture is painted at inside its box, by its fit.
			const scale = 'cover' === i.fit ? Math.max( i.w / i.nw, i.h / i.nh ) : Math.min( i.w / i.nw, i.h / i.nh );
			const paintW = i.nw * scale;
			const paintH = i.nh * scale;
			const sharp = i.nw >= paintW * dpr - 1 && i.nh >= paintH * dpr - 1;
			check( sized && i.fit === spec.fit && sharp && i.alt === '',
				`${ where } ${ place }: a picture's box ${ i.w.toFixed( 1 ) }x${ i.h.toFixed( 1 ) } (drawn ${ box[ 0 ] }x${ box[ 1 ] }), fit ${ i.fit } (drawn ${ spec.fit }), natural ${ i.nw }x${ i.nh } (ratio ${ ratio.toFixed( 2 ) }) for a paint of ${ paintW.toFixed( 0 ) }x${ paintH.toFixed( 0 ) } at ${ dpr }x: never upscaled (${ i.src })` );
		} else {
			empties++;
			const glyph = Math.abs( i.glyph[ 0 ] - spec.glyph ) <= 0.5 && Math.abs( i.glyph[ 1 ] - spec.glyph ) <= 0.5;
			check( i.empty && sized && glyph && i.off <= 0.5 && i.bg === TINT && i.hidden === 'true',
				`${ where } ${ place }: a category with no picture shows the image glyph ${ i.glyph.map( ( n ) => n.toFixed( 0 ) ).join( 'x' ) } (wanted ${ spec.glyph }), centred (${ i.off.toFixed( 1 ) } off), on the tint (${ i.bg }) in the same box ${ i.w.toFixed( 1 ) }x${ i.h.toFixed( 1 ) } (drawn ${ box[ 0 ] }x${ box[ 1 ] })` );
		}
	}
	if ( want.picture ) {
		check( pictures > 0, `${ where } ${ place }: a category with a picture shows it (${ pictures })` );
	}
	if ( want.empty ) {
		check( empties > 0, `${ where } ${ place }: a category with no picture shows the placeholder, never an empty gap (${ empties })` );
	}
}

async function open( browser, url, width, dpr, touch ) {
	const context = await browser.newContext( { viewport: { width, height: 900 }, deviceScaleFactor: dpr, hasTouch: touch, isMobile: touch && browser.browserType().name() === 'chromium' } );
	await context.addCookies( [ { name: 'mizzey_consent', value: 'denied', domain: new URL( url ).hostname, path: '/' } ] );
	const page = await context.newPage();
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.waitForTimeout( 300 );
	return { context, page };
}

async function shot( page, name ) {
	const file = path.join( plan.shots, `t123-${ name }.png` );
	await page.screenshot( { path: file } );
	shots.push( file );
}

async function desktop( browser, engine, lang, dpr ) {
	const L = plan[ lang ];
	const where = `[${ engine }, ${ lang }, 1440, ${ dpr }x]`;
	// The mega menu: every row, then each fixture category's panel in turn.
	let { context, page } = await open( browser, L.home, 1440, dpr, false );
	await page.locator( '.mizzey-header__all' ).click();
	await page.waitForTimeout( 400 );
	judge( await measure( page, '.mizzey-mega__rows' ), 'row', 1440, dpr, `${ where } mega rows`, { picture: true, empty: true } );
	for ( const [ key, place, want ] of [ [ 'beauty', 'mega', { picture: true } ], [ 'home', 'mega', { empty: true } ], [ 'gifts', 'mega-wide', { picture: true } ], [ 'pets', 'mega-wide', { empty: true } ] ] ) {
		await page.locator( '.mizzey-mega__row', { hasText: L.names[ key ] } ).first().hover();
		await page.waitForTimeout( 300 );
		judge( await measure( page, '.mizzey-mega__panel:not([hidden])' ), place, 1440, dpr, `${ where } mega panel of ${ key }`, want );
		if ( 2 === dpr && 'chromium' === engine ) {
			await shot( page, `mega-${ key }-${ lang }-1440` );
		}
	}
	await context.close();
	// The home page's category shortcuts.
	( { context, page } = await open( browser, L.shortcuts, 1440, dpr, false ) );
	await page.locator( '.mizzey-shortcuts' ).scrollIntoViewIfNeeded();
	await page.waitForTimeout( 300 );
	judge( await measure( page, '.mizzey-shortcuts' ), 'shortcut', 1440, dpr, `${ where } home shortcuts`, { picture: true, empty: true } );
	if ( 2 === dpr && 'chromium' === engine ) {
		await shot( page, `shortcuts-${ lang }-1440` );
	}
	await context.close();
	// The category page's banner tile, with and without a picture.
	for ( const [ key, want ] of [ [ 'beauty', { picture: true } ], [ 'pets', { empty: true } ] ] ) {
		( { context, page } = await open( browser, L.listing[ key ], 1440, dpr, false ) );
		judge( await measure( page, '.mizzey-listing__banner' ), 'listing', 1440, dpr, `${ where } category banner of ${ key }`, want );
		if ( 2 === dpr && 'chromium' === engine ) {
			await shot( page, `listing-${ key }-${ lang }-1440` );
		}
		await context.close();
	}
}

async function phone( browser, engine, lang, dpr ) {
	const L = plan[ lang ];
	const where = `[${ engine }, ${ lang }, 390, ${ dpr }x, touch]`;
	let { context, page } = await open( browser, L.home, 390, dpr, true );
	// The chips under the phone header on the home page.
	judge( await measure( page, '.mizzey-header__chips' ), 'chip', 390, dpr, `${ where } header chips`, { picture: true, empty: true } );
	// The phone menu: the first level's rows, then a level with a picture and one without.
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.locator( '.mizzey-header__menu' ).tap();
	await page.waitForTimeout( 600 );
	judge( await measure( page, '#mizzey-menu .mizzey-menu__level:not([hidden])' ), 'row', 390, dpr, `${ where } phone menu rows`, { picture: true, empty: true } );
	for ( const [ key, want ] of [ [ 'beauty', { picture: true } ], [ 'home', { empty: true } ] ] ) {
		await page.locator( '#mizzey-menu .mizzey-menu__level:not([hidden]) .mizzey-menu__row', { hasText: L.names[ key ] } ).first().tap();
		await page.waitForTimeout( 500 );
		judge( await measure( page, '#mizzey-menu .mizzey-menu__level:not([hidden])' ), 'banner', 390, dpr, `${ where } phone menu level of ${ key }`, want );
		if ( 3 === dpr && 'chromium' === engine ) {
			await shot( page, `menu-${ key }-${ lang }-390` );
		}
		await page.locator( '#mizzey-menu .mizzey-menu__level:not([hidden]) .mizzey-menu__back' ).tap();
		await page.waitForTimeout( 400 );
	}
	await context.close();
	// The home page's category shortcuts (the section on a page of its own: the front page is shared).
	( { context, page } = await open( browser, L.shortcuts, 390, dpr, true ) );
	await page.locator( '.mizzey-shortcuts' ).scrollIntoViewIfNeeded();
	await page.waitForTimeout( 300 );
	judge( await measure( page, '.mizzey-shortcuts' ), 'shortcut', 390, dpr, `${ where } home shortcuts`, { picture: true, empty: true } );
	if ( 3 === dpr && 'chromium' === engine ) {
		await shot( page, `shortcuts-${ lang }-390` );
	}
	await context.close();
	// The Categories tab.
	( { context, page } = await open( browser, L.home, 390, dpr, true ) );
	await page.locator( '.mizzey-tabbar__categories' ).tap();
	await page.waitForTimeout( 500 );
	judge( await measure( page, '.mizzey-categories' ), 'tile', 390, dpr, `${ where } Categories tab`, { picture: true, empty: true } );
	if ( 3 === dpr && 'chromium' === engine ) {
		await shot( page, `categories-tab-${ lang }-390` );
	}
	await context.close();
	// The category page's banner tile.
	for ( const [ key, want ] of [ [ 'beauty', { picture: true } ], [ 'pets', { empty: true } ] ] ) {
		( { context, page } = await open( browser, L.listing[ key ], 390, dpr, true ) );
		judge( await measure( page, '.mizzey-listing__banner' ), 'listing', 390, dpr, `${ where } category banner of ${ key }`, want );
		if ( 3 === dpr && 'chromium' === engine ) {
			await shot( page, `listing-${ key }-${ lang }-390` );
		}
		await context.close();
	}
}

const playwright = loadPlaywright();

( async () => {
	if ( ! playwright ) {
		console.log( JSON.stringify( { checks: [ { ok: false, what: 'Playwright is not available' } ], shots } ) );
		return;
	}
	for ( const engine of plan.engines || [ 'chromium', 'webkit' ] ) {
		const browser = await playwright.pw[ engine ].launch( 'chromium' === engine ? playwright.launch : { headless: true } );
		for ( const lang of [ 'en', 'ar' ] ) {
			for ( const dpr of [ 1, 2 ] ) {
				await desktop( browser, engine, lang, dpr );
			}
			for ( const dpr of [ 2, 3 ] ) {
				await phone( browser, engine, lang, dpr );
			}
		}
		await browser.close();
	}
	console.log( JSON.stringify( { checks, shots } ) );
} )().catch( ( error ) => {
	console.log( JSON.stringify( { checks, shots, error: String( error && error.stack ? error.stack : error ).slice( 0, 1500 ) } ) );
} );

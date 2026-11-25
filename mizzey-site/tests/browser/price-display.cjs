#!/usr/bin/env node
/**
 * Prices as the boards draw them, in a real browser (spec 066; owner ruling of 25 November 2026). Driven by t120, which
 * makes the fixture products and the page that carries them as a rail, and passes a JSON plan as the only argument;
 * prints one JSON object: { checks: [{ ok, what }], shots }.
 *
 * In Chromium and in WebKit (the owner tests on an iPhone: 390 wide, touch), in English and in Arabic: the card and the
 * product page of a whole-pound price, a price with piasters and a sale price; then the cart block's total for a
 * whole-pound cart and for a cart with piasters, beside the Store API's cart response read in the same session.
 *
 * English reads "EGP 5,099" and "EGP 99.50"; Arabic "5,099 ج.م" and "99.50 ج.م"; the space is a non-breaking one.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

process.on( 'unhandledRejection', () => {} );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const shots = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const TIMEOUT = 60000;
const NB = ' ';
const AR = 'ج.م';

/** The ruled form of an amount in a language. */
const ruled = ( lang, amount ) => ( lang === 'en' ? `EGP${ NB }${ amount }` : `${ amount }${ NB }${ AR }` );
/** A string a visitor can read, with the non-breaking space shown. */
const show = ( text ) => JSON.stringify( text ).replace( / /g, '<nbsp>' );
/** A price in the old form: ".00" on a whole pound, or the symbol and the amount with no space or a plain one. */
const oldForm = ( text ) => /\d\.00(?!\d)/.test( text ) || /EGP[ 0-9]/.test( text ) || /\d ?ج\.م/.test( text );

async function cardPrices( page, id ) {
	return page.evaluate( ( id ) => {
		const card = document.querySelector( `.mizzey-card[data-product-id="${ id }"]` );
		const price = card && card.querySelector( '.mizzey-card__price' );
		const old = card && card.querySelector( '.mizzey-card__old .woocommerce-Price-amount' );
		return { price: price ? price.textContent.trim() : null, old: old ? old.textContent.trim() : null };
	}, id );
}

async function pagePrice( page ) {
	return page.evaluate( () => {
		const box = document.querySelector( '.wp-block-woocommerce-product-price' );
		const amounts = box ? [ ...box.querySelectorAll( '.woocommerce-Price-amount' ) ].map( ( a ) => a.textContent.trim() ) : [];
		return { amounts, text: box ? box.textContent.replace( /\s+/g, ' ' ).trim() : null };
	} );
}

/** The cart, through the Store API, as the session holds it; and an add, with the nonce the API asks for. */
async function storeCart( page ) {
	return page.evaluate( async ( store ) => {
		const res = await fetch( store + 'cart', { credentials: 'same-origin' } );
		return { nonce: res.headers.get( 'Nonce' ), body: await res.json() };
	}, plan.store );
}

async function addItem( page, id, nonce ) {
	return page.evaluate( async ( [ store, id, nonce ] ) => {
		const res = await fetch( store + 'cart/add-item', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Nonce: nonce },
			body: JSON.stringify( { id, quantity: 1 } ),
		} );
		return res.status;
	}, [ plan.store, id, nonce ] );
}

/** The cart block's grand total, once the block has drawn it. */
async function blockTotal( page ) {
	const sel = '.wc-block-components-totals-footer-item .wc-block-components-totals-item__value';
	await page.waitForSelector( sel, { timeout: TIMEOUT } );
	// The formatter runs on the block's own render; give it a frame.
	await page.waitForTimeout( 300 );
	return page.evaluate( ( sel ) => {
		const el = document.querySelector( sel );
		const all = [ ...document.querySelectorAll( '.wp-block-woocommerce-cart .wc-block-formatted-money-amount, .wp-block-woocommerce-cart .wc-block-components-formatted-money-amount' ) ].map( ( a ) => a.textContent );
		return { total: el ? el.textContent.trim() : null, all };
	}, sel );
}

async function runIn( browserType, name, launch ) {
	const browser = await browserType.launch( launch );
	try {
		for ( const lang of [ 'en', 'ar' ] ) {
			const L = plan[ lang ];
			const ID = plan.ids[ lang ];
			const where = `[${ name } ${ lang } 390]`;
			const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: name === 'webkit', hasTouch: true } );
			const page = await ctx.newPage();

			// ---- The card -------------------------------------------------------------------------------------------
			await page.goto( L.cards, { waitUntil: 'load', timeout: TIMEOUT } );
			await page.waitForSelector( '.mizzey-products--rail .mizzey-card', { timeout: TIMEOUT } );
			const cw = await cardPrices( page, ID.whole );
			const cp = await cardPrices( page, ID.pt );
			const cs = await cardPrices( page, ID.sale );
			check( cw.price === ruled( lang, '5,099' ), `ruling ${ where } the card's whole-pound price reads ${ show( ruled( lang, '5,099' ) ) } (${ show( cw.price ) })` );
			check( cp.price === ruled( lang, '99.50' ), `ruling ${ where } the card's price with piasters reads ${ show( ruled( lang, '99.50' ) ) } (${ show( cp.price ) })` );
			check( cs.price === ruled( lang, '5,099' ) && cs.old === ruled( lang, '6,000' ), `ruling ${ where } the card's sale price and its struck original read ${ show( ruled( lang, '5,099' ) ) } and ${ show( ruled( lang, '6,000' ) ) } (${ show( cs.price ) } / ${ show( cs.old ) })` );
			if ( name === 'chromium' ) {
				const file = path.join( plan.shots, `${ plan.tag }-${ lang }-390-cards.png` );
				await page.locator( '.mizzey-products--rail' ).screenshot( { path: file } );
				shots.push( file );
			}

			// ---- The product page -----------------------------------------------------------------------------------
			for ( const [ key, want ] of [ [ 'whole', [ '5,099' ] ], [ 'pt', [ '99.50' ] ], [ 'sale', [ '6,000', '5,099' ] ] ] ) {
				await page.goto( L[ key ], { waitUntil: 'load', timeout: TIMEOUT } );
				const got = await pagePrice( page );
				const expect = want.map( ( a ) => ruled( lang, a ) );
				check( JSON.stringify( got.amounts ) === JSON.stringify( expect ), `ruling ${ where } the product page (${ key }) reads ${ show( expect.join( ' ' ) ) } (${ show( got.amounts.join( ' ' ) ) })` );
			}

			// ---- The cart: a whole-pound cart, then one with piasters ----------------------------------------------
			await page.goto( L.cart, { waitUntil: 'load', timeout: TIMEOUT } );
			let api = await storeCart( page );
			check( 201 === await addItem( page, ID.whole, api.nonce ), `${ where } the whole-pound product went into the cart` );
			api = await storeCart( page );
			const t1 = api.body.totals || {};
			check( t1.total_price === '509900' && t1.currency_minor_unit === 2, `ruling, display only ${ where } the Store API cart total is unchanged: 509900 at 2 places (${ t1.total_price } at ${ t1.currency_minor_unit })` );
			await page.goto( L.cart, { waitUntil: 'load', timeout: TIMEOUT } );
			const b1 = await blockTotal( page );
			check( b1.total === ruled( lang, '5,099' ), `ruling ${ where } the cart block's whole-pound total reads ${ show( ruled( lang, '5,099' ) ) } (${ show( b1.total ) })` );
			check( b1.all.length > 0 && ! b1.all.some( oldForm ), `ruling ${ where } no amount in the cart block keeps the old form (${ show( b1.all.join( ' | ' ) ) })` );
			if ( name === 'chromium' ) {
				const file = path.join( plan.shots, `${ plan.tag }-${ lang }-390-cart.png` );
				await page.screenshot( { path: file, fullPage: false } );
				shots.push( file );
			}
			check( 201 === await addItem( page, ID.pt, api.nonce ), `${ where } the product with piasters went into the cart` );
			api = await storeCart( page );
			const t2 = api.body.totals || {};
			check( t2.total_price === '519850', `ruling, display only ${ where } the Store API cart total with piasters is 519850 (${ t2.total_price })` );
			const prefix = lang === 'en' ? `EGP${ NB }` : '';
			const suffix = lang === 'en' ? '' : `${ NB }${ AR }`;
			check( t2.currency_prefix === prefix && t2.currency_suffix === suffix, `ruling ${ where } the Store API cart response carries the ruled prefix and suffix (${ show( t2.currency_prefix ) }, ${ show( t2.currency_suffix ) })` );
			await page.goto( L.cart, { waitUntil: 'load', timeout: TIMEOUT } );
			const b2 = await blockTotal( page );
			check( b2.total === ruled( lang, '5,198.50' ), `ruling ${ where } the cart block's total with piasters reads ${ show( ruled( lang, '5,198.50' ) ) } (${ show( b2.total ) })` );
			// The block draws the line prices too: the piasters line keeps its two places.
			check( b2.all.some( ( a ) => a.trim() === ruled( lang, '99.50' ) ) && ! b2.all.some( oldForm ), `ruling ${ where } the cart block's lines read the ruled form, piasters kept (${ show( b2.all.join( ' | ' ) ) })` );

			await ctx.close();
		}
	} finally {
		await browser.close();
	}
}

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' } ) );
		return;
	}
	for ( const [ name, launch ] of [ [ 'chromium', playwright.launch ], [ 'webkit', { headless: true } ] ] ) {
		try {
			await runIn( playwright.pw[ name ], name, launch );
		} catch ( e ) {
			check( false, `the ${ name } run stopped: ${ e.message.split( '\n' )[ 0 ] }` );
		}
	}
	console.log( JSON.stringify( { checks, shots } ) );
} )();

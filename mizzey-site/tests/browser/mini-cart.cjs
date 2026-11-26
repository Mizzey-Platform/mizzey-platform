#!/usr/bin/env node
/**
 * The mini cart in a real browser (spec 066, slice 12; AC-13, AC-14; CART-12). Driven by t113, which prepares the
 * runtime and passes a JSON plan as the only argument; prints one JSON object: { checks: [{ ok, what }], measured }.
 *
 * In English and in Arabic, each in a fresh cart:
 *
 * - at 1440 the header's cart control opens a drawer out of the end edge (the start edge in Arabic): full height, 420
 *   wide, square where it meets the edge, 28 on the two corners facing the page; at 390 a bottom sheet whose top
 *   edge is 56 from the top of the screen and which rounds its two top corners only;
 * - empty, it shows the line icon in a disc and no ZZ; Escape closes it and focus returns to the cart control;
 * - after a simple and a variable item are added (through WooCommerce's Store API, as the store's own buttons add),
 *   opening it shows the loading rows until the server answers, then each line with its name, its option and its
 *   price, the free-delivery line and the subtotal;
 * - a quantity change shows the i-dot loader and leaves the quantity and the subtotal as they were until the server
 *   answers, then shows the server's; the header's count follows; a removal does the same;
 * - focus stays inside while it is open, a press on the scrim closes it, and focus returns to the cart control;
 * - its subtotal is the cart page's subtotal.
 *
 * The key sizes of the board (C1-Mini-Cart) are measured and returned in `measured` as well as checked.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site: PLAYWRIGHT_DIR, or the copy
 * `npx playwright` has cached; when it has no Chromium of its own, the installed Chrome is used.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const measured = {};
const TIMEOUT = 90000;
const DIALOG = '#mizzey-mini-cart';
const CONTROL = '.mizzey-header__cart';

/** A price's minor units from its text, whatever its symbol, grouping and decimals ("EGP 1,000" and "1,000.00 ج.م"
    are both 100000). The format itself is the formatter's, not this test's. */
const minor = ( text ) => {
	const digits = String( text || '' ).replace( /[^0-9.,]/g, '' ).replace( /,/g, '' ).replace( /^\.+|\.+$/g, '' );
	return Math.round( ( parseFloat( digits ) || 0 ) * 100 );
};
const near = ( a, b, tolerance = 1 ) => Math.abs( a - b ) <= tolerance;

async function newPage( browser, width ) {
	const phone = width < 600;
	const context = await browser.newContext( { viewport: { width, height: phone ? 844 : 900 }, deviceScaleFactor: 1, isMobile: phone, hasTouch: phone } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	return { context, page, errors };
}

async function go( page, url ) {
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.waitForTimeout( 300 );
}

/** The visible cart control of the header. */
async function control( page ) {
	const all = await page.$$( CONTROL );
	for ( const el of all ) {
		if ( await el.isVisible() ) {
			return el;
		}
	}
	return null;
}

const isOpen = ( page ) => page.evaluate( ( s ) => !! document.querySelector( s ) && document.querySelector( s ).open, DIALOG );

/** Open the mini cart from the header and wait until it is open. */
async function openFromHeader( page ) {
	const cart = await control( page );
	if ( ! cart ) {
		return false;
	}
	await cart.click();
	try {
		await page.waitForFunction( ( s ) => document.querySelector( s ) && document.querySelector( s ).open, DIALOG, { timeout: 5000 } );
		return true;
	} catch ( e ) {
		return false;
	}
}

/** Wait until the mini cart shows the server's answer (lines or the empty state). */
const settled = ( page ) => page.waitForFunction( ( s ) => {
	const d = document.querySelector( s );
	return d && d.getAttribute( 'data-state' ) === 'ready' && ! d.querySelector( '[aria-busy="true"]' );
}, DIALOG, { timeout: 30000 } );

/** What the open mini cart shows. */
const read = ( page ) => page.evaluate( ( s ) => {
	const d = document.querySelector( s );
	const shown = ( el ) => !! el && el.getClientRects().length > 0 && getComputedStyle( el ).visibility !== 'hidden';
	const text = ( el ) => ( el ? el.textContent.replace( /\s+/g, ' ' ).trim() : '' );
	const lines = [ ...d.querySelectorAll( '.mizzey-minicart__line:not(.mizzey-minicart__line--loading)' ) ].filter( shown ).map( ( line ) => ( {
		name: text( line.querySelector( '.mizzey-minicart__name' ) ),
		option: text( line.querySelector( '.mizzey-minicart__option' ) ),
		price: text( line.querySelector( '.mizzey-minicart__price' ) ),
		qty: text( line.querySelector( '.mizzey-minicart__qty' ) ),
		busy: !! line.querySelector( '.mizzey-loader' ) && shown( line.querySelector( '.mizzey-loader' ) ),
		// The option row takes room only when the line has an option; the price row's distance from the line's top.
		optionRow: !! line.querySelector( '.mizzey-minicart__option' ) && line.querySelector( '.mizzey-minicart__option' ).getClientRects().length > 0,
		rowTop: Math.round( line.querySelector( '.mizzey-minicart__row' ).getBoundingClientRect().top - line.getBoundingClientRect().top ),
		nameH: Math.round( line.querySelector( '.mizzey-minicart__name' ).getBoundingClientRect().height ),
		optionH: line.querySelector( '.mizzey-minicart__option' ) ? Math.round( line.querySelector( '.mizzey-minicart__option' ).getBoundingClientRect().height ) : 0,
		removeName: ( line.querySelector( '.mizzey-minicart__remove' ) || { getAttribute: () => '' } ).getAttribute( 'aria-label' ) || '',
	} ) );
	const delivery = d.querySelector( '.mizzey-minicart__delivery' );
	const fill = d.querySelector( '.mizzey-minicart__fill' );
	const track = d.querySelector( '.mizzey-minicart__track' );
	return {
		state: d.getAttribute( 'data-state' ),
		title: text( d.querySelector( '.mizzey-minicart__title' ) ),
		lines,
		subtotal: shown( d.querySelector( '.mizzey-minicart__subtotal' ) ) ? text( d.querySelector( '.mizzey-minicart__subtotal' ) ) : '',
		delivery: shown( delivery ) ? text( delivery.querySelector( '.mizzey-minicart__delivery-text' ) ) : '',
		reached: shown( d.querySelector( '.mizzey-minicart__tick' ) ),
		// The filled part's target (it animates there): the share of the track the server's figure fills.
		fill: shown( fill ) && shown( track ) ? parseFloat( getComputedStyle( fill ).getPropertyValue( '--mizzey-minicart-fill' ) ) : -1,
		count: text( d.querySelector( '.mizzey-minicart__count' ) ),
		empty: shown( d.querySelector( '.mizzey-minicart__empty' ) ),
		disc: shown( d.querySelector( '.mizzey-minicart__empty .mizzey-empty__disc svg' ) ),
		zz: !! d.querySelector( '.mizzey-zz' ),
		loading: shown( d.querySelector( '.mizzey-minicart__loading' ) ),
		disabled: [ ...d.querySelectorAll( '.mizzey-minicart__view, .mizzey-minicart__checkout' ) ].filter( shown ).map( ( b ) => b.getAttribute( 'aria-disabled' ) === 'true' ),
	};
}, DIALOG );

/** The panel's place and corners. */
const geometry = ( page ) => page.evaluate( ( s ) => {
	const panel = document.querySelector( s + ' .mizzey-minicart__panel' );
	const r = panel.getBoundingClientRect();
	const cs = getComputedStyle( panel );
	const px = ( v ) => parseFloat( v ) || 0;
	return {
		left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width,
		vw: document.documentElement.clientWidth, vh: window.innerHeight,
		tl: px( cs.borderTopLeftRadius ), tr: px( cs.borderTopRightRadius ), bl: px( cs.borderBottomLeftRadius ), br: px( cs.borderBottomRightRadius ),
	};
}, DIALOG );

/** The board's sizes, measured on the rendered mini cart. */
const sizes = ( page ) => page.evaluate( ( s ) => {
	const d = document.querySelector( s );
	const box = ( q ) => { const el = d.querySelector( q ); return el ? el.getBoundingClientRect() : null; };
	const style = ( q ) => { const el = d.querySelector( q ); return el ? getComputedStyle( el ) : null; };
	const px = ( v ) => Math.round( parseFloat( v ) || 0 );
	const head = box( '.mizzey-minicart__head' );
	const tile = box( '.mizzey-minicart__tile' );
	const stepper = box( '.mizzey-minicart__line .mizzey-stepper' );
	const step = box( '.mizzey-minicart__line .mizzey-stepper button' );
	const remove = box( '.mizzey-minicart__remove' );
	const track = box( '.mizzey-minicart__fill' );
	const van = box( '.mizzey-minicart__van' );
	const view = box( '.mizzey-minicart__view' );
	const checkout = box( '.mizzey-minicart__checkout' );
	const list = style( '.mizzey-minicart__list' );
	const body = style( '.mizzey-minicart__body' );
	const foot = style( '.mizzey-minicart__foot' );
	const name = style( '.mizzey-minicart__name' );
	const option = style( '.mizzey-minicart__option:not([hidden])' );
	const price = style( '.mizzey-minicart__price' );
	const title = style( '.mizzey-minicart__title' );
	const line = style( '.mizzey-minicart__line' );
	const message = style( '.mizzey-minicart__delivery-text' );
	const subtotal = style( '.mizzey-minicart__subtotal' );
	const tileStyle = style( '.mizzey-minicart__tile' );
	return {
		headHeight: head ? Math.round( head.height ) : 0,
		bodyPadding: body ? [ px( body.paddingTop ), px( body.paddingInlineStart ) ] : [],
		listGap: list ? px( list.rowGap ) : -1,
		linePadding: line ? px( line.paddingTop ) : 0,
		lineGap: line ? px( line.columnGap ) : 0,
		tile: tile ? [ Math.round( tile.width ), Math.round( tile.height ), px( tileStyle.borderTopLeftRadius ) ] : [],
		stepper: stepper ? [ Math.round( stepper.width ), Math.round( stepper.height ) ] : [],
		stepButton: step ? [ Math.round( step.width ), Math.round( step.height ) ] : [],
		remove: remove ? [ Math.round( remove.width ), Math.round( remove.height ) ] : [],
		track: track ? Math.round( track.height ) : 0,
		van: van ? [ Math.round( van.width ), Math.round( van.height ) ] : [],
		buttons: view && checkout ? [ Math.round( view.height ), Math.round( checkout.height ), Math.round( Math.abs( checkout.left - view.right ) < Math.abs( view.left - checkout.right ) ? checkout.left - view.right : view.left - checkout.right ) ] : [],
		footPadding: foot ? [ px( foot.paddingTop ), px( foot.paddingInlineStart ), px( foot.paddingBottom ) ] : [],
		type: {
			title: title ? [ px( title.fontSize ), px( title.lineHeight ), title.fontWeight ] : [],
			name: name ? [ px( name.fontSize ), px( name.lineHeight ), name.fontWeight ] : [],
			option: option ? [ px( option.fontSize ), px( option.lineHeight ) ] : [],
			price: price ? [ px( price.fontSize ), px( price.lineHeight ), price.fontWeight ] : [],
			message: message ? [ px( message.fontSize ), px( message.lineHeight ) ] : [],
			subtotal: subtotal ? [ px( subtotal.fontSize ), px( subtotal.lineHeight ), subtotal.fontWeight ] : [],
		},
	};
}, DIALOG );

/** The empty state's sizes (C1-Mini-Cart C, empty: the cart icon in a 96 disc, the title, one sentence, one action). */
const emptySizes = ( page ) => page.evaluate( ( s ) => {
	const d = document.querySelector( s );
	const box = ( q ) => { const el = d.querySelector( q ); return el ? el.getBoundingClientRect() : null; };
	const style = ( q ) => { const el = d.querySelector( q ); return el ? getComputedStyle( el ) : null; };
	const px = ( v ) => Math.round( parseFloat( v ) || 0 );
	const disc = box( '.mizzey-minicart__empty .mizzey-empty__disc' );
	const icon = box( '.mizzey-minicart__empty .mizzey-empty__disc svg' );
	const title = style( '.mizzey-minicart__empty .mizzey-empty__title' );
	const text = style( '.mizzey-minicart__empty .mizzey-empty__text' );
	const action = box( '.mizzey-minicart__empty .mizzey-button' );
	const foot = d.querySelector( '.mizzey-minicart__foot' );
	return {
		disc: disc ? [ Math.round( disc.width ), Math.round( disc.height ) ] : [],
		icon: icon ? Math.round( icon.width ) : 0,
		title: title ? [ px( title.fontSize ), px( title.lineHeight ) ] : [],
		text: text ? [ px( text.fontSize ), px( text.lineHeight ) ] : [],
		action: action ? Math.round( action.height ) : 0,
		foot: !! foot && foot.getClientRects().length > 0,
	};
}, DIALOG );

/** The loading rows' sizes (C1-Mini-Cart C, loading: a 64 tile with radius 16, bars 12 high, a 140 by 52 pill). */
const loadingSizes = ( page ) => page.evaluate( ( s ) => {
	const d = document.querySelector( s );
	const rows = [ ...d.querySelectorAll( '.mizzey-minicart__line--loading' ) ].filter( ( el ) => el.getClientRects().length );
	const first = rows[ 0 ];
	const r = ( el ) => ( el ? el.getBoundingClientRect() : { width: 0, height: 0 } );
	const tile = first && first.querySelector( '.mizzey-minicart__tile-skeleton' );
	const bars = first ? [ ...first.querySelectorAll( '.mizzey-minicart__main > .mizzey-skeleton' ) ].map( ( b ) => Math.round( r( b ).height ) ) : [];
	const pill = first && first.querySelector( '.mizzey-minicart__stepper-skeleton' );
	const sub = d.querySelector( '.mizzey-minicart__subtotal-skeleton' );
	return {
		rows: rows.length,
		tile: tile ? [ Math.round( r( tile ).width ), Math.round( r( tile ).height ), Math.round( parseFloat( getComputedStyle( tile ).borderTopLeftRadius ) ) ] : [],
		bars,
		pill: pill ? [ Math.round( r( pill ).width ), Math.round( r( pill ).height ) ] : [],
		subtotal: !! sub && sub.getClientRects().length > 0,
	};
}, DIALOG );

/** The busy line: its loader's dots and width, inside the stepper's value slot, and its controls waiting. */
const busySizes = ( page ) => page.evaluate( ( s ) => {
	const line = [ ...document.querySelectorAll( s + ' .mizzey-minicart__line.is-busy' ) ][ 0 ];
	if ( ! line ) {
		return null;
	}
	const loader = line.querySelector( '.mizzey-minicart__updating .mizzey-loader' );
	const slot = line.querySelector( '.mizzey-stepper__value' );
	const dots = loader ? [ ...loader.querySelectorAll( '.mizzey-loader__dot' ) ].map( ( dot ) => Math.round( parseFloat( getComputedStyle( dot ).width ) ) ) /* the laid-out size: the dots pulse in scale */ : [];
	const lb = loader ? loader.getBoundingClientRect() : null;
	const sb = slot.getBoundingClientRect();
	return {
		dots,
		width: lb ? Math.round( lb.width ) : 0,
		inside: !! lb && lb.left >= sb.left - 0.5 && lb.right <= sb.right + 0.5 && lb.top >= sb.top - 0.5 && lb.bottom <= sb.bottom + 0.5,
		waiting: [ ...document.querySelectorAll( s + ' .mizzey-stepper__button, ' + s + ' .mizzey-minicart__remove' ) ].every( ( b ) => b.disabled || b.getAttribute( 'aria-disabled' ) === 'true' ),
		busy: document.querySelector( s + ' .mizzey-minicart__body' ).getAttribute( 'aria-busy' ),
	};
}, DIALOG );

/** Where the panel is on its way in: its translation now. */
const travel = ( page ) => page.evaluate( ( s ) => {
	const m = new DOMMatrixReadOnly( getComputedStyle( document.querySelector( s + ' .mizzey-minicart__panel' ) ).transform );
	return [ Math.round( m.m41 ), Math.round( m.m42 ) ];
}, DIALOG );

/** Open and watch the panel come in: moving shortly after the press, at rest at the end; at once under reduced motion. */
async function opening( browser, url, width, reduced ) {
	const phone = width < 600;
	const context = await browser.newContext( { viewport: { width, height: phone ? 844 : 900 }, isMobile: phone, hasTouch: phone, reducedMotion: reduced ? 'reduce' : 'no-preference' } );
	const page = await context.newPage();
	await go( page, url );
	const cart = await control( page );
	await cart.click();
	// The script is imported on the press: the panel's way in is measured from the moment the dialog opens.
	await page.waitForFunction( ( q ) => document.querySelector( q ).open, DIALOG, { polling: 'raf', timeout: TIMEOUT } );
	await page.waitForTimeout( 40 );
	const early = await travel( page );
	if ( ! reduced ) {
		await page.screenshot( { path: path.join( plan.shots, `t113-en-${ width }-opening.png` ) } );
	}
	await page.waitForTimeout( 900 );
	const late = await travel( page );
	await context.close();
	return { early, late };
}

/** axe-core, from the copy `npx` has cached or AXE_PATH (test tooling on the developer's machine, as product-card.cjs). */
const AXE = ( () => {
	const root = path.join( require( 'node:os' ).homedir(), 'AppData', 'Local', 'npm-cache', '_npx' );
	for ( const dir of fs.existsSync( root ) ? fs.readdirSync( root ) : [] ) {
		const file = path.join( root, dir, 'node_modules', 'axe-core', 'axe.min.js' );
		if ( fs.existsSync( file ) ) {
			return file;
		}
	}
	return process.env.AXE_PATH || null;
} )();

/** Serious or critical axe-core findings in the open mini cart (K-5). */
async function axe( page ) {
	if ( ! AXE ) {
		return null;
	}
	await page.addScriptTag( { path: AXE } );
	return page.evaluate( async ( s ) => ( await window.axe.run( s, { resultTypes: [ 'violations' ] } ) ).violations
		.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' ).map( ( v ) => `${ v.id }: ${ v.nodes.length }` ), DIALOG );
}

/** Add through the Store API, as WooCommerce's own buttons do, in the page's session. */
const add = ( page, rest, items ) => page.evaluate( async ( { rest, items } ) => {
	const first = await fetch( rest + 'cart', { credentials: 'same-origin' } );
	const nonce = first.headers.get( 'Nonce' );
	const out = [];
	for ( const item of items ) {
		const r = await fetch( rest + 'cart/add-item', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify( item ) } );
		out.push( r.status );
	}
	return out;
}, { rest, items } );

/** Focus stays in the open mini cart however far Tab goes. */
async function trapped( page ) {
	let inside = true;
	for ( let i = 0; i < 14; i++ ) {
		await page.keyboard.press( i % 5 === 4 ? 'Shift+Tab' : 'Tab' );
		inside = inside && await page.evaluate( ( s ) => {
			const d = document.querySelector( s );
			return d.contains( document.activeElement ) || document.activeElement === document.body;
		}, DIALOG );
	}
	return inside;
}

const focusOnControl = ( page ) => page.evaluate( ( s ) => !! document.activeElement && document.activeElement.matches( s ), CONTROL );

async function headerCount( page ) {
	return page.evaluate( ( s ) => {
		const badge = [ ...document.querySelectorAll( s + ' .mizzey-count' ) ].find( ( b ) => ! b.hidden );
		return badge ? badge.textContent.trim() : '';
	}, CONTROL );
}

/** The cart page's subtotal, as WooCommerce's cart block shows it. */
async function cartPageSubtotal( page, url ) {
	await go( page, url );
	try {
		await page.waitForSelector( '.wc-block-components-totals-item__value', { timeout: 30000 } );
	} catch ( e ) {
		return '';
	}
	return page.evaluate( () => {
		const rows = [ ...document.querySelectorAll( '.wc-block-components-totals-item' ) ];
		const sub = rows.find( ( r ) => r.className.includes( 'subtotal' ) ) || rows[ 0 ];
		return sub ? sub.querySelector( '.wc-block-components-totals-item__value' ).textContent.trim() : '';
	} );
}

/**
 * Back to a page from the back-forward cache (the browser's Back): a change that was with the server when the shopper
 * left must not stay busy forever, and the mini cart must show the cart as it is now. A change is held (it never
 * answers), the shopper goes to another page and back. Where the engine restores the page from its cache, that real
 * restore is checked; where it does not (the page is loaded again, so nothing could stay busy), the restore event is
 * given to the same page instead, as the browser gives it. Either way: nothing busy, and the subtotal is the cart's.
 */
async function backForward( engineName, launch ) {
	const where = `[en, 1440, ${ engineName }]`;
	const L = plan.en;
	const found = loadPlaywright();
	const browser = await found.pw[ engineName ].launch( engineName === 'chromium' ? found.launch : { headless: true } );
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	const page = await context.newPage();
	await page.addInitScript( () => {
		window.addEventListener( 'pageshow', ( e ) => {
			window.__restored = ( window.__restored || 0 ) + ( e.persisted ? 1 : 0 );
		} );
	} );
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await go( page, L.home );
	await add( page, L.rest, [ { id: L.simple.id, quantity: 1 } ] );
	const busyNow = async () => {
		await openFromHeader( page );
		await settled( page );
		await page.route( '**/cart/update-item*', () => {} ); // held: never answers
		await page.click( `${ DIALOG } .mizzey-minicart__more` );
		await page.waitForTimeout( 300 );
		return page.evaluate( ( q ) => !! document.querySelector( q + ' .mizzey-minicart__line.is-busy' ), DIALOG );
	};
	const wasBusy = await busyNow();
	// The server's cart changes meanwhile (as from another tab): the restored page must show it.
	const other = await context.newPage();
	await go( other, L.home );
	await add( other, L.rest, [ { id: L.simple.id, quantity: 2 } ] );
	await other.close();
	await page.goto( L.cart, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.goBack( { waitUntil: 'load', timeout: TIMEOUT } );
	await page.waitForTimeout( 600 );
	let real = await page.evaluate( () => window.__restored || 0 );
	if ( ! real ) {
		// Loaded again, not restored: put the page back in the busy state and give it the restore event.
		await page.unroute( '**/cart/update-item*' );
		await busyNow();
		await page.evaluate( () => window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) ) );
	}
	// The held change is still held (it never answers into this page), so only the restore can clear it.
	await page.waitForTimeout( 2500 );
	const after = await page.evaluate( async ( { q, rest } ) => {
		const d = document.querySelector( q );
		const cart = await ( await fetch( rest + 'cart', { credentials: 'same-origin' } ) ).json();
		const sub = d.querySelector( '.mizzey-minicart__subtotal' );
		return {
			busy: !! d.querySelector( '.is-busy, [aria-busy="true"]' ) || d.getAttribute( 'data-state' ) === 'loading',
			open: d.open,
			shown: sub && sub.getClientRects().length ? sub.textContent.trim() : '',
			server: Number( cart.totals.total_items ),
			unit: Number( cart.totals.currency_minor_unit ),
		};
	}, { q: DIALOG, rest: L.rest } );
	const serverMinor = Math.round( after.server / Math.pow( 10, after.unit ) * 100 );
	await page.unroute( '**/cart/update-item*' ).catch( () => {} );
	check( wasBusy && ! after.busy && ( ! after.open || minor( after.shown ) === serverMinor ) && errors.length === 0, `CART-12 ${ where } back from another page (${ real ? 'restored from the back-forward cache' : 'restore event given: this engine loaded the page again' }): nothing stays busy, and the mini cart shows the cart's subtotal now (${ JSON.stringify( { wasBusy, after, serverMinor, errors } ) })` );
	await browser.close();
}

async function language( browser, lang ) {
	const L = plan[ lang ];
	const rtl = lang === 'ar';
	const where = ( w ) => `[${ lang }, ${ w }]`;
	const { context, page, errors } = await newPage( browser, 1440 );
	await go( page, L.home );

	// ---- Empty ------------------------------------------------------------------------------------------------------
	let opened = await openFromHeader( page );
	check( opened, `CART-12 ${ where( 1440 ) } the header's cart control opens the mini cart` );
	if ( ! opened ) {
		await context.close();
		return;
	}
	await settled( page );
	let seen = await read( page );
	check( seen.empty && seen.disc && ! seen.zz, `CART-12 ${ where( 1440 ) } an empty cart shows the line icon in a disc and no ZZ (${ JSON.stringify( { empty: seen.empty, disc: seen.disc, zz: seen.zz } ) })` );
	if ( lang === 'en' ) {
		const e = await emptySizes( page );
		measured[ 'en-1440-empty' ] = e;
		check( e.disc.join() === '96,96' && e.icon === 40 && e.title.join() === '19,25' && e.text.join() === '16,24' && e.action === 52 && ! e.foot, `board ${ where( 1440 ) } empty: a 96 disc with the 40 icon, title 19/25, text 16/24, a 52 action and no footer (${ JSON.stringify( e ) })` );
		await page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-1440-empty.png` ) } );
	}
	const foundEmpty = await axe( page );
	check( Array.isArray( foundEmpty ) && foundEmpty.length === 0, `K-5 ${ where( 1440 ) } no serious or critical axe-core finding in the empty mini cart (${ foundEmpty === null ? 'axe-core was not found: set AXE_PATH' : JSON.stringify( foundEmpty ) })` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 400 );
	check( ! ( await isOpen( page ) ) && await focusOnControl( page ), `NFR-11 ${ where( 1440 ) } Escape closes it and focus returns to the cart control` );

	// ---- A simple and a variable item -------------------------------------------------------------------------------
	const statuses = await add( page, L.rest, [ { id: L.simple.id, quantity: 1 }, { id: L.variation.id, quantity: 1, variation: L.variation.attributes } ] );
	check( statuses.every( ( s ) => s === 201 ), `the fixture: both items are added through the Store API (${ statuses.join( ', ' ) })` );

	// The loading rows show until the server answers: the cart's answer is held back for a moment.
	let release;
	const held = new Promise( ( r ) => ( release = r ) );
	await page.route( ( url ) => /wc\/store\/v1\/cart\/?(\?|$)/.test( url.pathname + url.search ), async ( route ) => {
		await held;
		await route.continue();
	} );
	opened = await openFromHeader( page );
	await page.waitForTimeout( 200 );
	seen = await read( page );
	check( opened && seen.loading && seen.lines.length === 0 && seen.disabled.length === 2 && seen.disabled.every( Boolean ), `CART-12 ${ where( 1440 ) } while the cart loads it shows the loading rows and the footer's buttons are disabled (${ JSON.stringify( { loading: seen.loading, lines: seen.lines.length, disabled: seen.disabled } ) })` );
	if ( lang === 'en' ) {
		const l = await loadingSizes( page );
		measured[ 'en-1440-loading' ] = l;
		check( l.rows === 3 && l.tile.join() === '64,64,16' && l.bars.join() === '12,12' && l.pill.join() === '140,52' && l.subtotal, `board ${ where( 1440 ) } loading: three rows, the 64 tile with radius 16, two 12 high bars, the 140 by 52 pill, the subtotal's bar (${ JSON.stringify( l ) })` );
		await page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-1440-loading.png` ) } );
	}
	release();
	await page.unroute( () => true );
	await settled( page );
	seen = await read( page );
	const names = seen.lines.map( ( l ) => l.name );
	const simple = seen.lines.find( ( l ) => l.name === L.simple.name );
	const variable = seen.lines.find( ( l ) => l.name === L.variation.name );
	check( seen.lines.length === 2 && !! simple && !! variable, `CART-12 ${ where( 1440 ) } it lists the simple and the variable item by name (${ JSON.stringify( names ) })` );
	check( !! variable && variable.option === L.variation.option, `CART-12 ${ where( 1440 ) } the variable item shows its option (${ variable ? JSON.stringify( variable.option ) : 'none' }, expected ${ JSON.stringify( L.variation.option ) })` );
	check( !! simple && minor( simple.price ) === L.simple.minor && !! variable && minor( variable.price ) === L.variation.minor, `CART-12 ${ where( 1440 ) } each line shows its price (${ simple ? simple.price : '' }, ${ variable ? variable.price : '' })` );
	check( minor( seen.subtotal ) === L.simple.minor + L.variation.minor, `CART-12 ${ where( 1440 ) } the subtotal is the lines' sum (${ seen.subtotal })` );
	check( seen.count !== '' && ( rtl || seen.count.includes( '2' ) ), `CART-12 ${ where( 1440 ) } the title counts the items (${ seen.title })` );
	const needed = plan.freeMinor - ( L.simple.minor + L.variation.minor );
	check( seen.delivery !== '' && minor( seen.delivery ) === needed && ! seen.reached, `CART-07 ${ where( 1440 ) } the free-delivery line says what is still needed, from the cart's own rule (${ JSON.stringify( seen.delivery ) })` );
	check( near( seen.fill, ( L.simple.minor + L.variation.minor ) / plan.freeMinor, 0.03 ), `CART-07 ${ where( 1440 ) } the track is filled to what the cart has reached (${ seen.fill.toFixed( 3 ) })` );
	check( errors.length === 0, `${ where( 1440 ) } no script error (${ errors.join( ' | ' ) })` );

	// A line with no option has no option row; the price row sits where the board draws it: 12 + the 40 of the name's two
	// lines + 8 (60), and 22 lower (4 + 18) under an option.
	// In Arabic the name's two lines are Tajawal's line height, so the same rule is measured from them.
	const rowAt = ( l, withOption ) => 12 + l.nameH + ( withOption ? 4 + l.optionH : 0 ) + 8;
	check( !! simple && ! simple.optionRow && simple.rowTop === rowAt( simple, false ) && ( rtl || simple.rowTop === 60 ) && !! variable && variable.optionRow && variable.rowTop === rowAt( variable, true ) && ( rtl || variable.rowTop === 82 ), `board ${ where( 1440 ) } no option row without an option; the price row 60 from the line's top, 82 under an option (${ JSON.stringify( { simple: simple && [ simple.optionRow, simple.rowTop, simple.nameH ], variable: variable && [ variable.optionRow, variable.rowTop, variable.nameH, variable.optionH ] } ) })` );
	// Each remove button is named for its product (the board's word, then the name), so a screen reader tells them apart.
	check( !! simple && simple.removeName.includes( L.simple.name ) && !! variable && variable.removeName.includes( L.variation.name ), `NFR-11 ${ where( 1440 ) } each remove button is named for its product (${ JSON.stringify( [ simple && simple.removeName, variable && variable.removeName ] ) })` );

	// A load the shopper has already replaced (closed and opened again) fails late: it must not clear the lines the newer
	// load showed, nor put an error above them.
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );
	let calls = 0;
	await page.route( ( url ) => /wc\/store\/v1\/cart\/?(\?|$)/.test( url.pathname + url.search ), async ( route ) => {
		calls += 1;
		if ( calls === 1 ) {
			await new Promise( ( r ) => setTimeout( r, 1500 ) );
			await route.fulfill( { status: 500, contentType: 'application/json', body: JSON.stringify( { code: 'test', message: 'held and failed' } ) } );
			return;
		}
		await route.continue();
	} );
	await openFromHeader( page );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 200 );
	await openFromHeader( page );
	await page.waitForTimeout( 2500 );
	await settled( page ).catch( () => {} );
	await page.unroute( () => true );
	const late = await read( page );
	const errorShown = await page.evaluate( ( q ) => { const e = document.querySelector( q + ' .mizzey-minicart__error' ); return !! e && e.getClientRects().length > 0; }, DIALOG );
	const where2 = await page.evaluate( ( q ) => { const d = document.querySelector( q ); return { open: d.open, state: d.getAttribute( 'data-state' ), lines: d.querySelectorAll( '.mizzey-minicart__line:not(.mizzey-minicart__line--loading)' ).length }; }, DIALOG );
	check( late.lines.length === 2 && ! errorShown, `CART-12 ${ where( 1440 ) } a replaced load that fails late leaves the newer lines and shows no error (${ JSON.stringify( { lines: late.lines.length, errorShown, calls, where2 } ) })` );

	// ---- The drawer: out of the edge, full height, 28 on the corners facing the page -----------------------------------
	const g = await geometry( page );
	measured[ `${ lang }-1440-panel` ] = g;
	const edge = rtl ? near( g.left, 0 ) : near( g.right, g.vw );
	const corners = rtl ? ( g.tl === 0 && g.bl === 0 && g.tr === 28 && g.br === 28 ) : ( g.tr === 0 && g.br === 0 && g.tl === 28 && g.bl === 28 );
	check( edge && near( g.top, 0 ) && near( g.bottom, g.vh ) && near( g.width, 420 ), `CART-12 ${ where( 1440 ) } a drawer out of the ${ rtl ? 'left' : 'right' } edge, full height, 420 wide (${ JSON.stringify( g ) })` );
	check( corners, `CART-12 ${ where( 1440 ) } square on its edge, 28 on the two corners facing the page` );
	if ( lang === 'en' ) {
		const m = await sizes( page );
		measured[ 'en-1440' ] = m;
		check( m.headHeight === 64 && m.type.title[ 0 ] === 19 && m.type.title[ 1 ] === 25, `board ${ where( 1440 ) } the header is 64 high, the title 19/25 (${ m.headHeight }, ${ m.type.title })` );
		check( m.bodyPadding[ 0 ] === 16 && m.bodyPadding[ 1 ] === 24, `board ${ where( 1440 ) } the list is padded 16 24 (${ m.bodyPadding })` );
		check( m.linePadding === 12 && m.lineGap === 12 && m.tile.join() === '64,64,16', `board ${ where( 1440 ) } a line: 12 above and below, the 64 by 64 tile with radius 16, 12 to the text (${ m.linePadding }, ${ m.lineGap }, ${ m.tile })` );
		check( m.type.name.join() === '13,20,500' && m.type.option.join() === '12,18' && m.type.price.join() === '16,24,700', `board ${ where( 1440 ) } name 13/20 medium, option 12/18, price 16/24 bold (${ JSON.stringify( m.type ) })` );
		check( m.stepper.join() === '140,52' && m.stepButton.join() === '44,44' && m.remove.join() === '44,44', `board ${ where( 1440 ) } stepper 140 by 52 with 44 buttons, remove 44 by 44 (${ m.stepper }, ${ m.stepButton }, ${ m.remove })` );
		check( m.track === 6 && m.van[ 0 ] === 56 && m.type.message.join() === '13,20', `board ${ where( 1440 ) } delivery: 13/20 message, a 6 high track, the van 56 wide (${ m.type.message }, ${ m.track }, ${ m.van })` );
		check( m.footPadding.join() === '16,24,24' && m.buttons[ 0 ] === 52 && m.buttons[ 1 ] === 52 && m.buttons[ 2 ] === 8 && m.type.subtotal.join() === '16,24,700', `board ${ where( 1440 ) } footer padded 16 24 24, two 52 buttons 8 apart, subtotal 16/24 bold (${ m.footPadding }, ${ m.buttons }, ${ m.type.subtotal })` );
	}
	await page.waitForTimeout( 700 );
	await page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-1440.png` ) } );

	const found = await axe( page );
	check( Array.isArray( found ) && found.length === 0, `K-5 ${ where( 1440 ) } no serious or critical axe-core finding in the open mini cart (${ found === null ? 'axe-core was not found: set AXE_PATH' : JSON.stringify( found ) })` );

	// ---- Focus stays inside -------------------------------------------------------------------------------------------
	check( await trapped( page ), `NFR-11 ${ where( 1440 ) } focus stays inside the open mini cart` );

	// ---- A quantity change: the loader, and the server's answer only ----------------------------------------------------
	let releaseUpdate;
	const heldUpdate = new Promise( ( r ) => ( releaseUpdate = r ) );
	await page.route( '**/cart/update-item*', async ( route ) => {
		await heldUpdate;
		await route.continue();
	} );
	const before = await read( page );
	await page.click( `${ DIALOG } .mizzey-minicart__line:has(.mizzey-minicart__name:text-is("${ L.simple.name.replace( /"/g, '\\"' ) }")) .mizzey-minicart__more` );
	await page.waitForTimeout( 300 );
	const waiting = await read( page );
	const waitingLine = waiting.lines.find( ( l ) => l.name === L.simple.name ) || {};
	check( waitingLine.busy && waiting.lines.filter( ( l ) => l.busy ).length === 1 && waiting.subtotal === before.subtotal && ( await headerCount( page ) ) === before.lines.length.toString(), `CART-12 ${ where( 1440 ) } while the change is with the server the line shows the i-dot loader and the subtotal and count stay as they were (${ JSON.stringify( { busy: waitingLine.busy, subtotal: waiting.subtotal } ) })` );
	const u = await busySizes( page );
	measured[ `${ lang }-1440-updating` ] = u;
	check( !! u && u.dots.join() === '6,6,6' && u.width === 22 && u.inside && u.waiting && u.busy === 'true', `board ${ where( 1440 ) } updating a line: three 6 px i-dots 2 apart (22 wide) inside the stepper, every control waiting, the list marked busy (${ JSON.stringify( u ) })` );
	if ( lang === 'en' ) {
		await page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-1440-updating.png` ) } );
	}
	releaseUpdate();
	await page.unroute( '**/cart/update-item*' );
	await settled( page );
	await page.waitForTimeout( 200 );
	const after = await read( page );
	const afterLine = after.lines.find( ( l ) => l.name === L.simple.name ) || {};
	const total = 2 * L.simple.minor + L.variation.minor;
	check( afterLine.qty === '2' && minor( after.subtotal ) === total, `CART-12 ${ where( 1440 ) } after the server answers, the quantity is 2 and the subtotal ${ total } (${ after.subtotal })` );
	check( ( await headerCount( page ) ) === '3', `NAV-06 ${ where( 1440 ) } the header's count follows the server (${ await headerCount( page ) })` );
	if ( total >= plan.freeMinor ) {
		check( after.reached && after.fill > 0.97, `CART-07 ${ where( 1440 ) } at the threshold the line says free delivery is reached, the track full and the tick shown (${ JSON.stringify( after.delivery ) })` );
		if ( lang === 'en' ) {
			await page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-1440-reached.png` ) } );
		}
	}

	// ---- A removal ----------------------------------------------------------------------------------------------------
	let releaseRemove;
	const heldRemove = new Promise( ( r ) => ( releaseRemove = r ) );
	await page.route( '**/cart/remove-item*', async ( route ) => {
		await heldRemove;
		await route.continue();
	} );
	const beforeRemove = await read( page );
	await page.click( `${ DIALOG } .mizzey-minicart__line:has(.mizzey-minicart__name:text-is("${ L.variation.name.replace( /"/g, '\\"' ) }")) .mizzey-minicart__remove` );
	await page.waitForTimeout( 300 );
	const removing = await read( page );
	const rm = await busySizes( page );
	measured[ `${ lang }-1440-removing` ] = rm;
	const removingLine = removing.lines.find( ( l ) => l.name === L.variation.name ) || {};
	check( removingLine.busy && removing.lines.filter( ( l ) => l.busy ).length === 1 && removing.lines.length === beforeRemove.lines.length && removing.subtotal === beforeRemove.subtotal && !! rm && rm.dots.join() === '6,6,6' && rm.inside && rm.waiting, `CART-12 ${ where( 1440 ) } while a removal is with the server the line stays, shows the 6 px i-dots inside its stepper, and the subtotal stays (${ JSON.stringify( { busy: removingLine.busy, lines: removing.lines.length, rm } ) })` );
	if ( lang === 'en' ) {
		await page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-1440-removing.png` ) } );
	}
	releaseRemove();
	await page.unroute( '**/cart/remove-item*' );
	await settled( page );
	await page.waitForTimeout( 200 );
	const removed = await read( page );
	check( removed.lines.length === 1 && minor( removed.subtotal ) === 2 * L.simple.minor && ( await headerCount( page ) ) === '2', `CART-12 ${ where( 1440 ) } removing a line takes it out and the subtotal and count follow the server (${ removed.subtotal })` );
	const shownSubtotal = removed.subtotal;

	// ---- The scrim closes it, focus returns ---------------------------------------------------------------------------
	await page.mouse.click( rtl ? 1300 : 100, 450 );
	await page.waitForTimeout( 500 );
	check( ! ( await isOpen( page ) ) && await focusOnControl( page ), `NFR-11 ${ where( 1440 ) } a press on the scrim closes it and focus returns to the cart control` );

	// ---- One source: the cart page's subtotal ----------------------------------------------------------------------------
	const pageSubtotal = await cartPageSubtotal( page, L.cart );
	check( pageSubtotal !== '' && minor( pageSubtotal ) === minor( shownSubtotal ), `CART-12 ${ where( 1440 ) } the mini cart's subtotal is the cart page's (${ shownSubtotal } and ${ pageSubtotal })` );

	// ---- The phone: a bottom sheet ------------------------------------------------------------------------------------
	const cookies = await context.cookies();
	const phone = await newPage( browser, 390 );
	await phone.context.addCookies( cookies );
	await go( phone.page, L.home );
	opened = await openFromHeader( phone.page );
	check( opened, `CART-12 ${ where( 390 ) } the header's cart control opens the mini cart` );
	if ( opened ) {
		await settled( phone.page );
		await phone.page.waitForTimeout( 500 );
		const p = await geometry( phone.page );
		measured[ `${ lang }-390-panel` ] = p;
		check( near( p.top, 56 ) && near( p.bottom, p.vh ) && near( p.left, 0 ) && near( p.right, p.vw ), `CART-12 ${ where( 390 ) } a bottom sheet, its top edge 56 from the top, full width to the bottom (${ JSON.stringify( p ) })` );
		check( p.tl === 28 && p.tr === 28 && p.bl === 0 && p.br === 0, `CART-12 ${ where( 390 ) } it rounds its two top corners only` );
		const ps = await read( phone.page );
		check( ps.lines.length === 1 && ps.lines[ 0 ].name === L.simple.name && minor( ps.subtotal ) === minor( shownSubtotal ), `CART-12 ${ where( 390 ) } it lists the cart's line and subtotal (${ JSON.stringify( ps.lines.map( ( l ) => l.name ) ) })` );
		if ( lang === 'en' ) {
			const m = await sizes( phone.page );
			measured[ 'en-390' ] = m;
			check( m.headHeight === 64 && m.bodyPadding.join() === '16,16' && m.footPadding[ 0 ] === 16 && m.footPadding[ 1 ] === 16 && m.footPadding[ 2 ] >= 24, `board ${ where( 390 ) } header 64, content padded 16, footer 16 16 and 24 or the safe area (${ m.headHeight }, ${ m.bodyPadding }, ${ m.footPadding })` );
		}
		await phone.page.screenshot( { path: path.join( plan.shots, `t113-${ lang }-390.png` ) } );
		await phone.page.keyboard.press( 'Escape' );
		await phone.page.waitForTimeout( 400 );
		check( ! ( await isOpen( phone.page ) ), `NFR-11 ${ where( 390 ) } Escape closes the sheet` );
	}
	check( phone.errors.length === 0, `${ where( 390 ) } no script error (${ phone.errors.join( ' | ' ) })` );
	await phone.context.close();
	await context.close();
}

( async () => {
	const found = loadPlaywright();
	if ( ! found ) {
		throw new Error( 'Playwright was not found. Set PLAYWRIGHT_DIR.' );
	}
	const browser = await found.pw.chromium.launch( found.launch );
	try {
		for ( const lang of [ 'en', 'ar' ] ) {
			await language( browser, lang );
		}
		for ( const engine of [ 'chromium', 'webkit' ] ) {
			await backForward( engine );
		}
		// Opening: the drawer comes in from the end edge and the sheet from the bottom, at rest at the end; under reduced
		// motion both are in place at once (C1-Motion-Playground's reduced form).
		for ( const width of [ 1440, 390 ] ) {
			const moving = await opening( browser, plan.en.home, width, false );
			const still = await opening( browser, plan.en.home, width, true );
			measured[ `en-${ width }-opening` ] = { moving, still };
			const axis = width < 600 ? 1 : 0;
			check( moving.early[ axis ] > 0 && moving.late.join() === '0,0' && still.early.join() === '0,0', `board [en, ${ width }] opening: the ${ width < 600 ? 'sheet rises from the bottom' : 'drawer slides in from the end edge' } and comes to rest; under reduced motion it is in place at once (${ JSON.stringify( { moving, still } ) })` );
		}
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks, measured } ) );
} )().catch( ( error ) => {
	console.log( JSON.stringify( { error: String( error && error.stack ? error.stack : error ), checks, measured } ) );
	process.exit( 0 );
} );

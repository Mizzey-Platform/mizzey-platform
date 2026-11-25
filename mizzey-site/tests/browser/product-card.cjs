#!/usr/bin/env node
/**
 * The product card and the product rail in a real browser (spec 066, slice 4; AC-5, AC-6). Driven by t107, which makes
 * the fixture products and the page that carries them, and passes a JSON plan as the only argument; prints one JSON
 * object: { checks: [{ ok, what }], cart, shots }.
 *
 * The states are measured against C1-Components 06 and C1-Options (g, chosen): one corner tag flush with the card's
 * corner; the sale in the price line, its tag on a third line under 260 wide and 8 clear of the add button; the stock
 * on line 2, an out-of-stock card dimmed with no add button and no sale; a long name clamped to two lines; images of
 * any shape shown whole; no image and no rating keeping the card's height. Quick add goes through the Store API and
 * claims nothing before the server answers; a product the stock source refuses comes back to "+" with a notice; the
 * options of a variable product open as a sheet on a phone and a popover on a desktop; the heart saves and rolls back.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

// A response still awaited when a step fails is dropped with the browser; the failed step is reported by its check.
process.on( 'unhandledRejection', () => {} );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const shots = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const TIMEOUT = 60000;
const ID = plan.ids;
const INDIGO = 'rgb(38, 59, 115)';
const MUTED = 'rgb(91, 100, 128)';
const TINT = 'rgb(238, 241, 250)';

/** One card's measurements, in the given list ('rail' or 'grid'). */
function measure( page, list, id ) {
	return page.evaluate( ( [ list, id ] ) => {
		const root = document.querySelector( `.mizzey-products--${ list }` );
		const card = root && root.querySelector( `.mizzey-card[data-product-id="${ id }"]` );
		if ( ! card ) {
			return null;
		}
		const box = ( el ) => {
			if ( ! el || el.getClientRects().length === 0 ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			return { x: r.left, y: r.top, w: r.width, h: r.height, r: r.right, b: r.bottom };
		};
		const q = ( s ) => card.querySelector( s );
		const style = ( el ) => ( el ? getComputedStyle( el ) : null );
		const name = q( '.mizzey-card__name' );
		const img = q( 'img.mizzey-card__image' );
		const tag = q( '.mizzey-corner-tag' );
		const line = q( '.mizzey-card__line' );
		const lineVisible = line && line.getClientRects().length && line.textContent.trim();
		return {
			card: box( card ),
			classes: card.className,
			well: box( q( '.mizzey-card__well' ) ),
			name: box( name ),
			nameLines: name ? Math.round( name.getBoundingClientRect().height / parseFloat( style( name ).lineHeight ) ) : 0,
			nameClipped: name ? name.scrollHeight > name.clientHeight + 1 : false,
			img: box( img ),
			imgNatural: img ? { w: img.naturalWidth, h: img.naturalHeight, fit: style( img ).objectFit, opacity: style( img ).opacity } : null,
			noImage: !! q( '.mizzey-card__noimage svg' ),
			tags: card.querySelectorAll( '.mizzey-corner-tag' ).length,
			tag: tag ? { text: tag.textContent.trim(), box: box( tag ), bg: style( tag ).backgroundColor, color: style( tag ).color, ss: style( tag ).borderStartStartRadius, ee: style( tag ).borderEndEndRadius, h: tag.getBoundingClientRect().height } : null,
			heart: box( q( '.mizzey-card__heart' ) ),
			heartPressed: q( '.mizzey-card__heart' ) ? q( '.mizzey-card__heart' ).getAttribute( 'aria-pressed' ) : null,
			meta: box( q( '.mizzey-card__meta' ) ),
			metaText: q( '.mizzey-card__meta' ) ? q( '.mizzey-card__meta' ).textContent.replace( /\s+/g, ' ' ).trim() : '',
			price: box( q( '.mizzey-card__price' ) ),
			priceColor: style( q( '.mizzey-card__price' ) ) ? style( q( '.mizzey-card__price' ) ).color : null,
			priceText: q( '.mizzey-card__price' ) ? q( '.mizzey-card__price' ).textContent.trim() : '',
			old: box( q( '.mizzey-card__old' ) ),
			oldDecoration: style( q( '.mizzey-card__old' ) ) ? style( q( '.mizzey-card__old' ) ).textDecorationLine : null,
			sale: box( q( '.mizzey-sale-tag' ) ),
			saleText: q( '.mizzey-sale-tag' ) && q( '.mizzey-sale-tag' ).getClientRects().length ? q( '.mizzey-sale-tag' ).textContent.trim() : '',
			line: lineVisible ? box( line ) : null,
			lineText: lineVisible ? line.textContent.replace( /\s+/g, ' ' ).trim() : '',
			lineColor: line ? style( line ).color : null,
			added: q( '.mizzey-card__added' ) && q( '.mizzey-card__added' ).getClientRects().length ? q( '.mizzey-card__added' ).textContent.trim() : '',
			add: box( q( '.mizzey-card__add' ) ),
			addLabel: q( '.mizzey-card__add' ) ? q( '.mizzey-card__add' ).getAttribute( 'aria-label' ) : null,
			disc: box( q( '.mizzey-card__disc' ) ),
			dir: getComputedStyle( card ).direction,
		};
	}, [ list, id ] );
}

const near = ( a, b, tolerance = 1 ) => Math.abs( a - b ) <= tolerance;

/**
 * No price is ever cut: every price and struck price, in every card on the page, shows its whole text (its scroll
 * width within its box) and sits inside its card. Run after each state the page reaches.
 */
async function noPriceClipped( page, where ) {
	const cut = await page.evaluate( () => {
		const out = [];
		document.querySelectorAll( '.mizzey-card:not(.mizzey-card--skeleton)' ).forEach( ( card ) => {
			const box = card.getBoundingClientRect();
			card.querySelectorAll( '.mizzey-card__price, .mizzey-card__old' ).forEach( ( el ) => {
				if ( el.getClientRects().length === 0 ) {
					return;
				}
				const r = el.getBoundingClientRect();
				if ( el.scrollWidth > el.clientWidth || r.left < box.left - 0.5 || r.right > box.right + 0.5 ) {
					out.push( `${ card.dataset.productId } ${ el.className }: ${ el.textContent.trim() } (${ el.scrollWidth } > ${ el.clientWidth })` );
				}
			} );
		} );
		return out;
	} );
	check( cut.length === 0, `MER-07 [${ where }] no price or struck price is cut, in any card (${ JSON.stringify( cut.slice( 0, 3 ) ) })` );
}

/** The five-digit sale: on one line when price, struck price and tag fit; otherwise struck on line 2, tag on line 3. */
async function bigSale( page, where, list ) {
	const b = await measure( page, list, ID.big );
	if ( ! b ) {
		return check( false, `[${ where }] the five-digit sale has a card` );
	}
	const mid = ( x ) => x.y + x.h / 2;
	const oneLine = near( mid( b.old ), mid( b.price ), 3 ) && near( mid( b.sale ), mid( b.price ), 3 ) && b.sale.r <= b.add.x + 1;
	const stacked = b.old.y >= b.price.b - 1 && b.sale.y >= b.old.b - 1;
	check( oneLine || stacked, `MER-07 [${ where }] the five-digit sale "${ b.priceText }" keeps its struck price and tag whole: ${ oneLine ? 'one line' : stacked ? 'stacked' : 'neither' } (card ${ Math.round( b.card.w ) } wide)` );
}

/** The states every card shows, at one width and language. */
async function states( page, where, width, list ) {
	const m = {};
	for ( const [ key, id ] of Object.entries( ID ) ) {
		m[ key ] = await measure( page, list, id );
	}
	const all = Object.values( m );
	check( all.every( Boolean ), `PLP-01 [${ where }] every fixture product has a card in the ${ list }` );
	if ( ! all.every( Boolean ) ) {
		return m;
	}
	const rtl = where.startsWith( 'ar' );
	// The layout follows the room the card has, not a width: one line where the sale fits, stacked otherwise; under
	// 260 wide it is always stacked.
	const mid = ( x ) => x.y + x.h / 2;
	const narrow = ! near( mid( m.sale.old ), mid( m.sale.price ), 3 );
	check( m.sale.card.w >= 260 || narrow, `MER-07 [${ where }] under 260 wide the sale is stacked (${ Math.round( m.sale.card.w ) } wide)` );

	// One height in every state, and the price line at the same place in every card.
	const heights = [ ...new Set( all.map( ( c ) => Math.round( c.card.h ) ) ) ];
	check( heights.length === 1, `[${ where }] every state keeps the card's height (${ heights.join( ', ' ) })` );
	const priceTops = [ ...new Set( all.map( ( c ) => Math.round( c.price.y - c.card.y ) ) ) ];
	check( priceTops.length === 1, `[${ where }] the price starts at the same height in every card (${ priceTops.join( ', ' ) })` );

	// PLP-01: image, name, price; a long name is clamped to two lines; images of any shape are shown whole.
	check( m.long.nameLines === 2 && m.long.nameClipped, `PLP-01 [${ where }] the 200 character name is clamped to two lines (${ m.long.nameLines })` );
	for ( const key of [ 'long', 'sale' ] ) {
		const c = m[ key ];
		const ratio = c.imgNatural && c.imgNatural.w / c.imgNatural.h;
		const shown = c.img && c.img.w / c.img.h;
		check( c.img && c.imgNatural.fit === 'contain' && c.img.x >= c.well.x - 0.5 && c.img.r <= c.well.r + 0.5 && c.img.y >= c.well.y - 0.5 && c.img.b <= c.well.b + 0.5 && ( c.imgNatural.fit === 'contain' || near( ratio, shown, 0.02 ) ),
			`PLP-01 [${ where }] the ${ key === 'long' ? 'wide' : 'tall' } image is shown whole inside its well (contain, ${ c.img ? Math.round( c.img.w ) + 'x' + Math.round( c.img.h ) : '-' })` );
	}
	check( m.bare.noImage && ! m.bare.img, `PLP-01 [${ where }] a product with no image shows the image glyph` );
	check( m.bare.metaText === '' && m.bare.meta && near( m.bare.meta.h, m.long.meta.h ), `REV-04 [${ where }] no rating leaves its line empty, the same height` );
	check( /4\.7/.test( m.long.metaText ) && /305/.test( m.long.metaText ), `REV-04 [${ where }] the average rating and the review count: "${ m.long.metaText }"` );
	check( m.long.priceColor === INDIGO && m.long.priceText !== '', `PLP-01 [${ where }] the price in Royal Indigo (${ m.long.priceText })` );

	// MER-01: one label at most, the precedence New before Trending, flush with the card's top start corner.
	const t = m.two.tag;
	const startX = rtl ? m.two.card.r - ( t ? t.box.r : 0 ) : ( t ? t.box.x : 0 ) - m.two.card.x;
	check( m.two.tags === 1 && t && t.text === ( rtl ? t.text : 'New' ), `MER-01 [${ where }] two labels show one tag, by precedence: "${ t ? t.text : '-' }"` );
	check( t && near( t.box.y, m.two.card.y ) && near( startX, 0 ), `MER-01 [${ where }] the tag is flush with the card's top start corner (${ t ? Math.round( startX ) + ',' + Math.round( t.box.y - m.two.card.y ) : '-' })` );
	check( t && t.bg === TINT && t.color === INDIGO && near( t.h, 24 ) && t.ss === '20px' && t.ee === '12px', `MER-01 [${ where }] the tag is 24 high on #EEF1FA with Indigo text, corners 20 and 12 (${ t ? [ t.bg, t.color, t.h, t.ss, t.ee ].join( ' ' ) : '-' })` );
	check( all.filter( ( c ) => c !== m.two ).every( ( c ) => c.tags === 0 ), `MER-01 [${ where }] a product with no label shows no tag` );
	const heartEnd = rtl ? m.long.heart.x - m.long.card.x : m.long.card.r - m.long.heart.r;
	check( heartEnd < 16 && m.long.heart.y - m.long.card.y < 16, `PLP-05 [${ where }] the wishlist heart keeps the top end corner (${ Math.round( heartEnd ) })` );

	// PLP-02, MER-07: the sale in the price line; under 260 wide the tag drops to line 3, 8 clear of the add button.
	const s = m.sale;
	check( s.old && s.oldDecoration === 'line-through' && /-\d+%/.test( s.saleText ), `PLP-02 [${ where }] the sale shows the struck old price and the "${ s.saleText }" tag` );
	if ( narrow ) {
		check( s.old.y >= s.price.b - 1 && s.sale.y >= s.old.b - 1, `MER-07 [${ where }] under 260 wide: the struck price on line 2, the tag on line 3` );
		const clear = s.sale.y - s.add.b;
		const sideClear = rtl ? s.sale.x - s.add.r : s.add.x - s.sale.r;
		check( clear >= 8 || sideClear >= 8, `MER-07 [${ where }] the tag is at least 8 clear of the add button (${ Math.round( Math.max( clear, sideClear ) ) })` );
	} else {
		check( near( s.old.y + s.old.h / 2, s.price.y + s.price.h / 2, 3 ) && near( s.sale.y + s.sale.h / 2, s.price.y + s.price.h / 2, 3 ), `MER-07 [${ where }] from 260 wide: price, struck price and tag on one line` );
	}
	const discLine = s.disc.y + s.disc.h / 2;
	check( discLine >= s.price.y && discLine <= s.price.b + 8, `PLP-04 [${ where }] the add button lines up with the first price line` );
	check( ! /save/i.test( await page.evaluate( () => [ ...document.querySelectorAll( '.mizzey-card__well' ) ].map( ( w ) => w.textContent ).join( ' ' ) ) ), `MER-07 [${ where }] no "Save" or "Out of stock" badge on any image` );

	// PLP-03, AC-6: stock on line 2.
	check( m.low.lineText !== '' && /3/.test( m.low.lineText ), `PLP-03 [${ where }] three left under the low-stock amount reads "${ m.low.lineText }"` );
	const o = m.sold;
	check( o.lineText !== '' && o.lineColor === MUTED, `PLP-03 [${ where }] sold out reads "${ o.lineText }" in #5B6480` );
	check( o.imgNatural && near( parseFloat( o.imgNatural.opacity ), 0.6, 0.01 ), `PLP-03 [${ where }] sold out shows the image at 60% (${ o.imgNatural ? o.imgNatural.opacity : '-' })` );
	check( ! o.add && ! o.old && o.saleText === '', `PLP-03 [${ where }] sold out has no add button and no sale` );
	check( m.var.addLabel && m.var.add, `PLP-04 [${ where }] a product with options has its add button ("${ m.var.addLabel }")` );
	return m;
}

/** Click a card's add button and follow it: no claim before the answer, then the tick and "Added". */
async function quickAdd( page, where, list, id, { hold = 0 } = {} ) {
	let release;
	const gate = new Promise( ( resolve ) => ( release = resolve ) );
	const route = async ( r ) => {
		if ( hold ) {
			await gate;
		}
		await r.continue();
	};
	await page.route( '**/wc/store/v1/cart/add-item**', route );
	const answer = page.waitForResponse( ( res ) => res.url().includes( '/cart/add-item' ), { timeout: TIMEOUT } );
	const before = await measure( page, list, id );
	await page.evaluate( () => ( window.__mizzeyCount = [] ) );
	await page.locator( `.mizzey-products--${ list } .mizzey-card[data-product-id="${ id }"] .mizzey-card__add` ).click();
	let early = null;
	let earlyEvents = null;
	if ( hold ) {
		await page.waitForTimeout( hold );
		early = await measure( page, list, id );
		earlyEvents = await page.evaluate( () => [ ...window.__mizzeyCount ] );
		release();
	}
	const response = await answer;
	const body = await response.json().catch( () => ( {} ) );
	await page.waitForFunction( ( [ l, i ] ) => {
		const c = document.querySelector( `.mizzey-products--${ l } .mizzey-card[data-product-id="${ i }"]` );
		return c && ! c.classList.contains( 'is-busy' );
	}, [ list, id ], { timeout: TIMEOUT } );
	await page.waitForTimeout( 400 );
	const after = await measure( page, list, id );
	const events = await page.evaluate( () => window.__mizzeyCount );
	await page.unroute( '**/wc/store/v1/cart/add-item**', route );
	return { before, early, earlyEvents, after, status: response.status(), body, events };
}

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' } ) );
		return;
	}
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	let cart = null;
	try {
		const watchErrors = ( page, errors ) => page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		const listen = () => document.addEventListener( 'mizzey-cart-changed', ( e ) => ( window.__mizzeyCount || ( window.__mizzeyCount = [] ) ).push( e.detail && e.detail.count ) );

		// ---- English, desktop: the rail and the grid ----------------------------------------------------------------
		const desk = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
		const page = await desk.newPage();
		const errors = [];
		watchErrors( page, errors );
		await page.addInitScript( listen );
		await page.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
		await page.waitForSelector( '.mizzey-products--rail .mizzey-card', { timeout: TIMEOUT } );
		await page.evaluate( () => document.fonts.ready );

		const rail = await page.evaluate( () => {
			const r = document.querySelector( '.mizzey-products--rail' );
			const list = r.querySelector( '.mizzey-products__list' );
			const cards = [ ...list.querySelectorAll( '.mizzey-card' ) ].map( ( c ) => c.getBoundingClientRect() );
			const visible = cards.filter( ( c ) => c.left >= list.getBoundingClientRect().left - 1 && c.right <= list.getBoundingClientRect().right + 1 );
			return {
				heading: ( r.querySelector( 'h2' ) || {} ).textContent,
				all: !! r.querySelector( '.mizzey-products__all' ),
				arrows: r.querySelectorAll( '.mizzey-products__arrow' ).length,
				scrolls: list.scrollWidth > list.clientWidth,
				visible: visible.length,
				width: Math.round( cards[ 0 ].width ),
			};
		} );
		check( rail.heading && rail.all && rail.arrows === 2 && rail.scrolls, `PLP-01 [en 1440] the rail: heading, "View all", two arrows, and it scrolls (${ JSON.stringify( rail ) })` );
		check( rail.visible === 6 && near( rail.width, 192, 2 ), `[en 1440] the rail shows six cards 192 wide on the 1272 grid (${ rail.visible }, ${ rail.width })` );
		const firstBefore = await page.evaluate( () => document.querySelector( '.mizzey-products--rail .mizzey-products__list' ).scrollLeft );
		await page.locator( '.mizzey-products--rail .mizzey-products__arrow' ).nth( 1 ).click();
		await page.waitForTimeout( 900 );
		const firstAfter = await page.evaluate( () => document.querySelector( '.mizzey-products--rail .mizzey-products__list' ).scrollLeft );
		check( firstAfter > firstBefore, `[en 1440] the next arrow scrolls the rail (${ firstBefore } to ${ firstAfter })` );
		await page.locator( '.mizzey-products--rail .mizzey-products__arrow' ).nth( 0 ).click();
		await page.waitForTimeout( 900 );

		await states( page, 'en 1440 rail', 1440, 'rail' );
		const grid = await page.evaluate( () => {
			const cards = [ ...document.querySelectorAll( '.mizzey-products--grid .mizzey-card' ) ].map( ( c ) => c.getBoundingClientRect() );
			return { perRow: cards.filter( ( c ) => Math.round( c.top ) === Math.round( cards[ 0 ].top ) ).length, width: Math.round( cards[ 0 ].width ) };
		} );
		check( grid.perRow === 4, `PLP-01 [en 1440] the grid: four in a row (${ grid.perRow }, ${ grid.width } wide)` );
		await states( page, 'en 1440 grid', 1440, 'grid' );
		await bigSale( page, 'en 1440 grid', 'grid' );
		await bigSale( page, 'en 1440 rail', 'rail' );
		await noPriceClipped( page, 'en 1440' );

		const skeleton = await page.evaluate( () => {
			const sk = document.querySelector( '.mizzey-card--skeleton' );
			return sk ? { zz: !! sk.querySelector( '.mizzey-well .mizzey-zz' ), bars: sk.querySelectorAll( '.mizzey-skeleton' ).length, hidden: sk.getAttribute( 'aria-hidden' ) || ( sk.querySelector( '[role="img"]' ) ? 'labelled' : '' ) } : null;
		} );
		check( skeleton && skeleton.zz && skeleton.bars >= 4, `[en 1440] the loading card: the image well with the breathing ZZ and the bars (${ JSON.stringify( skeleton ) })` );

		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await page.locator( '.mizzey-products--rail' ).screenshot( { path: path.join( plan.shots, `${ plan.tag }-en-1440-rail.png` ) } );
		await page.locator( '.mizzey-products--grid' ).screenshot( { path: path.join( plan.shots, `${ plan.tag }-en-1440-grid.png` ) } );
		shots.push( path.join( plan.shots, `${ plan.tag }-en-1440-rail.png` ), path.join( plan.shots, `${ plan.tag }-en-1440-grid.png` ) );

		// ---- PLP-04: quick add, held until the server answers -------------------------------------------------------
		const add = await quickAdd( page, 'en 1440', 'grid', ID.long, { hold: 1200 } );
		check( add.early && add.early.classes.includes( 'is-busy' ) && ! add.early.classes.includes( 'is-added' ) && add.early.added === '' && add.earlyEvents.length === 0,
			`PLP-04 while the server has not answered, the + shows the loader and nothing claims success (no "Added", no count): ${ JSON.stringify( add.early && { classes: add.early.classes, added: add.early.added, events: add.earlyEvents } ) }` );
		check( add.status === 201 || add.status === 200, `PLP-04 the add went to WooCommerce's Store API (HTTP ${ add.status })` );
		check( add.after.classes.includes( 'is-added' ) && /added/i.test( add.after.added ) && add.after.addLabel && /in your cart/i.test( add.after.addLabel ), `PLP-04 after the answer: the White tick, line 2 "${ add.after.added }", the button "${ add.after.addLabel }" (${ add.after.classes })` );
		check( near( add.after.card.h, add.before.card.h ) && near( add.after.price.y - add.after.card.y, add.before.price.y - add.before.card.y ), `PLP-04 nothing else moves when the add confirms (${ [ add.before.card.h, add.after.card.h, add.before.price.y - add.before.card.y, add.after.price.y - add.after.card.y ].map( Math.round ).join( ', ' ) })` );
		check( add.events.length === 1 && add.events[ 0 ] === add.body.items_count, `PLP-04 the count is the server's (${ JSON.stringify( add.events ) } and items_count ${ add.body.items_count })` );
		const toast = await page.evaluate( () => [ ...document.querySelectorAll( '.mizzey-card-toasts .mizzey-notice' ) ].map( ( n ) => ( { text: n.textContent.replace( /\s+/g, ' ' ).trim(), link: !! n.querySelector( 'a.mizzey-notice__action' ) } ) ) );
		check( toast.length >= 1 && toast[ toast.length - 1 ].link, `PLP-04 the notice confirms the add, with a link to the cart (${ JSON.stringify( toast ) })` );

		// ---- ERP-03 through the card: WooCommerce lists 3, the stock source holds none -----------------------------
		const refused = await quickAdd( page, 'en 1440', 'grid', ID.refused );
		check( refused.before.lineText !== '' && refused.status >= 400, `ERP-03 the card says "${ refused.before.lineText }" but the stock source refuses the add (HTTP ${ refused.status })` );
		check( ! refused.after.classes.includes( 'is-added' ) && ! refused.after.classes.includes( 'is-busy' ) && refused.events.length === 0, 'ERP-03 the + returns, and no count is claimed' );
		const error = await page.evaluate( () => {
			const n = [ ...document.querySelectorAll( '.mizzey-card-toasts .mizzey-notice--error' ) ].pop();
			return n ? n.textContent.replace( /\s+/g, ' ' ).trim() : '';
		} );
		check( error !== '', `ERP-03 a notice says why: "${ error }"` );

		// ---- PLP-04 with options, desktop: the popover --------------------------------------------------------------
		await page.locator( `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.var }"] .mizzey-card__add` ).click();
		const pop = page.locator( `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.var }"] dialog.mizzey-card-picker[open]` );
		await pop.waitFor( { timeout: TIMEOUT } );
		const popInfo = await pop.evaluate( ( d ) => {
			const panel = d.querySelector( '.mizzey-overlay__panel' ).getBoundingClientRect();
			const card = d.closest( '.mizzey-card' ).getBoundingClientRect();
			return {
				popover: d.classList.contains( 'is-popover' ),
				near: panel.bottom <= card.top + 24 || panel.top >= card.bottom - 24 || ( panel.left < card.right && panel.right > card.left ),
				options: [ ...d.querySelectorAll( '[role="radio"]' ) ].map( ( o ) => ( { text: o.textContent.replace( /\s+/g, ' ' ).trim(), disabled: o.getAttribute( 'aria-disabled' ) === 'true' } ) ),
				focusInside: d.contains( document.activeElement ),
			};
		} );
		check( popInfo.popover && popInfo.near, `PLP-04 [en 1440] the options open as a popover by the card (${ JSON.stringify( { popover: popInfo.popover, near: popInfo.near } ) })` );
		check( popInfo.options.length === 3 && popInfo.options.filter( ( o ) => o.disabled ).length === 1 && /chocolate/i.test( popInfo.options.find( ( o ) => o.disabled ).text ), `PLP-03 [en 1440] the options are the product's own (flavours), the sold-out one marked: ${ JSON.stringify( popInfo.options ) }` );
		check( popInfo.focusInside, 'NFR-11 focus moves into the options' );
		await page.screenshot( { path: path.join( plan.shots, `${ plan.tag }-en-1440-options.png` ) } );
		shots.push( path.join( plan.shots, `${ plan.tag }-en-1440-options.png` ) );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 400 );
		const returned = await page.evaluate( ( id ) => document.activeElement && document.activeElement.closest( `.mizzey-card[data-product-id="${ id }"]` ) !== null && document.activeElement.classList.contains( 'mizzey-card__add' ), ID.var );
		check( returned, 'NFR-11 Escape closes the options and focus returns to the + it came from' );

		// A press on the scrim (anywhere outside the popover) closes it too.
		await page.locator( `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.var }"] .mizzey-card__add` ).click();
		await pop.waitFor( { timeout: TIMEOUT } );
		await page.waitForTimeout( 400 );
		await page.mouse.click( 4, 4 );
		await page.waitForTimeout( 400 );
		check( ( await page.locator( 'dialog.mizzey-card-picker[open]' ).count() ) === 0, 'PLP-04 [en 1440] a press on the scrim closes the options popover' );

		// ---- PLP-05: the heart, saved, removed, and rolled back when the server fails -------------------------------
		const heart = page.locator( `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.sale }"] .mizzey-card__heart` );
		const saved = page.waitForResponse( ( r ) => r.url().includes( '/mizzey/v1/wishlist/items' ) && r.request().method() === 'POST', { timeout: TIMEOUT } );
		await heart.click();
		const savedStatus = ( await saved ).status();
		await page.waitForTimeout( 300 );
		const list = await page.evaluate( async ( url ) => ( await ( await fetch( url, { credentials: 'same-origin' } ) ).json() ).items.map( ( i ) => i.canonical ), plan.wishlist );
		check( savedStatus === 201 && ( await heart.getAttribute( 'aria-pressed' ) ) === 'true' && list.includes( ID.sale ), `PLP-05 the heart saves the product to the wishlist (HTTP ${ savedStatus }, ${ JSON.stringify( list ) })` );
		await page.route( '**/mizzey/v1/wishlist/items/**', ( r ) => r.fulfill( { status: 500, contentType: 'application/json', body: '{"code":"x"}' } ) );
		await heart.click();
		const optimistic = await heart.getAttribute( 'aria-pressed' );
		await page.waitForTimeout( 800 );
		check( optimistic === 'false' && ( await heart.getAttribute( 'aria-pressed' ) ) === 'true', 'PLP-05 a removal the server refuses is rolled back: the heart is filled again' );
		await page.unroute( '**/mizzey/v1/wishlist/items/**' );
		const removed = page.waitForResponse( ( r ) => r.url().includes( '/mizzey/v1/wishlist/items/' ) && r.request().method() === 'DELETE', { timeout: TIMEOUT } );
		await heart.click();
		check( ( await removed ).status() === 200 && ( await heart.getAttribute( 'aria-pressed' ) ) === 'false', 'PLP-05 a second press removes it' );

		// ---- Accessibility (K-5): axe-core on the cards ------------------------------------------------------------
		const axePath = ( () => {
			const root = path.join( require( 'node:os' ).homedir(), 'AppData', 'Local', 'npm-cache', '_npx' );
			for ( const dir of fs.existsSync( root ) ? fs.readdirSync( root ) : [] ) {
				const file = path.join( root, dir, 'node_modules', 'axe-core', 'axe.min.js' );
				if ( fs.existsSync( file ) ) {
					return file;
				}
			}
			return process.env.AXE_PATH || null;
		} )();
		if ( axePath ) {
			await page.addScriptTag( { path: axePath } );
			const found = await page.evaluate( async () => ( await window.axe.run( '.mizzey-products', { resultTypes: [ 'violations' ] } ) ).violations
				.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' ).map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) );
			check( found.length === 0, `K-5 no serious or critical axe-core finding on the rail and the grid (${ JSON.stringify( found ) })` );
		} else {
			check( false, 'K-5 axe-core was not found (set AXE_PATH)' );
		}
		await noPriceClipped( page, 'en 1440, after the adds' );
		check( errors.length === 0, `no script error on the page (${ JSON.stringify( errors.slice( 0, 3 ) ) })` );

		// ---- English, phone: the 2-column grid, the narrow foot, and the options sheet -----------------------------
		const phoneCtx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
		await phoneCtx.addCookies( await desk.cookies() );
		const phone = await phoneCtx.newPage();
		const phoneErrors = [];
		watchErrors( phone, phoneErrors );
		await phone.addInitScript( listen );
		await phone.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
		await phone.waitForSelector( '.mizzey-products--grid .mizzey-card', { timeout: TIMEOUT } );
		await phone.evaluate( () => document.fonts.ready );
		const phoneGrid = await phone.evaluate( () => {
			const cards = [ ...document.querySelectorAll( '.mizzey-products--grid .mizzey-card' ) ].map( ( c ) => c.getBoundingClientRect() );
			const rail = [ ...document.querySelectorAll( '.mizzey-products--rail .mizzey-card' ) ].map( ( c ) => c.getBoundingClientRect() );
			return { perRow: cards.filter( ( c ) => Math.round( c.top ) === Math.round( cards[ 0 ].top ) ).length, width: Math.round( cards[ 0 ].width ), height: Math.round( cards[ 0 ].height ), railWidth: Math.round( rail[ 0 ].width ), arrows: [ ...document.querySelectorAll( '.mizzey-products__arrow' ) ].filter( ( a ) => a.getClientRects().length ).length };
		} );
		check( phoneGrid.perRow === 2 && near( phoneGrid.width, 173, 1 ) && near( phoneGrid.height, 298, 1 ), `PLP-01 [en 390] the grid: two in a row, 173 by 298 (${ JSON.stringify( phoneGrid ) })` );
		check( near( phoneGrid.railWidth, 156, 1 ) && phoneGrid.arrows === 0, `[en 390] the rail: cards 156 wide that scroll by touch, no arrows (${ phoneGrid.railWidth }, ${ phoneGrid.arrows })` );
		await states( phone, 'en 390 grid', 390, 'grid' );
		await bigSale( phone, 'en 390 grid', 'grid' );
		await noPriceClipped( phone, 'en 390' );
		await phone.locator( '.mizzey-products--grid' ).screenshot( { path: path.join( plan.shots, `${ plan.tag }-en-390-grid.png` ) } );
		shots.push( path.join( plan.shots, `${ plan.tag }-en-390-grid.png` ) );
		await phone.locator( `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.var }"] .mizzey-card__add` ).click();
		const sheet = phone.locator( `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.var }"] dialog.mizzey-card-picker[open]` );
		await sheet.waitFor( { timeout: TIMEOUT } );
		await phone.waitForTimeout( 600 );
		const sheetInfo = await sheet.evaluate( ( d ) => {
			const p = d.querySelector( '.mizzey-overlay__panel' ).getBoundingClientRect();
			return { popover: d.classList.contains( 'is-popover' ), bottom: Math.round( window.innerHeight - p.bottom ), width: Math.round( p.width ) };
		} );
		check( ! sheetInfo.popover && sheetInfo.bottom <= 1 && sheetInfo.width === 390, `PLP-04 [en 390] the options open as a bottom sheet (${ JSON.stringify( sheetInfo ) })` );
		await phone.screenshot( { path: path.join( plan.shots, `${ plan.tag }-en-390-options.png` ) } );
		shots.push( path.join( plan.shots, `${ plan.tag }-en-390-options.png` ) );
		const choc = sheet.locator( '[role="radio"][aria-disabled="true"]' );
		await choc.click( { force: true } );
		check( ( await choc.getAttribute( 'aria-checked' ) ) !== 'true', 'PLP-03 [en 390] the sold-out option cannot be chosen' );
		await sheet.locator( '[role="radio"]:not([aria-disabled="true"])' ).last().click();
		const answer = phone.waitForResponse( ( r ) => r.url().includes( '/cart/add-item' ), { timeout: TIMEOUT } );
		await sheet.locator( '.mizzey-card-picker__add' ).click();
		const varRes = await answer;
		await phone.waitForTimeout( 700 );
		const varAfter = await measure( phone, 'grid', ID.var );
		check( varRes.status() === 201 && varAfter.classes.includes( 'is-added' ) && ! ( await phone.locator( 'dialog.mizzey-card-picker[open]' ).count() ), `PLP-04 [en 390] the chosen flavour is added, the sheet closes and the card shows "Added" (HTTP ${ varRes.status() })` );
		await noPriceClipped( phone, 'en 390, after the add' );
		// ---- Two options at once: each choice rules out what is not in stock with it --------------------------------
		const pairCard = `.mizzey-products--grid .mizzey-card[data-product-id="${ ID.pair }"]`;
		await phone.locator( `${ pairCard } .mizzey-card__add` ).click();
		const pairSheet = phone.locator( `${ pairCard } dialog.mizzey-card-picker[open]` );
		await pairSheet.waitFor( { timeout: TIMEOUT } );
		await phone.waitForTimeout( 500 );
		const marks = () => pairSheet.evaluate( ( d ) => Object.fromEntries( [ ...d.querySelectorAll( '[role="radio"]' ) ].map( ( o ) => [ o.querySelector( '.mizzey-option__label' ).textContent.trim(), ( o.getAttribute( 'aria-checked' ) === 'true' ? 'chosen' : '' ) + ( o.getAttribute( 'aria-disabled' ) === 'true' ? 'sold out' : '' ) ] ) ) );
		const blue = await marks();
		check( blue.Blue === 'chosen' && blue.L === 'chosen' && blue.M === 'sold out' && blue.Red === 'sold out', `PLP-03 [en 390] with Blue and L chosen, M and Red show sold out (only Blue/L and Red/M are in stock): ${ JSON.stringify( blue ) }` );
		// Red is marked sold out with L, so it is pressed as a person presses it, past Playwright's own refusal.
		await pairSheet.locator( '[role="radio"]', { hasText: 'Red' } ).click( { force: true } );
		await phone.waitForTimeout( 300 );
		const red = await marks();
		check( red.Red === 'chosen' && red.M === 'chosen' && red.L === 'sold out' && red.Blue === 'sold out', `PLP-03 [en 390] choosing Red moves to Red/M, and L shows sold out with Red: ${ JSON.stringify( red ) }` );
		await phone.mouse.click( 195, 40 );
		await phone.waitForTimeout( 500 );
		check( ( await phone.locator( 'dialog.mizzey-card-picker[open]' ).count() ) === 0, 'PLP-04 [en 390] a press on the scrim above the sheet closes it' );

		check( phoneErrors.length === 0, `no script error on the phone (${ JSON.stringify( phoneErrors.slice( 0, 3 ) ) })` );
		cart = await phone.evaluate( async ( url ) => ( await fetch( url + 'cart', { credentials: 'same-origin' } ) ).json(), plan.store );

		// ---- Arabic, phone: right to left -------------------------------------------------------------------------
		const arPage = await phoneCtx.newPage();
		await arPage.goto( plan.ar, { waitUntil: 'load', timeout: TIMEOUT } );
		await arPage.waitForSelector( '.mizzey-products--grid .mizzey-card', { timeout: TIMEOUT } );
		await arPage.evaluate( () => document.fonts.ready );
		const ar = await states( arPage, 'ar 390 grid', 390, 'grid' );
		await bigSale( arPage, 'ar 390 grid', 'grid' );
		await noPriceClipped( arPage, 'ar 390' );
		check( ar.long && ar.long.dir === 'rtl', `FIX-04a [ar 390] the cards read right to left (${ ar.long ? ar.long.dir : '-' })` );
		const arWords = await arPage.evaluate( () => {
			const c = document.querySelector( '.mizzey-products--grid .mizzey-card.is-out' );
			return c ? c.querySelector( '.mizzey-card__line' ).textContent.trim() : '';
		} );
		check( /[؀-ۿ]/.test( arWords ), `NFR-04a [ar 390] the card's words are translated: "${ arWords }"` );
		await arPage.locator( '.mizzey-products--grid' ).screenshot( { path: path.join( plan.shots, `${ plan.tag }-ar-390-grid.png` ) } );
		shots.push( path.join( plan.shots, `${ plan.tag }-ar-390-grid.png` ) );

		// ---- Reduced motion: the skeleton is still ----------------------------------------------------------------
		const still = await browser.newContext( { viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' } );
		const stillPage = await still.newPage();
		await stillPage.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
		const anim = await stillPage.evaluate( () => {
			const zz = document.querySelector( '.mizzey-card--skeleton .mizzey-zz' );
			const bar = document.querySelector( '.mizzey-card--skeleton .mizzey-skeleton' );
			return zz && bar ? [ getComputedStyle( zz ).animationName, getComputedStyle( bar ).animationName ] : null;
		} );
		check( anim && anim.every( ( a ) => a === 'none' ), `K-5 under reduced motion the loading card is still (${ JSON.stringify( anim ) })` );
	} catch ( e ) {
		check( false, `the browser run stopped: ${ e.message.split( '\n' )[ 0 ] }` );
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks, cart, shots } ) );
} )();

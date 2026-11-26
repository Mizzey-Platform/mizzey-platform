#!/usr/bin/env node
/**
 * The top of the product page in a real browser (spec 066, slice 9; AC-6, AC-11): the gallery and the buying area.
 * Driven by t112, which makes the fixture products and passes a JSON plan as the only argument; prints one JSON object:
 * { checks: [{ ok, what }], cart, wishlist, shots }.
 *
 * Measured against C1-Product-Desktop (the main frame, the variant states, E1 to E7, the zoom), C1-Product-Phone
 * (frames 1, 3, 5, 6, 8, E1 to E7) and C1-Arabic-Stage-2 (G1), in numbers: the grid, every gap between the blocks of
 * the buying area, the heights of buttons and bars, type sizes and radii. The transient states are measured too: Add
 * to Cart busy and done, Buy Now busy, the buy bar busy and added, a photo loading, an option switching.
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
let step = 'start';
const check = ( ok, what ) => {
	checks.push( { ok: !! ok, what } );
	step = what.slice( 0, 80 );
};
// A step that never returns ends the run with what it has, named, rather than holding the scenario open.
setTimeout( () => {
	checks.push( { ok: false, what: `the browser run timed out after: ${ step }` } );
	console.log( JSON.stringify( { checks, cart: null, wishlist: null, shots } ) );
	process.exit( 0 );
}, 15 * 60000 ).unref();
const TIMEOUT = 60000;
const near = ( a, b, tolerance = 1 ) => typeof a === 'number' && Math.abs( a - b ) <= tolerance;
const INDIGO = 'rgb(38, 59, 115)';

/** Every box and style the checks read, in one pass. A missing part reads as null. */
function measure( page ) {
	return page.evaluate( () => {
		const root = document.querySelector( '.mizzey-buy' );
		if ( ! root ) {
			return null;
		}
		const shown = ( el ) => el && el.getClientRects().length > 0 && getComputedStyle( el ).visibility !== 'hidden';
		const box = ( sel, scope = root ) => {
			const el = typeof sel === 'string' ? scope.querySelector( sel ) : sel;
			if ( ! shown( el ) ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			const s = getComputedStyle( el );
			return {
				x: r.left, y: r.top, w: r.width, h: r.height, r: r.right, b: r.bottom,
				font: `${ Math.round( parseFloat( s.fontSize ) ) }px/${ Math.round( parseFloat( s.lineHeight ) ) }px ${ s.fontWeight }`, color: s.color,
				radius: s.borderStartStartRadius, pad: [ s.paddingTop, s.paddingInlineEnd, s.paddingBottom, s.paddingInlineStart ].join( ' ' ),
				text: el.textContent.replace( /\s+/g, ' ' ).trim(),
			};
		};
		const all = ( sel ) => [ ...root.querySelectorAll( sel ) ].filter( shown ).map( ( el ) => box( el ) );
		const name = root.querySelector( '.mizzey-buy__name' );
		const first = root.querySelector( '.mizzey-gallery__slide img' );
		return {
			dir: getComputedStyle( root ).direction,
			back: box( '.mizzey-buy__back' ),
			stage: box( '.mizzey-gallery__stage' ),
			counter: box( '.mizzey-gallery-counter' ),
			zoom: box( '.mizzey-gallery__zoom' ),
			arrows: all( '.mizzey-gallery__arrow' ),
			thumbs: all( '.mizzey-gallery__thumb' ),
			current: [ ...root.querySelectorAll( '.mizzey-gallery__thumb' ) ].findIndex( ( t ) => t.getAttribute( 'aria-current' ) === 'true' ),
			none: box( '.mizzey-gallery__none' ),
			showing: box( '.mizzey-gallery__showing' ),
			slides: [ ...root.querySelectorAll( '.mizzey-gallery__slide' ) ].filter( ( s ) => s.getAttribute( 'aria-hidden' ) !== 'true' ).map( ( s ) => {
				const img = s.querySelector( 'img' );
				const r = img ? img.getBoundingClientRect() : null;
				return img ? { alt: img.alt, x: r.left, y: r.top, r: r.right, b: r.bottom, fit: getComputedStyle( img ).objectFit } : { video: !! s.querySelector( 'video, iframe' ) };
			} ),
			firstImg: first ? { loading: first.getAttribute( 'loading' ), priority: first.getAttribute( 'fetchpriority' ), width: first.getAttribute( 'width' ), height: first.getAttribute( 'height' ) } : null,
			summary: box( '.mizzey-buy__summary' ),
			brand: box( '.mizzey-buy__brand' ),
			name: box( name ),
			nameClipped: name ? name.scrollHeight > name.clientHeight + 1 : null,
			more: box( '.mizzey-buy__more' ),
			rating: box( '.mizzey-buy__rating' ),
			prices: box( '.mizzey-buy__prices' ),
			price: box( '.mizzey-buy__price' ),
			from: box( '.mizzey-buy__from' ),
			old: box( '.mizzey-buy__old' ),
			oldStruck: root.querySelector( '.mizzey-buy__old' ) ? getComputedStyle( root.querySelector( '.mizzey-buy__old' ) ).textDecorationLine : null,
			save: box( '.mizzey-buy__save' ),
			saving: box( '.mizzey-buy__saving' ),
			authentic: box( '.mizzey-buy__authentic' ),
			stock: box( '.mizzey-buy__stock' ),
			stockState: root.querySelector( '.mizzey-buy__stock' ) ? root.querySelector( '.mizzey-buy__stock' ).dataset.state : null,
			groups: all( '.mizzey-buy__group' ),
			legends: all( '.mizzey-buy__legend' ),
			options: [ ...root.querySelectorAll( '.mizzey-buy__option' ) ].filter( shown ).map( ( o ) => ( { ...box( o ), checked: o.getAttribute( 'aria-checked' ), out: o.classList.contains( 'is-out' ), struck: getComputedStyle( o.querySelector( '.mizzey-buy__option-label' ) ).textDecorationLine } ) ),
			message: box( '.mizzey-buy__message' ),
			qty: box( '.mizzey-buy__qty' ),
			qtyLabel: box( '.mizzey-buy__qty-label' ),
			stepper: box( '.mizzey-buy__stepper' ),
			steps: all( '.mizzey-buy__step' ),
			count: box( '.mizzey-buy__count' ),
			add: box( '.mizzey-buy__add' ),
			addDisabled: root.querySelector( '.mizzey-buy__add' ) ? root.querySelector( '.mizzey-buy__add' ).disabled : null,
			addBusy: root.querySelector( '.mizzey-buy__add' ) ? root.querySelector( '.mizzey-buy__add' ).getAttribute( 'aria-busy' ) : null,
			now: box( '.mizzey-buy__now' ),
			nowHref: root.querySelector( '.mizzey-buy__now' ) ? root.querySelector( '.mizzey-buy__now' ).getAttribute( 'href' ) : null,
			links: box( '.mizzey-buy__links' ),
			wish: box( '.mizzey-buy__wish' ),
			wishPressed: root.querySelector( '.mizzey-buy__wish' ) ? root.querySelector( '.mizzey-buy__wish' ).getAttribute( 'aria-pressed' ) : null,
			share: box( '.mizzey-buy__share' ),
			out: box( '.mizzey-buy__out' ),
			delivery: box( '.mizzey-buy__delivery' ),
			returnsLink: root.querySelector( '.mizzey-buy__returns a' ) ? root.querySelector( '.mizzey-buy__returns a' ).href : null,
			features: box( '.mizzey-buy__features' ),
			featuresTitle: box( '.mizzey-buy__features h2' ),
			featureItems: all( '.mizzey-buy__features li' ).length,
			crumbs: box( '.woocommerce-breadcrumb, .wc-block-breadcrumbs', document ),
			headerCount: ( document.querySelector( '.mizzey-header__cart .mizzey-count' ) || {} ).textContent || '',
		};
	} );
}

/** The loader inside a control: its dots' size, and whether every dot stays inside the control's box. */
function loaderIn( page, selector ) {
	return page.evaluate( ( sel ) => {
		const host = document.querySelector( sel );
		const loader = host && host.querySelector( '.mizzey-loader' );
		if ( ! loader || ! loader.getClientRects().length || getComputedStyle( loader ).display === 'none' ) {
			return null;
		}
		const h = host.getBoundingClientRect();
		const dots = [ ...loader.querySelectorAll( '.mizzey-loader__dot' ) ].map( ( d ) => d.getBoundingClientRect() );
		// The dots breathe (scale 0.8 to 1), so their drawn box moves: the size and gap are the ones the style sets.
		return {
			dot: Math.round( parseFloat( getComputedStyle( loader.querySelector( '.mizzey-loader__dot' ) ).width ) ),
			gap: Math.round( parseFloat( getComputedStyle( loader ).columnGap ) ),
			inside: dots.every( ( d ) => d.left >= h.left - 0.5 && d.right <= h.right + 0.5 && d.top >= h.top - 0.5 && d.bottom <= h.bottom + 0.5 ),
			w: Math.round( h.width ),
			h: Math.round( h.height ),
			busy: host.getAttribute( 'aria-busy' ),
			label: ( host.querySelector( '.mizzey-buy__label' ) && getComputedStyle( host.querySelector( '.mizzey-buy__label' ) ).visibility ) || '',
		};
	}, selector );
}

async function axe( page, where ) {
	const root = path.join( require( 'node:os' ).homedir(), 'AppData', 'Local', 'npm-cache', '_npx' );
	let file = process.env.AXE_PATH || null;
	for ( const dir of fs.existsSync( root ) ? fs.readdirSync( root ) : [] ) {
		const candidate = path.join( root, dir, 'node_modules', 'axe-core', 'axe.min.js' );
		if ( fs.existsSync( candidate ) ) {
			file = candidate;
		}
	}
	if ( ! file ) {
		check( false, `K-5 [${ where }] axe-core was not found (set AXE_PATH)` );
		return;
	}
	await page.addScriptTag( { path: file } );
	const found = await page.evaluate( async () => ( await window.axe.run( '.mizzey-buy', { resultTypes: [ 'violations' ] } ) ).violations
		.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' ).map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 2 ).join( ', ' ) }` ) );
	check( found.length === 0, `K-5 [${ where }] no serious or critical axe-core finding on the buying area (${ JSON.stringify( found ) })` );
}

async function shot( page, name, full = false ) {
	const file = path.join( plan.shots, `${ plan.tag }-${ name }.png` );
	await page.screenshot( { path: file, fullPage: full } );
	shots.push( file );
}

async function open( ctx, url ) {
	const page = await ctx.newPage();
	page.errors = [];
	page.on( 'pageerror', ( e ) => page.errors.push( e.message ) );
	await page.addInitScript( () => {
		window.__mizzeyCount = [];
		document.addEventListener( 'mizzey-cart-changed', ( e ) => window.__mizzeyCount.push( e.detail && e.detail.count ) );
		window.__lcp = null;
		new PerformanceObserver( ( list ) => {
			const last = list.getEntries().pop();
			window.__lcp = last && last.element ? ( last.element.closest( '.mizzey-gallery__slide' ) ? 'gallery-first' : last.element.tagName + '.' + last.element.className ) : null;
		} ).observe( { type: 'largest-contentful-paint', buffered: true } );
	} );
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.evaluate( () => document.fonts.ready );
	await page.waitForTimeout( 400 );
	return page;
}

/** Choose an option by its label, inside a group named by its legend. */
async function choose( page, label ) {
	await page.locator( '.mizzey-buy__option', { has: page.locator( '.mizzey-buy__option-label', { hasText: new RegExp( `^${ label }$` ) } ) } ).first().click();
}

/** Press Add to Cart (or the bar's) with the answer held, and follow it. */
async function heldAdd( page, selector, where, hold = 1200 ) {
	let release;
	const gate = new Promise( ( resolve ) => ( release = resolve ) );
	const route = async ( r ) => {
		await gate;
		await r.continue();
	};
	await page.route( '**/wc/store/v1/cart/add-item**', route );
	const answer = page.waitForResponse( ( res ) => res.url().includes( '/cart/add-item' ), { timeout: TIMEOUT } );
	const before = await measure( page );
	const width = await page.evaluate( ( sel ) => document.querySelector( sel ).getBoundingClientRect().width, selector );
	await page.evaluate( () => ( window.__mizzeyCount = [] ) );
	await page.locator( selector ).click();
	await page.waitForTimeout( hold );
	const busy = await loaderIn( page, selector );
	const early = await measure( page );
	const earlyEvents = await page.evaluate( () => [ ...window.__mizzeyCount ] );
	const busyWidth = await page.evaluate( ( sel ) => document.querySelector( sel ).getBoundingClientRect().width, selector );
	await shot( page, `${ where }-busy` );
	release();
	const response = await answer;
	const body = await response.json().catch( () => ( {} ) );
	await page.waitForTimeout( 700 );
	const after = await measure( page );
	const done = await page.evaluate( ( sel ) => {
		const el = document.querySelector( sel );
		return { text: el.textContent.replace( /\s+/g, ' ' ).trim(), tick: !! el.querySelector( '.mizzey-buy__tick' ) && el.querySelector( '.mizzey-buy__tick' ).getClientRects().length > 0, h: Math.round( el.getBoundingClientRect().height ), bg: getComputedStyle( el ).backgroundColor };
	}, selector );
	await shot( page, `${ where }-added` );
	const events = await page.evaluate( () => window.__mizzeyCount );
	await page.unroute( '**/wc/store/v1/cart/add-item**', route );
	return { before, early, earlyEvents, busy, width, busyWidth, after, done, status: response.status(), body, events };
}

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' } ) );
		return;
	}
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	let cart = null;
	let wishlist = null;
	try {
		// ==== English, 1440: the simple product ===================================================================
		const desk = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
		const page = await open( desk, plan.urls.simple );
		const d = await measure( page );
		check( d !== null, 'AC-11 [en 1440] the product page shows the designed buying area (.mizzey-buy), not the native one' );
		if ( d ) {
			await shot( page, 'en-1440', true );
			// The grid: the gallery 732 and the buying area 516, 24 apart, on the 1272 grid.
			check( d.stage && near( d.stage.w, 732, 2 ) && near( d.stage.h, 560 ) && d.stage.radius === '24px', `PDP-01 [en 1440] the gallery's stage is 732 by 560, radius 24 (${ d.stage && [ d.stage.w, d.stage.h, d.stage.radius ] })` );
			check( d.summary && d.stage && near( d.summary.x - d.stage.r, 24 ) && near( d.summary.w, 516, 2 ), `[en 1440] the buying area is 516 wide, 24 after the gallery (${ d.summary && [ Math.round( d.summary.x - d.stage.r ), Math.round( d.summary.w ) ] })` );
			check( d.summary && d.stage && near( d.summary.y, d.stage.y ), `[en 1440] the brand line and the stage start on one line (${ d.summary && [ d.summary.y, d.stage.y ] })` );
			check( d.crumbs && d.stage && d.crumbs.b <= d.stage.y + 1 && near( d.crumbs.h, 44, 2 ), `[en 1440] the breadcrumb, 44 high, sits above the gallery (${ d.crumbs && [ d.crumbs.h, d.crumbs.b, d.stage.y ] })` );
			check( d.thumbs.length === 3 && d.thumbs.every( ( t ) => near( t.w, 88 ) && near( t.h, 88 ) && t.radius === '16px' ) && near( d.thumbs[ 0 ].y - d.stage.b, 8 ) && near( d.thumbs[ 1 ].x - d.thumbs[ 0 ].r, 8 ), `PDP-01 [en 1440] three thumbnails 88 by 88, radius 16, 8 under the stage and 8 apart (${ JSON.stringify( d.thumbs.map( ( t ) => [ t.w, t.h, t.radius ] ) ) }, ${ d.thumbs[ 0 ] && Math.round( d.thumbs[ 0 ].y - d.stage.b ) })` );
			check( d.counter && d.counter.text === '1 / 3' && near( d.counter.x - d.stage.x, 12 ) && near( d.stage.b - d.counter.b, 12 ) && near( d.counter.h, 32 ), `PDP-01 [en 1440] the counter "1 / 3", 32 high, 12 from the stage's start and foot (${ d.counter && [ d.counter.text, d.counter.h, Math.round( d.counter.x - d.stage.x ), Math.round( d.stage.b - d.counter.b ) ] })` );
			check( d.zoom && near( d.zoom.w, 44 ) && near( d.stage.r - d.zoom.r, 8 ) && near( d.stage.b - d.zoom.b, 8 ), `PDP-01 [en 1440] the zoom control, 44, 8 from the stage's end and foot (${ d.zoom && [ d.zoom.w, Math.round( d.stage.r - d.zoom.r ), Math.round( d.stage.b - d.zoom.b ) ] })` );
			check( d.arrows.length === 2 && d.arrows.every( ( a ) => near( a.w, 44 ) ) && near( d.arrows[ 0 ].x - d.stage.x, 12 ), `PDP-01 [en 1440] previous and next, 44, 12 inside the stage (${ JSON.stringify( d.arrows.map( ( a ) => [ a.w, Math.round( a.x - d.stage.x ) ] ) ) })` );
			check( d.firstImg && d.firstImg.loading !== 'lazy' && d.firstImg.priority === 'high' && d.firstImg.width && d.firstImg.height, `K-6 [en 1440] the first photo loads first: not lazy, fetchpriority high, its size written (${ JSON.stringify( d.firstImg ) })` );
			const lcp = await page.evaluate( () => window.__lcp );
			check( lcp === 'gallery-first', `K-6 [en 1440] the largest contentful paint is the gallery's first photo (${ lcp })` );
			check( d.slides.length === 1 && d.slides.every( ( s ) => s.fit === 'contain' && s.x >= d.stage.x - 0.5 && s.r <= d.stage.r + 0.5 && s.y >= d.stage.y - 0.5 && s.b <= d.stage.b + 0.5 ), `K-2 [en 1440] the photo, 2.27 wide, is shown whole inside the stage (${ JSON.stringify( d.slides ) })` );
			// The name: 20/28 Medium, three lines at most until "Show full name".
			check( d.name && d.name.font === '20px/28px 500' && d.name.h <= 84 + 1 && d.nameClipped, `PDP-03 [en 1440] the 200 character name: 20/28 Medium, three lines (${ d.name && [ d.name.font, d.name.h, d.nameClipped ] })` );
			check( d.brand && d.brand.text.includes( `${ plan.tag } Vital` ) && near( d.name.y - d.brand.b, 0 ) && d.brand.font.startsWith( '13px/20px 500' ), `PDP-03 [en 1440] the brand above the name, 13/20 Medium (${ d.brand && [ d.brand.text, d.brand.font, Math.round( d.name.y - d.brand.b ) ] })` );
			check( d.rating && /4\.8/.test( d.rating.text ) && /96/.test( d.rating.text ) && near( d.rating.y - d.more.b, 6 ), `REV-04 [en 1440] the rating line: 4.8 and 96 reviews, 6 under "Show full name" (${ d.rating && [ d.rating.text, Math.round( d.rating.y - d.more.b ) ] })` );
			check( d.price && d.price.font === '31px/40px 700' && d.price.color === INDIGO && near( d.prices.y - d.rating.b, 16 ), `PDP-04 [en 1440] the price: 31/40 Bold, Indigo, 16 under the rating (${ d.price && [ d.price.font, d.price.color, Math.round( d.prices.y - d.rating.b ) ] })` );
			check( d.old && d.oldStruck === 'line-through' && /1,875/.test( d.old.text ) && d.save && /20%/.test( d.save.text ) && d.saving && /375/.test( d.saving.text ), `PDP-04 [en 1440] the sale: old price struck, "Save 20%", "You save EGP 375" (${ [ d.old && d.old.text, d.save && d.save.text, d.saving && d.saving.text ] })` );
			check( d.authentic && d.authentic.text === 'Authentic, guaranteed' && d.stock && d.stock.text === 'In stock' && near( d.stock.y - d.authentic.b, 8 ), `PDP-06, PDP-16 [en 1440] "Authentic, guaranteed", then "In stock" 8 under it (${ [ d.authentic && d.authentic.text, d.stock && d.stock.text ] })` );
			check( d.stepper && near( d.stepper.w, 140 ) && near( d.stepper.h, 56 ) && d.add && near( d.add.h, 56 ) && near( d.add.x - d.stepper.r, 12 ) && near( d.add.y, d.stepper.y ), `PDP-07, PDP-08 [en 1440] the stepper 140 by 56 and Add to Cart 56 high on one row, 12 apart (${ d.add && [ d.stepper.w, d.stepper.h, d.add.h, Math.round( d.add.x - d.stepper.r ) ] })` );
			check( d.add && near( d.add.y - d.stock.b, 24 ) && ! d.qtyLabel, `[en 1440] the action row 24 under the stock line, no "Quantity" label on a desktop (${ d.add && Math.round( d.add.y - d.stock.b ) })` );
			check( d.now && near( d.now.h, 56 ) && near( d.now.y - d.add.b, 12 ) && near( d.now.w, 516, 2 ), `PDP-09 [en 1440] Buy Now, 56 high, full width, 12 under (${ d.now && [ d.now.h, Math.round( d.now.y - d.add.b ), d.now.w ] })` );
			check( d.nowHref && d.nowHref.includes( `add-to-cart=${ plan.ids.simple }` ) && d.nowHref.includes( 'mizzey_buy_now=1' ), `PDP-09 [en 1440] Buy Now carries the product and the Buy Now flag of specs/055 (${ d.nowHref })` );
			check( d.links && near( d.links.h, 44 ) && near( d.links.y - d.now.b, 8 ) && d.wish && d.share && near( d.wish.w, d.share.w, 1 ), `PDP-10, PDP-21 [en 1440] wishlist and share, 44 high, two halves, 8 under Buy Now (${ d.links && [ d.links.h, Math.round( d.links.y - d.now.b ), d.wish.w, d.share.w ] })` );
			check( d.delivery && near( d.delivery.y - d.links.b, 24 ) && d.delivery.radius === '20px' && d.delivery.pad === '16px 16px 16px 16px', `PDP-14 [en 1440] the delivery box: 24 under, radius 20, padding 16 (${ d.delivery && [ Math.round( d.delivery.y - d.links.b ), d.delivery.radius, d.delivery.pad ] })` );
			check( d.delivery && /Order now, get it (by|between) [A-Z][a-z]{2} \d{1,2} [A-Z][a-z]{2}/.test( d.delivery.text ) && /Easy returns within 14 days/.test( d.delivery.text ) && d.returnsLink === plan.returns, `PDP-14, PDP-15 [en 1440] the estimate of specs/055 as dates ("Order now, get it ...") and the returns summary with its page (${ d.delivery && d.delivery.text }; ${ d.returnsLink })` );
			check( d.features && near( d.features.y - d.delivery.b, 32 ) && d.featureItems === 3 && d.featuresTitle && d.featuresTitle.font === '20px/28px 900', `PDP-11 [en 1440] key features: 32 under, the title 20/28 Black, three points (${ d.features && [ Math.round( d.features.y - d.delivery.b ), d.featuresTitle && d.featuresTitle.font, d.featureItems ] })` );

			// "Show full name" opens the whole name in place.
			await page.locator( '.mizzey-buy__more' ).click();
			await page.waitForTimeout( 400 );
			const full = await measure( page );
			check( full.name && full.name.h > d.name.h && ! full.nameClipped, `PDP-03 [en 1440] "Show full name" shows the whole name (${ d.name.h } to ${ full.name && full.name.h })` );
			const noScroll = await page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth );
			check( noScroll, '[en 1440] nothing is wider than the window' );

			// A photo loading: the stage keeps its size and shows the breathing ZZ until the photo arrives.
			let release;
			const gate = new Promise( ( resolve ) => ( release = resolve ) );
			const hold = async ( r ) => {
				await gate;
				await r.continue();
			};
			await page.route( `**/*${ plan.tag }-tall*`, hold );
			await page.locator( '.mizzey-gallery__arrow' ).nth( 1 ).click();
			await page.waitForTimeout( 500 );
			const loading = await page.evaluate( () => {
				const slide = [ ...document.querySelectorAll( '.mizzey-gallery__slide' ) ].find( ( s ) => s.getAttribute( 'aria-hidden' ) !== 'true' );
				const zz = slide && slide.querySelector( '.mizzey-zz' );
				const stage = document.querySelector( '.mizzey-gallery__stage' ).getBoundingClientRect();
				return { zz: !! zz && zz.getClientRects().length > 0 && getComputedStyle( zz ).opacity !== '0', loaded: slide ? slide.classList.contains( 'is-loaded' ) : null, h: Math.round( stage.height ) };
			} );
			await shot( page, 'en-1440-photo-loading' );
			check( loading.zz && loading.loaded === false && loading.h === 560, `PDP-01 [en 1440] a photo loading: the stage keeps 560 and shows the breathing ZZ (${ JSON.stringify( loading ) })` );
			release();
			await page.unroute( `**/*${ plan.tag }-tall*`, hold );
			await page.waitForTimeout( 800 );
			const second = await measure( page );
			check( second.counter && second.counter.text === '2 / 3' && second.current === 1 && second.slides.length === 1 && second.slides[ 0 ].alt.includes( '-tall' ), `PDP-01 [en 1440] next shows photo 2 of 3, its thumbnail marked (${ second.counter && second.counter.text }, ${ second.current })` );
			check( second.slides[ 0 ] && second.slides[ 0 ].y >= second.stage.y - 0.5 && second.slides[ 0 ].b <= second.stage.b + 0.5, `K-2 [en 1440] the photo 0.29 wide is shown whole inside the stage (${ JSON.stringify( second.slides[ 0 ] ) })` );
			await page.locator( '.mizzey-gallery__thumb' ).nth( 2 ).click();
			await page.waitForTimeout( 600 );
			check( ( await measure( page ) ).counter.text === '3 / 3', 'PDP-01 [en 1440] a thumbnail shows its photo' );

			// Zoom: a dialog over the dimmed page, 880 wide, its own counter, the level control, focus in and back.
			await page.locator( '.mizzey-gallery__zoom' ).click();
			await page.waitForTimeout( 600 );
			const zoom = await page.evaluate( () => {
				const dlg = document.querySelector( 'dialog.mizzey-zoom' );
				if ( ! dlg || ! dlg.open ) {
					return null;
				}
				const panel = dlg.querySelector( '.mizzey-zoom__panel' ).getBoundingClientRect();
				const head = dlg.querySelector( '.mizzey-zoom__head' ).getBoundingClientRect();
				const level = dlg.querySelector( '.mizzey-zoom__level' ).getBoundingClientRect();
				const thumbs = [ ...dlg.querySelectorAll( '.mizzey-zoom__thumb' ) ].map( ( t ) => t.getBoundingClientRect() );
				return { w: Math.round( panel.width ), radius: getComputedStyle( dlg.querySelector( '.mizzey-zoom__panel' ) ).borderStartStartRadius, head: Math.round( head.height ), level: [ Math.round( level.width ), Math.round( level.height ) ], thumbs: thumbs.map( ( t ) => Math.round( t.width ) ), focus: dlg.contains( document.activeElement ), counter: dlg.querySelector( '.mizzey-zoom__counter' ).textContent.trim(), value: dlg.querySelector( '.mizzey-zoom__value' ).textContent.trim() };
			} );
			check( zoom && zoom.w === 880 && zoom.radius === '24px' && zoom.head === 56 && near( zoom.level[ 0 ], 148, 2 ) && zoom.level[ 1 ] === 48 && zoom.thumbs.every( ( t ) => t === 56 ), `PDP-01 [en 1440] zoom opens as a dialog 880 wide, radius 24, its head 56, the level control 148 by 48, thumbnails 56 (${ JSON.stringify( zoom ) })` );
			check( zoom && zoom.focus && zoom.counter === '3 / 3' && zoom.value === '100%', `PDP-01 [en 1440] zoom: focus inside, the photo shown, at 100% (${ zoom && [ zoom.focus, zoom.counter, zoom.value ] })` );
			await shot( page, 'en-1440-zoom' );
			await page.locator( '.mizzey-zoom__in' ).click();
			await page.locator( '.mizzey-zoom__in' ).click();
			await page.waitForFunction( () => document.querySelector( '.mizzey-zoom__value' ).textContent.trim() === '160%', null, { timeout: TIMEOUT } ).catch( () => {} );
			await page.waitForTimeout( 300 );
			const zoomed = await page.evaluate( () => ( { value: document.querySelector( '.mizzey-zoom__value' ).textContent.trim(), scale: new DOMMatrix( getComputedStyle( document.querySelector( '.mizzey-zoom__image' ) ).transform ).a } ) );
			check( zoomed.value === '160%' && Math.abs( zoomed.scale - 1.6 ) < 0.001, `PDP-01 [en 1440] zoom in twice: 160% and the photo scaled (${ JSON.stringify( zoomed ) })` );
			// A mouse drag moves the zoomed photo (this square photo is taller than the wide frame at 160%, so it moves up
			// and down; it is narrower, so it stays centred across); the wheel zooms at the pointer.
			const view = await page.locator( '.mizzey-zoom__view' ).boundingBox();
			const tf = () => page.evaluate( () => {
				const m = new DOMMatrix( getComputedStyle( document.querySelector( '.mizzey-zoom__image' ) ).transform );
				return [ m.e, m.f ];
			} );
			const [ x0, y0 ] = await tf();
			await page.mouse.move( view.x + view.width / 2, view.y + view.height / 2 );
			await page.mouse.down();
			await page.mouse.move( view.x + view.width / 2 - 60, view.y + view.height / 2 - 60, { steps: 6 } );
			await page.mouse.up();
			await page.waitForTimeout( 900 );
			const [ x1, y1 ] = await tf();
			check( y1 < y0 - 30 && Math.abs( x1 - x0 ) < 0.5, `PDP-01 [en 1440] a mouse drag moves the zoomed photo where it is larger than the frame, and holds it where it is not (${ [ x0, y0 ] } to ${ [ x1, y1 ] })` );
			await page.mouse.wheel( 0, -200 );
			await page.waitForTimeout( 300 );
			const wheeled = await page.evaluate( () => document.querySelector( '.mizzey-zoom__value' ).textContent.trim() );
			check( parseInt( wheeled, 10 ) > 160, `PDP-01 [en 1440] the wheel zooms further in (${ wheeled })` );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 400 );
			const back = await page.evaluate( () => ( { open: !! document.querySelector( 'dialog.mizzey-zoom[open]' ), focus: document.activeElement && document.activeElement.classList.contains( 'mizzey-gallery__zoom' ) } ) );
			check( ! back.open && back.focus, `NFR-11 [en 1440] Escape closes the zoom and focus returns to its control (${ JSON.stringify( back ) })` );

			// Add to Cart, held: the loader in the button, nothing claimed; then the tick, the count, the notice.
			const add = await heldAdd( page, '.mizzey-buy__add', 'en-1440-add' );
			check( add.busy && add.busy.busy === 'true' && near( add.busy.dot, 12, 1 ) && near( add.busy.gap, 4, 1 ) && add.busy.inside && near( add.busyWidth, add.width, 0.5 ) && near( add.busy.h, 56 ), `PDP-08 [en 1440] Add to Cart busy: the i-dot loader, dots 12 with 4 between (board frame 3), inside the 56 high button, its width kept (${ JSON.stringify( add.busy ) }, ${ add.width } to ${ add.busyWidth })` );
			check( add.earlyEvents.length === 0 && add.early.headerCount === add.before.headerCount, `PDP-08 [en 1440] while the server has not answered, the header's count stays (${ add.before.headerCount } to ${ add.early.headerCount }, events ${ JSON.stringify( add.earlyEvents ) })` );
			check( add.status === 201 && add.done.tick && /added/i.test( add.done.text ) && add.done.h === 56 && [ INDIGO, 'rgb(26, 40, 80)' ].includes( add.done.bg ), `PDP-08 [en 1440] after the answer (HTTP ${ add.status }): the Indigo button with the tick and "Added", 56 high (${ JSON.stringify( add.done ) })` );
			check( add.events.length === 1 && add.events[ 0 ] === add.body.items_count && String( add.body.items_count ) === add.after.headerCount, `NAV-06 [en 1440] the header shows the server's count, only after it answered (${ JSON.stringify( add.events ) }, ${ add.body.items_count }, header ${ add.after.headerCount })` );
			const toast = await page.evaluate( () => [ ...document.querySelectorAll( '.mizzey-buy-toasts .mizzey-notice' ) ].map( ( n ) => ( { text: n.textContent.replace( /\s+/g, ' ' ).trim(), link: !! n.querySelector( 'a.mizzey-notice__action' ) } ) ) );
			check( toast.length === 1 && /added to your cart/i.test( toast[ 0 ].text ) && toast[ 0 ].link, `PDP-08 [en 1440] a notice offers "View cart"; the mini cart does not open (${ JSON.stringify( toast ) })` );

			// The wishlist heart.
			const saved = page.waitForResponse( ( r ) => r.url().includes( '/mizzey/v1/wishlist/items' ) && r.request().method() === 'POST', { timeout: TIMEOUT } );
			await page.locator( '.mizzey-buy__wish' ).click();
			const savedStatus = ( await saved ).status();
			await page.waitForTimeout( 400 );
			const wished = await measure( page );
			check( savedStatus === 201 && wished.wishPressed === 'true' && /in your wishlist/i.test( wished.wish.text ), `PDP-10 [en 1440] the heart saves the product: "In your wishlist" (HTTP ${ savedStatus }, ${ wished.wish && wished.wish.text })` );

			// Share: the popover with Copy link and the sharing addresses of specs/055.
			await page.locator( '.mizzey-buy__share' ).click();
			await page.waitForTimeout( 400 );
			const share = await page.evaluate( () => {
				const pop = document.querySelector( '.mizzey-share' );
				if ( ! pop || ! pop.matches( ':popover-open, [open]' ) ) {
					return null;
				}
				return { links: [ ...pop.querySelectorAll( 'a' ) ].map( ( a ) => a.href ), copy: !! pop.querySelector( '.mizzey-share__copy' ), focus: pop.contains( document.activeElement ) };
			} );
			const url = encodeURIComponent( plan.urls.simple );
			check( share && share.copy && share.links.some( ( l ) => l.startsWith( 'https://wa.me/' ) ) && share.links.some( ( l ) => l.includes( 'facebook.com' ) && l.includes( url ) ) && share.links.some( ( l ) => l.includes( 'x.com' ) ) && share.links.some( ( l ) => l.startsWith( 'mailto:' ) ), `PDP-21 [en 1440] Share opens Copy link and the WhatsApp, Facebook, X and email addresses (${ JSON.stringify( share ) })` );
			await shot( page, 'en-1440-share' );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 300 );
			check( await page.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'mizzey-buy__share' ) ), 'NFR-11 [en 1440] Escape closes Share and focus returns to it' );
			await page.evaluate( () => window.scrollTo( 0, 0 ) );
			await axe( page, 'en 1440 simple' );
			check( page.errors.length === 0, `[en 1440] no script error (${ JSON.stringify( page.errors.slice( 0, 3 ) ) })` );
		}

		// ==== English, 1440: the variable product, options that are not size and colour ============================
		const vpage = await open( desk, plan.urls.var );
		const v = await measure( vpage );
		if ( v ) {
			const legends = v.legends.map( ( l ) => l.text );
			check( legends.length === 2 && legends[ 0 ] === 'Pack: 1 tub' && legends[ 1 ] === 'Flavour: Vanilla', `PDP-05 [en 1440] the groups are the product's own (Pack, Flavour), the default chosen (${ JSON.stringify( legends ) })` );
			check( ! /\b(Size|Colour|Color)\b/.test( v.summary.text ), 'PDP-05 [en 1440] no group is assumed to be size or colour' );
			check( /2,600/.test( v.price.text ) && v.stock.text === 'In stock in 1 tub, Vanilla' && v.stockState === 'in', `PDP-04, PDP-06 [en 1440] the chosen variant's price and stock (${ v.price.text }, ${ v.stock.text })` );
			check( v.showing && /Vanilla/.test( v.showing.text ) && v.slides[ 0 ] && v.slides[ 0 ].alt.includes( '-vanilla' ), `PDP-01 [en 1440] the gallery follows the option: the Vanilla photo, "Showing Vanilla" (${ v.showing && v.showing.text }, ${ v.slides[ 0 ] && v.slides[ 0 ].alt })` );
			const choc = v.options.find( ( o ) => /Chocolate/.test( o.text ) );
			check( choc && choc.out && choc.struck === 'line-through' && /Sold out/.test( choc.text ), `PDP-22 [en 1440] Chocolate, sold out in every pack, is struck with "Sold out" (${ JSON.stringify( choc && { out: choc.out, struck: choc.struck, text: choc.text } ) })` );
			check( v.options.every( ( o ) => near( o.h, 44 ) && o.radius === '999px' ) && near( v.options[ 1 ].x - v.options[ 0 ].r, 8 ) && near( v.legends[ 0 ].font.split( ' ' )[ 0 ].split( '/' )[ 0 ] === '16px' ? 16 : 0, 16 ), `PDP-05 [en 1440] the options: pills 44 high, 8 apart, the legend 16 (${ JSON.stringify( v.options.map( ( o ) => [ o.h, o.radius ] ) ) })` );
			check( near( v.groups[ 0 ].y - v.stock.b, 16 ) && near( v.groups[ 1 ].y - v.groups[ 0 ].b, 16 ) && near( v.options[ 0 ].y - v.legends[ 0 ].b, 8 ), `[en 1440] the groups 16 apart, the options 8 under their legend (${ [ v.groups[ 0 ].y - v.stock.b, v.groups[ 1 ].y - v.groups[ 0 ].b, v.options[ 0 ].y - v.legends[ 0 ].b ].map( Math.round ) })` );

			// An option switching: nothing moves, then the price, stock and photo follow.
			const fixed = ( m ) => [ m.add.y, m.stock.y, m.groups[ 1 ].y, m.stage.h, m.features.y ].map( Math.round );
			await choose( vpage, 'Unflavoured' );
			const mid = await measure( vpage );
			await shot( vpage, 'en-1440-switching' );
			await vpage.waitForTimeout( 700 );
			const u = await measure( vpage );
			check( JSON.stringify( fixed( mid ) ) === JSON.stringify( fixed( v ) ) && JSON.stringify( fixed( u ) ) === JSON.stringify( fixed( v ) ), `PDP-05 [en 1440] choosing an option shifts nothing, during the switch or after (${ JSON.stringify( [ fixed( v ), fixed( mid ), fixed( u ) ] ) })` );
			check( /2,200/.test( u.price.text ) && u.old && /2,450/.test( u.old.text ) && u.save && /10%/.test( u.save.text ), `PDP-04 [en 1440] 1 tub Unflavoured: its sale price, old price and saving (${ [ u.price.text, u.old && u.old.text, u.save && u.save.text ] })` );
			check( u.stock.text === 'Only 3 left in 1 tub, Unflavoured' && u.stockState === 'low', `PDP-06 [en 1440] the stock follows the variant: "${ u.stock.text }"` );
			check( ! u.showing && u.slides[ 0 ] && ! u.slides[ 0 ].alt.includes( '-vanilla' ), `PDP-01 [en 1440] a variant with no photo of its own shows the product's photos again (${ u.slides[ 0 ] && u.slides[ 0 ].alt })` );
			await choose( vpage, 'Chocolate' );
			await vpage.waitForTimeout( 500 );
			const c = await measure( vpage );
			check( c.message && /sold out/i.test( c.message.text ) && c.addDisabled && c.stockState === 'out', `PDP-22 [en 1440] the chosen combination sold out: a plain line says what to do, Add to Cart off (${ c.message && c.message.text }, ${ c.addDisabled })` );
			await choose( vpage, 'Unflavoured' );
			await choose( vpage, '2 tubs' );
			await vpage.waitForTimeout( 500 );
			const n = await measure( vpage );
			check( n.message && /not made/i.test( n.message.text ) && n.addDisabled, `PDP-05 [en 1440] a combination that is not made says so, Add to Cart waits (${ n.message && n.message.text })` );
			await shot( vpage, 'en-1440-not-made' );
			// Not made: no variant's price stays behind; the price goes back to "From" the lowest, with no sale.
			check( n.from && n.price && /2,200/.test( n.price.text ) && ! n.old && ! n.save && ( ! n.saving || n.saving.w === 0 || ( await vpage.evaluate( () => getComputedStyle( document.querySelector( '.mizzey-buy__saving' ) ).visibility ) ) === 'hidden' ), `PDP-04 [en 1440] a combination that is not made shows "From" the lowest price, no old price, no saving (${ JSON.stringify( { from: !! n.from, price: n.price && n.price.text, old: !! n.old, save: !! n.save } ) })` );
			// A variant whose photo is already in the gallery: listed once, and the counter counts what is shown.
			await choose( vpage, 'Chocolate' );
			await vpage.waitForTimeout( 600 );
			const dup = await vpage.evaluate( () => {
				const thumbs = [ ...document.querySelectorAll( '.mizzey-gallery__thumb' ) ].filter( ( t ) => ! t.hidden );
				return { shown: thumbs.length, indices: thumbs.map( ( t ) => t.dataset.index ), counter: document.querySelector( '.mizzey-gallery-counter' ).textContent.trim() };
			} );
			check( new Set( dup.indices ).size === dup.indices.length && dup.counter.startsWith( `1 / ${ dup.shown }` ), `PDP-01 [en 1440] 2 tubs Chocolate's photo, already in the gallery, is listed once and counted once (${ JSON.stringify( dup ) })` );
			await choose( vpage, 'Unflavoured' );
			await choose( vpage, '1 tub' );
			await vpage.waitForTimeout( 400 );
			await vpage.locator( '.mizzey-buy__step' ).nth( 1 ).click();
			await vpage.locator( '.mizzey-buy__step' ).nth( 1 ).click();
			await vpage.waitForTimeout( 300 );
			const q = await measure( vpage );
			check( q.count && q.count.text === '3' && await vpage.locator( '.mizzey-buy__step' ).nth( 1 ).isDisabled(), `PDP-07 [en 1440] the quantity stops at the 3 left (${ q.count && q.count.text })` );
			await vpage.locator( '.mizzey-buy__step' ).nth( 0 ).click();
			const answer = vpage.waitForResponse( ( r ) => r.url().includes( '/cart/add-item' ), { timeout: TIMEOUT } );
			await vpage.locator( '.mizzey-buy__add' ).click();
			const res = await answer;
			const sent = JSON.parse( res.request().postData() || '{}' );
			check( res.status() === 201 && sent.id === plan.variations[ '1 tub/Unflavoured' ] && sent.quantity === 2, `PDP-08 [en 1440] Add to Cart sends the chosen variant and its quantity (HTTP ${ res.status() }, ${ JSON.stringify( sent ) })` );
			// The video: in the gallery, muted, with its controls; it never plays by itself.
			await vpage.locator( '.mizzey-gallery__thumb.is-video' ).click();
			await vpage.waitForTimeout( 600 );
			const video = await vpage.evaluate( () => {
				const el = document.querySelector( '.mizzey-gallery__stage video' );
				return el ? { muted: el.muted, controls: el.controls, autoplay: el.autoplay, paused: el.paused, src: el.currentSrc || el.getAttribute( 'src' ) } : null;
			} );
			check( video && video.muted && video.controls && ! video.autoplay && video.src.includes( plan.video ), `PDP-02 [en 1440] the video plays in the gallery, muted, with its controls (${ JSON.stringify( video ) })` );
			check( vpage.errors.length === 0, `[en 1440 variable] no script error (${ JSON.stringify( vpage.errors.slice( 0, 3 ) ) })` );
		} else {
			check( false, 'AC-11 [en 1440] the variable product shows the designed buying area' );
		}

		// ==== Sold out, and no photo ==============================================================================
		const spage = await open( desk, plan.urls.sold );
		const s = await measure( spage );
		check( s && s.stock && s.stock.text === 'Out of stock' && s.stockState === 'out' && s.out && s.addDisabled && /out of stock/i.test( s.add.text ) && ! s.stepper && ! s.now && s.wish, `PDP-22 [en 1440] the whole product sold out: "Out of stock", the box, the button off, no quantity, no Buy Now, the wishlist kept (${ JSON.stringify( s && { stock: s.stock && s.stock.text, out: !! s.out, add: s.add && s.add.text, stepper: !! s.stepper, now: !! s.now } ) })` );
		if ( s ) {
			await shot( spage, 'en-1440-sold-out' );
		}
		// Sold out in every variation, nothing chosen: both buttons off and saying so; no "choose" line.
		const apage = await open( desk, plan.urls.allout );
		const ao = await measure( apage );
		check( ao && ao.addDisabled && /out of stock/i.test( ao.add.text ) && ! ao.message && ! ao.now && ao.out, `PDP-22 [en 1440] a variable product sold out in every variation: Add to Cart off with "Out of stock", no "choose" line, no Buy Now (${ JSON.stringify( ao && { add: ao.add && ao.add.text, disabled: ao.addDisabled, message: ao.message && ao.message.text, now: !! ao.now } ) })` );
		const bpage = await open( desk, plan.urls.bare );
		const b = await measure( bpage );
		check( b && b.none && /No photo yet/.test( b.none.text ) && ! b.zoom && ! b.counter && b.thumbs.length === 0 && near( b.stage.h, 560 ), `PDP-01 [en 1440] no photo: the image glyph and "No photo yet", no zoom, counter or thumbnails, the stage kept (${ JSON.stringify( b && { none: b.none && b.none.text, zoom: !! b.zoom, counter: !! b.counter, thumbs: b.thumbs.length } ) })` );
		if ( b ) {
			// Buy Now busy: the loader while the store adds and redirects. A page that is leaving cannot be measured, so
			// this one press is held on the page (a listener after the page's own cancels the leaving), measured and
			// photographed; then the address it carries is followed, and lands on checkout.
			await bpage.evaluate( () => document.addEventListener( 'click', ( e ) => {
				if ( e.target.closest( '.mizzey-buy__now' ) ) {
					e.preventDefault();
				}
			}, { once: true } ) );
			await bpage.locator( '.mizzey-buy__now' ).click();
			await bpage.waitForTimeout( 400 );
			const nowBusy = await loaderIn( bpage, '.mizzey-buy__now' );
			await shot( bpage, 'en-1440-buy-now-busy' );
			check( nowBusy && nowBusy.busy === 'true' && near( nowBusy.dot, 12, 1 ) && nowBusy.inside && near( nowBusy.h, 56 ), `PDP-09 [en 1440] Buy Now busy: the loader inside the 56 high button while the store adds (${ JSON.stringify( nowBusy ) })` );
			const href = await bpage.locator( '.mizzey-buy__now' ).getAttribute( 'href' );
			await bpage.goto( new URL( href, plan.urls.bare ).href, { waitUntil: 'load', timeout: TIMEOUT } ).catch( () => {} );
			check( bpage.url().startsWith( plan.checkout ), `PDP-09 [en 1440] Buy Now lands on checkout (${ bpage.url() })` );
		}
		cart = await page.evaluate( async ( u ) => ( await fetch( u + 'cart', { credentials: 'same-origin' } ) ).json(), plan.store ).catch( () => null );
		wishlist = await page.evaluate( async ( u ) => ( await ( await fetch( u, { credentials: 'same-origin' } ) ).json() ).items.map( ( i ) => i.canonical ), plan.store.replace( 'wc/store/v1/', 'mizzey/v1/wishlist' ) ).catch( () => null );

		// ==== English, 390: the variable product and the buy bar ===================================================
		const phoneCtx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 } );
		const phone = await open( phoneCtx, plan.urls.var );
		const p = await measure( phone );
		check( p !== null, 'AC-11 [en 390] the designed buying area on a phone' );
		if ( p ) {
			await shot( phone, 'en-390', true );
			const lcp = await phone.evaluate( () => window.__lcp );
			check( lcp === 'gallery-first', `K-6 [en 390] the largest contentful paint is the gallery's first photo (${ lcp })` );
			check( p.back && near( p.back.h, 44 ) && near( p.back.x, 12, 1 ) && p.back.font.startsWith( '16px/24px 500' ) && ! p.crumbs, `[en 390] the back link: 44 high, 16/24 Medium, no breadcrumb (${ p.back && [ p.back.h, p.back.x, p.back.font ] }, ${ !! p.crumbs })` );
			check( p.stage && near( p.stage.w, 358 ) && near( p.stage.h, 358 ) && near( p.stage.x, 16 ) && p.stage.radius === '24px' && near( p.stage.y - p.back.b, 0 ), `PDP-01 [en 390] the stage 358 square at 16, radius 24, right under the back link (${ p.stage && [ p.stage.w, p.stage.h, p.stage.x, p.stage.radius, Math.round( p.stage.y - p.back.b ) ] })` );
			check( p.thumbs.length === 3 && p.thumbs.every( ( t ) => near( t.w, 64 ) && near( t.h, 64 ) ) && near( p.thumbs[ 0 ].y - p.stage.b, 8 ) && p.arrows.length === 0, `PDP-01 [en 390] thumbnails 64, 8 under the stage, no arrows on a phone (${ JSON.stringify( p.thumbs.map( ( t ) => t.w ) ) })` );
			check( p.brand && near( p.brand.y - p.thumbs[ 0 ].b, 8 ) && near( p.brand.h, 44 ), `[en 390] the brand link, 44 high, 8 under the thumbnails (${ p.brand && [ Math.round( p.brand.y - p.thumbs[ 0 ].b ), p.brand.h ] })` );
			check( p.name && p.name.font === '19px/27px 500', `PDP-03 [en 390] the name 19/27 Medium (${ p.name && p.name.font })` );
			check( p.price && p.price.font === '28px/35px 700', `PDP-04 [en 390] the price 28/35 Bold (${ p.price && p.price.font })` );
			check( p.qty && p.qtyLabel && /Quantity/.test( p.qtyLabel.text ) && p.stepper && near( p.stepper.w, 140 ) && near( p.stepper.h, 52 ) && near( p.stepper.r, 374 ) && near( p.qty.y - p.groups[ 1 ].b, 16 ), `PDP-07 [en 390] the quantity row: "Quantity" and the 140 by 52 stepper at the end, 16 under the options (${ p.stepper && [ p.stepper.w, p.stepper.h, p.stepper.r, Math.round( p.qty.y - p.groups[ 1 ].b ) ] })` );
			check( p.add && near( p.add.h, 52 ) && near( p.add.w, 358 ) && near( p.add.y - p.qty.b, 16 ), `PDP-08 [en 390] Add to Cart 52 high, full width, 16 under the quantity (${ p.add && [ p.add.h, p.add.w, Math.round( p.add.y - p.qty.b ) ] })` );
			check( p.now && near( p.now.h, 52 ) && near( p.now.y - p.add.b, 12 ) && near( p.links.y - p.now.b, 8 ) && near( p.links.h, 44 ), `PDP-09 [en 390] Buy Now 52 high 12 under, the links 44 high 8 under (${ p.now && [ p.now.h, Math.round( p.now.y - p.add.b ), Math.round( p.links.y - p.now.b ) ] })` );
			check( p.delivery && near( p.delivery.y - p.links.b, 24 ) && p.features && near( p.features.y - p.delivery.b, 32 ) && p.featuresTitle.font === '19px/25px 900', `[en 390] delivery 24 under, key features 32 under, its title 19/25 Black (${ p.delivery && [ Math.round( p.delivery.y - p.links.b ), Math.round( p.features.y - p.delivery.b ), p.featuresTitle && p.featuresTitle.font ] })` );
			check( p.options.every( ( o ) => near( o.h, 44 ) ) && near( p.groups[ 0 ].y - p.stock.b, 16 ), `PDP-05 [en 390] options 44 high, the first group 16 under the stock line (${ Math.round( p.groups[ 0 ].y - p.stock.b ) })` );

			// The buy bar: away while Add to Cart is on screen, there once it scrolls away.
			const bar = () => phone.evaluate( () => {
				const el = document.querySelector( '.mizzey-buy-bar' );
				if ( ! el ) {
					return null;
				}
				const r = el.getBoundingClientRect();
				const btn = el.querySelector( '.mizzey-buy-bar__add' );
				const price = el.querySelector( '.mizzey-buy-bar__price' );
				const s = getComputedStyle( el );
				return {
					shown: el.classList.contains( 'is-shown' ) && r.top < window.innerHeight - 1, top: Math.round( r.top ), h: Math.round( r.height ), bottom: Math.round( window.innerHeight - r.bottom ),
					pad: [ s.paddingTop, s.paddingInlineStart ].join( ' ' ), button: btn ? Math.round( btn.getBoundingClientRect().height ) : null,
					price: price ? { text: price.textContent.trim(), font: `${ getComputedStyle( price ).fontSize }/${ getComputedStyle( price ).lineHeight } ${ getComputedStyle( price ).fontWeight }` } : null,
					note: ( el.querySelector( '.mizzey-buy-bar__note' ) || {} ).textContent || '',
				};
			} );
			const rest = await bar();
			check( rest && ! rest.shown, `[en 390] the buy bar is away while Add to Cart is on screen (${ JSON.stringify( rest ) })` );
			await phone.evaluate( () => window.scrollTo( 0, document.querySelector( '.mizzey-buy__features' ).getBoundingClientRect().top + window.scrollY - 200 ) );
			await phone.waitForTimeout( 700 );
			const up = await bar();
			check( up && up.shown && up.h === 80 && up.bottom === 0 && up.pad === '14px 16px' && up.button === 52 && up.price && up.price.font === '16px/24px 700' && /2,600/.test( up.price.text ) && /1 tub, Vanilla/.test( up.note ), `[en 390] the buy bar once Add to Cart scrolls away: 80 high at the foot, padding 14 16, the button 52, the price 16/24 Bold and the option (${ JSON.stringify( up ) })` );
			await shot( phone, 'en-390-bar' );
			const barAdd = await heldAdd( phone, '.mizzey-buy-bar__add', 'en-390-bar' );
			check( barAdd.busy && barAdd.busy.busy === 'true' && near( barAdd.busy.dot, 12, 1 ) && barAdd.busy.inside && near( barAdd.busyWidth, barAdd.width, 0.5 ) && barAdd.earlyEvents.length === 0, `[en 390] the bar busy: the loader inside its button, the width kept, no count claimed (${ JSON.stringify( barAdd.busy ) })` );
			const added = await phone.evaluate( () => ( { cart: !! document.querySelector( '.mizzey-buy-bar__cart' ) && document.querySelector( '.mizzey-buy-bar__cart' ).getClientRects().length > 0 } ) );
			check( barAdd.status === 201 && barAdd.done.tick && /added/i.test( barAdd.done.text ) && added.cart && barAdd.events.length === 1, `[en 390] the bar added: the tick and "Added", "View cart" under the price (${ JSON.stringify( barAdd.done ) }, ${ JSON.stringify( added ) })` );
			// The bar never covers a notice or the consent banner. A refused add brings a notice on a phone.
			await phone.route( '**/wc/store/v1/cart/add-item**', ( r ) => r.fulfill( { status: 400, contentType: 'application/json', body: JSON.stringify( { code: 'x', message: 'Only 1 left in stock.' } ) } ) );
			await phone.locator( '.mizzey-buy-bar__add' ).click();
			await phone.waitForTimeout( 800 );
			await phone.unroute( '**/wc/store/v1/cart/add-item**' );
			await shot( phone, 'en-390-bar-notice' );
			const covers = await phone.evaluate( () => {
				const barBox = document.querySelector( '.mizzey-buy-bar' ).getBoundingClientRect();
				const overlap = ( el ) => {
					if ( ! el || ! el.getClientRects().length ) {
						return null;
					}
					const r = el.getBoundingClientRect();
					return r.bottom > barBox.top + 0.5 && r.top < barBox.bottom - 0.5 && document.querySelector( '.mizzey-buy-bar' ).classList.contains( 'is-shown' );
				};
				const notice = overlap( document.querySelector( '.mizzey-buy-toasts .mizzey-notice' ) );
				const consent = document.getElementById( 'mizzey-consent' );
				if ( consent ) {
					consent.hidden = false;
				}
				return { notice, consent: consent ? 'pending' : 'absent' };
			} );
			await phone.waitForTimeout( 400 );
			const consentOverlap = await phone.evaluate( () => {
				const el = document.getElementById( 'mizzey-consent' );
				const b = document.querySelector( '.mizzey-buy-bar' );
				if ( ! el ) {
					return null;
				}
				const r = el.getBoundingClientRect();
				const q = b.getBoundingClientRect();
				const shown = b.classList.contains( 'is-shown' ) && q.top < window.innerHeight - 1;
				return shown && r.bottom > q.top + 0.5 && r.top < q.bottom - 0.5;
			} );
			await shot( phone, 'en-390-consent' );
			check( covers.notice === false && consentOverlap === false, `[en 390] the buy bar never covers the notice or the consent banner (${ JSON.stringify( covers ) }, consent ${ consentOverlap })` );
			await phone.evaluate( () => {
				const el = document.getElementById( 'mizzey-consent' );
				if ( el ) {
					el.hidden = true;
				}
				window.scrollTo( 0, 0 );
			} );
			// Zoom on a phone: the whole screen.
			await phone.locator( '.mizzey-gallery__zoom' ).click();
			await phone.waitForTimeout( 600 );
			const pz = await phone.evaluate( () => {
				const panel = document.querySelector( 'dialog.mizzey-zoom[open] .mizzey-zoom__panel' );
				return panel ? [ Math.round( panel.getBoundingClientRect().width ), Math.round( panel.getBoundingClientRect().height ) ] : null;
			} );
			check( pz && pz[ 0 ] === 390 && pz[ 1 ] === 844, `PDP-01 [en 390] zoom fills the phone's screen (${ JSON.stringify( pz ) })` );
			await shot( phone, 'en-390-zoom' );
			await phone.keyboard.press( 'Escape' );
			await axe( phone, 'en 390 variable' );
			check( phone.errors.length === 0, `[en 390] no script error (${ JSON.stringify( phone.errors.slice( 0, 3 ) ) })` );
		}
		const psold = await open( phoneCtx, plan.urls.sold );
		await psold.evaluate( () => window.scrollTo( 0, 2000 ) );
		await psold.waitForTimeout( 600 );
		const soldBar = await psold.evaluate( () => {
			const btn = document.querySelector( '.mizzey-buy-bar__add' );
			return btn ? { text: btn.textContent.trim(), disabled: btn.disabled } : null;
		} );
		check( soldBar && soldBar.disabled && /out of stock/i.test( soldBar.text ), `PDP-22 [en 390] the bar of a sold-out product: its button off, "Out of stock" (${ JSON.stringify( soldBar ) })` );
		const pall = await open( phoneCtx, plan.urls.allout );
		await pall.evaluate( () => window.scrollTo( 0, 2000 ) );
		await pall.waitForTimeout( 600 );
		const allBar = await pall.evaluate( () => {
			const btn = document.querySelector( '.mizzey-buy-bar__add' );
			return btn ? { text: btn.textContent.trim(), disabled: btn.disabled, note: document.querySelector( '.mizzey-buy-bar__note' ).textContent } : null;
		} );
		check( allBar && allBar.disabled && /out of stock/i.test( allBar.text ) && ! /choose/i.test( allBar.note ), `PDP-22 [en 390] the bar of a product sold out in every variation: off, "Out of stock", no "choose" (${ JSON.stringify( allBar ) })` );

		// ==== Arabic, 390 and 1440 ================================================================================
		const arPhone = await open( phoneCtx, plan.urls.var_ar );
		const a = await measure( arPhone );
		check( a && a.dir === 'rtl', `FIX-04a [ar 390] the buying area reads right to left (${ a && a.dir })` );
		if ( a ) {
			await shot( arPhone, 'ar-390', true );
			check( near( a.stage.r, 374 ) && a.steps.length === 2 && a.steps[ 0 ].x > a.steps[ 1 ].x, `[ar 390] mirrored: the stage at the right margin, the stepper's minus at the start (right) (${ [ a.stage.r, a.steps.map( ( x ) => Math.round( x.x ) ) ] })` );
			check( /[؀-ۿ]/.test( a.stock.text ) && /[؀-ۿ]/.test( a.add.text ), `NFR-04a [ar 390] the words are Arabic: "${ a.stock.text }", "${ a.add.text }"` );
			const chevron = await arPhone.evaluate( () => {
				const svg = document.querySelector( '.mizzey-buy__back svg' );
				return svg ? getComputedStyle( svg ).scale + ' ' + getComputedStyle( svg ).transform : null;
			} );
			check( chevron && /-1/.test( chevron ), `[ar 390] the back chevron points right (${ chevron })` );
			check( arPhone.errors.length === 0, `[ar 390] no script error (${ JSON.stringify( arPhone.errors.slice( 0, 3 ) ) })` );
		}
		const arDesk = await open( desk, plan.urls.simple_ar );
		const ad = await measure( arDesk );
		check( ad && ad.dir === 'rtl' && ad.stage.x > ad.summary.x && near( ad.stage.r, 1356, 2 ), `[ar 1440] the gallery on the start (right) side (${ ad && [ ad.stage.x, ad.summary.x, ad.stage.r ] })` );
		if ( ad ) {
			await shot( arDesk, 'ar-1440', true );
		}

		// ==== Reduced motion: the photos change without a fade ===================================================
		const still = await browser.newContext( { viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' } );
		const stillPage = await open( still, plan.urls.simple );
		const motion = await stillPage.evaluate( () => {
			const slide = document.querySelector( '.mizzey-gallery__slide' );
			return slide ? getComputedStyle( slide ).transitionDuration : null;
		} );
		check( motion !== null && motion.split( ',' ).every( ( t ) => parseFloat( t ) === 0 ), `K-5 under reduced motion the photos change without a fade (${ motion })` );
	} catch ( e ) {
		check( false, `the browser run stopped: ${ e.message.split( '\n' ).slice( 0, 4 ).join( ' ' ) }` );
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks, cart, wishlist, shots } ) );
} )();

#!/usr/bin/env node
/**
 * The category listing in a real browser (spec 066, slice 6; AC-7). Driven by t111, which makes the fixture and passes
 * a JSON plan as the only argument; prints one JSON object: { checks: [{ ok, what }] }.
 *
 * At 1440 wide in English and Arabic: the page against C1-Category-Desktop's numbers; each filter (a child category,
 * a brand, the attribute, availability, the price by dragging a handle and by typing in the two fields) answers with
 * the server's products; each sort orders them; one active filter is removed on its own; page 2; back from a product
 * keeps the filters and the sort; the loading state (skeleton cards with the breathing ZZ, the cards' place kept) while
 * the server answers; a list with no products (a line icon in a disc, never the ZZ) and an empty category with its
 * suggestions.
 *
 * At 390 wide in English and Arabic: the page against C1-Category-Phone's numbers; the filters drawer comes out of the
 * start edge (the right in Arabic), traps focus and gives it back; its "Show N products" button stays pinned at the
 * bottom while the groups scroll under it; it is the drawer's one count, it shows the i-dot loader until the server
 * answers, and its number is the server's answer for every chosen filter together; the sort sheet; removing a filter.
 *
 * Without JavaScript the filters still work as a form, and the sort as links.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const T = 120000;
const near = ( a, b, tolerance = 1.5 ) => Math.abs( a - b ) <= tolerance;
const lang = ( l ) => ( 'ar' === l ? 'AR' : 'EN' );

/* ---- What the server should answer, from the fixture ------------------------------------------------------------ */

const P = plan.products;
const slug = ( key, l ) => ( plan.slugs[ key ] ? plan.slugs[ key ][ l ] : '' );
const enSlug = ( key ) => slug( key, 'en' );
function matches( p, f ) {
	if ( f.child && p.child !== enSlug( f.child ) ) {
		return false;
	}
	if ( f.brands && ! f.brands.map( enSlug ).includes( p.brand ) ) {
		return false;
	}
	if ( f.form && p.form !== enSlug( f.form ) ) {
		return false;
	}
	if ( f.instock && ! p.instock ) {
		return false;
	}
	if ( undefined !== f.min && p.price < f.min ) {
		return false;
	}
	if ( undefined !== f.max && p.price > f.max ) {
		return false;
	}
	return true;
}
const expected = ( f ) => P.filter( ( p ) => matches( p, f ) );
const ORDERS = {
	menu_order: [ ...P ].sort( ( a, b ) => a.name.localeCompare( b.name ) ),
	date: [ ...P ].sort( ( a, b ) => b.order - a.order ),
	price: [ ...P ].sort( ( a, b ) => a.price - b.price ),
	'price-desc': [ ...P ].sort( ( a, b ) => b.price - a.price ),
	popularity: [ ...P ].sort( ( a, b ) => b.sales - a.sales ),
};
const first = ( list, n = 12 ) => list.slice( 0, n ).map( ( p ) => p.name );
const sameSet = ( a, b ) => a.length === b.length && [ ...a ].sort().join( '|' ) === [ ...b ].sort().join( '|' );

/* ---- Reading the page ------------------------------------------------------------------------------------------ */

const cardNames = ( page ) => page.$$eval( '.mizzey-listing__results .mizzey-card:not(.mizzey-card--skeleton) .mizzey-card__name a', ( a ) => a.map( ( x ) => x.textContent.trim() ) );
const params = ( page ) => Object.fromEntries( new URL( page.url() ).searchParams );
const measure = ( page, selector ) => page.$eval( selector, ( el ) => {
	const r = el.getBoundingClientRect();
	const cs = getComputedStyle( el );
	return { x: r.x, y: r.y + scrollY, w: r.width, h: r.height, right: r.right, bottom: r.bottom + scrollY, top: r.top, fs: parseFloat( cs.fontSize ), fw: cs.fontWeight, radius: cs.borderTopLeftRadius, radiusEnd: cs.borderTopRightRadius, bg: cs.backgroundColor, pad: cs.padding, display: cs.display };
} );
/** An element's box and look; asked again once when the page's context was replaced under the question. */
const box = async ( page, selector ) => {
	try {
		return await measure( page, selector );
	} catch ( e ) {
		if ( ! /context|destroyed|navigat/i.test( String( e ) ) ) {
			return null;
		}
		await page.waitForLoadState( 'domcontentloaded' ).catch( () => {} );
		return measure( page, selector ).catch( () => null );
	}
};
const noSideScroll = ( page ) => page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 );

async function settle( page ) {
	await page.waitForLoadState( 'domcontentloaded', { timeout: T } );
	await page.waitForSelector( '.mizzey-listing.is-enhanced, .mizzey-listing', { timeout: T } );
	await page.waitForTimeout( 400 );
}

/** Wait for the navigation a control starts, then for the page to settle. */
async function navigates( page, action ) {
	const before = page.url();
	await Promise.all( [
		page.waitForURL( ( u ) => u.toString() !== before, { timeout: T } ),
		action(),
	] );
	await settle( page );
}

/** axe-core from the copy `npx` has cached, or AXE_PATH (test tooling, as product-card.cjs finds it). */
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

/** K-5: no serious or critical axe-core finding in a part of the page. */
async function axe( page, selector, what ) {
	if ( ! AXE ) {
		check( false, `${ what }: axe-core was not found (set AXE_PATH)` );
		return;
	}
	await page.addScriptTag( { path: AXE } );
	const found = await page.evaluate( async ( s ) => ( await window.axe.run( s, { resultTypes: [ 'violations' ] } ) ).violations
		.filter( ( v ) => 'serious' === v.impact || 'critical' === v.impact ).map( ( v ) => `${ v.id }: ${ v.nodes.length }` ), selector );
	check( 0 === found.length, `${ what } K-5: no serious or critical axe-core finding (${ JSON.stringify( found ) })` );
}

/** Tick an option of a filter group, opening the group first when it is closed, as a person does. */
async function tick( page, group, value, scope = '' ) {
	const details = page.locator( `${ scope } .mizzey-filter[data-group="${ group }"]` ).first();
	if ( ! ( await details.evaluate( ( d ) => d.open ) ) ) {
		await details.locator( 'summary' ).click();
	}
	await details.locator( `label:has(input[value="${ value }"])` ).click();
}

/** A context whose visitor has already answered the consent banner, so the bar covers nothing (slice 3 tests it). */
async function context( browser, options ) {
	const ctx = await browser.newContext( options );
	await ctx.addCookies( [ { name: 'mizzey_consent', value: 'denied', url: new URL( plan.url.en ).origin } ] );
	return ctx;
}

/** A screenshot of the whole page; of the window only for a state a full-page capture would disturb (a drag). */
const shot = async ( page, name, fullPage = true ) => {
	if ( plan.shots ) {
		await page.screenshot( { path: path.join( plan.shots, `s6-${ name }.png` ), fullPage } ).catch( () => {} );
	}
};

/* ---- Desktop ---------------------------------------------------------------------------------------------------- */

async function desktop( browser, l ) {
	const L = `${ lang( l ) } 1440`;
	const ctx = await context( browser, { viewport: { width: 1440, height: 900 }, locale: 'ar' === l ? 'ar' : 'en' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	const base = plan.url[ l ];
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );

	// The page and its parts.
	check( await page.$( '.mizzey-listing' ), `${ L }: the category page is the designed listing` );
	check( await page.$( '.mizzey-listing nav.woocommerce-breadcrumb a' ), `${ L } PLP-21: the breadcrumb links home` );
	const title = await page.$eval( 'h1.mizzey-listing__title', ( h ) => h.textContent.trim() ).catch( () => '' );
	check( title === plan.names.category[ l ], `${ L } PLP-20: the banner card carries the category's name (${ title })` );
	check( await page.$( '.mizzey-listing__description' ), `${ L } PLP-20, SSC-04: the description is on the banner card` );
	check( await page.$( '.mizzey-listing__tile img' ), `${ L } SSC-04: the banner image sits in its tile` );
	const chips = await page.$$eval( '.mizzey-listing__cats .mizzey-chip', ( c ) => c.length ).catch( () => 0 );
	check( 3 === chips, `${ L }: the chips row holds All and the two child categories (${ chips })` );
	const count = await page.$eval( '.mizzey-listing__count', ( c ) => c.textContent ).catch( () => '' );
	check( /14/.test( count ), `${ L } PLP-17: the result count says 14 (${ count.trim() })` );
	const groups = await page.$$eval( '.mizzey-filters .mizzey-filter', ( g ) => g.map( ( x ) => x.dataset.group ) ).catch( () => [] );
	check( [ 'categories', 'brands', 'price', plan.attribute ].every( ( g ) => groups.includes( g ) ), `${ L }: the filter panel shows category, brand, price and the attribute (${ groups.join( ', ' ) })` );
	check( await page.$( '.mizzey-filters input[name="filter_stock_status"]' ), `${ L } PLP-10: the panel carries "In stock only"` );
	check( 12 === ( await cardNames( page ) ).length, `${ L } PLP-16: the first page shows 12 cards` );
	check( await noSideScroll( page ), `${ L }: the page never scrolls sideways` );
	await shot( page, `${ l }-1440` );
	await axe( page, '.mizzey-listing', `${ L }: the listing` );
	await page.click( '.mizzey-listing__sort .mizzey-select__button' );
	await page.waitForTimeout( 300 );
	await axe( page, '.mizzey-listing__sort', `${ L }: the open sort list` );
	await shot( page, `${ l }-1440-sort` );
	await page.keyboard.press( 'Escape' );

	// The board's numbers, measured on the filtered state the board draws (a price chip applied).
	await page.goto( `${ base }?min_price=300&max_price=1700`, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const m = {
		crumbs: await box( page, '.mizzey-listing__crumbs' ),
		banner: await box( page, '.mizzey-listing__banner' ),
		title: await box( page, '.mizzey-listing__title' ),
		tile: await box( page, '.mizzey-listing__tile' ),
		cats: await box( page, '.mizzey-listing__cats .mizzey-chip' ),
		aside: await box( page, '.mizzey-filters__panel' ),
		toolbar: await box( page, '.mizzey-listing__toolbar' ),
		sort: await box( page, '.mizzey-listing__sort .mizzey-select__button' ),
		applied: await box( page, '.mizzey-listing__applied' ),
		chip: await box( page, '.mizzey-listing__applied .mizzey-chip--applied' ),
		grid: await box( page, '.mizzey-listing__results .mizzey-products__list' ),
		cards: await page.$$eval( '.mizzey-listing__results .mizzey-products__item', ( items ) => items.slice( 0, 5 ).map( ( i ) => { const r = i.getBoundingClientRect(); return { x: r.x, y: r.y + scrollY, w: r.width, h: r.height }; } ) ),
		group: await box( page, '.mizzey-filter__name' ),
		checks: await page.$$eval( '.mizzey-filter[data-group="categories"] .mizzey-check', ( c ) => c.map( ( x ) => x.getBoundingClientRect().y ) ),
	};
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	m.paging = await box( page, '.mizzey-listing .mizzey-pagination' );
	m.grid2 = await box( page, '.mizzey-listing__results .mizzey-products__list' );
	m.showing = await box( page, '.mizzey-listing__showing' );
	const step = ( a ) => ( a && a.length > 1 ? Math.round( a[ 1 ] - a[ 0 ] ) : 0 );
	check( m.crumbs && near( m.crumbs.h, 44 ), `${ L } K-1: the breadcrumb row is 44 high (board 44; built ${ m.crumbs && m.crumbs.h })` );
	check( m.banner && near( m.banner.h, 176 ) && near( m.banner.w, 1272 ) && '24px' === m.banner.radius && 'rgb(238, 241, 250)' === m.banner.bg && near( m.banner.y, m.crumbs.bottom ), `${ L } K-1: the banner card is 1272 by 176, radius 24, Indigo 50, right under the breadcrumb (board; built ${ m.banner && [ m.banner.w, m.banner.h, m.banner.radius, m.banner.bg, m.banner.y - m.crumbs.bottom ].join( ' ' ) })` );
	check( m.title && near( m.title.fs, 'ar' === l ? 34 : 31 ) && '900' === m.title.fw, `${ L } K-1: the category name is the h2 role in Black, 31 (34 in the Arabic scale) (board 31/900; built ${ m.title && m.title.fs }/${ m.title && m.title.fw })` );
	check( m.tile && near( m.tile.w, 128 ) && near( m.tile.h, 128 ) && '20px' === m.tile.radius, `${ L } K-1: the image tile is 128 square, radius 20 (built ${ m.tile && [ m.tile.w, m.tile.h, m.tile.radius ].join( ' ' ) })` );
	check( m.cats && near( m.cats.y - m.banner.bottom, 24 ) && near( m.cats.h, 36 ), `${ L } K-1: the chips row is 24 under the banner, chips 36 high (built ${ m.cats && Math.round( m.cats.y - m.banner.bottom ) }, ${ m.cats && m.cats.h })` );
	check( m.aside && near( m.aside.w, 300 ) && near( m.aside.y - ( m.cats.y + m.cats.h ), 24 ) && '20px' === m.aside.radius, `${ L } K-1: the filter column is 300 wide, 24 under the chips, radius 20 (built ${ m.aside && [ m.aside.w, Math.round( m.aside.y - m.cats.y - m.cats.h ), m.aside.radius ].join( ' ' ) })` );
	const startEdge = ( b ) => ( 'ar' === l ? 1440 - b.right : b.x );
	check( m.aside && near( startEdge( m.aside ), 84 ) && m.grid && near( startEdge( m.grid ), 408 ), `${ L } K-1: the column starts at 84 and the results at 408, 24 apart (built ${ m.aside && startEdge( m.aside ) }, ${ m.grid && startEdge( m.grid ) })` );
	check( m.sort && near( m.sort.h, 44 ) && m.toolbar && near( m.toolbar.y, m.aside.y ) && near( m.toolbar.h, 48 ), `${ L } K-1: the toolbar row is 48 high, level with the column, the sort control 44 high (built ${ m.toolbar && Math.round( m.toolbar.y - m.aside.y ) }, ${ m.toolbar && m.toolbar.h }, ${ m.sort && m.sort.h })` );
	check( m.applied && near( m.applied.y - m.toolbar.bottom, 12 ) && near( m.applied.h, 36 ) && m.chip && near( m.chip.h, 36 ), `${ L } K-1: the active filters row is 12 under the toolbar and 36 high, as its chips (built ${ m.applied && Math.round( m.applied.y - m.toolbar.bottom ) }, ${ m.applied && m.applied.h }, ${ m.chip && m.chip.h })` );
	check( m.grid && near( m.grid.y - m.applied.bottom, 16 ), `${ L } K-1: the grid is 16 under the active filters row (built ${ m.grid && Math.round( m.grid.y - m.applied.bottom ) })` );
	const head = await box( page, '.mizzey-filters__title' );
	check( 'ar' === l || ( head && near( head.fs, 20 ) && '900' === head.fw ), `${ L } K-1: "Filters" is 20 Black (board 20/900; built ${ head && head.fs }/${ head && head.fw })` );
	check( m.cards.length >= 5 && near( m.cards[ 0 ].w, 219 ) && near( Math.abs( m.cards[ 1 ].x - m.cards[ 0 ].x ) - m.cards[ 0 ].w, 24 ) && near( m.cards[ 4 ].y - m.cards[ 0 ].y - m.cards[ 0 ].h, 24 ), `${ L } K-1: four cards 219 wide in a row, 24 apart both ways (built ${ m.cards[ 0 ] && Math.round( m.cards[ 0 ].w ) }, ${ m.cards[ 1 ] && Math.round( Math.abs( m.cards[ 1 ].x - m.cards[ 0 ].x ) - m.cards[ 0 ].w ) }, ${ m.cards[ 4 ] && Math.round( m.cards[ 4 ].y - m.cards[ 0 ].y - m.cards[ 0 ].h ) })` );
	check( m.group && near( m.group.fs, 16 ) && '900' === m.group.fw && 44 === step( m.checks ), `${ L } K-1: a group's name is 16 Black, its rows 44 apart (built ${ m.group && m.group.fs }/${ m.group && m.group.fw }, ${ step( m.checks ) })` );
	check( m.paging && m.grid2 && near( m.paging.y - m.grid2.bottom, 40 ) && near( m.paging.h, 44 ), `${ L } K-1: the paging sits 40 under the grid, 44 high (built ${ m.paging && Math.round( m.paging.y - m.grid2.bottom ) }, ${ m.paging && m.paging.h })` );
	check( m.showing && m.paging && near( m.showing.y - m.paging.bottom, 12 ), `${ L } K-1: "Showing" is 12 under the paging (built ${ m.showing && Math.round( m.showing.y - m.paging.bottom ) })` );
	const footer = await box( page, '.mizzey-footer' );
	check( footer && m.showing && near( footer.y - m.showing.bottom, 72 ), `${ L } K-1: the footer starts 72 under "Showing" (board 72; built ${ footer && m.showing && Math.round( footer.y - m.showing.bottom ) })` );

	// PLP-06: a child category.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await navigates( page, () => tick( page, 'categories', slug( 'minerals', l ) ) );
	let names = await cardNames( page );
	check( params( page ).categories === slug( 'minerals', l ) && sameSet( names, expected( { child: 'minerals' } ).map( ( p ) => p.name ) ), `${ L } PLP-06: Minerals lists its five products (${ names.length })` );

	const ofTotal = await page.$eval( '.mizzey-listing__count', ( c ) => c.textContent.replace( /\s+/g, ' ' ).trim() ).catch( () => '' );
	check( ofTotal.includes( '5' ) && ofTotal.includes( String( P.length ) ), `${ L } PLP-17: a filtered count reads "5 of ${ P.length } products", the category's whole count after "of" (${ ofTotal })` );

	// PLP-07 with it, then PLP-18: remove the category on its own.
	await navigates( page, () => tick( page, 'brands', slug( 'lumen', l ) ) );
	names = await cardNames( page );
	check( sameSet( names, expected( { child: 'minerals', brands: [ 'lumen' ] } ).map( ( p ) => p.name ) ), `${ L } PLP-07: and Lumen narrows it to Lumen's (${ names.length })` );
	const applied = await page.$$eval( '.mizzey-listing__applied .mizzey-chip--applied', ( c ) => c.length );
	check( 2 === applied, `${ L } PLP-18: both active filters show as chips (${ applied })` );
	await navigates( page, () => page.click( `.mizzey-listing__applied a.mizzey-chip--applied[data-param="categories"]` ) );
	names = await cardNames( page );
	check( ! params( page ).categories && params( page ).brands === slug( 'lumen', l ) && sameSet( names, expected( { brands: [ 'lumen' ] } ).map( ( p ) => p.name ) ), `${ L } PLP-18: removing the category chip keeps the brand (${ names.length } of Lumen's ${ expected( { brands: [ 'lumen' ] } ).length })` );

	// PLP-09 the attribute; PLP-10 availability.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await navigates( page, () => tick( page, plan.attribute, slug( 'capsules', l ) ) );
	names = await cardNames( page );
	check( sameSet( names, expected( { form: 'capsules' } ).map( ( p ) => p.name ) ), `${ L } PLP-09: the attribute Capsules lists its eight products (${ names.length })` );
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await navigates( page, () => page.click( '.mizzey-filters label:has(input[name="filter_stock_status"])' ) );
	const instock = expected( { instock: true } );
	const count2 = await page.$eval( '.mizzey-listing__count', ( c ) => c.textContent ).catch( () => '' );
	check( 'instock' === params( page ).filter_stock_status && count2.includes( String( instock.length ) ), `${ L } PLP-10: "In stock only" leaves the ${ instock.length } in stock (${ count2.trim() })` );

	// "On sale", as the board draws it beside "In stock only".
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await navigates( page, () => page.click( '.mizzey-filters label:has(input[name="on_sale"])' ) );
	names = await cardNames( page );
	check( '1' === params( page ).on_sale && sameSet( names, P.filter( ( p ) => p.sale ).map( ( p ) => p.name ) ), `${ L }: "On sale" leaves the products on sale (${ names.join( ', ' ) })` );

	// PLP-08 by dragging the high handle.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await page.locator( '.mizzey-price__track' ).scrollIntoViewIfNeeded();
	const tb = await page.locator( '.mizzey-price__track' ).boundingBox();
	const track = tb ? { x: tb.x, y: tb.y, w: tb.width, h: tb.height } : null;
	if ( track ) {
		const from = 'ar' === l ? track.x + 12 : track.x + track.w - 12;
		const to = 'ar' === l ? track.x + track.w * 0.55 : track.x + track.w * 0.45;
		const y = track.y + track.h / 2;
		await page.mouse.move( from, y );
		await page.mouse.down();
		await page.mouse.move( to, y, { steps: 12 } );
		const bubble = await page.$eval( '.mizzey-price__bubble', ( b ) => ! b.hidden && getComputedStyle( b ).display !== 'none' && b.textContent.trim() ).catch( () => '' );
		check( bubble, `${ L } PLP-08: a price bubble shows while dragging (${ bubble })` );
		const counting = await page.$eval( '.mizzey-price__count', ( c ) => ( { waiting: c.classList.contains( 'is-waiting' ), loader: getComputedStyle( c.querySelector( '.mizzey-loader' ) ).display !== 'none', dots: [ ...c.querySelectorAll( '.mizzey-loader__dot' ) ].map( ( d ) => Math.round( parseFloat( getComputedStyle( d ).width ) ) ) } ) ).catch( () => null );
		check( counting && counting.waiting && counting.loader && counting.dots.every( ( d ) => near( d, 7 ) ), `${ L } motion: while a handle moves, the count line shows the i-dot loader ("Updating the count"), dots 7 wide as the board, until the server answers (dots ${ counting && counting.dots.join( '/' ) })` );
		await shot( page, `${ l }-1440-dragging`, false );
		await navigates( page, () => page.mouse.up() );
		const max = Number( params( page ).max_price );
		names = await cardNames( page );
		check( max > 100 && max < 1700 && sameSet( names, first( expected( { max } ).sort( ( a, b ) => a.name.localeCompare( b.name ) ) ) ), `${ L } PLP-08: dragging the high handle to ${ max } lists the products up to it (${ names.length })` );
	} else {
		check( false, `${ L } PLP-08: the price slider has a track and a high handle` );
	}

	// PLP-08 by typing: the range line opens the two fields.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await page.click( '.mizzey-price__range' ).catch( () => {} );
	const fieldsOpen = await page.$eval( '.mizzey-price__fields', ( f ) => ! f.hidden ).catch( () => false );
	check( fieldsOpen, `${ L } PLP-08: the range line opens the two fields for typing` );
	if ( fieldsOpen ) {
		await page.fill( '.mizzey-price__fields input[data-end="min"]', '300' );
		await page.fill( '.mizzey-price__fields input[data-end="max"]', '900' );
		await navigates( page, () => page.press( '.mizzey-price__fields input[data-end="max"]', 'Enter' ) );
		names = await cardNames( page );
		check( '300' === params( page ).min_price && '900' === params( page ).max_price && sameSet( names, expected( { min: 300, max: 900 } ).map( ( p ) => p.name ) ), `${ L } PLP-08: typing 300 and 900 lists the products in between (${ names.length })` );
	}

	// PLP-12 to PLP-15: each sort, through the select.
	for ( const [ value, list ] of Object.entries( ORDERS ) ) {
		await page.goto( 'menu_order' === value ? `${ base }?orderby=date` : base, { waitUntil: 'domcontentloaded', timeout: T } );
		await settle( page );
		await page.click( '.mizzey-listing__sort .mizzey-select__button' );
		await navigates( page, () => page.click( `.mizzey-listing__sort [role="option"][data-value="${ value }"]` ) );
		names = await cardNames( page );
		check( names.join( '|' ) === first( list ).join( '|' ), `${ L } ${ { menu_order: 'PLP-15 relevance', date: 'PLP-12 newest', price: 'PLP-13 price up', 'price-desc': 'PLP-13 price down', popularity: 'PLP-14 best selling' }[ value ] }: the cards come in the server's order (${ names.slice( 0, 3 ).join( ', ' ) })` );
	}

	// PLP-16: page 2, and the line under the paging.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await navigates( page, () => page.click( '.mizzey-listing .mizzey-pagination a[href*="/page/2"]' ) );
	names = await cardNames( page );
	const showing = await page.$eval( '.mizzey-listing__showing', ( s ) => s.textContent ).catch( () => '' );
	check( 2 === names.length && /13/.test( showing ) && /14/.test( showing ), `${ L } PLP-16: page 2 shows the last two, "${ showing.trim() }"` );

	// Back from a product keeps the filters and the sort.
	const kept = `${ base }?brands=${ encodeURIComponent( slug( 'lumen', l ) ) }&orderby=price-desc`;
	await page.goto( kept, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const firstName = ( await cardNames( page ) )[ 0 ];
	await page.click( '.mizzey-listing__results .mizzey-card__name a' );
	await page.waitForLoadState( 'domcontentloaded', { timeout: T } );
	await page.goBack( { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const still = await page.$eval( `.mizzey-filter[data-group="brands"] input[value="${ slug( 'lumen', l ) }"]`, ( i ) => i.checked ).catch( () => false );
	check( params( page ).brands === slug( 'lumen', l ) && 'price-desc' === params( page ).orderby && still && ( await cardNames( page ) )[ 0 ] === firstName, `${ L }: back from a product, the brand stays ticked and the sort and the list are the same` );

	// The loading state, while the next page is on its way. The next page is answered "204 No Content", which leaves the
	// browser on this page in that state, to be measured and photographed.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const before = await page.$$eval( '.mizzey-listing__results .mizzey-products__item', ( i ) => i.map( ( x ) => { const r = x.getBoundingClientRect(); return [ r.x, r.y + scrollY, r.width, r.height ]; } ) );
	await page.route( ( u ) => u.toString().includes( 'categories=' ), ( route ) => route.fulfill( { status: 204, body: '' } ) );
	await tick( page, 'categories', slug( 'omega', l ) );
	await page.waitForTimeout( 600 );
	const loading = await page.evaluate( () => {
		const results = document.querySelector( '.mizzey-listing__results' );
		const skeletons = [ ...document.querySelectorAll( '.mizzey-listing__results .mizzey-card--skeleton' ) ];
		const items = [ ...document.querySelectorAll( '.mizzey-listing__results .mizzey-products__item' ) ].map( ( x ) => { const r = x.getBoundingClientRect(); return [ r.x, r.y + scrollY, r.width, r.height ]; } );
		const well = skeletons[ 0 ] && skeletons[ 0 ].querySelector( '.mizzey-card__well' );
		const zz = well && well.querySelector( '.mizzey-zz' );
		return {
			busy: results && results.getAttribute( 'aria-busy' ),
			skeletons: skeletons.length,
			zz: skeletons.filter( ( s ) => s.querySelector( '.mizzey-zz' ) ).length,
			breathing: zz ? getComputedStyle( zz ).animationName : '',
			items,
		};
	} );
	check( 'true' === loading.busy && loading.skeletons === before.length && loading.zz === before.length && /breathe/.test( loading.breathing ), `${ L } K-1 loading: while the server answers, each card is a skeleton with the breathing ZZ in its well (${ loading.skeletons } of ${ before.length }, ${ loading.breathing })` );
	check( loading.items.length === before.length && loading.items.every( ( it, i ) => it.every( ( v, k ) => near( v, before[ i ][ k ] ) ) ), `${ L } K-1 loading: the skeletons keep the cards' places and sizes, nothing shifts (${ JSON.stringify( loading.items.map( ( it, i ) => it.map( ( v, k ) => Math.round( v - before[ i ][ k ] ) ) ).filter( ( d ) => d.some( Boolean ) ).slice( 0, 2 ) ) })` );
	await shot( page, `${ l }-1440-loading` );
	await page.unrouteAll( { behavior: 'ignoreErrors' } );

	// PLP-19: a filter that matches nothing, then an empty category.
	const none = `${ base }?brands=${ encodeURIComponent( slug( 'nova', l ) ) }&${ plan.attribute }=${ encodeURIComponent( slug( 'powder', l ) ) }&filter_stock_status=instock`;
	await page.goto( none, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const empty = await page.evaluate( () => {
		const e = document.querySelector( '.mizzey-listing__empty .mizzey-empty' );
		return e ? { disc: !! e.querySelector( '.mizzey-empty__disc svg' ), zz: !! e.querySelector( '.mizzey-zz' ), clear: ( e.querySelector( 'a' ) || {} ).href || '', filters: !! document.querySelector( '.mizzey-filters__panel' ) } : null;
	} );
	check( empty && empty.disc && ! empty.zz && empty.filters && empty.clear.split( '?' )[ 0 ] === base && ! empty.clear.includes( 'brands' ), `${ L } PLP-19: no match shows a line icon in a disc (no ZZ), the filters stay, and "Clear filters" leads to the bare category` );
	await shot( page, `${ l }-1440-empty` );
	await page.goto( plan.url.empty[ l ], { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const nothing = await page.evaluate( () => ( {
		title: ( document.querySelector( '.mizzey-listing__empty .mizzey-empty__title' ) || {} ).textContent || '',
		toolbar: !! document.querySelector( '.mizzey-listing__toolbar' ),
		suggestions: document.querySelectorAll( '.mizzey-listing__suggestions .mizzey-card' ).length,
	} ) );
	check( nothing.title.includes( plan.names.empty[ l ] ) && ! nothing.toolbar && nothing.suggestions > 0, `${ L } PLP-19: an empty category says so by name, has no toolbar, and suggests products (${ nothing.suggestions })` );
	await shot( page, `${ l }-1440-empty-category` );
	check( 0 === errors.length, `${ L }: no script error on the pages (${ JSON.stringify( errors.slice( 0, 3 ) ) })` );
	await ctx.close();
}

/* ---- Phone ------------------------------------------------------------------------------------------------------ */

async function phone( browser, l ) {
	const L = `${ lang( l ) } 390`;
	const ctx = await context( browser, { viewport: { width: 390, height: 760 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, locale: 'ar' === l ? 'ar' : 'en' } );
	const page = await ctx.newPage();
	const base = plan.url[ l ];
	await page.goto( `${ base }?min_price=300&max_price=1700`, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	const m = {
		crumbs: await box( page, '.mizzey-listing__crumbs' ),
		banner: await box( page, '.mizzey-listing__banner' ),
		title: await box( page, '.mizzey-listing__title' ),
		tile: await box( page, '.mizzey-listing__tile' ),
		cats: await box( page, '.mizzey-listing__cats .mizzey-chip' ),
		toolbar: await box( page, '.mizzey-listing__toolbar' ),
		filters: await box( page, '.mizzey-listing__filters-open' ),
		applied: await box( page, '.mizzey-listing__applied' ),
		grid: await box( page, '.mizzey-listing__results .mizzey-products__list' ),
		cards: await page.$$eval( '.mizzey-listing__results .mizzey-products__item', ( items ) => items.slice( 0, 3 ).map( ( i ) => { const r = i.getBoundingClientRect(); return { x: r.x, y: r.y + scrollY, w: r.width, h: r.height }; } ) ),
		panel: await box( page, '.mizzey-filters__panel' ),
	};
	check( m.crumbs && near( m.crumbs.h, 44 ), `${ L } K-1: the breadcrumb row is 44 high (built ${ m.crumbs && m.crumbs.h })` );
	check( m.banner && near( m.banner.w, 358 ) && near( m.banner.y, m.crumbs.bottom ) && '24px' === m.banner.radius && '16px' === m.banner.pad, `${ L } K-1: the banner card is 358 wide, padding 16, radius 24, under the breadcrumb (built ${ m.banner && [ m.banner.w, m.banner.pad, m.banner.radius ].join( ' ' ) })` );
	check( m.title && near( m.title.fs, 'ar' === l ? 25 : 23 ) && '900' === m.title.fw, `${ L } K-1: the category name is the h2 role in Black, 23 (25 in the Arabic scale) (board 23/900; built ${ m.title && m.title.fs })` );
	check( m.tile && near( m.tile.w, 88 ) && near( m.tile.h, 88 ), `${ L } K-1: the image tile is 88 square (built ${ m.tile ? m.tile.w : await page.$eval( '.mizzey-listing__banner', ( b ) => b.outerHTML.slice( 0, 160 ) ).catch( ( e ) => String( e ).slice( 0, 160 ) ) })` );
	check( m.cats && near( m.cats.y - m.banner.bottom, 16 ), `${ L } K-1: the chips row is 16 under the banner (built ${ m.cats && Math.round( m.cats.y - m.banner.bottom ) })` );
	check( m.toolbar && near( m.toolbar.y - ( m.cats.y + m.cats.h ), 16 ) && near( m.toolbar.h, 44 ), `${ L } K-1: the toolbar is 16 under the chips and 44 high (built ${ m.toolbar && Math.round( m.toolbar.y - m.cats.y - m.cats.h ) }, ${ m.toolbar && m.toolbar.h })` );
	check( m.filters && near( m.filters.h, 44 ), `${ L } K-1: the Filters button is 44 high (built ${ m.filters && m.filters.h })` );
	check( m.applied && near( m.applied.y - m.toolbar.bottom, 8 ), `${ L } K-1: the active filters row is 8 under the toolbar (built ${ m.applied && Math.round( m.applied.y - m.toolbar.bottom ) })` );
	check( m.grid && near( m.grid.y - m.applied.bottom, 16 ), `${ L } K-1: the grid is 16 under the active filters row (built ${ m.grid && Math.round( m.grid.y - m.applied.bottom ) })` );
	check( m.cards.length >= 3 && near( m.cards[ 0 ].w, 173 ) && near( Math.abs( m.cards[ 1 ].x - m.cards[ 0 ].x ) - m.cards[ 0 ].w, 12 ) && near( m.cards[ 2 ].y - m.cards[ 0 ].y - m.cards[ 0 ].h, 12 ), `${ L } K-1: two cards 173 wide in a row, 12 apart both ways (built ${ m.cards[ 0 ] && Math.round( m.cards[ 0 ].w ) })` );
	check( ! m.panel || 'none' === m.panel.display || 0 === m.panel.w, `${ L } PLP-22: on a phone the filters are not on the page, they open in a drawer` );
	check( await noSideScroll( page ), `${ L }: the page never scrolls sideways` );
	await shot( page, `${ l }-390` );

	// The loading state on a phone: an active filter's chip tapped, the next page held back ("204 No Content").
	const chipHref = await page.$eval( '.mizzey-listing__applied a.mizzey-chip--applied', ( a ) => a.href ).catch( () => '' );
	const phoneItems = await page.$$eval( '.mizzey-listing__results .mizzey-products__item', ( i ) => i.length );
	// WordPress's speculative loading prefetches a link as it is touched, out of the test's reach: its rules go first.
	await page.evaluate( () => document.querySelectorAll( 'script[type="speculationrules"]' ).forEach( ( r ) => r.remove() ) );
	await page.route( ( u ) => u.toString() === chipHref, ( route ) => route.fulfill( { status: 204, body: '' } ) );
	await page.click( '.mizzey-listing__applied a.mizzey-chip--applied' ).catch( () => {} );
	await page.waitForTimeout( 500 );
	const phoneLoading = await page.evaluate( () => ( {
		busy: document.querySelector( '.mizzey-listing__results' ).getAttribute( 'aria-busy' ),
		skeletons: document.querySelectorAll( '.mizzey-listing__results .mizzey-card--skeleton .mizzey-zz' ).length,
	} ) );
	check( '' !== chipHref && 'true' === phoneLoading.busy && phoneLoading.skeletons === phoneItems, `${ L } K-1 loading: a tapped filter chip turns each card into its skeleton with the ZZ until the server answers (${ phoneLoading.skeletons } of ${ phoneItems })` );
	await shot( page, `${ l }-390-loading` );
	await page.unrouteAll( { behavior: 'ignoreErrors' } );

	// The drawer.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await page.click( '.mizzey-listing__filters-open' );
	await page.waitForSelector( '#mizzey-filters-drawer[open]', { timeout: 10000 } ).catch( () => {} );
	await page.waitForTimeout( 700 );
	const d = await page.evaluate( () => {
		const dialog = document.querySelector( '#mizzey-filters-drawer' );
		const panel = dialog && dialog.querySelector( '.mizzey-overlay__panel' );
		const r = panel ? panel.getBoundingClientRect() : null;
		const cs = panel ? getComputedStyle( panel ) : null;
		const header = dialog && dialog.querySelector( '.mizzey-overlay__header' );
		return dialog ? {
			open: dialog.open, focusIn: dialog.contains( document.activeElement ), left: r.left, right: r.right, w: r.width, h: r.height, vh: innerHeight,
			radii: [ cs.borderStartStartRadius, cs.borderStartEndRadius, cs.borderEndStartRadius, cs.borderEndEndRadius ],
			header: header ? header.getBoundingClientRect().height : 0,
		} : null;
	} );
	check( d && d.open && d.focusIn, `${ L } PLP-22: Filters opens the drawer and moves focus into it` );
	check( d && near( d.w, 342 ) && near( d.h, d.vh ) && ( 'ar' === l ? near( d.right, 390 ) : near( d.left, 0 ) ), `${ L } PLP-22: the drawer is 342 wide, full height, out of the ${ 'ar' === l ? 'right' : 'left' } edge (built ${ d && [ d.w, d.h, d.left, d.right ].join( ' ' ) })` );
	check( d && '0px' === d.radii[ 0 ] && '28px' === d.radii[ 1 ] && '0px' === d.radii[ 2 ] && '28px' === d.radii[ 3 ], `${ L }: the drawer is square on its edge and 28 on the two corners facing the page (${ d && d.radii.join( ' ' ) })` );
	check( d && near( d.header, 56 ), `${ L } K-1: the drawer's header is 56 high (board 56; built ${ d && d.header })` );
	const pin = async () => page.evaluate( () => {
		const b = document.querySelector( '#mizzey-filters-drawer .mizzey-listing__show' );
		const r = b ? b.getBoundingClientRect() : null;
		const panel = document.querySelector( '#mizzey-filters-drawer .mizzey-overlay__panel' ).getBoundingClientRect();
		const scroller = document.querySelector( '#mizzey-filters-drawer .mizzey-filters__groups' );
		return r ? { top: r.top, bottom: r.bottom, h: r.height, panelBottom: panel.bottom, text: b.textContent.trim(), busy: b.getAttribute( 'aria-busy' ), loader: !! b.querySelector( '.mizzey-loader' ) && getComputedStyle( b.querySelector( '.mizzey-loader' ) ).display !== 'none', scrolls: scroller ? scroller.scrollHeight > scroller.clientHeight : false } : null;
	} );
	const p1 = await pin();
	check( p1 && near( p1.h, 52 ) && p1.bottom <= p1.panelBottom - 16 + 1, `${ L } PLP-22: "Show N products" is a 52 button pinned above the drawer's bottom edge (built ${ p1 && [ p1.h, Math.round( p1.panelBottom - p1.bottom ) ].join( ', ' ) })` );
	await page.$eval( '#mizzey-filters-drawer .mizzey-filters__groups', ( s ) => s.scrollTo( 0, s.scrollHeight ) ).catch( () => {} );
	await page.waitForTimeout( 200 );
	const p2 = await pin();
	check( p1 && p2 && p1.scrolls && near( p1.top, p2.top ), `${ L } PLP-22: the groups scroll under the button, which stays where it is` );
	const counts = await page.evaluate( () => {
		const dialog = document.querySelector( '#mizzey-filters-drawer' );
		const visible = ( el ) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle( el ).visibility !== 'hidden'; };
		const counted = /\d[\d,٬]*\s*(?:of\s+[\d,]+\s*)?(?:products?|منتج)/u;
		return [ ...dialog.querySelectorAll( '*' ) ].filter( ( el ) => [ ...el.childNodes ].some( ( n ) => 3 === n.nodeType && n.textContent.trim() ) && visible( el ) && counted.test( el.textContent ) && ! el.closest( '.mizzey-listing__show' ) ).map( ( el ) => el.textContent.trim() );
	} );
	check( 0 === counts.length && p1 && /\d/.test( p1.text ), `${ L } PLP-22: the button carries the drawer's one count; no other result count is in the drawer (${ counts.join( ' | ' ) })` );

	// Each change asks the server; the button waits with the i-dot loader, then shows the server's number.
	let release;
	await page.route( ( u ) => u.toString().includes( 'mizzey_count=1' ), async ( route ) => {
		await new Promise( ( r ) => {
			release = r;
			setTimeout( r, 1500 );
		} );
		await route.continue();
	} );
	await page.$eval( '#mizzey-filters-drawer .mizzey-filters__groups', ( s ) => s.scrollTo( 0, 0 ) ).catch( () => {} );
	await tick( page, 'brands', slug( 'lumen', l ), '#mizzey-filters-drawer' );
	await page.waitForTimeout( 250 );
	const waiting = await pin();
	const loaderBox = await page.$eval( '#mizzey-filters-drawer .mizzey-listing__show .mizzey-loader', ( el ) => { const r = el.getBoundingClientRect(); const dots = [ ...el.querySelectorAll( '.mizzey-loader__dot' ) ].map( ( x ) => parseFloat( getComputedStyle( x ).width ) ); const gap = parseFloat( getComputedStyle( el ).columnGap ); return { w: r.width, h: r.height, dots, gap }; } ).catch( () => null );
	check( waiting && 'true' === waiting.busy && waiting.loader && ! /\d/.test( waiting.text ), `${ L } motion: while the server has not answered, the button shows the i-dot loader and no number (${ waiting && waiting.text })` );
	check( loaderBox && p1 && loaderBox.h <= p1.h && loaderBox.dots.every( ( x ) => near( x, 7 ) ) && near( loaderBox.gap, 3 ), `${ L } K-1 waiting: the loader sits inside the 52 button, dots 7 wide and 3 apart as the board (gap ${ loaderBox && loaderBox.gap }; loader ${ loaderBox && [ Math.round( loaderBox.w ), Math.round( loaderBox.h ) ].join( 'x' ) }, dots ${ loaderBox && loaderBox.dots.map( Math.round ).join( '/' ) })` );
	await shot( page, `${ l }-390-drawer-waiting` );
	if ( release ) {
		release();
	}
	await page.waitForFunction( () => { const b = document.querySelector( '#mizzey-filters-drawer .mizzey-listing__show' ); return b && 'true' !== b.getAttribute( 'aria-busy' ); }, null, { timeout: T } );
	let after = await pin();
	check( after && after.text.includes( String( expected( { brands: [ 'lumen' ] } ).length ) ), `${ L } PLP-22: then the button reads the server's count for Lumen (${ after && after.text })` );
	await page.unrouteAll( { behavior: 'ignoreErrors' } );
	await page.click( '#mizzey-filters-drawer label:has(input[name="filter_stock_status"])' );
	await tick( page, 'categories', slug( 'minerals', l ), '#mizzey-filters-drawer' );
	await page.waitForFunction( () => { const b = document.querySelector( '#mizzey-filters-drawer .mizzey-listing__show' ); return b && 'true' !== b.getAttribute( 'aria-busy' ); }, null, { timeout: T } );
	await page.waitForTimeout( 300 );
	await page.waitForFunction( () => { const b = document.querySelector( '#mizzey-filters-drawer .mizzey-listing__show' ); return b && 'true' !== b.getAttribute( 'aria-busy' ); }, null, { timeout: T } );
	after = await pin();
	const together = expected( { brands: [ 'lumen' ], instock: true, child: 'minerals' } ).length;
	check( after && after.text.includes( String( together ) ), `${ L } PLP-22: with three filters chosen the one count is the server's for all of them together, ${ together } (${ after && after.text })` );
	await shot( page, `${ l }-390-drawer` );
	await axe( page, '#mizzey-filters-drawer', `${ L }: the filters drawer` );
	await navigates( page, () => page.click( '#mizzey-filters-drawer .mizzey-listing__show' ) );
	const q = params( page );
	let names = await cardNames( page );
	check( q.brands === slug( 'lumen', l ) && q.categories === slug( 'minerals', l ) && 'instock' === q.filter_stock_status && names.length === together, `${ L } PLP-22: the button applies them: the address carries the three filters and the list has ${ together } (${ names.length })` );
	check( 3 === ( await page.$$eval( '.mizzey-listing__applied .mizzey-chip--applied', ( c ) => c.length ) ), `${ L } PLP-18: three active filter chips` );
	await navigates( page, () => page.click( '.mizzey-listing__applied a.mizzey-chip--applied[data-param="brands"]' ) );
	names = await cardNames( page );
	check( ! params( page ).brands && sameSet( names, expected( { child: 'minerals', instock: true } ).map( ( p ) => p.name ) ), `${ L } PLP-18: removing the brand chip keeps the other two (${ names.length })` );

	// Escape closes; focus goes back to Filters.
	await page.click( '.mizzey-listing__filters-open' );
	await page.waitForSelector( '#mizzey-filters-drawer[open]', { timeout: 10000 } ).catch( () => {} );
	await page.waitForTimeout( 500 );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 500 );
	const back = await page.evaluate( () => ( { open: document.querySelector( '#mizzey-filters-drawer' ).open, focus: document.activeElement && document.activeElement.classList.contains( 'mizzey-listing__filters-open' ) } ) );
	check( ! back.open && back.focus, `${ L } PLP-22: Escape closes the drawer and focus returns to Filters` );

	// The sort sheet.
	await page.goto( base, { waitUntil: 'domcontentloaded', timeout: T } );
	await settle( page );
	await page.click( '.mizzey-listing__sort-open' );
	await page.waitForSelector( '#mizzey-sort-sheet[open]', { timeout: 10000 } ).catch( () => {} );
	await page.waitForTimeout( 500 );
	const sheet = await page.evaluate( () => { const p = document.querySelector( '#mizzey-sort-sheet .mizzey-overlay__panel' ); const cs = getComputedStyle( p ); return { radios: document.querySelectorAll( '#mizzey-sort-sheet input[type="radio"]' ).length, radii: [ cs.borderStartStartRadius, cs.borderStartEndRadius, cs.borderEndStartRadius ] }; } ).catch( () => null );
	check( sheet && 5 === sheet.radios && '28px' === sheet.radii[ 0 ] && '28px' === sheet.radii[ 1 ] && '0px' === sheet.radii[ 2 ], `${ L }: Sort opens a bottom sheet of five plain radios, its two top corners rounded` );
	await shot( page, `${ l }-390-sort` );
	await axe( page, '#mizzey-sort-sheet', `${ L }: the sort sheet` );
	await navigates( page, () => page.click( '#mizzey-sort-sheet label:has(input[value="price-desc"])' ) );
	names = await cardNames( page );
	check( 'price-desc' === params( page ).orderby && names.join( '|' ) === first( ORDERS[ 'price-desc' ] ).join( '|' ), `${ L } PLP-13: the sheet's "Price high to low" orders the list` );
	check( await noSideScroll( page ), `${ L }: still no sideways scroll` );
	await ctx.close();
}

/* ---- Without JavaScript --------------------------------------------------------------------------------------- */

async function noScript( browser ) {
	const ctx = await context( browser, { viewport: { width: 1440, height: 900 }, javaScriptEnabled: false } );
	const page = await ctx.newPage();
	await page.goto( plan.url.en, { waitUntil: 'domcontentloaded', timeout: T } );
	await page.check( `.mizzey-filter[data-group="brands"] input[value="${ slug( 'lumen', 'en' ) }"]` ).catch( () => {} );
	await page.check( `.mizzey-filter[data-group="brands"] input[value="${ slug( 'nova', 'en' ) }"]` ).catch( () => {} );
	const submit = await page.$( '.mizzey-filters__submit' );
	check( submit && ( await submit.isVisible() ), 'no JavaScript: the filter column carries its own submit button' );
	if ( submit ) {
		await Promise.all( [ page.waitForNavigation( { timeout: T } ), submit.click() ] );
		await page.waitForTimeout( 300 );
	}
	const names = await cardNames( page );
	const q = params( page );
	check( q.brands === `${ slug( 'lumen', 'en' ) },${ slug( 'nova', 'en' ) }` && sameSet( names, first( expected( { brands: [ 'lumen', 'nova' ] } ).sort( ( a, b ) => a.name.localeCompare( b.name ) ) ) ), `no JavaScript: the form applies two brands at once through a clean address (${ page.url().split( '?' )[ 1 ] })` );
	const sortLink = await page.$( '.mizzey-listing__sort-links a[href*="orderby=price"]' );
	check( sortLink && ( await sortLink.isVisible() ), 'no JavaScript: the sorts are links' );
	if ( sortLink ) {
		await Promise.all( [ page.waitForNavigation( { timeout: T } ), sortLink.click() ] );
		check( /orderby=price/.test( page.url() ) && q.brands === params( page ).brands, 'no JavaScript: a sort link keeps the filters' );
	}

	// Two values of one attribute: a product with either is listed (WooCommerce's "or", not "and").
	const submitForm = async () => {
		await Promise.all( [ page.waitForNavigation( { timeout: T } ), page.click( '.mizzey-filters__submit' ) ] );
		await page.waitForTimeout( 300 );
	};
	const countOf = async () => Number( ( ( await page.$eval( '.mizzey-listing__count .mizzey-listing__number', ( n ) => n.textContent ).catch( () => '' ) ) || '' ).replace( /\D/g, '' ) );
	const either = expected( {} ).filter( ( p ) => p.form );
	await page.goto( plan.url.en, { waitUntil: 'domcontentloaded', timeout: T } );
	await page.check( `.mizzey-filter[data-group="${ plan.attribute }"] input[value="${ slug( 'capsules', 'en' ) }"]` ).catch( () => {} );
	await page.check( `.mizzey-filter[data-group="${ plan.attribute }"] input[value="${ slug( 'powder', 'en' ) }"]` ).catch( () => {} );
	await submitForm();
	const anyForm = params( page );
	check( `query_type_${ plan.attribute.slice( 7 ) }` in anyForm && 'or' === anyForm[ `query_type_${ plan.attribute.slice( 7 ) }` ] && either.length === await countOf(), `no JavaScript: two values of one attribute list the products with either, ${ either.length } (${ await countOf() }; ${ page.url().split( '?' )[ 1 ] })` );

	// Two filters one after the other, the price never touched: no product is lost and no price is applied.
	await page.goto( plan.url.en, { waitUntil: 'domcontentloaded', timeout: T } );
	await page.check( `.mizzey-filter[data-group="brands"] input[value="${ slug( 'nova', 'en' ) }"]` ).catch( () => {} );
	await submitForm();
	await page.check( `.mizzey-filter[data-group="brands"] input[value="${ slug( 'lumen', 'en' ) }"]` ).catch( () => {} );
	await submitForm();
	const both = expected( { brands: [ 'lumen', 'nova' ] } );
	const after = params( page );
	const priceChip = await page.$( '.mizzey-listing__applied [data-param="price"]' );
	check( ! ( 'min_price' in after ) && ! ( 'max_price' in after ) && ! priceChip && both.length === await countOf(), `no JavaScript: a second filter after a first, the price untouched, keeps every product (${ await countOf() } of ${ both.length }) and applies no price (${ page.url().split( '?' )[ 1 ] })` );
	await ctx.close();
	const phoneCtx = await context( browser, { viewport: { width: 390, height: 760 }, javaScriptEnabled: false, isMobile: true } );
	const p = await phoneCtx.newPage();
	await p.goto( plan.url.en, { waitUntil: 'domcontentloaded', timeout: T } );
	await p.click( '.mizzey-listing__filters-open' ).catch( () => {} );
	await p.waitForTimeout( 300 );
	const shown = await p.$eval( '.mizzey-filters__panel', ( el ) => el.getBoundingClientRect().width > 300 ).catch( () => false );
	check( shown, 'no JavaScript on a phone: Filters opens the filter form' );
	await phoneCtx.close();
}

/**
 * The filter column and the footer (owner comment 12): on a list shorter than the column (two products) and on a full
 * page, at 1440 and 1280, the column ends 64 above the listing's end and so at least 136 above the footer, as
 * C1-Category-Desktop draws it (column 1710, footer 1846), and the page's background reaches the footer with no edge.
 */
async function footerClear( engine, name ) {
	const browser = await engine.launch( 'chromium' === name ? loadPlaywright().launch : {} );
	const few = `${ plan.url.en }?brands=${ encodeURIComponent( slug( 'nova', 'en' ) ) }&categories=${ encodeURIComponent( slug( 'minerals', 'en' ) ) }`;
	for ( const width of [ 1440, 1280 ] ) {
		for ( const [ what, url ] of [ [ 'two products', few ], [ 'a full page', plan.url.en ] ] ) {
			const ctx = await context( browser, { viewport: { width, height: 900 } } );
			const page = await ctx.newPage();
			await page.goto( url, { waitUntil: 'load', timeout: T } );
			await page.waitForTimeout( 500 );
			const m = await page.evaluate( () => {
				const b = ( s ) => { const e = document.querySelector( s ); if ( ! e ) { return null; } const r = e.getBoundingClientRect(); return { top: r.top + scrollY, bottom: r.bottom + scrollY }; };
				return { panel: b( '.mizzey-filters__panel' ), listing: b( '.mizzey-listing-page' ), footer: b( '.mizzey-footer' ), cards: document.querySelectorAll( '.mizzey-listing__results .mizzey-card' ).length };
			} );
			const gap = m.panel && m.footer ? Math.round( m.footer.top - m.panel.bottom ) : null;
			const end = m.panel && m.listing ? Math.round( m.listing.bottom - m.panel.bottom ) : null;
			check( null !== gap && gap >= 136 && end >= 64, `${ name } ${ width } ${ what } (${ m.cards } cards), owner comment 12: the filter column ends 64 or more above the listing's end and never reaches the footer (to the listing's end ${ end }, to the footer ${ gap }; board 64 and 136)` );
			// The background at the listing's last rows and in the space above the footer: the same colour, no edge.
			const y = Math.round( m.listing.bottom );
			const png = await page.screenshot( { clip: { x: 4, y: y - 3, width: 2, height: 6 }, fullPage: true } );
			const shade = await page.evaluate( async ( data ) => {
				const img = new Image();
				img.src = `data:image/png;base64,${ data }`;
				await img.decode();
				const c = document.createElement( 'canvas' );
				c.width = img.width;
				c.height = img.height;
				const x = c.getContext( '2d' );
				x.drawImage( img, 0, 0 );
				const px = ( r ) => [ ...x.getImageData( 0, r, 1, 1 ).data ].slice( 0, 3 );
				return [ px( 0 ), px( img.height - 1 ) ];
			}, png.toString( 'base64' ) );
			const diff = Math.max( ...shade[ 0 ].map( ( v, i ) => Math.abs( v - shade[ 1 ][ i ] ) ) );
			check( diff <= 2, `${ name } ${ width } ${ what }, owner comment 12: the background runs on into the space above the footer with no edge (above ${ shade[ 0 ].join( ',' ) }, below ${ shade[ 1 ].join( ',' ) })` );
			await ctx.close();
		}
	}
	await browser.close();
}

( async () => {
	const { pw, launch } = loadPlaywright();
	const browser = await pw.chromium.launch( launch );
	try {
		for ( const l of [ 'en', 'ar' ] ) {
			await desktop( browser, l );
			await phone( browser, l );
		}
		await noScript( browser );
		await footerClear( pw.chromium, 'chromium' );
		await footerClear( pw.webkit, 'webkit' );
	} catch ( e ) {
		check( false, `the run stopped: ${ String( e && e.stack ? e.stack : e ).slice( 0, 900 ) }` );
	}
	await browser.close();
	process.stdout.write( JSON.stringify( { checks } ) + '\n' );
} )();

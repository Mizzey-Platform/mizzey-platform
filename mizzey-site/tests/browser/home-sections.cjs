#!/usr/bin/env node
/**
 * The home page sections in a real browser (spec 066, slice 5; AC-3, AC-4). Driven by t110, which prepares the
 * runtime and passes a JSON plan as the only argument; prints one JSON object: { checks: [{ ok, what }] }.
 *
 * As an editor account, on the front page in English: every library section is added through the inserter and
 * edited as a person does (words typed in place, settings in the sidebar, an image chosen from the media library),
 * saved, and the storefront shows the sections in the editor's order with their content. The offer banner is moved up
 * with the block toolbar and previewed (the preview has the new order, the live page the old one), then published;
 * the deals section is hidden with "Show on the site", and reopened, the editor still holds its content. On the
 * Arabic front page three sections get their own Arabic words.
 *
 * As a visitor, at 390 and 1440 wide in English and Arabic: the page never scrolls sideways, the hero's first image is
 * preloaded from the head and is not lazy, it is the page's Largest Contentful Paint, and the slider waits at least
 * six seconds a slide, holds while hovered, pauses from its button and stays still under reduced motion.
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
const T = plan.tag;
const TIMEOUT = 120000;

async function openEditor( page, url ) {
	await page.goto( url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT } );
	await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/editor' ) && wp.data.select( 'core/editor' ).getCurrentPostId() && wp.data.select( 'core/block-editor' ).getSettings().allowedBlockTypes !== undefined, null, { timeout: TIMEOUT } );
	await page.locator( 'iframe[name="editor-canvas"]' ).waitFor( { timeout: TIMEOUT } );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await canvas.locator( '.is-root-container' ).waitFor( { timeout: TIMEOUT } );
	for ( const name of [ 'Close', 'Skip' ] ) {
		const dialog = page.locator( '.components-modal__frame' ).getByRole( 'button', { name } );
		if ( await dialog.count() ) {
			await dialog.first().click().catch( () => {} );
		}
	}
	return canvas;
}

async function inserterNames( page ) {
	const toggle = page.locator( 'button.editor-document-tools__inserter-toggle' );
	await toggle.click();
	await page.locator( '.block-editor-inserter__block-list, .block-editor-inserter__panel-content' ).first().waitFor( { timeout: TIMEOUT } );
	await page.waitForTimeout( 500 );
	const data = await page.evaluate( () => wp.data.select( 'core/block-editor' ).getInserterItems().map( ( i ) => i.name || i.id ) ).catch( () => [] );
	await toggle.click();
	return data;
}

/** Add a section through the inserter's search, as a person does; resolves to the new block's client id. */
async function insert( page, name, search ) {
	const before = await page.evaluate( () => wp.data.select( 'core/block-editor' ).getBlockOrder() );
	const toggle = page.locator( 'button.editor-document-tools__inserter-toggle' );
	await toggle.click();
	await page.locator( '.block-editor-inserter__search input, input[type="search"]' ).first().fill( search );
	await page.locator( `.block-editor-block-types-list__item.editor-block-list-item-${ name.replace( '/', '-' ) }` ).first().click();
	await page.waitForFunction( ( n ) => wp.data.select( 'core/block-editor' ).getBlockOrder().length > n, before.length, { timeout: TIMEOUT } );
	if ( await page.locator( '.block-editor-inserter__search' ).count() ) {
		await toggle.click().catch( () => {} );
	}
	return page.evaluate( ( old ) => wp.data.select( 'core/block-editor' ).getBlockOrder().find( ( id ) => ! old.includes( id ) ), before );
}

async function openSidebar( page ) {
	const settings = page.locator( 'button[aria-label="Settings"][aria-expanded="false"]' );
	if ( await settings.count() ) {
		await settings.first().click();
	}
}

async function select( canvas, page, clientId ) {
	await page.evaluate( ( id ) => wp.data.dispatch( 'core/block-editor' ).selectBlock( id ), clientId );
	await canvas.locator( `[data-block="${ clientId }"]` ).scrollIntoViewIfNeeded().catch( () => {} );
	await openSidebar( page );
	const blockTab = page.getByRole( 'tab', { name: 'Block' } );
	if ( await blockTab.count() ) {
		await blockTab.first().click().catch( () => {} );
	}
}

/** Type in a field in place, in the canvas. */
async function typeIn( canvas, page, clientId, label, text ) {
	const field = canvas.locator( `[data-block="${ clientId }"]` ).getByRole( 'textbox', { name: label, exact: true } );
	await field.click();
	if ( ( await field.innerText() ).trim() !== '' ) {
		await page.keyboard.press( 'Control+A' );
	}
	await page.keyboard.type( text );
}

const sidebarText = ( page, label, value ) => page.locator( '.block-editor-block-inspector' ).getByRole( 'textbox', { name: label, exact: true } ).fill( value );
const sidebarSelect = ( page, label, value ) => page.locator( '.block-editor-block-inspector' ).getByRole( 'combobox', { name: label, exact: true } ).selectOption( value );

/** Choose an image from the media library through the sidebar's button, as a person does. */
async function chooseImage( page, button, search ) {
	await page.locator( '.block-editor-block-inspector' ).getByRole( 'button', { name: button } ).click();
	const modal = page.locator( '.media-modal:visible' );
	await modal.waitFor( { timeout: TIMEOUT } );
	const libraryTab = modal.getByRole( 'tab', { name: 'Media Library' } );
	if ( await libraryTab.count() ) {
		await libraryTab.click();
	}
	await modal.locator( '#media-search-input' ).fill( search );
	await modal.locator( 'li.attachment' ).first().waitFor( { timeout: TIMEOUT } );
	await page.waitForTimeout( 1500 );
	await modal.locator( 'li.attachment' ).first().click();
	await modal.getByRole( 'button', { name: /^Select$|^Choose image$/ } ).click();
	await modal.waitFor( { state: 'hidden', timeout: TIMEOUT } );
}

async function save( page ) {
	await page.locator( '.editor-post-publish-button, .editor-post-publish-button__button' ).first().click();
	await page.waitForFunction( () => {
		const e = wp.data.select( 'core/editor' );
		return ! e.isSavingPost() && ! e.isEditedPostDirty() && ! e.isAutosavingPost();
	}, null, { timeout: TIMEOUT } );
	await page.waitForTimeout( 500 );
}

/** The library sections a page shows, in order, with what tells them apart. */
async function sectionsOn( context, url ) {
	const page = await context.newPage();
	await page.goto( url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT } );
	const out = await page.evaluate( () => ( {
		dir: document.documentElement.dir || getComputedStyle( document.documentElement ).direction,
		sections: [ ...document.querySelectorAll( '[data-mizzey-section]' ) ].map( ( s ) => ( {
			type: s.dataset.mizzeySection,
			heading: ( ( s.querySelector( 'h1, h2' ) || {} ).textContent || '' ).replace( /\s+/g, ' ' ).trim(),
			text: s.textContent.replace( /\s+/g, ' ' ).trim(),
			links: [ ...s.querySelectorAll( 'a[href]' ) ].map( ( a ) => a.getAttribute( 'href' ) ),
			images: [ ...s.querySelectorAll( 'img' ) ].length,
		} ) ),
	} ) );
	await page.close();
	return out;
}

/*
 * The boards' numbers, read from C1-Home-Desktop (1440) and C1-Home-Phone (390); where another board differs, the
 * page board wins. "gap a>b" is from the bottom of what a shows (its last card, for a product section) to the top of
 * b; "head>x" from the bottom of a section's heading row to the top of its first tile, card or chip.
 */
const BOARD = {
	1440: {
		'hero top under the header': 24, 'hero width': 1272, 'hero height': 440, 'hero corner': 24,
		'hero words from the start': 56, 'hero words from the top': 48, 'hero controls under the banner': 12, 'hero controls height': 44,
		'gap hero>offer-banner': 72, 'gap offer-banner>products': 72, 'gap products>category-shortcuts': 72,
		'gap category-shortcuts>featured-collections': 72, 'gap featured-collections>deals': 72, 'gap deals>brand-highlights': 72,
		'gap brand-highlights>signup': 72,
		'heading row height': 48, 'head>tile category-shortcuts': 24, 'head>card products': 24, 'head>tile featured-collections': 24,
		'category tile width': 138, 'category tile corner': 24, 'category gap': 24,
		'collection tile width': 300, 'collection pill height': 44, 'collection pill from the corner': 12,
		'deals width': 1272, 'deals inner space': 40, 'deals corner': 24, 'deals head>card': 24,
		'offer height': 320, 'offer words from the start': 56, 'offer width': 1272,
		'brand chip height': 36, 'brand chip gap': 8,
		'signup inner space': 56, 'signup corner': 24, 'signup width': 1272,
	},
	390: {
		'hero top under the header': 0, 'hero width': 390, 'hero height': 440, 'hero image height': 224, 'hero card over the image': 32,
		'hero card corner': 28, 'hero dots from the bottom': 16,
		'gap hero>offer-banner': 40, 'gap offer-banner>products': 40, 'gap products>category-shortcuts': 40,
		'gap category-shortcuts>featured-collections': 40, 'gap featured-collections>deals': 40, 'gap deals>brand-highlights': 40,
		'gap brand-highlights>signup': 40,
		'heading row height': 48, 'head>tile category-shortcuts': 16, 'head>card products': 16, 'head>tile featured-collections': 16,
		'head>chip brand-highlights': 16,
		'category tile width': 80.5, 'category tile corner': 20, 'category gap': 12,
		'collection tile width': 173, 'collection pill height': 36, 'collection pill from the corner': 8,
		'deals width': 390, 'deals inner space': 24, 'deals corner': 24,
		'offer image height': 200, 'offer width': 358,
		'brand chip height': 36, 'brand chip gap': 8,
		'signup inner space': 24, 'signup width': 358, 'signup button height': 52,
	},
};

/** The numbers BOARD names, read from the storefront in the browser. */
function measure() {
	const none = { top: NaN, bottom: NaN, left: NaN, right: NaN, width: NaN, height: NaN };
	const r = ( el ) => ( el ? el.getBoundingClientRect() : none );
	const px = ( el, prop ) => ( el ? parseFloat( getComputedStyle( el )[ prop ] ) : null );
	const out = {};
	const sections = [ ...document.querySelectorAll( '[data-mizzey-section]' ) ];
	const of = ( type ) => sections.find( ( s ) => s.dataset.mizzeySection === type );
	const header = document.querySelector( 'header' );
	const visualBottom = ( s ) => {
		const cards = [ ...s.querySelectorAll( '.mizzey-card' ) ];
		return cards.length && s.dataset.mizzeySection === 'products' ? Math.max( ...cards.map( ( c ) => r( c ).bottom ) ) : r( s ).bottom;
	};
	for ( let i = 1; i < sections.length; i++ ) {
		out[ `gap ${ sections[ i - 1 ].dataset.mizzeySection }>${ sections[ i ].dataset.mizzeySection }` ] = Math.round( ( r( sections[ i ] ).top - visualBottom( sections[ i - 1 ] ) ) * 2 ) / 2;
	}
	const hero = of( 'hero' );
	if ( hero ) {
		const slide = hero.querySelector( '.mizzey-hero__slide' );
		const panel = slide.querySelector( '.mizzey-hero__panel' );
		const media = slide.querySelector( '.mizzey-hero__media' );
		const controls = hero.querySelector( '.mizzey-hero__controls' );
		out[ 'hero top under the header' ] = r( slide ).top - r( header ).bottom;
		out[ 'hero width' ] = r( slide ).width;
		out[ 'hero height' ] = r( slide ).height;
		out[ 'hero corner' ] = px( slide, 'borderTopLeftRadius' );
		out[ 'hero words from the start' ] = r( panel ).left - r( slide ).left;
		out[ 'hero words from the top' ] = r( panel ).top - r( slide ).top;
		out[ 'hero controls under the banner' ] = controls ? r( controls ).top - r( slide ).bottom : null;
		out[ 'hero controls height' ] = controls ? r( controls ).height : null;
		out[ 'hero image height' ] = r( media ).height;
		out[ 'hero card over the image' ] = r( media ).bottom - r( panel ).top;
		out[ 'hero card corner' ] = px( panel, 'borderTopLeftRadius' );
		const dots = hero.querySelector( '.mizzey-hero__dots' );
		out[ 'hero dots from the bottom' ] = dots ? r( slide ).bottom - r( dots ).bottom - ( r( controls ).height - r( dots ).height ) / 2 : null;
	}
	const headRow = ( s ) => s && s.querySelector( '.mizzey-home__head, .mizzey-products__head' );
	const cats = of( 'category-shortcuts' );
	if ( cats ) {
		const tiles = [ ...cats.querySelectorAll( '.mizzey-shortcuts__image' ) ];
		out[ 'heading row height' ] = r( headRow( cats ) ).height;
		out[ 'head>tile category-shortcuts' ] = r( tiles[ 0 ] ).top - r( headRow( cats ) ).bottom;
		out[ 'category tile width' ] = Math.round( r( tiles[ 0 ] ).width * 2 ) / 2;
		out[ 'category tile corner' ] = px( tiles[ 0 ], 'borderTopLeftRadius' );
		out[ 'category gap' ] = tiles[ 1 ] ? Math.round( ( r( tiles[ 1 ] ).left - r( tiles[ 0 ] ).right ) * 2 ) / 2 : null;
	}
	const products = of( 'products' );
	if ( products ) {
		out[ 'head>card products' ] = r( products.querySelector( '.mizzey-card' ) ).top - r( headRow( products ) ).bottom;
	}
	const colls = of( 'featured-collections' );
	if ( colls ) {
		const tile = colls.querySelector( '.mizzey-collections__link' );
		const pill = colls.querySelector( '.mizzey-collections__name' );
		out[ 'head>tile featured-collections' ] = r( tile ).top - r( headRow( colls ) ).bottom;
		out[ 'collection tile width' ] = Math.round( r( tile ).width );
		out[ 'collection pill height' ] = r( pill ).height;
		out[ 'collection pill from the corner' ] = r( tile ).bottom - r( pill ).bottom;
	}
	const deals = of( 'deals' );
	if ( deals ) {
		out[ 'deals width' ] = r( deals ).width;
		out[ 'deals inner space' ] = r( headRow( deals ) ).top - r( deals ).top;
		out[ 'deals corner' ] = px( deals, 'borderTopLeftRadius' );
		out[ 'deals head>card' ] = r( deals.querySelector( '.mizzey-card' ) ).top - r( headRow( deals ) ).bottom;
	}
	const offer = of( 'offer-banner' );
	if ( offer ) {
		const card = offer.querySelector( '.mizzey-offer__card' );
		out[ 'offer height' ] = r( card ).height;
		out[ 'offer width' ] = r( card ).width;
		out[ 'offer words from the start' ] = r( offer.querySelector( '.mizzey-offer__body > *' ) ).left - r( card ).left;
		out[ 'offer image height' ] = r( offer.querySelector( '.mizzey-offer__media' ) ).height;
	}
	const brands = of( 'brand-highlights' );
	if ( brands ) {
		const chips = [ ...brands.querySelectorAll( '.mizzey-brands__chip' ) ];
		out[ 'brand chip height' ] = r( chips[ 0 ] ).height;
		out[ 'brand chip gap' ] = chips[ 1 ] ? Math.abs( r( chips[ 1 ] ).left - r( chips[ 0 ] ).right ) : null;
		out[ 'head>chip brand-highlights' ] = r( chips[ 0 ] ).top - r( headRow( brands ) ).bottom;
	}
	const signup = of( 'signup' );
	if ( signup ) {
		out[ 'signup inner space' ] = px( signup, 'paddingTop' );
		out[ 'signup corner' ] = px( signup, 'borderTopLeftRadius' );
		out[ 'signup width' ] = r( signup ).width;
		out[ 'signup button height' ] = r( signup.querySelector( '.mizzey-signup__button' ) ).height;
	}
	return out;
}

/**
 * The slider's controls on a phone (C1-Home-Phone): the row sits 12 under the button, as drawn; the pause button's
 * 44 target reaches 16 above the 12 high row, so it overlaps the button's last 4 px. That strip stays the button's
 * (a touch there follows the link), and nothing the controls draw touches the button: no grey tap highlight, the
 * board's press (a scale), and a keyboard ring kept clear of it. A tap leaves no focus ring.
 */
async function heroControls( view, where ) {
	const m = await view.evaluate( () => {
		const slide = document.querySelector( '.mizzey-hero__slide.is-active' ) || document.querySelector( '.mizzey-hero__slide' );
		const cta = slide && slide.querySelector( '.mizzey-hero__button' );
		const pause = document.querySelector( '.mizzey-hero__pause' );
		const row = document.querySelector( '.mizzey-hero__controls' );
		if ( ! cta || ! pause || ! row ) {
			return null;
		}
		const c = cta.getBoundingClientRect();
		const p = pause.getBoundingClientRect();
		const r = row.getBoundingClientRect();
		const st = getComputedStyle( pause );
		const width = parseFloat( st.outlineWidth ) || 2;
		// The keyboard ring of the focused pause button, from its own outline offset.
		pause.focus();
		const offset = parseFloat( getComputedStyle( pause ).outlineOffset ) || 0;
		pause.blur();
		const ringTop = p.top - offset - width;
		// Across the button's bottom strip, over the pause target, a touch reaches the button (on the pill's straight
		// part: at its rounded ends that strip is outside the button).
		const lo = Math.max( p.left, c.left + c.height / 2 ) + 1;
		const hi = Math.min( p.right, c.right - c.height / 2 ) - 1;
		const xs = hi > lo ? [ lo, ( lo + hi ) / 2, hi ] : [];
		const hits = xs.map( ( x ) => {
			const el = document.elementFromPoint( x, c.bottom - 1 );
			return !! el && ( el === cta || cta.contains( el ) );
		} );
		return {
			gap: Math.round( ( r.top - c.bottom ) * 10 ) / 10,
			ctaBottom: Math.round( c.bottom ), pauseTop: Math.round( p.top ), rowTop: Math.round( r.top ),
			ringClear: Math.round( ( ringTop - c.bottom ) * 10 ) / 10,
			hits, overlap: p.left < c.right && p.right > c.left,
			highlight: st.webkitTapHighlightColor || st.getPropertyValue( '-webkit-tap-highlight-color' ),
		};
	} );
	if ( ! m ) {
		return check( false, `[${ where }] the hero has its button and its controls` );
	}
	check( Math.abs( m.gap - 12 ) <= 1, `K-1 [${ where }] the controls row sits 12 under the button, as C1-Home-Phone draws (${ m.gap }: button bottom ${ m.ctaBottom }, row top ${ m.rowTop }, pause target top ${ m.pauseTop })` );
	check( ! m.overlap || m.hits.every( Boolean ), `HOME-01 [${ where }] the button's bottom strip under the pause target stays the button's (${ JSON.stringify( m.hits ) })` );
	if ( m.highlight !== '' ) {
		// A WebKit build without the property (it is iOS Safari's) leaves it to the Chromium run.
		check( /rgba\(0, 0, 0, 0\)|transparent/.test( m.highlight ), `HOME-01 [${ where }] the pause button draws no grey tap highlight over the button (${ m.highlight })` );
	}
	check( m.ringClear > 0, `NFR-11 [${ where }] the pause button's keyboard ring stays clear of the button (${ m.ringClear } px)` );
	const pause = view.locator( '.mizzey-hero__pause' );
	await pause.tap();
	await view.waitForTimeout( 250 );
	const ring = await pause.evaluate( ( e ) => ( { fv: e.matches( ':focus-visible' ), outline: getComputedStyle( e ).outlineStyle } ) );
	check( ! ring.fv && ring.outline === 'none', `NFR-11 [${ where }] a tap on the pause button leaves no focus ring (${ JSON.stringify( ring ) })` );
	await pause.tap();
}

const types = ( view ) => view.sections.map( ( s ) => s.type );
const find = ( view, type ) => view.sections.find( ( s ) => s.type === type ) || { text: '', heading: '', links: [], images: 0 };

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found. Run `npx playwright install chromium`, or set PLAYWRIGHT_DIR.' } ) );
		process.exit( 2 );
	}
	const browser = await playwright.pw.chromium.launch( playwright.launch );
	const base = new URL( plan.base );
	const editor = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	await editor.addCookies( plan.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: base.hostname, path: '/' } ) ) );
	const visitor = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	const page = await editor.newPage();
	page.setDefaultTimeout( TIMEOUT );
	const errors = [];
	let otherErrors = 0;
	page.on( 'pageerror', ( e ) => ( /mizzey|Blocks\//i.test( `${ e.message } ${ e.stack || '' }` ) ? errors.push( e.message ) : otherErrors++ ) );
	let step = 'start';
	try {
		// ---- The inserter offers the whole library on the front page ---------------------------------------------
		step = 'inserter';
		let canvas = await openEditor( page, plan.en.edit );
		const offered = await inserterNames( page );
		check( JSON.stringify( [ ...offered ].sort() ) === JSON.stringify( [ ...plan.library ].sort() ), `FR-003 the front page's inserter offers the library's sections and nothing else: ${ offered.join( ', ' ) }` );

		// ---- Every section added and edited in English ---------------------------------------------------------------
		step = 'hero';
		const hero = await insert( page, 'mizzey/hero', 'Hero' );
		await typeIn( canvas, page, hero, 'Slide 1 headline', `${ T } Hero one` );
		await typeIn( canvas, page, hero, 'Slide 1 line', 'Authentic products, guaranteed' );
		await typeIn( canvas, page, hero, 'Slide 1 button', 'Shop now' );
		await select( canvas, page, hero );
		await sidebarText( page, 'Slide 1 button link', plan.link );
		await chooseImage( page, 'Choose slide 1 image', plan.image );
		await page.waitForFunction( ( id ) => ( wp.data.select( 'core/block-editor' ).getBlockAttributes( id ).slides || [] )[ 0 ].imageId > 0, hero, { timeout: TIMEOUT } );
		const shownImage = await canvas.locator( `[data-block="${ hero }"] img.mizzey-hero__image` ).first().waitFor( { timeout: 20000 } ).then( () => true ).catch( () => false );
		check( shownImage, 'HOME-01 the chosen image shows in the editor\'s hero' );
		await page.locator( '.block-editor-block-inspector' ).getByRole( 'button', { name: 'Add a slide' } ).click();
		await typeIn( canvas, page, hero, 'Slide 2 headline', `${ T } Hero two` );
		await select( canvas, page, hero );
		await sidebarSelect( page, 'Slide 2 ground', 'dark' );

		step = 'products';
		const products = await insert( page, 'mizzey/products', 'Products' );
		await select( canvas, page, products );
		await sidebarText( page, 'Heading', `${ T } Trending` );
		await sidebarSelect( page, 'Products from', 'badge' );
		await sidebarSelect( page, 'Badge', 'trending' );

		step = 'offer';
		const offer = await insert( page, 'mizzey/offer-banner', 'Offer' );
		await typeIn( canvas, page, offer, 'Banner tag', 'New' );
		await typeIn( canvas, page, offer, 'Banner headline', `${ T } Offer` );
		await typeIn( canvas, page, offer, 'Banner text', 'This month\'s arrivals. All original stock.' );
		await typeIn( canvas, page, offer, 'Banner button', 'See what\'s new' );
		await select( canvas, page, offer );
		await sidebarText( page, 'Banner button link', plan.link );
		await chooseImage( page, 'Choose the banner image', plan.image );

		step = 'term sections';
		const cats = await insert( page, 'mizzey/category-shortcuts', 'Category' );
		await select( canvas, page, cats );
		await sidebarText( page, 'Heading', `${ T } Shop by category` );
		const colls = await insert( page, 'mizzey/featured-collections', 'Collections' );
		await select( canvas, page, colls );
		await sidebarText( page, 'Heading', `${ T } Featured` );

		step = 'deals';
		const deals = await insert( page, 'mizzey/deals', 'Deals' );
		await select( canvas, page, deals );
		await sidebarText( page, 'Heading', `${ T } Deals` );
		await sidebarText( page, 'Line', 'Up to 25% off this week' );
		await sidebarSelect( page, 'Products from', 'sale' );

		step = 'brands and sign-up';
		const brands = await insert( page, 'mizzey/brand-highlights', 'Brand' );
		await select( canvas, page, brands );
		await sidebarText( page, 'Heading', `${ T } Brands` );
		await sidebarText( page, 'All brands label', 'All brands' );
		await sidebarText( page, 'All brands link', plan.link );
		const signup = await insert( page, 'mizzey/signup', 'Sign-up' );
		await typeIn( canvas, page, signup, 'Sign-up headline', `${ T } Stay in touch` );
		await typeIn( canvas, page, signup, 'Sign-up text', 'One short email when something worth knowing lands.' );
		await typeIn( canvas, page, signup, 'Sign-up button', 'Sign up' );

		check( await canvas.locator( `[data-block="${ products }"] .mizzey-card` ).count() >= 2, 'SSC-13 the editor shows the products section with its cards, as the storefront will' );
		await save( page );
		const first = [ 'hero', 'products', 'offer-banner', 'category-shortcuts', 'featured-collections', 'deals', 'brand-highlights', 'signup' ];
		let live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( types( live ) ) === JSON.stringify( first ), `SSC-02 [en] the storefront shows every section in the editor's order: ${ types( live ).join( ', ' ) }` );
		check( find( live, 'hero' ).heading.includes( `${ T } Hero one` ) && find( live, 'hero' ).text.includes( `${ T } Hero two` ) && find( live, 'hero' ).links.includes( plan.link ) && find( live, 'hero' ).images >= 1, 'HOME-01 the hero shows both slides, the typed words, the button\'s link and the chosen image' );
		check( plan.products.every( ( n ) => find( live, 'products' ).text.includes( n ) ) && ! find( live, 'products' ).text.includes( plan.sale ) && find( live, 'products' ).heading === `${ T } Trending`, `HOME-02 the Trending section shows the products the admin flagged, under its heading: ${ find( live, 'products' ).heading }` );
		check( find( live, 'offer-banner' ).heading === `${ T } Offer` && find( live, 'offer-banner' ).links.includes( plan.link ), 'HOME-07 the offer banner shows its words and its button\'s link' );
		check( find( live, 'category-shortcuts' ).text.includes( plan.category.en ), 'HOME-05 the category shortcuts show the category set to show on the home page' );
		check( find( live, 'featured-collections' ).heading === `${ T } Featured` && find( live, 'featured-collections' ).text.includes( `${ T } Gym bag` ), 'HOME-04 the featured collections show the collection set to show on the home page' );
		check( find( live, 'deals' ).text.includes( plan.sale ) && find( live, 'deals' ).text.includes( 'Up to 25% off this week' ), 'HOME-07 the deals panel shows the products on sale and its line' );
		check( find( live, 'brand-highlights' ).heading === `${ T } Brands`, 'HOME-06 the brand highlights show their heading' );
		check( find( live, 'signup' ).heading === `${ T } Stay in touch` && find( live, 'signup' ).links.length === 0, 'HOME-09 the sign-up prompt shows its words and its own form, with no link away (t119 checks the form)' );

		// ---- Reordered, previewed, then published -------------------------------------------------------------------
		step = 'reorder and preview';
		await select( canvas, page, offer );
		await canvas.locator( `[data-block="${ offer }"]` ).getByRole( 'textbox', { name: 'Banner headline', exact: true } ).click();
		await page.locator( 'button[aria-label="Move up"]' ).first().click();
		await page.waitForFunction( ( [ o, p ] ) => {
			const order = wp.data.select( 'core/block-editor' ).getBlockOrder();
			return order.indexOf( o ) < order.indexOf( p );
		}, [ offer, products ], { timeout: 20000 } ).catch( () => {} );
		const moved = [ 'hero', 'offer-banner', 'products', 'category-shortcuts', 'featured-collections', 'deals', 'brand-highlights', 'signup' ];
		let preview = null;
		const toggle = page.locator( '.editor-preview-dropdown__toggle' );
		if ( await toggle.count() ) {
			await toggle.first().click();
			const [ popup ] = await Promise.all( [ editor.waitForEvent( 'page', { timeout: TIMEOUT } ), page.getByRole( 'menuitem', { name: /Preview in new tab/ } ).click() ] );
			await popup.waitForURL( /[?&]preview(?:_id)?=/, { timeout: TIMEOUT } );
			await popup.waitForLoadState( 'domcontentloaded', { timeout: TIMEOUT } );
			preview = popup.url();
			await popup.close();
		}
		const previewView = preview ? await sectionsOn( editor, preview ) : { sections: [] };
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( types( previewView ) ) === JSON.stringify( moved ), `SSC-13 the preview shows the new order before it is published: ${ types( previewView ).join( ', ' ) }` );
		check( JSON.stringify( types( live ) ) === JSON.stringify( first ), 'SSC-13 the live page keeps the old order until then' );
		await save( page );
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( types( live ) ) === JSON.stringify( moved ), `HOME-11 published, the storefront shows the new order: ${ types( live ).join( ', ' ) }` );

		// ---- The boards' numbers, every section shown, at both widths -------------------------------------------------
		step = 'measure';
		for ( const width of [ 1440, 390 ] ) {
			const ctx = await browser.newContext( { viewport: { width, height: width > 600 ? 900 : 844 }, isMobile: width < 600, hasTouch: width < 600 } );
			const view = await ctx.newPage();
			await view.goto( plan.en.view, { waitUntil: 'load', timeout: TIMEOUT } );
			await view.waitForTimeout( 1000 );
			const got = await view.evaluate( measure );
			for ( const [ key, want ] of Object.entries( BOARD[ width ] ) ) {
				const value = got[ key ];
				check( typeof value === 'number' && Math.abs( value - want ) <= 1, `K-1 [en ${ width }] ${ key }: ${ value } against the board's ${ want }` );
			}
			await view.screenshot( { path: path.join( plan.shots, `t110-all-${ width }.png` ), fullPage: true } ).catch( () => {} );
			await ctx.close();
		}

		// ---- Hidden, its content kept -------------------------------------------------------------------------------
		step = 'hide';
		await select( canvas, page, deals );
		const show = page.getByRole( 'checkbox', { name: 'Show on the site', exact: true } );
		await show.click();
		await save( page );
		live = await sectionsOn( visitor, plan.en.view );
		check( ! types( live ).includes( 'deals' ) && types( live ).length === moved.length - 1, `SSC-03 the hidden section is no longer on the storefront: ${ types( live ).join( ', ' ) }` );
		canvas = await openEditor( page, plan.en.edit );
		const kept = await page.evaluate( () => wp.data.select( 'core/block-editor' ).getBlocks().filter( ( b ) => b.name === 'mizzey/deals' ).map( ( b ) => ( { visible: b.attributes.visible, heading: b.attributes.heading, source: b.attributes.source } ) ) );
		check( kept.length === 1 && kept[ 0 ].visible === false && kept[ 0 ].heading === `${ T } Deals` && kept[ 0 ].source === 'sale', `SSC-03 reopened, the editor still holds the hidden section's content: ${ JSON.stringify( kept ) }` );

		// ---- The Arabic front page: its own sections and words -------------------------------------------------------
		step = 'Arabic';
		canvas = await openEditor( page, plan.ar.edit );
		const arHero = await insert( page, 'mizzey/hero', 'Hero' );
		await typeIn( canvas, page, arHero, 'Slide 1 headline', `${ T } الأصلي` );
		await typeIn( canvas, page, arHero, 'Slide 1 line', 'منتجات أصلية بضمان ميزي' );
		const arProducts = await insert( page, 'mizzey/products', 'Products' );
		await select( canvas, page, arProducts );
		await sidebarText( page, 'Heading', `${ T } الأكثر رواجًا` );
		await sidebarSelect( page, 'Products from', 'badge' );
		await sidebarSelect( page, 'Badge', 'trending' );
		const arCats = await insert( page, 'mizzey/category-shortcuts', 'Category' );
		await select( canvas, page, arCats );
		await sidebarText( page, 'Heading', `${ T } تسوّق حسب القسم` );
		await save( page );
		const ar = await sectionsOn( visitor, plan.ar.view );
		check( ar.dir === 'rtl' && JSON.stringify( types( ar ) ) === JSON.stringify( [ 'hero', 'products', 'category-shortcuts' ] ), `SSC-12 [ar] the Arabic storefront shows its own sections, right to left: ${ ar.dir } ${ types( ar ).join( ', ' ) }` );
		check( find( ar, 'hero' ).heading === `${ T } الأصلي` && find( ar, 'products' ).heading === `${ T } الأكثر رواجًا`, 'SSC-12 [ar] each Arabic section shows its Arabic words' );
		check( plan.products.every( ( n ) => find( ar, 'products' ).text.includes( n ) ) && find( ar, 'category-shortcuts' ).text.includes( plan.category.ar ), 'SSC-12 [ar] the Arabic sections read the same rules: the trending products and the Arabic category name' );
		live = await sectionsOn( visitor, plan.en.view );
		check( JSON.stringify( types( live ) ) === JSON.stringify( moved.filter( ( t ) => t !== 'deals' ) ), 'SSC-12 [en] the English storefront is unchanged by the Arabic edit' );

		// ---- The storefront at phone and desktop width, in both languages -------------------------------------------
		step = 'storefront';
		for ( const [ lang, width ] of [ [ 'en', 1440 ], [ 'en', 390 ], [ 'ar', 1440 ], [ 'ar', 390 ] ] ) {
			const ctx = await browser.newContext( { viewport: { width, height: width > 600 ? 900 : 844 }, isMobile: width < 600, hasTouch: width < 600 } );
			const view = await ctx.newPage();
			await view.addInitScript( () => {
				window.__lcp = null;
				new PerformanceObserver( ( list ) => {
					const last = list.getEntries().pop();
					window.__lcp = last && last.element ? ( last.element.className && last.element.className.baseVal === undefined ? last.element.className : last.element.tagName ) : null;
				} ).observe( { type: 'largest-contentful-paint', buffered: true } );
			} );
			await view.goto( plan[ lang ].view, { waitUntil: 'load', timeout: TIMEOUT } );
			await view.waitForTimeout( 1500 );
			const m = await view.evaluate( () => {
				const img = document.querySelector( '.mizzey-hero__slide img.mizzey-hero__image' );
				const pre = document.querySelector( 'head link[rel="preload"][as="image"]' );
				return {
					sideways: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					img: img ? { lazy: img.getAttribute( 'loading' ) === 'lazy', priority: img.getAttribute( 'fetchpriority' ), w: img.getAttribute( 'width' ), h: img.getAttribute( 'height' ), src: img.currentSrc } : null,
					preload: pre ? { href: pre.href, srcset: pre.getAttribute( 'imagesrcset' ) || '' } : null,
					lcp: window.__lcp,
				};
			} );
			check( m.sideways <= 0, `K-2 [${ lang } ${ width }] the home page never scrolls sideways (${ m.sideways } px over)` );
			if ( lang === 'en' ) {
				const preloaded = !! m.preload && !! m.img && ( m.preload.href === m.img.src || m.preload.srcset.split( ',' ).some( ( c ) => new URL( c.trim().split( ' ' )[ 0 ], plan.base ).href === m.img.src ) );
				check( preloaded, `K-6 [${ lang } ${ width }] the hero's first image is preloaded from the head: ${ JSON.stringify( m.preload ) } for ${ m.img && m.img.src }` );
				check( !! m.img && ! m.img.lazy && m.img.priority === 'high' && m.img.w && m.img.h, `K-6 [${ lang } ${ width }] the first slide's image is not lazy, has a high priority and its dimensions: ${ JSON.stringify( m.img ) }` );
				check( /mizzey-hero__image/.test( String( m.lcp ) ), `K-6 [${ lang } ${ width }] the hero's first image is the Largest Contentful Paint: ${ m.lcp }` );
			}
			if ( width === 1440 || lang === 'ar' ) {
				await view.screenshot( { path: path.join( plan.shots, `t110-${ lang }-${ width }.png` ), fullPage: true } ).catch( () => {} );
			}
			if ( lang === 'en' && width === 390 ) {
				await view.screenshot( { path: path.join( plan.shots, `t110-${ lang }-${ width }.png` ), fullPage: true } ).catch( () => {} );
			}
			// The Arabic front page this scenario builds has a hero with no button; the Arabic controls are checked on a
			// slider with one (the card fix's own page).
			if ( width === 390 && lang === 'en' ) {
				await heroControls( view, `${ lang } ${ width } chromium` );
			}
			await ctx.close();
		}
		// The same controls in WebKit (the owner tests on an iPhone).
		const webkit = await playwright.pw.webkit.launch( { headless: true } ).catch( () => null );
		check( !! webkit, 'WebKit launches for the phone checks' );
		if ( webkit ) {
			for ( const lang of [ 'en' ] ) {
				const ctx = await webkit.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
				const view = await ctx.newPage();
				await view.goto( plan[ lang ].view, { waitUntil: 'load', timeout: TIMEOUT } );
				await view.waitForTimeout( 1500 );
				await heroControls( view, `${ lang } 390 webkit` );
				await view.locator( '.mizzey-hero' ).screenshot( { path: path.join( plan.shots, `t110-hero-${ lang }-390-webkit.png` ) } ).catch( () => {} );
				await ctx.close();
			}
			await webkit.close();
		}

		// ---- The slider's motion ------------------------------------------------------------------------------------
		step = 'slider';
		const active = ( p ) => p.evaluate( () => {
			const s = document.querySelector( '.mizzey-hero__slide.is-active' );
			return s ? Number( s.dataset.index ) : -1;
		} );
		const moving = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
		const slide = await moving.newPage();
		await slide.goto( plan.en.view, { waitUntil: 'load', timeout: TIMEOUT } );
		await slide.mouse.move( 5, 890 );
		const at0 = await active( slide );
		await slide.waitForTimeout( 4000 );
		const at4 = await active( slide );
		await slide.waitForTimeout( 3500 );
		const at7 = await active( slide );
		check( at0 === 0 && at4 === 0 && at7 === 1, `HOME-01 a slide stays at least six seconds, then the next comes in (${ at0 }, ${ at4 }, ${ at7 })` );
		await slide.locator( '.mizzey-hero__viewport' ).hover();
		const held = await active( slide );
		await slide.waitForTimeout( 7000 );
		check( await active( slide ) === held, 'HOME-01 the slider holds while it is hovered' );
		await slide.mouse.move( 5, 890 );
		await slide.locator( '.mizzey-hero__pause' ).click();
		const label = await slide.locator( '.mizzey-hero__pause' ).getAttribute( 'aria-label' );
		const paused = await active( slide );
		await slide.mouse.move( 5, 890 );
		await slide.waitForTimeout( 7000 );
		check( label === 'Play the slides' && await active( slide ) === paused, `HOME-01 the pause button stops the slider and offers to play it (${ label })` );
		await slide.locator( '.mizzey-hero__arrow' ).last().click();
		await slide.waitForTimeout( 800 );
		check( await active( slide ) === ( paused + 1 ) % 2, 'HOME-01 the arrow shows the next slide' );
		// Play, pressed with the mouse, leaves the keyboard focus on the button: it still runs the slides at once and
		// again a slide later, over the focus hold of that moment.
		const beforePlay = await active( slide );
		await slide.locator( '.mizzey-hero__pause' ).click();
		await slide.mouse.move( 5, 890 );
		await slide.waitForTimeout( 800 );
		const onPlay = await active( slide );
		const playLabel = await slide.locator( '.mizzey-hero__pause' ).getAttribute( 'aria-label' );
		const focusOnButton = await slide.evaluate( () => !! document.activeElement && document.activeElement.classList.contains( 'mizzey-hero__pause' ) );
		await slide.waitForTimeout( 6800 );
		const afterPlay = await active( slide );
		check( focusOnButton && onPlay === ( beforePlay + 1 ) % 2 && afterPlay === ( onPlay + 1 ) % 2 && playLabel === 'Pause the slides', `HOME-01 Play restarts the slider at once and within one slide's time, with the focus on the button (${ beforePlay }, ${ onPlay }, ${ afterPlay }, ${ playLabel }, focus on the button: ${ focusOnButton })` );
		// The next hold begins: the focus moves to an arrow in the hero, and the slider holds again.
		await slide.locator( '.mizzey-hero__arrow' ).first().focus();
		const focusHeld = await active( slide );
		await slide.waitForTimeout( 7000 );
		check( await active( slide ) === focusHeld, 'HOME-01 after Play, the focus elsewhere in the hero holds the slider again' );
		await slide.locator( '.mizzey-hero__viewport' ).hover();
		const hoverHeld = await active( slide );
		await slide.waitForTimeout( 7000 );
		check( await active( slide ) === hoverHeld, 'HOME-01 after Play, hovering the hero holds the slider again' );
		await moving.close();
		const calm = await browser.newContext( { viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' } );
		const still = await calm.newPage();
		await still.goto( plan.en.view, { waitUntil: 'load', timeout: TIMEOUT } );
		await still.mouse.move( 5, 890 );
		await still.waitForTimeout( 7500 );
		const calmLabel = await still.locator( '.mizzey-hero__pause' ).getAttribute( 'aria-label' );
		check( await active( still ) === 0 && calmLabel === 'Play the slides', `K-5 under reduced motion the slider starts still and its button offers to play it (${ calmLabel })` );
		await still.locator( '.mizzey-hero__pause' ).click();
		await still.mouse.move( 5, 890 );
		await still.waitForTimeout( 800 );
		const calmOn = await active( still );
		const calmPlaying = await still.locator( '.mizzey-hero__pause' ).getAttribute( 'aria-label' );
		await still.waitForTimeout( 6800 );
		const calmNext = await active( still );
		check( calmOn === 1 && calmNext === 0 && calmPlaying === 'Pause the slides', `K-5 under reduced motion, an explicit Play runs the slides and the button then offers to pause (${ calmOn }, ${ calmNext }, ${ calmPlaying })` );
		await still.locator( '.mizzey-hero__pause' ).click();
		const calmStopped = await active( still );
		await still.waitForTimeout( 7000 );
		check( await active( still ) === calmStopped && await still.locator( '.mizzey-hero__pause' ).getAttribute( 'aria-label' ) === 'Play the slides', 'K-5 under reduced motion, Pause stops it again and the button offers to play' );
		await calm.close();

		check( errors.length === 0, `no script error from the library's scripts${ errors.length ? ': ' + errors.slice( 0, 3 ).join( ' | ' ) : '' } (other script errors in this editor: ${ otherErrors })` );
	} catch ( error ) {
		check( false, `stopped at "${ step }": ${ String( error && error.message ).split( '\n' )[ 0 ] }` );
		await page.screenshot( { path: path.join( plan.shots, 't110-failure.png' ) } ).catch( () => {} );
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks } ) );
} )();

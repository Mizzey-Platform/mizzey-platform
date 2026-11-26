#!/usr/bin/env node
/**
 * On a phone every control acts on the first tap, the drawers move, and the sliders hold under a finger (spec 066,
 * K-5 and K-6). Driven by t118, which makes the products and the page and passes a JSON plan as the only argument
 * (plan.engine is "chromium" or "webkit"); prints one JSON object: { checks: [{ ok, what }] }, or { skipped } when
 * the engine is not installed.
 *
 * The phone is 390 by 844 with touch. In Chromium the network is slowed to 150 ms and 1.6 Mbps and the processor four
 * times; WebKit has no such control here. In both, the Interactivity runtime is held back five seconds on the runs
 * that tap early, which stands in for the longer wait of a real phone on a real network: the controls are on the
 * screen and tappable before the page's scripts have started.
 *
 * Playwright is test tooling on the developer's machine, not a dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

process.on( 'unhandledRejection', () => {} );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const engine = plan.engine || 'chromium';
const checks = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const TIMEOUT = 60000;
const MENU = '.mizzey-header__menu';
const SEARCH = '.mizzey-header__search-toggle';
const CLOSE = '#mizzey-menu .mizzey-menu__close:visible';
const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

/** A Playwright whose browser for this engine is installed. */
function playwright() {
	if ( engine === 'chromium' ) {
		return loadPlaywright();
	}
	const roots = [ path.join( os.homedir(), 'AppData', 'Local', 'npm-cache', '_npx' ), path.join( os.homedir(), '.npm', '_npx' ) ];
	const candidates = [ process.env.PLAYWRIGHT_DIR, 'playwright' ].filter( Boolean );
	for ( const root of roots ) {
		if ( fs.existsSync( root ) ) {
			fs.readdirSync( root ).forEach( ( dir ) => candidates.push( path.join( root, dir, 'node_modules', 'playwright' ) ) );
		}
	}
	for ( const candidate of candidates ) {
		try {
			const pw = require( candidate );
			if ( fs.existsSync( pw[ engine ].executablePath() ) ) {
				return { pw, launch: { headless: true } };
			}
		} catch ( error ) {
			// Try the next one.
		}
	}
	return null;
}

/** What the page did: dialogs opened, each control's aria-expanded changes. */
function instrument() {
	window.__opens = {};
	// Whether the first tap came before the Interactivity runtime had even arrived: nothing on the page could act on it.
	addEventListener( 'pointerup', () => {
		if ( window.__tapEarly === undefined ) {
			window.__tapEarly = ! performance.getEntriesByType( 'resource' ).some( ( e ) => e.name.includes( 'script-modules/interactivity' ) );
		}
	}, true );
	const show = HTMLDialogElement.prototype.showModal;
	HTMLDialogElement.prototype.showModal = function () {
		window.__opens[ this.id ] = ( window.__opens[ this.id ] || 0 ) + 1;
		return show.call( this );
	};
	window.__expanded = [];
	document.addEventListener( 'DOMContentLoaded', () => {
		new MutationObserver( ( list ) => list.forEach( ( m ) => window.__expanded.push( m.target.className + '=' + m.target.getAttribute( 'aria-expanded' ) ) ) )
			.observe( document.body, { subtree: true, attributes: true, attributeFilter: [ 'aria-expanded' ] } );
		// The strip's message life, shortened for the test only.
		const style = document.createElement( 'style' );
		style.textContent = '.mizzey-announcement{--mizzey-motion-life-strip:1500ms}';
		document.head.appendChild( style );
	} );
}

async function phone( browser, { slow = false, hold = false, reduced = false, lang = 'en' } = {} ) {
	const context = await browser.newContext( {
		viewport: { width: 390, height: 844 },
		isMobile: engine !== 'firefox',
		hasTouch: true,
		deviceScaleFactor: 2,
		reducedMotion: reduced ? 'reduce' : 'no-preference',
		locale: lang === 'ar' ? 'ar-EG' : 'en-GB',
	} );
	const page = await context.newPage();
	page.setDefaultTimeout( TIMEOUT );
	await page.addInitScript( instrument );
	if ( hold ) {
		await page.route( '**/script-modules/interactivity/**', async ( route ) => {
			await sleep( 5000 );
			await route.continue();
		} );
	}
	if ( slow && engine === 'chromium' ) {
		const cdp = await context.newCDPSession( page );
		await cdp.send( 'Network.enable' );
		await cdp.send( 'Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: ( 1.6 * 1024 * 1024 ) / 8, uploadThroughput: ( 750 * 1024 ) / 8 } );
		await cdp.send( 'Emulation.setCPUThrottlingRate', { rate: 4 } );
	}
	return { context, page };
}

const ready = ( page ) => page.waitForSelector( 'html[data-mizzey-ready]', { state: 'attached', timeout: 30000 } ).then( () => true, () => false );
const tapEarly = ( page ) => page.evaluate( () => window.__tapEarly === true );
const opens = ( page, id ) => page.evaluate( ( id ) => ( window.__opens || {} )[ id ] || 0, id );
const isOpen = ( page, id ) => page.evaluate( ( id ) => { const d = document.getElementById( id ); return !! ( d && d.open ); }, id );
const expanded = ( page, sel ) => page.evaluate( ( sel ) => { const b = document.querySelector( sel ); return b ? b.getAttribute( 'aria-expanded' ) : null; }, sel );
const searchFlips = ( page ) => page.evaluate( () => window.__expanded.filter( ( e ) => e.includes( 'mizzey-header__search-toggle' ) ) );
const pickerOf = ( page, id ) => page.evaluate( ( id ) => document.querySelector( `.mizzey-card[data-product-id="${ id }"] .mizzey-card__add` ).getAttribute( 'aria-controls' ), id );
const count = ( page ) => page.evaluate( () => { const c = document.querySelector( '.mizzey-header__cart .mizzey-count' ); return c && ! c.hidden ? c.textContent.trim() : '0'; } );
const card = ( id ) => `.mizzey-card[data-product-id="${ id }"] .mizzey-card__add`;

/** A touch the browser sends no click for: pointer down and up on the control's centre, nothing else. */
function touchOnly( page, sel ) {
	return page.evaluate( ( sel ) => {
		const el = document.querySelector( sel );
		const r = el.getBoundingClientRect();
		const at = { bubbles: true, cancelable: true, composed: true, pointerId: 41, pointerType: 'touch', isPrimary: true, clientX: r.left + r.width / 2, clientY: r.top + r.height / 2 };
		el.dispatchEvent( new PointerEvent( 'pointerdown', at ) );
		el.dispatchEvent( new PointerEvent( 'pointerup', at ) );
	}, sel );
}

/** Sample the menu panel every frame for a while, started before the action that moves it. */
function startSampling( page, ms ) {
	return page.evaluate( ( ms ) => {
		window.__samples = [];
		// The animations the panel ran, from its own events: they are sent whatever the frame rate.
		window.__ran = [];
		const panel = document.querySelector( '#mizzey-menu .mizzey-overlay__panel' );
		if ( panel && ! panel.__t118 ) {
			panel.__t118 = true;
			panel.addEventListener( 'animationend', ( e ) => e.target === panel && window.__ran.push( e.animationName + ( document.getElementById( 'mizzey-menu' ).open ? ':open' : ':closed' ) ) );
		}
		const t0 = performance.now();
		const frame = () => {
			const d = document.getElementById( 'mizzey-menu' );
			const p = d && d.querySelector( '.mizzey-overlay__panel' );
			const r = p.getBoundingClientRect();
			window.__samples.push( { t: Math.round( performance.now() - t0 ), open: d.open, x: Math.round( r.left ), w: Math.round( r.width ), o: Number( getComputedStyle( p ).opacity ).toFixed( 2 ), a: p.getAnimations().map( ( a ) => a.constructor.name ).join( ',' ), n: getComputedStyle( p ).animationName, c: d.classList.contains( 'is-closing' ) } );
			if ( performance.now() - t0 < ms ) {
				requestAnimationFrame( frame );
			}
		};
		requestAnimationFrame( frame );
	}, ms );
}
const samples = ( page ) => page.evaluate( () => window.__samples );
const ran = ( page ) => page.evaluate( () => window.__ran || [] );
const between = ( list, key, a, b ) => new Set( list.map( ( s ) => Number( s[ key ] ) ).filter( ( v ) => v > Math.min( a, b ) + 1 && v < Math.max( a, b ) - 1 ) ).size;
const between01 = ( list ) => new Set( list.map( ( s ) => Number( s.o ) ).filter( ( v ) => v > 0.02 && v < 0.98 ) ).size;

async function earlyTaps( browser ) {
	// The menu.
	let { context, page } = await phone( browser, { slow: true, hold: true } );
	await page.goto( plan.en, { waitUntil: 'commit' } );
	await page.locator( MENU ).waitFor( { state: 'visible' } );
	await page.locator( MENU ).tap();
	let early = await tapEarly( page );
	let hydrated = await ready( page );
	await sleep( 1000 );
	check( early && hydrated && ( await isOpen( page, 'mizzey-menu' ) ) && ( await opens( page, 'mizzey-menu' ) ) === 1, `the menu tapped the moment it shows, before the scripts start, opens once (early ${ early }, ready ${ hydrated }, opened ${ await opens( page, 'mizzey-menu' ) })` );
	await context.close();

	// A card's add: a variable product's options, then a simple product into the cart.
	( { context, page } = await phone( browser, { slow: true, hold: true } ) );
	await page.goto( plan.en, { waitUntil: 'commit' } );
	await page.locator( card( plan.var ) ).waitFor( { state: 'visible' } );
	await page.locator( card( plan.var ) ).tap();
	early = await tapEarly( page );
	hydrated = await ready( page );
	await sleep( 1000 );
	const picker = await pickerOf( page, plan.var );
	check( early && hydrated && ( await isOpen( page, picker ) ) && ( await opens( page, picker ) ) === 1, `a card's add tapped the moment it shows opens its options once (early ${ early }, opened ${ await opens( page, picker ) })` );
	await context.close();

	( { context, page } = await phone( browser, { slow: true, hold: true } ) );
	await page.goto( plan.en, { waitUntil: 'commit' } );
	await page.locator( card( plan.simple ) ).waitFor( { state: 'visible' } );
	await page.locator( card( plan.simple ) ).tap();
	early = await tapEarly( page );
	hydrated = await ready( page );
	await page.waitForFunction( () => document.querySelector( '.mizzey-header__cart .mizzey-count:not([hidden])' ), null, { timeout: 20000 } ).catch( () => null );
	await sleep( 1500 );
	check( early && hydrated && ( await count( page ) ) === '1', `a simple product's add tapped the moment it shows puts one in the cart (early ${ early }, count ${ await count( page ) })` );
	await context.close();

	// The search, on the product page.
	( { context, page } = await phone( browser, { slow: true, hold: true } ) );
	await page.goto( plan.product, { waitUntil: 'commit' } );
	await page.locator( SEARCH ).waitFor( { state: 'visible' } );
	await page.locator( SEARCH ).tap();
	early = await tapEarly( page );
	hydrated = await ready( page );
	await sleep( 1000 );
	const flips = await searchFlips( page );
	check( early && hydrated && ( await expanded( page, SEARCH ) ) === 'true' && flips.length === 1, `the search tapped the moment it shows opens once (early ${ early }, changes ${ JSON.stringify( flips ) })` );
	await context.close();
}

async function touches( browser ) {
	let { context, page } = await phone( browser );
	await page.goto( plan.en, { waitUntil: 'load' } );
	await ready( page );
	await touchOnly( page, MENU );
	await sleep( 700 );
	check( await isOpen( page, 'mizzey-menu' ), 'the menu opens on a touch the browser sends no click for' );
	await context.close();

	( { context, page } = await phone( browser ) );
	await page.goto( plan.en, { waitUntil: 'load' } );
	await ready( page );
	await page.locator( MENU ).tap();
	await sleep( 900 );
	check( ( await isOpen( page, 'mizzey-menu' ) ) && ( await opens( page, 'mizzey-menu' ) ) === 1, `a full tap opens the menu once and it stays open (opened ${ await opens( page, 'mizzey-menu' ) })` );
	await context.close();

	( { context, page } = await phone( browser ) );
	await page.goto( plan.en, { waitUntil: 'load' } );
	await ready( page );
	const picker = await pickerOf( page, plan.var );
	await touchOnly( page, card( plan.var ) );
	await sleep( 700 );
	check( await isOpen( page, picker ), 'a card\'s options open on a touch the browser sends no click for' );
	await page.keyboard.press( 'Escape' );
	await sleep( 700 );
	await page.locator( card( plan.var ) ).tap();
	await sleep( 900 );
	check( ( await isOpen( page, picker ) ) && ( await opens( page, picker ) ) === 2, `a full tap on a card's add opens its options once (opened ${ await opens( page, picker ) } in two presses)` );
	await context.close();

	( { context, page } = await phone( browser ) );
	await page.goto( plan.product, { waitUntil: 'load' } );
	await ready( page );
	await touchOnly( page, SEARCH );
	await sleep( 500 );
	check( ( await expanded( page, SEARCH ) ) === 'true', 'the search opens on a touch the browser sends no click for' );
	await page.locator( SEARCH ).tap();
	await sleep( 700 );
	const flips = await searchFlips( page );
	check( ( await expanded( page, SEARCH ) ) === 'false' && flips.length === 2, `a full tap on the open search closes it, once (changes ${ JSON.stringify( flips ) })` );
	// Keyboard: Enter on the toggle still works through the click.
	await page.locator( SEARCH ).focus();
	await page.keyboard.press( 'Enter' );
	await sleep( 400 );
	check( ( await expanded( page, SEARCH ) ) === 'true', 'the keyboard opens the search with Enter, as before' );
	await context.close();
}

async function motion( browser, lang ) {
	const url = plan[ lang ];
	let { context, page } = await phone( browser, { lang } );
	await page.goto( url, { waitUntil: 'load' } );
	await ready( page );
	await startSampling( page, 900 );
	await page.locator( MENU ).tap();
	await sleep( 1000 );
	let list = ( await samples( page ) ).filter( ( s ) => s.open );
	const last = list[ list.length - 1 ] || {};
	// The panel's width is known once it shows: in place it starts at 0 (English) or 390 less its width (Arabic).
	const home = lang === 'ar' ? 390 - ( last.w || 0 ) : 0;
	const away = lang === 'ar' ? 390 : -( last.w || 0 );
	check( list.length && Math.abs( last.x - home ) <= 1 && between( list, 'x', away, home ) >= 3, `[${ lang }] the phone menu slides in from the ${ lang === 'ar' ? 'right' : 'left' } over several frames (${ between( list, 'x', away, home ) } positions between, ends at ${ last.x })` );
	check( list.some( ( s ) => s.a.includes( 'CSSAnimation' ) ), `[${ lang }] the way in is a CSS animation, which every engine starts when the dialog opens (seen: ${ [ ...new Set( list.map( ( s ) => s.a ) ) ].join( ' | ' ) || 'none' })` );

	await startSampling( page, 700 );
	await page.locator( CLOSE ).first().tap();
	await sleep( 800 );
	list = await samples( page );
	const moving = list.filter( ( s ) => s.open );
	// The way out is 240 ms: on a loaded machine a browser may paint few frames of it, so the check is that the
	// out animation ran while the dialog was still open, then the dialog closed; the frames seen are reported.
	// Headless WebKit on this machine paints the Arabic page at a few frames a second (no GPU compositing), so a 240 ms
	// way out can pass between two frames there: the panel's own animationend, sent while the dialog is still open,
	// shows the animation ran in full. The direction is checked on the frames that were painted.
	let ended = await ran( page );
	const out = ( list, name ) => list.some( ( s ) => s.c && s.n === name ) || ( engine === 'webkit' && ended.includes( `${ name }:open` ) );
	check( out( moving, 'mizzey-overlay-slide-out' ) && ! ( await isOpen( page, 'mizzey-menu' ) ), `[${ lang }] the phone menu slides out before it closes (${ between( moving, 'x', away, home ) } positions between seen)` );
	const xs = moving.filter( ( m ) => m.c ).map( ( m ) => m.x );
	check( xs.every( ( v, i ) => i === 0 || ( lang === 'ar' ? v >= xs[ i - 1 ] : v <= xs[ i - 1 ] ) ), `[${ lang }] it leaves toward its own edge, the ${ lang === 'ar' ? 'right' : 'left' } (${ xs.join( ', ' ) })` );
	check( await page.evaluate( ( sel ) => document.activeElement && document.activeElement.matches( sel ), MENU ), `[${ lang }] the focus is back on the menu button after it closes` );

	await page.locator( MENU ).tap();
	await sleep( 800 );
	await startSampling( page, 700 );
	await page.keyboard.press( 'Escape' );
	await sleep( 800 );
	list = ( await samples( page ) ).filter( ( s ) => s.open );
	ended = await ran( page );
	check( out( list, 'mizzey-overlay-slide-out' ) && ! ( await isOpen( page, 'mizzey-menu' ) ), `[${ lang }] Escape closes the menu the same way, sliding out (${ between( list, 'x', away, home ) } positions between seen)` );
	check( await page.evaluate( () => getComputedStyle( document.documentElement ).overflow !== 'hidden' ), `[${ lang }] the page scrolls again once the menu is closed` );
	await context.close();

	if ( lang !== 'en' ) {
		return;
	}
	( { context, page } = await phone( browser, { reduced: true } ) );
	await page.goto( url, { waitUntil: 'load' } );
	await ready( page );
	await startSampling( page, 500 );
	await page.locator( MENU ).tap();
	await sleep( 600 );
	list = ( await samples( page ) ).filter( ( s ) => s.open );
	check( list.length && between01( list ) >= 1 && list.every( ( s ) => Math.abs( s.x - home ) <= 1 ), `under reduced motion the menu fades in, in place (${ between01( list ) } steps of opacity)` );
	await startSampling( page, 500 );
	await page.locator( CLOSE ).first().tap();
	await sleep( 600 );
	list = ( await samples( page ) ).filter( ( s ) => s.open );
	check( list.some( ( s ) => s.c && s.n === 'mizzey-overlay-fade-out' ) && ! ( await isOpen( page, 'mizzey-menu' ) ), `under reduced motion the menu fades out, then closes (${ between01( list ) } steps of opacity seen)` );
	await context.close();
}

async function sliders( browser ) {
	const { context, page } = await phone( browser );
	await page.goto( plan.en, { waitUntil: 'load' } );
	await ready( page );
	const at = () => page.evaluate( () => ( {
		strip: [ ...document.querySelectorAll( '.mizzey-announcement__message' ) ].findIndex( ( m ) => m.classList.contains( 'is-current' ) ),
		hero: [ ...document.querySelectorAll( '.mizzey-hero__slide' ) ].findIndex( ( s ) => s.classList.contains( 'is-active' ) ),
	} ) );
	const watch = async ( ms ) => {
		const seen = [];
		for ( let t = 0; t < ms; t += 250 ) {
			seen.push( await at() );
			await sleep( 250 );
		}
		seen.push( await at() );
		return seen;
	};
	const still = ( seen, key ) => new Set( seen.map( ( s ) => s[ key ] ) ).size === 1;
	const start = await at();
	const found = await page.evaluate( () => [ document.querySelectorAll( '.mizzey-announcement__message' ).length, document.querySelectorAll( '.mizzey-hero__slide' ).length, location.href ] );
	check( start.strip === 0 && start.hero === 0, `the test page shows the strip slider and the hero slider (${ JSON.stringify( start ) }, found ${ JSON.stringify( found ) })` );
	await sleep( 2500 );
	check( ( await at() ).strip !== start.strip, 'the strip moves on by itself (its timer runs)' );

	// A finger down on the page, away from both, held seven seconds: neither moves.
	await page.evaluate( () => {
		const el = document.querySelector( '.mizzey-products, main, body' );
		const r = el.getBoundingClientRect();
		window.__finger = { bubbles: true, cancelable: true, pointerId: 52, pointerType: 'touch', isPrimary: true, clientX: r.left + 20, clientY: r.top + 20 };
		el.dispatchEvent( new PointerEvent( 'pointerdown', window.__finger ) );
	} );
	let seen = await watch( 7000 );
	check( still( seen, 'strip' ) && still( seen, 'hero' ), `while a finger is down neither the strip nor the hero changes (${ JSON.stringify( seen[ 0 ] ) } to ${ JSON.stringify( seen[ seen.length - 1 ] ) })` );
	await page.evaluate( () => document.querySelector( '.mizzey-products, main, body' ).dispatchEvent( new PointerEvent( 'pointerup', window.__finger ) ) );
	const lifted = await at();
	await sleep( 7000 );
	check( ( await at() ).hero !== lifted.hero, 'after the finger lifts the hero moves on again (its timer runs)' );

	// The page hidden: neither moves.
	await page.evaluate( () => {
		Object.defineProperty( document, 'hidden', { configurable: true, get: () => true } );
		Object.defineProperty( document, 'visibilityState', { configurable: true, get: () => 'hidden' } );
		document.dispatchEvent( new Event( 'visibilitychange' ) );
	} );
	seen = await watch( 7000 );
	check( still( seen, 'strip' ) && still( seen, 'hero' ), `while the page is hidden neither the strip nor the hero changes (${ JSON.stringify( seen[ 0 ] ) } to ${ JSON.stringify( seen[ seen.length - 1 ] ) })` );
	await context.close();
}

/*
 * Changing page. Speculation rules: a storefront link is prefetched once the pointer rests on it, the cart's is not
 * (Chromium; WebKit has no speculation rules, and says so). The page change is a cross-document view transition with
 * the board's timings, and its reduced-motion form is the 120 ms fade.
 */
async function navigation( browser ) {
	const supported = await ( async () => {
		const { context, page } = await phone( browser );
		await page.goto( plan.en, { waitUntil: 'load' } );
		const out = await page.evaluate( () => ( {
			rules: HTMLScriptElement.supports ? HTMLScriptElement.supports( 'speculationrules' ) : false,
			printed: !! document.querySelector( 'script[type="speculationrules"]' ),
			vt: typeof CSSViewTransitionRule !== 'undefined' || 'onpagereveal' in window,
		} ) );
		await context.close();
		return out;
	} )();
	check( supported.printed, 'the page prints its speculation rules' );

	// Prefetch on hover, at desktop: a product's link yes, the cart no.
	const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
	const page = await context.newPage();
	page.setDefaultTimeout( TIMEOUT );
	const prefetched = [];
	page.on( 'request', ( request ) => {
		const purpose = request.headers()[ 'sec-purpose' ] || '';
		if ( purpose.includes( 'prefetch' ) ) {
			prefetched.push( request.url() );
		}
	} );
	await page.goto( plan.en, { waitUntil: 'load' } );
	await ready( page );
	const product = await page.evaluate( ( id ) => document.querySelector( `.mizzey-card[data-product-id="${ id }"] .mizzey-card__name a` ).href, plan.simple );
	await page.locator( `.mizzey-card[data-product-id="${ plan.simple }"] .mizzey-card__name a` ).hover();
	await sleep( 1500 );
	const cart = await page.evaluate( () => { const a = document.querySelector( '.mizzey-header__cart' ); return a ? a.href : ''; } );
	await page.locator( '.mizzey-header__cart' ).hover();
	await sleep( 1500 );
	if ( engine === 'webkit' ) {
		// WebKit answers that it knows speculation rules but, in this build, sends no prefetch for them: recorded, not
		// required. The links work the same either way.
		check( cart && ! prefetched.includes( cart ), `WebKit: the cart's link is not prefetched; a product's link was prefetched ${ prefetched.includes( product ) ? 'too' : 'not at all (no speculative loading in this engine)' }` );
	} else if ( supported.rules ) {
		check( prefetched.includes( product ), `a product's link is prefetched when the pointer rests on it (${ prefetched.length } prefetched)` );
		check( cart && ! prefetched.includes( cart ), 'the cart\'s link is not prefetched' );
	} else {
		check( prefetched.length === 0, `${ engine } has no speculation rules: nothing is prefetched, links work as before` );
	}
	await context.close();

	// The page change, with the board's timings, then under reduced motion.
	for ( const reduced of [ false, true ] ) {
		const { context: ctx, page: pg } = await phone( browser, { reduced } );
		await pg.addInitScript( () => {
			addEventListener( 'pagereveal', ( event ) => {
				window.__vt = !! event.viewTransition;
				window.__vtTimes = [];
				if ( event.viewTransition ) {
					event.viewTransition.ready.then( () => {
						window.__vtTimes = document.getAnimations().map( ( a ) => {
							const t = a.effect.getTiming();
							return `${ a.effect.pseudoElement || '' }:${ Math.round( t.duration ) }+${ Math.round( t.delay ) }`;
						} );
					} ).catch( () => {} );
				}
			} );
		} );
		await pg.goto( plan.en, { waitUntil: 'load' } );
		await ready( pg );
		await pg.locator( `.mizzey-card[data-product-id="${ plan.simple }"] .mizzey-card__name a` ).click();
		await pg.waitForURL( ( u ) => u.href.startsWith( product.replace( /\/$/, '' ) ), { timeout: 30000 } );
		await pg.waitForLoadState( 'load' );
		await sleep( 800 );
		const seen = await pg.evaluate( () => ( { vt: window.__vt, times: window.__vtTimes || [] } ) );
		const root = seen.times.find( ( t ) => t.startsWith( '::view-transition-new(root)' ) ) || '';
		if ( ! supported.vt ) {
			check( true, `${ engine } has no cross-document view transitions: the page changes as before` );
		} else if ( reduced ) {
			check( seen.vt && root.endsWith( ':120+0' ), `under reduced motion the page change is the 120 ms fade (${ root || 'no animation' })` );
		} else {
			check( seen.vt && root.endsWith( ':320+80' ) && seen.times.some( ( t ) => t.startsWith( '::view-transition-old(root):120' ) ), `the page change fades the old page out in 120 ms and the new one in over 320 ms after 80 (${ seen.times.join( ', ' ) || 'none' })` );
			check( ! seen.times.some( ( t ) => /mizzey-(header|tabbar)/.test( t ) && ! t.endsWith( ':0+0' ) ), 'the header and the tab bar hold still through the page change' );
		}
		await ctx.close();
	}
}

/*
 * Order attribution waits for the load event on a lean page; the visitor's choice made before then is the one it
 * follows, both ways: a returning visitor who had accepted and now refuses is not tracked, and one who accepts is.
 */
async function consent( browser ) {
	const host = new URL( plan.en ).hostname;
	for ( const [ before, now, tracked ] of [ [ 'granted', 'denied', false ], [ null, 'granted', true ] ] ) {
		const { context, page } = await phone( browser );
		if ( before ) {
			await context.addCookies( [ { name: 'mizzey_consent', value: before, domain: host, path: '/' } ] );
		}
		// The choice made on the banner while the page is still loading: the banner writes this cookie.
		await page.addInitScript( ( value ) => {
			document.addEventListener( 'DOMContentLoaded', () => {
				document.cookie = `mizzey_consent=${ value }; path=/`;
			} );
		}, now );
		await page.goto( plan.en, { waitUntil: 'load' } );
		await page.waitForFunction( () => window.wc_order_attribution && typeof window.wc_order_attribution.setOrderTracking === 'function', null, { timeout: 20000 } ).catch( () => null );
		await sleep( 1500 );
		const state = await page.evaluate( () => ( {
			loaded: !! ( window.wc_order_attribution && window.wc_order_attribution.setOrderTracking ),
			allow: window.wc_order_attribution && window.wc_order_attribution.params ? window.wc_order_attribution.params.allowTracking : null,
			sbjs: document.cookie.split( '; ' ).filter( ( c ) => c.startsWith( 'sbjs_' ) ).length,
		} ) );
		check( state.loaded && state.allow === tracked && ( tracked ? engine !== 'chromium' || state.sbjs > 0 : state.sbjs === 0 ), `order attribution follows a choice made before the page loaded (${ before || 'no choice' } then ${ now }): ${ tracked ? 'tracked' : 'not tracked' } (${ JSON.stringify( state ) })` );
		await context.close();
	}
}

/*
 * When the page's behaviour never starts (ui.js does not arrive), the held taps are not kept for ever: after the load
 * event and a short wait the page stops holding them, and a control whose own script did arrive still acts.
 */
async function failOpen( browser ) {
	for ( const [ url, control, what ] of [ [ plan.product, SEARCH, 'the search' ], [ plan.en, card( plan.var ), 'a card\'s add' ] ] ) {
		const { context, page } = await phone( browser );
		await page.route( '**/mizzey-theme/assets/js/ui.js*', ( route ) => route.abort() );
		await page.goto( url, { waitUntil: 'load' } );
		await sleep( 2500 );
		const picker = control === SEARCH ? null : await pickerOf( page, plan.var );
		await page.locator( control ).tap();
		await sleep( 900 );
		const acted = control === SEARCH ? ( await expanded( page, SEARCH ) ) === 'true' : await isOpen( page, picker );
		check( acted, `with ui.js missing, ${ what } still acts on a tap once the page has loaded` );
		await context.close();
	}
}

/** The pixels of a PNG screenshot, as rows of [r, g, b] (8-bit RGB or RGBA, any filter). Enough for a 1 px strip. */
function pixels( png ) {
	const zlib = require( 'node:zlib' );
	const width = png.readUInt32BE( 16 );
	const height = png.readUInt32BE( 20 );
	const channels = png[ 25 ] === 6 ? 4 : 3;
	const chunks = [];
	for ( let at = 8; at < png.length; ) {
		const size = png.readUInt32BE( at );
		if ( png.toString( 'ascii', at + 4, at + 8 ) === 'IDAT' ) {
			chunks.push( png.subarray( at + 8, at + 8 + size ) );
		}
		at += size + 12;
	}
	const raw = zlib.inflateSync( Buffer.concat( chunks ) );
	const stride = width * channels;
	const rows = [];
	let previous = Buffer.alloc( stride );
	for ( let y = 0; y < height; y++ ) {
		const type = raw[ y * ( stride + 1 ) ];
		const line = Buffer.from( raw.subarray( y * ( stride + 1 ) + 1, ( y + 1 ) * ( stride + 1 ) ) );
		for ( let i = 0; i < stride; i++ ) {
			const left = i >= channels ? line[ i - channels ] : 0;
			const up = previous[ i ];
			const corner = i >= channels ? previous[ i - channels ] : 0;
			const p = left + up - corner;
			const pa = Math.abs( p - left );
			const pb = Math.abs( p - up );
			const pc = Math.abs( p - corner );
			const add = [ 0, left, up, ( left + up ) >> 1, pa <= pb && pa <= pc ? left : pb <= pc ? up : corner ][ type ];
			line[ i ] = ( line[ i ] + add ) & 255;
		}
		const row = [];
		for ( let x = 0; x < width; x++ ) {
			row.push( [ line[ x * channels ], line[ x * channels + 1 ], line[ x * channels + 2 ] ] );
		}
		rows.push( row );
		previous = line;
	}
	return rows;
}

/** Where the line is painted on the screen's top row: how wide, and its first and last pixel. */
async function lineOnScreen( page, cdp ) {
	// Playwright waits for a pending navigation before it takes a screenshot in Chromium; the DevTools protocol takes
	// it at once, which is what is on the screen while the next page is asked for.
	const shot = cdp
		? await cdp.send( 'Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 1, width: 390, height: 1, scale: 1 } } ).then( ( r ) => Buffer.from( r.data, 'base64' ) ).catch( () => null )
		: await page.screenshot( { clip: { x: 0, y: 1, width: 390, height: 1 }, scale: 'css' } ).catch( () => null );
	if ( ! shot ) {
		return null;
	}
	const row = pixels( shot )[ 0 ];
	// Indigo #263B73: clearly bluer and darker than the white page. Read in CSS pixels whatever the device scale.
	const ink = row.map( ( [ r, , b ] ) => b > r + 25 && r < 200 );
	const k = 390 / row.length;
	const first = ink.indexOf( true );
	const last = ink.lastIndexOf( true );
	return { width: Math.round( ink.filter( Boolean ).length * k ), first: first < 0 ? -1 : Math.round( first * k ), last: last < 0 ? -1 : Math.round( ( last + 1 ) * k ) - 1 };
}

/*
 * The navigation line (owner's design addition): a thin Indigo line at the top while the next page loads. Not before
 * 150 ms (a prefetched page opens with none), then it creeps forward; in Arabic it grows from the right; under reduced
 * motion it is a full, still line that fades in. It takes no tap and moves nothing. Some engines stop the page's own
 * timers and animations once the next page is requested, so the line is read from screenshots of the screen itself.
 * A link the page handles itself (the cookie preferences) shows none.
 */
async function progress( browser ) {
	for ( const [ lang, reduced ] of [ [ 'en', false ], [ 'ar', false ], [ 'en', true ] ] ) {
		const { context, page } = await phone( browser, { lang, reduced } );
		await page.goto( plan[ lang ], { waitUntil: 'load' } );
		await ready( page );
		const where = `[${ lang }${ reduced ? ', reduced motion' : '' }]`;
		// Its look, with the class set by hand: fixed at the top, 3 px of Indigo, no taps, nothing moved.
		const look = await page.evaluate( () => {
			const top = () => document.querySelector( '.mizzey-header' ).getBoundingClientRect().top;
			const header = top();
			document.documentElement.classList.add( 'mizzey-nav' );
			const cs = getComputedStyle( document.body, '::after' );
			const out = { pos: cs.position, top: Math.round( parseFloat( cs.top ) ), h: Math.round( parseFloat( cs.height ) ), pe: cs.pointerEvents, bg: cs.backgroundColor, origin: cs.transformOrigin, moved: top() !== header };
			document.documentElement.classList.remove( 'mizzey-nav' );
			return out;
		} );
		check( look.pos === 'fixed' && look.top === 0 && look.h === 3 && look.pe === 'none' && look.bg === 'rgb(38, 59, 115)' && ! look.moved, `${ where } the line is 3 px of Indigo fixed at the top, takes no tap and moves nothing (${ JSON.stringify( look ) })` );
		if ( ! reduced ) {
			const x = parseFloat( look.origin );
			check( lang === 'ar' ? x > 300 : x === 0, `${ where } it grows from the ${ lang === 'ar' ? 'right' : 'left' } (origin ${ look.origin })` );
		}

		// The next page is slow: a link to a page of the store whose answer is held back three seconds (any request for
		// it, the prefetch the speculation rules may make included).
		const target = await page.evaluate( () => {
			const a = document.createElement( 'a' );
			a.href = '/t118-slow-page/';
			a.textContent = 'Slow page';
			a.setAttribute( 'data-t118-link', '' );
			a.style.cssText = 'position:fixed;inset-block-start:300px;inset-inline-start:20px;z-index:2147483000;padding:12px;background:#fff';
			document.body.appendChild( a );
			return a.href;
		} );
		await page.route( ( u ) => u.href === target, async ( route ) => {
			await sleep( 3000 );
			await route.fulfill( { status: 200, contentType: 'text/html', body: '<!doctype html><title>Slow page</title><p>Slow page</p>' } ).catch( () => {} );
		} );
		const cdp = engine === 'chromium' ? await context.newCDPSession( page ) : null;
		await page.locator( '[data-t118-link]' ).first().click( { noWaitAfter: true } );
		const early = await lineOnScreen( page, cdp );
		await sleep( 350 );
		const soon = await lineOnScreen( page, cdp );
		await sleep( 900 );
		const later = await lineOnScreen( page, cdp );
		const fromEdge = ( l ) => ( lang === 'ar' ? 389 - l.last : l.first );
		check( early && early.width === 0, `${ where } nothing shows in the first moment, so a page that opens at once shows none (${ JSON.stringify( early ) })` );
		if ( reduced ) {
			check( later && later.width > 380, `${ where } a full, still line while the page loads (${ JSON.stringify( soon ) } then ${ JSON.stringify( later ) })` );
		} else {
			check( soon && soon.width >= 70 && fromEdge( soon ) === 0, `${ where } the line shows within the first half second, from its start edge (${ JSON.stringify( soon ) })` );
			// Chromium keeps the old page's animations running while the next page is requested; WebKit here holds them
			// where they were, so there the line stays at its first fifth.
			check( later && later.width < 390 && ( engine !== 'chromium' || later.width > soon.width ), `${ where } it creeps forward without reaching the end (${ soon && soon.width } px then ${ later && later.width } px)` );
		}
		await context.close();
	}
	// A link the page handles itself shows no line.
	const { context, page } = await phone( browser );
	await page.goto( plan.en, { waitUntil: 'load' } );
	await ready( page );
	const handled = await page.evaluate( () => {
		const a = document.createElement( 'a' );
		a.href = '#cookie-preferences';
		a.textContent = 'Cookie settings';
		document.querySelector( 'main, body' ).appendChild( a );
		a.click();
		return new Promise( ( resolve ) => setTimeout( () => resolve( document.documentElement.classList.contains( 'mizzey-nav' ) ), 600 ) );
	} );
	check( ! handled, 'a link the page handles itself (the cookie preferences) shows no line' );
	await context.close();
}

( async () => {
	const found = playwright();
	if ( ! found ) {
		console.log( JSON.stringify( { skipped: `no Playwright ${ engine } is installed` } ) );
		return;
	}
	const { pw, launch } = found;
	const browser = await pw[ engine ].launch( launch );
	try {
		const steps = { early: () => earlyTaps( browser ), touches: () => touches( browser ), motionEn: () => motion( browser, 'en' ), motionAr: () => motion( browser, 'ar' ), sliders: () => sliders( browser ), navigation: () => navigation( browser ), consent: () => consent( browser ), failOpen: () => failOpen( browser ), progress: () => progress( browser ) };
		// T118_ONLY=sliders (or a comma-separated list) runs some of the steps, when one is being worked on.
		const only = ( process.env.T118_ONLY || '' ).split( ',' ).filter( Boolean );
		for ( const [ name, step ] of Object.entries( steps ) ) {
			if ( only.length && ! only.includes( name ) ) {
				continue;
			}
			try {
				await step();
			} catch ( error ) {
				check( false, `a step stopped: ${ String( error.message || error ).split( '\n' )[ 0 ] }` );
			}
		}
	} finally {
		await browser.close();
	}
	console.log( JSON.stringify( { checks } ) );
} )().catch( ( error ) => {
	console.log( JSON.stringify( { error: String( error.stack || error ) } ) );
} );

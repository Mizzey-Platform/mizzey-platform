#!/usr/bin/env node
/**
 * The phone menu's motion in a real browser (spec 066, slice 2: NAV-10; K-5 for reduced motion), measured frame by
 * frame against C1-Motion-Playground ("Overlays", "Phone menu, sub-level push") and C1-Menus-Phone frame 1.
 *
 * Usage: node phone-menu-motion.cjs [base-url] [--shots=DIR]. The base URL serves the storefront (default
 * http://127.0.0.1:8124); its header menu must hold a category with children (the phone menu's second level).
 * Prints one JSON object: { checks: [{ ok, what }], numbers: {...} } and exits 1 when a check fails.
 *
 * At 390 wide with touch, in English and Arabic, in Chromium and WebKit:
 *
 * - M-1 the wave menu icon turns into the drawer's close button as the drawer opens (its lines turn to the cross,
 *   the wave fades), and the header's control turns back on close; the drawer's close keeps its label;
 * - M-2 the sub-level push: the old level moves 30% toward the start and fades, the new one comes from the end,
 *   320 ms on the soft spring; Back reverses it; "start" and "end" follow the language; focus moves to the new
 *   level's back button, and back to the row that opened it; the level left behind is inert and hidden after;
 * - M-3 the content stagger on opening: the header, the rows and the foot rise 12 px, 40 ms apart, only the first
 *   six stagger, the rest arrive with the sixth; nothing staggers on the way out;
 * - M-4 the drawer follows a finger dragged toward its edge, closes past 35% of its width or on a flick faster than
 *   0.5 px per ms, and springs back otherwise; a drag the other way does not move it;
 * - M-5 reduced motion: no movement, a short cross-fade between levels, the end states shown at once.
 *
 * A duration passes within 15% of the board's. Playwright is test tooling on the developer's machine, not a
 * dependency of the site (see _theme-preview.cjs).
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

const BASE = ( process.argv.slice( 2 ).find( ( a ) => ! a.startsWith( '--' ) ) || 'http://127.0.0.1:8124' ).replace( /\/$/, '' );
const SHOTS = ( process.argv.find( ( a ) => a.startsWith( '--shots=' ) ) || '' ).slice( 8 );
const ENGINES = ( process.env.MIZZEY_ENGINES || 'chromium,webkit' ).split( ',' );
const checks = [];
const numbers = {};
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const within = ( value, target, share = 0.15 ) => Math.abs( value - target ) <= target * share;

/* The board's numbers. */
const BOARD = { push: 320, iconIn: 450, iconOut: 240, rise: 320, step: 40, distance: 12, firstRise: 410 };

/** In the page: sample elements every frame from the moment `start()` runs. Returns a handle on window. */
function installSampler() {
	window.__mz = {
		frames: [],
		run( targets, ms ) {
			const t0 = performance.now();
			this.frames = [];
			return new Promise( ( resolve ) => {
				const tick = () => {
					const t = performance.now() - t0;
					const row = { t: Math.round( t ) };
					for ( const [ name, find ] of Object.entries( targets ) ) {
						const el = typeof find === 'function' ? find() : document.querySelector( find );
						if ( ! el ) {
							row[ name ] = null;
							continue;
						}
						const cs = getComputedStyle( el );
						const m = cs.transform && cs.transform !== 'none' ? new DOMMatrixReadOnly( cs.transform ) : new DOMMatrixReadOnly();
						const box = el.getBoundingClientRect();
						row[ name ] = {
							x: m.e,
							y: m.f,
							deg: Math.atan2( m.b, m.a ) * 180 / Math.PI,
							o: parseFloat( cs.opacity ),
							gone: el.getClientRects().length === 0,
							left: Math.round( box.left * 10 ) / 10,
							w: Math.round( box.width ),
						};
					}
					this.frames.push( row );
					if ( t < ms ) {
						requestAnimationFrame( tick );
					} else {
						resolve( this.frames );
					}
				};
				tick();
			} );
		},
	};
}

/** Start sampling when an attribute of the element changes (the drawer's `open`), then run the action. */
async function sampleOn( page, targets, ms, action ) {
	const done = page.evaluate( ( [ t, ms2 ] ) => {
		const targetsFn = {};
		for ( const [ k, v ] of Object.entries( t ) ) {
			targetsFn[ k ] = v;
		}
		return new Promise( ( resolve ) => {
			window.__mzGo = () => window.__mz.run( targetsFn, ms2 ).then( resolve );
		} );
	}, [ targets, ms ] );
	await page.evaluate( () => {
		const go = () => {
			const f = window.__mzGo;
			window.__mzGo = null;
			if ( f ) {
				f();
			}
		};
		const dialog = document.getElementById( 'mizzey-menu' );
		const obs = new MutationObserver( () => {
			obs.disconnect();
			go();
		} );
		obs.observe( document.getElementById( 'mizzey-menu' ), { attributes: true, subtree: true, attributeFilter: [ 'open', 'hidden', 'class', 'style', 'inert' ] } );
		window.__mzStop = () => obs.disconnect();
		window.__mzDialog = dialog;
	} );
	await action();
	const frames = await done;
	return frames;
}

/**
 * The span of a motion: from the first frame it leaves `from` (by more than `tol`) to the first frame it rests at
 * `to` exactly (or is no longer drawn: a level hidden at the end of its motion). The end is exact, so a spring's or
 * an ease-out's long tail counts to its last frame, as the board's duration does.
 */
function span( frames, name, key, from, to, tol = 0.6 ) {
	const values = frames.filter( ( f ) => f[ name ] ).map( ( f ) => ( { t: f.t, v: f[ name ].gone ? to : f[ name ][ key ], gone: f[ name ].gone } ) );
	const start = values.find( ( s ) => ! s.gone && Math.abs( s.v - from ) > tol );
	if ( ! start ) {
		return { moved: false, values };
	}
	const startIndex = values.indexOf( start );
	const prev = values[ startIndex - 1 ];
	const exact = Math.max( 1e-3, Math.abs( to - from ) * 1e-4 );
	let end = null;
	for ( let i = startIndex; i < values.length; i++ ) {
		if ( Math.abs( values[ i ].v - to ) <= exact && values.slice( i ).every( ( s ) => Math.abs( s.v - to ) <= exact ) ) {
			end = values[ i ];
			break;
		}
	}
	const between = values.filter( ( s ) => s.t >= start.t && ( ! end || s.t < end.t ) && Math.abs( s.v - from ) > tol && Math.abs( s.v - to ) > tol );
	const began = prev ? ( prev.t + start.t ) / 2 : start.t;
	return { moved: true, start: began, end: end ? end.t : null, ms: end ? Math.round( end.t - began ) : null, between: between.length, values };
}

const LEVEL0 = '#mizzey-menu .mizzey-menu__level:first-child';

async function openPage( browser, lang, reduced ) {
	const context = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1, reducedMotion: reduced ? 'reduce' : 'no-preference' } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( BASE + ( 'ar' === lang ? '/ar/' : '/' ), { waitUntil: 'load', timeout: 90000 } );
	await page.waitForTimeout( 600 );
	await page.evaluate( installSampler );
	return { context, page, errors };
}

const isOpen = ( page ) => page.evaluate( () => document.getElementById( 'mizzey-menu' ).open );

async function waitClosed( page ) {
	await page.waitForFunction( () => ! document.getElementById( 'mizzey-menu' ).open, null, { timeout: 3000 } ).catch( () => {} );
	await page.waitForTimeout( 400 );
}

/** The order the open stagger follows: the head, the rows (the offer first), the foot. */
const STAGGER_ITEMS = `() => {
	const l = document.querySelector( '${ LEVEL0 }' );
	return [ l.querySelector( '.mizzey-menu__head' ), ...l.querySelectorAll( '.mizzey-menu__body > .mizzey-menu__offer, .mizzey-menu__body > .mizzey-menu__list > li' ), document.querySelector( '#mizzey-menu .mizzey-menu__foot' ) ];
}`;

async function openAndClose( page, where, reduced, n ) {
	const count = await page.evaluate( `(${ STAGGER_ITEMS })().length` );
	const targets = { close: `${ LEVEL0 } .mizzey-menu__close svg path:first-child`, wave: `${ LEVEL0 } .mizzey-menu__close svg path:nth-child(3)`, panel: '#mizzey-menu .mizzey-overlay__panel' };
	for ( let i = 0; i < Math.min( count, 9 ); i++ ) {
		targets[ 'i' + i ] = `#mizzey-menu [data-mz-probe="${ i }"]`;
	}
	await page.evaluate( `(${ STAGGER_ITEMS })().slice( 0, 9 ).forEach( ( el, i ) => el && el.setAttribute( 'data-mz-probe', String( i ) ) )` );
	const frames = await sampleOn( page, targets, 1300, () => page.locator( '.mizzey-header__menu' ).tap() );
	const opened = await isOpen( page );
	check( opened, `NAV-10 ${ where } the wave menu control opens the drawer` );

	// M-1: the close button's first line turns from the menu's line (0 deg) to the cross (45 deg); the wave fades.
	const turn = span( frames, 'close', 'deg', 0, 45, 1 );
	const fade = span( frames, 'wave', 'o', 1, 0, 0.02 );
	const label = await page.evaluate( ( s ) => {
		const b = document.querySelector( s );
		return b ? ( b.getAttribute( 'aria-label' ) || '' ) : '';
	}, `${ LEVEL0 } .mizzey-menu__close` );
	if ( reduced ) {
		const first = frames.find( ( f ) => f.close );
		check( first && Math.abs( first.close.deg - 45 ) <= 1, `K-5 ${ where } reduced motion: the close button shows the cross from the first frame (${ first ? first.close.deg : 'none' } deg)` );
	} else {
		numbers[ `${ where } icon in` ] = turn.ms;
		check( turn.moved && turn.between >= 3 && turn.ms && within( turn.ms, BOARD.iconIn ), `M-1 ${ where } the wave menu icon turns into the close button as the drawer opens: ${ turn.between } frames between, ${ turn.ms } ms (board ${ BOARD.iconIn })` );
		check( fade.moved && fade.between >= 2, `M-1 ${ where } the icon's wave fades out as it turns (${ fade.between } frames between)` );
	}
	check( label.length > 0, `M-1 ${ where } the drawer's close button stays labelled ("${ label }")` );
	const expanded = await page.getAttribute( '.mizzey-header__menu', 'aria-expanded' );
	check( 'true' === expanded, `M-1 ${ where } the header's menu control reads as expanded while the drawer is open (${ expanded })` );

	// M-3: the stagger.
	const items = [];
	for ( let i = 0; i < Math.min( count, 9 ); i++ ) {
		const s = span( frames, 'i' + i, 'y', BOARD.distance, 0, 0.3 );
		const top = Math.max( ...frames.filter( ( f ) => f[ 'i' + i ] ).map( ( f ) => f[ 'i' + i ].y ) );
		const firstO = ( frames.find( ( f ) => f[ 'i' + i ] ) || {} )[ 'i' + i ];
		items.push( { start: s.start, ms: s.ms, between: s.between, top, o0: firstO ? firstO.o : null } );
	}
	numbers[ `${ where } stagger` ] = items.map( ( s ) => [ Math.round( s.start || 0 ), s.ms, s.top ] );
	if ( reduced ) {
		check( items.every( ( s ) => s.top <= 0.3 ), `K-5 ${ where } reduced motion: nothing rises when the drawer opens (${ JSON.stringify( items.map( ( s ) => s.top ) ) })` );
	} else {
		const six = items.slice( 0, Math.min( 6, items.length ) );
		const rose = six.every( ( s ) => s.between >= 3 && Math.abs( s.top - BOARD.distance ) <= 0.5 );
		check( rose, `M-3 ${ where } the header, the rows and the foot rise ${ BOARD.distance } px: ${ JSON.stringify( items.map( ( s ) => s.top ) ) }` );
		const steps = ( six[ six.length - 1 ].start - six[ 0 ].start ) / ( six.length - 1 );
		numbers[ `${ where } stagger step` ] = Math.round( steps * 10 ) / 10;
		// The menu is the runtime's: with fewer than six items (head, rows, foot) all of them stagger.
		check( six.length >= 3 && within( steps, BOARD.step, 0.2 ), `M-3 ${ where } the first ${ six.length } of up to six stagger ${ Math.round( steps ) } ms apart (board ${ BOARD.step })` );
		check( within( six[ 0 ].start, BOARD.firstRise, 0.15 ), `M-3 ${ where } the header starts to rise ${ Math.round( six[ 0 ].start ) } ms after the drawer opens (board ${ BOARD.firstRise }, as the panel lands)` );
		const durations = six.map( ( s ) => s.ms );
		check( durations.every( ( d ) => d && within( d, BOARD.rise ) ), `M-3 ${ where } each rise takes the board's ${ BOARD.rise } ms: ${ JSON.stringify( durations ) }` );
		const rest = items.slice( 6 );
		check( rest.every( ( s ) => Math.abs( s.start - six[ six.length - 1 ].start ) <= 20 ), `M-3 ${ where } the rows after the sixth arrive with the sixth: ${ rest.length ? JSON.stringify( rest.map( ( s ) => Math.round( s.start ) ) ) : 'none past the sixth on this menu' } vs ${ Math.round( six[ six.length - 1 ].start ) }` );
	}

	// On the way out: no stagger; the header's control turns back from the cross to the wave icon.
	await page.waitForTimeout( 200 );
	const outTargets = { menu: '.mizzey-header__menu svg path:first-child' };
	for ( let i = 0; i < Math.min( count, 6 ); i++ ) {
		outTargets[ 'i' + i ] = `#mizzey-menu [data-mz-probe="${ i }"]`;
	}
	const before = await page.evaluate( () => {
		const p = document.querySelector( '.mizzey-header__menu svg path:first-child' );
		const m = new DOMMatrixReadOnly( getComputedStyle( p ).transform === 'none' ? undefined : getComputedStyle( p ).transform );
		return Math.round( Math.atan2( m.b, m.a ) * 1800 / Math.PI ) / 10;
	} );
	const out = await sampleOn( page, outTargets, 700, () => page.keyboard.press( 'Escape' ) );
	await waitClosed( page );
	const back = span( out, 'menu', 'deg', 45, 0, 1 );
	const risesOut = out.some( ( f ) => Object.keys( outTargets ).some( ( k ) => k.startsWith( 'i' ) && f[ k ] && ( Math.abs( f[ k ].y ) > 0.3 ) ) );
	check( ! risesOut, `M-3 ${ where } no stagger on the way out` );
	const expandedAfter = await page.getAttribute( '.mizzey-header__menu', 'aria-expanded' );
	check( 'false' === expandedAfter, `M-1 ${ where } closed, the header's control reads as collapsed (${ expandedAfter })` );
	if ( reduced ) {
		const last = out[ out.length - 1 ];
		check( last.menu && Math.abs( last.menu.deg ) <= 1, `K-5 ${ where } reduced motion: closed, the wave icon is back` );
	} else {
		numbers[ `${ where } icon out` ] = back.ms;
		check( Math.abs( before - 45 ) <= 1 && back.moved && back.between >= 2 && back.ms && within( back.ms, BOARD.iconOut ), `M-1 ${ where } closing, the cross turns back into the wave icon: from ${ before } deg, ${ back.between } frames between, ${ back.ms } ms (board ${ BOARD.iconOut }, a quarter quicker)` );
	}
	void n;
}

async function push( page, where, reduced, rtl, shotsDir ) {
	await page.locator( '.mizzey-header__menu' ).tap();
	await page.waitForTimeout( 1100 );
	const row = page.locator( `${ LEVEL0 } .mizzey-menu__row[aria-controls]` ).first();
	if ( ! await row.count() ) {
		check( false, `M-2 ${ where } the runtime's menu has a category with children to open (none in this language now: the push is not measured)` );
		await page.keyboard.press( 'Escape' );
		await waitClosed( page );
		return;
	}
	const id = await row.getAttribute( 'aria-controls' );
	const dir = rtl ? -1 : 1;
	const targets = { from: LEVEL0, to: `#${ id }` };

	if ( shotsDir ) {
		// The frame strip: the push held at five moments of its 320 ms.
		await page.evaluate( () => {
			window.__mzHold = true;
		} );
	}
	const frames = await sampleOn( page, targets, 700, () => row.tap() );
	const focus = await page.evaluate( () => ( { cls: document.activeElement.className, inTo: !! document.activeElement.closest( '.mizzey-menu__level' ) && document.activeElement.closest( '.mizzey-menu__level' ).id } ) );
	const width = ( frames.find( ( f ) => f.to && f.to.w ) || { to: { w: 342 } } ).to.w;
	const fromX = span( frames, 'from', 'x', 0, -0.3 * width * dir, 1 );
	const fromO = span( frames, 'from', 'o', 1, 0, 0.02 );
	const toX = span( frames, 'to', 'x', width * dir, 0, 1 );
	const firstTo = frames.find( ( f ) => f.to && f.to.w );
	numbers[ `${ where } push` ] = { old: fromX.ms, oldFade: fromO.ms, new: toX.ms, width, firstNewX: firstTo ? firstTo.to.x : null };
	if ( reduced ) {
		const still = frames.every( ( f ) => ( ! f.from || Math.abs( f.from.x ) <= 0.5 ) && ( ! f.to || Math.abs( f.to.x ) <= 0.5 ) );
		const fadeIn = span( frames, 'to', 'o', 0, 1, 0.02 );
		check( still, `K-5 ${ where } reduced motion: the levels do not move` );
		check( fadeIn.moved && fadeIn.between >= 1 && fadeIn.ms && fadeIn.ms <= 200, `K-5 ${ where } reduced motion: the new level cross-fades in (${ fadeIn.ms } ms, ${ fadeIn.between } frames between)` );
	} else {
		check( fromX.moved && fromX.between >= 3 && fromX.ms && within( fromX.ms, BOARD.push ), `M-2 ${ where } the old level moves 30% toward the start (${ Math.round( -0.3 * width * dir ) } px): ${ fromX.between } frames between, ${ fromX.ms } ms (board ${ BOARD.push })` );
		check( fromO.moved && fromO.between >= 3, `M-2 ${ where } the old level fades as it moves (${ fromO.between } frames between)` );
		check( toX.moved && toX.between >= 3 && toX.ms && within( toX.ms, BOARD.push ) && firstTo && Math.sign( firstTo.to.x ) === dir, `M-2 ${ where } the new level comes from the end (first frame at ${ firstTo ? firstTo.to.x : 'none' } px): ${ toX.between } frames between, ${ toX.ms } ms (board ${ BOARD.push })` );
	}
	check( focus.inTo === id && /mizzey-menu__back/.test( focus.cls ), `M-2 ${ where } focus moves to the new level's back button (${ focus.cls } in ${ focus.inTo })` );
	const after = await page.evaluate( ( [ s, t ] ) => {
		const a = document.querySelector( s );
		const b = document.querySelector( t );
		return { fromHidden: a.hidden && ! a.getClientRects().length, toShown: ! b.hidden && b.getAttribute( 'aria-hidden' ) !== 'true' && ! b.inert, toX: getComputedStyle( b ).transform };
	}, [ LEVEL0, `#${ id }` ] );
	check( after.fromHidden && after.toShown && ( after.toX === 'none' || after.toX === 'matrix(1, 0, 0, 1, 0, 0)' ), `M-2 ${ where } after the push the first level is hidden from everyone and the new one rests in place (${ JSON.stringify( after ) })` );

	// The level left behind during the motion: inert and hidden from screen readers.
	const during = await page.evaluate( async ( [ s, t ] ) => {
		const back = document.querySelector( `${ t } .mizzey-menu__back` );
		back.click();
		await new Promise( ( r ) => requestAnimationFrame( () => requestAnimationFrame( r ) ) );
		const leaving = document.querySelector( t );
		const r = { inert: !! leaving.inert, ariaHidden: leaving.getAttribute( 'aria-hidden' ), focusIn: document.querySelector( s ).contains( document.activeElement ), opener: document.activeElement.getAttribute( 'aria-controls' ) };
		await new Promise( ( res ) => setTimeout( res, 700 ) );
		// Forward again, to measure Back by itself below.
		document.activeElement.click();
		await new Promise( ( res ) => setTimeout( res, 700 ) );
		return r;
	}, [ LEVEL0, `#${ id }` ] );
	check( ( during.inert && during.ariaHidden === 'true' ) || reduced, `M-2 ${ where } the level going away is inert and hidden from screen readers while it moves (${ JSON.stringify( during ) })` );
	check( during.focusIn && during.opener === id, `M-2 ${ where } Back returns focus to the row that opened the level (${ during.opener })` );

	// Back reverses the push.
	const back = await sampleOn( page, { from: LEVEL0, to: `#${ id }`, row: `${ LEVEL0 } .mizzey-menu__body > .mizzey-menu__list > li:last-child`, head: `${ LEVEL0 } .mizzey-menu__head`, cross: `${ LEVEL0 } .mizzey-menu__close svg path:first-child` }, 700, () => page.locator( `#${ id } .mizzey-menu__back` ).tap() );
	// Back shows the first level as it was: its rows do not rise again and its cross does not turn again.
	const replay = back.filter( ( f ) => ( f.row && ! f.row.gone && ( Math.abs( f.row.y ) > 0.01 || f.row.o < 0.99 ) ) || ( f.head && ! f.head.gone && Math.abs( f.head.y ) > 0.01 ) || ( f.cross && ! f.cross.gone && Math.abs( f.cross.deg - 45 ) > 0.5 ) );
	check( replay.length === 0, `M-3 ${ where } Back does not replay the entrance on the first level (${ replay.length } frames with a rise or a turn)` );
	const deeperX = span( back, 'to', 'x', 0, width * dir, 1 );
	const firstX = span( back, 'from', 'x', -0.3 * width * dir, 0, 1 );
	const firstO = span( back, 'from', 'o', 0, 1, 0.02 );
	numbers[ `${ where } back` ] = { deeper: deeperX.ms, first: firstX.ms, firstFade: firstO.ms };
	if ( ! reduced ) {
		check( deeperX.moved && deeperX.between >= 3 && deeperX.ms && within( deeperX.ms, BOARD.push ), `M-2 ${ where } Back: the level goes back to the end: ${ deeperX.between } frames between, ${ deeperX.ms } ms` );
		check( firstX.moved && firstX.between >= 3 && firstX.ms && within( firstX.ms, BOARD.push ) && firstO.between >= 3, `M-2 ${ where } Back: the first level returns from 30% toward the start and fades in: ${ firstX.between } frames between, ${ firstX.ms } ms` );
	} else {
		check( back.every( ( f ) => ( ! f.from || Math.abs( f.from.x ) <= 0.5 ) && ( ! f.to || Math.abs( f.to.x ) <= 0.5 ) ), `K-5 ${ where } reduced motion: Back does not move the levels either` );
	}
	const backAfter = await page.evaluate( ( t ) => document.querySelector( t ).hidden, `#${ id }` );
	check( backAfter, `M-2 ${ where } after Back the second level is hidden again` );

	if ( shotsDir && ! reduced ) {
		await framestrip( page, row, id, shotsDir, where );
	}
	await page.keyboard.press( 'Escape' );
	await waitClosed( page );
}

/** The level push held at 0, 80, 160, 240 and 320 ms, one shot each, then one strip. */
async function framestrip( page, row, id, dir, where ) {
	const files = [];
	for ( const at of [ 0, 40, 80, 160, 240, 320 ] ) {
		await row.tap();
		await page.evaluate( ( ms ) => {
			document.getAnimations().forEach( ( a ) => {
				if ( a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest( '.mizzey-menu__level' ) ) {
					a.pause();
					a.currentTime = ms;
				}
			} );
		}, at );
		const file = path.join( dir, `push-${ where.replace( /[^a-z0-9]+/gi, '-' ) }-${ at }.png` );
		await page.locator( '#mizzey-menu .mizzey-overlay__panel' ).screenshot( { path: file } );
		files.push( [ at, file ] );
		await page.evaluate( () => document.getAnimations().forEach( ( a ) => a.finish && a.playState === 'paused' && a.finish() ) );
		await page.waitForTimeout( 100 );
		await page.locator( `#${ id } .mizzey-menu__back` ).tap();
		await page.waitForTimeout( 600 );
	}
	const html = `<body style="margin:0;display:flex;gap:8px;background:#ccc;font:12px sans-serif">${ files.map( ( [ at, f ] ) => `<figure style="margin:0"><img src="data:image/png;base64,${ fs.readFileSync( f ).toString( 'base64' ) }" style="display:block;width:240px"><figcaption>${ at } ms</figcaption></figure>` ).join( '' ) }</body>`;
	const strip = await page.context().newPage();
	await strip.setViewportSize( { width: 6 * 248, height: 560 } );
	await strip.setContent( html );
	await strip.screenshot( { path: path.join( dir, `push-strip-${ where.replace( /[^a-z0-9]+/gi, '-' ) }.png` ), fullPage: true } );
	await strip.close();
}

/** A synthetic touch drag on the panel: pointer events as a finger makes them, spaced in real time. */
async function drag( page, dx, ms, steps ) {
	return page.evaluate( async ( [ dx2, ms2, n ] ) => {
		const panel = document.querySelector( '#mizzey-menu .mizzey-overlay__panel' );
		const box = panel.getBoundingClientRect();
		const x0 = box.left + box.width / 2;
		const y = box.top + box.height / 2;
		const fire = ( type, x ) => panel.dispatchEvent( new PointerEvent( type, { bubbles: true, cancelable: true, composed: true, pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: x, clientY: y, buttons: type === 'pointerup' ? 0 : 1 } ) );
		const seen = [];
		fire( 'pointerdown', x0 );
		for ( let i = 1; i <= n; i++ ) {
			await new Promise( ( r ) => setTimeout( r, ms2 / n ) );
			fire( 'pointermove', x0 + ( dx2 * i ) / n );
			await new Promise( ( r ) => requestAnimationFrame( r ) );
			seen.push( Math.round( panel.getBoundingClientRect().left - box.left ) );
		}
		fire( 'pointerup', x0 + dx2 );
		// After the release: where the panel is, frame by frame, for 500 ms.
		const after = [];
		const t0 = performance.now();
		while ( performance.now() - t0 < 500 ) {
			await new Promise( ( r ) => requestAnimationFrame( r ) );
			after.push( [ Math.round( performance.now() - t0 ), Math.round( ( panel.getBoundingClientRect().left - box.left ) * 10 ) / 10 ] );
		}
		return { seen, width: box.width, after };
	}, [ dx, ms, steps ] );
}

async function drags( page, where, rtl, engine ) {
	const toward = rtl ? 1 : -1;
	const openIt = async () => {
		await page.locator( '.mizzey-header__menu' ).tap();
		await page.waitForTimeout( 1100 );
	};
	await openIt();
	const width = await page.evaluate( () => document.querySelector( '#mizzey-menu .mizzey-overlay__panel' ).getBoundingClientRect().width );

	// 20% slowly: follows the finger, then springs back.
	const slow = await drag( page, toward * width * 0.2, 500, 10 );
	const follows = slow.seen.every( ( v, i ) => Math.abs( v - ( toward * width * 0.2 * ( i + 1 ) ) / 10 ) <= 3 );
	const home = slow.after.length ? slow.after[ slow.after.length - 1 ][ 1 ] : null;
	const springing = slow.after.filter( ( [ , v ] ) => Math.abs( v ) > 1 && Math.abs( v ) < width * 0.2 - 1 ).length;
	const rest = await page.evaluate( () => Math.round( document.querySelector( '#mizzey-menu .mizzey-overlay__panel' ).getBoundingClientRect().left ) );
	const panelStart = rtl ? 390 - width : 0;
	check( follows, `M-4 ${ where } the drawer follows the finger toward its edge: ${ JSON.stringify( slow.seen ) }` );
	check( await isOpen( page ) && Math.abs( rest - panelStart ) <= 1, `M-4 ${ where } released at 20% slowly, it springs back and stays open (left ${ rest })` );
	numbers[ `${ where } spring back` ] = slow.after.filter( ( _, i ) => i % 3 === 0 );
	check( springing >= 3 && home !== null && Math.abs( home ) <= 1, `M-4 ${ where } the spring back passes through ${ springing } frames between the finger and the edge` );

	// The other way: it does not move.
	const wrong = await drag( page, -toward * width * 0.3, 300, 6 );
	check( wrong.seen.every( ( v ) => Math.abs( v ) <= 1 ) && await isOpen( page ), `M-4 ${ where } a drag away from the edge does not move the drawer: ${ JSON.stringify( wrong.seen ) }` );
	await page.waitForTimeout( 400 );

	// Past 35% slowly: it closes.
	await drag( page, toward * width * 0.4, 700, 14 );
	await waitClosed( page );
	check( ! await isOpen( page ), `M-4 ${ where } released past 35% of its width, the drawer closes` );
	// WebKit, as Safari, does not focus a button on a tap, so there the check is that focus left the closed drawer.
	const focusBack = await page.evaluate( ( e ) => {
		const a = document.activeElement;
		return 'webkit' === e ? ! document.getElementById( 'mizzey-menu' ).contains( a ) : !! a && a.classList.contains( 'mizzey-header__menu' );
	}, engine );
	check( focusBack, `M-4 ${ where } closed by a drag, focus returns to the menu control${ 'webkit' === engine ? ' (WebKit: out of the closed drawer; a tap does not focus a button there)' : '' }` );

	// A flick: 20% in two quick moves, it closes although it is short of 35%.
	await openIt();
	const flick = await drag( page, toward * width * 0.2, 16, 2 );
	numbers[ `${ where } flick` ] = flick.seen;
	await waitClosed( page );
	check( ! await isOpen( page ), `M-4 ${ where } a fast flick of 20% (short of 35%) closes the drawer` );

	// Reopened after a drag-close, the drawer comes in whole, not from where the finger left it.
	await openIt();
	const again = await page.evaluate( () => Math.round( document.querySelector( '#mizzey-menu .mizzey-overlay__panel' ).getBoundingClientRect().left ) );
	check( Math.abs( again - panelStart ) <= 1, `M-4 ${ where } reopened after a drag, the drawer rests at its edge (left ${ again })` );
	await page.keyboard.press( 'Escape' );
	await waitClosed( page );

	// A real finger in Chromium (CDP touch events, so the browser's own pointer events and touch-action apply).
	if ( 'chromium' === engine ) {
		await openIt();
		const cdp = await page.context().newCDPSession( page );
		const y = 420;
		const x0 = rtl ? 390 - width / 2 : width / 2;
		await cdp.send( 'Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [ { x: x0, y } ] } );
		for ( let i = 1; i <= 12; i++ ) {
			await cdp.send( 'Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [ { x: x0 + ( toward * width * 0.5 * i ) / 12, y } ] } );
			await page.waitForTimeout( 30 );
		}
		await cdp.send( 'Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] } );
		await waitClosed( page );
		check( ! await isOpen( page ), `M-4 ${ where } a real touch drag of half the drawer closes it` );
		// A vertical swipe scrolls the list and leaves the drawer open.
		await openIt();
		await cdp.send( 'Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [ { x: x0, y: 600 } ] } );
		for ( let i = 1; i <= 8; i++ ) {
			await cdp.send( 'Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [ { x: x0 + toward * 4 * i / 8, y: 600 - 30 * i } ] } );
			await page.waitForTimeout( 20 );
		}
		await cdp.send( 'Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] } );
		await page.waitForTimeout( 500 );
		const left = await page.evaluate( () => Math.round( document.querySelector( '#mizzey-menu .mizzey-overlay__panel' ).getBoundingClientRect().left ) );
		check( await isOpen( page ) && Math.abs( left - panelStart ) <= 1, `M-4 ${ where } a vertical swipe leaves the drawer open and in place (left ${ left })` );
		await page.keyboard.press( 'Escape' );
		await waitClosed( page );
	}
}

( async () => {
	const loaded = loadPlaywright();
	if ( ! loaded ) {
		console.log( JSON.stringify( { checks: [ { ok: false, what: 'Playwright not found' } ] } ) );
		process.exit( 1 );
	}
	const { pw, launch } = loaded;
	if ( SHOTS ) {
		fs.mkdirSync( SHOTS, { recursive: true } );
	}
	for ( const engine of ENGINES ) {
		const browser = await pw[ engine ].launch( 'chromium' === engine ? launch : { headless: true } );
		for ( const lang of [ 'en', 'ar' ] ) {
			for ( const reduced of [ false, true ] ) {
				const where = `[${ engine }, ${ lang }, 390${ reduced ? ', reduced' : '' }]`;
				const { context, page, errors } = await openPage( browser, lang, reduced );
				const rtl = await page.evaluate( () => getComputedStyle( document.documentElement ).direction === 'rtl' );
				check( rtl === ( 'ar' === lang ), `${ where } the page reads ${ rtl ? 'right to left' : 'left to right' }` );
				try {
					await openAndClose( page, where, reduced );
					await push( page, where, reduced, rtl, SHOTS && 'chromium' === engine ? SHOTS : '' );
					if ( ! reduced ) {
						await drags( page, where, rtl, engine );
					}
				} catch ( e ) {
					check( false, `${ where } ran to the end: ${ e.message.split( '\n' )[ 0 ] }` );
				}
				if ( SHOTS && ! reduced ) {
					if ( await isOpen( page ) ) {
						await page.keyboard.press( 'Escape' );
						await waitClosed( page );
					}
					await page.locator( '.mizzey-header__menu' ).tap( { timeout: 5000 } ).catch( () => {} );
					await page.waitForTimeout( 1200 );
					await page.screenshot( { path: path.join( SHOTS, `open-${ engine }-${ lang }.png` ) } );
				}
				check( errors.length === 0, `${ where } no script errors: ${ JSON.stringify( errors ) }` );
				await context.close();
			}
		}
		await browser.close();
	}
	const failed = checks.filter( ( c ) => ! c.ok );
	console.log( JSON.stringify( { checks, numbers, failed: failed.length } ) );
	process.exit( failed.length ? 1 : 0 );
} )();

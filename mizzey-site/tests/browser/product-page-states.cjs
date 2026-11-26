#!/usr/bin/env node
/**
 * Two states of the product page on a phone, in Chromium and WebKit at 390 with touch (spec 066, slice 9): the
 * owner's iPhone check on staging v0.1.37.
 *
 * 1. Nothing chosen (C1-Product-Phone E4, C1-Product-Desktop E4): the board draws no Buy Now while an option is
 *    missing, only "Choose options"; pressing it, or the buy bar's, names the missing group once and moves focus to
 *    the group's first option. Nothing on the page is pressable without an effect.
 * 2. A return through the browser's back-forward cache after a busy press: the page comes back as it truly is, no
 *    button stuck in its loader. Buy Now is pressed for real (it leaves for checkout), the page is restored with
 *    page.goBack(), and the restore must have come from the cache (pageshow with persisted true). Add to Cart's reset
 *    is checked on the same events while it waits for the server.
 *
 * Plan (JSON, first argument): { urls: { nodefault, simple }, checkout, shots, tag }. Prints { checks, shots }.
 * Driven by t112. Playwright is test tooling on the developer's machine (see _theme-preview.cjs).
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

async function open( ctx, url ) {
	const page = await ctx.newPage();
	page.errors = [];
	page.on( 'pageerror', ( e ) => page.errors.push( e.message ) );
	await page.addInitScript( () => {
		window.__shows = [];
		window.addEventListener( 'pageshow', ( e ) => window.__shows.push( e.persisted ) );
	} );
	await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
	await page.waitForTimeout( 600 );
	return page;
}

const shown = ( page, sel ) => page.evaluate( ( s ) => {
	const el = document.querySelector( s );
	return !! el && el.getClientRects().length > 0 && getComputedStyle( el ).visibility !== 'hidden';
}, sel );

const focusOnOption = ( page ) => page.evaluate( () => {
	const a = document.activeElement;
	return a && a.classList.contains( 'mizzey-buy__option' ) ? a.querySelector( '.mizzey-buy__option-label' ).textContent.trim() : null;
} );

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found.' } ) );
		return;
	}
	for ( const engine of [ 'chromium', 'webkit' ] ) {
		// Playwright starts Chromium with its back-forward cache off; it is turned back on here, as a phone has it.
		// Playwright's WebKit keeps no back-forward cache: there the reset is checked through the same events.
		const browser = engine === 'chromium'
			? await playwright.pw.chromium.launch( { ...playwright.launch, ignoreDefaultArgs: [ '--disable-back-forward-cache' ] } )
			: await playwright.pw.webkit.launch( { headless: true } );
		const where = `${ engine } 390`;
		try {
			const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, hasTouch: true, ...( engine === 'chromium' ? { isMobile: true } : {} ) } );

			// ---- 1. Nothing chosen ---------------------------------------------------------------------------------
			const page = await open( ctx, plan.urls.nodefault );
			const state = await page.evaluate( () => ( {
				add: ( document.querySelector( '.mizzey-buy__add .mizzey-buy__label' ) || {} ).textContent || '',
				disabled: ( document.querySelector( '.mizzey-buy__add' ) || {} ).disabled,
			} ) );
			check( ! await shown( page, '.mizzey-buy__now' ) && /choose options/i.test( state.add ) && ! state.disabled, `PDP-09 [${ where }] nothing chosen: no Buy Now (E4 draws none), "Choose options" (${ JSON.stringify( state ) })` );
			await page.locator( '.mizzey-buy__add' ).tap();
			await page.waitForTimeout( 900 );
			const msg = await page.evaluate( () => {
				const m = document.querySelector( '.mizzey-buy__message' );
				return m && ! m.hidden ? m.textContent.trim() : '';
			} );
			const focused = await focusOnOption( page );
			check( /choose a scent/i.test( msg ) && focused === 'Cedar', `PDP-05 [${ where }] "Choose options" names the missing group and takes focus to its first option ("${ msg }", focus ${ focused })` );
			await page.screenshot( { path: path.join( plan.shots, `${ plan.tag }-${ engine }-390-nothing-chosen.png` ) } );
			shots.push( path.join( plan.shots, `${ plan.tag }-${ engine }-390-nothing-chosen.png` ) );
			// The bar's button does the same. The fixture's page is short, so the window is made short enough for
			// Add to Cart to scroll away.
			await page.setViewportSize( { width: 390, height: 400 } );
			await page.evaluate( () => {
				document.activeElement.blur();
				window.scrollTo( 0, document.documentElement.scrollHeight );
			} );
			await page.waitForTimeout( 900 );
			const bar = await page.evaluate( () => {
				const b = document.querySelector( '.mizzey-buy-bar' );
				return { shown: b.classList.contains( 'is-shown' ), text: b.querySelector( '.mizzey-buy-bar__add .mizzey-buy__label' ).textContent.trim(), note: b.querySelector( '.mizzey-buy-bar__note' ).textContent.trim() };
			} );
			if ( bar.shown ) {
				await page.locator( '.mizzey-buy-bar__add' ).tap();
				await page.waitForTimeout( 1200 );
			}
			const barFocus = await focusOnOption( page );
			check( bar.shown && /choose options/i.test( bar.text ) && /choose a scent/i.test( bar.note ) && barFocus === 'Cedar', `PDP-05 [${ where }] the bar's "Choose options" takes focus to the missing group too (${ JSON.stringify( bar ) }, focus ${ barFocus })` );
			await page.setViewportSize( { width: 390, height: 844 } );
			await page.evaluate( () => window.scrollTo( 0, 0 ) );
			// Once chosen, Buy Now is there.
			await page.locator( '.mizzey-buy__option', { hasText: 'Rose' } ).tap();
			await page.waitForTimeout( 500 );
			check( await shown( page, '.mizzey-buy__now' ), `PDP-09 [${ where }] with an option chosen, Buy Now is there` );
			check( page.errors.length === 0, `[${ where }] no script error (${ JSON.stringify( page.errors.slice( 0, 2 ) ) })` );

			// ---- 3. No ring and no stuck highlight after a touch, on every control of the buying area --------------
			if ( plan.urls.var ) {
				const vp = await open( ctx, plan.urls.var );
				const ring = ( label ) => vp.evaluate( ( what ) => {
					const a = document.activeElement;
					const fv = [ ...document.querySelectorAll( ':focus-visible' ) ].filter( ( el ) => {
						const s = getComputedStyle( el );
						return s.outlineStyle !== 'none' && parseFloat( s.outlineWidth ) > 0;
					} );
					return { what, ring: fv.map( ( el ) => el.className.toString().slice( 0, 40 ) ), active: a ? a.className.toString().slice( 0, 40 ) : '' };
				}, label );
				const rest = ( sel ) => vp.locator( sel ).first().evaluate( ( el ) => {
					const c = getComputedStyle( el );
					return [ c.backgroundColor, c.textDecorationLine ].join( ' | ' );
				} );
				const controls = [
					[ '.mizzey-buy__more', 'Show full name' ],
					[ '.mizzey-buy__more', 'Show less' ],
					[ '.mizzey-gallery__thumb:not([hidden]) >> nth=1', 'a thumbnail' ],
					[ '.mizzey-buy__option[aria-checked="false"]:not(.is-out) >> nth=0', 'an option' ],
					[ '.mizzey-buy__step >> nth=1', 'quantity plus' ],
					[ '.mizzey-buy__step >> nth=0', 'quantity minus' ],
					[ '.mizzey-buy__wish', 'the heart' ],
					[ '.mizzey-buy__wish', 'the heart again' ],
				];
				const found = [];
				for ( const [ sel, label ] of controls ) {
					const before = await rest( sel );
					await vp.locator( sel ).first().tap();
					await vp.waitForTimeout( 700 );
					const r = await ring( label );
					const after = await rest( sel );
					// The option, the steps and the thumbnail change on purpose (chosen, at a limit, shown); the others
					// look as they did.
					const kept = [ 'an option', 'quantity plus', 'quantity minus', 'a thumbnail' ].includes( label ) || before === after;
					if ( r.ring.length || ! kept ) {
						found.push( { ...r, before, after } );
					}
				}
				// The dialogs: zoom (its level and close) and share (its close).
				for ( const [ open1, inner, close1, label ] of [ [ '.mizzey-gallery__zoom', '.mizzey-zoom__in', '.mizzey-zoom__close', 'zoom' ], [ '.mizzey-buy__share', null, '.mizzey-share__close', 'share' ] ] ) {
					await vp.locator( open1 ).tap();
					await vp.waitForTimeout( 700 );
					const r1 = await ring( `${ label } open` );
					if ( r1.ring.length ) {
						found.push( r1 );
					}
					if ( inner ) {
						await vp.locator( inner ).tap();
						await vp.waitForTimeout( 400 );
						const r2 = await ring( `${ label } level` );
						if ( r2.ring.length ) {
							found.push( r2 );
						}
					}
					// A phone that has its own share sheet opens that instead (E12): there is no dialog to close.
					if ( await vp.locator( `${ close1 }:visible` ).count() ) {
						await vp.locator( close1 ).tap();
					}
					await vp.waitForTimeout( 600 );
					const r3 = await ring( `${ label } closed` );
					if ( r3.ring.length ) {
						found.push( r3 );
					}
				}
				await vp.screenshot( { path: path.join( plan.shots, `${ plan.tag }-${ engine }-390-after-taps.png` ) } );
				shots.push( path.join( plan.shots, `${ plan.tag }-${ engine }-390-after-taps.png` ) );
				check( found.length === 0, `NFR-11 [${ where }] after a tap on each control of the buying area, no focus ring shows and no pressed or hover colour stays (${ JSON.stringify( found ).slice( 0, 600 ) })` );
				// The keyboard still shows the ring.
				await vp.keyboard.press( 'Tab' );
				await vp.keyboard.press( 'Tab' );
				const kb = await ring( 'keyboard' );
				check( kb.ring.length === 1, `NFR-11 [${ where }] the keyboard still shows the ring (${ JSON.stringify( kb ) })` );
				await vp.close();
			}

			// ---- 4. Zoom on a phone: at 160% a finger drags the photo 1:1, held in the frame; two fingers pinch; a
			// double tap zooms at the tap. Touch pointers are given to the page as a finger gives them.
			if ( plan.urls.var ) {
				const zp = await open( ctx, plan.urls.var );
				await zp.locator( '.mizzey-gallery__zoom' ).tap();
				await zp.waitForTimeout( 800 );
				await zp.locator( '.mizzey-zoom__in' ).tap();
				await zp.locator( '.mizzey-zoom__in' ).tap();
				// The gestures are a module fetched on the first zoom: the two presses land once it has come.
				await zp.waitForFunction( () => document.querySelector( '.mizzey-zoom__value' ).textContent.trim() === '160%', null, { timeout: TIMEOUT } ).catch( () => {} );
				await zp.waitForTimeout( 300 );
				const finger = ( moves ) => zp.evaluate( ( list ) => {
					const view = document.querySelector( '.mizzey-zoom__view' );
					const b = view.getBoundingClientRect();
					const at = ( id, x, y, type ) => view.dispatchEvent( new PointerEvent( type, { pointerId: id, pointerType: 'touch', isPrimary: id === 1, clientX: b.left + b.width / 2 + x, clientY: b.top + b.height / 2 + y, bubbles: true } ) );
					list.forEach( ( [ type, id, x, y ] ) => at( id, x, y, type ) );
				}, moves );
				const where2 = () => zp.evaluate( () => {
					const img = document.querySelector( '.mizzey-zoom__image' );
					const view = document.querySelector( '.mizzey-zoom__view' ).getBoundingClientRect();
					const m = new DOMMatrix( getComputedStyle( img ).transform );
					const ratio = img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : 1;
					const fitW = Math.min( view.width, view.height * ratio );
					const fitH = fitW / ratio;
					return { x: m.e, y: m.f, scale: m.a, maxX: Math.max( 0, ( fitW * m.a - view.width ) / 2 ), maxY: Math.max( 0, ( fitH * m.a - view.height ) / 2 ), value: document.querySelector( '.mizzey-zoom__value' ).textContent.trim() };
				} );
				const z0 = await where2();
				await finger( [ [ 'pointerdown', 1, 0, 0 ], [ 'pointermove', 1, 20, 0 ], [ 'pointermove', 1, 40, 0 ], [ 'pointermove', 1, 50, 0 ] ] );
				await zp.waitForTimeout( 200 );
				const z1 = await where2();
				await finger( [ [ 'pointerup', 1, 50, 0 ] ] );
				await zp.waitForTimeout( 900 );
				check( z0.value === '160%' && Math.abs( ( z1.x - z0.x ) - Math.min( 50, z0.maxX - z0.x ) ) < 1, `PDP-01 [${ where }] at 160% a finger moves the photo with it, 1:1 (${ JSON.stringify( { z0, z1 } ) })` );
				await finger( [ [ 'pointerdown', 1, 0, 0 ], [ 'pointermove', 1, 900, 600 ], [ 'pointermove', 1, 2000, 2000 ], [ 'pointerup', 1, 2000, 2000 ] ] );
				await zp.waitForTimeout( 900 );
				const z2 = await where2();
				check( z2.x <= z2.maxX + 0.5 && z2.y <= z2.maxY + 0.5 && z2.x >= -z2.maxX - 0.5, `PDP-01 [${ where }] however far it is dragged, the photo's edge stays outside the frame (${ JSON.stringify( z2 ) })` );
				await zp.screenshot( { path: path.join( plan.shots, `${ plan.tag }-${ engine }-390-zoom-dragged.png` ) } );
				shots.push( path.join( plan.shots, `${ plan.tag }-${ engine }-390-zoom-dragged.png` ) );
				// Two fingers apart: further in.
				await finger( [ [ 'pointerdown', 1, -20, 0 ], [ 'pointerdown', 2, 20, 0 ], [ 'pointermove', 1, -60, 0 ], [ 'pointermove', 2, 60, 0 ], [ 'pointerup', 1, -60, 0 ], [ 'pointerup', 2, 60, 0 ] ] );
				await zp.waitForTimeout( 400 );
				const z3 = await where2();
				check( parseInt( z3.value, 10 ) > 160, `PDP-01 [${ where }] two fingers moving apart zoom in (${ z3.value })` );
				// A double tap: back to 100%; another: in to 200% at the tap.
				await zp.waitForTimeout( 400 );
				await finger( [ [ 'pointerdown', 1, 0, 0 ], [ 'pointerup', 1, 0, 0 ], [ 'pointerdown', 1, 0, 0 ], [ 'pointerup', 1, 0, 0 ] ] );
				await zp.waitForTimeout( 300 );
				const z4 = await where2();
				await zp.waitForTimeout( 400 );
				await finger( [ [ 'pointerdown', 1, 30, 30 ], [ 'pointerup', 1, 30, 30 ], [ 'pointerdown', 1, 30, 30 ], [ 'pointerup', 1, 30, 30 ] ] );
				await zp.waitForTimeout( 300 );
				const z5 = await where2();
				check( z4.value === '100%' && z5.value === '200%', `PDP-01 [${ where }] a double tap zooms out, another zooms in to 200% (${ z4.value }, ${ z5.value })` );
				check( zp.errors.length === 0, `[${ where }] no script error in the zoom (${ JSON.stringify( zp.errors.slice( 0, 2 ) ) })` );
				await zp.close();
			}

			// ---- 2. Back from checkout through the back-forward cache, after Buy Now's busy press -----------------
			const simple = await open( ctx, plan.urls.simple );
			await simple.locator( '.mizzey-buy__now' ).tap( { noWaitAfter: true } );
			await simple.waitForURL( ( u ) => u.href.startsWith( plan.checkout ), { timeout: TIMEOUT } ).catch( () => {} );
			const left = simple.url();
			// A page restored from the cache fires no load event: the navigation's commit is what is awaited.
			await simple.goBack( { waitUntil: 'commit', timeout: TIMEOUT } ).catch( () => {} );
			await simple.waitForTimeout( 800 );
			const back = await simple.evaluate( () => {
				const now = document.querySelector( '.mizzey-buy__now' );
				const loader = now && now.querySelector( '.mizzey-buy__loader' );
				return {
					persisted: ( window.__shows || [] ).includes( true ),
					busy: now ? now.getAttribute( 'aria-busy' ) : 'missing',
					loader: loader ? getComputedStyle( loader ).display : 'missing',
					label: now ? getComputedStyle( now.querySelector( '.mizzey-buy__label' ) ).visibility : 'missing',
				};
			} );
			await simple.screenshot( { path: path.join( plan.shots, `${ plan.tag }-${ engine }-390-back.png` ) } );
			shots.push( path.join( plan.shots, `${ plan.tag }-${ engine }-390-back.png` ) );
			if ( engine === 'chromium' ) {
				check( left.startsWith( plan.checkout ) && back.persisted, `[${ where }] Buy Now left for checkout and Back restored the page from the back-forward cache (${ left }, persisted ${ back.persisted })` );
			} else {
				check( left.startsWith( plan.checkout ), `[${ where }] Buy Now left for checkout and Back came back (WebKit here reloads it: persisted ${ back.persisted })` );
			}
			check( back.busy === null && back.loader === 'none' && back.label === 'visible', `PDP-09 [${ where }] back from checkout, Buy Now is not stuck in its loader (${ JSON.stringify( back ) })` );

			// Add to Cart (and the bar) while the server has not answered, then the page is hidden and shown from the
			// cache: the buttons come back as they are, and the header's count is read again.
			await simple.evaluate( () => {
				[ '.mizzey-buy__add', '.mizzey-buy-bar__add' ].forEach( ( s ) => {
					const b = document.querySelector( s );
					b.setAttribute( 'aria-busy', 'true' );
				} );
				window.dispatchEvent( new PageTransitionEvent( 'pagehide', { persisted: true } ) );
				window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) );
			} );
			await simple.waitForTimeout( 600 );
			const reset = await simple.evaluate( () => [ '.mizzey-buy__add', '.mizzey-buy-bar__add' ].map( ( s ) => document.querySelector( s ).getAttribute( 'aria-busy' ) ) );
			check( reset.every( ( v ) => v === null ), `PDP-08 [${ where }] a page shown from the cache drops Add to Cart's and the bar's busy state (${ JSON.stringify( reset ) })` );
			check( simple.errors.length === 0, `[${ where }] no script error on the restored page (${ JSON.stringify( simple.errors.slice( 0, 2 ) ) })` );
			await ctx.close();
		} catch ( e ) {
			check( false, `[${ where }] the run stopped: ${ e.message.split( '\n' ).slice( 0, 3 ).join( ' ' ) }` );
		} finally {
			await browser.close();
		}
	}
	console.log( JSON.stringify( { checks, shots } ) );
} )();

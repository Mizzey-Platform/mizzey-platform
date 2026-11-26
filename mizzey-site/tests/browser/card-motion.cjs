#!/usr/bin/env node
/**
 * The card's add sequence, its count, and the toast on a phone, in a real browser (spec 066, slices 1 and 4; PLP-04,
 * NFR-11). Driven by t107 over its fixture, after product-card.cjs; prints one JSON object: { checks, shots }.
 *
 * Measured against C1-Motion-Playground ("Add to cart, confirmed by the server": press within 100 ms; "Adding" from
 * 400 ms for at least 500; only then the product flies to the cart, the count bumps and rolls, the White tick draws
 * and "Added" holds 1500 ms) and C1-Components card states 7 and 8, with the owner's ruling of 25 November 2026: after
 * the tick the card returns to "+" with the quantity in the cart, a count bubble on the disc styled as the header's
 * count and line 2 "N in your cart"; the quantities come from one read of the cart for the page and follow every
 * change. A toast on a phone can be swiped away sideways. A tap leaves no focus ring.
 *
 * Run in Chromium and in WebKit (the owner tests on an iPhone), at 390 with touch and at 1440, in English and Arabic,
 * and under reduced motion.
 */

const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { loadPlaywright } = require( './_theme-preview.cjs' );

process.on( 'unhandledRejection', () => {} );

const plan = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const checks = [];
const shots = [];
const check = ( ok, what ) => checks.push( { ok: !! ok, what } );
const TIMEOUT = 120000;
const ID = plan.ids;
const near = ( a, b, tolerance = 1 ) => Math.abs( a - b ) <= tolerance;
const card = ( list, id ) => `.mizzey-products--${ list } .mizzey-card[data-product-id="${ id }"]`;

/** A recorder in the page: one sample per frame of the card, the flight, the header's count and the tick. */
function recorder() {
	window.__mizzeyRec = ( sel ) => {
		const frames = [];
		const t0 = performance.now();
		let on = true;
		const tick = () => {
			if ( ! on ) {
				return;
			}
			const c = document.querySelector( sel );
			const fly = document.querySelector( '.mizzey-card-flight' );
			const cart = [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length );
			const badge = cart && cart.querySelector( '.mizzey-count' );
			const path = c && c.querySelector( '.mizzey-card__tick path' );
			const r = ( el ) => {
				const b = el.getBoundingClientRect();
				return { x: b.left + b.width / 2, y: b.top + b.height / 2, w: b.width };
			};
			const vis = ( el ) => !! el && el.getClientRects().length > 0 && getComputedStyle( el ).visibility !== 'hidden';
			frames.push( {
				t: performance.now(),
				classes: c ? c.className : '',
				fly: fly ? { ...r( fly.querySelector( '.mizzey-card-flight__item' ) || fly ), o: parseFloat( getComputedStyle( fly.querySelector( '.mizzey-card-flight__item' ) || fly ).opacity ) } : null,
				badge: badge ? { text: badge.textContent.trim(), hidden: badge.hidden, spans: badge.classList.contains( 'is-rolling' ) ? 2 : 0, transform: getComputedStyle( badge ).transform } : null,
				dash: path && vis( path ) ? parseFloat( getComputedStyle( path ).strokeDashoffset ) || 0 : null,
				added: c && vis( c.querySelector( '.mizzey-card__added' ) ),
				loader: c && vis( c.querySelector( '.mizzey-card__loader' ) ),
				qty: c && vis( c.querySelector( '.mizzey-card__qty' ) ) ? c.querySelector( '.mizzey-card__qty' ).textContent.trim() : '',
				bubble: c && vis( c.querySelector( '.mizzey-card__bubble' ) ) ? c.querySelector( '.mizzey-card__bubble' ).textContent.trim() : '',
			} );
			requestAnimationFrame( tick );
		};
		requestAnimationFrame( tick );
		// Exact times of the changes, whatever the frame rate: the card's classes, and the flight coming and going.
		const marks = [];
		const card = document.querySelector( sel );
		const watch = new MutationObserver( ( list ) => list.forEach( ( m ) => {
			if ( m.type === 'attributes' ) {
				marks.push( { t: performance.now(), classes: card.className } );
			} else {
				m.addedNodes.forEach( ( n ) => {
					if ( ! n.classList || ! n.classList.contains( 'mizzey-card-flight' ) ) {
						return;
					}
					marks.push( { t: performance.now(), flight: 'on' } );
					// Where it starts and where it is set to land, read as it is put in the page (before it moves): the
					// card and the cart at that moment, the item's centre, and the last keyframes of its two moves.
					const item = n.querySelector( '.mizzey-card-flight__item' ) || n;
					const num = ( a, axis ) => {
						const last = a ? a.effect.getKeyframes().pop().transform || '' : '';
						const hit = last.match( axis === 'X' ? /translateX\((-?[\d.]+)px\)/ : /translateY\((-?[\d.]+)px\)/ );
						return hit ? parseFloat( hit[ 1 ] ) : NaN;
					};
					const across = n.getAnimations()[ 0 ];
					const up = item.getAnimations().find( ( a ) => a.effect.getKeyframes().some( ( k ) => /translateY/.test( k.transform || '' ) ) );
					const b = item.getBoundingClientRect();
					const well = card.querySelector( '.mizzey-card__well' ).getBoundingClientRect();
					const cart = [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length );
					const cr = cart ? cart.getBoundingClientRect() : null;
					window.__mizzeyFlight = {
						duration: across ? across.effect.getComputedTiming().duration : null,
						start: { x: b.left + b.width / 2, y: b.top + b.height / 2 },
						end: { x: b.left + b.width / 2 + num( across, 'X' ), y: b.top + b.height / 2 + num( up, 'Y' ) },
						well: { x: well.left + well.width / 2, y: well.top + well.height / 2 },
						cart: cr ? ( () => {
							const row = cart.closest( '.mizzey-header__bar' ) || cart.closest( '.mizzey-header' );
							const y = cr.bottom > 0 ? cr.top + cr.height / 2 : cr.top - ( row ? row.getBoundingClientRect().top : cr.top ) + cr.height / 2;
							return { x: cr.left + cr.width / 2, y };
						} )() : null,
					};
				} );
				m.removedNodes.forEach( ( n ) => n.classList && n.classList.contains( 'mizzey-card-flight' ) && marks.push( { t: performance.now(), flight: 'off' } ) );
			}
		} ) );
		watch.observe( card, { attributes: true, attributeFilter: [ 'class' ] } );
		watch.observe( document.body, { childList: true } );
		return { stop: () => {
			on = false;
			watch.disconnect();
		}, frames, marks, t0 };
	};
	window.__mizzeyPress = 0;
	document.addEventListener( 'pointerdown', () => ( window.__mizzeyPress = performance.now() ), true );
	document.addEventListener( 'click', () => ( window.__mizzeyPress = window.__mizzeyPress || performance.now() ), true );
}

/** Where things are: the card's image well and the visible header cart, as centres. */
function places( page, sel ) {
	return page.evaluate( ( s ) => {
		// The cart where it is, or, scrolled off the top, where the sticky header row puts it.
		const at = ( cart, cr ) => {
			const row = cart.closest( '.mizzey-header__bar' ) || cart.closest( '.mizzey-header' );
			const y = cr.bottom > 0 ? cr.top + cr.height / 2 : cr.top - ( row ? row.getBoundingClientRect().top : cr.top ) + cr.height / 2;
			return { x: cr.left + cr.width / 2, y };
		};
		const c = document.querySelector( s );
		const well = c.querySelector( '.mizzey-card__well' ).getBoundingClientRect();
		const cart = [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length );
		const cr = cart ? cart.getBoundingClientRect() : null;
		return {
			well: { x: well.left + well.width / 2, y: well.top + well.height / 2 },
			cart: cr ? at( cart, cr ) : null,
			count: cart && cart.querySelector( '.mizzey-count' ) && ! cart.querySelector( '.mizzey-count' ).hidden ? cart.querySelector( '.mizzey-count' ).textContent.trim() : '',
		};
	}, sel );
}

/**
 * Press the "+" of one card with the server held back `hold` ms, record every frame for `span` ms after, and return
 * the frames, the press and answer times (the page's clock) and the server's answer.
 */
async function addAndRecord( page, sel, { hold = 0, span = 2600, shotsAt = null, shotTag = '' } = {} ) {
	let release;
	const gate = new Promise( ( resolve ) => ( release = resolve ) );
	const route = async ( r ) => {
		await gate;
		await r.continue();
	};
	await page.route( '**/wc/store/v1/cart/add-item**', route );
	await page.locator( sel ).scrollIntoViewIfNeeded();
	await page.waitForTimeout( 200 );
	const at = await places( page, sel );
	await page.evaluate( ( s ) => {
		window.__mizzeyPress = 0;
		window.__mizzeyFlight = null;
		window.__rec = window.__mizzeyRec( s );
	}, sel );
	const answer = page.waitForResponse( ( res ) => res.url().includes( '/cart/add-item' ), { timeout: TIMEOUT } );
	await page.locator( `${ sel } .mizzey-card__add` ).click();
	setTimeout( release, hold );
	const response = await answer;
	const body = await response.json().catch( () => ( {} ) );
	const taken = [];
	if ( shotsAt ) {
		// The frame sequence: screenshots a fixed time after the answer.
		const start = Date.now();
		for ( const at of shotsAt ) {
			const wait = at - ( Date.now() - start );
			if ( wait > 0 ) {
				await page.waitForTimeout( wait );
			}
			const file = path.join( plan.shots, `${ plan.tag }-${ shotTag }-flight-${ String( at ).padStart( 4, '0' ) }.png` );
			await page.screenshot( { path: file } );
			taken.push( file );
		}
	}
	await page.waitForTimeout( span );
	const rec = await page.evaluate( () => {
		window.__rec.stop();
		const add = performance.getEntriesByType( 'resource' ).filter( ( e ) => e.name.includes( '/cart/add-item' ) ).pop();
		return { flight: window.__mizzeyFlight, frames: window.__rec.frames, marks: window.__rec.marks, press: window.__mizzeyPress, answer: add ? add.responseEnd : 0 };
	} );
	await page.unroute( '**/wc/store/v1/cart/add-item**', route );
	return { ...rec, at, status: response.status(), body, shots: taken };
}

/** The add sequence, measured from the frames, against the board. */
function sequence( where, rec, at, { reduce = false, hold = 0, wasCount = '' } = {} ) {
	const f = rec.frames;
	const after = ( t ) => f.filter( ( x ) => x.t >= t );
	const before = ( t ) => f.filter( ( x ) => x.t < t );
	const count = String( rec.body.items_count );
	check( rec.status === 201 || rec.status === 200, `PLP-04 [${ where }] the add reached the Store API (HTTP ${ rec.status }${ rec.body && rec.body.message ? ': ' + rec.body.message : '' })` );
	// Nothing claims success before the answer: no flight, no tick, no "Added", the header's count unchanged.
	const early = before( rec.answer );
	check( early.every( ( x ) => ! x.fly && ! x.added && ( ! x.badge || x.badge.text === wasCount || x.badge.hidden ) ),
		`PLP-04 [${ where }] before the server answers: no flight, no "Added", the count unchanged (${ early.length } frames)` );
	if ( hold >= 600 ) {
		// The first stretch of frames with the loader showing, from its first frame to the first frame without it.
		const lon = rec.marks.find( ( m ) => m.classes && /\bis-loading\b/.test( m.classes ) );
		const loff = lon && rec.marks.find( ( m ) => m.t > lon.t && m.classes && ! /\bis-loading\b/.test( m.classes ) );
		const first = lon ? lon.t - rec.press : null;
		const last = lon && loff ? loff.t - lon.t : null;
		check( first !== null && first >= 360 && first <= 520, `C1-Motion [${ where }] "Adding" shows from 400 ms after the press (${ first === null ? 'never' : Math.round( first ) } ms)` );
		check( last !== null && last >= 460, `C1-Motion [${ where }] "Adding" stays at least 500 ms (${ last === null ? '-' : Math.round( last ) } ms)` );
	}
	const flights = f.filter( ( x ) => x.fly ).map( ( x ) => ( { t: x.t, ...x.fly } ) );
	if ( reduce ) {
		check( flights.length === 0, `K-5 [${ where }] under reduced motion no product flies (${ flights.length } frames)` );
		const changed = after( rec.answer ).find( ( x ) => x.badge && x.badge.text === count && ! x.badge.hidden );
		check( changed && changed.t - rec.answer <= 250, `K-5 [${ where }] under reduced motion the count changes on the answer, to ${ count } (${ changed ? Math.round( changed.t - rec.answer ) : '-' } ms)` );
		check( f.every( ( x ) => ! x.badge || x.badge.spans === 0 ), `K-5 [${ where }] under reduced motion the count does not roll` );
		const dashes = f.filter( ( x ) => x.dash !== null ).map( ( x ) => x.dash );
		check( dashes.length && dashes.every( ( d ) => d === 0 ), `K-5 [${ where }] under reduced motion the tick shows without drawing (${ JSON.stringify( [ ...new Set( dashes ) ].slice( 0, 4 ) ) })` );
	} else {
		const start = flights[ 0 ];
		const end = flights[ flights.length - 1 ];
		check( start && start.t >= rec.answer - 20, `C1-Motion [${ where }] the product flies only after the answer (${ start ? Math.round( start.t - rec.answer ) : 'never' } ms after)` );
		if ( start ) {
			const on = rec.marks.find( ( m ) => m.flight === 'on' );
			const off = rec.marks.find( ( m ) => m.flight === 'off' );
			// The flight's own timing, as it was set; the marks and frames come late on a loaded machine.
			const length = rec.flight && rec.flight.duration ? rec.flight.duration : on && off ? off.t - on.t : end.t - start.t;
			check( length >= 480 && length <= 760, `C1-Motion [${ where }] the flight takes 600 ms (${ Math.round( length ) })` );
			const fl = rec.flight || { start, end, well: at.well, cart: at.cart };
			check( near( fl.start.x, fl.well.x, 2 ) && near( fl.start.y, fl.well.y, 2 ), `C1-Motion [${ where }] it leaves from the product's image (${ Math.round( fl.start.x ) },${ Math.round( fl.start.y ) } against ${ Math.round( fl.well.x ) },${ Math.round( fl.well.y ) })` );
			check( fl.end.y > 0, `C1-Motion [${ where }] it never flies off the top of the screen (lands at y ${ Math.round( fl.end.y ) })` );
			check( fl.cart && near( fl.end.x, fl.cart.x, 2 ) && near( fl.end.y, fl.cart.y, 2 ), `C1-Motion [${ where }] it lands on the header's cart (${ Math.round( fl.end.x ) },${ Math.round( fl.end.y ) } against ${ fl.cart ? Math.round( fl.cart.x ) + ',' + Math.round( fl.cart.y ) : '-' })` );
			// A curve: the middle of the path stands off the straight line from start to end.
			const mid = flights[ Math.floor( flights.length / 2 ) ];
			const dx = end.x - start.x;
			const dy = end.y - start.y;
			const bend = Math.abs( dy * mid.x - dx * mid.y + end.x * start.y - end.y * start.x ) / Math.hypot( dx, dy );
			check( bend >= 8, `C1-Motion [${ where }] it flies along a curve (the middle is ${ Math.round( bend ) } px off the straight line)` );
			check( end.w < start.w * 0.6, `C1-Motion [${ where }] it shrinks as it lands (${ Math.round( start.w ) } to ${ Math.round( end.w ) })` );
		}
		const landed = start ? start.t + 540 : rec.answer + 540;
		const changed = f.find( ( x ) => x.badge && x.badge.text === count && ! x.badge.hidden && x.badge.spans === 0 ) || f.find( ( x ) => x.badge && x.badge.spans > 0 );
		check( changed && changed.t >= landed - 60, `C1-Motion [${ where }] the count changes as the product lands, not before (${ changed ? Math.round( changed.t - rec.answer ) : '-' } ms after the answer)` );
		check( f.some( ( x ) => x.badge && x.badge.spans >= 2 ) || wasCount === '', `C1-Motion [${ where }] the count rolls from ${ wasCount || 'none' } to ${ count }` );
		const scales = f.filter( ( x ) => x.badge && /matrix/.test( x.badge.transform ) ).map( ( x ) => parseFloat( x.badge.transform.split( '(' )[ 1 ] ) );
		check( scales.some( ( s ) => s > 1.08 ), `C1-Motion [${ where }] the count bumps (largest scale ${ scales.length ? Math.max( ...scales ).toFixed( 2 ) : '-' })` );
		const dashes = after( rec.answer ).filter( ( x ) => x.dash !== null ).map( ( x ) => x.dash );
		check( dashes.some( ( d ) => d > 4 ) && dashes.some( ( d ) => d === 0 ), `C1-Motion [${ where }] the White tick draws (${ dashes.length ? Math.round( Math.max( ...dashes ) ) : '-' } to 0)` );
	}
	const addedOn = rec.marks.find( ( m ) => m.classes && /\bis-added\b/.test( m.classes ) );
	const addedOff = addedOn && rec.marks.find( ( m ) => m.t > addedOn.t && m.classes && ! /\bis-added\b/.test( m.classes ) );
	const held = addedOn && addedOff ? addedOff.t - addedOn.t : 0;
	check( addedOn && addedOn.t >= rec.answer - 20 && held >= 1400 && held <= 1700, `C1-Motion [${ where }] "Added" holds 1500 ms (${ Math.round( held ) })` );
	const last = f[ f.length - 1 ];
	return { last, held };
}

/** The quantity a card shows after the hold, from the last frame. */
function rested( where, last, want ) {
	check( ! /is-added/.test( last.classes ) && ! last.added, `PLP-04 [${ where }] after the hold the card returns to its "+" (${ last.classes })` );
	check( last.bubble === String( want ), `owner 25 Nov [${ where }] the "+" carries the count bubble "${ want }" (${ last.bubble || 'none' })` );
	check( new RegExp( `${ want }` ).test( last.qty ) && last.qty !== '', `owner 25 Nov [${ where }] line 2 reads the quantity in the cart: "${ last.qty }"` );
}

/** Drag a toast with synthetic touch pointer events: dx in `steps` moves over `ms`; returns what the toast did. */
async function swipe( page, dx, { ms = 300, steps = 10, holdMs = 0 } = {} ) {
	return page.evaluate( async ( [ dx, ms, steps, holdMs ] ) => {
		const n = document.querySelector( '.mizzey-card-toasts .mizzey-notice' );
		if ( ! n ) {
			return { none: true };
		}
		const b = n.getBoundingClientRect();
		const x0 = b.left + b.width / 2;
		const y0 = b.top + b.height / 2;
		/* Each event carries the time a finger would give it (`at` ms after the press), not the time the test got round
		   to sending it: on a loaded machine the page's work between two moves can take 40 ms, which turns a 60 ms flick
		   into a 140 ms drag (seen in WebKit, 26 November). The swipe reads the events' own times, as on a device. */
		let t0 = 0;
		const fire = ( type, x, at ) => {
			const e = new PointerEvent( type, { bubbles: true, cancelable: true, composed: true, pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: x, clientY: y0, buttons: type === 'pointerup' ? 0 : 1 } );
			if ( 'pointerdown' === type ) {
				t0 = e.timeStamp;
			}
			Object.defineProperty( e, 'timeStamp', { value: t0 + at } );
			return n.dispatchEvent( e );
		};
		// Short waits are spun, so a flick's timing does not depend on how late the engine runs its timers.
		const wait = ( t ) => {
			if ( t >= 40 ) {
				return new Promise( ( r ) => setTimeout( r, t ) );
			}
			const end = performance.now() + t;
			while ( performance.now() < end ) {
				// Spin.
			}
			return Promise.resolve();
		};
		const life = n.querySelector( '.mizzey-notice__life' );
		// What the toast receives, for the report when a check fails.
		const got = [];
		[ 'pointerdown', 'pointermove', 'pointerup', 'pointercancel' ].forEach( ( t ) => n.addEventListener( t, ( e ) => got.push( [ t.slice( 7 ), Math.round( e.clientX ), Math.round( e.timeStamp ), typeof e.getCoalescedEvents === 'function' ? e.getCoalescedEvents().length : -1 ] ) ) );
		fire( 'pointerdown', x0, 0 );
		const seen = [];
		for ( let i = 1; i <= steps; i++ ) {
			await wait( ms / steps );
			fire( 'pointermove', x0 + ( dx * i ) / steps, ( ms * i ) / steps );
			seen.push( n.getBoundingClientRect().left - b.left );
		}
		const heldLife = life ? getComputedStyle( life ).animationPlayState : null;
		if ( holdMs ) {
			await wait( holdMs );
		}
		const stillThere = n.isConnected;
		fire( 'pointerup', x0 + dx, ms + holdMs );
		const out = [];
		for ( let i = 0; i < 12; i++ ) {
			await wait( 50 );
			out.push( n.isConnected ? { x: n.getBoundingClientRect().left - b.left, o: parseFloat( getComputedStyle( n ).opacity ) } : null );
		}
		return { width: b.width, follow: seen, heldLife, stillThere, after: out, gone: ! n.isConnected, got, classes: n.className };
	}, [ dx, ms, steps, holdMs ] );
}

/** Show a fresh toast: the "added" one, by an add through the card. */
async function freshToast( page, sel ) {
	// Every earlier add answered first, so no late toast replaces this one while it is swiped (a slow server).
	const settled = () => page.waitForFunction( () => ! document.querySelector( '.mizzey-card.is-busy' ), null, { timeout: 30000 } ).catch( () => null );
	await settled();
	await page.evaluate( () => document.querySelectorAll( '.mizzey-card-toasts .mizzey-notice' ).forEach( ( n ) => n.remove() ) );
	const answer = page.waitForResponse( ( r ) => r.url().includes( '/cart/add-item' ), { timeout: TIMEOUT } );
	await page.locator( `${ sel } .mizzey-card__add` ).click();
	await answer;
	await page.waitForSelector( '.mizzey-card-toasts .mizzey-notice', { timeout: TIMEOUT } );
	await settled();
	await page.waitForTimeout( 450 );
}

async function toastChecks( page, where, sel, { reduce = false } = {} ) {
	const rtl = where.startsWith( 'ar' );
	await freshToast( page, sel );
	// Short and slow: it follows the finger, the life line pauses while held, and it springs back.
	const short = await swipe( page, rtl ? -60 : 60, { ms: 400, holdMs: 200 } );
	if ( short.none ) {
		return check( false, `[${ where }] a toast to swipe` );
	}
	const followed = short.follow[ short.follow.length - 1 ];
	if ( reduce ) {
		check( short.follow.every( ( x ) => near( x, 0, 1 ) ), `K-5 [${ where }] under reduced motion the toast does not follow the finger (${ Math.round( followed ) })` );
	} else {
		check( near( Math.abs( followed ), 60, 6 ), `C1-Notices [${ where }] the toast follows the finger (${ Math.round( followed ) } of 60)` );
		check( short.heldLife === 'paused', `C1-Notices [${ where }] the life line pauses while the toast is held (${ short.heldLife })` );
		const back = short.after.filter( Boolean ).pop();
		check( ! short.gone && back && near( back.x, 0, 1 ), `C1-Notices [${ where }] released under 35% of its width (60 of ${ Math.round( short.width ) }), it springs back (${ back ? Math.round( back.x ) : 'gone' })` );
	}
	// Past 35%, slowly: it slides out (or fades, under reduced motion) and is dismissed.
	await freshToast( page, sel );
	const far = await swipe( page, ( rtl ? -1 : 1 ) * Math.round( short.width * 0.45 ), { ms: 600 } );
	const moved = far.after.filter( Boolean );
	check( far.gone, `C1-Notices [${ where }] past 35% of its width it is dismissed (${ far.gone ? 'gone' : 'still there' })` );
	if ( reduce ) {
		check( moved.every( ( m ) => near( m.x, 0, 1 ) ), `K-5 [${ where }] under reduced motion it fades instead of sliding (${ JSON.stringify( moved.slice( 0, 3 ) ) })` );
	} else {
		check( moved.length === 0 || Math.abs( moved[ moved.length - 1 ].x ) > far.width * 0.45, `C1-Notices [${ where }] it slides out the way it was swiped (${ JSON.stringify( moved.slice( -2 ) ) })` );
	}
	// A fast flick, the other way: it goes too.
	await freshToast( page, sel );
	const flick = await swipe( page, rtl ? 60 : -60, { ms: 60, steps: 4 } );
	check( flick.gone, `C1-Notices [${ where }] a fast flick (60 px in 60 ms) dismisses it${ flick.gone ? '' : ` (${ JSON.stringify( { follow: flick.follow, after: flick.after && flick.after.slice( 0, 3 ), got: flick.got, classes: flick.classes } ) })` }` );
	// The close button stays.
	await freshToast( page, sel );
	const close = page.locator( '.mizzey-card-toasts .mizzey-notice .mizzey-notice__close' );
	check( await close.count() === 1, `C1-Notices [${ where }] the close button is still there` );
	await close.tap().catch( () => close.click() );
	await page.waitForTimeout( 300 );
	check( await page.locator( '.mizzey-card-toasts .mizzey-notice' ).count() === 0, `C1-Notices [${ where }] the close button dismisses it` );
}

/** A tap leaves no focus ring, and no grey tap highlight: the hero pause, the "+" and the heart (item 4). */
async function tapChecks( page, where, sel ) {
	for ( const [ name, target ] of [ [ 'heart', `${ sel } .mizzey-card__heart` ], [ '+', `${ sel } .mizzey-card__add` ] ] ) {
		const el = page.locator( target );
		await el.scrollIntoViewIfNeeded();
		await el.tap();
		await page.waitForTimeout( 250 );
		const s = await el.evaluate( ( e ) => ( {
			focusVisible: e.matches( ':focus-visible' ),
			outline: getComputedStyle( e ).outlineStyle,
			highlight: getComputedStyle( e ).webkitTapHighlightColor || getComputedStyle( e ).getPropertyValue( '-webkit-tap-highlight-color' ),
		} ) );
		check( ! s.focusVisible && s.outline === 'none', `NFR-11 [${ where }] a tap on the ${ name } leaves no focus ring (${ JSON.stringify( s ) })` );
		if ( name === 'heart' ) {
			// Pressed on: it grows 0.8 to 1 with one ring (C1-Motion-Playground); a page opening with it saved plays nothing.
			const pop = await el.evaluate( ( e ) => ( { fill: getComputedStyle( e.querySelector( '.mizzey-card__heart-fill' ) ).animationName, ring: getComputedStyle( e, '::after' ).animationName, pressed: e.getAttribute( 'aria-pressed' ) } ) );
			check( pop.pressed === 'true' && pop.fill === 'mizzey-card-pop' && pop.ring === 'mizzey-card-ring', `C1-Motion [${ where }] the heart pops 0.8 to 1 with its ring (${ JSON.stringify( pop ) })` );
		}
		if ( s.highlight === '' ) {
			// This WebKit build does not implement the property (it is iOS Safari's); Chromium's run checks the rule.
			continue;
		}
		check( /rgba\(0, 0, 0, 0\)|transparent/.test( s.highlight ), `NFR-11 [${ where }] a tap on the ${ name } draws no grey tap highlight (${ s.highlight })` );
	}
	// The keyboard still gets its ring.
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Shift+Tab' );
	const ring = await page.evaluate( () => ( document.activeElement ? { fv: document.activeElement.matches( ':focus-visible' ), outline: getComputedStyle( document.activeElement ).outlineStyle } : null ) );
	check( ring && ring.fv && ring.outline !== 'none', `NFR-11 [${ where }] the keyboard still shows the focus ring (${ JSON.stringify( ring ) })` );
	// Wait out the hold the tap on "+" started.
	await page.waitForTimeout( 2200 );
}

/**
 * The options sheet on a phone against C1-Category-Phone frame 5 (two options, Size and Flavour): 384 high; the
 * title 25.5 under the top; the name 68 and the price 108; the first option row 172 and the second 256; the Regular
 * 52 "Add to cart" 206 wide at 316 with its glow, 16 from the end edge and 16 above the bottom; no rule above the
 * foot; the stepper's number 500; the Close icon 20.
 */
async function sheetChecks( page, where, id ) {
	await page.locator( `${ card( 'grid', id ) } .mizzey-card__add` ).tap();
	const dialog = page.locator( `${ card( 'grid', id ) } dialog[open]` );
	await dialog.waitFor( { timeout: TIMEOUT } );
	await page.waitForTimeout( 700 );
	// Opened by a touch: no focus ring and no tap highlight anywhere in the sheet (the owner saw one on "10 oz").
	const rings = () => dialog.evaluate( ( d ) => [ ...d.querySelectorAll( '*' ) ].filter( ( e ) => {
		const st = getComputedStyle( e );
		return st.outlineStyle !== 'none' && parseFloat( st.outlineWidth ) > 0;
	} ).map( ( e ) => `${ e.tagName.toLowerCase() }.${ ( e.className.baseVal === undefined ? e.className : '' ).split( ' ' )[ 0 ] } "${ e.textContent.trim().slice( 0, 12 ) }"${ e.matches( ':focus-visible' ) ? ' (focus-visible)' : '' }` ) );
	const onOpen = await rings();
	check( onOpen.length === 0, `NFR-11 [${ where }] the options sheet opened by a touch shows no focus ring (${ JSON.stringify( onOpen ) })` );
	// Any option not chosen yet (one marked sold out with the others can still be chosen: it moves them).
	const choice = dialog.locator( '[role="radio"]:not([aria-checked="true"])' ).first();
	if ( await choice.count() ) {
		// One marked sold out with the others is pressed as a person presses it (Playwright treats aria-disabled
		// as disabled and would not tap it).
		await choice.tap( { force: true } );
		await page.waitForTimeout( 300 );
		const afterTap = await rings();
		check( afterTap.length === 0, `NFR-11 [${ where }] a tap on an option leaves no focus ring (${ JSON.stringify( afterTap ) })` );
	}
	const m = await dialog.evaluate( ( d ) => {
		const p = d.querySelector( '.mizzey-overlay__panel' ).getBoundingClientRect();
		const at = ( el ) => {
			const r = el.getBoundingClientRect();
			return { x: Math.round( ( r.left - p.left ) * 10 ) / 10, y: Math.round( ( r.top - p.top ) * 10 ) / 10, w: Math.round( r.width * 10 ) / 10, h: Math.round( r.height * 10 ) / 10, r: Math.round( ( p.right - r.right ) * 10 ) / 10, b: Math.round( ( p.bottom - r.bottom ) * 10 ) / 10 };
		};
		const add = d.querySelector( '.mizzey-card-picker__add' );
		const options = [ ...d.querySelectorAll( '.mizzey-card-picker__options' ) ];
		const close = d.querySelector( '.mizzey-overlay__header .mizzey-icon' );
		return {
			height: Math.round( p.height * 10 ) / 10,
			title: at( d.querySelector( '.mizzey-overlay__title' ) ).y,
			name: at( d.querySelector( '.mizzey-card-picker__title' ) ).y,
			price: at( d.querySelector( '.mizzey-card-picker__price' ) ).y,
			rows: options.map( ( o ) => at( o ).y ),
			add: at( add ),
			glow: getComputedStyle( add, '::after' ).boxShadow,
			rule: getComputedStyle( d.querySelector( '.mizzey-overlay__footer' ) ).boxShadow,
			weight: getComputedStyle( d.querySelector( '.mizzey-stepper__value' ) ).fontWeight,
			close: close ? Math.round( close.getBoundingClientRect().width ) : null,
		};
	} );
	const ok = ( a, b, t = 1 ) => typeof a === 'number' && Math.abs( a - b ) <= t;
	if ( where.startsWith( 'ar' ) ) {
		// No Arabic sheet is drawn: the mirror of the English one, its rows as tall as Tajawal's lines make them.
		check( ok( m.add.w, 206 ) && ok( m.add.h, 52 ) && ok( m.add.b, 16 ) && ok( m.add.x, 16 ) && /rgba\(38, 59, 115, 0\.22\)/.test( m.glow ) && m.rule === 'none', `C1-Category-Phone 5 [${ where }] mirrored: "Add to cart" 206 x 52 at the start, 16 from the edge and the bottom, with its glow, no rule (${ JSON.stringify( m ) })` );
		await dialog.locator( '.mizzey-overlay__header button' ).first().tap( { force: true } );
		await page.waitForTimeout( 700 );
		return;
	}
	check( ok( m.height, 384 ), `C1-Category-Phone 5 [${ where }] the options sheet is 384 high (${ m.height })` );
	check( ok( m.title, 25.5 ) && ok( m.name, 68 ) && ok( m.price, 108 ), `C1-Category-Phone 5 [${ where }] title 25.5, name 68, price 108 from the top (${ m.title }, ${ m.name }, ${ m.price })` );
	check( ok( m.rows[ 0 ], 172 ) && ok( m.rows[ 1 ], 256 ), `C1-Category-Phone 5 [${ where }] the option rows at 172 and 256 (${ JSON.stringify( m.rows ) })` );
	check( ok( m.add.w, 206 ) && ok( m.add.h, 52 ) && ok( m.add.y, 316 ) && ok( m.add.b, 16 ) && /rgba\(38, 59, 115, 0\.22\)/.test( m.glow ), `C1-Category-Phone 5 [${ where }] "Add to cart" 206 x 52 at 316, 16 above the bottom, with its glow (${ JSON.stringify( m.add ) } ${ m.glow })` );
	check( m.rule === 'none' && m.weight === '500' && m.close === 20, `C1-Category-Phone 5 [${ where }] no rule above the foot, the number 500, the Close icon 20 (${ m.rule }, ${ m.weight }, ${ m.close })` );
	const file = path.join( plan.shots, `${ plan.tag }-${ where.replace( /\s+/g, '-' ) }-sheet.png` );
	await page.screenshot( { path: file } );
	shots.push( file );
	// Closed by a touch on its Close, as on a phone (a key press here would make Chromium draw the focus ring for the
	// next taps' scripted focus, a keyboard state a phone never has).
	await dialog.locator( '.mizzey-overlay__header button' ).first().tap( { force: true } );
	await page.waitForTimeout( 700 );
}

/**
 * Back after a busy press: "+" pressed, the server held, the visitor leaves and comes Back. A page restored from the
 * back-forward cache (pageshow, persisted) shows no card busy, and the cards and the header read the cart's truth.
 */
async function backForward( browser, name ) {
	const where = `en 390 ${ name }, Back`;
	const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
	await ctx.addInitScript( () => {
		window.__mizzeyShows = [];
		window.addEventListener( 'pageshow', ( e ) => window.__mizzeyShows.push( e.persisted ) );
	} );
	const page = await ctx.newPage();
	try {
		await page.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
		await page.waitForSelector( '.mizzey-products--grid .mizzey-card', { timeout: TIMEOUT } );
		// One already in the cart, so the truth to come back to is "1".
		await page.evaluate( async ( [ u, id ] ) => {
			const n = ( await fetch( u + 'cart', { credentials: 'same-origin' } ) ).headers.get( 'Nonce' );
			await fetch( u + 'cart/add-item', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Nonce: n }, body: JSON.stringify( { id, quantity: 1 } ) } );
		}, [ plan.store, ID.sale ] );
		await page.reload( { waitUntil: 'load', timeout: TIMEOUT } );
		await page.waitForSelector( '.mizzey-products--grid .mizzey-card', { timeout: TIMEOUT } );
		await page.waitForTimeout( 3000 );
		// Away, the cart changed elsewhere (one more), then Back.
		await page.goto( plan.store + 'cart', { waitUntil: 'load', timeout: TIMEOUT } );
		await page.evaluate( async ( [ u, id ] ) => {
			const n = ( await fetch( u + 'cart', { credentials: 'same-origin' } ) ).headers.get( 'Nonce' );
			await fetch( u + 'cart/add-item', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Nonce: n }, body: JSON.stringify( { id, quantity: 1 } ) } );
		}, [ plan.store, ID.sale ] );
		await page.goBack( { waitUntil: 'load', timeout: TIMEOUT } );
		await page.waitForTimeout( 4000 );
		const sel = card( 'grid', ID.sale );
		const read = ( s ) => {
			const c = document.querySelector( s );
			const cart = [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length );
			const badge = cart && cart.querySelector( '.mizzey-count' );
			const bubble = c.querySelector( '.mizzey-card__bubble' );
			return {
				persisted: window.__mizzeyShows.slice( -1 )[ 0 ],
				busy: document.querySelectorAll( '.mizzey-card.is-busy, .mizzey-card.is-loading, .mizzey-card.is-added' ).length,
				loader: !! c.querySelector( '.mizzey-card__loader' ).getClientRects().length,
				bubble: bubble && bubble.getClientRects().length ? bubble.textContent.trim() : '',
				header: badge && ! badge.hidden ? badge.textContent.trim() : '',
			};
		};
		const truth = async () => page.evaluate( async ( u ) => ( await ( await fetch( u + 'cart', { credentials: 'same-origin' } ) ).json() ).items_count, plan.store );
		// Playwright turns the back-forward cache off in the browsers it drives (pageshow comes back with persisted
		// false even with nothing in flight), so the restore is checked with the event the browser sends on one.
		// 2. A busy card when the page is shown again: "+" pressed and held (a page with a request in flight is not
		// kept by these engines, so the restore is the event the browser sends, persisted).
		await page.route( '**/wc/store/v1/cart/add-item**', () => {} );
		await page.locator( `${ sel } .mizzey-card__add` ).click();
		await page.waitForSelector( `${ sel }.is-loading`, { timeout: TIMEOUT } );
		await page.evaluate( () => window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) ) );
		// The cart's answer can take seconds on a loaded machine.
		await page.waitForFunction( ( s ) => {
			const b = document.querySelector( s + ' .mizzey-card__bubble' );
			return b && b.getClientRects().length > 0;
		}, sel, { timeout: 30000 } ).catch( () => null );
		await page.waitForTimeout( 1500 );
		const reset = await page.evaluate( read, sel );
		const total2 = await truth();
		check( reset.busy === 0 && ! reset.loader, `[${ where }] shown again from the cache, no card is busy and no loader shows (${ JSON.stringify( reset ) })` );
		check( reset.bubble === '2' && reset.header === String( total2 ), `[${ where }] and the card and the header show the cart's truth (bubble ${ reset.bubble || 'none' }, header ${ reset.header || 'none' }, cart ${ total2 })` );
		await page.unroute( '**/wc/store/v1/cart/add-item**' );
	} catch ( e ) {
		check( false, `[${ where }] stopped: ${ String( e.message ).split( '\n' )[ 0 ] }` );
	}
	await ctx.close();
}

/**
 * The options sheet's add, as the board plays it: the sheet's "Add to cart" goes busy (the loader from 400 ms, the
 * same width) until the server answers; then the sheet slides down (main's animated close, .is-closing) and only
 * once it is gone does the product fly, from the card's "+", to the header cart. Frames are kept for the report.
 */
async function sheetSequence( page, where, id ) {
	const sel = card( 'grid', id );
	await page.locator( `${ sel } .mizzey-card__add` ).scrollIntoViewIfNeeded();
	await page.locator( `${ sel } .mizzey-card__add` ).click();
	const dialog = page.locator( `${ sel } dialog[open]` );
	await dialog.waitFor( { timeout: TIMEOUT } );
	await page.waitForTimeout( 700 );
	let release;
	const gate = new Promise( ( r ) => ( release = r ) );
	const route = async ( r ) => {
		await gate;
		await r.continue();
	};
	await page.route( '**/wc/store/v1/cart/add-item**', route );
	await page.evaluate( ( s ) => {
		const c = document.querySelector( s );
		const d = c.querySelector( 'dialog' );
		const btn = d.querySelector( '.mizzey-card-picker__add' );
		const panel = d.querySelector( '.mizzey-overlay__panel' );
		const w0 = btn.getBoundingClientRect().width;
		window.__sheet = { frames: [], closedAt: 0, flightAt: 0, w0, top0: panel.getBoundingClientRect().top };
		new MutationObserver( () => {
			if ( ! d.open && ! window.__sheet.closedAt ) {
				window.__sheet.closedAt = performance.now();
			}
		} ).observe( d, { attributes: true, attributeFilter: [ 'open' ] } );
		new MutationObserver( ( list ) => list.forEach( ( m ) => m.addedNodes.forEach( ( n ) => {
			if ( n.classList && n.classList.contains( 'mizzey-card-flight' ) && ! window.__sheet.flightAt ) {
				window.__sheet.flightAt = performance.now();
			}
		} ) ) ).observe( document.body, { childList: true } );
		const tick = () => {
			const busy = btn.querySelector( '.mizzey-card-picker__busy' );
			window.__sheet.frames.push( {
				t: performance.now(),
				open: d.open,
				closing: d.classList.contains( 'is-closing' ),
				top: panel.getBoundingClientRect().top,
				aria: btn.getAttribute( 'aria-busy' ),
				busy: !! busy && busy.getClientRects().length > 0,
				w: btn.getBoundingClientRect().width,
			} );
			if ( window.__sheet.frames.length < 600 ) {
				requestAnimationFrame( tick );
			}
		};
		requestAnimationFrame( tick );
		window.__sheet.pressAt = 0;
		btn.addEventListener( 'click', () => ( window.__sheet.pressAt = performance.now() ), { capture: true, once: true } );
	}, sel );
	await dialog.locator( '.mizzey-card-picker__add' ).click();
	await page.waitForTimeout( 1100 );
	const busyShot = path.join( plan.shots, `${ plan.tag }-${ where.replace( /\s+/g, '-' ) }-sheet-busy.png` );
	await page.screenshot( { path: busyShot } );
	shots.push( busyShot );
	release();
	const closing = [];
	for ( const at of [ 60, 120, 180 ] ) {
		await page.waitForTimeout( 60 );
		const file = path.join( plan.shots, `${ plan.tag }-${ where.replace( /\s+/g, '-' ) }-sheet-close-${ at }.png` );
		await page.screenshot( { path: file } );
		closing.push( file );
	}
	shots.push( ...closing );
	// The server can take seconds on a loaded machine; the sequence waits for it.
	await page.waitForFunction( () => window.__sheet && window.__sheet.flightAt > 0, null, { timeout: 30000 } ).catch( () => null );
	await page.waitForTimeout( 500 );
	await page.unroute( '**/wc/store/v1/cart/add-item**', route );
	const m = await page.evaluate( () => window.__sheet );
	const busyFrames = m.frames.filter( ( f ) => f.busy && f.aria === 'true' );
	const firstBusy = busyFrames.length ? busyFrames[ 0 ].t - m.pressAt : null;
	check( firstBusy !== null && firstBusy >= 360 && firstBusy <= 520, `C1-Motion [${ where }] the sheet's "Add to cart" shows the loader from 400 ms (${ firstBusy === null ? 'never' : Math.round( firstBusy ) } ms)` );
	check( busyFrames.length && busyFrames.every( ( f ) => Math.abs( f.w - m.w0 ) <= 0.5 ), `C1-Motion [${ where }] busy, the button keeps its width (${ Math.round( m.w0 ) })` );
	const closingTops = m.frames.filter( ( f ) => f.closing && f.open ).map( ( f ) => Math.round( f.top ) );
	const between = closingTops.filter( ( t ) => t > m.top0 + 4 && t < 844 - 4 );
	check( between.length >= 2, `C1-Motion [${ where }] the sheet slides down as it closes: ${ between.length } frames between open (${ Math.round( m.top0 ) }) and gone (${ JSON.stringify( closingTops.slice( 0, 8 ) ) })` );
	check( m.closedAt && m.flightAt && m.flightAt >= m.closedAt, `C1-Motion [${ where }] the product flies only after the sheet is gone (closed at ${ Math.round( m.closedAt - m.pressAt ) } ms, flight at ${ Math.round( m.flightAt - m.pressAt ) } ms)` );
}

/**
 * Shop pages load WooCommerce's classic styles, whose `.woocommerce img { height: auto }` and kin outrank a class: with
 * them on the page, every card's photo (aspects 0.29 and 2.27 in the fixture) stays inside its well and clear of the
 * name (staging v0.1.38: a lip balm covered its name on the category page).
 */
async function wooStyles( page, where ) {
	const base = new URL( plan.en ).origin + '/wp-content/plugins/woocommerce/assets/css/';
	await page.evaluate( ( b ) => {
		document.body.classList.add( 'woocommerce', 'woocommerce-page' );
		for ( const f of [ 'woocommerce-layout.css', 'woocommerce-smallscreen.css', 'woocommerce.css', 'woocommerce-blocktheme.css' ] ) {
			const l = document.createElement( 'link' );
			l.rel = 'stylesheet';
			l.href = b + f;
			l.dataset.mizzeyTest = 'woo';
			document.head.appendChild( l );
		}
	}, base );
	await page.waitForFunction( () => [ ...document.querySelectorAll( 'link[data-mizzey-test="woo"]' ) ].every( ( l ) => l.sheet ), null, { timeout: TIMEOUT } ).catch( () => null );
	await page.waitForTimeout( 400 );
	const m = await page.evaluate( () => {
		const bad = [];
		let n = 0;
		document.querySelectorAll( '.mizzey-card:not(.mizzey-card--skeleton)' ).forEach( ( c ) => {
			const img = c.querySelector( 'img.mizzey-card__image' );
			if ( ! img || ! img.getClientRects().length ) {
				return;
			}
			n++;
			const w = c.querySelector( '.mizzey-card__well' ).getBoundingClientRect();
			const i = img.getBoundingClientRect();
			const name = c.querySelector( '.mizzey-card__name' ).getBoundingClientRect();
			const inside = i.left >= w.left - 0.5 && i.right <= w.right + 0.5 && i.top >= w.top - 0.5 && i.bottom <= w.bottom + 0.5;
			const clear = i.bottom <= name.top + 0.5 || i.top >= name.bottom - 0.5;
			if ( ! inside || ! clear || getComputedStyle( img ).objectFit !== 'contain' ) {
				bad.push( { id: c.dataset.productId, aspect: Math.round( img.naturalWidth / img.naturalHeight * 100 ) / 100, img: [ Math.round( i.top ), Math.round( i.bottom ), Math.round( i.width ) ], well: [ Math.round( w.top ), Math.round( w.bottom ) ], nameTop: Math.round( name.top ), fit: getComputedStyle( img ).objectFit } );
			}
		} );
		const aspects = [ ...document.querySelectorAll( 'img.mizzey-card__image' ) ].map( ( img ) => Math.round( img.naturalWidth / img.naturalHeight * 100 ) / 100 );
		document.querySelectorAll( 'link[data-mizzey-test="woo"]' ).forEach( ( l ) => l.remove() );
		document.body.classList.remove( 'woocommerce', 'woocommerce-page' );
		return { n, bad, aspects: [ ...new Set( aspects ) ] };
	} );
	check( m.n > 0 && m.bad.length === 0 && m.aspects.some( ( a ) => a <= 0.3 ) && m.aspects.some( ( a ) => a >= 2.2 ), `PLP-01 [${ where }] with WooCommerce's classic styles on the page, every photo (aspects ${ JSON.stringify( m.aspects ) }) stays contained in its well and clear of the name (${ m.n } cards; ${ JSON.stringify( m.bad.slice( 0, 3 ) ) })` );
}

async function engine( name, type ) {
	const browser = await type.launch( { headless: true, ...( name === 'chromium' ? ( loadPlaywright().launch || {} ) : {} ) } );
	try {
		const runs = [
			[ `en 390`, plan.en, { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } ],
			[ `en 1440`, plan.en, { viewport: { width: 1440, height: 1000 } } ],
			[ `ar 390`, plan.ar, { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } ],
		];
		for ( const [ label, url, options ] of runs ) {
			const where = `${ label } ${ name }`;
			const ctx = await browser.newContext( options );
			await ctx.addInitScript( recorder );
			const page = await ctx.newPage();
			const errors = [];
			page.on( 'pageerror', ( e ) => errors.push( e.message ) );
			let reads = 0;
			page.on( 'request', ( r ) => {
				if ( r.method() === 'GET' && /\/wc\/store\/v1\/cart(\?|$)/.test( r.url() ) ) {
					reads++;
				}
			} );
			await page.goto( url, { waitUntil: 'load', timeout: TIMEOUT } );
			await page.waitForSelector( '.mizzey-products--grid .mizzey-card', { timeout: TIMEOUT } );
			await page.evaluate( () => document.fonts.ready );
			await page.waitForTimeout( 500 );
			check( reads <= 1, `owner 25 Nov [${ where }] the page reads the cart at most once, not once per card (${ reads })` );
			const empty = await page.evaluate( () => [ ...document.querySelectorAll( '.mizzey-card__bubble' ) ].filter( ( b ) => b.getClientRects().length ).length );
			check( empty === 0, `owner 25 Nov [${ where }] with an empty cart no card shows a count (${ empty })` );
			await wooStyles( page, where );

			// The first add, held 700 ms: "Adding" from 400 ms for at least 500, then the sequence.
			// The Arabic page shows the product's Arabic twin.
			const saleId = label.startsWith( 'ar' ) && plan.arIds && plan.arIds.sale ? plan.arIds.sale : ID.sale;
			const sel = card( 'grid', saleId );
			const first = await addAndRecord( page, sel, { hold: 700 } );
			const one = sequence( where, first, first.at, { hold: 700, wasCount: first.at.count } );
			rested( where, one.last, 1 );
			const rail = await page.evaluate( ( s ) => {
				const b = document.querySelector( s + ' .mizzey-card__bubble' );
				return b && b.getClientRects().length ? b.textContent.trim() : '';
			}, card( 'rail', saleId ) );
			check( rail === '1', `owner 25 Nov [${ where }] the same product's other card follows the change (${ rail || 'none' })` );

			// The bubble is the header's count badge, on the disc's top end corner, inside the card.
			const bubble = await page.evaluate( ( s ) => {
				const c = document.querySelector( s );
				const b = c.querySelector( '.mizzey-card__bubble' );
				const cart = [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length );
				const h = cart.querySelector( '.mizzey-count' );
				const pick = ( el ) => {
					const st = getComputedStyle( el );
					return [ st.height, st.minWidth, st.borderTopLeftRadius === '0px' ? '0' : 'round', st.backgroundColor, st.color, st.fontSize, st.lineHeight, st.fontWeight, st.paddingLeft, st.fontFamily.split( ',' )[ 0 ] ].join( ' | ' );
				};
				const br = b.getBoundingClientRect();
				const disc = c.querySelector( '.mizzey-card__disc' ).getBoundingClientRect();
				const cr = c.getBoundingClientRect();
				const rtl = getComputedStyle( c ).direction === 'rtl';
				return {
					same: pick( b ) === pick( h ), card: pick( b ), header: pick( h ),
					onDisc: br.left < disc.right && br.right > disc.left && br.top < disc.bottom && br.bottom > disc.top,
					endCorner: rtl ? br.left <= disc.left + 8 : br.right >= disc.right - 8,
					top: br.top <= disc.top + 4,
					inside: br.left >= cr.left && br.right <= cr.right,
					h: br.height, w: br.width,
				};
			}, sel );
			check( bubble.same, `owner 25 Nov [${ where }] the bubble is styled as the header's count (${ bubble.card } against ${ bubble.header })` );
			check( bubble.onDisc && bubble.endCorner && bubble.top && bubble.inside && near( bubble.h, 20 ), `owner 25 Nov [${ where }] the bubble sits on the disc's top end corner, inside the card, 20 high (${ JSON.stringify( bubble ) })` );
			if ( label === 'en 390' || label === 'ar 390' ) {
				const file = path.join( plan.shots, `${ plan.tag }-${ label.replace( ' ', '-' ) }-${ name }-bubble.png` );
				await page.locator( sel ).screenshot( { path: file } );
				shots.push( file );
			}

			// A second press adds one more; the number changes only after the answer and the hold. The frame sequence.
			const second = await addAndRecord( page, sel, { hold: 0, span: 2400, shotsAt: label === 'en 390' || ( label === 'en 1440' && name === 'chromium' ) ? [ 0, 120, 240, 360, 480, 600, 900, 1600 ] : null, shotTag: `${ label.replace( ' ', '-' ) }-${ name }` } );
			shots.push( ...second.shots );
			if ( ! second.shots.length ) {
				const two = sequence( `${ where }, second press`, second, second.at, { wasCount: second.at.count } );
				rested( `${ where }, second press`, two.last, 2 );
			} else {
				const last = second.frames[ second.frames.length - 1 ];
				const during = second.frames.filter( ( x ) => x.t > second.answer && x.t < second.answer + 1200 );
				check( during.every( ( x ) => x.bubble === '1' || x.bubble === '' ), `owner 25 Nov [${ where }] while "Added" holds, the bubble keeps the number it had (${ [ ...new Set( during.map( ( x ) => x.bubble ) ) ].join( ',' ) })` );
				rested( `${ where }, second press`, last, 2 );
			}

			if ( options.hasTouch && label === 'en 390' ) {
				await sheetSequence( page, where, ID.var );
			}
			if ( options.hasTouch ) {
				await sheetChecks( page, where, ID.pair );
				await tapChecks( page, where, card( 'grid', ID.two ) );
				await toastChecks( page, where, card( 'grid', ID.bare ) );
			}

			// The page read again: the quantities come from the cart, a variable product counts its variations
			// together, a sold-out card shows none.
			if ( label === 'en 390' ) {
				await page.evaluate( async ( [ store, ids ] ) => {
					const nonce = ( await fetch( store + 'cart', { credentials: 'same-origin' } ) ).headers.get( 'Nonce' );
					for ( const [ id, quantity ] of ids ) {
						await fetch( store + 'cart/add-item', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify( { id, quantity } ) } );
					}
				}, [ plan.store, [ [ plan.variations.Vanilla, 2 ], [ plan.variations.Unflavoured, 1 ] ] ] );
				reads = 0;
				// The cart's answer, then the cards.
				const read = page.waitForResponse( ( r ) => r.request().method() === 'GET' && /\/wc\/store\/v1\/cart(\?|$)/.test( r.url() ), { timeout: TIMEOUT } ).catch( () => null );
				await page.reload( { waitUntil: 'load', timeout: TIMEOUT } );
				await read;
				await page.waitForTimeout( 300 );
				const shown = await page.evaluate( ( [ g, ids ] ) => Object.fromEntries( Object.entries( ids ).map( ( [ k, id ] ) => {
					const b = document.querySelector( `.mizzey-products--${ g } .mizzey-card[data-product-id="${ id }"] .mizzey-card__bubble` );
					return [ k, b && b.getClientRects().length ? b.textContent.trim() : '' ];
				} ) ), [ 'grid', { sale: ID.sale, var: ID.var, sold: ID.sold, long: ID.long } ] );
				// The flavours: one from the options sheet above, then two Vanilla and one Unflavoured: 4 together.
				check( shown.sale === '2' && shown.var === '4' && shown.sold === '' && shown.long === '', `owner 25 Nov [${ where }] on load the cards read the cart: the sale product 2, the flavours together 4, none on the sold-out card (${ JSON.stringify( shown ) })` );
				check( reads === 1, `owner 25 Nov [${ where }] one read of the cart for the whole page (${ reads })` );
			}
			check( errors.length === 0, `[${ where }] no script error (${ JSON.stringify( errors.slice( 0, 3 ) ) })` );
			await ctx.close();
		}

		await backForward( browser, name );

		// Reduced motion, on a phone: no flight, the count changes, the tick shows; the toast fades, it does not follow.
		const calm = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' } );
		await calm.addInitScript( recorder );
		const still = await calm.newPage();
		await still.goto( plan.en, { waitUntil: 'load', timeout: TIMEOUT } );
		await still.waitForSelector( '.mizzey-products--grid .mizzey-card', { timeout: TIMEOUT } );
		const sel = card( 'grid', ID.sale );
		const rec = await addAndRecord( still, sel, { hold: 0 } );
		const r = sequence( `en 390 ${ name } reduced`, rec, rec.at, { reduce: true, wasCount: rec.at.count } );
		rested( `en 390 ${ name } reduced`, r.last, 1 );
		await toastChecks( still, `en 390 ${ name } reduced`, card( 'grid', ID.bare ), { reduce: true } );
		await calm.close();
	} catch ( e ) {
		check( false, `the ${ name } run stopped: ${ String( e.message ).split( '\n' )[ 0 ] }` );
	} finally {
		await browser.close();
	}
}

( async () => {
	const playwright = loadPlaywright();
	if ( ! playwright ) {
		console.log( JSON.stringify( { error: 'Playwright was not found.' } ) );
		return;
	}
	await engine( 'chromium', playwright.pw.chromium );
	await engine( 'webkit', playwright.pw.webkit );
	console.log( JSON.stringify( { checks, shots } ) );
} )();

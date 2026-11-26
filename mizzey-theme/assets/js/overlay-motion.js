/**
 * The overlays' heavier motion (spec 066; C1-Motion-Playground, "Overlays" and "Phone menu, sub-level push"), loaded
 * only when it can be needed: ui.js and header.js import it when an overlay opens, on a press on a dialog opener, or
 * on the first touch on an open overlay (D-21: it is not in a page's script budget, as it is not loaded with it).
 *
 * - Drag to close by touch, drawers and sheets: 1:1 toward the edge (a sheet resists 4:1 up), the scrim fading;
 *   past 35% (drawer) or 30% (sheet), or a flick over 0.5 px/ms, it leaves in 240 ms and closes through ui.js's
 *   animated close; else springs back, 320 soft. Sheets: handle, header, or the body at its top. Reduced motion:
 *   no follow, a release past the threshold still closes.
 * - The phone menu's level push: old level 30% to the start and fading, new one from the end, 320 soft; Back
 *   reverses; reduced motion: a 120 cross-fade. The leaving level is inert and aria-hidden at once.
 */

const isRtl = ( el ) => getComputedStyle( el ).direction === 'rtl';
const token = ( name ) => getComputedStyle( document.documentElement ).getPropertyValue( name ).trim();
const tokenMs = ( name ) => parseFloat( token( name ) ) || 0;
const reducedMotion = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

/* ---- Drag to close -------------------------------------------------------------------------------------------- */

const DRAG_SLOP = 6;
const FLICK = 0.5;
const held = new WeakMap();
let closeWith = ( dialog ) => dialog.close();
let drag = null;
let swallowClick = false;

/** ui.js hands over its animated close. */
export function init( close ) {
	closeWith = close;
}

function dragKind( dialog ) {
	if ( dialog.classList.contains( 'mizzey-overlay--sheet' ) ) {
		return { axis: 'y', sign: 1, share: 0.3 };
	}
	if ( dialog.classList.contains( 'mizzey-overlay--drawer-start' ) || dialog.classList.contains( 'mizzey-overlay--drawer-end' ) ) {
		const toStart = dialog.classList.contains( 'mizzey-overlay--drawer-start' );
		return { axis: 'x', sign: toStart === isRtl( dialog ) ? 1 : -1, share: 0.35 };
	}
	return null;
}

/** Opened again: the panel comes in whole, not from where a finger left it. */
export function release( dialog ) {
	const kept = held.get( dialog );
	if ( kept ) {
		kept.forEach( ( a ) => a.cancel() );
		held.delete( dialog );
	}
}

function scrim( dialog, from, to, options ) {
	try {
		return dialog.animate( [ { opacity: from }, { opacity: to } ], { ...options, pseudoElement: '::backdrop' } );
	} catch ( e ) {
		return null;
	}
}

/** A touch went down on an open overlay's panel (ui.js passes it on, after loading this module if need be). */
export function start( event ) {
	const panel = event.target.closest( '.mizzey-overlay__panel' );
	const dialog = panel && panel.closest( 'dialog.mizzey-overlay' );
	const kind = dialog && dialog.open && ! dialog.classList.contains( 'is-closing' ) ? dragKind( dialog ) : null;
	if ( ! kind ) {
		return;
	}
	const grip = 'y' === kind.axis && !! event.target.closest( '.mizzey-overlay__handle, .mizzey-overlay__header' );
	if ( 'y' === kind.axis && ! grip ) {
		const body = event.target.closest( '.mizzey-overlay__body' );
		if ( ! body || body.scrollTop > 0 ) {
			return;
		}
	}
	release( dialog );
	drag = { dialog, panel, kind, grip, id: event.pointerId, x0: event.clientX, y0: event.clientY, offset: 0, live: false, anim: null, veil: null, samples: [ [ performance.now(), 0 ] ] };
}

document.addEventListener( 'pointermove', ( event ) => {
	if ( ! drag || event.pointerId !== drag.id ) {
		return;
	}
	const { kind, panel, dialog } = drag;
	const along = 'x' === kind.axis ? event.clientX - drag.x0 : event.clientY - drag.y0;
	const across = 'x' === kind.axis ? event.clientY - drag.y0 : event.clientX - drag.x0;
	if ( ! drag.live ) {
		if ( Math.abs( along ) < DRAG_SLOP && Math.abs( across ) < DRAG_SLOP ) {
			return;
		}
		// Mostly across a drawer, or up in a sheet's body: a scroll.
		if ( Math.abs( across ) >= Math.abs( along ) || ( 'y' === kind.axis && along < 0 && ! drag.grip ) ) {
			drag = null;
			return;
		}
		drag.live = true;
		if ( ! reducedMotion() ) {
			drag.anim = panel.animate( [ {}, {} ], { duration: 1, fill: 'forwards' } );
			drag.anim.pause();
			drag.veil = scrim( dialog, 1, 1, { duration: 1, fill: 'forwards' } );
			if ( drag.veil ) {
				drag.veil.pause();
			}
		}
	}
	drag.offset = along * kind.sign >= 0 ? along : ( 'y' === kind.axis ? along / 4 : 0 );
	const size = ( 'x' === kind.axis ? panel.offsetWidth : panel.offsetHeight ) || 1;
	if ( drag.anim ) {
		const t = 'x' === kind.axis ? `translateX(${ drag.offset }px)` : `translateY(${ drag.offset }px)`;
		drag.anim.effect.setKeyframes( [ { transform: t }, { transform: t } ] );
	}
	if ( drag.veil ) {
		const o = Math.max( 0, 1 - Math.max( 0, drag.offset * kind.sign ) / size );
		drag.veil.effect.setKeyframes( [ { opacity: o }, { opacity: o } ] );
	}
	const now = performance.now();
	drag.samples.push( [ now, drag.offset ] );
	drag.samples = drag.samples.filter( ( [ t ] ) => now - t <= 100 );
}, true );

// A live drag is ours, not a scroll (the browser would cancel the stream).
document.addEventListener( 'touchmove', ( event ) => {
	if ( drag && drag.live && event.cancelable ) {
		event.preventDefault();
	}
}, { capture: true, passive: false } );

function endDrag( event, cancelled ) {
	if ( ! drag || event.pointerId !== drag.id ) {
		return;
	}
	const { dialog, panel, kind, live, offset, anim, veil, samples } = drag;
	drag = null;
	if ( ! live ) {
		return;
	}
	swallowClick = true;
	window.setTimeout( () => {
		swallowClick = false;
	}, 0 );
	const size = ( 'x' === kind.axis ? panel.offsetWidth : panel.offsetHeight ) || 1;
	const [ t0, x0 ] = samples[ 0 ] || [ performance.now(), offset ];
	const elapsed = performance.now() - t0;
	const speed = elapsed > 0 ? ( ( offset - x0 ) * kind.sign ) / elapsed : 0;
	const travelled = offset * kind.sign;
	const shut = ! cancelled && travelled > 0 && ( travelled / size > kind.share || speed > FLICK );
	const at = ( v ) => ( 'x' === kind.axis ? `translateX(${ v }px)` : `translateY(${ v }px)` );
	const opacity = Math.max( 0, 1 - Math.max( 0, travelled ) / size );
	if ( anim ) {
		anim.cancel();
	}
	if ( veil ) {
		veil.cancel();
	}
	if ( shut ) {
		const kept = [];
		if ( ! reducedMotion() ) {
			const out = { duration: tokenMs( '--mizzey-motion-duration-5' ), easing: token( '--mizzey-motion-easing-in' ) || 'ease-in', fill: 'forwards' };
			kept.push( panel.animate( [ { transform: at( offset ) }, { transform: at( kind.sign * size ) } ], out ) );
			const fade = scrim( dialog, opacity, 0, out );
			if ( fade ) {
				kept.push( fade );
			}
		}
		held.set( dialog, kept );
		closeWith( dialog );
		return;
	}
	if ( ! anim ) {
		return;
	}
	const back = { duration: tokenMs( '--mizzey-motion-duration-6' ), easing: token( '--mizzey-motion-easing-spring-soft' ) || 'ease-out' };
	[ panel.animate( [ { transform: at( offset ) }, { transform: at( 0 ) } ], back ), scrim( dialog, opacity, 1, back ) ]
		.filter( Boolean )
		.forEach( ( a ) => a.finished.then( () => a.cancel() ).catch( () => {} ) );
}

document.addEventListener( 'pointerup', ( event ) => endDrag( event, false ), true );
document.addEventListener( 'pointercancel', ( event ) => endDrag( event, true ), true );
// A drag that ends over a control is not a tap on it.
document.addEventListener( 'click', ( event ) => {
	if ( swallowClick ) {
		event.preventDefault();
		event.stopPropagation();
		swallowClick = false;
	}
}, true );

/* ---- The phone menu's level push ------------------------------------------------------------------------------ */

// A new push first ends the one under way, so a tap mid-motion acts at once.
let pushing = null;

export function settlePush() {
	if ( ! pushing ) {
		return;
	}
	const { animations, done } = pushing;
	pushing = null;
	animations.forEach( ( a ) => a.cancel() );
	done();
}

/** From one level to another; showLevel( level, on ) is header.js's. */
export function pushLevel( from, to, forward, showLevel ) {
	settlePush();
	const dir = isRtl( from ) ? -1 : 1;
	from.style.setProperty( '--mizzey-menu-level-size', `${ from.offsetHeight }px` );
	from.inert = true;
	from.setAttribute( 'aria-hidden', 'true' );
	from.classList.add( 'is-leaving' );
	showLevel( to, true );
	( forward ? to : from ).classList.add( 'is-over' );

	const done = () => {
		showLevel( from, false );
		from.classList.remove( 'is-leaving', 'is-over' );
		to.classList.remove( 'is-over' );
		from.style.removeProperty( '--mizzey-menu-level-size' );
	};

	let animations;
	if ( reducedMotion() ) {
		const fade = { duration: tokenMs( '--mizzey-motion-duration-2' ), easing: token( '--mizzey-motion-easing-out' ) || 'ease-out' };
		animations = [ from.animate( [ { opacity: 1 }, { opacity: 0 } ], fade ), to.animate( [ { opacity: 0 }, { opacity: 1 } ], fade ) ];
	} else {
		const timing = { duration: tokenMs( '--mizzey-motion-duration-6' ), easing: token( '--mizzey-motion-easing-spring-soft' ) || 'ease-out' };
		const aside = { transform: `translateX(${ -30 * dir }%)`, opacity: 0 };
		const home = { transform: 'translateX(0)', opacity: 1 };
		const out = { transform: `translateX(${ 100 * dir }%)`, opacity: 1 };
		animations = forward
			? [ from.animate( [ home, aside ], timing ), to.animate( [ out, home ], timing ) ]
			: [ from.animate( [ home, out ], timing ), to.animate( [ aside, home ], timing ) ];
	}
	const mine = { animations, done };
	pushing = mine;
	Promise.all( animations.map( ( a ) => a.finished ) )
		.then( () => {
			if ( pushing === mine ) {
				pushing = null;
				animations.forEach( ( a ) => a.cancel() );
				done();
			}
		} )
		.catch( () => {} );
}

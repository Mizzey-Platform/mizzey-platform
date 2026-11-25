/**
 * The announcement strip's slider and the cookie preferences (spec 066, slice 3), on the WordPress Interactivity API.
 *
 * The slider (inc/strip.php) shows each message for --mizzey-motion-life-strip, sliding the next up into place. It
 * holds while the pointer is over it, while the keyboard focus is in it and after a touch, until the touch is elsewhere;
 * its pause control stops and starts it. Under reduced motion it starts paused. A message that is not showing is inert.
 *
 * The preferences (inc/consent.php) open from any link to #cookie-preferences. They record nothing themselves: each
 * choice presses the consent banner's own button (specs/043, consent.js), so the cookie, the consent mode update and
 * the data layer event stay the banner's. "Save choices" is "granted" only with Analytics and Marketing both on.
 * While the banner shows, the page keeps room at its foot as tall as the banner (--mizzey-consent-space).
 */
import { store, getElement, withSyncEvent } from '@wordpress/interactivity';

const ANCHOR = 'a[href="#cookie-preferences"]';
const DIALOG = 'mizzey-consent-preferences';
const CONSENT = /(?:^|; )mizzey_consent=(granted|denied)/;
const strips = new WeakMap();

const reducedMotion = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

/** A duration token in milliseconds. */
function duration( el, name ) {
	const raw = getComputedStyle( el ).getPropertyValue( name ).trim();
	const value = parseFloat( raw );
	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return 0;
	}
	return raw.endsWith( 'ms' ) ? value : value * 1000;
}

/* ---- The strip's slider ------------------------------------------------------------------------------------- */

function show( state, next ) {
	state.messages.forEach( ( message, i ) => {
		const current = i === next;
		message.classList.toggle( 'is-current', current );
		message.classList.toggle( 'is-leaving', i === state.index && ! current );
		message.inert = ! current;
		if ( current ) {
			message.removeAttribute( 'aria-hidden' );
		} else {
			message.setAttribute( 'aria-hidden', 'true' );
		}
	} );
	state.index = next;
}

function schedule( state ) {
	window.clearTimeout( state.timer );
	state.timer = 0;
	if ( state.paused || state.holds.size || ! state.life ) {
		return;
	}
	state.timer = window.setTimeout( () => {
		show( state, ( state.index + 1 ) % state.messages.length );
		schedule( state );
	}, state.life );
}

function setPaused( state, paused ) {
	state.paused = paused;
	state.root.classList.toggle( 'is-paused', paused );
	const button = state.root.querySelector( '.mizzey-announcement__pause' );
	if ( button ) {
		button.setAttribute( 'aria-label', paused ? button.dataset.labelPlay : button.dataset.labelPause );
	}
	schedule( state );
}

function hold( state, reason, on ) {
	if ( on ) {
		state.holds.add( reason );
	} else {
		state.holds.delete( reason );
	}
	schedule( state );
}

/* ---- The cookie preferences --------------------------------------------------------------------------------- */

function openPreferences() {
	const dialog = document.getElementById( DIALOG );
	if ( ! dialog || dialog.open ) {
		return;
	}
	const granted = ( document.cookie.match( CONSENT ) || [] )[ 1 ] === 'granted';
	dialog.querySelectorAll( '[data-mizzey-consent-category]:not(:disabled)' ).forEach( ( control ) => {
		control.setAttribute( 'aria-checked', granted ? 'true' : 'false' );
	} );
	dialog.showModal();
}

function keepRoom( banner ) {
	const measure = () => {
		const room = banner.hidden ? 0 : Math.ceil( window.innerHeight - banner.getBoundingClientRect().top );
		document.body.style.setProperty( '--mizzey-consent-space', `${ Math.max( 0, room ) }px` );
	};
	new window.ResizeObserver( measure ).observe( banner );
	new window.MutationObserver( measure ).observe( banner, { attributes: true, attributeFilter: [ 'hidden' ] } );
	window.addEventListener( 'resize', measure, { passive: true } );
	measure();
}

store( 'mizzey/site', {
	actions: {
		stripToggle: withSyncEvent( ( event ) => {
			const state = strips.get( event.currentTarget.closest( '.mizzey-announcement' ) );
			if ( state ) {
				setPaused( state, ! state.paused );
			}
		} ),
		consentChoice: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const dialog = ref.closest( 'dialog' );
			const banner = document.getElementById( 'mizzey-consent' );
			let value = ref.dataset.mizzeyConsentChoice;
			if ( value === 'save' ) {
				const on = ( name ) => dialog.querySelector( `[data-mizzey-consent-category="${ name }"]` ).getAttribute( 'aria-checked' ) === 'true';
				value = on( 'analytics' ) && on( 'marketing' ) ? 'granted' : 'denied';
			}
			event.preventDefault();
			const press = banner && banner.querySelector( `[data-mizzey-consent="${ value }"]` );
			if ( press ) {
				press.click();
			}
			if ( dialog && dialog.open ) {
				dialog.close();
			}
		} ),
	},
	callbacks: {
		stripInit() {
			const { ref } = getElement();
			const messages = [ ...ref.querySelectorAll( '.mizzey-announcement__message' ) ];
			if ( messages.length < 2 || strips.has( ref ) ) {
				return;
			}
			const state = { root: ref, messages, index: 0, paused: false, holds: new Set(), timer: 0, life: duration( ref, '--mizzey-motion-life-strip' ) };
			strips.set( ref, state );
			ref.addEventListener( 'mouseenter', () => hold( state, 'pointer', true ) );
			ref.addEventListener( 'mouseleave', () => hold( state, 'pointer', false ) );
			ref.addEventListener( 'focusin', () => hold( state, 'focus', true ) );
			ref.addEventListener( 'focusout', ( event ) => {
				if ( ! ref.contains( event.relatedTarget ) ) {
					hold( state, 'focus', false );
				}
			} );
			document.addEventListener( 'pointerdown', ( event ) => {
				if ( event.pointerType === 'touch' ) {
					hold( state, 'touch', ref.contains( event.target ) );
				}
			} );
			setPaused( state, reducedMotion() );
		},
		consentInit() {
			document.addEventListener( 'click', ( event ) => {
				const link = event.target && event.target.closest ? event.target.closest( ANCHOR ) : null;
				if ( link ) {
					event.preventDefault();
					openPreferences();
				}
			} );
			const banner = document.getElementById( 'mizzey-consent' );
			if ( banner ) {
				keepRoom( banner );
			}
		},
	},
} );

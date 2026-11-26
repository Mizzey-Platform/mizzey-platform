/**
 * Behaviour of the base components (spec 066, slice 1), on the WordPress Interactivity API and nothing else.
 *
 * Overlays are native modal dialogs: showModal() moves focus in, makes the page behind inert, gives the top layer
 * and the scrim (::backdrop), closes on Escape and returns focus to the opener, so this store adds only what the
 * platform leaves open: a press on the scrim closes (when it also started there). The page behind does not scroll
 * while one is open (components.css). Tabs follow the keyboard pattern of the ARIA practices (arrows mirror in
 * Arabic, Home and End), a tooltip is dismissed with Escape, the switch toggles, and the select is our own list.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const pressedOnScrim = new WeakMap();

/*
 * An overlay closes by playing its way out (components.css, .is-closing), then closing the dialog, which gives the
 * focus back and lets the page scroll again. Keyframes, not transitions: Safari before 17.5 has no @starting-style
 * and no transition of the top layer, and showed and hid the drawers at once. The close waits for the panel's
 * animation to end, with a fallback in case it never reports, and is immediate when there is no animation to play.
 * Escape takes the same way: the dialog's cancel event is stopped and the overlay closed here.
 */
const ms = ( value ) => Math.max( 0, ...String( value ).split( ',' ).map( ( v ) => ( v.trim().endsWith( 'ms' ) ? parseFloat( v ) : parseFloat( v ) * 1000 ) || 0 ) );

function closeOverlay( dialog ) {
	if ( ! dialog || ! dialog.open || dialog.classList.contains( 'is-closing' ) ) {
		return;
	}
	const panel = dialog.querySelector( '.mizzey-overlay__panel' );
	if ( ! dialog.classList.contains( 'mizzey-overlay' ) || ! panel ) {
		dialog.close();
		return;
	}
	dialog.classList.add( 'is-closing' );
	const style = getComputedStyle( panel );
	const wait = style.animationName === 'none' ? 0 : ms( style.animationDuration ) + ms( style.animationDelay );
	let done = false;
	let timer = 0;
	const finish = () => {
		if ( done ) {
			return;
		}
		done = true;
		panel.removeEventListener( 'animationend', onEnd );
		window.clearTimeout( timer );
		dialog.close();
		dialog.classList.remove( 'is-closing' );
	};
	const onEnd = ( event ) => event.target === panel && finish();
	if ( wait <= 0 || document.hidden ) {
		finish();
		return;
	}
	panel.addEventListener( 'animationend', onEnd );
	timer = window.setTimeout( finish, wait + 100 );
}

document.addEventListener(
	'cancel',
	( event ) => {
		const dialog = event.target;
		if ( dialog instanceof HTMLDialogElement && dialog.classList.contains( 'mizzey-overlay' ) && event.cancelable ) {
			event.preventDefault();
			closeOverlay( dialog );
		}
	},
	true
);

const isRtl = ( el ) => getComputedStyle( el ).direction === 'rtl';

/* Rings for the keyboard only (K-5): an overlay opened by a press focuses itself, closed by one it refocuses its
   opener, both without a ring; watched on `open`, so overlays other scripts open follow too. Drag to close and the
   level push are overlay-motion.js, loaded on a press on an opener, an overlay opening or a touch on one (D-21). */
let byPointer = false;
let pressed = null;
let motion = null;
let loading = null;
function loadMotion() {
	loading =
		loading ||
		import( 'mizzey-theme/overlay-motion' )
			// No import map entry (the component preview): beside this file.
			.catch( () => import( new URL( 'overlay-motion.js', import.meta.url ).href ) )
			.then( ( m ) => {
				m.init( closeOverlay );
				return ( motion = m );
			} );
	return loading;
}
document.addEventListener( 'pointerdown', ( event ) => {
	const t = event.target instanceof Element ? event.target : null;
	byPointer = true;
	pressed = t && t.closest( 'a[href], button, [tabindex], input, select, textarea, summary' );
	if ( t && t.closest( '[aria-haspopup="dialog"]' ) ) {
		loadMotion();
	}
	if ( t && 'touch' === event.pointerType && event.isPrimary && t.closest( 'dialog.mizzey-overlay[open] .mizzey-overlay__panel' ) ) {
		( motion ? Promise.resolve( motion ) : loadMotion() ).then( ( m ) => m.start( event ) );
	}
}, true );
document.addEventListener( 'keydown', () => {
	byPointer = false;
}, true );

const openers = new WeakMap();

// The mousedown sent after a touch (which already acted) does not move focus in an overlay.
let touchUp = -1e4;
document.addEventListener( 'pointerup', ( event ) => {
	if ( 'touch' === event.pointerType ) {
		touchUp = event.timeStamp;
	}
}, true );
document.addEventListener( 'mousedown', ( event ) => {
	const t = event.target;
	if ( event.timeStamp - touchUp < 1000 && t instanceof Element && t.closest( 'dialog.mizzey-overlay[open]' ) && ! t.closest( 'input, textarea, select, [contenteditable]' ) ) {
		event.preventDefault();
	}
}, true );

const quietFocus = ( el ) => el.focus( { preventScroll: true, focusVisible: false } );

new MutationObserver( ( records ) => {
	for ( const { target: dialog } of records ) {
		if ( ! ( dialog instanceof HTMLDialogElement ) || ! dialog.classList.contains( 'mizzey-overlay' ) ) {
			continue;
		}
		if ( dialog.open ) {
			loadMotion().then( ( m ) => m.release( dialog ) );
			const opener = pressed && ! dialog.contains( pressed ) ? pressed : null;
			if ( opener ) {
				openers.set( dialog, opener );
			}
			if ( byPointer ) {
				if ( ! dialog.hasAttribute( 'tabindex' ) ) {
					dialog.tabIndex = -1;
				}
				quietFocus( dialog );
			}
		} else if ( byPointer ) {
			// Only where the platform's return misses the opener (WebKit) or rings.
			const opener = openers.get( dialog );
			const active = document.activeElement;
			if ( opener && opener.isConnected && ( active !== opener || opener.matches( ':focus-visible' ) ) ) {
				if ( active === opener ) {
					opener.blur();
				}
				quietFocus( opener );
			}
		}
	}
} ).observe( document.documentElement, { subtree: true, attributes: true, attributeFilter: [ 'open' ] } );

function activateTab( tab ) {
	const list = tab.closest( '[role="tablist"]' );
	const tabs = [ ...list.querySelectorAll( '[role="tab"]' ) ];
	tabs.forEach( ( item ) => {
		const selected = item === tab;
		item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
		item.tabIndex = selected ? 0 : -1;
		const panel = document.getElementById( item.getAttribute( 'aria-controls' ) );
		if ( panel ) {
			panel.hidden = ! selected;
		}
	} );
	list.style.setProperty( '--mizzey-tab-index', String( tabs.indexOf( tab ) ) );
}

function selectParts( el ) {
	const root = el.closest( '.mizzey-select' );
	return {
		root,
		button: root.querySelector( '.mizzey-select__button' ),
		list: root.querySelector( '[role="listbox"]' ),
		options: [ ...root.querySelectorAll( '[role="option"]' ) ],
		input: root.querySelector( 'input[type="hidden"]' ),
		value: root.querySelector( '.mizzey-select__value' ),
	};
}

function setActive( parts, index ) {
	const count = parts.options.length;
	const next = ( ( index % count ) + count ) % count;
	parts.options.forEach( ( option, i ) => option.classList.toggle( 'is-active', i === next ) );
	parts.button.setAttribute( 'aria-activedescendant', parts.options[ next ].id );
	parts.options[ next ].scrollIntoView( { block: 'nearest' } );
	return next;
}

function openList( parts, open ) {
	parts.list.hidden = ! open;
	parts.button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	if ( open ) {
		const selected = parts.options.findIndex( ( o ) => o.getAttribute( 'aria-selected' ) === 'true' );
		setActive( parts, Math.max( 0, selected ) );
	} else {
		parts.button.removeAttribute( 'aria-activedescendant' );
	}
}

function choose( parts, option ) {
	parts.options.forEach( ( o ) => o.setAttribute( 'aria-selected', o === option ? 'true' : 'false' ) );
	parts.value.textContent = option.dataset.label || option.textContent.trim();
	if ( parts.input ) {
		parts.input.value = option.dataset.value || '';
		parts.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
	openList( parts, false );
	parts.button.focus();
}

store( 'mizzey/ui', {
	actions: {
		/* Overlays. showModal() does the rest: it moves focus in, makes the page behind inert and gives focus back to
		   the control that opened it; closeOverlay() plays the way out first, on Escape too. */
		open: withSyncEvent( ( event ) => {
			const { target } = getContext();
			const dialog = document.getElementById( target );
			if ( ! dialog || dialog.open ) {
				return;
			}
			event.preventDefault();
			dialog.showModal();
		} ),
		/* For the other stores (the product card's picker, the cookie preferences):
		   store( 'mizzey/ui' ).actions.closeOverlay( dialog ). */
		closeOverlay( dialog ) {
			closeOverlay( dialog );
		},
		close() {
			const { ref } = getElement();
			const dialog = ref.closest( 'dialog' );
			closeOverlay( dialog );
		},
		/* A press on the scrim closes, only when it starts there too: a text selection dragged out of a field onto
		   the scrim ends with a click on the dialog, and must not close it. */
		scrimDown: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			pressedOnScrim.set( ref, event.target === ref );
		} ),
		backdrop: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const started = pressedOnScrim.get( ref );
			pressedOnScrim.delete( ref );
			if ( event.target === ref && started ) {
				closeOverlay( ref );
			}
		} ),

		/* Notices: the close control removes its notice. */
		dismiss() {
			const notice = getElement().ref.closest( '.mizzey-notice, .mizzey-sitewide' );
			if ( notice ) {
				notice.remove();
			}
		},

		/* Tabs. */
		selectTab: withSyncEvent( ( event ) => {
			activateTab( event.currentTarget );
		} ),
		tabKey: withSyncEvent( ( event ) => {
			const tab = event.currentTarget;
			const tabs = [ ...tab.closest( '[role="tablist"]' ).querySelectorAll( '[role="tab"]' ) ];
			const index = tabs.indexOf( tab );
			const forward = isRtl( tab ) ? 'ArrowLeft' : 'ArrowRight';
			const back = isRtl( tab ) ? 'ArrowRight' : 'ArrowLeft';
			const moves = {
				[ forward ]: index + 1,
				[ back ]: index - 1,
				Home: 0,
				End: tabs.length - 1,
			};
			if ( ! ( event.key in moves ) ) {
				return;
			}
			event.preventDefault();
			const next = tabs[ ( moves[ event.key ] + tabs.length ) % tabs.length ];
			activateTab( next );
			next.focus();
		} ),

		/* Tooltips: Escape hides the tooltip without moving focus; leaving the control resets it. */
		tipKey: withSyncEvent( ( event ) => {
			if ( event.key === 'Escape' ) {
				getElement().ref.classList.add( 'is-dismissed' );
			}
		} ),
		tipReset() {
			getElement().ref.classList.remove( 'is-dismissed' );
		},

		/* Switch. */
		toggle() {
			const { ref } = getElement();
			ref.setAttribute( 'aria-checked', ref.getAttribute( 'aria-checked' ) === 'true' ? 'false' : 'true' );
		},

		/* Select: our own list, the select-only combobox pattern. */
		selectToggle() {
			const parts = selectParts( getElement().ref );
			openList( parts, parts.list.hidden );
		},
		selectKey: withSyncEvent( ( event ) => {
			const parts = selectParts( event.currentTarget );
			const open = ! parts.list.hidden;
			const active = parts.options.findIndex( ( o ) => o.classList.contains( 'is-active' ) );
			switch ( event.key ) {
				case 'ArrowDown':
				case 'ArrowUp':
					event.preventDefault();
					if ( ! open ) {
						openList( parts, true );
					} else {
						setActive( parts, active + ( event.key === 'ArrowDown' ? 1 : -1 ) );
					}
					break;
				case 'Home':
				case 'End':
					if ( open ) {
						event.preventDefault();
						setActive( parts, event.key === 'Home' ? 0 : parts.options.length - 1 );
					}
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					if ( open && active >= 0 ) {
						choose( parts, parts.options[ active ] );
					} else {
						openList( parts, true );
					}
					break;
				case 'Escape':
					if ( open ) {
						event.preventDefault();
						event.stopPropagation();
						openList( parts, false );
					}
					break;
				case 'Tab':
					openList( parts, false );
					break;
			}
		} ),
		selectChoose: withSyncEvent( ( event ) => {
			const option = event.target.closest( '[role="option"]' );
			if ( option ) {
				choose( selectParts( option ), option );
			}
		} ),
		selectOutside: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				const parts = selectParts( ref.querySelector( '.mizzey-select__button' ) );
				if ( ! parts.list.hidden ) {
					openList( parts, false );
				}
			}
		} ),
	},
	callbacks: {
		/* The last region on the page (inc/performance.php prints it at the end of the body) has hydrated, so every
		   control before it has too: taps held by early-taps.js are replayed now. */
		ready() {
			document.documentElement.setAttribute( 'data-mizzey-ready', '' );
			document.dispatchEvent( new Event( 'mizzey:ready' ) );
		},
	},
} );

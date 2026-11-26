/**
 * The header's behaviour (spec 066, slice 2), on the WordPress Interactivity API, store "mizzey/header".
 *
 * - The mega menu: "All categories" opens it and dims the page; focus lands on the first row and stays inside; the
 *   arrow keys move between rows (the panel follows) and into the panel; Escape or a press outside closes it, and
 *   focus returns to the control.
 * - The phone menu's levels: a category with children opens its own level, Back returns; closing the drawer starts
 *   it again at the first level. Its modal focus and the return of focus are the base drawer's (store "mizzey/ui").
 * - The phone menu's motion: the level push (overlay-motion.js), the entrance class, the control's expanded state.
 * - The Categories tab shows the categories between the header and the tab bar; Escape or the tab again closes it.
 * - The phone's search control (product, cart and checkout pages) opens the search row and focuses the field.
 * - The category row: what does not fit, and anything past the eighth link, moves under "More".
 * - The cart's count follows an add or a removal without a reload: on WooCommerce's own events (the classic
 *   "added_to_cart" and "removed_from_cart", the blocks' "wc-blocks_added_to_cart" and "wc-blocks_removed_from_cart")
 *   the header asks GET mizzey/v1/header and shows its answer; "mizzey-cart-changed" (the product card, the mini
 *   cart) already carries the server's count, which is shown when the product's flight lands (detail.landing ms
 *   later), with the bump and the roll of C1-Motion-Playground: the badge swells on the bouncy spring while the old
 *   number rolls out and the new one in, 12 px in the direction of the change. Under reduced motion it just changes.
 *   The count changes only when the server has answered (motion never claims success early); a header read that
 *   arrives while a flight is in the air waits for it.
 * - The cart control opens the mini cart (slice 12), whose script is imported on its first press.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), [tabindex]:not([tabindex="-1"])';
const visible = ( el ) => el.getClientRects().length > 0;
const focusables = ( root ) => [ ...root.querySelectorAll( FOCUSABLE ) ].filter( ( el ) => visible( el ) && el.getAttribute( 'tabindex' ) !== '-1' );
const isRtl = ( el ) => getComputedStyle( el ).direction === 'rtl';

const header = () => document.querySelector( '.mizzey-header' );
const mega = () => document.getElementById( 'mizzey-mega' );
const allButton = () => document.querySelector( '.mizzey-header__all' );

/* ---- The mega menu ------------------------------------------------------------------------------------------- */

function showPanel( row, { switching = false } = {} ) {
	const root = mega();
	if ( ! root || ! row ) {
		return;
	}
	const id = JSON.parse( row.dataset.wpContext || '{}' ).panel;
	const shown = root.querySelector( '.mizzey-mega__panel:not([hidden])' );
	// Another top category cross-fades in place.
	if ( switching && shown && shown.id !== id ) {
		root.classList.remove( 'is-switching' );
		void root.offsetWidth;
		root.classList.add( 'is-switching' );
	}
	root.querySelectorAll( '.mizzey-mega__row' ).forEach( ( r ) => r.classList.toggle( 'is-active', r === row ) );
	root.querySelectorAll( '.mizzey-mega__panel' ).forEach( ( p ) => {
		p.hidden = p.id !== id;
	} );
}

function setMega( open, { focus = true } = {} ) {
	const root = mega();
	const button = allButton();
	if ( ! root || ! button ) {
		return;
	}
	const scrim = document.querySelector( '.mizzey-mega__scrim' );
	const wasInside = root.contains( document.activeElement );
	root.hidden = ! open;
	root.classList.remove( 'is-switching' );
	if ( scrim ) {
		scrim.hidden = ! open;
	}
	button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	header().classList.toggle( 'is-mega-open', open );
	if ( open ) {
		// It grows to fit, up to the window, then scrolls (C1-Menus-Desktop B4).
		const top = header().getBoundingClientRect().bottom;
		root.style.setProperty( '--mizzey-mega-max', `${ Math.max( 0, Math.floor( window.innerHeight - top ) ) }px` );
		const first = root.querySelector( '.mizzey-mega__row' );
		showPanel( first );
		if ( focus && first ) {
			first.focus();
		}
	} else if ( focus && wasInside ) {
		button.focus();
	}
}

const megaOpen = () => {
	const root = mega();
	return !! root && ! root.hidden;
};

/* ---- The Categories tab -------------------------------------------------------------------------------------- */

function setCategories( open ) {
	const panel = document.getElementById( 'mizzey-categories' );
	const tab = document.querySelector( '.mizzey-tabbar__categories' );
	if ( ! panel || ! tab ) {
		return;
	}
	// The chips step aside first: measured after.
	document.documentElement.classList.toggle( 'mizzey-categories-open', open );
	if ( open ) {
		window.scrollTo( 0, 0 );
		const bar = header();
		panel.style.setProperty( '--mizzey-categories-top', `${ Math.round( bar.getBoundingClientRect().bottom ) }px` );
	}
	panel.hidden = ! open;
	tab.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	if ( open ) {
		panel.querySelector( '.mizzey-categories__title' ).focus();
	} else if ( panel.contains( document.activeElement ) ) {
		tab.focus();
	}
}

/* ---- The category row's overflow ----------------------------------------------------------------------------- */

function fitRow( row, max ) {
	const list = row.querySelector( '.mizzey-header__links' );
	const more = row.querySelector( '.mizzey-header__more' );
	const pop = row.querySelector( '.mizzey-header__more-list' );
	if ( ! list || ! more || ! pop ) {
		return;
	}
	// Everything back in the row, in order, then move out what does not fit.
	[ ...pop.children ].forEach( ( item ) => list.insertBefore( item, more ) );
	const items = () => [ ...list.querySelectorAll( ':scope > .mizzey-header__item' ) ];
	more.hidden = true;
	if ( ! visible( list ) ) {
		return;
	}
	const overflows = () => list.scrollWidth > list.clientWidth + 1;
	const moved = [];
	items().slice( max ).forEach( ( item ) => moved.push( item ) );
	moved.forEach( ( item ) => item.remove() );
	if ( moved.length || overflows() ) {
		more.hidden = false;
		while ( overflows() && items().length ) {
			const last = items().pop();
			moved.unshift( last );
			last.remove();
		}
	}
	moved.forEach( ( item ) => pop.appendChild( item ) );
	more.hidden = moved.length === 0;
}

/* ---- The cart's count ---------------------------------------------------------------------------------------- */

function cartText( count, ctx ) {
	if ( count <= 0 ) {
		return ctx.cartEmpty;
	}
	let category = 'other';
	try {
		category = new Intl.PluralRules( document.documentElement.lang || 'en' ).select( count );
	} catch ( e ) {
		// The default form stands.
	}
	const template = ctx.cartLabels[ category ] || ctx.cartLabels.other;
	return template.replace( '%d', String( count ) );
}

const motionMs = ( name ) => {
	const raw = getComputedStyle( document.documentElement ).getPropertyValue( name ).trim();
	return ( raw.endsWith( 'ms' ) ? parseFloat( raw ) : parseFloat( raw ) * 1000 ) || 0;
};

/**
 * The badge's number rolls from the old to the new one while the badge bumps (C1-Motion-Playground). The badge's text
 * is the new number at once; the roll is drawn by its two pseudo-elements, so anything reading the count reads the
 * server's number.
 */
function roll( badge, text, up ) {
	const before = badge.textContent;
	const wasHidden = badge.hidden;
	badge.hidden = false;
	badge.textContent = text;
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || before === text ) {
		return;
	}
	clearTimeout( Number( badge.dataset.rolling || 0 ) );
	badge.classList.remove( 'is-bumping', 'is-rolling', 'is-down' );
	if ( ! wasHidden ) {
		badge.dataset.was = before;
		badge.dataset.now = text;
		badge.classList.toggle( 'is-down', ! up );
		badge.classList.add( 'is-rolling' );
	}
	void badge.offsetWidth; // Start the bump again when one is still running.
	badge.classList.add( 'is-bumping' );
	badge.dataset.rolling = String( setTimeout( () => badge.classList.remove( 'is-bumping', 'is-rolling', 'is-down' ), motionMs( '--mizzey-motion-duration-7' ) ) );
}

function showCount( count, ctx, animate = false ) {
	document.querySelectorAll( '.mizzey-header__cart' ).forEach( ( link ) => {
		const badge = link.querySelector( '.mizzey-count' );
		const label = link.querySelector( '.mizzey-header__cart-label' );
		if ( badge ) {
			const text = count > 99 ? badge.dataset.over || '99+' : String( count );
			const was = badge.hidden ? 0 : parseInt( badge.textContent, 10 ) || 0;
			if ( animate && count > 0 ) {
				roll( badge, text, count >= was );
			} else {
				clearTimeout( Number( badge.dataset.rolling || 0 ) );
				badge.classList.remove( 'is-bumping', 'is-rolling', 'is-down' );
				badge.textContent = text;
				badge.hidden = count <= 0;
			}
		}
		if ( label ) {
			label.textContent = cartText( count, ctx );
		}
	} );
}

let asking = null;
// One truth: a Store API count (the card's event) wins over a header read asked before it, or while a product flies.
let landingUntil = 0;
let storeAt = -1;
function refresh( ctx ) {
	if ( asking ) {
		asking.abort();
	}
	asking = new AbortController();
	const asked = performance.now();
	return fetch( ctx.rest, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': ctx.nonce }, signal: asking.signal } )
		.then( ( r ) => ( r.ok ? r.json() : null ) )
		.then( ( data ) => {
			if ( data && data.cart && performance.now() >= landingUntil && storeAt < asked ) {
				showCount( Number( data.cart.count ) || 0, ctx );
			}
		} )
		.catch( () => {} );
}

/* ---- The phone menu's motion (C1-Motion-Playground: "Overlays", "Phone menu, sub-level push") ------------------- */

function showLevel( level, on ) {
	level.hidden = level.inert = ! on;
	level.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
}

const miniCart = ( e ) => {
	if ( document.getElementById( 'mizzey-mini-cart' ) && e.target.closest( '[aria-controls=mizzey-mini-cart]' ) ) {
		const script = import( 'mizzey-theme/mini-cart' );
		if ( e.type === 'click' ) {
			e.preventDefault();
			script.then( ( m ) => m.openMiniCart() );
		}
	}
};
document.addEventListener( 'click', miniCart );
document.addEventListener( 'pointerdown', miniCart, { passive: true } );

// The level push is overlay-motion.js's, loaded as the drawer opens; until then the levels switch at once.
let motion = null;
const loadMotion = () => import( 'mizzey-theme/overlay-motion' ).then( ( m ) => ( motion = m ) ).catch( () => {} );

// Focus moved by the levels shows a ring after a key, never after a tap (blurred first, so none carries over).
let byKey = false;
document.addEventListener( 'keydown', () => ( byKey = true ), true );
document.addEventListener( 'pointerdown', () => ( byKey = false ), true );
function moveFocus( el ) {
	document.activeElement.blur();
	el.focus( { preventScroll: true, focusVisible: byKey } );
}

function pushLevel( from, to, forward ) {
	// The entrance (the close button's turn, the stagger) is over: Back does not replay it.
	from.closest( 'dialog' ).classList.remove( 'is-entering' );
	if ( motion ) {
		motion.pushLevel( from, to, forward, showLevel );
		return;
	}
	showLevel( to, true );
	showLevel( from, false );
	loadMotion();
}

const PRESSABLE = '.mizzey-button, .mizzey-chip, .mizzey-tabbar__item, .mizzey-shortcuts__link, .mizzey-collections__link, .mizzey-brands__link, .mizzey-card';

function pressFeedback() {
	let pressed = null;
	const release = () => {
		if ( pressed ) {
			pressed.classList.remove( 'is-pressed' );
			pressed = null;
		}
	};
	document.addEventListener( 'pointerdown', ( event ) => {
		release();
		const el = event.button ? null : event.target.closest?.( PRESSABLE );
		if ( ! el || el.matches( ':disabled, [aria-disabled="true"]' ) ) {
			return;
		}
		pressed = el;
		el.classList.add( 'is-pressed' );
	}, { passive: true, capture: true } );
	[ 'pointerup', 'pointercancel', 'dragstart', 'scroll' ].forEach( ( name ) => addEventListener( name, release, { passive: true, capture: true } ) );
	addEventListener( 'blur', release );
}

function wells() {
	const mark = ( img ) => img.complete && img.naturalWidth && img.parentElement?.setAttribute( 'data-mizzey-loaded', '' );
	document.querySelectorAll( 'img' ).forEach( mark );
	document.addEventListener( 'load', ( event ) => 'IMG' === event.target.tagName && mark( event.target ), true );
}

let lazy = null;
const later = () => ( lazy = lazy || import( 'mizzey-theme/header-lazy' ).catch( () => null ) );
const afterLoad = ( fn ) => ( 'complete' === document.readyState ? setTimeout( fn ) : addEventListener( 'load', () => setTimeout( fn ), { once: true } ) );

/* ---- The store ----------------------------------------------------------------------------------------------- */

store( 'mizzey/header', {
	actions: {
		megaToggle: withSyncEvent( ( event ) => {
			event.stopPropagation();
			setMega( ! megaOpen() );
		} ),
		megaShow() {
			showPanel( getElement().ref, { switching: true } );
		},
		megaClose() {
			setMega( false, { focus: false } );
		},
		megaKey: withSyncEvent( ( event ) => {
			const root = mega();
			const rows = [ ...root.querySelectorAll( '.mizzey-mega__row' ) ];
			const current = rows.indexOf( document.activeElement );
			const forward = isRtl( root ) ? 'ArrowLeft' : 'ArrowRight';
			const back = isRtl( root ) ? 'ArrowRight' : 'ArrowLeft';
			if ( current >= 0 && [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( event.key ) ) {
				event.preventDefault();
				const moves = { ArrowDown: current + 1, ArrowUp: current - 1, Home: 0, End: rows.length - 1 };
				const next = rows[ ( moves[ event.key ] + rows.length ) % rows.length ];
				next.focus();
				return;
			}
			if ( current >= 0 && event.key === forward ) {
				const panel = root.querySelector( '.mizzey-mega__panel:not([hidden])' );
				const first = panel ? focusables( panel )[ 0 ] : null;
				if ( first ) {
					event.preventDefault();
					first.focus();
				}
				return;
			}
			if ( current < 0 && event.key === back && document.activeElement.closest( '.mizzey-mega__panel' ) ) {
				event.preventDefault();
				( root.querySelector( '.mizzey-mega__row.is-active' ) || rows[ 0 ] ).focus();
				return;
			}
			if ( event.key === 'Tab' ) {
				const items = focusables( root );
				if ( ! items.length ) {
					return;
				}
				const first = items[ 0 ];
				const last = items[ items.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} ),
		key: withSyncEvent( ( event ) => {
			if ( event.key !== 'Escape' ) {
				return;
			}
			if ( megaOpen() ) {
				event.preventDefault();
				setMega( false );
				allButton().focus();
			}
			const panel = document.getElementById( 'mizzey-categories' );
			if ( panel && ! panel.hidden ) {
				event.preventDefault();
				setCategories( false );
				document.querySelector( '.mizzey-tabbar__categories' ).focus();
			}
		} ),
		outside: withSyncEvent( ( event ) => {
			const root = mega();
			if ( megaOpen() && ! root.contains( event.target ) && ! allButton().contains( event.target ) ) {
				setMega( false, { focus: false } );
			}
		} ),

		/* The phone menu's levels. */
		levelOpen() {
			const { level } = getContext();
			const button = getElement().ref;
			const from = button.closest( '.mizzey-menu__level' );
			const to = document.getElementById( level );
			if ( ! from || ! to ) {
				return;
			}
			pushLevel( from, to, true );
			button.setAttribute( 'aria-expanded', 'true' );
			// Usable from the first frame: focus is on the new level's back button as it comes in.
			const first = to.querySelector( '.mizzey-menu__back' ) || focusables( to )[ 0 ];
			if ( first ) {
				moveFocus( first );
			}
		},
		levelBack() {
			const { level, from } = getContext();
			const here = document.getElementById( from );
			const to = document.getElementById( level );
			if ( ! here || ! to ) {
				return;
			}
			pushLevel( here, to, false );
			const opener = to.querySelector( `[aria-controls="${ CSS.escape( from ) }"]` );
			if ( opener ) {
				opener.setAttribute( 'aria-expanded', 'false' );
				moveFocus( opener );
			}
		},

		categoriesToggle() {
			const panel = document.getElementById( 'mizzey-categories' );
			setCategories( !! panel && panel.hidden );
		},

		searchToggle() {
			const button = getElement().ref;
			const root = header();
			const open = ! root.classList.contains( 'is-search-open' );
			root.classList.toggle( 'is-search-open', open );
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				root.querySelector( '.mizzey-header__search-input' ).focus();
			}
		},
	},
	callbacks: {
		init() {
			const ctx = getContext();
			const root = getElement().ref;
			const tuck = () => later().then( ( m ) => m && m.tuck( root ) );
			addEventListener( 'scroll', tuck, { once: true, passive: true } );
			afterLoad( tuck );
			pressFeedback();
			wells();
			// The cart's count after a change made on the page.
			const ask = () => refresh( ctx );
			[ 'wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart' ].forEach( ( name ) => document.body.addEventListener( name, ask ) );
			document.addEventListener( 'mizzey-cart-changed', ( event ) => {
				const count = event.detail ? Number( event.detail.count ) : NaN;
				const landing = event.detail ? Number( event.detail.landing ) || 0 : 0;
				if ( Number.isFinite( count ) ) {
					if ( event.detail.cart ) {
						storeAt = performance.now();
					}
					landingUntil = Math.max( landingUntil, performance.now() + landing );
					if ( landing > 0 ) {
						setTimeout( () => showCount( count, ctx, true ), landing );
					} else {
						showCount( count, ctx, true );
					}
				} else {
					ask();
				}
			} );
			if ( window.jQuery ) {
				window.jQuery( document.body ).on( 'added_to_cart removed_from_cart', ask );
			}
			// A page restored from the back-forward cache shows the count of now.
			window.addEventListener( 'pageshow', ( event ) => {
				if ( event.persisted ) {
					ask();
				}
			} );
			// The category row's overflow, now and whenever its width changes.
			const row = root.querySelector( '.mizzey-header__row' );
			if ( row ) {
				let settle = () => {};
				const reached = () => later().then( ( m ) => m && ( settle = m.reached( row ) ) );
				row.addEventListener( 'pointerenter', reached, { once: true } );
				row.addEventListener( 'focusin', reached, { once: true } );
				afterLoad( reached );
				const fit = () => {
					fitRow( row, Number( ctx.rowMax ) || 8 );
					settle();
				};
				fit();
				if ( window.ResizeObserver ) {
					let width = row.clientWidth;
					new ResizeObserver( () => {
						if ( row.clientWidth !== width ) {
							width = row.clientWidth;
							fit();
						}
					} ).observe( row );
				}
				if ( document.fonts && document.fonts.ready ) {
					document.fonts.ready.then( fit );
				}
			}
		},
		initDrawer() {
			const dialog = getElement().ref;
			// The menu control reads expanded while the drawer is open and not leaving (ui.js's is-closing).
			const control = () => document.querySelector( `.mizzey-header__menu[aria-controls="${ CSS.escape( dialog.id ) }"]` );
			let wasOpen = dialog.open;
			const follow = () => {
				const open = dialog.open && ! dialog.classList.contains( 'is-closing' );
				const button = control();
				if ( button ) {
					button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				}
				if ( dialog.open && ! wasOpen ) {
					dialog.classList.add( 'is-entering' );
					loadMotion();
				}
				wasOpen = dialog.open;
			};
			new MutationObserver( follow ).observe( dialog, { attributes: true, attributeFilter: [ 'open', 'class' ] } );
			follow();
			// Closed, the drawer starts again at its first level.
			dialog.addEventListener( 'close', () => {
				dialog.classList.remove( 'is-entering' );
				if ( motion ) {
					motion.settlePush();
				}
				dialog.querySelectorAll( '.mizzey-menu__level' ).forEach( ( level, i ) => showLevel( level, i === 0 ) );
				dialog.querySelectorAll( '.mizzey-menu__row[aria-expanded]' ).forEach( ( row ) => row.setAttribute( 'aria-expanded', 'false' ) );
				follow();
			} );
		},
	},
} );

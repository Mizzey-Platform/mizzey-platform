/**
 * The header's behaviour (spec 066, slice 2), on the WordPress Interactivity API, store "mizzey/header".
 *
 * - The mega menu: "All categories" opens it under the header and dims the page; focus lands on the first row and
 *   stays inside; the arrow keys move between rows (the panel follows the row in focus or under the pointer) and
 *   into the panel; Escape, a press on the dimmed page or a click elsewhere closes it, and focus returns to the
 *   control when it was inside.
 * - The phone menu's levels: a category with children opens its own level, Back returns to the row that opened it;
 *   closing the drawer starts it again at the first level. Opening the drawer, its focus trap and the return of
 *   focus are the base drawer's (store "mizzey/ui").
 * - The Categories tab shows the categories between the header and the tab bar; Escape or the tab again closes it.
 * - The phone's search control (product, cart and checkout pages) opens the search row and focuses the field.
 * - The category row: what does not fit, and anything past the eighth link, moves under "More".
 * - The cart's count follows an add or a removal without a reload: on WooCommerce's own events (the classic
 *   "added_to_cart" and "removed_from_cart", the blocks' "wc-blocks_added_to_cart" and "wc-blocks_removed_from_cart")
 *   and on "mizzey:cart-changed", the header asks GET mizzey/v1/header and shows its answer. The count changes only
 *   when the server has answered (C1-Motion-Playground: motion never claims success early).
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

function showPanel( row ) {
	const root = mega();
	if ( ! root || ! row ) {
		return;
	}
	const id = JSON.parse( row.dataset.wpContext || '{}' ).panel;
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
	if ( open ) {
		window.scrollTo( 0, 0 );
		const bar = header();
		panel.style.setProperty( '--mizzey-categories-top', `${ Math.round( bar.getBoundingClientRect().bottom ) }px` );
	}
	panel.hidden = ! open;
	tab.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	document.documentElement.classList.toggle( 'mizzey-categories-open', open );
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

function showCount( count, ctx ) {
	document.querySelectorAll( '.mizzey-header__cart' ).forEach( ( link ) => {
		const badge = link.querySelector( '.mizzey-count' );
		const label = link.querySelector( '.mizzey-header__cart-label' );
		if ( badge ) {
			badge.textContent = count > 99 ? badge.dataset.over || '99+' : String( count );
			badge.hidden = count <= 0;
		}
		if ( label ) {
			label.textContent = cartText( count, ctx );
		}
	} );
}

let asking = null;
function refresh( ctx ) {
	if ( asking ) {
		asking.abort();
	}
	asking = new AbortController();
	return fetch( ctx.rest, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': ctx.nonce }, signal: asking.signal } )
		.then( ( r ) => ( r.ok ? r.json() : null ) )
		.then( ( data ) => {
			if ( data && data.cart ) {
				showCount( Number( data.cart.count ) || 0, ctx );
			}
		} )
		.catch( () => {} );
}

/* ---- The store ----------------------------------------------------------------------------------------------- */

store( 'mizzey/header', {
	actions: {
		megaToggle: withSyncEvent( ( event ) => {
			event.stopPropagation();
			setMega( ! megaOpen() );
		} ),
		megaShow() {
			showPanel( getElement().ref );
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
			const more = document.getElementById( 'mizzey-header-more' );
			if ( more && ! more.hidden ) {
				more.hidden = true;
				const button = document.querySelector( '.mizzey-header__more-button' );
				button.setAttribute( 'aria-expanded', 'false' );
				button.focus();
			}
		} ),
		outside: withSyncEvent( ( event ) => {
			const root = mega();
			if ( megaOpen() && ! root.contains( event.target ) && ! allButton().contains( event.target ) ) {
				setMega( false, { focus: false } );
			}
			const more = document.getElementById( 'mizzey-header-more' );
			if ( more && ! more.hidden && ! event.target.closest( '.mizzey-header__more' ) ) {
				more.hidden = true;
				document.querySelector( '.mizzey-header__more-button' ).setAttribute( 'aria-expanded', 'false' );
			}
		} ),
		moreToggle() {
			const button = getElement().ref;
			const list = document.getElementById( 'mizzey-header-more' );
			list.hidden = ! list.hidden;
			button.setAttribute( 'aria-expanded', list.hidden ? 'false' : 'true' );
		},

		/* The phone menu's levels. */
		levelOpen() {
			const { level } = getContext();
			const button = getElement().ref;
			const from = button.closest( '.mizzey-menu__level' );
			const to = document.getElementById( level );
			if ( ! from || ! to ) {
				return;
			}
			from.hidden = true;
			to.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			const first = focusables( to )[ 0 ];
			if ( first ) {
				first.focus();
			}
		},
		levelBack() {
			const { level, from } = getContext();
			const here = document.getElementById( from );
			const to = document.getElementById( level );
			if ( ! here || ! to ) {
				return;
			}
			here.hidden = true;
			to.hidden = false;
			const opener = to.querySelector( `[aria-controls="${ CSS.escape( from ) }"]` );
			if ( opener ) {
				opener.setAttribute( 'aria-expanded', 'false' );
				opener.focus();
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
			// The cart's count after a change made on the page.
			const ask = () => refresh( ctx );
			[ 'wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart' ].forEach( ( name ) => document.body.addEventListener( name, ask ) );
			document.addEventListener( 'mizzey:cart-changed', ask );
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
				const fit = () => fitRow( row, Number( ctx.rowMax ) || 8 );
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
			// Closed, the drawer starts again at its first level.
			dialog.addEventListener( 'close', () => {
				dialog.querySelectorAll( '.mizzey-menu__level' ).forEach( ( level, i ) => {
					level.hidden = i !== 0;
				} );
				dialog.querySelectorAll( '.mizzey-menu__row[aria-expanded]' ).forEach( ( row ) => row.setAttribute( 'aria-expanded', 'false' ) );
			} );
		},
	},
} );

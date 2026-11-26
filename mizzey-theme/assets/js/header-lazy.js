/**
 * The header's parts header.js imports at their first need or once the page has loaded, so they are not in a page's
 * script budget (D-21; header.js's later() and afterLoad()). Each starts once, whichever call comes first:
 *
 * - On the first scroll, the sticky header's tuck (C1-Motion-Playground): scrolling down past 200 px tucks the strip
 *   and the chips away, scrolling up brings them back (header.css).
 * - When a pointer or the keyboard first reaches the desktop category row: "More" placed under its button (a native
 *   popover; until then it opens under the row's end), and the row's sliding underline (the owner's design addition,
 *   26 November 2026): one bar slides to the item under the pointer or in focus and settles back on the page's own
 *   item when they leave, on the soft spring (header.css); it fades where the page has no item of its own; reduced
 *   motion moves it without sliding. Until then the page's own item keeps its static bar.
 */

const visible = ( el ) => el.getClientRects().length > 0;
const isRtl = ( el ) => getComputedStyle( el ).direction === 'rtl';

function rowIndicator( row ) {
	const inner = row.querySelector( '.mizzey-header__row-inner' );
	const bar = row.querySelector( '.mizzey-header__indicator' );
	const list = row.querySelector( '.mizzey-header__links' );
	if ( ! inner || ! bar || ! list ) {
		return () => {};
	}
	const link = ( target ) => {
		const a = target && target.closest ? target.closest( '.mizzey-header__link' ) : null;
		return a && list.contains( a ) && ! a.classList.contains( 'mizzey-header__more-button' ) ? a : null;
	};
	const own = () => list.querySelector( ':scope > .mizzey-header__item > .mizzey-header__link[aria-current]' );
	const to = ( a, instant = false ) => {
		if ( ! a || ! visible( a ) ) {
			bar.classList.remove( 'is-shown' );
			return;
		}
		const box = inner.getBoundingClientRect();
		const r = a.getBoundingClientRect();
		const appearing = ! bar.classList.contains( 'is-shown' );
		if ( instant || appearing ) {
			bar.classList.add( 'is-placing' );
		}
		bar.style.setProperty( '--mizzey-indicator-start', `${ isRtl( inner ) ? box.right - r.right : r.left - box.left }px` );
		bar.style.setProperty( '--mizzey-indicator-size', `${ r.width }px` );
		bar.style.setProperty( '--mizzey-indicator-foot', `${ r.bottom - box.top }px` );
		if ( bar.classList.contains( 'is-placing' ) ) {
			void bar.offsetWidth; // the place is taken at once; only what follows moves
			bar.classList.remove( 'is-placing' );
		}
		bar.classList.add( 'is-shown' );
	};
	list.addEventListener( 'pointerover', ( event ) => {
		const a = link( event.target );
		if ( a ) {
			to( a );
		}
	} );
	list.addEventListener( 'pointerleave', () => to( own() ) );
	list.addEventListener( 'focusin', ( event ) => {
		const a = link( event.target );
		if ( a ) {
			to( a );
		}
	} );
	list.addEventListener( 'focusout', ( event ) => {
		if ( ! list.contains( event.relatedTarget ) ) {
			to( own() );
		}
	} );
	row.classList.add( 'has-indicator' );
	const settle = () => to( own(), true );
	settle();
	// A pointer or a focus already on an item when this started.
	const now = link( list.querySelector( '.mizzey-header__link:hover' ) ) || link( list.contains( document.activeElement ) ? document.activeElement : null );
	if ( now ) {
		to( now );
	}
	return settle;
}

/* "More": a popover in the top layer, placed under its button with their end edges aligned; it closes when the page
   moves under it. The browser opens it, closes it on Escape or a press outside, and returns the focus. */
function morePopover( row ) {
	const list = row.querySelector( '.mizzey-header__more-list' );
	const button = row.querySelector( '.mizzey-header__more-button' );
	if ( ! list || ! button || typeof list.showPopover !== 'function' ) {
		return;
	}
	const place = () => {
		const box = button.getBoundingClientRect();
		const width = document.documentElement.clientWidth;
		list.style.setProperty( '--mizzey-more-top', `${ Math.round( box.bottom + 4 ) }px` );
		list.style.setProperty( '--mizzey-more-end', `${ Math.round( isRtl( button ) ? box.left : width - box.right ) }px` );
	};
	list.addEventListener( 'beforetoggle', ( event ) => {
		if ( event.newState === 'open' ) {
			place();
		}
		button.setAttribute( 'aria-expanded', event.newState === 'open' ? 'true' : 'false' );
	} );
	const close = () => {
		if ( list.matches( ':popover-open' ) ) {
			list.hidePopover();
		}
	};
	window.addEventListener( 'scroll', close, { passive: true } );
	window.addEventListener( 'resize', close, { passive: true } );
	// Opened before this loaded (a quick first press): placed now.
	if ( list.matches( ':popover-open' ) ) {
		place();
		button.setAttribute( 'aria-expanded', 'true' );
	}
}

/** The row was reached: "More" placed, the underline running; returns the underline's settle, for header.js. */
const started = new WeakMap();

export function reached( row ) {
	if ( ! started.has( row ) ) {
		morePopover( row );
		started.set( row, rowIndicator( row ) );
	}
	return started.get( row );
}

export function tuck( root ) {
	const part = root.closest( '.wp-block-template-part' );
	if ( ! part || started.has( part ) ) {
		return;
	}
	started.set( part, true );
	const strip = part.querySelector( '.mizzey-announcement' );
	const measure = () => part.style.setProperty( '--mizzey-tuck', `${ strip ? strip.offsetHeight : 0 }px` );
	measure();
	addEventListener( 'resize', measure, { passive: true } );
	let last = 0;
	const update = () => {
		const y = scrollY;
		if ( y > 200 && y > last ) {
			part.classList.add( 'is-tucked' );
		} else if ( y <= 200 || y < last ) {
			part.classList.remove( 'is-tucked' );
		}
		last = y;
	};
	// The scroll that imported this counts too.
	update();
	addEventListener( 'scroll', () => requestAnimationFrame( update ), { passive: true } );
}

/**
 * Motion after a press on the product page (spec 066, slice 9), loaded by view.js on the first press (the page's script
 * budget, D-21): the photo's thumbnail flying to the header's cart after an add, and the share popover's place.
 */
const q = ( root, sel ) => root.querySelector( sel );

/** After the answer, on a desktop: the photo's thumbnail flies to the header's cart (C1-Product-Desktop). */
export function fly( root ) {
	const img = q( root, '.mizzey-gallery__slide.is-current img' );
	const cart = document.querySelector( '.mizzey-header__cart' );
	if ( ! img || ! cart || window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! img.animate ) {
		return;
	}
	const from = img.getBoundingClientRect();
	const to = cart.getBoundingClientRect();
	const ghost = img.cloneNode();
	ghost.removeAttribute( 'srcset' );
	ghost.className = 'mizzey-buy-fly';
	ghost.alt = '';
	document.body.appendChild( ghost );
	const size = ghost.getBoundingClientRect();
	const at = ( x, y ) => `translate(${ x - size.width / 2 }px, ${ y - size.height / 2 }px)`;
	const css = getComputedStyle( ghost );
	ghost.animate( [
		{ transform: `${ at( from.x + from.width / 2, from.y + from.height / 2 ) } scale(1)`, opacity: 1 },
		{ transform: `${ at( to.x + to.width / 2, to.y + to.height / 2 ) } scale(0.3)`, opacity: 0.4 },
	], { duration: parseFloat( css.getPropertyValue( '--mizzey-buy-fly-time' ) ) || 0, easing: css.getPropertyValue( '--mizzey-buy-fly-easing' ).trim() || 'ease' } ).finished.then( () => ghost.remove(), () => ghost.remove() );
}

/** The share popover under Share, its end edge on Share's; above it when it does not fit below. */
export function placeShare( dialog, button ) {
	const r = button.getBoundingClientRect();
	const rtl = getComputedStyle( button ).direction === 'rtl';
	const panel = q( dialog, '.mizzey-share__panel' );
	const box = panel.getBoundingClientRect();
	const gap = parseFloat( getComputedStyle( panel ).paddingBlockStart ) || 0;
	const below = r.bottom + gap + box.height <= window.innerHeight;
	dialog.style.setProperty( '--mizzey-pop-block', `${ below ? r.bottom : r.top - box.height - gap * 2 }px` );
	dialog.style.setProperty( '--mizzey-pop-inline', `${ rtl ? r.x : window.innerWidth - r.x - r.width }px` );
	dialog.classList.remove( 'is-placing' );
}

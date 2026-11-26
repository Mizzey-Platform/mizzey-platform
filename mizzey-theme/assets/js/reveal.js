/**
 * The scroll reveal (the owner's ruling of 25 November 2026, a design addition): each library section fades in and
 * rises 12 px once, the first time it enters the screen; the cards of a rail follow 30 ms apart, the first six only.
 * The look is in assets/css/base.css ("Scroll reveal"), on the motion tokens; this module only marks the sections.
 *
 * - Nothing is hidden before this script runs, so with no JavaScript, for a crawler, or before it loads, every section
 *   shows. It hides only what is below the first screen, after it has marked the html element (mizzey-reveal).
 * - What is on the first screen when the page loads (the hero, the Largest Contentful Paint) never animates.
 * - Under reduced motion it does nothing: everything shows at once.
 * - A section is revealed once, when about 15% of it is in view, and never hidden again.
 *
 * Any page can use it: an element with the class mizzey-section (every library section) or the attribute
 * data-mizzey-reveal is revealed. A plain script module with no import.
 */

const SELECTOR = '.mizzey-section, [data-mizzey-reveal]';
const THRESHOLD = 0.15;

function start() {
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	const fold = window.innerHeight;
	const waiting = [ ...document.querySelectorAll( SELECTOR ) ].filter( ( el ) => {
		// Nested sections (a section inside another's preview) follow their parent.
		if ( el.parentElement && el.parentElement.closest( SELECTOR ) ) {
			return false;
		}
		return el.getBoundingClientRect().top >= fold;
	} );
	if ( ! waiting.length ) {
		return;
	}
	// About 15% of the section in view, or, for a section taller than the screen, 15% of the screen filled by it.
	const enough = ( entry ) => entry.isIntersecting && ( entry.intersectionRatio >= THRESHOLD || entry.intersectionRect.height >= window.innerHeight * THRESHOLD );
	const observer = new IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			if ( enough( entry ) ) {
				entry.target.setAttribute( 'data-reveal', 'in' );
				observer.unobserve( entry.target );
			}
		} );
	}, { threshold: [ 0, 0.05, THRESHOLD, 0.3 ] } );
	waiting.forEach( ( el ) => {
		el.setAttribute( 'data-reveal', 'waiting' );
		observer.observe( el );
	} );
	document.documentElement.classList.add( 'mizzey-reveal' );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start, { once: true } );
} else {
	start();
}

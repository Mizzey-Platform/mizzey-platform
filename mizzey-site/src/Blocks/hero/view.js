/**
 * The hero slider on the storefront (spec 066, slice 5: HOME-01), as C1-Hero-Banners draws it.
 *
 * A plain script module with no import: it loads only on a page that shows a hero slider. The slides sit in a row
 * that scrolls sideways and snaps, so a phone swipes it natively and Arabic runs the other way by itself; this script
 * moves it on a timer, follows a swipe, and keeps the dots, the label and the progress line in step.
 *
 * - At least six seconds a slide (the section's data-interval, never below MOTION.minSeconds).
 * - It holds while the visitor touches, hovers or focuses it, or the page is hidden, and starts again after.
 * - The pause button stops it until it is pressed again. Play moves on at once, over the hover, focus or touch hold of
 *   that moment (pressing it puts the focus on the button), until the next hold begins: the rule of the announcement
 *   strip (mizzey-theme/assets/js/site-parts.js).
 * - Under reduced motion it starts paused and moves only after an explicit Play, without animation; the arrows and a
 *   swipe still change the slide. The button's name always says what pressing it does.
 * - Only the slide shown can be reached by keyboard and read; the others are inert.
 *
 * How it moves is kept here, in MOTION, and in style.css under [data-motion]: another way from C1-Slider-Options is a
 * new name there and its own rules, nothing else.
 */

const MOTION = {
	name: 'slide',
	minSeconds: 6,
	behavior: 'smooth',
};

const calm = window.matchMedia( '(prefers-reduced-motion: reduce)' );

function setup( hero ) {
	const track = hero.querySelector( '.mizzey-hero__track' );
	const slides = [ ...hero.querySelectorAll( '.mizzey-hero__slide' ) ];
	const dots = [ ...hero.querySelectorAll( '.mizzey-hero__dot' ) ];
	const status = hero.querySelector( '.mizzey-hero__dots' );
	const pause = hero.querySelector( '.mizzey-hero__pause' );
	if ( ! track || slides.length < 2 ) {
		return;
	}
	const seconds = Math.max( MOTION.minSeconds, parseInt( hero.dataset.interval, 10 ) || MOTION.minSeconds );
	hero.style.setProperty( '--mizzey-hero-interval', `${ seconds }s` );
	hero.dataset.motion = MOTION.name;

	let index = 0;
	let timer = 0;
	let moving = false;
	let settle = 0;
	let stopped = calm.matches;
	const holds = { hover: false, focus: false, touch: false, hidden: false };

	const rtl = () => getComputedStyle( track ).direction === 'rtl';
	const held = () => stopped || Object.values( holds ).some( Boolean );

	function show( next ) {
		index = next;
		slides.forEach( ( slide, i ) => {
			const on = i === index;
			slide.classList.toggle( 'is-active', on );
			slide.toggleAttribute( 'inert', ! on );
			slide.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
		} );
		dots.forEach( ( dot, i ) => dot.classList.toggle( 'is-active', i === index ) );
		if ( status && status.dataset.label ) {
			status.setAttribute( 'aria-label', status.dataset.label.replace( '%1$d', String( index + 1 ) ).replace( '%2$d', String( slides.length ) ) );
		}
		restart();
	}

	function go( next ) {
		const target = ( next + slides.length ) % slides.length;
		const box = track.getBoundingClientRect();
		const at = slides[ target ].getBoundingClientRect();
		// The slide's start edge against the row's: its far edge in Arabic.
		const edge = ( r ) => ( rtl() ? r.x + r.width : r.x );
		const left = track.scrollLeft + edge( at ) - edge( box );
		// The row passes the slides between while it moves: they are not "seen" until it stops.
		moving = true;
		window.clearTimeout( settle );
		settle = window.setTimeout( () => ( moving = false ), 1500 );
		track.scrollTo( { left, behavior: calm.matches ? 'auto' : MOTION.behavior } );
		show( target );
	}

	function restart() {
		window.clearTimeout( timer );
		hero.classList.remove( 'is-running' );
		hero.classList.toggle( 'is-paused', stopped );
		if ( held() ) {
			return;
		}
		void hero.offsetWidth; // restart the progress line's fill from empty
		hero.classList.add( 'is-running' );
		timer = window.setTimeout( () => go( index + 1 ), seconds * 1000 );
	}

	function hold( key, on ) {
		if ( holds[ key ] !== on ) {
			holds[ key ] = on;
			restart();
		}
	}

	let frame = 0;
	track.addEventListener( 'scrollend', () => ( moving = false ) );
	track.addEventListener( 'scroll', () => {
		if ( moving ) {
			return;
		}
		window.cancelAnimationFrame( frame );
		frame = window.requestAnimationFrame( () => {
			const width = track.clientWidth || 1;
			const seen = Math.round( Math.abs( track.scrollLeft ) / width );
			if ( seen !== index && seen >= 0 && seen < slides.length ) {
				show( seen );
			}
		} );
	}, { passive: true } );

	hero.querySelectorAll( '.mizzey-hero__arrow' ).forEach( ( arrow ) => arrow.addEventListener( 'click', () => go( index + Number( arrow.dataset.step || 1 ) ) ) );
	function name() {
		if ( pause ) {
			pause.setAttribute( 'aria-label', stopped ? pause.dataset.labelPlay : pause.dataset.labelPause );
		}
	}

	function setStopped( on ) {
		stopped = on;
		name();
		if ( on ) {
			restart();
			return;
		}
		// An explicit play overrides the hover, focus or touch hold of the moment (the next one holds again) and moves on
		// at once. The page being hidden is not a hold a press can lift.
		holds.hover = false;
		holds.focus = false;
		holds.touch = false;
		go( index + 1 );
	}

	if ( pause ) {
		pause.addEventListener( 'click', () => setStopped( ! stopped ) );
	}
	name();
	const viewport = hero.querySelector( '.mizzey-hero__viewport' ) || hero;
	viewport.addEventListener( 'pointerenter', ( e ) => e.pointerType === 'mouse' && hold( 'hover', true ) );
	viewport.addEventListener( 'pointerleave', ( e ) => e.pointerType === 'mouse' && hold( 'hover', false ) );
	viewport.addEventListener( 'touchstart', () => hold( 'touch', true ), { passive: true } );
	viewport.addEventListener( 'touchend', () => hold( 'touch', false ), { passive: true } );
	viewport.addEventListener( 'touchcancel', () => hold( 'touch', false ), { passive: true } );
	hero.addEventListener( 'focusin', () => hold( 'focus', true ) );
	hero.addEventListener( 'focusout', ( e ) => ! hero.contains( e.relatedTarget ) && hold( 'focus', false ) );
	document.addEventListener( 'visibilitychange', () => hold( 'hidden', document.hidden ) );
	calm.addEventListener( 'change', () => {
		if ( calm.matches ) {
			setStopped( true );
		}
	} );

	show( 0 );
}

document.querySelectorAll( '.mizzey-hero--slider' ).forEach( setup );

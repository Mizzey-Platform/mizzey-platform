/* Spec 066 slice 10. Words come from the page; nothing is sent. prefers-reduced-motion: reduce is followed. */

const root = document.querySelector( '.mizzey-details' );
const $ = ( s, el = root ) => el.querySelector( s );
const glide = () => ( matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' );

function jump( nav ) {
	const links = [ ...nav.querySelectorAll( 'a' ) ];
	const parts = links.map( ( a ) => document.getElementById( a.hash.slice( 1 ) ) );
	let held = 0;
	const mark = ( n ) => {
		links.forEach( ( a, i ) => ( i === n ? a.setAttribute( 'aria-current', 'true' ) : a.removeAttribute( 'aria-current' ) ) );
		nav.firstElementChild.style.setProperty( '--mizzey-details-current', n );
	};
	// Under whatever part of the header stays on screen (a sticky or fixed bar), or at the top.
	let top = 0;
	const under = () => {
		top = 0;
		document.querySelectorAll( 'header.wp-block-template-part, header.wp-block-template-part *' ).forEach( ( el ) => {
			const s = getComputedStyle( el );
			const r = el.getBoundingClientRect();
			if ( /sticky|fixed/.test( s.position ) && r.height && r.width > innerWidth / 2 && r.height < innerHeight / 3 ) {
				top = Math.max( top, ( parseFloat( s.top ) || 0 ) + r.height );
			}
		} );
		root.style.setProperty( '--mizzey-details-top', `${ top }px` );
	};
	under();
	addEventListener( 'resize', under, { passive: true } );
	const stuck = () => nav.classList.toggle( 'is-stuck', $( '.mizzey-details__sentinel' ).getBoundingClientRect().top < top );
	addEventListener( 'scroll', stuck, { passive: true } );
	stuck();
	addEventListener( 'scroll', () => {
		if ( Date.now() - held > 900 ) {
			const line = nav.getBoundingClientRect().bottom + 8;
			mark( parts.reduce( ( n, p, i ) => ( p && p.getBoundingClientRect().top <= line ? i : n ), 0 ) );
		}
	}, { passive: true } );
	links.forEach( ( a, i ) => a.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		held = Date.now();
		mark( i );
		parts[ i ].scrollIntoView( { behavior: glide() } );
		history.replaceState( null, '', a.hash );
	} ) );
}

function more( body, button ) {
	const label = button.textContent;
	const fit = () => {
		if ( button.getAttribute( 'aria-expanded' ) !== 'true' ) {
			button.hidden = body.scrollHeight <= body.clientHeight + 1;
		}
	};
	fit();
	addEventListener( 'resize', fit, { passive: true } );
	button.addEventListener( 'click', () => {
		const open = button.getAttribute( 'aria-expanded' ) !== 'true';
		button.setAttribute( 'aria-expanded', open );
		body.classList.toggle( 'is-open', open );
		button.textContent = open ? button.dataset.less : label;
	} );
}


if ( root ) {
	const nav = $( '.mizzey-details__jump' );
	const body = $( '.mizzey-details__body' );
	const sort = $( '.mizzey-reviews__sort input[name="review_sort"]' );
	const entry = $( '.mizzey-review-entry' );
	if ( nav ) {
		jump( nav );
	}
	if ( body ) {
		more( body, $( '.mizzey-details__more' ) );
	}
	if ( sort ) {
		sort.addEventListener( 'change', () => sort.form.requestSubmit() );
	}
	root.querySelectorAll( '.mizzey-reviews__write-button' ).forEach( ( b ) => b.addEventListener( 'click', ( e ) => {
		entry.hidden = ! entry.hidden;
		b.setAttribute( 'aria-expanded', ! entry.hidden );
		if ( ! entry.hidden ) {
			entry.scrollIntoView( { behavior: glide(), block: 'nearest' } );
			// Keyboard only: no ring after a tap.
			if ( ! e.detail ) {
				$( 'input, textarea, a', entry ).focus( { preventScroll: true } );
			}
		}
	} ) );
}

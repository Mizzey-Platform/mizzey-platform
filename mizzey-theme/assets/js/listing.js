/**
 * The category listing's behaviour (spec 066, slice 6), on the WordPress Interactivity API.
 *
 * The page works without this script: the filter column is a form and the sorts are links. This adds:
 *
 * - **A desktop applies each change at once**: a box ticked, the switch, a price handle let go, a sort chosen. The
 *   address it goes to is the clean one WooCommerce reads (a value list joined by commas, an untouched price range
 *   left out), so the filters and the sort stay in the address and come back with the page.
 * - **The loading state**: while the next page is on its way, each card gives way to its skeleton, in its place, with
 *   the breathing ZZ in the image well; nothing moves.
 * - **The phone's drawer**: the filter form moves into it when it opens and back when it closes. Each change asks the
 *   server for the count of every chosen filter together; until the answer comes, the button shows the i-dot loader
 *   and no number (motion never claims a result early); then it reads "Show N products". The button applies them.
 * - **The price slider**: the two handles never cross, the bars in the range turn Indigo, a bubble shows the price
 *   while a handle moves; Page Up and Page Down move a handle by ten steps; the range line opens two fields for
 *   typing. On a desktop the count line asks the server while a handle moves.
 * - **Brands**: the search field narrows the list; "Show more" shows the rest.
 * - **Back from a product**: the page's scroll position is kept for its address, and restored when the page comes back.
 *
 * Every word comes from the server, translated. Reduced motion is honoured by the styles.
 */
import { store, getElement } from '@wordpress/interactivity';

const desktop = () => window.matchMedia( '(min-width: 1024px)' ).matches;
const SCROLL = 'mizzey-listing-scroll';
let leaving = false;

/** The clean address of the form's choices. */
function addressOf( root, form ) {
	const base = root.dataset.base;
	const groups = new Map();
	for ( const [ rawKey, rawValue ] of new FormData( form ) ) {
		const key = rawKey.replace( /\[\]$/, '' );
		const value = String( rawValue ).trim();
		if ( [ 'mizzey_filters', 'mizzey_price_low', 'mizzey_price_high' ].includes( key ) || '' === value ) {
			continue;
		}
		if ( ! groups.has( key ) ) {
			groups.set( key, [] );
		}
		groups.get( key ).push( value );
	}
	const price = form.querySelector( '.mizzey-price' );
	if ( price ) {
		const low = Number( price.dataset.low );
		const high = Number( price.dataset.high );
		const min = Number( ( groups.get( 'min_price' ) || [ low ] )[ 0 ] );
		const max = Number( ( groups.get( 'max_price' ) || [ high ] )[ 0 ] );
		if ( min <= low && max >= high ) {
			groups.delete( 'min_price' );
			groups.delete( 'max_price' );
		} else {
			groups.set( 'min_price', [ String( Math.max( low, min ) ) ] );
			groups.set( 'max_price', [ String( Math.min( high, max ) ) ] );
		}
	}
	const query = new URLSearchParams();
	for ( const [ key, values ] of groups ) {
		query.set( key, values.join( ',' ) );
		if ( key.startsWith( 'filter_' ) && 'filter_stock_status' !== key && values.length > 1 ) {
			query.set( `query_type_${ key.slice( 7 ) }`, 'or' );
		}
	}
	const text = query.toString();
	return text ? `${ base }${ base.includes( '?' ) ? '&' : '?' }${ text }` : base;
}

/** Each card gives way to its skeleton, in place; the results say they are busy. */
function showLoading( root ) {
	const results = root.querySelector( '.mizzey-listing__results' );
	const template = root.querySelector( '#mizzey-listing-skeleton' );
	if ( ! results || ! template || 'true' === results.getAttribute( 'aria-busy' ) ) {
		return;
	}
	results.setAttribute( 'aria-busy', 'true' );
	results.querySelectorAll( '.mizzey-products__item' ).forEach( ( item ) => {
		const card = item.firstElementChild;
		const skeleton = template.content.firstElementChild.cloneNode( true );
		if ( card ) {
			skeleton.style.blockSize = `${ card.getBoundingClientRect().height }px`;
			card.hidden = true;
		}
		item.append( skeleton );
	} );
}

function clearLoading( root ) {
	const results = root.querySelector( '.mizzey-listing__results' );
	if ( ! results ) {
		return;
	}
	results.setAttribute( 'aria-busy', 'false' );
	results.querySelectorAll( '.mizzey-card--skeleton' ).forEach( ( s ) => s.remove() );
	results.querySelectorAll( '.mizzey-products__item > [hidden]' ).forEach( ( c ) => {
		c.hidden = false;
	} );
}

function go( root, url ) {
	try {
		leaving = true;
		sessionStorage.setItem( SCROLL, JSON.stringify( { to: new URL( url, location.href ).href, y: window.scrollY } ) );
	} catch ( e ) {}
	showLoading( root );
	window.location.assign( url );
}

/** Ask the server how many products a set of choices finds; one question at a time, the last one wins. */
function counter( root ) {
	let controller = null;
	return async ( url ) => {
		if ( controller ) {
			controller.abort();
		}
		controller = new AbortController();
		const ask = new URL( url, location.href );
		ask.searchParams.set( 'mizzey_count', '1' );
		const response = await fetch( ask, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } } );
		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}
		return response.json();
	};
}

function setButton( button, state, text ) {
	const label = button.querySelector( '.mizzey-button__label' );
	button.setAttribute( 'aria-busy', 'waiting' === state ? 'true' : 'false' );
	button.classList.toggle( 'is-waiting', 'waiting' === state );
	button.disabled = 'waiting' === state;
	if ( label ) {
		label.textContent = text;
	}
}

/* ---- The price slider -------------------------------------------------------------------------------------- */

function priceParts( price ) {
	return {
		price,
		min: price.querySelector( '.mizzey-price__min' ),
		max: price.querySelector( '.mizzey-price__max' ),
		track: price.querySelector( '.mizzey-price__track' ),
		bubble: price.querySelector( '.mizzey-price__bubble' ),
		range: price.querySelector( '.mizzey-price__range' ),
		fields: price.querySelector( '.mizzey-price__fields' ),
		typed: [ ...price.querySelectorAll( '.mizzey-price__type' ) ],
		bars: [ ...price.querySelectorAll( '.mizzey-price__bar' ) ],
		low: Number( price.dataset.low ),
		high: Number( price.dataset.high ),
		step: Number( price.dataset.step ) || 1,
		format: price.dataset.format || '',
	};
}

const group = ( n ) => new Intl.NumberFormat( document.documentElement.lang || 'en', { numberingSystem: 'latn', maximumFractionDigits: 0 } ).format( n );
const rangeText = ( p, from, to ) => p.format.replace( '1,111', group( from ) ).replace( '2,222', group( to ) ).replace( '1111', group( from ) ).replace( '2222', group( to ) );

function paint( p, moving ) {
	let from = Number( p.min.value );
	let to = Number( p.max.value );
	if ( from > to ) {
		if ( moving === p.min ) {
			from = to;
			p.min.value = String( to );
		} else {
			to = from;
			p.max.value = String( from );
		}
	}
	const span = p.high - p.low || 1;
	p.track.style.setProperty( '--mizzey-price-from', `${ ( 100 * ( from - p.low ) ) / span }%` );
	p.track.style.setProperty( '--mizzey-price-to', `${ ( 100 * ( to - p.low ) ) / span }%` );
	p.bars.forEach( ( bar ) => bar.classList.toggle( 'is-in', Number( bar.dataset.end ) > from && Number( bar.dataset.start ) < to ) );
	const text = rangeText( p, from, to );
	p.range.textContent = text;
	p.typed.forEach( ( input ) => {
		if ( document.activeElement !== input ) {
			input.value = String( 'min' === input.dataset.end ? from : to );
		}
	} );
	if ( moving && p.bubble ) {
		const value = moving === p.min ? from : to;
		p.bubble.textContent = bubbleText( p, value );
		p.bubble.style.setProperty( '--mizzey-price-at', String( ( value - p.low ) / span ) );
	}
	return { from, to };
}

/** The bubble: one price in the store's format ("EGP 6,000"), from the range format with both ends the same. */
function bubbleText( p, value ) {
	const both = rangeText( p, value, value );
	const number = group( value );
	const first = both.indexOf( number );
	const last = both.lastIndexOf( number );
	if ( first < 0 || first === last ) {
		return both;
	}
	// Keep the currency on its side of the first number, drop " to <number>".
	const before = both.slice( 0, first );
	const after = both.slice( last + number.length );
	return `${ before }${ number }${ after }`.replace( /\s+/g, ' ' ).trim();
}

store( 'mizzey/listing', {
	callbacks: {
		init() {
			const { ref: root } = getElement();
			root.classList.add( 'is-enhanced' );
			const form = root.querySelector( '.mizzey-filters__panel' );
			const aside = root.querySelector( '.mizzey-filters' );
			const drawer = root.querySelector( '#mizzey-filters-drawer' );
			const drawerBody = drawer && drawer.querySelector( '.mizzey-listing__drawer-body' );
			const show = form && form.querySelector( '.mizzey-listing__show' );
			const ask = counter( root );

			// Back from a product: the page comes back where it was.
			try {
				const kept = JSON.parse( sessionStorage.getItem( SCROLL ) || 'null' );
				if ( kept && kept.to === location.href ) {
					window.scrollTo( 0, kept.y );
				}
			} catch ( e ) {}
			window.addEventListener( 'pagehide', () => {
				if ( leaving ) {
					return;
				}
				try {
					sessionStorage.setItem( SCROLL, JSON.stringify( { to: location.href, y: window.scrollY } ) );
				} catch ( e ) {}
			} );
			window.addEventListener( 'pageshow', ( event ) => {
				if ( event.persisted ) {
					clearLoading( root );
				}
			} );

			// Links that lead to another state of the list show the loading state on the way.
			root.addEventListener( 'click', ( event ) => {
				const link = event.target.closest( '.mizzey-listing__applied a, .mizzey-listing__clear, .mizzey-pagination a[href], .mizzey-listing__sort-links a' );
				if ( link && ! event.defaultPrevented && 0 === event.button && ! event.metaKey && ! event.ctrlKey ) {
					showLoading( root );
				}
			} );

			// The desktop sort.
			const sortValue = root.querySelector( '.mizzey-listing__sort-value' );
			if ( sortValue ) {
				sortValue.addEventListener( 'change', () => {
					const option = root.querySelector( `.mizzey-listing__sort [role="option"][data-value="${ CSS.escape( sortValue.value ) }"]` );
					if ( option && option.dataset.url ) {
						go( root, option.dataset.url );
					}
				} );
			}
			// The phone's sort sheet.
			root.querySelectorAll( '#mizzey-sort-sheet input[type="radio"]' ).forEach( ( radio ) => {
				radio.addEventListener( 'change', () => {
					const sheet = radio.closest( 'dialog' );
					if ( sheet && sheet.open ) {
						sheet.close();
					}
					const option = root.querySelector( `.mizzey-listing__sort [role="option"][data-value="${ CSS.escape( radio.value ) }"]` );
					if ( option && option.dataset.url ) {
						go( root, option.dataset.url );
					}
				} );
			} );

			if ( ! form ) {
				return;
			}

			// The drawer holds the form while it is open.
			const opener = root.querySelector( '.mizzey-listing__filters-open' );
			if ( opener && drawer && drawerBody ) {
				opener.addEventListener( 'click', () => {
					if ( ! drawer.open ) {
						drawerBody.append( form );
					}
				}, { capture: true } );
				drawer.addEventListener( 'close', () => {
					aside.append( form );
					if ( show ) {
						setButton( show, 'done', show.dataset.shown );
					}
				} );
			}
			const inDrawer = () => drawer && drawer.open && drawer.contains( form );

			// A change: applied at once on a desktop; in the drawer, the server is asked for the count.
			let waiting = 0;
			const recount = async () => {
				const ticket = ++waiting;
				setButton( show, 'waiting', root.dataset.updating );
				try {
					const answer = await ask( addressOf( root, form ) );
					if ( ticket === waiting ) {
						setButton( show, 'done', answer.label );
					}
				} catch ( e ) {
					if ( 'AbortError' !== e.name && ticket === waiting ) {
						setButton( show, 'done', show.dataset.shown );
					}
				}
			};
			form.addEventListener( 'change', ( event ) => {
				if ( event.target.matches( '.mizzey-filter__find, .mizzey-price__type' ) ) {
					return;
				}
				if ( inDrawer() ) {
					recount();
				} else if ( desktop() ) {
					go( root, addressOf( root, form ) );
				}
			} );
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				if ( show && 'true' === show.getAttribute( 'aria-busy' ) ) {
					return;
				}
				if ( drawer && drawer.open ) {
					drawer.close();
				}
				go( root, addressOf( root, form ) );
			} );

			// Brands: search and show more.
			form.querySelectorAll( '.mizzey-filter' ).forEach( ( filter ) => {
				const find = filter.querySelector( '.mizzey-filter__find' );
				const more = filter.querySelector( '.mizzey-filter__more' );
				const options = [ ...filter.querySelectorAll( '.mizzey-filter__option' ) ];
				if ( more ) {
					filter.classList.add( 'is-collapsed' );
					more.addEventListener( 'click', () => {
						const open = filter.classList.toggle( 'is-collapsed' );
						more.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
						more.textContent = open ? more.dataset.more : more.dataset.fewer;
					} );
				}
				if ( find ) {
					find.addEventListener( 'input', () => {
						const words = find.value.trim().toLowerCase();
						filter.classList.toggle( 'is-searching', '' !== words );
						options.forEach( ( option ) => {
							option.hidden = '' !== words && ! option.dataset.name.includes( words );
						} );
					} );
					find.addEventListener( 'keydown', ( event ) => {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
						}
					} );
				}
			} );

			// The price slider.
			const priceEl = form.querySelector( '.mizzey-price' );
			if ( priceEl ) {
				const p = priceParts( priceEl );
				const count = priceEl.querySelector( '.mizzey-price__count' );
				const found = priceEl.querySelector( '.mizzey-price__found' );
				let timer = 0;
				const liveCount = () => {
					if ( ! desktop() || inDrawer() || ! count ) {
						return;
					}
					clearTimeout( timer );
					count.classList.add( 'is-waiting' );
					timer = setTimeout( async () => {
						try {
							const answer = await ask( addressOf( root, form ) );
							found.textContent = answer.products;
							count.classList.remove( 'is-waiting' );
						} catch ( e ) {}
					}, 250 );
				};
				[ p.min, p.max ].forEach( ( input ) => {
					input.addEventListener( 'input', () => {
						paint( p, input );
						p.bubble.hidden = false;
						liveCount();
					} );
					input.addEventListener( 'pointerdown', () => {
						input.classList.add( 'is-moving' );
						paint( p, input );
						p.bubble.hidden = false;
					} );
					const settle = () => {
						input.classList.remove( 'is-moving' );
						p.bubble.hidden = true;
					};
					input.addEventListener( 'pointerup', settle );
					input.addEventListener( 'blur', settle );
					input.addEventListener( 'keydown', ( event ) => {
						if ( 'PageUp' !== event.key && 'PageDown' !== event.key ) {
							return;
						}
						event.preventDefault();
						const next = Number( input.value ) + ( 'PageUp' === event.key ? 10 : -10 ) * p.step;
						input.value = String( Math.max( p.low, Math.min( p.high, next ) ) );
						input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
						input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					} );
				} );
				p.range.addEventListener( 'click', () => {
					const open = p.fields.hidden;
					p.fields.hidden = ! open;
					p.range.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
					if ( open ) {
						p.typed[ 0 ].focus();
					}
				} );
				p.typed.forEach( ( input ) => {
					const apply = () => {
						const target = 'min' === input.dataset.end ? p.min : p.max;
						const value = Math.max( p.low, Math.min( p.high, Math.round( Number( input.value ) || 0 ) ) );
						target.value = String( value );
						paint( p, null );
					};
					input.addEventListener( 'change', apply );
					input.addEventListener( 'keydown', ( event ) => {
						if ( 'Enter' !== event.key ) {
							return;
						}
						event.preventDefault();
						p.typed.forEach( ( field ) => {
							( 'min' === field.dataset.end ? p.min : p.max ).value = String( Math.max( p.low, Math.min( p.high, Math.round( Number( field.value ) || 0 ) ) ) );
						} );
						paint( p, null );
						p.max.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					} );
				} );
				paint( p, null );
			}
		},
	},
} );

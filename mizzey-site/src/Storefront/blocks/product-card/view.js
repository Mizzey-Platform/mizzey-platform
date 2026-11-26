/**
 * The product card's behaviour (spec 066, slice 4), store "mizzey/card". Loaded with the page: quick add (PLP-04)
 * through the Store API (the loader from 400 ms for at least 500; nothing claims success before the answer), the
 * number in the cart on each card (one read of the cart; every `mizzey-cart-changed` that carries one), the price's
 * step down when it would come closer than 8 to the "+", the heart (PLP-05), the rail's arrows' state, and the reset
 * when the browser shows the page again from its back-forward cache. The flight, the toast, the options sheet and the
 * rail's scrolling are in card-extras.js, loaded on first use (D-21 byte budgets). Every word shown comes from the
 * server, translated (NFR-04a). No value here is a style.
 */
import { store, getContext, getElement, withSyncEvent, withScope } from '@wordpress/interactivity';

/* card-extras.js, on first use; a press starting on the "+" fetches it, so the first flight starts on time. */
let X = null;
let loading = null;
function extras() {
	if ( X ) {
		return Promise.resolve( X );
	}
	if ( ! loading ) {
		loading = import( state.extras ).then( ( m ) => ( X = m ) );
	}
	return loading;
}



/* The last input: a sheet opened by a touch shows no focus ring; from the keyboard it focuses its option. */
let lastInput = 'pointer';
document.addEventListener( 'pointerdown', () => ( lastInput = 'pointer' ), true );
document.addEventListener( 'keydown', () => ( lastInput = 'key' ), true );

/** A motion token's time in ms ("600ms" or "0.6s"). */
function ms( name, fallback = 0 ) {
	const raw = getComputedStyle( document.documentElement ).getPropertyValue( name ).trim();
	const value = raw.endsWith( 'ms' ) ? parseFloat( raw ) : parseFloat( raw ) * 1000;
	return Number.isFinite( value ) ? value : fallback;
}




/** The header's cart control that is on screen (the phone and the desktop header each have one). */
function headerCart() {
	return [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length > 0 ) || null;
}


const cards = new Set();
let reading = null;
/* A card's quantity that arrived while it was mid-add (shown after its hold), and the timer of that hold. */
const pending = new WeakMap();
const holding = new WeakMap();

/** One read of the cart for the page; none when the header's count says the cart is empty. */
/** The Store API's cart, told to the page: the header's count takes the same number (one truth). */
function announce( cart ) {
	if ( cart ) {
		document.dispatchEvent( new CustomEvent( 'mizzey-cart-changed', { detail: { count: Number( cart.items_count ), cart } } ) );
	}
	return cart;
}

function readCart() {
	if ( ! reading ) {
		const cart = headerCart() || document.querySelector( '.mizzey-header__cart' );
		const badge = cart && cart.querySelector( '.mizzey-count' );
		reading = badge && badge.hidden
			? Promise.resolve( null )
			: fetch( state.cartRead, { credentials: 'same-origin', headers: { Nonce: state.storeNonce } } )
				.then( ( r ) => ( r.ok ? r.json() : null ) )
				.then( announce )
				.catch( () => null );
	}
	return reading;
}

/** The quantity of a card's product (all its variations together) in a Store API cart. */
function quantityIn( cart, ids ) {
	const want = new Set( ( ids || [] ).map( Number ) );
	return ( cart && Array.isArray( cart.items ) ? cart.items : [] )
		.filter( ( item ) => want.has( Number( item.id ) ) )
		.reduce( ( sum, item ) => sum + ( Number( item.quantity ) || 0 ), 0 );
}

/** Line 2's words for a quantity, in the plural form the page's language needs. */
function inCartWords( qty ) {
	const labels = state.words.inCart || {};
	let category = 'other';
	try {
		category = new Intl.PluralRules( document.documentElement.lang || 'en' ).select( qty );
	} catch ( e ) {
		// The default form stands.
	}
	return ( labels[ category ] || labels.other || '%d' ).replace( '%d', String( qty ) );
}

/** Show a quantity on a card: the bubble, line 2 and the button's words. */
function showQty( context, qty ) {
	context.qty = qty;
	context.qtyText = qty > 99 ? state.words.over : String( qty );
	context.qtyLine = qty > 0 ? inCartWords( qty ) : '';
	if ( ! context.busy && ! context.added ) {
		context.addLabel = qty > 0 ? context.labels.added : context.labels.add;
	}
}

/**
 * Back to a page the browser kept (pageshow, persisted): a card left busy, mid-add or holding "Added" is put back to
 * the truth, and the cart is read once again for every card and the header's count.
 */
window.addEventListener( 'pageshow', ( event ) => {
	if ( ! event.persisted ) {
		return;
	}
	generation++;
	cards.forEach( ( context ) => {
		clearTimeout( holding.get( context ) );
		holding.delete( context );
		pending.delete( context );
		context.busy = false;
		context.loading = false;
		context.added = false;
		context.addLabel = context.qty > 0 ? context.labels.added : context.labels.add;
	} );
	document.querySelectorAll( '.mizzey-card-flight' ).forEach( ( n ) => n.remove() );
	reading = fetch( state.cartRead, { credentials: 'same-origin', cache: 'no-store', headers: { Nonce: state.storeNonce } } )
		.then( ( r ) => ( r.ok ? r.json() : null ) )
		.catch( () => null );
	reading.then( announce );
} );

/** A cart change made anywhere on the page: every card shows its new number, a card mid-add after its hold. */
document.addEventListener( 'mizzey-cart-changed', ( event ) => {
	const cart = event.detail && event.detail.cart;
	if ( ! cart || ! Array.isArray( cart.items ) ) {
		return;
	}
	cards.forEach( ( context ) => {
		const qty = quantityIn( cart, context.cartIds );
		if ( context.busy || context.added ) {
			pending.set( context, qty );
		} else {
			showQty( context, qty );
		}
	} );
} );

/**
 * C1-Components 06: at least 8 between the price and the "+"; a price that does not fit drops one step on the scale
 * (16 to 13), never wrapping or truncating. The room is the foot less its start padding, its gap (8) and the
 * button's own inline size within it; the price is measured at its full size.
 */
function fit( card ) {
	const foot = card.querySelector( '.mizzey-card__foot' );
	const add = card.querySelector( '.mizzey-card__action' );
	const price = card.querySelector( '.mizzey-card__price' );
	if ( ! foot || ! add || ! price ) {
		return;
	}
	const fs = getComputedStyle( foot );
	const as = getComputedStyle( add );
	const room = foot.clientWidth - parseFloat( fs.paddingInlineStart ) - ( parseFloat( fs.columnGap ) || 0 ) - add.offsetWidth - parseFloat( as.marginInlineEnd );
	card.classList.remove( 'is-price-step' );
	// The words' own width: a grid item's box can be wider than its text.
	const range = document.createRange();
	range.selectNodeContents( price );
	const wide = range.getBoundingClientRect().width > room + 0.5;
	card.classList.toggle( 'is-price-step', wide );
}

let fitting = null;
function watchFit( card ) {
	if ( ! window.ResizeObserver ) {
		return fit( card );
	}
	if ( ! fitting ) {
		fitting = new ResizeObserver( ( entries ) => entries.forEach( ( entry ) => fit( entry.target ) ) );
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( () => document.querySelectorAll( '.mizzey-card' ).forEach( fit ) );
		}
	}
	fitting.observe( card );
	fit( card );
}

const { state } = store( 'mizzey/card', {
	actions: {
		*add() {
			const context = getContext();
			if ( context.busy ) {
				return;
			}
			if ( context.variable ) {
				const { ref } = getElement();
				const dialog = document.getElementById( context.pickerId );
				if ( dialog && ! dialog.open ) {
					const M = yield extras();
					M.openSheet( dialog, ref, ref.closest( '.mizzey-card' ), lastInput === 'key' );
				}
				return;
			}
			yield* send( context, { id: context.id, quantity: 1 }, getElement().ref.closest( '.mizzey-card' ) );
		},
		*addChosen() {
			const context = getContext();
			const picker = context.picker;
			const variation = picker.variations.find( ( v ) => v.in && matches( v, picker.chosen ) );
			if ( ! variation || context.busy ) {
				return;
			}
			const { ref } = getElement();
			const dialog = ref.closest( 'dialog' );
			const attributes = picker.attributes.map( ( a ) => ( { attribute: a.name, value: picker.chosen[ a.key ] } ) );
			const card = ref.closest( '.mizzey-card' );
			// The sheet stays until the server answers; then it closes as overlays do, and only then does the product fly,
			// from the card's "+". A refusal keeps it open, saying why.
			yield* send( context, { id: variation.id, quantity: picker.qty, variation: attributes }, card, { sheet: dialog, origin: card && card.querySelector( '.mizzey-card__disc' ) } );
		},
		/* The options sheet's choices and quantity (card-extras.js, loaded when the sheet opened). */
		choose: withSyncEvent( ( event ) => X && X.chooseOption( event, getContext() ) ),
		step: withSyncEvent( ( event ) => X && X.stepQty( event, getContext() ) ),
		*wish() {
			const context = getContext();
			const was = context.wished;
			context.wished = ! was;
			if ( ! was ) {
				const { ref } = getElement();
				extras().then( ( M ) => M.popHeart( ref ) );
			}
			context.wishLabel = context.wished ? context.labels.wished : context.labels.wish;
			const url = was ? `${ state.wishlistApi }/${ context.id }` : state.wishlistApi;
			try {
				const response = yield fetch( url, {
					method: was ? 'DELETE' : 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.restNonce },
					body: was ? undefined : JSON.stringify( { id: context.id } ),
				} );
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}
			} catch ( e ) {
				context.wished = was;
				context.wishLabel = was ? context.labels.wished : context.labels.wish;
				extras().then( ( X ) => X.toast( 'error', state.words.wishFailed ) );
			}
		},
		scroll: withSyncEvent( ( event ) => {
			const button = event.currentTarget;
			extras().then( ( X ) => X.scrollRail( button ) );
		} ),
		/** A press starting on the "+" fetches the motion module, so the first flight starts on time. */
		warm() {
			extras();
		},
	},
	callbacks: {
		/** A card joins the page's one read of the cart, and keeps its price clear of the "+". */
		quantity() {
			const context = getContext();
			const { ref } = getElement();
			cards.add( context );
			watchFit( ref );
			readCart().then( withScope( ( cart ) => {
				if ( cart && ! context.busy && ! context.added ) {
					showQty( context, quantityIn( cart, context.cartIds ) );
				}
			} ) );
		},
		image() {
			const context = getContext();
			const { ref } = getElement();
			const img = ref.querySelector( 'img' );
			if ( ! img || img.complete ) {
				context.loaded = true;
				return;
			}
			const done = () => ( context.loaded = true );
			img.addEventListener( 'load', done, { once: true } );
			img.addEventListener( 'error', done, { once: true } );
		},
		rail() {
			const { ref } = getElement();
			const list = ref.querySelector( '.mizzey-products__list' );
			const arrows = [ ...ref.querySelectorAll( '.mizzey-products__arrow' ) ];
			if ( ! list || arrows.length !== 2 ) {
				return;
			}
			const update = () => {
				const max = list.scrollWidth - list.clientWidth;
				const at = Math.abs( list.scrollLeft );
				arrows[ 0 ].disabled = at <= 1;
				arrows[ 1 ].disabled = at >= max - 1;
			};
			list.addEventListener( 'scroll', update, { passive: true } );
			window.addEventListener( 'resize', update );
			update();
		},
	},
} );

/** The hold after an add ends: the card is the "+" again, with the number the cart now holds. */
function rest( context ) {
	clearTimeout( holding.get( context ) );
	holding.delete( context );
	context.added = false;
	showQty( context, pending.has( context ) ? pending.get( context ) : context.qty );
	pending.delete( context );
}


/* Bumped on a back-forward restore: an add left waiting then changes nothing when it answers. */
let generation = 0;

/** Post one add to the Store API; true when the server took it. Options: the sheet it came from and the element the
    product flies from. */
function* send( context, body, card, { sheet = null, origin = null } = {} ) {
	const mine = generation;
	if ( context.added ) {
		// A press while "Added" holds: the hold ends here and this add starts its own.
		rest( context );
	}
	context.busy = true;
	context.addLabel = context.labels.adding;
	let shown = 0;
	// The times of C1-Motion-Playground's add to cart ("'Adding' from 400 ms for at least 500; 'Added' holds 1500 ms"),
	// from the theme's tokens.
	const loader = setTimeout( withScope( () => {
		context.loading = true;
		shown = performance.now();
	} ), ms( '--mizzey-motion-delay-busy', 400 ) );
	// Once "Adding" shows, it stays at least its time, whatever the answer.
	const settle = function* () {
		clearTimeout( loader );
		const left = shown ? ms( '--mizzey-motion-hold-busy', 500 ) - ( performance.now() - shown ) : 0;
		if ( left > 0 ) {
			yield new Promise( ( resolve ) => setTimeout( resolve, left ) );
		}
		context.loading = false;
		context.busy = false;
	};
	const said = sheet && sheet.querySelector( '.mizzey-card-picker__error' );
	if ( said ) {
		said.hidden = true;
	}
	const refused = function* ( words ) {
		yield* settle();
		context.addLabel = context.qty > 0 ? context.labels.added : context.labels.add;
		if ( said && sheet.open ) {
			// The sheet stays open and says why, in its own words (the page's notices sit under the modal sheet).
			said.textContent = words;
			said.hidden = false;
		} else {
			extras().then( ( X ) => X.toast( 'error', words ) );
		}
		return false;
	};
	try {
		const response = yield fetch( state.cartApi, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Nonce: state.storeNonce },
			body: JSON.stringify( body ),
		} );
		const nonce = response.headers.get( 'Nonce' );
		if ( nonce ) {
			state.storeNonce = nonce;
		}
		const answer = yield response.json().catch( () => ( {} ) );
		if ( mine !== generation ) {
			return false;
		}
		if ( ! response.ok ) {
			const R = yield extras();
			return yield* refused( R.message( answer, state.words.failed ) );
		}
		yield* settle();
		const X = yield extras();
		// From the options sheet: it closes first, the card is in view again, and the product flies from its "+".
		if ( sheet ) {
			yield X.closeSheet( sheet );
		}
		// Only now: the flight, the tick and "Added"; the count as the product lands; the number after the hold.
		context.added = true;
		context.addLabel = context.labels.added;
		pending.set( context, quantityIn( answer, context.cartIds ) );
		const landing = X.fly( card, origin );
		const count = Number( answer.items_count );
		document.dispatchEvent( new CustomEvent( 'mizzey-cart-changed', { detail: { count, cart: answer, landing } } ) );
		document.body.dispatchEvent( new CustomEvent( 'wc-blocks_added_to_cart', { bubbles: true } ) );
		X.toast( 'added' );
		holding.set( context, setTimeout( withScope( () => rest( context ) ), ms( '--mizzey-motion-hold-added', 1500 ) ) );
		return true;
	} catch ( e ) {
		if ( mine !== generation ) {
			return false;
		}
		return yield* refused( state.words.failed );
	}
}

/** Whether a variation fits every option chosen (an empty value on the variation means any). */
function matches( variation, chosen ) {
	return Object.entries( chosen ).every( ( [ key, value ] ) => {
		const has = variation.attrs[ key ] || '';
		return has === '' || has === value;
	} );
}




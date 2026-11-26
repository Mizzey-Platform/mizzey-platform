/**
 * The mini cart's behaviour (spec 066, slice 12; CART-12), on the WordPress Interactivity API and WooCommerce's Store
 * API, the cart the cart page reads.
 *
 * - The header's cart control opens it, through header.js, which imports this module on its first press (a modal dialog: showModal() moves focus in, keeps the page behind inert,
 *   closes on Escape and returns focus; the scrim closes it through the base store mizzey/ui) and asks the server for
 *   the cart; the loading rows show until the answer.
 * - A quantity change or a removal is sent to the Store API; the line shows the i-dot loader and every control waits.
 *   Nothing on screen changes until the server answers: then the lines, the subtotal and the free-delivery line are
 *   the server's, and the header's count is told the server's count ("mizzey-cart-changed", as the product card does).
 * - Back from the back-forward cache, nothing stays busy and an open mini cart reads the cart again.
 * - Money is written as C1-Mini-Cart writes it ("EGP 5,200", "5,200 ج.م"): the Store API's amount in the store's
 *   currency symbol and separators, a space between symbol and number, and no decimals when they are zero.
 * - The free-delivery line reads what specs/018 puts on the cart (extensions.mizzey.free_shipping), in the board's two
 *   forms: an amount still needed, or reached. The board draws no other form, so the line shows nothing else.
 */
import { store, getContext } from '@wordpress/interactivity';

let nonce = '';

const decode = ( text ) => {
	const area = document.createElement( 'textarea' );
	area.innerHTML = String( text || '' );
	return area.value;
};

/** Minor units as the board writes money: symbol, a space, grouped digits, decimals only when not zero; in parts. */
function money( value, c ) {
	const unit = Number( c.currency_minor_unit ) || 0;
	const fixed = ( Number( value ) / Math.pow( 10, unit ) ).toFixed( unit );
	const [ whole, part ] = fixed.split( '.' );
	const grouped = whole.replace( /\B(?=(\d{3})+(?!\d))/g, c.currency_thousand_separator );
	const number = grouped + ( part && /[1-9]/.test( part ) ? c.currency_decimal_separator + part : '' );
	const prefix = String( c.currency_prefix || '' ).trim();
	const suffix = String( c.currency_suffix || '' ).trim();
	// In three parts, so the digits are set in Inter and an Arabic symbol in Tajawal, as the board sets them.
	// The space between the digits and a symbol after them is set with the digits, in Inter, as the board sets it:
	// before them in a right-to-left page (where the symbol stands to their left), after them otherwise.
	const rtl = document.documentElement.dir === 'rtl';
	// An ordinary space, kept by the digits' white-space: pre (at the start of the price's line it would collapse).
	const num = suffix ? ( rtl ? ' ' + number : number + ' ' ) : number;
	return { pre: prefix ? prefix + ' ' : '', num, suf: suffix };
}

function plural( forms, n ) {
	let category = 'other';
	try {
		category = new Intl.PluralRules( document.documentElement.lang || 'en' ).select( n );
	} catch ( e ) {
		// The default form stands.
	}
	return ( forms[ category ] || forms.other ).replace( '%d', String( n ) );
}

const { state } = store( 'mizzey/mini-cart', {
	state: {
		fill: 0,
		deliveryShown: false,
		deliveryReached: false,
		deliveryBefore: '',
		deliveryAmount: { pre: '', num: '', suf: '' },
		deliveryAfter: '',
		deliveryLabel: '',
		get loading() {
			return state.status === 'loading';
		},
		get hasLines() {
			return state.status === 'ready' && state.lines.length > 0;
		},
		get empty() {
			return state.status === 'ready' && state.lines.length === 0 && ! state.error;
		},
		get locked() {
			return state.busyKey !== '';
		},
		get lockedAttr() {
			return state.locked ? 'true' : null;
		},
		get busy() {
			return state.loading || state.locked ? 'true' : null;
		},
		get footOff() {
			return state.hasLines ? null : 'true';
		},
		get footTab() {
			return state.hasLines ? null : '-1';
		},
		get lineBusy() {
			return getContext().line.key === state.busyKey;
		},
		get lessOff() {
			const { line } = getContext();
			return ! line.editable || line.quantity <= line.min;
		},
		get moreOff() {
			const { line } = getContext();
			return ! line.editable || ( line.max > 0 && line.quantity + line.step > line.max );
		},
	},
	actions: {
		less() {
			const { line } = getContext();
			send( 'cart/update-item', { key: line.key, quantity: line.quantity - line.step }, line.key );
		},
		more() {
			const { line } = getContext();
			send( 'cart/update-item', { key: line.key, quantity: line.quantity + line.step }, line.key );
		},
		remove() {
			const { line } = getContext();
			send( 'cart/remove-item', { key: line.key }, line.key ).then( () => {
				const title = document.getElementById( 'mizzey-mini-cart-title' );
				if ( title && ! document.activeElement.closest( '#mizzey-mini-cart .mizzey-minicart__line' ) ) {
					title.focus();
				}
			} );
		},
	},
} );

/**
 * Open the mini cart and ask the server for the cart. header.js imports this module on the first press of the cart
 * control (it is named in the import map, not loaded with the page) and calls this.
 */
export function openMiniCart() {
	const dialog = document.getElementById( 'mizzey-mini-cart' );
	if ( ! dialog ) {
		return false;
	}
	if ( ! dialog.open ) {
		dialog.showModal();
	}
	load();
	return true;
}

/** Show the server's cart. */
function apply( cart ) {
	const words = state.words;
	state.error = '';
	const items = Array.isArray( cart.items ) ? cart.items : [];
	state.lines = items.map( ( item ) => {
		const limits = item.quantity_limits || {};
		const name = decode( item.name );
		return {
			key: item.key,
			name,
			url: item.permalink || '',
			// The photo whole (any shape, as the board shows it contained): the full image and its sizes, not the
			// square-cropped thumbnail.
			image: item.images && item.images[ 0 ] ? item.images[ 0 ].src || item.images[ 0 ].thumbnail : '',
			srcset: item.images && item.images[ 0 ] ? item.images[ 0 ].srcset || '' : '',
			option: ( item.variation || [] ).map( ( v ) => decode( v.value ) ).join( words.listSep ),
			price: money( item.prices.price, item.prices ),
			quantity: Number( item.quantity ),
			min: Number( limits.minimum ) || 1,
			max: Number( limits.maximum ) || 0,
			step: Number( limits.multiple_of ) || 1,
			editable: limits.editable !== false && ! item.sold_individually,
			removeLabel: words.remove.replace( '%s', name ),
		};
	} );
	const t = cart.totals || {};
	const shown = Number( t.total_items || 0 ) + ( state.incTax ? Number( t.total_items_tax || 0 ) : 0 );
	state.subtotal = money( shown, t );
	state.count = plural( words.items, Number( cart.items_count ) || 0 );

	// Free delivery: what specs/018 says the cart still needs (CART-07), nothing when it says nothing.
	const rule = ( cart.extensions && cart.extensions.mizzey && cart.extensions.mizzey.free_shipping ) || {};
	const unit = Number( t.currency_minor_unit ) || 0;
	let fill = -1;
	state.deliveryReached = false;
	state.deliveryBefore = '';
	state.deliveryAmount = { pre: '', num: '', suf: '' };
	state.deliveryAfter = '';
	if ( items.length && rule.free ) {
		fill = 1;
		state.deliveryReached = true;
		state.deliveryBefore = words.reached;
		state.deliveryLabel = words.reachedAlt;
	} else if ( items.length && rule.amount_needed !== null && rule.amount_needed !== undefined ) {
		const needed = Math.round( Number( rule.amount_needed ) * Math.pow( 10, unit ) );
		// Measured as WooCommerce's free shipping method measures it: the shown subtotal less the discount.
		const reached = Math.max( 0, shown - Number( t.total_discount || 0 ) - ( state.incTax ? Number( t.total_discount_tax || 0 ) : 0 ) );
		fill = reached / ( reached + needed );
		const [ before, after ] = words.amount.split( '%s' );
		state.deliveryBefore = before || '';
		state.deliveryAmount = money( needed, t );
		state.deliveryAfter = after || '';
	}
	state.deliveryShown = fill >= 0;
	state.fill = Math.max( 0, Math.min( 1, fill ) );
	// The track's filled part and the van on its end follow the server's figure; CSS animates the change.
	const delivery = document.querySelector( '#mizzey-mini-cart .mizzey-minicart__delivery' );
	if ( delivery ) {
		delivery.style.setProperty( '--mizzey-minicart-fill', String( state.fill ) );
	}
	if ( ! state.deliveryReached ) {
		state.deliveryLabel = words.percent.replace( '%d', String( Math.round( state.fill * 100 ) ) );
	}
}

/** The header's count follows the server's count (store mizzey/header listens, as for the product card). */
function tell( cart ) {
	document.dispatchEvent( new CustomEvent( 'mizzey-cart-changed', { detail: { count: Number( cart.items_count ) || 0 } } ) );
}

async function ask( path, options = {} ) {
	const headers = { 'Content-Type': 'application/json' };
	if ( nonce ) {
		headers.Nonce = nonce;
	}
	const response = await fetch( state.rest + path, { credentials: 'same-origin', cache: 'no-store', ...options, headers } );
	nonce = response.headers.get( 'Nonce' ) || nonce;
	const body = await response.json().catch( () => null );
	if ( ! response.ok || ! body ) {
		throw new Error( ( body && body.message ) || state.words.error );
	}
	return body;
}

let loadingNow = null;
function load() {
	state.status = 'loading';
	state.error = '';
	const mine = ( loadingNow = ask( 'cart' ) );
	return mine
		.then( ( cart ) => {
			if ( mine === loadingNow ) {
				apply( cart );
				// The header's count is told the server's count too: an add made elsewhere may not have reached it.
				tell( cart );
			}
		} )
		.catch( () => {
			// Only the newest load speaks: a replaced one that fails late leaves the newer answer alone.
			if ( mine === loadingNow ) {
				state.lines = [];
				state.error = state.words.error;
			}
		} )
		.finally( () => {
			if ( mine === loadingNow ) {
				state.status = 'ready';
			}
		} );
}

async function send( path, body, key ) {
	if ( state.busyKey ) {
		return;
	}
	state.busyKey = key;
	state.error = '';
	try {
		const cart = await ask( path, { method: 'POST', body: JSON.stringify( body ) } );
		apply( cart );
		tell( cart );
	} catch ( error ) {
		state.error = decode( error.message ) || state.words.error;
	} finally {
		state.busyKey = '';
	}
}

/*
 * Back to this page from the back-forward cache (the browser's Back, iOS Safari included): a request that was with
 * the server when the shopper left will not answer into this page, so nothing may stay busy; and the cart may have
 * changed elsewhere. Every busy state is cleared, and an open mini cart reads the cart again, once. (The header's own
 * count is asked again by store mizzey/header on the same event.)
 */
window.addEventListener( 'pageshow', ( event ) => {
	if ( ! event.persisted ) {
		return;
	}
	state.busyKey = '';
	loadingNow = null;
	const dialog = document.getElementById( 'mizzey-mini-cart' );
	if ( dialog && dialog.open ) {
		load();
	} else if ( state.status === 'loading' ) {
		state.status = 'idle';
	}
} );

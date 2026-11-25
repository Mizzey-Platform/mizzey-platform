/**
 * The product card's behaviour (spec 066, slice 4), on the WordPress Interactivity API: store "mizzey/card".
 *
 * - Quick add (PLP-04) posts to WooCommerce's Store API (`cart/add-item`), which asks the stock source (specs/062).
 *   While it waits the "+" shows the i-dot loader; only the server's answer turns it into the tick with "Added" on
 *   line 2, and only then is the cart's new count announced, as the `mizzey-cart-changed` event on the document
 *   (detail.count, the server's items_count) and WooCommerce's own `wc-blocks_added_to_cart`. A refusal brings the "+"
 *   back with a notice saying why.
 * - A product with options opens its options: a bottom sheet on a phone, a popover by the card from 1024 wide. Focus
 *   moves in, stays in (the slice 1 overlay's trap), and returns to the "+" on close.
 * - The heart (PLP-05) changes at once, as the motion rule allows for it, and is rolled back if the server refuses.
 * - The rail's arrows scroll it by what is visible, and are disabled at either end.
 *
 * Every word shown comes from the server, translated (NFR-04a). No value here is a style.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const DESKTOP = '(min-width: 1024px)';
const openers = new WeakMap();

function toast( kind, text ) {
	const region = document.querySelector( '.mizzey-card-toasts' );
	const template = document.getElementById( `mizzey-card-toast-${ kind }` );
	if ( ! region || ! template ) {
		return;
	}
	region.querySelectorAll( '.mizzey-notice' ).forEach( ( n ) => n.remove() );
	const notice = template.content.firstElementChild.cloneNode( true );
	if ( text ) {
		notice.querySelector( '.mizzey-notice__text' ).textContent = text;
	}
	const close = notice.querySelector( '.mizzey-notice__close' );
	if ( close ) {
		close.addEventListener( 'click', () => notice.remove() );
	}
	region.appendChild( notice );
	// The life of a notice is the token's (5000ms); its unit is read with it.
	const raw = getComputedStyle( document.documentElement ).getPropertyValue( '--mizzey-motion-life-toast' ).trim();
	const life = ( raw.endsWith( 'ms' ) ? parseFloat( raw ) : parseFloat( raw ) * 1000 ) || 5000;
	let timer = setTimeout( () => notice.remove(), life );
	const pause = () => clearTimeout( timer );
	const resume = () => ( timer = setTimeout( () => notice.remove(), life ) );
	notice.addEventListener( 'mouseenter', pause );
	notice.addEventListener( 'focusin', pause );
	notice.addEventListener( 'mouseleave', resume );
}

function message( body, fallback ) {
	const text = body && typeof body.message === 'string' ? body.message : '';
	const plain = text ? new DOMParser().parseFromString( text, 'text/html' ).body.textContent.trim() : '';
	return plain || fallback;
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
					openers.set( dialog, ref );
					const card = ref.closest( '.mizzey-card' );
					mode( dialog );
					dialog.showModal();
					place( dialog, card );
					const first = dialog.querySelector( '[role="radio"][aria-checked="true"]' ) || dialog.querySelector( 'button' );
					if ( first ) {
						first.focus();
					}
					dialog.addEventListener( 'close', () => {
						const opener = openers.get( dialog );
						openers.delete( dialog );
						if ( opener && opener.isConnected ) {
							opener.focus();
						}
					}, { once: true } );
				}
				return;
			}
			yield* send( context, { id: context.id, quantity: 1 } );
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
			const done = yield* send( context, { id: variation.id, quantity: picker.qty, variation: attributes } );
			if ( done && dialog && dialog.open ) {
				dialog.close();
			}
		},
		choose: withSyncEvent( ( event ) => {
			const context = getContext();
			const button = event.currentTarget;
			if ( button.getAttribute( 'aria-disabled' ) === 'true' ) {
				event.preventDefault();
				return;
			}
			context.picker.chosen = { ...context.picker.chosen, [ button.dataset.key ]: button.dataset.value };
			refresh( button.closest( 'dialog' ), context.picker );
		} ),
		step: withSyncEvent( ( event ) => {
			const context = getContext();
			const next = Math.max( 1, context.picker.qty + Number( event.currentTarget.dataset.step ) );
			context.picker.qty = next;
			event.currentTarget.closest( '.mizzey-stepper' ).querySelector( '.mizzey-stepper__value' ).textContent = String( next );
		} ),
		closePicker() {
			const dialog = getElement().ref.closest( 'dialog' );
			if ( dialog && dialog.open ) {
				dialog.close();
			}
		},
		*wish() {
			const context = getContext();
			const was = context.wished;
			context.wished = ! was;
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
				toast( 'error', state.words.wishFailed );
			}
		},
		scroll: withSyncEvent( ( event ) => {
			const button = event.currentTarget;
			const list = document.getElementById( button.getAttribute( 'aria-controls' ) );
			if ( ! list ) {
				return;
			}
			// By whole cards, in the reading direction: the first card past the visible ones (or a page back) is
			// brought to the start edge, so Arabic scrolls the mirrored way with no sum of its own.
			const rtl = getComputedStyle( list ).direction === 'rtl';
			const box = list.getBoundingClientRect();
			const along = ( el ) => {
				const r = el.getBoundingClientRect();
				return rtl ? box.x + box.width - ( r.x + r.width ) : r.x - box.x;
			};
			const items = [ ...list.children ];
			const page = list.clientWidth;
			const target = Number( button.dataset.step ) > 0
				? items.find( ( el ) => along( el ) >= page - 1 )
				: [ ...items ].reverse().find( ( el ) => along( el ) <= -page + 1 ) || items[ 0 ];
			const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			if ( target ) {
				target.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'nearest', inline: 'start' } );
			}
		} ),
	},
	callbacks: {
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

/** Post one add to the Store API; true when the server took it. */
function* send( context, body ) {
	context.busy = true;
	context.addLabel = context.labels.adding;
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
		if ( ! response.ok ) {
			context.busy = false;
			context.addLabel = context.added ? context.labels.added : context.labels.add;
			toast( 'error', message( answer, state.words.failed ) );
			return false;
		}
		context.busy = false;
		context.added = true;
		context.addLabel = context.labels.added;
		const count = Number( answer.items_count );
		document.dispatchEvent( new CustomEvent( 'mizzey-cart-changed', { detail: { count, cart: answer } } ) );
		document.body.dispatchEvent( new CustomEvent( 'wc-blocks_added_to_cart', { bubbles: true } ) );
		toast( 'added' );
		return true;
	} catch ( e ) {
		context.busy = false;
		context.addLabel = context.added ? context.labels.added : context.labels.add;
		toast( 'error', state.words.failed );
		return false;
	}
}

function matches( variation, chosen, only ) {
	return Object.entries( chosen ).every( ( [ key, value ] ) => {
		if ( only && key !== only ) {
			return true;
		}
		const has = variation.attrs[ key ] || '';
		return has === '' || has === value;
	} );
}

/** The options' marks after a choice: checked, sold out, the chosen value's name and the price. */
function refresh( dialog, picker ) {
	if ( ! dialog ) {
		return;
	}
	dialog.querySelectorAll( '[role="radio"]' ).forEach( ( button ) => {
		const key = button.dataset.key;
		const value = button.dataset.value;
		const want = { ...picker.chosen, [ key ]: value };
		const available = picker.variations.some( ( v ) => v.in && matches( v, want, key ) );
		button.setAttribute( 'aria-checked', picker.chosen[ key ] === value ? 'true' : 'false' );
		button.setAttribute( 'aria-disabled', available ? 'false' : 'true' );
	} );
	dialog.querySelectorAll( '.mizzey-card-picker__chosen' ).forEach( ( el ) => {
		const attribute = picker.attributes.find( ( a ) => a.key === el.dataset.key );
		const option = attribute && attribute.options.find( ( o ) => o.value === picker.chosen[ attribute.key ] );
		el.textContent = option ? option.label : '';
	} );
	const variation = picker.variations.find( ( v ) => matches( v, picker.chosen ) );
	const price = dialog.querySelector( '.mizzey-card-picker__price' );
	if ( price && variation ) {
		price.textContent = variation.price;
	}
	const add = dialog.querySelector( '.mizzey-card-picker__add' );
	if ( add ) {
		add.disabled = ! picker.variations.some( ( v ) => v.in && matches( v, picker.chosen ) );
	}
}

/** A bottom sheet on a phone; from 1024 wide a popover by the card. */
function mode( dialog ) {
	const desktop = window.matchMedia( DESKTOP ).matches;
	dialog.classList.toggle( 'is-popover', desktop );
	dialog.classList.toggle( 'mizzey-overlay--sheet', ! desktop );
}

/** The popover above the card when there is room, below it otherwise, its start edge on the card's, kept on screen. */
function place( dialog, card ) {
	if ( ! dialog.classList.contains( 'is-popover' ) || ! card ) {
		return;
	}
	const panel = dialog.querySelector( '.mizzey-overlay__panel' );
	// The gap is the panel's own padding (a token), so no size is written here.
	const gap = parseFloat( getComputedStyle( panel ).paddingInlineStart ) || 0;
	const rect = card.getBoundingClientRect();
	const size = panel.getBoundingClientRect();
	const rtl = getComputedStyle( card ).direction === 'rtl';
	const start = rtl ? window.innerWidth - ( rect.x + rect.width ) : rect.x;
	const above = rect.top - size.height - gap;
	const top = above >= gap ? above : Math.min( rect.bottom + gap, window.innerHeight - size.height - gap );
	const inline = Math.max( gap, Math.min( start, window.innerWidth - size.width - gap ) );
	dialog.classList.toggle( 'is-below', above < gap );
	dialog.style.setProperty( '--mizzey-pop-block', `${ Math.max( gap, top ) }px` );
	dialog.style.setProperty( '--mizzey-pop-inline', `${ inline }px` );
}

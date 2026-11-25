/**
 * The product page's gallery and buying area (spec 066, slice 9), on the WordPress Interactivity API: store
 * "mizzey/buy".
 *
 * - The options are the product's own attributes. A choice updates, in place and without moving anything, the
 *   legend, the price, the stock line, the message, Add to Cart, Buy Now's address, the quantity's limit, the buy bar,
 *   and the gallery when the variant has a photo of its own.
 * - Add to Cart posts to WooCommerce's Store API (`cart/add-item`), which asks the stock source (specs/062). While it
 *   waits the button shows the i-dot loader, its width kept; only the server's answer turns it into the tick with
 *   "Added", and only then is the cart's new count announced (`mizzey-cart-changed`, detail.count the server's
 *   items_count). A refusal brings the button back with a notice saying why.
 * - Buy Now is specs/055's address, with the quantity and the variant; it shows the loader while the store adds.
 * - The gallery shows one photo at a time and loads the others when they are shown, over the breathing ZZ; zoom opens
 *   them in a dialog; the video plays only when pressed, muted, with its controls.
 * - On a phone the buy bar comes up once Add to Cart has scrolled away.
 *
 * Every word shown comes from the server, translated (NFR-04a). No value here is a style.
 */
import { store, getElement, withSyncEvent } from '@wordpress/interactivity';

const DESKTOP = '(min-width: 1024px)';
const openers = new WeakMap();
const states = new WeakMap();

/** The product's data, copied once from the server's context: the script changes this copy, never a reactive one. */
function data( root ) {
	if ( ! states.has( root ) ) {
		states.set( root, JSON.parse( root.dataset.wpContext ) );
	}
	return states.get( root );
}
const fill = ( text, ...args ) => args.reduce( ( out, arg, i ) => out.replace( `%${ i + 1 }$d`, arg ).replace( `%${ i + 1 }$s`, arg ), text ).replace( '%d', args[ 0 ] ).replace( '%s', args[ 0 ] );
const q = ( root, sel ) => root.querySelector( sel );
const qa = ( root, sel ) => [ ...root.querySelectorAll( sel ) ];
const html = ( el, value ) => {
	if ( el ) {
		el.innerHTML = value;
	}
};

function rootOf() {
	return getElement().ref.closest( '.mizzey-buy' );
}

function fits( variation, chosen, skipEmpty = false ) {
	return Object.entries( chosen ).every( ( [ key, value ] ) => {
		const has = variation.attrs[ key ] || '';
		return has === '' || has === value || ( skipEmpty && value === '' );
	} );
}

function current( ctx ) {
	const chosen = { ...ctx.chosen };
	if ( ! ctx.variable ) {
		return { done: true, variation: ctx.simple };
	}
	if ( Object.values( chosen ).some( ( v ) => v === '' ) ) {
		return { done: false, variation: null };
	}
	return { done: true, variation: ctx.variations.find( ( v ) => fits( v, chosen ) ) || null };
}

function chosenLabel( ctx ) {
	return ctx.attributes.map( ( a ) => ( a.options.find( ( o ) => o.value === ctx.chosen[ a.key ] ) || {} ).label ).filter( Boolean ).join( state.words.sep );
}

function toast( kind, text, undo ) {
	const region = document.querySelector( '.mizzey-buy-toasts' );
	const template = document.getElementById( `mizzey-buy-toast-${ kind }` );
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
	const action = notice.querySelector( '.mizzey-notice__action' );
	if ( action && undo ) {
		action.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			notice.remove();
			undo();
		} );
	}
	region.appendChild( notice );
	const raw = getComputedStyle( document.documentElement ).getPropertyValue( '--mizzey-motion-life-toast' ).trim();
	const life = ( raw.endsWith( 'ms' ) ? parseFloat( raw ) : parseFloat( raw ) * 1000 ) || 5000;
	let timer = setTimeout( () => notice.remove(), life );
	notice.addEventListener( 'mouseenter', () => clearTimeout( timer ) );
	notice.addEventListener( 'focusin', () => clearTimeout( timer ) );
	notice.addEventListener( 'mouseleave', () => ( timer = setTimeout( () => notice.remove(), life ) ) );
}

function message( body, fallback ) {
	const text = body && typeof body.message === 'string' ? body.message : '';
	return ( text && new DOMParser().parseFromString( text, 'text/html' ).body.textContent.trim() ) || fallback;
}

/** Everything a choice changes, written in place. */
function refresh( root, ctx ) {
	const { done, variation } = current( ctx );
	const words = state.words;
	const out = ! variation || variation.state === 'out';
	// Options: chosen, and sold out with the other choices as they are.
	qa( root, '.mizzey-buy__option' ).forEach( ( button ) => {
		const key = button.dataset.key;
		const want = { ...ctx.chosen, [ key ]: button.dataset.value };
		const available = ctx.variations.some( ( v ) => v.state !== 'out' && fits( v, want, true ) );
		const checked = ctx.chosen[ key ] === button.dataset.value;
		button.setAttribute( 'aria-checked', checked ? 'true' : 'false' );
		button.classList.toggle( 'is-out', ! available );
	} );
	qa( root, '.mizzey-buy__group' ).forEach( ( group ) => {
		const buttons = qa( group, '.mizzey-buy__option' );
		const on = buttons.find( ( b ) => b.getAttribute( 'aria-checked' ) === 'true' ) || buttons[ 0 ];
		buttons.forEach( ( b ) => ( b.tabIndex = b === on ? 0 : -1 ) );
		const key = group.dataset.key;
		const attribute = ctx.attributes.find( ( a ) => a.key === key );
		const option = attribute && attribute.options.find( ( o ) => o.value === ctx.chosen[ key ] );
		q( group, '.mizzey-buy__legend-value' ).textContent = option ? ` ${ option.label }` : '';
		q( group, 'input' ).value = ctx.chosen[ key ] || '';
	} );
	const which = chosenLabel( ctx );
	// Price.
	const lead = variation || ( ctx.variable ? null : ctx.simple );
	// No variant (an option missing, or a combination not made): "From" the lowest price, and no sale of any variant.
	const shown = lead || { price: ctx.from, old: '', save: '', saving: '' };
	html( q( root, '.mizzey-buy__price' ), shown.price );
	html( q( root, '.mizzey-buy__old' ), shown.old );
	q( root, '.mizzey-buy__old' ).hidden = ! shown.old;
	q( root, '.mizzey-buy__save' ).textContent = shown.save;
	q( root, '.mizzey-buy__save' ).hidden = ! shown.save;
	html( q( root, '.mizzey-buy__saving-amount' ), shown.saving );
	q( root, '.mizzey-buy__saving' ).classList.toggle( 'is-blank', ! shown.saving );
	const from = q( root, '.mizzey-buy__from' );
	if ( from ) {
		from.hidden = !! lead;
	}
	// Sold out in every variation: nothing to choose toward; the buttons say so and stay off.
	const allOut = ctx.variable && ctx.variations.length > 0 && ctx.variations.every( ( v ) => v.state === 'out' );
	// Stock and the message.
	const stock = q( root, '.mizzey-buy__stock' );
	const line = q( root, '.mizzey-buy__stock-text' );
	if ( stock && line ) {
		const s = lead ? lead.state : '';
		stock.dataset.state = s;
		line.textContent = ! lead ? '' : { out: words.out, unknown: words.unknown, low: which ? fill( words.lowWhich, lead.left, which ) : fill( words.low, lead.left ), in: which ? fill( words.inWhich, which ) : words.in }[ s ] || '';
	}
	// As the boards write it: "24 oz in Chocolate is sold out. Choose another flavour or size."
	let text = '';
	let kind = 'plain';
	const missing = ctx.variable && ! done ? ctx.attributes.find( ( a ) => ! ctx.chosen[ a.key ] ) : null;
	if ( allOut ) {
		text = '';
	} else if ( missing ) {
		text = fill( words.choose, missing.label.toLowerCase() );
	} else if ( ctx.variable && ( ! variation || out ) ) {
		const values = ctx.attributes.map( ( a ) => ( a.options.find( ( o ) => o.value === ctx.chosen[ a.key ] ) || {} ).label ).filter( Boolean );
		const combo = values.length > 1 ? fill( words.combo, values[ 0 ], values.slice( 1 ).join( words.sep ) ) : values.join( '' );
		const names = ctx.attributes.map( ( a ) => a.label.toLowerCase() ).reverse();
		const others = names.length > 1 ? fill( words.or, names.slice( 0, -1 ).join( words.sep ), names[ names.length - 1 ] ) : names.join( '' );
		text = fill( variation ? words.soldOut : words.notMade, combo, others );
		kind = variation ? 'plain' : 'box';
	}
	const box = q( root, '.mizzey-buy__message' );
	if ( box ) {
		box.hidden = ! text;
		box.dataset.kind = kind;
		q( box, '.mizzey-buy__message-text' ).textContent = text;
	}
	// Add to Cart, the bar, Buy Now, the quantity.
	const blocked = ( done && out ) || allOut;
	const label = allOut ? words.out : ! done ? words.chooseBtn : blocked && ! ctx.variable ? words.out : words.add;
	[ q( root, '.mizzey-buy__add' ), q( root, '.mizzey-buy-bar__add' ) ].forEach( ( b ) => {
		if ( b ) {
			b.disabled = blocked;
			q( b, '.mizzey-buy__label' ).textContent = label;
		}
	} );
	const variationInput = q( root, '.mizzey-buy__variation' );
	if ( variationInput ) {
		variationInput.value = variation ? variation.id : 0;
	}
	ctx.max = lead ? lead.max || 1 : 99;
	ctx.qty = Math.max( 1, Math.min( ctx.qty, ctx.max ) );
	quantity( root, ctx );
	const bar = q( root, '.mizzey-buy-bar' );
	if ( bar ) {
		html( q( bar, '.mizzey-buy-bar__price' ), lead ? lead.price : q( root, '.mizzey-buy__price' ).innerHTML );
		// Under the bar's price: the options chosen, or the stock word of a product without options; "Choose a size"
		// while one is missing; "Availability being confirmed" when the stock is not known.
		let note = which || ( q( root, '.mizzey-buy__stock-text' ) || {} ).textContent || '';
		if ( allOut ) {
			note = words.out;
		} else if ( missing ) {
			note = fill( words.chooseShort, missing.label.toLowerCase() );
		} else if ( lead && lead.state === 'unknown' ) {
			note = words.unknownShort;
		} else if ( lead && lead.state === 'out' && ! ctx.variable ) {
			note = words.out;
		}
		q( bar, '.mizzey-buy-bar__note' ).textContent = note;
		bar.classList.toggle( 'has-from', ! done );
	}
	// The gallery follows a variant with its own photo.
	const set = variation && variation.set ? variation.set : '';
	if ( set !== ctx.set ) {
		ctx.set = set;
		ctx.index = 0;
		show( root, ctx );
	}
	const chip = q( root, '.mizzey-gallery__showing' );
	if ( chip ) {
		chip.hidden = ! set;
		chip.textContent = set ? fill( words.showing, setName( ctx, set ) ) : '';
	}
	counter( root, ctx );
}

/** The option the variant's photo belongs to: the value every variant with that photo shares. */
function setName( ctx, set ) {
	const members = ctx.variations.filter( ( v ) => v.set === set );
	for ( const a of ctx.attributes ) {
		const values = new Set( members.map( ( v ) => v.attrs[ a.key ] ) );
		if ( values.size === 1 ) {
			const option = a.options.find( ( o ) => o.value === [ ...values ][ 0 ] );
			if ( option ) {
				return option.label;
			}
		}
	}
	return '';
}

function quantity( root, ctx ) {
	const count = q( root, '.mizzey-buy__count' );
	if ( ! count ) {
		buyNow( root, ctx );
		return;
	}
	count.textContent = String( ctx.qty );
	q( root, '.mizzey-buy__quantity' ).value = ctx.qty;
	const steps = qa( root, '.mizzey-buy__step' );
	steps[ 0 ].disabled = ctx.qty <= 1;
	steps[ 1 ].disabled = ctx.qty >= ctx.max;
	buyNow( root, ctx );
}

function buyNow( root, ctx ) {
	const link = q( root, '.mizzey-buy__now' );
	if ( ! link ) {
		return;
	}
	const { done, variation } = current( ctx );
	const ok = done && variation && variation.state !== 'out';
	const url = new URL( ctx.buyNow, window.location.href );
	url.searchParams.set( 'quantity', String( ctx.qty ) );
	if ( ctx.variable && variation ) {
		url.searchParams.set( 'variation_id', String( variation.id ) );
		Object.entries( ctx.chosen ).forEach( ( [ key, value ] ) => url.searchParams.set( key, value ) );
	}
	if ( ok ) {
		link.setAttribute( 'href', url.toString() );
		link.removeAttribute( 'aria-disabled' );
	} else {
		link.removeAttribute( 'href' );
		link.setAttribute( 'aria-disabled', 'true' );
	}
}

/** The list of what the gallery shows now: the set's photos, then the video. */
function list( root, ctx ) {
	const items = [ ...( ctx.sets[ ctx.set ] || ctx.sets[ '' ] || [] ) ].map( String );
	if ( q( root, '.mizzey-gallery__slide--video' ) ) {
		items.push( 'video' );
	}
	return items;
}

function counter( root, ctx ) {
	const items = list( root, ctx );
	// The board counts the video with the photos ("1 / 4": three photos and the video).
	const at = Math.min( ctx.index + 1, items.length );
	const text = ctx.set ? fill( state.words.counterOf, at, items.length, setName( ctx, ctx.set ) ) : fill( state.words.counter, at, items.length );
	const el = q( root, '.mizzey-gallery-counter' );
	if ( el ) {
		el.textContent = text;
	}
	const zoomCounter = q( root, '.mizzey-zoom__counter' );
	if ( zoomCounter ) {
		zoomCounter.textContent = text;
	}
}

/** Show the photo (or the video) at ctx.index of the current list; load it if it has not been. */
function show( root, ctx ) {
	const items = list( root, ctx );
	if ( ! items.length ) {
		return;
	}
	ctx.index = ( ctx.index + items.length ) % items.length;
	const key = items[ ctx.index ];
	qa( root, '.mizzey-gallery__slide' ).forEach( ( slide ) => {
		const on = slide.dataset.index === key;
		slide.classList.toggle( 'is-current', on );
		if ( on ) {
			slide.removeAttribute( 'aria-hidden' );
		} else {
			slide.setAttribute( 'aria-hidden', 'true' );
			const video = q( slide, 'video' );
			if ( video ) {
				video.pause();
			}
		}
		if ( ! on ) {
			return;
		}
		const img = q( slide, 'img' );
		if ( img && img.dataset.src ) {
			// Shown now: loaded now (a lazy image sized by the stylesheet may never be seen as on screen).
			img.loading = 'eager';
			if ( img.dataset.srcset ) {
				img.srcset = img.dataset.srcset;
			}
			img.src = img.dataset.src;
			delete img.dataset.src;
			delete img.dataset.srcset;
		}
		if ( img ) {
			watch( img, slide );
		}
		if ( key === 'video' && ! slide.firstElementChild ) {
			const media = document.createElement( slide.dataset.kind === 'file' ? 'video' : 'iframe' );
			media.src = slide.dataset.src;
			media.className = 'mizzey-gallery__video';
			if ( slide.dataset.kind === 'file' ) {
				media.controls = true;
				media.muted = true;
				media.playsInline = true;
				media.preload = 'metadata';
			} else {
				media.allow = 'autoplay; encrypted-media; picture-in-picture';
				media.allowFullscreen = true;
				media.title = q( root, '.mizzey-gallery__thumb.is-video' ).getAttribute( 'aria-label' );
			}
			slide.appendChild( media );
		}
	} );
	qa( root, '.mizzey-gallery__thumb, .mizzey-zoom__thumb' ).forEach( ( thumb ) => {
		const index = thumb.dataset.index;
		thumb.hidden = ! items.includes( index );
		thumb.setAttribute( 'aria-current', index === key ? 'true' : 'false' );
		if ( index !== 'video' && thumb.classList.contains( 'mizzey-gallery__thumb' ) ) {
			const n = items.indexOf( index ) + 1;
			thumb.setAttribute( 'aria-label', fill( index === key ? state.words.photoShown : state.words.photo, n ) );
		}
	} );
	const label = q( root, '.mizzey-gallery__video-label' );
	if ( label ) {
		label.hidden = false;
	}
	counter( root, ctx );
	zoomImage( root, ctx );
}

function watch( img, slide ) {
	if ( img.complete && img.naturalWidth ) {
		slide.classList.add( 'is-loaded' );
		return;
	}
	const done = () => slide.classList.add( 'is-loaded' );
	img.addEventListener( 'load', done, { once: true } );
	img.addEventListener( 'error', done, { once: true } );
}

function zoomImage( root, ctx ) {
	const dialog = q( root, 'dialog.mizzey-zoom' );
	if ( ! dialog || ! dialog.open ) {
		return;
	}
	const key = list( root, ctx )[ ctx.index ];
	const slide = q( root, `.mizzey-gallery__slide[data-index="${ key }"]` );
	const img = q( dialog, '.mizzey-zoom__image' );
	if ( slide && slide.dataset.full ) {
		img.src = slide.dataset.full;
		img.alt = ( q( slide, 'img' ) || {} ).alt || '';
	}
	q( dialog, '.mizzey-zoom__value' ).textContent = `${ ctx.zoom }%`;
	img.style.scale = String( ctx.zoom / 100 );
}

function bar( root ) {
	const el = q( root, '.mizzey-buy-bar' );
	const add = q( root, '.mizzey-buy__add' );
	if ( ! el || ! add ) {
		return;
	}
	// Up once Add to Cart is above the window's top (a jump past it counts too, so the position is read on scroll).
	let queued = false;
	const update = () => {
		queued = false;
		const up = add.getBoundingClientRect().bottom < 0 && ! window.matchMedia( DESKTOP ).matches;
		if ( up === el.classList.contains( 'is-shown' ) ) {
			return;
		}
		el.classList.toggle( 'is-shown', up );
		el.inert = ! up;
		el.setAttribute( 'aria-hidden', up ? 'false' : 'true' );
		document.body.classList.toggle( 'has-buy-bar', up );
	};
	const ask = () => {
		if ( ! queued ) {
			queued = true;
			requestAnimationFrame( update );
		}
	};
	window.addEventListener( 'scroll', ask, { passive: true } );
	window.addEventListener( 'resize', ask );
	update();
}

/** Post one add to the Store API from a button; the button shows the loader, then the tick, only after the answer. */
async function send( root, ctx, button ) {
	const { done, variation } = current( ctx );
	if ( ctx.busy || ! button || button.disabled ) {
		return;
	}
	if ( ! done ) {
		const first = qa( root, '.mizzey-buy__group' ).find( ( g ) => ! q( g, 'input' ).value );
		const option = first && q( first, '.mizzey-buy__option' );
		if ( option ) {
			option.scrollIntoView( { block: 'center', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
			option.focus( { preventScroll: true } );
		}
		return;
	}
	if ( ! variation || variation.state === 'out' ) {
		return;
	}
	const body = { id: ctx.variable ? variation.id : ctx.id, quantity: ctx.qty };
	if ( ctx.variable ) {
		body.variation = ctx.attributes.map( ( a ) => ( { attribute: a.name, value: ctx.chosen[ a.key ] } ) );
	}
	ctx.busy = true;
	button.style.minInlineSize = `${ button.getBoundingClientRect().width }px`;
	button.setAttribute( 'aria-busy', 'true' );
	button.classList.remove( 'is-done' );
	const finish = () => {
		ctx.busy = false;
		button.removeAttribute( 'aria-busy' );
	};
	try {
		const response = await fetch( state.cartApi, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Nonce: state.storeNonce },
			body: JSON.stringify( body ),
		} );
		const nonce = response.headers.get( 'Nonce' );
		if ( nonce ) {
			state.storeNonce = nonce;
		}
		const answer = await response.json().catch( () => ( {} ) );
		finish();
		if ( ! response.ok ) {
			button.style.minInlineSize = '';
			toast( 'error', message( answer, state.words.failed ) );
			return;
		}
		button.classList.add( 'is-done' );
		const cartLink = q( root, '.mizzey-buy-bar__cart' );
		if ( cartLink ) {
			cartLink.hidden = false;
		}
		document.dispatchEvent( new CustomEvent( 'mizzey-cart-changed', { detail: { count: Number( answer.items_count ), cart: answer } } ) );
		document.body.dispatchEvent( new CustomEvent( 'wc-blocks_added_to_cart', { bubbles: true } ) );
		if ( window.matchMedia( DESKTOP ).matches ) {
			fly( root );
			toast( 'added' );
		}
		setTimeout( () => {
			button.classList.remove( 'is-done' );
			button.style.minInlineSize = '';
		}, 5000 );
	} catch ( e ) {
		finish();
		button.style.minInlineSize = '';
		toast( 'error', state.words.failed );
	}
}

/** After the answer, on a desktop: the photo's thumbnail flies to the header's cart (C1-Product-Desktop). */
function fly( root ) {
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

async function wishlist( ctx, root, on ) {
	const button = q( root, '.mizzey-buy__wish' );
	const set = ( value ) => {
		ctx.wished = value;
		button.setAttribute( 'aria-pressed', value ? 'true' : 'false' );
		q( button, '.mizzey-buy__wish-label' ).textContent = value ? state.words.wished : state.words.wish;
	};
	set( on );
	try {
		const response = await fetch( on ? state.wishlistApi : `${ state.wishlistApi }/${ ctx.id }`, {
			method: on ? 'POST' : 'DELETE',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': state.restNonce },
			body: on ? JSON.stringify( { id: ctx.id } ) : undefined,
		} );
		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}
		if ( ! on ) {
			toast( 'removed', '', () => wishlist( ctx, root, true ) );
		}
	} catch ( e ) {
		set( ! on );
		toast( 'error', state.words.wishFailed );
	}
}

function openDialog( dialog, opener ) {
	openers.set( dialog, opener );
	dialog.showModal();
}

const { state } = store( 'mizzey/buy', {
	actions: {
		choose: withSyncEvent( ( event ) => {
			const button = event.currentTarget;
			const ctx = data( button.closest( '.mizzey-buy' ) );
			ctx.chosen = { ...ctx.chosen, [ button.dataset.key ]: button.dataset.value };
			refresh( button.closest( '.mizzey-buy' ), ctx );
		} ),
		arrows: withSyncEvent( ( event ) => {
			const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
			if ( ! keys[ event.key ] ) {
				return;
			}
			event.preventDefault();
			const button = event.currentTarget;
			const rtl = getComputedStyle( button ).direction === 'rtl' && event.key.startsWith( 'Arrow' ) && ! /Up|Down/.test( event.key );
			const buttons = qa( button.parentElement, '.mizzey-buy__option' );
			const next = buttons[ ( buttons.indexOf( button ) + keys[ event.key ] * ( rtl ? -1 : 1 ) + buttons.length ) % buttons.length ];
			next.focus();
			next.click();
		} ),
		qty: withSyncEvent( ( event ) => {
			const ctx = data( event.currentTarget.closest( '.mizzey-buy' ) );
			ctx.qty = Math.max( 1, Math.min( ctx.max, ctx.qty + Number( event.currentTarget.dataset.step ) ) );
			quantity( event.currentTarget.closest( '.mizzey-buy' ), ctx );
		} ),
		submit: withSyncEvent( ( event ) => {
			event.preventDefault();
			const root = event.currentTarget.closest( '.mizzey-buy' );
			send( root, data( root ), q( root, '.mizzey-buy__add' ) );
		} ),
		barAdd: withSyncEvent( ( event ) => {
			const root = event.currentTarget.closest( '.mizzey-buy' );
			send( root, data( root ), event.currentTarget );
		} ),
		buyNow: withSyncEvent( ( event ) => {
			const link = event.currentTarget;
			if ( link.getAttribute( 'aria-disabled' ) === 'true' || ! link.getAttribute( 'href' ) ) {
				event.preventDefault();
				return;
			}
			link.style.minInlineSize = `${ link.getBoundingClientRect().width }px`;
			link.setAttribute( 'aria-busy', 'true' );
		} ),
		wish: () => {
			const root = rootOf();
			const ctx = data( root );
			wishlist( ctx, root, ! ctx.wished );
		},
		more: withSyncEvent( ( event ) => {
			const button = event.currentTarget;
			const open = button.getAttribute( 'aria-expanded' ) !== 'true';
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			button.textContent = open ? state.words.less : state.words.more;
			button.closest( '.mizzey-buy' ).querySelector( '.mizzey-buy__name' ).classList.toggle( 'is-open', open );
		} ),
		step: withSyncEvent( ( event ) => {
			event.stopPropagation();
			const ctx = data( event.currentTarget.closest( '.mizzey-buy' ) );
			ctx.index += Number( event.currentTarget.dataset.step );
			show( event.currentTarget.closest( '.mizzey-buy' ), ctx );
		} ),
		show: withSyncEvent( ( event ) => {
			const root = event.currentTarget.closest( '.mizzey-buy' );
			const ctx = data( root );
			ctx.index = Math.max( 0, list( root, ctx ).indexOf( event.currentTarget.dataset.index ) );
			show( root, ctx );
		} ),
		zoomOpen: withSyncEvent( ( event ) => {
			const root = event.currentTarget.closest( '.mizzey-buy' );
			const ctx = data( root );
			if ( list( root, ctx )[ ctx.index ] === 'video' ) {
				return;
			}
			const dialog = q( root, 'dialog.mizzey-zoom' );
			ctx.zoom = 100;
			openDialog( dialog, q( root, '.mizzey-gallery__zoom' ) );
			show( root, ctx );
			q( dialog, '.mizzey-zoom__close' ).focus();
		} ),
		zoomClose: withSyncEvent( ( event ) => event.currentTarget.closest( 'dialog' ).close() ),
		zoomBackdrop: withSyncEvent( ( event ) => {
			if ( event.target === event.currentTarget ) {
				event.currentTarget.close();
			}
		} ),
		zoomClosed: withSyncEvent( ( event ) => {
			const opener = openers.get( event.currentTarget );
			if ( opener && opener.isConnected ) {
				opener.focus();
			}
		} ),
		level: withSyncEvent( ( event ) => {
			const ctx = data( event.currentTarget.closest( '.mizzey-buy' ) );
			ctx.zoom = Math.max( 100, Math.min( 300, ctx.zoom + Number( event.currentTarget.dataset.level ) ) );
			zoomImage( event.currentTarget.closest( '.mizzey-buy' ), ctx );
		} ),
		share: withSyncEvent( ( event ) => {
			const button = event.currentTarget;
			const root = button.closest( '.mizzey-buy' );
			const copy = q( root, '.mizzey-share__copy' );
			if ( ! window.matchMedia( DESKTOP ).matches && navigator.share ) {
				navigator.share( { title: q( root, '.mizzey-buy__name' ).textContent, url: copy.dataset.url } ).catch( () => {} );
				return;
			}
			const dialog = q( root, 'dialog.mizzey-share' );
			openDialog( dialog, button );
			if ( window.matchMedia( DESKTOP ).matches ) {
				// Under Share, its end edge on Share's: the distance from the window's end edge, either direction.
				const r = button.getBoundingClientRect();
				const rtl = getComputedStyle( button ).direction === 'rtl';
				// Under Share when it fits in the window, above it otherwise.
				const panel = q( dialog, '.mizzey-share__panel' ).getBoundingClientRect();
				const gap = parseFloat( getComputedStyle( q( dialog, '.mizzey-share__panel' ) ).paddingBlockStart ) || 0;
				const below = r.bottom + gap + panel.height <= window.innerHeight;
				dialog.style.setProperty( '--mizzey-pop-block', `${ below ? r.bottom : r.top - panel.height - gap * 2 }px` );
				dialog.style.setProperty( '--mizzey-pop-inline', `${ rtl ? r.x : window.innerWidth - r.x - r.width }px` );
			}
			copy.focus();
		} ),
		shareClose: withSyncEvent( ( event ) => event.currentTarget.closest( 'dialog' ).close() ),
		shareBackdrop: withSyncEvent( ( event ) => {
			if ( event.target === event.currentTarget ) {
				event.currentTarget.close();
			}
		} ),
		shareClosed: withSyncEvent( ( event ) => {
			const opener = openers.get( event.currentTarget );
			if ( opener && opener.isConnected ) {
				opener.focus();
			}
		} ),
		copy: withSyncEvent( ( event ) => {
			const button = event.currentTarget;
			const label = q( button, '.mizzey-share__copy-label' );
			const said = () => {
				label.textContent = state.words.copied;
				setTimeout( () => ( label.textContent = state.words.copy ), 2000 );
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( button.dataset.url ).then( said, () => {} );
			}
		} ),
	},
	callbacks: {
		init() {
			const root = getElement().ref;
			const ctx = data( root );
			qa( root, '.mizzey-gallery__slide.is-current img' ).forEach( ( img ) => watch( img, img.closest( '.mizzey-gallery__slide' ) ) );
			refresh( root, ctx );
			if ( ctx.set ) {
				show( root, ctx );
			}
			bar( root );
		},
	},
} );

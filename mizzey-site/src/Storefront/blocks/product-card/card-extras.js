/**
 * The product card's motion, loaded on first use by view.js (spec 066, slice 4; D-21 byte budgets): the flight to the
 * cart, the toast with its life and its swipe, the options sheet's look (sheet or popover) and way out, the rail's
 * arrows.
 *
 * The add, as C1-Motion-Playground plays it once the server has answered: a small copy of the product's image (the
 * disc when it has none; from the card's "+" after the options sheet, which closes first through ui.js) flies along
 * a curve to the header's cart, the header's count bumps and rolls as it lands, the White tick draws and "Added"
 * holds 1500 ms, then the count bubble. Under reduced motion nothing flies. The toast can be swiped away sideways on a
 * touch screen (past 35% of its width or a flick of 0.5 px per ms; it springs back otherwise; its life pauses while
 * held); under reduced motion it fades.
 *
 * Every word shown comes from the server, translated (NFR-04a). No value here is a style.
 */
import { store } from '@wordpress/interactivity';

const DESKTOP = '(min-width: 1024px)';
const REDUCE = '(prefers-reduced-motion: reduce)';
/* The swipe on a toast (the brief of 25 November; the flick speed is the sheet's, C1-Motion-Playground), and the 20 px
   at each screen edge where no custom gesture starts (C1-System 06, edge swipes). */
const SWIPE_PAST = 0.35;
const FLICK = 0.5;
const EDGE = 20;
/* The flight shrinks to 30% as it lands (C1-Motion-Playground, the "fly" keyframes). */
const LANDING_SCALE = 0.3;

const reduced = () => window.matchMedia( REDUCE ).matches;

/** A motion token's time in ms ("600ms" or "0.6s"). */
function ms( name, fallback = 0 ) {
	const raw = getComputedStyle( document.documentElement ).getPropertyValue( name ).trim();
	const value = raw.endsWith( 'ms' ) ? parseFloat( raw ) : parseFloat( raw ) * 1000;
	return Number.isFinite( value ) ? value : fallback;
}

const token = ( name ) => getComputedStyle( document.documentElement ).getPropertyValue( name ).trim();

/** The header's cart control that is on screen (the phone and the desktop header each have one). */
function headerCart() {
	return [ ...document.querySelectorAll( '.mizzey-header__cart' ) ].find( ( a ) => a.getClientRects().length > 0 ) || null;
}

/** Whether a variation fits every option chosen (an empty value on the variation means any). */
function matches( variation, chosen ) {
	return Object.entries( chosen ).every( ( [ key, value ] ) => {
		const has = variation.attrs[ key ] || '';
		return has === '' || has === value;
	} );
}

/**
 * A toast's life: it leaves after the token's time (5000ms), counting only while nothing holds it (the pointer over
 * it, the focus in it, a finger on it). The life line pauses with it.
 */
function lifeOf( notice ) {
	let left = ms( '--mizzey-motion-life-toast', 5000 );
	let since = 0;
	let timer = 0;
	const holds = new Set();
	const run = () => {
		if ( holds.size || ! notice.isConnected ) {
			return;
		}
		since = performance.now();
		timer = setTimeout( () => notice.remove(), left );
	};
	const hold = ( why, on ) => {
		const was = holds.size;
		if ( on ) {
			holds.add( why );
		} else {
			holds.delete( why );
		}
		notice.classList.toggle( 'is-held', holds.has( 'touch' ) );
		if ( ! was && holds.size ) {
			clearTimeout( timer );
			left -= performance.now() - since;
		} else if ( was && ! holds.size ) {
			run();
		}
	};
	return { run, hold };
}

/** Swiping a toast away sideways, on a touch screen. */
function swipeable( notice, life ) {
	let start = null;
	let moves = [];
	const width = () => notice.getBoundingClientRect().width;
	const place = ( x ) => notice.style.setProperty( '--mizzey-notice-drag', `${ x }px` );
	const leave = ( direction ) => {
		const done = () => notice.remove();
		if ( reduced() ) {
			notice.classList.add( 'is-fading' );
			setTimeout( done, ms( '--mizzey-motion-duration-2', 120 ) );
			return;
		}
		notice.classList.remove( 'is-dragging' );
		notice.classList.add( 'is-leaving' );
		place( direction * ( width() + EDGE ) );
		setTimeout( done, ms( '--mizzey-motion-duration-5', 240 ) );
	};
	notice.addEventListener( 'pointerdown', ( e ) => {
		if ( e.pointerType !== 'touch' || e.clientX < EDGE || e.clientX > window.innerWidth - EDGE ) {
			return;
		}
		start = { x: e.clientX, id: e.pointerId, t: e.timeStamp };
		moves = [ { x: e.clientX, t: e.timeStamp } ];
		notice.classList.remove( 'is-returning' );
		notice.classList.add( 'is-dragging' );
		life.hold( 'touch', true );
		try {
			notice.setPointerCapture( e.pointerId );
		} catch ( error ) {
			// A pointer the page did not see begin cannot be captured; the moves still reach the toast.
		}
	} );
	notice.addEventListener( 'pointermove', ( e ) => {
		if ( ! start || e.pointerId !== start.id ) {
			return;
		}
		// Every sample the browser has, the coalesced ones too, each at its own time.
		const all = typeof e.getCoalescedEvents === 'function' ? e.getCoalescedEvents() : [];
		( all.length ? all : [ e ] ).forEach( ( c ) => moves.push( { x: c.clientX, t: c.timeStamp } ) );
		if ( ! reduced() ) {
			place( e.clientX - start.x );
		}
	} );
	const end = ( e ) => {
		if ( ! start || e.pointerId !== start.id ) {
			return;
		}
		const dx = e.clientX - start.x;
		/* The flick: the speed over the last 80 ms, the lift included; when those samples span less than 16 ms (few
		   or coalesced events, a coarse clock), the whole gesture's distance over its time from the press, at least
		   16 ms. A slow drag that rests before the lift reads slow either way. */
		moves.push( { x: e.clientX, t: e.timeStamp } );
		const recent = moves.filter( ( m ) => e.timeStamp - m.t <= 80 );
		const first = recent[ 0 ];
		const span = first ? e.timeStamp - first.t : 0;
		const whole = dx / Math.max( e.timeStamp - start.t, 16 );
		const late = span >= 16 ? ( e.clientX - first.x ) / span : whole;
		// Either reading fast enough is a flick: the end of the gesture, or the whole of it (a quick short swipe).
		const speed = Math.abs( late ) > Math.abs( whole ) ? late : whole;
		start = null;
		notice.classList.remove( 'is-dragging' );
		life.hold( 'touch', false );
		if ( e.type === 'pointerup' && ( Math.abs( dx ) > width() * SWIPE_PAST || ( Math.abs( speed ) > FLICK && Math.sign( speed ) === Math.sign( dx ) ) ) ) {
			leave( Math.sign( dx ) || 1 );
			return;
		}
		notice.classList.add( 'is-returning' );
		place( 0 );
	};
	notice.addEventListener( 'pointerup', end );
	notice.addEventListener( 'pointercancel', end );
}

export function toast( kind, text ) {
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
	notice.classList.add( 'is-swipeable' );
	region.appendChild( notice );
	const life = lifeOf( notice );
	notice.addEventListener( 'mouseenter', () => life.hold( 'hover', true ) );
	notice.addEventListener( 'mouseleave', () => life.hold( 'hover', false ) );
	notice.addEventListener( 'focusin', () => life.hold( 'focus', true ) );
	notice.addEventListener( 'focusout', ( e ) => ! notice.contains( e.relatedTarget ) && life.hold( 'focus', false ) );
	swipeable( notice, life );
	life.run();
}

/**
 * A small copy of the product's image (or the disc, with no image) flies from the card to the header's cart along a
 * curve: across on the travel easing, up on the ease-in, so the path bends (C1-Motion-Playground). Returns the time
 * until it lands, 0 when nothing flies.
 */
export function fly( card, origin = null ) {
	const cart = headerCart();
	const well = card && card.querySelector( '.mizzey-card__well' );
	// From the image, or from the element given (the card's "+", after the options sheet has closed).
	const source = origin || well;
	if ( reduced() || ! cart || ! well ) {
		return 0;
	}
	const from = source.getBoundingClientRect();
	const to = cart.getBoundingClientRect();
	const flight = document.createElement( 'div' );
	flight.className = 'mizzey-card-flight';
	flight.setAttribute( 'aria-hidden', 'true' );
	const item = document.createElement( 'div' );
	const img = well.querySelector( 'img.mizzey-card__image' );
	if ( img && ( img.currentSrc || img.src ) ) {
		item.className = 'mizzey-card-flight__item';
		const copy = document.createElement( 'img' );
		copy.src = img.currentSrc || img.src;
		copy.alt = '';
		copy.className = 'mizzey-card-flight__image';
		item.appendChild( copy );
	} else {
		item.className = 'mizzey-card-flight__item mizzey-card-flight__item--disc';
	}
	flight.appendChild( item );
	document.body.appendChild( flight );
	const size = item.getBoundingClientRect();
	const x0 = from.left + from.width / 2;
	const y0 = from.top + from.height / 2;
	// The cart where it is now; scrolled off the top, where the sticky header row puts it (the row at the top of the
	// screen, the cart in its top end corner), never off the screen.
	const x1 = to.left + to.width / 2;
	let y1 = to.top + to.height / 2;
	if ( to.bottom <= 0 ) {
		const row = cart.closest( '.mizzey-header__bar' ) || cart.closest( '.mizzey-header' );
		y1 = row ? to.top - row.getBoundingClientRect().top + to.height / 2 : to.height / 2;
	}
	flight.style.setProperty( '--mizzey-flight-x', `${ x0 - size.width / 2 }px` );
	flight.style.setProperty( '--mizzey-flight-y', `${ y0 - size.height / 2 }px` );
	const duration = ms( '--mizzey-motion-duration-8', 600 );
	const options = { duration, fill: 'forwards' };
	flight.animate( [ { transform: 'translateX(0)' }, { transform: `translateX(${ x1 - x0 }px)` } ], { ...options, easing: token( '--mizzey-motion-easing-travel' ) || 'ease' } );
	item.animate( [ { transform: 'translateY(0) scale(1)' }, { transform: `translateY(${ y1 - y0 }px) scale(${ LANDING_SCALE })` } ], { ...options, easing: token( '--mizzey-motion-easing-in' ) || 'ease-in' } );
	const fade = item.animate( [ { opacity: 0 }, { opacity: 1, offset: 0.1 }, { opacity: 1, offset: 0.8 }, { opacity: 0 } ], options );
	fade.finished.then( () => flight.remove(), () => flight.remove() );
	return duration;
}

/**
 * An overlay's way out, as ui.js plays it (main, #573): `.is-closing` runs the panel's keyframes down, then the dialog
 * closes (focus goes back, the page scrolls again). Waits for the panel's animation, with a fallback; at once when
 * there is none (reduced motion).
 */
export function closeSheet( dialog ) {
	// The overlays' own way out (mizzey/ui, theme ui.js): this only waits for the dialog to report it has closed, so
	// the product flies once the sheet is gone. A fallback resolves if no close is ever reported.
	return new Promise( ( resolve ) => {
		if ( ! dialog || ! dialog.open ) {
			resolve();
			return;
		}
		let timer = 0;
		const done = () => {
			clearTimeout( timer );
			dialog.removeEventListener( 'close', done );
			resolve();
		};
		dialog.addEventListener( 'close', done );
		timer = setTimeout( done, 1500 );
		store( 'mizzey/ui' ).actions.closeOverlay( dialog );
	} );
}

/** The options' marks after a choice: checked, sold out, the chosen value's name and the price. */
export function refresh( dialog, picker ) {
	if ( ! dialog ) {
		return;
	}
	dialog.querySelectorAll( '[role="radio"]' ).forEach( ( button ) => {
		const key = button.dataset.key;
		const value = button.dataset.value;
		const want = { ...picker.chosen, [ key ]: value };
		const available = picker.variations.some( ( v ) => v.in && matches( v, want ) );
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
export function mode( dialog ) {
	const desktop = window.matchMedia( DESKTOP ).matches;
	dialog.classList.toggle( 'is-popover', desktop );
	dialog.classList.toggle( 'mizzey-overlay--sheet', ! desktop );
}

/** The popover above the card when there is room, below it otherwise, its start edge on the card's, kept on screen. */
export function place( dialog, card ) {
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

/** The rail's arrows: by whole cards, in the reading direction. */
export function scrollRail( button ) {
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
}

const openers = new WeakMap();

/**
 * The options open: a sheet on a phone, a popover by the card from 1024 wide. From the keyboard the focus goes to the
 * chosen option; after a touch or a click to the sheet itself, so Safari draws no ring. Focus returns to the "+".
 */
export function openSheet( dialog, ref, card, byKey ) {
	openers.set( dialog, ref );
	mode( dialog );
	dialog.showModal();
	place( dialog, card );
	if ( byKey ) {
		const first = dialog.querySelector( '[role="radio"][aria-checked="true"]' ) || dialog.querySelector( 'button' );
		if ( first ) {
			first.focus();
		}
	} else {
		// Opened by a touch or a click: the focus is in the sheet (its panel), with no ring anywhere.
		const panel = dialog.querySelector( '.mizzey-overlay__panel' );
		if ( panel ) {
			panel.setAttribute( 'tabindex', '-1' );
			panel.focus( { preventScroll: true } );
		}
	}
	dialog.addEventListener( 'close', () => {
		const opener = openers.get( dialog );
		openers.delete( dialog );
		if ( opener && opener.isConnected ) {
			opener.focus();
		}
	}, { once: true } );
}

/** An option chosen in the sheet: its marks, the chosen value's name and the price follow. */
export function chooseOption( event, context ) {
	const button = event.currentTarget;
	const key = button.dataset.key;
	const value = button.dataset.value;
	let chosen = { ...context.picker.chosen, [ key ]: value };
	if ( button.getAttribute( 'aria-disabled' ) === 'true' ) {
		// Sold out with the other options as chosen: taken only when some variation with it is in stock, and
		// then the other options move to that variation (Red from Blue/L moves to Red/M). Sold out everywhere,
		// it cannot be chosen.
		const other = context.picker.variations.find( ( v ) => v.in && ( v.attrs[ key ] || '' ) === value );
		if ( ! other ) {
			event.preventDefault();
			return;
		}
		chosen = Object.fromEntries( Object.entries( chosen ).map( ( [ k, current ] ) => [ k, other.attrs[ k ] || current ] ) );
	}
	context.picker.chosen = chosen;
	refresh( button.closest( 'dialog' ), context.picker );
}

/** The sheet's quantity: one more or one less, never under one. */
export function stepQty( event, context ) {
	const next = Math.max( 1, context.picker.qty + Number( event.currentTarget.dataset.step ) );
	context.picker.qty = next;
	event.currentTarget.closest( '.mizzey-stepper' ).querySelector( '.mizzey-stepper__value' ).textContent = String( next );
}

/** The server's refusal in plain words (its message may carry markup), or the card's own. */
export function message( body, fallback ) {
	const text = body && typeof body.message === 'string' ? body.message : '';
	const plain = text ? new DOMParser().parseFromString( text, 'text/html' ).body.textContent.trim() : '';
	return plain || fallback;
}

/** The heart pressed on: it grows 0.8 to 1 with one ring, only when pressed (not when a page opens with it saved). */
export function popHeart( ref ) {
	clearTimeout( Number( ref.dataset.popping || 0 ) );
	ref.classList.remove( 'is-popping' );
	void ref.offsetWidth;
	ref.classList.add( 'is-popping' );
	ref.dataset.popping = String( setTimeout( () => ref.classList.remove( 'is-popping' ), ms( '--mizzey-motion-duration-7', 450 ) ) );
}

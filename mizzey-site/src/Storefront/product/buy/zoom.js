/**
 * The product photo zoom's photo and gestures (spec 066, slice 9), loaded by view.js the first time the zoom opens
 * (the product page's script budget, D-21): a drag 1:1 with a glide, two fingers pinching, a double tap or click, the
 * wheel and the arrow keys, the photo always held so its edge never comes inside the frame.
 */
const q = ( root, sel ) => root.querySelector( sel );

export function zoomImage( root, ctx, key ) {
	const dialog = q( root, 'dialog.mizzey-zoom' );
	if ( ! dialog || ! dialog.open ) {
		return;
	}
	const slide = q( root, `.mizzey-gallery__slide[data-index="${ key }"]` );
	const img = q( dialog, '.mizzey-zoom__image' );
	if ( slide && slide.dataset.full && img.getAttribute( 'src' ) !== slide.dataset.full ) {
		img.src = slide.dataset.full;
		img.alt = ( q( slide, 'img' ) || {} ).alt || '';
		ctx.panX = 0;
		ctx.panY = 0;
	}
	place( dialog, ctx );
}

/** The zoomed photo, scaled and moved, held so its edge never comes inside the frame. */
export function place( dialog, ctx ) {
	const view = q( dialog, '.mizzey-zoom__view' );
	const img = q( dialog, '.mizzey-zoom__image' );
	const box = view.getBoundingClientRect();
	const ratio = img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : 1;
	// The photo's own size inside the frame at 100% (object-fit: contain).
	const fitW = Math.min( box.width, box.height * ratio );
	const fitH = fitW / ratio;
	const scale = ctx.zoom / 100;
	const maxX = Math.max( 0, ( fitW * scale - box.width ) / 2 );
	const maxY = Math.max( 0, ( fitH * scale - box.height ) / 2 );
	ctx.panX = Math.max( -maxX, Math.min( maxX, ctx.panX || 0 ) );
	ctx.panY = Math.max( -maxY, Math.min( maxY, ctx.panY || 0 ) );
	img.style.transform = `translate(${ ctx.panX }px, ${ ctx.panY }px) scale(${ scale })`;
	q( dialog, '.mizzey-zoom__value' ).textContent = `${ Math.round( ctx.zoom ) }%`;
	view.classList.toggle( 'is-zoomed', ctx.zoom > 100 );
}

/** Zoom to a level about a point of the frame (from its centre, screen pixels), the point staying under the finger. */
export function zoomAt( dialog, ctx, level, px = 0, py = 0 ) {
	const next = Math.max( 100, Math.min( 300, level ) );
	const k = next / ctx.zoom;
	ctx.panX = px - ( px - ( ctx.panX || 0 ) ) * k;
	ctx.panY = py - ( py - ( ctx.panY || 0 ) ) * k;
	ctx.zoom = next;
	place( dialog, ctx );
}

/** Drag 1:1 with a glide, pinch, double tap or click, wheel and arrow keys move the zoomed photo. */
export function pan( root, ctx ) {
	const dialog = q( root, 'dialog.mizzey-zoom' );
	if ( ! dialog ) {
		return;
	}
	const view = q( dialog, '.mizzey-zoom__view' );
	const img = q( dialog, '.mizzey-zoom__image' );
	img.addEventListener( 'load', () => place( dialog, ctx ) );
	const still = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const pointers = new Map();
	let pinch = null;
	let last = { x: 0, y: 0, t: 0 };
	let speed = { x: 0, y: 0 };
	let glide = 0;
	let tap = { at: 0, x: 0, y: 0 };
	const centre = ( x, y ) => {
		const b = view.getBoundingClientRect();
		return [ x - b.left - b.width / 2, y - b.top - b.height / 2 ];
	};
	view.addEventListener( 'pointerdown', ( event ) => {
		if ( event.target.closest( 'button' ) ) {
			return;
		}
		cancelAnimationFrame( glide );
		try {
			view.setPointerCapture( event.pointerId );
		} catch ( e ) {
			// A pointer the browser no longer tracks: the moves still reach the frame.
		}
		pointers.set( event.pointerId, { x: event.clientX, y: event.clientY } );
		last = { x: event.clientX, y: event.clientY, t: performance.now() };
		speed = { x: 0, y: 0 };
		if ( pointers.size === 2 ) {
			const [ a, b ] = [ ...pointers.values() ];
			pinch = { d: Math.hypot( a.x - b.x, a.y - b.y ) || 1, z: ctx.zoom };
		}
	} );
	view.addEventListener( 'pointermove', ( event ) => {
		if ( ! pointers.has( event.pointerId ) ) {
			return;
		}
		pointers.set( event.pointerId, { x: event.clientX, y: event.clientY } );
		if ( pinch && pointers.size === 2 ) {
			const [ a, b ] = [ ...pointers.values() ];
			const [ cx, cy ] = centre( ( a.x + b.x ) / 2, ( a.y + b.y ) / 2 );
			zoomAt( dialog, ctx, pinch.z * Math.hypot( a.x - b.x, a.y - b.y ) / pinch.d, cx, cy );
			return;
		}
		const now = performance.now();
		const dx = event.clientX - last.x;
		const dy = event.clientY - last.y;
		const dt = Math.max( 1, now - last.t );
		speed = { x: dx / dt, y: dy / dt };
		last = { x: event.clientX, y: event.clientY, t: now };
		if ( ctx.zoom > 100 ) {
			ctx.panX += dx;
			ctx.panY += dy;
			place( dialog, ctx );
		}
	} );
	const end = ( event ) => {
		if ( ! pointers.has( event.pointerId ) ) {
			return;
		}
		pointers.delete( event.pointerId );
		if ( pointers.size < 2 ) {
			pinch = null;
		}
		if ( pointers.size ) {
			return;
		}
		// A double tap: in to 200% at that point, or back out.
		const now = performance.now();
		const moved = Math.hypot( event.clientX - tap.x, event.clientY - tap.y );
		if ( event.pointerType === 'touch' && now - tap.at < 300 && moved < 24 ) {
			const [ cx, cy ] = centre( event.clientX, event.clientY );
			zoomAt( dialog, ctx, ctx.zoom > 100 ? 100 : 200, cx, cy );
			tap = { at: 0, x: 0, y: 0 };
			return;
		}
		tap = { at: now, x: event.clientX, y: event.clientY };
		// The glide: the speed it had, slowing down, held in the frame.
		if ( still() || ctx.zoom <= 100 || now - last.t > 80 ) {
			return;
		}
		let v = { x: speed.x * 16, y: speed.y * 16 };
		const step = () => {
			v = { x: v.x * 0.92, y: v.y * 0.92 };
			if ( Math.abs( v.x ) < 0.3 && Math.abs( v.y ) < 0.3 ) {
				return;
			}
			ctx.panX += v.x;
			ctx.panY += v.y;
			place( dialog, ctx );
			glide = requestAnimationFrame( step );
		};
		glide = requestAnimationFrame( step );
	};
	view.addEventListener( 'pointerup', end );
	view.addEventListener( 'pointercancel', end );
	view.addEventListener( 'dblclick', ( event ) => {
		if ( event.target.closest( 'button' ) ) {
			return;
		}
		const [ cx, cy ] = centre( event.clientX, event.clientY );
		zoomAt( dialog, ctx, ctx.zoom > 100 ? 100 : 200, cx, cy );
	} );
	view.addEventListener( 'wheel', ( event ) => {
		event.preventDefault();
		const [ cx, cy ] = centre( event.clientX, event.clientY );
		zoomAt( dialog, ctx, ctx.zoom * Math.exp( -event.deltaY / 500 ), cx, cy );
	}, { passive: false } );
	dialog.addEventListener( 'keydown', ( event ) => {
		const keys = { ArrowLeft: [ 1, 0 ], ArrowRight: [ -1, 0 ], ArrowUp: [ 0, 1 ], ArrowDown: [ 0, -1 ] };
		if ( ctx.zoom <= 100 || ! keys[ event.key ] || event.target.closest( '.mizzey-zoom__thumbs' ) ) {
			return;
		}
		event.preventDefault();
		const by = parseFloat( getComputedStyle( view ).getPropertyValue( '--mizzey-zoom-key-step' ) ) || 40;
		ctx.panX += keys[ event.key ][ 0 ] * by;
		ctx.panY += keys[ event.key ][ 1 ] * by;
		place( dialog, ctx );
	} );
}

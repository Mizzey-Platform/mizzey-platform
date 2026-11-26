/* Spec 066 slice 10: the review photo viewer (C1-Photo-Reviews; D-23, a gift). Loaded only on a page that shows a
   review's photos. One photo at a time, its number and who took it; the arrow keys step (the other way in Arabic);
   after a tap the dialog holds focus without a ring on its first button (WebKit draws one). */

const root = document.querySelector( '.mizzey-details' );
const $ = ( s, el = root ) => el.querySelector( s );

function viewer( dialog ) {
	const img = $( 'img', dialog );
	const label = $( 'p', dialog );
	let photos = [];
	let name = '';
	let at = 0;
	const show = ( n ) => {
		at = ( n + photos.length ) % photos.length;
		img.src = photos[ at ].src;
		img.alt = photos[ at ].alt;
		label.textContent = label.dataset.template.replace( '%1$s', at + 1 ).replace( '%2$s', photos.length ).replace( '%3$s', name );
		dialog.classList.toggle( 'is-single', photos.length < 2 );
	};
	root.addEventListener( 'click', ( e ) => {
		const photo = e.target.closest( '.mizzey-reviews__photo' );
		const review = photo && photo.closest( '.mizzey-review' );
		if ( ! review ) {
			return;
		}
		photos = JSON.parse( review.dataset.photos || '[]' );
		name = review.dataset.name;
		show( +photo.dataset.index );
		dialog.showModal();
		if ( e.detail ) {
			document.activeElement.blur();
		}
	} );
	dialog.addEventListener( 'click', ( e ) => {
		const step = e.target.closest( '[data-step]' );
		if ( step ) {
			show( at + +step.dataset.step );
		} else if ( e.target === dialog || e.target.closest( '.mizzey-photo-viewer__close' ) ) {
			dialog.close();
		}
	} );
	// On the document: after a tap the focus is not inside the dialog, and the arrows still step.
	document.addEventListener( 'keydown', ( e ) => {
		if ( dialog.open && ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) ) {
			show( at + ( ( e.key === 'ArrowRight' ) !== ( getComputedStyle( dialog ).direction === 'rtl' ) ? 1 : -1 ) );
		}
	} );
}

const dialog = root && $( '.mizzey-photo-viewer' );
if ( dialog ) {
	viewer( dialog );
}

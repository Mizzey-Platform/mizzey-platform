/* Spec 066 slice 10: "Write a review" with photos (C1-Photo-Reviews; D-23, a gift). Loaded only for a customer who may
   review. The review goes to WordPress's comments route, which specs/020 checks; then its photos to specs/025's route.
   The button holds the i-dot loader until the server answers; only then does the thank-you show. Without this script
   the form posts as WordPress's own comment form, without photos. Every word comes from the page or the server. */

const form = document.querySelector( '.mizzey-review-entry__form' );

if ( form ) {
	const input = form.querySelector( 'input[type="file"]' );
	const chosen = form.querySelector( '.mizzey-review-entry__chosen' );
	const error = form.querySelector( '.mizzey-review-entry__error' );
	const send = form.querySelector( '.mizzey-review-entry__send' );
	const max = Number( form.dataset.max ) || 5;
	let files = [];

	const draw = () => {
		chosen.replaceChildren( ...files.map( ( file ) => {
			const item = document.createElement( 'li' );
			const img = document.createElement( 'img' );
			img.src = URL.createObjectURL( file );
			img.alt = '';
			item.append( img );
			return item;
		} ) );
		form.querySelector( '.mizzey-review-entry__add' ).hidden = files.length >= max;
	};

	input.addEventListener( 'change', () => {
		files = files.concat( [ ...input.files ] ).slice( 0, max );
		input.value = '';
		draw();
	} );

	const answer = async ( response ) => {
		const body = await response.json().catch( () => ( {} ) );
		if ( ! response.ok ) {
			throw new Error( body.message || response.statusText );
		}
		return body;
	};

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( send.disabled ) {
			return;
		}
		error.textContent = '';
		send.disabled = true;
		send.classList.add( 'is-busy' );
		const headers = { 'X-WP-Nonce': form.dataset.nonce };
		let review = null;
		try {
			review = await answer( await fetch( form.dataset.rest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { ...headers, 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					post: Number( form.elements.comment_post_ID.value ),
					content: form.elements.comment.value,
					rating: Number( ( form.querySelector( 'input[name="rating"]:checked' ) || {} ).value ),
				} ),
			} ) );
			if ( files.length ) {
				const data = new FormData();
				files.forEach( ( file ) => data.append( 'photos[]', file ) );
				await answer( await fetch( `${ form.dataset.photos }${ review.id }/photos`, { method: 'POST', credentials: 'same-origin', headers, body: data } ) );
			}
		} catch ( failure ) {
			error.textContent = failure.message;
			send.disabled = false;
			send.classList.remove( 'is-busy' );
			if ( ! review ) {
				return;
			}
		}
		// The review is held for approval: the form gives way to the thank-you, at the same place.
		const done = document.createElement( 'p' );
		done.className = 'mizzey-review-entry__note';
		done.setAttribute( 'role', 'status' );
		done.textContent = form.dataset.done;
		form.replaceWith( done, ...( error.textContent ? [ error ] : [] ) );
	} );
}

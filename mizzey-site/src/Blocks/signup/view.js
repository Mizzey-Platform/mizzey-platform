/**
 * The home page's sign-up on the storefront (spec 066, slice 5: HOME-09), as C1-Home-Phone, C1-Home-Desktop and
 * C1-Motion-Playground draw it.
 *
 * A plain script module with no import: it loads only on a page that shows the section. Without it the form posts to
 * its page and the server answers in the same states; with it the form is sent to `mizzey/v1/signup` and the page
 * stays where it is.
 *
 * - An address that is not one is refused before anything is sent; the server's answer decides the rest.
 * - Busy: from 400 ms after the press the button's label gives way to the i-dot loader, its width locked, shown for at
 *   least 500 ms. Nothing claims success before the server answers.
 * - Success replaces the form in the same place at the same height, and the focus moves to the message.
 * - A refusal shows its words under the field (they come from the server, translated, on the form's data attributes),
 *   the field turns red, and the focus goes back to the field. No shake. A request that never reaches the site, a
 *   site closed for maintenance (503 without our answer) and a record that could not be written each have their own
 *   words.
 * - The form's token is the script's own: the page may come from a cache every visitor shares, and a token is
 *   single-use, so the one printed in the page (for a visitor without JavaScript) is never sent. A fresh one is asked
 *   of `mizzey/v1/signup/token` when the field first gets the focus, or on the press if none is held, and is sent no
 *   sooner than the server accepts it (TIMING.tokenAge). A refused token is replaced and sent once more, unseen.
 */

const TIMING = { busyAfter: 400, busyAtLeast: 500, tokenAge: 3200 };
const LOOKS_LIKE_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const wait = ( ms ) => new Promise( ( done ) => setTimeout( done, ms ) );

function setup( section ) {
	const form = section.querySelector( '.mizzey-signup__form' );
	const input = section.querySelector( '.mizzey-signup__input' );
	const error = section.querySelector( '.mizzey-signup__error' );
	const errorText = section.querySelector( '.mizzey-signup__error-text' );
	const optin = section.querySelector( '.mizzey-signup__optin' );
	const button = section.querySelector( '.mizzey-signup__button' );
	const busy = section.querySelector( '.mizzey-signup__busy' );
	const done = section.querySelector( '.mizzey-signup__done' );
	const address = section.querySelector( '.mizzey-signup__address' );
	if ( ! form || ! input || ! button || ! done || ! form.dataset.endpoint ) {
		return;
	}
	let sending = false;
	let held = null;
	let asking = null;
	let why = 'offline';

	/** A fresh token from the server, never from the page or a cache. */
	function askToken() {
		if ( ! asking ) {
			asking = fetch( form.dataset.token, { credentials: 'same-origin', cache: 'no-store' } )
				.then( async ( response ) => {
					const answer = await response.json().catch( () => null );
					held = answer && answer.token ? { value: answer.token, at: performance.now() } : null;
					why = response.status === 503 ? 'unavailable' : 'failed';
				} )
				.catch( () => {
					held = null;
					why = 'offline';
				} )
				.finally( () => {
					asking = null;
				} );
		}
		return asking;
	}

	/** The held token, spent: asked for if none is held, and old enough for the server to accept. */
	async function spendToken() {
		if ( ! held ) {
			await askToken();
		}
		if ( ! held ) {
			return '';
		}
		const token = held;
		held = null;
		await wait( Math.max( 0, TIMING.tokenAge - ( performance.now() - token.at ) ) );
		return token.value;
	}

	async function send( typed, token ) {
		try {
			const response = await fetch( form.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					email: typed,
					consent: !! ( optin && optin.checked ),
					lang: new FormData( form ).get( 'mizzey_signup_lang' ) || '',
					token,
					trap: new FormData( form ).get( 'mizzey_signup_website' ) || '',
				} ),
			} );
			const answer = await response.json().catch( () => null );
			// No answer of ours: the site is closed for maintenance, or something in front of it refused.
			return answer && answer.state ? answer : { state: response.status === 503 ? 'unavailable' : 'failed' };
		} catch ( failure ) {
			// The request never reached the site.
			return { state: 'offline' };
		}
	}

	input.addEventListener( 'focus', () => {
		if ( ! held ) {
			askToken();
		}
	} );

	function showError( state ) {
		errorText.textContent = form.dataset[ state ] || form.dataset.invalid;
		error.hidden = false;
		section.classList.add( 'is-error' );
		input.setAttribute( 'aria-invalid', 'true' );
		input.focus();
	}

	function clearError() {
		if ( error.hidden ) {
			return;
		}
		error.hidden = true;
		section.classList.remove( 'is-error' );
		input.removeAttribute( 'aria-invalid' );
	}

	function startBusy() {
		const label = [ ...button.childNodes ];
		let shownAt = 0;
		const timer = setTimeout( () => {
			button.style.inlineSize = `${ button.getBoundingClientRect().width }px`;
			button.setAttribute( 'aria-busy', 'true' );
			button.replaceChildren( busy.content.cloneNode( true ) );
			shownAt = performance.now();
		}, TIMING.busyAfter );
		return async () => {
			clearTimeout( timer );
			if ( shownAt ) {
				await wait( Math.max( 0, TIMING.busyAtLeast - ( performance.now() - shownAt ) ) );
			}
			button.replaceChildren( ...label );
			button.removeAttribute( 'aria-busy' );
			button.style.inlineSize = '';
		};
	}

	function showDone( email ) {
		address.textContent = email;
		form.inert = true;
		done.hidden = false;
		section.classList.add( 'is-sent' );
		done.focus();
	}

	input.addEventListener( 'input', clearError );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( sending ) {
			return;
		}
		const typed = input.value.trim();
		if ( ! LOOKS_LIKE_EMAIL.test( typed ) ) {
			showError( 'invalid' );
			return;
		}
		clearError();
		sending = true;
		const stopBusy = startBusy();
		let token = await spendToken();
		let answer = token ? await send( typed, token ) : { state: why };
		if ( answer.state === 'expired' ) {
			// Spent or refused on the way: one fresh token, once, before saying anything.
			token = await spendToken();
			answer = token ? await send( typed, token ) : { state: why };
		}
		if ( answer.token ) {
			held = { value: answer.token, at: performance.now() };
		}
		await stopBusy();
		sending = false;
		if ( answer.state === 'ok' ) {
			showDone( answer.email || typed );
			return;
		}
		showError( form.dataset[ answer.state ] ? answer.state : 'failed' );
	} );
}

document.querySelectorAll( '.mizzey-signup' ).forEach( setup );

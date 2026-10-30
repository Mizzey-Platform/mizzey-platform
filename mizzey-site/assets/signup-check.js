/**
 * The signup check on the two account-creating forms the browser draws (AUTH-15, specs/009-sign-in-protection).
 *
 * The block checkout: the token the page was issued, and the value of a trap field people never see, travel in the
 * Store API request as `extensions['mizzey-signup']`, kept set on WooCommerce's checkout data store.
 * The order-received account offer: its form is drawn after the page loads, so the token printed beside the block and
 * the trap field are added to the form whenever it appears.
 *
 * No external request, no cookie, nothing visible.
 */
( function () {
	'use strict';

	var config = window.mizzeySignup || {};

	/** The trap field inside a container: a text input hidden from people and from assistive technology. */
	function trapField( container ) {
		var input = container.querySelector( 'input[name="' + config.trapName + '"]' );
		if ( input ) {
			return input;
		}
		var holder = document.createElement( 'span' );
		holder.style.display = 'none';
		holder.setAttribute( 'aria-hidden', 'true' );
		var label = document.createElement( 'label' );
		label.textContent = config.trapLabel || '';
		input = document.createElement( 'input' );
		input.type = 'text';
		input.name = config.trapName;
		input.tabIndex = -1;
		input.autocomplete = 'off';
		label.appendChild( input );
		holder.appendChild( label );
		container.appendChild( holder );
		return input;
	}

	function keepCheckoutData() {
		if ( ! config.token || ! window.wp || ! window.wp.data ) {
			return;
		}
		var data = window.wp.data;
		var apply = function () {
			var container = document.querySelector( '.wc-block-checkout__form, .wp-block-woocommerce-checkout' );
			var store = data.select( 'wc/store/checkout' );
			if ( ! container || ! store || typeof store.getExtensionData !== 'function' ) {
				return false;
			}
			var wanted = { token: config.token, trap: trapField( container ).value };
			var current = store.getExtensionData() || {};
			var ours = current[ config.namespace ];
			if ( ! ours || ours.token !== wanted.token || ours.trap !== wanted.trap ) {
				data.dispatch( 'wc/store/checkout' ).setExtensionData( config.namespace, wanted );
			}
			return true;
		};
		apply();
		data.subscribe( apply );
		document.addEventListener( 'input', apply, true );
		document.addEventListener( 'change', apply, true );
		new MutationObserver( apply ).observe( document.body, { childList: true, subtree: true } );
	}

	function addOfferFields() {
		var holder = document.querySelector( '[data-mizzey-signup-offer]' );
		if ( ! holder ) {
			return;
		}
		var forms = document.querySelectorAll( '.wp-block-woocommerce-order-confirmation-create-account form' );
		Array.prototype.forEach.call( forms, function ( form ) {
			if ( ! form.querySelector( 'input[name="' + config.tokenName + '"]' ) ) {
				var token = document.createElement( 'input' );
				token.type = 'hidden';
				token.name = config.tokenName;
				token.value = holder.getAttribute( 'data-mizzey-signup-offer' );
				form.appendChild( token );
			}
			trapField( form );
		} );
	}

	function watchOffer() {
		if ( ! document.querySelector( '[data-mizzey-signup-offer]' ) ) {
			return;
		}
		addOfferFields();
		new MutationObserver( addOfferFields ).observe( document.body, { childList: true, subtree: true } );
		document.addEventListener( 'submit', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.wp-block-woocommerce-order-confirmation-create-account' ) ) {
				addOfferFields();
			}
		}, true );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			keepCheckoutData();
			watchOffer();
		} );
	} else {
		keepCheckoutData();
		watchOffer();
	}
}() );

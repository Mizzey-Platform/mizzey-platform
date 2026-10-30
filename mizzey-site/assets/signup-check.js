/**
 * The signup check on the two account-creating forms the browser draws (AUTH-15, specs/009-sign-in-protection).
 *
 * The block checkout: the token the page was issued travels in the Store API request as
 * `extensions['mizzey-signup']`, set on WooCommerce's checkout data store once it exists and kept set.
 * The order-received account offer: its form is drawn after the page loads, so the token printed beside the block is
 * added to the form as hidden fields, with the empty trap, whenever the form appears.
 *
 * No external request, no cookie, nothing visible.
 */
( function () {
	'use strict';

	var config = window.mizzeySignup || {};

	function keepCheckoutData() {
		if ( ! config.token || ! window.wp || ! window.wp.data ) {
			return;
		}
		var data = window.wp.data;
		var wanted = { token: config.token, trap: '' };
		var apply = function () {
			var store = data.select( 'wc/store/checkout' );
			if ( ! store || typeof store.getExtensionData !== 'function' ) {
				return false;
			}
			var current = store.getExtensionData() || {};
			var ours = current[ config.namespace ];
			if ( ! ours || ours.token !== wanted.token || ours.trap !== '' ) {
				data.dispatch( 'wc/store/checkout' ).setExtensionData( config.namespace, wanted );
			}
			return true;
		};
		apply();
		data.subscribe( apply );
	}

	function addOfferFields( root ) {
		var holder = document.querySelector( '[data-mizzey-signup-offer]' );
		if ( ! holder ) {
			return;
		}
		var forms = ( root || document ).querySelectorAll( '.wp-block-woocommerce-order-confirmation-create-account form' );
		Array.prototype.forEach.call( forms, function ( form ) {
			if ( form.querySelector( 'input[name="' + config.tokenName + '"]' ) ) {
				return;
			}
			var token = document.createElement( 'input' );
			token.type = 'hidden';
			token.name = config.tokenName;
			token.value = holder.getAttribute( 'data-mizzey-signup-offer' );
			var trap = document.createElement( 'input' );
			trap.type = 'hidden';
			trap.name = config.trapName;
			trap.value = '';
			form.appendChild( token );
			form.appendChild( trap );
		} );
	}

	function watchOffer() {
		if ( ! document.querySelector( '[data-mizzey-signup-offer]' ) ) {
			return;
		}
		addOfferFields( document );
		new MutationObserver( function () {
			addOfferFields( document );
		} ).observe( document.body, { childList: true, subtree: true } );
		document.addEventListener( 'submit', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.wp-block-woocommerce-order-confirmation-create-account' ) ) {
				addOfferFields( document );
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

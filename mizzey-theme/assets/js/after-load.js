/**
 * WooCommerce's order attribution, started after the page has loaded on a lean page (spec 066, K-6). Printed inline
 * by inc/performance.php, with this comment taken off, after the scripts it starts; they wait as data blocks
 * (script[data-mizzey-after-load]) and are started in document order, kept in order (async off). The visitor may
 * have chosen on the consent banner while the page loaded, so the cookie is read again, both ways, as consent.js
 * does: before attribution starts (so it starts on that choice) and once it has (so it follows it).
 */
addEventListener( 'load', function () {
	setTimeout( function () {
		var choice = function () {
			var m = /(?:^|;\s*)mizzey_consent=(granted|denied)(?:;|$)/.exec( document.cookie );
			return m ? m[ 1 ] : '';
		};
		var a = window.wc_order_attribution;
		if ( choice() && a && a.params ) {
			a.params.allowTracking = choice() === 'granted';
		}
		document.querySelectorAll( 'script[data-mizzey-after-load]' ).forEach( function ( held ) {
			var s = document.createElement( 'script' );
			s.src = held.getAttribute( 'data-mizzey-after-load' );
			s.async = false;
			if ( held.id === 'wc-order-attribution-js' ) {
				s.onload = function () {
					var w = window.wc_order_attribution;
					if ( choice() && w && w.setOrderTracking ) {
						w.setOrderTracking( choice() === 'granted' );
					}
				};
			}
			held.after( s );
		} );
	}, 0 );
} );

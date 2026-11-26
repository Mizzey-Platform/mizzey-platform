/**
 * Every tap on our buttons acts on the first press (spec 066, K-6 and K-5). Printed inline at the top of the head by
 * inc/performance.php, with this comment taken off; no dependency.
 *
 * - Before the Interactivity runtime has hydrated the page, a click on a button that carries data-wp-on--click is
 *   held (its default and its propagation stopped) and replayed once, when ui.js sets data-mizzey-ready on the html
 *   element and sends "mizzey:ready". Only the last one is kept, so a second impatient tap does not undo the first.
 * - On touch, the button acts on the finger's lift (the primary pointer, moved under 10 px, under 500 ms, on the same
 *   button, not taken over by a scroll), and the click the browser may send after it is dropped: iOS Safari can skip
 *   that click when content on the page changes during the tap, and it is not needed. The button takes the focus
 *   first, as the tap would have given it (without a ring, K-5), so a dialog it opens gives the focus back to it.
 * - It fails open: when the page's behaviour has not started 1.5 s after the load event (ui.js missing or stopped),
 *   it stops holding and acting for the page (the held tap is replayed once, to whatever did start), until ui.js
 *   reports ready after all.
 * - Only button elements of type "button": links stay links and navigate, a submit button keeps its form. Mouse and
 *   keyboard keep using the click, so keyboard and screen reader use is unchanged.
 * - The navigation line (the owner's design addition; its look and motion in base.css): a link or a form to another
 *   page of the store that nothing on the page handled sets mizzey-nav on the html element after 150 ms, so a page
 *   that opens at once shows nothing; leaving the page sets mizzey-nav-done, and a page brought back from the
 *   back-forward cache clears both. A new tab, a download, a modified click and a jump within the page show none.
 */
( function ( d, l ) {
	var root = d.documentElement, held = null, drop = 0, own = 0, down = null, off = 0;
	function nav( e, to ) {
		to = new URL( to, l );
		setTimeout( function () {
			if ( ! e.defaultPrevented && to.origin === l.origin && ( to.pathname + to.search !== l.pathname + l.search || ! to.hash ) ) {
				setTimeout( function () {
					root.classList.add( 'mizzey-nav' );
				}, 150 );
			}
		} );
	}
	function end( e ) {
		if ( e.type !== 'pageshow' ) {
			root.classList.add( 'mizzey-nav-done' );
		} else if ( e.persisted ) {
			root.classList.remove( 'mizzey-nav', 'mizzey-nav-done' );
		}
	}
	d.addEventListener( 'click', function ( e ) {
		var a = e.target.closest && e.target.closest( 'a[href]' );
		if ( a && ! ( e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || a.hasAttribute( 'download' ) || ( a.target && a.target !== '_self' ) ) ) {
			nav( e, a.href );
		}
	} );
	d.addEventListener( 'submit', function ( e ) {
		if ( ! e.target.target ) {
			nav( e, e.target.action );
		}
	} );
	addEventListener( 'pageswap', end );
	addEventListener( 'pagehide', end );
	addEventListener( 'pageshow', end );
	function ready() {
		return root.hasAttribute( 'data-mizzey-ready' );
	}
	function button( e ) {
		var t = e.target && e.target.closest ? e.target.closest( 'button[data-wp-on--click]' ) : null;
		return t && t.type === 'button' && ! t.disabled ? t : null;
	}
	function act( t ) {
		if ( ! ready() && ! off ) {
			held = t;
			return;
		}
		own = 1;
		try {
			t.focus( { preventScroll: true, focusVisible: false } );
			t.click();
		} finally {
			own = 0;
		}
	}
	d.addEventListener( 'pointerdown', function ( e ) {
		drop = 0;
		down = ! off && e.pointerType === 'touch' && e.isPrimary ? { t: button( e ), id: e.pointerId, x: e.clientX, y: e.clientY, at: e.timeStamp } : null;
	}, true );
	d.addEventListener( 'pointercancel', function () {
		down = null;
	}, true );
	d.addEventListener( 'pointerup', function ( e ) {
		var p = down;
		down = null;
		if ( ! p || ! p.t || p.id !== e.pointerId || button( e ) !== p.t || Math.abs( e.clientX - p.x ) + Math.abs( e.clientY - p.y ) >= 10 || e.timeStamp - p.at >= 500 ) {
			return;
		}
		drop = e.timeStamp + 1000;
		act( p.t );
	}, true );
	d.addEventListener( 'click', function ( e ) {
		var t;
		if ( own ) {
			return;
		}
		if ( drop && e.timeStamp < drop ) {
			drop = 0;
			e.preventDefault();
			e.stopPropagation();
			return;
		}
		drop = 0;
		t = ! ready() && ! off && button( e );
		if ( t ) {
			e.preventDefault();
			e.stopPropagation();
			held = t;
		}
	}, true );
	function replay() {
		var t = held;
		held = null;
		if ( t && t.isConnected ) {
			act( t );
		}
	}
	d.addEventListener( 'mizzey:ready', function () {
		off = 0;
		replay();
	} );
	addEventListener( 'load', function () {
		setTimeout( function () {
			if ( ! ready() ) {
				off = 1;
				replay();
			}
		}, 1500 );
	} );
} )( document, location );

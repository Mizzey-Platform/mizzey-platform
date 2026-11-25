/**
 * Behaviour of the base components (spec 066, slice 1), on the WordPress Interactivity API and nothing else.
 *
 * Overlays are native modal dialogs: showModal() makes the page behind them inert and gives the top layer and the
 * scrim (::backdrop); Escape closes them natively. This store adds what the platform leaves open: focus moves to the
 * first control on open, Tab and Shift+Tab stay inside, a press on the scrim closes, and focus returns to the
 * control that opened the overlay. Tabs follow the keyboard pattern of the ARIA practices (arrows mirror in
 * Arabic, Home and End), a tooltip is dismissed with Escape, the switch toggles, and the select is our own list.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled]):not([type="hidden"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

const openers = new WeakMap();

/* The controls Tab reaches, in order: visible ones, and one radio per group (the checked one, or the first). */
function focusables( root ) {
	const seen = new Set();
	return [ ...root.querySelectorAll( FOCUSABLE ) ].filter( ( el ) => {
		if ( el.getClientRects().length === 0 ) {
			return false;
		}
		if ( el.type !== 'radio' || ! el.name ) {
			return true;
		}
		if ( seen.has( el.name ) ) {
			return false;
		}
		const group = [ ...root.querySelectorAll( `input[type="radio"][name="${ CSS.escape( el.name ) }"]` ) ];
		const chosen = group.find( ( radio ) => radio.checked ) || group[ 0 ];
		if ( chosen === el ) {
			seen.add( el.name );
			return true;
		}
		return false;
	} );
}

const isRtl = ( el ) => getComputedStyle( el ).direction === 'rtl';

function activateTab( tab ) {
	const list = tab.closest( '[role="tablist"]' );
	const tabs = [ ...list.querySelectorAll( '[role="tab"]' ) ];
	tabs.forEach( ( item ) => {
		const selected = item === tab;
		item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
		item.tabIndex = selected ? 0 : -1;
		const panel = document.getElementById( item.getAttribute( 'aria-controls' ) );
		if ( panel ) {
			panel.hidden = ! selected;
		}
	} );
	list.style.setProperty( '--mizzey-tab-index', String( tabs.indexOf( tab ) ) );
}

function selectParts( el ) {
	const root = el.closest( '.mizzey-select' );
	return {
		root,
		button: root.querySelector( '.mizzey-select__button' ),
		list: root.querySelector( '[role="listbox"]' ),
		options: [ ...root.querySelectorAll( '[role="option"]' ) ],
		input: root.querySelector( 'input[type="hidden"]' ),
		value: root.querySelector( '.mizzey-select__value' ),
	};
}

function setActive( parts, index ) {
	const count = parts.options.length;
	const next = ( ( index % count ) + count ) % count;
	parts.options.forEach( ( option, i ) => option.classList.toggle( 'is-active', i === next ) );
	parts.button.setAttribute( 'aria-activedescendant', parts.options[ next ].id );
	parts.options[ next ].scrollIntoView( { block: 'nearest' } );
	return next;
}

function openList( parts, open ) {
	parts.list.hidden = ! open;
	parts.button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	if ( open ) {
		const selected = parts.options.findIndex( ( o ) => o.getAttribute( 'aria-selected' ) === 'true' );
		setActive( parts, Math.max( 0, selected ) );
	} else {
		parts.button.removeAttribute( 'aria-activedescendant' );
	}
}

function choose( parts, option ) {
	parts.options.forEach( ( o ) => o.setAttribute( 'aria-selected', o === option ? 'true' : 'false' ) );
	parts.value.textContent = option.dataset.label || option.textContent.trim();
	if ( parts.input ) {
		parts.input.value = option.dataset.value || '';
		parts.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
	openList( parts, false );
	parts.button.focus();
}

store( 'mizzey/ui', {
	actions: {
		/* Overlays. */
		open: withSyncEvent( ( event ) => {
			const { target } = getContext();
			const dialog = document.getElementById( target );
			if ( ! dialog || dialog.open ) {
				return;
			}
			event.preventDefault();
			openers.set( dialog, event.currentTarget );
			dialog.showModal();
			const first = dialog.querySelector( '[autofocus]' ) || focusables( dialog )[ 0 ];
			if ( first ) {
				first.focus();
			}
		} ),
		close() {
			const { ref } = getElement();
			const dialog = ref.closest( 'dialog' );
			if ( dialog && dialog.open ) {
				dialog.close();
			}
		},
		backdrop: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			if ( event.target === ref ) {
				ref.close();
			}
		} ),
		trap: withSyncEvent( ( event ) => {
			if ( event.key !== 'Tab' ) {
				return;
			}
			const { ref } = getElement();
			const items = focusables( ref );
			if ( ! items.length ) {
				event.preventDefault();
				return;
			}
			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			const inside = ref.contains( document.activeElement );
			if ( event.shiftKey && ( document.activeElement === first || ! inside ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && ( document.activeElement === last || ! inside ) ) {
				event.preventDefault();
				first.focus();
			}
		} ),
		restore() {
			const { ref } = getElement();
			const opener = openers.get( ref );
			openers.delete( ref );
			if ( opener && opener.isConnected ) {
				opener.focus();
			}
		},

		/* Notices: the close control removes its notice. */
		dismiss() {
			const notice = getElement().ref.closest( '.mizzey-notice, .mizzey-sitewide' );
			if ( notice ) {
				notice.remove();
			}
		},

		/* Tabs. */
		selectTab: withSyncEvent( ( event ) => {
			activateTab( event.currentTarget );
		} ),
		tabKey: withSyncEvent( ( event ) => {
			const tab = event.currentTarget;
			const tabs = [ ...tab.closest( '[role="tablist"]' ).querySelectorAll( '[role="tab"]' ) ];
			const index = tabs.indexOf( tab );
			const forward = isRtl( tab ) ? 'ArrowLeft' : 'ArrowRight';
			const back = isRtl( tab ) ? 'ArrowRight' : 'ArrowLeft';
			const moves = {
				[ forward ]: index + 1,
				[ back ]: index - 1,
				Home: 0,
				End: tabs.length - 1,
			};
			if ( ! ( event.key in moves ) ) {
				return;
			}
			event.preventDefault();
			const next = tabs[ ( moves[ event.key ] + tabs.length ) % tabs.length ];
			activateTab( next );
			next.focus();
		} ),

		/* Tooltips: Escape hides the tooltip without moving focus; leaving the control resets it. */
		tipKey: withSyncEvent( ( event ) => {
			if ( event.key === 'Escape' ) {
				getElement().ref.classList.add( 'is-dismissed' );
			}
		} ),
		tipReset() {
			getElement().ref.classList.remove( 'is-dismissed' );
		},

		/* Switch. */
		toggle() {
			const { ref } = getElement();
			ref.setAttribute( 'aria-checked', ref.getAttribute( 'aria-checked' ) === 'true' ? 'false' : 'true' );
		},

		/* Select: our own list, the select-only combobox pattern. */
		selectToggle() {
			const parts = selectParts( getElement().ref );
			openList( parts, parts.list.hidden );
		},
		selectKey: withSyncEvent( ( event ) => {
			const parts = selectParts( event.currentTarget );
			const open = ! parts.list.hidden;
			const active = parts.options.findIndex( ( o ) => o.classList.contains( 'is-active' ) );
			switch ( event.key ) {
				case 'ArrowDown':
				case 'ArrowUp':
					event.preventDefault();
					if ( ! open ) {
						openList( parts, true );
					} else {
						setActive( parts, active + ( event.key === 'ArrowDown' ? 1 : -1 ) );
					}
					break;
				case 'Home':
				case 'End':
					if ( open ) {
						event.preventDefault();
						setActive( parts, event.key === 'Home' ? 0 : parts.options.length - 1 );
					}
					break;
				case 'Enter':
				case ' ':
					event.preventDefault();
					if ( open && active >= 0 ) {
						choose( parts, parts.options[ active ] );
					} else {
						openList( parts, true );
					}
					break;
				case 'Escape':
					if ( open ) {
						event.preventDefault();
						event.stopPropagation();
						openList( parts, false );
					}
					break;
				case 'Tab':
					openList( parts, false );
					break;
			}
		} ),
		selectChoose: withSyncEvent( ( event ) => {
			const option = event.target.closest( '[role="option"]' );
			if ( option ) {
				choose( selectParts( option ), option );
			}
		} ),
		selectOutside: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			if ( ! ref.contains( event.target ) ) {
				const parts = selectParts( ref.querySelector( '.mizzey-select__button' ) );
				if ( ! parts.list.hidden ) {
					openList( parts, false );
				}
			}
		} ),
	},
} );

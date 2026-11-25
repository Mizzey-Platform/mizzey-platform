/**
 * Behaviour of the base components (spec 066, slice 1), on the WordPress Interactivity API and nothing else.
 *
 * Overlays are native modal dialogs: showModal() moves focus in, makes the page behind inert, gives the top layer
 * and the scrim (::backdrop), closes on Escape and returns focus to the opener, so this store adds only what the
 * platform leaves open: a press on the scrim closes (when it also started there). The page behind does not scroll
 * while one is open (components.css). Tabs follow the keyboard pattern of the ARIA practices (arrows mirror in
 * Arabic, Home and End), a tooltip is dismissed with Escape, the switch toggles, and the select is our own list.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const pressedOnScrim = new WeakMap();

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
		/* Overlays. showModal() does the rest: it moves focus in, makes the page behind inert, closes on Escape and
		   gives focus back to the control that opened it. */
		open: withSyncEvent( ( event ) => {
			const { target } = getContext();
			const dialog = document.getElementById( target );
			if ( ! dialog || dialog.open ) {
				return;
			}
			event.preventDefault();
			dialog.showModal();
		} ),
		close() {
			const { ref } = getElement();
			const dialog = ref.closest( 'dialog' );
			if ( dialog && dialog.open ) {
				dialog.close();
			}
		},
		/* A press on the scrim closes, only when it starts there too: a text selection dragged out of a field onto
		   the scrim ends with a click on the dialog, and must not close it. */
		scrimDown: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			pressedOnScrim.set( ref, event.target === ref );
		} ),
		backdrop: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const started = pressedOnScrim.get( ref );
			pressedOnScrim.delete( ref );
			if ( event.target === ref && started ) {
				ref.close();
			}
		} ),

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

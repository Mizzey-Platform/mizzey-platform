/**
 * The refund reference field on the order screen's refund panel (specs/029-refunds, ADM-124).
 *
 * WooCommerce's refund panel has no hook inside its form, so the field is added after the reason row, and its value
 * is added to WooCommerce's own refund request. The server keeps it only for a refund made by hand, and only for
 * staff with the refund permission.
 */
( function ( $, cfg ) {
	'use strict';

	if ( ! $ || ! cfg ) {
		return;
	}

	function addField() {
		var reason = $( 'input#refund_reason' );
		if ( ! reason.length || $( '#' + cfg.field ).length ) {
			return;
		}
		var row = $( '<tr class="mizzey-refund-reference-row"><td class="label"><label></label></td><td class="total"><input type="text" autocomplete="off" /><div class="clear"></div></td></tr>' );
		row.find( 'label' ).attr( 'for', cfg.field ).attr( 'title', cfg.tip ).text( cfg.label );
		row.find( 'input' ).attr( { id: cfg.field, name: cfg.field, maxlength: cfg.max } );
		reason.closest( 'tr' ).after( row );
	}

	$( addField );
	// The order items panel is redrawn after items change and after a refund; the field is added again each time.
	$( document ).on( 'click', 'button.refund-items', addField );
	$( document.body ).on( 'order-totals-recalculate-complete', addField );

	$.ajaxPrefilter( function ( options ) {
		if ( typeof options.data !== 'string' || options.data.indexOf( 'action=woocommerce_refund_line_items' ) === -1 ) {
			return;
		}
		var value = $( '#' + cfg.field ).val();
		if ( value ) {
			options.data += '&' + encodeURIComponent( cfg.field ) + '=' + encodeURIComponent( String( value ).slice( 0, cfg.max ) );
		}
	} );
}( window.jQuery, window.mizzeyRefundReference ) );

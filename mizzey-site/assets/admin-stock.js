/**
 * Stock in the admin is the stock source's (D-19 item 5; specs/026-admin-stock): on the product screens the stored
 * stock fields are hidden and every stock control disabled, so none is sent. The server refuses a change regardless.
 */
(function ($) {
	'use strict';

	var fields = [
		'#_stock', '#_stock_status', '#_manage_stock', '#_backorders', '#_low_stock_amount',
		'[name^="variable_stock"]', '[name^="variable_manage_stock"]', '[name^="variable_backorders"]',
		'[name^="variable_stock_status"]', '[name^="variable_low_stock_amount"]',
		'.inline-edit-row [name="_stock"]', '.inline-edit-row [name="_stock_status"]', '.inline-edit-row [name="_manage_stock"]',
		'.inline-edit-row [name="_backorders"]', '.inline-edit-row [name="change_stock"]',
		'.inline-edit-row [name="_stock_qty"]'
	].join(',');
	var hidden = ['._stock_field', '.stock_fields', '.variable_stock0_field'].join(',');

	function lock() {
		$(fields).prop('disabled', true).attr('aria-disabled', 'true');
		$(hidden).hide();
		$('.woocommerce_variation .form-row').filter(function () {
			return $(this).find('[name^="variable_stock"]').length > 0;
		}).hide();
	}

	$(lock);
	$(document).on('woocommerce_variations_loaded woocommerce_variations_added', lock);
	$('#the-list').on('click', '.editinline', function () {
		setTimeout(lock, 0);
	});
	$('#doaction, #doaction2').on('click', function () {
		setTimeout(lock, 0);
	});
}(jQuery));

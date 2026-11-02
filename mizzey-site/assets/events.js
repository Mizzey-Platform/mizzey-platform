/*
 * The commerce events, pushed to the data layer for Tag Manager (EVT-01 to EVT-16, EVT-18; specs/044-commerce-events).
 *
 * - The page's own events and anything the store queued come in window.mizzeyEvents.
 * - Events the store queues while the page is open (the blocks' cart changes) arrive in the Store API's cart answer
 *   (extensions["mizzey-events"]) and are pushed as they come.
 * - A product card clicked, a shipping or a payment method chosen are heard from WooCommerce's blocks.
 * Each queued event has an id and is pushed once, even across pages.
 */
(function () {
	var config = window.mizzeyEvents || { language: 'en', currency: 'EGP', events: [] };
	var dataLayer = window.dataLayer = window.dataLayer || [];
	var seen = {};
	try { seen = JSON.parse(window.sessionStorage.getItem('mizzeyEventsSeen') || '{}') || {}; } catch (e) { seen = {}; }

	function remember(id) {
		seen[id] = 1;
		try { window.sessionStorage.setItem('mizzeyEventsSeen', JSON.stringify(seen)); } catch (e) { /* private mode */ }
	}

	function push(event) {
		if (!event || !event.event) {
			return;
		}
		if (event.id) {
			if (seen[event.id]) {
				return;
			}
			remember(event.id);
		}
		var entry = { event: event.event, language: config.language };
		var params = event.params || {};
		Object.keys(params).forEach(function (key) { entry[key] = params[key]; });
		if (event.ecommerce) {
			dataLayer.push({ ecommerce: null });
			entry.ecommerce = event.ecommerce;
		}
		dataLayer.push(entry);
	}

	(config.events || []).forEach(push);

	// A product or cart line as the Store API gives it, as the tracking plan names its fields.
	function item(product, quantity, listName) {
		var prices = product.prices || {};
		var unit = Math.pow(10, prices.currency_minor_unit || 0);
		var out = {
			item_id: product.sku || String(product.id),
			item_name: product.name,
			price: prices.price ? parseInt(prices.price, 10) / unit : 0,
			quantity: quantity || product.quantity || 1
		};
		if (product.categories && product.categories.length) {
			out.item_category = product.categories[0].name;
		}
		if (product.variation && product.variation.length) {
			out.item_variant = product.variation.map(function (v) { return v.value; }).join(', ');
		}
		if (listName) {
			out.item_list_name = listName;
		}
		return out;
	}

	function cart() {
		var data = window.wp && window.wp.data;
		var store = data && data.select('wc/store/cart');
		return store && store.getCartData ? store.getCartData() : null;
	}

	function cartPayload(extra) {
		var c = cart();
		var items = c ? (c.items || []).map(function (line) { return item(line, line.quantity); }) : [];
		var totals = c && c.totals ? c.totals : {};
		var unit = Math.pow(10, totals.currency_minor_unit || 0);
		var payload = { currency: config.currency, value: totals.total_items ? parseInt(totals.total_items, 10) / unit : 0, items: items };
		Object.keys(extra || {}).forEach(function (key) { payload[key] = extra[key]; });
		return payload;
	}

	// Events queued by the store, in the cart's answer.
	if (window.wp && window.wp.data && window.wp.data.subscribe) {
		window.wp.data.subscribe(function () {
			var c = cart();
			var queued = c && c.extensions && c.extensions['mizzey-events'] ? c.extensions['mizzey-events'].events : null;
			(queued || []).forEach(push);
		});
	}

	// Choices made in the page, from WooCommerce's blocks.
	if (window.wp && window.wp.hooks) {
		var hooks = window.wp.hooks;
		var prefix = 'experimental__woocommerce_blocks-';
		hooks.addAction(prefix + 'product-view-link', 'mizzey-events', function (args) {
			if (args && args.product) {
				push({ event: 'select_item', ecommerce: { item_list_name: args.listName || '', items: [item(args.product, 1, args.listName)] } });
			}
		});
		hooks.addAction(prefix + 'checkout-set-selected-shipping-rate', 'mizzey-events', function (args) {
			push({ event: 'add_shipping_info', ecommerce: cartPayload({ shipping_tier: args && args.shippingRateId ? String(args.shippingRateId) : '' }) });
		});
		hooks.addAction(prefix + 'checkout-set-active-payment-method', 'mizzey-events', function (args) {
			push({ event: 'add_payment_info', ecommerce: cartPayload({ payment_type: args && args.paymentMethodSlug ? String(args.paymentMethodSlug) : '' }) });
		});
	}
})();

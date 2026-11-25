/*
 * Prices drawn by WooCommerce's blocks (cart, checkout, mini cart) read as the boards draw them, like the prices the
 * server writes: no ".00" on a whole pound ("EGP 5,099"), piasters kept ("EGP 99.50"), and a non-breaking space between
 * the symbol and the amount. The blocks always pad the decimals and have no setting for it, so each amount they draw
 * is rewritten in place, as text only: the amounts, the totals and what the Store API holds are not touched.
 * See CurrencyFormat.php (owner ruling of 25 November 2026, specs/066).
 */
(function () {
	var MONEY = '.wc-block-formatted-money-amount, .wc-block-components-formatted-money-amount, .wc-block-mini-cart__amount';
	var WHOLE = /(\d)\.0+(?!\d)/g;

	// Text nodes are rewritten one by one, so the nodes the blocks hold stay the ones in the page.
	function tidy(el) {
		var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
		for (var node = walker.nextNode(); node; node = walker.nextNode()) {
			var next = node.data.replace(WHOLE, '$1').replace(/ /g, '\u00a0');
			if (next !== node.data) {
				node.data = next;
			}
		}
	}

	function sweep(root) {
		if (root.nodeType !== 1) {
			root = root.parentElement;
		}
		if (!root) {
			return;
		}
		var own = root.closest(MONEY);
		if (own) {
			tidy(own);
		}
		var list = root.querySelectorAll(MONEY);
		for (var i = 0; i < list.length; i++) {
			tidy(list[i]);
		}
	}

	function start() {
		sweep(document.body);
		new MutationObserver(function (changes) {
			for (var i = 0; i < changes.length; i++) {
				var change = changes[i];
				if (change.type === 'characterData') {
					sweep(change.target);
				} else {
					for (var j = 0; j < change.addedNodes.length; j++) {
						sweep(change.addedNodes[j]);
					}
					var own = change.target.nodeType === 1 && change.target.closest(MONEY);
					if (own) {
						tidy(own);
					}
				}
			}
		}).observe(document.body, { childList: true, subtree: true, characterData: true });
	}

	if (document.body) {
		start();
	} else {
		document.addEventListener('DOMContentLoaded', start);
	}
})();

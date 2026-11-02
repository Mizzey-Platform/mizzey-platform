/*
 * The consent banner (MKT-10): shown until the visitor chooses, reopened from any element with
 * data-mizzey-consent-open. The choice is kept for 180 days and sent to Google's consent mode at once.
 * See specs/043-measurement-tools.
 */
(function () {
	var banner = document.getElementById('mizzey-consent');
	if (!banner) {
		return;
	}
	var chosen = /(?:^|; )mizzey_consent=(granted|denied)/.test(document.cookie);
	function show() { banner.hidden = false; }
	function choose(value) {
		var secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = 'mizzey_consent=' + value + '; path=/; max-age=15552000; SameSite=Lax' + secure;
		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', {
				ad_storage: value,
				ad_user_data: value,
				ad_personalization: value,
				analytics_storage: value
			});
		}
		// WooCommerce's campaign capture follows the choice at once, without waiting for the next page.
		if (window.wc_order_attribution && typeof window.wc_order_attribution.setOrderTracking === 'function') {
			window.wc_order_attribution.setOrderTracking(value === 'granted');
		}
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: 'mizzey_consent', consent: value });
		banner.hidden = true;
	}
	banner.querySelector('[data-mizzey-consent="granted"]').addEventListener('click', function () { choose('granted'); });
	banner.querySelector('[data-mizzey-consent="denied"]').addEventListener('click', function () { choose('denied'); });
	document.addEventListener('click', function (e) {
		if (e.target && e.target.closest && e.target.closest('[data-mizzey-consent-open]')) {
			e.preventDefault();
			show();
		}
	});
	if (!chosen) {
		show();
	}
})();

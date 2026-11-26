/* A guest's view of a product, recorded by the page once it has loaded (specs/015, WISH-07), so the product page
   itself sets no cookie and can be served from the page cache. Printed inline by MizzeySite\Wishlist\RecentlyViewed,
   with __DATA__ replaced by the route's address and the product's id. */
(function (d) {
	if (!window.fetch) return;
	var send = function () {
		fetch(d.url, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: true,
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ id: d.id })
		}).catch(function () {});
	};
	if (document.readyState === 'complete') send();
	else window.addEventListener('load', send);
})(__DATA__);

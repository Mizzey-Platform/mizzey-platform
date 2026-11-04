/* The chosen language on a page served from a page cache (specs/052): the same page in the chosen language, from the
   cookie and the page's own hreflang alternates. Printed inline by MizzeySite\I18n\LanguagePreference. */
(function () {
	var m = document.cookie.match(/(?:^|; )__COOKIE__=(en|ar)(?:;|$)/);
	if (!m) return;
	var c = m[1];
	var h = (document.documentElement.lang || '').slice(0, 2);
	if (!h || h === c) return;
	var l = document.querySelector('link[rel="alternate"][hreflang^="' + c + '"]');
	if (!l || !l.href) return;
	var u = new URL(l.href);
	var q = new URLSearchParams(location.search);
	q.delete('__COOKIE__');
	q.delete('lang');
	q.forEach(function (v, k) { u.searchParams.set(k, v); });
	if (u.href !== location.href) location.replace(u.href + location.hash);
})();

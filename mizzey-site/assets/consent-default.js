/*
 * Consent defaults for Google's consent mode, before any tag loads (MKT-10). Everything is denied until the visitor
 * chooses; a choice already made is read from the mizzey_consent cookie. See specs/043-measurement-tools.
 */
window.dataLayer = window.dataLayer || [];
function gtag() { window.dataLayer.push(arguments); }
(function () {
	var match = document.cookie.match(/(?:^|; )mizzey_consent=(granted|denied)/);
	var value = match && match[1] === 'granted' ? 'granted' : 'denied';
	gtag('consent', 'default', {
		ad_storage: value,
		ad_user_data: value,
		ad_personalization: value,
		analytics_storage: value,
		wait_for_update: 500
	});
	if (window.mizzeyLanguage) {
		window.dataLayer.push({ language: window.mizzeyLanguage });
	}
})();

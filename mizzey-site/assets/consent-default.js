/*
 * Consent defaults for Google's consent mode, before any tag loads (MKT-10). Everything is denied until the visitor
 * chooses; a choice already made is read from the mizzey_consent cookie and pushed as the mizzey_consent event.
 * See specs/043-measurement-tools.
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
	// The visitor's standing choice, on every page as well as when it is made (consent.js), so a tag that is not Google's
	// (the Meta and TikTok pixels) can wait for it with a trigger on this event (tracking plan, section 8).
	window.dataLayer.push({ event: 'mizzey_consent', consent: value });
	if (window.mizzeyLanguage) {
		window.dataLayer.push({ language: window.mizzeyLanguage });
	}
})();

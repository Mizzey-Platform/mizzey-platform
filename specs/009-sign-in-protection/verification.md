# Verification: sign-in protection, automated account creation and sessions

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 30 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0 (block checkout), WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0. Browser cases in
Playwright's Chromium.

| Evidence | What it shows |
|---|---|
| `evidence/before.txt` | t46 to t49 against the site code on `main`: t46, t47 and t49 fail. t48 passes, because it verifies WordPress's own session behaviour, which this feature documents and does not change. Recorded after the code was written, with the final scenarios |
| `evidence/mutation.txt` | Five deliberate defects (an account permit outliving its handler, no minimum token age, an always-empty checkout trap, the sign-in row ignoring a hidden default address, an untranslated lockout message), each caught by its scenario |
| `evidence/clean-baseline.txt` | The runtime reset to the clean baseline before the suite (exit 0) |
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially: 45 scenarios, 39 pass, 6 fact-finding, 0 failed |

After the suite, CoreX's login protection and WP 2FA's policy are as they were found (off, not enforced): each scenario
restores what it changed.

| AC | Scenario | Result |
|---|---|---|
| AC-1 | t46, t49 | Pass. Over HTTP, on `wp-login.php` and the account page in English and Arabic: five wrong passwords lock that identity and address, the right password is then refused with the translated message, and another identity signs in. Username and email count apart, case and spaces count together, an untrusted forwarding header changes nothing, and behind a trusted proxy another address is unaffected. A lockout ends on its own with protection still on, and WP 2FA's challenge completes with the limit on. In Chromium, every sign-in link the home, account and checkout pages print resolves in both languages, and so do the account sign-in, sign-out, lost password, the reset link, `wp-login.php`, CoreX's address and the two-factor sign-in |
| AC-2 | t47, t49 | Pass. On the account page (both languages), the block checkout, the order-received offer and WordPress registration (opened for one case), requests the page did not produce create no account: no, malformed, array, oversized, tampered, future, wrong-route, spent, too fresh or expired token, or a filled trap. The checkout refuses before its handler runs: no new order, and a session retry order is unchanged until a valid request places it. The classic checkout's handlers create no account. Staff, `create_customers` and WP-CLI keep native behaviour. In Chromium, each form a person submits creates the account, and a filled trap reaches the server and is refused |
| AC-3 | t48 | Pass. Both cookies are HttpOnly. Sign-in lasts 2 days, or 14 with "remember me"; without it the cookies are session-only, and with it they expire 12 hours after the sign-in does. An expired server session, signing out and a password change each end the earlier cookies, while another session and the changer's replacement cookies keep working. Secure is not applicable on the HTTP runtime |
| AC-4 | t41, t46, t47 | Pass. The check reports the four rows; each says review when its protection is removed or off; the runtime without the sign-in baseline reports `sign_in_limit` as review |

Unit and repository checks: `python -m unittest discover -s tools/tests -t .`, `repo_checks.py`, `discovery.check`,
`house_rules.py` and `scope_trace.py`, as reported in the pull request.

## 2. Staging

Not done. T017 waits for the owner's approval: deploy, apply the sign-in baseline, verify that token-bearing pages are
not served from a cache in either language, that both cookies are Secure over HTTPS, and run the browser flows there.
The trusted-proxy setting, pending with the server changes, decides whether the limit separates visitors behind the
proxy. The person's own staging review (DOD-04) is separate from any automated check.

## 3. Client acceptance

Not done. T018, through MS-UAT-2026-027 (DOD-09).

# Implementation Plan: sign-in protection, automated account creation and sessions

**Branch**: `009-sign-in-protection` | **Date**: 2026-10-30 | **Spec**: [spec.md](spec.md)

## Summary

CoreX's login protection, off by default, provides the limit and the lockout; a Developer command turns it on once with
the documented settings, keeping WordPress's default sign-in address served. Mizzey adds a signup check: a signed,
route-bound, expiring token issued with each account-creating page, a minimum time since issue, and a trap field,
enforced where WooCommerce and WordPress create accounts. Sessions stay WordPress's, documented and verified. The
security check gains the rows.

## Technical Context

| | |
|---|---|
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0 (block checkout), WPML 4.9.7, CoreX v0.47.0 |
| Storage | CoreX's option `corex_login_protection_settings` and its attempts table. Nothing of Mizzey's: the token is an HMAC over WordPress's salts |
| Dependencies | None new |
| Testing | Real HTTP for sign-in, lockout, the three account routes and cookies. CoreX attempts are cleared after each case through its own store or `wp corex security reset-login`'s lockout release, scoped to the scenario's identities. Registration settings (`woocommerce_enable_myaccount_registration`, `woocommerce_enable_signup_and_login_from_checkout`, the order-confirmation account offer) are switched on for the scenario and restored |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | AUTH-15 only. Registration screens (AUTH-01), guest conversion (AUTH-07) and Google sign-in are later #305 slices |
| M-4 native first | Yes | CoreX's lockout and WordPress's sessions are used as they are; Mizzey adds only the account-creation check, which nothing provides for Woo routes |
| One authority (ADR-0003) | Yes | CoreX's settings are the owner's after the one-time command |
| M-9 languages | Yes | Every message the visitor sees is translated; the lockout message is supplied in Arabic |

## Design

| Piece | Responsibility |
|---|---|
| `src/Security/Cli/SecurityCommand.php` | `sign-in-baseline` (FR-001): reads `LoginProtectionSettingsStore::current()`, replaces the four owned fields, saves through the store's `save()`, reads back and verifies |
| `src/Security/SignInMessages.php` | The `corex_login_locked` error keeps its code and gets Mizzey's translated message (CoreX ships no Arabic) |
| `src/Security/SignupCheck.php` | FR-003, FR-004. `issue(route)`: a token `base64(route|issued|nonce|hmac)`, the HMAC keyed by `wp_salt('nonce')`. `verify(route, token, trap)`: refuses a missing, malformed, foreign-route, expired (over 2 hours) or too-recent (under 3 seconds) token, or a filled trap. Enforced at `woocommerce_registration_errors` (every WooCommerce route reaches `wc_create_new_customer()`) and `registration_errors` (WordPress). The route is told from the request: the Store API checkout (route captured at `rest_request_before_callbacks` for `/wc/store/v1/checkout`, token from the request's `extensions.mizzey-signup`), the order-received account offer (`create-account` POST), the account page's registration (`woocommerce-register-nonce`), WordPress's registration (`registration_errors`). Any other caller of `wc_create_new_customer()` from an anonymous request is refused as an unknown route; signed-in users with `create_users`, and WP-CLI, pass unchecked |
| Rendering | Account page: hidden token and trap fields on `woocommerce_register_form`. WordPress registration: the same on `register_form`. Order-received account offer: the same fields added inside its form via `render_block_woocommerce/order-confirmation-create-account`. Block checkout: a small script, enqueued only on the checkout page, registers the token with `wp.data.dispatch('wc/store/checkout').setExtensionData('mizzey-signup', …)` (public in WooCommerce 11.1.0), with the Store API schema registered through `woocommerce_store_api_register_endpoint_data` |
| `src/Security/BaselineReport.php` | FR-006: `sign_in_limit` (CoreX enabled, the settings, default addresses served) replaces the `corex_login_protection` fact as a control; `signup_check` (each enforcement and rendering hook present, and a self-test that an unsigned token fails and a signed one of the right age passes); `wordpress_registration` (closed, or open with the hooks). All three in `CONTROLS` |
| `docs/security-baseline.md` | FR-005, FR-007: the session policy, the limit and its settings, the signup method, recovery from a lockout |

The trap field is a text input with an ordinary-looking name, hidden from people with CSS and `aria-hidden`, excluded
from autofill (`autocomplete="off"`, `tabindex="-1"`). It is labelled for screen readers as not to be filled. The
token is not secret from the visitor; it proves a page view of the right route at a plausible time.

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1 | `t46-sign-in-limit` | The command sets the four fields and keeps the rest. Over HTTP on `wp-login.php` and the account page in English and Arabic: five wrong passwords, then the right one is refused with the translated message; another account from the same address signs in; after the lockout is released the account signs in. An enrolled administrator completes the WP 2FA challenge with the baseline on. Every sign-in link printed on the home page, the account page and the checkout in both languages answers 200 |
| AC-2 | `t47-signup-check` | For each route with registration switched on: a request with no token, a filled trap, a token for another route, an expired token, or one submitted within 3 seconds creates no account and shows the translated error; a request carrying the token the page issued, after the minimum time, creates the account. The block checkout case goes through the Store API with and without `extensions.mizzey-signup`. WordPress's registration is opened for one case and closed again. An administrator creating a customer and a WP-CLI user creation pass |
| AC-3 | `t48-sessions` | Over HTTP: the auth cookie is `HttpOnly`; its expiry matches WordPress's two days, and fourteen with "remember me"; after sign-out and after a password change the old cookie no longer authenticates. `Secure` is asserted on staging (HTTPS), not on the HTTP runtime, where it is recorded as not applicable |
| AC-4 | `t46-sign-in-limit`, `t47-signup-check` | The check passes the three rows with the baseline; with CoreX's protection off, or default addresses hidden, `sign_in_limit` says review; with a hook removed, `signup_check` says review; with WordPress registration opened and the hooks removed, `wordpress_registration` says review |

## Risks

| Risk | Mitigation |
|---|---|
| CoreX's rewritten sign-in address breaks a link under WPML | Default addresses stay served; t46 fetches every printed sign-in link in both languages |
| The block checkout's extension data API differs in this Woo version | Confirmed against WooCommerce 11.1.0's source before building; the Store API case in t47 proves the server side independently of the script |
| A page cache serves one visitor's token to another | Tokens carry no identity and expire; account, checkout and order-received pages are not cached by WooCommerce's rules |
| A lockout on the shared runtime blocks the suite's own sign-ins | Scenarios use their own users and clear their attempts; `pilot_admin` is never used for failures |
| Shared proxy address on staging | Recorded; per-visitor once the trusted-proxy setting is approved |

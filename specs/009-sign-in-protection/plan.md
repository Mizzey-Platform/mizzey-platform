# Implementation Plan: sign-in protection, automated account creation and sessions

**Branch**: `009-sign-in-protection` | **Date**: 2026-10-30 | **Spec**: [spec.md](spec.md)

## Summary

CoreX's login protection, off by default, provides the limit and the lockout; a Developer command turns it on once with
the documented settings, keeping WordPress's default sign-in address served. Mizzey adds a signup verifier: a signed,
single-use, route-bound token issued with each account-creating page, a minimum age, and a trap field. It is enforced
early on the Store API checkout and before account insertion on every WooCommerce and WordPress registration route.
Sessions stay WordPress's, documented and verified. The security check gains the rows.

## Technical Context

| | |
|---|---|
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0 (block checkout), WPML 4.9.7, CoreX v0.47.0 |
| Storage | CoreX's option `corex_login_protection_settings` and its attempts table; one transient per spent token nonce |
| Dependencies | None new. Browser tests use the existing Playwright install (playwright-core 1.56.1, Chromium) |
| Testing | Real HTTP for sign-in, lockout, cookies and the request-level account cases; a real browser for the React forms (block checkout, after-purchase offer) and the WPML link flows. Disposable identities per case. Registration settings are switched on for a scenario and restored exactly |

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
| `src/Security/Cli/SecurityCommand.php` | `sign-in-baseline` (FR-001): reads `LoginProtectionSettingsStore::current()`, maps the settings object's camelCase properties to the store's snake_case input keys, changes the five fields, saves through `save()`, reads back in the same call and verifies every field, the untouched ones included |
| `src/Security/SignInMessages.php` | `corex_login_locked` keeps its code and gets Mizzey's translated message |
| `src/Security/SignupCheck.php` | The verifier (FR-005). Token: `v1.<base64url(json{p:"mizzey-signup",r:<route>,t:<int>,n:<16 random bytes hex>})>.<base64url(hmac)>`, HMAC-SHA256 over the payload part keyed by `wp_salt('nonce')`, compared with `hash_equals`. Input bounded to 512 bytes, strings only, strict base64 and JSON decoding, every field validated. Rejects a future timestamp, an age under 3 or over 7,200 seconds, another route, a spent nonce, or a filled trap. On success the nonce is recorded spent (`set_transient('mizzey_signup_' . n, 1, remaining validity)`) before the account is created. Routes are semantic ids: `account`, `checkout`, `order-received`, `wordpress`; language and localised paths are not part of them |
| `src/Security/SignupRoutes.php` | Route adapters (FR-003, FR-004). **Checkout**: at `rest_request_before_callbacks`, for the matched handler of POST `/wc/store/v1/checkout`, preserve an earlier response or error; when WooCommerce would create an account (signed out, `woocommerce_enable_signup_and_login_from_checkout`, and `woocommerce_enable_guest_checkout` off or `create_account` true by `rest_sanitize_boolean`), verify `extensions['mizzey-signup']` and return a 400 `WP_Error` on failure, before the callback creates or updates the draft order. The current request is held in a stack, popped at `rest_request_after_callbacks`, so nested dispatch cannot leak it. **Account page**: within WooCommerce's registration processing (`woocommerce_process_registration_errors`, reached only after its own `woocommerce-register-nonce` or `_wpnonce` check), verify route `account` from the POST fields. **Order-received**: WooCommerce processes the offer while rendering the `woocommerce/order-confirmation-create-account` block (`render_content()` calls `process_form_post()`, which checks its `wc_create_account` nonce, the order's customer and email before `wc_create_new_customer()`). The adapter marks that block's rendering between `pre_render_block` and `render_block` for that block name, and the guard verifies route `order-received` from the POST fields only inside that window. **WordPress**: `registration_errors`, route `wordpress`. **Guard**: at `woocommerce_registration_errors`, an insertion is allowed only when one adapter has verified the current request, the caller is WP-CLI, or the current user holds `create_customers` (WooCommerce) or `create_users`; anything else fails closed with the translated message |
| Rendering | Account page and WordPress registration: hidden token and trap fields printed on `woocommerce_register_form` and `register_form`. Block checkout: `assets/signup-check.js`, depending on `wp-data` and `wc-blocks-checkout`, enqueued on the checkout page only, calls `wp.data.dispatch('wc/store/checkout').setExtensionData('mizzey-signup', { token, trap })` after the store initialises and mirrors the trap input. The schema is registered with `woocommerce_store_api_register_endpoint_data()` for `CheckoutSchema::IDENTIFIER`, namespace `mizzey-signup`, writable string properties `token` and `trap`; registration does not enforce, the early validator does. Order-received: the block's PHP output is a mount, not the form, so the render filter on `woocommerce/order-confirmation-create-account` adds escaped token data to the wrapper, and `assets/signup-check.js` inserts the token and trap into the rendered form when it mounts (observing later mounts) and before submission |
| Inventory | `SignupRoutes::writers()` lists every known public account writer with its state: account page, block checkout, order-received offer, WordPress registration (checked when enabled); classic checkout (inactive: not the site's checkout; its insertions still reach the guard and fail closed); stock-notification signup (inactive unless `woocommerce_customer_stock_notifications_allow_signups` and `…_create_account_on_signup` are both yes; then reported as unchecked, because its failure path silently degrades to an email-only signup) |
| `src/Security/BaselineReport.php` | FR-007: `sign_in_limit`, `signup_check`, `account_writers`, `wordpress_registration`, all in `CONTROLS`. `signup_check` self-tests the verifier with a token it signs: a fresh one fails (too new), a backdated one passes once and then fails (spent); the probe nonce is removed afterwards |
| `docs/security-baseline.md` | FR-006, FR-008: the session policy, the limit and its settings, the signup method and its limits, recovery from a lockout |

The trap field is a text input with an ordinary-looking name, hidden from people with CSS and `aria-hidden`,
`autocomplete="off"` and `tabindex="-1"`, labelled for screen readers as one to leave empty.

The token authenticates server issuance for a route and an age. Being single-use, it cannot be replayed; it proves
neither a particular visitor's page view nor human interaction (spec: "The account-creation check, and what it does not
do").

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1 | `t46-sign-in-limit` | The command changes the five fields and keeps the rest. Over HTTP on `wp-login.php` and the account page in English and Arabic: five wrong passwords then the right one is refused with the translated message; a different identity from the same address signs in; the same identity from a different trusted address is unaffected; username and email are separate buckets; case and surrounding spaces normalise to one bucket; a spoofed `X-Forwarded-For` from an untrusted connection does not change the address. With the lockout period shortened for the scenario through CoreX's store, the identity signs in after it ends with protection still on. Ordinary scenarios never call `reset-login` or `releaseActiveLockouts()`: they use disposable identities and remove only their own attempt rows |
| FR-002 | `t46-sign-in-limit` (browser) | In Chromium, English and Arabic: the sign-in link from the home page, the account page and the checkout; `wp-login.php` and `/corex-login/`; WP 2FA's HTML challenge and REST challenge with a wrong code, retry and success; sign-out; lost password and the reset link. Each asserts destination, preserved query arguments, language and authentication outcome |
| AC-2 | `t47-signup-check` | Request level, for the account page and the order-received offer, and the Store API checkout: no token; malformed, array, oversized, tampered, future-dated, wrong-route and spent tokens; ages of 2 and 7,201 seconds against 3 and 7,200; a filled trap. Each creates no account and shows the translated error; native nonce failures are tested apart. Checkout failures leave no account, no new order, no payment attempt, no stock or coupon reservation, and an existing pending or failed retry order unchanged. Browser level: each route submitted through its page in English and Arabic creates the account, with the extension payload and trap verified; checkout with optional and with required registration, guest checkout and a signed-in customer pass without the check. Two tabs each succeed once. Native behaviour kept: an administrator and an enrolled shop manager creating a customer, WooCommerce REST customer creation with `create_customers`, authorised WordPress REST user creation, WP-CLI; unauthorised callers still refused. WordPress registration is opened for one case and closed again |
| AC-3 | `t48-sessions` | Over HTTP: the authentication and logged-in cookies are `HttpOnly`; their embedded expiry and the server session's follow `auth_cookie_expiration` (two days, fourteen with "remember me"); without "remember me" the browser cookie is session-only, with it its Expires is the authentication expiry plus twelve hours; WooCommerce's account creation requests "remember me". Sign-out rejects the old cookies while another independent session continues; after a password change, pre-change cookies are rejected and native replacement cookies are allowed. `Secure` is asserted on staging over HTTPS; on the HTTP runtime it is recorded as not applicable |
| AC-4 | `t46-sign-in-limit`, `t47-signup-check` | The check passes the four rows with the baseline; with CoreX off or default addresses hidden, `sign_in_limit` says review; with an adapter or a rendering hook removed, `signup_check` says review; with stock-notification account creation switched on, `account_writers` says review; with WordPress registration opened and its hook removed, `wordpress_registration` says review |

Settings scenarios snapshot and restore exactly what they change and run serially. The emergency `reset-login` is not
run on the shared runtime; its documented behaviour is checked against CoreX's source.

## Risks

| Risk | Mitigation |
|---|---|
| CoreX's URL filters send a WPML-prefixed sign-in link to an address it does not serve | Default addresses stay served; the browser flows in FR-002 decide. A failure there stops the baseline and raises a CoreX compatibility task |
| The block checkout or the after-purchase form changes its client API | Pinned WooCommerce 11.1.0; the browser cases prove the payload reaches the server; the request-level cases prove the server independently |
| A page cache serves a token-bearing page | Verified on staging for every token-bearing route in both languages and after back-navigation; a cached token is expired or spent, and the person gets a translated retry message |
| A lockout on the shared runtime blocks the suite's own sign-ins | Disposable identities; `pilot_admin` is never used for failures; only the scenario's attempt rows are removed |
| Shared proxy address on staging | Recorded; separated per visitor once the trusted-proxy setting is approved |
| A determined script that drives a page passes the check | Recorded as the method's limit; S-2 and S-3 offered |

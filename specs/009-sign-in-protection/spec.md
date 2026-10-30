# Feature Specification: sign-in protection, automated account creation and sessions

**Feature Branch**: `009-sign-in-protection`

**Created**: 2026-10-30

**Status**: Draft

**Work type**: requirement

**Input**: User description: "Start #305, AUTH-15 sign-in protection, with Codex reviewing" (the owner, 30 October 2026).
A slice of #305 (E-SF-7): AUTH-15 only. #305's design predecessors (#250, #253) block its storefront screens, not this
slice: it changes no screen, only what the existing sign-in and account-creating handlers accept, and it adds no visible
element. The owner started it on that basis.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| AUTH-15 | P1 [key] | S1 | Secure session handling, protection against automated account creation, and rate limiting on login attempts |

Story: Functional Specification MS-SPC-2026-032 v1.4, US-06-05 (Account security), the three criteria that trace to
AUTH-15: "Sessions are handled securely and expire per the documented policy"; "Login attempts are rate limited, and the
limit is a documented setting"; "Automated account creation is protected against: every route that creates an account,
including account creation at checkout, applies a check that a scripted submission fails. A registration that fails the
check creates no account, and the method used is documented". PRE-05 (Integrations, Accounts and Inputs): "No external
service is named in any source".

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| AUTH-01, AUTH-02, AUTH-07 | P1, P1-L, P1 | Registration, guest checkout and converting after purchase. Owned by #305, later slices, which open these routes. This slice protects each route whether or not it is open yet, and opens none |
| AUTH-16 | P1 | The reset link is single-use and time-limited. #305, a later slice; WordPress's reset keys behave so already |
| NFR-05 | P1 | Owned by #269. The security check and `SignInMessages` (spec 007) are extended |
| ADM-131 | P1 | Two-factor (spec 008). CoreX checks the lockout during password authentication and records failures announced through `wp_login_failed`, WP 2FA's provider failures included. Completing the challenge follows WP 2FA's own HTML and REST paths and its own attempt controls; CoreX does not independently enforce those paths. The challenge must keep working with the limit on |
| US-06-05, last criterion | | Password storage is WordPress's own (bcrypt since 6.8). Reported, not built here |

## Open contract items

None. No external service is named, so the account-creation check uses none: no CAPTCHA provider, no third-party
script, no cookie.

## The account-creation check, and what it does not do

The check rejects a submission that the page did not produce: one sent without a check token from that route, with a
token that is malformed, tampered, issued for another route, already used, expired or too fresh, or with the trap field
filled. Each token is single-use, so every account needs its own page visit at a human pace. This defeats generic form
bots and direct or replayed requests, which is what US-06-05's "a check that a scripted submission fails" is taken to
mean here.

It does not prove that a person is present. A script that loads the page, waits, and submits as a browser would passes
it, once per page load. Slowing such an attacker needs a per-address limit (S-2, after the server's trusted-proxy setting)
or a challenge service (S-3, an external service the owner would have to approve). This residual risk is recorded in
the handover documentation with the method.

## Contractual acceptance criteria and engineering checks [checked]

AC-1 to AC-3 express US-06-05's AUTH-15 criteria. AC-4 is an engineering safeguard, not a contractual criterion.

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Login attempts are rate limited on the WordPress sign-in page and on the account page, in both languages: after the documented number of failures for one submitted login identity (trimmed and lowercased) paired with one resolved address within the window, further attempts for that pair, even with the right password, are refused until the lockout period ends, with a translated message; other identities and other addresses are unaffected. The limit is a documented setting the owner can change | AUTH-15 | provisional |
| AC-2 | Every enabled public route that creates an account applies the check above before any account (and, at checkout, before any order processing) is created: the account page's registration, account creation at checkout, account creation after purchase, and WordPress's own registration if it is ever opened. A submission that fails it creates no account; a person using the page passes it. A public account writer without a checked route is reported, never counted as protected. The method and its limits are documented | AUTH-15 | provisional |
| AC-3 | Sessions are handled securely and expire per the documented policy: WordPress's authentication and logged-in cookies are HTTP-only, and secure under the verified HTTPS configuration; authentication lifetime and browser persistence follow the documented native policy; signing out invalidates the current session's cookies; a password change invalidates cookies issued before it (a self-service change may issue replacement cookies and keep the person signed in) | AUTH-15 | provisional |
| AC-4 | One command reports whether these protections are in force: the sign-in limit on with its settings and default addresses served; the check attached to each covered route; each known public account writer either checked, verified closed to account creation, or inactive; WordPress's registration closed or checked (an engineering safeguard supporting AUTH-15, not a contractual criterion) | AUTH-15 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Guessing a password stops quickly (Priority: High)

**Independent Test**: five wrong passwords for one identity from one address, then the right one, on each sign-in page;
the sixth attempt is refused, and another identity still signs in.

**Acceptance Scenarios**:

1. **Given** the baseline, **When** an identity receives five wrong passwords within five minutes from one address,
   **Then** the next attempt from that address, even with the right password, is refused until the lockout ends, with a
   translated message (AC-1).
2. **Given** that lockout, **When** a different identity signs in from the same address, **Then** it succeeds (AC-1).
3. **Given** that lockout, **When** its period ends, **Then** the identity signs in with protection still on (AC-1).
4. **Given** an enrolled administrator, **When** they sign in with the right password and code, **Then** the WP 2FA
   challenge completes on its HTML and REST paths, with protection on (AC-1, ADM-131 unchanged).

### User Story 2 - A script cannot open accounts by posting to the store (Priority: High)

**Independent Test**: on each account-creating route, a request the page did not produce creates no account; the
route used through its page in a real browser creates one.

**Acceptance Scenarios**:

1. **Given** registration open on the account page, **When** a request arrives without a valid token, with the trap
   filled, too soon, with a used token, or with another route's token, **Then** no account is created and a translated
   message is shown (AC-2).
2. **Given** account creation at checkout, **When** the checkout request lacks a valid check, **Then** the request is
   rejected before checkout order processing: no account, new order, payment attempt or stock or coupon reservation is
   created, and an existing retry order is unchanged (AC-2).
3. **Given** the order-received page's account offer, **When** it is posted without a valid check, **Then** no account
   is created (AC-2).
4. **Given** each route used through its page in a browser, **When** a person submits it, **Then** the account is
   created (AC-2).

### User Story 3 - Sessions behave as documented (Priority: Medium)

**Independent Test**: sign in, read both cookies, sign out and reuse the old cookies; change the password and reuse
the pre-change cookies.

**Acceptance Scenarios**:

1. **Given** a sign-in, **When** the cookies are set, **Then** they are HTTP-only, secure over HTTPS, and their
   authentication expiry follows the documented policy with and without "remember me" (AC-3).
2. **Given** a session, **When** the person signs out, **Then** the old cookies no longer authenticate; another
   independent session of the same person is unaffected (AC-3).
3. **Given** a session, **When** the password changes, **Then** cookies issued before the change no longer
   authenticate (AC-3).

### Edge Cases

- **Shared addresses.** This is an identity-and-address lockout, not an account-wide lockout. CoreX trusts
  `X-Forwarded-For` only through configured trusted proxies and otherwise uses `REMOTE_ADDR`. Until the server's
  trusted-proxy setting is made (an owner-approved server change, already pending), every visitor shares the proxy's
  address, so a lockout for an identity applies to everyone using that identity. With it, people sharing one NAT address
  still share the bucket for the same identity.
- **Rotating identities or addresses** escapes a single bucket; username and email are counted apart, a successful
  sign-in does not clear recent failures, and concurrent attempts are not reserved atomically. CoreX's design. S-1
  proposes an additional address-wide edge limit; it is pending and provides no current coverage.
- **The rewritten sign-in address.** Turning CoreX's limit on installs its URL and redirect filters whatever the
  default-endpoint setting: WordPress's sign-in links point at CoreX's address (`/corex-login/` by default), built from
  `home_url()`, while CoreX matches only the unprefixed path. `wp-login.php` stays served because the baseline keeps
  default addresses visible. The scenarios follow real browser flows in both languages; if WPML produces a prefixed
  address CoreX does not serve, a CoreX compatibility task is raised before the baseline is enabled anywhere.
- **Staff and tools creating accounts** keep their native authorisation: WordPress uses `create_users`, WooCommerce's
  customer creation uses `create_customers`. WP-CLI is a trusted path. WordPress's REST user creation calls
  `wp_insert_user()` directly and is protected by its capability check, not by this feature.
- **Inactive writers.** WooCommerce's stock-notification signup can create accounts when its settings allow it (both off
  by default and on the runtime); the classic checkout is not offered by the site's page, but its native handlers remain
  registered. The inventory tells an inactive feature from an available handler whose account creation is verified
  closed by the guard. An enabled writer that is neither checked nor verified closed makes the check say review.
- **A visitor without JavaScript** cannot pass the checkout or after-purchase checks; both are JavaScript forms already,
  so nobody is newly excluded. The account page's form works without JavaScript.
- **Early, expired or used tokens** get a translated message asking the person to try again; the page issues a fresh
  token when reloaded. Each tab holds its own token.
- **Cached pages.** Token-bearing account, checkout, order-received and WordPress registration responses must not be
  served from a browser, server or edge cache. WooCommerce's cache signals do not prove every configured cache honours
  them, so the behaviour is verified on staging; a cached token would be expired or already used.

## Native coverage

| Need | Provided by | Decision |
|---|---|---|
| Login rate limit and lockout | CoreX login protection, off by default | A Developer command turns it on with the documented settings, once; afterwards the setting is the owner's |
| Lockout message | CoreX, English only | `SignInMessages` keeps CoreX's error code and supplies Mizzey's translated message |
| A check against scripted account creation on Woo routes | Not covered: CoreX's challenge services protect CoreX forms only | Mizzey adds a shared verifier with route adapters: an early rejection on the Store API checkout, and a pre-insertion guard at `woocommerce_registration_errors` |
| WordPress's own registration | Closed (`users_can_register` off) | Kept closed; if opened, the verifier applies at `registration_errors` |
| Session cookies, lifetime, sign-out and password-change invalidation | WordPress | Used as is; documented and verified |
| Protection status | Partly: the check reports CoreX's lockout as a fact | It becomes a control, with the new rows |

## Requirements

### Functional Requirements

- **FR-001**: `wp mizzey security sign-in-baseline` MUST turn on CoreX login protection through CoreX's own settings
  store, changing five fields (`enabled=true`, `threshold=5`, `window_seconds=300`, `lockout_seconds=900`,
  `block_default_endpoints=false`) and preserving every other setting. It is not re-applied automatically: afterwards
  CoreX's screen is the owner's authority (ADR-0003).
- **FR-002**: With the baseline, these flows MUST keep working in both languages, verified in a browser with their
  destination, query arguments, language and outcome: the WordPress sign-in page, CoreX's address, the account page's
  sign-in, WP 2FA's HTML and REST challenge, sign-out, lost password and the reset link.
- **FR-003**: `woocommerce_registration_errors` provides a pre-insertion check for callers of `wc_create_new_customer()`;
  `registration_errors` covers `register_new_user()`, not arbitrary callers of `wp_insert_user()`. Mizzey MUST keep an
  inventory of public account writers and their enforcement points, and each enabled public writer MUST pass the
  verifier before an account is inserted. The route is identified from the executing native handler, never from the
  token's claim or from the presence of a POST field. Unknown public callers, signed-in customers included, fail closed.
  Staff creation keeps its native authorisation (`create_users`, `create_customers`); WP-CLI is trusted. This feature
  opens no registration route.
- **FR-004**: On the Store API checkout, an invalid check MUST be rejected before the endpoint's callback runs, only when
  WooCommerce would create an account (the visitor is signed out, checkout registration is enabled, and registration is
  required or `create_account` is true by WooCommerce's rules). Guest checkout without an account, signed-in checkout, and
  non-POST requests are not checked. WooCommerce's own nonce and cart-token checks stay as they are.
- **FR-005**: The verifier MUST use no external service, no third-party script and no cookie, and add no visible element.
  Its token is versioned, carries a fixed purpose, the route, a server timestamp and a random nonce, is authenticated
  with HMAC-SHA256 over WordPress's salts, is single-use, and is valid from 3 to 7,200 seconds after issue.
- **FR-006**: The session policy MUST be documented in `docs/security-baseline.md`: cookie attributes, authentication
  lifetime and browser persistence with and without "remember me", invalidation on sign-out and password change, the
  effective filters, the absence of a native idle timeout and of an explicit SameSite attribute, and the difference
  between authentication cookies and WooCommerce's cart-session cookie. HTTPS acceptance needs correct HTTPS detection
  behind the proxy, an HTTPS home URL and secure cookies. No WordPress session behaviour is changed.
- **FR-007**: `wp mizzey security check` MUST report, as controls: `sign_in_limit` (CoreX enabled, its enforcer attached,
  valid effective settings reported, default addresses served; an owner's change to valid threshold or timing settings
  does not fail it), `signup_check` (each covered route's enforcement and rendering present, and a self-test of
  the verifier), `account_writers` (each inventoried public writer checked, verified closed to account creation, or inactive), `wordpress_registration` (closed,
  or open and checked). The existing `corex_login_protection` fact becomes `sign_in_limit`.
- **FR-008**: The method, its limits, the settings and recovery MUST be documented for the handover in
  `docs/security-baseline.md`. Recovery for a locked-out administrator: waiting out the lockout, or, with server access,
  `wp corex security reset-login`, which disables protection, sets default-endpoint blocking off, resets the slug to
  `corex-login` and releases every active lockout. It is emergency recovery, followed by re-applying the intended settings
  and checking them in a fresh request; it is not a per-account unlock.

### Key Entities

| Entity | Where | Notes |
|---|---|---|
| Spent token | A non-autoloaded options row per claimed nonce, inserted atomically and kept for the token's whole validity | Exactly one request may claim a nonce; a storage failure refuses. Holds no personal data |

CoreX keeps its attempts in its own table.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Approval needed |
|---|---|---|---|
| S-1 | An address-wide limit on sign-in attempts at the edge (Cloudflare rate limiting) | Covers credential stuffing that rotates identities | Yes: a Cloudflare setting, pending with the server changes |
| S-2 | A per-address limit on account creation | Slows a browser-driven attacker who passes the check | Yes: needs the trusted-proxy setting first, or it limits every visitor together |
| S-3 | A challenge service (Cloudflare Turnstile) on the same routes | Distinguishes people from scripts that drive a browser | Yes: an external service and account, which PRE-05 does not name |

## Future items (not built)

- Hiding the default sign-in address (CoreX's custom slug as a secret): obscurity, not owed, and it conflicts with
  WPML's language prefix.
- Registration, Google sign-in and the guest conversion screens themselves: later #305 slices.

## Success Criteria

- **SC-001**: Sign-in flows and every covered account route pass in a real browser in both languages; requests the page
  did not produce fail on every route.
- **SC-002**: The security check passes every new row on the runtime with the baseline and, once deployed, on staging.
- **SC-003**: The account, checkout and order-received pages gain no external request and no cookie from this feature.

## Delivery stage

S1, under #305. No screen changes.

## Assumptions

- The pinned stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, CoreX v0.47.0.
- The checkout is WooCommerce's block checkout (the runtime's checkout page), which requires JavaScript.
- On the disposable runtime, settings scenarios snapshot and restore exactly what they change, and run serially.

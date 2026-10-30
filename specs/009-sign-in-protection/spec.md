# Feature Specification: sign-in protection, automated account creation and sessions

**Feature Branch**: `009-sign-in-protection`

**Created**: 2026-10-30

**Status**: Draft

**Work type**: requirement

**Input**: User description: "Start #305, AUTH-15 sign-in protection, with Codex reviewing" (the owner, 30 October 2026).
A slice of #305 (E-SF-7): AUTH-15 only. #305's design predecessors (#250, #253) block its storefront screens, not this
slice: it changes no screen, only what the existing sign-in and registration handlers accept, and it adds no visible
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
| AUTH-01, AUTH-02, AUTH-07 | P1, P1-L, P1 | Registration, guest checkout and converting after purchase. Owned by #305, later slices, which open these routes. This slice protects each route whether or not it is open yet |
| AUTH-16 | P1 | The reset link is single-use and time-limited. #305, a later slice; WordPress's reset keys behave so already |
| NFR-05 | P1 | Owned by #269. The recovery limiter (spec 007) and the security check are reused |
| ADM-131 | P1 | Two-factor (spec 008). The sign-in limit counts password failures before WP 2FA's challenge; the challenge must keep working |
| US-06-05, last criterion | | Password storage is WordPress's own (bcrypt since 6.8). Reported, not built here |

## Open contract items

None. No external service is named, so the account-creation check uses none: no CAPTCHA provider, no third-party
script, no cookie.

## Contractual acceptance criteria and engineering checks [checked]

AC-1 to AC-3 express US-06-05's AUTH-15 criteria. AC-4 is an engineering safeguard, not a contractual criterion.

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Login attempts are rate limited on the WordPress sign-in page and on the account page, in both languages: after the documented number of failures for one account from one address within the window, further attempts for it from that address, even with the right password, are refused for the lockout period with a translated message, and other accounts are unaffected. The limit is a documented setting the owner can change | AUTH-15 | draft |
| AC-2 | Every public route that creates an account applies a check that a scripted submission fails: the account page's registration, account creation at checkout, account creation after purchase, and WordPress's own registration if it is ever opened. A submission that fails the check creates no account; a person using the page passes it. The method is documented | AUTH-15 | draft |
| AC-3 | Sessions are handled securely and expire per the documented policy: the session cookie is HTTP-only and, over HTTPS, secure; its lifetime is the documented one; signing out and changing the password end the session | AUTH-15 | draft |
| AC-4 | One command reports whether these protections are in force: the sign-in limit on with its settings, the account-creation check attached to each route, WordPress's registration closed or checked (an engineering safeguard supporting AUTH-15, not a contractual criterion) | AUTH-15 | draft |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Guessing a password stops quickly (Priority: High)

**Independent Test**: five wrong passwords for one account, then the right one, on each sign-in page; the sixth attempt
is refused, and another account still signs in.

**Acceptance Scenarios**:

1. **Given** the baseline, **When** an account receives five wrong passwords within five minutes from one address,
   **Then** the next attempt, even with the right password, is refused for fifteen minutes with a translated message
   (AC-1).
2. **Given** that lockout, **When** a different account signs in from the same address, **Then** it succeeds (AC-1).
3. **Given** an enrolled administrator, **When** they sign in with the right password and code, **Then** the WP 2FA
   challenge still completes through the rewritten sign-in address (AC-1, ADM-131 unchanged).

### User Story 2 - A script cannot open accounts (Priority: High)

**Independent Test**: on each account-creating route, a request that a script would send (no page visit, a filled trap
field, or an instant submission) creates no account; the same route used through its page creates one.

**Acceptance Scenarios**:

1. **Given** registration open on the account page, **When** a request arrives without a valid check, with the trap
   field filled, or sooner than a person could type, **Then** no account is created and a translated message is shown
   (AC-2).
2. **Given** account creation allowed at checkout, **When** the checkout request lacks the check, **Then** the order is
   not placed with an account and no account exists (AC-2).
3. **Given** the order-received page's account offer, **When** it is posted without the check, **Then** no account is
   created (AC-2).
4. **Given** each route used through its page, **When** a person submits it, **Then** the account is created (AC-2).

### User Story 3 - Sessions behave as documented (Priority: Medium)

**Independent Test**: sign in, read the cookie's attributes and lifetime, sign out and reuse the old cookie.

**Acceptance Scenarios**:

1. **Given** a sign-in, **When** the session cookie is set, **Then** it is HTTP-only, secure over HTTPS, and lives for
   the documented period (AC-3).
2. **Given** a session, **When** the person signs out or changes the password, **Then** the old cookie no longer
   authenticates (AC-3).

### Edge Cases

- **Shared addresses.** CoreX counts per account and address together. Behind a proxy that hides visitors' addresses,
  everyone shares one address, so a lockout for an account applies to every visitor using it. That is the behaviour of
  an account lockout anyway; it becomes per visitor once the server's trusted-proxy setting is made (an owner-approved
  server change, already pending).
- **Rotating usernames or addresses** escapes a single counter; username and email are counted apart. CoreX's design;
  an edge rate limit covers the address dimension first (S-1).
- **The rewritten sign-in address.** Turning CoreX's limit on also points WordPress's sign-in links at its own address
  (`/corex-login/` by default). `wp-login.php` stays served because the baseline keeps default addresses visible. Links
  built from a language-prefixed home URL would not match CoreX's address; the scenarios check every sign-in link the
  site prints in both languages.
- **Staff and tools creating accounts** (an administrator adding a customer, WP-CLI, the REST API with a capability) are
  not public routes and are not checked.
- **A visitor without JavaScript** on the checkout cannot pass the checkout check; the block checkout itself needs
  JavaScript, so nobody is newly excluded. The account page's form works without JavaScript.
- **Cached pages.** The check's token is issued per page view; a page cache must not serve one visitor's token to
  another. Account and checkout pages are not cached (WooCommerce marks them).

## Native coverage

| Need | Provided by | Decision |
|---|---|---|
| Login rate limit and lockout | CoreX login protection, off by default | A Developer command turns it on with the documented settings, once; afterwards the setting is the owner's |
| Lockout message | CoreX | Used; checked in Arabic |
| A check against scripted account creation on Woo routes | Not covered: CoreX's challenge services protect CoreX forms only | Mizzey adds one check, enforced where every Woo route creates an account (`woocommerce_registration_errors`) |
| WordPress's own registration | Closed (`users_can_register` off) | Kept closed; if opened, the same check applies |
| Session cookies, lifetime, sign-out and password-change invalidation | WordPress | Used as is; documented and verified |
| Protection status | Partly: the check reports CoreX's lockout as a fact | It becomes a control, with the new rows |

## Requirements

### Functional Requirements

- **FR-001**: `wp mizzey security sign-in-baseline` MUST turn on CoreX login protection with `threshold=5`,
  `window_seconds=300`, `lockout_seconds=900` and `block_default_endpoints=false`, through CoreX's own settings store,
  keeping every other field as it is. It is not re-applied automatically: afterwards CoreX's screen is the owner's
  authority (ADR-0003).
- **FR-002**: With the baseline, the WordPress sign-in page, the account page in both languages, and WP 2FA's challenge
  MUST keep working, and every sign-in link the site prints MUST resolve.
- **FR-003**: Every public account-creating route MUST require a check before an account is created: a signed token
  issued with the page and bound to the route, a minimum time since issue, and an empty trap field. The routes are the
  account page's registration form, the block checkout's account creation, the order-received account offer, and
  WordPress's registration form. The check is enforced in `woocommerce_registration_errors` for every WooCommerce route
  and in `registration_errors` for WordPress's, so a route cannot create an account without passing it. A failed check
  returns a translated error and creates nothing. Requests from a signed-in user who may create users, and WP-CLI, are
  not checked.
- **FR-004**: The check MUST use no external service, no third-party script and no cookie, and add no visible element.
  The token is single-purpose, expires, and is not reusable across routes.
- **FR-005**: The session policy MUST be documented in `docs/security-baseline.md`: cookie attributes, lifetime with and
  without "remember me", invalidation on sign-out and password change. No WordPress session behaviour is changed.
- **FR-006**: `wp mizzey security check` MUST report, as controls: the sign-in limit on with its settings and default
  addresses served; the account-creation check attached to each route; WordPress registration closed, or open with the
  check attached. The existing `corex_login_protection` fact becomes the first control.
- **FR-007**: The method and the settings MUST be documented for the handover in `docs/security-baseline.md`, with the
  recovery command for a locked-out administrator (`wp corex security reset-login`, and its side effects).

### Key Entities

None stored by Mizzey. The token is computed from WordPress's salts; CoreX keeps its attempts in its own table.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Approval needed |
|---|---|---|---|
| S-1 | A per-address limit on sign-in attempts across accounts, at the edge (Cloudflare rate limiting) | Covers credential stuffing that rotates usernames | Yes: a Cloudflare setting, pending with the server changes |
| S-2 | A per-address limit on account creation | Slows a browser-driven attacker who passes the check | Yes: needs the trusted-proxy setting first, or it limits every visitor together |
| S-3 | A challenge service (Cloudflare Turnstile) on the same routes | Stronger than a token and timing against a real browser | Yes: an external service and account, which PRE-05 does not name |

## Future items (not built)

- Hiding the default sign-in address (CoreX's custom slug as a secret): obscurity, not owed, and it conflicts with
  WPML's language prefix.
- Registration, Google sign-in and the guest conversion screens themselves: later #305 slices.

## Success Criteria

- **SC-001**: Sign-in and account-creation scenarios pass over real HTTP in both languages; the scripted cases fail and
  the page cases pass on every route.
- **SC-002**: The security check passes every new row on the runtime with the baseline and, once deployed, on staging.
- **SC-003**: The account and checkout pages gain no external request and no cookie from this feature.

## Delivery stage

S1, under #305. No screen changes.

## Assumptions

- The pinned stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, CoreX v0.47.0.
- The checkout is WooCommerce's block checkout (the runtime's checkout page), which requires JavaScript.
- On the disposable runtime, the sign-in baseline is applied by its scenarios and restored after them, as the
  two-factor scenarios do.

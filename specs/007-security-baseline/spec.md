# Feature Specification: security baseline, the site-level controls

**Feature Branch**: `007-security-baseline`

**Created**: 2026-10-29

**Status**: Draft

**Work type**: requirement

**Input**: User description: "Start the site hardening PR" (the owner, 29 October 2026), under the quality standard D-21
section 3. The site plugin closes the exposures a WordPress store carries by default that CoreX and WooCommerce leave
open, uses CoreX wherever CoreX already provides the control, and reports the baseline's state in one check.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NFR-05 | P1 | S1 | HTTPS, secure password storage, RBAC, admin protection, input validation, rate limiting, secret management, dependency updates |

Story: Functional Specification MS-SPC-2026-032, US-27-03 (Security), the NFR-05 part only: "admin protection, input
validation, rate limiting ... are all in place" and "The security controls are listed in the handover documentation".
PBI #269 (E-NFR-1) owns NFR-05 and holds its evidence.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| AUTH-15 | P1 [key] | Rate limiting on login attempts and protection against automated account creation. Owned by #305 (E-SF-7). CoreX's login lockout is that control; this feature neither configures it nor replaces it, and changes no sign-in throttle |
| AUTH-16 | P1 | The reset link is single-use and time-limited. Owned by #305. WordPress's reset keys already behave so; the request throttle here does not touch the link |
| ADM-131 | P1 | Two-factor sign-in for admin accounts. Owned by #275 (E-ADM-9), in its own specification |
| NFR-06 | P1 | No card data stored. Owned by #269 with PAY-02; nothing here touches payment |
| NFR-10 | P1-L | Logs and monitoring. Owned by E-NFR-2 |
| REV-01 to REV-05 | P1 | Product reviews are WordPress comments on products. This feature never closes or alters comments on products |
| D-21 | Owner standard | Section 3 sets the baseline. Where D-21 asks more than NFR-05's wording it is the developer's own standard, not a client obligation |

## Open contract items

None. Nothing here waits for a client, vendor or ERP input.

## Contractual acceptance criteria [checked]

| AC | Criterion | Rows | Status |
|---|---|---|---|
| AC-1 | No page, feed or response header discloses the WordPress, WooCommerce or CoreX product or version through a generator mark | NFR-05 (admin protection) | final |
| AC-2 | XML-RPC answers no method, pingbacks and trackbacks are off, and no page advertises an XML-RPC or pingback address | NFR-05 (admin protection) | final |
| AC-3 | A visitor who is not signed in cannot list or discover staff or customer accounts: not through the users endpoint, not through a numeric author query, not through the users sitemap | NFR-05 (admin protection) | final |
| AC-4 | Application passwords can be created and used only by administrators; customers and other staff cannot | NFR-05 (admin protection) | final |
| AC-5 | A failed sign-in, on the WordPress sign-in page and on the account page, gives one message whether the account exists or not, and the failure still reaches CoreX's lockout | NFR-05 (admin protection) | final |
| AC-6 | Password-recovery requests are rate limited per account and per client address, on the WordPress page and on the account page, in both languages, and the limits are documented settings | NFR-05 (rate limiting) | final |
| AC-7 | Comments and pings are closed on every content type except products, whose reviews are unchanged | NFR-05 (input validation) | final |
| AC-8 | One command reports the state of every control in this baseline, and the controls are listed for the handover | NFR-05, US-27-03 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The store reveals nothing an attacker can use (Priority: High)

A scanner probing the store learns neither the software versions nor the account names, and finds no XML-RPC door.

**Why this priority**: version and account disclosure is the first step of most automated attacks on WordPress stores.

**Independent Test**: fetch the home page, a product, a feed, the users endpoint, `?author=1`, the users sitemap and
`xmlrpc.php` as a visitor, and read what comes back.

**Acceptance Scenarios**:

1. **Given** any storefront page or feed, **When** a visitor reads it, **Then** it carries no generator mark (AC-1).
2. **Given** a visitor, **When** they call the users endpoint or ask for `?author=<id>`, **Then** they get no account
   name or slug (AC-3).
3. **Given** an XML-RPC request, **When** it reaches WordPress, **Then** no method answers (AC-2).

### User Story 2 - Accounts are harder to attack (Priority: High)

Someone guessing at sign-in learns nothing from the message; someone flooding password recovery is stopped.

**Independent Test**: a wrong password for a real account and a made-up account give the same message; the fourth
recovery request for one account within the window is refused.

**Acceptance Scenarios**:

1. **Given** a real account and a made-up one, **When** each fails to sign in, **Then** the message is the same (AC-5).
2. **Given** an account, **When** recovery is requested more often than the documented limit, **Then** the extra
   request is refused with a plain message and no email is sent (AC-6).
3. **Given** a customer or a shop manager, **When** they try to create or use an application password, **Then** it is
   not possible; an administrator still can (AC-4).

### User Story 3 - The Developer can show the baseline holds (Priority: Medium)

**Independent Test**: `wp mizzey security check` lists every control with pass or review, on the runtime and on staging.

**Acceptance Scenarios**:

1. **Given** the baseline active, **When** the check runs, **Then** every control reports pass (AC-8).
2. **Given** a control switched off by a filter, **When** the check runs, **Then** that control reports review.

### Edge Cases

- A product review is a comment: AC-7 never closes comments on products.
- The block editor lists authors through the users endpoint: a signed-in user who may edit content keeps that access.
- WooCommerce integrations authenticate with WooCommerce API keys, which AC-4 does not touch. An integration that needs
  an application password uses an administrator service account; the test harness does the same.
- CoreX's lockout message is not the generic message: AC-5 replaces only the unknown-account and wrong-password
  errors, so a locked-out visitor is still told to wait.
- Behind the proxy, the client address comes from CoreX's `ClientAddress` service, the same one its lockout uses.
- Registration necessarily reveals that an email is already registered, and WooCommerce's recovery says an unknown
  account is unknown. Hiding that needs a different account flow; the mitigation here is the recovery limit (AC-6), and
  automated registration is #305's (AUTH-15).

## Native coverage

| Need | Native or CoreX | Decision |
|---|---|---|
| Generator marks | WordPress `wp_generator`, WooCommerce `wc_generator_tag`, CoreX `GeneratorTag` (filter `corex_generator_tag`) | Switch each off through its own hook |
| XML-RPC | `xmlrpc_enabled` turns off authenticated methods only; pingbacks still answer | Empty `xmlrpc_methods`, remove the pingback header and discovery links |
| User discovery | REST users controller, `?author=` canonical redirect, users sitemap provider | Restrict for visitors through the native filters |
| Application passwords | `wp_is_application_passwords_available_for_user` | Administrators only |
| Sign-in failure message | Distinct WordPress errors; WooCommerce applies `login_errors` | Replace the two account-revealing codes in `authenticate`, after CoreX |
| Recovery rate limit | None in WordPress, WooCommerce or CoreX (CoreX's limiter wraps its own controllers only) | Mizzey limiter on `lostpassword_post`, which both pages fire |
| Comment surface | Per-post `comments_open`, `pings_open` | Closed except products |
| Login lockout | CoreX login protection | Not here: AUTH-15, #305 |

## Requirements

### Functional Requirements

- **FR-001**: The site MUST print no generator mark for WordPress, WooCommerce or CoreX in pages, feeds or the head.
- **FR-002**: XML-RPC MUST answer no method; the `X-Pingback` header, the RSD link and pingbacks MUST be off.
- **FR-003**: For a visitor not signed in, the users REST collection and single-user routes MUST refuse; a numeric
  `author` query MUST not redirect to or reveal an author slug; the users sitemap MUST not exist. Signed-in users with
  `list_users` or `edit_posts` keep the users endpoint.
- **FR-004**: Application passwords MUST be available only to users with `manage_options`.
- **FR-005**: The unknown-username, unknown-email and wrong-password failures MUST return one generic message on both
  sign-in pages; every other error, CoreX's lockout included, MUST pass unchanged, and the failure MUST still fire
  `wp_login_failed`.
- **FR-006**: Password-recovery requests MUST be limited per target account and per client address over a window;
  the limits and window MUST be filterable settings with documented defaults (3 per account and 10 per address per 15
  minutes). A refused request MUST send no email and MUST show a plain message in the visitor's language.
- **FR-007**: Comments and pings MUST be closed for every post type except `product`.
- **FR-008**: `wp mizzey security check` MUST report each control of FR-001 to FR-007, plus the configuration facts the
  site depends on (file changes disabled, debug display off, CoreX's hardening facts, whether CoreX login protection
  is on, reported for #305 and not judged here), and exit non-zero when a control of this feature fails.
- **FR-009**: No control here MAY write an option or change a setting another component owns; each acts through a hook.

### Key Entities

None. The recovery limiter keeps short-lived counters in transients; nothing is stored permanently.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Approval needed |
|---|---|---|---|
| S-1 | An independent per-address sign-in limit beside CoreX's identity-and-address bucket | Credential stuffing that rotates usernames from one address escapes a combined bucket | Yes: it belongs to AUTH-15 (#305) or upstream in CoreX, and the edge rate limit covers it first |

## Future items (not built)

- Spam protection on WooCommerce forms with the CoreX challenge services: AUTH-15 (#305) and the reviews PBI, after
  the owner chooses the service.
- Security headers, archive files refused, the edge rate limits: server and edge, each on the owner's approval.
- The vulnerability feed check: its own tooling pull request.

## Success Criteria

- **SC-001**: Every fetch in the independent tests of stories 1 and 2 returns the expected result in both languages.
- **SC-002**: The security check reports pass for every control on the development runtime and, once deployed, on
  staging.
- **SC-003**: Products keep their reviews: the review scenarios already in the suite keep their verdicts.

## Delivery stage

S1, under #269. No storefront presentation changes; the only visible text is the generic sign-in message and the
recovery refusal, both through the existing notices.

## Assumptions

- The pinned stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, CoreX v0.47.0 (`stack.lock.json`).
- No contracted surface uses XML-RPC, a non-administrator application password, or author archives by number.
- The users sitemap is not an NFR-03 obligation: t23 records it as a configuration decision, and this feature decides it
  (removed).

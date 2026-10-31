# Feature Specification: Google sign-in

**Feature Branch**: `024-google-sign-in`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 2 of the Stage 3
build plan under D-22: "E-SF-7 accounts, rest of #305: one account per email across manual and Google sign-in". The
second PR of #305 (specs/017 recorded it). Built as our own code under D-24 ("don't use ready made plugin, create
ours", the owner, 31 October 2026), in place of the plugin PL-02 (Nextend Social Login), which was never installed.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| AUTH-10 | P1 | S1 | Sign in and register with a Google account |
| AUTH-13 | P1 | S1 | A Google sign-in and a manual registration using the same email address resolve to one account, never two |

Stories: Functional Specification MS-SPC-2026-032 v1.4, the account stories, the criteria that trace to the rows above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| AUTH-05, AUTH-08 | P1 | Email verification and guest orders linked once verified (specs/017): an email Google has verified is marked verified here, which links the guest orders |
| AUTH-09 | P1 | Marketing consent stays separate: an account made with Google gives none |
| AUTH-10a | P2 | Social login beyond Google is not built |
| ADM-131 | P1 | Staff sign in with their password and two factors (specs/008), never with Google |

## Open contract items

- The OAuth client (client ID and secret) is created in a Google Cloud project the client owns (a vendor input,
  D-10: it does not block development). Until then the feature stays off; a developer test client may be used on
  staging.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A visitor signs in, or registers, with their Google account | AUTH-10 | provisional |
| AC-2 | A Google sign-in and a manual registration with the same email are one account, never two | AUTH-13 | provisional |

Provisional: verified by automated tests against a mocked Google token endpoint; acceptance follows manual testing on
staging with a real OAuth client and the client's approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - No form to fill (Priority: High)

1. **Given** a visitor with a Google account, **When** they choose Continue with Google and approve, **Then** they are
   signed in to a customer account, made for them if they had none, and returned to where they began.
2. **Given** a customer who registered with the same email, **When** they continue with Google, **Then** they are
   signed in to that same account.

### Edge Cases

- An email Google has not verified makes no account and links none.
- An account whose email was never verified here has its password replaced, and its sessions ended, before it is
  linked: someone who registered another person's email cannot keep that account once its owner signs in with Google.
- A staff account cannot be signed in to with Google.
- A callback whose state is unknown, spent, expired or not bound to the browser's cookie signs no one in.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Sign-in with Google | None in WordPress or WooCommerce | Gap: `GoogleSignIn` (our own code, D-24; no plugin) |
| One account per email | WordPress's unique account email | Native, extended: the Google account is linked to the account with that email |
| Email verified | WooCommerce's customer email verification (`mark_verified`) | Native, used |

## Requirements

### Functional Requirements

- **FR-001**: The flow MUST be OpenID Connect's authorization code flow with PKCE (S256), a state bound to the
  browser by a cookie and a nonce bound to the ID token, both single-use and kept ten minutes (AC-1).
- **FR-002**: The code MUST be exchanged server to server; the ID token MUST have Google's issuer, this client as
  audience, an expiry ahead and the nonce sent (AC-1).
- **FR-003**: The account MUST be the one linked to the Google account, else the one with that email when Google has
  verified it, else a new customer account (AC-1, AC-2).
- **FR-004**: Linking an account whose email was never verified here MUST replace its password first (AC-2).
- **FR-005**: Staff accounts MUST be refused (ADM-131).
- **FR-006**: The feature MUST stay off until the client ID and secret are set; the secret MAY come from
  `MIZZEY_GOOGLE_CLIENT_SECRET` in the configuration.
- **FR-007**: Every message MUST be translated (FIX-04).

### Key Entities

- **Google link**: the Google account's subject on the WordPress account (`_mizzey_google_sub`).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Other social sign-in (AUTH-10a, P2).

## Success Criteria

- **SC-001**: No email has two accounts.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 2 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- Replacing the password of a never-verified account on its first Google sign-in is a working default (an owner
  decision, not a client confirmation); the customer can set a new one with the reset link.

# Feature Specification: accounts

**Feature Branch**: `017-accounts`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 2 of the Stage 3
build plan under D-22: "E-SF-7 accounts, rest of #305: registration, one account per email across manual and Google
sign-in, guest checkout and guest conversion (AUTH-07, AUTH-11), consent kept separate (AUTH-09)". #305. The function
layer; the designed sign-in and registration screens wait for PRE-03b. Built on `016-launch-rules` for AUTH-06.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| AUTH-01 | P1 | S1 | Register with email and password |
| AUTH-02 | P1-L | S1 | Register with phone number |
| AUTH-03 | P1 | S1 | Login |
| AUTH-04 | P1 | S1 | Forgot / reset password |
| AUTH-05 | P1 | S1 | Email verification |
| AUTH-06 | P1 | S1 | Welcome discount applied on registration per configured rules |
| AUTH-07 | P1 | S1 | Guest checkout |
| AUTH-08 | P1 | S1 | Guest order later linked to an account securely |
| AUTH-09 | P1 | S1 | Marketing consent captured separately from account creation |
| AUTH-11 | P1 | S1 | One-step account creation from the order confirmation: a guest who has just ordered becomes a registered customer by setting a password only, with no re-entry of name, email, phone or address |
| AUTH-12 | P1-L | S1 | Passwordless email sign-in link as an alternative to remembering a password |
| AUTH-14 | P1 | S1 | Account creation requires no more than email, password and consent, no unnecessary mandatory fields |
| AUTH-16 | P1 | S1 | Password reset link is single-use and time-limited |

Stories: Functional Specification MS-SPC-2026-032 v1.4, US-06-01, US-06-02, US-06-06 and US-06-07, the criteria that
trace to the rows above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| AUTH-10, AUTH-13 | P1 | Google sign-in and one account per email across Google and manual registration: the approved plugin (PL-02, Nextend Social Login, D-2026-09-09) waits for the owner's approval to install it, and an OAuth client in a client-owned Google account (a vendor input). A second PR of #305 |
| AUTH-15 | P1 | Secure sessions, the check against scripted account creation and the sign-in limit: done (specs/009, #427) |
| AUTH-10a | P2 | Social login beyond Google is not built |

## Open contract items

- AUTH-02 and AUTH-12 are P1-L with no reduction written in Part One 1.2. AUTH-02 follows the default the ownership
  audit recorded on 5 October 2026 (the phone is captured at registration and can be used to sign in, with no OTP).
  AUTH-12's numeric limits follow B16 and are proposed below as working defaults.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Registration requires no more than email, password and consent; no unnecessary mandatory fields | AUTH-01, AUTH-14 | provisional |
| AC-2 | Phone number registration is available at the reduced scope: captured at registration, usable to sign in | AUTH-02 | provisional |
| AC-3 | Sign-in with email and password succeeds; a failed sign-in does not reveal whether the email exists | AUTH-03 | provisional |
| AC-4 | Password reset sends a link that is single-use and time-limited, and a used or expired link says so plainly | AUTH-04, AUTH-16 | provisional |
| AC-5 | Email verification is sent and the account state reflects whether the email is verified | AUTH-05 | provisional |
| AC-6 | The welcome discount is applied on registration per the configured rules | AUTH-06 | provisional |
| AC-7 | A purchase completes with no account created and no forced registration | AUTH-07 | provisional |
| AC-8 | A guest order is linked to an account securely: never claimed by an account that does not own the ordering email | AUTH-08 | provisional |
| AC-9 | Marketing consent is captured separately from account creation, and is not implied by registering | AUTH-09 | provisional |
| AC-10 | The order confirmation offers account creation with a password only; name, email, phone and address are carried across; the order is linked | AUTH-11 | provisional |
| AC-11 | A passwordless email sign-in link is available as an alternative, single-use and time-limited | AUTH-12 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval. The
`sign_up` and `login` events are #312's (EVT rows), the screens PRE-03b's.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Register, verify, keep earlier orders (Priority: High)

1. **Given** the registration form, **When** a visitor registers, **Then** only email and password are required; a
   mobile number and marketing consent are optional, the consent unticked.
2. **Given** a new account, **When** it is created, **Then** a verification email is sent; the account is unverified
   until the emailed link is followed.
3. **Given** guest orders under that email, **When** the email is verified, **Then** they are linked to the account,
   and not before (B14).

### User Story 2 - Sign in without friction (Priority: High)

1. **Given** an account, **When** the customer signs in with email or mobile number and password, **Then** they are in;
   a wrong password and an unknown account get the same message.
2. **Given** a forgotten password, **When** the customer resets it, **Then** the link works once and expires.
3. **Given** a customer account, **When** they ask for a sign-in link, **Then** one link arrives that signs them in
   once, within 15 minutes; staff are never sent one.

### User Story 3 - A guest becomes a customer (Priority: High)

1. **Given** a guest who just ordered, **When** they set a password on the order confirmation, **Then** the account
   carries their name, phone and address and holds the order.

### Edge Cases

- A mobile number is one account's: registering it again is refused; a number held by no account, or ambiguously,
  signs in to nothing.
- A sign-in link: a wrong token does not spend it; asking again replaces it; requests share the password-reset limits
  (per account and per address), so neither route multiplies the other; the answer is the same whether or not an
  account exists.
- An account made from the order confirmation is verified by WooCommerce when its set-password link is used; it is not
  sent a second email.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Register, sign in, reset | WooCommerce's account page and WordPress's reset keys (single-use, `password_reset_expiration`) | Native, verified |
| Email verification | WooCommerce 11's `CustomerEmailVerification`: a one-time key bound to the email, a day long, the account prompt and resend, `woocommerce_customer_email_verified` linking guest orders | Native; sent at registration by a hook (`VerificationOnRegistration`) |
| Guest checkout, order confirmation account | WooCommerce's guest checkout and order-received offer (`CreateAccount`), guarded by AUTH-15 | Native, verified |
| Marketing consent | None at registration | Gap: one unticked box, recorded with time and source |
| Phone registration and sign-in | None | Gap: the billing phone at registration, one per account, accepted at sign-in |
| Passwordless sign-in link | None for the storefront (`MobileAppMagicLink` is WooCommerce's mobile app's) | Gap: `SignInLink` |
| Welcome discount | #290's `WelcomeDiscount` | Verified here |

## Requirements

### Functional Requirements

- **FR-001**: The verification email MUST be sent when a customer account is created, through WooCommerce's own
  controller, when its email is enabled (AC-5).
- **FR-002**: The registration form MUST offer an unticked marketing consent box; the choice MUST be recorded with its
  time and source, and registering MUST NOT need it (AC-1, AC-9).
- **FR-003**: The registration form MUST offer an optional mobile number, kept in the Egyptian mobile form, one per
  account; sign-in MUST accept it in place of the email or username with the account's password (AC-2).
- **FR-004**: A customer MUST be able to ask for a sign-in link by email; it MUST work once, expire (default 15
  minutes), share the password-reset limits, never go to staff, and the answer MUST NOT disclose whether an account
  exists (AC-11).
- **FR-005**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Marketing consent**: given or not, when, where.
- **Sign-in link**: a hashed token and its expiry on the account, spent when used.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- SMS one-time codes for phone sign-in (P2, INT-07); social login beyond Google (AUTH-10a, P2).

## Success Criteria

- **SC-001**: No guest order is linked to an account before its email is verified.
- **SC-002**: No sign-in link works twice or after it expires, and no staff account is sent one.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 2 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- B16's numeric limits, proposed as working defaults (owner decisions, not client confirmations): a sign-in link lives
  15 minutes and works once; requests share the recovery limits of 3 per account and 10 per address in 15 minutes.

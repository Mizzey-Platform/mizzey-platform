# Feature Specification: the customer record in admin

**Feature Branch**: `033-customer-records`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ADM-6, issue #279 ("Customer records in admin: profile, internal notes, suspension with a reason").
No predecessor. The owner's instruction of 2 November 2026 puts it in the admin set that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-100 | P1 | S1 | Customer profile: contacts, addresses, order history |
| ADM-103 | P1-L | S1 | Internal notes not visible to the customer |
| ADM-104 | P1-L | S1 | Disable or suspend an account with reason |

ADM-103 and ADM-104 are P1-L with no reduction written in Part One 1.2; they are built at their own wording.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-101, ADM-105 | P1-L, S2 | Lifetime metrics and the data export are the second release (E-ADM-6b) |
| ADM-102 | P2 | Segments: not built |
| AUTH rows | P1 | Sign-in by password, Google and a sign-in link (specs/017, specs/024): a suspension refuses all of them |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Staff open a customer's profile with their contacts, addresses and order history | ADM-100 | provisional |
| AC-2 | Staff keep internal notes on a customer that the customer never sees | ADM-103 | provisional |
| AC-3 | Staff suspend a customer account with a recorded reason; a suspended account cannot sign in by any route until reinstated | ADM-104 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The customer on the phone (Priority: High)

1. **Given** a customer, **When** staff open their profile, **Then** their phone, email, addresses and orders are on
   one screen, with staff's internal notes.

### User Story 2 - Suspend an abusive account (Priority: Medium)

1. **Given** a customer who keeps refusing deliveries, **When** staff suspend the account with a reason, **Then** the
   customer is signed out everywhere and cannot sign in, the sign-in page says the account is suspended, and the reason
   and who suspended it are kept; **When** staff reinstate it, **Then** the customer can sign in again.

### Edge Cases

- A suspension without a reason: refused with a message.
- A staff account or one's own: cannot be suspended here.
- Google sign-in or a sign-in link for a suspended account: the cookie gives no session.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Contacts and addresses | WordPress's user screen with WooCommerce's customer fields | Native |
| Order history on the profile | Only the orders list filtered by customer | Gap: the list on the profile |
| Notes on a customer | None | Gap: a private field on the profile |
| Suspension | None in WordPress or WooCommerce | Gap: a suspension with reason and history, enforced on sign-in |

## Requirements

### Functional Requirements

- **FR-001**: The customer's user screen MUST list their orders, newest first, linked to each order (AC-1).
- **FR-002**: Internal notes MUST be stored in a private field and shown only on the staff screen (AC-2).
- **FR-003**: Suspending MUST need a reason, end the account's sessions and keep who, when and why; reinstating MUST be
  kept too (AC-3).
- **FR-004**: A suspended account MUST be refused at password sign-in with a message in the visitor's language, and
  MUST never be the signed-in user whatever cookie it holds (AC-3).
- **FR-005**: Only customer accounts other than one's own MUST be suspendable, by staff who may edit them (AC-3).
- **FR-006**: Changes MUST need the form's nonce.

### Key Entities

- **Customer record**: the WordPress user with WooCommerce's fields, plus internal notes, suspension state, reason and
  history.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Lifetime metrics and the data export (ADM-101, ADM-105, S2); segments (ADM-102, P2).

## Success Criteria

- **SC-001**: On staging, a test customer is suspended and refused at sign-in in both languages, then reinstated.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- "Staff" accounts (able to edit posts, manage the store or edit orders) are managed through roles (specs/013), not
  suspended here.

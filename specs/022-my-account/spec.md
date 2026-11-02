# Feature Specification: the account area

**Feature Branch**: `022-my-account`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 3 of the Stage 3
build plan under D-22: "E-SF-9 My Account: address book, order detail with status history, profile and password on
WooCommerce's account details (B9), invoice download after ADM-92". #308. The function layer; the designed account
screens wait for PRE-03b. Built on `021-emails` (the shipment fields), which carries the earlier Stage 3 slices.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ACCT-01 | P1 | S1 | Dashboard overview |
| ACCT-02 | P1 | S1 | Profile details, editable |
| ACCT-03 | P1 | S1 | Change password |
| ACCT-04 | P1 | S1 | Address book: add, edit, delete, set default |
| ACCT-05 | P1 | S1 | Order history list |
| ACCT-06 | P1 | S1 | Order detail view with full breakdown |
| ACCT-07 | P1 | S1 | Order tracking |
| ACCT-08 | P1 | S1 | Saved wishlist |
| ACCT-12 | P1-L | S1 | Download invoice |

Stories: Functional Specification MS-SPC-2026-032 v1.4, the account stories, the criteria that trace to the rows above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-92 | P1-L | The invoice document is #280's (specs/023-invoice, our own code under D-24); ACCT-12 downloads it |
| ACCT-09, ACCT-11, ACCT-13 | P1-L S2 | Reorder, notification preferences and the deletion request: the second release |

## Open contract items

- ACCT-12 is P1-L with no reduction written in Part One 1.2: its download is built in specs/023-invoice, on ADM-92's
  invoice (#280).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The account opens on a dashboard; profile details and the password can be changed there | ACCT-01, ACCT-02, ACCT-03 | provisional |
| AC-2 | The customer keeps an address book: adds, edits and deletes addresses and sets the default | ACCT-04 | provisional |
| AC-3 | The account lists the customer's orders, and each order shows its full breakdown | ACCT-05, ACCT-06 | provisional |
| AC-4 | An order shows where it is: each step it has reached, and the carrier and tracking number once shipped | ACCT-07 | provisional |
| AC-5 | The account shows the customer's saved wishlist | ACCT-08 | provisional |
| AC-6 | The customer downloads an order's invoice from the account | ACCT-12 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval.
AC-6 is built in specs/023-invoice, on #280's invoice (ADM-92), and tested by t64. AC-1 and AC-3 are WooCommerce's own account pages (B9).
The designed screens are PRE-03b's.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Addresses kept, not retyped (Priority: High)

1. **Given** a signed-in customer, **When** they add addresses, **Then** each is kept in the Egyptian structure, and
   the one they set as default is the address the checkout offers.
2. **Given** an incomplete address, a malformed mobile number or an unknown governorate, **When** it is saved, **Then**
   it is refused with a message.

### User Story 2 - Where is my order (Priority: High)

1. **Given** a shipped order, **When** the customer opens it, **Then** they see each step it reached with its date,
   and the carrier and tracking number.

### Edge Cases

- The default address's mobile number goes to the delivery phone; the number the customer signs in with is not
  changed (specs/017).
- One customer cannot read or change another's addresses.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Dashboard, profile, password, order history and detail | WooCommerce's My Account pages | Native, verified (B9) |
| Address book | One billing and one shipping address | Gap: `AddressBook` |
| Order tracking | The order's status | Gap: `AccountPages` adds the shipment and the progress |
| Wishlist in the account | None | Gap: `AccountPages` adds a Wishlist item to the account menu, opening the storefront's one Wishlist page (IA-11, G-1 of specs/003); the page's screen is specs/015's, after PRE-03b |

## Requirements

### Functional Requirements

- **FR-001**: The address book MUST hold up to 10 addresses per customer, each validated, one the default, copied to
  WooCommerce's billing and shipping address without changing the billing phone (AC-2).
- **FR-002**: The REST routes MUST act on the signed-in customer's own book only (AC-2).
- **FR-003**: An order's view MUST show its progress and, once shipped, its carrier and tracking number (AC-4).
- **FR-004**: The account menu MUST offer the wishlist (AC-5).
- **FR-005**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Address**: name, mobile number, governorate, city, district, street and building, landmark, default or not.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Reorder, notification preferences, the deletion request (second release).

## Success Criteria

- **SC-001**: A customer never retypes an address the book holds.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 3 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- Ten addresses per customer is a working default (an owner decision, not a client confirmation).

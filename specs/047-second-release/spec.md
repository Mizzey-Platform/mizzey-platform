# Feature Specification: the second release, built behind its switch

**Feature Branch**: `047-second-release`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: The contracted second-release rows (S2), delivered after go-live within the Phase 1 contract. The owner's
instruction of 2 November 2026: build them now behind a switch that is off by default, so the second release is
almost immediate after launch. The customer screens follow the interface design (PRE-03b); the functions are built
now. ADM-28 (scheduled price changes) is P2 in the register, not S2, and is not built here.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-05 | P1-L | S2 | Customers: new and returning |
| ADM-07 | P1-L | S2 | Conversion rate |
| ADM-101 | P1-L | S2 | Total spend, AOV, returns, coupon usage |
| ADM-105 | P1-L | S2 | Export under privacy controls |
| NFR-12a | P1-L | S2 | Privacy: Automated data export and deletion request handling |
| ACCT-13 | P1-L | S2 | Account deletion request |
| NOTF-14 | P1-L | S2 | Admin-editable notification templates |
| JRN-11 | P1-L | S2 | After-sales: Reorder: repeat a previous order from history |
| ACCT-09 | P1-L | S2 | Reorder from history |
| IA-23 | P1-L | S2 | Notification Preferences |
| ACCT-11 | P1-L | S2 | Notification preferences |
| PLP-11 | P1-L | S2 | Filter by rating |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| NFR-12 | P1 | Consent and data minimisation, which the preferences and requests extend |
| ADM-100, ADM-103 | P1 | The customer record (specs/033) the figures are added to |
| ADM-01 to ADM-15 | P1 | The dashboard (specs/035) the new and returning figures are added to |
| RPT-08 | P1-L | The conversion funnel in Google Analytics (#313) |
| ADM-28 | P2 | Scheduled price changes: P2, not built |

## Open contract items

- None. The second release goes live within 45 days of go-live (the Statement of Work); the switch is turned on then.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Nothing of the second release is offered until its switch is on, and it is off by default | NFR-12a | provisional |
| AC-2 | A customer reorders a delivered order from their history | JRN-11, ACCT-09 | provisional |
| AC-3 | A customer asks for a copy of their data or the deletion of their account; once confirmed by email it is handled automatically; staff prepare a customer's export under the privacy controls | NFR-12a, ACCT-13, ADM-105 | provisional |
| AC-4 | A customer chooses which non-order emails they receive | IA-23, ACCT-11 | provisional |
| AC-5 | Staff see a customer's total spend, order value, refunds and coupon use, and the new and returning customers of the last 30 days; the conversion rate is Google Analytics' | ADM-101, ADM-05, ADM-07 | provisional |
| AC-6 | Staff edit every email's subject, heading and text; the listing pages can filter by rating | NOTF-14, PLP-11 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Leaving the store (Priority: High)

1. **Given** the second release is live, **When** a customer asks to delete their account, **Then** they get a
   confirmation email; **When** they confirm, **Then** their personal data is erased as the store's privacy settings
   say and the account is deleted, in the background.

### Edge Cases

- A staff member's account: never deleted by a request.
- An export prepared by staff: downloaded from Tools, not emailed to the customer.
- Order emails: always sent, whatever the preferences.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Reorder | WooCommerce's "Order again" | Native; offered on Delivered and COD Collected once the switch is on |
| Requests, export, erasure | WordPress's privacy requests and WooCommerce's exporters and erasers | Native; the customer's buttons and the automatic handling added |
| Editable email texts | WooCommerce, Settings, Emails (subject, heading, additional content) | Native, for every email including Mizzey's |
| Rating filter | WooCommerce's product filter (rating) block | Native; placed on the listing pages with their design (#303) |
| Preferences, customer figures, the switch | None | Gap: `NotificationPreferences`, `CustomerMetrics`, `ReleaseSwitch` |

## Requirements

### Functional Requirements

- **FR-001**: Settings, Second release (`manage_options`) MUST hold the switch, off by default; until it is on, no
  reorder, request button, preference, customer figure or dashboard line MUST show (AC-1).
- **FR-002**: "Order again" MUST be offered on a Delivered or COD Collected order (AC-2).
- **FR-003**: The customer's request MUST be a WordPress privacy request confirmed by email; once confirmed, the
  export or the erasure MUST run in the background with the registered exporters and erasers; an erased customer's
  account MUST then be deleted, never a staff account (AC-3).
- **FR-004**: Staff with `export_others_personal_data` MUST be able to prepare a customer's export from the record; it
  is downloaded from Tools, Export Personal Data, not emailed (AC-3).
- **FR-005**: The customer MUST be able to turn off review invitations and turn offers on or off; order emails MUST
  always go (AC-4).
- **FR-006**: The record MUST show total spend net of refunds, paid orders, average order value, orders refunded and
  coupons used; the dashboard MUST show new and returning customers over 30 days and say the conversion rate is in
  Google Analytics (AC-5).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Scheduled price changes (ADM-28, P2).

## Success Criteria

- **SC-001**: On staging, with the switch on, a test customer reorders, changes their preferences and deletes their
  account through the confirmation email; with the switch off, none of it shows.

## Delivery stage

Contractual stage: S2 (second release). Engineering timing: November 2026, released with the switch after go-live.

## Assumptions

- The customer-facing controls are placed in the designed account screens after PRE-03b; their function is final.

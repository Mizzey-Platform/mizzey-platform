# Feature Specification: customer emails

**Feature Branch**: `021-emails`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 3 of the Stage 3
build plan under D-22: "E-NOTF-1 emails, Stage 3 part: account and order emails, in the order's language, with
triggers and content". #285. The function layer; the shared email layout waits for PRE-03b, the brand header for
OD-01, and the sending address for OD-18. Built on `019-checkout` (the order flow) with `020-reviews` (the delivered
invitation) merged in.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NOTF-01 | P1 | S1 | Account created: welcome, verification, discount details |
| NOTF-02 | P1 | S1 | Order confirmed: order number and summary |
| NOTF-03 | P1 | S1 | Payment failed: retry prompt |
| NOTF-04 | P1 | S1 | Shipped: carrier and tracking number |
| NOTF-06 | P1 | S1 | Delivered: confirmation and review invitation |
| NOTF-08 | P1-L | S1 | Refund completed, using the standard WooCommerce refunded-order email |
| NOTF-11 | P1 | S1 | Sending governed by marketing and transactional consent |
| NOTF-12 | P1-L | S1 | Frequency limits respected |
| NOTF-13 | P1 | S1 | Compliance with email provider policies |

Stories: Functional Specification MS-SPC-2026-032 v1.4, the notification stories, the criteria that trace to the rows
above. Email only at launch is agreed (OD-11).

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| REV-05 | P1 | The review invitation is #309's (specs/020); NOTF-06 names the same invitation |
| INT-05 | P1 | The sending service, its domain authentication and deliverability are #284's |
| NOTF-05, NOTF-14 | P2, P1-L S2 | Out for delivery email and admin-editable templates: not here |

## Open contract items

- NOTF-08 and NOTF-12 are P1-L with no reduction written in Part One 1.2. NOTF-08 is WooCommerce's own refunded order
  email, as the row says; NOTF-12 is met by sending each event's email once.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A new account receives the welcome email with its verification and its welcome discount details | NOTF-01 | provisional |
| AC-2 | A placed order sends a confirmation with its number and summary | NOTF-02 | provisional |
| AC-3 | A failed payment sends an email with a way to pay again | NOTF-03 | provisional |
| AC-4 | A shipped order sends an email with the carrier and the tracking number | NOTF-04 | provisional |
| AC-5 | A delivered order sends a confirmation and the review invitation | NOTF-06 | provisional |
| AC-6 | A refund sends the standard refunded order email | NOTF-08 | provisional |
| AC-7 | Transactional emails are sent whatever the customer's marketing choice; no marketing email is sent at launch | NOTF-11 | provisional |
| AC-8 | Each event's email is sent once | NOTF-12 | provisional |
| AC-9 | Emails are sent as transactional mail through the store's sending service, to the order's address only | NOTF-13 | provisional |

Provisional: verified by automated tests; acceptance follows the events triggered on staging and the emails received
in English and Arabic, and the client's approval. AC-4's tracking number comes from the carrier integration (#291,
Stage 4); until then the email says the details follow. AC-9's sending service and its domain authentication are
#284's (INT-05), and the sending address waits for OD-18.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Told at each step, in their language (Priority: High)

1. **Given** a customer, **When** they register, **Then** they receive the welcome email with their welcome code and
   the verification email.
2. **Given** an order, **When** it is placed, shipped, delivered or refunded, **Then** the customer receives the email
   for that step, once, in the order's language.
3. **Given** a failed payment, **When** it fails, **Then** the email offers a link to pay again.

### Edge Cases

- A customer who declined marketing still receives every order email.
- An order shipped twice (back from Out for Delivery) is told once.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Welcome, confirmation, delivered, refunded, failed payment | WooCommerce's new account, processing and on-hold, completed, refunded and customer failed order emails | Native, verified |
| Order's language | WooCommerce Multilingual sends WooCommerce's emails in the order's language | Native, verified |
| Welcome discount details | None | Gap: `EmailAdditions` |
| Pay again link | WooCommerce's text describes it without a link | Gap: `EmailAdditions` |
| Shipped email | None for the status model's Shipped | Gap: `Shipped`, `ShippedEmail` |

## Requirements

### Functional Requirements

- **FR-001**: The new account email MUST name the welcome discount, what it gives, its code and its last day (AC-1).
- **FR-002**: The failed order email MUST carry the link to pay for the order again (AC-3).
- **FR-003**: Entering Shipped MUST send one email with the carrier and the tracking number, in the order's language
  (AC-4, AC-8).
- **FR-004**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Shipment**: carrier, tracking number, tracking page, on the order.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- WhatsApp and SMS notifications (INT-03, INT-07, P2); admin-editable templates (NOTF-14, S2).

## Success Criteria

- **SC-001**: No contracted event goes without its email, and none is sent twice.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 3 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- No marketing email is sent at launch, so marketing consent governs nothing yet; when one is, it reads the consent
  #305 records.

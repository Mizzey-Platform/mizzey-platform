# Feature Specification: refunds with their payment reference

**Feature Branch**: `029-refunds`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slices E-ORD-4, issue #292 ("Refunds recorded from the standard order screen, with the standard refund
email"), and E-ADM-8, issue #293 ("A refund is recorded with its payment reference from the standard order screen").
Both rest on the payment layer of #283 (specs/028-payments), verified on staging on 1 November 2026. The owner's
instruction of 2 November 2026 puts them first in the work that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| RET-08 | P1-L | S1 | Refund, full or partial, recorded from the standard WooCommerce order screen with the customer notified by the standard refund email. Through the payment gateway where the integration supports it, otherwise through the provider's own process and recorded on the order |
| ADM-124 | P1 | S1 | Record a refund, full or partial, with its payment reference, from the standard order screen (RET-08) |

ADM-124 is marked [key] in the register. RET-08 is P1-L: returns are handled by the client's team through its
support channels (AC-14, OD-30), with no customer returns area and no approval workflow.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| PAY-07, PAY-08, PAY-17 | P1, P1-L | The refund through Paymob and the dashboard fallback are #283's (specs/028); this slice adds the reference and the email |
| PAY-06 | P1 | The payment's transaction reference stored against the order (specs/028), which each refund now carries |
| ENT-13 | P1 | Refund: amount, status, payment reference; the data model is #273's, this slice fills the fields |
| BR-006 | P1 | A refund never exceeds what was paid after discount (`RefundIntegrity`, specs/016) |
| NOTF-08 | P1-L | The standard refunded-order email (specs/021, AC-6) |
| ORD-12 | P1-L | The standard WooCommerce refund states (#248) |
| ROLE-06, AC-11 | | Refunds are a capability of their own (`RefundCapability`, specs/013; D-10). CX-01 stays pending for ROLE-06 |
| AC-14 | P1-L | Customer return request handled by the client's team; no returns area |

## Open contract items

- **OD-08, return window and exceptions.** Policy text only (RET-03 automatic eligibility is DEF); it does not change
  this slice.
- **CX-01.** Who holds the refund capability beyond the store owner and shop manager waits for the client's
  acknowledgement of the Accountant role; the capability itself is built (specs/013).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Staff with the refund permission record a full or a partial refund from the standard order screen, through the gateway where it supports refunds, otherwise recorded by hand | RET-08, ADM-124 | provisional |
| AC-2 | Every refund carries its payment reference: the reference of the payment it returns, and, where there is one, the refund's own reference from the provider or typed by staff; both are shown on the refund in the order screen | ADM-124 | provisional |
| AC-3 | A refund made through the provider's own process is recorded on the order with the payment reference | RET-08, ADM-124 | provisional |
| AC-4 | The customer receives the standard refunded-order email, full or partial, in the order's language, naming the refund's reference | RET-08 | provisional |
| AC-5 | Staff without the refund permission cannot record a refund, by the order screen or the REST API | ADM-124 | provisional |
| AC-6 | No customer returns area, return request form or approval workflow exists | RET-08 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Refund a card payment through Paymob (Priority: High)

**Independent test**: refund a paid Paymob order in part from the order screen, through the gateway.

1. **Given** an order paid by card, **When** permitted staff refund 10.00 of it through Paymob, **Then** the refund
   line shows the payment's reference and Paymob's refund reference, and the customer's refunded-order email names
   the refund reference.

### User Story 2 - Refund an order paid on delivery (Priority: High)

**Independent test**: record a manual refund on a cash-on-delivery order with a typed reference.

1. **Given** an order paid on delivery, **When** staff refund it by hand and type the reference of the money returned,
   **Then** the refund line shows that reference, marked as typed by staff, and the customer gets the email.

### User Story 3 - A refund made at Paymob (Priority: Medium)

1. **Given** a refund made in Paymob's dashboard, **When** Paymob reports it, **Then** the order records it once, with
   the payment's reference.

### Edge Cases

- A manual refund with no reference typed: allowed, the payment's reference (where the order has one) is still kept;
  the refund line says no refund reference was given.
- A reference typed but the refund made through the gateway: the provider's reference is kept, the typed one is not.
- A refund the provider refuses: no refund is kept (specs/028), so nothing is shown or emailed.
- Text typed into the reference: kept as plain text, at most 100 characters, never markup.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Full and partial refunds from the order screen | WooCommerce's refund panel (`wc_create_refund`, `WC_AJAX::refund_line_items`) | Native |
| Refund through the gateway | The Paymob plugin's `process_refund` (specs/028) | Native (the plugin) |
| Refunded-order email, full and partial | `WC_Email_Customer_Refunded_Order` | Native, verified (specs/021 AC-6) |
| Refund states | WooCommerce's Refunded status and partial refunds | Native |
| The payment reference on each refund | None: a refund keeps an amount, a reason and whether the gateway made it | Gap: the payment reference and the refund reference on the refund, shown on the refund line |
| A reference for a refund made by hand | None: the panel has a reason field, which the customer sees | Gap: a reference field on the refund panel |
| The reference in the email | None | Gap: one line in the standard email |

## Requirements

### Functional Requirements

- **FR-001**: Every refund MUST store the reference of the payment it returns (the order's transaction reference,
  PAY-06), where the order has one (AC-2).
- **FR-002**: A refund made through the gateway MUST store the provider's refund reference; a refund made by hand MUST
  store the reference staff typed, if any; a refund recorded from Paymob's report MUST be marked as made in Paymob's
  dashboard (AC-2, AC-3).
- **FR-003**: The order screen MUST show each refund's references and how it was made (AC-2).
- **FR-004**: The refund panel MUST offer a field for the reference of a refund made by hand; its value MUST be kept
  as plain text of at most 100 characters, and only from the refund request of permitted staff (AC-1, AC-5).
- **FR-005**: The refunded-order email MUST name the refund's reference when it has one, in the order's language
  (AC-4).
- **FR-006**: No customer-facing returns feature MUST be added (AC-6).
- **FR-007**: Every customer-facing string MUST be translated (FIX-04); staff text is English (ADM-159).

### Key Entities

- **Refund** (WooCommerce's order refund): amount, reason, whether the gateway made it, and now the payment reference,
  the refund reference and its source (gateway, Paymob's dashboard, staff).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- A returns workflow, return requests, return cases and their audit trail (RET-01 to RET-07, RET-09, RET-10,
  ADM-120 to ADM-123, ADM-125, ADM-126, ACCT-10, NOTF-07): DEF or P2.

## Success Criteria

- **SC-001**: On staging, a partial gateway refund and a manual refund each show their references on the order screen
  and in the customer's email, in English and in Arabic.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026, while the brand identity is awaited (D-22, D-27). Delivered
and offered for acceptance in contract order.

## Assumptions

- The refund's "payment reference" in ADM-124 is read as both the reference of the payment refunded and, where one
  exists, the refund's own reference; both are kept, so either reading is met.
- A refund made by hand needs no reference: cash returned on delivery may have none. The field is offered, not forced.

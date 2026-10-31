# Feature Specification: checkout

**Feature Branch**: `019-checkout`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 3 of the Stage 3
build plan under D-22: "E-SF-8 checkout: order placement in both languages, BR-005 price snapshot, ENT-08 order
snapshot, CHK validation messages in both languages (CHK-11), and the stock safeguard". #255. The function layer; the
designed checkout waits for PRE-03b. Built on `018-cart` (the stock check and fixture) and, through it, the stock seam
(#245).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| CHK-01 | P1 | S1 | Address entry with Egyptian structure: governorate, city, district, street, landmark |
| CHK-02 | P1 | S1 | Saved address selection for returning customers |
| CHK-03 | P1 | S1 | Shipping method selection with cost |
| CHK-04 | P1 | S1 | Payment method selection |
| CHK-05 | P1 | S1 | Order review step before confirmation |
| CHK-06 | P1 | S1 | Order notes field |
| CHK-07 | P1 | S1 | Coupon entry at checkout |
| CHK-08 | P1 | S1 | Clear total breakdown including all charges |
| CHK-09 | P1 | S1 | Terms acceptance |
| CHK-10 | P1 | S1 | Mobile-optimised checkout |
| CHK-11 | P1 | S1 | Validation with clear error messages in both languages |
| CHK-12 | P1 | S1 | Stock re-validated at the point of order placement, by reserving it in your ERP (ERP-04) |
| BR-005 | P1 | S1 | Price snapshot. Every order item stores the price charged at the moment of order, and a later price change never alters a past order |
| ENT-08 | P1 | S1 | Order / Order Item: snapshot of prices, discounts and products at the moment of purchase |

Stories: Functional Specification MS-SPC-2026-032 v1.4, the checkout stories, the criteria that trace to the rows
above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ERP-04, ERP-05 | P1-E, P1 | How the ERP holds and confirms a reservation, and no sale against unvalidated stock: E-ERP's, after PRE-09; this slice reserves through the seam |
| BR-003, BR-007 | P1 | #290's rows, pending PRE-09: the store side of both (reserve at placement, release on cancellation) is built here |
| PAY-09 | P1 | Cash on delivery, #283's; switched on in staging only as the Stage 3 test fixture (the build plan) |
| CHK-13 | P2 | Address autocomplete via maps: not built |

## Open contract items

- OD-09 (VAT and invoicing) is open: no VAT field is added; the order records WooCommerce's own tax lines.
- The district is required and the landmark optional: a working default (an owner decision, not a client
  confirmation).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The checkout address takes governorate, city, district, street and landmark, with district required and the landmark optional | CHK-01 | provisional |
| AC-2 | A returning customer's saved address is offered at checkout | CHK-02 | provisional |
| AC-3 | The checkout offers shipping methods with their cost and the payment methods, and shows a total breakdown with every charge | CHK-03, CHK-04, CHK-08 | provisional |
| AC-4 | The order is reviewed before it is confirmed, with a notes field, a coupon entry and terms acceptance | CHK-05, CHK-06, CHK-07, CHK-09 | provisional |
| AC-5 | The checkout works on a phone | CHK-10 | provisional |
| AC-6 | A missing or invalid field is refused with a clear message naming it, in English and in Arabic | CHK-11 | provisional |
| AC-7 | At placement each line's stock is reserved with the stock source; a line it cannot reserve refuses the order before payment | CHK-12 | pending PRE-09 |
| AC-8 | Each order item keeps the price, discount and product charged; a later product change never alters it | BR-005, ENT-08 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval.
AC-7 is pending PRE-09: it is verified against a mock and the development fixture, not the ERP (M-3). AC-2 is
WooCommerce's saved address, prefilled for a signed-in customer; choosing among several saved addresses comes with the
address book (#308) and the designed checkout (PRE-03b, a block extension). AC-4 and AC-5 are WooCommerce's block
checkout (terms once the client's terms page is set); their designed form is PRE-03b's, with the D-21 accessibility and
performance gates.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Place an order in either language (Priority: High)

1. **Given** a cart, **When** the customer checks out, **Then** the address asks for governorate, city, district,
   street and building, and a landmark; shipping and payment methods are offered with their costs; the total breaks
   down; a note can be added.
2. **Given** a missing district, **When** the customer places the order, **Then** it is refused with a message naming
   the district, in the checkout's language.

### User Story 2 - Never sell what is not there (Priority: High)

1. **Given** an order being placed, **When** a line's stock cannot be reserved with the stock source, **Then** the
   order is refused before payment and nothing stays reserved.
2. **Given** a cancelled or failed order, **When** its status changes, **Then** its reservation is released, once.

### User Story 3 - What was charged stays charged (Priority: High)

1. **Given** a placed order, **When** the product's price changes, **Then** the order keeps the price charged.

### Edge Cases

- A placement retried after an interruption reserves nothing twice (one operation key per order line); one refused
  and undone asks again with new keys.
- WooCommerce's own checkout hold, order stock reduction and restoration, refund restocking and line-edit stock
  adjustment change nothing.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Shipping, payment, review, notes, coupon, terms, totals | WooCommerce's block checkout and Store API | Native, verified |
| Saved address | The customer's saved billing and shipping address, prefilled | Native; several addresses with #308 |
| Price snapshot | Order items store their totals | Native, verified (P-002, P-017) |
| Egyptian address | Egypt's governorates as states; no Egyptian address format | Gap: `EgyptianAddress` (country locale, district and landmark fields) |
| Stock at placement | WooCommerce holds and reduces its own stock | Gap: `OrderStock`, through the adapter (D-19 item 5) |
| Validation messages | WooCommerce's, translated by its Arabic pack | Native; Mizzey labels translated |

## Requirements

### Functional Requirements

- **FR-001**: Egypt's address MUST take governorate (required), city, street and building, district (required) and a
  landmark (optional), and hide the postcode and second line (AC-1).
- **FR-002**: No WooCommerce order path MAY change stock: checkout holds, reduction, restoration, refund restocking and
  line-edit adjustment are off (`LocalStockWriters`, specs/018; verified again here) (AC-7).
- **FR-003**: At placement, on the block and classic checkouts, each line MUST be reserved through the gateway, once
  per line; any failure MUST release the lines reserved and refuse the order before payment, with a message (AC-7).
- **FR-004**: A cancelled or failed order MUST release its reservation once (AC-7).
- **FR-005**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Order reservation**: reserved or not, released or not, and the attempt its keys belong to, on the order.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Address autocomplete (CHK-13, P2).

## Success Criteria

- **SC-001**: No order is placed for a line the stock source did not reserve.
- **SC-002**: No order changes stock in WooCommerce's own fields.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 3 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- A release or undo the stock source does not answer is marked unsettled for reconciliation with the ERP (PRE-09);
  a refund's restocking is the ERP's, not WooCommerce's.
- A paid order the stock source refuses (a payment callback that arrives without a reservation) cannot be refused here:
  it is marked for staff with a note, and the status model decides where it may move next.
- The classic checkout is not the storefront's (the block checkout is); it reserves the same way, and its address does
  not carry the district fields.

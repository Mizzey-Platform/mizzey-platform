# Feature Specification: order management in admin

**Feature Branch**: `030-order-management`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ADM-5, issue #280 ("Order management in admin: find, annotate, ship, cancel, refund, print"). The
invoice (ADM-92) is specs/023-invoice, verified on staging on 31 October 2026; this spec covers the rest of the slice.
The owner's instruction of 2 November 2026 puts it second in the work that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-85 | P1 | S1 | Search/filter by order number, customer, phone, email, status, payment, date, carrier |
| ADM-88 | P1 | S1 | Internal notes |
| ADM-89 | P1-L | S1 | Create and attach shipment and tracking |
| ADM-90 | P1 | S1 | Cancel order |
| ADM-91 | P1 | S1 | Full or partial refund, subject to permissions |
| ADM-93 | P1-L | S1 | Print / download packing slip |

ADM-92 (invoice) is specs/023. ADM-93 is per order: bulk packing-slip printing (ADM-156) is DEF.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-86, ADM-87 | P1 | Order detail and validated status updates are #248's (specs/010) |
| ADM-157 | P1 | Order search by phone is E-ADM-11's; WooCommerce's own search covers it, checked here for ADM-85 |
| ORD-08, BR-007 | P1 | Cancelled with a recorded reason (`CancellationField`, specs/010); stock on cancellation is the ERP's (P1-E) |
| SHIP-03, SHIP-09 | P1, P1-L | The carrier integration (#291) creates the shipment and writes the same fields |
| RET-08, ADM-124 | P1-L, P1 | Refunds and their reference (specs/029); the refund permission (specs/013) |
| ADM-92 | P1-L | The invoice (specs/023), whose PDF library the slip shares |

## Open contract items

- **CR-12, OD-18.** The legal entity details on documents: the slip shows the seller name the invoice settings hold,
  a placeholder until the client supplies them.
- **#291.** Until the Bosta integration creates shipments, staff attach them by hand; after it, they correct them.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Orders are found by order number, customer, phone and email, and filtered by status, payment method, date and carrier; the filters combine | ADM-85 | provisional |
| AC-2 | Internal notes are added to an order and never shown to the customer | ADM-88 | provisional |
| AC-3 | A shipment with its tracking number is attached to the order and can be corrected; the customer sees the tracking number | ADM-89 | provisional |
| AC-4 | An order is cancelled with a reason recorded; cancelling a paid order does not by itself issue a refund | ADM-90 | provisional |
| AC-5 | A full or partial refund is made only by staff with the refund permission | ADM-91 | provisional |
| AC-6 | A packing slip is printed or downloaded for one order | ADM-93 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Find the order the customer is calling about (Priority: High)

1. **Given** a customer who gives their phone number, **When** staff search the orders list, **Then** the order is
   found; **When** staff filter by cash on delivery, a carrier and last week, **Then** only those orders remain.

### User Story 2 - Ship and correct (Priority: High)

1. **Given** a prepared order, **When** staff enter the carrier and tracking number, **Then** the order keeps them,
   the notes say who entered them, and the customer sees the tracking number on their order; **When** the code was
   wrong, **Then** staff correct it and the correction is noted.

### User Story 3 - Pack the parcel (Priority: High)

1. **Given** an order, **When** staff print its packing slip, **Then** it lists each item with its SKU and quantity,
   the delivery address and phone and the customer's note, without prices, in the order's language.

### User Story 4 - Cancel (Priority: Medium)

1. **Given** a paid order, **When** staff cancel it with a reason, **Then** the reason is kept and no refund is made
   until a permitted member of staff makes one.

### Edge Cases

- An item refunded before packing: the slip shows only what is left to pack.
- A tracking address that is not a web address: not kept.
- A filter with an invalid date or method: ignored.
- A customer or visitor opening a slip link: refused.
- AC-5 covers the status route: staff without the refund permission cannot move an order to Refunded by any route (t70).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Search by number, customer, phone, email | WooCommerce's order search ("All") on its order tables | Native, verified |
| Filter by status, month, registered customer | WooCommerce's orders list | Native |
| Filter by payment method, carrier, date range | None in the list | Gap: three filters on the list's own hooks |
| Internal notes | Private order notes | Native, verified |
| Shipment and tracking on the order | None (a separate Shipment Tracking extension, not installed, D-24) | Gap: three fields on the order screen, shared with #291 and the Shipped email |
| Cancel with a reason | Cancelled status; the reason is specs/010's | Native plus specs/010 |
| Refund within permissions | Refund panel; `RefundCapability` (specs/013) | Native plus specs/013 |
| Packing slip | None (a plugin would be needed, D-24 says our own) | Gap: our own, with the invoice's PDF library |

## Requirements

### Functional Requirements

- **FR-001**: The orders list MUST offer filters by payment method, carrier and a date range, combining with each
  other and with WooCommerce's own search and filters (AC-1).
- **FR-002**: The order screen MUST let staff who edit orders attach and correct the carrier, the tracking number and
  an optional tracking address, with the form's nonce; each change MUST be noted on the order with who made it (AC-3).
- **FR-003**: The customer's order view MUST show the carrier and tracking number once there is one, in their
  language (AC-3).
- **FR-004**: Staff who edit orders MUST be able to open a packing slip PDF for one order from the order screen and
  the orders list; nobody else MUST be given it (AC-6).
- **FR-005**: The packing slip MUST list each item still to pack with its options, SKU and quantity, the delivery
  name, address and phone, the customer's note and the shipment, and MUST NOT show prices; it MUST be in the order's
  language and direction (AC-6).
- **FR-006**: Every customer-facing string MUST be translated (FIX-04); staff text is English (ADM-159).

### Key Entities

- **Order shipment**: carrier, tracking number, tracking address on the order (the fields #291 and the Shipped email
  use).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Saved list views (ADM-144, DEF), bulk packing slips (ADM-156, DEF), bulk dispatch and airway bills (SHIP-10,
  SHIP-11), order editing after placement (ADM-141, DEF).

## Success Criteria

- **SC-001**: On staging, an order is found by phone, filtered by method, carrier and dates, given a shipment that
  the customer sees, and its packing slip printed, in English and in Arabic.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27). Delivered and offered for acceptance in
contract order.

## Assumptions

- The store runs WooCommerce's own order tables (HPOS), as the runtime and staging do; the filters extend that list.
- The packing slip is a staff document in the order's language, so it can travel in the parcel.

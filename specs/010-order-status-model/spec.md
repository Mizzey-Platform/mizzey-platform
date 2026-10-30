# Feature Specification: order status model and the record of when each status was entered

**Feature Branch**: `010-order-status-model`

**Created**: 2026-10-30

**Status**: Draft

**Work type**: requirement

**Input**: User description: "start wave 1 with #248" (the owner, 30 October 2026), under D-22 and the Stage 3 build
plan (`docs/2026-10-30-stage3-build-plan.md`, wave 1). E-ORD-1 (#248). Function layer only: the status labels a
customer sees exist as plain translated text so the store works, and their designed presentation waits for PRE-03b
(D-14 item 16, D-22 item 2).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ORD-01 | P1 | S1 | Pending Payment: Order created, awaiting payment |
| ORD-02 | P1 | S1 | Payment Failed: Payment failed; order not confirmed |
| ORD-03 | P1 | S1 | Confirmed: Payment or method accepted, ready to prepare |
| ORD-04 | P1 | S1 | Preparing: Warehouse picking and packing |
| ORD-05 | P1 | S1 | Shipped: Handed to carrier, tracking available |
| ORD-06 | P1 | S1 | Out for Delivery: With the courier |
| ORD-07 | P1 | S1 | Delivered: Delivered to customer |
| ORD-08 | P1 | S1 | Cancelled: Cancelled with recorded reason |
| ORD-12 | P1-L | S1 | Refunded, fully or partially, using the standard WooCommerce refund states |
| ORD-13 | P1 | S1 | Delivery Failed / Returned to Origin: a distinct state, never recorded as Delivered |
| ORD-14 | P1-L | S1 | COD Collected, Awaiting Remittance: Courier holds the cash; funds not yet received |
| ADM-86 | P1 | S1 | Order detail: items, prices, discounts, customer, address, payment, shipment, notes, timeline |
| ADM-87 | P1 | S1 | Update status with validation |

Stories: Functional Specification MS-SPC-2026-032 v1.4, US-07-10 (Order status model), US-18-02 (Order detail: "The
timeline records every status change with actor and timestamp"), US-18-03 (Status updates).

## Context rows (no obligation)

| ID | Scope | Why it matters here |
|---|---|---|
| ORD-09, ORD-10, ORD-11 | DEF | Return statuses are not built. No status, transition or placeholder is added for them |
| RPT-06 | P2 | Order-status reporting is not built. The record of when each status was entered is kept for the contracted uses below, not as a report |
| SHIP-13, SHIP-14, SHIP-16, SHIP-17 | P1, P1, P1-L, P1 | Owned by #291. The carrier mapping, the cash collected and remitted amounts and the shipment record are #291's (ADR-0002). This feature gives them the statuses to map to and adds no carrier logic |
| REV-01 to REV-05 | P1 | Owned by #309. D-17 makes a review depend on a delivered purchase; this feature makes the delivered set and the time delivery was established queryable |
| NOTF-03, NOTF-04, NOTF-06 | P1 | Owned by #285. No new customer email is sent by this feature |
| ERP-01, ERP-06 | P1, P1-E | Stock belongs to the ERP (D-19 item 5). This feature adds no stock write |
| ROLE-01 to ROLE-09 | | Owned by #251. This feature uses WooCommerce's order capabilities as they stand |

## Open contract items

- CX-01 (resolved): financial detail on the order screen is visible only to roles with financial permission. ADM-86's
  financial view is not changed here.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Every contracted state exists as a distinct order status with its register name: Pending Payment, Payment Failed, Confirmed, Preparing, Shipped, Out for Delivery, Delivered, Cancelled, Refunded, Delivery Failed / Returned to Origin, and COD Collected, Awaiting Remittance | ORD-01, ORD-02, ORD-03, ORD-04, ORD-05, ORD-06, ORD-07, ORD-08, ORD-12, ORD-13, ORD-14 | final |
| AC-2 | A status update is validated: a change the allowed-transition table does not permit is refused, the order keeps its status, and the person is told why. The rule applies to the order screen, bulk actions, the REST API and code paths alike | ADM-87 | final |
| AC-3 | A cancellation records its reason. A cancellation without a reason is refused when a person makes it, and a system cancellation records the system's reason | ORD-08, ADM-87 | final |
| AC-4 | Delivery Failed / Returned to Origin is a distinct status. It is never counted as Delivered, and no path moves an order from it to Delivered | ORD-13 | final |
| AC-5 | COD Collected, Awaiting Remittance is entered only from Out for Delivery or Shipped, which establishes that delivery happened, and it leads to Delivered when the remittance is recorded | ORD-14, ORD-07 | final |
| AC-6 | Refunds use WooCommerce's standard refund states: a full refund sets Refunded, a partial refund leaves the status and records the refund | ORD-12 | final |
| AC-7 | The order timeline records every status change with its actor and timestamp | ADM-86, ADM-87 | final |
| AC-8 | When an order first entered each status is stored as order data and can be queried, not read from note text | ADM-86, ORD-07, ORD-14 | final |

## The status model

| Register state | Status | Source |
|---|---|---|
| ORD-01 Pending Payment | `pending` | WooCommerce |
| ORD-02 Payment Failed | `failed` | WooCommerce |
| ORD-03 Confirmed | `processing`, named Confirmed | WooCommerce |
| ORD-04 Preparing | `preparing` | New |
| ORD-05 Shipped | `shipped` | New |
| ORD-06 Out for Delivery | `out-for-delivery` | New |
| ORD-14 COD Collected, Awaiting Remittance | `cod-collected` | New |
| ORD-07 Delivered | `completed`, named Delivered | WooCommerce |
| ORD-08 Cancelled | `cancelled` | WooCommerce, plus a recorded reason |
| ORD-12 Refunded | `refunded`, and partial refunds as WooCommerce records them | WooCommerce |
| ORD-13 Delivery Failed / Returned to Origin | `returned-to-origin` | New |

WooCommerce's `on-hold` (used by payment methods that wait for confirmation) and `checkout-draft` (an internal state of
the block checkout) stay as WooCommerce has them. They are not register states and are not renamed or removed.

**Why Delivered is WooCommerce's Completed.** Completed is what WooCommerce's reports, its sales figures, its completed
date and the verified-purchase check already read as finished. A new Delivered status would need every one of them
rewired. A cash-on-delivery order passes through COD Collected on delivery and reaches Delivered when its remittance is
recorded, so a card order and a settled cash order end in the same place. **Owner to confirm (Q1).**

**Allowed transitions** (staff, bulk actions, REST and code alike):

| From | To |
|---|---|
| Pending Payment | Confirmed, On hold, Payment Failed, Cancelled |
| Payment Failed | Pending Payment, Confirmed, Cancelled |
| On hold | Confirmed, Payment Failed, Cancelled |
| Confirmed | Preparing, Cancelled, Refunded |
| Preparing | Shipped, Confirmed, Cancelled, Refunded |
| Shipped | Out for Delivery, Delivered, COD Collected, Returned to Origin |
| Out for Delivery | Delivered, COD Collected, Returned to Origin, Shipped |
| COD Collected | Delivered, Refunded |
| Delivered | Refunded |
| Returned to Origin | Refunded, Cancelled |
| Cancelled | none |
| Refunded | none |

A full refund moves any paid status to Refunded, as WooCommerce does. Cancellation stops at Preparing: once a parcel is
with the carrier, its outcome is Delivered or Returned to Origin.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staff move an order through its states (Priority: High)

The warehouse and customer service move an order from Confirmed to Preparing, Shipped, Out for Delivery and Delivered,
and the store refuses a move that skips or reverses the model.

**Why this priority**: every later slice (shipping, emails, reviews, the account's order history) reads these states.

**Independent Test**: create an order, walk it through the table above from the order screen and over the REST API,
try forbidden moves, and read the result.

**Acceptance Scenarios**:

1. **Given** a Confirmed order, **When** staff set Preparing, then Shipped, then Out for Delivery, then Delivered,
   **Then** each change is accepted and the timeline shows each one with the person and the time (AC-1, AC-7)
2. **Given** a Confirmed order, **When** staff set Delivered directly, **Then** the change is refused, the order stays
   Confirmed and the screen says which moves are allowed (AC-2)
3. **Given** two orders selected in the list, one Confirmed and one Delivered, **When** staff apply "Preparing" in
   bulk, **Then** the Confirmed one moves, the Delivered one stays, and the result says which was refused (AC-2)
4. **Given** a Delivered order, **When** a REST request sets Shipped, **Then** the request fails with a clear error and
   the order stays Delivered (AC-2)

---

### User Story 2 - Cancellations carry a reason (Priority: High)

**Independent Test**: cancel with and without a reason, as staff and as the system.

**Acceptance Scenarios**:

1. **Given** a Preparing order, **When** staff cancel it without a reason, **Then** the cancellation is refused (AC-3)
2. **Given** a Preparing order, **When** staff cancel it with a reason, **Then** it is Cancelled and the reason is
   stored and shown in the timeline (AC-3)
3. **Given** an unpaid order WooCommerce cancels when its payment window lapses, **When** it is cancelled, **Then** the
   recorded reason says so (AC-3)
4. **Given** a Shipped order, **When** staff try to cancel it, **Then** it is refused (AC-2)

---

### User Story 3 - Failed deliveries and cash on delivery are never mistaken for success (Priority: High)

**Independent Test**: drive one order to Returned to Origin and one cash order through COD Collected.

**Acceptance Scenarios**:

1. **Given** an Out for Delivery order, **When** it is set to Returned to Origin, **Then** it is not in the delivered
   set and cannot be set to Delivered (AC-4)
2. **Given** an Out for Delivery cash order, **When** it is set to COD Collected, **Then** it is in the delivered set,
   the time delivery was established is recorded, and it can move to Delivered (AC-5, AC-8)
3. **Given** a Confirmed order, **When** COD Collected is set, **Then** it is refused (AC-5)

---

### User Story 4 - When an order entered each status is a fact (Priority: Medium)

**Independent Test**: walk an order through several statuses and query, by order data only, when it first entered each.

**Acceptance Scenarios**:

1. **Given** an order walked to Delivered, **When** its record is read, **Then** the first time it entered each status
   is returned, equal to the times in its timeline (AC-8)
2. **Given** an order that returns to Shipped from Out for Delivery and goes out again, **When** the record is read,
   **Then** the first time it entered Out for Delivery is unchanged (AC-8)
3. **Given** a full refund, **When** the order is read, **Then** it is Refunded; **given** a partial refund, **Then** the
   status is unchanged and the refund is recorded (AC-6)

### Edge Cases

- A payment method moves an order on its own (Pending Payment to Confirmed, On hold or Payment Failed): allowed by the
  table, so payment callbacks are never blocked.
- A move to the status an order already has: no change, no timeline entry, nothing refused.
- An order created by staff in admin starts as Pending Payment, as WooCommerce does.
- Orders that exist before this feature: their status stays, and the record of first entry starts from the next change.
- A status a deactivated plugin leaves behind: the order keeps it; the table refuses moves into it.
- Both languages: the order's language, as stored at checkout, is unchanged by every status move.

## Native coverage

| Capability | Evidence | Status |
|---|---|---|
| Eight order statuses, none for Returned to Origin | P-012 (WooCommerce 11.1.0: pending, processing, on-hold, completed, cancelled, refunded, failed, checkout-draft) | PREVIOUSLY TESTED |
| No structured status history; only order-note prose | P-018 | PREVIOUSLY TESTED |
| Timeline of status changes with actor and time | Order notes: WooCommerce adds a note on every status change, attributed to the person for a manual change (P-018 recorded the notes) | PREVIOUSLY TESTED (attribution UNVERIFIED) |
| Full and partial refunds with their states | WooCommerce refunds; P-014 (the refund API refuses more than the remaining paid amount) | PREVIOUSLY TESTED |
| Registering extra order statuses, HPOS and legacy storage | WooCommerce's order status filters | UNVERIFIED |
| The order's language stored at checkout | WooCommerce Multilingual 5.5.7, `inc/class-wcml-orders.php` (`wpml_language` order meta, set before save and on classic checkout) | UNVERIFIED for the block checkout |
| Transition validation | None in WooCommerce | GAP |
| Cancellation reason | None in WooCommerce | GAP |

## Requirements

### Functional Requirements

- **FR-001**: The store MUST offer the eleven register states as order statuses, as the status model table maps them,
  with their register names as translatable labels in both languages (AC-1)
- **FR-002**: Every status change MUST be checked against the allowed-transition table, whoever or whatever makes it;
  a forbidden change MUST leave the order unchanged and report why (AC-2)
- **FR-003**: A cancellation MUST store a reason; a person cancelling MUST give one; a system cancellation MUST record
  its own (AC-3)
- **FR-004**: Returned to Origin MUST NOT count as delivered and MUST NOT lead to Delivered (AC-4)
- **FR-005**: COD Collected MUST be enterable only from Shipped or Out for Delivery and MUST count as delivered (AC-5)
- **FR-006**: Refunds MUST use WooCommerce's refund states unchanged (AC-6)
- **FR-007**: Every status change MUST appear in the order timeline with its actor and time (AC-7)
- **FR-008**: The first time an order entered each status MUST be stored as order data, written once, and readable by
  a query; it MUST NOT be derived from note text (AC-8)
- **FR-009**: The store MUST expose one definition of the delivered set (Delivered and COD Collected) for #309 and other
  readers (AC-4, AC-5)
- **FR-010**: The new statuses MUST NOT add any stock reduction or restoration of their own (D-19 item 5); moves among
  them MUST leave stock unchanged
- **FR-011**: The new statuses that follow payment (Preparing, Shipped, Out for Delivery, COD Collected) MUST be treated
  as paid wherever WooCommerce counts paid orders, so sales figures stay complete; Returned to Origin MUST NOT be
- **FR-012**: All of this MUST work with WooCommerce's order storage as configured (HPOS or legacy) and in both
  languages

### Key Entities

- **Order status**: one of the eleven states, plus WooCommerce's On hold and the internal checkout draft.
- **Status entry record**: per order, for each status it has entered, the time it first entered it.
- **Cancellation reason**: per cancelled order, the reason and who gave it.
- **Delivered set**: Delivered and COD Collected.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | Bulk actions in the order list for Preparing, Shipped and Out for Delivery | Saves the warehouse a click per order. Everyday admin tasks are #278's | Yes, small |

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Return statuses | ORD-09, ORD-10, ORD-11, DEF |
| Order-status and delivery-time reporting | RPT-06, P2; the event log ADR-0002 rejects |
| Carrier-driven status changes | SHIP-13, SHIP-14, #291 |

## Success Criteria

- **SC-001**: An order can be taken through every contracted state in both languages, and every forbidden move is
  refused, on staging.
- **SC-002**: For any order, when it first entered each status is answered from order data alone, and matches its
  timeline to the second.
- **SC-003**: No Returned to Origin order is ever counted as delivered, in any query or report this feature defines.
- **SC-004**: Stock is unchanged by every move among the new statuses.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 1 of the Stage 3 build plan, from 30
October 2026. Customer-facing status presentation follows PRE-03b.

## Assumptions

- Staff who can edit orders today (WooCommerce's order capabilities) make status changes; #251 may narrow this later.
- Bosta's status codes are mapped onto these statuses by #291 once Bosta's legend arrives (ADR-0002). Until then staff
  set shipping states by hand.
- The cash remittance itself (amounts, reference) is recorded by #291 (SHIP-16); this feature moves the status only.

# Feature Specification: shipping through Bosta

**Feature Branch**: `041-bosta`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Issue #291 ("Shipping through Bosta with an explicit status mapping, governorate rates and cash-on-delivery
reconciliation"). Its predecessor #248 (the order status model, specs/010) is merged; the shipment fields on the order
are specs/030. The owner's instruction of 2 November 2026 puts it in the work that needs no client input: it is built
against the official Bosta plugin's documented behaviour and a mock, and the account, the rate card and the status
legend are filled in when they arrive (D-10, M-10).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| SHIP-01 | P1 | S1 | Shipping zones and rates manageable from admin |
| SHIP-03 | P1 | S1 | Shipment created and tracking number linked to the order |
| SHIP-04 | P1-L | S1 | Delivery estimate shown to the customer where available |
| SHIP-05 | P1 | S1 | Free-shipping promotion overrides the shipping rate |
| SHIP-08 | P1-L | S1 | Bosta via its official WooCommerce integration, wrapped in an abstraction layer |
| SHIP-09 | P1-L | S1 | Order pushed to Bosta with a tracking code returned automatically |
| SHIP-12 | P1-L | S1 | Pickup requests created and tracked |
| SHIP-13 | P1-L | S1 | Carrier status retrieved and reflected on the order |
| SHIP-14 | P1 | S1 | Explicit carrier-status-to-order-status mapping, documented and visible in admin |
| SHIP-15 | P1 | S1 | Rates configured as manual zones by governorate, matching your carrier rate card |
| SHIP-16 | P1-L | S1 | Cash-on-delivery reconciliation: collected versus remitted recorded per order |
| SHIP-17 | P1 | S1 | Returned-to-origin handled as a distinct state, never silently recorded as delivered |
| INT-02 | P1-L | S1 | Shipping carrier, Bosta |

SHIP-14, SHIP-16 and SHIP-17 are marked [key] in the register.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ORD-13, ORD-14 | P1 | The statuses the mapping moves an order to (specs/010) |
| PAY-09 | P1 | Cash on delivery, the payment the reconciliation follows |
| ADM-106 | P1 | The shipment fields on the order (specs/030), which the carrier's tracking fills |
| SHIP-02, SHIP-06, SHIP-07, SHIP-18 | P2 | Live rates, several carriers, scheduled delivery, carrier tickets: not built |
| SHIP-10, SHIP-11, ADM-155, ADM-156 | DEF | Bulk dispatch and bulk airway bills: not built, even where the plugin offers them |

## Open contract items

- **CR-03.** The Bosta account, its API key and the rate card by governorate. Until then the plugin is not connected
  and the zones carry example rates.
- **I-08.** Bosta's status code legend. The plugin ships none; until it arrives the mapping table is empty and every
  code Bosta sends puts the order in review (ADR-0002).
- **OD-20.** The remittance cycle. Remittances are entered by staff on the order until it is decided whether they are
  imported from a statement.
- **OD-05.** Whether cash on delivery is switched on at launch (PAY-09 leaves the switch to the client).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Staff manage shipping zones by governorate with their rates in admin, and a free-shipping promotion overrides the rate | SHIP-01, SHIP-05, SHIP-15 | pending CR-03 |
| AC-2 | A rate shows the customer its delivery estimate, in their language, where staff gave one | SHIP-04 | provisional |
| AC-3 | Orders are pushed to Bosta through its official plugin, behind a carrier layer; the tracking number returned is linked to the order and shown to the customer; pickups are requested and followed in the plugin | SHIP-03, SHIP-08, SHIP-09, SHIP-12, INT-02 | pending CR-03 |
| AC-4 | A carrier status is reflected on the order through a mapping table visible and editable in admin; a code with no mapping, or a move the status model refuses, never moves the order and marks it for review | SHIP-13, SHIP-14 | pending I-08 |
| AC-5 | Returned to origin is its own state and is never recorded as delivered | SHIP-17 | provisional |
| AC-6 | For each cash order, the amount collected and the amount remitted are recorded apart, and staff can list the orders collected and not remitted | SHIP-16 | pending OD-20 |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A delivered cash order (Priority: High)

1. **Given** a cash order shipped through Bosta, **When** Bosta reports it delivered, **Then** the order is COD
   Collected with the amount and time kept; **When** staff record Bosta's remittance, **Then** the order is Delivered
   and the remittance is on the order.

### User Story 2 - A parcel that comes back (Priority: High)

1. **Given** a shipped order, **When** Bosta reports a code mapped to returned to origin, **Then** the order is
   Delivery Failed / Returned to Origin, never Delivered.

### User Story 3 - A code nobody mapped (Priority: High)

1. **Given** a code with no mapping, **When** Bosta sends it, **Then** the order stays where it is, is marked for
   review with the reason, and the code appears in the mapping table for staff to map.

### Edge Cases

- The same code sent twice: applied once.
- A move the status model refuses (for example delivered while still Processing): review, no move.
- A tracking number typed by staff: kept; the carrier's fills the shipment only when staff gave none.
- A remittance on a card order: not offered.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Zones by governorate, rates, free shipping by coupon | WooCommerce shipping zones (Egypt's governorates are its states), flat rate, free shipping | Native, verified |
| Push to Bosta, tracking number, pickups, webhook | The official Bosta plugin (D-24) | Native to the plugin; its own status mapping is switched off in the plugin |
| The status mapping, review, returned to origin | None (the plugin's own mapping takes code 46 as completed) | Gap: `CarrierStatus` |
| Collected versus remitted | None | Gap: `CodReconciliation` |
| Delivery estimate per rate and language | None | Gap: `DeliveryEstimate` |
| Carrier layer | None | Gap: `Carrier`, `BostaCarrier` |

## Requirements

### Functional Requirements

- **FR-001**: The carrier MUST be reached through a carrier interface; Bosta is its one implementation, reading what
  the official plugin writes on the order (AC-3).
- **FR-002**: The carrier's tracking number MUST fill the order's shipment when staff gave none, so the customer's
  order view and the Shipped email show it (AC-3).
- **FR-003**: Each new carrier code on an order MUST be applied once, through the mapping table (WooCommerce, Settings,
  Shipping, Carrier statuses), to one of: in transit, out for delivery, delivered, returned, informational (AC-4).
- **FR-004**: A code with no mapping, or a move the status model refuses, MUST leave the order where it is, mark it
  for review with the reason, and list the code in the table (AC-4).
- **FR-005**: Returned MUST move the order to Delivery Failed / Returned to Origin (AC-5).
- **FR-006**: Delivered on a cash order MUST move it to COD Collected and keep the amount and time once; staff MUST
  record the remittance's amount, date and reference, which moves the order to Delivered (AC-6).
- **FR-007**: The orders list MUST offer the cash orders collected and not remitted (AC-6).
- **FR-008**: Each rate MAY carry an English and an Arabic estimate; the customer sees the one of their language (AC-2).
- **FR-009**: The table and the remittance MUST need `manage_woocommerce` or `edit_shop_orders` and a nonce.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Live carrier rates, other carriers, scheduled delivery, carrier tickets (P2); bulk dispatch and bulk airway bills
  (DEF); importing remittance statements (OD-20).

## Success Criteria

- **SC-001**: On staging, with the Bosta plugin and a mock webhook, a mapped delivered code moves a cash order to COD
  Collected, a returned code to Returned to Origin, an unmapped code to review, and a recorded remittance to Delivered.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- The Bosta plugin's webhook writes `bosta_state_code` and `bosta_status` on the order and saves it; its own mapping
  (which takes code 46 as completed) is commented out in the version installed (P-012).
- Until I-08 the table is empty: every code is reviewed by staff, which is the safe default ADR-0002 asks for.

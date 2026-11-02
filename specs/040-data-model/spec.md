# Feature Specification: the contracted data model

**Feature Branch**: `040-data-model`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-DATA-1, issue #273 ("The contracted data model, stored, related and documented"). Most entities are
native WooCommerce, WordPress or WPML structures (M-4); the work is the record of where each lives, how it relates and
how it behaves across the two languages, checked against a running store. HND-05 (the handover) draws on it. The
owner's instruction of 2 November 2026 puts it in the set that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ENT-01 | P1 | S1 | Product: General product data |
| ENT-02 | P1 | S1 | Product Variant: SKU, barcode, attributes, price, stock, weight |
| ENT-03 | P1 | S1 | Category / Brand / Collection: Classification, discovery, SEO |
| ENT-05 | P1 | S1 | Customer / Address: Account, addresses, preferences |
| ENT-06 | P1 | S1 | Cart / Cart Item: Current cart and pricing |
| ENT-07 | P1 | S1 | Wishlist: Saved items |
| ENT-09 | P1 | S1 | Payment: Transaction, reference, status, amount |
| ENT-10 | P1-L | S1 | Shipment: Carrier, tracking, rate, status |
| ENT-11 | P1 | S1 | Promotion / Coupon: Conditions, rewards, eligibility, usage |
| ENT-13 | P1 | S1 | Refund: Amount, status, payment reference |
| ENT-14 | P1-L | S1 | Review: Rating, text, status |
| ENT-15 | P1-L | S1 | Notification: Channel, template, status, provider reference |
| ENT-16 | P1 | S1 | Admin User / Role / Permission: Staff permissions |
| ENT-17 | P1-L | S1 | Audit Log: Actor, action, entity, before-and-after values, timestamp |
| ENT-19 | P1 | S1 | Import Run: File, mapping used, row counts, outcome, actor, timestamp |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ENT-08 | P1 | Order snapshot: #255's row (specs/019), listed in the record for its relations |
| ENT-04 | P1-E | Inventory transactions, per PRE-09 |
| ENT-12, ENT-18, ENT-20, ENT-21 | DEF, P2, P3, P2 | Not built |
| HND-05 | P1 | The handover documentation, which draws on the record |

## Open contract items

- **CX-03** (D-12): ENT-17 is the narrow audit record the rows ask for; ROLE-10 stays DEF.
- **CX-04** (D-12): how import runs are kept (ENT-19); the run file stands until the client acknowledges it.
- **PRE-09.** The stock of ENT-02 is the ERP's.
- **I-10.** ENT-15's provider reference waits for the email provider account (#284).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Each entity exists in the running store with the fields its row names, and the record says where it is stored, how it relates to the others and how it behaves across the two languages | ENT-01, ENT-02, ENT-03, ENT-05, ENT-06, ENT-07, ENT-09, ENT-10, ENT-11, ENT-13, ENT-14, ENT-16 | provisional |
| AC-2 | The audit log keeps actor, action, entity, before and after values and time for what the rows ask to record | ENT-17 | pending CX-03 |
| AC-3 | Each import run is kept with its file, mapping, row counts, outcome, actor and time | ENT-19 | pending CX-04 |
| AC-4 | Notifications are kept by channel and template with their status; the provider's reference follows the provider account | ENT-15 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Handover (Priority: Medium)

1. **Given** the record, **When** the client's developer reads it, **Then** each entity is found where it says, with its
   fields, relations and language behaviour.

### Edge Cases

- An entity property that is the item's, not a language's (stock, cost, badges): kept once or copied, as the record
  says.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Products, variants, terms, customers, carts, orders, coupons, refunds, reviews, roles | WooCommerce, WordPress, WPML | Native, verified |
| Wishlist, shipment fields, payment events, refund references, audit, import run | None | Built in their specs (015, 030, 028, 029, 031 and 036, 006) |
| The record | None | This spec's data-model.md |

## Requirements

### Functional Requirements

- **FR-001**: `data-model.md` MUST name, per entity, its storage, relations and language behaviour (AC-1).
- **FR-002**: `t80-data-model` MUST check each storage the record names exists in the running store with the named
  fields (AC-1 to AC-4).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- An entity-relationship diagram tool; the deferred entities.

## Success Criteria

- **SC-001**: `t80-data-model` passes on the local runtime and on staging.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- The record is technical documentation in the repository, delivered with the handover (HND-05).

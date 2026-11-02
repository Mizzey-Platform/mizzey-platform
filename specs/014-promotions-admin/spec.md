# Feature Specification: promotions managed from admin

**Feature Branch**: `014-promotions-admin`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 2 of the Stage 3
build plan under D-22: "E-ADM-7 promotions in admin: create, schedule, pause, reactivate, usage kept". #289. Admin
only, so no design dependency.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-110 | P1 | S1 | Create automatic promotions or coupon codes |
| ADM-111 | P1-L | S1 | Conditions, rewards, limits and exclusions, plus the combination setting in BR-004 |
| ADM-113 | P1 | S1 | Schedule start and end |
| ADM-115 | P1 | S1 | Pause or deactivate without deleting usage history |

Story: Functional Specification MS-SPC-2026-032 v1.4, US-14-01 (Promotion engine), the criteria that trace to the rows
above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-112, ADM-114 | P2 | Preview eligibility and the usage report are not built |
| PROMO-16, PROMO-17 | DEF | General stacking and priority rules are not built |
| PROMO-01 to PROMO-23 | P1 | The engine these screens set up is #281 (specs/011-promotion-engine) |
| BR-004 | P1 | The combination setting is built by #290; ADM-111's part of it waits for #290 |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Staff create an automatic promotion, or a coupon code, from the coupon screen, and it applies to a cart as set | ADM-110 | provisional |
| AC-2 | Conditions, rewards, limits and exclusions are set from the same screen, plus the setting that says whether promotions combine | ADM-111 | provisional |
| AC-3 | A promotion is scheduled with a start and an end: it does not apply before its start or after its end | ADM-113 | provisional |
| AC-4 | A promotion is paused and reactivated without losing its usage history: paused, it stops applying (a code is refused, an automatic one is taken off carts that hold it); reactivated, it applies again with its use count and per-customer history intact | ADM-115 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval. AC-2's
combination setting is BR-004's, built by #290: this slice verifies the conditions, rewards, limits and exclusions, and
#290's test verifies the setting.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A campaign set up, paused and brought back (Priority: High)

**Independent Test**: a promotion saved through the coupon screen's own save handler, used, paused, reactivated.

**Acceptance Scenarios**:

1. **Given** the coupon screen, **When** staff mark a promotion automatic with its conditions and dates, **Then** it
   applies to a qualifying cart with no code.
2. **Given** a coupon code a customer used on an order, **When** staff tick Paused on the coupon screen, **Then** a new
   cart is refused it ("This coupon is paused."), and its use count and record of who used it are unchanged.
3. **Given** the paused promotion, **When** that order is cancelled, **Then** its use is reversed as for a live one.
4. **Given** the paused promotion, **When** staff untick Paused, **Then** it applies again.

### Edge Cases

- Pausing and deactivating are one act, the Paused box; deleting (Trash) is a separate, deliberate act.
- Pausing stops new use. An order that already has the promotion keeps it, also when its coupons are recalculated:
  pausing does not rewrite placed orders.
- The promotion stays published while paused, so WooCommerce keeps finding it by code to record and reverse its use.
  WordPress's Draft status is not a pause: WooCommerce looks a coupon up by code to record or reverse a use, finds no
  draft, and the counts would drift; and a code that is the coupon's own number still loads as a draft.
- An automatic promotion paused while carts hold it is taken off them on their next calculation (#281's settle()).
- A start date in the future keeps the promotion waiting; an end before the start is refused on save (#281).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Create a coupon code | WooCommerce's coupon screen | Native, used as is |
| Create an automatic promotion, its start date, minimum items, first order, cap | The Mizzey settings #281 adds to the coupon screen | Built by #281 |
| End date | WooCommerce's coupon expiry date | Native, used as is |
| Pause, deactivate, reactivate | WordPress's Draft status | Gap: WooCommerce records and reverses a use by looking the coupon up by code (`wc-order-functions.php`), which finds no draft, so counts drift while paused; a numeric code loads a draft by id (`WC_Coupon::__construct`). A Mizzey Paused setting keeps the coupon published and is refused in WooCommerce's own validity check (`woocommerce_coupon_is_valid`) |
| Usage history | WooCommerce's coupon usage count and per-customer record | Native, used as is |

One setting is added (Paused); everything else is native or #281's, and t55 proves it.

## Requirements

### Functional Requirements

- **FR-001**: A promotion set up on the coupon screen MUST apply as set: automatic or by code (AC-1, AC-2).
- **FR-002**: A promotion MUST NOT apply before its start or after its end (AC-3).
- **FR-003**: A Paused box on the coupon screen MUST stop the promotion applying to any new cart or order, by any route
  WooCommerce validates a coupon on, while keeping it published; pausing and reactivating MUST NOT change its use count
  or its record of who used it, and use MUST still be recorded and reversed while it is paused (AC-4).
- **FR-004**: The coupon list MUST show "Paused" beside a paused promotion (AC-4).

### Key Entities

- **Promotion**: a WooCommerce coupon with the Mizzey settings of #281.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Preview eligibility (ADM-112) and the usage report (ADM-114), P2.

## Success Criteria

- **SC-001**: A paused promotion never discounts an order.
- **SC-002**: No use is lost across a pause and a reactivation.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 2 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- "Pause or deactivate" is one act: the Paused box. Staff see "Paused" beside the promotion in the coupon list.

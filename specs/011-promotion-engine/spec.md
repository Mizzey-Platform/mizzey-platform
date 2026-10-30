# Feature Specification: promotion engine

**Feature Branch**: `011-promotion-engine`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "start #281, the promotion engine" (the owner, 30 October 2026), wave 1 of the Stage 3
build plan under D-22. E-RULES-1 (#281). Admin only, so no design dependency; the cart's coupon feedback is #306's.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| PROMO-01 | P1 | S1 | Percentage discount |
| PROMO-02 | P1 | S1 | Fixed-amount discount |
| PROMO-03 | P1 | S1 | Free shipping reward |
| PROMO-04 | P1 | S1 | Condition: minimum cart value |
| PROMO-05 | P1 | S1 | Condition: minimum item count |
| PROMO-06 | P1 | S1 | Condition: specific products |
| PROMO-07 | P1 | S1 | Condition: specific categories |
| PROMO-08 | P1 | S1 | Condition: specific brands |
| PROMO-09 | P1 | S1 | Condition: first order only |
| PROMO-11 | P1 | S1 | Automatic promotion (no code needed) |
| PROMO-12 | P1 | S1 | Coupon code promotion |
| PROMO-13 | P1 | S1 | Usage limit: total |
| PROMO-14 | P1 | S1 | Usage limit: per customer |
| PROMO-15 | P1 | S1 | Start and end date scheduling |
| PROMO-18 | P1 | S1 | Product / category exclusions |
| PROMO-19 | P1 | S1 | Maximum discount cap |
| PROMO-23 | P1 | S1 | All rules configurable from admin without code changes |

Story: Functional Specification MS-SPC-2026-032 v1.4, US-14-01 (Promotion engine), the criteria that trace to the rows
above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| PROMO-10 | DEF | Customer-group conditions are not built |
| PROMO-16, PROMO-17 | DEF | General stacking and priority rules are not built. Part One 1.6: "One promotion applies per order unless separately configured" |
| PROMO-20 to PROMO-22 | P2 | Buy X get Y, tiers and bundles are not built |
| BR-001, BR-002, BR-004, BR-009, BR-010 | P1 | Owned by #290. The welcome discount, two-item free shipping, the combination setting, free shipping overriding the shipping rate, and the non-negative total are rules built on this engine there. US-14-01's last two criteria (free shipping overrides the calculated rate; a threshold changes without code) trace to BR-009 and are #290's |
| ADM-110, ADM-111, ADM-113, ADM-115 | P1, P1-L | Owned by #289: the admin screens to create, schedule and pause. This feature gives each setting its admin field on the coupon screen; #289 completes the management screens |
| FIX-08 | P1 | Owned by #271. This feature keeps every promotion rule in admin data, none in code |
| ENT-11 | P1 | Owned by #273. The promotion is a WooCommerce coupon plus Mizzey's settings on it |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A promotion gives a percentage discount, a fixed-amount discount, or free shipping | PROMO-01, PROMO-02, PROMO-03 | final |
| AC-2 | A promotion applies only when its conditions hold: a minimum cart value, a minimum item count, specific products, specific categories, specific brands, or the customer's first order | PROMO-04, PROMO-05, PROMO-06, PROMO-07, PROMO-08, PROMO-09 | final |
| AC-3 | A promotion applies either automatically, with no code, when its conditions hold, or when its coupon code is entered | PROMO-11, PROMO-12 | final |
| AC-4 | A promotion stops applying when its total usage limit or its per-customer limit is reached | PROMO-13, PROMO-14 | final |
| AC-5 | A promotion applies only between its start and end dates | PROMO-15 | final |
| AC-6 | Products and categories can be excluded from a promotion | PROMO-18 | final |
| AC-7 | A promotion's discount never exceeds its maximum cap, on the cart and when an order's coupons are recalculated | PROMO-19 | final |
| AC-8 | Every one of these settings is made from the admin, without a code change | PROMO-23 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Staff run a campaign without a developer (Priority: High)

Marketing creates "10 percent off Beauty, from 5 November to 15 November, up to 150 EGP off, for orders of 3 items or
more", saves it, and it applies by itself on those dates.

**Why this priority**: PROMO-23 is a key row; it is the point of the engine.

**Independent Test**: create the promotion from the coupon screen and price carts before, during and after the dates.

**Acceptance Scenarios**:

1. **Given** that automatic promotion, **When** a cart holds 3 Beauty items on 6 November, **Then** 10 percent comes
   off the Beauty items, capped at 150 EGP, with no code entered (AC-1, AC-2, AC-3, AC-5, AC-7, AC-8)
2. **Given** the same cart with 2 items, **Then** no discount (AC-2)
3. **Given** the same cart on 4 November or 16 November, **Then** no discount (AC-5)

---

### User Story 2 - A coupon code with limits (Priority: High)

**Acceptance Scenarios**:

1. **Given** code WELCOME10 limited to 100 uses and 1 per customer, **When** a customer uses it a second time,
   **Then** it is refused with a clear reason in their language (AC-3, AC-4)
2. **Given** a code restricted to a brand, **When** the cart holds no product of that brand, **Then** it is refused
   (AC-2)
3. **Given** a code for first orders only, **When** a customer with a past order enters it, **Then** it is refused;
   **When** a new customer or a guest whose email has no orders enters it, **Then** it applies (AC-2)
4. **Given** a free-shipping code, **When** it applies, **Then** the free shipping method becomes available (AC-1)

---

### User Story 3 - Exclusions (Priority: Medium)

1. **Given** a 20 percent promotion excluding the Vitamins category and one product, **When** the cart holds both and
   other items, **Then** only the other items are discounted (AC-6)

### Edge Cases

- Several automatic promotions qualify for one cart: one applies, because one promotion applies per order unless
  separately configured (Part One 1.6). The one that gives the customer the larger discount applies; on a tie, the one
  created first. **Confirmed by the owner on 31 October 2026 (Q1).** The setting that lets promotions combine is
  BR-004, #290's.
- A customer enters a code while an automatic promotion applies: the customer's code takes the place of automatic
  promotions, and removing it brings them back. Whether two codes the customer enters can combine follows each
  promotion's own "Individual use only" setting until #290 adds the combination setting and makes one promotion per
  order the default (BR-004); automatic promotions are made individual-use when they are switched on.
- A guest whose billing email has already used a promotion up, or is not on its email list, is not given it as an
  automatic promotion; the next qualifying one applies, so checkout is never blocked by one it would remove.
- A free-shipping automatic promotion is worth the cheapest paid rate it replaces, and nothing where no free shipping
  method accepts a coupon; the value is the same whichever promotion is applied, so the choice is stable.
- An automatic promotion stops qualifying when the cart changes: it is removed; a code the customer entered is kept
  until it no longer qualifies, as WooCommerce does.
- A start date in the future with no end date: the promotion waits; an end date before the start date is refused on
  save.
- First order only, for a guest: decided by the billing email, against orders that are not cancelled, failed or
  pending.
- The cap applies to the promotion's total discount, not per item, and is exact to the cent. It is offered for
  percentage and fixed cart promotions; a fixed discount per product cannot hold a cap exactly, so the coupon screen
  does not save one.
- Arabic and English: the refusal messages are translated; promotions themselves are not language-specific.

## Native coverage

| Capability | Evidence | Status |
|---|---|---|
| Percentage and fixed discounts, free-shipping coupons, coupon codes | WooCommerce coupons; P-003 schema | PREVIOUSLY TESTED |
| Minimum cart value, product and category conditions and exclusions, total and per-customer limits, expiry date | P-003 (`minimum_amount`, `product_ids`, `product_categories`, `excluded_*`, `usage_limit`, `usage_limit_per_user`, `date_expires`) | PREVIOUSLY TESTED |
| Brand condition and exclusion | WooCommerce 11.1.0 core Brands, `includes/class-wc-brands-coupons.php` (`is_coupon_valid`, `is_valid_for_product`): settings stored on the coupon, so P-003's field list did not show them | UNVERIFIED |
| Minimum item count | Not in the schema (P-003) | GAP |
| First order only | Not in the schema; `email_restrictions` limits who, not their history | GAP |
| Automatic application without a code | Not in WooCommerce | GAP |
| Start date | Only `date_expires` (P-003) | GAP |
| Maximum discount cap | Not in the schema (P-003) | GAP |

## Requirements

### Functional Requirements

- **FR-001**: Promotions MUST be WooCommerce coupons, so every native capability above is used as it is (AC-1, AC-4,
  AC-6)
- **FR-002**: A promotion MUST be able to require a minimum number of items in the cart, counting units of the
  products it applies to (AC-2)
- **FR-003**: A promotion MUST be able to require that the customer has no earlier order, judged by account and by
  billing email (AC-2)
- **FR-004**: A promotion MUST be able to apply automatically, without a code, whenever its conditions hold, in the
  block cart and checkout and in the classic cart (AC-3)
- **FR-005**: A promotion MUST be able to have a start date; before it, and after its end date, it does not apply
  (AC-5)
- **FR-006**: A percentage or fixed cart promotion MUST be able to have a maximum discount; its total discount on a
  cart, and on an order whose coupons are recalculated, MUST NOT exceed it (AC-7)
- **FR-007**: Each of these settings MUST be a field on the coupon's admin screen, saved with it (AC-8)
- **FR-008**: A refused code MUST say why, in the customer's language (AC-2, AC-4, AC-5)
- **FR-009**: The brand condition MUST be verified native and used as it is (AC-2)

### Key Entities

- **Promotion**: a WooCommerce coupon, with Mizzey's settings on it: automatic, minimum item count, first order only,
  start date, maximum discount.

## Optional safeguards (not owed)

None.

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Customer-group conditions | PROMO-10, DEF |
| General stacking and priority rules | PROMO-16, PROMO-17, DEF |
| Buy X get Y, tiers, bundles | PROMO-20 to PROMO-22, P2 |

## Success Criteria

- **SC-001**: Every condition, reward, limit and date in the register is set from the coupon screen and proven on a
  real cart by an automated test, in both languages for the messages.
- **SC-002**: No promotion ever discounts more than its cap or outside its dates.
- **SC-003**: At most one automatic promotion applies to a cart unless #290's combination setting allows more.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 1 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- Item count counts units, not distinct products, for PROMO-05 (the register says "minimum item count"); OD-04's
  question about two items is BR-002's and is answered in #290.
- Times are the store's time zone (Africa/Cairo).

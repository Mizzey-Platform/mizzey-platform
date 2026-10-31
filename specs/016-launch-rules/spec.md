# Feature Specification: the launch business rules

**Feature Branch**: `016-launch-rules`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 2 of the Stage 3
build plan under D-22: "E-RULES-2 launch business rules: BR-001, BR-002, BR-004, BR-006, BR-008 to BR-010; BR-003 and
BR-007 through the seam". #290. Built on `014-promotions-admin`, whose Paused setting the welcome discount honours.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| BR-001 | P1 | S1 | Welcome discount on account creation: value, cap, expiry and eligibility all admin-configurable |
| BR-002 | P1 | S1 | Free shipping on two or more items: condition admin-configurable |
| BR-003 | P1 | S1 | Stock validation. An order is never confirmed for a quantity above the stock actually available. The stock actually available is the figure your ERP holds (ERP-04) |
| BR-004 | P1 | S1 | Promotion stacking is explicit. Whether the welcome discount, a coupon and free shipping can combine is a setting you control, not a hidden behaviour |
| BR-006 | P1 | S1 | Refund integrity. A refund never exceeds the amount actually paid for that item or order after discounts |
| BR-007 | P1 | S1 | Stock is returned on cancellation. A cancelled or failed order releases its stock back to your ERP (ERP-04) under the agreed reservation policy |
| BR-008 | P1 | S1 | Guest checkout, with a route back. A guest can complete an order, and afterwards be invited to create an account with that order linked to it securely |
| BR-009 | P1 | S1 | Free-shipping promotion overrides the calculated shipping rate |
| BR-010 | P1 | S1 | Discounts never produce a negative order total |

Story: Functional Specification MS-SPC-2026-032 v1.4, the business rules section, the criteria that trace to the rows
above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| BR-005 | P1 | The price snapshot is #255's |
| ERP-04 | P1-E | The ERP stock figure and the reservation policy wait for PRE-09 (M-3); the stock seam is specs/012 |
| PROMO-16, PROMO-17 | DEF | General stacking and priority rules are not built; BR-004's one setting is |
| AUTH-07, AUTH-11 | P1 | Guest checkout and guest conversion are also #305's; BR-008's route back is WooCommerce's order-received offer, guarded by AUTH-15 (specs/009) |

## Open contract items

- OD-03 (the welcome discount's values) and OD-04 (what two items means) are working defaults, not client-confirmed.
- PRE-09 for BR-003 and BR-007's ERP half.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A new customer account gets a welcome discount whose value, cap, expiry and eligibility staff set from admin | BR-001 | pending OD-03 |
| AC-2 | Free shipping applies on two or more items, the condition set from admin | BR-002 | pending OD-04 |
| AC-3 | No order is confirmed for a quantity above the stock the ERP holds | BR-003 | pending PRE-09 |
| AC-4 | Whether the welcome discount, a coupon and free shipping combine is a setting staff control | BR-004 | provisional |
| AC-5 | A refund never exceeds what was paid for the item or the order after discounts | BR-006 | provisional |
| AC-6 | A cancelled or failed order releases its stock back to the ERP under the agreed reservation policy | BR-007 | pending PRE-09 |
| AC-7 | A guest completes an order, and is then invited to create an account with that order linked to it securely | BR-008 | provisional |
| AC-8 | A free-shipping promotion overrides the calculated shipping rate | BR-009 | provisional |
| AC-9 | Discounts never produce a negative order total | BR-010 | provisional |

AC-1 and AC-2 rest on OD-03 and OD-04, so they are `pending OD-nn` until the client confirms the values (M-10); the
mechanism is verified. AC-3 and AC-6 wait for PRE-09 (M-3): the stock gateway (specs/012) carries every stock question
and change, and #255 (checkout) routes order placement, cancellation and failure through it.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A welcome for a new customer (Priority: High)

**Independent Test**: a template set up on the coupon screen, a customer account created, a cart.

**Acceptance Scenarios**:

1. **Given** a welcome discount template, **When** a customer account is created, **Then** that customer has their own
   welcome discount: their email, one use, first order only, expiring after the set days.
2. **Given** that customer's qualifying cart, **When** it is calculated, **Then** the discount applies with no code, up
   to its cap; below the minimum it does not; another customer never gets it; used once, it stops.
3. **Given** the template, **When** anyone enters its code, **Then** it is refused.

### User Story 2 - Promotions combine only when staff say so (Priority: High)

**Acceptance Scenarios**:

1. **Given** the default setting, **When** a customer enters two codes and an automatic promotion qualifies, **Then**
   one promotion applies.
2. **Given** the setting on, **When** a code, an automatic discount and automatic free shipping qualify, **Then** all
   three apply, unless one is individual-use, which then stands alone.

### Edge Cases

- No template, or a paused one: no welcome discount is issued.
- A staff account gets no welcome discount; only accounts with the Customer role do.
- A customer's own welcome discount is a candidate for that customer only, not one of the store's automatic promotions
  every cart is measured against.
- With combining on, at most one automatic discount and one automatic free shipping apply, as #281 chooses each.
- The free-shipping override uses WooCommerce's "hide shipping rates when free shipping is available"; with it off, the
  customer sees the free method beside the paid one.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Welcome discount per new customer | None: WooCommerce has no account-creation reward | Gap: a template coupon and a per-customer copy |
| Free shipping on two items | Free shipping method: minimum amount or a coupon, no item count (P-011) | #281's minimum items on an automatic free-shipping promotion; configuration, no new code |
| Combination | Each coupon's "Individual use only" box | Gap: one store setting decides, default one promotion per order |
| Refund ceiling | `wc_create_refund()` refuses more than remains (P-014) | Native, verified |
| Guest route back | WooCommerce's order-received account offer links the order (`CreateAccount::process_form_post()`), guarded by AUTH-15 | Native, verified |
| Free shipping overrides the rate | `woocommerce_shipping_hide_rates_when_free` | Native, configuration, verified |
| Non-negative total | WooCommerce caps a cart discount at the cart | Native, verified |

## Requirements

### Functional Requirements

- **FR-001**: Staff MUST be able to mark a coupon as the welcome discount template and set its validity in days; its
  own fields carry value, cap, minimum and eligibility (AC-1).
- **FR-002**: Creating a customer account MUST issue that customer's own welcome discount from the published, unpaused
  template, once; it MUST apply automatically for that customer only (AC-1).
- **FR-003**: The template MUST NOT be accepted as a code (AC-1).
- **FR-004**: A store setting MUST decide whether promotions combine, off by default; off, every promotion is
  individual-use; on, each coupon's own box decides, and the best automatic discount and free shipping may join entered
  codes (AC-4).
- **FR-005**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Welcome discount template**: a coupon marked as such, with a validity in days.
- **A customer's welcome discount**: a coupon copied from it, owned by one customer.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- General stacking and priority rules (PROMO-16, PROMO-17, DEF).

## Success Criteria

- **SC-001**: No customer receives the welcome discount twice, and no other customer receives theirs.
- **SC-002**: With the default setting, no order carries more than one promotion.
- **SC-003**: No refund exceeds what was paid; no total is negative.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 2 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- The welcome discount reaches the customer by applying automatically on their first qualifying order; the account
  email that mentions it is #285's.

# Feature Specification: the commerce events

**Feature Branch**: `044-commerce-events`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Issue #312 ("The standard e-commerce events fire across the funnel, in both languages"). The events, their
triggers and parameters are the tracking plan's (specs/042-event-tracking-plan); the data layer and Tag Manager are
specs/043. The owner's instruction of 2 November 2026 puts the tracking chain in the work built with stand-ins:
the Analytics property and the containers are the client's (I-09), and the events are pushed whether or not a
container is named.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| EVT-01 | P1 | S1 | Home page viewed (`view_home`) |
| EVT-02 | P1 | S1 | Category, collection or search results viewed (`view_item_list`) |
| EVT-03 | P1 | S1 | Search performed, with term and result count (`search`) |
| EVT-04 | P1 | S1 | Product page viewed (`view_item`) |
| EVT-05 | P1 | S1 | Product card clicked (`select_item`) |
| EVT-06 | P1 | S1 | Added to wishlist (`add_to_wishlist`) |
| EVT-07 | P1 | S1 | Added to cart (`add_to_cart`) |
| EVT-08 | P1 | S1 | Removed from cart (`remove_from_cart`) |
| EVT-09 | P1 | S1 | Cart viewed (`view_cart`) |
| EVT-10 | P1-L | S1 | Coupon applied (`apply_coupon`) |
| EVT-11 | P1 | S1 | Checkout started (`begin_checkout`) |
| EVT-12 | P1 | S1 | Shipping information added (`add_shipping_info`) |
| EVT-13 | P1 | S1 | Payment method selected (`add_payment_info`) |
| EVT-14 | P1 | S1 | Purchase (`purchase`): items, tax, shipping and discount values |
| EVT-15 | P1-L | S1 | Refund issued (`refund`) |
| EVT-16 | P1 | S1 | Registration and login (`sign_up`, `login`) |
| EVT-18 | P1-L | S1 | Search with no results (`zero_result_search`) |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| EVT-20 | DLV | The tracking plan these events follow (specs/042) |
| MKT-01, MKT-23 | P1 | The data layer read by Tag Manager (specs/043) |
| DOD-05 | P1 | Analytics events verified where required |
| EVT-17, EVT-19 | P2, DEF | Not built |

## Open contract items

- **I-09.** The Analytics property, its measurement id and a Measurement Protocol secret (for refunds) are the
  client's. Until they exist every browser event is pushed to the data layer and the refund is not sent.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The page events fire as the plan defines, in both languages: home, lists, search and zero-result search, product | EVT-01, EVT-02, EVT-03, EVT-04, EVT-18 | provisional |
| AC-2 | The cart, coupon, wishlist, checkout, sign-up and sign-in events fire once each, with the plan's parameters | EVT-05, EVT-06, EVT-07, EVT-08, EVT-09, EVT-10, EVT-11, EVT-12, EVT-13, EVT-16 | provisional |
| AC-3 | Purchase fires once per order, on confirmation, with its value, tax, shipping, coupon and items | EVT-14 | provisional |
| AC-4 | A refund is sent to Analytics when it is made | EVT-15 | pending I-09 |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The funnel joins up (Priority: High)

1. **Given** a visitor on the Arabic storefront, **When** they view a product, add it to the cart, check out and pay,
   **Then** view_item, add_to_cart, begin_checkout and purchase fire once each, with the same `item_id` (the SKU) as
   on the English storefront.

### Edge Cases

- A refreshed confirmation page: no second purchase.
- A payment not yet confirmed: no purchase.
- A refused coupon: no apply_coupon.
- A staff member signing in: no login event.
- No personal data in any event.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Event names and shape | Google Analytics 4's recommended e-commerce events | Used unchanged |
| Card clicks, shipping and payment choices | WooCommerce Blocks' `experimental__woocommerce_blocks-*` actions | Native hooks, listened to |
| Cart changes | WooCommerce's cart actions; the Store API's extension data | Native hooks, used |
| The events themselves | None (no analytics plugin, D-24) | Gap: `Events`, `assets/events.js` |

## Requirements

### Functional Requirements

- **FR-001**: Page events MUST be worked out when the page is built and pushed as it loads (AC-1).
- **FR-002**: Cart changes, an accepted coupon, a wishlist save, a sign-up and a customer's sign-in MUST be queued in
  the visitor's session and delivered once, in the Store API's cart answer or on the next page (AC-2).
- **FR-003**: Card clicks and shipping and payment choices MUST be pushed from WooCommerce Blocks' actions (AC-2).
- **FR-004**: Purchase MUST fire once per order, only on its confirmation page with the order's key, and only once the
  order is confirmed (AC-3).
- **FR-005**: A refund MUST be sent to Analytics' Measurement Protocol with the order number and value when a
  measurement id and API secret are set, and nothing MUST be sent otherwise (AC-4).
- **FR-006**: Every item MUST be named by its SKU; no event MUST carry personal data.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Promotion events (EVT-17, P2), return requests (EVT-19, DEF), server-side tagging (MKT-11, P3).

## Success Criteria

- **SC-001**: On staging, with a test container in preview mode, each event is seen in the data layer and in
  Analytics' debug view, in English and in Arabic, as section 9 of the plan lists.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22).

## Assumptions

- WooCommerce Blocks' actions are named `experimental__`: if a WooCommerce update renames them, card clicks, shipping
  and payment choices stop until the names are updated; the staging check in SC-001 catches it.
- A gateway that confirms a payment after the customer has left the page leaves no browser to push the purchase from;
  the plan accepts that loss (specs/042, section 5).

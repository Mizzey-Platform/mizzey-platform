# Feature Specification: cart

**Feature Branch**: `018-cart`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 2 of the Stage 3
build plan under D-22: "E-SF-6 cart, after #305: cart merge on register, sign-in and Google (P-001), guest retention as
a setting (CART-16), stock re-validation through the seam, free-shipping prompt data, related products from the Upsells
field (D-19)". #306. The function layer; the designed cart and mini-cart screens wait for PRE-03b. Built on
`017-accounts` (sign-in and registration) and `015-wishlist` (the wishlist and the stock seam).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| CART-01 | P1 | S1 | Line items with image, name, variant, price, quantity |
| CART-02 | P1 | S1 | Update quantity |
| CART-03 | P1 | S1 | Remove item |
| CART-04 | P1 | S1 | Move to wishlist |
| CART-05 | P1 | S1 | Subtotal, discount, shipping, total breakdown |
| CART-06 | P1 | S1 | Coupon entry with clear success and failure messages |
| CART-07 | P1 | S1 | Free-shipping progress indicator, "add one more item to qualify" |
| CART-08 | P1-L | S1 | Cross-sell suggestions in cart |
| CART-09 | P1 | S1 | Cart persists for logged-in customers across devices and sessions |
| CART-10 | P1 | S1 | Stock re-validated against your ERP when the cart is viewed (ERP-03) |
| CART-11 | P1 | S1 | Empty cart state with suggestions |
| CART-12 | P1 | S1 | Mini-cart in header |
| CART-13 | P1 | S1 | Guest cart survives registration: a guest who fills a cart and then creates an account keeps every item |
| CART-14 | P1 | S1 | Guest cart survives sign-in: a guest who fills a cart and then signs in has it merged with any saved cart, with no item lost and no duplicate line |
| CART-15 | P1 | S1 | Guest cart survives Google sign-in: identical behaviour through the Google flow |
| CART-16 | P1 | S1 | Guest cart survives browser close and return within a defined window |
| CART-17 | P1-L | S1 | Guest wishlist transfers to the account on registration or sign-in |
| CART-18 | P1 | S1 | Merge conflicts resolved predictably: quantities combined, stock limits respected, prices re-validated |

Stories: Functional Specification MS-SPC-2026-032 v1.4, US-05-01 to US-05-13, the criteria that trace to the rows
above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ERP-03 | P1-E | Asking the ERP when the cart is viewed is E-ERP-4's mechanism, after PRE-09; this slice asks through the seam (#245) |
| BR-002 | P1 | Free shipping on two units is #290's promotion; the progress reads it |
| AUTH-10, AUTH-13 | P1 | Google sign-in is #305's second PR; the merge does not depend on the sign-in route |

## Open contract items

- CART-08 and CART-17 are P1-L with no reduction written in Part One 1.2. CART-08 follows D-19 item 3 (the suggestions
  are the staff's related-products assignment); CART-17 is #307's merge, verified there.
- CART-16's window is a proposed working default of 30 days, for the owner to confirm (the Stage 3 build plan).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Each line shows image, name, variant, price and quantity; the quantity can be changed and the line removed | CART-01, CART-02, CART-03 | provisional |
| AC-2 | A cart line moves to the wishlist in one action | CART-04 | provisional |
| AC-3 | The cart shows subtotal, discount, shipping and total; a coupon entry gives a clear success or failure message | CART-05, CART-06 | provisional |
| AC-4 | The cart states what is still needed for free shipping, read from the promotion and shipping configuration, and nothing once it is met | CART-07 | provisional |
| AC-5 | Cart suggestions are the related products the staff assign to the cart's items; nothing when nothing is assigned | CART-08 | provisional |
| AC-6 | A signed-in customer's cart is the same on every device and session | CART-09 | provisional |
| AC-7 | Stock is re-validated through the stock source when the cart is viewed and before checkout; a line whose stock cannot be confirmed blocks checkout | CART-10 | pending PRE-09 |
| AC-8 | The empty cart offers suggestions, and the header offers a mini-cart | CART-11, CART-12 | provisional |
| AC-9 | A guest who fills a cart and then registers keeps every item | CART-13 | provisional |
| AC-10 | A guest who signs in has the cart merged with the saved cart, with no item lost and no duplicate line, whatever route signed them in | CART-14, CART-15 | provisional |
| AC-11 | A guest's cart survives closing the browser within a window that is a setting | CART-16 | provisional |
| AC-12 | The guest wishlist transfers to the account on registration or sign-in | CART-17 | provisional |
| AC-13 | On merge, quantities of the same item are combined and capped at what can be bought; prices are the products' current prices | CART-18 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval.
AC-7 is pending PRE-09: it is verified against a mock and a fixture, not the ERP (M-3). AC-10 is verified for the
account page, registration and any route that sets WordPress's sign-in cookie; the Google flow itself is verified with
#305's Google sign-in. AC-8's screens are PRE-03b's.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The cart I left is the cart I find (Priority: High)

1. **Given** a guest with a cart, **When** they register or sign in, **Then** every item is kept, merged with the
   account's saved cart: the same item's quantities added and capped at what is in stock and can be bought.
2. **Given** a guest who closes the browser, **When** they return within the window (30 days by default), **Then** the
   cart is there.
3. **Given** a signed-in customer, **When** they sign in on another device, **Then** the same cart is there.

### User Story 2 - The cart tells the truth about stock (Priority: High)

1. **Given** a cart, **When** it is viewed or checked out, **Then** each item's stock is asked of the stock source; an
   item it cannot confirm, or holds too few of, is named and checkout is blocked until the cart is changed.

### User Story 3 - The cart helps the customer finish (Priority: Medium)

1. **Given** one unit in the cart and free shipping from two, **When** the cart is viewed, **Then** it says one more
   item unlocks free shipping; with two, shipping is free and the prompt is gone.
2. **Given** items with related products assigned, **When** the cart is viewed, **Then** those products are suggested.
3. **Given** a line the customer is not ready to buy, **When** they move it to the wishlist, **Then** it is saved and
   leaves the cart.

### Edge Cases

- An item the stock source says is sold out leaves the merged cart, and the customer is told; an unanswered stock
  question caps nothing at merge, and the cart check then refuses it.
- Two lines of one stock item (or its two language records) are checked as one quantity.
- An item sold one at a time is merged as one.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Lines, quantity, remove, totals, coupon messages | WooCommerce's cart and the Store API cart | Native, verified |
| Saved cart across devices | WooCommerce's persistent cart (`_woocommerce_persistent_cart_`) | Native; written at merge |
| Guest cart through sign-in | The session moves to the account (`migrate_guest_session_to_user_session()`), and the saved cart is added, replacing a line rather than combining it, and only after `wp_login` | Gap: `CartMerge` combines, caps, and marks every sign-in |
| Guest cart window | The session lasts two days | Gap: `GuestCartRetention`, a setting |
| Stock check | WooCommerce checks its own stock field | Gap: `CartStock` asks the stock source (D-19 item 5) |
| Free shipping progress | None | Gap: `FreeShippingProgress`, on the Store API cart |
| Suggestions | The Cross-sells field | Gap: `CartSuggestions` reads the Upsells field (D-19) |
| Move to wishlist | None | Gap: `Wishlist::fromCart()` |
| Empty cart, mini-cart | WooCommerce's cart blocks | Native; designed after PRE-03b |

## Requirements

### Functional Requirements

- **FR-001**: Every stock question the cart asks MUST go through the stock gateway and fail closed; nothing in the cart
  may write stock (AC-7).
- **FR-002**: Outside production, a stand-in stock fixture MAY be switched on (`mizzey_stock_fixture`); it MUST answer
  from the record's stock fields less its own ledger of changes (once per operation key), never write WooCommerce's
  stock, never bind on production, and yield to a bound adapter.
- **FR-003**: Every sign-in MUST mark the saved cart to merge; the merge MUST combine quantities, keep every line of
  both carts, cap each item at what can be bought, tell the customer when a quantity was lowered, and save the result
  to the account (AC-6, AC-9, AC-10, AC-13).
- **FR-004**: The guest cart window MUST be a setting, 30 days by default, leaving a signed-in customer's session as
  WooCommerce sets it (AC-11).
- **FR-005**: The Store API cart MUST carry the free shipping progress (AC-4); suggestions MUST read the Upsells field
  (AC-5); a cart line MUST move to the wishlist (AC-2).
- **FR-006**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Free shipping progress**: free or not, items needed, amount needed.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- None.

## Success Criteria

- **SC-001**: No item is lost and no line is doubled when a guest signs in or registers.
- **SC-002**: No order can be placed for a line whose stock the stock source did not confirm.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 2 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- The guest cart window of 30 days is a proposed working default (an owner decision, not a client confirmation).
- The stock fixture stands in for PRE-09's adapter on development and staging only (D-10, M-3); production keeps the
  unconfigured source until the real adapter is bound.
- Known limit: WooCommerce's own stock checks (the classic cart, the Store API's validation and quantity limits) read
  WooCommerce's stock field, the ERP's projection under D-19. They are stricter than the adapter, never looser: a stale
  projection can refuse a sale the ERP would allow, never confirm one it would refuse. Keeping the projection in step is
  the ERP integration's (PRE-09).

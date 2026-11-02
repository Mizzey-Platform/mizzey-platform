# Feature Specification: wishlist and recently viewed

**Feature Branch**: `015-wishlist`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 2 of the Stage 3
build plan under D-22: "E-SF-10 wishlist: B17 minimal wishlist keyed to the canonical product, guest lifetime,
idempotent merge on sign-in (CART-17), recently viewed". #307. The function layer; the wishlist screens wait for
PRE-03b.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| WISH-01 | P1 | S1 | Add / remove from wishlist |
| WISH-02 | P1 | S1 | Wishlist persists for logged-in customers |
| WISH-03 | P1 | S1 | Temporary wishlist for guests |
| WISH-04 | P1 | S1 | Move from wishlist to cart |
| WISH-07 | P1 | S1 | Recently viewed products |

Stories: Functional Specification MS-SPC-2026-032 v1.4, US-11-04 (Wishlist), US-11-05 (Move wishlist to cart), US-11-06
(Guest wishlist transfers on sign in) and US-11-07 (Recently viewed), the criteria that trace to the rows above.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| CART-17 | P1-L | The guest wishlist's transfer to the account is #306's row (E-SF-6); this slice builds the transfer B17 asks for, and #306 accepts it |
| PDP-19, IA-11, ENT-07 | P1 | The product page's recently viewed rail, the wishlist page and the wishlist entity; the screens are #254's and this slice's presentation layer, after PRE-03b |
| WISH-05, WISH-06, WISH-08 | P2 | Sharing, price-drop alerts and comparison are not built |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Items can be added to and removed from the wishlist, one entry per product or variation whatever language it was saved in | WISH-01 | provisional |
| AC-2 | The wishlist persists for a signed-in customer, on any device | WISH-02 | provisional |
| AC-3 | A guest gets a temporary wishlist for the session, which joins the account's on registration or sign-in, an item already saved not duplicated | WISH-03 | provisional |
| AC-4 | An item moves from wishlist to cart in one action; an out-of-stock item cannot be moved, and the reason is stated | WISH-04 | provisional |
| AC-5 | Recently viewed products are shown to the visitor, and the list persists across sessions for a signed-in customer | WISH-07 | provisional |

Provisional: verified by automated tests through the function layer and its REST routes; the screens (the product
page and listing card buttons, the wishlist page, the recently viewed rail) are the presentation layer after PRE-03b,
and acceptance follows manual testing on staging and the client's approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Save now, buy later (Priority: High)

**Independent Test**: save as a guest, sign in, find the items; move one to the cart.

**Acceptance Scenarios**:

1. **Given** a guest, **When** they save a product in Arabic and the same product in English, **Then** the wishlist
   holds one entry, shown in the language of the page.
2. **Given** a guest with saved items, **When** they sign in or register, **Then** the items are on the account, none
   twice, and the guest's list is cleared.
3. **Given** a customer, **When** they sign in on another device, **Then** their wishlist is there.
4. **Given** a saved item in stock, **When** they move it to the cart, **Then** it is in the cart and off the wishlist;
   **Given** one out of stock, **Then** it stays, and they are told why.

### User Story 2 - Get back to what I looked at (Priority: Medium)

**Acceptance Scenarios**:

1. **Given** a visitor who viewed products, **When** recently viewed is shown, **Then** the products are listed, most
   recent first, each once, in the page's language.
2. **Given** a customer, **When** they come back in a new session, **Then** their recently viewed list is there.

### Edge Cases

- A guest's list lives for WooCommerce's guest session (`wc_session_expiration`, two days by default): "a temporary
  wishlist for the session" (US-11-04), B17's defined guest lifetime.
- The guest cookie is signed with the site's own salt: an altered or expired cookie is an empty list.
- The list holds at most 100 items (`mizzey_wishlist_capacity`), the oldest dropped.
- A product deleted after it was saved is left out of the list.
- Moving to the cart uses WooCommerce's own stock check now; the cart's stock questions go through the stock seam with
  #306 (D-19 item 5).
- Recently viewed keeps 15 products, WooCommerce's own number, and leaves out variations (a product page is a product).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| A wishlist | None: WooCommerce 11.1.0 has no wishlist functions, classes or tables (P-015) | Gap: built minimal (B17) |
| Guest state | WooCommerce's session is tied to the cart | Gap: a signed cookie for the wishlist, with the session's lifetime |
| Move to cart | `WC_Cart::add_to_cart()` and its stock check | Native, used as is |
| Recently viewed | `wc_track_product_view()`: only while the Recently Viewed widget is active, by language record, for the browser session | Gap: tracked by canonical product, kept on the account for customers |
| Canonical product | WPML's translation group | Native, read as `CanonicalProduct` (shared with the stock seam, specs/012) |

## Requirements

### Functional Requirements

- **FR-001**: Saving, removing and moving MUST key the canonical product or variation, the translation group's source
  record (AC-1).
- **FR-002**: A customer's lists MUST be kept on the account; a guest's in a signed cookie for the guest session (AC-2,
  AC-3, AC-5).
- **FR-003**: On sign-in and registration, a guest's lists MUST be added to the account's without duplicates, and the
  guest's cleared; doing it again MUST change nothing (AC-3).
- **FR-004**: Moving to the cart MUST add the item, then take it off the wishlist; an item out of stock, or refused by
  the cart, MUST stay saved with the reason (AC-4).
- **FR-005**: The lists MUST be offered over REST (`mizzey/v1/wishlist`, `.../items`, `.../items/{id}`,
  `.../items/{id}/cart`, `mizzey/v1/recently-viewed`), acting on the visitor's own lists, in the request's language.
- **FR-006**: Every customer-facing message MUST be translated (FIX-04).

### Key Entities

- **Wishlist**: canonical product ids, most recently saved first.
- **Recently viewed**: canonical product ids, most recently viewed first, at most 15.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Sharing, multiple lists, price-drop alerts, comparison (P2).

## Success Criteria

- **SC-001**: No product appears twice in a wishlist because it was saved in two languages or merged twice.
- **SC-002**: No out-of-stock item is moved to the cart from the wishlist.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 2 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- A guest's recently viewed list lasts for the browser session, as WooCommerce's own does; only a customer's persists
  across sessions (US-11-07).

# Feature Specification: adding to the cart takes account of the stock source

**Feature Branch**: `062-add-to-cart-stock`

**Created**: 2026-11-05

**Status**: Draft

**Work type**: requirement

**Input**: #314. ERP-03 has two halves: viewing the cart (CART-10, built and verified in specs/018-cart) and adding to
it. Until now an item went into the cart on WooCommerce's own stock field and was refused only when the cart was
viewed ("We could not confirm that ... is in stock"), so a shopper could add and then be turned back. Mustafa found it
on staging on 5 November 2026. This slice makes adding ask the same stock source the cart asks.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ERP-03 | P1-E | S1 | Adding to the cart and viewing the cart take account of your ERP stock |

ERP-03 is owned by #314.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| CART-10 | P1 | The viewing half of ERP-03: specs/018-cart, verified on staging 31 October 2026 |
| ERP-05 | P1 | No sale confirmed against stock the ERP cannot validate: the add fails closed the same way |
| PDP-22, AC-15 | P1 | Out-of-stock messaging, consistent across product page, cart and checkout |
| PLP-04 | P1 | Quick add from the card is one of the routes that adds |
| ERP-09 | OUT | Not built |

## Open contract items

- None. ERP-03 is P1-E: its method waits for the ERP Integration Specification (PRE-09, M-3). This slice asks
  through the stock gateway (#245), so the method behind it can change without changing what is checked here.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Adding an item asks the stock source for what the cart would then hold, and lets it in only when the source holds that many; otherwise the cart is unchanged and the shopper is told how many there are, or that the item is out of stock | ERP-03 | provisional |
| AC-2 | The quantity asked about counts every line and both language records of the same stock item; each variation is its own item | ERP-03 | provisional |
| AC-3 | When the source does not answer (none bound, down, or the item unknown to it), nothing is added and the shopper is told stock could not be confirmed | ERP-03 | provisional |
| AC-4 | What the shopper's own unpaid order already holds counts as theirs, as at the cart check | ERP-03 | provisional |
| AC-5 | Every route that adds follows the same rule: the product page form, an add-to-cart link (buy now), and the Store API behind the block cart and quick add | ERP-03 | provisional |
| AC-6 | Every refusal reads in the shopper's language | ERP-03 | provisional |

Provisional: verified by automated tests against a stand-in source and a fixture, not the ERP (M-3). Acceptance
follows the ERP Integration Specification, manual testing on staging and the client's approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The cart never takes what cannot be sold (Priority: High)

1. **Given** 3 left, **When** a shopper adds 3, **Then** they go in; **When** they add a fourth, **Then** nothing
   changes and they are told only 3 are available, and their cart already holds 3 (AC-1).
2. **Given** 3 English lamps in the cart and 3 left, **When** the Arabic page's lamp is added, **Then** it is
   refused: it is the same lamp (AC-2).
3. **Given** the stock source is down, **When** anything is added, **Then** nothing goes in and the shopper is asked
   to try again in a few minutes (AC-3).

### User Story 2 - A retry is not refused for the shopper's own reservation (Priority: Medium)

1. **Given** an unpaid order holding 2 and 1 left at the source, **When** the shopper adds 1 more, **Then** it goes in
   (AC-4).

### Edge Cases

- A quantity raised inside the cart is not an add: the cart check (CART-10) refuses it on the next view and blocks
  checkout, as before.
- An item without a stock identity is refused, as the cart check refuses it.
- Code that calls `WC_Cart::add_to_cart()` directly (the sign-in merge) is not an add by a shopper: the merge caps
  itself (specs/018) and the cart check covers the rest.
- Measured with WooCommerce alone on 5 November 2026: 3 English lamps in the cart against a stock of 3, and the Arabic
  lamp was still let in (4 in the cart). AC-2 closes it.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| A check before adding | `woocommerce_add_to_cart_validation`, run by the form handler and by the Store API, which turns its notice into the route's error (`CartController::validate_add_to_cart`) | Native hook, used |
| Stock from the stock source | WooCommerce checks its own stock field, per language record | Gap: `AddToCartStock` asks the gateway (D-19 item 5) |
| Raising a quantity in the cart | Store API update-item checks WooCommerce's limits only | Covered by the cart check (CART-10, specs/018) |

## Requirements

### Functional Requirements

- **FR-001**: Every add MUST ask the stock gateway, counting the item's lines and language records and the shopper's
  own held quantity, and MUST fail closed (AC-1 to AC-4).
- **FR-002**: The check MUST run on `woocommerce_add_to_cart_validation`, so every route that adds is covered, and
  MUST not run, ask or report again when an earlier check already refused (AC-5).
- **FR-003**: Every refusal MUST be translated (AC-6, FIX-04).
- **FR-004**: Nothing in the check may write stock.

### Key Entities

- None new. Stock item and stock answer are specs/012's.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The ERP's own method of answering (PRE-09), behind the gateway.

## Success Criteria

- **SC-001**: A shopper is never let add something the cart will then refuse for stock.

## Delivery stage

Contractual stage: S1, with #314. Engineering timing: 5 November 2026, on the store side of the seam, before PRE-09
(D-10).

## Assumptions

- The gateway's usual attempts apply to an add (three, with a short pause), as at the cart check.

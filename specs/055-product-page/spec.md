# Feature Specification: what the product page states beside the product

**Feature Branch**: `055-product-page`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #254 (the product details page, in both languages), its function layer under D-27: the data and admin
settings the designed page will read. The designed page is PRE-03b's (D-14).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| PDP-04 | P1 | S1 | Price, sale price, saving displayed |
| PDP-09 | P1 | S1 | Buy Now |
| PDP-14 | P1-L | S1 | Delivery estimate and shipping information |
| PDP-15 | P1 | S1 | Returns policy summary |
| PDP-16 | P1 | S1 | Authenticity guarantee statement |
| PDP-21 | P1 | S1 | Share buttons |

Stories US-04-02, US-04-04, US-04-06 and US-04-10 (Functional Specification MS-SPC-2026-032 v1.4). Owned by #254.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| PDP-01, PDP-02, PDP-03, PDP-05, PDP-07, PDP-08, PDP-11, PDP-12, PDP-13 | P1 | WooCommerce's own: gallery with zoom and its order, name, options, quantity, Add to Cart, short and full description, attributes as the specifications table (absent when there are none); the video and the brand are specs/036 and specs/033 |
| PDP-06, PDP-22 | P1 | Stock per variant and its message read the stock source (ERP-02, P1-E): built in specs/026 and specs/049, pending PRE-09 |
| PDP-10, PDP-19 | P1 | Wishlist and recently viewed: specs/015 |
| PDP-17 | P1-L | Reviews: specs/020 |
| PDP-18 | P1 | Related products: specs/036 (ADM-45) |
| PDP-24 | P1 | Structured data: specs/038 |
| PDP-20, PDP-23 | P2 | Frequently bought together, back-in-stock signup: not built |
| OD-08 | Client decision | The return window the returns summary states |

## Open contract items

- OD-08 (the return window): the summary is the admin's words; the client writes it once OD-08 is decided.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A product on sale states its saving (amount and percent); a variable product its largest; a product not on sale none | PDP-04 | final |
| AC-2 | Buy Now adds the product or the chosen variation, with its quantity, to the cart and takes the visitor to checkout | PDP-09 | final |
| AC-3 | A delivery estimate is stated for the customer's shipping zone where the address is known and the admin set one, otherwise the admin's default, in working days, as an estimate and never as a date | PDP-14 | final |
| AC-4 | A returns summary and an authenticity statement are stated in the page's language, as the admin wrote them, each linking to its page in that language | PDP-15, PDP-16 | provisional |
| AC-5 | Share links for WhatsApp, Facebook, X and email carry the product's address, and the product page carries Open Graph tags so a shared link shows its title, image and price | PDP-21 | final |

AC-4 is provisional: the summary must agree with the return window approved under OD-08.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The buyer sees the saving and acts (Priority: High)

1. **Given** a lamp at 200, on sale at 150, **When** its page loads, **Then** it states a saving of 50, or 25%.
2. **Given** two lamps chosen, **When** Buy Now is pressed, **Then** checkout opens with the two lamps in the cart.

### User Story 2 - The cautious buyer (Priority: High)

1. **Given** a customer whose address is in Cairo and a Cairo estimate of 1 to 2 days, **When** the page loads,
   **Then** it states "Estimated delivery: 1 to 2 working days".
2. **Given** an Arabic page, **When** it loads, **Then** the returns summary and the authenticity statement read in
   Arabic, linking to the Arabic policy pages.

### Edge Cases

- A text left empty in Arabic falls back to the English one rather than nothing.
- A zone estimate written wrongly (not "1-3") is ignored, and the default stands.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Saving | Regular and sale prices | The amount and percent, computed in the Store API extension |
| Buy Now | WooCommerce's add-to-cart address and its redirect filter | `mizzey_buy_now=1` redirects to checkout |
| Delivery estimate | Shipping zones and their matching | An estimate per zone and a default, in WooCommerce > Settings > Products > Product page |
| Returns, authenticity | Pages | The two texts in both languages and their pages, in the same section |
| Share | None | The share addresses; Open Graph tags on the product page |

## Requirements

### Functional Requirements

- **FR-001**: The Store API product response MUST carry `extensions.mizzey-page` with `saving`, `buy_now`,
  `delivery`, `returns`, `authenticity` and `share` (AC-1 to AC-5).
- **FR-002**: The settings MUST be in WooCommerce > Settings > Products > Product page (AC-3, AC-4).
- **FR-003**: A product page MUST carry `og:title`, `og:description`, `og:url`, `og:image`, `og:type`,
  `product:price:amount` and `product:price:currency` (AC-5).

### Key Entities

- **Product page settings**: the two texts per language, their pages, the default estimate, an estimate per zone.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The designed page (PRE-03b); frequently bought together and back-in-stock signup (P2).

## Success Criteria

- **SC-001**: The client changes every statement on the product page without a developer.

## Delivery stage

Contractual stage: S1, with #254. Engineering timing: 4 November 2026, the function layer (D-27).

## Assumptions

- Delivery days are working days; the courier's own estimate (Bosta) can replace a zone's figure later, without a change
  to what the page reads.

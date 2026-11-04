# Feature Specification: manual product ordering within categories and collections

**Feature Branch**: `061-manual-order`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #282 (merchandising controls) and #277 (categories, brands, collections, home sections and pages managed
from admin), their function layer under D-27. Most of both is in place: the badges, related products and product SEO
(specs/036), the featured flag (WooCommerce), curated collections (specs/003's taxonomy, with its admin screen), the
categories' and brands' own order, images and SEO (WooCommerce, specs/038), the home sections (specs/056) and pages
(WordPress). What was missing is MER-03's ordering within a category or a collection: WooCommerce has one order for the
whole catalogue. The presentation rows (MER-06, MER-07) are PRE-03b's.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MER-03 | P1 | S1 | Manual product ordering within categories and collections |
| ADM-55 | P1 | S1 | Categories and subcategories with ordering, images and SEO |
| ADM-57 | P1 | S1 | Manually curated collections |

MER-03 is owned by #282; ADM-55 and ADM-57 by #277.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| MER-01, MER-02, MER-04, MER-05 | P1 | Badges, featured, curated collections, related products: specs/036, WooCommerce, specs/003 |
| MER-06, MER-07 | P1 | Premium visual and discount presentation: PRE-03b |
| ADM-56, ADM-59 to ADM-61, ADM-63 | P1, P1-L | Brands, home sections, pages: WooCommerce, specs/056, WordPress |
| ADM-58, ADM-62 | P2 | Rule-based collections and scheduled home sections: not built |
| SSC-24 | P1 | Per-product placement in collections (#311): the positions set here are that placement |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Positions set on a category's edit screen order its page: the positioned products first, by position, then the rest in the store's order; the Arabic page in the same order | MER-03, ADM-55 | final |
| AC-2 | The Store API, for that category in the store's sorting, answers the same order; a sorting the shopper chooses is left as it is | MER-03 | final |
| AC-3 | A collection is ordered by its own positions, independently of a category, and a change on its screen applies at once | MER-03, ADM-57 | final |
| AC-4 | Positions are saved only by staff with the permission, through the screen's nonce | MER-03 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The best sellers first (Priority: High)

1. **Given** Kitchen with four products, **When** staff give the bamboo organiser position 1, **Then** it leads the
   Kitchen page in both languages; a shopper sorting by price still gets price order.

### Edge Cases

- A product without a position keeps the store's own order after the positioned ones.
- A query for several categories at once has no single manual order: the store's order applies.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Order within a category or collection | One catalogue-wide manual order (`menu_order`) | Positions per category and collection on the term screen; applied to its page and Store API |
| Category order, images, SEO | WooCommerce; specs/038 | Used as they are |
| Curated collections | specs/003's taxonomy and screen | Used as it is |

## Requirements

### Functional Requirements

- **FR-001**: Each category's and collection's edit screen MUST carry "Product order" (AC-1, AC-3).
- **FR-002**: Positions MUST be kept per main-language term, on each language record of the product (AC-1).
- **FR-003**: The order MUST apply to the term's page in the default sorting and to a single-term Store API query in
  `menu_order` (AC-1, AC-2).

### Key Entities

- **Position**: product, term, a whole number from 1.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Drag-and-drop ordering (a designed admin control); rule-based collections (ADM-58, P2).

## Success Criteria

- **SC-001**: Merchandising a category needs no developer.

## Delivery stage

Contractual stage: S1, with #282 and #277. Engineering timing: 4 November 2026, the function layer (D-27).

## Assumptions

- The screen lists up to 200 of the term's products; a larger category is ordered by its leading products.

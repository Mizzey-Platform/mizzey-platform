# Feature Specification: the listing's filters, sorting and paging

**Feature Branch**: `057-listing-filters`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #303 (product listing, category and collection pages with filters and sorting, in both languages), its
function layer under D-27. WooCommerce covers this layer: its product query (the Store API, which its product
collection and filter blocks read), its category pages and their breadcrumb and result count. This slice records that
coverage, measures it in both languages, and builds nothing new, except where an earlier slice had to (the options
made store attributes so the attribute filter can offer them, specs/053). The designed pages and the phone's filter
drawer are PRE-03b's.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| PLP-06 | P1 | S1 | Filter by category |
| PLP-07 | P1 | S1 | Filter by brand |
| PLP-08 | P1 | S1 | Filter by price range |
| PLP-10 | P1 | S1 | Filter by availability |
| PLP-12 | P1 | S1 | Sort by newest |
| PLP-13 | P1 | S1 | Sort by price ascending / descending |
| PLP-14 | P1 | S1 | Sort by best selling |
| PLP-16 | P1 | S1 | Pagination or infinite scroll |
| PLP-17 | P1 | S1 | Result count displayed |
| PLP-18 | P1 | S1 | Active filters visible and individually removable |
| PLP-20 | P1 | S1 | Category banner and description |
| PLP-21 | P1 | S1 | Breadcrumb navigation |

Stories US-03-01, US-03-02 and US-03-04 (Functional Specification MS-SPC-2026-032 v1.4). Owned by #303.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| PLP-09 | P1 | Filter by attribute: measured in specs/053 (t93), on the options the load makes store attributes |
| PLP-01 to PLP-05 | P1 | The card: image, name, price, sale price and badge (specs/036), stock (ERP-02, P1-E), quick add to cart and wishlist (specs/015): presentation and the Store API, PRE-03b |
| PLP-11 | P1-L | Filter by rating: the second release |
| PLP-15 | P1 | Sort by relevance: a search's order, #304 |
| PLP-19 | P1 | The empty state: WooCommerce's "no results" block, and #304's suggestions |
| PLP-22 | P1 | The phone's filter drawer: presentation, PRE-03b |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Filters by category, brand, price range and availability return the matching products only, on the English and Arabic store | PLP-06, PLP-07, PLP-08, PLP-10 | final |
| AC-2 | Sorting by newest, price ascending, price descending and best selling, kept while a filter changes | PLP-12, PLP-13, PLP-14 | final |
| AC-3 | Results are paged, with the result count | PLP-16, PLP-17 | final |
| AC-4 | A category's page carries its description, breadcrumb and result count; its filters and sort live in its address, so they are kept on returning and each can be removed on its own | PLP-17, PLP-18, PLP-20, PLP-21 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Narrowing down (Priority: High)

1. **Given** a kitchen of three products, **When** the shopper limits it to 100 to 250 and in stock, sorted by price
   descending, **Then** the list keeps that sort as filters change, pages by the chosen size and states the count.
2. **Given** that filtered page, **When** the shopper opens a product and goes back, **Then** the same filters apply.

### Edge Cases

- A filter that matches nothing returns an empty list with a count of 0; the page shows WooCommerce's "no results"
  block.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Category, brand, price, availability filters | WooCommerce's product query and filter blocks | None |
| Attribute filter | Same, on store attributes | specs/053 makes the options store attributes |
| Sorting, paging, count | WooCommerce's product query and its blocks | None |
| Description, banner, breadcrumb | The category's description and image; WooCommerce's breadcrumb | None |
| Filters kept on return; removable | Filters and sort are in the address; WooCommerce's active filters block | None |

## Requirements

### Functional Requirements

- **FR-001**: No code: the requirements are WooCommerce's, measured by t97.

### Key Entities

- None.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Filter by rating (PLP-11), the second release.

## Success Criteria

- **SC-001**: Every listing behaviour the rows name works from the admin's data in both languages.

## Delivery stage

Contractual stage: S1, with #303. Engineering timing: 4 November 2026 (D-27).

## Assumptions

- The designed pages use WooCommerce's product collection and filter blocks (D-27), which read this query.

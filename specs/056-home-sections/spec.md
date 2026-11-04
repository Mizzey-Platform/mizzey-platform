# Feature Specification: what the home page's sections draw from

**Feature Branch**: `056-home-sections`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #302 (the home page, in both languages, with sections the client edits and reorders), its function layer
under D-27. The home page is a page of blocks the client edits in the block editor (the mechanism the owner chose for
the content rows); the designed sections are PRE-03b's (D-14).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| HOME-02 | P1 | S1 | Trending products section |
| HOME-03 | P1 | S1 | New arrivals section |
| HOME-04 | P1 | S1 | Featured collections section |
| HOME-05 | P1 | S1 | Category shortcuts |
| HOME-06 | P1 | S1 | Brand highlights |
| HOME-10 | P1 | S1 | Section content editable without a developer |
| HOME-11 | P1-L | S1 | Section order changeable without a developer |

Stories US-02-01 and US-02-03 (Functional Specification MS-SPC-2026-032 v1.4): "Each section draws its products from
the rule or curation set in admin, not from code"; "An empty section ... renders without a broken region". Owned by
#302.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| HOME-01, HOME-07 | P1 | Hero and offer banners: core image and cover blocks, the client's own images and words in each language |
| HOME-08 | P1 | Trust signals: blocks with the client's words in each language; the product page's statements are specs/055 |
| HOME-09 | P1 | The signup prompt: a link to the account's registration, whose consent is separate (specs/017, AUTH-09) |
| HOME-12, HOME-13 | P2, P3 | Scheduling and a personalised home page: not built |
| MER-01 | P1 | The badges themselves: specs/036 (D-19) |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A product's badges (New, Trending, Limited, Best Seller) are available to the store's product sections as a taxonomy, kept equal to the badges set on the product, its Arabic record included | HOME-02, HOME-03 | final |
| AC-2 | A product section on the home page shows the products its admin rule selects (a badge, a category, a collection, newest) and follows a change made in the admin with no code change; a rule that matches nothing leaves the page whole | HOME-02, HOME-03, HOME-10 | final |
| AC-3 | Categories, brands and collections marked "Show on the home page" are listed in their admin-set position, in the request's language, for the category shortcuts, brand highlights and featured collections | HOME-04, HOME-05, HOME-06, HOME-11 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Trending is the admin's (Priority: High)

1. **Given** a lamp flagged Trending, **When** the home page loads, **Then** the Trending section shows it.
2. **Given** a mug flagged Trending afterwards, **When** the page loads again, **Then** the section shows it too.

### User Story 2 - The shortcuts are the admin's (Priority: High)

1. **Given** Beauty at position 1 and Kitchen at 2, both shown on the home page, **When** the shortcuts load in
   Arabic, **Then** they read الجمال then المطبخ.

### Edge Cases

- A section whose rule matches nothing renders nothing in its place; the page around it stands.
- The home settings are kept on the main-language term, so the Arabic one always follows.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Sections, their order and content | The block editor; each language's page | Used as it is (HOME-10, HOME-11) |
| A product section by rule | WooCommerce's Product Collection block: newest, on sale, featured, category, brand, collection, by taxonomy | Used as it is |
| A section by badge | None: the badges are product meta | The badges mirrored into a taxonomy the block can filter by |
| Which terms go on the home page | None | "Show on the home page" and a position on each category, brand and collection; `GET mizzey/v1/home/terms` |

## Requirements

### Functional Requirements

- **FR-001**: The taxonomy `mizzey_badge` MUST hold the four badges and MUST equal each product's badges after every
  save and every copy to its translation (AC-1).
- **FR-002**: Each product category, brand and collection MUST carry "Show on the home page" and a position, saved on
  the main-language term, with WordPress's nonce and the term-management permission (AC-3).
- **FR-003**: `GET mizzey/v1/home/terms?taxonomy=` MUST list the shown terms as AC-3, and refuse another taxonomy.

### Key Entities

- **Home term**: name, address, description, image, product count, in the request's language.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Scheduling a section (HOME-12, P2); a personalised home page (HOME-13, P3).

## Success Criteria

- **SC-001**: No home page change needs a developer.

## Delivery stage

Contractual stage: S1, with #302. Engineering timing: 4 November 2026, the function layer (D-27).

## Assumptions

- A collection's image is set as for a category (`thumbnail_id`) once #277's collection screen carries it.

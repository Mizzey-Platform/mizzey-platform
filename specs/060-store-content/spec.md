# Feature Specification: promotional banners and each term's page content

**Feature Branch**: `060-store-content`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #311 (self-service storefront and product control), its function layer under D-27. Most of it is WordPress's
and WooCommerce's own, and earlier slices': pages and their blocks, each language's own version, preview and revisions
(SSC-01, SSC-02, SSC-10, SSC-12 to SSC-14); hiding a block without deleting it (WordPress 6.9 and later, SSC-03); the
navigation (specs/054, SSC-07); every product field, its badges, related products and SEO (specs/036, SSC-20 to
SSC-22, SSC-25, SSC-27); its featured flag, visibility and schedule, shipping and tax class (WooCommerce, SSC-23,
SSC-26, SSC-28). What was missing: a banner on category, brand and collection pages, and promotional banners that are
scheduled and targeted. The designed screens are PRE-03b's.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| SSC-03 | P1 | S1 | Show or hide any home page section without deleting its content |
| SSC-04 | P1 | S1 | Category page banners, descriptions and hero imagery, per category |
| SSC-05 | P1 | S1 | Brand page content, per brand |
| SSC-06 | P1 | S1 | Collection page content and product ordering, per collection |
| SSC-09 | P1-L | S1 | Promotional banners: create, schedule, target to a page, and remove |
| SSC-12 | P1 | S1 | All of the above editable independently in English and Arabic |

Owned by #311. SSC-06's product ordering within a collection is MER-03's (#282).

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| SSC-01, SSC-02, SSC-07, SSC-08, SSC-10, SSC-11, SSC-13, SSC-14 | P1, P1-L | The block editor, its preview and revisions, menus and pages; the section library and templates are PRE-03b's |
| SSC-20 to SSC-29 | P1, P1-E, P1-L | Product control: WooCommerce's product screen and specs/036; the stock field is P1-E |
| HOME-12 | P2 | Scheduling a home page section: not built; a promotional banner's schedule is SSC-09's own |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A promotional banner is shown only within its dates and on its targets (every page, the home page, the shop, given pages, given categories), in the page's language; moved to the bin it is gone | SSC-09, SSC-12 | final |
| AC-2 | Its content is written in the editor, and its dates and targets set by staff on its screen | SSC-09 | final |
| AC-3 | A category, a brand and a collection each carry a banner (image, alternative text, optional link) and their description, set on their screens; shown in both languages, the Arabic term with its own words and, if it sets one, its own banner; a collection has an image | SSC-04, SSC-05, SSC-06, SSC-12 | final |
| AC-4 | A section hidden in the editor is not shown and its content is kept | SSC-03 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A weekend offer (Priority: High)

1. **Given** a banner for the offers page from Friday to Sunday, **When** the page is opened on Saturday, **Then** the
   banner shows; on Monday it does not.

### Edge Cases

- A banner with no dates shows until it is removed.
- A banner's targets name the main-language page or category, so its Arabic version follows them.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Hide a section | WordPress's block visibility (6.9 and later) | Used as it is |
| Category and brand description, image | WooCommerce | Used as they are |
| A banner per category, brand, collection; a collection image | None | Term fields, `GET mizzey/v1/term-content` |
| Promotional banners with dates and targets | None | The "Promotional banner" type, its screen, the `mizzey/promo-banners` block and `GET mizzey/v1/promos` |

## Requirements

### Functional Requirements

- **FR-001**: Promotional banners MUST be a post type with content, revisions and a schedule-and-targets box (AC-1, AC-2).
- **FR-002**: The term fields MUST be saved with WordPress's nonce and the term-management permission (AC-3).

### Key Entities

- **Promotional banner**: content, start, end, targets. **Term content**: banner, banner link, image.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Scheduling a home page section (HOME-12, P2).

## Success Criteria

- **SC-001**: A campaign's banner is put up and taken down without a developer.

## Delivery stage

Contractual stage: S1, with #311. Engineering timing: 4 November 2026, the function layer (D-27).

## Assumptions

- Images are chosen from the media library by their address or id; the designed admin picker is not owed.

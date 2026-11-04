# Feature Specification: the header and its navigation, as data

**Feature Branch**: `054-header-navigation`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #253 (header, navigation and footer), its function layer under D-27: the data, validation, admin settings
and REST the designed header will read. The designed header, the mega menu's look and the mobile header are
PRE-03b's (D-14). The language switcher's behaviour is specs/052.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NAV-03 | P1 | S1 | Clear, prominent search bar |
| NAV-04 | P1 | S1 | Account entry point |
| NAV-05 | P1 | S1 | Wishlist entry point |
| NAV-06 | P1 | S1 | Cart icon with live item count |
| NAV-07 | P1 | S1 | Mega menu / category navigation, admin-manageable |
| NAV-09 | P1 | S1 | Mobile: quick search |
| NAV-10 | P1 | S1 | Mobile: hamburger menu |

Stories US-01-05 and US-01-06 (Functional Specification MS-SPC-2026-032 v1.4): "The cart icon shows a live item
count that updates when the cart changes, without a page reload"; "The mega menu and category navigation are driven
by admin managed structure, not by code"; "Given the menu structure is changed in admin, when the storefront is
reloaded, then the new structure appears"; "The hamburger menu exposes the same category structure as the desktop mega
menu". Owned by #253.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| NAV-01 | P1 | The logo: WordPress's site logo, native, placed by the designed header |
| NAV-02 | P1 | The switcher: specs/052 |
| NAV-08 | P1 | The simplified mobile header: presentation, PRE-03b |
| SRCH-01 to SRCH-08 | P1, P1-L | What the search does: #304 |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The header's data gives the cart's live item count, the wishlist's count, whether the visitor is signed in, and the addresses of the cart, wishlist, account and search, in the request's language, never cached | NAV-03, NAV-04, NAV-05, NAV-06, NAV-09 | final |
| AC-2 | The category navigation is the admin's categories, in WooCommerce's own order, with their children, in the request's language, empty categories and the default category left out; the mobile menu reads the same structure | NAV-07, NAV-10 | final |
| AC-3 | Changed in the admin (the categories, their order, a category taken out of the main menu), the navigation follows on the next request, in both languages, with no code change | NAV-07 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The header knows the cart (Priority: High)

1. **Given** an empty cart, **When** two mugs are added, **Then** the header's count reads 2 without a page reload.

### User Story 2 - The menu is the admin's (Priority: High)

1. **Given** Beauty ordered before Home in Products > Categories, **When** the menu loads, **Then** Beauty comes first,
   with Home's sub-categories under Home, in English and in Arabic.
2. **Given** "Show in the main menu" unticked on Beauty, **When** the menu loads, **Then** Beauty is not in it, in
   either language.

### Edge Cases

- A category with no products is left out.
- The setting is kept on the category in the main language, so the Arabic category always follows it.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Logo, menu links | Site logo; WordPress navigation menus | Used as they are |
| Cart count | WooCommerce's cart count (`get_cart_contents_count()`) and its mini cart | Read into the header's data, from the visitor's session |
| Category order | WooCommerce's drag-and-drop order in Products > Categories | Used as it is |
| A category out of the menu | None | A "Show in the main menu" setting on the category |
| One read for the header | None | `GET mizzey/v1/header`, `GET mizzey/v1/navigation` |

## Requirements

### Functional Requirements

- **FR-001**: `GET mizzey/v1/header` MUST answer as AC-1, `Cache-Control: no-store`.
- **FR-002**: `GET mizzey/v1/navigation` MUST answer the tree of AC-2, three levels at most.
- **FR-003**: Each product category MUST carry "Show in the main menu" on its add and edit screens, saved on the
  main-language category, with WordPress's nonce and the category-management permission (AC-3).

### Key Entities

- **Category navigation node**: id, name, address, product count, image, children.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The designed header, mega menu and mobile header (PRE-03b).

## Success Criteria

- **SC-001**: No menu change needs a developer.

## Delivery stage

Contractual stage: S1, with #253. Engineering timing: 4 November 2026, the function layer (D-27).

## Assumptions

- The menu shows product categories; other links (pages, offers) are WordPress navigation menu items the admin adds.

# Feature Specification: the store's own product search

**Feature Branch**: `058-search`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: #304 (search that works in English and Arabic, with an admin-managed synonym list), its function layer
under D-27. D-27 item 2 and D-20 row B13 settle the component: Mizzey's own search, no licensed component or plugin,
with the curated Arabic synonym and correction list managed by staff; an exact SKU leads; text suggestions; staff
order then newest as the fallback; empty-state suggestions that are not personal. The designed search box, its
suggestion panel and the results page are PRE-03b's (D-14).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| SRCH-01 | P1 | S1 | Full-text search across products |
| SRCH-02 | P1 | S1 | Search across name, description, SKU, brand, category |
| SRCH-03 | P1 | S1 | Autocomplete / suggestions as you type |
| SRCH-04 | P1 | S1 | Search results page with the full filter set |
| SRCH-05 | P1 | S1 | Zero-result page with alternative suggestions |
| SRCH-06 | P1-L | S1 | Arabic search handling: synonym and correction list covering main categories and brands, including common Franco-Arabic spellings |
| SRCH-06a | P1 | S1 | Search operates correctly in both English and Arabic |
| SRCH-07 | P1 | S1 | Admin-managed synonym list |
| SRCH-08 | P1 | S1 | Search term logging for later analysis |

Stories US-03-05 to US-03-08 (Functional Specification MS-SPC-2026-032 v1.4). Owned by #304. SRCH-06's boundary
(Annex A section 1.2): a curated list, "not full automatic typo tolerance across the whole catalogue".

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| SRCH-09 | P2 | An external search engine: not built, and not needed |
| PLP-15 | P1 | Sort by relevance: this search's order (#303's row, served here) |
| EVT-03, EVT-18 | P1, P1-L | The `search` and `zero_result_search` events: specs/044 |
| RPT (search terms report) | Second release | The report over the log: US-03-08 places it in the second release |

## Open contract items

- None. The list's first entries are the client's content (D-27).

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A search finds products by name, description, SKU, brand and category, every word of the query matched, on the English and Arabic store; an exact SKU comes first, then names, then brands and categories, then the staff's order and newest | SRCH-01, SRCH-02, SRCH-06a | final |
| AC-2 | Arabic is matched however it is written in its equal forms: the hamza and madda forms of alef, taa marbuta, alef maqsura, short vowels and tatweel, Arabic-Indic digits | SRCH-06a | final |
| AC-3 | A term on the curated list, searched in any of its listed forms (Arabic, English, Franco-Arabic, a misspelling, several words), finds what the other forms find; a misspelling the list does not name finds nothing | SRCH-06 | final |
| AC-4 | The list is managed in the admin by staff, without a developer, and a change applies at once | SRCH-07 | final |
| AC-5 | Suggestions appear as one types, from two characters: products and categories or brands, and the full results' address; a search with no result offers the most stocked categories and the best sellers, the same for everyone | SRCH-03, SRCH-05 | final |
| AC-6 | Every search is recorded with its term and result count, a search with no result told apart; suggestions as one types are not recorded, and nothing about the person is | SRCH-08 | final |
| AC-7 | The results page is the product archive, with its full filter set, count and ordering | SRCH-04 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The shopper's own words (Priority: High)

1. **Given** a matte lipstick, its Arabic record أحمر شفاه مطفي, and the list line
   `lipstick, روج, احمر شفاه, ahmar shafayef, lipstik`, **When** a shopper searches روج, ahmar shafayef or lipstik,
   **Then** the lipstick is found, in the store's language.
2. **Given** the same product, **When** someone searches "lipstck", which the list does not name, **Then** nothing is
   found: the list is what is covered.

### User Story 2 - Staff keep the list (Priority: High)

1. **Given** WooCommerce > Search synonyms, **When** staff add `serum, serun`, **Then** a search for "serun" finds the
   serum at once.

### Edge Cases

- A single letter matches nothing on its own, so it cannot return the whole catalogue.
- An Arabic comma (،) separates forms like a comma.
- A line with one form is not a group.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Search | WordPress's search: title, excerpt and content only; no SKU, brand or category; Arabic compared letter by letter | Mizzey's own index (`SearchIndex`), normaliser (`Normalise`) and query (`ProductSearch`), on WordPress's product query, so WooCommerce's results page and Store API carry it |
| Synonyms | None | `Synonyms` and its admin screen |
| Suggestions | None in WooCommerce's search box | `GET mizzey/v1/search/suggest` |
| The log | None | `mizzey_search_log` |
| Results page and its filters | WooCommerce's product archive, with its filter blocks | Used as it is |

## Requirements

### Functional Requirements

- **FR-001**: The index MUST hold, per product record, its normalised name, descriptions, SKUs (its variations'
  included), and brand and category names (with each category's parents), kept on save and on a term's rename.
- **FR-002**: The product search MUST apply to the storefront and the Store API, never to the administration.
- **FR-003**: The synonym list MUST be kept as one group per line, edited by `manage_woocommerce` with WordPress's
  nonce.
- **FR-004**: The log MUST hold the term, its normalised form, the language, the result count and the time, and
  nothing about the person; rows older than a year are removed.

### Key Entities

- **Index row**, **synonym group**, **log row**.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The search terms report (second release); automatic typo tolerance (excluded); an external engine (SRCH-09, P2).

## Success Criteria

- **SC-001**: Every form on the list finds its products in both languages.

## Delivery stage

Contractual stage: S1, with #304. Engineering timing: 4 November 2026, the function layer (D-27); #304 follows #303,
measured in specs/057.

## Assumptions

- The catalogue is a few thousand products (OD-15's default is 5,000); the index is a table read with `LIKE`, which
  holds at that size. A larger catalogue would add a full-text index without changing what the search finds.

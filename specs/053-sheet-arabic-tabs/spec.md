# Feature Specification: the product sheet carries its Arabic, and its tabs

**Feature Branch**: `053-sheet-arabic-tabs`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: The owner, 4 November 2026 (translated from Arabic): the client cannot export from Amazon and fills the
product sheet by hand, most likely in Google Sheets, perhaps a tab per category; the categories, brands and options
need their Arabic. Staging showed it (specs/051): with the first sheet the Arabic store shows no category, and option
names and values read in English. D-32 records the choice: the sheet carries the Arabic beside the English, and the
importer reads the workbook as downloaded.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MIG-01 | P1-L | S1 | The Developer accepts one agreed catalogue export in CSV, tab-delimited or Excel format for the initial migration |
| MIG-10 | P1-L | S1 | Source attributes are mapped to store attributes during the initial migration |
| MIG-11 | P1-L | S1 | Source categories are mapped to the store category structure during the initial migration |
| MIG-12 | P1-L | S1 | Brands are mapped during the initial migration |
| MIG-19 | P1-L | S1 | Validation issues encountered during the initial migration are reviewed by the Developer and provided to the Client as an actionable issue list where Client correction is required |

SSC-21 (product content maintained separately in English and Arabic) is the reason the Arabic matters; it is traced
by specs/046. The PBIs are unchanged: MIG-01 and MIG-19 belong to #249, MIG-10 to MIG-12 to #295.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| MIG-05, MIG-07, MIG-08 | DEF | Nothing reusable: one file, for the one initial migration |
| CR-15 | Client input | The Arabic words are the client's; the sheet is where they write them |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A workbook (`.xlsx`, as Google Sheets and Excel download one) is read as the one sheet: every tab with the header, in order, columns matched by name, each row with its tab and line; the reports name each row's tab; numbers read as typed | MIG-01, MIG-19 | final |
| AC-2 | A tab whose columns differ from the first tab's stops the read, naming the tab and the columns | MIG-01 | final |
| AC-3 | The sheet may carry `category_ar`, `brand_ar` and `option_<name>_ar`; a sheet without them is still read | MIG-01, MIG-10 | final |
| AC-4 | The load gives each category level and the brand their Arabic names, created where the store has none, so the Arabic product shows them; a category or brand already translated in the store keeps its translation | MIG-11, MIG-12 | final |
| AC-5 | The load gives the options their Arabic: on the product's row, the option's name; on each variation row, its value; a value with no Arabic keeps its English wording | MIG-10 | final |
| AC-6 | An Arabic category with other levels than the English one is a client issue (W11), and its Arabic is not used; an Arabic option column without its option's column stops the run (F4) | MIG-11, MIG-19 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Tabs by category (Priority: High)

1. **Given** a Google Sheet with a Kitchen tab and a Clothing tab, the same columns in each, **When** it is downloaded
   as Excel and validated, **Then** every row of both tabs is planned, and an issue names its tab and line.

### User Story 2 - The Arabic store reads Arabic (Priority: High)

1. **Given** `Kitchen > Storage` and `المطبخ > التخزين`, **When** loaded, **Then** the Arabic product sits in التخزين
   under المطبخ.
2. **Given** a T-shirt by Size and Colour, with `option_size_ar` = المقاس and `option_colour_ar` = اللون on its row and
   أسود on a black variation, **When** loaded, **Then** the Arabic product page offers المقاس and اللون, with أسود.

### Edge Cases

- A tab with only the header, or none, is skipped.
- A tab with the same columns in another order is read by name.
- A size like M or L with no Arabic stays M or L in Arabic.
- An Arabic name already used by another category or brand is not taken over: the load reports it.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Read a workbook | WooCommerce's importer reads CSV only | PHP's own zip and XML readers (`Workbook`), no library |
| Arabic categories and brands | WPML term translations | Created through WPML's own API where a term has none |
| Arabic option names and values | WooCommerce Multilingual's product editor fields for custom attributes | Passed through the editor's own save (`ProductTranslationJob`) |

## Requirements

### Functional Requirements

- **FR-001**: The reader MUST read `.xlsx` as described in AC-1 and refuse a mismatched tab (AC-2).
- **FR-002**: The mapping MUST allow optional columns and an Arabic suffix for its prefixes (AC-3).
- **FR-003**: The load MUST write Arabic category levels, brands and options as in AC-4 and AC-5.
- **FR-004**: The rules MUST raise W11 and F4 as in AC-6.

### Key Entities

- **The sheet**: its columns now include `category_ar`, `brand_ar`, `option_<name>_ar`; its rows carry their tab.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Reading a Google Sheet straight from Google (an account connection): not built; the sheet is downloaded.

## Success Criteria

- **SC-001**: After the load, no category, brand or option of a product with Arabic text reads in English on the
  Arabic store, unless the sheet left its Arabic empty.

## Delivery stage

Contractual stage: S1 (the initial migration). Engineering timing: 4 November 2026, before the client's test file.

## Assumptions

- Option names stay in English letters (`option_size`); their Arabic goes in the `_ar` column.

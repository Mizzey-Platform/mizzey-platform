# Feature Specification: the importer reads Mizzey's product sheet

**Feature Branch**: `051-product-sheet-import`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: The owner, 4 November 2026: the client fills Mizzey's own product sheet (`Mizzey-Product-Sheet.csv`)
instead of sending an Amazon export, and a test file of about ten products is due around 15 November. The importer
(specs/006 and specs/046) was built on an illustrative export; this slice makes the plugin's mapping read the sheet.
The owner's instruction: options are generic, so any `option_<name>` column becomes the attribute `<name>` without the
mapping listing every possible option (never size and colour only). It is for the one initial migration.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MIG-01 | P1-L | S1 | The Developer accepts one agreed catalogue export in CSV, tab-delimited or Excel format for the initial migration |
| MIG-04 | P1-L | S1 | The Developer maps the source file columns to store fields for the initial migration. A self-service column-mapping interface is not delivered under the Launch Platform |
| MIG-09 | P1-L | S1 | Product variations present in the agreed source data are converted correctly into variable products during the initial Developer-performed migration |
| MIG-19 | P1-L | S1 | Validation issues encountered during the initial migration are reviewed by the Developer and provided to the Client as an actionable issue list where Client correction is required |
| MIG-10 | P1-L | S1 | Source attributes are mapped to store attributes during the initial migration |

The PBIs are unchanged: MIG-01, MIG-04 and MIG-19 belong to #249, MIG-09 and MIG-10 to #295 (`docs/scope/backlog-ownership.json`).
This slice replaces the stand-in source those PBIs were verified on with the agreed one.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| MIG-05 | DEF | Saved mapping profiles: not built. The mapping stays one file kept with the plugin, for the initial migration |
| MIG-07 | DEF | Safe re-runs: not built here; the load's own rules are unchanged |
| MIG-08 | DEF | Control over which fields an update overwrites: not built |
| MIG-11, MIG-12, MIG-13, MIG-15 | P1-L | Categories, brands, prices and cost, SEO fields: written by the existing load (specs/046); this slice only maps the sheet's columns to them |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The plugin's mapping reads the product sheet: every column is mapped (name and description in both languages, price, sale price, cost, category path, brand, image addresses, SEO title and description, Amazon identifier), none is left over, and the validation pass plans every row of the sample; the client's issue list asks for nothing the load does not use (no Arabic name for a variation row, which takes its product's) | MIG-01, MIG-04, MIG-19 | final |
| AC-2 | Every `option_<name>` column is read as the option `<name>` of a variation, whatever the name, without the mapping listing it | MIG-04, MIG-10 | final |
| AC-3 | An option column whose name cannot be read (no name, a name not in English letters, or the same option twice) stops the run and is named; its values are never dropped quietly | MIG-04 | final |
| AC-4 | The load writes the sheet: simple products, and variable products with each variation's options, price, sale price and cost; a product's options are only the ones its sheet columns give | MIG-09, MIG-10 | final |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The client's sheet loads as it is (Priority: High)

1. **Given** the sample sheet, **When** the Developer validates it, **Then** every column is mapped and every row is
   planned for creation.
2. **Given** a sheet with a column `option_scent`, **When** it is validated, **Then** scent is an option of the
   variations, with no change to the mapping.
3. **Given** a sheet with a column `option_اللون`, **When** it is validated, **Then** the run stops and names the
   column, with the correction (an English name).

### User Story 2 - Variations by any option (Priority: High)

1. **Given** a parent row and its variation rows that fill `option_capacity` only, **When** loaded, **Then** the
   product varies by capacity and has no size or colour.

### Edge Cases

- `option_pack_size` is the option "Pack size" (key `pack-size`).
- `option_Size` beside `option_size` names the same option twice: refused.
- A row type other than `simple`, `parent` or `variation` is refused by the existing rules (R7).

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| Read the sheet | WooCommerce's CSV importer reads its own column names only, from a screen | The existing importer (specs/006, specs/046), with the sheet's mapping |
| Any option | WooCommerce's custom product attributes, any name | Each `option_` column becomes one, by the mapping's prefix rule |

## Requirements

### Functional Requirements

- **FR-001**: The plugin's mapping MUST name the sheet's columns and its row types (`simple`, `parent`, `variation`) (AC-1).
- **FR-002**: The mapping MUST be able to name a header prefix (`option_`) that fills an attribute from the rest of the
  header, for any header of the file (AC-2).
- **FR-003**: A prefixed column the mapping cannot take MUST stop the run before any row is read, named (F4) (AC-3).
- **FR-004**: An option's name MUST read as the client wrote it, with spaces for `_` (AC-4).
- **FR-005**: A variation row MUST NOT be asked for Arabic text the load does not write (rule W3) (AC-1).

### Key Entities

- **Mapping**: its columns, and now its prefixes.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- A screen for the mapping or for repeated imports (MIG-04's exclusion, MIG-05, MIG-07, MIG-08).

## Success Criteria

- **SC-001**: The client's test file loads with no mapping change when its columns follow the sheet.

## Delivery stage

Contractual stage: S1 (the initial migration). Engineering timing: 4 November 2026, before the client's test file.

## Assumptions

- The sheet is UTF-8 CSV, as sent; a sheet saved in another encoding is converted first (MIG-02, the existing reader).
- Option names are written in English letters; the option values may be in any language.

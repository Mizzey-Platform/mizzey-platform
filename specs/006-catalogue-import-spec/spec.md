# Feature Specification: Catalogue import specification and validation pass

**Feature Branch**: `006-catalogue-import-spec`

**Created**: 2026-10-29

**Status**: Draft

**Work type**: requirement

**Input**: User description: "Catalogue import specification, validated against the real source file (#249). The
import specification exists, and a validation pass over the client's export file reports, per row, what will be
created, updated or rejected before anything is written to the store. Built against a fixture in the documented
export format until the real sample export (CR-06) arrives."

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| MIG-01 | P1-L | S1 | The Developer accepts one agreed catalogue export in CSV, tab-delimited or Excel format for the initial migration |
| MIG-03 | P1-L | S1 | The Developer previews and validates the source file before anything is written to the store |
| MIG-04 | P1-L | S1 | The Developer maps the source file columns to store fields for the initial migration |
| MIG-06 | P1-L | S1 | The Developer performs a validation pass before the live load and reports what will be created, updated or rejected |
| MIG-19 | P1-L | S1 | Validation issues encountered during the initial migration are reviewed by the Developer and provided to the Client as an actionable issue list where Client correction is required |

Stories: Functional Specification MS-SPC-2026-032, US-23-01 (file formats accepted), US-23-03 (column mapping, the
MIG-04 part only), US-23-05 (preview), US-23-06 (dry run, the MIG-06 part only) and US-23-10 (error report).

## Context rows (no obligation)

| ID | Scope | Why it matters here |
|---|---|---|
| MIG-02 | P1-L | Arabic content integrity of the import. Owned by #247 (E-MIG-3). The validation pass reports encoding problems it finds; verifying and normalising encoding is #247's |
| MIG-09, MIG-13 | P1-L | Variations converted into variable products, and price, sale price and cost migrated. Owned by #247. The mapping names their columns; converting and loading them is #247's |
| MIG-10, MIG-11, MIG-12, MIG-15 | P1-L | Attributes, categories, brands and SEO fields mapped. Owned by #295 (E-MIG-2). The mapping carries their columns; the store-side mapping tables are #295's |
| MIG-16, MIG-17 | P1-L | Images and alt text. Owned by #296 (E-MIG-4), subject to the rights confirmation CR-08 |
| MIG-20, MIG-25 | P1-L | The live load runs in the background; the catalogue stays exportable. Owned by #297 (E-MIG-6) |
| ENT-19 | P1 | Import Run: file, mapping used, row counts, outcome, actor, timestamp. Owned by #273 (E-DATA-1). Under CX-04 each developer-operated validation run is recorded as an Import Run |
| MIG-05 | DEF | Saved mapping profiles. Not built: one mapping for the initial migration only |
| MIG-07 | DEF | Safe re-runs that update by SKU. Not built here; the native importer's own behaviour is recorded under Native coverage |
| MIG-08 | DEF | Control over which fields an update may overwrite. Not built |
| MIG-18 | DEF | Import history (who, when, file, counts, outcome). Not built as a product; see CX-04 |
| MIG-21, MIG-22 | P2 | Undo the last import run; scheduled recurring imports. Not built |
| MIG-23, MIG-24 | OUT | Live Amazon synchronisation; Amazon orders and customers. Excluded |
| ADM-159 | P1 | The administration is English only. The validation report and the issue list are in English |

## Open contract items

- **CX-04 (resolved by the owner, D-12, not client-confirmed)**: ENT-19 is a real persisted Import Run record for the
  developer-operated Stage 1 import and validation process. This feature records each validation run as one Import
  Run when #273 provides the record, and never builds the deferred MIG-18 import-history product, a history browser
  or a reusable self-service re-upload workflow. Criteria resting on it are `pending CX-04`.
- **CR-06 (client input, pending)**: the real, unmodified sample export, including at least one simple product, one
  with variations, one with Arabic text and one with several images. The specification is not final, and is not
  presented as validated against the real file, until it arrives.
- **CR-07 (client input, pending)**: the Amazon account type and whether the Category Listings Report is available.
  It decides whether descriptions and variation data exist in the source at all.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The one agreed catalogue export is accepted in the format it is delivered in: CSV, tab-delimited or Excel | MIG-01 | final |
| AC-2 | A written import specification maps every column of the agreed export to a store field, or records it as not imported with the reason | MIG-04 | provisional |
| AC-3 | Before catalogue data is written, a preview shows the first rows interpreted under the mapping. Under the owner's CX-04 engineering reading, the separately recorded Import Run is permitted; this exception remains pending client confirmation | MIG-03 | pending CX-04 |
| AC-4 | For a file whose structure permits row interpretation, a validation pass over the whole file reports, for every row, whether it will be created, updated or rejected, and why | MIG-06 | final |
| AC-5 | The preview and the validation pass write nothing to the store's catalogue: products, variations, terms, media and their translations are unchanged after a run | MIG-03, MIG-06 | final |
| AC-6 | For a file whose structure permits row interpretation, for every data row that carries a SKU, the validation report states the resolved WooCommerce product or variation ID, or no match, and whether that product is the source-language original or a translation | MIG-06 | final |
| AC-7 | The Developer reviews validation issues and provides every issue requiring Client correction to the Client as an actionable issue list: the row, the field, the problem and what to change | MIG-19 | final |
| AC-8 | The specification is validated against the real sample export, and the validation report of that file is reviewed by the Developer before the live load | MIG-01, MIG-04, MIG-06 | provisional |

**CX-04 integration dependency (pending CX-04):** #249 supplies the source file or reference, the mapping used and its
version, the row counts, the run outcome, the actor, the timestamp, and the validation or error-summary reference
where applicable. ENT-19 persistence and its acceptance belong to #273. Development uses a contract and fixture
adapter until that implementation is available; a fixture record is not evidence of persisted ENT-19 integration.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The Developer previews the export before trusting it (Priority: High)

The Developer receives the client's export file and wants to see, before any catalogue data is changed, how the first
rows read under the mapping: which column became the product name, the SKU, the price, the category, and whether
the Arabic text arrived intact.

**Why this priority**: a wrong mapping found after a load means cleaning a live catalogue. The preview is the cheapest
point to catch it.

**Independent Test**: run the preview on the fixture export; the output lists the first rows field by field, and the
catalogue's product count and last-modified times are unchanged.

**Acceptance Scenarios**:

1. **Given** the fixture export in the agreed format, **When** the Developer runs the preview, **Then** the first rows are shown
   field by field under the mapping (AC-1, AC-3)
2. **Given** the export in the agreed format, **When** the Developer previews it without manually reformatting its
   data, **Then** its rows are interpreted under the documented mapping (AC-1)
3. **Given** any preview run, **When** it finishes, **Then** no product, variation, term, media item or translation
   was created, changed or deleted (AC-5)

---

### User Story 2 - The Developer knows exactly what a load would do (Priority: High)

Before the live load, the Developer runs a validation pass over the whole file and gets one line per row: create,
update or reject, with the reason, and for a row with a SKU, the product it would touch.

**Why this priority**: this is MIG-06's outcome and the operator safety net the native importer lacks (P-008).

**Independent Test**: run the pass on the fixture, which contains a new product, an existing product, a row with a
missing required field, a duplicate SKU within the file and a SKU that resolves to a translation; each row gets the
expected outcome and reason, and the catalogue is unchanged.

**Acceptance Scenarios**:

1. **Given** an otherwise valid row that the documented initial-load rules identify as a new product, **When** the pass
   runs, **Then** the row is reported as create (AC-4)
2. **Given** an otherwise valid row that the documented initial-load rules identify as an update to one
   source-language product, **When** the pass runs, **Then** the row is reported
   as update and names that product (AC-4, AC-6)
3. **Given** a row whose SKU is shared by a product and its translation, **When** the pass runs, **Then** the report
   names the product the SKU resolves to and says whether it is the source-language original, and explicitly reports
   the shared-SKU finding and the outcome required by the documented initial-load rules (AC-6)
4. **Given** a row that the documented initial-load rules reject because a required field is missing or a repeated
   source SKU cannot be resolved unambiguously, **When** the pass runs, **Then** the row is reported as reject with the
   documented reason (AC-4)
5. **Given** any validation run, **When** it finishes, **Then** the catalogue is unchanged (AC-5)

---

### User Story 3 - The Client receives the corrections they must make (Priority: Medium)

When the validation report identifies issues requiring Client correction under the documented initial-load rules,
the Developer reviews them and provides an actionable list, regardless of whether the affected row is created,
updated or rejected.

**Why this priority**: MIG-19's outcome. A partial load must not be a dead end, but the Client corrects their own
data rather than the Developer guessing.

**Independent Test**: from a fixture run with issues requiring Client correction, produce the issue list; every such
issue appears with its row, field, problem and the correction asked for, whatever the row's outcome, and issues the
Developer resolves in the mapping do not appear.

**Acceptance Scenarios**:

1. **Given** a validation run with issues requiring Client correction, **When** the Developer reviews them and produces
   the issue list, **Then** each issue appears with its row number, SKU where present, field, problem and the change
   asked for, whatever the row's outcome (AC-7)
2. **Given** an issue the Developer resolves in the mapping, **When** the issue list is produced, **Then** it is not on
   the Client's list (AC-7)

---

### User Story 4 - The real file proves the specification (Priority: Medium)

When the real sample export (CR-06) arrives, the Developer runs the preview and the validation pass on it, adjusts
the mapping where the real columns differ from the documented format, and reviews the report before any live load.

**Why this priority**: the register states that the import specification cannot be finalised before the real file.
It is the acceptance gate, not a development blocker (D-10).

**Independent Test**: on the real file, every column is either mapped or recorded as not imported with a reason, and
the validation report covers every row.

**Acceptance Scenarios**:

1. **Given** the real sample export, **When** the Developer runs the pass, **Then** every column is mapped or recorded as
   not imported, and every row has an outcome (AC-2, AC-8)

---

### Edge Cases

- A file with a byte-order mark or a declared encoding other than UTF-8 is previewed using the documented
  interpretation, and parsing problems are reported. This slice does not promise automatic encoding detection or
  verification that the eventual load preserves Arabic; MIG-02 belongs to #247.
- An Excel workbook with more than one sheet: the pass reads the one sheet the mapping names and reports the others.
- A header row that differs from the mapping (a renamed, missing or extra column): the differences are reported, and
  if they prevent the documented mapping the run stops with a file-level failure, rather than mapping by position.
- A variation whose parent cannot be resolved under the documented initial-load rules is reported with that reason. A
  parent absent from the file is not automatically unresolved if the rules permit an existing store parent. Variation
  conversion and loading belong to #247.
- For SKU whitespace or case differences, the report preserves the source value and identifies the interpretation and
  matching rule used. Its predicted outcome follows that same rule.
- An empty file, or a file with a header and no rows: reported as such, with no catalogue change.
- A large file: the whole agreed export is validated. The 5,000-product fixture (OD-15) is a provisional engineering
  sizing check, not a migration limit; the register keeps migration volume separate from OD-15.

## Native coverage

| Capability | Evidence | Status |
|---|---|---|
| Reading a UTF-8 CSV with a byte-order mark and Arabic text, and keeping the Arabic intact | P-008: WooCommerce 11.1.0's `WC_Product_CSV_Importer` round-tripped an Arabic name | PREVIOUSLY TESTED |
| Re-importing the unchanged simple-product fixture without creating a duplicate | P-008: update_existing enabled; first pass imported 1, second pass imported 0 and updated 0; one product remained. Updating changed values was not demonstrated | PREVIOUSLY TESTED |
| A dry run that reports outcomes without writing | P-008: no dry-run method in the importer's 33 methods | VERIFIED (absent) |
| Saved column mappings | P-008 | PREVIOUSLY INSPECTED: no saved-mapping method found on the importer class; this does not establish absence across the administration workflow |
| A column-mapping screen in the administration | WooCommerce's product CSV importer screen | UNVERIFIED by the cited P-008 evidence; no self-service mapping interface is delivered |
| Resolving a SKU to one product | A5, measured: a WPML duplicate carries its original's SKU, and `wc_get_product_id_by_sku()` returns whichever product the query reaches first | VERIFIED (a gap: the report must name the product and its language) |
| Excel workbooks | Not read by the native importer | UNVERIFIED (to confirm in the plan) |

The recorded dry-run and SKU-resolution gaps justify investigating the operator safety work. Excel intake remains
unverified and cannot yet justify custom implementation. Assess native coverage for the agreed format before choosing
additional parsing work. The eventual load belongs to the neighbouring migration slices.

## Requirements

### Functional Requirements

- **FR-001**: The Developer MUST accept the one agreed export in its agreed CSV, tab-delimited or Excel format. The
  fixture MUST exercise that format; equivalent copies in all three formats are not an acceptance condition (AC-1)
- **FR-002**: The import specification MUST record, for every column of the agreed export, the store field it maps
  to or that it is not imported and why, and MUST identify the mapping used and its version in each run's report (AC-2; CX-04 integration dependency)
- **FR-003**: The mapping MUST be the Developer's single mapping for the initial migration, kept with the
  specification; no reusable saved-mapping-profile capability is delivered and no self-service mapping interface is
  provided (AC-2)
- **FR-004**: The preview MUST show the first rows of the file, field by field, as the store would interpret them
  under the mapping, using the documented encoding and displaying the parsed Arabic text; encoding verification and normalisation
  belong to #247 (AC-3)
- **FR-005**: For a file whose structure permits row interpretation, the validation pass MUST give every data row
  exactly one outcome, create, update or reject, with a
  reason (AC-4)
- **FR-006**: For a file whose structure permits row interpretation, for every data row with a SKU, the pass MUST report the resolved product or variation ID, or no match, and
  whether the resolved record is the source-language original or a translation. Shared-SKU or translation-target
  findings MUST be explicit in the outcome reason. Create, update or reject MUST follow the documented initial-load
  rules; a translated pair alone does not define rejection. This pass performs no identity-repair writes (AC-4, AC-6)
- **FR-007**: The preview and the pass MUST NOT create, change or delete any product, variation, term, media item or
  translation, and a test MUST prove the catalogue unchanged after a run (AC-5)
- **FR-008**: The pass MUST report header differences. If they prevent application of the documented mapping, it MUST
  stop with an explicit file-level failure before row interpretation. Columns explicitly recorded as not imported do
  not alone invalidate the mapping (AC-2, AC-4)
- **FR-009**: The Developer MUST review the validation issues and provide an English actionable list of every issue
  requiring Client correction, regardless of row outcome. Each issue MUST identify the row, SKU where present, field,
  problem and requested correction. Issues resolved by the Developer in the mapping are excluded from the Client's
  correction list (AC-7)
- **FR-010**: Each validation run MUST supply the payload specified by the CX-04 integration dependency. Verification
  MUST distinguish fixture-adapter output from a persisted ENT-19 record. #249 does not implement or accept the ENT-19
  storage model
- **FR-011**: The tool MUST be operated by the Developer only; it offers no storefront surface and no client-facing
  administration screen (AC-2, AC-7)
- **FR-012**: The pass MUST validate the whole agreed export. OD-15's 5,000-product fixture is a provisional
  engineering sizing check, not a contractual migration limit or an unconditional memory or execution-time guarantee
  (AC-4)

### Key Entities

- **Import specification**: the documented mapping of the agreed export's columns to store fields, with its version.
- **Validation report**: one line per row: row number, SKU, outcome, reason, the product the SKU resolves to and its
  language role; with the run's totals.
- **Client issue list**: the reviewed validation issues requiring Client correction, as an actionable list.
- **Import Run** (ENT-19, owned by #273): the record of one developer-operated run, under CX-04.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | A checksum of the catalogue records and their related data before and after each run, excluding Import Run records, printed in the report | Shows whether the catalogue's recorded state changed; does not prove that no intermediate write occurred | Yes, small |

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Saved mapping profiles | MIG-05, DEF |
| Safe re-runs as a delivered capability | MIG-07, DEF |
| Field-level overwrite control | MIG-08, DEF |
| An import history product, browser or reusable self-service re-upload workflow | MIG-18, DEF; CX-04 |
| Undo the last import; scheduled imports | MIG-21, MIG-22, P2 |
| Live Amazon synchronisation; orders and customers | MIG-23, MIG-24, OUT |

## Success Criteria

- **SC-001**: For structurally interpretable fixtures, every data row receives exactly one documented expected outcome
  and reason. Separate malformed-header fixtures produce the expected file-level failure without claiming a completed
  row-validation pass.
- **SC-002**: After any preview or validation run, the catalogue's products, variations, terms, media and
  translations are unchanged, checked by a test.
- **SC-003**: The fixture in the agreed format produces the documented expected interpreted rows.
- **SC-004**: Every reviewed validation issue requiring Client correction appears in the actionable issue list, with
  its affected row and field; issues resolved by the Developer in the mapping are excluded.
- **SC-005**: On the documented staging configuration, the provisional 5,000-product fixture completes validation with
  every data row accounted for. Evidence records product, variation and row counts, elapsed time and peak memory. The
  agreed export remains subject to whole-file validation without a contractual row or product cap.
- **SC-006**: On the real sample export (CR-06), every column is mapped or recorded as not imported, and every row has
  an outcome.

## Delivery stage

Contractual stage: S1. Engineering timing: built now against a fixture in the documented export format, under the
owner's restart of 29 October 2026 (D-20 row D8); finalised after CR-06 and CR-07 are received and the real-file validation is reviewed. Contractual acceptance remains
subject to CX-04 client confirmation and the applicable Acceptance and UAT Plan conditions. Workflow completion,
technical verification and contractual acceptance are reported separately.

## Assumptions

- The fixture follows the documented column layout of Amazon's seller inventory export (the Category Listings Report
  where CR-07 confirms it); its rows are invented and carry no client data.
- The store's source language is English (FIX-04, the default language); where a row carries Arabic text, the mapping
  assigns it to the Arabic translation, and the translation itself is created by the load that #247 owns, not by this
  pass. How the real export carries the two languages is confirmed on the real file (CR-06).
- The Developer runs the tool from the command line on the staging server or the development runtime; there is no
  web interface.
- Required fields and rejection rules are documented by row type and intended operation, consistently with the
  initial-load behaviour. A field is not mandatory merely because it appears in this fixture. Encoding verification and
  normalisation, variation conversion, and price or cost loading remain #247's responsibilities; taxonomy and SEO
  migration remain #295's.

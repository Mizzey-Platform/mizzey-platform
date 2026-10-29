# Implementation Plan: catalogue import specification and validation pass

**Branch**: `006-catalogue-import-spec` | **Date**: 2026-10-29 | **Spec**: [spec.md](spec.md)

## Summary

A Developer-operated WP-CLI tool in the site plugin that reads the one agreed export, applies the documented mapping
and initial-load rules, and reports per row what the live load would do, writing nothing to the catalogue. It previews
the first rows, runs a whole-file validation pass, resolves every SKU to a product or variation id with its language
role, and writes a Client issue list for the Developer to review. The import specification itself is data (the mapping
and the rules), versioned and documented. Built against a fixture now; the real export (CR-06) finalises the mapping.

## Technical Context

| | |
|---|---|
| Language | PHP 8.3, strict types |
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0 (`stack.lock.json`) |
| Interface | WP-CLI only: `wp mizzey catalogue preview`, `wp mizzey catalogue validate` |
| Storage | None of its own. Reads products, variations and WPML language details. Writes three files to a directory the Developer names |
| Dependencies | None added. PHP `zip` and `xmlreader` for `.xlsx` (present locally and in the staging image) |
| Testing | Integration scenarios in `mizzey-site/tests/integration/` (`run.py`), against the disposable runtime only |
| Scale | The 5,000-product fixture is a provisional sizing check (OD-15), not a limit |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced, wording respected | Yes | Five rows, MIG-01, 03, 04, 06, 19. Every component serves "the Developer previews, validates, maps and reports"; no screen, no saved profile, no history browser |
| M-2 contradictions | Cited, not resolved | CX-04 (resolved by the owner, D-12): the Import Run is a dependency on #273, `pending CX-04` |
| M-3 P1-E waits for PRE-09 | Not touched | No stock figure is validated or loaded here (MIG-14 is P1-E and not traced) |
| M-4 native first | Yes | The native importer's dry-run gap is located precisely (research R-2: parsing writes placeholders and terms), so the interpretation is custom and read-only; reading uses PHP's own CSV reader and the `zip` and `xmlreader` extensions, no library |
| M-5 three lists | Yes | Criteria, one optional safeguard (S-1, not built without approval), future items, kept apart in the spec |
| M-6 contractual stage fixed | Yes | S1, unchanged |
| M-7 evidence and tests | Yes | Every criterion has an integration scenario, except AC-8, which is the real file (CR-06) |
| M-8 versions pinned | Yes | Evidence on the pinned stack only |
| M-9 languages | Yes | The reports are English (ADM-159); Arabic content is shown as parsed, never translated by the tool |
| M-10 inputs classified | Yes | CR-06 and CR-07 gate acceptance only; a fixture stands in for development (D-10) |
| CoreX client-site mode | Yes | Only `mizzey-site/` and `specs/` change; no CoreX file |

No violation, so no complexity is tracked.

## Design

### Components, `mizzey-site/src/Catalogue/Import/`

| Class | Responsibility | Writes? |
|---|---|---|
| `ExportReader` | Opens the agreed file by the mapping's format (`csv`, `tsv`, `xlsx`), strips a byte-order mark, yields the header and the data rows | No |
| `Mapping` | Loads and validates `mizzey-site/import/catalogue-mapping.json` (the import specification): version, format, the column map, the rules version | No |
| `HeaderCheck` | Compares the file's header with the mapping; a missing mapped column is a file-level failure (FR-008) | No |
| `RowInterpreter` | Applies the mapping to one raw row: the store field values, trimmed and typed, the row type (simple, variable, variation) | No |
| `SkuIndex` | One batched read of every product and variation with a SKU in the file, with WPML's language details per id (research R-4) | No |
| `LoadRules` | Turns an interpreted row and its SKU resolution into create, update or reject with a rule code and reason (research R-5) | No |
| `ValidationPass` | Runs the above over the whole file; collects the report lines, the issues and the totals | No |
| `CatalogueWriteGuard` | While a run is active, refuses any catalogue write (product, variation, product meta, product term, attachment) by throwing, so an accidental write stops the run loudly | No |
| `ReportWriter` | Writes `validation-report.csv`, `client-issues.csv` (UTF-8 with a byte-order mark) and `run.json` | Files only |
| `ImportRunRecorder` (interface) and `FileImportRunRecorder` | The CX-04 payload; the file implementation writes `run.json` marked `fixture-adapter` | Files only |
| `Cli\CatalogueCommand` | `preview <file> [--rows=<n>]`, `validate <file> --out=<dir>`; registered only under WP-CLI | No |

The service provider registers the command when `WP_CLI` is defined. No hook runs on a web request.

### The import specification as data

`mizzey-site/import/catalogue-mapping.json` holds the mapping and the rules version; `contracts/mapping-schema.md`
documents its shape and `contracts/initial-load-rules.md` the rules. For the fixture it maps the illustrative headers;
for the real file the Developer edits only this file (and the rules, if the real data needs it), bumps the version and
re-runs. Not a saved profile (MIG-05): one mapping, for the initial migration.

### Proving nothing is written (AC-5)

Two layers: the write guard throws on any catalogue write during a run, and the scenario compares the catalogue before
and after (counts of products, variations, product terms and attachments, and a checksum of the product rows and their
meta). The Import Run file is outside the catalogue.

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1 | `t30-catalogue-read` | The fixture in the agreed format (tab-delimited) is read row for row; the same data as `.xlsx` and `.csv` reads identically (a regression guard, not an acceptance condition) |
| AC-2 | `t31-catalogue-mapping` | Every fixture header is mapped or recorded as not imported with a reason; a mapping that misses a header fails validation |
| AC-3 | `t32-catalogue-preview` | The preview prints the first rows field by field under the mapping, Arabic intact |
| AC-4 | `t33-catalogue-outcomes` | Every data row gets one outcome, and each seeded case its expected rule code: create, update, missing required field, repeated source SKU, translation-only SKU, ambiguous SKU, unresolvable variation parent; the header-mismatch fixture fails at file level |
| AC-5 | `t34-catalogue-no-write` | Catalogue counts and checksum unchanged after preview and validate; the guard throws if a write is attempted |
| AC-6 | `t35-catalogue-sku-resolution` | For a seeded WPML duplicate pair, the report gives the original's id, marks it the source-language original and lists the translation |
| AC-7 | `t36-catalogue-issue-list` | Every `client` issue appears in `client-issues.csv` with row, SKU, field, problem and correction, whatever the row's outcome; `developer` issues do not |
| AC-8 | Manual, on CR-06 | The real file: every column mapped or not imported, every row an outcome, the report reviewed (`provisional`) |
| CX-04 | `t37-catalogue-run-record` | `run.json` carries the CX-04 payload and says `fixture-adapter` |
| FR-012 | `t38-catalogue-volume` (fact-finding) | A generated 5,000-product file: rows accounted for, elapsed time and peak memory recorded |

## Project Structure

```text
mizzey-site/
  import/catalogue-mapping.json
  src/Catalogue/Import/{ExportReader,Mapping,HeaderCheck,RowInterpreter,SkuIndex,LoadRules,ValidationPass,
                        CatalogueWriteGuard,ReportWriter,ImportRunRecorder,FileImportRunRecorder}.php
  src/Catalogue/Import/Cli/CatalogueCommand.php
  tests/integration/t30-catalogue-read.php ... t38-catalogue-volume.php
  tests/integration/fixtures/catalogue/{export.tsv,header-mismatch.tsv,README.md}
specs/006-catalogue-import-spec/{plan.md,research.md,data-model.md,quickstart.md,contracts/}
```

## Risks

| Risk | Mitigation |
|---|---|
| The real export's columns differ from the fixture | Header-driven mapping; only the mapping data changes (R-1) |
| The real export is `.xls` or a report that lacks descriptions or variations (CR-07) | Surfaced in the first preview; a different report or format is agreed, the tool does not grow a library |
| A future WooCommerce changes the SKU storage | `SkuIndex` reads through one query in one class; the scenarios fail loudly |
| The integration runtime is shared with other sessions | Scenarios run in their own processes and clean up their seeded products |

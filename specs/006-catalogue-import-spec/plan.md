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
| Dependencies | None added. The tab-delimited path uses PHP's own CSV reader; an `.xlsx` reader is deferred until the agreed export needs it (research R-3) |
| Testing | Integration scenarios in `mizzey-site/tests/integration/` (`run.py`), against the disposable runtime only |
| Scale | The 5,000-product fixture is a provisional sizing check (OD-15), not a limit |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced, wording respected | Yes | Five rows, MIG-01, 03, 04, 06, 19. Every component serves "the Developer previews, validates, maps and reports"; no screen, no saved profile, no history browser |
| M-2 contradictions | Cited, not resolved | CX-04 (resolved by the owner, D-12): the Import Run is a dependency on #273, `pending CX-04` |
| M-3 P1-E waits for PRE-09 | Not touched | No stock figure is validated or loaded here (MIG-14 is P1-E and not traced) |
| M-4 native first | Yes | The native importer's dry-run gap is located precisely (research R-2: parsing writes placeholders and terms), so the interpretation is custom and read-only; reading uses PHP's own CSV reader, no library |
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
| `ExportReader` | Opens the agreed file by the mapping's delimited format (`tsv`, or `csv` where needed), strips a byte-order mark, yields the header and the data rows; another format is added only when agreed (research R-3) | No |
| `Mapping` | Loads and validates `mizzey-site/import/catalogue-mapping.json` (the import specification): version, format, the column map, the rules version | No |
| `HeaderCheck` | Compares the file's header with the mapping; a missing mapped column is a file-level failure (FR-008) | No |
| `RowInterpreter` | Preserves source SKU and parent-SKU values unchanged; interprets other fields using explicit mapping and loader rules, including decimal and encoding rules. Unknown row types and malformed values receive documented outcomes rather than silent coercion | No |
| `SkuIndex` | One batched read of every product and variation with a SKU in the file, with WPML's language details per id (research R-4) | No |
| `LoadRules` | Turns an interpreted row and its SKU resolution into create, update or reject with a rule code and reason (research R-5) | No |
| `ValidationPass` | Runs the above over the whole file; collects the report lines, the issues and the totals | No |
| `CatalogueWriteGuard` | Installed around command execution and removed in `finally`. Pre-write hooks give descriptive failures; a command-local `query` filter (it runs before execution, `class-wpdb.php` line 2214) also rejects mutations to protected catalogue storage: product and attachment records and their metadata, product terms and relationships, WooCommerce product lookup rows, and WPML catalogue translation relationships. High-level hooks alone do not cover direct SQL. Ordinary object-cache operations and identified read-side cache or transient persistence and expiry are permitted, including WPML `_icl_cache`; this does not permit catalogue repair or arbitrary settings changes. ENT-19 persistence stays behind its separate adapter and never whitelists a shared catalogue table wholesale. Bootstrap and shutdown behaviour are checked separately by command-level tests | No |
| `ReportWriter` | Writes `validation-report.csv`, `client-issues.csv` (UTF-8 with a byte-order mark) and `run.json` | Files only |
| `ImportRunRecorder` (interface) and `FileImportRunRecorder` | The CX-04 payload; the file implementation writes `run.json` marked `fixture-adapter` | Files only |
| `Cli\CatalogueCommand` | `preview <file> [--rows=<n>]`, `validate <file> --out=<dir>`; registered only under WP-CLI | No |

The service provider registers the command when `WP_CLI` is defined. No hook runs on a web request.

### The import specification as data

`mizzey-site/import/catalogue-mapping.json` holds the mapping and the rules version; `data-model.md`
documents its shape and `contracts/initial-load-rules.md` the rules. For the fixture it maps the illustrative headers;
for the real file the Developer edits only this file (and the rules, if the real data needs it), bumps the version and
re-runs. Not a saved profile (MIG-05): one mapping, for the initial migration.

### Proving nothing is written (AC-5)

Two layers: the write guard stops any protected catalogue mutation during a run (exit 3, before the mutation), and the
scenario runs both real commands in separate processes and compares the protected records before and after (t34). The
Import Run file is outside the catalogue.

## Criterion to test map

| AC | Scenario | What it asserts |
|---|---|---|
| AC-1 | `t30-catalogue-read` | The fixture in the agreed format is read row for row, preserving identifiers and parsed text. Another format's cases are added when that format is selected |
| AC-2 | `t31-catalogue-mapping` | Every agreed header is mapped or explicitly ignored with a reason. Missing mapped columns cause F1. Unlisted extra columns produce W2 and prevent claiming mapping completeness until mapped or explicitly ignored. Duplicate headers and inconsistent row widths are tested |
| AC-3 | `t32-catalogue-preview` | The preview prints the first rows field by field under the mapping, Arabic intact. Also asserts the mapping version, the declared encoding, the row limit and explicit header differences |
| AC-4 | `t33-catalogue-outcomes` | Every data row gets one outcome, and each seeded case its expected rule code: create, update, missing required field, repeated source SKU, translation-only SKU, ambiguous SKU, unresolvable variation parent; the header-mismatch fixture fails at file level. Also covers partial updates, multiple simultaneous validation issues, duplicate-row order independence, later, rejected and wrong-type parents, unknown row types, empty files and header-only files |
| AC-5 | `t34-catalogue-no-write` | Both actual WP-CLI commands run in separate processes; protected records are compared before and after, including term values and relationships, term metadata, attachment metadata, product lookup rows and WPML translation relationships. Representative API and direct-SQL mutations are exercised and exit 3 is asserted before mutation. Cold-cache reads and permitted transient and cache housekeeping succeed. Failure paths are checked as well as completed runs |
| AC-6 | `t35-catalogue-sku-resolution` | Products and variations: duplicate and separately translated pairs, unmatched SKU, unrelated duplicate identities, missing language details, missing group original, an original with a different SKU, trash, and case and whitespace candidates. Asserts ids, language roles, related candidates and loader-consistent outcomes |
| AC-7 | `t36-catalogue-issue-list` | Every `client` issue appears in `client-issues.csv` with row, SKU, field, problem and correction, whatever the row's outcome; `developer` issues do not. Includes a Client issue on a non-rejected row; the Developer review step is verified separately, since a generated CSV alone is not evidence that review occurred |
| AC-8 | Manual, on CR-06 | The real file: every column mapped or not imported, every row an outcome, the report reviewed (`provisional`) |
| CX-04 | `t37-catalogue-run-record` | The complete CX-04 payload: mapping-used reference, versions, counts, outcome, actor, timestamps and report or error reference; fixture output is distinguished from persistence, and a file-level failure is covered |
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
| The real export is a workbook, or a report that lacks descriptions or variations (CR-07) | Surfaced in the first preview; a different report or format is agreed, or a reader is assessed then (research R-3) |
| A future WooCommerce changes the SKU storage | `SkuIndex` reads through one query in one class; the scenarios fail loudly |
| The integration runtime is shared with other sessions | Scenarios run in their own processes and clean up their seeded products |

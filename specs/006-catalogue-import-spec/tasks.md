# Tasks: catalogue import specification and validation pass

Feature `006-catalogue-import-spec`, PBI #249, MIG-01, MIG-03, MIG-04, MIG-06, MIG-19. From `plan.md`. Every task
names its test (M-7). Task-to-issue generation is disabled: these are not GitHub issues.

Integration scenarios run on the disposable runtime only, serially, and never while another session is using it.

## Phase 1: Fixtures and the import specification as data

- [x] T001 Write `mizzey-site/tests/integration/fixtures/catalogue/README.md`: the fixture is invented, its headers are
      illustrative of an Amazon seller listings report, and the real export (CR-06) replaces them. Test: none; a record.
- [x] T002 Write `fixtures/catalogue/export.tsv`: simple, variable and variation rows across the four client categories
      (Beauty, Health and household, Sport, Vitamins), Arabic text, and one seeded row per rule code and warning in
      `contracts/initial-load-rules.md`, including a parent that appears after its variations. Test: t30, t33.
- [x] T003 Write the file-level fixtures: `header-mismatch.tsv`, `duplicate-headers.tsv`, `ragged-rows.tsv`,
      `empty.tsv`, `header-only.tsv`. Test: t31, t33.
- [x] T004 Write `mizzey-site/import/catalogue-mapping.json` for the fixture: `version`, `rules_version`, `source`,
      every header mapped or ignored with a reason, `row_type`, `language` (data-model.md). Test: t31.

## Phase 2: Failing scenarios first

- [x] T005 [US1] Write `t30-catalogue-read.php`: the agreed format read row for row, identifiers and parsed text
      preserved, a byte-order mark stripped. Test: itself (AC-1).
- [x] T006 [US1] Write `t31-catalogue-mapping.php`: every agreed header mapped or ignored with a reason; F1 on a missing
      mapped column; W2 on an unlisted column and no completeness claim; duplicate headers; inconsistent row widths;
      mapping validation (version, format, delimiter, encoding, unique headers, rules version). Test: itself (AC-2).
- [x] T007 [US1] Write `t32-catalogue-preview.php`: the first rows field by field, Arabic intact, the mapping version,
      the declared encoding, the row limit and explicit header differences. Test: itself (AC-3).
- [x] T008 [US2] Write `t33-catalogue-outcomes.php`: one deterministic outcome per row with every applicable issue
      retained; each seeded rule code; partial updates; several issues at once; duplicate-row order independence;
      later, rejected and wrong-type parents; unknown row types; empty and header-only files. Assert the completed
      command's `validation-report.csv` contains the expected row outcomes, reasons and applicable issues. Test: itself
      (AC-4, FR-005).
- [x] T009 [US2] Write `t34-catalogue-no-write.php`: both real commands in separate processes; protected records
      compared before and after (posts, meta, terms and relationships, term meta, attachment meta, product lookup rows,
      WPML translation relationships); representative API and direct-SQL mutations stopped with exit 3 before they
      happen; cold-cache reads and permitted cache housekeeping succeed; failure paths included. Test: itself (AC-5).
- [x] T010 [US2] Write `t35-catalogue-sku-resolution.php`: products and variations; duplicate and separately
      translated pairs; unmatched SKU; unrelated duplicate identities; missing language details; missing group
      original; an original with a different SKU; trash; case and whitespace candidates. Assert `validation-report.csv`
      contains the expected resolved ids, language roles, translations and near-match candidate ids, stored SKUs and
      comparison rules. Test: itself (AC-6, FR-006).
- [x] T011 [US3] Write `t36-catalogue-issue-list.php`: every `client` issue in `client-issues.csv` with row, SKU,
      field, problem and correction, including one on a non-rejected row; no `developer` issue; UTF-8 with a
      byte-order mark. Test: itself (AC-7).
- [x] T012 Write `t37-catalogue-run-record.php`: the complete CX-04 payload, the fixture-adapter marking, and a
      file-level failure recorded without invented row outcomes. Exercise guard and output-write failures and assert
      that neither leaves a run marked completed. Test: itself (FR-010, CX-04).
- [x] T013 Run t30 to t37 before any production code and keep the output as `evidence/before.txt`. Each must fail.
      **Not done as written:** the components were written before the scenarios. In its place, three deliberate
      defects were each shown to fail the scenario that guards them, and the code was restored
      (`evidence/mutation.txt`).

## Phase 3: Reading and mapping

- [x] T014 [US1] Create `src/Catalogue/Import/ExportReader.php`: the delimited reader, byte-order mark, documented
      encoding, bounded reading (research R-3). Test: t30.
- [x] T015 [US1] Create `Mapping.php`: load and validate the import specification (data-model.md). Test: t31.
- [x] T016 [US1] Create `HeaderCheck.php`: F1, F2, F3, W2 (FR-008); row widths are judged per row (R8, `RowInterpreter`). Test: t31, t33.
- [x] T017 [US1] Create `RowInterpreter.php`: SKU and parent SKU preserved unchanged; other fields by explicit mapping
      and loader rules; unknown row types and malformed values get documented outcomes. Test: t32, t33.

## Phase 4: Resolution, rules and the pass

- [x] T018 [US2] Create `SkuIndex.php`: batched prepared queries on `wc_product_meta_lookup` joined to posts, trash
      excluded, no `LIMIT 1`; byte-identical, collation-equivalent and trimming-only candidates kept apart; WPML
      details by element type with full-group resolution (research R-4). Test: t35.
- [x] T019 [US2] Create `LoadRules.php`: file-level checks first, rejections before C1 or U1, separate create and update
      requirements, parent resolution irrespective of row order, R2 without first-row-wins (contracts). Test: t33.
- [x] T020 [US2] Create `ValidationPass.php`: index the file's parent candidates, then finalise outcomes, totals and
      issues. Test: t33, t35.
- [x] T021 [US2] Create `CatalogueWriteGuard.php`: hooks plus the command-local `query` filter over protected storage,
      the permitted housekeeping list, removal in `finally` (plan.md). Test: t34.

## Phase 5: Reports, record and command

- [x] T022 [US2] [US3] Create `ReportWriter.php`: `validation-report.csv` with near-match evidence, `client-issues.csv`,
      UTF-8 with a byte-order mark (research R-8). Test: t33, t35, t36.
- [x] T023 Create `ImportRunRecorder.php` and `FileImportRunRecorder.php`: the CX-04 payload in `run.json`, a guard or
      output failure never marked completed (research R-6). Test: t37.
- [x] T024 [US1] [US2] Create `Cli/CatalogueCommand.php` and register it in `mizzey-site.php` only when `WP_CLI` is
      defined (contracts/cli.md, exits 0 to 3). Test: t32, t34, t37; the FR-011 registration and surface review in T026
      verifies CLI-only registration and no administration screen. The unchanged t27 is storefront regression evidence
      only.

## Phase 6: Scale and verification

- [x] T025 Write `t38-catalogue-volume.php`: a generated 5,000-product file, rows accounted for, elapsed time and peak
      memory recorded as facts (FR-012, OD-15 provisional). Test: itself.
- [ ] T026 Run the guards on the diff (wp-guard, woo-guard, test-guard, clean-code-guard, docs-guard as relevant).
      Record the named FR-003 single-mapping and no-profile review and the FR-011 CLI-only registration and no-screen
      review, with source references. Verification: the guard results and both boundary reviews in `analysis.md`.
- [ ] T027 Reset the runtime to the clean baseline and run the whole suite serially; commit `evidence/clean-baseline.txt`,
      `evidence/final-suite.txt` and `evidence/final-suite.json`. Test: the suite, t02 to t29 verdicts unchanged.
- [ ] T028 Write `verification.md`, reporting technical verification, staging and client acceptance separately.
      Test: `test_spec_consistency.py` and the scope trace.

## Not done until outside inputs arrive

- [ ] T029 [US4] AC-8 on the real sample export: preview, finalise the mapping, validate, Developer review, send the
      Client list. **Waits for CR-06 and CR-07.** If the agreed export is a workbook, assess a reader first (research
      R-3) and add its cases to t30. Test: named manual check `AC-8 real-file validation`, recorded in
      `verification.md`, referencing the mapping used, the complete row report, the Developer review and the Client
      issue list.
- [ ] T030 Manual testing on staging (DOD-04). Test: named manual check `DOD-04 staging verification`, recorded in
      `verification.md` with the release, environment, operator, checks and results; scripted evidence is labelled
      separately.
- [ ] T031 Client approval through MS-UAT-2026-027 (DOD-09). Verification: the referenced client acceptance record
      in `verification.md`; CX-04 client confirmation is recorded separately, and AC-3 stays `pending CX-04` until that
      confirmation exists.

## Not tasks

- The optional safeguard S-1: not approved, not built.
- A saved mapping profile, a history screen or an administration screen (MIG-05 and the future items in the spec).
- Persisting the Import Run: #273 (ENT-19).

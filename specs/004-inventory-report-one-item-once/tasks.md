# Tasks: the inventory report counts one physical item once

Feature `004-inventory-report-one-item-once`, PBI #246, RPT-10. From `plan.md`. Every task names its test (M-7).
Task-to-issue generation is disabled: these are not GitHub issues.

## Phase 1: Evidence before code

- [x] T001 Audit the native stock-reporting surfaces in three language contexts and record the measurements in
      `research.md` R-2 and R-9. Test: the audit's own output, reproduced by t24 run before the correction.
- [x] T002 Establish how the report and its export query, and what identifies that query, in `research.md` R-1
      and R-5. Test: t24's export assertions and its ordinary-query assertion.
- [x] T003 Compare the September P-020 measurement with the current one and record what is and is not
      established, in `research.md` R-3. Test: none; a research record.

## Phase 2: The failing scenario

- [x] T004 [US1] [US2] Write `mizzey-site/tests/integration/t24-stock-report-physical-items.php`: six physical
      items as fixtures, the low and out-of-stock lists in English, Arabic and all languages, quantities, totals,
      a two-per-page walk, four sort orders, WooCommerce's own exporter in two contexts, stored stock before and
      after, and an ordinary product query. Test: itself.
- [x] T005 Run t24 before the correction and keep the output as `evidence/t24-before.txt`. It must fail on the
      omission, the duplication and the export. Test: the recorded failure.

## Phase 3: The correction

- [x] T006 [US1] [US2] Create `mizzey-site/src/Reporting/PhysicalItems.php`: the representative condition and the
      language-scope pair (FR-001, FR-002, FR-004, FR-009). Test: t24, AC-246-01 to AC-246-03, AC-246-05,
      AC-246-07.
- [x] T007 [US1] Create `mizzey-site/src/Reporting/StockReport.php`: identify the report's query, widen the
      scope at `parse_query`, add the condition at `posts_clauses`, restore at `posts_request` (FR-003, FR-005,
      FR-006). Test: t24, AC-246-04, AC-246-06, AC-246-08, and the session-language assertion.
- [x] T008 Register the hook in `mizzey-site/mizzey-site.php`. Test: t24 passes.

## Phase 4: Scale and non-interference

- [x] T009 Write `mizzey-site/tests/integration/t25-stock-report-volume.php`: 5,000 physical items as 10,000
      records, the total, a page, the last page, the elapsed time and the query plan as facts. Test: itself.
- [x] T010 Confirm FR-010: no stored stock value changes and no other product query is touched. Test: the two
      FR-010 assertions in t24, and the unchanged verdicts of t02 to t23 in the full suite.

## Phase 5: Verification and records

- [x] T011 Run the guards on the diff: wp-guard, woo-guard, test-guard, clean-code-guard, docs-guard, as relevant.
      Test: `analysis.md`.
- [x] T012 Reset the runtime to the clean baseline and run the whole suite serially; commit
      `evidence/clean-baseline.txt`, `evidence/final-suite.txt` and `evidence/final-suite.json`. Test: the suite.
- [x] T013 Write `verification.md`, reporting the three states separately. Test: `test_spec_consistency.py` and
      the scope trace.
- [ ] T014 Manual testing on staging (DOD-04). **Run on 5 October 2026 as a scripted pass in a real browser, and
      it found three faults**, corrected in Phase 6. A person's own pass is still owed.
- [ ] T015 Client approval of the acceptance criteria (DOD-09), through MS-UAT-2026-027. **Not done.**
- [ ] T016 AC-246-11, the figures the ERP supplies. **Not done: `pending PRE-09`.**

## Phase 6: Repair after the staging finding, 5 October 2026

Staging disproved AC-246-03 and AC-246-04 in the signed-in browser session, and showed a summary that counted
language records. Board status went from Verified to In progress before any code changed.

- [x] T017 Reproduce through the real request before touching production code: write
      `mizzey-site/tests/integration/t26-stock-report-real-request.php` and its front-end probe fixture, reading
      the report over HTTP, signed in, in the English, Arabic and all-languages admin contexts. Test: it fails on
      the code of `main`, with t24 passing beside it (`evidence/repair/t26-before.txt`).
- [x] T018 Identify the cause by tracing one such request. Test: `evidence/repair/root-cause-trace.txt`,
      `research.md` R-11.
- [x] T019 [US1] Replace the session-language switch in `PhysicalItems` with the multilingual plugin's per-query
      switch, and drop the restore step from `StockReport` (FR-012). Test: t26, AC-246-01 to AC-246-08.
- [x] T020 [US1] Make each figure of the summary under the table the total of its list (FR-013). Test: t26, the
      summary assertion in each context.
- [x] T021 Re-run the sizing baseline, in process and over HTTP, with the summary. Test: t25.
- [x] T022 Correct the claim in t24's header that the three in-process contexts are the whole input.
- [x] T023 Run the guards on the diff, reset the runtime to the clean baseline and run the whole suite serially;
      commit `evidence/repair/`. Test: the suite.
- [ ] T024 After merge: rebuild staging from the new `main`, repeat the staging pass through the signed-in
      browser, and only then return the board status to Verified.

## Not tasks, by decision D-246-1

- The dashboard low-stock and out-of-stock counts: ADM-10 and ADM-11, for their own PBI. The measurement is in
  `research.md` R-9.
- The older stock report screen: no row. The finding is in `research.md` R-9.
- The optional safeguard S-1: not approved, not built.

# Tasks: Product cost captured from day one

**Input**: `specs/001-product-cost-capture/` (spec, plan, research, data-model, contracts, quickstart)

**Tests**: REQUIRED (constitution M-7). In this feature the tests are the deliverable, because the behaviour is
native. Every task names its test and criterion.

**Scope guard**: no `mizzey-site/src/` task exists. T020 is the only contingent change, and it runs only if
t05 or t06 fails.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [x] T001 Confirm the disposable runtime matches `stack.lock.json` (WordPress 7.1, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, PHP 8.3.6; languages en and ar) and record the observed versions in `specs/001-product-cost-capture/verification.md`
- [x] T002 Create the runner `mizzey-site/tests/integration/run.py`: runs each `t*.php` with `wp eval-file` against `--wp`, parses one JSON verdict per script, prints a table, exits 1 on any failed contract scenario (verdict `pass: false`)

## Phase 2: Foundational

- [x] T003 Create `mizzey-site/tests/integration/_bootstrap.php`: helpers to record and restore the feature flag, create and delete fixture products, variations, orders and users, and emit a JSON verdict (`test`, `criterion`, `pass`, `observed`)
- [x] T004 [P] Create the import fixture `mizzey-site/tests/integration/fixtures/products-with-cost.csv`: two rows with a "Cost of goods" value and one row with a blank cost

## Phase 3: User Story 1 - Store owner records cost (Priority: High)

**Goal**: AC-1. **Independent test**: t02 and t03 pass.

- [x] T005 [US1] FR-001 enablement check in `mizzey-site/tests/integration/_bootstrap.php` (`enable_cogs()` fails the scenario if the flag will not switch on, and records its prior state). The separate t01 script was removed at the test-guard review (Rule 4: it caught nothing the other scenarios do not)
- [x] T006 [P] [US1] t02 `mizzey-site/tests/integration/t02-simple-cost.php`: a simple product's cost saves and reads back unchanged (AC-1); record how 0 and blank are stored (edge case, fact only)
- [x] T007 [P] [US1] t03 `mizzey-site/tests/integration/t03-variation-cost.php`: two variations hold different costs; record the default additive flag and a variation with no cost (AC-1, edge cases)

## Phase 4: User Story 2 - Historical orders keep the cost (Priority: High)

**Goal**: AC-2 and AC-3. **Independent test**: t04 to t06 pass.

- [x] T008 [US2] t04 `mizzey-site/tests/integration/t04-order-line-snapshot.php`: an order for 2 units at cost 100 records 200, and still records 200 after the product cost changes to 999 (AC-2)
- [x] T009 [P] [US2] Superseded by T022. t05 was replaced: its "separately authored translation" was not how WCML creates one, and it only tested a simulated admin save
- [x] T010 [US2] Superseded by T022. Arabic-language orders are now placed inside every matrix case (AC-3)

## Phase 5: User Story 3 - Cost arrives with the catalogue (Priority: Medium)

**Goal**: AC-4. **Independent test**: t07 passes.

- [x] T011 [US3] t07 `mizzey-site/tests/integration/t07-csv-import-cost.php`: the native CSV importer maps "Cost of goods" to cost for each fixture row, and the blank row imports without a cost, not as 0 (AC-4)

## Phase 6: User Story 4 - Customers never see cost (Priority: High)

**Goal**: AC-5, plus facts for AC-6. **Independent test**: t08 passes.

- [x] T012 [US4] t08 `mizzey-site/tests/integration/t08-no-public-exposure.php`: using a unique cost value, check Store API product and list responses as a visitor, REST v3 products and variations as a visitor and a customer (status and body), the rendered product page HTML, and the customer's own order in My Account (order emails are not sent locally; recorded as not tested); the value appears nowhere (AC-5, contracts/cost-exposure.md)
- [x] T013 [US4] t09 `mizzey-site/tests/integration/t09-staff-visibility.php`: for each standard role (administrator, shop_manager, editor, author, contributor, customer), record whether it can edit products and read cost through REST v3. Report `pass: null`, facts only (AC-6, pending CX-01)

## Phase 7: Verification and records

- [x] T014 Run `python mizzey-site/tests/integration/run.py --wp ../app/wp` and save the full output and the verdict table in `specs/001-product-cost-capture/verification.md`
- [x] T015 Confirm the runtime is left as found: feature flag restored, no fixture products, orders or users left (checked in the verification record)
- [x] T016 [P] Run test-guard on `mizzey-site/tests/integration/` and record the result in `specs/001-product-cost-capture/verification.md`
- [x] T017 Run the repository checks (`discovery.check`, both unit suites, `tools/repo_checks.py`, `tools/run_trusted.py` with the PR body) before opening the PR
- [ ] T018 Report the three states separately in the PR: workflow complete, technically verified (AC-1 to AC-5), contractually accepted (no: AC-6 pending CX-01, AC-7 pending OD-12)
- [x] T019 Record the new facts for later governance PRs, without changing governance files here: MySQL 8.3.0 for `stack.lock.json`, and any version or behaviour finding for ADR-0001

## Corrective investigation (authorised 22 September 2026, after the first pilot report)

- [x] T022 [US2] Replace t05, t06 and t10 with `mizzey-site/tests/integration/t11-cost-sync-matrix.php`: 2 translation methods (WPML duplicate, WCML translation editor) x 2 product types (simple, variation) x 5 channels (admin-http, import-http, rest-http, crud-cli, crud-web), each in its real request context, plus 4 creation cases; every case also checks Arabic and English orders and that price, stock, SKU and status are untouched (AC-3)
- [x] T023 Script a clean, repeatable runtime baseline in `mizzey-site/tests/integration/baseline/` (destructive, disposable runtime only, guarded by MIZZEY_CONFIRM_RESET), including the wp-admin visit that makes WPML parse plugin config files
- [x] T024 Compare the same matrix with and without `wpml-config.xml` on fresh baselines; record both runs in `evidence/`
- [x] T025 [US2] Implement the minimum correction, `MizzeySite\Catalogue\CostTranslationSync`, answering the design questions in verification.md before writing it; re-run the matrix (24 of 24 pass)
- [x] T026 Re-run the full suite on a clean baseline, confirm the runtime is left as found, and run woo-guard on the new class (two findings, both fixed)

## Contingent (only if t05 or t06 fails): triggered, see T020

- [x] T020 [US2] Tried and rejected. `mizzey-site/wpml-config.xml` was added, tested on a clean baseline with and without it (identical results in all 24 cases), and removed. Configuration alone does not close the gap (verification.md, WPML-2)

- [x] T021 [US2] Superseded by T022. t10 became the crud-cli, crud-web and rest-http columns of the matrix

## Review round (requested 22 September 2026, before merge)

- [x] T027 [US2] t12 `mizzey-site/tests/integration/t12-cost-sync-identity.php`: the original, the translation
  relationship, simple products, variation to matching variation, a missing translation, a deleted translation, and
  a cost written on the translation (AC-3)
- [x] T028 [US2] t13 `mizzey-site/tests/integration/t13-cost-sync-semantics.php`: first cost, increase, decrease,
  no change, repeated saves, clearing, zero, the number of writes each causes, a forced failure with its log and
  its repair, and re-entrancy (AC-3)
- [x] T029 [US2] t14 `mizzey-site/tests/integration/t14-arabic-content-after-sync.php`: every authored field, the
  translation relationship, an unrelated custom field, and what an Arabic customer reads, before and after (AC-3,
  AC-5)
- [x] T030 [US2] t15 `mizzey-site/tests/integration/t15-cost-sync-entrypoints.php`: the hook that carries the copy
  in each of the five channels, recorded inside the request that does the work, for simple products and variations
  (AC-3)
- [x] T031 [US2] Correct what T027 to T030 found: identity resolved from the WPML translation rows rather than the
  separately cached `wpml_original_element_id`, and the storage key corrected to `_cogs_total_value` in the spec,
  plan, research and data model (verification.md, review round)

## Second review round (requested 22 September 2026, before merge)

- [x] T032 [US2] Establish what each supported channel does today when a cost is written directly onto a
  translation (REST, the importer, WooCommerce CRUD, wp-admin, a front-end request), and what wp-admin and WPML
  already do about it, before choosing a mechanism (research R-8, verification.md)
- [x] T033 [US2] Implement the smallest rule consistent with the original owning the cost:
  `CostTranslationSync::keepOriginalCost()` and `keepOriginalAdditiveFlag()` on WooCommerce's own write filters,
  with the attempt logged. Not bidirectional, no extra save, no other field touched (AC-3)
- [x] T034 [US2] t16 `mizzey-site/tests/integration/t16-translation-cost-ownership.php`: five channels x two
  product types, each with the authoritative English cost, the direct write attempt, the resulting ownership, an
  Arabic order, and everything else on the Arabic post held still (AC-3)
- [x] T035 Correct the Store API exposure check in t14: it looked for a number with a broken pattern and could
  never have failed. It now inspects the decoded response for cost-sensitive fields by name, verifies the product
  id, distinguishes a duplicate from an authored translation, and carries a negative control that proves the check
  reports a planted cost field (AC-5)
- [x] T036 Record the SKU ambiguity T034 exposed: one SKU belongs to both members of a duplicated pair, so an
  import row reaches whichever the lookup returns. t11 and t16 resolve the SKU and assert the outcome that
  follows; spec.md, verification.md and the MIG-13 operational note say what an import can and cannot be relied
  on to do (AC-3, AC-4)

## Dependencies

- T001 to T004 before every story. US1 (T005 to T007) before US2, because the orders need products with cost.
  US3 and US4 depend only on Phase 2.
- T014 needs every scenario script. T020 depends on the T014 result. T031 depends on T027 to T030.
  T033 depends on T032, and T034 on T033. T036 came out of T034.

## Parallel opportunities

- T004 alongside T003. T006 and T007 together. T009 alongside T008. US3 and US4 alongside US2.

## Implementation strategy

MVP: US1 plus US2 (AC-1 to AC-3), the key rows. Then US3 and US4. No production change. The PR delivers the spec,
the tests, the verification record and the configuration instruction.

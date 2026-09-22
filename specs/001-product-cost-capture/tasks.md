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
- [x] T009 [P] [US2] t05 `mizzey-site/tests/integration/t05-translation-cost.php`: after an English product gets a cost, does the Arabic copy carry it, (a) as a WPML duplicate and (b) as a separately authored linked translation, both at creation and after a later cost edit on the English product (AC-3)
- [x] T010 [US2] t06 `mizzey-site/tests/integration/t06-arabic-order-cost.php`: an order for the Arabic product, placed in the Arabic language context, records a line cost (AC-3)

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

## Contingent (only if t05 or t06 fails): triggered, see verification.md

- [x] T020 [US2] If an Arabic copy or an Arabic order lacks cost: add `mizzey-site/wpml-config.xml` declaring `_cogs_value` (and `_cogs_value_is_additive`) as copied custom fields, justify it in the spec's Native coverage table, re-run t05 and t06, and run woo-guard and wp-guard on it. If the XML does not fix it, stop and report; do not write PHP

- [x] T021 [US2] t10 `mizzey-site/tests/integration/t10-programmatic-cost-sync.php`: programmatic (CRUD-only) cost edits reach existing translations (AC-3, WPML-1). Added after the T020 diagnosis. Fails until WPML-1 is decided

## Dependencies

- T001 to T004 before every story. US1 (T005 to T007) before US2, because the orders need products with cost.
  US3 and US4 depend only on Phase 2.
- T014 needs every scenario script. T020 depends on the T014 result.

## Parallel opportunities

- T004 alongside T003. T006 and T007 together. T009 alongside T008. US3 and US4 alongside US2.

## Implementation strategy

MVP: US1 plus US2 (AC-1 to AC-3), the key rows. Then US3 and US4. No production change. The PR delivers the spec,
the tests, the verification record and the configuration instruction.

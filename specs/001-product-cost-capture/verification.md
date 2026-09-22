# Verification record: 001-product-cost-capture

**Date**: 2026-09-22. **Branch**: `001-product-cost-capture`, from `main` at `914e537`.
**Runtime**: the disposable `../app/wp` (WAMP, Windows). Never production.

## Versions observed (T001)

| Component | Observed | `stack.lock.json` | Note |
|---|---|---|---|
| WordPress | **7.1.2** | 7.1 | The lock records the minor line only. Record the patch in a governance PR |
| WooCommerce | 11.1.0 | 11.1.0 | |
| WPML | 4.9.7 | 4.9.7 | |
| WCML | 5.5.7 | 5.5.7 | |
| PHP | 8.3.6 | 8.3.6 | |
| Database | **MySQL 8.3.0** | unverified | New fact. Record in a governance PR |
| Languages | en (default), ar | | |
| HPOS | on | | |

`stack.lock.json` is a governance-control path, so these facts are not written into it by this requirement PR (T019).

## Results (T014)

`python mizzey-site/tests/integration/run.py --wp ../app/wp`, final run: `evidence/run-3-after-guard.txt` and
`.json`.

| Scenario | Criterion | Result | Key observation |
|---|---|---|---|
| t02 simple cost | AC-1 | PASS | 137.5 read back. 0 and blank both read back as no cost (native) |
| t03 variation cost | AC-1 | PASS | 40 and 70 kept per variation; additive flag defaults to false; a variation with no cost reads null, effective 0 |
| t04 order-line snapshot | AC-2 | PASS | 200 at sale, 200 after the product cost changed to 999 |
| t05 translation cost | AC-3 | PASS | Duplicate: 120 at creation, follows the edit to 130; variations 40 and 70. Separate translation: null right after linking, 150 once the English product is next saved, then follows the edit to 160 |
| t06 Arabic order cost | AC-3 | PASS | Orders in the Arabic context record 220 (duplicate) and 180 (separate translation) |
| t07 CSV import | AC-4 | PASS | 111.25, 95, 40, 70 imported; the blank row imported with no cost |
| t08 exposure | AC-5 | PASS | No cost in visitor product pages (HTTP 200), Store API (200), REST v3 (401), customer Store API (200) or REST v3 (403), or the My Account order view. Emails not tested |
| t09 staff visibility | AC-6 | FACT | administrator and shop_manager: edit_products yes, REST v3 returns cost. editor, author, contributor, subscriber, customer: no, 403 |
| t10 programmatic sync | AC-3 (WPML-1) | **FAIL** | EN changed to 125 by a CRUD save only; the AR translation stays at 100 |

Every scenario enabled the feature from `off`, and restored it. After the run: flag `no`, 0 products, 0 orders, 0
test users (T015).

## What happened, in order

1. **First run (`evidence/run-1-before-fix.txt`).** t05 and t06 failed. The Arabic copies did not follow cost edits
   made by CRUD save, duplicated variations had no cost, and an order for a separately authored Arabic product
   recorded **0 instead of 180**.
2. **Contingent task T020 triggered.** `mizzey-site/wpml-config.xml` was added, declaring `_cogs_value` and
   `_cogs_value_is_additive` as copied fields. WPML then reported both as copy (1), locked by config. Duplicated
   variations now carried cost. CRUD-only edits still did not propagate.
3. **Diagnosis.** WPML copies "copy" fields in its `save_post` handler. The wp-admin product form writes the
   product and then fires `save_post`, so the copy happens. A WooCommerce CRUD `save()` that changes only meta does
   not fire `save_post` after the meta is written, so nothing is copied. That is how the importer, REST and WP-CLI
   save.
4. **Tests split by path.** t05 and t06 now test the admin save order (a simulation: CRUD save, then
   `save_post`). The new t10 tests the programmatic path and fails.
5. **Control runs.** With the XML removed and the WPML setting at 0, and again with the setting unset, t05 and t06
   still passed in this runtime, and t10 still failed. So the XML is not shown to be necessary here. The runtime may
   keep state from the first time the file was loaded (WPML-2).

## Open items raised by the pilot

| Id | Question | Options | Recommendation |
|---|---|---|---|
| **WPML-1** | Cost changed programmatically after translations exist does not reach them | (a) Process rule: import cost before translations are created, and change cost afterwards only in wp-admin. No code. (b) A small `mizzey-site` hook that calls WPML's public `wpml_sync_all_custom_fields` action after a product save. That is PHP, and needs approval under M-4. (c) Accept the gap | (a) for launch, since the initial migration runs before translation, with (b) held for when bulk cost updates are actually needed. Mustafa's decision |
| **WPML-2** | Is `wpml-config.xml` necessary? | Re-run t05 and t06 on a fresh runtime database without the file | Keep the file for now (declarative, mirrors WCML, locks the setting against accidental "translate"), and settle it on the next fresh runtime. Remove it if unnecessary |
| **CX-01** | Which staff roles hold financial permission (AC-6) | Client, at the Stage 1 review | t09 facts attached. No position taken |
| **OD-12** | Cost basis and who enters it (AC-7) | Client | Also ask whether zero-cost items (for example samples) must be told apart from uncosted ones, since WooCommerce treats 0 as no cost |

## Guard Gate (T016)

- test-guard on `mizzey-site/tests/integration/`: one Rule 4 finding (t01 caught nothing the other scenarios did not).
  Fixed by folding the FR-001 check into `_bootstrap.php` and removing t01. Rule 7 (testing framework behaviour) is
  deliberately waived by the project: native coverage proven by tests, and re-run on upgrades, is the deliverable
  (M-4, M-8). No other findings.
- `wpml-config.xml` is declarative XML with no PHP, so woo-guard and wp-guard have no code to review. The file
  follows WCML's own `wpml-config.xml` pattern.

## Not verified

- A real wp-admin browser session (the admin save order was simulated). This belongs to UAT.
- Order emails.
- Staging and production enablement of the flag (runbook step; optional safeguard S-1).
- Behaviour on WooCommerce versions other than 11.1.0.

## Three states

- **Workflow pilot**: spec, plan, tasks, analysis, implementation (tests and configuration), guard, verification,
  all through Spec Kit. **Complete**, pending PR review.
- **Technically verified**: AC-1, AC-2, AC-4 and AC-5 yes. AC-3 yes for the admin workflow, but open for
  programmatic edits (WPML-1).
- **Contractually accepted**: **no**. AC-6 is pending CX-01 and AC-7 pending OD-12. Acceptance happens at the Stage 1
  gate under MS-UAT-2026-027.

# Verification record: 001-product-cost-capture

**Date**: 22 September 2026. **Branch**: `001-product-cost-capture`, from `main` at `914e537`.
**Runtime**: the disposable `../app/wp` (WAMP, Windows), reset to a scripted clean baseline for each comparison.
Never production.

## Versions observed (T001)

| Component | Observed | `stack.lock.json` | Note |
|---|---|---|---|
| WordPress | **7.1.2** | 7.1 | The lock records the minor line only |
| WooCommerce | 11.1.0 | 11.1.0 | |
| WPML | 4.9.7 | 4.9.7 | |
| WCML | 5.5.7 | 5.5.7 | |
| PHP | 8.3.6 | 8.3.6 | |
| Database | **MySQL 8.3.0** | unverified | New fact |
| Languages | en (default), ar; products and variations translatable | | |
| HPOS | on | | |

`stack.lock.json` is a governance-control path. These two facts are recorded here for a separate
`internal:governance` PR rather than written into the lock by this requirement PR (T019).

## Results (T014)

`python mizzey-site/tests/integration/run.py --wp ../app/wp`, on a clean baseline, with the fix in place:
`evidence/final-suite.txt` and `.json`.

| Scenario | Criterion | Result | Key observation |
|---|---|---|---|
| t02 simple cost | AC-1 | PASS | 137.5 read back; 0 and blank both read back as no cost (native) |
| t03 variation cost | AC-1 | PASS | 40 and 70 kept per variation; additive flag defaults to false |
| t04 order-line snapshot | AC-2 | PASS | 200 at sale, 200 after the product cost changed to 999 |
| t07 CSV import | AC-4 | PASS | 111.25, 95, 40, 70 imported; the blank row imported with no cost |
| t08 exposure | AC-5 | PASS | No cost in visitor pages, Store API, REST v3, or the customer's order view |
| t09 staff visibility | AC-6 | FACT | administrator and shop_manager only; every other role 403 |
| t11 cost sync matrix | AC-3 | PASS | 24 cases: 2 translation methods x 2 product types x 5 channels, plus 4 creation cases |

After the run: feature flag `no`, 0 products, 0 orders, no test users, no temporary must-use plugin, no leftover
import files (T015).

## The WPML investigation

### What was wrong with the first attempt, and how it was corrected

The first pilot report said the Arabic copy failed to carry cost, that a `wpml-config.xml` fixed part of it, and
that removing the file no longer reproduced the failure. Three faults in the test environment and harness explain
that, and each was corrected before any conclusion was drawn:

1. **The runtime had drifted.** It had been used for discovery since 8 September, had run WPML's "Translate
   Everything" mode at some point, and WPML had downloaded a fresh remote configuration index mid-session. A
   scripted clean baseline now exists (`mizzey-site/tests/integration/baseline/`), and every comparison below starts
   from it.
2. **The harness compared products with themselves.** `make_duplicate()` returns `true`, not the new post id, and
   the baseline had not made products translatable, so "the Arabic product" was sometimes the English one. The
   matrix now refuses any case whose Arabic post is not a distinct post registered in Arabic.
3. **Channels ran in the wrong request context.** WCML registers its sync only when `is_admin()` or WP-CLI is
   running, so a REST or front-end save tested from WP-CLI passes for the wrong reason. Each channel now runs in its
   real context: wp-admin over HTTP, the wp-admin importer AJAX endpoint, REST over HTTP with an application
   password, WP-CLI, and a front-end request.

### Root cause

- WooCommerce's product data store only calls `wp_update_post()` when post fields change. A cost-only change
  therefore never fires `save_post` (confirmed by trace: 0 fires).
- WPML and WCML copy custom fields to translations on `save_post`, on the wp-admin variations AJAX save, and
  through WCML's importer hook (`woocommerce_product_import_inserted_product_object`, which calls
  `wpml_sync_all_custom_fields` and is registered for the backend only).
- So wp-admin edits and the wp-admin importer keep the Arabic cost correct, and REST, WP-CLI and any other code
  path leave it stale, with no visible sign. An order for the Arabic product then records cost 0.
- WPML parses plugin `wpml-config.xml` files only on a few admin pages (Plugins, Themes, its own settings), which is
  why the file appeared to do nothing until wp-admin had been visited.

### Was the XML necessary? No

Same clean baseline, same 24 cases, run twice:

| | `_cogs_value` setting | admin-http, import-http | rest-http, crud-cli, crud-web |
|---|---|---|---|
| Without `wpml-config.xml` (`evidence/wpml1-clean-no-xml.txt`) | unset | pass | fail |
| With `wpml-config.xml` (`evidence/wpml1-clean-with-xml.txt`) | 1 (copy), locked by config | pass | fail |

The file changed WPML's settings and changed no outcome. It was removed (WPML-2 closed). WPML's own remote
configuration already marks `_cogs_total_value` as copied; neither it nor WCML declares `_cogs_value`.

### The minimum correction

`mizzey-site/src/Catalogue/CostTranslationSync.php` (81 lines of logic, registered on `plugins_loaded`):

| Question asked before building | Answer |
|---|---|
| Canonical identity | The WPML source-language original, resolved with `wpml_original_element_id`. Translations never push cost back |
| Variation relationships | Each variation's own WPML element (`post_product_variation`) and its translations |
| Events handled | `woocommerce_update_product` and `woocommerce_update_product_variation`, which fire after WooCommerce has written the meta, in every channel |
| Existing APIs used | WPML's `wpml_original_element_id`, `wpml_element_trid`, `wpml_get_element_translations`; WooCommerce CRUD getters and setters, so the product lookup table stays correct |
| Duplicate execution and loops | Compare-then-write (an unchanged cost writes nothing), a re-entrancy guard, and translations are skipped because they are not originals |
| Consistency after translation creation | Creation already carries cost natively (t11 part 1); the hook covers later changes, and repairs drift on the next save |
| Missing and zero costs | Copied as WooCommerce stores them: null for both blank and zero. No Mizzey-specific meaning invented |
| Partial failure | Each translation is written in its own try/catch; a failure is logged to the WooCommerce log (`source: mizzey-cost-sync`) and the remaining translations still run |

With the fix, all 24 matrix cases pass (`evidence/wpml1-clean-with-hook.txt`).

It does not create a cost store, duplicate the native field, or add a general synchronisation framework.

### Side effect recorded

Saving a variation makes WooCommerce regenerate that variation's title from the parent name and its attributes
(variation data store `update()`). An Arabic variation authored in the WCML editor is renamed the first time any
save reaches it. This happens with WCML's own admin sync too, before and without this fix. Price, stock, SKU and
status are unchanged in every case, which the matrix checks.

## Open items after the pilot

| Id | Question | Owner |
|---|---|---|
| CX-01 | Which staff roles hold financial permission (AC-6). t09 facts attached: administrator and shop_manager today | Client, Stage 1 review |
| OD-12 | Cost basis and who enters it (AC-7), plus whether a deliberate zero cost must be distinguishable from an uncosted product | Client |
| Governance | Record WordPress 7.1.2 and MySQL 8.3.0 in `stack.lock.json` | Separate `internal:governance` PR |

## Guard Gate (T016)

- **test-guard** on the scenarios: one Rule 4 finding (t01 duplicated what every other scenario already proves).
  Fixed by folding the FR-001 check into `_bootstrap.php` and deleting t01. Rule 7 (testing framework behaviour) is
  deliberately waived by the project: proven native coverage, re-run on upgrades, is the deliverable (M-4, M-8).
- **woo-guard** on `CostTranslationSync.php`: two findings, both fixed. Rule 5, cost values were compared with float
  equality (now compared through `wc_format_decimal` at the store's precision). Rule 6, the feature flag was read
  through an invented filter name (now read through `FeaturesController::feature_is_enabled`, the API the probes
  verified). No order data is touched, no direct meta writes, no template overrides, no checkout code.
- The removed `wpml-config.xml` needed no code review.

## Not verified

- A human using wp-admin in a browser. The admin channel drives the real admin form and the real variations AJAX
  endpoint over HTTP, which is closer than a simulation, but it is not a browser session. That belongs to UAT.
- Order emails.
- Staging and production enablement of the feature flag (runbook, safeguard S-1).
- Other WooCommerce, WPML or WCML versions. Re-run this suite on any upgrade (`docs/stack-and-upgrades.md`).

## Three states

- **Workflow pilot**: complete. Spec, plan, tasks, analysis, implementation, guards, verification, all through Spec
  Kit, including a corrective investigation that overturned the first conclusion.
- **Technically verified**: AC-1 to AC-5 verified on a clean baseline, with the sync fix in place.
- **Contractually accepted**: **no**. AC-6 is pending CX-01 and AC-7 pending OD-12. Acceptance happens at the
  Stage 1 gate under MS-UAT-2026-027.

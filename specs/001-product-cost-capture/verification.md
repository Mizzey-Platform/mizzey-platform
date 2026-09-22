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
`evidence/final-suite.txt` and `.json`. 11 scenarios, 0 failed (t09 gives facts, not a verdict).

| Scenario | Criterion | Result | Key observation |
|---|---|---|---|
| t02 simple cost | AC-1 | PASS | 137.5 read back; 0 and blank both read back as no cost (native) |
| t03 variation cost | AC-1 | PASS | 40 and 70 kept per variation; additive flag defaults to false |
| t04 order-line snapshot | AC-2 | PASS | 200 at sale, 200 after the product cost changed to 999 |
| t07 CSV import | AC-4 | PASS | 111.25, 95, 40, 70 imported; the blank row imported with no cost |
| t08 exposure | AC-5 | PASS | No cost in visitor pages, Store API, REST v3, or the customer's order view |
| t09 staff visibility | AC-6 | FACT | administrator and shop_manager only; every other role 403 |
| t11 cost sync matrix | AC-3 | PASS | 24 cases: 2 translation methods x 2 product types x 5 channels, plus 4 creation cases |
| t12 identity | AC-3 | PASS | 7 cases: the copy reaches the WPML original's translations, the matching variation, and nothing else |
| t13 semantics and failure | AC-3 | PASS | 8 update steps with the write count each causes, a forced failure with its log and repair, re-entrancy |
| t14 side effects and Arabic content | AC-3, AC-5 | PASS | 4 cases: no authored field, relationship, unrelated field or customer-visible string moved |
| t15 entry points | AC-3 | PASS | 10 cases: the hook that carries the copy, recorded inside each channel's own request |

After the run: feature flag `no`, 0 products, 0 orders, no test users beyond the baseline administrator, no
temporary must-use plugin, no leftover import files, no scenario options, and no cost-sync log files or rows
(T015).

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

| | `_cogs_value` declared | admin-http, import-http | rest-http, crud-cli, crud-web |
|---|---|---|---|
| Without `wpml-config.xml` (`evidence/wpml1-clean-no-xml.txt`) | unset | pass | fail |
| With `wpml-config.xml` (`evidence/wpml1-clean-with-xml.txt`) | 1 (copy), locked by config | pass | fail |

The file changed WPML's settings and changed no outcome. It was removed (WPML-2 closed).

Two facts found in the review round explain that result completely. `_cogs_total_value` is the meta key WooCommerce
actually stores a product's cost in; `_cogs_value` is the name of the admin form field and the CRUD accessor, not a
stored key. So the file declared a field that does not exist in the database. And WPML's own downloaded
configuration already declares `_cogs_total_value` as copied, and locks it (`_cogs_total_value=1 locked=true` in the
baseline output). Configuration was never the missing piece: the event was.

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

## Review round, 22 September 2026: identity, semantics, side effects, entry points

Asked for before merge: prove the synchronisation reaches the right posts, behaves correctly on every kind of cost
change, leaves everything else alone, fails safely, and runs on the hook each channel actually fires. Four scenarios
were added, t12 to t15. They found one defect in the implementation and two errors in this folder's records.

### The defect, and the correction

t13 created a second English product and its translation in the same process and changed its cost. The Arabic copy
did not follow. The cause was how identity was read, not the feature. WPML answers `wpml_original_element_id` from
a cache of its own (`SitePress::get_original_element_translation`, cache group `original_element`), and inside a
single long-running process that had just created another translation it named an earlier product, so the sync saw
the original as "not an original" and declined to copy. At that same moment `wpml_get_element_translations`, which
the class already called for the list of translations, returned the correct rows, and the rows in
`icl_translations` were correct throughout (`evidence/wpml-identity-cache.txt`: the database rows, the stale answer,
and the same answer after `wp_cache_flush()`).

The correction is smaller than what it replaced. Identity and the translation list now come from one call to
`wpml_get_element_translations`: the element WPML flags as `original`, with no source language of its own, is the
one that copies outwards. The class no longer calls `wpml_original_element_id`, so the two answers cannot disagree.
t12 keeps a regression case (a pair created second in the same process, with no cache flush) and t13 exercises the
same condition.

Stated plainly: the failure mode was a copy that did not happen, never a copy to the wrong product, and a copy that
did not happen is repaired by the next save of the original (t13). A long-running import or migration script is the
realistic place to meet it, which matters for MIG-13; WCML's own synchronisation reads identity through the same
WPML caches.

### Two record corrections

- **The storage key.** WooCommerce 11.1.0 stores a product's or variation's own cost in post meta
  `_cogs_total_value` (`WC_Product_Data_Store_CPT` lines 513 and 813, confirmed against the database).
  `_cogs_value` is the admin form field and the CRUD accessor. The spec, plan, research and data model said
  `_cogs_value` was the stored key and are corrected. The class itself was never affected: it reads and writes
  through CRUD.
- **What WPML declares.** WPML's downloaded configuration already declares `_cogs_total_value` as copied and locks
  it. The earlier record said the cost field was undeclared. It was not: the copy simply runs on events that a
  cost-only save never fires.

### Identity (t12)

| Case | Result |
|---|---|
| The original and the translation, read as the class reads them: one trid, the English row flagged original with no source language, the Arabic row sourced from `en` | PASS |
| A simple product: the cost reaches its own translation, and the only products written are those two | PASS |
| Variations: changing the cost of variation S moves S in Arabic and leaves the L sibling untouched | PASS |
| A product with no translation: written once, nothing else touched | PASS |
| A translation that has been deleted: the English variation is still saved, nothing else is written, no error | PASS |
| A cost written directly on the translation does not reach the original, and the next save of the original restores the translation | PASS |
| A pair created second in the same process, the condition that exposed the defect above | PASS |
| Creating a translation when a cost already exists | covered by t11 part 1, both methods, both product types |

### Update semantics and failure handling (t13)

Each step records how many times the translation was actually written, so compare-before-write is measured.

| Step | Arabic value | Writes to the translation |
|---|---|---|
| First cost, 100 | 100 | 1 |
| Increase to 150 | 150 | 1 |
| Decrease to 80 | 80 | 1 |
| Saved again at 80 | 80 | 0 |
| Saved three more times at 80 | 80 | 0 |
| Cleared | none | 1 |
| Set again to 60 | 60 | 1 |
| Zero | none, as WooCommerce stores zero | 1 |

Failure: the save of one Arabic product was forced to throw. The English product was still saved, the unrelated
pair was still synchronised, the failure was logged to the WooCommerce log under `mizzey-cost-sync` naming both
products, and an ordinary save of the original afterwards repaired the gap. Nothing polls and nothing queues: the
repair happens on the next supported save, which is stated here because it is a limit of the design, not a feature.

Re-entrancy: one save of an original produced exactly two writes, the translation (nested inside the save of the
original) and the original, and stopped.

### Side effects, including what an Arabic customer reads (t14)

For both translation methods and both product types, the Arabic post was photographed before and after a cost
change on the English original. Nothing an editor authored moved: price, sale price, price index, SKU, stock,
stock management and status, attributes, post status, slug, content, excerpt, product terms, the WPML language and
trid, and a custom field written by hand to stand for unrelated third-party data. The cost fields moved, which is
the point.

What a customer reads was measured, not assumed: the product name and a variation's attribute summary in the
Arabic context, what the Store API serves for the Arabic product, and the name and meta an Arabic order records for
the line. All identical before and after, in Arabic where the translation was authored in Arabic, with no cost in
the Store API response.

The variation title reported earlier is explained. Measured at three points for a variation translated in the WCML
editor: `منتج 363` as WCML created it, `منتج 363 - S` after an ordinary save, and unchanged by the cost sync. The
regeneration is caused by any save, WCML's own included, and the name the customer reads was already the
regenerated form at every point, because WooCommerce composes it from the parent name and the attributes. It is a
stored title catching up with what was already displayed, not an Arabic content regression. WooCommerce also writes
its own bookkeeping fields on any CRUD save (product version, rating and review counters); those are recorded as
facts.

### The hook that carries the copy, per channel (t15)

Recorded inside the request that does the work, with a temporary recorder installed in the disposable runtime, for
a simple product and for a variation in each channel. Ten cases, all passing.

| Channel | Request context | Hook that carries the copy | Does `save_post` fire, so WPML and WCML can run? |
|---|---|---|---|
| wp-admin product form | admin | `woocommerce_update_product` | yes |
| wp-admin variations AJAX | admin, ajax | `woocommerce_update_product_variation`, after `woocommerce_ajax_save_product_variations` | yes |
| wp-admin CSV importer, simple | admin, ajax | `woocommerce_update_product` | no, and WCML's own importer hook fires instead |
| wp-admin CSV importer, variation | admin, ajax | `woocommerce_update_product_variation` | yes |
| REST API | rest | `woocommerce_update_product(_variation)` | no |
| WooCommerce CRUD under WP-CLI | cli | `woocommerce_update_product(_variation)` | no |
| Front-end application code, webhooks, cron | front | `woocommerce_update_product(_variation)` | no |

The three channels where `save_post` never fires are exactly the three that failed before this feature existed.

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

### Guard Gate, review round

- **woo-guard** on the corrected `CostTranslationSync.php`. No order data is read or written, so the HPOS rules do
  not apply (Rule 1); every write goes through a CRUD object and `save()`, so lookup tables and hooks stay correct
  (Rule 2); no checkout code (Rule 4); costs are compared through `wc_format_decimal` at the store's precision and
  no arithmetic is done on them (Rule 5); no cart or session is touched and the hook names are the ones WooCommerce
  11.1.0 fires, which t15 confirms in the admin, AJAX, REST, CLI and front-end contexts (Rule 6); no templates
  (Rule 7); no background jobs, and the absence of a queue is a recorded design decision rather than an oversight
  (Rule 8). One change came out of the pass: each row from WPML is cast to an object, so a filter that returned
  arrays could not turn a product save into a fatal error. Follow-up for later work, not for this feature: the
  plugin will need `FeaturesUtil::declare_compatibility` for `custom_order_tables` when it first touches orders.
- **test-guard** on t12 to t15. No mocks anywhere: real WordPress, WooCommerce, WPML, MySQL and real HTTP requests
  (Rules 2, 8, 9). The forced failure in t13 injects a fault through a real WooCommerce hook, which is the only way
  to exercise a translation that cannot be saved. Variants are data-driven rather than copied (Rule 3). Two
  deliberate exceptions, both recorded rather than waved through:
  - Rule 7, framework behaviour: t12 asserts the shape of WPML's own translation rows and t13 asserts WooCommerce's
    conversion of a zero cost. Both are the project's deliverable under M-4 and M-8, proven native coverage, and
    the zero conversion is a contractual fact raised under OD-12.
  - Rule 1, implementation detail: t15 asserts which hook fired. That is the point of the scenario, and the cost
    outcome is still asserted alongside it, so t11 and t15 cannot pass on the strength of the hook alone.
  The regression cases for the identity defect found in this round are marked as such in t12 and t13 (Rule 6).

## Not verified

- A human using wp-admin in a browser. The admin channel drives the real admin form and the real variations AJAX
  endpoint over HTTP, which is closer than a simulation, but it is not a browser session. That belongs to UAT.
- The rendered Arabic storefront page. WPML serves Arabic from `/ar/` URLs, which need the web server rewrite
  configuration that the disposable runtime does not have, and the id-based URL quietly serves the English
  translation instead. t14 measures the Arabic catalogue data through the Store API and the Arabic order line
  instead, and t08 covers public exposure of cost over HTTP. The rendered page belongs to UAT.
- Order emails.
- Staging and production enablement of the feature flag (runbook, safeguard S-1).
- Other WooCommerce, WPML or WCML versions. Re-run this suite on any upgrade (`docs/stack-and-upgrades.md`).

## Three states

- **Workflow pilot**: complete. Spec, plan, tasks, analysis, implementation, guards, verification, all through Spec
  Kit, including a corrective investigation that overturned the first conclusion.
- **Technically verified**: AC-1 to AC-5 verified on a clean baseline, with the sync fix in place.
- **Contractually accepted**: **no**. AC-6 is pending CX-01 and AC-7 pending OD-12. Acceptance happens at the
  Stage 1 gate under MS-UAT-2026-027.

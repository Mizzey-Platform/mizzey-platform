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
`evidence/final-suite.txt` and `.json`. 12 scenarios, 0 failed (t09 gives facts, not a verdict).

| Scenario | Criterion | Result | Key observation |
|---|---|---|---|
| t02 simple cost | AC-1 | PASS | 137.5 read back; 0 and blank both read back as no cost (native) |
| t03 variation cost | AC-1 | PASS | 40 and 70 kept per variation; additive flag defaults to false |
| t04 order-line snapshot | AC-2 | PASS | 200 at sale, 200 after the product cost changed to 999 |
| t07 CSV import | AC-4 | PASS | 111.25, 95, 40, 70 imported; the blank row imported with no cost |
| t08 exposure | AC-5 | PASS | No cost in visitor pages, Store API, REST v3, or the customer's order view |
| t09 staff visibility | AC-6 | FACT | administrator and shop_manager only; every other role 403 |
| t11 cost sync matrix | AC-3 | PASS | 24 cases: 2 translation methods x 2 product types x 5 channels, plus 4 creation cases |
| t12 identity | AC-3 | PASS | 8 cases: the copy reaches the WPML original's translations, the matching variation, and nothing else |
| t13 semantics and failure | AC-3 | PASS | 8 update steps with the write count each causes, a forced failure with its log and repair, re-entrancy |
| t14 side effects and Arabic content | AC-3, AC-5 | PASS | 4 cases: no authored field, relationship, unrelated field or customer-visible string moved |
| t15 entry points | AC-3 | PASS | 10 cases: the hook that carries the copy, recorded inside each channel's own request |
| t16 cost ownership | AC-3 | PASS | 10 cases: a cost written straight onto a translation is replaced by the original's |

After the run: feature flag `no`, 0 products, 0 orders, no test users beyond the baseline administrator, no
temporary must-use plugin, no leftover import files, no scenario options, and no cost-sync log files or rows
(T015).

Alongside the suite, on the final state of the branch: PHP lint clean across `mizzey-site` and `mizzey-theme`;
the discovery dataset gate clean; 126 discovery tests and 71 tooling tests passing; `tools/repo_checks.py` 0
problems; `tools/scope_trace.py` 0 problems over 39 changed files; `tools/house_rules.py` 0 problems over 983
added markdown lines; and `tools/run_trusted.py` passing all four of its checks, the trusted base copy and the
proposed copy of each checker.

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

## Second review round, 22 September 2026: cost ownership, and a broken assertion

Two findings came back from the review of the package. Both are answered below. Nothing else was changed: the
architecture, the identity model and the class stay as they were.

### 1. A cost written straight onto a translation

t12 had recorded, without treating it as a problem, that a programmatic write could move an Arabic product's cost
to 999 while the English original stayed at 100. That is a reachable path to a wrong Arabic order cost, so it was
investigated before anything was decided.

**What each channel did, before the correction** (probe on the clean baseline, writing 999 onto the Arabic
product of a pair whose English cost is 100):

| Channel | Arabic cost after | English cost | Cost recorded by an Arabic order |
|---|---|---|---|
| WooCommerce CRUD under WP-CLI | 999 | 100 | **999** |
| REST API | 999 | 100 | **999** |
| Front-end code, webhook, cron | 999 | 100 | **999** |
| wp-admin product form | 100, the write did not stick | 100 | 100 |
| Native CSV importer | 999, but so did the original | 999 | 999 |

Two facts decided the mechanism. First, **wp-admin already enforces the rule**: WooCommerce Multilingual replaces
the value from the original when it runs on `save_post`, so a cost typed onto a translated product never sticks.
The gap is only in the channels that do not fire `save_post`, which is the same root cause as the outward gap.
Second, **WooCommerce provides a filter at exactly the write point**, `woocommerce_save_product_cogs_value`, with
`woocommerce_save_product_cogs_is_additive_flag` beside it, and the probe confirmed both fire for products and for
variations in every channel.

**The policy: the original owns the cost, and a write to a translation is corrected from the original.** Whatever
value reaches the data store for a translation, the original's value is what gets stored. The attempt is written
to the WooCommerce log under `mizzey-cost-sync`, naming both products and both values, so it is not silent.
Rejecting the write with an exception was considered and rejected: it would break supported integrations with hard
failures over a value the caller should not be setting, and it would not match what wp-admin already does.
Two-way synchronisation was never on the table; it would destroy the ownership rule the contract needs.

Nothing is saved inside a filter, so there is no second write, no recursion and no other field touched. Inside the
outward copy the filter returns the same value it is given, so the two halves cannot fight.

**After the correction**, all five channels behave: the Arabic cost stays at the original's value and the Arabic
order records the original's cost. t16 holds ten cases, and t12 keeps the single-product version of the same case.

**Operations that are deliberately not supported, and how each is bounded:**

| Operation | Status |
|---|---|
| Setting a different cost on a translation, through any supported channel | Not possible: the original's value is stored instead, and the attempt is logged |
| Importing a different Arabic cost by CSV | Not possible: rows match by SKU, which a duplicate shares with its original, so the row cannot name one of the two. It reaches whichever the SKU lookup returns, and both outcomes leave the pair in agreement. See "One SKU, two products" below |
| Editing an Arabic variation's cost in wp-admin | Not reachable in this runtime, for a reason unrelated to cost: see the catalogue fact below |
| A direct database or `update_post_meta()` write | Not supported and not detectable, because it bypasses the WooCommerce data store where every filter lives. The project forbids direct meta writes on products (woo-guard rule 2), which is a code-review control, not a runtime one |

**A catalogue fact found on the way, outside this feature.** The Arabic parent of a translated variable product is
registered as a `simple` product with no variations attached, with both translation methods, even though each
Arabic variation exists and points at that parent. That is why wp-admin has no variations panel for it. It does
not affect cost correctness, because variations are WPML elements in their own right and are synchronised
individually, and every Arabic variation order in t11 and t16 records the right cost. It does need answering
before the Arabic storefront is accepted, and it belongs to the translation workflow (US-16-03), not here. The
observation was made with translations created from WP-CLI; whether the wp-admin translation flow builds the
Arabic parent correctly is not established.

### One SKU, two products: what a CSV import row actually addresses

Found while making t16 and t11 assert the importer honestly, and recorded because it changes an operational
instruction rather than any code.

A WPML duplicate is created with its original's SKU, so after translation one SKU belongs to two products.
`wc_get_product_id_by_sku()` answers with whichever of them the query returns first, and in this runtime that was
sometimes the Arabic duplicate. The importer matches rows by SKU, so an import row lands on one of the pair
without the operator choosing which:

| The row reaches | What happens | The pair afterwards |
|---|---|---|
| The English original | The cost changes, and `sync()` copies it to the translation | Agree, at the new value |
| The Arabic translation | `keepOriginalCost()` stores the original's value instead and logs the attempt, so the import appears to have done nothing to the cost | Agree, at the old value |

Neither outcome can produce a wrong Arabic order cost, which is why this is not a defect in the feature. It is a
limit on what an import can be relied on to do: a cost import after translations exist is not guaranteed to change
the cost. The tests assert it as it is, by resolving the SKU first (`Workflows::sku_target()`) and expecting the
outcome that follows, rather than assuming the row reaches the original.

**Operational note for MIG-13.** Import costs before creating translations, or verify that the SKU resolves to the
source-language product before relying on an import to change a cost. Changing a cost on the original through
wp-admin, REST or WP-CLI is unaffected and always flows outwards.

### 2. A test assertion that could never fail

The Store API check added to t14 in the previous round searched the response text for the cost as a number, using
a pattern that had been mangled into literal backspace characters. It could not match anything, so "cost absent"
was meaningless. The exposure evidence for AC-5 was never affected: t08 is the AC-5 scenario, and its detection
uses plain string comparison against several number formats plus a scan for `cogs` and `cost_of_goods` field
names, which was checked and is sound.

The check is rebuilt on field names rather than digits, because a price of 123.5 is legitimate and a cost of 123.5
is not, and only the field they sit in tells them apart. It walks the decoded response, reports any key whose name
contains `cogs`, `cost_of_goods` or `cost`, and reads WooCommerce's custom-field pairs, where the field name is a
value rather than a key. It also verifies that the response is the product that was asked for, by id.

A negative control runs beside it: a cost field and a `_cogs_total_value` custom field are planted in a copy of a
real response and the check must report both, a cost field nested three levels down must be reported, and the real
response must still come back clean. If the check ever breaks again, the control fails and the scenario fails.

t14 now also distinguishes the things the review asked to keep apart: a **WPML duplicate**, whose Arabic product
keeps the source-language name until somebody translates it, and is asserted to do so; a **separately authored
translation** through the WCML editor, asserted to carry the Arabic name; the **Arabic catalogue data**, read
through the Store API by product id; and the **rendered Arabic storefront page**, which this runtime cannot serve
and which is therefore not tested and not claimed.

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

### Guard Gate, second review round

- **woo-guard** on the ownership rule. The two new methods are filters: they read and return a value and save
  nothing, so there is no write to review, no recursion and no other field touched (Rules 1, 2, 8). The value
  returned is the original's own `get_cogs_value()`, compared through `wc_format_decimal` at store precision
  (Rule 5). The filter names are WooCommerce 11.1.0's own, and the probe confirmed both fire for products and for
  variations in every channel (Rule 6). One change came out of the pass: when another extension has already
  suppressed the write by returning `false`, that decision is now left alone instead of being overridden, since
  `false` means something else is storing the cost.
- **test-guard** on t16: real products, real HTTP, no mocks (Rules 2, 8, 9); ten cases driven from a channel and
  type table rather than copied (Rule 3); it is the regression test for a reviewed finding and is marked as such
  (Rule 6). Two notes rather than silent choices. The importer case asserts WooCommerce's own SKU matching, which
  is framework behaviour under Rule 7, and is kept because it is the evidence for a contractual limitation: an
  Arabic cost import is not possible and nobody should plan one. Its expectation is derived from the SKU lookup
  rather than assumed, which is also what stopped the ambiguity recorded above from being read as a defect: the
  assertion states what the channel does, not what it was hoped to do. And t12's single-product version of the
  same case overlaps t16's `crud-cli` row; it is kept because it carries the sequence t16 does not, a write on the
  translation followed by a later change on the original that still propagates.
- **woo-guard and test-guard on the final state of the branch**, re-read after the SKU correction: no new findings.
  The correction is confined to test expectations (`Workflows::sku_target()`, a thin wrapper over
  `wc_get_product_id_by_sku()`); no production code changed with it, and `CostTranslationSync` still writes only
  through CRUD, declares nothing about orders, and touches no template, checkout or order code.

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

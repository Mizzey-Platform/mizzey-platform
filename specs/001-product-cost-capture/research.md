# Research: Product cost captured from day one

Phase 0 for `specs/001-product-cost-capture`. Each item: decision, rationale, alternatives. Evidence dates are 2026-09-22
unless stated otherwise.

## R-1. Mechanism for storing product cost

- **Decision**: WooCommerce 11.1.0 native Cost of Goods Sold (ADR-0001, Proposed).
- **Rationale**: P-016 and P-017 (9 Sep) showed the field exists and the order line keeps the cost at sale. The
  source is stable: `CostOfGoodsSoldController` has `is_experimental => false`. Storage: post meta
  `_cogs_total_value` on the product or variation, the variation flag `_cogs_value_is_additive`, and order line
  meta `_cogs_total_value` (`WC_Product_Data_Store_CPT::read_product_data` and `update_post_meta_fields`, confirmed
  against the database). `_cogs_value` is the name of the admin form field and the CRUD accessor, not a stored key:
  the earlier records in this folder named it as the storage key and were wrong.
- **Alternatives**: a custom meta field. Rejected in ADR-0001: it duplicates native behaviour and loses the native
  order-line snapshot.

## R-2. How the feature is switched on

- **Decision**: the option `woocommerce_feature_cost_of_goods_sold_enabled = yes`, set per environment as
  configuration. It is recorded in the quickstart and the go-live runbook.
- **Rationale**: this is the native flag. The runtime currently reads `no` (the default).
- **Alternatives**: enabling it from `mizzey-site` code. Rejected: this is configuration, not behaviour, and FR-001
  needs it on before the first order, whatever the code does.

## R-3. Test harness

- **Decision**: WP-CLI integration scripts under `mizzey-site/tests/integration/`, one scenario per file, each
  printing a JSON verdict. A small runner, `mizzey-site/tests/integration/run.py`, runs them against the disposable
  runtime `../app/wp`, and `baseline/reset-runtime.sh` rebuilds that runtime to a known state first. Scenarios that
  must exercise a real request (wp-admin, the importer, REST, a front-end request) drive it over HTTP rather than
  simulating it in the CLI process.
- **Rationale**: `mizzey-site/` is an empty scaffold with no Composer, Pest or WordPress test library. Setting those
  up is test-infrastructure work outside this pilot. The discovery probes already prove the WP-CLI pattern works
  against real WordPress, WooCommerce and WPML. The files sit under `mizzey-site/tests/`, which the path policy
  classifies as site-tests, allowed for requirement PRs.
- **Alternatives**: Pest with the WordPress test suite (deferred as separate `internal:test-infrastructure` work);
  new probes under `discovery/` (that path class is tooling, which requirement PRs may not touch).

## R-4. Translation behaviour (WPML 4.9.7, WCML 5.5.7)

- **Finding, confirmed on clean baselines.** WPML's downloaded configuration already declares the cost field
  `_cogs_total_value` as copied, and locks it (baseline output: `_cogs_total_value=1 locked=true`). Configuration
  was therefore never missing. What matters is the event: WPML and WCML copy fields to translations on `save_post`,
  on the wp-admin variations AJAX save, and through WCML's importer hook, and WooCommerce does not fire `save_post`
  when a save changes only meta. So cost changes made through REST, WP-CLI or other code never reach the Arabic
  copy, whatever any configuration file says. Which hook each channel does fire is measured per channel in t15.
- **Decision.** Test every channel in its real request context (this matters: WCML registers its sync only for
  `is_admin()` or WP-CLI), on a scripted clean baseline, with translations created both ways.
- **Outcome.** A `wpml-config.xml` was tried first and removed: it changed WPML's settings and changed no outcome.
  It declared `_cogs_value`, which is not a stored key at all, which explains why it could not have helped. The gap
  is closed by `MizzeySite\Catalogue\CostTranslationSync`, justified by the failing cases. See verification.md.
- **Correction, 6 October 2026.** "Configuration was therefore never missing" holds for the update paths and not
  for the creation of a translation. The baselines this finding was confirmed on all held WPML's downloaded
  configuration, which was the only thing declaring `_cogs_total_value`. WooCommerce Multilingual copies a custom
  field to a new translation only when WPML holds a setting for it, so on a runtime without the download three of
  the four creation cases fail (t29, before the correction). The setting is now declared in
  `mizzey-site/wpml-config.xml`, with the stored key, and the four cases pass without the download.
- **Environment lesson.** A runtime that has been used for months is not a test baseline. The first conclusions in
  this pilot were wrong because of runtime drift and harness faults, not because of the product.
- **Identity, added in the review round.** WPML answers `wpml_original_element_id` from a cache of its own that can
  go stale inside one long-running process, while `wpml_get_element_translations` stays correct. Identity is
  therefore read from the translation rows, not from the cached answer (verification.md, review round).

## R-8. A cost written straight onto a translation

- **Finding.** Before this decision, a cost written onto the Arabic product through REST, WP-CLI or a front-end
  request stuck: the two language versions disagreed, and an Arabic order recorded a cost the original never had
  (probe across all five channels, both product types). wp-admin never allowed it, because WooCommerce
  Multilingual replaces the value from the original when it runs on `save_post`. The programmatic channels do not
  fire `save_post` for a cost-only save, which is the same root cause as the outward gap.
- **Decision.** Correct the value from the original, at WooCommerce's own write filters,
  `woocommerce_save_product_cogs_value` and `woocommerce_save_product_cogs_is_additive_flag`. Both fire for
  products and for variations, in every channel, at the point the value is about to be stored. The attempt is
  logged under `mizzey-cost-sync` so it is not silent.
- **Rationale.** It makes code behave exactly as wp-admin already does, which is the smallest rule that keeps the
  original canonical. Nothing is saved inside a filter, so nothing recurses and no second write happens; no other
  field is touched; and a divergent value cannot survive a save.
- **Alternatives.** Rejecting the write with an exception: breaks supported integrations and importers with hard
  failures, for a value the caller has no business setting. Two-way synchronisation: breaks the ownership rule the
  contract needs, and makes the authoritative cost ambiguous. Doing nothing: leaves a reachable path to a wrong
  Arabic order cost, which is a data-integrity defect, not a preference.

## R-5. Public and customer exposure

- **Decision**: test the Store API product endpoints as a visitor, REST v3 products as a visitor and as a customer,
  and the rendered product page HTML.
- **Rationale**: source reading found no cost in the Store API, and REST v3 gated by `read_private_posts`. Runtime
  confirmation is still missing.

## R-6. Import

- **Decision**: test the native WooCommerce CSV importer with a fixture that has a "Cost of goods" column, including
  a blank-cost row.
- **Rationale**: MIG-13 is a one-off Developer-run migration. The native importer maps the column only when the
  feature is on (`mappings/default.php:85`).

## R-7. Tested versions

- **Runtime**: WordPress 7.1, WooCommerce 11.1.0, WPML 4.9.7, WPML String Translation 3.5.4, WCML 5.5.7, PHP 8.3.6,
  HPOS on, languages `en` (default) and `ar`.
- **New fact**: the local database is **MySQL 8.3.0** (`SELECT VERSION()`). `stack.lock.json` lists the database as
  unverified. Updating it is a governance-control change, so it is not made in this requirement PR. It is recorded
  for a separate `internal:governance` PR.

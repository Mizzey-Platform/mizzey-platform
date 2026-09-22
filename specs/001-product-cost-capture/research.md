# Research: Product cost captured from day one

Phase 0 for `specs/001-product-cost-capture`. Each item: decision, rationale, alternatives. Evidence dates are 2026-09-22
unless stated otherwise.

## R-1. Mechanism for storing product cost

- **Decision**: WooCommerce 11.1.0 native Cost of Goods Sold (ADR-0001, Proposed).
- **Rationale**: P-016 and P-017 (9 Sep) showed the field exists and the order line keeps the cost at sale. The
  source is stable: `CostOfGoodsSoldController` has `is_experimental => false`. Storage: product meta
  `_cogs_value`, variation flag `_cogs_value_is_additive`, order line meta `_cogs_total_value` (grep of
  `includes/` and `src/`).
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
  runtime `../app/wp`.
- **Rationale**: `mizzey-site/` is an empty scaffold with no Composer, Pest or WordPress test library. Setting those
  up is test-infrastructure work outside this pilot. The discovery probes already prove the WP-CLI pattern works
  against real WordPress, WooCommerce and WPML. The files sit under `mizzey-site/tests/`, which the path policy
  classifies as site-tests, allowed for requirement PRs.
- **Alternatives**: Pest with the WordPress test suite (deferred as separate `internal:test-infrastructure` work);
  new probes under `discovery/` (that path class is tooling, which requirement PRs may not touch).

## R-4. Translation behaviour (WPML 4.9.7, WCML 5.5.7)

- **Finding**: neither WCML nor WooCommerce ships a `wpml-config.xml` entry for `_cogs_value`. WPML holds no setting
  for it on this runtime (`custom_fields_translation['_cogs_value']` is unset). Behaviour on Arabic copies is
  therefore not declared anywhere, and must be tested.
- **Decision**: test two creation paths, a WPML duplicate and a separately authored linked translation, plus an order
  placed on the Arabic product.
- **If a gap is found**: the smallest fix is to declare `_cogs_value` as a copied custom field in a
  `wpml-config.xml` shipped with `mizzey-site`. That is configuration read by WPML, not PHP code. It is justified
  only by a failing AC-3 test.

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

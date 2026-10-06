# Quickstart: validate product cost capture

## Prerequisites

- The disposable runtime is built: `node tools/corex-sync.mjs`, with the versions in `stack.lock.json`.
- WP-CLI on the PATH, Python 3.10, and the local site reachable over HTTP (the admin, importer, REST and front-end
  scenarios drive real requests).
- Never run any of this against production.

## A clean baseline (recommended before any comparison)

A runtime that has been used for other work is not a baseline: WPML settings, its downloaded configuration and
WooCommerce options all drift, and the first version of this pilot drew wrong conclusions from that. Rebuild it:

```bash
MIZZEY_CONFIRM_RESET=yes sh mizzey-site/tests/integration/baseline/reset-runtime.sh ../app/wp
```

**This drops every table in the runtime database and reinstalls WordPress.** It refuses to run unless the path is
the disposable runtime, the database is local, and the confirmation variable is set. Take a backup first
(`wp db export`). It installs and activates the plugins, configures WooCommerce (Egypt, EGP, HPOS), sets up WPML
(English default, Arabic active, products and variations translatable), and loads the wp-admin Plugins page once,
which is when WPML parses plugin `wpml-config.xml` files.

## Configuration (the deliverable for FR-001)

```bash
wp --path=../app/wp option update woocommerce_feature_cost_of_goods_sold_enabled yes
```

The same setting goes into the go-live runbook for staging and production, before the first live order (safeguard
S-1 adds a scripted check).

## Run the scenarios

```bash
python mizzey-site/tests/integration/run.py --wp ../app/wp
```

Each scenario prints one JSON line (`test`, `criterion`, `pass`, `observed`), and the runner exits non-zero if a
contract scenario fails. Scripts clean up their fixtures, restore the feature flag, and leave no temporary files.

## Expected outcome

| Scenario | Expect |
|---|---|
| t02, t03 | pass. Every scenario also checks FR-001 enablement |
| t04 | pass: the order keeps the cost it was sold at |
| t07 | pass: import carries cost; a blank stays blank |
| t08 | pass: no cost in any visitor or customer response |
| t09 | facts only, for the CX-01 discussion |
| t11 | pass: 24 cases (2 translation methods x 2 product types x 5 channels, plus 4 creation cases) |
| t12 | pass: identity. The copy reaches the WPML original's translations, the matching variation, and nothing else |
| t13 | pass: every kind of cost change, the number of writes each causes, a forced failure, its repair, and re-entrancy |
| t14 | pass: nothing else on the Arabic post moves, including what the customer reads. Variation titles are reported as facts, because WooCommerce regenerates them on any save |
| t15 | pass: the hook that carries the copy in each channel, recorded inside the request that does the work |
| t16 | pass: a cost written straight onto a translation is replaced by the original's, in every channel |
| t29 | pass: a cost on a product before its Arabic record is created reaches the Arabic record, four creation cases. It tests the site's own declaration only on the baseline built without WPML's download (below); its first note says which baseline it ran on |

## The baseline without WPML's downloaded configuration

WPML downloads a configuration from its publisher's host when a plugin is activated, and a host that cannot reach
it gets different settings. To build that baseline on purpose:

```bash
MIZZEY_WPML_REMOTE_CONFIG=off MIZZEY_CONFIRM_RESET=yes sh mizzey-site/tests/integration/baseline/reset-runtime.sh ../app/wp
```

The reset ends with `WPML remote configuration: off (not downloaded)`. A reset without the variable restores the
ordinary baseline. Staging's reset copies development's download, so reset development the ordinary way before
resetting staging.
